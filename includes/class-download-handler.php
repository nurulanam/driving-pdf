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
 * Renders a document when its URL is requested, and streams it.
 *
 * Nothing is generated in advance and nothing is kept: every request renders
 * the document it asks for and streams the bytes straight out. So this endpoint
 * is not a file server with a permission check in front of it — it is the only
 * place a document exists at all, which puts three questions here:
 *
 *   - may this requester see this order's documents (the order key, the
 *     customer's own session, or an operator's nonce);
 *   - is this order's permit released yet (Release_Schedule);
 *   - does this order include the document being asked for (Generator).
 *
 * An authenticated operator is exempt from the second: the release wait is a
 * customer-facing delivery rule, not a security boundary, and an operator
 * needing a permit now must not have to wait four hours for it.
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
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

	/**
	 * Constructor.
	 *
	 * @param Generator        $generator Document generator.
	 * @param Release_Schedule $releases  Release rules.
	 */
	public function __construct( Generator $generator, Release_Schedule $releases ) {
		$this->generator = $generator;
		$this->releases  = $releases;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'maybe_handle_request' ) );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'add_account_actions' ), 10, 2 );
	}

	/**
	 * Build a customer-facing document URL.
	 *
	 * @param \WC_Order $order    Order object.
	 * @param string    $slug     Document slug.
	 * @param bool      $download Offer as a file rather than showing it inline.
	 *
	 * @return string
	 */
	public function url( \WC_Order $order, string $slug, bool $download = false ): string {
		$args = array(
			self::ACTION => $slug,
			'order_id'   => $order->get_id(),
			'key'        => $order->get_order_key(),
		);

		if ( $download ) {
			$args['download'] = 1;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Build a nonce-protected operator URL.
	 *
	 * @param \WC_Order $order    Order object.
	 * @param string    $slug     Document slug.
	 * @param bool      $download Offer as a file rather than showing it inline.
	 *
	 * @return string
	 */
	public function admin_url( \WC_Order $order, string $slug, bool $download = false ): string {
		$args = array(
			self::ACTION => $slug,
			'order_id'   => $order->get_id(),
			'context'    => 'admin',
		);

		if ( $download ) {
			$args['download'] = 1;
		}

		return add_query_arg(
			'_wpnonce',
			wp_create_nonce( 'idta-pdf-download' ),
			add_query_arg( $args, admin_url( 'admin.php' ) )
		);
	}

	/**
	 * Detect a document request, render it, and serve it.
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

		$is_operator = $this->is_operator();

		if ( ! $is_operator && ! $this->is_authorised( $order ) ) {
			$this->deny( __( 'You are not allowed to download this document.', 'idta-pdf' ), 403 );
		}

		/*
		 * The release gate. Held to 403 rather than 404 with the reason spelled
		 * out, because the usual case is a customer who paid minutes ago and
		 * wants to know when to come back, not someone probing for a document.
		 */
		if ( ! $is_operator && ! $this->releases->is_released( $order ) ) {
			$this->deny( $this->releases->explain( $order ), 403 );
		}

		if ( ! $this->generator->offers( $order, $slug ) ) {
			$this->deny( __( 'Unknown document.', 'idta-pdf' ), 404 );
		}

		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Gated on the capability checked in is_operator().
			$wants_html = $is_operator && isset( $_GET['html'] ) && $this->generator->settings()->debug_html();

			if ( $wants_html ) {
				$this->stream(
					$this->generator->render_html( $order, $slug ),
					'text/html; charset=' . get_bloginfo( 'charset' ),
					'',
					false
				);
			}

			$this->stream(
				$this->generator->render_bytes( $order, $slug ),
				$this->generator->mime( $slug ),
				$this->generator->filename( $order, $slug ),
				$this->wants_download( $slug )
			);
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );

			$this->deny( __( 'The document could not be generated.', 'idta-pdf' ), 500 );
		}
	}

	/**
	 * Whether the document should arrive as a file rather than shown in place.
	 *
	 * A PDF is shown inline by default, so following the link opens the permit
	 * straight away and the browser's own controls offer the save. A bitmap is
	 * always a file: no browser displays one usefully, and it exists to be fed
	 * to a card printer.
	 *
	 * @param string $slug Document slug.
	 *
	 * @return bool
	 */
	private function wants_download( string $slug ): bool {
		if ( Card_Bitmap::is_face( $slug ) ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses a Content-Disposition, nothing more.
		return isset( $_GET['download'] );
	}

	/**
	 * Whether the request comes from someone who runs the store.
	 *
	 * Decided on the capability alone, deliberately, and not on the nonce the
	 * admin links carry. A nonce expires after a day, and an operator who
	 * bookmarked a document link would otherwise find it quietly demoted to a
	 * customer link — gated behind the release wait — rather than failing in a
	 * way that explains itself. Nothing here changes state, so there is nothing
	 * for the nonce to protect: whoever may edit the order may read its permit.
	 *
	 * @return bool
	 */
	private function is_operator(): bool {
		return current_user_can( 'edit_shop_orders' );
	}

	/**
	 * Whether the current request may see this order's documents.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	private function is_authorised( \WC_Order $order ): bool {
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
	 * Stream a rendered document and stop.
	 *
	 * @param string $bytes    Document body.
	 * @param string $mime     Media type.
	 * @param string $filename Filename, when offered as a file.
	 * @param bool   $download Whether to offer it as a file.
	 *
	 * @return never
	 */
	private function stream( string $bytes, string $mime, string $filename, bool $download ): void {
		nocache_headers();

		header( 'Content-Type: ' . $mime );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: ' . strlen( $bytes ) );

		if ( '' !== $filename ) {
			header(
				sprintf(
					'Content-Disposition: %s; filename="%s"',
					$download ? 'attachment' : 'inline',
					sanitize_file_name( $filename )
				)
			);
		}

		// Discard any buffered output so the document is not corrupted.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A rendered binary document.

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
	 * Offer the customer their documents on the My Account order list.
	 *
	 * Offered only once the order's permit is released, so a customer is not
	 * given a link that answers with a refusal.
	 *
	 * @param array<string,array<string,string>> $actions Existing actions.
	 * @param \WC_Order                          $order   Order object.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function add_account_actions( $actions, $order ): array {
		$actions = is_array( $actions ) ? $actions : array();

		if ( ! $order instanceof \WC_Order || ! Order_Data::has_data( $order ) ) {
			return $actions;
		}

		if ( ! $this->releases->is_released( $order ) ) {
			return $actions;
		}

		$labels = array(
			'booklet' => __( 'Download permit', 'idta-pdf' ),
			'card'    => __( 'Download card', 'idta-pdf' ),
		);

		foreach ( $this->generator->offered_slugs( $order ) as $slug ) {
			/*
			 * The booklet and the card only — see Generator::CUSTOMER_DOCUMENTS.
			 * The permit print and the card bitmap are production files for
			 * whoever prints the permit, and mean nothing to the holder.
			 */
			if ( ! in_array( $slug, Generator::CUSTOMER_DOCUMENTS, true ) || ! isset( $labels[ $slug ] ) ) {
				continue;
			}

			$actions[ 'idta_pdf_' . $slug ] = array(
				'url'  => $this->url( $order, $slug ),
				'name' => $labels[ $slug ],
			);
		}

		return $actions;
	}
}
