<?php
/**
 * Plugin Name: Paid Memberships Pro - Blockonomics Bitcoin Gateway
 * Plugin URI:  https://github.com/BankkRoll/pmpro-blockonomics
 * Description: Accept Bitcoin payments on Paid Memberships Pro using Blockonomics direct-to-wallet settlement.
 * Version:     1.0.0
 * Author:      BankkRoll
 * Author URI:  https://github.com/BankkRoll
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pmpro-blockonomics
 * Domain Path: /languages
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PMPRO_BLOCKONOMICS_VERSION', '1.0.0' );
define( 'PMPRO_BLOCKONOMICS_DIR', plugin_dir_path( __FILE__ ) );
define( 'PMPRO_BLOCKONOMICS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register the gateway once PMPro is loaded.
 */
function pmpro_blockonomics_register_gateway() {
	if ( ! defined( 'PMPRO_VERSION' ) ) {
		return;
	}

	require_once PMPRO_BLOCKONOMICS_DIR . 'classes/class.pmprogateway_blockonomics.php';

	MemberOrder::$gateway_class_map['blockonomics'] = 'PMProGateway_blockonomics';

	// Back-compat: PMPro < 2.12 used a filter instead of the class map.
	add_filter( 'pmpro_gateways', 'pmpro_blockonomics_add_gateway' );
}
add_action( 'plugins_loaded', 'pmpro_blockonomics_register_gateway' );

function pmpro_blockonomics_add_gateway( $gateways ) {
	$gateways['blockonomics'] = 'PMProGateway_blockonomics';
	return $gateways;
}

/**
 * Handle the Blockonomics payment callback.
 * URL: /?pmpro-blockonomics=callback
 */
function pmpro_blockonomics_parse_request( $wp ) {
	if ( empty( $_GET['pmpro-blockonomics'] ) || $_GET['pmpro-blockonomics'] !== 'callback' ) {
		return;
	}

	require_once PMPRO_BLOCKONOMICS_DIR . 'classes/class.pmprogateway_blockonomics.php';
	PMProGateway_blockonomics::handle_callback();
	exit;
}
add_action( 'parse_request', 'pmpro_blockonomics_parse_request' );

/**
 * Add query var so WordPress doesn't strip it.
 */
function pmpro_blockonomics_query_vars( $vars ) {
	$vars[] = 'pmpro-blockonomics';
	return $vars;
}
add_filter( 'query_vars', 'pmpro_blockonomics_query_vars' );

/**
 * Enqueue checkout assets.
 */
function pmpro_blockonomics_enqueue_scripts() {
	if ( ! function_exists( 'pmpro_is_checkout' ) || ! pmpro_is_checkout() ) {
		return;
	}

	if ( ! isset( $_GET['pmpro-blockonomics'] ) ) {
		return;
	}

	wp_enqueue_style(
		'pmpro-blockonomics',
		PMPRO_BLOCKONOMICS_URL . 'css/checkout.css',
		[],
		PMPRO_BLOCKONOMICS_VERSION
	);
}
add_action( 'wp_enqueue_scripts', 'pmpro_blockonomics_enqueue_scripts' );
