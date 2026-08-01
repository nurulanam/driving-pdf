<?php
/**
 * Editable IDP meta panel on the order screen.
 *
 * @package IDTA\PDF
 */

declare( strict_types=1 );

namespace IDTA\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Lets a shop manager read and correct the `_idp_*` meta an order carries, with
 * a thumbnail for each of the four uploaded images.
 *
 * All four images come from the one folder in `_idp_assets`, so that is the only
 * field for them. The previews resolve through Order_Data, the same accessors the
 * PDFs call, so a thumbnail that fails to load is a genuine signal that
 * generation will not find the image either.
 */
final class Order_Fields {

	/**
	 * Nonce action.
	 */
	private const NONCE_ACTION = 'idta_pdf_save_order_fields';

	/**
	 * Nonce field name.
	 */
	private const NONCE_NAME = 'idta_pdf_order_fields_nonce';

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 30, 2 );

		// Legacy post-based orders screen.
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ), 30, 1 );

		// HPOS orders screen. Fires on every order save, so save() checks the
		// nonce first and returns unless this form is what was submitted.
		add_action( 'woocommerce_after_order_object_save', array( $this, 'save' ), 30, 1 );
	}

	/**
	 * Register the panel on both the legacy and HPOS order screens.
	 *
	 * Shown for any order, not only ones that already carry IDP meta, so the
	 * fields can be filled in on an order where the checkout did not write them.
	 *
	 * @param string $screen_id Current screen ID.
	 * @param mixed  $post      Post or order object.
	 */
	public function add_meta_box( $screen_id, $post = null ): void {
		if ( ! $this->resolve_order( $post ) instanceof \WC_Order ) {
			return;
		}

		add_meta_box(
			'idta-pdf-order-fields',
			__( 'IDP Driver Details (editable)', 'idta-pdf' ),
			array( $this, 'render' ),
			$screen_id,
			'normal',
			'high'
		);
	}

	/**
	 * Render the form.
	 *
	 * @param mixed $post Post or order object.
	 */
	public function render( $post ): void {
		$order = $this->resolve_order( $post );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$data = new Order_Data( $order );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$this->render_styles();

		echo '<table class="idta-fields">';

		foreach ( Order_Data::FIELDS as $key => $label ) {
			printf(
				'<tr><th><label for="%1$s">%2$s</label></th><td>',
				esc_attr( $key ),
				esc_html( $label )
			);

			if ( '_idp_order_from' === $key ) {
				$this->render_source_select( $key, (string) $order->get_meta( $key, true ) );
			} else {
				printf(
					'<input type="text" id="%1$s" name="%1$s" value="%2$s" class="idta-fields__input">',
					esc_attr( $key ),
					esc_attr( trim( (string) $order->get_meta( $key, true ) ) )
				);
			}

			if ( '_idp_assets' === $key ) {
				printf(
					'<p class="description">%s</p>',
					esc_html__( 'Folder holding all four uploads, e.g. 2026/08/01/063701-213.', 'idta-pdf' )
				);

				$this->render_asset_previews( $data );
			}

			echo '</td></tr>';
		}

		echo '</table>';

		printf(
			'<p class="description">%s</p>',
			esc_html__(
				'Saving here only updates the order. Use Regenerate in the IDP Documents panel to rebuild the PDFs from the corrected values.',
				'idta-pdf'
			)
		);
	}

	/**
	 * Render the order-source dropdown.
	 *
	 * A list rather than free text: the value selects which bucket the image
	 * paths are resolved against, so a typo would break every preview and every
	 * image in the PDFs.
	 *
	 * @param string $key     Meta key.
	 * @param string $current Stored value.
	 */
	private function render_source_select( string $key, string $current ): void {
		$current = strtolower( trim( $current ) );

		printf( '<select id="%1$s" name="%1$s" class="idta-fields__input">', esc_attr( $key ) );

		printf(
			'<option value=""%1$s>%2$s</option>',
			selected( $current, '', false ),
			esc_html__( '— not set —', 'idta-pdf' )
		);

		foreach ( array_keys( Order_Data::sources() ) as $source ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $source ),
				selected( $current, $source, false ),
				esc_html( strtoupper( $source ) )
			);
		}

		echo '</select>';

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Which front end took the order. Decides the base URL the assets folder is resolved against.', 'idta-pdf' )
		);
	}

	/**
	 * Render a thumbnail for each of the four images, together.
	 *
	 * One grid under the Assets Folder field, since that single value is what
	 * produces all four. The URLs come from the same accessors the PDFs call, so a
	 * thumbnail that fails to load means generation will not find the image either.
	 *
	 * @param Order_Data $data Order data.
	 */
	private function render_asset_previews( Order_Data $data ): void {
		$labels = array(
			'passport_photo' => __( 'Passport photo', 'idta-pdf' ),
			'license_front'  => __( 'License front', 'idta-pdf' ),
			'license_back'   => __( 'License back', 'idta-pdf' ),
			'signature'      => __( 'Signature', 'idta-pdf' ),
		);

		$urls = array(
			'passport_photo' => $data->passport_photo(),
			'license_front'  => $data->license_front(),
			'license_back'   => $data->license_back(),
			'signature'      => $data->signature(),
		);

		if ( array() === array_filter( $urls ) ) {
			// A folder is set but resolved to nothing, which means the source is
			// unknown; with no folder at all there is simply nothing to show yet.
			if ( '' !== trim( (string) $data->get( '_idp_assets' ) ) ) {
				printf(
					'<p class="idta-fields__warning">%s</p>',
					esc_html__( 'A folder is stored, but the order names no known source — set Order From above.', 'idta-pdf' )
				);
			}

			return;
		}

		echo '<div class="idta-fields__grid">';

		foreach ( $labels as $asset_key => $label ) {
			$url = $urls[ $asset_key ];

			echo '<div class="idta-fields__grid-item">';
			printf( '<p class="idta-fields__grid-label">%s</p>', esc_html( $label ) );

			if ( '' !== $url ) {
				printf(
					'<a href="%1$s" target="_blank" rel="noreferrer noopener"><img src="%1$s" alt="%2$s"></a>',
					esc_url( $url ),
					esc_attr( $label )
				);
			} else {
				printf( '<p class="description">%s</p>', esc_html__( 'Not set.', 'idta-pdf' ) );
			}

			echo '</div>';
		}

		echo '</div>';
	}

	/**
	 * Persist submitted values.
	 *
	 * @param int|\WC_Order $order_or_id Order object or ID.
	 */
	public function save( $order_or_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified immediately below.
		$nonce = $_POST[ self::NONCE_NAME ] ?? '';

		if ( ! is_string( $nonce ) || ! wp_verify_nonce( sanitize_key( $nonce ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$order = $order_or_id instanceof \WC_Order ? $order_or_id : wc_get_order( absint( $order_or_id ) );

		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$changed = false;

		foreach ( array_keys( Order_Data::FIELDS ) as $key ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised on the next line.
			$value = sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) );

			if ( '_idp_order_from' === $key ) {
				$value = strtolower( $value );

				// Never store a source that would not resolve.
				if ( '' !== $value && ! isset( Order_Data::sources()[ $value ] ) ) {
					continue;
				}
			}

			if ( (string) $order->get_meta( $key, true ) === $value ) {
				continue;
			}

			$order->update_meta_data( $key, $value );

			$changed = true;
		}

		if ( ! $changed ) {
			return;
		}

		/*
		 * save_meta_data(), not save(): this runs from
		 * woocommerce_after_order_object_save, and a full save() from inside that
		 * hook would recurse.
		 */
		$order->save_meta_data();
	}

	/**
	 * Panel styles.
	 */
	private function render_styles(): void {
		echo '<style>
			.idta-fields { width: 100%; border-collapse: collapse; }
			.idta-fields th,
			.idta-fields td { padding: 10px; text-align: left; border-bottom: 1px solid #e0e0e0; vertical-align: top; }
			.idta-fields th { width: 25%; font-weight: 600; }
			.idta-fields__input { width: 100%; max-width: 420px; }
			.idta-fields__warning { margin: 6px 0 0; color: #b32d2e; font-size: 12px; }
			.idta-fields__grid { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 10px; }
			.idta-fields__grid-item { width: 110px; }
			.idta-fields__grid-label { margin: 0 0 4px; font-size: 11px; font-weight: 600; color: #1d2327; }
			.idta-fields__grid-item img { max-width: 100%; height: auto; display: block; border: 1px solid #ccd0d4; border-radius: 4px; }
			.idta-fields__grid-item .description { margin: 0; font-size: 11px; }
		</style>';
	}

	/**
	 * Resolve the order from whatever the screen passed.
	 *
	 * @param mixed $post Post or order object.
	 *
	 * @return \WC_Order|null
	 */
	private function resolve_order( $post ): ?\WC_Order {
		if ( $post instanceof \WC_Order ) {
			return $post;
		}

		if ( $post instanceof \WP_Post ) {
			$order = wc_get_order( $post->ID );

			return $order instanceof \WC_Order ? $order : null;
		}

		return null;
	}
}
