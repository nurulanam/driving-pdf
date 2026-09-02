<?php
/**
 * Print copy: A4, four pages, an overlay for pre-printed booklet stock.
 *
 * Prints only what varies from one permit to the next, positioned against the
 * stock itself — assets/img/page-01.jpg and page-23.jpg. Nothing else is
 * emitted at all: where an earlier version laid out the whole booklet page and
 * hid all but a few items, this one carries only the items, each reached by a
 * spacer of measured height. mPDF has no absolute positioning, so the spacers
 * are the positioning.
 *
 * Four pages, alternating blank and printed, so a duplex pass lands each
 * overlay on the right leaf:
 *
 *   1  blank
 *   2  the cover's expiry date
 *   3  blank
 *   4  the holder page's details
 *
 * Override by copying to `idta-pdf/print-copy.php` in your theme.
 *
 * @var \IDTA\PDF\Print_Copy_Document $document Document instance.
 * @var \IDTA\PDF\Order_Data          $data     Order data.
 * @var array<string,mixed>           $context  Template context.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * The six numbered values, in the order the stock numbers them.
 *
 * @var string[] $print_values
 */
$print_values = array(
	(string) $context['last_name'],
	(string) $context['given_names'],
	(string) $context['birth_country'],
	(string) $context['date_of_birth'],
	(string) $context['residence'],
	(string) $context['license_number'],
);
?>

<?php // ------------------------------------------------ 1: blank leaf ---- ?>
<div class="pc-page pc-blank"></div>

<?php // ------------------------------- 2: the cover's expiry date only ---- ?>
<div class="pc-page pc-page--break">
	<div class="pc-date">
		<div class="pc-date__value"><?php echo esc_html( (string) $context['expiry_date'] ); ?></div>
	</div>
</div>

<?php // ------------------------------------------------ 3: blank leaf ---- ?>
<div class="pc-page pc-page--break pc-blank"></div>

<?php // -------------------------- 4: the holder page's details only ---- ?>
<div class="pc-page pc-page--break">

	<div class="pc-values">
		<?php foreach ( $print_values as $print_value ) : ?>
			<?php // Emitted even when empty: the row's height is what places the next one. ?>
			<div class="pc-value"><?php echo esc_html( $print_value ); ?></div>
		<?php endforeach; ?>
	</div>

	<div class="pc-row">

		<div class="pc-seals">
			<?php foreach ( $context['all_categories'] as $print_index => $print_category ) : ?>
				<?php
				/*
				 * Only a held category is sealed. The stock prints "Seal or
				 * stamp of authority" in every box, so an unheld one is left as
				 * it is rather than covered.
				 */
				$print_granted = in_array( $print_category, (array) $context['categories'], true );
				$print_first   = 0 === $print_index ? ' pc-seal--first' : '';
				?>
				<div class="pc-seal<?php echo esc_attr( $print_first ); ?>">
					<?php if ( $print_granted && '' !== $context['seal'] ) : ?>
						<img class="pc-seal__img" src="<?php echo esc_attr( (string) $context['seal'] ); ?>" alt="">
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="pc-ident">

			<div class="pc-portrait">
				<?php if ( '' !== $context['photo'] ) : ?>
					<img class="pc-portrait__img" src="<?php echo esc_attr( (string) $context['photo'] ); ?>" alt="">
				<?php endif; ?>
			</div>

			<div class="pc-stamp">
				<?php if ( '' !== $context['stamp'] ) : ?>
					<img class="pc-stamp__img" src="<?php echo esc_attr( (string) $context['stamp'] ); ?>" alt="">
				<?php endif; ?>
			</div>

			<div class="pc-sign">
				<?php if ( '' !== $context['signature'] ) : ?>
					<img class="pc-sign__img" src="<?php echo esc_attr( (string) $context['signature'] ); ?>" alt="">
				<?php endif; ?>
			</div>
		</div>

		<div class="idta-clear"></div>
	</div>
</div>
