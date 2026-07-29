<?php
/**
 * Image localisation and transformation.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Turns remote and local image references into embeddable data URIs.
 *
 * The passport photo and signature are served from an external worker. Letting
 * the PDF engine fetch them itself is unreliable (no cookies, no retries, and
 * it silently drops the image on failure), so every asset is downloaded once,
 * cached, and embedded as a data URI.
 */
final class Image_Helper {

	/**
	 * Image MIME types accepted for embedding.
	 *
	 * @var array<string,string>
	 */
	private const ALLOWED_MIME = array(
		'image/jpeg' => 'jpg',
		'image/png'  => 'png',
		'image/gif'  => 'gif',
		'image/webp' => 'webp',
	);

	/**
	 * Maximum accepted asset size.
	 */
	private const MAX_BYTES = 12582912; // 12 MB.

	/**
	 * Storage helper.
	 *
	 * @var Filesystem
	 */
	private Filesystem $filesystem;

	/**
	 * In-request memo of resolved data URIs.
	 *
	 * @var array<string,string>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @param Filesystem $filesystem Storage helper.
	 */
	public function __construct( Filesystem $filesystem ) {
		$this->filesystem = $filesystem;
	}

	/**
	 * Resolve an image reference to a local path the PDF engine can read.
	 *
	 * This is the form documents should use. A local path keeps the generated
	 * HTML small — embedding 22 full-page scans as data URIs pushes it past
	 * pcre.backtrack_limit and mPDF refuses to render — while still guaranteeing
	 * the engine never makes its own outbound request, because remote assets
	 * have already been downloaded to the cache directory.
	 *
	 * @param string     $source    Absolute URL, site-relative path or local path.
	 * @param bool       $grayscale Whether to desaturate the image.
	 * @param float|null $aspect    Width divided by height to crop the image to,
	 *                              or null to leave its proportions alone.
	 *
	 * @return string Local absolute path, or an empty string when unavailable.
	 */
	public function embed( string $source, bool $grayscale = false, ?float $aspect = null ): string {
		$source = trim( $source );

		if ( '' === $source ) {
			return '';
		}

		$memo_key = ( $grayscale ? 'gp:' : 'cp:' ) . ( null !== $aspect ? $aspect . ':' : '' ) . $source;

		if ( isset( $this->memo[ $memo_key ] ) ) {
			return $this->memo[ $memo_key ];
		}

		$path = $this->localise( $source );

		if ( '' === $path ) {
			return $this->memo[ $memo_key ] = '';
		}

		if ( $grayscale ) {
			$path = $this->grayscale( $path );
		}

		if ( null !== $aspect && $aspect > 0.0 ) {
			$path = $this->crop_to_aspect( $path, $aspect );
		}

		// Reject anything the engine could not read anyway.
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return $this->memo[ $memo_key ] = '';
		}

		return $this->memo[ $memo_key ] = $path;
	}

	/**
	 * Resolve an image reference to a base64 data URI.
	 *
	 * Prefer embed(). Use this only for genuinely small assets: every byte is
	 * inlined into the HTML, and mPDF aborts once the document exceeds
	 * pcre.backtrack_limit.
	 *
	 * @param string $source    Absolute URL, site-relative path or local path.
	 * @param bool   $grayscale Whether to desaturate the image.
	 *
	 * @return string Data URI, or an empty string when unavailable.
	 */
	public function data_uri( string $source, bool $grayscale = false ): string {
		$source = trim( $source );

		if ( '' === $source ) {
			return '';
		}

		$memo_key = ( $grayscale ? 'g:' : 'c:' ) . $source;

		if ( isset( $this->memo[ $memo_key ] ) ) {
			return $this->memo[ $memo_key ];
		}

		$path = $this->localise( $source );

		if ( '' === $path ) {
			return $this->memo[ $memo_key ] = '';
		}

		if ( $grayscale ) {
			$path = $this->grayscale( $path );
		}

		return $this->memo[ $memo_key ] = $this->encode( $path );
	}

	/**
	 * Resolve an image reference to a local absolute path.
	 *
	 * @param string $source Absolute URL, site-relative path or local path.
	 *
	 * @return string Local path, or an empty string on failure.
	 */
	public function localise( string $source ): string {
		// Already a data URI: write it out so the engine reads a real file.
		if ( str_starts_with( $source, 'data:' ) ) {
			return $this->store_data_uri( $source );
		}

		$local = $this->to_local_path( $source );

		if ( '' !== $local ) {
			return $local;
		}

		if ( ! preg_match( '#^https?://#i', $source ) ) {
			return '';
		}

		return $this->download( $source );
	}

	/**
	 * Map a URL or relative path onto a readable local file.
	 *
	 * @param string $source Image reference.
	 *
	 * @return string
	 */
	private function to_local_path( string $source ): string {
		// Plain filesystem path.
		if ( ! preg_match( '#^(https?:)?//#i', $source ) && ! str_starts_with( $source, '/wp-content' ) ) {
			if ( is_readable( $source ) && is_file( $source ) ) {
				return $source;
			}
		}

		$uploads = wp_get_upload_dir();

		// Inside the uploads directory.
		if ( str_contains( $source, $uploads['baseurl'] ) ) {
			$relative = str_replace( $uploads['baseurl'], '', $source );
			$path     = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . ltrim( $relative, '/' ) );

			if ( is_readable( $path ) && is_file( $path ) ) {
				return $path;
			}
		}

		// Site-relative reference such as /wp-content/uploads/....
		$candidate = $source;

		if ( str_contains( $candidate, content_url() ) ) {
			$candidate = str_replace( content_url(), '/wp-content', $candidate );
		}

		if ( str_starts_with( $candidate, '/wp-content/' ) ) {
			$path = wp_normalize_path( WP_CONTENT_DIR . substr( $candidate, strlen( '/wp-content' ) ) );

			if ( is_readable( $path ) && is_file( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Download a remote asset into the cache directory.
	 *
	 * @param string $url Remote URL.
	 *
	 * @return string Cached path, or an empty string on failure.
	 */
	private function download( string $url ): string {
		$cache_key = md5( $url );
		$existing  = glob( $this->filesystem->cache_dir() . '/' . $cache_key . '.*' );

		if ( is_array( $existing ) && array() !== $existing ) {
			$cached   = $existing[0];
			$modified = filemtime( $cached );

			if ( is_file( $cached ) && false !== $modified && $modified > ( time() - DAY_IN_SECONDS ) ) {
				return $cached;
			}
		}

		/**
		 * Filters the HTTP arguments used to fetch remote document assets.
		 *
		 * @param array<string,mixed> $args Request arguments.
		 * @param string              $url  Asset URL.
		 */
		$args = apply_filters(
			'idta_pdf_asset_request_args',
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => true,
				'user-agent'  => 'IDTA-PDF/' . VERSION . '; ' . home_url(),
			),
			$url
		);

		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( sprintf( 'Asset fetch failed for %s: %s', $url, $response->get_error_message() ) );

			return '';
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			$this->log(
				sprintf(
					'Asset fetch returned HTTP %d for %s',
					wp_remote_retrieve_response_code( $response ),
					$url
				)
			);

			return '';
		}

		$body = wp_remote_retrieve_body( $response );

		if ( '' === $body || strlen( $body ) > self::MAX_BYTES ) {
			$this->log( sprintf( 'Asset rejected (empty or oversized) for %s', $url ) );

			return '';
		}

		$extension = $this->extension_from_bytes( $body );

		if ( '' === $extension ) {
			$this->log( sprintf( 'Asset rejected (unsupported image type) for %s', $url ) );

			return '';
		}

		$path = $this->filesystem->cache_dir() . '/' . $cache_key . '.' . $extension;

		if ( ! $this->filesystem->put_contents( $path, $body ) ) {
			$this->log( sprintf( 'Could not cache asset for %s', $url ) );

			return '';
		}

		return $path;
	}

	/**
	 * Persist an inline data URI to the cache directory.
	 *
	 * @param string $source Data URI.
	 *
	 * @return string
	 */
	private function store_data_uri( string $source ): string {
		if ( ! preg_match( '#^data:(image/[a-z.+-]+);base64,(.+)$#is', $source, $matches ) ) {
			return '';
		}

		$mime = strtolower( $matches[1] );

		if ( ! isset( self::ALLOWED_MIME[ $mime ] ) ) {
			return '';
		}

		$bytes = base64_decode( $matches[2], true );

		if ( false === $bytes || '' === $bytes ) {
			return '';
		}

		$path = $this->filesystem->cache_dir() . '/' . md5( $source ) . '.' . self::ALLOWED_MIME[ $mime ];

		if ( is_file( $path ) ) {
			return $path;
		}

		return $this->filesystem->put_contents( $path, $bytes ) ? $path : '';
	}

	/**
	 * Encode a local image as a data URI.
	 *
	 * @param string $path Local path.
	 *
	 * @return string
	 */
	private function encode( string $path ): string {
		if ( ! is_readable( $path ) || ! is_file( $path ) ) {
			return '';
		}

		$info = wp_getimagesize( $path );

		if ( ! is_array( $info ) || ! isset( $info['mime'] ) || ! isset( self::ALLOWED_MIME[ $info['mime'] ] ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bytes = file_get_contents( $path );

		if ( false === $bytes || '' === $bytes ) {
			return '';
		}

		return 'data:' . $info['mime'] . ';base64,' . base64_encode( $bytes );
	}

	/**
	 * Produce a grayscale copy of a local image.
	 *
	 * Returns the original path when GD is unavailable or the format is not
	 * supported, so a missing extension degrades to a colour image rather than
	 * to no image.
	 *
	 * @param string $path Local path.
	 *
	 * @return string
	 */
	public function grayscale( string $path ): string {
		if ( ! function_exists( 'imagefilter' ) || ! function_exists( 'imagecreatefromjpeg' ) ) {
			return $path;
		}

		$info = wp_getimagesize( $path );

		if ( ! is_array( $info ) || ! isset( $info['mime'] ) ) {
			return $path;
		}

		$target = preg_replace( '/(\.[a-z0-9]+)$/i', '-gray$1', $path );

		if ( ! is_string( $target ) || $target === $path ) {
			return $path;
		}

		if ( is_file( $target ) ) {
			return $target;
		}

		$image = match ( $info['mime'] ) {
			'image/jpeg' => imagecreatefromjpeg( $path ),
			'image/png'  => imagecreatefrompng( $path ),
			'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : false,
			default      => false,
		};

		if ( ! $image instanceof \GdImage ) {
			return $path;
		}

		if ( 'image/png' === $info['mime'] || 'image/webp' === $info['mime'] ) {
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}

		imagefilter( $image, IMG_FILTER_GRAYSCALE );

		$saved = match ( $info['mime'] ) {
			'image/jpeg' => imagejpeg( $image, $target, 92 ),
			'image/png'  => imagepng( $image, $target ),
			'image/webp' => function_exists( 'imagewebp' ) && imagewebp( $image, $target ),
			default      => false,
		};

		imagedestroy( $image );

		return $saved && is_file( $target ) ? $target : $path;
	}

	/**
	 * Centre-crop an image to a given width-to-height ratio.
	 *
	 * This is `object-fit: cover`, done before the engine sees the file, because
	 * mPDF has neither: it ignores `object-fit`, and it ignores `height` on an
	 * `<img>` altogether — an image is drawn at the declared width and whatever
	 * height its own proportions dictate. A portrait upload is therefore as tall
	 * as it likes, and on a card that pushed the signature 3mm down the face.
	 *
	 * Returns the original path when GD is unavailable, the format is unsupported,
	 * or the image is already the right shape, so a missing extension degrades to
	 * an uncropped image rather than to no image.
	 *
	 * @param string $path   Local path.
	 * @param float  $aspect Target width divided by height.
	 *
	 * @return string
	 */
	public function crop_to_aspect( string $path, float $aspect ): string {
		if ( $aspect <= 0.0 || ! function_exists( 'imagecreatetruecolor' ) ) {
			return $path;
		}

		$info = wp_getimagesize( $path );

		if ( ! is_array( $info ) || ! isset( $info['mime'] ) || empty( $info[0] ) || empty( $info[1] ) ) {
			return $path;
		}

		$width  = (int) $info[0];
		$height = (int) $info[1];

		// Already within a pixel of the wanted shape; nothing to gain.
		if ( abs( ( $width / $height ) - $aspect ) < 0.005 ) {
			return $path;
		}

		$suffix = '-crop' . str_replace( '.', '', (string) round( $aspect, 4 ) );
		$target = preg_replace( '/(\.[a-z0-9]+)$/i', $suffix . '$1', $path );

		if ( ! is_string( $target ) || $target === $path ) {
			return $path;
		}

		if ( is_file( $target ) ) {
			return $target;
		}

		$source = match ( $info['mime'] ) {
			'image/jpeg' => function_exists( 'imagecreatefromjpeg' ) ? imagecreatefromjpeg( $path ) : false,
			'image/png'  => function_exists( 'imagecreatefrompng' ) ? imagecreatefrompng( $path ) : false,
			'image/webp' => function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $path ) : false,
			default      => false,
		};

		if ( ! $source instanceof \GdImage ) {
			return $path;
		}

		// Take the largest centred rectangle of the wanted shape.
		if ( ( $width / $height ) > $aspect ) {
			$crop_h = $height;
			$crop_w = (int) round( $height * $aspect );
		} else {
			$crop_w = $width;
			$crop_h = (int) round( $width / $aspect );
		}

		$crop_w = max( 1, min( $crop_w, $width ) );
		$crop_h = max( 1, min( $crop_h, $height ) );

		$canvas = imagecreatetruecolor( $crop_w, $crop_h );

		if ( ! $canvas instanceof \GdImage ) {
			imagedestroy( $source );

			return $path;
		}

		if ( 'image/png' === $info['mime'] || 'image/webp' === $info['mime'] ) {
			imagealphablending( $canvas, false );
			imagesavealpha( $canvas, true );
		}

		$copied = imagecopy(
			$canvas,
			$source,
			0,
			0,
			(int) round( ( $width - $crop_w ) / 2 ),
			(int) round( ( $height - $crop_h ) / 2 ),
			$crop_w,
			$crop_h
		);

		$saved = $copied && match ( $info['mime'] ) {
			'image/jpeg' => imagejpeg( $canvas, $target, 92 ),
			'image/png'  => imagepng( $canvas, $target ),
			'image/webp' => function_exists( 'imagewebp' ) && imagewebp( $canvas, $target ),
			default      => false,
		};

		imagedestroy( $source );
		imagedestroy( $canvas );

		return $saved && is_file( $target ) ? $target : $path;
	}

	/**
	 * Detect a supported image extension from raw bytes.
	 *
	 * Sniffs content rather than trusting the URL extension or the remote
	 * Content-Type header.
	 *
	 * Done entirely in memory. This used to write the bytes to a temp file from
	 * wp_tempnam() and call wp_getimagesize() on it — but wp_tempnam() lives in
	 * wp-admin/includes/file.php, which WordPress does not load on the frontend.
	 * Checkout *is* the frontend, so every remote asset made automatic generation
	 * die with "Call to undefined function wp_tempnam()", while the identical code
	 * succeeded from the order screen because admin has that file loaded. Nothing
	 * here may depend on an admin-only function.
	 *
	 * @param string $bytes Raw file contents.
	 *
	 * @return string Extension without a dot, or an empty string.
	 */
	private function extension_from_bytes( string $bytes ): string {
		$extension = $this->extension_from_signature( $bytes );

		if ( '' === $extension || ! function_exists( 'getimagesizefromstring' ) ) {
			return $extension;
		}

		// The signature only proves how the bytes start. This confirms they
		// actually decode, so a file merely wearing an image header is rejected.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- warns on malformed data, which is precisely what is being tested for.
		$info = @getimagesizefromstring( $bytes );

		if ( ! is_array( $info ) || ! isset( $info['mime'] ) ) {
			return '';
		}

		return self::ALLOWED_MIME[ $info['mime'] ] ?? '';
	}

	/**
	 * Match the leading magic number of an accepted image format.
	 *
	 * @param string $bytes Raw file contents.
	 *
	 * @return string Extension without a dot, or an empty string.
	 */
	private function extension_from_signature( string $bytes ): string {
		if ( str_starts_with( $bytes, "\xFF\xD8\xFF" ) ) {
			return 'jpg';
		}

		if ( str_starts_with( $bytes, "\x89PNG\r\n\x1A\n" ) ) {
			return 'png';
		}

		if ( str_starts_with( $bytes, 'GIF87a' ) || str_starts_with( $bytes, 'GIF89a' ) ) {
			return 'gif';
		}

		if ( str_starts_with( $bytes, 'RIFF' ) && 'WEBP' === substr( $bytes, 8, 4 ) ) {
			return 'webp';
		}

		return '';
	}

	/**
	 * Log a diagnostic message.
	 *
	 * @param string $message Message.
	 */
	private function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( $message, array( 'source' => 'idta-pdf' ) );
		}
	}
}
