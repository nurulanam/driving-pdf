<?php
/**
 * Read-only view over the IDP meta stored on a WooCommerce order.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Normalises `_idp_*` order meta into typed, template-ready values.
 */
final class Order_Data {

	/**
	 * Meta key => human label, as written by the checkout flow.
	 *
	 * @var array<string,string>
	 */
	public const FIELDS = array(
		'_idp_order_from'            => 'Order From',
		'_idp_first_name'            => 'First Name',
		'_idp_middle_name'           => 'Middle Name',
		'_idp_last_name'             => 'Last Name',
		'_idp_date_of_birth'         => 'Date of Birth',
		'_idp_gender'                => 'Gender',
		'_idp_country_of_birth'      => 'Country of Birth',
		'_idp_country_of_residence'  => 'Country of Residence',
		'_idp_driver_license_number' => 'Driver License Number',
		'_idp_country_of_issuance'   => 'Country of Issuance',
		'_idp_license_category'      => 'License Category',
		'_idp_validity_years'        => 'Validity Years',
		'_idp_format'                => 'IDP Format',
		'_idp_assets'                => 'Assets Folder',
	);

	/**
	 * Filename of each image within an order's asset folder.
	 *
	 * `_idp_assets` holds only the folder — "2026/08/01/063701-213" — because all
	 * four uploads land in it under these same names every time. Storing the
	 * folder once replaces the four full paths this plugin used to read, and is
	 * the whole point of the scheme.
	 *
	 * @var array<string,string>
	 */
	public const ASSET_FILES = array(
		'passport_photo' => 'portrait.jpg',
		'license_front'  => 'license-front.jpg',
		'license_back'   => 'license-back.jpg',
		'signature'      => 'signature.png',
	);

	/**
	 * Retired per-image meta keys, still read so existing orders keep their
	 * images, keyed as in self::ASSET_FILES.
	 *
	 * Nothing writes these any more and they are not offered on the order screen.
	 * They are read only as a fallback for orders already in the database from
	 * before `_idp_assets`; once no such orders matter, this and the branch in
	 * resolve_asset() that uses it can go.
	 *
	 * @var array<string,string>
	 */
	private const LEGACY_ASSET_FIELDS = array(
		'passport_photo' => '_idp_passport_photo',
		'license_front'  => '_idp_license_front',
		'license_back'   => '_idp_license_back',
		'signature'      => '_idp_signature',
	);

	/**
	 * Meta keys that must be present for an order to be considered an IDP order.
	 *
	 * @var string[]
	 */
	private const REQUIRED_FIELDS = array( '_idp_last_name', '_idp_date_of_birth' );

	/**
	 * Meta key holding the generated permit number.
	 */
	public const CARD_NUMBER_META = '_idp_card_number';

	/**
	 * Where an order was taken, and the bucket its uploads live in.
	 *
	 * The checkout stores `_idp_assets` as a path relative to one of these —
	 * "2026/08/01/063701-213" — so the host cannot be inferred from the value
	 * itself and has to come from `_idp_order_from`.
	 *
	 * Keys are compared lower-case. This is the fallback used only when the
	 * settings screen has none configured (see Settings::asset_sources()) — an
	 * administrator manages the real list under IDTA PDF → Verification.
	 * Add another front end programmatically with the `idta_pdf_order_sources`
	 * filter, which is applied on top of whichever list is in effect.
	 *
	 * @var array<string,string>
	 */
	public const SOURCES = array(
		'idta' => 'https://idta-upload.shamim66ewu.workers.dev/files/',
		'idpa' => 'https://pub-1c2e77688359483ca692ff2d8369b41b.r2.dev/',
	);

	/**
	 * Countries party to the Vienna Convention on Road Traffic of 8 November 1968.
	 *
	 * Keyed by ISO 3166-1 alpha-2 code.
	 *
	 * @var array<string,string>
	 */
	private const VIENNA_1968 = array(
		'AL' => 'Albania',
		'AD' => 'Andorra',
		'AM' => 'Armenia',
		'AT' => 'Austria',
		'AZ' => 'Azerbaijan',
		'BH' => 'Bahrain',
		'BY' => 'Belarus',
		'BE' => 'Belgium',
		'BA' => 'Bosnia and Herzegovina',
		'BG' => 'Bulgaria',
		'CV' => 'Cabo Verde',
		'HR' => 'Croatia',
		'CU' => 'Cuba',
		'CZ' => 'Czechia',
		'DK' => 'Denmark',
		'EE' => 'Estonia',
		'FI' => 'Finland',
		'FR' => 'France',
		'GE' => 'Georgia',
		'DE' => 'Germany',
		'GR' => 'Greece',
		'HU' => 'Hungary',
		'IR' => 'Iran',
		'IT' => 'Italy',
		'KZ' => 'Kazakhstan',
		'KW' => 'Kuwait',
		'LV' => 'Latvia',
		'LI' => 'Liechtenstein',
		'LT' => 'Lithuania',
		'LU' => 'Luxembourg',
		'MC' => 'Monaco',
		'ME' => 'Montenegro',
		'MA' => 'Morocco',
		'MK' => 'North Macedonia',
		'NO' => 'Norway',
		'PL' => 'Poland',
		'PT' => 'Portugal',
		'RO' => 'Romania',
		'RU' => 'Russia',
		'SM' => 'San Marino',
		'SK' => 'Slovakia',
		'SI' => 'Slovenia',
		'ZA' => 'South Africa',
		'ES' => 'Spain',
		'SE' => 'Sweden',
		'CH' => 'Switzerland',
		'TJ' => 'Tajikistan',
		'TN' => 'Tunisia',
		'TR' => 'Turkey',
		'TM' => 'Turkmenistan',
		'UA' => 'Ukraine',
		'AE' => 'United Arab Emirates',
		'UZ' => 'Uzbekistan',
		'VN' => 'Viet Nam',
	);

	/**
	 * All permit categories printed on the documents, in order.
	 *
	 * @var string[]
	 */
	public const CATEGORIES = array( 'A', 'B', 'C', 'D', 'E' );

	/**
	 * Order object.
	 *
	 * @var \WC_Order
	 */
	private \WC_Order $order;

	/**
	 * Raw meta values, keyed by meta key.
	 *
	 * @var array<string,string>
	 */
	private array $meta = array();

	/**
	 * Constructor.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function __construct( \WC_Order $order ) {
		$this->order = $order;

		// The retired keys are loaded alongside the current ones so an order from
		// before `_idp_assets` still resolves its images. See LEGACY_ASSET_FIELDS.
		$keys = array_merge( array_keys( self::FIELDS ), array_values( self::LEGACY_ASSET_FIELDS ) );

		foreach ( $keys as $key ) {
			$value = $order->get_meta( $key, true );

			$this->meta[ $key ] = is_scalar( $value ) ? trim( (string) $value ) : '';
		}
	}

	/**
	 * Determine whether an order carries IDP meta.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public static function has_data( \WC_Order $order ): bool {
		foreach ( self::REQUIRED_FIELDS as $key ) {
			$value = $order->get_meta( $key, true );

			if ( ! is_scalar( $value ) || '' === trim( (string) $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Order accessor.
	 *
	 * @return \WC_Order
	 */
	public function order(): \WC_Order {
		return $this->order;
	}

	/**
	 * Order ID.
	 *
	 * @return int
	 */
	public function order_id(): int {
		return $this->order->get_id();
	}

	/**
	 * Raw meta value.
	 *
	 * @param string $key      Meta key.
	 * @param string $fallback Value returned when the meta is empty.
	 *
	 * @return string
	 */
	public function get( string $key, string $fallback = '' ): string {
		$value = $this->meta[ $key ] ?? '';

		return '' !== $value ? $value : $fallback;
	}

	/**
	 * First name.
	 *
	 * @return string
	 */
	public function first_name(): string {
		return $this->get( '_idp_first_name' );
	}

	/**
	 * Middle name.
	 *
	 * @return string
	 */
	public function middle_name(): string {
		return $this->get( '_idp_middle_name' );
	}

	/**
	 * Last name.
	 *
	 * @return string
	 */
	public function last_name(): string {
		return $this->get( '_idp_last_name' );
	}

	/**
	 * Given names, middle name included when present.
	 *
	 * @return string
	 */
	public function given_names(): string {
		return trim( $this->first_name() . ' ' . $this->middle_name() );
	}

	/**
	 * Full name, surname last.
	 *
	 * @return string
	 */
	public function full_name(): string {
		return trim( $this->given_names() . ' ' . $this->last_name() );
	}

	/**
	 * Gender.
	 *
	 * @return string
	 */
	public function gender(): string {
		return $this->get( '_idp_gender' );
	}

	/**
	 * Country of birth.
	 *
	 * @return string
	 */
	public function country_of_birth(): string {
		return $this->get( '_idp_country_of_birth' );
	}

	/**
	 * Country of residence.
	 *
	 * @return string
	 */
	public function country_of_residence(): string {
		return $this->get( '_idp_country_of_residence' );
	}

	/**
	 * Country that issued the domestic licence.
	 *
	 * @return string
	 */
	public function country_of_issuance(): string {
		return $this->get( '_idp_country_of_issuance' );
	}

	/**
	 * Domestic driver licence number.
	 *
	 * @return string
	 */
	public function driver_license_number(): string {
		return $this->get( '_idp_driver_license_number' );
	}

	/**
	 * Requested IDP format (booklet, card, both).
	 *
	 * @return string
	 */
	public function format(): string {
		return strtolower( $this->get( '_idp_format' ) );
	}

	/**
	 * Front end the order came from, as a key of self::sources().
	 *
	 * @return string Source key, or an empty string when it is not recognised.
	 */
	public function order_from(): string {
		$source = strtolower( trim( $this->get( '_idp_order_from' ) ) );

		if ( isset( self::sources()[ $source ] ) ) {
			return $source;
		}

		/**
		 * Filters the source assumed for an order that does not name one.
		 *
		 * Orders placed before `_idp_order_from` existed stored absolute URLs, so
		 * they resolve without this. It only matters for a relative path with no
		 * source recorded, where the bucket genuinely cannot be worked out.
		 *
		 * @param string     $source Source key, or an empty string to give up.
		 * @param Order_Data $data   Order data.
		 */
		$fallback = (string) apply_filters( 'idta_pdf_default_order_source', 'idta', $this );

		return isset( self::sources()[ $fallback ] ) ? $fallback : '';
	}

	/**
	 * Base URL the order's uploads are stored under.
	 *
	 * @return string Base URL with a trailing slash, or an empty string.
	 */
	public function asset_base_url(): string {
		$source = $this->order_from();

		return '' !== $source ? self::sources()[ $source ] : '';
	}

	/**
	 * Upload sources, keyed by the value stored in `_idp_order_from`.
	 *
	 * @return array<string,string>
	 */
	public static function sources(): array {
		static $sources = null;

		if ( null !== $sources ) {
			return $sources;
		}

		// The settings screen is the normal way to manage this list; the function
		// may not exist in an isolated test script that loads this class on its
		// own, so the hardcoded default still applies there.
		$configured = function_exists( __NAMESPACE__ . '\\plugin' )
			? plugin()->settings()->asset_sources()
			: self::SOURCES;

		/**
		 * Filters the upload sources an order may have been taken through.
		 *
		 * Keyed by the lower-case value stored in `_idp_order_from`, each a base
		 * URL `_idp_assets` is relative to. Applied on top of whatever is
		 * configured on the settings screen (IDTA PDF → Verification).
		 *
		 * @param array<string,string> $sources Base URLs keyed by source.
		 */
		$filtered = apply_filters( 'idta_pdf_order_sources', $configured );

		$sources = array();

		foreach ( (array) $filtered as $key => $base ) {
			if ( ! is_string( $key ) || ! is_string( $base ) || '' === trim( $base ) ) {
				continue;
			}

			$sources[ strtolower( trim( $key ) ) ] = trailingslashit( trim( $base ) );
		}

		return $sources;
	}

	/**
	 * Filenames within `_idp_assets`, keyed as in self::ASSET_FILES.
	 *
	 * @return array<string,string>
	 */
	public static function asset_filenames(): array {
		static $files = null;

		if ( null !== $files ) {
			return $files;
		}

		/**
		 * Filters the filenames expected inside an order's asset folder.
		 *
		 * Keyed as in Order_Data::ASSET_FILES ('passport_photo', 'license_front',
		 * 'license_back', 'signature'). Change this if the upload step ever names
		 * the files differently, rather than reintroducing four separate meta
		 * fields to hold full paths.
		 *
		 * @param array<string,string> $files Filenames keyed by image.
		 */
		$filtered = apply_filters( 'idta_pdf_asset_filenames', self::ASSET_FILES );

		$files = array();

		foreach ( (array) $filtered as $key => $name ) {
			if ( is_string( $key ) && is_string( $name ) && '' !== trim( $name ) ) {
				$files[ $key ] = ltrim( trim( $name ), '/' );
			}
		}

		return $files;
	}

	/**
	 * Date of birth.
	 *
	 * @return \DateTimeImmutable|null
	 */
	public function date_of_birth(): ?\DateTimeImmutable {
		return $this->to_date( $this->get( '_idp_date_of_birth' ) );
	}

	/**
	 * Formatted date of birth.
	 *
	 * @param string $format PHP date format.
	 *
	 * @return string
	 */
	public function date_of_birth_formatted( string $format = 'd/m/Y' ): string {
		$date = $this->date_of_birth();

		return $date instanceof \DateTimeImmutable ? $date->format( $format ) : '';
	}

	/**
	 * Issue date, taken from the order creation date.
	 *
	 * @return \DateTimeImmutable
	 */
	public function issue_date(): \DateTimeImmutable {
		$created = $this->order->get_date_created();

		if ( $created instanceof \WC_DateTime ) {
			$date = $this->to_date( $created->date( 'Y-m-d' ) );

			if ( $date instanceof \DateTimeImmutable ) {
				return $date;
			}
		}

		return new \DateTimeImmutable( current_time( 'Y-m-d' ) );
	}

	/**
	 * Formatted issue date.
	 *
	 * @param string $format PHP date format.
	 *
	 * @return string
	 */
	public function issue_date_formatted( string $format = 'd/m/Y' ): string {
		return $this->issue_date()->format( $format );
	}

	/**
	 * Validity in whole years, defaulting to one.
	 *
	 * @return int
	 */
	public function validity_years(): int {
		$raw = $this->get( '_idp_validity_years' );

		// Tolerates values such as "3", "3 years" or "3-year".
		if ( preg_match( '/\d+/', $raw, $matches ) ) {
			$years = (int) $matches[0];

			if ( $years > 0 ) {
				return min( $years, 10 );
			}
		}

		return 1;
	}

	/**
	 * Expiry date: issue date plus the validity period, less one day.
	 *
	 * @return \DateTimeImmutable
	 */
	public function expiry_date(): \DateTimeImmutable {
		return $this->issue_date()->modify(
			sprintf( '+%d years -1 day', $this->validity_years() )
		);
	}

	/**
	 * Formatted expiry date.
	 *
	 * @param string $format PHP date format.
	 *
	 * @return string
	 */
	public function expiry_date_formatted( string $format = 'd/m/Y' ): string {
		return $this->expiry_date()->format( $format );
	}

	/**
	 * Licence categories held, for example ['A', 'B'].
	 *
	 * @return string[]
	 */
	public function license_categories(): array {
		$raw = $this->get( '_idp_license_category' );

		if ( '' === $raw ) {
			return array();
		}

		$parts = preg_split( '/[,;|\/]+|\s+/', $raw, -1, PREG_SPLIT_NO_EMPTY );

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$parts = array_map(
			static fn( string $part ): string => strtoupper( trim( $part ) ),
			$parts
		);

		// Keep only categories the documents can render, preserving canonical order.
		$valid = array_values( array_intersect( self::CATEGORIES, array_unique( $parts ) ) );

		return $valid;
	}

	/**
	 * Whether a given category is held.
	 *
	 * @param string $category Category letter.
	 *
	 * @return bool
	 */
	public function has_category( string $category ): bool {
		return in_array( strtoupper( $category ), $this->license_categories(), true );
	}

	/**
	 * Whether the issuing country is party to the 1968 Vienna Convention.
	 *
	 * @return bool
	 */
	public function is_vienna_1968(): bool {
		$country = $this->country_of_issuance();

		if ( '' === $country ) {
			return false;
		}

		if ( 2 === strlen( $country ) && isset( self::VIENNA_1968[ strtoupper( $country ) ] ) ) {
			return true;
		}

		foreach ( self::VIENNA_1968 as $name ) {
			if ( 0 === strcasecmp( $name, $country ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Convention line printed on the booklet cover.
	 *
	 * @return string
	 */
	public function convention_label(): string {
		return $this->is_vienna_1968()
			? __( 'Convention on Road Traffic of 8 November 1968', 'idta-pdf' )
			: __( 'Convention on Road Traffic of 19 September 1949', 'idta-pdf' );
	}

	/**
	 * Permit number, generated and persisted on first use.
	 *
	 * @return string
	 */
	public function card_number(): string {
		$existing = $this->order->get_meta( self::CARD_NUMBER_META, true );

		if ( is_scalar( $existing ) && '' !== trim( (string) $existing ) ) {
			return trim( (string) $existing );
		}

		$number = sprintf(
			'IDP-%s-%06d',
			$this->issue_date()->format( 'Y' ),
			$this->order_id()
		);

		/**
		 * Filters the generated permit number.
		 *
		 * @param string     $number Permit number.
		 * @param Order_Data $data   Order data.
		 */
		$number = (string) apply_filters( 'idta_pdf_card_number', $number, $this );

		$this->order->update_meta_data( self::CARD_NUMBER_META, $number );
		$this->order->save_meta_data();

		return $number;
	}

	/**
	 * Passport photo URL.
	 *
	 * @return string
	 */
	public function passport_photo(): string {
		return $this->resolve_asset( 'passport_photo' );
	}

	/**
	 * Signature image URL.
	 *
	 * @return string
	 */
	public function signature(): string {
		return $this->resolve_asset( 'signature' );
	}

	/**
	 * Front-of-licence image URL.
	 *
	 * @return string
	 */
	public function license_front(): string {
		return $this->resolve_asset( 'license_front' );
	}

	/**
	 * Back-of-licence image URL.
	 *
	 * @return string
	 */
	public function license_back(): string {
		return $this->resolve_asset( 'license_back' );
	}

	/**
	 * Resolve one of the four uploaded images.
	 *
	 * The folder in `_idp_assets` plus the image's standard filename, which is all
	 * a current order stores.
	 *
	 * An order written before `_idp_assets` has no folder but does have a full path
	 * in a retired per-image key, so that is read as a fallback rather than leaving
	 * the order without images. See LEGACY_ASSET_FIELDS.
	 *
	 * @param string $asset_key Key into self::asset_filenames().
	 *
	 * @return string
	 */
	private function resolve_asset( string $asset_key ): string {
		$folder   = trim( str_replace( '\/', '/', $this->get( '_idp_assets' ) ) );
		$filename = self::asset_filenames()[ $asset_key ] ?? '';

		if ( '' !== $folder && '' !== $filename ) {
			return $this->normalise_url( trailingslashit( $folder ) . $filename );
		}

		$legacy = $this->get( self::LEGACY_ASSET_FIELDS[ $asset_key ] ?? '' );

		return '' !== $legacy ? $this->normalise_url( $legacy ) : '';
	}

	/**
	 * Customer email, for notifications.
	 *
	 * @return string
	 */
	public function email(): string {
		return (string) $this->order->get_billing_email();
	}

	/**
	 * Accept a plain URL, an escaped URL or a JSON-encoded array of URLs.
	 *
	 * The legacy Gravity Forms templates stored uploads as JSON arrays; the
	 * checkout flow now writes a single URL. Both are handled here.
	 *
	 * @param string $value Raw meta value.
	 *
	 * @return string
	 */
	private function normalise_url( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		if ( str_starts_with( $value, '[' ) || str_starts_with( $value, '{' ) ) {
			$decoded = json_decode( $value, true );

			if ( is_array( $decoded ) ) {
				$first = reset( $decoded );
				$value = is_string( $first ) ? $first : '';
			}
		}

		$value = trim( str_replace( '\/', '/', $value ) );

		if ( '' === $value ) {
			return '';
		}

		/*
		 * The checkout now stores these as a path relative to the bucket the
		 * order was taken through — "2026/07/29/084250-394/portrait.jpg" — so the
		 * base URL has to be put back before anything can fetch it.
		 *
		 * Two kinds of value are already complete. An absolute URL, which is how
		 * orders placed before `_idp_order_from` existed stored these. And a path
		 * into this site's own directories, which Image_Helper resolves locally;
		 * that is checked for by name rather than by a leading slash alone,
		 * because a bucket path may arrive with one and "/2026/07/..." would
		 * otherwise be looked for on this server and 404.
		 */
		$absolute = 1 === preg_match( '#^(?:[a-z][a-z0-9+.-]*:|//)#i', $value );
		$on_site  = ! $absolute && 1 === preg_match( '#^/(?:wp-content|wp-includes|wp-admin)/#i', $value );

		if ( ! $absolute && ! $on_site ) {
			$base = $this->asset_base_url();

			if ( '' === $base ) {
				$this->log_unresolved_asset( $value );

				return '';
			}

			$value = $base . ltrim( $value, '/' );
		}

		return esc_url_raw( $value );
	}

	/**
	 * Note a relative asset path that could not be turned into a URL.
	 *
	 * @param string $value Stored path.
	 */
	private function log_unresolved_asset( string $value ): void {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		wc_get_logger()->warning(
			sprintf(
				'Order %d stores "%s" as a relative path but its _idp_order_from (%s) matches no known source, so the image was skipped.',
				$this->order_id(),
				$value,
				'' !== $this->get( '_idp_order_from' ) ? $this->get( '_idp_order_from' ) : 'empty'
			),
			array( 'source' => 'idta-pdf' )
		);
	}

	/**
	 * Parse a date string into an immutable date at midnight.
	 *
	 * @param string $value Date string.
	 *
	 * @return \DateTimeImmutable|null
	 */
	private function to_date( string $value ): ?\DateTimeImmutable {
		if ( '' === $value ) {
			return null;
		}

		/**
		 * Filters the date formats accepted in `_idp_*` meta, in priority order.
		 *
		 * The first format that matches wins, so ordering resolves ambiguous
		 * values: 03/04/2026 reads as 3 April under 'd/m/Y' but as 4 March
		 * under 'm/d/Y'. Reorder this list to match how the checkout stores
		 * dates.
		 *
		 * @param string[] $formats Date formats.
		 */
		$formats = (array) apply_filters(
			'idta_pdf_date_input_formats',
			array( 'Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d' )
		);

		foreach ( $formats as $format ) {
			$date = \DateTimeImmutable::createFromFormat( '!' . $format, $value );

			// createFromFormat tolerates trailing data and out-of-range parts,
			// so require a clean parse and a lossless round trip.
			if ( $date instanceof \DateTimeImmutable && $date->format( $format ) === $value ) {
				// As of PHP 8.2 getLastErrors() returns false on a clean parse.
				$errors = \DateTimeImmutable::getLastErrors();

				if ( ! is_array( $errors ) ) {
					return $date;
				}

				if ( empty( $errors['warning_count'] ) && empty( $errors['error_count'] ) ) {
					return $date;
				}
			}
		}

		$timestamp = strtotime( $value );

		if ( false === $timestamp ) {
			return null;
		}

		return ( new \DateTimeImmutable() )->setTimestamp( $timestamp )->setTime( 0, 0 );
	}
}
