<?php
/**
 * Permit card template: 85.6 x 53.98 mm, front and back.
 *
 * Override by copying to `idta-pdf/card.php` in your theme.
 *
 * Available variables:
 *
 * @var \IDTA\PDF\Card_Document $document Document instance.
 * @var \IDTA\PDF\Order_Data    $data     Order data.
 * @var array<string,mixed>     $context  Template context.
 *
 * Embedded images are data URIs, so they are escaped with esc_attr() rather
 * than esc_url() — the latter drops the data: protocol.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$front_style = '' !== $context['front_background']
	? sprintf( ' style="background-image: url(\'%s\');"', esc_attr( $context['front_background'] ) )
	: '';

$back_style = '' !== $context['back_background']
	? sprintf( ' style="background-image: url(\'%s\');"', esc_attr( $context['back_background'] ) )
	: '';

/*
 * The eight numbered fields, prepared by Card_Document, print in two columns of
 * four: 1-4 on the left, 5-8 on the right. Each field is two table rows — the
 * label, then the value — so the row heights carry the artwork's vertical rhythm
 * rather than margins, which mPDF ignores inside a cell.
 */
$card_fields = (array) ( $context['card_fields'] ?? array() );
$card_left   = array_slice( $card_fields, 0, 4 );
$card_right  = array_slice( $card_fields, 4, 4 );

/**
 * Emit the label and value cells for one field, or empty cells when a column
 * runs out of fields.
 *
 * @param array<string,mixed>|null $field Field definition.
 * @param string                   $part  'label' or 'value'.
 * @param string                   $extra Extra class for the cell.
 */
$card_cell = static function ( ?array $field, string $part, string $extra = '' ): void {
	if ( null === $field ) {
		printf( '<td class="%s"></td>', esc_attr( trim( 'idta-card__' . $part . ' ' . $extra ) ) );

		return;
	}

	if ( 'label' === $part ) {
		printf(
			'<td class="%s">%s %s</td>',
			esc_attr( trim( 'idta-card__label ' . $extra ) ),
			esc_html( (string) $field['num'] ),
			esc_html( (string) $field['label'] )
		);

		return;
	}

	// The size is per field: a long name is stepped down so it cannot run into
	// the next column. See Card_Document::fit_pt().
	$classes = 'idta-card__value ' . $extra;

	if ( ! empty( $field['wrap'] ) ) {
		$classes .= ' idta-card__value--wrap';
	}

	printf(
		'<td class="%s" style="font-size: %spt;">%s</td>',
		esc_attr( trim( $classes ) ),
		esc_attr( (string) $field['pt'] ),
		esc_html( (string) $field['value'] )
	);
};
?>
<div class="idta-card idta-card--front"<?php echo $front_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr(). ?>>

	<?php
	/*
	 * The artwork already carries the whole header — logo, the title in six
	 * languages, the holographic seal and the guilloche divider — so nothing is
	 * drawn above this point; the padding on .idta-card--front is what clears it.
	 */
	?>
	<div class="idta-card__body">

		<?php
		/*
		 * Portrait and signature each sit in their own block, and the size goes on
		 * the image via its own class. Two bare `img` siblings share a line box,
		 * and mPDF then scales them to the float's width and ignores the width it
		 * was given; a descendant selector (`.wrapper img`) is ignored outright.
		 */
		?>
		<div class="idta-card__photo">
			<div>
				<?php if ( '' !== $context['photo'] ) : ?>
					<img class="idta-card__portrait" src="<?php echo esc_attr( $context['photo'] ); ?>" alt="">
				<?php endif; ?>
			</div>
			<div>
				<?php if ( '' !== $context['signature'] ) : ?>
					<img class="idta-card__signature" src="<?php echo esc_attr( $context['signature'] ); ?>" alt="">
				<?php endif; ?>
			</div>
		</div>

		<div class="idta-card__aside">
			<?php if ( '' !== $context['photo_gray'] ) : ?>
				<img class="idta-card__ghost" src="<?php echo esc_attr( $context['photo_gray'] ); ?>" alt="">
			<?php endif; ?>

			<?php if ( '' !== $context['details_qr'] ) : ?>
				<img class="idta-card__qr" src="<?php echo esc_attr( $context['details_qr'] ); ?>" alt="">
			<?php endif; ?>
		</div>

		<div class="idta-card__details">
			<table class="idta-card__fields">
				<?php foreach ( $card_left as $row => $field ) : ?>
					<tr>
						<?php
						$card_cell( $field, 'label', 'idta-card__cell--left' );
						$card_cell( $card_right[ $row ] ?? null, 'label', 'idta-card__cell--right' );
						?>
					</tr>
					<tr>
						<?php
						$card_cell( $field, 'value', 'idta-card__cell--left' );
						$card_cell( $card_right[ $row ] ?? null, 'value', 'idta-card__cell--right' );
						?>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="idta-clear"></div>
	</div>
</div>

<div class="idta-card idta-card--back"<?php echo $back_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr(). ?>>
	<?php if ( '' === $context['back_background'] ) : ?>
		<table class="idta-card__back-table">
			<tr>
				<td class="idta-card__back-label"><?php esc_html_e( 'Permit No.', 'idta-pdf' ); ?></td>
				<td class="idta-card__back-value"><?php echo esc_html( $context['card_number'] ); ?></td>
			</tr>
			<tr>
				<td class="idta-card__back-label"><?php esc_html_e( 'Licence No.', 'idta-pdf' ); ?></td>
				<td class="idta-card__back-value"><?php echo esc_html( $context['license_number'] ); ?></td>
			</tr>
			<tr>
				<td class="idta-card__back-label"><?php esc_html_e( 'Issued by', 'idta-pdf' ); ?></td>
				<td class="idta-card__back-value"><?php echo esc_html( $context['issuance'] ); ?></td>
			</tr>
			<tr>
				<td class="idta-card__back-label"><?php esc_html_e( 'Convention', 'idta-pdf' ); ?></td>
				<td class="idta-card__back-value"><?php echo esc_html( $context['convention'] ); ?></td>
			</tr>
		</table>

		<?php if ( '' !== $context['permit_qr'] ) : ?>
			<img class="idta-card__back-qr" src="<?php echo esc_attr( $context['permit_qr'] ); ?>" alt="">
		<?php endif; ?>
	<?php endif; ?>
</div>
