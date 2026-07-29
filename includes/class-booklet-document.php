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
	 * Memoised language definitions with their flag artwork resolved.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private ?array $languages = null;

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

		// The index page needs the same flags the translation pages print, so the
		// languages are resolved once and shared rather than embedded twice.
		$context['languages'] = $this->languages();

		/*
		 * Cropped square before the engine sees it: mPDF ignores `height` on an
		 * image and `object-fit` entirely, drawing it at the declared width and
		 * whatever height its own proportions give. A portrait upload was printing
		 * two-thirds again as tall as its frame.
		 */
		$context['photo'] = $this->images->embed( $this->data->passport_photo(), false, 1.0 );

		$context['interior_pages'] = $this->describe_pages( Artwork::interior_pages( $this ) );
		$context['closing_pages']  = $this->describe_pages( Artwork::closing_pages( $this ) );
		$context['seal']           = $this->images->embed( Artwork::seal( true ) );
		$context['seal_blank']     = $this->images->embed( Artwork::seal( false ) );

		foreach ( array(
			'cover_logo'     => 'cover_logo',
			'logo'           => 'logo',
			'authority_sign' => 'signature',
			'stamp'          => 'stamp',
			'back_map'       => 'back_map',
			'wordmark'       => 'wordmark',
			'un_emblem'      => 'un_emblem',
			'qr_left'        => 'qr_left',
			'qr_right'       => 'qr_right',
		) as $key => $artwork ) {
			$context[ $key ] = $this->images->embed( Artwork::brand( $artwork ) );
		}

		/**
		 * Filters the contact details printed on the back cover.
		 *
		 * @param string $site Website, without a scheme.
		 */
		$context['brand_site'] = (string) apply_filters( 'idta_pdf_brand_site', 'idta.com' );

		/**
		 * Filters the support address printed on the back cover.
		 *
		 * @param string $email Support address.
		 */
		$context['brand_email'] = (string) apply_filters( 'idta_pdf_brand_email', 'support@idta.com' );

		return $context;
	}

	/**
	 * Language definitions with their flag artwork resolved, keyed by page number.
	 *
	 * Flags are declared as a path or URL; resolving them here means the templates
	 * only ever deal with something the engine can read locally, and the index page
	 * and the translation pages cannot end up with different artwork.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function languages(): array {
		if ( null !== $this->languages ) {
			return $this->languages;
		}

		$this->languages = array();

		foreach ( Language_Pages::all() as $number => $language ) {
			$flag = (string) $language['flag_image'];

			if ( '' === $flag ) {
				$flag = Artwork::flag( (string) $language['label'] );
			}

			$language['flag_image'] = '' === $flag ? '' : $this->images->embed( $flag );

			$this->languages[ $number ] = $language;
		}

		return $this->languages;
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

			// Otherwise a translation page renders from the shared layout, with the
			// flag artwork already resolved by languages().
			$language = $this->languages()[ $number ] ?? null;

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
