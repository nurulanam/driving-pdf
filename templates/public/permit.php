<?php
/**
 * Public permit page, served at /idp/?entry_key=…
 *
 * Reached by scanning the QR code on the booklet cover. Offers the permit PDF,
 * and the card too when the order has one.
 *
 * Override by copying to `idta-pdf/public/permit.php` in your theme.
 *
 * @var \WC_Order              $order     Order object.
 * @var \IDTA\PDF\Order_Data   $data      Order data.
 * @var array<string,string>   $documents Generated document paths, keyed by slug.
 * @var \IDTA\PDF\Download_Handler $downloads Download URL builder.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$permit_labels = array(
	'booklet' => __( 'Download permit (PDF)', 'idta-pdf' ),
	'card'    => __( 'Download card (PDF)', 'idta-pdf' ),
);
?>
<div class="idta-card">

	<h1><?php esc_html_e( 'International Driving Permit', 'idta-pdf' ); ?></h1>

	<p class="idta-lede">
		<?php echo esc_html( $data->full_name() ); ?>
	</p>

	<?php if ( '' !== $data->card_number() ) : ?>
		<div class="idta-ref"><?php echo esc_html( $data->card_number() ); ?></div>
	<?php endif; ?>

	<?php
	/*
	 * Only the documents that exist are offered. The download endpoint would
	 * happily render one on demand, but that means waiting on a phone connection
	 * for a booklet that takes a couple of seconds to build, so a missing document
	 * says so instead.
	 */
	$permit_available = array_intersect_key( $permit_labels, $documents );

	if ( array() === $permit_available ) :
		?>
		<p><?php esc_html_e( 'Your permit is still being prepared. Please check back shortly.', 'idta-pdf' ); ?></p>
		<?php
	else :
		foreach ( $permit_available as $permit_slug => $permit_label ) :
			printf(
				'<a class="idta-button%1$s" href="%2$s">%3$s</a>',
				'booklet' === $permit_slug ? '' : ' idta-button--secondary',
				esc_url( $downloads->url( $order, $permit_slug ) ),
				esc_html( $permit_label )
			);
		endforeach;
	endif;
	?>

	<p class="idta-note">
		<?php esc_html_e( 'This permit is a translation of the holder\'s domestic driving licence and is valid only when presented with it.', 'idta-pdf' ); ?>
	</p>
</div>

<p class="idta-footer">
	<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
</p>
