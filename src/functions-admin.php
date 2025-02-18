<?php
defined('ABSPATH') || exit;

/**
 * Add Aramex tracking label meta box
 */
function aramex_add_tracking_label_meta_box() {
    // Check if HPOS is enabled
    if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && 
        \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
        // HPOS enabled - add meta box to the new orders page
        add_meta_box(
            'aramex_tracking_label',
            __('Aramex Tracking Label', 'Aramex-Plugin'),
            'aramex_tracking_label_meta_box_content',
            'woocommerce_page_wc-orders',
            'side',
            'default'
        );
    }
    
    // Always add to traditional orders screen as fallback
    add_meta_box(
        'aramex_tracking_label',
        __('Aramex Tracking Label', 'Aramex-Plugin'),
        'aramex_tracking_label_meta_box_content',
        'shop_order',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'aramex_add_tracking_label_meta_box');

/**
 * Meta box content
 */
function aramex_tracking_label_meta_box_content($post_or_order_object) {
    // Get order object and ID based on what we received
    if ($post_or_order_object instanceof WC_Order) {
        $order = $post_or_order_object;
        $order_id = $order->get_id();
    } else {
        $order_id = $post_or_order_object->ID;
        $order = wc_get_order($order_id);
    }

    if (!$order) {
        return;
    }
    
    // Get the current value using WC Order method
    $label_no = $order->get_meta('aramex_label_no', true);
    
    // Add nonce for security
    wp_nonce_field('aramex_save_tracking_label', 'aramex_tracking_label_nonce');
    ?>
    <p>
        <input type="text" 
               id="aramex_label_no" 
               name="aramex_label_no" 
               value="<?php echo esc_attr($label_no); ?>" 
               style="width: 100%;"
               placeholder="<?php esc_attr_e('Enter tracking number', 'Aramex-Plugin'); ?>"
        />
    </p>
    <?php
}

/**
 * Save the meta box data
 */
function aramex_save_tracking_label($post_id) {
    // Check if our nonce is set and verify it
    $nonce = isset($_POST['aramex_tracking_label_nonce']) ? sanitize_text_field(wp_unslash($_POST['aramex_tracking_label_nonce'])) : '';
    if (empty($nonce) || !wp_verify_nonce($nonce, 'aramex_save_tracking_label')) {
        return;
    }

    // If this is an autosave, our form has not been submitted
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check the user's permissions
    if (!current_user_can('edit_shop_orders')) {
        return;
    }

    // Get the order object
    $order = wc_get_order($post_id);
    if (!$order) {
        return;
    }

    // Check if the tracking label field is set and sanitize it
    if (isset($_POST['aramex_label_no'])) {
        $label_no = sanitize_text_field(wp_unslash($_POST['aramex_label_no']));
        
        // Get the old value
        $old_label_no = $order->get_meta('aramex_label_no', true);
        
        // Update the meta field using WC Order method
        $order->update_meta_data('aramex_label_no', $label_no);
        $order->save();
        
        // If the value has changed, add an order note
        if ($label_no !== $old_label_no) {
            /* translators: %s: The new tracking label number */
            $order->add_order_note(
                sprintf(
                    /* translators: %s: The new tracking label number */
                    __('Aramex tracking label updated to: %s', 'Aramex-Plugin'),
                    $label_no
                )
            );
        }
    }
}

// Add save actions for both traditional and HPOS
add_action('save_post', 'aramex_save_tracking_label');
add_action('woocommerce_process_shop_order_meta', 'aramex_save_tracking_label');
add_action('woocommerce_process_shop_order_meta_boxes', 'aramex_save_tracking_label');

/**
 * Register the custom field for WooCommerce orders
 */
function aramex_register_order_meta() {
    register_post_meta('shop_order', 'aramex_label_no', [
        'type' => 'string',
        'description' => 'Aramex Tracking Label Number',
        'single' => true,
        'show_in_rest' => true,
    ]);
}
add_action('init', 'aramex_register_order_meta'); 