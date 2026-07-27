<?php
/**
 * Resolves the document artwork bundled with the plugin.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Supplies default artwork paths, each overridable by filter.
 *
 * The plugin ships the booklet scans, card faces, seals and branding under
 * `assets/img`, so a fresh install produces complete documents. Every lookup
 * still passes through a filter, which is how a site points at its own media
 * library instead.
 */
final class Artwork {

	/**
	 * Directory holding the scanned booklet pages, relative to the plugin.
	 */
	private const PAGES_DIR = 'assets/img/pages';

	/**
	 * Bundled branding files, keyed by artwork key.
	 *
	 * @var array<string,string>
	 */
	private const BRAND = array(
		'logo'      => 'assets/img/idp-single-logo.png',
		'signature' => 'assets/img/idp-signature.png',
		'stamp'     => 'assets/img/blank-stamp.png',
	);

	/**
	 * Bundled card faces, keyed by artwork key.
	 *
	 * @var array<string,string>
	 */
	private const CARD = array(
		'front' => 'assets/img/white-front.jpeg',
		'back'  => 'assets/img/white-back.jpeg',
		'stamp' => 'assets/img/blank-stamp.png',
	);

	/**
	 * Bundled category seals.
	 *
	 * @var array<string,string>
	 */
	private const SEAL = array(
		'granted' => 'assets/img/stamp.png',
		'blank'   => 'assets/img/non-sealed.png',
	);

	/**
	 * Memoised, numerically sorted list of bundled booklet pages.
	 *
	 * @var string[]|null
	 */
	private static ?array $pages = null;

	/**
	 * Booklet branding artwork.
	 *
	 * @param string $key One of 'logo', 'signature' or 'stamp'.
	 *
	 * @return string
	 */
	public static function brand( string $key ): string {
		$default = self::path( self::BRAND[ $key ] ?? '' );

		/**
		 * Filters brand artwork references.
		 *
		 * @param string $url Artwork reference.
		 * @param string $key Artwork key.
		 */
		return (string) apply_filters( 'idta_pdf_brand_url', $default, $key );
	}

	/**
	 * Card face artwork.
	 *
	 * @param string        $key      One of 'front', 'back' or 'stamp'.
	 * @param Card_Document $document Document instance.
	 *
	 * @return string
	 */
	public static function card( string $key, Card_Document $document ): string {
		$default = self::path( self::CARD[ $key ] ?? '' );

		/**
		 * Filters the card artwork references.
		 *
		 * @param string        $url      Artwork reference.
		 * @param string        $key      Artwork key.
		 * @param Card_Document $document Document instance.
		 */
		return (string) apply_filters( 'idta_pdf_card_artwork_url', $default, $key, $document );
	}

	/**
	 * Category seal artwork.
	 *
	 * @param bool $granted Whether the category is held.
	 *
	 * @return string
	 */
	public static function seal( bool $granted ): string {
		$default = self::path( self::SEAL[ $granted ? 'granted' : 'blank' ] );

		/**
		 * Filters the seal artwork used for permit categories.
		 *
		 * @param string $url     Artwork reference.
		 * @param bool   $granted Whether the category is held.
		 */
		return (string) apply_filters( 'idta_pdf_seal_url', $default, $granted );
	}

	/**
	 * Scanned pages printed before the holder details page.
	 *
	 * @param Booklet_Document $document Document instance.
	 *
	 * @return string[]
	 */
	public static function interior_pages( Booklet_Document $document ): array {
		$pages = self::pages();

		// The highest-numbered scan is the back cover and is printed after the
		// holder page; everything before it is interior.
		$default = array() !== $pages ? array_slice( $pages, 0, -1 ) : array();

		/**
		 * Filters a list of the booklet's static artwork pages.
		 *
		 * @param string[]         $pages    Artwork references, in print order.
		 * @param Booklet_Document $document Document instance.
		 */
		return (array) apply_filters( 'idta_pdf_booklet_interior_pages', $default, $document );
	}

	/**
	 * Pages printed after the holder details page.
	 *
	 * @param Booklet_Document $document Document instance.
	 *
	 * @return string[]
	 */
	public static function closing_pages( Booklet_Document $document ): array {
		$pages = self::pages();

		$default = array() !== $pages ? array_slice( $pages, -1 ) : array();

		/** This filter is documented in includes/class-artwork.php */
		return (array) apply_filters( 'idta_pdf_booklet_closing_pages', $default, $document );
	}

	/**
	 * Booklet page number carried by an artwork filename.
	 *
	 * Reads the last run of digits in the filename, so a path containing dated
	 * directories ("/uploads/2025/12/page-0006.jpg") still yields 6.
	 *
	 * @param string $file Artwork reference.
	 *
	 * @return int Page number, or 0 when the filename carries none.
	 */
	public static function page_number( string $file ): int {
		$name = pathinfo( parse_url( $file, PHP_URL_PATH ) ?? $file, PATHINFO_FILENAME );

		if ( ! is_string( $name ) || '' === $name ) {
			return 0;
		}

		if ( ! preg_match_all( '/\d+/', $name, $matches ) ) {
			return 0;
		}

		return (int) end( $matches[0] );
	}

	/**
	 * Bundled booklet scans, sorted by the page number in each filename.
	 *
	 * Filenames are inconsistently zero-padded ("page-002", "page-0003"), so
	 * they are ordered by extracted number rather than alphabetically.
	 *
	 * @return string[]
	 */
	private static function pages(): array {
		if ( null !== self::$pages ) {
			return self::$pages;
		}

		$dir = self::path( self::PAGES_DIR );

		// GLOB_BRACE is not available in every PHP build, so match broadly and
		// filter by extension instead.
		$found = '' !== $dir ? glob( $dir . '/*.*' ) : false;

		if ( ! is_array( $found ) || array() === $found ) {
			return self::$pages = array();
		}

		$numbered = array();

		foreach ( $found as $file ) {
			$extension = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );

			if ( ! in_array( $extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
				continue;
			}

			$number = self::page_number( $file );

			$numbered[] = array(
				// Unnumbered files sort last rather than first.
				'number' => 0 !== $number ? $number : PHP_INT_MAX,
				'file'   => $file,
			);
		}

		usort(
			$numbered,
			static fn( array $a, array $b ): int => $a['number'] <=> $b['number']
		);

		return self::$pages = array_column( $numbered, 'file' );
	}

	/**
	 * Absolute path to a bundled asset, or an empty string when absent.
	 *
	 * @param string $relative Path relative to the plugin directory.
	 *
	 * @return string
	 */
	private static function path( string $relative ): string {
		if ( '' === $relative ) {
			return '';
		}

		$path = plugin_dir_path( PLUGIN_FILE ) . ltrim( $relative, '/' );

		return file_exists( $path ) ? $path : '';
	}
}
