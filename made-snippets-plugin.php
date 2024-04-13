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

class MadeSnippetsPlugin
{


    protected static $instance;

    //Aramex API Credentiaals and API Details. 
    private $client_id = 'fw-fl2-AUK0830472-b39278061d5d';
    private $client_secret = 'c921a54e-e32d-41b0-ad51-3edbaad29c21';
    private $scope = 'fw-fl2-api-nz';
    private $tokenURL = 'https://identity.fastway.org/connect/token';
    private $locationServiceURL = 'https://identity.fastway.org/api/location'; // Replace with actual location service URL


    /************ CLASS FUNCTIONS ************/
    // Create Instance. 
    public static function init()
    {
        is_null(self::$instance) && self::$instance = new self;
        return self::$instance;
    }

    // Class Constructor Function.
    private function __construct()
    {
        add_filter('woocommerce_get_sections_shipping', array($this, 'add_aramex_settings_section'));
        add_filter('woocommerce_get_settings_shipping', array($this, 'add_aramex_settings'), 10, 2);
        add_filter('woocommerce_admin_settings_sanitize_option', array($this, 'fuzzfilter_aramex_settings'), 10, 3);
        //add_action('admin_init', 'new_function'); // Corrected
        //add_action('admin_init', fn() => $this->refreshAccessToken()); 
        //add_action('admin_init', fn () => $this->fetchLocationDataKey($from_streetAddress = "10 Bridge Avenue", $from_localit = "Te Atatu", $from_postalCode = "0610", $from_country = "NZ", $to_streetAddress = "145 Symonds Street", $to_locality = "Grafton", $to_postalCode = "1010", $to_country = "NZ"));

        add_action('admin_init', [$this, 'handle_token_refresh']);

    }

    public function handle_token_refresh() {
        if (isset($_GET['refresh_token']) && $_GET['refresh_token'] == 'true' && current_user_can('manage_options')) {
            $this->refreshAccessToken();
            add_action('admin_notices', function() {
                echo '<div class="updated"><p>Access token refreshed successfully.</p></div>';
            });
        }
    }




    //Function which defines two locations, makes an API Call, then handles the returned error for failure and success. 
    public static function fetchLocationDataKey($from_streetAddress, $from_locality, $from_postalCode, $from_country, $to_streetAddress, $to_locality, $to_postalCode, $to_country)
    {
        $aramexToken = get_option('aramex_token');
        $aramexTokenUpdate = 'Bearer ' . $aramexToken;

        $address = retrieveWPOrderShippingAddress(105);
        
        //Check Location Data Being Returned. 
        //var_dump($address); 
        if ($address) {
            // Define the data array
            $locationRequest = [
                "conTypeId" => 1,
                "from" => [
                    "streetAddress" => $address['from_streetAddress'],
                    "locality" => $address['from_locality'],
                    "postalCode" => $address['from_postalCode'],
                    "country" => $address['from_country']
                ],
                "to" => [
                    "streetAddress" => $to_streetAddress,
                    "locality" => $to_locality,
                    "postalCode" => $to_postalCode,
                    "country" => $to_country
                ]
            ];
        } else {
            // Define the data array
            $locationRequest = [
                "conTypeId" => 1,
                "from" => [
                    "streetAddress" => $from_streetAddress,
                    "locality" => $from_locality,
                    "postalCode" => $from_postalCode,
                    "country" => $from_country
                ],
                "to" => [
                    "streetAddress" => $to_streetAddress,
                    "locality" => $to_locality,
                    "postalCode" => $to_postalCode,
                    "country" => $to_country
                ]
            ];
        }


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
                'Authorization' =>  $aramexTokenUpdate
            ],
            'method'      => 'POST',
            'data_format' => 'body',
        ];

        // Make the API call
        $response = wp_remote_post('https://api.myfastway.co.nz/api/location', $args);


        // Check for error in the response and handle it
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo "Something went wrong: $error_message";
        } else {
            // Decode the response body
            $responseBody = wp_remote_retrieve_body($response);

            $data = json_decode($responseBody, true); // Decode into an associative array

            // Output the entire response data
            echo '<pre>';
            print_r($responseBody); // Use var_dump($data) if you prefer
            echo '</pre>';


            // Extract the locationDetailsKey
            if (isset($data['data']['locationDetails']['locationDetailsKey'])) {
                $locationDetailsKey = $data['data']['locationDetails']['locationDetailsKey'];
                echo "Location Details Key: " . $locationDetailsKey;
            } else {
                echo "Location Details Key not found in the response.";
            }
        }
        //$order_id = 105; 
        //$order = wc_get_order($order_id);
        //$order->update_meta_data('_employee_code', '111');
        //$order->save();



        return $response;
    }

    public function fuzzfilter_aramex_settings($value, $option, $raw_value)
    {
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

    public function add_aramex_settings_section($sections)
    {
        $sections['aramex'] = __('Aramex', 'text-domain');
        return $sections;
    }

    public function add_aramex_settings($settings, $current_section)
    {
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
                    'type' => 'sectionend',
                    'id'   => 'aramex_api_settings_end'
                ),
                array(
                'title' => __('Refresh Access Token', 'text-domain'),
                'type'  => 'title',
                'desc'  => __('Use this button to manually refresh the access token.', 'text-domain'),
                'id'    => 'aramex_refresh_token'
            ),
            array(
                'title' => '',
                'desc'  => '<a href="' . esc_url( admin_url('admin.php?page=wc-settings&tab=shipping&section=aramex&refresh_token=true') ) . '" class="button-secondary">Refresh Token</a>',
                'type'  => 'title',
                'id'    => 'refresh_token_button'
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
                'id'   => 'aramex_refresh_token_end'
            ),
            );
            return array_merge($settings, $aramex_settings);
        }
    }


    private function refreshAccessToken()
    {
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

function new_function()
{


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

    updateExistingWCOrderMeta($order_id = 56, $carrier_id = '1111111111', $tracking_number_id = '9999999999', $shipping_label_url = 'google.com');

    $testing_Retreive = retrieveWPOrderShippingAddress(61); 


    echo "‹pre>";
    //print_r($post_create);
    print_r($newVariable);
}

/******************************** */

//function requestShippingCoSignmentfromAramex( $address1 , $address2, $weight, $dimensions ){

//    return $carrier_id, $tracking_number_id, $shipping_label_url; 
//}

function mainPluginFeatures($carrier_id, $tracking_number_id, $shipping_label_url)
{


    //addNewShippingCoSignment(); 
    return updateOrderPageShippingMeta($carrier_id, $tracking_number_id, $shipping_label_url);
    //addPurolatorPluginTabel(); 


}

function updateOrderPageShippingMeta($carrier_id, $tracking_number_id, $shipping_label_url)
{

    $TempVariable = 'TestingupdateOrderPageShippingMeta' . $carrier_id . $tracking_number_id .  $shipping_label_url;
    return $TempVariable;
}


// Add Custom Meta Box On HPOS Enabled Orders Page
use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

add_action('add_meta_boxes', function () {
    // Determine the correct screen ID based on whether custom orders table usage is enabled
    $screen_id = wc_get_container()->get(
        CustomOrdersTableController::class
    )->custom_orders_table_usage_is_enabled()
        ? wc_get_page_screen_id('shop-order')
        : 'shop_order';

    // Add the meta box with the determined screen ID
    add_meta_box(
        'aramex_shipping_metabox',
        'Aramex Shipping Details',
        'aramex_shipping_metabox_callback',
        $screen_id
    );
});

function aramex_shipping_metabox_callback($post_or_order_object)
{
    // Determine whether the passed object is a WP_Post or an order object
    $order = ($post_or_order_object instanceof WP_Post)
        ? wc_get_order($post_or_order_object->ID)
        : $post_or_order_object;

    // If no order is found, return early
    if (!$order) {
        return;
    }

    // Format and display the values as required
    $shipping_url = $order->get_meta('_made_aramex_shipping_label_url');
    $carrier_id = $order->get_meta('_made_aramex_carrier_id');
    $tracking_number = $order->get_meta('_made_aramex_tracking_number_id');

    // Check if shipping URL is present and display as a clickable link
    if (!empty($shipping_url)) {
        echo "Shipping Label URL: <a href='" . esc_url($shipping_url) . "' target='_blank'>Click here to view the shipping label</a><br>";
    } else {
        echo "Shipping Label URL: Not available<br>";
    }

    // Display Carrier ID
    if (!empty($carrier_id)) {
        echo "Carrier ID: " . esc_html($carrier_id) . "<br>";
    } else {
        echo "Carrier ID: Not available<br>";
    }

    // Display Tracking Number
    if (!empty($tracking_number)) {
        echo "Tracking Number: " . esc_html($tracking_number) . "<br>";
    } else {
        echo "Tracking Number: Not available<br>";
    }

    // Add a button to fetch location data
    //echo '<form action="" method="post" id="get-estimate">';
    //echo '<input type="hidden" name="action" value="test_function">';
    //echo '<input type="hidden" name="fetch_location_data" value="1">';
    //echo '<button type="submit" class="button button-primary">Get Estimate Original</button>';
    echo '</form>';

    echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
    echo '<input type="hidden" name="action" value="wpse_79898">';
    //echo '<input type="text" name="test" value="">';
    echo submit_button('Get Estimate Updated');
    echo '</form>';
    
}

add_action('admin_post_wpse_79898', 'wpse_79898_test');

function wpse_79898_test() {
    TestFunction();
    

    // Redirect back to the originating page
    $redirect_url = wp_get_referer() ? wp_get_referer() : home_url();
    wp_safe_redirect($redirect_url);
    exit; // Make sure to terminate the script
}

add_action('wp_ajax_test_function', 'TestFunction'); // If logged in
add_action('wp_ajax_nopriv_test_function', 'TestFunction'); // If not logged in

function TestFunction() {
    $order_id = 105;
    $order = wc_get_order($order_id);
    if ($order) {
        $order->update_meta_data('_employee_code', 'Testing Function Firing 123456');
        $order->save();
        // Echo a success message if needed
        // echo "Success123"; - Consider if you want this to be part of the AJAX response
    } else {
        // Echo an error message if needed
        // echo "Order Not Found"; - Consider if you want this to be part of the AJAX response
    }
    // Use wp_die() for AJAX handlers to stop further execution and return a proper response
    if (defined('DOING_AJAX') && DOING_AJAX) { 
        wp_die(); // Important for AJAX to stop further execution and return proper response
    }
}


function enqueue_custom_scripts() {
    wp_enqueue_script('custom-js', '../path_to_your_script.js', array('jquery'), null, true);
    wp_localize_script('custom-js', 'ajaxurl', admin_url('admin-ajax.php'));
}
add_action('wp_enqueue_scripts', 'enqueue_custom_scripts');


function TestFunctionold(){
    $order_id = 105; 
    $order = wc_get_order($order_id);
    $order->update_meta_data('_employee_code', 'ABC123');
    $order->save();
    echo "This is a Test Function"; 
}


//This Function Is Use to Update the Order_id
function updateExistingWCOrder($order_id)
{
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



//This Function Is Use to Update the Meta Data for the Aramex Plugin. 
/* Inputs : Order ID, Carrier ID, Tracking Number and Shipping Label URL*/
function updateExistingWCOrderMeta($order_id, $carrier_id, $tracking_number_id, $shipping_label_url)
{
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

/* Function to retrieve WP Order Shipping Address */
function retrieveWPOrderShippingAddress($orderId)
{
    // Load the order
    $order = wc_get_order($orderId);
    if (!$order) {
        return false; // Order not found
    }

    // Extract shipping address (assuming 'from' is the shipping address)
    $from_streetAddress = "10 Bridge Avenue";
    $from_locality = "Te Atatu South";
    $from_postalCode = "0610";
    $from_country = "NZ";

    // Extract billing address (assuming 'to' is the billing address for this example)
    $to_streetAddress = $order->get_billing_address_1();
    $to_locality = $order->get_shipping_city();
    $to_postalCode = $order->get_shipping_postcode();
    $to_country = $order->get_shipping_country();

    return [
        'from_streetAddress' => $from_streetAddress,
        'from_locality' => $from_locality,
        'from_postalCode' => $from_postalCode,
        'from_country' => $from_country,
        'to_streetAddress' => $to_streetAddress,
        'to_locality' => $to_locality,
        'to_postalCode' => $to_postalCode,
        'to_country' => $to_country,
    ];
}
