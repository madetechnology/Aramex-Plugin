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

class MadeSnippetsPlugin {
    protected static $instance;
    private $client_id = 'fw-fl2-AUK0830472-b39278061d5d';
    private $client_secret = 'c921a54e-e32d-41b0-ad51-3edbaad29c21';
    private $scope = 'fw-fl2-api-nz';
    private $tokenURL = 'https://identity.fastway.org/connect/token';
    private $locationServiceURL = 'https://identity.fastway.org/api/location'; // Replace with actual location service URL

    public static function init() {
        is_null(self::$instance) && self::$instance = new self;
        return self::$instance;
    }

    private function __construct() {
        add_filter('woocommerce_get_sections_shipping', array($this, 'add_aramex_settings_section'));
        add_filter('woocommerce_get_settings_shipping', array($this, 'add_aramex_settings'), 10, 2);
        add_filter('woocommerce_admin_settings_sanitize_option', array($this, 'validate_aramex_settings'), 10, 3);
        add_action('admin_footer', array($this, 'trigger_get_access_token'));
        add_action('wp_ajax_trigger_get_access_token', array($this, 'ajax_trigger_get_access_token'));
        add_action('admin_init', array($this, 'example_call_location_service_with_token')); // Corrected

        


    }
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
                'Authorization' => 'Bearer eyJhbGciOiJSUzI1NiIsImtpZCI6IkZEODZERDU4NkY5OTg1NERDMzIyRTRBNzY0QjAxMUFDMjkzRUEwMEEiLCJ0eXAiOiJhdCtqd3QiLCJ4NXQiOiJfWWJkV0ctWmhVM0RJdVNuWkxBUnJDay1vQW8ifQ.eyJuYmYiOjE3MTE5MzI5NzgsImV4cCI6MTcxMTkzNjU3OCwiaXNzIjoiaHR0cHM6Ly9pZGVudGl0eS5mYXN0d2F5Lm9yZyIsImF1ZCI6ImZ3LWZsMi1hcGktbnoiLCJjbGllbnRfaWQiOiJmdy1mbDItQVVLMDgzMDQ3Mi1iMzkyNzgwNjFkNWQiLCJjb3VudHJ5aWQiOiI2IiwicGFyZW50aWQiOiIwIiwiY3VzdG9tZXJpZCI6IjEzNzQ4MSIsInNjb3BlIjpbImZ3LWZsMi1hcGktbnoiXX0.KGGXNiqG_7jiCZoe64PFO5iImyGgiqEVZwZZ_wsebGrEuYoeSMjLkJTiu_a8IhipeDQ1bZTa-xubGl5w_W7B35yf7_czbBYg13kdV2iEeph1D0UvGwReiqW7YVsDMpBtleZhPO4McpxeDB0QU8Dqe9jZScUxlP1bHQrAYyrn1ade1ENdf0TrokIygDp3VPxPMhZwGT6yVmYRK7xiGbBD-gBt4o29N_E_JmmAYyVONP_3syBYDOqwX-gkLWeT4E0go2Kcg8n9lkTY-pznAY3nCuDw7ZkQp2zxPNjm0nQuoDlTxl9s_a0gyHHMpYyWSsEEohGfizbL_04NzjTTACoaqeWIGAp-TbELxvKiF_EY7YTDiZKuxW-7Pka8JpMTo26WWaaSK271TbhgZd5EMmi6aq50WGMB1vaDGjY9YOC7lG1C6OLKGI4_o71EZjhFbyVQhn4GsTwzd6CqxEYXxKcBhjxCU4a6YZGdopd6nb7abDTkyZ9ud54YqDH07B-aByOcCAzageriYZ9YdH-zsf4jGmTgFe-9_QzkI4b6yYjqmWMvjBcTFEsNn66R3xmnJa8iYVASt3Np2pLiZtuZ8l5vGBXvkAjapRP0O8wsOqBv4BRLXHA5q9TzAM_To0NAa1WNpi92gx4pigS_ExEn9WBL_2XbwO9RmHr1WddCJw_kj_w'
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
    }

    public function trigger_get_access_token() {
        ?>
        <script>
            jQuery(document).ready(function($) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'trigger_get_access_token'
                    },
                    success: function(response) {
                        console.log('Access token generated:', response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Failed to generate access token. Error:', error);
                    }
                });
            });
        </script>
        <?php
    }

    public function ajax_trigger_get_access_token() {
        $token = $this->getAccessToken();
        if ($token) {
            echo 'Token generated successfully.';
        } else {
            echo 'Failed to generate token.';
        }
        wp_die();
    }
    
    public function validate_aramex_settings($value, $option, $raw_value) {
        // Validation for the Client ID
        if ($option['id'] === 'aramex_client_id') {
            if (strlen($raw_value) !== 30 || strpos($raw_value, 'fw-') !== 0) {
                WC_Admin_Settings::add_error(__('Your Client ID is Wrong: Client ID must be 30 characters long and start with "fw-".', 'text-domain'));
                return get_option($option['id']); // Prevent saving the new value if validation fails
            }
        }
    
        // Validation for the Client Secret
        if ($option['id'] === 'aramex_client_secret') {
            if (strlen($raw_value) !== 36) {
                WC_Admin_Settings::add_error(__('Your Client Secret is Wrong: Client Secret must be exactly 36 characters long.', 'text-domain'));
                return get_option($option['id']); // Prevent saving the new value if validation fails
            }
        }
    
        return $value; // Return the new value if all validations pass
    }
    

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
        return $settings;
    }

    
    private function getAccessToken() {
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
            update_option('aramex_token', $responseArray['access_token']);
            return $responseArray['access_token'];
        } else {
            echo 'Access token not found in response';
            return false;
        }
    }


    
}
