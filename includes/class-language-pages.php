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
 *   sun-exta       Chinese, Japanese
 *   unbatang       Korean
 *   freeserif      Devanagari (Hindi), Amharic
 *   garuda         Thai
 *
 * Fonts registered through `idta_pdf_font_data` count too, so a closer match
 * for a script can be dropped in without touching this class.
 */
final class Language_Pages {

	/**
	 * Codepoint ranges whose glyphs are as wide as they are tall: ideographs,
	 * Hangul syllables, kana, and the fullwidth forms.
	 *
	 * @var array<int,array{0:int,1:int}>
	 */
	private const WIDE_RANGES = array(
		array( 0x1100, 0x115F ),
		array( 0x2E80, 0x303E ),
		array( 0x3041, 0x33FF ),
		array( 0x3400, 0x4DBF ),
		array( 0x4E00, 0x9FFF ),
		array( 0xA000, 0xA4CF ),
		array( 0xAC00, 0xD7A3 ),
		array( 0xF900, 0xFAFF ),
		array( 0xFE30, 0xFE4F ),
		array( 0xFF00, 0xFF60 ),
		array( 0xFFE0, 0xFFE6 ),
	);

	/**
	 * Cached definitions.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private static ?array $pages = null;

	/**
	 * Cached list of font names mPDF will accept.
	 *
	 * @var string[]|null
	 */
	private static ?array $fonts = null;

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
	 * Estimated printed width of a short label, in millimetres.
	 *
	 * The exclusion block's fill-in rules print with the rule starting straight
	 * after the label, so the label column has to be as wide as its own text and
	 * no wider. mPDF cannot be asked for that: a shrink-to-content cell needs the
	 * rule cell at 100%, which reads as an overflow, and mPDF answers an overflow
	 * by shrinking the whole table's type — so "Signature" printed a size smaller
	 * than "Lieu" on the same page. Sizing the column here instead keeps every
	 * label at its declared size.
	 *
	 * The estimate is deliberately a little generous, since the cost of guessing
	 * high is a slightly wider gap while the cost of guessing low is a wrapped or
	 * shrunken label. Measured against rendered output it lands 1-3mm over, which
	 * is about the gap the printed booklet leaves anyway.
	 *
	 * @param string $text    Label text.
	 * @param float  $font_pt Font size the label is set in.
	 *
	 * @return float Width in millimetres, zero for an empty label.
	 */
	public static function label_width_mm( string $text, float $font_pt ): float {
		if ( '' === $text ) {
			return 0.0;
		}

		$ems       = 0.0;
		$character = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( (array) $character as $glyph ) {
			$ems += self::glyph_ems( (string) $glyph );
		}

		// A point is 0.352778mm; the extra 2mm is the gap before the rule.
		return round( $ems * $font_pt * 0.352778, 1 ) + 2.0;
	}

	/**
	 * Width of one character as a fraction of the font size.
	 *
	 * @param string $glyph Single character.
	 *
	 * @return float
	 */
	private static function glyph_ems( string $glyph ): float {
		// A combining mark sits on the preceding glyph and adds no width, which
		// matters for Thai and Devanagari where they are a third of the string.
		if ( 1 === preg_match( '/^\p{Mn}$/u', $glyph ) ) {
			return 0.0;
		}

		if ( ' ' === $glyph ) {
			return 0.28;
		}

		if ( function_exists( 'mb_ord' ) ) {
			$code = mb_ord( $glyph, 'UTF-8' );

			if ( false !== $code ) {
				foreach ( self::WIDE_RANGES as $range ) {
					if ( $code >= $range[0] && $code <= $range[1] ) {
						return 1.0;
					}
				}
			}
		}

		if ( 1 === preg_match( '/^\p{Lu}$/u', $glyph ) ) {
			return 0.72;
		}

		if ( 1 === preg_match( '/^\p{Ll}$/u', $glyph ) ) {
			return 0.52;
		}

		// Arabic, Ethiopic, Devanagari and Thai bases, digits and punctuation.
		return 0.55;
	}

	/**
	 * Whether mPDF has a font registered under this name.
	 *
	 * @param string $font Font name.
	 *
	 * @return bool
	 */
	private static function font_exists( string $font ): bool {
		if ( '' === $font ) {
			return true;
		}

		if ( null === self::$fonts ) {
			// The same registry the renderer hands mPDF: the bundled faces plus
			// anything the site added. Deliberately not mPDF's own list, which
			// names forty fonts whose files this distribution does not carry.
			self::$fonts = array_keys( Fonts::registry() );
		}

		return array() === self::$fonts || in_array( $font, self::$fonts, true );
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
			'weight'      => 'normal',
			'rtl'         => false,
			'flag'        => array( '#cccccc', '#ffffff', '#cccccc' ),
			'flag_image'  => '',
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

		// Only the two weights mPDF can act on; anything else would end up in
		// the markup verbatim.
		if ( ! in_array( $page['weight'], array( 'normal', 'bold' ), true ) ) {
			$page['weight'] = $defaults['weight'];
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
