<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Pricing {

	public static function init() {
		add_filter( 'woocommerce_product_get_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_product_get_regular_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_product_variation_get_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_product_variation_get_regular_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_variation_prices_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_variation_prices_regular_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_get_variation_prices_hash', [ __CLASS__, 'variation_prices_hash' ], 10, 3 );
		add_action( 'add_meta_boxes', [ __CLASS__, 'register_meta_box' ] );
		add_action( 'save_post_product', [ __CLASS__, 'save_meta_box' ] );
	}

	public static function register_meta_box() {
		add_meta_box(
			'prolific_dealer_discount',
			'Dealer Discount Override',
			[ __CLASS__, 'render_meta_box' ],
			'product',
			'side',
			'default'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'prolific_dealer_discount', 'prolific_dealer_discount_nonce' );
		$value = get_post_meta( $post->ID, '_prolific_dealer_discount', true );
		?>
		<p>
			<label for="prolific_dealer_discount">
				<?php esc_html_e( 'Discount % (overrides tier default)', 'prolific-dealers' ); ?>
			</label>
		</p>
		<p>
			<input type="number" id="prolific_dealer_discount" name="prolific_dealer_discount"
				value="<?php echo esc_attr( $value ); ?>" min="0" max="100" step="1" style="width:80px;" />
			<span class="description"><?php esc_html_e( 'Leave empty to use tier default.', 'prolific-dealers' ); ?></span>
		</p>
		<?php
	}

	public static function save_meta_box( $post_id ) {
		if ( ! isset( $_POST['prolific_dealer_discount_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( $_POST['prolific_dealer_discount_nonce'], 'prolific_dealer_discount' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$value = isset( $_POST['prolific_dealer_discount'] ) ? sanitize_text_field( $_POST['prolific_dealer_discount'] ) : '';
		if ( '' === $value ) {
			delete_post_meta( $post_id, '_prolific_dealer_discount' );
			return;
		}
		update_post_meta( $post_id, '_prolific_dealer_discount', $value );
	}

	public static function apply_dealer_discount( $price, $product ) {
		if ( ! Prolific_Dealers::is_dealer() || '' === $price ) {
			return $price;
		}

		$discount = self::get_discount_for_current_user( $product );
		if ( $discount <= 0 ) {
			return $price;
		}

		return (string) round( (float) $price * ( 1 - $discount / 100 ), 2 );
	}

	public static function get_discount_for_current_user( $product = null ) {
		$user_override = Prolific_Dealers_User::get_discount_override();
		if ( null !== $user_override ) {
			return $user_override;
		}

		if ( $product ) {
			$product_id       = $product->get_parent_id() ?: $product->get_id();
			$product_discount = get_post_meta( $product_id, '_prolific_dealer_discount', true );
			if ( '' !== $product_discount ) {
				return (float) $product_discount;
			}
		}

		$tier = Prolific_Dealers_User::get_dealer_tier();
		return Prolific_Dealers_Settings::get_tier_discount( $tier );
	}

	public static function variation_prices_hash( $hash, $product = null, $for_display = false ) {
		if ( Prolific_Dealers::is_dealer() ) {
			$user_override    = Prolific_Dealers_User::get_discount_override();
			$tier             = Prolific_Dealers_User::get_dealer_tier();
			$discount         = Prolific_Dealers_Settings::get_tier_discount( $tier );
			$product_discount = '';
			if ( $product ) {
				$product_discount = get_post_meta( $product->get_id(), '_prolific_dealer_discount', true );
			}
			$hash[] = 'dealer_' . $user_override . '_' . $discount . '_' . $product_discount;
		}
		return $hash;
	}
}
