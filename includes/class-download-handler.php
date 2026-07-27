<?php
/**
 * Authenticated document delivery.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Streams generated documents to authorised requesters.
 *
 * Files live outside the web root's reachable paths, so every download passes
 * through here and is authorised first.
 */
final class Download_Handler {

	/**
	 * Query variable that triggers a download.
	 */
	private const ACTION = 'idta_pdf_download';

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Constructor.
	 *
	 * @param Generator $generator Document generator.
	 */
	public function __construct( Generator $generator ) {
		$this->generator = $generator;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_handle_request' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_account_actions' ), 10, 2 );
	}

	/**
	 * Build a download URL.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 */
	public function url( \WC_Order $order, string $slug ): string {
		$args = array(
			self::ACTION => $slug,
			'order_id'   => $order->get_id(),
			'key'        => $order->get_order_key(),
		);

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Build a nonce-protected admin download URL.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 * @param bool      $force Re-render rather than serve the stored file.
	 *
	 * @return string
	 */
	public function admin_url( \WC_Order $order, string $slug, bool $force = false ): string {
		$args = array(
			self::ACTION => $slug,
			'order_id'   => $order->get_id(),
			'context'    => 'admin',
		);

		if ( $force ) {
			$args['force'] = 1;
		}

		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php' ) ), 'idta-pdf-download' );
	}

	/**
	 * Detect and serve a download request.
	 */
	public function maybe_handle_request(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorisation is performed below.
		if ( ! isset( $_GET[ self::ACTION ], $_GET['order_id'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorisation is performed below.
		$slug = sanitize_key( wp_unslash( (string) $_GET[ self::ACTION ] ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authorisation is performed below.
		$order_id = absint( wp_unslash( (string) $_GET['order_id'] ) );

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			$this->deny( __( 'Order not found.', 'idta-pdf' ), 404 );
		}

		if ( ! $this->is_authorised( $order ) ) {
			$this->deny( __( 'You are not allowed to download this document.', 'idta-pdf' ), 403 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in is_authorised() for the admin context.
		$force = isset( $_GET['force'] ) && current_user_can( 'edit_shop_orders' );

		$documents = $this->generator->generated_documents( $order );

		if ( ! $force && isset( $documents[ $slug ] ) ) {
			$this->stream_file( $documents[ $slug ], basename( $documents[ $slug ] ) );
		}

		// Nothing stored yet: render on demand rather than 404.
		try {
			$regenerated = $this->generator->generate( $order, (bool) $force );
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );

			$this->deny( __( 'The document could not be generated.', 'idta-pdf' ), 500 );
		}

		if ( isset( $regenerated[ $slug ] ) ) {
			$this->stream_file( $regenerated[ $slug ], basename( $regenerated[ $slug ] ) );
		}

		$this->deny( __( 'Unknown document.', 'idta-pdf' ), 404 );
	}

	/**
	 * Whether the current request may download the order's documents.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	private function is_authorised( \WC_Order $order ): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is checked immediately below.
		$context = isset( $_GET['context'] ) ? sanitize_key( wp_unslash( (string) $_GET['context'] ) ) : '';

		if ( 'admin' === $context ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified here.
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['_wpnonce'] ) ) : '';

			return current_user_can( 'edit_shop_orders' )
				&& (bool) wp_verify_nonce( $nonce, 'idta-pdf-download' );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key is the credential.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';

		// The order key is a per-order secret; compare it in constant time.
		if ( '' !== $key && hash_equals( $order->get_order_key(), $key ) ) {
			return true;
		}

		$user_id = get_current_user_id();

		if ( $user_id > 0 && $user_id === $order->get_customer_id() ) {
			return true;
		}

		return current_user_can( 'edit_shop_orders' );
	}

	/**
	 * Stream a stored PDF and exit.
	 *
	 * @param string $path     Absolute path.
	 * @param string $filename Download filename.
	 */
	private function stream_file( string $path, string $filename ): void {
		if ( ! $this->generator->filesystem()->is_managed( $path ) || ! is_readable( $path ) ) {
			$this->deny( __( 'The document is no longer available.', 'idta-pdf' ), 404 );
		}

		$size = filesize( $path );

		nocache_headers();

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );

		if ( false !== $size ) {
			header( 'Content-Length: ' . $size );
		}

		// Discard any buffered output so the PDF is not corrupted.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );

		exit;
	}

	/**
	 * Abort the request with a status code.
	 *
	 * @param string $message Message shown to the requester.
	 * @param int    $status  HTTP status code.
	 *
	 * @return never
	 */
	private function deny( string $message, int $status ): void {
		wp_die( esc_html( $message ), esc_html__( 'IDTA PDF', 'idta-pdf' ), array( 'response' => $status ) );
	}

	/**
	 * Offer downloads on the customer's order list.
	 *
	 * @param array<string,array<string,string>> $actions Existing actions.
	 * @param \WC_Order                          $order   Order object.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function add_account_actions( $actions, $order ): array {
		$actions = is_array( $actions ) ? $actions : array();

		if ( ! $order instanceof \WC_Order ) {
			return $actions;
		}

		$labels = array(
			'booklet' => __( 'Download permit', 'idta-pdf' ),
			'card'    => __( 'Download card', 'idta-pdf' ),
		);

		foreach ( $this->generator->generated_documents( $order ) as $slug => $path ) {
			unset( $path );

			$actions[ 'idta_pdf_' . $slug ] = array(
				'url'  => $this->url( $order, $slug ),
				'name' => $labels[ $slug ] ?? __( 'Download document', 'idta-pdf' ),
			);
		}

		return $actions;
	}
}
