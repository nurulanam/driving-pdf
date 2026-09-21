<?php
/**
 * Sends the permit-ready email when an order's wait is up.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Watches orders for payment and schedules the permit-ready email.
 *
 * Documents themselves are never queued — each is rendered when its link is
 * opened — but the email announcing them has to be, because it goes out at a
 * moment nothing else brings about: the end of the release wait. So this is the
 * one scheduled job the plugin keeps, and all it does is send a message.
 *
 * It fires once per order. The guard is a timestamp on the order rather than the
 * scheduler's own state, because a job can be lost — a queue cleared, a cron
 * that never ran — and an order that then reaches a status change would send a
 * second copy.
 */
final class Release_Notifier {

	/**
	 * Scheduled action that sends the email.
	 */
	public const HOOK = 'idta_pdf_send_release_email';

	/**
	 * Daily sweep that catches orders the scheduled send missed.
	 */
	public const SWEEP_HOOK = 'idta_pdf_release_sweep';

	/**
	 * How far back the sweep looks.
	 */
	private const SWEEP_WINDOW_DAYS = 14;

	/**
	 * Order meta recording when the permit-ready email was sent.
	 *
	 * Declared here rather than on Release_Email, which is where it belongs
	 * conceptually, because Release_Email extends WC_Email: naming a constant on
	 * it loads the file, and the file cannot be loaded before WooCommerce has
	 * defined its parent. Release_Schedule reads this key on the front end, long
	 * before anything has asked WooCommerce for its mailer, so the key has to
	 * live somewhere with no parent of its own.
	 */
	public const SENT_META = '_idta_pdf_permit_email_sent';

	/**
	 * Order meta counting how many times sending has been attempted.
	 */
	public const ATTEMPTS_META = '_idta_pdf_permit_email_attempts';

	/**
	 * How many times to try before giving up on an order.
	 */
	private const MAX_ATTEMPTS = 3;

	/**
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

	/**
	 * The Action Scheduler action currently being run, when there is one.
	 *
	 * @var int|null
	 */
	private ?int $running_action = null;

	/**
	 * Constructor.
	 *
	 * @param Release_Schedule $releases Release rules.
	 */
	public function __construct( Release_Schedule $releases ) {
		$this->releases = $releases;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		// Payment is what starts the wait, so it is the moment worth watching.
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_schedule' ), 20, 1 );

		/*
		 * And every status change afterwards, which covers an order an operator
		 * marks paid by hand — that fires no payment_complete — and an order
		 * that only later reaches a status the store releases documents for.
		 */
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_schedule_on_status' ), 20, 4 );

		// Both the Action Scheduler and the WP-Cron signatures.
		add_action( self::HOOK, array( $this, 'send' ), 10, 1 );

		/*
		 * A safety net under the scheduled send. Everything else here reacts to
		 * an event — payment, a status change — so an order whose queued action
		 * is lost has nothing left to bring it back: the queue can be cleared, a
		 * migration can drop it, a host can kill the run. The order would then
		 * sit released and unannounced indefinitely, and nobody would know.
		 *
		 * WP-Cron rather than a recurring action, because asking Action
		 * Scheduler whether the sweep is scheduled costs a query on every
		 * request, while wp_next_scheduled() reads an option that is already
		 * loaded. The sweep itself still queues through Action Scheduler.
		 */
		add_action( self::SWEEP_HOOK, array( $this, 'sweep' ) );

		if ( ! wp_next_scheduled( self::SWEEP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::SWEEP_HOOK );
		}

		/*
		 * Note which action is running, so send() can write what it did into
		 * that action's own log. Without it the Scheduled Actions screen says
		 * only that the action completed, which is equally true of an email
		 * that went out and one that was skipped.
		 */
		add_action( 'action_scheduler_before_execute', array( $this, 'note_running_action' ), 10, 1 );
		add_action( 'action_scheduler_after_execute', array( $this, 'forget_running_action' ), 10, 1 );

		/*
		 * Record the send wherever it came from — the scheduled run, the button
		 * on the order screen, or WooCommerce's own "Resend order emails".
		 * Recording it in send() alone meant a manual send left the order still
		 * marked as never notified, so it kept its failed-attempt count and its
		 * release stayed revocable.
		 */
		add_action( 'woocommerce_email_sent', array( $this, 'record_sent' ), 10, 3 );

		add_filter( 'woocommerce_email_classes', array( $this, 'register_email' ) );
		add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'offer_resend' ) );
	}

	/**
	 * Add the email to WooCommerce's mailer.
	 *
	 * @param mixed $emails Email instances, keyed by class name.
	 *
	 * @return array<string,\WC_Email>
	 */
	public function register_email( $emails ): array {
		$emails = is_array( $emails ) ? $emails : array();

		$plugin = plugin();

		$emails['IDTA_PDF_Release'] = new Release_Email(
			$plugin->generator(),
			new Download_Handler( $plugin->generator(), $plugin->releases() )
		);

		return $emails;
	}

	/**
	 * Offer this email in the order screen's "Resend order emails" action.
	 *
	 * Support needs a way to send it again — a customer who deleted the email,
	 * or an address corrected after the fact — without waiting for another
	 * release that will never come.
	 *
	 * @param mixed $available Email IDs offered.
	 *
	 * @return array<int,string>
	 */
	public function offer_resend( $available ): array {
		$available = is_array( $available ) ? $available : array();

		$available[] = 'idta_pdf_release';

		return $available;
	}

	/**
	 * Note a successful send on the order.
	 *
	 * @param mixed  $success  Whether wp_mail() accepted the message.
	 * @param string $email_id Email type ID.
	 * @param mixed  $email    The email instance.
	 */
	public function record_sent( $success, $email_id = '', $email = null ): void {
		if ( ! $success || 'idta_pdf_release' !== $email_id ) {
			return;
		}

		$order = ( $email instanceof \WC_Email ) ? $email->object : null;

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$order->update_meta_data( self::SENT_META, time() );

		// A success clears the failures: whatever was wrong — an SMTP password,
		// a rejected sender — has been put right, and the count exists only to
		// stop the scheduled run pestering a broken mail stack.
		$order->delete_meta_data( self::ATTEMPTS_META );
		$order->save_meta_data();
	}

	/**
	 * Send the email now, whatever the release rules say.
	 *
	 * For the button on the order screen: an operator asking for this has
	 * already decided, and the usual reason is that the scheduled send failed
	 * for something outside the order — a mail server that was misconfigured at
	 * the time. None of the guards in send() apply.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool Whether the mail was accepted for delivery.
	 */
	public function dispatch( \WC_Order $order ): bool {
		$email = $this->email();

		return null !== $email && $email->trigger( $order->get_id(), $order );
	}

	/**
	 * The registered permit-ready email, when WooCommerce has one.
	 *
	 * @return Release_Email|null
	 */
	private function email(): ?Release_Email {
		/*
		 * Stepped through rather than chained. This runs from a scheduled job,
		 * and a fatal there does not fail one order — it kills the queue pass,
		 * taking every unrelated action in the batch with it. Returning null is
		 * a missed email; dereferencing something absent is an outage.
		 */
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();

		if ( ! is_object( $woocommerce ) || ! method_exists( $woocommerce, 'mailer' ) ) {
			return null;
		}

		$mailer = $woocommerce->mailer();

		if ( ! is_object( $mailer ) || ! method_exists( $mailer, 'get_emails' ) ) {
			return null;
		}

		$email = $mailer->get_emails()['IDTA_PDF_Release'] ?? null;

		return $email instanceof Release_Email ? $email : null;
	}

	/**
	 * Remember the action being executed.
	 *
	 * Recorded for every action, not only this plugin's: the id is only ever
	 * read from inside send(), which nothing but this plugin's own action runs.
	 *
	 * @param int|string $action_id Action ID.
	 */
	public function note_running_action( $action_id ): void {
		$this->running_action = absint( $action_id );
	}

	/**
	 * Forget it again.
	 *
	 * @param int|string $action_id Action ID.
	 */
	public function forget_running_action( $action_id ): void {
		unset( $action_id );

		$this->running_action = null;
	}

	/**
	 * Write a line into the running action's log.
	 *
	 * Shown in the Log column of WooCommerce → Status → Scheduled Actions,
	 * against the action it belongs to, so each order's outcome can be read
	 * individually rather than inferred from the batch.
	 *
	 * Does nothing when there is no action to attach to — an order whose wait
	 * had already passed is emailed there and then, without one — and nothing
	 * when Action Scheduler is not present at all.
	 *
	 * @param string $message What happened.
	 */
	private function log_action( string $message ): void {
		if ( null === $this->running_action || 0 === $this->running_action ) {
			return;
		}

		if ( ! class_exists( '\ActionScheduler' ) ) {
			return;
		}

		\ActionScheduler::logger()->log( $this->running_action, $message );
	}

	/**
	 * Re-offer every recently paid order to the scheduler.
	 *
	 * maybe_schedule() decides each one on its merits and is cheap for an order
	 * with nothing to do, so this needs no state of its own: an order already
	 * told, already queued, or not yet due is left exactly as it is.
	 *
	 * Bounded deliberately. A window of a fortnight and a batch of fifty keeps
	 * the sweep small on a busy store, and an order older than that has been
	 * missed long enough that someone should look at it rather than have it
	 * quietly appear.
	 */
	public function sweep(): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$statuses = function_exists( 'wc_get_is_paid_statuses' )
			? (array) wc_get_is_paid_statuses()
			: array( 'processing', 'completed' );

		$orders = wc_get_orders(
			array(
				'limit'     => 50,
				'status'    => $statuses,
				'date_paid' => '>' . ( time() - self::SWEEP_WINDOW_DAYS * DAY_IN_SECONDS ),
				'orderby'   => 'date',
				'order'     => 'ASC',
				'return'    => 'objects',
			)
		);

		foreach ( (array) $orders as $order ) {
			if ( $order instanceof \WC_Order ) {
				$this->maybe_schedule( $order->get_id() );
			}
		}
	}

	/**
	 * Queue the email from a status change.
	 *
	 * @param int      $order_id Order ID.
	 * @param string   $from     Previous status.
	 * @param string   $to       New status.
	 * @param mixed    $order    Order object.
	 */
	public function maybe_schedule_on_status( $order_id, $from = '', $to = '', $order = null ): void {
		unset( $from, $to, $order );

		$this->maybe_schedule( $order_id );
	}

	/**
	 * Queue the email for an order, or send it if the wait has already passed.
	 *
	 * @param int $order_id Order ID.
	 */
	public function maybe_schedule( $order_id ): void {
		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof \WC_Order || ! Order_Data::has_data( $order ) ) {
			return;
		}

		if ( $this->already_sent( $order ) ) {
			return;
		}

		$released_at = $this->releases->released_at( $order );

		// Not paid yet: there is nothing to time the wait from. A later payment
		// or status change brings us back here.
		if ( 0 === $released_at || ! $this->releases->status_allows( $order ) ) {
			return;
		}

		if ( $released_at <= time() ) {
			$this->send( $order->get_id() );

			return;
		}

		if ( $this->is_queued( $order->get_id() ) ) {
			return;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( $released_at, self::HOOK, array( 'order_id' => $order->get_id() ), 'idta-pdf' );

			return;
		}

		// WP-Cron only runs on a visit, so a quiet site sends the email on the
		// first request after the wait rather than exactly on it.
		wp_schedule_single_event( $released_at, self::HOOK, array( $order->get_id() ) );
	}

	/**
	 * Send the email.
	 *
	 * Accepts both the Action Scheduler named argument and the WP-Cron
	 * positional argument.
	 *
	 * @param int|array<string,mixed> $order_id Order ID or argument bag.
	 */
	public function send( $order_id ): void {
		if ( is_array( $order_id ) ) {
			$order_id = $order_id['order_id'] ?? 0;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof \WC_Order ) {
			/* translators: %d: order ID. */
			$this->log_action( sprintf( __( 'Order %d could not be loaded; nothing sent.', 'idta-pdf' ), absint( $order_id ) ) );

			return;
		}

		if ( $this->already_sent( $order ) ) {
			$this->log_action(
				sprintf(
					/* translators: %s: order number. */
					__( 'Order %s has already been told, or has used all its attempts; nothing sent.', 'idta-pdf' ),
					$order->get_order_number()
				)
			);

			return;
		}

		/*
		 * Re-checked at the moment of sending, not just when it was queued: an
		 * order refunded or put on hold during the wait must not be told its
		 * permit is ready.
		 */
		if ( ! $this->releases->is_released( $order ) ) {
			$this->log_action(
				sprintf(
					/* translators: 1: order number, 2: the reason it is held back. */
					__( 'Order %1$s is no longer released, so nothing was sent. %2$s', 'idta-pdf' ),
					$order->get_order_number(),
					$this->releases->reason( $order )
				)
			);

			return;
		}

		$email = $this->email();

		if ( null === $email ) {
			$this->log_action( __( 'The permit-ready email is not available, so nothing was sent.', 'idta-pdf' ) );

			return;
		}

		/*
		 * The attempt is recorded before the send and the success after it, so
		 * the two are separate facts.
		 *
		 * Recording the attempt first is what makes a hard failure survivable:
		 * if the send dies outright — a fatal somewhere in the mail stack, a
		 * host that kills the request — this order has still used one of its
		 * tries rather than looping forever on the next status change.
		 *
		 * Recording success only on success is what stops a broken send from
		 * silently marking every order as notified. An earlier version marked
		 * the order before sending, to guard against a timeout delivering the
		 * mail twice, and the cost of that trade was steep: when sending broke
		 * for an unrelated reason, every order was flagged as told and none
		 * could ever be retried. A rare duplicate is the lesser problem, and
		 * MAX_ATTEMPTS caps it.
		 */
		$attempts = (int) $order->get_meta( self::ATTEMPTS_META, true ) + 1;

		$order->update_meta_data( self::ATTEMPTS_META, $attempts );
		$order->save_meta_data();

		$sent = $email->trigger( $order->get_id(), $order );

		if ( $sent ) {
			// Recorded on the order by record_sent(), which listens for the
			// send itself and so catches a manual one too.
			$this->log_action(
				sprintf(
					/* translators: 1: order number, 2: recipient email address. */
					__( 'Permit-ready email for order %1$s sent to %2$s.', 'idta-pdf' ),
					$order->get_order_number(),
					$order->get_billing_email()
				)
			);

			return;
		}

		$this->log_action(
			sprintf(
				/* translators: 1: order number, 2: attempt number, 3: maximum attempts. */
				__( 'Permit-ready email for order %1$s failed to send (attempt %2$d of %3$d).', 'idta-pdf' ),
				$order->get_order_number(),
				$attempts,
				self::MAX_ATTEMPTS
			)
		);

		$order->add_order_note(
			sprintf(
				/* translators: 1: attempt number, 2: maximum attempts. */
				__( 'The permit-ready email could not be sent (attempt %1$d of %2$d).', 'idta-pdf' ),
				$attempts,
				self::MAX_ATTEMPTS
			)
		);
	}

	/**
	 * Whether this order should be left alone.
	 *
	 * Either it has been told, or it has been tried enough times: a store whose
	 * mail is misconfigured should not have every status change queue another
	 * doomed attempt. An operator can still send it by hand from the order
	 * screen's "Resend order emails", which goes straight to the email and does
	 * not consult any of this.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	private function already_sent( \WC_Order $order ): bool {
		if ( '' !== (string) $order->get_meta( self::SENT_META, true ) ) {
			return true;
		}

		return (int) $order->get_meta( self::ATTEMPTS_META, true ) >= self::MAX_ATTEMPTS;
	}

	/**
	 * Whether the email is already queued for an order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return bool
	 */
	private function is_queued( int $order_id ): bool {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return as_has_scheduled_action( self::HOOK, array( 'order_id' => $order_id ), 'idta-pdf' );
		}

		return (bool) wp_next_scheduled( self::HOOK, array( $order_id ) );
	}
}
