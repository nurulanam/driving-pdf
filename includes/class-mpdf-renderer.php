<?php
/**
 * mPDF rendering engine.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

use Mpdf\Mpdf;

defined( 'ABSPATH' ) || exit;

/**
 * Renders documents with mPDF.
 */
final class Mpdf_Renderer implements Renderer {

	/**
	 * Storage helper, for the engine's temporary directory.
	 *
	 * @var Filesystem
	 */
	private Filesystem $filesystem;

	/**
	 * Constructor.
	 *
	 * @param Filesystem $filesystem Storage helper.
	 */
	public function __construct( Filesystem $filesystem ) {
		$this->filesystem = $filesystem;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( Mpdf::class );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function name(): string {
		return 'mPDF';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $document Document to render.
	 *
	 * @return string
	 *
	 * @throws Render_Exception When mPDF is missing or rendering fails.
	 */
	public function render( Document $document ): string {
		if ( ! $this->is_available() ) {
			throw new Render_Exception(
				'mPDF is not installed. Run "composer install" in the idta-pdf plugin directory.'
			);
		}

		$margins = $document->margins();

		$config = array(
			// A format array is taken as [width, height] in millimetres, so a
			// card page stays 85.6 wide by 53.98 tall under portrait.
			'format'           => array( $document->width_mm(), $document->height_mm() ),
			'orientation'      => $document->orientation(),
			'margin_top'       => (float) $margins['top'],
			'margin_right'     => (float) $margins['right'],
			'margin_bottom'    => (float) $margins['bottom'],
			'margin_left'      => (float) $margins['left'],
			'margin_header'    => 0,
			'margin_footer'    => 0,
			'tempDir'          => $this->filesystem->temp_dir(),
			'mode'             => 'utf-8',
			// Every asset is embedded as a data URI, so the engine never needs
			// to make its own outbound request.
			'curlAllowUnsafeSslRequests' => false,
		);

		/**
		 * Filters the mPDF configuration for a document.
		 *
		 * @param array<string,mixed> $config   mPDF configuration.
		 * @param Document            $document Document instance.
		 */
		$config = (array) apply_filters( 'idta_pdf_mpdf_config', $config, $document );

		$html = $document->html();

		$this->ensure_pcre_capacity( strlen( $html ) );

		try {
			$mpdf = new Mpdf( $config );

			$mpdf->SetTitle( $document->title() );
			$mpdf->SetAuthor( get_bloginfo( 'name' ) );
			$mpdf->SetCreator( 'IDTA PDF ' . VERSION );

			// Keep artwork edge-to-edge and predictable.
			$mpdf->setAutoTopMargin   = false;
			$mpdf->setAutoBottomMargin = false;
			$mpdf->shrink_tables_to_fit = 1;

			/**
			 * Fires after the mPDF instance is configured and before writing.
			 *
			 * @param Mpdf     $mpdf     Engine instance.
			 * @param Document $document Document instance.
			 */
			do_action( 'idta_pdf_before_write', $mpdf, $document );

			$mpdf->WriteHTML( $html );

			$bytes = $mpdf->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
		} catch ( \Throwable $exception ) {
			throw new Render_Exception(
				sprintf(
					'mPDF failed to render the %s document: %s',
					$document->slug(),
					$exception->getMessage()
				),
				0,
				$exception
			);
		}

		if ( ! is_string( $bytes ) || '' === $bytes ) {
			throw new Render_Exception(
				sprintf( 'mPDF returned no output for the %s document.', $document->slug() )
			);
		}

		return $bytes;
	}

	/**
	 * Raise PCRE limits when a document is large enough to trip them.
	 *
	 * mPDF preprocesses the whole document with PCRE and aborts outright when
	 * the HTML is longer than pcre.backtrack_limit. Artwork is passed as local
	 * file paths precisely to keep the HTML small, but custom CSS or a long
	 * booklet can still approach the default 1,000,000 bytes.
	 *
	 * @param int $html_length Length of the HTML about to be rendered.
	 */
	private function ensure_pcre_capacity( int $html_length ): void {
		$required = $html_length * 2;

		foreach ( array( 'pcre.backtrack_limit', 'pcre.recursion_limit' ) as $setting ) {
			$current = (int) ini_get( $setting );

			if ( $current > 0 && $current < $required ) {
				// Best effort: ini_set fails silently on locked-down hosts, and
				// mPDF will report the limit itself if it still cannot proceed.
				ini_set( $setting, (string) $required );
			}
		}
	}
}
