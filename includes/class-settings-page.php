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
 * Renders the plugin's settings screen.
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
	 * Add the settings menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'IDTA PDF', 'idta-pdf' ),
			__( 'IDTA PDF', 'idta-pdf' ),
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-pdf'
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
			'booklet'    => __( 'Permit booklet — A4, 210 × 297 mm portrait', 'idta-pdf' ),
			'card'       => __( 'Permit card — 85.6 × 53.98 mm portrait', 'idta-pdf' ),
			'print-copy' => __( 'Permit print — A5, the holder\'s details alone, for printing onto pre-printed booklet stock', 'idta-pdf' ),
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

			<style>
				.idta-tabs { border-bottom: 1px solid #ccc; margin: 20px 0 0; padding: 0; list-style: none; display: flex; }
				.idta-tabs li { margin: 0; padding: 0; }
				.idta-tabs a { display: block; padding: 12px 20px; text-decoration: none; color: #555; border-bottom: 3px solid transparent; cursor: pointer; }
				.idta-tabs a:hover { color: #0073aa; }
				.idta-tabs a.active { color: #0073aa; border-bottom-color: #0073aa; }
				.idta-tab-pane { display: none; }
				.idta-tab-pane.active { display: block; }
			</style>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="idta_pdf_save_settings">
				<?php wp_nonce_field( 'idta-pdf-settings' ); ?>

				<ul class="idta-tabs">
					<li><a href="#idta-tab-general" class="idta-tab-link active"><?php esc_html_e( 'General', 'idta-pdf' ); ?></a></li>
					<li><a href="#idta-tab-sources" class="idta-tab-link"><?php esc_html_e( 'Upload Sources', 'idta-pdf' ); ?></a></li>
					<li><a href="#idta-tab-style" class="idta-tab-link"><?php esc_html_e( 'Styling', 'idta-pdf' ); ?></a></li>
				</ul>

				<!-- General Tab -->
				<div id="idta-tab-general" class="idta-tab-pane active">
					<table class="form-table" role="presentation">
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
							<th scope="row"><?php esc_html_e( 'Generate at', 'idta-pdf' ); ?></th>
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
									<?php esc_html_e( 'Documents generate only when an order reaches one of the checked statuses. Include "pending" to generate on new orders.', 'idta-pdf' ); ?>
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
				</div>

				<!-- Upload Sources Tab -->
				<div id="idta-tab-sources" class="idta-tab-pane">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Upload sources', 'idta-pdf' ); ?></th>
							<td>
								<?php
								$configured_sources = is_array( $values['asset_sources'] ) && array() !== $values['asset_sources']
									? $values['asset_sources']
									: Order_Data::SOURCES;

								$source_rows = array();

								foreach ( $configured_sources as $source_key => $source_url ) {
									$source_rows[] = array( (string) $source_key, (string) $source_url );
								}

								for ( $i = 0; $i < 3; $i++ ) {
									$source_rows[] = array( '', '' );
								}
								?>
								<table class="idta-source-rows">
									<thead>
										<tr>
											<th style="text-align:left;font-weight:600;padding:0 8px 4px 0;"><?php esc_html_e( 'Order From value', 'idta-pdf' ); ?></th>
											<th style="text-align:left;font-weight:600;padding:0 0 4px;"><?php esc_html_e( 'Base URL', 'idta-pdf' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $source_rows as $row_index => $source_row ) : ?>
											<tr>
												<td style="padding:2px 8px 2px 0;">
													<input
														type="text"
														name="idta_pdf[asset_sources][<?php echo (int) $row_index; ?>][key]"
														value="<?php echo esc_attr( $source_row[0] ); ?>"
														class="regular-text code"
														placeholder="<?php esc_attr_e( 'e.g. idta', 'idta-pdf' ); ?>"
													>
												</td>
												<td style="padding:2px 0;">
													<input
														type="url"
														name="idta_pdf[asset_sources][<?php echo (int) $row_index; ?>][url]"
														value="<?php echo esc_attr( $source_row[1] ); ?>"
														class="regular-text code"
														placeholder="https://…/files/"
													>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<button type="button" class="button button-secondary" id="idta-add-source" style="margin-top:8px;">+ <?php esc_html_e( 'Add source', 'idta-pdf' ); ?></button>
								<script>
									document.getElementById( 'idta-add-source' ).addEventListener( 'click', function ( e ) {
										e.preventDefault();
										const table = document.querySelector( '.idta-source-rows tbody' );
										const rowCount = table.querySelectorAll( 'tr' ).length;
										const newIndex = rowCount;
										const newRow = document.createElement( 'tr' );
										newRow.innerHTML = '<td style="padding:2px 8px 2px 0;"><input type="text" name="idta_pdf[asset_sources][' + newIndex + '][key]" class="regular-text code" placeholder="e.g. idta"></td><td style="padding:2px 0;"><input type="url" name="idta_pdf[asset_sources][' + newIndex + '][url]" class="regular-text code" placeholder="https://…/files/"></td>';
										table.appendChild( newRow );
									} );
								</script>
								<p class="description">
									<?php esc_html_e( 'Each row maps a value the checkout stores in "_idp_order_from" to the base URL that order\'s _idp_assets folder is relative to. Blank rows are ignored; leaving all rows blank restores the built-in defaults.', 'idta-pdf' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<!-- Styling Tab -->
				<div id="idta-tab-style" class="idta-tab-pane">
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
								<?php
								$font_choices = array_keys( Fonts::registry() );
								sort( $font_choices );
								?>
								<select id="idta-font-family" name="idta_pdf[font_family]">
									<?php foreach ( $font_choices as $font_choice ) : ?>
										<option
											value="<?php echo esc_attr( $font_choice ); ?>"
											<?php selected( (string) $values['font_family'], $font_choice ); ?>
										>
											<?php echo esc_html( $font_choice ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<p class="description">
									<?php esc_html_e( 'The base face for both documents. Translation pages set their own face per script. Register another with the idta_pdf_font_data filter and it appears here.', 'idta-pdf' ); ?>
								</p>
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
				</div>

				<?php submit_button(); ?>
			</form>

			<script>
				document.querySelectorAll( '.idta-tab-link' ).forEach( link => {
					link.addEventListener( 'click', ( e ) => {
						e.preventDefault();
						const target = link.getAttribute( 'href' );
						document.querySelectorAll( '.idta-tab-pane' ).forEach( pane => pane.classList.remove( 'active' ) );
						document.querySelectorAll( '.idta-tab-link' ).forEach( l => l.classList.remove( 'active' ) );
						document.querySelector( target ).classList.add( 'active' );
						link.classList.add( 'active' );
					} );
				} );
			</script>
		</div>
		<?php
	}
}
