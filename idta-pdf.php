<?php
/**
 * Plugin Name:       IDTA PDF
 * Plugin URI:        https://am2am.com
 * Description:       Generates the International Driving Permit booklet (A4) and ID card (85.6 × 53.98 mm) PDFs from WooCommerce order meta.
 * Version:           1.7.1
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            am2am software
 * Author URI:        https://am2am.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       idta-pdf
 * Domain Path:       /languages
 *
 * WC requires at least: 7.0
 * WC tested up to:      9.9
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

const VERSION     = '1.7.1';
const PLUGIN_FILE = __FILE__;

require_once __DIR__ . '/includes/class-autoloader.php';

Autoloader::register( __DIR__ . '/includes' );

/**
 * Composer autoload, when the plugin ships with its own vendor directory.
 *
 * Falls back to an already-registered autoloader (for example the active
 * theme's) so mPDF / endroid can be shared instead of duplicated.
 */
if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Retrieve the plugin container.
 *
 * @return Plugin
 */
function plugin(): Plugin {
	return Plugin::instance();
}

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PLUGIN_FILE,
				true
			);
		}
	}
);

add_action( 'plugins_loaded', static fn() => plugin()->boot(), 20 );

register_activation_hook( __FILE__, array( Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Plugin::class, 'deactivate' ) );
