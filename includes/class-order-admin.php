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
 * Adds a document panel and actions to the order edit screen.
 */
final class Order_Admin {

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
	 * Constructor.
	 *
	 * @param Generator $generator Document generator.
	 */
	public function __construct( Generator $generator ) {
		$this->generator = $generator;
		$this->downloads = new Download_Handler( $generator );
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 30, 2 );
		add_action( 'admin_post_idta_pdf_regenerate', array( $this, 'handle_regenerate' ) );
		add_action( 'admin_post_idta_pdf_generate_one', array( $this, 'handle_generate_one' ) );
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_action' ), 10, 1 );
		add_action( 'woocommerce_order_action_idta_pdf_regenerate', array( $this, 'handle_order_action' ), 10, 1 );
	}

	/**
	 * Build the URL for the "generate a single document" action.
	 *
	 * Shared with Order_List_Column, which offers the same action from the
	 * orders list.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 */
	public function generate_one_url( \WC_Order $order, string $slug ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'idta_pdf_generate_one',
					'order_id' => $order->get_id(),
					'slug'     => $slug,
				),
				admin_url( 'admin-post.php' )
			),
			'idta-pdf-generate-one'
		);
	}

	/**
	 * Register the document panel on both legacy and HPOS order screens.
	 *
	 * @param string $screen_id Current screen ID.
	 * @param mixed  $post      Post or order object.
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
	 * Render the document panel.
	 *
	 * @param mixed $post Post or order object.
	 */
	public function render_meta_box( $post ): void {
		$order = $this->resolve_order( $post );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$documents = $this->generator->generated_documents( $order );
		$requested = array_keys( $this->generator->documents_for( $order ) );
		$error     = $order->get_meta( Generator::ERROR_META, true );

		$labels = array(
			'booklet' => __( 'Permit booklet (A4)', 'idta-pdf' ),
			'card'    => __( 'Permit card (85.6 × 53.98 mm)', 'idta-pdf' ),
		);

		echo '<ul style="margin:0 0 12px;">';

		if ( array() === $requested ) {
			printf(
				'<li><em>%s</em></li>',
				esc_html__( 'This order does not request any IDP document.', 'idta-pdf' )
			);
		}

		foreach ( $requested as $slug ) {
			$label = $labels[ $slug ] ?? $slug;

			if ( isset( $documents[ $slug ] ) ) {
				printf(
					'<li style="margin-bottom:6px;"><a href="%1$s">%2$s</a><br><small>%3$s</small></li>',
					esc_url( $this->downloads->admin_url( $order, $slug ) ),
					esc_html( $label ),
					esc_html( size_format( (int) ( filesize( $documents[ $slug ] ) ?: 0 ) ) )
				);

				continue;
			}

			printf(
				'<li style="margin-bottom:6px;">%1$s <a class="button button-small" href="%2$s">%3$s</a></li>',
				esc_html( $label ),
				esc_url( $this->generate_one_url( $order, $slug ) ),
				esc_html__( 'Generate', 'idta-pdf' )
			);
		}

		echo '</ul>';

		if ( is_string( $error ) && '' !== $error ) {
			printf(
				'<p style="color:#b32d2e;"><strong>%1$s</strong><br><small>%2$s</small></p>',
				esc_html__( 'Last error', 'idta-pdf' ),
				esc_html( $error )
			);
		}

		$regenerate = wp_nonce_url(
			add_query_arg(
				array(
					'action'   => 'idta_pdf_regenerate',
					'order_id' => $order->get_id(),
				),
				admin_url( 'admin-post.php' )
			),
			'idta-pdf-regenerate'
		);

		printf(
			'<p><a class="button button-primary" href="%1$s">%2$s</a></p>',
			esc_url( $regenerate ),
			esc_html__( 'Regenerate documents', 'idta-pdf' )
		);
	}

	/**
	 * Handle the regenerate button.
	 */
	public function handle_regenerate(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( (string) $_GET['order_id'] ) ) : 0;

		check_admin_referer( 'idta-pdf-regenerate' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to regenerate documents.', 'idta-pdf' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'idta-pdf' ), '', array( 'response' => 404 ) );
		}

		$notice = 'idta-pdf-regenerated';

		try {
			$this->generator->generate( $order, true );
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );

			$notice = 'idta-pdf-failed';
		}

		wp_safe_redirect(
			add_query_arg( 'idta_pdf_notice', $notice, $order->get_edit_order_url() )
		);

		exit;
	}

	/**
	 * Handle the per-document "Generate" button.
	 *
	 * Used both by the order edit screen and the orders list column, for the
	 * common case where only one of the two documents is missing.
	 */
	public function handle_generate_one(): void {
		$order_id = isset( $_GET['order_id'] ) ? absint( wp_unslash( (string) $_GET['order_id'] ) ) : 0;
		$slug     = isset( $_GET['slug'] ) ? sanitize_key( wp_unslash( (string) $_GET['slug'] ) ) : '';

		check_admin_referer( 'idta-pdf-generate-one' );

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to generate documents.', 'idta-pdf' ), '', array( 'response' => 403 ) );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			wp_die( esc_html__( 'Order not found.', 'idta-pdf' ), '', array( 'response' => 404 ) );
		}

		$notice = 'idta-pdf-generated';

		try {
			$this->generator->generate_one( $order, $slug );
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );

			$notice = 'idta-pdf-failed';
		}

		// Return to wherever the button was clicked from — the orders list or
		// the order edit screen — rather than assuming one or the other.
		$redirect = wp_get_referer();

		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = $order->get_edit_order_url();
		}

		wp_safe_redirect( add_query_arg( 'idta_pdf_notice', $notice, $redirect ) );

		exit;
	}

	/**
	 * Offer regeneration in the order actions dropdown.
	 *
	 * @param array<string,string> $actions Existing actions.
	 *
	 * @return array<string,string>
	 */
	public function add_order_action( $actions ): array {
		$actions = is_array( $actions ) ? $actions : array();

		$actions['idta_pdf_regenerate'] = __( 'Regenerate IDP documents', 'idta-pdf' );

		return $actions;
	}

	/**
	 * Run regeneration from the order actions dropdown.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function handle_order_action( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		try {
			$this->generator->generate( $order, true );
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );
		}
	}

	/**
	 * Resolve an order from whatever the order screen passes in.
	 *
	 * The legacy screen passes a WP_Post; HPOS passes a WC_Order.
	 *
	 * @param mixed $post Post or order object.
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
