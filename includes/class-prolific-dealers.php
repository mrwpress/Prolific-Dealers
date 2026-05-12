<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prolific_Dealers {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-pricing.php';
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-visibility.php';

		Prolific_Dealers_Pricing::init();
		Prolific_Dealers_Visibility::init();
	}

	public static function is_dealer() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		return in_array( 'dealer', (array) $user->roles, true );
	}
}
