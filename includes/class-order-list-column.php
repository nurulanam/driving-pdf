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
 * Adds a trailing "IDP Documents" column with per-document download or
 * generate buttons, on both the legacy post-based orders screen and the
 * High-Performance Order Storage orders screen.
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

		$columns[ self::COLUMN ] = __( 'IDP Documents', 'idta-pdf' );

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
			'booklet' => __( 'Permit', 'idta-pdf' ),
			'card'    => __( 'Card', 'idta-pdf' ),
		);

		echo '<div class="idta-pdf-column" style="display:flex;flex-direction:column;gap:4px;align-items:flex-start;">';

		foreach ( $requested as $slug ) {
			$label = $labels[ $slug ] ?? $slug;

			if ( isset( $documents[ $slug ] ) ) {
				printf(
					'<a class="button button-small" href="%1$s" style="width:100%%;text-align:center;">%2$s</a>',
					esc_url( $this->downloads->admin_url( $order, $slug ) ),
					esc_html( $label )
				);

				continue;
			}

			printf(
				'<a class="button button-small button-secondary" href="%1$s" style="width:100%%;text-align:center;">%2$s</a>',
				esc_url( $this->order_admin->generate_one_url( $order, $slug ) ),
				/* translators: %s: document label, e.g. "Permit" or "Card". */
				esc_html( sprintf( __( 'Generate %s', 'idta-pdf' ), $label ) )
			);
		}

		echo '</div>';
	}
}
