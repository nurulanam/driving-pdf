<?php
/**
 * IDP document column on the WooCommerce orders list.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a trailing "PDFs" column of per-document links, plus the last render
 * error when there is one — on both the legacy post-based orders screen and the
 * High-Performance Order Storage orders screen.
 *
 * Every link renders its document when it is followed, so there is nothing to
 * generate from here and no stored copy to be out of date.
 */
final class Order_List_Column {

	/**
	 * Column key.
	 */
	private const COLUMN = 'idta_pdf';

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Download URL builder.
	 *
	 * @var Download_Handler
	 */
	private Download_Handler $downloads;

	/**
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

	/**
	 * Constructor.
	 *
	 * @param Generator        $generator Document generator.
	 * @param Download_Handler $downloads Download URL builder.
	 * @param Release_Schedule $releases  Release rules.
	 */
	public function __construct( Generator $generator, Download_Handler $downloads, Release_Schedule $releases ) {
		$this->generator = $generator;
		$this->downloads = $downloads;
		$this->releases  = $releases;
	}

	/**
	 * Register hooks for both the legacy and HPOS orders screens.
	 */
	public function register(): void {
		// Legacy: shop_order post type list table.
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_legacy_column' ), 10, 2 );

		// HPOS: custom order tables list table.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_column' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_hpos_column' ), 10, 2 );
	}

	/**
	 * Append the column at the end of the list.
	 *
	 * @param array<string,string> $columns Existing columns.
	 *
	 * @return array<string,string>
	 */
	public function add_column( $columns ): array {
		$columns = is_array( $columns ) ? $columns : array();

		$columns[ self::COLUMN ] = __( 'PDFs', 'idta-pdf' );

		return $columns;
	}

	/**
	 * Render the column on the legacy orders screen.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Order (post) ID.
	 */
	public function render_legacy_column( $column, $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$order = wc_get_order( absint( $post_id ) );

		if ( $order instanceof \WC_Order ) {
			$this->render( $order );
		}
	}

	/**
	 * Render the column on the HPOS orders screen.
	 *
	 * @param string          $column Column key.
	 * @param \WC_Order|mixed $order  Order object.
	 */
	public function render_hpos_column( $column, $order ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		if ( $order instanceof \WC_Order ) {
			$this->render( $order );
		}
	}

	/**
	 * Render the column for one order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render( \WC_Order $order ): void {
		if ( ! Order_Data::has_data( $order ) ) {
			echo '&#8212;';

			return;
		}

		$slugs = $this->generator->offered_slugs( $order );

		if ( array() === $slugs ) {
			echo '&#8212;';

			return;
		}

		$labels = array(
			'booklet'    => __( 'Permit', 'idta-pdf' ),
			'card'       => __( 'Card', 'idta-pdf' ),
			'print-copy' => __( 'Permit print', 'idta-pdf' ),
			Card_Bitmap::FRONT_SLUG => __( 'Card front (BMP)', 'idta-pdf' ),
		);

		// One row, wrapping only if the column is too narrow for it.
		echo '<div class="idta-pdf-column" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">';

		foreach ( $slugs as $slug ) {
			printf(
				'<a class="button button-small" href="%1$s" target="_blank" rel="noopener">%2$s</a>',
				esc_url( $this->downloads->admin_url( $order, $slug ) ),
				esc_html( $labels[ $slug ] ?? $slug )
			);
		}

		$this->render_release( $order );
		$this->render_error( $order );

		echo '</div>';
	}

	/**
	 * A marker showing whether the customer can fetch their permit yet.
	 *
	 * Icon-only, with the detail in the title: the column has room for the
	 * document buttons and little else. Dashicons are always present in
	 * wp-admin, so nothing needs enqueueing.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render_release( \WC_Order $order ): void {
		if ( $this->releases->is_released( $order ) ) {
			printf(
				'<span class="dashicons dashicons-yes-alt" style="color:#00622b;" title="%s"></span>',
				esc_attr__( 'Released: the customer can download this now.', 'idta-pdf' )
			);

			return;
		}

		$when = $this->releases->released_at_local( $order );

		printf(
			'<span class="dashicons dashicons-clock" style="color:#8a5700;" title="%s"></span>',
			esc_attr(
				'' !== $when
					/* translators: %s: a date and time. */
					? sprintf( __( 'Held back from the customer until %s.', 'idta-pdf' ), $when )
					: __( 'Held back: the order is not paid.', 'idta-pdf' )
			)
		);
	}

	/**
	 * Warning icon carrying the last render error, when one was recorded.
	 *
	 * A render now fails in front of whoever asked for it, so this is the record
	 * of the last one that did — usually a customer's link that answered with an
	 * error while the operator heard nothing about it.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render_error( \WC_Order $order ): void {
		$error = $order->get_meta( Generator::ERROR_META, true );

		if ( ! is_string( $error ) || '' === trim( $error ) ) {
			return;
		}

		printf(
			'<span class="dashicons dashicons-warning" title="%1$s"'
			. ' style="color:#b32d2e;font-size:18px;width:18px;height:18px;cursor:help;"></span>',
			esc_attr( $error )
		);
	}
}
