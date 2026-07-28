<?php
/**
 * Permit booklet template: A4 portrait, 210 x 297 mm.
 *
 * Override by copying to `idta-pdf/booklet.php` in your theme.
 *
 * Available variables:
 *
 * @var \IDTA\PDF\Booklet_Document $document Document instance.
 * @var \IDTA\PDF\Order_Data       $data     Order data.
 * @var array<string,mixed>        $context  Template context.
 *
 * Embedded images are data URIs, so they are escaped with esc_attr() rather
 * than esc_url() — the latter drops the data: protocol.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Numbered holder fields, as printed on the permit page.
 *
 * @var array<int,array{num:string,label:string,value:string}> $holder_fields
 */
$holder_fields = array(
	array(
		'num'   => '1.',
		'label' => __( 'Surname', 'idta-pdf' ),
		'value' => $context['last_name'],
	),
	array(
		'num'   => '2.',
		'label' => __( 'Given names', 'idta-pdf' ),
		'value' => $context['given_names'],
	),
	array(
		'num'   => '3.',
		'label' => __( 'Place and country of birth', 'idta-pdf' ),
		'value' => $context['birth_country'],
	),
	array(
		'num'   => '4.',
		'label' => __( 'Date of birth', 'idta-pdf' ),
		'value' => $context['date_of_birth'],
	),
	array(
		'num'   => '5.',
		'label' => __( 'Permanent place of residence', 'idta-pdf' ),
		'value' => $context['residence'],
	),
	array(
		'num'   => '6.',
		'label' => __( 'Domestic licence number', 'idta-pdf' ),
		'value' => $context['license_number'],
	),
);

$exclusion_numerals = array( 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII' );

/**
 * Class attribute for a page block, forcing a break on every page but the
 * first. mPDF ignores adjacent-sibling selectors, so the break has to be
 * carried by a plain class.
 *
 * @var callable(string):string $page_class
 */
$idta_page_number = 0;

$page_class = static function ( string $extra = '' ) use ( &$idta_page_number ): string {
	$classes = array( 'idta-page' );

	if ( $idta_page_number > 0 ) {
		$classes[] = 'idta-page--break';
	}

	if ( '' !== $extra ) {
		$classes[] = $extra;
	}

	++$idta_page_number;

	return implode( ' ', $classes );
};
?>

<div class="<?php echo esc_attr( $page_class( 'idta-cover' ) ); ?>">

	<p class="idta-cover__association">
		<?php esc_html_e( 'INTERNATIONAL DRIVING PERMIT ASSOCIATION', 'idta-pdf' ); ?>
	</p>

	<h1 class="idta-cover__title">
		<?php esc_html_e( 'International Driving Permit', 'idta-pdf' ); ?>
	</h1>

	<p class="idta-cover__convention"><?php echo esc_html( $context['convention'] ); ?></p>

	<p class="idta-cover__notice">
		<?php esc_html_e( 'IMPORTANT — This Permit is Only Valid When Shown With Your Domestic Driver\'s Licence', 'idta-pdf' ); ?>
	</p>

	<div class="idta-cover__expiry">
		<span><?php esc_html_e( 'Expires on:', 'idta-pdf' ); ?></span>
		<span class="idta-cover__expiry-value"><?php echo esc_html( $context['expiry_date'] ); ?></span>
	</div>

	<?php if ( '' !== $context['cover_logo'] ) : ?>
		<img class="idta-cover__logo" src="<?php echo esc_attr( $context['cover_logo'] ); ?>" alt="">
	<?php endif; ?>

	<?php if ( '' !== $context['authority_sign'] ) : ?>
		<div class="idta-cover__authority">
			<img src="<?php echo esc_attr( $context['authority_sign'] ); ?>" alt="">
		</div>
	<?php endif; ?>

	<p class="idta-cover__authority-label">
		<?php esc_html_e( 'Authorized signature of Empowered Authority', 'idta-pdf' ); ?>
	</p>

	<?php if ( '' !== $context['permit_qr'] ) : ?>
		<img class="idta-cover__qr" src="<?php echo esc_attr( $context['permit_qr'] ); ?>" alt="">
	<?php endif; ?>

	<p class="idta-cover__order"><?php echo esc_html( $context['card_number'] ); ?></p>

	<p class="idta-cover__legal">
		<?php esc_html_e( 'This International Driving Permit is issued in accordance with the United Nations Conventions on Road Traffic of 1949 and 1968.', 'idta-pdf' ); ?><br>
		<?php esc_html_e( 'Acceptance of this permit is subject to local laws and regulations of each country.', 'idta-pdf' ); ?><br>
		<?php esc_html_e( 'The issuing authority bears no responsibility for refusal by local authorities.', 'idta-pdf' ); ?>
	</p>
</div>

<?php
foreach ( $context['interior_pages'] as $page ) :
	if ( 'template' === $page['type'] || 'language' === $page['type'] ) :
		// Page templates read $document, $data and $context from this scope;
		// a language page also reads its own strings from $page_lang.
		$page_lang = $page['language'] ?? null;
		?>
		<div class="<?php echo esc_attr( $page_class( 'idta-page--content' ) ); ?>">
			<?php include $page['template']; ?>
		</div>
		<?php
	else :
		?>
		<div class="<?php echo esc_attr( $page_class( 'idta-page--artwork' ) ); ?>">
			<img class="idta-page__artwork" src="<?php echo esc_attr( $page['src'] ); ?>" alt="">
		</div>
		<?php
	endif;
endforeach;
?>

<div class="<?php echo esc_attr( $page_class( 'idta-holder' ) ); ?>">

	<ul class="idta-holder__fields">
		<?php foreach ( $holder_fields as $field ) : ?>
			<li class="idta-holder__field">
				<span class="idta-holder__num"><?php echo esc_html( $field['num'] ); ?></span>
				<span class="idta-holder__value"><?php echo esc_html( $field['value'] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="idta-holder__row">

		<div class="idta-holder__categories">
			<?php foreach ( $context['all_categories'] as $category ) : ?>
				<?php $granted = in_array( $category, $context['categories'], true ); ?>
				<div class="idta-category">
					<div class="idta-category__box"><?php echo esc_html( $category ); ?></div>
					<?php
					$seal = $granted ? $context['seal'] : $context['seal_blank'];

					if ( '' !== $seal ) :
						?>
						<img class="idta-category__seal" src="<?php echo esc_attr( $seal ); ?>" alt="">
						<?php
					elseif ( $granted ) :
						?>
						<div class="idta-category__granted"><?php esc_html_e( 'VALID', 'idta-pdf' ); ?></div>
						<?php
					endif;
					?>
				</div>
			<?php endforeach; ?>
		</div>

		<div class="idta-holder__identity">

			<div class="idta-holder__photo">
				<?php if ( '' !== $context['photo'] ) : ?>
					<img class="idta-holder__portrait" src="<?php echo esc_attr( $context['photo'] ); ?>" alt="">
				<?php endif; ?>

				<?php
				/*
				 * The stamp needs its own left-aligned block. The identity column
				 * is centred, and a centred inline image measures its margins from
				 * where the centring put it, so the seal drifted to the middle of
				 * the portrait instead of its corner.
				 */
				?>
				<?php if ( '' !== $context['stamp'] ) : ?>
					<div class="idta-holder__stamp-box">
						<img class="idta-holder__stamp" src="<?php echo esc_attr( $context['stamp'] ); ?>" alt="">
					</div>
				<?php endif; ?>
			</div>

			<div class="idta-holder__signature">
				<?php if ( '' !== $context['signature'] ) : ?>
					<img src="<?php echo esc_attr( $context['signature'] ); ?>" alt="">
				<?php endif; ?>
			</div>

			<div class="idta-holder__rule"></div>

			<p class="idta-holder__signature-label">
				<?php esc_html_e( 'Signature du titulaire*', 'idta-pdf' ); ?>
			</p>

			<?php if ( '' !== $context['details_qr'] ) : ?>
				<img class="idta-holder__qr" src="<?php echo esc_attr( $context['details_qr'] ); ?>" alt="">
			<?php endif; ?>
		</div>

		<div class="idta-clear"></div>
	</div>

	<div class="idta-exclusions">
		<p class="idta-exclusions__title"><?php esc_html_e( 'EXCLUSIONS', 'idta-pdf' ); ?></p>
		<p class="idta-exclusions__subtitle"><?php esc_html_e( '(pays)', 'idta-pdf' ); ?></p>

		<?php
		/*
		 * The dashed rule is a cell border, not a bordered span: mPDF draws
		 * nothing at all for an empty inline-block, so the numerals printed on
		 * their own with no line to write on. Two numeral/rule pairs per row give
		 * the printed two-column layout without nesting a table in a cell, which
		 * mPDF also ignores.
		 */
		?>
		<table class="idta-exclusions__grid">
			<?php for ( $row = 0; $row < 4; $row++ ) : ?>
				<tr>
					<?php foreach ( array( $row, $row + 4 ) as $index ) : ?>
						<td class="idta-exclusions__numeral"><?php echo esc_html( $exclusion_numerals[ $index ] ); ?></td>
						<td class="idta-exclusions__line"></td>
					<?php endforeach; ?>
				</tr>
			<?php endfor; ?>
		</table>

		<p class="idta-exclusions__note"><?php esc_html_e( '*Ou l\'empreinte du pouce', 'idta-pdf' ); ?></p>
	</div>
</div>

<?php
foreach ( $context['closing_pages'] as $page ) :
	if ( 'template' === $page['type'] || 'language' === $page['type'] ) :
		$page_lang = $page['language'] ?? null;
		?>
		<div class="<?php echo esc_attr( $page_class( 'idta-page--content' ) ); ?>">
			<?php include $page['template']; ?>
		</div>
		<?php
	else :
		?>
		<div class="<?php echo esc_attr( $page_class( 'idta-page--artwork' ) ); ?>">
			<img class="idta-page__artwork" src="<?php echo esc_attr( $page['src'] ); ?>" alt="">
		</div>
		<?php
	endif;
endforeach;
?>
