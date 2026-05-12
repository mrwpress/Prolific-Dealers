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

define( 'PROLIFIC_DEALERS_VERSION', '1.0' );
define( 'PROLIFIC_DEALERS_PATH', plugin_dir_path( __FILE__ ) );
define( 'PROLIFIC_DEALERS_URL', plugin_dir_url( __FILE__ ) );

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

function prolific_dealers_wc_missing_notice() {
	echo '<div class="notice notice-error"><p><strong>Prolific Dealers</strong> requires WooCommerce to be installed and active.</p></div>';
}
