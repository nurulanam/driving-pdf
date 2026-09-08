<?php
/**
 * Rasterises the card to a bitmap for a direct-to-card printer.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * The card's front as a 24-bit RGB bitmap, one pixel per printer dot.
 *
 * A retransfer or dye-sublimation card printer — a Zebra ZC300, say — prints a
 * 300dpi grid and its driver resamples whatever it is handed to fit. Handing it
 * a PDF means the viewer rasterises first and the driver resamples second, and
 * the card's type is small enough that the strokes do not survive it: at 5.7pt
 * a stem is about 0.14mm, which is under two dots. Handing it a bitmap already
 * at 1011 x 638 removes both steps, so a stem drawn one dot wide prints one dot
 * wide.
 *
 * Only the front is produced. The back is a fixed design — the category legend
 * and the notes — so it is the same on every card and does not need producing
 * per order; the front is the one carrying the holder's details.
 *
 * This works from the rendered card PDF rather than redrawing the face, which
 * is what keeps the multilingual header's Arabic shaping and right-to-left runs
 * intact: nothing in PHP's own image library can shape Arabic, so redrawing was
 * never an option. Rasterising a PDF needs a tool the plugin does not bundle,
 * so the export is offered only where one is present — see self::rasteriser().
 *
 * Nothing is written where it can be served. Both the source PDF and the
 * intermediate raster are temporary files in the system temporary directory,
 * deleted before the bytes are returned.
 */
final class Card_Bitmap {

	/**
	 * Printer resolution, in dots per inch.
	 */
	public const DPI = 300;

	/**
	 * Document slug the front face is requested under.
	 */
	public const FRONT_SLUG = 'card-front-bmp';

	/**
	 * Page of the card PDF the front is on.
	 */
	private const FRONT_PAGE = 1;

	/**
	 * Card width and height in millimetres, matching Card_Document.
	 */
	private const WIDTH_MM  = 85.6;
	private const HEIGHT_MM = 53.98;

	/**
	 * Bitmap slugs, valued by the page of the card PDF each comes from.
	 *
	 * @return array<string,int>
	 */
	public static function faces(): array {
		return array( self::FRONT_SLUG => self::FRONT_PAGE );
	}

	/**
	 * Whether a slug names a bitmap face.
	 *
	 * @param string $slug Document slug.
	 *
	 * @return bool
	 */
	public static function is_face( string $slug ): bool {
		return isset( self::faces()[ $slug ] );
	}

	/**
	 * Filename a face is offered for download as.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $slug  Document slug.
	 *
	 * @return string
	 */
	public static function filename( \WC_Order $order, string $slug ): string {
		return sprintf( '%s-%s.bmp', $slug, $order->get_order_number() );
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
		 * Filters the rasteriser used to convert the card PDF to a bitmap.
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
	 * Convert a rendered card PDF into a bitmap face.
	 *
	 * @param string $pdf  The card PDF, as bytes.
	 * @param string $slug Which face to produce.
	 *
	 * @return string Bitmap bytes.
	 *
	 * @throws Render_Exception When no rasteriser is available, or conversion fails.
	 */
	public function bytes( string $pdf, string $slug ): string {
		$page = self::faces()[ $slug ] ?? 0;

		if ( 0 === $page ) {
			throw new Render_Exception( sprintf( 'Unknown card face "%s".', $slug ) );
		}

		$tool = self::rasteriser();

		if ( '' === $tool || ! self::is_available() ) {
			throw new Render_Exception(
				'Card bitmaps need the Imagick extension, Ghostscript or pdftoppm, and none could be used.'
			);
		}

		$source = $this->temp_file( 'pdf' );

		if ( '' === $source || false === file_put_contents( $source, $pdf ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			throw new Render_Exception( 'Could not stage the card PDF for rasterising.' );
		}

		try {
			return $this->convert( $source, $page, $tool );
		} finally {
			// Removed whether the conversion worked or threw, so a failure does
			// not leave the card's contents sitting in the temporary directory.
			wp_delete_file( $source );
		}
	}

	/**
	 * Rasterise one page of a staged PDF and return it as a bitmap.
	 *
	 * @param string $pdf  Path to the staged PDF.
	 * @param int    $page Page number, 1-based.
	 * @param string $tool Rasteriser to use.
	 *
	 * @return string Bitmap bytes.
	 *
	 * @throws Render_Exception When any step fails.
	 */
	private function convert( string $pdf, int $page, string $tool ): string {
		$raw = $this->rasterise( $pdf, $page, $tool );

		if ( '' === $raw || ! is_readable( $raw ) ) {
			throw new Render_Exception( sprintf( 'The card PDF could not be rasterised with %s.', $tool ) );
		}

		$source = @imagecreatefromstring( (string) file_get_contents( $raw ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		wp_delete_file( $raw );

		if ( false === $source ) {
			throw new Render_Exception( 'The rasterised card could not be read as an image.' );
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

		$target = $this->temp_file( 'bmp' );

		if ( '' === $target ) {
			imagedestroy( $canvas );

			throw new Render_Exception( 'Could not open a temporary file for the bitmap.' );
		}

		// Uncompressed: RLE bitmaps are not universally read by printer drivers.
		$written = imagebmp( $canvas, $target, false );

		imagedestroy( $canvas );

		if ( ! $written ) {
			wp_delete_file( $target );

			throw new Render_Exception( 'The bitmap could not be written.' );
		}

		$this->stamp_resolution( $target );

		$bytes = (string) file_get_contents( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		wp_delete_file( $target );

		return $bytes;
	}

	/**
	 * Run the chosen rasteriser over one page.
	 *
	 * @param string $pdf  Path to the staged PDF.
	 * @param int    $page Page number, 1-based.
	 * @param string $tool Rasteriser.
	 *
	 * @return string Path to a temporary raster, or an empty string.
	 */
	private function rasterise( string $pdf, int $page, string $tool ): string {
		$out = $this->temp_file( 'png' );

		if ( '' === $out ) {
			return '';
		}

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

	/**
	 * Reserve a temporary file with a given extension.
	 *
	 * The system temporary directory, not the uploads folder: these files exist
	 * for the length of one request and must never be reachable over HTTP.
	 *
	 * @param string $extension Extension, without the dot.
	 *
	 * @return string Absolute path, or an empty string when none could be made.
	 */
	private function temp_file( string $extension ): string {
		$dir = get_temp_dir();

		if ( '' === $dir || ! is_writable( $dir ) ) {
			return '';
		}

		$path = trailingslashit( $dir ) . uniqid( 'idta-card-', true ) . '.' . $extension;

		// Created now so the name cannot be claimed between here and its use.
		if ( false === file_put_contents( $path, '' ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return '';
		}

		return $path;
	}
}
