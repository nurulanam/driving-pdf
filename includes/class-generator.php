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
	 * Meta key holding the last failure message.
	 */
	public const ERROR_META = '_idta_pdf_last_error';

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
	 * Storage helper accessor.
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
	 * Generate every enabled document for an order.
	 *
	 * @param \WC_Order $order Order object.
	 * @param bool      $force Rebuild documents that already exist.
	 *
	 * @return array<string,string> Absolute paths, keyed by document slug.
	 *
	 * @throws Render_Exception When no document could be produced.
	 */
	public function generate( \WC_Order $order, bool $force = false ): array {
		if ( ! Order_Data::has_data( $order ) ) {
			throw new Render_Exception(
				sprintf( 'Order %d carries no IDP meta.', $order->get_id() )
			);
		}

		$existing = $this->generated_documents( $order );
		$results  = array();
		$failures = array();

		foreach ( $this->documents_for( $order ) as $slug => $document ) {
			if ( ! $force && isset( $existing[ $slug ] ) && is_readable( $existing[ $slug ] ) ) {
				$results[ $slug ] = $existing[ $slug ];

				continue;
			}

			try {
				$results[ $slug ] = $this->render_to_disk( $order, $document );
			} catch ( \Throwable $exception ) {
				// One failing document must not discard the other.
				$failures[ $slug ] = $exception->getMessage();

				$this->log(
					sprintf(
						'Order %d: %s document failed - %s',
						$order->get_id(),
						$slug,
						$exception->getMessage()
					)
				);
			}
		}

		$this->store_documents( $order, $results );

		if ( array() === $results ) {
			throw new Render_Exception(
				sprintf(
					'No documents could be generated for order %d. %s',
					$order->get_id(),
					implode( ' ', $failures )
				)
			);
		}

		if ( array() !== $failures ) {
			$order->update_meta_data( self::ERROR_META, implode( ' | ', $failures ) );
			$order->save_meta_data();
		} else {
			$order->delete_meta_data( self::ERROR_META );
			$order->save_meta_data();
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: comma-separated document names. */
				__( 'IDTA PDF generated: %s', 'idta-pdf' ),
				implode( ', ', array_keys( $results ) )
			)
		);

		/**
		 * Fires after an order's documents have been generated.
		 *
		 * @param array<string,string> $results Paths keyed by document slug.
		 * @param \WC_Order            $order   Order object.
		 */
		do_action( 'idta_pdf_generated', $results, $order );

		$this->filesystem->purge_cache();

		return $results;
	}

	/**
	 * Generate a single document for an order.
	 *
	 * Used by the manual "Generate" action on the orders list and order edit
	 * screen, where an operator wants just the missing document rather than
	 * rebuilding everything.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 * @param bool      $force Rebuild even if the document already exists.
	 *
	 * @return string Absolute path to the stored PDF.
	 *
	 * @throws Render_Exception When the order does not request this document,
	 *                          or rendering fails.
	 */
	public function generate_one( \WC_Order $order, string $slug, bool $force = false ): string {
		$documents = $this->documents_for( $order );

		if ( ! isset( $documents[ $slug ] ) ) {
			throw new Render_Exception(
				sprintf( 'Order %d does not request the "%s" document.', $order->get_id(), $slug )
			);
		}

		$existing = $this->generated_documents( $order );

		if ( ! $force && isset( $existing[ $slug ] ) && is_readable( $existing[ $slug ] ) ) {
			return $existing[ $slug ];
		}

		$path = $this->render_to_disk( $order, $documents[ $slug ] );

		$existing[ $slug ] = $path;

		$this->store_documents( $order, $existing );

		$order->delete_meta_data( self::ERROR_META );
		$order->save_meta_data();

		$order->add_order_note(
			sprintf(
				/* translators: %s: document slug. */
				__( 'IDTA PDF generated: %s', 'idta-pdf' ),
				$slug
			)
		);

		/** This action is documented in includes/class-generator.php */
		do_action( 'idta_pdf_generated', array( $slug => $path ), $order );

		$this->filesystem->purge_cache();

		return $path;
	}

	/**
	 * Render one document and write it to protected storage.
	 *
	 * @param \WC_Order $order    Order object.
	 * @param Document  $document Document to render.
	 *
	 * @return string Absolute path to the stored PDF.
	 *
	 * @throws Render_Exception When rendering or storing fails.
	 */
	private function render_to_disk( \WC_Order $order, Document $document ): string {
		$bytes = $this->renderer->render( $document );

		$dir = $this->filesystem->order_dir( $order );

		if ( ! $this->filesystem->ensure_dir( $dir ) ) {
			throw new Render_Exception(
				sprintf( 'Document directory "%s" is not writable.', $dir )
			);
		}

		$path = trailingslashit( $dir ) . $document->filename();

		if ( ! $this->filesystem->put_contents( $path, $bytes ) ) {
			throw new Render_Exception(
				sprintf( 'Could not write "%s".', $path )
			);
		}

		if ( $this->settings->debug_html() ) {
			$this->filesystem->put_contents(
				preg_replace( '/\.pdf$/', '.html', $path ) ?? $path . '.html',
				$document->html()
			);
		}

		return $path;
	}

	/**
	 * Render a document straight to the browser.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string Raw PDF bytes.
	 *
	 * @throws Render_Exception When the slug is unknown or rendering fails.
	 */
	public function render_bytes( \WC_Order $order, string $slug ): string {
		$documents = $this->documents_for( $order );

		if ( ! isset( $documents[ $slug ] ) ) {
			throw new Render_Exception(
				sprintf( 'Unknown document "%s".', $slug )
			);
		}

		return $this->renderer->render( $documents[ $slug ] );
	}

	/**
	 * Stored document paths for an order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array<string,string> Readable paths, keyed by document slug.
	 */
	public function generated_documents( \WC_Order $order ): array {
		$stored = $order->get_meta( self::DOCUMENTS_META, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$documents = array();

		foreach ( $stored as $slug => $path ) {
			if ( ! is_string( $slug ) || ! is_string( $path ) || '' === $path ) {
				continue;
			}

			// Reject anything outside the plugin's own storage.
			if ( ! $this->filesystem->is_managed( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			$documents[ $slug ] = $path;
		}

		return $documents;
	}

	/**
	 * Whether every enabled document already exists for an order.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public function is_generated( \WC_Order $order ): bool {
		$existing = $this->generated_documents( $order );
		$expected = array_keys( $this->documents_for( $order ) );

		if ( array() === $expected ) {
			return false;
		}

		// Compare against what this order actually asks for, not every enabled
		// document, or a card-only order would be queued forever.
		foreach ( $expected as $slug ) {
			if ( ! isset( $existing[ $slug ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Delete an order's documents.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function delete( \WC_Order $order ): void {
		foreach ( $this->generated_documents( $order ) as $path ) {
			$this->filesystem->delete( $path );
		}

		$order->delete_meta_data( self::DOCUMENTS_META );
		$order->save_meta_data();
	}

	/**
	 * Persist the generated document map on the order.
	 *
	 * @param \WC_Order            $order   Order object.
	 * @param array<string,string> $results Paths keyed by document slug.
	 */
	private function store_documents( \WC_Order $order, array $results ): void {
		if ( array() === $results ) {
			return;
		}

		$order->update_meta_data( self::DOCUMENTS_META, $results );
		$order->save_meta_data();
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
