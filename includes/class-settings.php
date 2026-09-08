<?php
/**
 * Settings repository.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Typed access to the plugin option, with defaults.
 */
final class Settings {

	/**
	 * Option name.
	 */
	public const OPTION_KEY = 'idta_pdf_settings';

	/**
	 * Base font size, in points, applied when no custom CSS overrides it.
	 */
	public const DEFAULT_FONT_SIZE = 10.0;

	/**
	 * Every document this plugin can produce, in the order the screens list it.
	 *
	 */
	public const DOCUMENTS = array( 'booklet', 'card', 'print-copy' );

	/**
	 * Cached option values.
	 *
	 * @var array<string,mixed>|null
	 */
	private ?array $values = null;

	/**
	 * Default settings.
	 *
	 * @return array<string,mixed>
	 */
	public function defaults(): array {
		return array(
			'font_size'        => self::DEFAULT_FONT_SIZE,
			'font_family'      => 'dejavusans',
			'custom_css'       => '',
			'booklet_css'      => '',
			'card_css'         => '',
			'trigger_statuses' => array( 'processing', 'completed' ),
			'documents'        => array( 'booklet', 'card', 'print-copy' ),
			// Empty by default: qr_base_url() falls back to the current
			// site's home_url() so QR links work out of the box.
			'qr_base_url'      => '',
			'qr_permit_secret' => '',
			'qr_details_secret' => '',
			// Base URL each order's `_idp_assets` folder is relative to, keyed by
			// the value checkout stores in `_idp_order_from`. Falls back to
			// Order_Data::SOURCES when nothing is configured, so upgrading sites
			// keep working without a trip to this screen first.
			'asset_sources'    => Order_Data::SOURCES,
			'grayscale_ghost'  => true,
			/*
			 * Generation is deferred, and how long by depends on what was
			 * bought: an order containing one of the rush products is built
			 * within minutes, everything else waits. Both delays are in
			 * minutes, and are measured from payment.
			 */
			'rush_products'    => array( 20 ),
			'rush_delay'       => 5,
			'standard_delay'   => 240,
			/**
			 * Bitmap card faces, off by default. They are only wanted where the
			 * card is printed on a direct-to-card printer, they need a PDF
			 * rasteriser on the server, and each face is about 1.9 MB.
			 */
			'card_bmp'         => false,
			'debug_html'       => false,
		);
	}

	/**
	 * All settings, defaults merged in.
	 *
	 * @return array<string,mixed>
	 */
	public function all(): array {
		if ( null === $this->values ) {
			$stored = get_option( self::OPTION_KEY, array() );

			$this->values = wp_parse_args(
				is_array( $stored ) ? $stored : array(),
				$this->defaults()
			);
		}

		/**
		 * Filters the resolved plugin settings.
		 *
		 * @param array<string,mixed> $values Settings.
		 */
		return apply_filters( 'idta_pdf_settings', $this->values );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback when the key is unknown.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$values = $this->all();

		return $values[ $key ] ?? $default;
	}

	/**
	 * Persist settings.
	 *
	 * @param array<string,mixed> $values Raw values.
	 */
	public function save( array $values ): void {
		update_option( self::OPTION_KEY, $this->sanitize( $values ) );

		$this->values = null;
	}

	/**
	 * Sanitize a settings payload.
	 *
	 * @param array<string,mixed> $input Raw values.
	 *
	 * @return array<string,mixed>
	 */
	public function sanitize( array $input ): array {
		$defaults = $this->defaults();
		$clean    = array();

		$font_size = isset( $input['font_size'] ) ? (float) $input['font_size'] : self::DEFAULT_FONT_SIZE;

		$clean['font_size'] = ( $font_size >= 4.0 && $font_size <= 72.0 )
			? round( $font_size, 2 )
			: self::DEFAULT_FONT_SIZE;

		$clean['font_family'] = sanitize_key( (string) ( $input['font_family'] ?? $defaults['font_family'] ) );

		foreach ( array( 'custom_css', 'booklet_css', 'card_css' ) as $css_key ) {
			$clean[ $css_key ] = $this->sanitize_css( (string) ( $input[ $css_key ] ?? '' ) );
		}

		$statuses = (array) ( $input['trigger_statuses'] ?? $defaults['trigger_statuses'] );

		$clean['trigger_statuses'] = array_values(
			array_filter( array_map( 'sanitize_key', $statuses ) )
		);

		$documents = (array) ( $input['documents'] ?? $defaults['documents'] );

		$clean['documents'] = array_values(
			array_intersect( self::DOCUMENTS, array_map( 'sanitize_key', $documents ) )
		);

		$clean['qr_base_url']       = esc_url_raw( (string) ( $input['qr_base_url'] ?? $defaults['qr_base_url'] ) );
		$clean['qr_permit_secret']  = sanitize_text_field( (string) ( $input['qr_permit_secret'] ?? '' ) );
		$clean['qr_details_secret'] = sanitize_text_field( (string) ( $input['qr_details_secret'] ?? '' ) );

		$clean['asset_sources'] = $this->sanitize_sources( (array) ( $input['asset_sources'] ?? array() ) );

		/*
		 * Product IDs arrive as the comma-separated list the field shows. Zero
		 * and anything non-numeric is dropped rather than kept as 0, which
		 * would otherwise match nothing and read like a configured rule.
		 */
		$rush_products = $input['rush_products'] ?? $defaults['rush_products'];

		if ( ! is_array( $rush_products ) ) {
			$rush_products = preg_split( '/[^0-9]+/', (string) $rush_products ) ?: array();
		}

		$clean['rush_products'] = array_values(
			array_unique(
				array_filter( array_map( 'absint', $rush_products ) )
			)
		);

		/*
		 * A delay of zero is meaningful — build it immediately — so these are
		 * only floored, not defaulted when empty. The ceiling is a week, which
		 * stops a stray keystroke parking an order's documents out of reach.
		 */
		foreach ( array( 'rush_delay', 'standard_delay' ) as $delay_key ) {
			$minutes = isset( $input[ $delay_key ] ) ? absint( $input[ $delay_key ] ) : (int) $defaults[ $delay_key ];

			$clean[ $delay_key ] = min( $minutes, 7 * 24 * 60 );
		}

		$clean['grayscale_ghost'] = ! empty( $input['grayscale_ghost'] );
		$clean['card_bmp']        = ! empty( $input['card_bmp'] );
		$clean['debug_html']      = ! empty( $input['debug_html'] );

		return $clean;
	}

	/**
	 * Sanitize the upload-source rows posted from the settings screen.
	 *
	 * @param array<int,mixed> $rows Rows, each expected to have 'key' and 'url'.
	 *
	 * @return array<string,string> Falls back to Order_Data::SOURCES when every
	 *                               row is blank or missing, so the setting can
	 *                               never leave asset resolution with nothing.
	 */
	private function sanitize_sources( array $rows ): array {
		$clean = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$key = sanitize_key( (string) ( $row['key'] ?? '' ) );
			$url = esc_url_raw( (string) ( $row['url'] ?? '' ) );

			if ( '' === $key || '' === $url ) {
				continue;
			}

			$clean[ $key ] = $url;
		}

		return array() !== $clean ? $clean : Order_Data::SOURCES;
	}

	/**
	 * Strip anything that is not CSS from a stylesheet blob.
	 *
	 * `wp_strip_all_tags` removes an injected `</style><script>` payload while
	 * leaving declarations, selectors and at-rules intact.
	 *
	 * @param string $css Raw CSS.
	 *
	 * @return string
	 */
	private function sanitize_css( string $css ): string {
		$css = wp_strip_all_tags( $css );

		return trim( str_replace( array( '<', '>' ), '', $css ) );
	}

	/**
	 * Base font size in points.
	 *
	 * @return float
	 */
	public function font_size(): float {
		$size = (float) $this->get( 'font_size', self::DEFAULT_FONT_SIZE );

		return $size > 0 ? $size : self::DEFAULT_FONT_SIZE;
	}

	/**
	 * Base font family.
	 *
	 * @return string
	 */
	public function font_family(): string {
		$family = (string) $this->get( 'font_family', 'dejavusans' );

		return '' !== $family ? $family : 'dejavusans';
	}

	/**
	 * Order statuses whose documents may be downloaded.
	 *
	 * Nothing is generated ahead of time any more, so this no longer decides
	 * when a document is built — it decides which orders are allowed to fetch
	 * one. The stored key keeps its original name so existing configuration
	 * carries over untouched.
	 *
	 * @return string[]
	 */
	public function release_statuses(): array {
		$statuses = (array) $this->get( 'trigger_statuses', array( 'processing', 'completed' ) );

		$statuses = array_values( array_filter( array_map( 'strval', $statuses ) ) );

		return array() !== $statuses ? $statuses : array( 'processing', 'completed' );
	}

	/**
	 * Product IDs that make an order a rush job.
	 *
	 * @return int[]
	 */
	public function rush_products(): array {
		return array_values(
			array_unique(
				array_filter( array_map( 'absint', (array) $this->get( 'rush_products', array() ) ) )
			)
		);
	}

	/**
	 * How long to wait before generating a rush order's documents, in seconds.
	 *
	 * @return int
	 */
	public function rush_delay(): int {
		return absint( $this->get( 'rush_delay', 5 ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * How long to wait before generating any other order's documents, in seconds.
	 *
	 * @return int
	 */
	public function standard_delay(): int {
		return absint( $this->get( 'standard_delay', 240 ) ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Document types to generate.
	 *
	 * @return string[]
	 */
	public function enabled_documents(): array {
		$documents = (array) $this->get( 'documents', self::DOCUMENTS );

		$documents = array_values(
			array_intersect( self::DOCUMENTS, array_map( 'strval', $documents ) )
		);

		return array() !== $documents ? $documents : self::DOCUMENTS;
	}

	/**
	 * Email IDs that receive the documents as attachments.
	 *
	 * Deprecated, and deliberately still here. Attachments were removed when
	 * documents stopped being stored — there is no file to attach — but the
	 * class that called this, Email_Attachments, was hooked to
	 * `woocommerce_email_attachments`, which runs on every email the store
	 * sends. Updating a plugin by uploading it over the existing folder
	 * overwrites files and deletes nothing, so that class can still be sitting
	 * on a server, and without this method it would call one that no longer
	 * exists and take down every outgoing email with it.
	 *
	 * Returning an empty list makes that stale code return immediately and
	 * attach nothing, which is the correct behaviour now anyway.
	 *
	 * @deprecated Documents are rendered on request and never stored.
	 *
	 * @return string[] Always empty.
	 */
	public function attachment_emails(): array {
		return array();
	}

	/**
	 * Custom CSS shared by both documents.
	 *
	 * @return string
	 */
	public function custom_css(): string {
		return (string) $this->get( 'custom_css', '' );
	}

	/**
	 * Custom CSS for a single document type.
	 *
	 * @param string $document Document slug.
	 *
	 * @return string
	 */
	public function document_css( string $document ): string {
		return (string) $this->get( $document . '_css', '' );
	}

	/**
	 * Base URL used when building QR target links.
	 *
	 * @return string
	 */
	public function qr_base_url(): string {
		return untrailingslashit( (string) $this->get( 'qr_base_url', '' ) );
	}

	/**
	 * Secret used to sign the permit QR link.
	 *
	 * Falls back to a site-specific salt so links are never signed with an
	 * empty key.
	 *
	 * @param string $context Either 'permit' or 'details'.
	 *
	 * @return string
	 */
	public function qr_secret( string $context = 'permit' ): string {
		$key    = 'details' === $context ? 'qr_details_secret' : 'qr_permit_secret';
		$secret = (string) $this->get( $key, '' );

		if ( '' !== $secret ) {
			return $secret;
		}

		return wp_salt( 'idta_pdf_' . $context );
	}

	/**
	 * Upload-source base URLs, keyed by the value stored in `_idp_order_from`.
	 *
	 * @return array<string,string>
	 */
	public function asset_sources(): array {
		$sources = (array) $this->get( 'asset_sources', Order_Data::SOURCES );
		$clean   = array();

		foreach ( $sources as $key => $url ) {
			if ( is_string( $key ) && is_string( $url ) && '' !== trim( $url ) ) {
				$clean[ strtolower( trim( $key ) ) ] = trim( $url );
			}
		}

		return array() !== $clean ? $clean : Order_Data::SOURCES;
	}

	/**
	 * Whether the small duplicate portrait is rendered in grayscale.
	 *
	 * @return bool
	 */
	public function grayscale_ghost(): bool {
		return (bool) $this->get( 'grayscale_ghost', true );
	}

	/**
	 * Whether to write the card's two faces as bitmaps alongside the card PDF.
	 *
	 * @return bool
	 */
	public function card_bitmaps(): bool {
		return (bool) $this->get( 'card_bmp', false );
	}

	/**
	 * Whether to keep the rendered HTML alongside each PDF for debugging.
	 *
	 * @return bool
	 */
	public function debug_html(): bool {
		return (bool) $this->get( 'debug_html', false );
	}
}
