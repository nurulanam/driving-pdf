<?php
/**
 * Credit-card sized permit card.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * The two-sided ID card, 85.6 x 53.98 mm, declared portrait.
 *
 * The page is wider than it is tall but is deliberately declared portrait so
 * the width and height are used verbatim by the engine rather than swapped.
 */
final class Card_Document extends Document {

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function slug(): string {
		return 'card';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function title(): string {
		return sprintf(
			/* translators: %s: order number. */
			__( 'IDP Card - Order %s', 'idta-pdf' ),
			$this->data->order()->get_order_number()
		);
	}

	/**
	 * ISO/IEC 7810 ID-1 width.
	 *
	 * @return float
	 */
	public function width_mm(): float {
		return 85.6;
	}

	/**
	 * ISO/IEC 7810 ID-1 height.
	 *
	 * @return float
	 */
	public function height_mm(): float {
		return 53.98;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	protected function template(): string {
		return 'card.php';
	}

	/**
	 * Add card-specific values to the template context.
	 *
	 * @return array<string,mixed>
	 */
	protected function context(): array {
		$context = parent::context();

		$context['front_background'] = $this->images->embed( Artwork::card( 'front', $this ) );
		$context['back_background']  = $this->images->embed( Artwork::card( 'back', $this ) );
		$context['stamp']            = $this->images->embed( Artwork::card( 'stamp', $this ) );

		// The card prints categories in a single block whose size depends on
		// how many were granted.
		$count = count( $context['categories'] );

		$context['category_font_pt'] = match ( true ) {
			1 === $count => 20.0,
			2 === $count => 11.0,
			default      => 8.0,
		};

		$context['category_rows'] = $count > 2
			? array_chunk( $context['categories'], 2 )
			: array_map( static fn( string $value ): array => array( $value ), $context['categories'] );

		return $context;
	}

}
