<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Pricing {

	public static function init() {
		add_filter( 'woocommerce_product_get_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_product_variation_get_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
		add_filter( 'woocommerce_variation_prices_price', [ __CLASS__, 'apply_dealer_discount' ], 10, 2 );
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
		$overrides = get_post_meta( $post->ID, '_prolific_dealer_discount_tiers', true ) ?: [];
		?>
		<p class="description"><?php esc_html_e( 'Leave empty to use the default discount for that tier.', 'prolific-dealers' ); ?></p>
		<?php for ( $i = 1; $i <= 10; $i++ ) :
			$val = isset( $overrides[ $i ] ) ? $overrides[ $i ] : '';
			?>
			<p class="prolific-discount-tier-row" data-tier="<?php echo $i; ?>" style="display:flex;align-items:center;gap:6px;margin:4px 0;">
				<label for="prolific_dealer_discount_tier_<?php echo $i; ?>" style="min-width:50px;">
					<?php echo esc_html( Prolific_Dealers_Settings::get_tier_label( $i ) ); ?>
				</label>
				<input type="number" id="prolific_dealer_discount_tier_<?php echo $i; ?>"
					name="prolific_dealer_discount_tier[<?php echo $i; ?>]"
					value="<?php echo esc_attr( $val ); ?>" min="0" max="100" step="1" style="width:60px;" />
				<span>%</span>
			</p>
		<?php endfor; ?>
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

		$tiers = isset( $_POST['prolific_dealer_discount_tier'] ) ? (array) $_POST['prolific_dealer_discount_tier'] : [];
		$clean = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			if ( isset( $tiers[ $i ] ) && '' !== $tiers[ $i ] ) {
				$clean[ $i ] = sanitize_text_field( $tiers[ $i ] );
			}
		}

		if ( empty( $clean ) ) {
			delete_post_meta( $post_id, '_prolific_dealer_discount_tiers' );
		} else {
			update_post_meta( $post_id, '_prolific_dealer_discount_tiers', $clean );
		}

		// Clean up legacy single-value meta if present.
		delete_post_meta( $post_id, '_prolific_dealer_discount' );
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

		$tier = Prolific_Dealers_User::get_dealer_tier();

		if ( $product ) {
			$product_id = $product->get_parent_id() ?: $product->get_id();
			$overrides  = get_post_meta( $product_id, '_prolific_dealer_discount_tiers', true ) ?: [];
			if ( isset( $overrides[ $tier ] ) && '' !== $overrides[ $tier ] ) {
				return (float) $overrides[ $tier ];
			}
		}

		return Prolific_Dealers_Settings::get_tier_discount( $tier );
	}

	public static function variation_prices_hash( $hash, $product = null, $for_display = false ) {
		if ( Prolific_Dealers::is_dealer() ) {
			$user_override = Prolific_Dealers_User::get_discount_override();
			$tier          = Prolific_Dealers_User::get_dealer_tier();
			$discount      = Prolific_Dealers_Settings::get_tier_discount( $tier );
			$tier_override = '';
			if ( $product ) {
				$overrides = get_post_meta( $product->get_id(), '_prolific_dealer_discount_tiers', true ) ?: [];
				$tier_override = $overrides[ $tier ] ?? '';
			}
			$hash[] = 'dealer_' . $user_override . '_' . $tier . '_' . $discount . '_' . $tier_override;
		}
		return $hash;
	}
}
