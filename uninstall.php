<?php
/**
 * Uninstall routine.
 *
 * Removes settings and generated documents. Order meta written by the checkout
 * flow (`_idp_*`) is left untouched, since it is not owned by this plugin.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'idta_pdf_settings' );

$uploads = wp_get_upload_dir();
$base    = trailingslashit( $uploads['basedir'] ) . 'idta-pdf';

if ( ! is_dir( $base ) ) {
	return;
}

/**
 * Recursively delete a directory inside the uploads folder.
 *
 * @param string $dir  Absolute path.
 * @param string $root Uploads base directory, used as a safety boundary.
 */
$idta_pdf_rmdir = static function ( string $dir, string $root ) use ( &$idta_pdf_rmdir ): void {
	$dir  = wp_normalize_path( $dir );
	$root = wp_normalize_path( trailingslashit( $root ) );

	// Refuse to walk outside the uploads directory.
	if ( ! str_starts_with( $dir, $root ) ) {
		return;
	}

	$entries = glob( $dir . '/*' );

	if ( is_array( $entries ) ) {
		foreach ( $entries as $entry ) {
			if ( is_dir( $entry ) && ! is_link( $entry ) ) {
				$idta_pdf_rmdir( $entry, $root );

				continue;
			}

			wp_delete_file( $entry );
		}
	}

	// Hidden guard files are not matched by the glob above.
	foreach ( array( '/.htaccess', '/index.php' ) as $guard ) {
		if ( is_file( $dir . $guard ) ) {
			wp_delete_file( $dir . $guard );
		}
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.directory_rmdir
	@rmdir( $dir );
};

$idta_pdf_rmdir( $base, $uploads['basedir'] );
