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
		add_filter( 'woocommerce_get_variation_prices_hash', [ __CLASS__, 'variation_prices_hash' ], 10, 1 );
	}

	public static function apply_dealer_discount( $price, $product ) {
		if ( ! Prolific_Dealers::is_dealer() || '' === $price ) {
			return $price;
		}

		$discount = self::get_discount_for_current_user();
		if ( $discount <= 0 ) {
			return $price;
		}

		return (string) round( (float) $price * ( 1 - $discount / 100 ), 2 );
	}

	public static function get_discount_for_current_user() {
		$tier     = Prolific_Dealers_User::get_dealer_tier();
		$discount = Prolific_Dealers_Settings::get_tier_discount( $tier );
		return $discount;
	}

	public static function variation_prices_hash( $hash ) {
		if ( Prolific_Dealers::is_dealer() ) {
			$discount = self::get_discount_for_current_user();
			$hash[]   = 'dealer_' . $discount;
		}
		return $hash;
	}
}
