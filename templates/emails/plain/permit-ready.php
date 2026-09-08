<?php
/**
 * Permit-ready email, plain text.
 *
 * Override by copying to `woocommerce/emails/plain/permit-ready.php` in your theme.
 *
 * @var \WC_Order $order              Order object.
 * @var string    $email_heading      Heading.
 * @var string    $additional_content Wording from the email's own settings.
 * @var array<int,array{label:string,url:string,primary:bool}> $permits Permit links.
 * @var array<int,array{name:string,quantity:int,total:string,downloads:array<int,array{name:string,url:string}>}> $extras Virtual lines.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$idta_data = new \IDTA\PDF\Order_Data( $order );

echo "= " . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

printf(
	/* translators: %s: customer's first name. */
	esc_html__( 'Hi %s,', 'idta-pdf' ),
	esc_html( $idta_data->first_name() )
);

echo "\n\n";

esc_html_e( 'Your International Driving Permit is ready. Use the links below to open it.', 'idta-pdf' );

echo "\n\n";

foreach ( $permits as $idta_permit ) {
	echo esc_html( $idta_permit['label'] ) . ":\n" . esc_url_raw( $idta_permit['url'] ) . "\n\n";
}

if ( array() !== $extras ) {
	echo "\n" . esc_html__( 'Also in this order', 'idta-pdf' ) . "\n";
	echo "----------------------------------------\n";

	foreach ( $extras as $idta_extra ) {
		echo '- ' . esc_html( $idta_extra['name'] );

		if ( $idta_extra['quantity'] > 1 ) {
			echo esc_html( sprintf( ' x %d', $idta_extra['quantity'] ) );
		}

		if ( '' !== $idta_extra['total'] ) {
			echo ' (' . esc_html( $idta_extra['total'] ) . ')';
		}

		echo "\n";

		foreach ( $idta_extra['downloads'] as $idta_download ) {
			echo '  ' . esc_url_raw( $idta_download['url'] ) . "\n";
		}
	}

	echo "\n";
}

printf(
	/* translators: %s: order number. */
	esc_html__( 'Order %s', 'idta-pdf' ),
	esc_html( $order->get_order_number() )
);

echo "\n\n";

if ( '' !== $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo esc_html( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
