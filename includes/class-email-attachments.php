<?php
/**
 * Attaches generated documents to WooCommerce emails.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the permit PDFs to the configured customer emails.
 */
final class Email_Attachments {

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Document generator.
	 *
	 * @var Generator
	 */
	private Generator $generator;

	/**
	 * Constructor.
	 *
	 * @param Settings  $settings  Settings repository.
	 * @param Generator $generator Document generator.
	 */
	public function __construct( Settings $settings, Generator $generator ) {
		$this->settings  = $settings;
		$this->generator = $generator;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach' ), 10, 4 );
	}

	/**
	 * Append document paths to an outgoing email.
	 *
	 * @param mixed  $attachments Existing attachments.
	 * @param string $email_id    Email identifier.
	 * @param mixed  $object      Email subject object.
	 * @param mixed  $email       Email instance.
	 *
	 * @return array<int,string>
	 */
	public function attach( $attachments, $email_id, $object = null, $email = null ): array {
		unset( $email );

		$attachments = is_array( $attachments ) ? $attachments : array();

		if ( ! $object instanceof \WC_Order ) {
			return $attachments;
		}

		if ( ! in_array( (string) $email_id, $this->settings->attachment_emails(), true ) ) {
			return $attachments;
		}

		$documents = $this->generator->generated_documents( $object );

		// Generate on the spot when the email fires before the queued job ran.
		if ( array() === $documents && Order_Data::has_data( $object ) ) {
			try {
				$documents = $this->generator->generate( $object );
			} catch ( \Throwable $exception ) {
				$this->generator->log_failure( $object, $exception );

				return $attachments;
			}
		}

		foreach ( $documents as $path ) {
			/*
			 * PDFs only. The stored map also holds the card's bitmap faces,
			 * which are production files for whoever runs the card printer, and
			 * attaching them would put nearly 4 MB of images the customer
			 * cannot use onto an email that is already large enough to be
			 * refused.
			 */
			if ( 'pdf' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
				continue;
			}

			if ( is_readable( $path ) ) {
				$attachments[] = $path;
			}
		}

		return $attachments;
	}
}
