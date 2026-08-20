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
	 * Point size the card artwork sets its field values in.
	 */
	private const VALUE_PT = 5.6;

	/**
	 * Point size for the issuing authority, which prints smaller and over two
	 * lines on the artwork.
	 */
	private const AUTHORITY_PT = 5.0;

	/**
	 * Smallest size a value may be shrunk to before it is left to overflow.
	 */
	private const MIN_VALUE_PT = 4.0;

	/**
	 * Printable width of the left field column, in millimetres.
	 *
	 * Measured from the artwork: the column starts 21.39mm from the card's left
	 * edge and the right column starts at 46.85mm.
	 */
	private const COLUMN_MM = 23.5;

	/**
	 * Advance width of a character as a fraction of the font size, by class.
	 *
	 * Calibrated against Roboto Condensed Bold and rounded up, so a value is
	 * never estimated narrower than it prints — guessing high costs a slightly
	 * smaller type size, guessing low would let a value run into the next column.
	 *
	 * @var array<string,float>
	 */
	private const GLYPH_EMS = array(
		'upper' => 0.58,
		'lower' => 0.47,
		'digit' => 0.51,
		'space' => 0.24,
		'other' => 0.34,
	);

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

		/*
		 * The two generated codes, built here rather than in Document::context()
		 * because the card is the only document that prints them: the booklet
		 * carries a fixed verification mark on both its cover and its holder
		 * page, and was paying to build and embed two QR images it never drew.
		 */
		$context['permit_qr']  = $this->qr->permit_qr( $this->data );
		$context['details_qr'] = $this->qr->details_qr( $this->data );

		$context['front_background'] = $this->images->embed( Artwork::card( 'front', $this ) );
		$context['back_background']  = $this->images->embed( Artwork::card( 'back', $this ) );
		$context['stamp']            = $this->images->embed( Artwork::card( 'stamp', $this ) );

		/*
		 * Both portraits are cropped to the shape the artwork prints them at
		 * before the engine sees them. mPDF ignores `height` on an image and draws
		 * it at whatever height its own proportions give, so a tall upload would
		 * otherwise run past its frame and push the signature down the face.
		 */
		$context['photo'] = $this->images->embed(
			$this->data->passport_photo(),
			false,
			13.45 / 14.82
		);

		$context['photo_gray'] = $this->images->embed(
			$this->data->passport_photo(),
			$this->settings->grayscale_ghost(),
			7.13 / 7.81
		);

		/**
		 * Filters the issuing authority printed in the card's last field.
		 *
		 * @param string $authority Authority name.
		 */
		$context['authority'] = (string) apply_filters(
			'idta_pdf_card_authority',
			__( 'International Document Translation Agency', 'idta-pdf' )
		);

		$context['card_fields'] = $this->card_fields( $context );

		return $context;
	}

	/**
	 * The card's eight numbered fields, in the artwork's two-column order.
	 *
	 * Each carries its own point size: the artwork sets every value at the same
	 * size, but a long name or permit number would run into the next column, so
	 * anything too wide is stepped down to fit.
	 *
	 * @param array<string,mixed> $context Template context so far.
	 *
	 * @return array<int,array{num:string,label:string,value:string,pt:float,wrap:bool}>
	 */
	private function card_fields( array $context ): array {
		$fields = array(
			array(
				'num'   => '1.',
				'label' => __( 'FULL NAME / NOM COMPLET', 'idta-pdf' ),
				'value' => (string) $context['full_name'],
			),
			array(
				'num'   => '2.',
				'label' => __( 'DATE OF BIRTH / DATE DE NAISSANCE', 'idta-pdf' ),
				'value' => (string) $context['date_of_birth'],
			),
			array(
				'num'   => '3.',
				'label' => __( 'PLACE OF BIRTH / LIEU DE NAISSANCE', 'idta-pdf' ),
				'value' => (string) $context['birth_country'],
			),
			array(
				'num'   => '4.',
				'label' => __( 'LICENCE NO. / N° DE LICENCE', 'idta-pdf' ),
				'value' => (string) $context['license_number'],
			),
			array(
				'num'   => '5.',
				'label' => __( 'ISSUED DATE / DÉLIVRÉ LE', 'idta-pdf' ),
				'value' => (string) $context['issue_date'],
			),
			array(
				'num'   => '6.',
				'label' => __( 'EXPIRY DATE / DATE D\'EXPIRATION', 'idta-pdf' ),
				'value' => (string) $context['expiry_date'],
			),
			array(
				'num'   => '7.',
				'label' => __( 'CLASS', 'idta-pdf' ),
				'value' => implode( ', ', (array) $context['categories'] ),
			),
			array(
				'num'   => '8.',
				'label' => __( 'ISSUING AUTHORITY / AUTORITÉ', 'idta-pdf' ),
				'value' => (string) $context['authority'],
				// The only field the artwork sets over two lines, and the only one
				// not upper-cased, so it is left to wrap at its own size instead of
				// being shrunk onto one line.
				'wrap'  => true,
			),
		);

		foreach ( $fields as $index => $field ) {
			$wrap  = ! empty( $field['wrap'] );
			$value = (string) $field['value'];

			/*
			 * Upper-cased here rather than with `text-transform`, which mPDF does
			 * not inherit into a table cell — and, more to the point, so that the
			 * string measured below is the string that prints. Measuring the mixed
			 * case form and printing capitals underestimates the width badly
			 * enough to run a long name into the next column.
			 */
			if ( ! $wrap ) {
				$value = function_exists( 'mb_strtoupper' )
					? mb_strtoupper( $value, 'UTF-8' )
					: strtoupper( $value );
			}

			$fields[ $index ]['value'] = $value;
			$fields[ $index ]['wrap']  = $wrap;
			$fields[ $index ]['pt']    = $wrap
				? self::AUTHORITY_PT
				: $this->fit_pt( $value, self::VALUE_PT );
		}

		return $fields;
	}

	/**
	 * Largest point size at which a value still fits its column.
	 *
	 * @param string $value      Text to be printed.
	 * @param float  $preferred  Size the artwork uses.
	 *
	 * @return float
	 */
	private function fit_pt( string $value, float $preferred ): float {
		$ems = $this->estimate_ems( $value );

		if ( $ems <= 0.0 ) {
			return $preferred;
		}

		// A point is 0.352778mm.
		$width = $ems * $preferred * 0.352778;

		if ( $width <= self::COLUMN_MM ) {
			return $preferred;
		}

		$fitted = self::COLUMN_MM / ( $ems * 0.352778 );

		return round( max( $fitted, self::MIN_VALUE_PT ), 1 );
	}

	/**
	 * Estimated width of a string, in multiples of the font size.
	 *
	 * @param string $value Text to measure.
	 *
	 * @return float
	 */
	private function estimate_ems( string $value ): float {
		$ems        = 0.0;
		$characters = preg_split( '//u', $value, -1, PREG_SPLIT_NO_EMPTY );

		foreach ( (array) $characters as $character ) {
			$character = (string) $character;

			if ( ' ' === $character ) {
				$ems += self::GLYPH_EMS['space'];
			} elseif ( 1 === preg_match( '/^\p{Lu}$/u', $character ) ) {
				$ems += self::GLYPH_EMS['upper'];
			} elseif ( 1 === preg_match( '/^\p{Ll}$/u', $character ) ) {
				$ems += self::GLYPH_EMS['lower'];
			} elseif ( 1 === preg_match( '/^\p{Nd}$/u', $character ) ) {
				$ems += self::GLYPH_EMS['digit'];
			} else {
				$ems += self::GLYPH_EMS['other'];
			}
		}

		return $ems;
	}

}
