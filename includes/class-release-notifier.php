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
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

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

		if ( ! $order instanceof \WC_Order || $this->already_sent( $order ) ) {
			return;
		}

		/*
		 * Re-checked at the moment of sending, not just when it was queued: an
		 * order refunded or put on hold during the wait must not be told its
		 * permit is ready.
		 */
		if ( ! $this->releases->is_released( $order ) ) {
			return;
		}

		$mailer = function_exists( 'WC' ) ? WC()->mailer() : null;

		if ( null === $mailer ) {
			return;
		}

		$emails = $mailer->get_emails();
		$email  = $emails['IDTA_PDF_Release'] ?? null;

		if ( ! $email instanceof Release_Email ) {
			return;
		}

		/*
		 * Marked before sending, not after. A mailer that throws or times out
		 * mid-send can still have delivered the message, and a duplicate "your
		 * permit is ready" is worse than a missing one the customer can ask for
		 * — the links are on their account page and the order screen regardless.
		 */
		$order->update_meta_data( Release_Email::SENT_META, time() );
		$order->save_meta_data();

		$email->trigger( $order->get_id(), $order );
	}

	/**
	 * Whether this order has already been told.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	private function already_sent( \WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( Release_Email::SENT_META, true );
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
