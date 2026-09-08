<?php
/**
 * The email telling a customer their permit is ready.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * A WooCommerce email carrying links to the customer's finished permit.
 *
 * A WC_Email rather than a wp_mail() call, so it arrives inside the store's own
 * header and footer, and so its subject, heading and on/off switch live where a
 * shop manager already looks for them: WooCommerce → Settings → Emails.
 *
 * It carries links, never attachments. Nothing is stored, so there is no file to
 * attach — and the links are better anyway: a booklet routinely exceeds what a
 * mail server will accept, and a link renders the permit as it stands when the
 * customer opens it.
 */
final class Release_Email extends \WC_Email {

	/**
	 * Order meta recording when this email was sent.
	 */
	public const SENT_META = '_idta_pdf_permit_email_sent';

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Download URL builder.
	 *
	 * @var Download_Handler
	 */
	private Download_Handler $downloads;

	/**
	 * Constructor.
	 *
	 * @param Generator        $generator Document generator.
	 * @param Download_Handler $downloads Download URL builder.
	 */
	public function __construct( Generator $generator, Download_Handler $downloads ) {
		$this->generator = $generator;
		$this->downloads = $downloads;

		$this->id             = 'idta_pdf_release';
		$this->customer_email = true;
		$this->title          = __( 'Permit ready', 'idta-pdf' );
		$this->description    = __( 'Sent to the customer once the wait set under IDTA PDF → Release has passed, with links to their permit booklet and digital card.', 'idta-pdf' );

		$this->template_html  = 'emails/permit-ready.php';
		$this->template_plain = 'emails/plain/permit-ready.php';
		$this->template_base  = plugin_dir_path( PLUGIN_FILE ) . 'templates/';

		$this->placeholders = array(
			'{order_number}' => '',
			'{order_date}'   => '',
		);

		parent::__construct();
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	public function get_default_subject(): string {
		return __( 'Your International Driving Permit is ready', 'idta-pdf' );
	}

	/**
	 * Default heading.
	 *
	 * @return string
	 */
	public function get_default_heading(): string {
		return __( 'Your permit is ready', 'idta-pdf' );
	}

	/**
	 * Send the email for an order.
	 *
	 * @param int            $order_id Order ID.
	 * @param \WC_Order|null $order    Order object, when the caller has one.
	 *
	 * @return bool Whether the mail was handed to the mailer.
	 */
	public function trigger( $order_id, $order = null ): bool {
		$this->setup_locale();

		if ( ! $order instanceof \WC_Order ) {
			$order = wc_get_order( absint( $order_id ) );
		}

		if ( ! $order instanceof \WC_Order || ! Order_Data::has_data( $order ) ) {
			$this->restore_locale();

			return false;
		}

		$this->object    = $order;
		$this->recipient = $order->get_billing_email();

		$this->placeholders['{order_number}'] = $order->get_order_number();
		$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );

		if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
			$this->restore_locale();

			return false;
		}

		$sent = $this->send(
			$this->get_recipient(),
			$this->get_subject(),
			$this->get_content(),
			$this->get_headers(),
			$this->get_attachments()
		);

		$this->restore_locale();

		return (bool) $sent;
	}

	/**
	 * Values shared by both templates.
	 *
	 * @return array<string,mixed>
	 */
	private function template_args(): array {
		$order = $this->object instanceof \WC_Order ? $this->object : null;

		return array(
			'order'          => $order,
			'email_heading'  => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'sent_to_admin'  => false,
			'plain_text'     => false,
			'email'          => $this,
			'permits'        => null !== $order ? $this->permit_links( $order ) : array(),
			'extras'         => null !== $order ? $this->virtual_items( $order ) : array(),
		);
	}

	/**
	 * The permit links to offer, in the order they are shown.
	 *
	 * Only the two documents the permit holder is given: the A5 permit print and
	 * the card bitmap are production files for whoever prints the permit.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array<int,array{label:string,url:string,primary:bool}>
	 */
	private function permit_links( \WC_Order $order ): array {
		$labels = array(
			'booklet' => __( 'Booklet', 'idta-pdf' ),
			'card'    => __( 'Digital Card', 'idta-pdf' ),
		);

		$links = array();

		foreach ( $this->generator->offered_slugs( $order ) as $slug ) {
			if ( ! isset( $labels[ $slug ] ) || ! in_array( $slug, Generator::CUSTOMER_DOCUMENTS, true ) ) {
				continue;
			}

			$links[] = array(
				'label'   => $labels[ $slug ],
				'url'     => $this->downloads->url( $order, $slug ),
				'primary' => 'booklet' === $slug,
			);
		}

		return $links;
	}

	/**
	 * The order's virtual lines, listed under the permit links.
	 *
	 * Virtual because that is what an order of this kind is made of — a permit
	 * is a service, not a parcel — so this is the rest of what the customer
	 * bought. A downloadable line carries its own link, taken from WooCommerce's
	 * own downloadable-items list so the permissions and expiry the store set
	 * still apply.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array<int,array{name:string,quantity:int,total:string,downloads:array<int,array{name:string,url:string}>}>
	 */
	private function virtual_items( \WC_Order $order ): array {
		$downloads = array();

		foreach ( $order->get_downloadable_items() as $item ) {
			$item_id = (int) ( $item['product_id'] ?? 0 );

			$downloads[ $item_id ][] = array(
				'name' => (string) ( $item['download_name'] ?? '' ),
				'url'  => (string) ( $item['download_url'] ?? '' ),
			);
		}

		$items = array();

		foreach ( $order->get_items() as $line ) {
			if ( ! $line instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$product = $line->get_product();

			if ( ! $product instanceof \WC_Product || ! $product->is_virtual() ) {
				continue;
			}

			$product_id = (int) $line->get_product_id();

			$items[] = array(
				/*
				 * Through the same filter WooCommerce's own order tables use, so
				 * a line renamed for the order screen and the order-details email
				 * reads identically here. get_name() alone returns the raw
				 * catalogue title and would have made this the one place showing
				 * the old name.
				 */
				'name'      => wp_strip_all_tags(
					(string) apply_filters( 'woocommerce_order_item_name', $line->get_name(), $line, false )
				),
				'quantity'  => (int) $line->get_quantity(),
				/*
				 * Decoded to real text, not left as markup. WooCommerce formats
				 * a price as HTML with the currency as an entity ("&yen;3,000"),
				 * and the templates escape what they print — which would have
				 * shown the customer "&amp;yen;3,000". The plain-text template
				 * needs it decoded anyway.
				 */
				'total'     => html_entity_decode(
					wp_strip_all_tags( (string) $order->get_formatted_line_subtotal( $line ) ),
					ENT_QUOTES,
					'UTF-8'
				),
				'downloads' => $downloads[ $product_id ] ?? array(),
			);
		}

		return $items;
	}

	/**
	 * HTML body.
	 *
	 * @return string
	 */
	public function get_content_html(): string {
		return wc_get_template_html( $this->template_html, $this->template_args(), '', $this->template_base );
	}

	/**
	 * Plain-text body.
	 *
	 * @return string
	 */
	public function get_content_plain(): string {
		$args = $this->template_args();

		$args['plain_text'] = true;

		return wc_get_template_html( $this->template_plain, $args, '', $this->template_base );
	}

	/**
	 * Wording shown under the body, editable in the email's own settings.
	 *
	 * @return string
	 */
	public function get_default_additional_content(): string {
		return __( 'These links are personal to your order — please keep them to yourself. Thanks for choosing us.', 'idta-pdf' );
	}
}
