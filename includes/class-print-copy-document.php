<?php
/**
 * Registration copy, for printing onto pre-printed booklet stock.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * The holder's own details alone, positioned as the booklet prints them.
 *
 * This is an overlay, not a document to read: it is fed through a printer over
 * booklet stock that already carries the fixed wording, so it prints only what
 * varies from one permit to the next — the expiry date on the cover, and the
 * numbered fields, portrait, signature, stamp and category seals on the holder
 * page. Everything else is still laid out, but invisible, because the visible
 * items sit where the blocks above them put them.
 *
 * Four pages, alternating blank and printed, so a duplex pass lands each
 * overlay on the right leaf of the booklet:
 *
 *   1  blank
 *   2  the cover's expiry date
 *   3  blank
 *   4  the holder page's details
 *
 * A5, matching the stock. Every position in assets/css/print-copy.css is
 * measured off the printed pages themselves — assets/img/demos/page-01.jpg and
 * page-23.jpg, which are stored at A4 pixel dimensions but represent A5 pages —
 * rather than taken from the booklet: the stock's field rules are 6.81mm apart
 * where the booklet sets its own 10.64mm apart at A4, so a layout derived from
 * the booklet walks off the printed lines. Re-measure the stock, not the
 * booklet, if this ever needs adjusting.
 */
final class Print_Copy_Document extends Document {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'print-copy';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return sprintf(
			/* translators: %s: order number. */
			__( 'IDP Permit Print - Order %s', 'idta-pdf' ),
			$this->data->order()->get_order_number()
		);
	}

	/**
	 * A5 width, matching the stock.
	 *
	 * @return float
	 */
	public function width_mm(): float {
		return 148.0;
	}

	/**
	 * A5 height, matching the stock.
	 *
	 * @return float
	 */
	public function height_mm(): float {
		return 210.0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array{top:float,right:float,bottom:float,left:float}
	 */
	public function margins(): array {
		/**
		 * Filters the print copy's page margins, in millimetres.
		 *
		 * @param array{top:float,right:float,bottom:float,left:float} $margins Margins.
		 */
		return (array) apply_filters(
			'idta_pdf_print_copy_margins',
			array(
				'top'    => 0.0,
				'right'  => 0.0,
				'bottom' => 0.0,
				'left'   => 0.0,
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	protected function template(): string {
		return 'print-copy.php';
	}

	/**
	 * Add the values this overlay prints to the template context.
	 *
	 * @return array<string,mixed>
	 */
	protected function context(): array {
		$context = parent::context();

		/*
		 * Cropped square, as the booklet's holder page crops it, so the portrait
		 * lands in the same frame at the same proportions.
		 */
		$context['photo'] = $this->images->embed( $this->data->passport_photo(), false, 1.0 );

		$context['stamp'] = $this->images->embed( Artwork::brand( 'stamp' ) );

		/*
		 * Only a held category is sealed. The booklet prints a blank seal in the
		 * empty boxes; here there is nothing to print over them, so they are left
		 * alone.
		 */
		$context['seal'] = $this->images->embed( Artwork::seal( true ) );

		return $context;
	}
}
