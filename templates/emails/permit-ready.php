<?php
/**
 * Permit-ready email, HTML.
 *
 * Override by copying to `woocommerce/emails/permit-ready.php` in your theme.
 *
 * @var \WC_Order $order              Order object.
 * @var string    $email_heading      Heading.
 * @var string    $additional_content Wording from the email's own settings.
 * @var array<int,array{label:string,url:string,primary:bool}> $permits Permit links.
 * @var array<int,array{name:string,quantity:int,total:string,downloads:array<int,array{name:string,url:string}>}> $extras Virtual lines.
 * @var \WC_Email $email              Email instance.
 *
 * Laid out with tables and inline styles, not flexbox and a stylesheet: Outlook
 * renders neither, and several clients strip a <style> block entirely.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$idta_data = new \IDTA\PDF\Order_Data( $order );

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	printf(
		/* translators: %s: customer's first name. */
		esc_html__( 'Hi %s,', 'idta-pdf' ),
		esc_html( $idta_data->first_name() )
	);
	?>
</p>

<p>
	<?php esc_html_e( 'Your International Driving Permit is ready. Use the links below to open it — the booklet is the permit itself, and the digital card is the wallet-sized copy.', 'idta-pdf' ); ?>
</p>

<?php if ( array() !== $permits ) : ?>
	<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin: 24px 0;">
		<tr>
			<?php
			foreach ( $permits as $idta_permit ) :
				/*
				 * Built as one string rather than a multi-line attribute. The
				 * newlines are legal HTML, but email clients rewrite markup
				 * before showing it and are far less forgiving than a browser.
				 */
				$idta_button = 'display:inline-block;padding:14px 28px;border-radius:4px;'
					. 'font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:bold;'
					. 'text-decoration:none;border:2px solid #00399D;'
					. ( $idta_permit['primary']
						? 'background-color:#00399D;color:#ffffff;'
						: 'background-color:#ffffff;color:#00399D;' );
				?>
				<td style="padding: 0 12px 12px 0;">
					<a href="<?php echo esc_url( $idta_permit['url'] ); ?>" style="<?php echo esc_attr( $idta_button ); ?>"><?php echo esc_html( $idta_permit['label'] ); ?></a>
				</td>
			<?php endforeach; ?>
		</tr>
	</table>
<?php else : ?>
	<p><?php esc_html_e( 'Your permit is available from your account page.', 'idta-pdf' ); ?></p>
<?php endif; ?>

<?php if ( array() !== $extras ) : ?>
	<h2 style="font-size: 16px; margin: 28px 0 8px;">
		<?php esc_html_e( 'Also in this order', 'idta-pdf' ); ?>
	</h2>

	<ul style="margin: 0 0 24px; padding-left: 20px; line-height: 1.7;">
		<?php foreach ( $extras as $idta_extra ) : ?>
			<li>
				<strong><?php echo esc_html( $idta_extra['name'] ); ?></strong>
				<?php if ( $idta_extra['quantity'] > 1 ) : ?>
					<?php echo esc_html( sprintf( ' × %d', $idta_extra['quantity'] ) ); ?>
				<?php endif; ?>
				<?php if ( '' !== $idta_extra['total'] ) : ?>
					<span style="color: #666666;"> — <?php echo esc_html( $idta_extra['total'] ); ?></span>
				<?php endif; ?>

				<?php if ( array() !== $idta_extra['downloads'] ) : ?>
					<br>
					<?php foreach ( $idta_extra['downloads'] as $idta_download ) : ?>
						<a href="<?php echo esc_url( $idta_download['url'] ); ?>" style="color:#00399D;"><?php echo esc_html( '' !== $idta_download['name'] ? $idta_download['name'] : __( 'Download', 'idta-pdf' ) ); ?></a><br>
					<?php endforeach; ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<p style="font-size: 13px; color: #666666;">
	<?php
	printf(
		/* translators: %s: order number. */
		esc_html__( 'Order %s', 'idta-pdf' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<?php
if ( '' !== $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

do_action( 'woocommerce_email_footer', $email );
