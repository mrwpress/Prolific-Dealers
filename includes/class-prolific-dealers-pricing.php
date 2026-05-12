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

		$discount = PROLIFIC_DEALERS_DISCOUNT / 100;
		return (string) round( (float) $price * ( 1 - $discount ), 2 );
	}
    public static function variation_prices_hash( $hash ) {
		if ( Prolific_Dealers::is_dealer() ) {
			$hash[] = 'dealer_' . PROLIFIC_DEALERS_DISCOUNT;
		}
		return $hash;
	}
}
