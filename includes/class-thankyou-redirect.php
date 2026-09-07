<?php
/**
 * Sends a paid order to its own brand's thank-you page.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects WooCommerce's order-received page to the front end that took the
 * order, chosen by `_idp_order_from`.
 *
 * The two front ends share this store, so a customer who ordered on one brand
 * would otherwise land on the other's confirmation. The destination is keyed by
 * the same meta the upload buckets are keyed by, so a source that has one has
 * the other.
 *
 * The order's key is checked before redirecting, exactly as WooCommerce checks
 * it before rendering that page: the order number goes to a third-party site in
 * the query string, so the request has to prove it is for that order first.
 */
final class Thankyou_Redirect {

	/**
	 * Thank-you page for each `_idp_order_from` value.
	 *
	 * @var array<string,string>
	 */
	private const DESTINATIONS = array(
		'idta' => 'https://e-idta.com/thank-you.html',
		'idpa' => 'https://internationaldrivingpermitagency.com/thank-you/',
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		/*
		 * template_redirect, not woocommerce_thankyou: that fires from inside the
		 * template, once output has begun, which is too late to redirect.
		 */
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ) );
	}

	/**
	 * Leave the order-received page for the brand that took the order.
	 */
	public function maybe_redirect(): void {
		if ( ! function_exists( 'is_order_received_page' ) || ! is_order_received_page() ) {
			return;
		}

		$order_id = absint( get_query_var( 'order-received' ) );

		if ( ! $order_id ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key is the credential, as it is for the page itself.
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';

		// Compared in constant time; the key is a per-order secret.
		if ( '' === $key || ! hash_equals( $order->get_order_key(), $key ) ) {
			return;
		}

		$destination = $this->destination( $order );

		if ( '' === $destination ) {
			return;
		}

		wp_redirect( $destination, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Deliberately off-site; the target comes from self::DESTINATIONS, never from the request.

		exit;
	}

	/**
	 * Thank-you URL for an order, with its reference appended.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string URL, or an empty string to leave the page alone.
	 */
	private function destination( \WC_Order $order ): string {
		/*
		 * Read straight from the meta rather than through
		 * Order_Data::order_from(), which falls back to a default source so that
		 * an order with no source still resolves its uploads. That fallback is
		 * right for finding a file and wrong for this: an order that names no
		 * brand should stay on WooCommerce's own page, not be sent to whichever
		 * brand happens to be the default.
		 */
		$source = strtolower( trim( (string) $order->get_meta( '_idp_order_from', true ) ) );

		/**
		 * Filters the thank-you page for each `_idp_order_from` value.
		 *
		 * @param array<string,string> $destinations URLs keyed by source.
		 * @param \WC_Order            $order        Order object.
		 */
		$destinations = (array) apply_filters(
			'idta_pdf_thankyou_destinations',
			self::DESTINATIONS,
			$order
		);

		$url = isset( $destinations[ $source ] ) ? (string) $destinations[ $source ] : '';

		if ( '' === $url ) {
			return '';
		}

		/**
		 * Filters the query argument the order reference is passed in.
		 *
		 * @param string    $arg   Query argument name.
		 * @param \WC_Order $order Order object.
		 */
		$arg = (string) apply_filters( 'idta_pdf_thankyou_order_arg', 'order_id', $order );

		/**
		 * Filters the order reference sent to the thank-you page.
		 *
		 * The order number rather than the ID: it is what the customer is shown
		 * and what a sequential-numbering plugin would rewrite, and the two are
		 * the same until such a plugin is in play.
		 *
		 * @param string    $reference Reference, e.g. "idta-957".
		 * @param string    $source    Source key.
		 * @param \WC_Order $order     Order object.
		 */
		$reference = (string) apply_filters(
			'idta_pdf_thankyou_order_reference',
			$source . '-' . $order->get_order_number(),
			$source,
			$order
		);

		return add_query_arg( rawurlencode( $arg ), rawurlencode( $reference ), $url );
	}
}
