<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Visibility {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post_product', [ __CLASS__, 'save_meta_box' ] );
		add_action( 'pre_get_posts', [ __CLASS__, 'hide_dealer_only_products' ] );
		add_filter( 'woocommerce_product_is_visible', [ __CLASS__, 'filter_product_visibility' ], 10, 2 );
	}

	public static function register_meta_box() {
		add_meta_box(
			'prolific_dealer_only',
			'Dealer Only',
			[ __CLASS__, 'render_meta_box' ],
			'product',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'prolific_dealer_only', 'prolific_dealer_only_nonce' );
		$checked = get_post_meta( $post->ID, '_prolific_dealer_only', true );
		?>
		<label>
			<input type="checkbox" name="prolific_dealer_only" value="1" <?php checked( $checked, '1' ); ?> />
			Restrict this product to dealers only
		</label>
		<?php
	}

	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['prolific_dealer_only_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['prolific_dealer_only_nonce'], 'prolific_dealer_only' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// TODO: Chuck Test - verify dealer-only checkbox saves and persists on product edit screen
		$value = isset( $_POST['prolific_dealer_only'] ) ? '1' : '';
		update_post_meta( $post_id, '_prolific_dealer_only', $value );
	}

	public static function hide_dealer_only_products( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( Prolific_Dealers::is_dealer() ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		if ( 'product' !== $post_type && ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( get_object_taxonomies( 'product' ) ) ) {
			return;
		}

		// TODO: Chuck Test - verify dealer-only products are hidden from shop/category pages for non-dealers
		$meta_query = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
			'relation' => 'OR',
			[
				'key'     => '_prolific_dealer_only',
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => '_prolific_dealer_only',
				'value'   => '1',
				'compare' => '!=',
			],
		];
		$query->set( 'meta_query', $meta_query );
	}

	// TODO: Chuck Test - verify dealer-only products return 404 or redirect for non-dealer direct URL access
	public static function filter_product_visibility( $visible, $product_id ) {
		if ( Prolific_Dealers::is_dealer() ) {
			return $visible;
		}

		$dealer_only = get_post_meta( $product_id, '_prolific_dealer_only', true );
		if ( '1' === $dealer_only ) {
			return false;
		}

		return $visible;
	}
}
