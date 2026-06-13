<?php
/**
 * Plugin Name:       GiftedPay for WooCommerce
 * Plugin URI:        https://pay.gifted.co.ke
 * Description:       Accept M-Pesa payments via GiftedPay STK Push. Works with Till, Paybill, Bank, and B2C apps.
 * Version:           1.0.0
 * Author:            GiftedPay
 * Author URI:        https://pay.gifted.co.ke
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       giftedpay-woocommerce
 * WC requires at least: 6.0
 * WC tested up to:   9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GIFTEDPAY_VERSION', '1.0.0' );
define( 'GIFTEDPAY_PLUGIN_FILE', __FILE__ );
define( 'GIFTEDPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GIFTEDPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GIFTEDPAY_API_BASE', 'https://mpesa.gifted.co.ke/api' );

/**
 * Check WooCommerce is active before loading.
 */
add_action( 'plugins_loaded', 'giftedpay_init_gateway', 0 );

function giftedpay_init_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>GiftedPay for WooCommerce</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return;
	}

	require_once GIFTEDPAY_PLUGIN_DIR . 'includes/class-wc-giftedpay-gateway.php';

	add_filter( 'woocommerce_payment_gateways', 'giftedpay_add_gateway' );
}

function giftedpay_add_gateway( $gateways ) {
	$gateways[] = 'WC_GiftedPay_Gateway';
	return $gateways;
}

/**
 * Declare HPOS compatibility.
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Enqueue checkout & thank-you assets.
 */
add_action( 'wp_enqueue_scripts', 'giftedpay_enqueue_scripts' );

function giftedpay_enqueue_scripts() {
	if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		return;
	}

	wp_enqueue_style(
		'giftedpay-styles',
		GIFTEDPAY_PLUGIN_URL . 'assets/css/giftedpay.css',
		[],
		GIFTEDPAY_VERSION
	);

	if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		wp_enqueue_script(
			'giftedpay-checkout',
			GIFTEDPAY_PLUGIN_URL . 'assets/js/checkout.js',
			[ 'jquery' ],
			GIFTEDPAY_VERSION,
			true
		);
	}

	if ( is_wc_endpoint_url( 'order-received' ) ) {
		wp_enqueue_script(
			'giftedpay-thankyou',
			GIFTEDPAY_PLUGIN_URL . 'assets/js/thankyou.js',
			[ 'jquery' ],
			GIFTEDPAY_VERSION,
			true
		);
		wp_localize_script( 'giftedpay-thankyou', 'giftedpayData', [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'giftedpay_poll' ),
			'apiBase'  => GIFTEDPAY_API_BASE,
		] );
	}
}

/**
 * AJAX: poll transaction status (proxied through WP so API key stays server-side).
 */
add_action( 'wp_ajax_giftedpay_poll_status', 'giftedpay_ajax_poll_status' );
add_action( 'wp_ajax_nopriv_giftedpay_poll_status', 'giftedpay_ajax_poll_status' );

function giftedpay_ajax_poll_status() {
	check_ajax_referer( 'giftedpay_poll', 'nonce' );

	$checkout_id = sanitize_text_field( $_POST['checkout_request_id'] ?? '' );
	if ( ! $checkout_id ) {
		wp_send_json_error( [ 'message' => 'Missing checkout_request_id' ], 400 );
	}

	$gateway  = WC()->payment_gateways()->payment_gateways()['giftedpay'] ?? null;
	$api_key  = $gateway ? $gateway->get_option( 'api_key' ) : '';

	$response = wp_remote_post( GIFTEDPAY_API_BASE . '/payments/verify', [
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		],
		'body'    => json_encode( [ 'checkoutRequestId' => $checkout_id ] ),
		'timeout' => 15,
	] );

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( [ 'message' => $response->get_error_message() ], 502 );
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// If payment completed, mark WC order as paid
	if ( ( $body['status'] ?? '' ) === 'completed' ) {
		$order_id = absint( $_POST['order_id'] ?? 0 );
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order && $order->has_status( [ 'pending', 'on-hold' ] ) ) {
				$receipt = $body['data']['mpesa_receipt_number'] ?? '';
				$order->payment_complete( $receipt );
				$order->add_order_note(
					sprintf( 'GiftedPay: M-Pesa payment confirmed. Receipt: %s | Amount: KES %s',
						$receipt,
						number_format( $body['data']['amount'] ?? 0, 2 )
					)
				);
			}
		}
	}

	wp_send_json_success( $body );
}

/**
 * Plugin action links.
 */
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$settings_link = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=giftedpay' ) . '">Settings</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );
