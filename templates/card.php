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

/**
 * Numbered fields, matching the layout of the printed card.
 *
 * @var array<int,array{num:string,value:string}> $left_fields
 */
$left_fields = array(
	array(
		'num'   => '1.',
		'value' => $context['last_name'],
	),
	array(
		'num'   => '2.',
		'value' => $context['given_names'],
	),
	array(
		'num'   => '3.',
		'value' => $context['birth_country'],
	),
	array(
		'num'   => '4.',
		'value' => $context['date_of_birth'],
	),
	array(
		'num'   => '5.',
		'value' => $context['residence'],
	),
);

$right_fields = array(
	array(
		'num'   => '7.',
		'value' => $context['issue_date'],
	),
	array(
		'num'   => '8.',
		'value' => $context['expiry_date'],
	),
	array(
		'num'   => '9.',
		'value' => $context['card_number'],
	),
);
?>
<div class="idta-card idta-card--front"<?php echo $front_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with esc_attr(). ?>>

	<div class="idta-card__disclaimer">
		<?php esc_html_e( 'This document is a translation of the holder\'s driver\'s licence and confers no legal privileges.', 'idta-pdf' ); ?>
	</div>

	<div class="idta-card__body">

		<div class="idta-card__photo">
			<?php if ( '' !== $context['photo'] ) : ?>
				<img class="idta-card__portrait" src="<?php echo esc_attr( $context['photo'] ); ?>" alt="">
			<?php endif; ?>

			<?php if ( '' !== $context['stamp'] ) : ?>
				<img class="idta-card__stamp" src="<?php echo esc_attr( $context['stamp'] ); ?>" alt="">
			<?php endif; ?>

			<div class="idta-card__signature">
				<?php if ( '' !== $context['signature'] ) : ?>
					<img src="<?php echo esc_attr( $context['signature'] ); ?>" alt="">
				<?php else : ?>
					<span class="idta-card__signature-missing">&nbsp;</span>
				<?php endif; ?>
			</div>
		</div>

		<div class="idta-card__details">

			<table class="idta-card__fields">
				<?php foreach ( $left_fields as $index => $field ) : ?>
					<tr>
						<td class="idta-card__field">
							<span class="idta-card__num"><?php echo esc_html( $field['num'] ); ?></span>
							<span class="idta-card__value"><?php echo esc_html( $field['value'] ); ?></span>
						</td>
						<td class="idta-card__field idta-card__field--right">
							<?php if ( isset( $right_fields[ $index ] ) ) : ?>
								<span class="idta-card__num"><?php echo esc_html( $right_fields[ $index ]['num'] ); ?></span>
								<span class="idta-card__value idta-card__value--tight"><?php echo esc_html( $right_fields[ $index ]['value'] ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>

			<div class="idta-card__class">
				<span class="idta-card__num"><?php esc_html_e( 'CLASS.', 'idta-pdf' ); ?></span>
				<span
					class="idta-card__class-values"
					style="font-size: <?php echo esc_attr( (string) $context['category_font_pt'] ); ?>pt;"
				>
					<?php echo esc_html( implode( ', ', $context['categories'] ) ); ?>
				</span>
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

		<div class="idta-clear"></div>
	</div>

	<div class="idta-card__footer">
		<?php esc_html_e( 'To be presented with the original driver\'s licence', 'idta-pdf' ); ?>
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
