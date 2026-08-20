<?php
/**
 * Base document.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * A single PDF deliverable: its geometry, stylesheet and body markup.
 */
abstract class Document {

	/**
	 * Order data.
	 *
	 * @var Order_Data
	 */
	protected Order_Data $data;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Image helper.
	 *
	 * @var Image_Helper
	 */
	protected Image_Helper $images;

	/**
	 * QR generator.
	 *
	 * @var QR_Generator
	 */
	protected QR_Generator $qr;

	/**
	 * Constructor.
	 *
	 * @param Order_Data   $data     Order data.
	 * @param Settings     $settings Settings repository.
	 * @param Image_Helper $images   Image helper.
	 * @param QR_Generator $qr       QR generator.
	 */
	public function __construct(
		Order_Data $data,
		Settings $settings,
		Image_Helper $images,
		QR_Generator $qr
	) {
		$this->data     = $data;
		$this->settings = $settings;
		$this->images   = $images;
		$this->qr       = $qr;
	}

	/**
	 * Document slug, used for filenames and settings keys.
	 *
	 * @return string
	 */
	abstract public function slug(): string;

	/**
	 * Human-readable document title.
	 *
	 * @return string
	 */
	abstract public function title(): string;

	/**
	 * Page width in millimetres.
	 *
	 * @return float
	 */
	abstract public function width_mm(): float;

	/**
	 * Page height in millimetres.
	 *
	 * @return float
	 */
	abstract public function height_mm(): float;

	/**
	 * Template file, relative to the templates directory.
	 *
	 * @return string
	 */
	abstract protected function template(): string;

	/**
	 * Page margins in millimetres: top, right, bottom, left.
	 *
	 * @return array{top:float,right:float,bottom:float,left:float}
	 */
	public function margins(): array {
		return array(
			'top'    => 0.0,
			'right'  => 0.0,
			'bottom' => 0.0,
			'left'   => 0.0,
		);
	}

	/**
	 * Page orientation. Both documents are declared portrait, so the width and
	 * height given above are used verbatim.
	 *
	 * @return string
	 */
	public function orientation(): string {
		return 'P';
	}

	/**
	 * Order data accessor, for templates.
	 *
	 * @return Order_Data
	 */
	public function data(): Order_Data {
		return $this->data;
	}

	/**
	 * Filename for the generated PDF.
	 *
	 * @return string
	 */
	public function filename(): string {
		$name = sprintf(
			'idp-%s-%d-%s.pdf',
			$this->slug(),
			$this->data->order_id(),
			sanitize_title( $this->data->last_name() ?: 'holder' )
		);

		/**
		 * Filters a generated document's filename.
		 *
		 * @param string   $name     Filename.
		 * @param Document $document Document instance.
		 */
		return sanitize_file_name( (string) apply_filters( 'idta_pdf_filename', $name, $this ) );
	}

	/**
	 * Base stylesheet shared by every document.
	 *
	 * Sets the 10pt default. Because this is emitted before the per-document
	 * and user stylesheets, any later rule of equal specificity wins.
	 *
	 * @return string
	 */
	protected function base_css(): string {
		$font_size = $this->settings->font_size();
		$family    = $this->settings->font_family();

		return sprintf(
			'html, body {
				margin: 0;
				padding: 0;
				font-family: %1$s;
				font-size: %2$spt;
				line-height: 1.35;
				color: #000000;
			}
			p, li, td, th, div, span { font-size: %2$spt; }
			p { margin: 0 0 %3$spt 0; }
			img { max-width: 100%%; }
			table { border-collapse: collapse; }
			.idta-clear { clear: both; }',
			$family,
			$this->format_number( $font_size ),
			$this->format_number( $font_size * 0.4 )
		);
	}

	/**
	 * Stylesheet supplied by the document itself.
	 *
	 * @return string
	 */
	protected function document_css(): string {
		$file = $this->asset_path( 'assets/css/' . $this->slug() . '.css' );

		if ( '' === $file ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = file_get_contents( $file );

		return is_string( $css ) ? $css : '';
	}

	/**
	 * Full stylesheet, in cascade order.
	 *
	 * Later blocks override earlier ones, so operator CSS always has the final
	 * say over the built-in defaults.
	 *
	 * @return string
	 */
	public function css(): string {
		$blocks = array(
			$this->page_css(),
			$this->base_css(),
			$this->document_css(),
			$this->settings->custom_css(),
			$this->settings->document_css( $this->slug() ),
		);

		$css = implode( "\n", array_filter( array_map( 'trim', $blocks ) ) );

		/**
		 * Filters the final stylesheet for a document.
		 *
		 * Appending here overrides everything, including the operator's CSS.
		 *
		 * @param string   $css      Stylesheet.
		 * @param Document $document Document instance.
		 */
		return (string) apply_filters( 'idta_pdf_document_css', $css, $this );
	}

	/**
	 * The @page rule matching this document's geometry.
	 *
	 * @return string
	 */
	protected function page_css(): string {
		$margins = $this->margins();

		return sprintf(
			'@page {
				size: %smm %smm;
				margin: %smm %smm %smm %smm;
			}',
			$this->format_number( $this->width_mm() ),
			$this->format_number( $this->height_mm() ),
			$this->format_number( (float) $margins['top'] ),
			$this->format_number( (float) $margins['right'] ),
			$this->format_number( (float) $margins['bottom'] ),
			$this->format_number( (float) $margins['left'] )
		);
	}

	/**
	 * Render the body markup.
	 *
	 * @return string
	 *
	 * @throws Render_Exception When the template is missing.
	 */
	public function body(): string {
		$template = $this->locate_template( $this->template() );

		if ( '' === $template ) {
			throw new Render_Exception(
				sprintf( 'Template "%s" could not be located.', $this->template() )
			);
		}

		$context = $this->context();

		ob_start();

		// Templates read $document, $data and $context from this scope.
		$document = $this;
		$data     = $this->data;

		include $template;

		$html = ob_get_clean();

		return is_string( $html ) ? $html : '';
	}

	/**
	 * Complete HTML document handed to the renderer.
	 *
	 * @return string
	 */
	public function html(): string {
		$html = sprintf(
			'<!DOCTYPE html><html><head><meta charset="utf-8"><title>%s</title><style>%s</style></head><body>%s</body></html>',
			esc_html( $this->title() ),
			$this->css(),
			$this->body()
		);

		/**
		 * Filters the complete HTML for a document before rendering.
		 *
		 * @param string   $html     Full HTML document.
		 * @param Document $document Document instance.
		 */
		return (string) apply_filters( 'idta_pdf_document_html', $html, $this );
	}

	/**
	 * Values made available to the template.
	 *
	 * @return array<string,mixed>
	 */
	protected function context(): array {
		$context = array(
			'order_id'        => $this->data->order_id(),
			'card_number'     => $this->data->card_number(),
			'full_name'       => $this->data->full_name(),
			'given_names'     => $this->data->given_names(),
			'last_name'       => $this->data->last_name(),
			'date_of_birth'   => $this->data->date_of_birth_formatted(),
			'gender'          => $this->data->gender(),
			'birth_country'   => $this->data->country_of_birth(),
			'residence'       => $this->data->country_of_residence(),
			'issuance'        => $this->data->country_of_issuance(),
			'license_number'  => $this->data->driver_license_number(),
			'categories'      => $this->data->license_categories(),
			'issue_date'      => $this->data->issue_date_formatted(),
			'expiry_date'     => $this->data->expiry_date_formatted(),
			'convention'      => $this->data->convention_label(),
			'photo'           => $this->images->embed( $this->data->passport_photo() ),
			'photo_gray'      => $this->images->embed(
				$this->data->passport_photo(),
				$this->settings->grayscale_ghost()
			),
			'signature'       => $this->images->embed( $this->data->signature() ),
			'license_front'   => $this->images->embed( $this->data->license_front() ),
			'license_back'    => $this->images->embed( $this->data->license_back() ),
			'all_categories'  => Order_Data::CATEGORIES,
		);

		/**
		 * Filters the template context for a document.
		 *
		 * @param array<string,mixed> $context  Template context.
		 * @param Document            $document Document instance.
		 */
		return (array) apply_filters( 'idta_pdf_template_context', $context, $this );
	}

	/**
	 * Locate a template, allowing theme overrides.
	 *
	 * Themes may override any template by placing it in
	 * `idta-pdf/<template>` inside the child or parent theme.
	 *
	 * @param string $template Template path relative to the templates directory.
	 *
	 * @return string Absolute path, or an empty string when not found.
	 */
	protected function locate_template( string $template ): string {
		$template = ltrim( $template, '/' );

		$override = locate_template( array( 'idta-pdf/' . $template ), false, false );

		if ( is_string( $override ) && '' !== $override && is_readable( $override ) ) {
			return $override;
		}

		$default = $this->asset_path( 'templates/' . $template );

		/**
		 * Filters the resolved template path.
		 *
		 * @param string   $default  Absolute template path.
		 * @param string   $template Relative template name.
		 * @param Document $document Document instance.
		 */
		return (string) apply_filters( 'idta_pdf_locate_template', $default, $template, $this );
	}

	/**
	 * Resolve a path inside the plugin directory.
	 *
	 * @param string $relative Relative path.
	 *
	 * @return string Absolute path, or an empty string when unreadable.
	 */
	protected function asset_path( string $relative ): string {
		$path = plugin_dir_path( PLUGIN_FILE ) . ltrim( $relative, '/' );

		return is_readable( $path ) ? $path : '';
	}

	/**
	 * Format a float for CSS without a trailing decimal point.
	 *
	 * @param float $value Value.
	 *
	 * @return string
	 */
	protected function format_number( float $value ): string {
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}
}
