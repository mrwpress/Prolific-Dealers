<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers_Settings {

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', [ __CLASS__, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_dealer', [ __CLASS__, 'output_settings' ] );
		add_action( 'woocommerce_update_options_dealer', [ __CLASS__, 'save_settings' ] );
	}

	public static function add_settings_tab( $tabs ) {
		$tabs['dealer'] = __( 'Dealer', 'prolific-dealers' );
		return $tabs;
	}

	public static function get_settings() {
		$settings = [
			[
				'title' => __( 'Dealer Tier Settings', 'prolific-dealers' ),
				'type'  => 'title',
				'desc'  => __( 'Set the name and default discount percentage for each dealer tier.', 'prolific-dealers' ),
				'id'    => 'prolific_dealer_tier_options',
			],
		];

		$defaults = PROLIFIC_DEALERS_DEFAULT_TIER_LABELS;

		for ( $i = 1; $i <= 10; $i++ ) {
			$settings[] = [
				'title'   => sprintf( __( 'Tier %d Name', 'prolific-dealers' ), $i ),
				'id'      => 'prolific_dealer_tier_label_' . $i,
				'type'    => 'text',
				'default' => $defaults[ $i ],
			];

			$settings[] = [
				'title'             => sprintf( __( 'Tier %d Discount (%%)', 'prolific-dealers' ), $i ),
				'id'                => 'prolific_dealer_tier_' . $i,
				'type'              => 'number',
				'default'           => '0',
				'desc'              => sprintf( __( 'Discount percentage for %s dealers.', 'prolific-dealers' ), self::get_tier_label( $i ) ),
				'desc_tip'          => true,
				'custom_attributes' => [
					'min'  => '0',
					'max'  => '100',
					'step' => '1',
				],
			];
		}

		$settings[] = [
			'type' => 'sectionend',
			'id'   => 'prolific_dealer_tier_options',
		];

		return $settings;
	}

	public static function output_settings() {
		woocommerce_admin_fields( self::get_settings() );
	}

	public static function save_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	public static function get_tier_label( $tier ) {
		$tier  = absint( $tier );
		if ( $tier < 1 || $tier > 10 ) {
			return '';
		}
		$label = get_option( 'prolific_dealer_tier_label_' . $tier );
		if ( $label ) {
			return $label;
		}
		$defaults = PROLIFIC_DEALERS_DEFAULT_TIER_LABELS;
		return $defaults[ $tier ] ?? sprintf( 'Tier %d', $tier );
	}

	public static function get_tier_discount( $tier ) {
		$tier = absint( $tier );
		if ( $tier < 1 || $tier > 10 ) {
			return 0;
		}
		return (float) get_option( 'prolific_dealer_tier_' . $tier, 0 );
	}
}
