<?php

if ( ! class_exists( 'My_Shipping_Method' ) ) {

	class My_Shipping_Method extends WC_Shipping_Method {
		protected $api_key;
		protected $secret;
		protected $origin_country;

		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'aramex_shipping';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Aramex Shipping Method', 'Aramex-Plugin' );
			$this->method_description = __( 'Custom shipping method using Aramex API.', 'Aramex-Plugin' );
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
			);

			$this->api_key        = get_option( 'aramex_shipping_aunz_api_key', '' );
			$this->secret         = get_option( 'aramex_shipping_aunz_api_secret', '' );
			$this->origin_country = get_option( 'aramex_shipping_aunz_origin_country', 'nz' );

			$this->init();
		}

		public function init() {
			$this->init_form_fields();
			$this->init_settings();
			$this->title = $this->get_option( 'title', $this->method_title );

			add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
		}

		public function init_form_fields() {
			$this->form_fields = array(
				'title' => array(
					'title'       => __( 'Method Title', 'Aramex-Plugin' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'Aramex-Plugin' ),
					'default'     => __( 'Aramex Shipping', 'Aramex-Plugin' ),
				),
				'packaging_type' => array(
					'title'       => __( 'Packaging Type', 'Aramex-Plugin' ),
					'type'        => 'select',
					'description' => __( 'Choose how to package items.', 'Aramex-Plugin' ),
					'default'     => 'single_box',
					'options'     => array(
						'single_box'         => __( 'Single Box', 'Aramex-Plugin' ),
						'fixed_size'         => __( 'Fixed Size Boxes', 'Aramex-Plugin' ),
						'product_dimensions' => __( 'Product Dimensions', 'Aramex-Plugin' ),
					),
				),
				'allow_customer_packaging' => array(
					'title'       => __( 'Customer Packaging Choice', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Allow customers to choose packaging type at checkout.', 'Aramex-Plugin' ),
					'default'     => 'no',
				),
				'custom_boxes' => array(
					'title'       => __( 'Custom Box Sizes', 'Aramex-Plugin' ),
					'type'        => 'textarea',
					'description' => __( 'Enter custom box dimensions in JSON format. Example: {"small":{"length":20,"width":20,"height":20,"weight":5}}', 'Aramex-Plugin' ),
					'default'     => '',
					'css'        => 'height: 150px;',
				),
				'package_types' => array(
					'title'       => __( 'Supported Aramex Shipping Package Types', 'Aramex-Plugin' ),
					'type'        => 'title',
					'description' => __( 'Enable or disable specific satchel and box sizes. Disabled sizes will not be offered at checkout.', 'Aramex-Plugin' ),
				),
				'satchel_300gm' => array(
					'title'       => __( 'Satchel 300GM (22.0 x 16.5 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 0.3 kg. Available in: Australia', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'satchel_dl' => array(
					'title'       => __( 'Satchel DL (12.6 x 24.0 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 5 kg. Available in: New Zealand', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'satchel_a5' => array(
					'title'       => __( 'Satchel A5 (19.0 x 26.0 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 5 kg. Available in: Australia, New Zealand', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'satchel_a4' => array(
					'title'       => __( 'Satchel A4 (25.0 x 32.5 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 5 kg. Available in: Australia, New Zealand', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'satchel_a3' => array(
					'title'       => __( 'Satchel A3 (32.5 x 44.0 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 5 kg. Available in: Australia, New Zealand', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'satchel_a2' => array(
					'title'       => __( 'Satchel A2 (45.0 x 61.0 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 5 kg. Available in: Australia, New Zealand', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'box_small' => array(
					'title'       => __( 'Small Box (20 x 20 x 20 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 5 kg', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'box_medium' => array(
					'title'       => __( 'Medium Box (30 x 30 x 30 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 10 kg', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
				'box_large' => array(
					'title'       => __( 'Large Box (40 x 40 x 40 cm)', 'Aramex-Plugin' ),
					'type'        => 'checkbox',
					'description' => __( 'Maximum weight: 20 kg', 'Aramex-Plugin' ),
					'default'     => 'yes',
				),
			);
		}

		public function process_admin_options() {
			parent::process_admin_options();
		}

		public function calculate_shipping( $package = array() ) {
			error_log( 'Calculating shipping for package: ' . print_r( $package, true ) );

			$access_token = $this->get_access_token();
			if ( ! $access_token ) {
				error_log( 'Failed to retrieve access token.' );
				return;
			}

			$location_details_key = $this->get_location_details_key( $access_token, $package );
			if ( ! $location_details_key ) {
				error_log( 'Failed to retrieve locationDetailsKey.' );
				return;
			}

			// Get customer's packaging choice if enabled
			$packaging_type = $this->get_option('packaging_type', 'product_dimensions');
			$allow_customer_choice = $this->get_option('allow_customer_packaging') === 'yes';
			
			if ($allow_customer_choice && isset($_POST['post_data'])) {
				parse_str($_POST['post_data'], $post_data);
				if (!empty($post_data['aramex_packaging_type'])) {
					$packaging_type = sanitize_text_field($post_data['aramex_packaging_type']);
				}
			}

			// Initialize package calculator
			require_once ARAMEX_PLUGIN_DIR . 'src/class-aramex-package-calculator.php';
			$calculator = new Aramex_Package_Calculator(
				$this->origin_country,
				$packaging_type,
				$this
			);

			// Calculate optimal packaging
			if ($allow_customer_choice && 
				$packaging_type === 'single_satchel' && 
				isset($post_data['aramex_satchel_size'])) {
				
				$satchel_size = sanitize_text_field($post_data['aramex_satchel_size']);
				// Check if the selected satchel size is valid
				$available_satchels = $calculator->get_available_satchel_sizes();
				if (isset($available_satchels[$satchel_size])) {
					$packages = array(array(
						'PackageType' => 'S',
						'Quantity' => 1,
						'SatchelSize' => $satchel_size,
					));
				} else {
					error_log('Invalid satchel size selected.');
					return;
				}
			} else {
				$packages = $calculator->calculate_optimal_packaging($package['contents']);
			}

			if (empty($packages)) {
				error_log('No valid packages could be calculated.');
				return;
			}

			// Get shipping rates for each package
			$rates = $this->get_quote_rates($access_token, $location_details_key, $packages);
			if (empty($rates)) {
				error_log('No rates found from the API.');
				return;
			}

			foreach ($rates as $rate_data) {
				$description = $rate_data['description'] ?? 'Unknown Service';
				$total_price = $rate_data['total_price'] ?? 0;

				if (!empty($total_price)) {
					$rate = array(
						'id'    => $this->id . '_' . sanitize_title($description),
						'label' => $description . ' - $' . number_format($total_price, 2),
						'cost'  => $total_price,
					);
					error_log('Adding shipping rate: ' . print_r($rate, true));
					$this->add_rate($rate);
				} else {
					error_log("Skipping rate for {$description} due to invalid total_price.");
				}
			}
		}

		public function get_access_token() {
			return aramex_shipping_aunz_get_access_token( $this->api_key, $this->secret, $this->origin_country );
		}

		private function get_location_details_key( $access_token, $package ) {
			error_log( "Fetching locationDetailsKey with access token: {$access_token}" );

			$destination = $package['destination'];
			$api_base_url = aramex_shipping_aunz_get_api_base_url( $this->origin_country );
			$url = $api_base_url . '/api/location';

			$from_street_address = get_option( 'woocommerce_store_address', '' );
			$from_locality       = get_option( 'woocommerce_store_city', '' );
			$from_postal_code    = get_option( 'woocommerce_store_postcode', '' );
			$from_country        = get_option( 'woocommerce_default_country', '' );

			if ( strpos( $from_country, ':' ) !== false ) {
				$from_country = explode( ':', $from_country )[0];
			}

			$body = array(
				'conTypeId' => 1,
				'from' => array(
					'streetAddress' => $from_street_address,
					'locality'      => $from_locality,
					'postalCode'    => $from_postal_code,
					'country'       => $from_country,
				),
				'to' => array(
					'streetAddress' => $destination['address_1'] ?? '',
					'locality'      => $destination['city'] ?? '',
					'postalCode'    => $destination['postcode'] ?? '',
					'country'       => $destination['country'] ?? '',
				),
			);

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $body ),
					'timeout' => 45,
				)
			);

			if ( is_wp_error( $response ) ) {
				error_log( 'Error fetching locationDetailsKey: ' . $response->get_error_message() );
				return null;
			}

			$response_body = wp_remote_retrieve_body( $response );
			error_log( 'Location API Response: ' . $response_body );

			$data = json_decode( $response_body, true );
			return $data['data']['locationDetails']['locationDetailsKey'] ?? null;
		}

		private function get_quote_rates($access_token, $location_details_key, $packages) {
			error_log("Fetching quote rates with locationDetailsKey: {$location_details_key}");

			$api_base_url = aramex_shipping_aunz_get_api_base_url($this->origin_country);
			$url = $api_base_url . '/api/consignments/quote?api-version=2';

			$body = array(
				'LocationDetailsKey' => $location_details_key,
				'Items' => $packages,
				'Services' => array(),
			);

			$response = wp_remote_post(
				$url,
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode($body),
					'timeout' => 45,
				)
			);

			if (is_wp_error($response)) {
				error_log('Error fetching quote rates: ' . $response->get_error_message());
				return array();
			}

			$response_body = wp_remote_retrieve_body($response);
			error_log('Quote API Response: ' . $response_body);

			$data = json_decode($response_body, true);

			if (!isset($data['data']) || !is_array($data['data'])) {
				error_log('Invalid or missing data in Quote API response.');
				return array();
			}

			$parsed_services = array();
			foreach ($data['data'] as $service) {
				if (isset($service['items']) && is_array($service['items'])) {
					foreach ($service['items'] as $item) {
						if (isset($item['productType']) && $item['productType'] === 'Labels') {
							$parsed_services[] = array(
								'description' => $item['description'] ?? 'Unknown Service',
								'total_price' => $item['total'] ?? 0,
								'details'     => $item,
							);
						}
					}
				}
			}

			error_log('Parsed Services: ' . print_r($parsed_services, true));

			return $parsed_services;
		}
	}

}
