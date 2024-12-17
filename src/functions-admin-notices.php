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