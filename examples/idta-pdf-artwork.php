<?php
/**
 * Plugin Name: IDTA PDF — Artwork overrides
 * Description: Optional. Points IDTA PDF at artwork in the media library instead of the copies bundled with the plugin. Copy to wp-content/mu-plugins/ or paste into your theme's functions.php.
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 *
 * The plugin already ships working artwork in `assets/img`, so none of this is
 * required. Use it when the artwork should be managed in the media library —
 * for example to update a scanned page without redeploying the plugin.
 *
 * Every reference may be an absolute URL, a site-relative path such as
 * /wp-content/uploads/..., or an absolute local path. Anything unreachable is
 * skipped rather than breaking the document.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Scanned pages printed before the holder details page, in print order.
 */
add_filter(
	'idta_pdf_booklet_interior_pages',
	static function ( array $pages ): array {
		$pages = array(
			'/wp-content/uploads/2026/05/countries-file-page-0002.jpg',
		);

		foreach ( range( 3, 22 ) as $page ) {
			// Page 5 was replaced with a newer scan.
			if ( 5 === $page ) {
				$pages[] = '/wp-content/uploads/2026/01/final-booklet-IDPA_page-0005-latest.jpg';

				continue;
			}

			$pages[] = sprintf(
				'/wp-content/uploads/2025/12/final-booklet-IDPA_page-%04d.jpg',
				$page
			);
		}

		return $pages;
	}
);

/**
 * Pages printed after the holder details page — the back cover.
 */
add_filter(
	'idta_pdf_booklet_closing_pages',
	static fn( array $pages ): array => array(
		'/wp-content/uploads/2025/12/final-booklet-IDPA_page-0024.jpg',
	)
);

/**
 * Seals stamped over each permit category.
 */
add_filter(
	'idta_pdf_seal_url',
	static function ( string $url, bool $granted ): string {
		return $granted
			? '/wp-content/uploads/2025/12/stamp.png'
			: '/wp-content/uploads/2025/03/non-sealed.png';
	},
	10,
	2
);

/**
 * Booklet branding.
 */
add_filter(
	'idta_pdf_brand_url',
	static function ( string $url, string $key ): string {
		return match ( $key ) {
			'logo'      => '/wp-content/uploads/2025/12/idp-single-logo.png',
			'signature' => '/wp-content/uploads/2025/12/idp-signature.png',
			'stamp'     => '/wp-content/uploads/2025/12/blank-stamp.png',
			default     => $url,
		};
	},
	10,
	2
);

/**
 * Card faces and overlay stamp.
 */
add_filter(
	'idta_pdf_card_artwork_url',
	static function ( string $url, string $key ): string {
		return match ( $key ) {
			'front' => '/wp-content/uploads/2026/01/white-front.jpeg',
			'back'  => '/wp-content/uploads/2026/01/white-back.jpeg',
			'stamp' => '/wp-content/uploads/2025/12/blank-stamp.png',
			default => $url,
		};
	},
	10,
	2
);
