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
	 * Action hook older versions queued generation on.
	 *
	 * Nothing schedules it any more — documents are rendered when their URL is
	 * requested — but it is still named here so deactivation and the upgrade
	 * routine can clear anything an earlier version left in the queue.
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
	 * Release rules.
	 *
	 * @var Release_Schedule
	 */
	private Release_Schedule $releases;

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
		$this->releases  = new Release_Schedule( $this->settings );
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
	 * Release rules accessor.
	 *
	 * @return Release_Schedule
	 */
	public function releases(): Release_Schedule {
		return $this->releases;
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

		/*
		 * Nothing is hooked to the checkout or to an order's status any more.
		 * Documents are not built ahead of time and not stored: each one is
		 * rendered by Download_Handler when its URL is requested, and whether
		 * that URL is allowed yet is Release_Schedule's decision.
		 */

		$downloads = new Download_Handler( $this->generator, $this->releases );

		( new Order_Admin( $this->generator, $this->releases, $downloads ) )->register();

		( new Order_Fields() )->register();
		( new Product_Names() )->register();

		( new Settings_Page( $this->settings ) )->register();
		$downloads->register();
		( new Public_Pages( $this->generator, $this->settings, $this->releases ) )->register();
		( new Thankyou_Redirect() )->register();

		/*
		 * The one scheduled job that remains. It builds nothing — it sends the
		 * email that tells the customer their permit links have started working.
		 */
		( new Release_Notifier( $this->releases ) )->register();
		( new Order_List_Column( $this->generator, $downloads, $this->releases ) )->register();
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
		$this->adopt_new_documents();
		$this->discard_stored_documents();
		$this->remove_orphaned_files();

		update_option( self::VERSION_OPTION, VERSION );
	}

	/**
	 * Delete plugin files that earlier versions shipped and this one does not.
	 *
	 * Updating a plugin by uploading it over the existing folder — which is how
	 * this one is usually updated — overwrites every file in the new copy and
	 * removes nothing else. A class that has been deleted from the source
	 * therefore stays on the server, and a deleted class that registered a
	 * WordPress hook keeps registering it.
	 *
	 * That is not hypothetical. Email_Attachments hooked
	 * `woocommerce_email_attachments`, which runs on every email the store
	 * sends, and it called methods that no longer exist — so a leftover copy
	 * stopped the store sending any email at all. Removing the file is the only
	 * thing that fixes that for good; Settings::attachment_emails() and
	 * Generator::generated_documents() survive as stubs to keep such a copy
	 * harmless until this has run.
	 */
	private function remove_orphaned_files(): void {
		$orphans = array(
			// Removed when documents stopped being stored: with nothing on disk
			// there is no file to attach to an email.
			'includes/class-email-attachments.php',
		);

		$base = plugin_dir_path( PLUGIN_FILE );

		foreach ( $orphans as $orphan ) {
			$path = $base . $orphan;

			// Confined to this plugin's own directory, and only to the names
			// listed above — never anything derived from input.
			if ( ! is_file( $path ) || ! str_starts_with( wp_normalize_path( $path ), wp_normalize_path( $base ) ) ) {
				continue;
			}

			if ( wp_delete_file_from_directory( $path, $base ) ) {
				$this->log( sprintf( 'Removed orphaned file %s left by an earlier version.', $orphan ) );
			}
		}
	}

	/**
	 * Delete the documents earlier versions stored, and anything they queued.
	 */
	private function discard_stored_documents(): void {
		/*
		 * Earlier versions rendered every document when the order was placed and
		 * kept it under uploads/idta-pdf/<order>/. Nothing reads those files any
		 * more — a document is rendered when its URL is asked for — so they are
		 * dead weight, and a booklet is several megabytes apiece. They are also
		 * the copies most likely to go stale, since an order edited afterwards
		 * would still have handed out the old render.
		 *
		 * Anything an earlier version queued is dropped too, so a pending job
		 * cannot wake up and write a file back after this has cleaned up.
		 */
		wp_clear_scheduled_hook( self::ASYNC_HOOK );
		wp_clear_scheduled_hook( Release_Notifier::HOOK );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ASYNC_HOOK, array(), 'idta-pdf' );
		}

		$filesystem = $this->generator->filesystem();
		$base       = $filesystem->base_dir();

		if ( '' === $base || ! is_dir( $base ) ) {
			return;
		}

		/*
		 * Walked on disk rather than read from each order's meta: the meta only
		 * names the orders WooCommerce can still load, and a store that has
		 * since deleted or trashed an order would keep its files for good.
		 * Deleting is confined to what this plugin itself wrote — is_managed()
		 * checks the path is inside the plugin's own directory — and only the
		 * two extensions it ever produced.
		 */
		$removed = 0;

		foreach ( (array) glob( trailingslashit( $base ) . '*/*.{pdf,bmp,html}', GLOB_BRACE ) as $file ) {
			$file = (string) $file;

			if ( ! $filesystem->is_managed( $file ) || ! is_file( $file ) ) {
				continue;
			}

			if ( $filesystem->delete( $file ) ) {
				++$removed;
			}
		}

		if ( $removed > 0 ) {
			$this->log(
				sprintf( 'Removed %d stored document(s); documents are now rendered on request.', $removed )
			);
		}
	}

	/**
	 * Write a line to the debug log.
	 *
	 * @param string $message Message.
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[idta-pdf] ' . $message );
		}
	}

	/**
	 * Switch on a document added since the settings were last saved.
	 *
	 * Changing the default in Settings::defaults() only reaches a site that has
	 * never saved the option. Any site that has visited the settings screen
	 * carries the old list, and would silently go on producing the old set — so
	 * a document added to Settings::DOCUMENTS is enabled once, here, on the
	 * upgrade that introduces it. Untick it afterwards and it stays off: this
	 * runs once per plugin version, not once per request.
	 */
	private function adopt_new_documents(): void {
		$stored = get_option( Settings::OPTION_KEY );

		if ( ! is_array( $stored ) || ! isset( $stored['documents'] ) || ! is_array( $stored['documents'] ) ) {
			return;
		}

		$missing = array_diff( Settings::DOCUMENTS, $stored['documents'] );

		if ( array() === $missing ) {
			return;
		}

		$stored['documents'] = array_values(
			array_intersect( Settings::DOCUMENTS, array_merge( $stored['documents'], $missing ) )
		);

		update_option( Settings::OPTION_KEY, $stored );
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
			as_unschedule_all_actions( Release_Notifier::HOOK );
		}
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
