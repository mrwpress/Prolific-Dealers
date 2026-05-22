<?php
/**
 * Plugin Name: Prolific Dealers
 * Description: Custom plugin for Prolific Audio to manage all things dealers.
 * Version: 1.0
 * Author: Mr. WPress
 * Author URI: https://www.mrwpress.com
 * Text Domain: prolific-dealers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PROLIFIC_DEALERS_VERSION', '1.2' );
define( 'PROLIFIC_DEALERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROLIFIC_DEALERS_URL', plugin_dir_url( __FILE__ ) );
define( 'PROLIFIC_DEALERS_DEFAULT_TIER_LABELS', [
	1  => 'Dealer5',
	2  => 'Dealer10',
	3  => 'Dealer15',
	4  => 'Dealer20',
	5  => 'Dealer25',
	6  => 'Dealer30',
	7  => 'Dealer35',
	8  => 'Dealer40',
	9  => 'CarAudio',
	10 => 'Wholesale',
] );

register_activation_hook( __FILE__, 'prolific_dealers_activate' );
register_deactivation_hook( __FILE__, 'prolific_dealers_deactivate' );
add_action( 'plugins_loaded', 'prolific_dealers_init' );

function prolific_dealers_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'prolific_dealers_wc_missing_notice' );
		deactivate_plugins( plugin_basename( __FILE__ ) );
		return;
	}

	require_once PROLIFIC_DEALERS_PATH . 'includes/class-prolific-dealers.php';
	Prolific_Dealers::instance();
}

function prolific_dealers_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$customer = get_role( 'customer' );
	$caps     = $customer ? $customer->capabilities : [];

	add_role( 'dealer', 'Dealer', $caps );
}

function prolific_dealers_deactivate() {
	remove_role( 'dealer' );
}

function prolific_dealers_wc_missing_notice() {
	echo '<div class="notice notice-error"><p><strong>Prolific Dealers</strong> requires WooCommerce to be installed and active.</p></div>';
}
