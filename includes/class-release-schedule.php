<?php
/**
 * Decides when an order's documents may be downloaded.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * When an order's documents become available, and whether they are yet.
 *
 * Nothing is generated in advance and nothing is stored: a document is rendered
 * when its URL is requested. That moves the store's delivery rules from "when
 * do we build this" to "when may this be fetched", which is what this class
 * answers.
 *
 * The rule has three parts, and all three must hold:
 *
 *   - the order is paid, because the wait is measured from payment;
 *   - the order's status is one the store releases documents for;
 *   - the wait for this kind of order has passed.
 *
 * The wait depends on what was bought: an order containing one of the rush
 * products is released within minutes, everything else waits. An operator is
 * never held to any of this — see Download_Handler, where an authenticated
 * admin request skips the gate so a permit can always be produced on request.
 */
final class Release_Schedule {

	/**
	 * Plugin settings.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Whether an order's documents may be downloaded now.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public function is_released( \WC_Order $order ): bool {
		/*
		 * Once the customer has been sent their links, the order stays released
		 * for good. Those links are in an email they keep, and a link that was
		 * handed out and then stopped working is worse than one that was never
		 * sent: the customer has no way to tell a withdrawn permit from a broken
		 * site, and the first they learn of it is at a roadside.
		 *
		 * It also makes the state stable. Without this, a later status change, a
		 * longer wait typed into the settings, or a refund being processed and
		 * reversed would each silently take the permit back.
		 *
		 * A store that does need to withdraw one can still do so through the
		 * idta_pdf_is_released filter below, which is the right place for a
		 * decision that deliberate.
		 */
		if ( $this->has_been_notified( $order ) ) {
			/** This filter is documented below. */
			return (bool) apply_filters( 'idta_pdf_is_released', true, $order );
		}

		$released_at = $this->released_at( $order );

		$released = 0 !== $released_at
			&& $this->status_allows( $order )
			&& time() >= $released_at;

		/**
		 * Filters whether an order's documents may be downloaded.
		 *
		 * @param bool      $released Whether the documents are available.
		 * @param \WC_Order $order    Order object.
		 */
		return (bool) apply_filters( 'idta_pdf_is_released', $released, $order );
	}

	/**
	 * Whether the customer has been sent their permit links.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public function has_been_notified( \WC_Order $order ): bool {
		return '' !== (string) $order->get_meta( Release_Notifier::SENT_META, true );
	}

	/**
	 * When an order's documents become available.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return int Unix timestamp, or zero when the order is not paid and so has
	 *             nothing to measure the wait from.
	 */
	public function released_at( \WC_Order $order ): int {
		$paid = $this->paid_at( $order );

		return 0 !== $paid ? $paid + $this->delay( $order ) : 0;
	}

	/**
	 * When an order was paid.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return int Unix timestamp, or zero when the order records no payment.
	 */
	public function paid_at( \WC_Order $order ): int {
		$paid = $order->get_date_paid();

		if ( null !== $paid ) {
			return (int) $paid->getTimestamp();
		}

		/*
		 * A paid status with no payment date: an order an operator marked
		 * processing or completed by hand, or a gateway that records none. The
		 * order is paid as far as the store is concerned, so it is treated as
		 * such and the wait runs from when the order was placed, which is the
		 * only other moment available.
		 */
		if ( $order->is_paid() ) {
			$created = $order->get_date_created();

			return null !== $created ? (int) $created->getTimestamp() : 0;
		}

		return 0;
	}

	/**
	 * How long after payment this order's documents are held back.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return int Seconds.
	 */
	public function delay( \WC_Order $order ): int {
		$rush = $this->is_rush( $order );

		$delay = $rush
			? $this->settings->rush_delay()
			: $this->settings->standard_delay();

		/**
		 * Filters how long after payment an order's documents are held back.
		 *
		 * @param int       $delay Delay in seconds.
		 * @param bool      $rush  Whether the order counts as a rush job.
		 * @param \WC_Order $order Order object.
		 */
		return max( 0, (int) apply_filters( 'idta_pdf_generation_delay', $delay, $rush, $order ) );
	}

	/**
	 * Whether an order contains a product that makes it a rush job.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public function is_rush( \WC_Order $order ): bool {
		$rush_products = $this->settings->rush_products();
		$is_rush       = false;

		if ( array() !== $rush_products ) {
			foreach ( $order->get_items() as $item ) {
				if ( ! $item instanceof \WC_Order_Item_Product ) {
					continue;
				}

				/*
				 * The variation as well as the parent: a rush option sold as a
				 * variation of another product carries its own ID, and a store
				 * may well have configured either one.
				 */
				$ids = array_filter( array( (int) $item->get_product_id(), (int) $item->get_variation_id() ) );

				if ( array() !== array_intersect( $ids, $rush_products ) ) {
					$is_rush = true;

					break;
				}
			}
		}

		/**
		 * Filters whether an order is treated as a rush job.
		 *
		 * @param bool      $is_rush Whether the order is a rush job.
		 * @param \WC_Order $order   Order object.
		 */
		return (bool) apply_filters( 'idta_pdf_is_rush_order', $is_rush, $order );
	}

	/**
	 * Whether the order's status is one the store releases documents for.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return bool
	 */
	public function status_allows( \WC_Order $order ): bool {
		return in_array( $order->get_status(), $this->settings->release_statuses(), true );
	}

	/**
	 * Why an order's documents are unavailable, in a sentence for the customer.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string Empty when the documents are available.
	 */
	public function explain( \WC_Order $order ): string {
		if ( $this->is_released( $order ) ) {
			return '';
		}

		if ( 0 === $this->paid_at( $order ) ) {
			return __( 'This permit becomes available once payment for the order has been received.', 'idta-pdf' );
		}

		if ( ! $this->status_allows( $order ) ) {
			// Deliberately vague about which status: the customer cannot act on
			// "on-hold" or "refunded", and the reason may be one the store would
			// rather explain itself.
			return __( 'This permit is not available for download at the moment. Please contact us.', 'idta-pdf' );
		}

		return sprintf(
			/* translators: %s: a date and time, in the site's own format and timezone. */
			__( 'This permit is being prepared and can be downloaded from %s.', 'idta-pdf' ),
			$this->released_at_local( $order )
		);
	}

	/**
	 * Why an order is held back, in the terms an operator needs.
	 *
	 * Separate from explain(), which is written for the customer. An operator
	 * needs to know which of the three conditions is the one failing — and in
	 * particular needs not to be told "held until 7:03" about a time that passed
	 * hours ago, which is what a message that only ever reports the wait does
	 * when the real obstacle is the order's status.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string Empty when the order is released.
	 */
	public function reason( \WC_Order $order ): string {
		if ( $this->is_released( $order ) ) {
			return '';
		}

		if ( 0 === $this->paid_at( $order ) ) {
			return __( 'Held back: the order is not paid, so there is nothing to time the wait from.', 'idta-pdf' );
		}

		if ( ! $this->status_allows( $order ) ) {
			return sprintf(
				/* translators: %s: an order status, e.g. "completed". */
				__( 'Held back: documents are not released for the "%s" status. Change it under IDTA PDF → Release.', 'idta-pdf' ),
				$order->get_status()
			);
		}

		return sprintf(
			/* translators: %s: a date and time. */
			__( 'Held back from the customer until %s.', 'idta-pdf' ),
			$this->released_at_local( $order )
		);
	}

	/**
	 * The release time, formatted in the site's timezone and date format.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return string Empty when the order has no release time yet.
	 */
	public function released_at_local( \WC_Order $order ): string {
		$released_at = $this->released_at( $order );

		if ( 0 === $released_at ) {
			return '';
		}

		// wp_date() renders in the site's timezone; the timestamp is UTC-based.
		return (string) wp_date(
			get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
			$released_at
		);
	}
}
