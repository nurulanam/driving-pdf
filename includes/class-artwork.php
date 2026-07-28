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
	 * Directory holding the language flags, relative to the plugin.
	 *
	 * Files are named after the language label a translation page prints, so
	 * `Français.png` is the flag on the French page.
	 */
	private const FLAGS_DIR = 'assets/img/Flag';

	/**
	 * Bundled branding files, keyed by artwork key.
	 *
	 * @var array<string,string>
	 */
	private const BRAND = array(
		// Cover: the plain seal, then the authorised signature beneath it.
		'cover_logo' => 'assets/img/blank-stamp.png',
		'signature'  => 'assets/img/idta-signature.png',
		// The horizontal lockup, used on the language index.
		'logo'       => 'assets/img/Idta logo.png',
		// Holder page: stamped across the corner of the portrait.
		'stamp'      => 'assets/img/blank-stamp.png',
		// Back cover: the dotted world map, the full lockup, the UN emblem and
		// the two QR codes.
		'back_map'   => 'assets/img/24 map.png',
		'wordmark'   => 'assets/img/Idta full 24.png',
		'un_emblem'  => 'assets/img/UNCE.ORG.png',
		'qr_left'    => 'assets/img/QR CODE1 24.png',
		'qr_right'   => 'assets/img/QR CODE2 24.jpg',
	);

	/**
	 * Bundled card faces, keyed by artwork key.
	 *
	 * @var array<string,string>
	 */
	private const CARD = array(
		'front' => 'assets/img/white-front.jpg',
		'back'  => 'assets/img/white-back.jpg',
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
	 * @param string $key One of the keys in self::BRAND.
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
	 * Flag artwork for a translation page.
	 *
	 * Named after the language label rather than an ISO code so the bundled files
	 * read the same way the page does. A language with no file falls back to the
	 * drawn tricolour, or to printing its own name.
	 *
	 * @param string $label Language label, e.g. 'Français'.
	 *
	 * @return string Artwork reference, empty when there is none.
	 */
	public static function flag( string $label ): string {
		$default = '' === $label
			? ''
			: self::path( self::FLAGS_DIR . '/' . $label . '.png' );

		/**
		 * Filters the flag artwork for a translation page.
		 *
		 * @param string $url   Artwork reference.
		 * @param string $label Language label.
		 */
		return (string) apply_filters( 'idta_pdf_flag_url', $default, $label );
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
	 * Every page this booklet prints now has a template or a language entry, so
	 * no scan is actually required for a stock install — but interior_pages()
	 * and closing_pages() still need *some* list of page numbers to loop over,
	 * since Booklet_Document::describe_pages() only sees numbers, not the fact
	 * that a template exists. Falling back to synthesise() when no scans are on
	 * disk keeps that loop populated instead of silently emitting zero pages.
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
			return self::$pages = self::synthesise();
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
	 * Page numbers covered by a bundled template or a language entry, standing in
	 * for scans that are not on disk.
	 *
	 * Each is a placeholder string rather than a real path — describe_pages()
	 * resolves the number to a template or language page before it ever tries to
	 * treat the string as an image, so nothing attempts to read it as a file. A
	 * page number covered by neither quietly renders nothing, exactly as an
	 * unreadable scan would have.
	 *
	 * @return string[]
	 */
	private static function synthesise(): array {
		$numbers = array_keys( Language_Pages::all() );

		$dir = self::path( 'templates/pages' );

		if ( '' !== $dir ) {
			foreach ( (array) glob( $dir . '/page-*.php' ) as $file ) {
				$number = self::page_number( $file );

				if ( 0 !== $number ) {
					$numbers[] = $number;
				}
			}
		}

		$numbers = array_unique( $numbers );

		sort( $numbers );

		return array_map(
			static fn( int $number ): string => sprintf( 'page-%02d', $number ),
			$numbers
		);
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
