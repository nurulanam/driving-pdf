<?php
/**
 * Plugin container and hook wiring.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstraps the plugin and owns its long-lived services.
 */
final class Plugin {

	/**
	 * Async action hook used to generate documents off the checkout request.
	 */
	public const ASYNC_HOOK = 'idta_pdf_generate_documents';

	/**
	 * Option recording the version whose one-time setup has already run.
	 */
	private const VERSION_OPTION = 'idta_pdf_version';

	/**
	 * Sole instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Whether boot() has already run.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings  = new Settings();
		$this->generator = new Generator( $this->settings );
	}

	/**
	 * Retrieve the sole instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Settings accessor.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		return $this->settings;
	}

	/**
	 * Generator accessor.
	 *
	 * @return Generator
	 */
	public function generator(): Generator {
		return $this->generator;
	}

	/**
	 * Register hooks. Safe to call once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain( 'idta-pdf', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );

		if ( ! class_exists( \WooCommerce::class ) ) {
			add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );

			return;
		}

		add_action( 'init', array( $this, 'maybe_upgrade' ), 5 );

		// Primary trigger: generate as soon as the order is placed, whatever
		// its payment status. Covers both the classic checkout and the
		// block-based Store API checkout.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'schedule_generation' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'schedule_generation' ), 20, 1 );

		foreach ( $this->settings->trigger_statuses() as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this, 'schedule_generation' ), 20, 1 );
		}

		// Async worker, plus the WP-Cron fallback signature.
		add_action( self::ASYNC_HOOK, array( $this, 'run_generation' ), 10, 1 );

		$order_admin = new Order_Admin( $this->generator );
		$order_admin->register();

		( new Order_Fields() )->register();

		( new Settings_Page( $this->settings ) )->register();
		( new Download_Handler( $this->generator ) )->register();
		( new Public_Pages( $this->generator, $this->settings ) )->register();
		( new Email_Attachments( $this->settings, $this->generator ) )->register();
		( new Order_List_Column( $this->generator, $order_admin ) )->register();
	}

	/**
	 * Run one-time work after the plugin files change.
	 *
	 * activate() only fires when the plugin is activated, so a site that updates
	 * the files in place — over SFTP, or through an updater — never runs it and
	 * would be left without the pages the QR codes point at. Gated on a stored
	 * version so this costs one option read per request and nothing else.
	 */
	public function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === VERSION ) {
			return;
		}

		Public_Pages::ensure_pages();

		update_option( self::VERSION_OPTION, VERSION );
	}

	/**
	 * Create storage on activation.
	 */
	public static function activate(): void {
		( new Filesystem() )->ensure_base_dir();

		add_option( Settings::OPTION_KEY, ( new Settings() )->defaults() );

		// The pages the QR codes point at. Idempotent: an existing page at either
		// slug is adopted rather than duplicated.
		Public_Pages::ensure_pages();

		// Their slugs need to resolve on the next request.
		flush_rewrite_rules();
	}

	/**
	 * Clear queued work on deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::ASYNC_HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ASYNC_HOOK );
		}
	}

	/**
	 * Queue document generation from a `woocommerce_after_order_object_save`
	 * callback, which passes the order object rather than its ID.
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function schedule_generation_for_order( $order ): void {
		if ( $order instanceof \WC_Order ) {
			$this->schedule_generation( $order->get_id() );
		}
	}

	/**
	 * Queue document generation for an order.
	 *
	 * @param int $order_id Order ID.
	 */
	public function schedule_generation( $order_id ): void {
		$order_id = absint( $order_id );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Nothing to build until the IDP meta has been written.
		if ( ! Order_Data::has_data( $order ) ) {
			return;
		}

		if ( $this->generator->is_generated( $order ) ) {
			return;
		}

		/**
		 * Filters whether an order should produce IDP documents.
		 *
		 * @param bool      $should_generate Whether to generate.
		 * @param \WC_Order $order           Order object.
		 */
		if ( ! apply_filters( 'idta_pdf_should_generate', true, $order ) ) {
			return;
		}

		if ( $this->is_queued( $order_id ) ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::ASYNC_HOOK, array( 'order_id' => $order_id ), 'idta-pdf' );

			return;
		}

		wp_schedule_single_event( time() + 5, self::ASYNC_HOOK, array( $order_id ) );
	}

	/**
	 * Generate documents for an order.
	 *
	 * Accepts both the Action Scheduler named argument and the WP-Cron
	 * positional argument.
	 *
	 * @param int|array<string,mixed> $order_id Order ID or argument bag.
	 */
	public function run_generation( $order_id ): void {
		if ( is_array( $order_id ) ) {
			$order_id = $order_id['order_id'] ?? 0;
		}

		$order = wc_get_order( absint( $order_id ) );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$this->raise_limits();

		try {
			$this->generator->generate( $order );
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );
		}
	}

	/**
	 * Give the worker the headroom the booklet needs.
	 *
	 * The booklet is by far the heavier document — 24 pages, ten embedded font
	 * subsets and the flag artwork, against the card's two pages — so it is the
	 * one that runs out of memory or time first. This matters because it runs in
	 * a WP-Cron or Action Scheduler request, which gets PHP's bare defaults, while
	 * the manual button on the order screen runs in wp-admin, where WordPress has
	 * already raised the memory limit to WP_MAX_MEMORY_LIMIT. That difference is
	 * enough to make the booklet fail on a schedule and succeed on a click.
	 *
	 * Both calls only ever raise a limit, and both are filterable — the memory one
	 * through `idta_pdf_memory_limit`, the time budget through
	 * `idta_pdf_time_limit`. A host that forbids either is left as it is.
	 */
	private function raise_limits(): void {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'idta_pdf' );
		}

		/**
		 * Filters the seconds allowed for one generation run.
		 *
		 * @param int $seconds Time limit. Zero leaves the limit untouched.
		 */
		$seconds = (int) apply_filters( 'idta_pdf_time_limit', 300 );

		if ( $seconds > 0 && function_exists( 'set_time_limit' ) ) {
			// Fails silently where it is disabled, which is the intended outcome.
			@set_time_limit( $seconds ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * Determine whether generation is already queued for an order.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return bool
	 */
	private function is_queued( int $order_id ): bool {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return as_has_scheduled_action( self::ASYNC_HOOK, array( 'order_id' => $order_id ), 'idta-pdf' );
		}

		return (bool) wp_next_scheduled( self::ASYNC_HOOK, array( $order_id ) );
	}

	/**
	 * Warn when WooCommerce is unavailable.
	 */
	public function render_missing_woocommerce_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'IDTA PDF requires WooCommerce to be installed and active.', 'idta-pdf' )
		);
	}
}
