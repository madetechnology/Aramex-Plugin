<?php
/**
 * Plugin Name: Made Snippets Plugin
 * Description: Demonstrates adding custom settings under WooCommerce > Settings > Shipping for Aramex API interactions, including API calls and displaying responses. Also includes consignment operations with the Fastway API.
 * Author: Timothy Lopez
 * Version: 1.0
 */



if (!defined('ABSPATH')) exit; // Exit if accessed directly

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('plugins_loaded', array('MadeSnippetsPlugin', 'init'));
}


/**MADE Aramex API Handler - Plugin Class */

class MadeSnippetsPlugin {

    
    protected static $instance;

    //Aramex API Credentiaals and API Details. 
    private $client_id = 'fw-fl2-AUK0830472-b39278061d5d';
    private $client_secret = 'c921a54e-e32d-41b0-ad51-3edbaad29c21';
    private $scope = 'fw-fl2-api-nz';
    private $tokenURL = 'https://identity.fastway.org/connect/token';
    private $locationServiceURL = 'https://identity.fastway.org/api/location'; // Replace with actual location service URL


    /************ CLASS FUNCTIONS ************/     
    // Create Instance. 
    public static function init() {
        is_null(self::$instance) && self::$instance = new self;
        return self::$instance;
    }

    // Class Constructor Function.
    private function __construct() {
        add_filter('woocommerce_get_sections_shipping', array($this, 'add_aramex_settings_section'));
        add_filter('woocommerce_get_settings_shipping', array($this, 'add_aramex_settings'), 10, 2);
        add_filter('woocommerce_admin_settings_sanitize_option', array($this, 'fuzzfilter_aramex_settings'), 10, 3);
        add_action('admin_init', 'new_function'); // Corrected
        add_action('admin_init', fn() => $this->getAccessToken()); 

    }


    //Function which defines two locations, makes an API Call, then handles the returned error for failure and success. 
    function example_call_location_service_with_token() {
        // Define the data array
        $locationRequest = [
            "conTypeId" => 1,
            "from" => [
                "streetAddress" => "1 Tennis Lane, Parnell",
                "locality" => "Parnell",
                "postalCode" => "1010",
                "country" => "New Zealand"
            ],
            "to" => [
                "streetAddress" => "10 Bridge Avenue, Te Atatu South",
                "locality" => "Auckland",
                "postalCode" => "0610",
                "country" => "New Zealand"
            ]
        ];
    
        // Encode the array into JSON
        $jsonPayload = json_encode($locationRequest);
    
        // Setup the request arguments including the payload and the authorization token
        $args = [
            'body'        => $jsonPayload,
            'timeout'     => '5',
            'redirection' => '5',
            'httpversion' => '1.0',
            'blocking'    => true,
            'headers'     => [
                'Content-Type' => 'application/json',
                'Authorization' => 'eyJhbGciOiJSUzI1NiIsImtpZCI6IkZEODZERDU4NkY5OTg1NERDMzIyRTRBNzY0QjAxMUFDMjkzRUEwMEEiLCJ0eXAiOiJhdCtqd3QiLCJ4NXQiOiJfWWJkV0ctWmhVM0RJdVNuWkxBUnJDay1vQW8ifQ.eyJuYmYiOjE3MTIzMDU2NTIsImV4cCI6MTcxMjMwOTI1MiwiaXNzIjoiaHR0cHM6Ly9pZGVudGl0eS5mYXN0d2F5Lm9yZyIsImF1ZCI6ImZ3LWZsMi1hcGktbnoiLCJjbGllbnRfaWQiOiJmdy1mbDItQVVLMDgzMDQ3Mi1iMzkyNzgwNjFkNWQiLCJjb3VudHJ5aWQiOiI2IiwicGFyZW50aWQiOiIwIiwiY3VzdG9tZXJpZCI6IjEzNzQ4MSIsInNjb3BlIjpbImZ3LWZsMi1hcGktbnoiXX0.mDcpiH8xzkqqOUlZ9pO0H9QNMVf9r2IMYer7fIznuuCGQ4iPN3ZPZ3kAb9Nr1sA2cbijhOVYOWJbhd4dTlp3ReNU7c9OaVyT52rrykxbn61McS-Jbbhopvz3PaWlJNKw5GI1UZQT2zxlBauvUKc2I6PIJE9He61YOdKfFz3vLm-7Oe3_h7ZHf35-YMgmVpJxkTDzBcnSIotSzKmx5C3IivRy6kNYWTzjaI8rkSbYW-xXtngIhYOz30BXvhTNlKtUF4XX0o0pckwQJ0HvK9yEc6kLAL9D9ySzUYPevgLUQJBo03frTGhAxXVv8K9eE0zHq4Gznz4lLE2QJkC4sW4RSgX8Pe7MZn4uYBYW-YH_6fwoeW828iwzYhbhbSQxJr7X3Bsy9J8zJYK7OrtOIV5Hk5yOCSiQD09GXNGN17QeXiWHnpX0s61012noAMf5SmcgMEycnZG7zlswmOUIrOu9xADh4P-L5z5gAEzUjtvnGUdjrCV3vOhUfKoNwksIOOvBudh8tt_0EDAN0r4K9Rnq1KToa6LssQ2t6jy8EMJdLIbL80hlnQnRAGqUaSvWVhvWP_4HiOZNzkHbKaGX9R0M2jsgi29Ez5z2fW4z21upk1_wMUej4Zt6uykT5mAQCoI16Rp1Mx5k77WOCSPWHuN8nT6UstINLFKeOmubhQd4Ptk'
            ],
            'method'      => 'POST',
            'data_format' => 'body',
        ];
    
        // Make the API call
        $response = wp_remote_post('https://identity.fastway.org/api/location', $args);

    
        // Check for error in the response and handle it
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";
        } else {
            // Assuming you want to save the response body for later use
            $responseBody = wp_remote_retrieve_body($response);
            
            // Save or process the response body as needed
            echo '<pre>' . print_r($responseBody, true) . '</pre>';
        }
        return $response; 
    }
  
    public function fuzzfilter_aramex_settings($value, $option, $raw_value) {
        // Validation for the Client ID | 30 Characters and Starts with 'fw-' 
        if ($option['id'] === 'aramex_client_id') {
            if (strlen($raw_value) !== 30 || strpos($raw_value, 'fw-') !== 0) {
                WC_Admin_Settings::add_error(__('Your Client ID is Wrong: Client ID must be 30 characters long and start with "fw-".', 'text-domain'));
                return get_option($option['id']); // Prevent saving the new value if validation fails
            }
        }
    
        // Validation for the Client Secret | 36 Characters  
        if ($option['id'] === 'aramex_client_secret') {
            if (strlen($raw_value) !== 36) {
                WC_Admin_Settings::add_error(__('Your Client Secret is Wrong: Client Secret must be exactly 36 characters long.', 'text-domain'));
                return get_option($option['id']); // Prevent saving the new value if validation fails
            }
        }
    
        return $value; // Return the new value if all validations pass
    }
    
    /************* ADD ARAMEX PLUGIN SETTINGS *************/

    public function add_aramex_settings_section($sections) {
        $sections['aramex'] = __('Aramex', 'text-domain');
        return $sections;
    }

    public function add_aramex_settings($settings, $current_section) {
        if ($current_section == 'aramex') {
            $aramex_settings = array(
                array(
                    'title' => __('Aramex API Settings', 'text-domain'),
                    'type'  => 'title',
                    'desc'  => __('Configure settings for interacting with the Aramex API.', 'text-domain'),
                    'id'    => 'aramex_api_settings'
                ),
                array(
                    'title'    => __('Client ID', 'text-domain'),
                    'desc'     => __('Enter your Aramex Client ID.', 'text-domain'),
                    'id'       => 'aramex_client_id',
                    'type'     => 'text',
                    'default'  => '',
                    'desc_tip' => true,
                ),
                array(
                    'title'    => __('Client Secret', 'text-domain'),
                    'desc'     => __('Enter your Aramex Client Secret.', 'text-domain'),
                    'id'       => 'aramex_client_secret',
                    'type'     => 'text',
                    'default'  => '',
                    'desc_tip' => true,
                ),
                array(
                    'title'    => __('Country', 'text-domain'),
                    'desc'     => __('Select your country.', 'text-domain'),
                    'id'       => 'aramex_country',
                    'type'     => 'select',
                    'default'  => 'NZ',
                    'options'  => array(
                        'NZ'        => __('New Zealand', 'text-domain'),
                        'Australia' => __('Australia', 'text-domain'),
                    ),
                    'desc_tip' => true,
                ),
                array(
                    'title'    => __('Aramex Token', 'text-domain'),
                    'desc'     => __('This is the token received after successful authentication.', 'text-domain'),
                    'id'       => 'aramex_token',
                    'type'     => 'text',
                    'default'  => get_option('aramex_token', 'Click "Test Authentication" to generate a token'),
                    'custom_attributes' => array('readonly' => 'readonly'),
                    'desc_tip' => true,
                ),
                

                array(
                    'type' => 'sectionend',
                    'id'   => 'aramex_api_settings_end'
                ),
            );
            return array_merge($settings, $aramex_settings);
        }    
    }

    
    private function getAccessToken() {
        //Prepare Variables for API Call
        $client_id = get_option('aramex_client_id');
        $client_secret = get_option('aramex_client_secret');
        $country = get_option('aramex_country');
    
        // Update the scope based on the selected country
        $scope = 'fw-fl2-api-nz'; // Default scope
        if ($country === 'Australia') {
            $scope = 'fw-fl2-api-au';
        }
    
        $data = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'scope'         => $scope,
        ]);
    
        $ch = curl_init($this->tokenURL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $response = curl_exec($ch);
    
        // Debugging: Display request and response on screen
        echo '<pre>';
        echo 'Request URL: ' . $this->tokenURL . PHP_EOL;
        echo 'Request Data: ' . print_r($data, true) . PHP_EOL;
        echo 'Response: ' . print_r($response, true) . PHP_EOL;
        echo '</pre>';
    
        curl_close($ch);
    
        if (!$response) {
            echo 'Failed to get response';
            return false;
        }
    
        $responseArray = json_decode($response, true);
        if (isset($responseArray['access_token'])) {
            echo 'Access Token: ' . $responseArray['access_token']; 
            update_option('aramex_token', $responseArray['access_token']);

            

            return $responseArray['access_token'];

        } else {
            echo 'Access token not found in response';
            return false;
        }
    }


    
}

function new_function() {

    $url = "https://api.myfastway.com.au/api/location";
    $location_args = [
        "conTypeId" => 1,
        "from" => [
            "streetAddress" => "1 Tennis Lane, Parnell",
            "locality" => "Parnell",
            "postalCode" => "1010",
            "country" => "New Zealand"
        ],
        "to" => [
            "streetAddress" => "10 Bridge Avenue, Te Atatu South",
            "locality" => "Auckland",
            "postalCode" => "0610",
            "country" => "New Zealand"
        ]
    ];

    // Encode the array into JSON
    $jsonPayload = json_encode($location_args);

    $args = [
        'body'        => $jsonPayload,
        'timeout'     => '5',
        'redirection' => '5',
        'httpversion' => '1.0',
        'blocking'    => true,
        'headers'     => [
            'Content-Type' => 'application/json',
            'Authorization' => 'bearer ' . 'eyJhbGciOiJSUzI1NiIsImtpZCI6IkZEODZERDU4NkY5OTg1NERDMzIyRTRBNzY0QjAxMUFDMjkzRUEwMEEiLCJ0eXAiOiJhdCtqd3QiLCJ4NXQiOiJfWWJkV0ctWmhVM0RJdVNuWkxBUnJDay1vQW8ifQ.eyJuYmYiOjE3MTIzNjU2NDYsImV4cCI6MTcxMjM2OTI0NiwiaXNzIjoiaHR0cHM6Ly9pZGVudGl0eS5mYXN0d2F5Lm9yZyIsImF1ZCI6ImZ3LWZsMi1hcGktbnoiLCJjbGllbnRfaWQiOiJmdy1mbDItQVVLMDgzMDQ3Mi1iMzkyNzgwNjFkNWQiLCJjb3VudHJ5aWQiOiI2IiwicGFyZW50aWQiOiIwIiwiY3VzdG9tZXJpZCI6IjEzNzQ4MSIsInNjb3BlIjpbImZ3LWZsMi1hcGktbnoiXX0.ksgGSiQ8cbYQlIPqSdOMeXCQWXtjCF2uwRA7Ddl5cBrvkKj0rDSqmeTNYhDZJH0_UIG4R2UCFdFzFIZVxBV8CQm0B23BysiGLBICpCZwIvW4gnMzivAFcA--a56DIjnGtUxAV88PkZq_EzPHkP0USRX9tKMeO0CVYIisKi3CWvzMpkG5z8098XQSnXtNlmjbUhShVGEPh1gkDoFJjALfM5wCYo1W9AWmWjF1PwGVXypAF7T4ZglBfZtkAXUvxqcqjJ3Sc0STrHQ_eoyagGiHqtgjsJ_512TV24q6xEWRryQDi97_4jHT_CN3KO3eB54fddCrX8NKJ8ZzSehR6ri5ROnk6iL6KqVtGD4O246z72iA1OBQvEdtWpDVVeHG1dRhZuehuiDRVf5W3ZvQRH_Qh15b3UaQie1LFKMjhe74_HXjD2S9pZAZKwslwPA-5tMDdY_SfyeAcj0PwVGSof3zskljww_orSjbPg-R1J62UqaxBWvXQ2e0K38j_3OBe1WyEpXg0n-VY_s2QfEvUlkyzm3EWmEOwoFDO_gdtj4iMcp7BxV5r-zlG3r29bSfy8IF7KwpIIMNJ2XyfbH0f7a8MZdhX_EC3LpIv801ZHeSJniTd6niyWfkE84Y2vT3OSvjyGbtRW22px83sMJDrK91OPCB7g9BREMA1lVOKD8G9zw'
        ],
        'method'      => 'POST',
        'data_format' => 'body',
    ];


    $post_create = wp_remote_post($url, $args);
    // Test calling the updateOrderPageShippingMeta. 
    $newVariable = mainPluginFeatures("Test", "Test", "Test"); 
    //createNewWCOrder();
    updateExistingWCOrder(58);
    updateExistingWCOrderMeta($order_id = 58 , $carrier_id = 'Example Carrier ID', $tracking_number_id = 'Example Tracker ID', $shipping_label_url = 'Example Shipping URL');


    echo "‹pre>";
    //print_r($post_create);
    print_r($newVariable);

    

}


/******************************** */
// Add custom meta box to WooCommerce orders page
add_action( 'add_meta_boxes', 'custom_order_meta_box' );

/**
 * Add custom meta box.
 *
 * @return void
 */
function custom_order_meta_box() {
 add_meta_box( 'custom-order-meta-box',    __( 'Aramex Meta Box', 'woocommerce' ), 'add_custom_other_field_content', 'shop_order', 'side',  'core');
}

/**
 * Callback function for custom meta box.
 *
 * @param object $post Post object.
 *
 * @return void
 */
function add_custom_other_field_content( $post ) {
    // Get the saved value
    $custom_value = get_post_meta( $post->ID, '_custom_value', true );


    // Output the input field
    echo '<p><label for="custom-value">' ."Penis" . '</label> ';
    echo '<input type="text" id="custom-value" name="custom_value" value="' . esc_attr( $custom_value ) . '" /></p>';
}


/******************************** */

//function requestShippingCoSignmentfromAramex( $address1 , $address2, $weight, $dimensions ){

//    return $carrier_id, $tracking_number_id, $shipping_label_url; 
//}

function mainPluginFeatures( $carrier_id, $tracking_number_id, $shipping_label_url){

    //addNewShippingCoSignment(); 
    return updateOrderPageShippingMeta( $carrier_id, $tracking_number_id, $shipping_label_url ); 
    //addPurolatorPluginTabel(); 

    
}

function updateOrderPageShippingMeta( $carrier_id, $tracking_number_id, $shipping_label_url ){
    
    $TempVariable = 'TestingupdateOrderPageShippingMeta' . $carrier_id . $tracking_number_id .  $shipping_label_url; 
    return $TempVariable; 
}


// Add Custom Meta Box On HPOS Enabled Orders Page
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

add_action( 'add_meta_boxes', function() {
    // Determine the correct screen ID based on whether custom orders table usage is enabled
    $screen_id = wc_get_container()->get(
        CustomOrdersTableController::class
    )->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id( 'shop-order' )
        : 'shop_order';

    // Add the meta box with the determined screen ID
    add_meta_box(
        'aramex_shipping_metabox',
        'Aramex Shipping Details',
        'aramex_shipping_metabox_callback',
        $screen_id
    );
});

function aramex_shipping_metabox_callback( $post_or_order_object ) {
    // Determine whether the passed object is a WP_Post or an order object
    $order = ( $post_or_order_object instanceof WP_Post )
        ? wc_get_order( $post_or_order_object->ID )
        : $post_or_order_object;

    // If no order is found, return early
    if ( ! $order ) {
        return;
    }

    // Display the value of the custom field
    echo "This is the new code:" . $order->get_meta( '_employee_code' ) . "\n";
    echo "Carrier ID :" . $order->get_meta( '_made_aramex_carrier_id'). "\n";
    echo "Shipping Label URL" . $order->get_meta( '_made_aramex_tracking_number_id' ). "\n";
}



//This Function Is Use to Update the Order_id
function updateExistingWCOrder( $order_id ) {
    // Attempt to fetch the existing order with ID 61
    
    $order = wc_get_order($order_id);
    
    // Check if the order exists
    if (!$order) {
        // Optionally, you can log this error or handle it as needed
        error_log("Order with ID $order_id does not exist.");
        return false;
    }

    // Add products to the order
    $order->add_product(wc_get_product(136), 2); // Example: Add 2 quantities of product with ID 136
    $order->add_product(wc_get_product(70)); // Example: Add 1 quantity of product with ID 70

    // Add shipping details
    $shipping = new WC_Order_Item_Shipping();
    $shipping->set_method_title('Free Shipping'); // Name of the shipping method
    $shipping->set_method_id('free_shipping:1'); // ID of the shipping method, adjust as needed
    $shipping->set_total(0); // Set shipping cost, if applicable
    $order->add_item($shipping);

    // Set billing and shipping addresses
    $address = array(
        'first_name' => 'Testing ',
        'last_name'  => 'User',
        'company'    => 'rudrastyh.com',
        'email'      => 'no-reply@rudrastyh.com',
        'phone'      => '+995-123-4567',
        'address_1'  => '29 Kote Marjanishvili St',
        'address_2'  => '', // Optional, for additional address information
        'city'       => 'Tbilisi',
        'state'      => '', // Optional, depending on country
        'postcode'   => '0108',
        'country'    => 'GE'
    );
    $order->set_address($address, 'billing');
    $order->set_address($address, 'shipping');

    // Set the payment method
    $order->set_payment_method('stripe'); // Ensure 'stripe' is a valid and enabled WC payment gateway ID
    $order->set_payment_method_title('Credit/Debit Card');

    // Update the order status to 'completed'
    // Be cautious with changing order statuses programmatically
    $order->set_status('wc-completed', 'Order updated programmatically.');

    $order->update_meta_data('_employee_code', '1234321');


    // Calculate totals and save the order
    $order->calculate_totals();
    $order->save();

    // Return the updated order object
    return $order;
}



//This Function Is Use to Update the Order_id
function updateExistingWCOrderMeta( $order_id , $carrier_id, $tracking_number_id, $shipping_label_url ) {
    // Attempt to fetch the existing order with ID 61
    
    $order = wc_get_order($order_id);
    
    // Check if the order exists
    if (!$order) {
        // Optionally, you can log this error or handle it as needed
        error_log("Order with ID $order_id does not exist.");
        return false;
    }
    $order->update_meta_data('_made_aramex_carrier_id', $carrier_id);
    $order->update_meta_data('_made_aramex_tracking_number_id', $tracking_number_id);
    $order->update_meta_data('_made_aramex_shipping_label_url', $shipping_label_url);

    $order->save();

    // Return the updated order object
    return $order;
}