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
