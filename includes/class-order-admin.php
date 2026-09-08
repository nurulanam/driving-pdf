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
	 * Constructor.
	 *
	 * @param Generator        $generator Document generator.
	 * @param Release_Schedule $releases  Release rules.
	 * @param Download_Handler $downloads Download URL builder.
	 */
	public function __construct( Generator $generator, Release_Schedule $releases, Download_Handler $downloads ) {
		$this->generator = $generator;
		$this->releases  = $releases;
		$this->downloads = $downloads;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 30, 2 );
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
			printf(
				'<p style="margin:0 0 10px;"><span style="color:#00622b;font-weight:600;">%1$s</span><br><small>%2$s</small></p>',
				esc_html__( 'Released to the customer', 'idta-pdf' ),
				esc_html(
					$this->releases->is_rush( $order )
						? __( 'Rush order.', 'idta-pdf' )
						: __( 'Standard order.', 'idta-pdf' )
				)
			);

			return;
		}

		$when = $this->releases->released_at_local( $order );

		printf(
			'<p style="margin:0 0 10px;"><span style="color:#8a5700;font-weight:600;">%1$s</span><br><small>%2$s</small></p>',
			esc_html__( 'Not yet released to the customer', 'idta-pdf' ),
			esc_html(
				'' !== $when
					/* translators: %s: a date and time. */
					? sprintf( __( 'Available to them from %s. Your links above work now.', 'idta-pdf' ), $when )
					: __( 'The order is not paid, so there is nothing to time the wait from. Your links above work now.', 'idta-pdf' )
			)
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
		$sent = (string) $order->get_meta( Release_Email::SENT_META, true );

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
