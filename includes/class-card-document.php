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

		/*
		 * The two generated codes, built here rather than in Document::context()
		 * because the card is the only document that prints them: the booklet
		 * carries a fixed verification mark on both its cover and its holder
		 * page, and was paying to build and embed two QR images it never drew.
		 */
		$context['permit_qr']  = $this->qr->permit_qr( $this->data );
		$context['details_qr'] = $this->qr->details_qr( $this->data );

		/*
		 * The card's parts. Both faces are composed from these rather than printed
		 * over one pre-rendered background, so every string on the card is live
		 * text.
		 */
		foreach ( array( 'head_logo', 'stamp', 'front_bg', 'back_logo' ) as $key ) {
			$context[ $key ] = $this->images->embed( Artwork::card( $key, $this ) );
		}

		/*
		 * One icon per vehicle category, keyed by the letter the back's table
		 * prints. A category with no artwork resolves to an empty string and its
		 * row prints without an icon.
		 */
		$icons = array();

		foreach ( Order_Data::CATEGORIES as $letter ) {
			$icons[ $letter ] = $this->images->embed(
				Artwork::card( 'icon_' . strtolower( $letter ), $this )
			);
		}

		$context['category_icons'] = $icons;

		/*
		 * Both portraits are cropped square, which is the shape a passport photo
		 * already is. They were cropped to the wider frame the reference prints
		 * them in — 23 x 19.74mm — and since crop_to_aspect centres its crop,
		 * that quietly cut about 15% off the top and bottom of every portrait.
		 *
		 * The crop stays rather than being dropped: a portrait upload that is not
		 * square is centre-cropped to square here, where the alternative is mPDF
		 * drawing it at the declared width and whatever height its own
		 * proportions give, running it past its frame.
		 */
		$context['photo'] = $this->images->embed(
			$this->data->passport_photo(),
			false,
			1.0
		);

		$context['photo_gray'] = $this->images->embed(
			$this->data->passport_photo(),
			$this->settings->grayscale_ghost(),
			1.0
		);

		/*
		 * The nine numbered values, in the order the face prints them. Field 6 is
		 * the photograph and signature, so it carries no text.
		 */
		$upper = static function ( $value ): string {
			$value = (string) $value;

			return function_exists( 'mb_strtoupper' )
				? mb_strtoupper( $value, 'UTF-8' )
				: strtoupper( $value );
		};

		/*
		 * Names and places print in capitals, as the card sets them. The dates
		 * and the licence number are left exactly as the holder's own licence
		 * gives them — a licence number can be case-significant, and upper-casing
		 * one would misstate it.
		 */
		$context['card_values'] = array(
			'1' => $upper( $context['last_name'] ),
			'2' => $upper( $context['given_names'] ),
			'3' => $upper( $context['birth_country'] ),
			'4' => (string) $context['date_of_birth'],
			'5' => $upper( $context['residence'] ),
			'7' => (string) $context['issue_date'],
			'8' => (string) $context['expiry_date'],
			'9' => (string) $context['license_number'],
		);

		$context['card_class'] = implode( ', ', (array) $context['categories'] );


		return $context;
	}

}
