<?php
/**
 * Shared layout for the booklet's translation pages (booklet pages 4-22).
 *
 * One layout, nineteen languages. Strings come from IDTA\PDF\Language_Pages;
 * this file only arranges them. A right-to-left language mirrors the whole
 * page — flag, letter column, stamp and divider all swap sides.
 *
 * Override by copying to `idta-pdf/pages/language-page.php` in your theme.
 *
 * mPDF layout constraints this template works within, each verified by
 * rendering. Keep them in mind before restructuring:
 *
 *   - A `<td>` is a poor container: font-family, text-align on a child `<p>`,
 *     `border-radius`, vertical margins and nested tables are all ignored
 *     inside one. Style the `<td>` itself, and use `<div>` for anything richer.
 *   - Percentage widths collapse for tables nested in a sized block, so every
 *     table here is given an explicit millimetre width.
 *
 * @var \IDTA\PDF\Booklet_Document $document  Document instance.
 * @var \IDTA\PDF\Order_Data       $data      Order data.
 * @var array<string,mixed>        $context   Template context.
 * @var array<string,mixed>        $page_lang Language definition.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! isset( $page_lang ) || ! is_array( $page_lang ) ) {
	return;
}

$lp_rtl  = ! empty( $page_lang['rtl'] );
$lp_font = (string) $page_lang['font'];

// Every block is font-scoped inline: mPDF will not inherit a font-family into
// table cells, so it has to be restated rather than set once on the wrapper.
$lp_style = sprintf( ' style="font-family: %s;"', esc_attr( $lp_font ) );

$lp_class = 'lp' . ( $lp_rtl ? ' lp--rtl' : '' );

/**
 * Emit a labelled fill-in rule as its own table.
 *
 * @param string $label Label text, may be empty for a blank continuation line.
 * @param string $style Inline font style.
 * @param bool   $rtl   Whether the page is right-to-left.
 * @param string $extra Extra class for the label cell.
 * @param string $line  Extra class for the line cell.
 */
$lp_rule = static function ( string $label, string $style, bool $rtl, string $extra = '', string $line = '' ): void {
	$label_class = trim( 'lp-rule__label ' . $extra );
	$line_class  = trim( 'lp-rule__line ' . $line );

	echo '<table class="lp-rule">';
	echo '<tr>';

	// In RTL the label sits on the right, so the cells are emitted in reverse.
	if ( $rtl ) {
		printf( '<td class="%s"%s></td>', esc_attr( $line_class ), $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr().
		printf( '<td class="%s"%s>%s</td>', esc_attr( $label_class ), $style, esc_html( $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr().
	} else {
		printf( '<td class="%s"%s>%s</td>', esc_attr( $label_class ), $style, esc_html( $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr().
		printf( '<td class="%s"%s></td>', esc_attr( $line_class ), $style ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr().
	}

	echo '</tr>';
	echo '</table>';
};

/*
 * A three-cell strip is only an honest rendering of a vertical tricolour. For
 * every other flag the language is named instead, rather than drawing something
 * that misrepresents a national flag. Supply real artwork through the
 * idta_pdf_language_pages filter if exact parity is needed.
 */
$lp_flag = array_values( array_filter( (array) $page_lang['flag'], 'is_string' ) );
?>
<div class="<?php echo esc_attr( $lp_class ); ?>"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>>

	<?php if ( 3 === count( $lp_flag ) ) : ?>
		<table class="lp-flag">
			<tr>
				<?php foreach ( $lp_flag as $lp_stripe ) : ?>
					<td class="lp-flag__stripe" style="background-color: <?php echo esc_attr( $lp_stripe ); ?>;"></td>
				<?php endforeach; ?>
			</tr>
		</table>
	<?php else : ?>
		<div class="lp-language"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $page_lang['label'] ); ?></div>
	<?php endif; ?>

	<?php
	/**
	 * One row per numbered holder field, so the two lead sentences sit on the
	 * same baselines as fields 1 and 5, as they do in the scans. Vertical
	 * margins on a div inside a `<td>` are ignored by mPDF, so the row
	 * structure — not spacing — does the aligning.
	 */
	$lp_holder = (array) $page_lang['holder'];
	$lp_leads  = array(
		0 => (string) $page_lang['lead_driver'],
		4 => (string) $page_lang['lead_valid'],
	);
	?>
	<table class="lp-head">
		<?php foreach ( $lp_holder as $lp_index => $lp_label ) : ?>
			<tr>
				<?php
				$lp_lead_cell  = sprintf(
					'<td class="lp-head__lead"%s>%s</td>',
					$lp_style,
					esc_html( $lp_leads[ $lp_index ] ?? '' )
				);
				$lp_label_cell = sprintf(
					'<td class="lp-head__label"%s>%s</td>',
					$lp_style,
					esc_html( (string) $lp_label )
				);

				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- cells built with esc_html()/esc_attr() above.
				if ( $lp_rtl ) {
					echo $lp_label_cell . $lp_lead_cell;
				} else {
					echo $lp_lead_cell . $lp_label_cell;
				}
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</tr>
		<?php endforeach; ?>
	</table>

	<table class="lp-categories">
		<?php foreach ( (array) $page_lang['categories'] as $lp_letter => $lp_text ) : ?>
			<tr>
				<?php
				$lp_text_cell   = sprintf(
					'<td class="lp-categories__text"%s>%s</td>',
					$lp_style,
					esc_html( (string) $lp_text )
				);
				$lp_letter_cell = sprintf(
					'<td class="lp-categories__letter"%s>%s</td>',
					$lp_style,
					esc_html( (string) $lp_letter )
				);

				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- cells built with esc_html()/esc_attr() above.
				if ( $lp_rtl ) {
					echo $lp_letter_cell . $lp_text_cell;
				} else {
					echo $lp_text_cell . $lp_letter_cell;
				}
				// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</tr>
		<?php endforeach; ?>
	</table>

	<?php
	$lp_notes = array_values( (array) $page_lang['notes'] );

	// Right-to-left pages set the notes as one full-width block, as printed;
	// left-to-right pages use the scans' two columns.
	if ( $lp_rtl ) :
		?>
		<div class="lp-notes-block"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>>
			<?php foreach ( $lp_notes as $lp_note ) : ?>
				<div class="lp-notes-block__line"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo wp_kses( (string) $lp_note, array() ); ?></div>
			<?php endforeach; ?>
		</div>
		<?php
	else :
		?>
		<table class="lp-notes">
			<tr>
				<td class="lp-notes__col"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo wp_kses( (string) ( $lp_notes[0] ?? '' ), array() ); ?></td>
				<td class="lp-notes__col lp-notes__col--last"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo wp_kses( (string) ( $lp_notes[1] ?? '' ), array() ); ?></td>
			</tr>
		</table>
		<?php
	endif;

	$lp_exclusion = (array) $page_lang['exclusion'];
	?>

	<div class="lp-exclusion-rule"></div>

	<div class="lp-exclusion"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>>

		<div class="lp-exclusion__title"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $lp_exclusion['title'] ); ?></div>

		<div class="lp-exclusion__intro"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $lp_exclusion['intro'] ); ?></div>

		<?php
		$lp_rule( (string) $lp_exclusion['country'], $lp_style, $lp_rtl );
		$lp_rule( (string) $lp_exclusion['reason'], $lp_style, $lp_rtl, 'lp-rule__label--wide', 'lp-rule__line--short' );

		// Two further blank rules, matching the scans' four-line group.
		for ( $lp_blank = 0; $lp_blank < 2; $lp_blank++ ) {
			$lp_rule( '', $lp_style, $lp_rtl, 'lp-rule__label--wide', 'lp-rule__line--short' );
		}
		?>

		<div class="lp-stamp">
			<div class="lp-stamp__seal">
				<div class="lp-seal"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo wp_kses( (string) $lp_exclusion['seal'], array( 'br' => array() ) ); ?></div>
			</div>
			<div class="lp-stamp__fields">
				<?php
				foreach ( array( 'place', 'date', 'signature' ) as $lp_field ) {
					$lp_rule(
						(string) $lp_exclusion[ $lp_field ],
						$lp_style,
						$lp_rtl,
						'lp-rule__label--stamp',
						'lp-rule__line--stamp'
					);
				}
				?>
			</div>
			<div class="idta-clear"></div>
		</div>

		<div class="lp-footnote"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo wp_kses( (string) $lp_exclusion['footnote'], array() ); ?></div>
	</div>

	<div class="lp-folio"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $page_lang['folio'] ); ?></div>
</div>
