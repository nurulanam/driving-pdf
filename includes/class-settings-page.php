<?php
/**
 * Admin settings screen.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the plugin's settings screen under WooCommerce.
 */
final class Settings_Page {

	/**
	 * Menu slug.
	 */
	private const SLUG = 'idta-pdf-settings';

	/**
	 * Settings repository.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings repository.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_idta_pdf_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Add the settings submenu.
	 */
	public function add_menu(): void {
		add_submenu_page(
			'woocommerce',
			__( 'IDTA PDF', 'idta-pdf' ),
			__( 'IDTA PDF', 'idta-pdf' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Persist submitted settings.
	 */
	public function handle_save(): void {
		check_admin_referer( 'idta-pdf-settings' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'idta-pdf' ), '', array( 'response' => 403 ) );
		}

		// Values are sanitised field by field in Settings::sanitize().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = isset( $_POST['idta_pdf'] ) ? wp_unslash( (array) $_POST['idta_pdf'] ) : array();

		$this->settings->save( $raw );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$values    = $this->settings->all();
		$renderer  = plugin()->generator()->renderer();
		$qr        = new QR_Generator( $this->settings );
		$statuses  = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$documents = array(
			'booklet' => __( 'Permit booklet — A4, 210 × 297 mm portrait', 'idta-pdf' ),
			'card'    => __( 'Permit card — 85.6 × 53.98 mm portrait', 'idta-pdf' ),
		);
		$emails    = array(
			'customer_processing_order' => __( 'Processing order', 'idta-pdf' ),
			'customer_completed_order'  => __( 'Completed order', 'idta-pdf' ),
			'customer_invoice'          => __( 'Invoice', 'idta-pdf' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'IDTA PDF', 'idta-pdf' ); ?></h1>

			<?php if ( ! $renderer->is_available() ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							/* translators: %s: engine name. */
							esc_html__( '%s is not installed. Run "composer install" inside the idta-pdf plugin directory before generating documents.', 'idta-pdf' ),
							esc_html( $renderer->name() )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $qr->is_available() ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'endroid/qr-code is not installed; documents will be generated without QR codes.', 'idta-pdf' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'idta-pdf' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="idta_pdf_save_settings">
				<?php wp_nonce_field( 'idta-pdf-settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="idta-font-size"><?php esc_html_e( 'Base font size', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<input
								type="number" step="0.25" min="4" max="72"
								id="idta-font-size"
								name="idta_pdf[font_size]"
								value="<?php echo esc_attr( (string) $values['font_size'] ); ?>"
								class="small-text"
							> pt
							<p class="description">
								<?php esc_html_e( 'Applied to both documents. Custom CSS below is loaded afterwards and overrides this.', 'idta-pdf' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="idta-font-family"><?php esc_html_e( 'Base font family', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="idta-font-family"
								name="idta_pdf[font_family]"
								value="<?php echo esc_attr( (string) $values['font_family'] ); ?>"
								class="regular-text"
							>
							<p class="description">
								<?php esc_html_e( 'An mPDF font name, for example dejavusans, freeserif or helvetica.', 'idta-pdf' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Documents to generate', 'idta-pdf' ); ?></th>
						<td>
							<?php foreach ( $documents as $slug => $label ) : ?>
								<label style="display:block;margin-bottom:4px;">
									<input
										type="checkbox"
										name="idta_pdf[documents][]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, (array) $values['documents'], true ) ); ?>
									>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Also generate when order becomes', 'idta-pdf' ); ?></th>
						<td>
							<?php foreach ( $statuses as $key => $label ) : ?>
								<?php $slug = str_replace( 'wc-', '', (string) $key ); ?>
								<label style="display:block;margin-bottom:4px;">
									<input
										type="checkbox"
										name="idta_pdf[trigger_statuses][]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, (array) $values['trigger_statuses'], true ) ); ?>
									>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'Documents are always generated in the background as soon as the order is placed. These statuses are an extra trigger for orders whose IDP details arrive later.', 'idta-pdf' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Attach to emails', 'idta-pdf' ); ?></th>
						<td>
							<?php foreach ( $emails as $key => $label ) : ?>
								<label style="display:block;margin-bottom:4px;">
									<input
										type="checkbox"
										name="idta_pdf[attach_to_emails][]"
										value="<?php echo esc_attr( $key ); ?>"
										<?php checked( in_array( $key, (array) $values['attach_to_emails'], true ) ); ?>
									>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
							<p class="description">
								<?php esc_html_e( 'Off by default. The booklet embeds every scanned page at full resolution and often exceeds 10 MB, which most mail servers reject — the download link is usually the better route.', 'idta-pdf' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="idta-qr-base"><?php esc_html_e( 'Verification base URL', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<input
								type="url"
								id="idta-qr-base"
								name="idta_pdf[qr_base_url]"
								value="<?php echo esc_attr( (string) $values['qr_base_url'] ); ?>"
								placeholder="<?php echo esc_attr( home_url() ); ?>"
								class="regular-text code"
							>
							<p class="description">
								<?php esc_html_e( 'QR codes point at /idp/ and /show-details/ on this host. Leave blank to use this site\'s own URL.', 'idta-pdf' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="idta-qr-permit"><?php esc_html_e( 'Permit link secret', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="idta-qr-permit"
								name="idta_pdf[qr_permit_secret]"
								value="<?php echo esc_attr( (string) $values['qr_permit_secret'] ); ?>"
								class="regular-text code"
								autocomplete="off"
							>
							<p class="description">
								<?php esc_html_e( 'Must match the secret used by the verification site. Leave blank to derive one from this site\'s salts.', 'idta-pdf' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="idta-qr-details"><?php esc_html_e( 'Details link secret', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="idta-qr-details"
								name="idta_pdf[qr_details_secret]"
								value="<?php echo esc_attr( (string) $values['qr_details_secret'] ); ?>"
								class="regular-text code"
								autocomplete="off"
							>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'idta-pdf' ); ?></th>
						<td>
							<label style="display:block;margin-bottom:4px;">
								<input
									type="checkbox"
									name="idta_pdf[grayscale_ghost]"
									value="1"
									<?php checked( ! empty( $values['grayscale_ghost'] ) ); ?>
								>
								<?php esc_html_e( 'Render the small duplicate portrait in grayscale', 'idta-pdf' ); ?>
							</label>
							<label style="display:block;">
								<input
									type="checkbox"
									name="idta_pdf[debug_html]"
									value="1"
									<?php checked( ! empty( $values['debug_html'] ) ); ?>
								>
								<?php esc_html_e( 'Save the rendered HTML next to each PDF (debugging)', 'idta-pdf' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Custom CSS', 'idta-pdf' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Loaded after the built-in stylesheets, so these rules win. Shared CSS is applied first, then the per-document CSS.', 'idta-pdf' ); ?>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="idta-custom-css"><?php esc_html_e( 'Both documents', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<textarea
								id="idta-custom-css"
								name="idta_pdf[custom_css]"
								rows="8" class="large-text code"
							><?php echo esc_textarea( (string) $values['custom_css'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="idta-booklet-css"><?php esc_html_e( 'Booklet only', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<textarea
								id="idta-booklet-css"
								name="idta_pdf[booklet_css]"
								rows="8" class="large-text code"
							><?php echo esc_textarea( (string) $values['booklet_css'] ); ?></textarea>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="idta-card-css"><?php esc_html_e( 'Card only', 'idta-pdf' ); ?></label>
						</th>
						<td>
							<textarea
								id="idta-card-css"
								name="idta_pdf[card_css]"
								rows="8" class="large-text code"
							><?php echo esc_textarea( (string) $values['card_css'] ); ?></textarea>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
