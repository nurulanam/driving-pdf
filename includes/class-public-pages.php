<?php
/**
 * Public permit and details pages reached from the QR codes.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * The two pages a scanned QR code lands on.
 *
 * Both are served as a standalone HTML document rather than through the theme:
 * they are read on a phone camera's browser, immediately after scanning, and a
 * theme header, navigation and footer are noise around the one thing the reader
 * wants. Bypassing the theme also means the pages look the same on every site.
 *
 * The WordPress pages themselves are created empty. They exist only so the slugs
 * `/idp/` and `/show-details/` resolve — the URLs QR_Generator encodes — and so an
 * administrator can see them in the Pages list. Nothing is stored in their
 * content; putting the markup here keeps it in version control.
 */
final class Public_Pages {

	/**
	 * Option holding the created page IDs, keyed as in self::pages().
	 */
	private const OPTION = 'idta_pdf_public_pages';

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * QR generator, for its token cipher.
	 *
	 * @var QR_Generator
	 */
	private QR_Generator $qr;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Download URL builder.
	 *
	 * @var Download_Handler
	 */
	private Download_Handler $downloads;

	/**
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

	/**
	 * Constructor.
	 *
	 * @param Generator $generator Document generator.
	 * @param Settings  $settings  Settings repository.
	 */
	public function __construct( Generator $generator, Settings $settings, Release_Schedule $releases ) {
		$this->generator = $generator;
		$this->settings  = $settings;
		$this->qr        = new QR_Generator( $settings );
		$this->releases  = $releases;
		$this->downloads = new Download_Handler( $generator, $releases );
	}

	/**
	 * The pages this plugin owns.
	 *
	 * The slugs must match the paths QR_Generator encodes, and the secret context
	 * must match the one it signs each link with, or a scanned code will not
	 * resolve.
	 *
	 * @return array<string,array{slug:string,title:string,context:string,template:string}>
	 */
	public static function pages(): array {
		return array(
			'permit'  => array(
				'slug'     => 'idp',
				'title'    => __( 'International Driving Permit', 'idta-pdf' ),
				'context'  => 'permit',
				'template' => 'permit.php',
			),
			'details' => array(
				'slug'     => 'show-details',
				'title'    => __( 'Your details', 'idta-pdf' ),
				'context'  => 'details',
				'template' => 'details.php',
			),
		);
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );
	}

	/**
	 * Create the two pages if they are not already there.
	 *
	 * Idempotent, and safe to call on every activation: an existing page at the
	 * slug is adopted rather than duplicated, so a site that already had an
	 * /idp/ page keeps it.
	 */
	public static function ensure_pages(): void {
		$stored = (array) get_option( self::OPTION, array() );

		foreach ( self::pages() as $key => $page ) {
			$existing = isset( $stored[ $key ] ) ? get_post( (int) $stored[ $key ] ) : null;

			if ( $existing instanceof \WP_Post && 'page' === $existing->post_type && 'trash' !== $existing->post_status ) {
				continue;
			}

			$by_slug = get_page_by_path( $page['slug'] );

			if ( $by_slug instanceof \WP_Post ) {
				$stored[ $key ] = $by_slug->ID;

				continue;
			}

			$id = wp_insert_post(
				array(
					'post_title'     => $page['title'],
					'post_name'      => $page['slug'],
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_content'   => '',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				)
			);

			if ( ! is_wp_error( $id ) && $id > 0 ) {
				$stored[ $key ] = (int) $id;
			}
		}

		update_option( self::OPTION, $stored );
	}

	/**
	 * Serve one of our pages, if this request is for one.
	 */
	public function maybe_render(): void {
		$key = $this->current_page_key();

		if ( null === $key ) {
			return;
		}

		$page = self::pages()[ $key ];

		// These pages are personal and reached from a printed code. They must not
		// be cached by a proxy or indexed by a crawler.
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );

		$order = $this->resolve_order( $page['context'] );

		if ( ! $order instanceof \WC_Order ) {
			status_header( 404 );

			$this->render(
				$page['title'],
				sprintf(
					'<div class="idta-card idta-card--empty"><h1>%s</h1><p>%s</p></div>',
					esc_html__( 'Link not recognised', 'idta-pdf' ),
					esc_html__( 'This link is invalid or has expired. Please scan the code again, or contact us if it keeps happening.', 'idta-pdf' )
				)
			);
		}

		$body = $this->render_template( $page['template'], $order );

		if ( '' === $body ) {
			status_header( 500 );

			$body = sprintf(
				'<div class="idta-card idta-card--empty"><h1>%s</h1></div>',
				esc_html__( 'This page is unavailable.', 'idta-pdf' )
			);
		}

		$this->render( $page['title'], $body );
	}

	/**
	 * Which of our pages is being requested, if any.
	 *
	 * Matched on the stored ID first, then on the slug, so the pages keep working
	 * if the option is lost or the pages are recreated by hand.
	 *
	 * @return string|null Key into self::pages(), or null.
	 */
	private function current_page_key(): ?string {
		if ( ! is_page() ) {
			return null;
		}

		$queried = get_queried_object_id();
		$stored  = (array) get_option( self::OPTION, array() );

		foreach ( self::pages() as $key => $page ) {
			if ( isset( $stored[ $key ] ) && (int) $stored[ $key ] === $queried ) {
				return $key;
			}

			if ( is_page( $page['slug'] ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Resolve the order the `entry_key` in the URL points at.
	 *
	 * @param string $context Secret context, 'permit' or 'details'.
	 *
	 * @return \WC_Order|null
	 */
	private function resolve_order( string $context ): ?\WC_Order {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The signed token is the credential; a nonce would break a printed link.
		$token = isset( $_GET['entry_key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['entry_key'] ) ) : '';

		if ( '' === $token ) {
			return null;
		}

		$order_id = absint( $this->qr->decrypt( $token, $this->settings->qr_secret( $context ) ) );

		if ( 0 === $order_id ) {
			return null;
		}

		$order = wc_get_order( $order_id );

		// A token that decrypts but names an order with no IDP meta is treated as
		// unrecognised, so nothing is revealed about which IDs exist.
		if ( ! $order instanceof \WC_Order || ! Order_Data::has_data( $order ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Run a template and return its markup.
	 *
	 * Override by copying to `idta-pdf/public/<template>` in your theme.
	 *
	 * @param string    $template Template filename.
	 * @param \WC_Order $order    Order object.
	 *
	 * @return string
	 */
	private function render_template( string $template, \WC_Order $order ): string {
		$file = locate_template( 'idta-pdf/public/' . $template );

		if ( '' === $file || ! is_readable( $file ) ) {
			$file = plugin_dir_path( PLUGIN_FILE ) . 'templates/public/' . $template;
		}

		if ( ! is_readable( $file ) ) {
			return '';
		}

		/*
		 * Made available to the template. $documents is the list of slugs this
		 * order may be offered, not stored paths — nothing is stored, and each
		 * link renders its document when it is followed. It is empty until the
		 * order's permit is released, which is what keeps the page from offering
		 * a link that would answer with a refusal.
		 */
		$data      = new Order_Data( $order );
		$documents = $this->releases->is_released( $order )
			? $this->generator->offered_slugs( $order )
			: array();
		$downloads = $this->downloads;
		$unavailable = $this->releases->explain( $order );

		unset( $template );

		ob_start();

		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Emit the standalone document and stop.
	 *
	 * @param string $title Document title.
	 * @param string $body  Body markup, already escaped.
	 *
	 * @return never
	 */
	private function render( string $title, string $body ): void {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		printf(
			'<!doctype html><html %1$s><head>'
			. '<meta charset="%2$s">'
			. '<meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<meta name="robots" content="noindex, nofollow">'
			. '<title>%3$s</title><style>%4$s</style>'
			. '</head><body class="idta-public"><main class="idta-wrap">%5$s</main></body></html>',
			get_language_attributes(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress builds this attribute string.
			esc_attr( get_bloginfo( 'charset' ) ),
			esc_html( $title ),
			$this->styles(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static stylesheet shipped with the plugin.
			$body // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the caller and the templates.
		);

		exit;
	}

	/**
	 * The page stylesheet, inlined.
	 *
	 * Inlined rather than enqueued because these pages deliberately never call
	 * wp_head(), so there is nothing for an enqueued stylesheet to print into.
	 *
	 * @return string
	 */
	private function styles(): string {
		$file = plugin_dir_path( PLUGIN_FILE ) . 'assets/css/public.css';

		if ( ! is_readable( $file ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$css = (string) file_get_contents( $file );

		/**
		 * Filters the stylesheet inlined into the public permit and details pages.
		 *
		 * @param string $css Stylesheet contents.
		 */
		return (string) apply_filters( 'idta_pdf_public_css', $css );
	}
}
