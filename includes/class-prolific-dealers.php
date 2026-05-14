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
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-settings.php';
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-user.php';
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-pricing.php';
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-visibility.php';
		require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers-application.php';

		Prolific_Dealers_Settings::init();
		Prolific_Dealers_User::init();
		Prolific_Dealers_Pricing::init();
		Prolific_Dealers_Visibility::init();
		Prolific_Dealers_Application::init();
	}

	public static function is_dealer() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user  = wp_get_current_user();
		$roles = (array) $user->roles;

		if ( in_array( 'administrator', $roles, true ) ) {
			return true;
		}

		if ( ! in_array( 'dealer', $roles, true ) ) {
			return false;
		}

		$status = get_user_meta( $user->ID, '_prolific_dealer_status', true );
		if ( 'pending' === $status ) {
			return false;
		}

		return true;
	}
}
