<?php
/**
 * Document generation orchestration.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Builds, stores and tracks an order's documents.
 */
final class Generator {

	/**
	 * Meta key holding the generated document map.
	 */
	public const DOCUMENTS_META = '_idta_pdf_documents';

	/**
	 * Legacy note: DOCUMENTS_META above recorded where each document was stored
	 * back when documents were rendered ahead of time and kept in the uploads
	 * folder. Nothing writes it now — it is read once, by the upgrade routine
	 * that deletes those files. See Plugin::discard_stored_documents().
	 */

	/**
	 * Meta key holding the last failure message.
	 */
	public const ERROR_META = '_idta_pdf_last_error';

	/**
	 * Documents the customer is given.
	 *
	 * The order's folder also holds production files — the A5 permit print,
	 * which is an overlay for pre-printed stock, and the card's bitmap face,
	 * which is for whoever runs the card printer. Neither means anything to the
	 * holder, so the customer's downloads and emails are limited to this list
	 * rather than to whatever happens to be on disk.
	 *
	 * @var string[]
	 */
	public const CUSTOMER_DOCUMENTS = array( 'booklet', 'card' );

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Storage helper.
	 *
	 * @var Filesystem
	 */
	private Filesystem $filesystem;

	/**
	 * Image helper.
	 *
	 * @var Image_Helper
	 */
	private Image_Helper $images;

	/**
	 * QR generator.
	 *
	 * @var QR_Generator
	 */
	private QR_Generator $qr;

	/**
	 * PDF renderer.
	 *
	 * @var Renderer
	 */
	private Renderer $renderer;

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings repository.
	 * @param Renderer|null $renderer Renderer, defaulting to mPDF.
	 */
	public function __construct( Settings $settings, ?Renderer $renderer = null ) {
		$this->settings   = $settings;
		$this->filesystem = new Filesystem();
		$this->images     = new Image_Helper( $this->filesystem );
		$this->qr         = new QR_Generator( $settings );

		$this->renderer = $renderer ?? new Mpdf_Renderer( $this->filesystem );
	}

	/**
	 * Settings accessor.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Storage helper, used for the remote-asset cache.
	 *
	 * @return Filesystem
	 */
	public function filesystem(): Filesystem {
		return $this->filesystem;
	}

	/**
	 * Renderer accessor.
	 *
	 * @return Renderer
	 */
	public function renderer(): Renderer {
		return $this->renderer;
	}

	/**
	 * Build the document objects for an order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array<string,Document> Keyed by document slug.
	 */
	public function documents_for( \WC_Order $order ): array {
		$data = new Order_Data( $order );

		$map = array(
			'booklet'    => Booklet_Document::class,
			'card'       => Card_Document::class,
			'print-copy' => Print_Copy_Document::class,
		);

		$documents = array();
		$requested = $this->requested_documents( $data );

		foreach ( $this->settings->enabled_documents() as $slug ) {
			if ( ! isset( $map[ $slug ] ) || ! in_array( $slug, $requested, true ) ) {
				continue;
			}

			$class = $map[ $slug ];

			$documents[ $slug ] = new $class( $data, $this->settings, $this->images, $this->qr );
		}

		/**
		 * Filters the documents generated for an order.
		 *
		 * @param array<string,Document> $documents Documents, keyed by slug.
		 * @param \WC_Order              $order     Order object.
		 */
		return (array) apply_filters( 'idta_pdf_documents', $documents, $order );
	}

	/**
	 * Document slugs the order itself asks for, via `_idp_format`.
	 *
	 * The meta is free text ("card", "Booklet", "both", "card + booklet"), so
	 * it is only allowed to narrow the set when it names a document
	 * unambiguously. Anything else falls back to every enabled document.
	 *
	 * @param Order_Data $data Order data.
	 *
	 * @return string[]
	 */
	private function requested_documents( Order_Data $data ): array {
		$both   = array( 'booklet', 'card', 'print-copy' );
		$format = $data->format();

		if ( '' === $format ) {
			return $both;
		}

		$wants_card    = str_contains( $format, 'card' );
		$wants_booklet = str_contains( $format, 'booklet' ) || str_contains( $format, 'book' );

		/*
		 * The print copy follows the booklet: it is an overlay of the booklet's
		 * own cover and holder page, so a card-only order has nothing for it to
		 * register against.
		 */
		if ( $wants_card && ! $wants_booklet ) {
			$requested = array( 'card' );
		} elseif ( $wants_booklet && ! $wants_card ) {
			$requested = array( 'booklet', 'print-copy' );
		} else {
			$requested = $both;
		}

		/**
		 * Filters the documents an order asks for.
		 *
		 * @param string[]   $requested Document slugs.
		 * @param string     $format    Raw `_idp_format` value.
		 * @param Order_Data $data      Order data.
		 */
		return (array) apply_filters( 'idta_pdf_requested_documents', $requested, $format, $data );
	}

	/**
	 * Slugs an order may be offered, in the order the screens list them.
	 *
	 * The documents the order asks for, plus the card's bitmap face when that
	 * export is switched on and this order has a card to make one from.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string[]
	 */
	public function offered_slugs( \WC_Order $order ): array {
		$slugs = array_keys( $this->documents_for( $order ) );

		if ( in_array( 'card', $slugs, true ) && $this->settings->card_bitmaps() && Card_Bitmap::is_available() ) {
			foreach ( array_keys( Card_Bitmap::faces() ) as $face ) {
				$slugs[] = $face;
			}
		}

		return $slugs;
	}

	/**
	 * Whether an order may be offered a given slug.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return bool
	 */
	public function offers( \WC_Order $order, string $slug ): bool {
		return in_array( $slug, $this->offered_slugs( $order ), true );
	}

	/**
	 * Render a document and return its bytes.
	 *
	 * Nothing is written to the uploads folder: a document exists for the length
	 * of the request that asked for it. That costs a render on every download,
	 * which the booklet in particular is not cheap at — hence raise_limits()
	 * below — but it means no order's documents can be stale, orphaned, or left
	 * sitting on disk after the permit is delivered.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 *
	 * @throws Render_Exception When the order does not offer this document, or
	 *                          rendering fails.
	 */
	public function render_bytes( \WC_Order $order, string $slug ): string {
		if ( ! Order_Data::has_data( $order ) ) {
			throw new Render_Exception(
				sprintf( 'Order %d carries no IDP meta.', $order->get_id() )
			);
		}

		if ( ! $this->offers( $order, $slug ) ) {
			throw new Render_Exception(
				sprintf( 'Order %d does not offer the "%s" document.', $order->get_id(), $slug )
			);
		}

		$this->raise_limits();

		// A bitmap face is a render of the card, so the card is rendered first
		// and converted; there is no second layout to keep in step.
		if ( Card_Bitmap::is_face( $slug ) ) {
			return ( new Card_Bitmap() )->bytes( $this->render_pdf( $order, 'card' ), $slug );
		}

		return $this->render_pdf( $order, $slug );
	}

	/**
	 * Render one of the PDF documents.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 *
	 * @throws Render_Exception When the document is unknown.
	 */
	private function render_pdf( \WC_Order $order, string $slug ): string {
		$documents = $this->documents_for( $order );

		if ( ! isset( $documents[ $slug ] ) ) {
			throw new Render_Exception( sprintf( 'Unknown document "%s".', $slug ) );
		}

		return $this->renderer->render( $documents[ $slug ] );
	}

	/**
	 * Render a document's HTML rather than its PDF.
	 *
	 * The layout as the engine receives it, for working out why a page broke.
	 * Reachable only by an authenticated operator, and only while the debug
	 * setting is on — see Download_Handler.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 *
	 * @throws Render_Exception When the document is unknown.
	 */
	public function render_html( \WC_Order $order, string $slug ): string {
		$documents = $this->documents_for( $order );

		// A bitmap face has no HTML of its own; it is a render of the card.
		if ( Card_Bitmap::is_face( $slug ) ) {
			$slug = 'card';
		}

		if ( ! isset( $documents[ $slug ] ) ) {
			throw new Render_Exception( sprintf( 'Unknown document "%s".', $slug ) );
		}

		$this->raise_limits();

		return $documents[ $slug ]->html();
	}

	/**
	 * Filename a document is delivered under.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 */
	public function filename( \WC_Order $order, string $slug ): string {
		if ( Card_Bitmap::is_face( $slug ) ) {
			return Card_Bitmap::filename( $order, $slug );
		}

		$documents = $this->documents_for( $order );

		return isset( $documents[ $slug ] )
			? $documents[ $slug ]->filename()
			: sprintf( '%s-%s.pdf', $slug, $order->get_order_number() );
	}

	/**
	 * Media type a document is served as.
	 *
	 * @param string $slug Document slug.
	 *
	 * @return string
	 */
	public function mime( string $slug ): string {
		return Card_Bitmap::is_face( $slug ) ? 'image/bmp' : 'application/pdf';
	}

	/**
	 * Give the request the headroom a document needs.
	 *
	 * The booklet embeds a portrait, seals and branding, and mPDF holds the
	 * whole document in memory while it lays it out. The defaults on shared
	 * hosting are not generous enough to make that survive, and it fails in a
	 * way that looks like the plugin rather than the limit.
	 */
	private function raise_limits(): void {
		/**
		 * Filters the memory limit raised for a render.
		 *
		 * @param string $limit Memory limit. Empty leaves it untouched.
		 */
		$memory = (string) apply_filters( 'idta_pdf_memory_limit', '512M' );

		if ( '' !== $memory ) {
			wp_raise_memory_limit( 'image' );

			// Fails silently where it is fixed, which is the intended outcome.
			@ini_set( 'memory_limit', $memory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
		}

		/**
		 * Filters the time limit raised for a render.
		 *
		 * @param int $seconds Time limit. Zero leaves the limit untouched.
		 */
		$seconds = (int) apply_filters( 'idta_pdf_time_limit', 300 );

		if ( $seconds > 0 && function_exists( 'set_time_limit' ) ) {
			@set_time_limit( $seconds ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Documents already generated for an order.
	 *
	 * Deprecated, and kept for the same reason as
	 * Settings::attachment_emails(): code from an earlier version may still be
	 * on the server after an in-place update, and calling a method that no
	 * longer exists is a fatal error rather than a missing feature. An empty
	 * list reads, correctly, as "nothing is stored".
	 *
	 * @deprecated Documents are rendered on request and never stored.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array<string,string> Always empty.
	 */
	public function generated_documents( \WC_Order $order ): array {
		unset( $order );

		return array();
	}

	/**
	 * Record a generation failure on the order and in the log.
	 *
	 * @param \WC_Order  $order     Order object.
	 * @param \Throwable $exception Failure.
	 */
	public function log_failure( \WC_Order $order, \Throwable $exception ): void {
		$message = $exception->getMessage();

		$order->update_meta_data( self::ERROR_META, $message );
		$order->save_meta_data();

		$order->add_order_note(
			sprintf(
				/* translators: %s: error message. */
				__( 'IDTA PDF generation failed: %s', 'idta-pdf' ),
				$message
			)
		);

		$this->log( sprintf( 'Order %d: %s', $order->get_id(), $message ) );
	}

	/**
	 * Write to the WooCommerce log.
	 *
	 * @param string $message Message.
	 */
	private function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'idta-pdf' ) );
		}
	}
}
