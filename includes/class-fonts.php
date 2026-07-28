<?php
/**
 * The set of fonts the plugin ships and will ask mPDF for.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * mPDF registers around forty fonts and ships all of them, which is 88MB — most
 * of an installable plugin's weight for scripts the booklet never prints. The
 * distribution therefore carries only the faces named below, and this class is
 * what makes that safe: the renderer hands mPDF exactly this registry, so a font
 * whose file is not present cannot be requested. `bin/build-plugin-zip.sh` reads
 * the same list to decide what to keep, so the two cannot drift apart.
 *
 * Adding a language means adding its face here (or, without touching the plugin,
 * through `idta_pdf_font_data` and `idta_pdf_font_directories`).
 */
final class Fonts {

	/**
	 * Face used when nothing else is specified, and for the CSS generic families.
	 */
	public const DEFAULT_FONT = 'dejavuserif';

	/**
	 * Faces mPDF may fall back to for a character the requested font lacks.
	 *
	 * @var string[]
	 */
	public const FALLBACKS = array( 'dejavusans', 'freeserif', 'sun-exta' );

	/**
	 * Bundled faces, in mPDF's fontdata shape, keyed by the name used in CSS.
	 *
	 * The `sip-ext` key mPDF gives sun-exta is deliberately absent: it points at
	 * Sun-ExtB, a 17MB face covering only rare Unicode plane-2 ideographs that
	 * this booklet never sets.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function bundled(): array {
		return array(
			// Latin, Cyrillic, Greek, Turkish, Lithuanian, Vietnamese: the body
			// face for fifteen of the nineteen translation pages.
			'dejavuserif'   => array(
				'R'  => 'DejaVuSerif.ttf',
				'B'  => 'DejaVuSerif-Bold.ttf',
				'I'  => 'DejaVuSerif-Italic.ttf',
				'BI' => 'DejaVuSerif-BoldItalic.ttf',
			),
			// The cover's sans, and a fallback for anything dejavuserif lacks.
			'dejavusans'    => array(
				'R'          => 'DejaVuSans.ttf',
				'B'          => 'DejaVuSans-Bold.ttf',
				'I'          => 'DejaVuSans-Oblique.ttf',
				'BI'         => 'DejaVuSans-BoldOblique.ttf',
				'useOTL'     => 255,
				'useKashida' => 75,
			),
			// Arabic (page 5).
			'xbriyaz'       => array(
				'R'          => 'XB Riyaz.ttf',
				'B'          => 'XB RiyazBd.ttf',
				'I'          => 'XB RiyazIt.ttf',
				'BI'         => 'XB RiyazBdIt.ttf',
				'useOTL'     => 255,
				'useKashida' => 75,
			),
			// Chinese (page 8) and Japanese (page 19).
			'sun-exta'      => array(
				'R' => 'Sun-ExtA.ttf',
			),
			// Korean (page 22).
			'unbatang'      => array(
				'R' => 'UnBatang_0613.ttf',
			),
			// Amharic (page 13).
			'abyssinicasil' => array(
				'R'      => 'Abyssinica_SIL.ttf',
				'useOTL' => 255,
			),
			// Devanagari, for Hindi (page 16).
			'freeserif'     => array(
				'R'          => 'FreeSerif.ttf',
				'B'          => 'FreeSerifBold.ttf',
				'I'          => 'FreeSerifItalic.ttf',
				'BI'         => 'FreeSerifBoldItalic.ttf',
				'useOTL'     => 255,
				'useKashida' => 75,
			),
			// Thai (page 21).
			'garuda'        => array(
				'R'      => 'Garuda.ttf',
				'B'      => 'Garuda-Bold.ttf',
				'I'      => 'Garuda-Oblique.ttf',
				'BI'     => 'Garuda-BoldOblique.ttf',
				'useOTL' => 255,
			),
		);
	}

	/**
	 * The registry mPDF is given: bundled faces plus anything the site adds.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function registry(): array {
		/**
		 * Filters mPDF's font registry.
		 *
		 * Keyed by the name used in CSS, each entry being mPDF's fontdata shape,
		 * e.g. `array( 'notoserifethiopic' => array( 'R' => 'NotoSerifEthiopic-Regular.ttf' ) )`.
		 * A name registered here is accepted by Language_Pages as well; point
		 * mPDF at the file with `idta_pdf_font_directories`.
		 *
		 * @param array<string,array<string,mixed>> $fonts Font definitions.
		 */
		$custom = apply_filters( 'idta_pdf_font_data', array() );

		return array_merge( self::bundled(), is_array( $custom ) ? $custom : array() );
	}

	/**
	 * Font filenames the bundled faces need, for the build script.
	 *
	 * @return string[]
	 */
	public static function files(): array {
		$files = array();

		foreach ( self::bundled() as $face ) {
			foreach ( $face as $key => $value ) {
				// Skip the numeric shaping options; only style keys name a file.
				if ( is_string( $value ) && '' !== $value ) {
					$files[] = $value;
				}
			}
		}

		sort( $files );

		return array_values( array_unique( $files ) );
	}
}
