<?php
/**
 * Booklet page 24 — back cover.
 *
 * A text and artwork rebuild of `assets/img/pages/final-booklet-IDPA_page-0024.jpg`,
 * the last page that was still a full-page scan.
 *
 * Reproduces the scan's dotted world map, then the lockup, the contact details,
 * and the row of the two QR codes either side of the UN emblem.
 *
 * Override by copying to `idta-pdf/pages/page-24.php` in your theme.
 *
 * @var \IDTA\PDF\Booklet_Document $document Document instance.
 * @var \IDTA\PDF\Order_Data       $data     Order data.
 * @var array<string,mixed>        $context  Template context.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/*
 * The three marks are a table rather than three floats: mPDF gives floated
 * siblings no shared baseline, so the emblem would not sit level between the two
 * codes.
 */
$back_marks = array(
	array(
		'class' => 'back__qr',
		'src'   => (string) $context['qr_left'],
	),
	array(
		'class' => 'back__emblem',
		'src'   => (string) $context['un_emblem'],
	),
	array(
		'class' => 'back__qr',
		'src'   => (string) $context['qr_right'],
	),
);
?>
<div class="back">

	<?php if ( '' !== $context['back_map'] ) : ?>
		<div class="back__map">
			<img src="<?php echo esc_attr( (string) $context['back_map'] ); ?>" alt="">
		</div>
	<?php endif; ?>

	<?php if ( '' !== $context['wordmark'] ) : ?>
		<div class="back__wordmark">
			<img src="<?php echo esc_attr( (string) $context['wordmark'] ); ?>" alt="">
		</div>
	<?php endif; ?>

	<p class="back__contact">
		<?php echo esc_html( (string) $context['brand_site'] ); ?><br>
		<?php echo esc_html( (string) $context['brand_email'] ); ?>
	</p>

	<table class="back__marks">
		<tr>
			<?php foreach ( $back_marks as $back_mark ) : ?>
				<td class="back__mark">
					<?php if ( '' !== $back_mark['src'] ) : ?>
						<img
							class="<?php echo esc_attr( $back_mark['class'] ); ?>"
							src="<?php echo esc_attr( $back_mark['src'] ); ?>"
							alt=""
						>
					<?php endif; ?>
				</td>
			<?php endforeach; ?>
		</tr>
	</table>
</div>
