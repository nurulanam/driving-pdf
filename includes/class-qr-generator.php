<?php
/**
 * QR code generation for the permit and details links.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the verification links and renders them as embeddable QR codes.
 */
final class QR_Generator {

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether a QR library is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return class_exists( QrCode::class ) && class_exists( PngWriter::class );
	}

	/**
	 * Public link to the digital permit.
	 *
	 * @param Order_Data $data Order data.
	 *
	 * @return string
	 */
	public function permit_url( Order_Data $data ): string {
		return $this->build_url( '/idp/', $data, 'permit' );
	}

	/**
	 * Public link to the holder's details page.
	 *
	 * @param Order_Data $data Order data.
	 *
	 * @return string
	 */
	public function details_url( Order_Data $data ): string {
		return $this->build_url( '/show-details/', $data, 'details' );
	}

	/**
	 * QR code for the permit link, as a data URI.
	 *
	 * @param Order_Data $data Order data.
	 * @param int        $size Pixel size.
	 *
	 * @return string
	 */
	public function permit_qr( Order_Data $data, int $size = 300 ): string {
		return $this->render( $this->permit_url( $data ), $size );
	}

	/**
	 * QR code for the details link, as a data URI.
	 *
	 * @param Order_Data $data Order data.
	 * @param int        $size Pixel size.
	 *
	 * @return string
	 */
	public function details_qr( Order_Data $data, int $size = 220 ): string {
		return $this->render( $this->details_url( $data ), $size );
	}

	/**
	 * Build a signed verification URL.
	 *
	 * @param string     $path    Path on the verification site.
	 * @param Order_Data $data    Order data.
	 * @param string     $context Secret context.
	 *
	 * @return string
	 */
	private function build_url( string $path, Order_Data $data, string $context ): string {
		$base = $this->settings->qr_base_url();

		if ( '' === $base ) {
			$base = untrailingslashit( home_url() );
		}

		$key = $this->encrypt( (string) $data->order_id(), $this->settings->qr_secret( $context ) );

		if ( '' === $key ) {
			return '';
		}

		$url = add_query_arg( 'entry_key', $key, $base . $path );

		/**
		 * Filters a verification URL before it is encoded into a QR code.
		 *
		 * @param string     $url     Verification URL.
		 * @param string     $context Either 'permit' or 'details'.
		 * @param Order_Data $data    Order data.
		 */
		return (string) apply_filters( 'idta_pdf_verification_url', $url, $context, $data );
	}

	/**
	 * Encrypt a payload into a URL-safe token.
	 *
	 * Keeps the AES-256-CBC scheme used by the previous templates so existing
	 * verification endpoints continue to resolve the tokens, while deriving a
	 * proper 32-byte key and rejecting a weak secret.
	 *
	 * @param string $payload Plain payload.
	 * @param string $secret  Shared secret.
	 *
	 * @return string URL-safe token, or an empty string on failure.
	 */
	public function encrypt( string $payload, string $secret ): string {
		if ( ! function_exists( 'openssl_encrypt' ) || '' === $secret ) {
			return '';
		}

		$iv = substr( md5( $secret, true ), 0, 16 );

		$encrypted = openssl_encrypt( $payload, 'aes-256-cbc', $secret, OPENSSL_RAW_DATA, $iv );

		if ( false === $encrypted ) {
			return '';
		}

		return rtrim( strtr( base64_encode( $encrypted ), '+/', '-_' ), '=' );
	}

	/**
	 * Decrypt a URL-safe token produced by encrypt().
	 *
	 * @param string $token  URL-safe token.
	 * @param string $secret Shared secret.
	 *
	 * @return string Plain payload, or an empty string on failure.
	 */
	public function decrypt( string $token, string $secret ): string {
		if ( ! function_exists( 'openssl_decrypt' ) || '' === $secret || '' === $token ) {
			return '';
		}

		$base64 = strtr( $token, '-_', '+/' );
		$padded = str_pad( $base64, (int) ( ceil( strlen( $base64 ) / 4 ) * 4 ), '=', STR_PAD_RIGHT );
		$binary = base64_decode( $padded, true );

		if ( false === $binary ) {
			return '';
		}

		$iv = substr( md5( $secret, true ), 0, 16 );

		$plain = openssl_decrypt( $binary, 'aes-256-cbc', $secret, OPENSSL_RAW_DATA, $iv );

		return false === $plain ? '' : $plain;
	}

	/**
	 * Medium error correction, in whichever form the installed library wants.
	 *
	 * v5 exposes an enum case; v4 a value object.
	 *
	 * @return mixed
	 */
	private function error_correction_level() {
		$enum = 'Endroid\\QrCode\\ErrorCorrectionLevel';

		if ( enum_exists( $enum ) ) {
			return constant( $enum . '::Medium' );
		}

		$class = 'Endroid\\QrCode\\ErrorCorrectionLevel\\ErrorCorrectionLevelMedium';

		return class_exists( $class ) ? new $class() : null;
	}

	/**
	 * Round-block-size mode, in whichever form the library wants.
	 *
	 * Shrink, not Margin. Both keep the modules on whole pixels, but Margin holds
	 * the requested pixel size and pads the difference as a border — 7.7% of the
	 * image on each side, varying with the module count, so the code drew about
	 * 16% smaller than the box it was given and by an amount that changed with the
	 * length of the encoded URL. Shrink instead trims the image to whole modules,
	 * making the file all code: the size given in CSS is the size that prints.
	 *
	 * That leaves no quiet zone inside the image. The pale card and page around it
	 * supply one, which is how the printed artwork does it too.
	 *
	 * @return mixed
	 */
	private function round_block_size_mode() {
		$enum = 'Endroid\\QrCode\\RoundBlockSizeMode';

		if ( enum_exists( $enum ) ) {
			return constant( $enum . '::Shrink' );
		}

		$class = 'Endroid\\QrCode\\RoundBlockSizeMode\\RoundBlockSizeModeShrink';

		return class_exists( $class ) ? new $class() : null;
	}

	/**
	 * Render a URL as a PNG data URI.
	 *
	 * @param string $url  Target URL.
	 * @param int    $size Pixel size.
	 *
	 * @return string
	 */
	private function render( string $url, int $size ): string {
		if ( '' === $url || ! $this->is_available() ) {
			return '';
		}

		$size = max( 60, min( 1200, $size ) );

		try {
			// The low-level QrCode + PngWriter API is stable across
			// endroid/qr-code v4 and v5; Builder::create() is not. The
			// constructor signature is identical in both, but v4 passes value
			// objects where v5 passes enum cases.
			$qr_code = new QrCode(
				data: $url,
				encoding: new Encoding( 'UTF-8' ),
				errorCorrectionLevel: $this->error_correction_level(),
				size: $size,
				margin: 0,
				roundBlockSizeMode: $this->round_block_size_mode(),
				foregroundColor: new Color( 0, 0, 0 ),
				backgroundColor: new Color( 255, 255, 255 )
			);

			return ( new PngWriter() )->write( $qr_code )->getDataUri();
		} catch ( \Throwable $exception ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'QR generation failed: ' . $exception->getMessage(),
					array( 'source' => 'idta-pdf' )
				);
			}

			return '';
		}
	}
}
