<?php
/**
 * AtomicPay for WooCommerce - Uninstall Function
 *
 * @author 		AtomicPay
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit();
}

global $wpdb;

// Delete from database
$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'woocommerce_atomicpay%';");
