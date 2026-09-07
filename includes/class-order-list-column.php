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
 * Adds a trailing "PDFs" column with per-document download or generate buttons,
 * a regenerate action, and the last generation error when there is one — on both
 * the legacy post-based orders screen and the High-Performance Order Storage
 * orders screen.
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
	 * Order admin, reused for its download and generate URL builders.
	 *
	 * @var Order_Admin
	 */
	private Order_Admin $order_admin;

	/**
	 * Download URL builder.
	 *
	 * @var Download_Handler
	 */
	private Download_Handler $downloads;

	/**
	 * Constructor.
	 *
	 * @param Generator   $generator   Document generator.
	 * @param Order_Admin $order_admin Order admin.
	 */
	public function __construct( Generator $generator, Order_Admin $order_admin ) {
		$this->generator   = $generator;
		$this->order_admin = $order_admin;
		$this->downloads   = new Download_Handler( $generator );
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
	 * Render the buttons for one order.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render( \WC_Order $order ): void {
		if ( ! Order_Data::has_data( $order ) ) {
			echo '&#8212;';

			return;
		}

		$requested = array_keys( $this->generator->documents_for( $order ) );

		if ( array() === $requested ) {
			echo '&#8212;';

			return;
		}

		$documents = $this->generator->generated_documents( $order );

		$labels = array(
			'booklet'    => __( 'Permit', 'idta-pdf' ),
			'card'       => __( 'Card', 'idta-pdf' ),
			'print-copy' => __( 'Permit print', 'idta-pdf' ),
			'card-front-bmp' => __( 'Card front (BMP)', 'idta-pdf' ),
			'card-back-bmp'  => __( 'Card back (BMP)', 'idta-pdf' ),
		);

		/*
		 * The card's bitmap faces are derived from the card PDF rather than
		 * requested in their own right, so documents_for() does not name them.
		 * Appending whatever else is on disk lists them for download without
		 * offering a "Generate" button that no document class could answer.
		 */
		$listed = array_merge(
			$requested,
			array_values( array_diff( array_keys( $documents ), $requested ) )
		);

		// One row, wrapping only if the column is too narrow for it.
		echo '<div class="idta-pdf-column" style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">';

		foreach ( $listed as $slug ) {
			$label = $labels[ $slug ] ?? $slug;

			if ( isset( $documents[ $slug ] ) ) {
				printf(
					'<a class="button button-small" href="%1$s">%2$s</a>',
					esc_url( $this->downloads->admin_url( $order, $slug ) ),
					esc_html( $label )
				);

				continue;
			}

			printf(
				'<a class="button button-small button-secondary" href="%1$s">%2$s</a>',
				esc_url( $this->order_admin->generate_one_url( $order, $slug ) ),
				/* translators: %s: document label, e.g. "Permit" or "Card". */
				esc_html( sprintf( __( 'Generate %s', 'idta-pdf' ), $label ) )
			);
		}

		$this->render_regenerate( $order );
		$this->render_error( $order );

		echo '</div>';
	}

	/**
	 * Icon-only regenerate button.
	 *
	 * Dashicons are always present in wp-admin, so no asset needs enqueueing. The
	 * label lives in the title and aria-label rather than as text, since the
	 * column has room for the two document buttons and little else.
	 *
	 * @param \WC_Order $order Order object.
	 */
	private function render_regenerate( \WC_Order $order ): void {
		printf(
			'<a class="button button-small" href="%1$s" title="%2$s" aria-label="%2$s"'
			. ' style="padding:0 5px;line-height:24px;">'
			. '<span class="dashicons dashicons-update" style="font-size:16px;width:16px;height:16px;'
			. 'line-height:24px;vertical-align:top;"></span></a>',
			esc_url( $this->order_admin->regenerate_url( $order ) ),
			esc_attr__( 'Regenerate PDFs', 'idta-pdf' )
		);
	}

	/**
	 * Warning icon carrying the last generation error, when one was recorded.
	 *
	 * Generator::generate() keeps going when a single document fails, so an order
	 * can legitimately end up with one PDF and not the other. Without this the
	 * only clue was the panel on the order screen, which is a click away from
	 * where the missing button is noticed.
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
