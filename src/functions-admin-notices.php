<?php
defined( 'ABSPATH' ) || exit;

/**
 * Display admin notices for missing WooCommerce store address details.
 */
function aramex_shipping_aunz_admin_notices() {
    $store_address  = get_option( 'woocommerce_store_address', '' );
    $store_city     = get_option( 'woocommerce_store_city', '' );
    $store_postcode = get_option( 'woocommerce_store_postcode', '' );

    $missing_fields = array();
    if ( empty( $store_address ) ) {
        $missing_fields[] = 'Address Line 1';
    }
    if ( empty( $store_city ) ) {
        $missing_fields[] = 'City';
    }
    if ( empty( $store_postcode ) ) {
        $missing_fields[] = 'Postcode/ZIP';
    }

    if ( ! empty( $missing_fields ) ) {
        $settings_url = admin_url( 'admin.php?page=wc-settings' );
        $missing_fields_list = implode( ', ', $missing_fields );

        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>';
        echo sprintf(
            __( 'Aramex Shipping: The following store address details are missing: %s. Please update them in the <a href="%s">WooCommerce settings</a>.', 'aramex-shipping-aunz' ),
            esc_html( $missing_fields_list ),
            esc_url( $settings_url )
        );
        echo '</p>';
        echo '</div>';
    }
}
add_action( 'admin_notices', 'aramex_shipping_aunz_admin_notices' );

/**
 * Add JavaScript for admin functionality
 */
function aramex_shipping_aunz_admin_scripts($hook) {
    // Only load on WooCommerce orders page
    if (!in_array($hook, array('woocommerce_page_wc-orders', 'post.php'))) {
        return;
    }

    // Check if we're on the order edit page
    global $post;
    if ($hook === 'post.php' && (!$post || $post->post_type !== 'shop_order')) {
        return;
    }

    // Enqueue the order actions script
    wp_enqueue_script(
        'aramex-order-actions',
        ARAMEX_PLUGIN_URL . 'assets/js/order-actions.js',
        array('jquery'),
        '1.0.0',
        true
    );

    // Localize the script with new data
    wp_localize_script(
        'aramex-order-actions',
        'aramexOrderActions',
        array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'create' => wp_create_nonce('create_consignment_nonce'),
                'delete' => wp_create_nonce('delete_consignment_nonce'),
                'print' => wp_create_nonce('print_label_nonce'),
                'track' => wp_create_nonce('track_shipment_nonce')
            )
        )
    );
}
add_action('admin_enqueue_scripts', 'aramex_shipping_aunz_admin_scripts');