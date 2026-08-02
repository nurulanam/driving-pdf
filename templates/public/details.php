<?php
/**
 * Public details page, served at /show-details/?entry_key=…
 *
 * Reached by scanning the QR code on the holder page, so an official can check
 * the printed details against the record.
 *
 * Override by copying to `idta-pdf/public/details.php` in your theme.
 *
 * @var \WC_Order                  $order     Order object.
 * @var \IDTA\PDF\Order_Data       $data      Order data.
 * @var array<string,string>       $documents Generated document paths, keyed by slug.
 * @var \IDTA\PDF\Download_Handler $downloads Download URL builder.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$detail_categories = $data->license_categories();

/**
 * Rows shown on the page. A row with an empty value is dropped rather than
 * printed blank, so an incomplete order does not look broken.
 *
 * @var array<string,string> $detail_rows
 */
$detail_rows = array(
	__( 'Name', 'idta-pdf' )          => $data->full_name(),
	__( 'Birth country', 'idta-pdf' ) => $data->country_of_birth(),
	// ISO order, which reads unambiguously whatever the reader's local convention.
	__( 'DOB', 'idta-pdf' )           => $data->date_of_birth_formatted( 'Y-m-d' ),
	__( 'Gender', 'idta-pdf' )        => $data->gender(),
	__( 'License types', 'idta-pdf' ) => implode( ', ', $detail_categories ),
);

$detail_scans = array_filter(
	array(
		__( 'Licence front', 'idta-pdf' ) => $data->license_front(),
		__( 'Licence back', 'idta-pdf' )  => $data->license_back(),
	)
);

$detail_photo = $data->passport_photo();
?>
<div class="idta-card">

	<?php if ( '' !== $detail_photo ) : ?>
		<img class="idta-photo" src="<?php echo esc_url( $detail_photo ); ?>" alt="<?php esc_attr_e( 'Passport photo', 'idta-pdf' ); ?>" loading="lazy">
	<?php endif; ?>

	<h1><?php esc_html_e( 'Your details', 'idta-pdf' ); ?></h1>

	<?php if ( '' !== $data->card_number() ) : ?>
		<div class="idta-ref"><?php echo esc_html( $data->card_number() ); ?></div>
	<?php endif; ?>

	<dl class="idta-details">
		<?php foreach ( $detail_rows as $detail_label => $detail_value ) : ?>
			<?php if ( '' === trim( (string) $detail_value ) ) : ?>
				<?php continue; ?>
			<?php endif; ?>
			<div class="idta-details__row">
				<dt><?php echo esc_html( $detail_label ); ?></dt>
				<dd><?php echo esc_html( $detail_value ); ?></dd>
			</div>
		<?php endforeach; ?>
	</dl>
</div>

<?php if ( array() !== $detail_scans ) : ?>
	<div class="idta-card">
		<h2><?php esc_html_e( 'Domestic licence', 'idta-pdf' ); ?></h2>

		<div class="idta-scans">
			<?php foreach ( $detail_scans as $scan_label => $scan_url ) : ?>
				<figure class="idta-scan">
					<img src="<?php echo esc_url( $scan_url ); ?>" alt="<?php echo esc_attr( $scan_label ); ?>" loading="lazy">
					<figcaption><?php echo esc_html( $scan_label ); ?></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>
	</div>
<?php endif; ?>
