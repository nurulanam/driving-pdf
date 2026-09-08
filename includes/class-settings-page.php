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

		/*
		 * Back to the tab the form was submitted from, so editing a stylesheet
		 * does not bounce to the first tab on every save.
		 */
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- The referer is checked at the top of this method.
		$tab = isset( $_POST['idta_tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['idta_tab'] ) ) : '';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::SLUG,
					'tab'     => isset( $this->tabs()[ $tab ] ) ? $tab : (string) array_key_first( $this->tabs() ),
					'updated' => 'true',
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/**
	 * Tabs, in the order they are shown, keyed by the slug the URL carries.
	 *
	 * @return array<string,string>
	 */
	private function tabs(): array {
		return array(
			'documents' => __( 'Documents', 'idta-pdf' ),
			'timing'    => __( 'Timing', 'idta-pdf' ),
			'delivery'  => __( 'Delivery', 'idta-pdf' ),
			'uploads'   => __( 'Uploads', 'idta-pdf' ),
			'styling'   => __( 'Styling', 'idta-pdf' ),
			'status'    => __( 'Status', 'idta-pdf' ),
		);
	}

	/**
	 * The tab to open, taken from the URL.
	 *
	 * Held in the URL rather than in the browser alone so that saving returns to
	 * the tab that was being edited: handle_save() reads the hidden field the
	 * form carries and redirects back to it. Editing the card's CSS and being
	 * dropped back onto the first tab on every save is a small thing that gets
	 * old quickly.
	 *
	 * @return string
	 */
	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses which read-only panel to show.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';

		return isset( $this->tabs()[ $tab ] ) ? $tab : (string) array_key_first( $this->tabs() );
	}

	/**
	 * Render the settings screen.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$values    = $this->settings->all();
		$generator = plugin()->generator();
		$renderer  = $generator->renderer();
		$qr        = new QR_Generator( $this->settings );
		$statuses  = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$active    = $this->current_tab();

		$documents = array(
			'booklet'    => array(
				__( 'Permit booklet', 'idta-pdf' ),
				__( 'A4, 210 × 297 mm portrait. The full multilingual booklet.', 'idta-pdf' ),
			),
			'card'       => array(
				__( 'Permit card', 'idta-pdf' ),
				__( '85.6 × 53.98 mm, credit-card size, printed both sides.', 'idta-pdf' ),
			),
			'print-copy' => array(
				__( 'Permit print', 'idta-pdf' ),
				__( 'A5, the holder\'s details alone, for printing onto pre-printed booklet stock.', 'idta-pdf' ),
			),
		);

		$emails = array(
			'customer_processing_order' => __( 'Processing order', 'idta-pdf' ),
			'customer_completed_order'  => __( 'Completed order', 'idta-pdf' ),
			'customer_invoice'          => __( 'Invoice', 'idta-pdf' ),
		);
		?>
		<div class="wrap idta-settings">
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

			<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag set by our own redirect. ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'idta-pdf' ); ?></p>
				</div>
			<?php endif; ?>

			<style>
				.idta-settings .nav-tab-wrapper { margin-bottom: 20px; }
				.idta-settings .idta-pane { display: none; }
				.idta-settings .idta-pane.is-active { display: block; }
				.idta-card {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
					padding: 4px 20px 16px;
					margin: 0 0 16px;
					max-width: 900px;
					box-shadow: 0 1px 1px rgba( 0, 0, 0, .04 );
				}
				.idta-card > h2 {
					font-size: 14px;
					margin: 16px 0 4px;
					padding: 0;
				}
				.idta-card > .idta-card__intro {
					margin: 0 0 4px;
					color: #646970;
					max-width: 70ch;
				}
				.idta-card .form-table th { padding-left: 0; width: 200px; }
				.idta-card .form-table td { padding-left: 0; }
				.idta-choice { display: block; margin-bottom: 8px; }
				.idta-choice:last-child { margin-bottom: 0; }
				.idta-choice__hint { display: block; margin: 2px 0 0 25px; color: #646970; }
				.idta-choice input[disabled] + span { color: #8c8f94; }
				.idta-inline { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; }
				.idta-inline label { display: block; font-weight: 600; }
				.idta-inline label span { display: block; font-weight: 400; margin-bottom: 4px; }
				.idta-status { border-collapse: collapse; width: 100%; max-width: 860px; }
				.idta-status th,
				.idta-status td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
				.idta-status th { width: 220px; font-weight: 600; }
				.idta-status tr:last-child th,
				.idta-status tr:last-child td { border-bottom: 0; }
				.idta-pill {
					display: inline-block;
					padding: 1px 8px;
					border-radius: 9px;
					font-size: 11px;
					font-weight: 600;
					text-transform: uppercase;
					letter-spacing: .02em;
				}
				.idta-pill--ok { background: #edfaef; color: #00622b; }
				.idta-pill--warn { background: #fcf3e4; color: #8a5700; }
				.idta-pill--bad { background: #fcf0f1; color: #8a2424; }
				.idta-status code { font-size: 12px; word-break: break-all; }
			</style>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $this->tabs() as $tab_slug => $tab_label ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => self::SLUG, 'tab' => $tab_slug ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab idta-tab<?php echo $active === $tab_slug ? ' nav-tab-active' : ''; ?>"
						data-tab="<?php echo esc_attr( $tab_slug ); ?>"
					>
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="idta_pdf_save_settings">
				<input type="hidden" name="idta_tab" id="idta-current-tab" value="<?php echo esc_attr( $active ); ?>">
				<?php wp_nonce_field( 'idta-pdf-settings' ); ?>

				<!-- Documents -->
				<div class="idta-pane<?php echo 'documents' === $active ? ' is-active' : ''; ?>" data-pane="documents">
					<div class="idta-card">
						<h2><?php esc_html_e( 'Documents to generate', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'An order still only receives what it asks for: an order for a card alone never produces a booklet, whatever is ticked here.', 'idta-pdf' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Produce', 'idta-pdf' ); ?></th>
								<td>
									<?php foreach ( $documents as $slug => $document ) : ?>
										<label class="idta-choice">
											<input
												type="checkbox"
												name="idta_pdf[documents][]"
												value="<?php echo esc_attr( $slug ); ?>"
												<?php checked( in_array( $slug, (array) $values['documents'], true ) ); ?>
											>
											<span><?php echo esc_html( $document[0] ); ?></span>
											<span class="idta-choice__hint"><?php echo esc_html( $document[1] ); ?></span>
										</label>
									<?php endforeach; ?>
								</td>
							</tr>
						</table>
					</div>

					<div class="idta-card">
						<h2><?php esc_html_e( 'Card bitmap', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'For a direct-to-card printer such as a Zebra ZC300. One pixel per printer dot, so the driver resamples nothing and fine type and the guilloche pattern stay sharp.', 'idta-pdf' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Bitmap export', 'idta-pdf' ); ?></th>
								<td>
									<?php
									$bitmaps_ready = Card_Bitmap::is_available();
									list( $bitmap_w, $bitmap_h ) = Card_Bitmap::pixels();
									?>
									<label class="idta-choice">
										<input
											type="checkbox"
											name="idta_pdf[card_bmp]"
											value="1"
											<?php checked( (bool) $values['card_bmp'] ); ?>
											<?php disabled( ! $bitmaps_ready ); ?>
										>
										<span>
											<?php
											printf(
												/* translators: 1: pixel width, 2: pixel height, 3: resolution in dots per inch. */
												esc_html__( 'Also write the card front as a 24-bit RGB bitmap, %1$d × %2$d px at %3$d dpi', 'idta-pdf' ),
												(int) $bitmap_w,
												(int) $bitmap_h,
												(int) Card_Bitmap::DPI
											);
											?>
										</span>
										<span class="idta-choice__hint">
											<?php esc_html_e( 'Listed with the order\'s documents as "Card front". The back is the same on every card, so it is not generated per order.', 'idta-pdf' ); ?>
										</span>
									</label>
									<?php if ( ! $bitmaps_ready ) : ?>
										<p class="description" style="color:#b32d2e;margin-top:8px;">
											<?php esc_html_e( 'Unavailable on this server: converting the card PDF to a bitmap needs the Imagick extension, Ghostscript or pdftoppm, and none of them can be reached. Ask your host to enable one.', 'idta-pdf' ); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>

					<div class="idta-card">
						<h2><?php esc_html_e( 'Rendering', 'idta-pdf' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Options', 'idta-pdf' ); ?></th>
								<td>
									<label class="idta-choice">
										<input
											type="checkbox"
											name="idta_pdf[grayscale_ghost]"
											value="1"
											<?php checked( ! empty( $values['grayscale_ghost'] ) ); ?>
										>
										<span><?php esc_html_e( 'Render the small duplicate portrait in grayscale', 'idta-pdf' ); ?></span>
									</label>
									<label class="idta-choice">
										<input
											type="checkbox"
											name="idta_pdf[debug_html]"
											value="1"
											<?php checked( ! empty( $values['debug_html'] ) ); ?>
										>
										<span><?php esc_html_e( 'Save the rendered HTML next to each PDF', 'idta-pdf' ); ?></span>
										<span class="idta-choice__hint"><?php esc_html_e( 'For debugging a layout. Leave off in normal use.', 'idta-pdf' ); ?></span>
									</label>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Timing -->
				<div class="idta-pane<?php echo 'timing' === $active ? ' is-active' : ''; ?>" data-pane="timing">
					<div class="idta-card">
						<h2><?php esc_html_e( 'When to generate', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'Documents are queued when an order reaches one of these statuses, then built after the wait set below.', 'idta-pdf' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Order statuses', 'idta-pdf' ); ?></th>
								<td>
									<?php foreach ( $statuses as $key => $label ) : ?>
										<?php $slug = str_replace( 'wc-', '', (string) $key ); ?>
										<label class="idta-choice">
											<input
												type="checkbox"
												name="idta_pdf[trigger_statuses][]"
												value="<?php echo esc_attr( $slug ); ?>"
												<?php checked( in_array( $slug, (array) $values['trigger_statuses'], true ) ); ?>
											>
											<span><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
									<p class="description" style="margin-top:8px;">
										<?php esc_html_e( 'Include "pending" to queue new orders before payment.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="idta-card">
						<h2><?php esc_html_e( 'How long to wait', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'Measured from payment, so the wait is the same whether the customer paid at checkout or followed a pay link days later. An order with no payment recorded is timed from when it reached one of the statuses above.', 'idta-pdf' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="idta-rush-products"><?php esc_html_e( 'Rush products', 'idta-pdf' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										class="regular-text code"
										id="idta-rush-products"
										name="idta_pdf[rush_products]"
										value="<?php echo esc_attr( implode( ', ', (array) $values['rush_products'] ) ); ?>"
										placeholder="20"
									>
									<p class="description">
										<?php esc_html_e( 'Product IDs, separated by commas. An order containing any of them takes the rush wait. Variation IDs work too. Leave empty to give every order the standard wait.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Waits', 'idta-pdf' ); ?></th>
								<td>
									<div class="idta-inline">
										<label for="idta-rush-delay">
											<span><?php esc_html_e( 'Rush order', 'idta-pdf' ); ?></span>
											<input
												type="number" min="0" step="1" class="small-text"
												id="idta-rush-delay"
												name="idta_pdf[rush_delay]"
												value="<?php echo esc_attr( (string) (int) $values['rush_delay'] ); ?>"
											>
											<?php esc_html_e( 'minutes', 'idta-pdf' ); ?>
										</label>
										<label for="idta-standard-delay">
											<span><?php esc_html_e( 'Every other order', 'idta-pdf' ); ?></span>
											<input
												type="number" min="0" step="1" class="small-text"
												id="idta-standard-delay"
												name="idta_pdf[standard_delay]"
												value="<?php echo esc_attr( (string) (int) $values['standard_delay'] ); ?>"
											>
											<?php esc_html_e( 'minutes', 'idta-pdf' ); ?>
										</label>
									</div>
									<p class="description" style="margin-top:10px;">
										<?php esc_html_e( 'Zero generates immediately. The admin "Generate" and "Regenerate" buttons always run at once and ignore these.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Delivery -->
				<div class="idta-pane<?php echo 'delivery' === $active ? ' is-active' : ''; ?>" data-pane="delivery">
					<div class="idta-card">
						<h2><?php esc_html_e( 'Email attachments', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'Only the booklet and the card are ever attached — the permit print and the card bitmap are production files and stay on the admin screens.', 'idta-pdf' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Attach to', 'idta-pdf' ); ?></th>
								<td>
									<?php foreach ( $emails as $key => $label ) : ?>
										<label class="idta-choice">
											<input
												type="checkbox"
												name="idta_pdf[attach_to_emails][]"
												value="<?php echo esc_attr( $key ); ?>"
												<?php checked( in_array( $key, (array) $values['attach_to_emails'], true ) ); ?>
											>
											<span><?php echo esc_html( $label ); ?></span>
										</label>
									<?php endforeach; ?>
									<p class="description" style="margin-top:8px;">
										<?php esc_html_e( 'Off by default. The booklet often exceeds 10 MB, which most mail servers reject — the download link is usually the better route.', 'idta-pdf' ); ?>
									</p>
									<?php if ( array() !== (array) $values['attach_to_emails'] ) : ?>
										<p class="description" style="color:#8a5700;">
											<?php esc_html_e( 'Note: an email that finds no documents yet builds them on the spot, so attaching to an email that goes out at payment cancels the wait set under Timing.', 'idta-pdf' ); ?>
										</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>

					<div class="idta-card">
						<h2><?php esc_html_e( 'Verification links', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'Where the card\'s QR codes point, and the secrets that sign them so a permit cannot be looked up by guessing an order number.', 'idta-pdf' ); ?>
						</p>
						<?php if ( ! $qr->is_available() ) : ?>
							<p class="description" style="color:#8a5700;">
								<?php esc_html_e( 'endroid/qr-code is not installed, so documents are generated without QR codes. These settings take effect once it is.', 'idta-pdf' ); ?>
							</p>
						<?php endif; ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="idta-qr-base"><?php esc_html_e( 'Base URL', 'idta-pdf' ); ?></label>
								</th>
								<td>
									<input
										type="url"
										class="regular-text code"
										id="idta-qr-base"
										name="idta_pdf[qr_base_url]"
										value="<?php echo esc_attr( (string) $values['qr_base_url'] ); ?>"
										placeholder="<?php echo esc_attr( home_url() ); ?>"
									>
									<p class="description">
										<?php esc_html_e( 'Left empty, this site\'s own address is used, which is right unless verification is served from another domain.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="idta-qr-permit"><?php esc_html_e( 'Permit secret', 'idta-pdf' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										class="regular-text code"
										id="idta-qr-permit"
										name="idta_pdf[qr_permit_secret]"
										value="<?php echo esc_attr( (string) $values['qr_permit_secret'] ); ?>"
										autocomplete="off"
									>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="idta-qr-details"><?php esc_html_e( 'Details secret', 'idta-pdf' ); ?></label>
								</th>
								<td>
									<input
										type="text"
										class="regular-text code"
										id="idta-qr-details"
										name="idta_pdf[qr_details_secret]"
										value="<?php echo esc_attr( (string) $values['qr_details_secret'] ); ?>"
										autocomplete="off"
									>
									<p class="description">
										<?php esc_html_e( 'Changing a secret invalidates the codes already printed on issued permits, so change one only to retire a leak.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- Uploads -->
				<div class="idta-pane<?php echo 'uploads' === $active ? ' is-active' : ''; ?>" data-pane="uploads">
					<div class="idta-card">
						<h2><?php esc_html_e( 'Upload sources', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'Each row maps a value the checkout stores in "_idp_order_from" to the base URL that order\'s _idp_assets folder is relative to. Blank rows are ignored; leaving every row blank restores the built-in defaults.', 'idta-pdf' ); ?>
						</p>
						<?php
						$configured_sources = is_array( $values['asset_sources'] ) && array() !== $values['asset_sources']
							? $values['asset_sources']
							: Order_Data::SOURCES;

						$source_rows = array();

						foreach ( $configured_sources as $source_key => $source_url ) {
							$source_rows[] = array( (string) $source_key, (string) $source_url );
						}

						for ( $i = 0; $i < 2; $i++ ) {
							$source_rows[] = array( '', '' );
						}
						?>
						<table class="idta-source-rows widefat striped" style="max-width:860px;margin-top:12px;">
							<thead>
								<tr>
									<th style="width:200px;"><?php esc_html_e( 'Order From value', 'idta-pdf' ); ?></th>
									<th><?php esc_html_e( 'Base URL', 'idta-pdf' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $source_rows as $row_index => $source_row ) : ?>
									<tr>
										<td>
											<input
												type="text"
												name="idta_pdf[asset_sources][<?php echo (int) $row_index; ?>][key]"
												value="<?php echo esc_attr( $source_row[0] ); ?>"
												class="code" style="width:100%;"
												placeholder="<?php esc_attr_e( 'e.g. idta', 'idta-pdf' ); ?>"
											>
										</td>
										<td>
											<input
												type="url"
												name="idta_pdf[asset_sources][<?php echo (int) $row_index; ?>][url]"
												value="<?php echo esc_attr( $source_row[1] ); ?>"
												class="code" style="width:100%;"
												placeholder="https://…/files/"
											>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p style="margin-top:10px;">
							<button type="button" class="button" id="idta-add-source">
								<?php esc_html_e( '+ Add source', 'idta-pdf' ); ?>
							</button>
						</p>
					</div>
				</div>

				<!-- Styling -->
				<div class="idta-pane<?php echo 'styling' === $active ? ' is-active' : ''; ?>" data-pane="styling">
					<div class="idta-card">
						<h2><?php esc_html_e( 'Base type', 'idta-pdf' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="idta-font-size"><?php esc_html_e( 'Font size', 'idta-pdf' ); ?></label>
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
										<?php esc_html_e( 'Applied to every document. The custom CSS below is loaded afterwards and overrides it.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th scope="row">
									<label for="idta-font-family"><?php esc_html_e( 'Font family', 'idta-pdf' ); ?></label>
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
										<?php esc_html_e( 'The base face for every document. Translation pages set their own face per script. Register another with the idta_pdf_font_data filter and it appears here.', 'idta-pdf' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>

					<div class="idta-card">
						<h2><?php esc_html_e( 'Custom CSS', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'Loaded after the built-in stylesheets, so these rules win. The shared CSS is applied first, then the per-document CSS.', 'idta-pdf' ); ?>
						</p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="idta-custom-css"><?php esc_html_e( 'Every document', 'idta-pdf' ); ?></label>
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
				</div>

				<!-- Status -->
				<div class="idta-pane<?php echo 'status' === $active ? ' is-active' : ''; ?>" data-pane="status">
					<div class="idta-card">
						<h2><?php esc_html_e( 'System status', 'idta-pdf' ); ?></h2>
						<p class="idta-card__intro">
							<?php esc_html_e( 'What this server can actually do. Worth reading first when something is missing or a setting will not stay switched on.', 'idta-pdf' ); ?>
						</p>
						<table class="idta-status">
							<?php foreach ( $this->status_rows( $renderer, $qr, $generator ) as $row ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
									<td>
										<?php if ( '' !== $row['state'] ) : ?>
											<span class="idta-pill idta-pill--<?php echo esc_attr( $row['state'] ); ?>">
												<?php echo esc_html( $row['value'] ); ?>
											</span>
										<?php else : ?>
											<code><?php echo esc_html( $row['value'] ); ?></code>
										<?php endif; ?>
										<?php if ( '' !== $row['note'] ) : ?>
											<p class="description" style="margin:4px 0 0;"><?php echo esc_html( $row['note'] ); ?></p>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				</div>

				<?php submit_button(); ?>
			</form>

			<script>
				( function () {
					var tabs  = document.querySelectorAll( '.idta-tab' );
					var panes = document.querySelectorAll( '.idta-pane' );
					var field = document.getElementById( 'idta-current-tab' );

					tabs.forEach( function ( tab ) {
						tab.addEventListener( 'click', function ( event ) {
							/*
							 * Switched without a round trip, but the link still
							 * carries the tab in its href, so opening it in a new
							 * window — or having JavaScript fail — lands on the
							 * right tab anyway.
							 */
							event.preventDefault();

							var slug = tab.getAttribute( 'data-tab' );

							tabs.forEach( function ( other ) {
								other.classList.toggle( 'nav-tab-active', other === tab );
							} );

							panes.forEach( function ( pane ) {
								pane.classList.toggle( 'is-active', pane.getAttribute( 'data-pane' ) === slug );
							} );

							// So a save returns here, and a reload stays here.
							field.value = slug;
							window.history.replaceState( {}, '', tab.getAttribute( 'href' ) );
						} );
					} );

					var add = document.getElementById( 'idta-add-source' );

					if ( add ) {
						add.addEventListener( 'click', function ( event ) {
							event.preventDefault();

							var body  = document.querySelector( '.idta-source-rows tbody' );
							var index = body.querySelectorAll( 'tr' ).length;
							var row   = document.createElement( 'tr' );

							row.innerHTML =
								'<td><input type="text" class="code" style="width:100%" name="idta_pdf[asset_sources][' + index + '][key]"></td>' +
								'<td><input type="url" class="code" style="width:100%" name="idta_pdf[asset_sources][' + index + '][url]"></td>';

							body.appendChild( row );
						} );
					}
				}() );
			</script>
		</div>
		<?php
	}

	/**
	 * Rows for the status table.
	 *
	 * @param Renderer     $renderer  Rendering engine.
	 * @param QR_Generator $qr        QR builder.
	 * @param Generator    $generator Document generator.
	 *
	 * @return array<int,array{label:string,value:string,state:string,note:string}>
	 */
	private function status_rows( Renderer $renderer, QR_Generator $qr, Generator $generator ): array {
		$rows = array();

		$rows[] = array(
			'label' => __( 'Plugin version', 'idta-pdf' ),
			'value' => VERSION,
			'state' => '',
			'note'  => '',
		);

		$rows[] = array(
			'label' => __( 'PDF engine', 'idta-pdf' ),
			'value' => $renderer->is_available() ? $renderer->name() : __( 'Missing', 'idta-pdf' ),
			'state' => $renderer->is_available() ? 'ok' : 'bad',
			'note'  => $renderer->is_available()
				? ''
				: __( 'Run "composer install" in the plugin directory. Nothing can be generated until this is present.', 'idta-pdf' ),
		);

		$rows[] = array(
			'label' => __( 'QR codes', 'idta-pdf' ),
			'value' => $qr->is_available() ? __( 'Available', 'idta-pdf' ) : __( 'Missing', 'idta-pdf' ),
			'state' => $qr->is_available() ? 'ok' : 'warn',
			'note'  => $qr->is_available()
				? ''
				: __( 'endroid/qr-code is not installed. Documents generate, but without QR codes.', 'idta-pdf' ),
		);

		$rasteriser = Card_Bitmap::rasteriser();

		$rows[] = array(
			'label' => __( 'Card bitmap', 'idta-pdf' ),
			'value' => '' !== $rasteriser ? $rasteriser : __( 'Unavailable', 'idta-pdf' ),
			'state' => '' !== $rasteriser ? 'ok' : 'warn',
			'note'  => '' !== $rasteriser
				/* translators: %s: name of the rasteriser in use. */
				? sprintf( __( 'Bitmaps are produced with %s.', 'idta-pdf' ), $rasteriser )
				: __( 'Needs the Imagick extension, Ghostscript or pdftoppm. The setting stays switched off until one is reachable.', 'idta-pdf' ),
		);

		/*
		 * Action Scheduler is what makes a delay land when it is meant to.
		 * WooCommerce ships it, so its absence means something has gone wrong
		 * with the install rather than being a host limitation.
		 */
		$scheduler = function_exists( 'as_schedule_single_action' );

		$rows[] = array(
			'label' => __( 'Scheduler', 'idta-pdf' ),
			'value' => $scheduler ? __( 'Action Scheduler', 'idta-pdf' ) : __( 'WP-Cron', 'idta-pdf' ),
			'state' => $scheduler ? 'ok' : 'warn',
			'note'  => $scheduler
				? __( 'Generation waits are honoured to the minute.', 'idta-pdf' )
				: __( 'Falling back to WP-Cron, which only runs when the site is visited, so a document may appear later than its wait implies.', 'idta-pdf' ),
		);

		$base     = $generator->filesystem()->base_dir();
		$writable = '' !== $base && is_writable( $base );

		// Shown as a path rather than a pill, because that is what an operator
		// needs to read; the note carries the verdict.
		$rows[] = array(
			'label' => __( 'Storage', 'idta-pdf' ),
			'value' => '' !== $base ? $base : __( 'Unavailable', 'idta-pdf' ),
			'state' => '',
			'note'  => $writable
				? __( 'Writable.', 'idta-pdf' )
				: __( 'Not writable, so generated documents cannot be stored.', 'idta-pdf' ),
		);

		return $rows;
	}
}
