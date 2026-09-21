<?php
/**
 * Order screen integration.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a document panel to the order edit screen.
 *
 * There is nothing to generate here, and nothing to rebuild. A document is
 * rendered when its link is followed, so every link in this panel produces the
 * order as it stands at the moment it is clicked. What the panel does have to
 * say is when the customer can fetch the same thing, since an operator's links
 * work immediately and the customer's do not.
 */
final class Order_Admin {

	/**
	 * admin-post action behind the "send the email now" button.
	 */
	private const SEND_ACTION = 'idta_pdf_send_permit_email';

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

	/**
	 * Download URL builder.
	 *
	 * @var Download_Handler
	 */
	private Download_Handler $downloads;

	/**
	 * Permit-ready email.
	 *
	 * @var Release_Notifier
	 */
	private Release_Notifier $notifier;

	/**
	 * Constructor.
	 *
	 * @param Generator        $generator Document generator.
	 * @param Release_Schedule $releases  Release rules.
	 * @param Download_Handler $downloads Download URL builder.
	 * @param Release_Notifier $notifier  Permit-ready email.
	 */
	public function __construct( Generator $generator, Release_Schedule $releases, Download_Handler $downloads, Release_Notifier $notifier ) {
		$this->generator = $generator;
		$this->releases  = $releases;
		$this->downloads = $downloads;
		$this->notifier  = $notifier;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 30, 2 );
		add_action( 'admin_post_' . self::SEND_ACTION, array( $this, 'handle_send_now' ) );
		add_action( 'admin_notices', array( $this, 'render_send_notice' ) );
	}

	/**
	 * Document labels, shared with the orders list column.
	 *
	 * @return array<string,string>
	 */
	public static function labels(): array {
		return array(
			'booklet'    => __( 'Permit booklet (A4)', 'idta-pdf' ),
			'card'       => __( 'Permit card (85.6 × 53.98 mm)', 'idta-pdf' ),
			'print-copy' => __( 'Permit print (A5)', 'idta-pdf' ),
			// Offered only where the server can rasterise a PDF.
			Card_Bitmap::FRONT_SLUG => __( 'Card front (BMP, 300 dpi)', 'idta-pdf' ),
		);
	}

	/**
	 * Register the panel on the order edit screen.
	 *
	 * @param string               $screen_id Screen ID.
	 * @param \WP_Post|\WC_Order|null $post   Post or order being edited.
	 */
	public function add_meta_box( $screen_id, $post = null ): void {
		$order = $this->resolve_order( $post );

		if ( ! $order instanceof \WC_Order || ! Order_Data::has_data( $order ) ) {
			return;
		}

		add_meta_box(
			'idta-pdf-documents',
			__( 'IDP Documents', 'idta-pdf' ),
			array( $this, 'render_meta_box' ),
			$screen_id,
			'side',
			'default'
		);
	}

	/**
	 * Render the panel.
	 *
	 * @param \WP_Post|\WC_Order $post Post or order being edited.
	 */
	public function render_meta_box( $post ): void {
		$order = $this->resolve_order( $post );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$labels = self::labels();
		$slugs  = $this->generator->offered_slugs( $order );
		$error  = $order->get_meta( Generator::ERROR_META, true );

		echo '<ul style="margin:0 0 12px;">';

		if ( array() === $slugs ) {
			printf(
				'<li><em>%s</em></li>',
				esc_html__( 'This order does not request any IDP document.', 'idta-pdf' )
			);
		}

		foreach ( $slugs as $slug ) {
			printf(
				'<li style="margin-bottom:6px;"><a href="%1$s" target="_blank" rel="noopener">%2$s</a></li>',
				esc_url( $this->downloads->admin_url( $order, $slug ) ),
				esc_html( $labels[ $slug ] ?? $slug )
			);
		}

		echo '</ul>';

		$this->render_release( $order );
		$this->render_email_state( $order );
		$this->render_send_button( $order );

		if ( is_string( $error ) && '' !== $error ) {
			printf(
				'<p style="color:#b32d2e;"><strong>%1$s</strong><br><small>%2$s</small></p>',
				esc_html__( 'Last error', 'idta-pdf' ),
				esc_html( $error )
			);
		}

		printf(
			'<p class="description" style="margin:0;">%s</p>',
			esc_html__( 'Documents are produced when a link is opened, so these are always up to date and nothing is stored on the server.', 'idta-pdf' )
		);
	}

	/**
	 * Say whether the customer can fetch their permit yet.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render_release( \WC_Order $order ): void {
		if ( $this->releases->is_released( $order ) ) {
			$kind = $this->releases->is_rush( $order )
				? __( 'Rush order.', 'idta-pdf' )
				: __( 'Standard order.', 'idta-pdf' );

			// Said plainly, because it is the thing an operator most needs to
			// know before changing anything: this one cannot be taken back.
			if ( $this->releases->has_been_notified( $order ) ) {
				$kind .= ' ' . __( 'The customer has their links, so this stays released.', 'idta-pdf' );
			}

			printf(
				'<p style="margin:0 0 10px;"><span style="color:#00622b;font-weight:600;">%1$s</span><br><small>%2$s</small></p>',
				esc_html__( 'Released to the customer', 'idta-pdf' ),
				esc_html( $kind )
			);

			return;
		}

		printf(
			'<p style="margin:0 0 10px;"><span style="color:#8a5700;font-weight:600;">%1$s</span><br><small>%2$s</small><br><small>%3$s</small></p>',
			esc_html__( 'Not yet released to the customer', 'idta-pdf' ),
			esc_html( $this->releases->reason( $order ) ),
			esc_html__( 'Your own links above work now.', 'idta-pdf' )
		);
	}

	/**
	 * Say what became of the permit-ready email.
	 *
	 * Sending happens out of sight, on a schedule, so without this the only
	 * evidence of a failure is the customer saying they never received it.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render_email_state( \WC_Order $order ): void {
		$sent = (string) $order->get_meta( Release_Notifier::SENT_META, true );

		if ( '' !== $sent ) {
			printf(
				'<p style="margin:0 0 10px;"><small>%s</small></p>',
				esc_html(
					sprintf(
						/* translators: %s: a date and time. */
						__( 'Permit email sent %s.', 'idta-pdf' ),
						(string) wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $sent )
					)
				)
			);

			return;
		}

		$attempts = (int) $order->get_meta( Release_Notifier::ATTEMPTS_META, true );

		if ( $attempts > 0 ) {
			printf(
				'<p style="margin:0 0 10px;color:#b32d2e;"><small>%s</small></p>',
				esc_html(
					sprintf(
						/* translators: %d: number of attempts. */
						_n(
							'Permit email failed after %d attempt. Use "Resend order emails" to try again.',
							'Permit email failed after %d attempts. Use "Resend order emails" to try again.',
							$attempts,
							'idta-pdf'
						),
						$attempts
					)
				)
			);

			return;
		}

		printf(
			'<p style="margin:0 0 10px;"><small>%s</small></p>',
			esc_html(
				$this->releases->is_released( $order )
					? __( 'Permit email not sent yet.', 'idta-pdf' )
					: __( 'Permit email goes out when the order is released.', 'idta-pdf' )
			)
		);
	}

	/**
	 * The button that sends the permit-ready email there and then.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render_send_button( \WC_Order $order ): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$sent = $this->releases->has_been_notified( $order );

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => self::SEND_ACTION,
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			self::SEND_ACTION . '-' . $order->get_id()
		);

		printf(
			'<p style="margin:0 0 10px;"><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( $url ),
			esc_html(
				$sent
					? __( 'Send permit email again', 'idta-pdf' )
					: __( 'Send permit email now', 'idta-pdf' )
			)
		);

		if ( ! $sent ) {
			printf(
				'<p class="description" style="margin:0 0 10px;"><small>%s</small></p>',
				esc_html__( 'Sends immediately, whatever the wait says, and releases the order to the customer for good.', 'idta-pdf' )
			);
		}
	}

	/**
	 * Send the email on request from the order screen.
	 */
	public function handle_send_now(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( (string) $_GET['order_id'] ) ) : 0;

		check_admin_referer( self::SEND_ACTION . '-' . $order_id );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to send this email.', 'idta-pdf' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'idta-pdf' ), '', array( 'response' => 404 ) );
		}

		/*
		 * Straight past the release rules and the attempt count. The whole point
		 * of this button is the case they get wrong: a send that failed for a
		 * reason outside the order — an SMTP password, a rejected sender — and
		 * has since been put right.
		 */
		$sent = $this->notifier->dispatch( $order );

		$order->add_order_note(
			$sent
				? __( 'The permit-ready email was sent by hand from the order screen.', 'idta-pdf' )
				: __( 'Sending the permit-ready email by hand failed. Check the mail configuration.', 'idta-pdf' )
		);

		wp_safe_redirect(
			add_query_arg(
				'idta_pdf_email',
				$sent ? 'sent' : 'failed',
				$order->get_edit_order_url()
			)
		);

		exit;
	}

	/**
	 * Report what the button did.
	 */
	public function render_send_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by our own redirect.
		$result = isset( $_GET['idta_pdf_email'] ) ? sanitize_key( wp_unslash( (string) $_GET['idta_pdf_email'] ) ) : '';

		if ( 'sent' === $result ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'The permit-ready email was sent.', 'idta-pdf' )
			);

			return;
		}

		if ( 'failed' === $result ) {
			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html__( 'The permit-ready email could not be sent. WooCommerce → Status → Logs, source "transactional-emails", records the reason.', 'idta-pdf' )
			);
		}
	}

	/**
	 * Resolve the order from whatever the screen passed.
	 *
	 * @param \WP_Post|\WC_Order|null $post Post or order.
	 *
	 * @return \WC_Order|null
	 */
	private function resolve_order( $post ): ?\WC_Order {
		if ( $post instanceof \WC_Order ) {
			return $post;
		}

		if ( $post instanceof \WP_Post ) {
			$order = wc_get_order( $post->ID );

			return $order instanceof \WC_Order ? $order : null;
		}

		return null;
	}
}
