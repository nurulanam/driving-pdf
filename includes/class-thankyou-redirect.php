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
	 * Built-in thank-you page for each `_idp_order_from` value.
	 *
	 * The canonical fallback: Settings::thankyou_destinations() returns these
	 * when the settings screen's "Thank-you redirects" table has not been
	 * configured, exactly as Order_Data::SOURCES backs asset_sources().
	 *
	 * @var array<string,string>
	 */
	public const DESTINATIONS = array(
		'idta' => 'https://e-idta.com/thank-you.html',
		'idpa' => 'https://internationaldrivingpermitagency.com/thank-you/',
	);

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

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

		wp_redirect( $destination, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Deliberately off-site; the target comes from Settings::thankyou_destinations()/the filter, never from the request.

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
		 * The settings screen's "Thank-you redirects" table (IDTA PDF →
		 * Uploads) is the normal way to change these; this filter remains for
		 * a custom change that table can't express, applied on top of
		 * whatever is configured there.
		 *
		 * @param array<string,string> $destinations URLs keyed by source.
		 * @param \WC_Order            $order        Order object.
		 */
		$destinations = (array) apply_filters(
			'idta_pdf_thankyou_destinations',
			$this->settings->thankyou_destinations(),
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
		$arg = (string) apply_filters( 'idta_pdf_thankyou_order_arg', 'order-id', $order );

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

		$args = array( $arg => $reference );

		/*
		 * The conversion details the thank-you page reports to its own
		 * analytics. Named the way Google Ads and GA4 name them, since that is
		 * what a thank-you page's tag expects to read.
		 */
		$transaction = trim( (string) $order->get_transaction_id() );

		if ( '' !== $transaction ) {
			// The gateway's receipt id, and only when the gateway gave one: a
			// bank transfer or a cheque leaves it empty, and an empty
			// transaction-id in a conversion tag is worse than none, since it
			// deduplicates against every other order that also sent nothing.
			$args['transaction-id'] = $transaction;
		}

		/*
		 * Everything from here down is for the thank-you page's own content —
		 * the conversion tag above only reads transaction-id/currency/value —
		 * so each of these is optional and the page is expected to fall back
		 * sensibly when one is missing (an order the checkout didn't finish
		 * writing IDP meta for, for instance).
		 */
		$data = new Order_Data( $order );

		$email = trim( $data->email() );

		if ( '' !== $email ) {
			$args['email'] = $email;
		}

		// Whatever the checkout actually sent ('digital_only' or
		// 'print_digital'); the thank-you page branches its own copy on this
		// rather than trying to re-derive it from the line items.
		$format = trim( (string) $order->get_meta( '_idp_format', true ) );

		if ( '' !== $format ) {
			$args['format'] = $format;
		}

		$args['validity-years'] = (string) $data->validity_years();

		$payment_method = trim( $order->get_payment_method_title() );

		if ( '' !== $payment_method ) {
			// Shown as-is, not reformatted into "Brand •••• 1234": this plugin
			// has no reliable access to the card's last four digits (that
			// lives in the payment gateway's own, gateway-specific meta, not
			// anything _idp_ writes), and a fabricated card number would be
			// worse than none.
			$args['payment-method'] = $payment_method;
		}

		$created = $order->get_date_created();

		if ( $created instanceof \WC_DateTime ) {
			// ISO 8601, so the thank-you page can format it in the visitor's
			// own locale rather than this site's.
			$args['order-date'] = $created->format( \DateTimeInterface::ATOM );
		}

		$args['currency'] = $order->get_currency();

		/*
		 * A plain decimal, so the tag reads a number rather than "1.200,00":
		 * wc_format_decimal normalises whatever separator the store's locale
		 * uses down to a dot.
		 *
		 * Deliberately without a decimal place count. Passing one would round to
		 * the *store's* configured precision, and this store sells in more than
		 * one currency: with the store set to JPY's zero decimals, a $59.90
		 * order reported a conversion value of 60. Left alone, the order's own
		 * total passes through at whatever precision it was actually charged at.
		 */
		$args['value'] = wc_format_decimal( $order->get_total() );

		/**
		 * Filters the query arguments added to the thank-you URL.
		 *
		 * @param array<string,string> $args  Arguments, before encoding.
		 * @param \WC_Order            $order Order object.
		 */
		$args = (array) apply_filters( 'idta_pdf_thankyou_query_args', $args, $order );

		$encoded = array();

		foreach ( $args as $name => $value ) {
			// Encoded by hand rather than left to add_query_arg, which encodes
			// the value but not the argument name.
			$encoded[ rawurlencode( (string) $name ) ] = rawurlencode( (string) $value );
		}

		return add_query_arg( $encoded, $url );
	}
}
