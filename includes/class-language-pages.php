<?php
/**
 * Per-language content for the booklet's translation pages.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Booklet pages 4-22 are one page repeated in nineteen languages: identical
 * layout, translated strings. The layout lives in
 * `templates/pages/language-page.php`; only the strings live here.
 *
 * Adding a language is therefore pure data — no layout work. A page with no
 * definition here keeps rendering from its scan, so the booklet is always
 * complete and translations can land one at a time.
 *
 * `font` must be an mPDF font that covers the script:
 *   dejavuserif    Latin, Cyrillic, Turkish, Lithuanian, Vietnamese
 *   xbriyaz        Arabic
 *   sun-exta       Chinese, Japanese, Korean
 *   abyssinicasil  Amharic
 */
final class Language_Pages {

	/**
	 * Cached definitions.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private static ?array $pages = null;

	/**
	 * Whether a page has a language definition.
	 *
	 * @param int $number Booklet page number.
	 *
	 * @return bool
	 */
	public static function has( int $number ): bool {
		return isset( self::all()[ $number ] );
	}

	/**
	 * Definition for one page.
	 *
	 * @param int $number Booklet page number.
	 *
	 * @return array<string,mixed>|null
	 */
	public static function get( int $number ): ?array {
		return self::all()[ $number ] ?? null;
	}

	/**
	 * Every language definition, keyed by booklet page number.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function all(): array {
		if ( null !== self::$pages ) {
			return self::$pages;
		}

		$pages = self::load();

		/**
		 * Filters the booklet's language page definitions.
		 *
		 * Use this to add a translation without editing the plugin. Each entry
		 * needs the same keys as the definitions in this class.
		 *
		 * @param array<int,array<string,mixed>> $pages Keyed by page number.
		 */
		$pages = (array) apply_filters( 'idta_pdf_language_pages', $pages );

		self::$pages = array();

		foreach ( $pages as $number => $page ) {
			if ( is_array( $page ) ) {
				self::$pages[ (int) $number ] = self::normalise( $page );
			}
		}

		return self::$pages;
	}

	/**
	 * Whether mPDF has a font registered under this name.
	 *
	 * @param string $font Font name.
	 *
	 * @return bool
	 */
	private static function font_exists( string $font ): bool {
		if ( '' === $font || ! class_exists( \Mpdf\Config\FontVariables::class ) ) {
			// Without the engine present, accept the name as declared.
			return true;
		}

		static $registered = null;

		if ( null === $registered ) {
			$defaults = ( new \Mpdf\Config\FontVariables() )->getDefaults();

			$registered = is_array( $defaults['fontdata'] ?? null )
				? array_keys( $defaults['fontdata'] )
				: array();
		}

		return array() === $registered || in_array( $font, $registered, true );
	}

	/**
	 * Read the translation content from includes/data/language-pages.php.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function load(): array {
		$file = plugin_dir_path( PLUGIN_FILE ) . 'includes/data/language-pages.php';

		if ( ! is_readable( $file ) ) {
			return array();
		}

		$pages = require $file;

		return is_array( $pages ) ? $pages : array();
	}

	/**
	 * Apply defaults so templates can read every key unconditionally.
	 *
	 * @param array<string,mixed> $page Raw definition.
	 *
	 * @return array<string,mixed>
	 */
	private static function normalise( array $page ): array {
		$defaults = array(
			'code'        => '',
			'label'       => '',
			'folio'       => '',
			'font'        => 'dejavuserif',
			'rtl'         => false,
			'flag'        => array( '#cccccc', '#ffffff', '#cccccc' ),
			'lead_driver' => '',
			'lead_valid'  => '',
			'holder'      => array(),
			'categories'  => array(),
			'notes'       => array(),
			'exclusion'   => array(),
		);

		$page = array_merge( $defaults, $page );

		// An unregistered font renders every glyph as a hollow box, which is
		// easy to miss in a script nobody on the team reads. Fall back to the
		// default and say so in the log instead.
		if ( ! self::font_exists( (string) $page['font'] ) ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf(
						'Language page "%s" requests unregistered mPDF font "%s"; falling back to %s.',
						(string) $page['code'],
						(string) $page['font'],
						$defaults['font']
					),
					array( 'source' => 'idta-pdf' )
				);
			}

			$page['font'] = $defaults['font'];
		}

		$page['exclusion'] = array_merge(
			array(
				'title'     => '',
				'intro'     => '',
				'country'   => '',
				'reason'    => '',
				'seal'      => '',
				'place'     => '',
				'date'      => '',
				'signature' => '',
				'footnote'  => '',
			),
			(array) $page['exclusion']
		);

		return $page;
	}



}
