<?php
/**
 * A4 permit booklet.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * The multi-page International Driving Permit booklet, A4 portrait.
 */
final class Booklet_Document extends Document {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'booklet';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return sprintf(
			/* translators: %s: order number. */
			__( 'International Driving Permit - Order %s', 'idta-pdf' ),
			$this->data->order()->get_order_number()
		);
	}

	/**
	 * A4 width.
	 *
	 * @return float
	 */
	public function width_mm(): float {
		return 210.0;
	}

	/**
	 * A4 height.
	 *
	 * @return float
	 */
	public function height_mm(): float {
		return 297.0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{top:float,right:float,bottom:float,left:float}
	 */
	public function margins(): array {
		/**
		 * Filters the booklet page margins, in millimetres.
		 *
		 * @param array{top:float,right:float,bottom:float,left:float} $margins Margins.
		 */
		return (array) apply_filters(
			'idta_pdf_booklet_margins',
			array(
				'top'    => 0.0,
				'right'  => 0.0,
				'bottom' => 0.0,
				'left'   => 0.0,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	protected function template(): string {
		return 'booklet.php';
	}

	/**
	 * Add booklet-specific values to the template context.
	 *
	 * @return array<string,mixed>
	 */
	protected function context(): array {
		$context = parent::context();

		$context['interior_pages'] = $this->describe_pages( Artwork::interior_pages( $this ) );
		$context['closing_pages']  = $this->describe_pages( Artwork::closing_pages( $this ) );
		$context['seal']           = $this->images->embed( Artwork::seal( true ) );
		$context['seal_blank']     = $this->images->embed( Artwork::seal( false ) );
		$context['logo']           = $this->images->embed( Artwork::brand( 'logo' ) );
		$context['authority_sign'] = $this->images->embed( Artwork::brand( 'signature' ) );
		$context['stamp']          = $this->images->embed( Artwork::brand( 'stamp' ) );

		return $context;
	}

	/**
	 * Describe how each booklet page should be rendered.
	 *
	 * A scanned page weighs 600-850 KB, so any page that has been rebuilt as
	 * HTML is rendered from that template instead — the text version costs a
	 * few KB. Conversion is incremental and needs no configuration: create
	 * `templates/pages/page-06.php` and page 6 stops using its scan. Delete the
	 * template and the scan comes back.
	 *
	 * An unreachable page is dropped rather than breaking the booklet.
	 *
	 * @param string[] $pages Artwork references, in print order.
	 *
	 * @return array<int,array{type:string,number:int,src?:string,template?:string}>
	 */
	private function describe_pages( array $pages ): array {
		$described = array();

		foreach ( $pages as $page ) {
			if ( ! is_string( $page ) || '' === trim( $page ) ) {
				continue;
			}

			$number = Artwork::page_number( $page );

			// A bespoke template wins, so a one-off page (the contracting-state
			// list, the index) can opt out of the shared language layout.
			$template = $this->page_template( $number );

			if ( '' !== $template ) {
				$described[] = array(
					'type'     => 'template',
					'number'   => $number,
					'template' => $template,
				);

				continue;
			}

			// Otherwise a translation page renders from the shared layout.
			$language = Language_Pages::get( $number );

			if ( null !== $language ) {
				$shared = $this->locate_template( 'pages/language-page.php' );

				if ( '' !== $shared && is_readable( $shared ) ) {
					$described[] = array(
						'type'     => 'language',
						'number'   => $number,
						'template' => $shared,
						'language' => $language,
					);

					continue;
				}
			}

			$src = $this->images->embed( $page );

			if ( '' !== $src ) {
				$described[] = array(
					'type'   => 'image',
					'number' => $number,
					'src'    => $src,
				);
			}
		}

		return $described;
	}

	/**
	 * Locate the HTML template that replaces a scanned page, if one exists.
	 *
	 * Both zero-padded and bare filenames are accepted, and themes may override
	 * either, so `pages/page-06.php` and `pages/page-6.php` both work.
	 *
	 * @param int $number Booklet page number.
	 *
	 * @return string Absolute template path, or an empty string.
	 */
	private function page_template( int $number ): string {
		if ( $number <= 0 ) {
			return '';
		}

		foreach ( array( sprintf( 'pages/page-%02d.php', $number ), sprintf( 'pages/page-%d.php', $number ) ) as $candidate ) {
			$path = $this->locate_template( $candidate );

			if ( '' !== $path && is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}
}
