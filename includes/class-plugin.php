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

		// Primary trigger: generate as soon as the order is placed, whatever
		// its payment status. Covers both the classic checkout and the
		// block-based Store API checkout.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'schedule_generation' ), 20, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'schedule_generation' ), 20, 1 );

		// Fallback triggers, for IDP meta that is only written after the order
		// is placed (a delayed upload, a status change, an admin-created
		// order). schedule_generation() no-ops once documents already exist.
		add_action( 'woocommerce_new_order', array( $this, 'schedule_generation' ), 20, 1 );
		add_action( 'woocommerce_payment_complete', array( $this, 'schedule_generation' ), 20, 1 );

		foreach ( $this->settings->trigger_statuses() as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this, 'schedule_generation' ), 20, 1 );
		}

		// Async worker, plus the WP-Cron fallback signature.
		add_action( self::ASYNC_HOOK, array( $this, 'run_generation' ), 10, 1 );

		$order_admin = new Order_Admin( $this->generator );
		$order_admin->register();

		( new Settings_Page( $this->settings ) )->register();
		( new Download_Handler( $this->generator ) )->register();
		( new Email_Attachments( $this->settings, $this->generator ) )->register();
		( new Order_List_Column( $this->generator, $order_admin ) )->register();
	}

	/**
	 * Create storage on activation.
	 */
	public static function activate(): void {
		( new Filesystem() )->ensure_base_dir();

		add_option( Settings::OPTION_KEY, ( new Settings() )->defaults() );
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

		try {
			$this->generator->generate( $order );
		} catch ( \Throwable $exception ) {
			$this->generator->log_failure( $order, $exception );
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
