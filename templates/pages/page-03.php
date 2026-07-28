<?php
/**
 * Booklet page 3 — language index.
 *
 * A text rebuild of `assets/img/pages/final-booklet-IDPA_page-0003.jpg`.
 *
 * The rows come from $context['languages'], the same list the translation pages
 * are built from, so the index cannot drift out of step with them: add a language
 * there and it appears here, in page order, with its own flag and printed folio.
 *
 * Override by copying to `idta-pdf/pages/page-03.php` in your theme.
 *
 * @var \IDTA\PDF\Booklet_Document $document Document instance.
 * @var \IDTA\PDF\Order_Data       $data     Order data.
 * @var array<string,mixed>        $context  Template context.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$index_pages = (array) ( $context['languages'] ?? array() );

ksort( $index_pages );
?>
<div class="index">

	<h2 class="index__title">INDEX</h2>

	<table class="index__list">
		<?php foreach ( $index_pages as $index_entry ) : ?>
			<?php
			$index_label = (string) $index_entry['label'];
			$index_folio = (string) $index_entry['folio'];

			if ( '' === $index_label ) {
				continue;
			}

			// Each name is set in a font that covers its own script.
			$index_font = sprintf( ' style="font-family: %s;"', esc_attr( (string) $index_entry['font'] ) );
			?>
			<tr>
				<?php
				/*
				 * Emitted without surrounding whitespace: a newline either side of
				 * the image becomes a second line box in the cell, which deepens
				 * the row and drops the dotted leader below the name beside it.
				 */
				$index_flag = (string) $index_entry['flag_image'];
				?>
				<td class="index__flag"><?php if ( '' !== $index_flag ) : ?><img class="index__flag-image" src="<?php echo esc_attr( $index_flag ); ?>" alt=""><?php endif; ?></td>
				<td class="index__language"<?php echo $index_font; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( $index_label ); ?></td>
				<td class="index__leader"></td>
				<td class="index__folio"><?php echo esc_html( str_pad( $index_folio, 2, '0', STR_PAD_LEFT ) ); ?></td>
			</tr>
		<?php endforeach; ?>
	</table>

	<?php if ( '' !== $context['logo'] ) : ?>
		<div class="index__logo">
			<img src="<?php echo esc_attr( $context['logo'] ); ?>" alt="">
		</div>
	<?php endif; ?>
</div>
