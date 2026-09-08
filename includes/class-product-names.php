<?php
/**
 * Product names as they appear on orders.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Renames the store's permit products wherever an order line is shown.
 *
 * The catalogue's own product titles are not what a customer should read on an
 * order, an email or an invoice — they carry internal wording and say nothing
 * about the term or what is included. This maps each product to the name the
 * store wants printed, and applies everywhere WooCommerce asks what a line is
 * called: the order screens, the thank-you page, the emails and the customer's
 * account.
 *
 * It renames the line only. The product itself, its slug and its catalogue
 * title are untouched, and an order placed before this existed reads the same
 * as one placed after, because the name is resolved at display time rather than
 * copied onto the line when the order was made.
 *
 * Both the property getter and the display filter are hooked — see register()
 * for why one alone is not enough.
 */
final class Product_Names {

	/**
	 * Printed name for each product ID.
	 *
	 * Keyed by the product, not the variation: the store sells these as plain
	 * products. A variation ID would need matching separately — see rename().
	 *
	 * @var array<int,string>
	 */
	private const NAMES = array(
		14 => 'IDP - 1 Year - Digital Only',
		15 => 'IDP - 1 Years - Card + Booklet',
		16 => 'IDP - 2 Year - Digital Only',
		17 => 'IDP - 2 Years - Card + Booklet',
		18 => 'IDP - 3 Year - Digital Only',
		19 => 'IDP - 3 Years - Card + Booklet',
	);

	/**
	 * Register hooks.
	 */
	public function register(): void {
		/*
		 * The property getter, which is the one that catches everything.
		 * WC_Order_Item resolves get_name() through WC_Data::get_prop(), and
		 * that applies this filter on every read in "view" context — so the
		 * mapped name reaches the admin's own new-order email, the order
		 * screens, invoices, exports and the REST API alike, without depending
		 * on which template a given mail happens to render through.
		 *
		 * "view" context only, deliberately. WooCommerce reads props in "edit"
		 * context when it saves, and that path is not filtered, so the name
		 * stored on the line is never overwritten by the mapped one. Remove the
		 * mapping and every order goes back to reading as it did.
		 */
		add_filter( 'woocommerce_order_item_get_name', array( $this, 'rename' ), 10, 2 );

		/*
		 * And the display filter as well. Several templates do not print
		 * get_name() directly — they wrap it in a link to the product first and
		 * pass that — so this catches the name in the form the template built,
		 * and any third-party code that calls the display filter with a name of
		 * its own. Mapping an already-mapped name returns the same string, so
		 * running through both is harmless.
		 */
		add_filter( 'woocommerce_order_item_name', array( $this, 'rename' ), 10, 2 );
	}

	/**
	 * The printed name for an order line.
	 *
	 * @param mixed $name Name WooCommerce was going to print.
	 * @param mixed $item Order line.
	 *
	 * @return mixed The mapped name, or what was passed in.
	 */
	public function rename( $name, $item = null ) {
		if ( ! $item instanceof \WC_Order_Item_Product ) {
			return $name;
		}

		$names = self::names();

		/*
		 * The variation first, then its parent. A variation that has its own
		 * entry wins; otherwise a variation of a mapped product inherits the
		 * parent's name, which is what a store selling one term as several
		 * variations would expect.
		 */
		$mapped = '';

		foreach ( array( (int) $item->get_variation_id(), (int) $item->get_product_id() ) as $id ) {
			if ( 0 !== $id && isset( $names[ $id ] ) ) {
				$mapped = $names[ $id ];

				break;
			}
		}

		if ( '' === $mapped ) {
			return $name;
		}

		/*
		 * Already carrying the mapped name: hand it back untouched, markup and
		 * all. This matters because both hooks run. The getter has already
		 * renamed the line by the time a template reads it, and a template like
		 * order-details-item.php then wraps that name in a link to the product
		 * before passing it to the display filter — so replacing it wholesale
		 * here would throw the link away and leave the customer plain text.
		 */
		if ( is_string( $name ) && '' !== $name && str_contains( $name, $mapped ) ) {
			return $name;
		}

		return $mapped;
	}

	/**
	 * The name map.
	 *
	 * @return array<int,string>
	 */
	public static function names(): array {
		/**
		 * Filters the printed name for each product ID.
		 *
		 * @param array<int,string> $names Names keyed by product or variation ID.
		 */
		$names = (array) apply_filters( 'idta_pdf_product_names', self::NAMES );

		$clean = array();

		foreach ( $names as $id => $name ) {
			$id = absint( $id );

			if ( 0 !== $id && is_string( $name ) && '' !== $name ) {
				$clean[ $id ] = $name;
			}
		}

		return $clean;
	}
}
