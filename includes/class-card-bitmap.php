<?php
/**
 * Rasterises the card to bitmaps for a direct-to-card printer.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * The card's two faces as 24-bit RGB bitmaps, one pixel per printer dot.
 *
 * A retransfer or dye-sublimation card printer — a Zebra ZC300, say — prints a
 * 300dpi grid and its driver resamples whatever it is handed to fit. Handing it
 * a PDF means the viewer rasterises first and the driver resamples second, and
 * the card's type is small enough that the strokes do not survive it: at 5.7pt
 * a stem is about 0.14mm, which is under two dots. Handing it a bitmap already
 * at 1011 x 638 removes both steps, so a stem drawn one dot wide prints one dot
 * wide.
 *
 * A face is written as its own file because that is how such a printer is fed:
 * one image per side, no page furniture.
 *
 * Rasterising a PDF needs a tool the plugin does not bundle, so this is offered
 * only where one is present — see self::rasteriser(). The card PDF stays the
 * single source of truth either way: these are renders of it, not a second
 * layout, which is what keeps the multilingual header's Arabic shaping and
 * right-to-left runs intact. Nothing in PHP's own image library can shape
 * Arabic, so redrawing the face directly was never an option.
 */
final class Card_Bitmap {

	/**
	 * Printer resolution, in dots per inch.
	 */
	public const DPI = 300;

	/**
	 * Card width and height in millimetres, matching Card_Document.
	 */
	private const WIDTH_MM  = 85.6;
	private const HEIGHT_MM = 53.98;

	/**
	 * Faces to write, keyed by the slug each is stored under, valued by the page
	 * of the card PDF each comes from.
	 *
	 * Front only. The back is a fixed design — the category legend and the
	 * notes — so it is the same on every card and does not need producing per
	 * order; the front is the one carrying the holder's details. Adding the
	 * back again is a single line here, since nothing else names a face.
	 *
	 * @var array<string,int>
	 */
	private const FACES = array(
		'card-front-bmp' => 1,
	);

	/**
	 * Storage helper.
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
	 * Pixel size of one face at self::DPI.
	 *
	 * @return array{0:int,1:int}
	 */
	public static function pixels(): array {
		return array(
			(int) round( self::WIDTH_MM / 25.4 * self::DPI ),
			(int) round( self::HEIGHT_MM / 25.4 * self::DPI ),
		);
	}

	/**
	 * Whether this machine can rasterise a PDF at all.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		// imagebmp() needs PHP 7.2 with GD; both are checked because a GD built
		// without it would fail only at the point of writing the file.
		return function_exists( 'imagebmp' )
			&& function_exists( 'imagecreatefromstring' )
			&& '' !== self::rasteriser();
	}

	/**
	 * Which rasteriser to use, in order of preference.
	 *
	 * Imagick first because it needs no shell. Both command-line tools are
	 * common on a VPS and absent from most shared hosting, which is why the
	 * whole feature is optional rather than assumed.
	 *
	 * @return string One of 'imagick', 'gs', 'pdftoppm', or an empty string.
	 */
	public static function rasteriser(): string {
		/**
		 * Filters the rasteriser used to convert the card PDF to bitmaps.
		 *
		 * @param string $tool One of 'imagick', 'gs', 'pdftoppm' or ''.
		 */
		$forced = (string) apply_filters( 'idta_pdf_card_bitmap_rasteriser', '' );

		if ( '' !== $forced ) {
			return $forced;
		}

		if ( extension_loaded( 'imagick' ) && class_exists( '\Imagick' ) ) {
			try {
				// Imagick reads PDF only through Ghostscript, and a policy may
				// forbid it, so ask rather than assume.
				if ( array() !== array_intersect( array( 'PDF' ), \Imagick::queryFormats( 'PDF' ) ) ) {
					return 'imagick';
				}
			} catch ( \Throwable $exception ) {
				// Fall through to the command-line tools.
			}
		}

		foreach ( array( 'gs', 'pdftoppm' ) as $binary ) {
			if ( '' !== self::binary_path( $binary ) ) {
				return $binary;
			}
		}

		return '';
	}

	/**
	 * Locate a command-line tool, or an empty string when it cannot be run.
	 *
	 * @param string $binary Tool name.
	 *
	 * @return string
	 */
	private static function binary_path( string $binary ): string {
		if ( ! function_exists( 'exec' ) ) {
			return '';
		}

		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );

		if ( in_array( 'exec', $disabled, true ) ) {
			return '';
		}

		$found = array();

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Guarded above; the argument is a literal.
		@exec( 'command -v ' . escapeshellarg( $binary ) . ' 2>/dev/null', $found ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$path = isset( $found[0] ) ? trim( (string) $found[0] ) : '';

		return ( '' !== $path && is_executable( $path ) ) ? $path : '';
	}

	/**
	 * Write the card's bitmap faces.
	 *
	 * @param \WC_Order $order Order the card belongs to.
	 * @param string    $pdf   Absolute path to the card PDF.
	 *
	 * @return array<string,string> Absolute paths, keyed by face slug. Empty when
	 *                              no rasteriser is available.
	 */
	public function render( \WC_Order $order, string $pdf ): array {
		$tool = self::rasteriser();

		if ( '' === $tool || ! self::is_available() || ! is_readable( $pdf ) ) {
			return array();
		}

		$dir = $this->filesystem->order_dir( $order );

		if ( ! $this->filesystem->ensure_dir( $dir ) ) {
			return array();
		}

		$written = array();

		foreach ( self::FACES as $slug => $page ) {
			$path = $this->render_face( $pdf, $page, trailingslashit( $dir ) . $slug . '.bmp', $tool );

			if ( '' !== $path ) {
				$written[ $slug ] = $path;
			}
		}

		$this->prune( $dir, array_keys( $written ) );

		return $written;
	}

	/**
	 * Delete bitmap faces this version no longer produces.
	 *
	 * A site that generated a back face under an earlier version would
	 * otherwise keep a 1.9 MB file on disk and keep offering it for download
	 * long after it stopped being rebuilt, slowly going stale against the card
	 * it was rendered from.
	 *
	 * @param string   $dir  Order directory.
	 * @param string[] $keep Face slugs just written.
	 */
	private function prune( string $dir, array $keep ): void {
		foreach ( (array) glob( trailingslashit( $dir ) . '*-bmp.bmp' ) as $file ) {
			if ( ! in_array( basename( (string) $file, '.bmp' ), $keep, true ) ) {
				wp_delete_file( (string) $file );
			}
		}
	}

	/**
	 * Rasterise one page and write it as a 24-bit bitmap.
	 *
	 * @param string $pdf    Source PDF.
	 * @param int    $page   Page number, 1-based.
	 * @param string $target Bitmap path to write.
	 * @param string $tool   Rasteriser to use.
	 *
	 * @return string The path written, or an empty string on failure.
	 */
	private function render_face( string $pdf, int $page, string $target, string $tool ): string {
		/*
		 * Reuse a face that is already newer than the card it came from.
		 * generate() is called on every order transition and copies an existing
		 * card PDF through untouched, so without this each of those would pay
		 * for two Ghostscript runs and rewrite 4 MB to produce the same two
		 * files. A forced regeneration rewrites the PDF, which makes it the
		 * newer of the two and brings both faces back through here.
		 */
		if ( is_readable( $target ) && filemtime( $target ) >= filemtime( $pdf ) ) {
			return $target;
		}

		$raw = $this->rasterise( $pdf, $page, $tool );

		if ( '' === $raw || ! is_readable( $raw ) ) {
			return '';
		}

		$source = @imagecreatefromstring( (string) file_get_contents( $raw ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		wp_delete_file( $raw );

		if ( false === $source ) {
			return '';
		}

		list( $width, $height ) = self::pixels();

		/*
		 * Forced to the exact pixel size. A rasteriser asked for 300dpi on an
		 * 85.6 x 53.98mm page returns 1011 x 638 or 1012 x 638 depending on how
		 * it rounds the fractional millimetre, and a driver handed an off-by-one
		 * image rescales the whole thing — which is the one thing this is here
		 * to avoid.
		 */
		$canvas = imagecreatetruecolor( $width, $height );

		imagefill( $canvas, 0, 0, imagecolorallocate( $canvas, 255, 255, 255 ) );

		$source_width  = imagesx( $source );
		$source_height = imagesy( $source );

		/*
		 * A rounding difference is cropped rather than rescaled. Rescaling 1012
		 * to 1011 resamples every pixel in the image to lose one column of it;
		 * cropping touches nothing but that column. On a test page of 0.3mm
		 * rules the crop kept 0.2% more ink — small, but it is free, and the
		 * pixels it leaves alone are the ones the printer reproduces exactly.
		 *
		 * Anything beyond a rounding difference is a genuinely different page
		 * size, so that is scaled to fit instead of being silently cut off.
		 */
		if ( abs( $source_width - $width ) <= 2 && abs( $source_height - $height ) <= 2 ) {
			imagecopy(
				$canvas,
				$source,
				0,
				0,
				0,
				0,
				min( $source_width, $width ),
				min( $source_height, $height )
			);
		} else {
			imagecopyresampled(
				$canvas,
				$source,
				0,
				0,
				0,
				0,
				$width,
				$height,
				$source_width,
				$source_height
			);
		}

		imagedestroy( $source );

		// Uncompressed: RLE bitmaps are not universally read by printer drivers.
		$ok = imagebmp( $canvas, $target, false );

		imagedestroy( $canvas );

		if ( ! $ok ) {
			return '';
		}

		$this->stamp_resolution( $target );

		return $target;
	}

	/**
	 * Run the chosen rasteriser over one page.
	 *
	 * @param string $pdf  Source PDF.
	 * @param int    $page Page number, 1-based.
	 * @param string $tool Rasteriser.
	 *
	 * @return string Path to a temporary raster, or an empty string.
	 */
	private function rasterise( string $pdf, int $page, string $tool ): string {
		$out = trailingslashit( $this->filesystem->temp_dir() ) . 'idta-card-' . wp_generate_password( 8, false ) . '.png';

		if ( 'imagick' === $tool ) {
			try {
				$image = new \Imagick();

				$image->setResolution( self::DPI, self::DPI );
				$image->readImage( $pdf . '[' . ( $page - 1 ) . ']' );
				$image->setImageBackgroundColor( 'white' );

				$flat = method_exists( $image, 'mergeImageLayers' )
					? $image->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN )
					: $image;

				$flat->setImageFormat( 'png' );
				$flat->writeImage( $out );

				return $out;
			} catch ( \Throwable $exception ) {
				return '';
			}
		}

		$binary = self::binary_path( $tool );

		if ( '' === $binary ) {
			return '';
		}

		if ( 'gs' === $tool ) {
			$command = sprintf(
				'%s -dNOPAUSE -dBATCH -dSAFER -dQUIET -sDEVICE=png16m -r%d -dFirstPage=%d -dLastPage=%d -dUseCropBox -sOutputFile=%s %s 2>/dev/null',
				escapeshellarg( $binary ),
				self::DPI,
				$page,
				$page,
				escapeshellarg( $out ),
				escapeshellarg( $pdf )
			);
		} else {
			// pdftoppm appends its own suffix, so it is given a prefix instead.
			$prefix  = preg_replace( '/\.png$/', '', $out );
			$command = sprintf(
				'%s -r %d -f %d -l %d -png -singlefile %s %s 2>/dev/null',
				escapeshellarg( $binary ),
				self::DPI,
				$page,
				$page,
				escapeshellarg( $pdf ),
				escapeshellarg( (string) $prefix )
			);
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec -- Guarded in binary_path(); every argument is escaped.
		@exec( $command ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		return is_readable( $out ) ? $out : '';
	}

	/**
	 * Record the resolution in the bitmap's own header.
	 *
	 * GD writes zero for both pixels-per-metre fields, which leaves a driver to
	 * guess the physical size. 300dpi is 11,811 pixels per metre.
	 *
	 * @param string $path Bitmap path.
	 */
	private function stamp_resolution( string $path ): void {
		$ppm = (int) round( self::DPI / 0.0254 );

		$handle = fopen( $path, 'r+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return;
		}

		// Bytes 38 and 42 of a BITMAPINFOHEADER: X and Y pixels per metre.
		fseek( $handle, 38 );
		fwrite( $handle, pack( 'V2', $ppm, $ppm ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	}
}
