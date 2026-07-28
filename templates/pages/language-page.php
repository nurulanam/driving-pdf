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
 *   - Tables need an explicit millimetre width, except where a cell has to
 *     shrink to its own text: see the fill-in rules below.
 *   - `position: absolute` is ignored, so the folio cannot simply be pinned to
 *     the bottom of the page. It is placed by giving the content above it a
 *     fixed height instead.
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

/*
 * Every block is font-scoped inline: mPDF will not inherit a font-family into
 * table cells, so it has to be restated rather than set once on the wrapper.
 *
 * The weight travels with it. Several bundled faces — the CJK and Ethiopic ones
 * — exist in a single hairline weight that prints far lighter than the scans, so
 * those languages ask for bold and mPDF strokes the outline to fake it.
 */
$lp_css = sprintf(
	'font-family: %s; font-weight: %s;',
	esc_attr( $lp_font ),
	esc_attr( (string) $page_lang['weight'] )
);

$lp_style = ' style="' . $lp_css . '"';

// Font size the exclusion block's rule labels are set in; kept in step with
// .lp-rule__label so their columns can be measured below.
$lp_label_pt = 9.5;

// The category letters are the one thing that is identical on all nineteen
// pages, so they are set in the same face throughout rather than picking up
// whichever script font the page uses.
$lp_letter_style = ' style="font-family: dejavuserif; font-weight: normal;"';

$lp_class = 'lp' . ( $lp_rtl ? ' lp--rtl' : '' );

/**
 * Emit a labelled fill-in rule.
 *
 * The rule starts straight after the label rather than at a fixed column, which
 * is how the booklet prints it — a shared 24mm label column left a short word
 * like "Date" adrift from its line. Both columns are given a millimetre width,
 * the label's measured from its own text, because the alternative mPDF offers is
 * worse: a shrink-to-content label needs the rule cell at 100%, mPDF reads the
 * pair as an overflow, and answers it by shrinking the table's type — so
 * "Signature" printed a size smaller than "Lieu" beside it.
 *
 * @param string $label  Label text, may be empty for a blank continuation line.
 * @param string $css    Inline font declarations.
 * @param bool   $rtl    Whether the page is right-to-left.
 * @param float  $total  Overall rule width in millimetres.
 * @param float  $pt     Font size the label is set in.
 * @param string $indent Extra class, for indenting continuation lines.
 */
$lp_rule = static function ( string $label, string $css, bool $rtl, float $total, float $pt, string $indent = '' ): void {
	$label_mm = \IDTA\PDF\Language_Pages::label_width_mm( $label, $pt );

	// Never let a long label crowd the rule out; 20mm is still a usable line.
	$label_mm = min( $label_mm, $total - 20.0 );
	$label_mm = max( $label_mm, 0.0 );

	printf( '<div class="%s">', esc_attr( trim( 'lp-rule-wrap ' . $indent ) ) );
	printf( '<table class="lp-rule" style="width: %smm;">', esc_attr( (string) $total ) );
	echo '<tr>';

	$cells = array(
		sprintf(
			'<td class="lp-rule__line" style="%s width: %smm;"></td>',
			$css,
			esc_attr( (string) round( $total - $label_mm, 1 ) )
		),
	);

	if ( $label_mm > 0.0 ) {
		$label_cell = sprintf(
			'<td class="lp-rule__label" style="%s width: %smm;">%s</td>',
			$css,
			esc_attr( (string) $label_mm ),
			esc_html( $label )
		);

		// In RTL the label sits to the right of its rule.
		if ( $rtl ) {
			$cells[] = $label_cell;
		} else {
			array_unshift( $cells, $label_cell );
		}
	}

	echo implode( '', $cells ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cells built with esc_html()/esc_attr() above.

	echo '</tr>';
	echo '</table>';
	echo '</div>';
};

/*
 * Real artwork wins. Failing that, a three-cell strip is only an honest
 * rendering of a vertical tricolour, so for every other flag the language is
 * named instead rather than drawing something that misrepresents a national
 * flag. Set `flag_image` (in the language data or through the
 * idta_pdf_language_pages filter) to print the real thing.
 */
$lp_flag_image = (string) $page_lang['flag_image'];
$lp_flag       = array_values( array_filter( (array) $page_lang['flag'], 'is_string' ) );
?>
<div class="<?php echo esc_attr( $lp_class ); ?>"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>>

<div class="lp-body">

	<?php if ( '' !== $lp_flag_image ) : ?>
		<div class="lp-flag-box">
			<img class="lp-flag-box__image" src="<?php echo esc_attr( $lp_flag_image ); ?>" alt="">
		</div>
	<?php elseif ( 3 === count( $lp_flag ) ) : ?>
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
					$lp_letter_style,
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

		<?php // The box is closed by a rule under the signature line, as printed; the footnote sits outside it. ?>
		<div class="lp-exclusion__box">

			<div class="lp-exclusion__title"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $lp_exclusion['title'] ); ?></div>

			<div class="lp-exclusion__intro"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $lp_exclusion['intro'] ); ?></div>

			<?php
			$lp_rule( (string) $lp_exclusion['country'], $lp_css, $lp_rtl, 106.0, $lp_label_pt );
			$lp_rule( (string) $lp_exclusion['reason'], $lp_css, $lp_rtl, 106.0, $lp_label_pt );

			// Two further blank rules, matching the scans' four-line group. They
			// carry no label, so the wrapper indents them to sit under the
			// reason line rather than starting at the column edge.
			for ( $lp_blank = 0; $lp_blank < 2; $lp_blank++ ) {
				$lp_rule( '', $lp_css, $lp_rtl, 80.0, $lp_label_pt, 'lp-rule-wrap--continue' );
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
							$lp_css,
							$lp_rtl,
							72.0,
							$lp_label_pt
						);
					}
					?>
				</div>
				<div class="idta-clear"></div>
			</div>
		</div>

		<div class="lp-footnote"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo wp_kses( (string) $lp_exclusion['footnote'], array() ); ?></div>
	</div>

	<div class="idta-clear"></div>
</div>

	<?php
	/*
	 * The folio prints at the bottom-left of the page (bottom-right when the
	 * page is mirrored), inside the page margin. mPDF ignores `position:
	 * absolute`, so it cannot be pinned there; instead .lp-body above is given a
	 * fixed height, which lands the folio at the same place on all nineteen
	 * pages regardless of how much text the language needs.
	 */
	?>
	<div class="lp-folio"<?php echo $lp_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>><?php echo esc_html( (string) $page_lang['folio'] ); ?></div>
</div>
