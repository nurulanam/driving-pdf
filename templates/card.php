<?php
/**
 * Permit card template: 85.6 x 53.98 mm, front and back.
 *
 * Both faces are built here rather than printed over a pre-composed background
 * image, so every string is live text. The layout is a stack of fixed-height
 * bands, and the columns inside each band are floats — mPDF has no absolute
 * positioning, and it ignores a table nested inside a `<td>`, so floats are the
 * only structure that survives.
 *
 * Every label on the card is fixed text, deliberately: these are the printed
 * card's own words in eight languages, not something a site configures. Only the
 * holder's own details come from the order.
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

$card_values = (array) ( $context['card_values'] ?? array() );
$card_icons  = (array) ( $context['category_icons'] ?? array() );

/**
 * The guilloche panel behind the front's fields.
 *
 * @var string $card_body_style
 */
$card_body_style = '' !== $context['front_bg']
	? sprintf( ' style="background-image: url(\'%s\');"', esc_attr( (string) $context['front_bg'] ) )
	: '';

/**
 * One numbered field, as the face prints it: the number, then the value.
 *
 * @param string $num   Field number.
 * @param string $value Field value.
 */
$card_field = static function ( string $num, string $value ): void {
	printf(
		'<tr><td class="cf-num">%s</td><td class="cf-val">%s</td></tr>',
		esc_html( $num . '.' ),
		esc_html( $value )
	);
};

/**
 * The five vehicle categories the back's table lists.
 *
 * @var array<int,array{letter:string,label:string}> $card_categories
 */
$card_categories = array(
	array(
		'letter' => 'A',
		'label'  => __( 'Motorcycles', 'idta-pdf' ),
	),
	array(
		'letter' => 'B',
		'label'  => __( 'Passenger cars', 'idta-pdf' ),
	),
	array(
		'letter' => 'C',
		'label'  => __( 'Goods vehicles', 'idta-pdf' ),
	),
	array(
		'letter' => 'D',
		'label'  => __( 'Buses Autobus', 'idta-pdf' ),
	),
	array(
		'letter' => 'E',
		'label'  => __( 'Cars with trailer < 750 kg', 'idta-pdf' ),
	),
);
?>

<?php // ---------------------------------------------------------- FRONT ---- ?>
<div class="idta-card idta-card--front">

	<div class="cf-head">
		<?php
		/*
		 * The titles come first and the seal is pulled back up alongside them.
		 * Neither is floated: beside a float mPDF shrinks a sibling block's line
		 * boxes, and it will not place two left floats side by side either, so
		 * the title wrapped and the block drifted to the left edge. With no
		 * float in this band the titles keep their declared width.
		 */
		?>
		<div class="cf-head__titles">
			<div class="cf-t1"><?php esc_html_e( 'INTERNATIONAL DRIVING PERMIT', 'idta-pdf' ); ?></div>
			<div class="cf-t2"><?php esc_html_e( 'Permis de conduire International   Permiso Internacional de Conducir', 'idta-pdf' ); ?></div>
			<div class="cf-t3"><?php esc_html_e( 'Международное Водительское удостоверение', 'idta-pdf' ); ?></div>
			<div class="cf-t4"><?php esc_html_e( 'Carta de Condução Internacional   Internationellt Körkort', 'idta-pdf' ); ?></div>
			<div class="cf-t5"><?php esc_html_e( 'Internationaler Führerschein   Patente di Guida Internazionale', 'idta-pdf' ); ?></div>
			<div class="cf-t6"><?php esc_html_e( 'רישיון נהיגה בינלאומי رخصة قيادة دولية', 'idta-pdf' ); ?></div>
		</div>

		<?php if ( '' !== $context['head_logo'] ) : ?>
			<div class="cf-head__sealbox">
				<img class="cf-head__seal" src="<?php echo esc_attr( (string) $context['head_logo'] ); ?>" alt="">
			</div>
		<?php endif; ?>
	</div>

	<div class="cf-band"><?php esc_html_e( "TRANSLATION OF FOREIGN DRIVER'S LICENSE", 'idta-pdf' ); ?></div>

	<div class="cf-body"<?php echo $card_body_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_attr(). ?>>

		<div class="cf-photo">
			<?php if ( '' !== $context['photo'] ) : ?>
				<img class="cf-photo__img" src="<?php echo esc_attr( (string) $context['photo'] ); ?>" alt="">
			<?php endif; ?>

			<?php
			/*
			 * The signature and the stamp sit side by side in one row that
			 * overlaps the portrait's lower edge, as the printed card has them.
			 *
			 * They share a row so the row's own negative margin can stay small.
			 * mPDF decides where to break a page from a block's position before
			 * its negative margin is applied, so a large pull-up — the stamp
			 * reaching back over a full-height signature block — put the stamp on
			 * a page of its own.
			 */
			?>
			<div class="cf-photo__row">
				<div class="cf-photo__sign">
					<?php if ( '' !== $context['signature'] ) : ?>
						<img src="<?php echo esc_attr( (string) $context['signature'] ); ?>" alt="">
					<?php endif; ?>
				</div>

				<div class="cf-photo__stamp">
					<?php if ( '' !== $context['stamp'] ) : ?>
						<img src="<?php echo esc_attr( (string) $context['stamp'] ); ?>" alt="">
					<?php endif; ?>
				</div>

				<div class="idta-clear"></div>
			</div>
		</div>

		<?php
		/*
		 * One flat table, four columns: number, value, number, value. Not an
		 * outer table of two column cells each holding its own table — mPDF
		 * ignores a table nested inside a `<td>`, which cost these rows their
		 * declared height and squeezed the block to 3.4mm a row instead of 4.23.
		 */
		?>
		<div class="cf-fields">
			<table class="cf-grid">
				<?php foreach ( array( array( '1', '7' ), array( '2', '8' ), array( '3', '9' ), array( '4', '' ), array( '5', '' ) ) as $card_pair ) : ?>
					<tr>
						<?php
						foreach ( array( 'a', 'b' ) as $card_side ) {
							$card_num = 'a' === $card_side ? $card_pair[0] : $card_pair[1];

							printf(
								'<td class="cf-n cf-n--%1$s">%2$s</td><td class="cf-v cf-v--%1$s">%3$s</td>',
								esc_attr( $card_side ),
								'' === $card_num ? '' : esc_html( $card_num . '.' ),
								'' === $card_num ? '' : esc_html( (string) ( $card_values[ $card_num ] ?? '' ) )
							);
						}
						?>
					</tr>
				<?php endforeach; ?>
			</table>

			<div class="cf-class">
				<span class="cf-class__label"><?php esc_html_e( 'CLASS.', 'idta-pdf' ); ?></span>
				<span class="cf-class__value"><?php echo esc_html( (string) $context['card_class'] ); ?></span>
			</div>
		</div>

		<div class="cf-side">
			<?php
			/*
			 * A block each: left on one line they are 22.5mm of images in a
			 * 13.4mm column, and the QR wrapped past the band's height and was
			 * clipped away entirely.
			 */
			?>
			<div class="cf-side__row">
				<?php if ( '' !== $context['photo_gray'] ) : ?>
					<img class="cf-side__photo" src="<?php echo esc_attr( (string) $context['photo_gray'] ); ?>" alt="">
				<?php endif; ?>
			</div>

			<div class="cf-side__row">
				<?php if ( '' !== $context['details_qr'] ) : ?>
					<img class="cf-side__qr" src="<?php echo esc_attr( (string) $context['details_qr'] ); ?>" alt="">
				<?php endif; ?>
			</div>
		</div>

		<div class="idta-clear"></div>
	</div>
</div>

<?php // ----------------------------------------------------------- BACK ---- ?>
<div class="idta-card idta-card--back">

	<div class="cb-title"><?php esc_html_e( 'INTERNATIONAL DRIVING PERMIT TRANSLATION CARD', 'idta-pdf' ); ?></div>
	<div class="cb-sub"><?php esc_html_e( "This document is a translation of the holder's driver's licence and confers no legal privileges", 'idta-pdf' ); ?></div>

	<div class="cb-main">

		<?php
		/*
		 * The rounded outline is drawn by the wrapper div, not the table. mPDF
		 * ignores border-radius on a `<td>` — verified by rendering — but honours
		 * it on a div, so the border and its corners live on the wrapper and the
		 * cells carry only the lines between them.
		 *
		 * That also settles the mismatch between the header and the rows: a
		 * background-filled header cell spans the full table width while a
		 * bordered cell sits inside its own border, so the two edges never lined
		 * up. Now neither draws an outer edge.
		 */
		?>
		<div class="cb-table">
			<div class="cb-head">
				<table class="cb-head__grid">
					<tr>
						<td class="cb-head__label"><?php esc_html_e( 'CATEGORIES OF VEHICLES FOR WHICH THE PERMIT IS VALID', 'idta-pdf' ); ?></td>
						<td class="cb-head__code"><?php esc_html_e( 'CATEGORY CODE', 'idta-pdf' ); ?></td>
					</tr>
				</table>
			</div>

			<table class="cb-grid">
				<?php foreach ( $card_categories as $card_index => $card_category ) : ?>
					<?php
					$card_icon = (string) ( $card_icons[ $card_category['letter'] ] ?? '' );
					// The last row draws no line beneath it: the wrapper's own
					// rounded border closes the table off.
					$card_last = ( count( $card_categories ) - 1 ) === $card_index ? ' cb-grid__cell--last' : '';
					?>
					<tr>
						<?php // The class goes on the image itself: mPDF ignores a descendant selector inside a `<td>`. ?>
						<td class="cb-grid__cell cb-grid__icon<?php echo esc_attr( $card_last ); ?>"><?php if ( '' !== $card_icon ) : ?><img class="cb-grid__icon-img" src="<?php echo esc_attr( $card_icon ); ?>" alt=""><?php endif; ?></td>
						<td class="cb-grid__cell cb-grid__label<?php echo esc_attr( $card_last ); ?>"><?php echo esc_html( $card_category['label'] ); ?></td>
						<td class="cb-grid__cell cb-grid__code<?php echo esc_attr( $card_last ); ?>"><?php echo esc_html( $card_category['letter'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<div class="cb-side">
			<?php if ( '' !== $context['back_logo'] ) : ?>
				<img class="cb-side__logo" src="<?php echo esc_attr( (string) $context['back_logo'] ); ?>" alt="">
			<?php endif; ?>

			<div class="cb-side__heading"><?php esc_html_e( 'NOTES', 'idta-pdf' ); ?></div>

			<?php
			/*
			 * The bullet is a literal character, not a `:before` rule — mPDF
			 * supports neither generated content nor a reliable list marker
			 * inside a float.
			 */
			?>
			<div class="cb-side__note">&#8226;&nbsp; <?php esc_html_e( 'This International Driving Permit is valid for the period indicated on the front, from the date of issue.', 'idta-pdf' ); ?></div>
			<div class="cb-side__note">&#8226;&nbsp; <?php esc_html_e( 'It is not valid for driving in the country of issue.', 'idta-pdf' ); ?></div>
		</div>

		<div class="idta-clear"></div>
	</div>

	<div class="cb-legend cb-legend--first"><?php esc_html_e( '1. Surname   2. Other Names   3. Place of Birth   4. Date of Birth   5. Country of Residence', 'idta-pdf' ); ?></div>
	<div class="cb-legend"><?php esc_html_e( '6. Photo and Signature   7. Date of Issue   8. Expiry Date   9. Licence Number', 'idta-pdf' ); ?></div>
</div>
