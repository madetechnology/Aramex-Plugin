<?php

if ( ! class_exists( 'My_Shipping_Method' ) ) {

	class My_Shipping_Method extends WC_Shipping_Method {
		protected $api_key;
		protected $secret;
		protected $origin_country;

		public function __construct( $instance_id = 0 ) {
			$this->id                 = 'my_shipping_method';
			$this->instance_id        = absint( $instance_id );
			$this->method_title       = __( 'Aramex Shipping Method', 'aramex-shipping-aunz' );
			$this->method_description = __( 'Custom shipping method using Aramex API.', 'aramex-shipping-aunz' );
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

			$rates = $this->get_quote_rates( $access_token, $location_details_key );
			if ( empty( $rates ) ) {
				error_log( 'No rates found from the API.' );
				return;
			}

			foreach ( $rates as $rate_data ) {
				$description = $rate_data['description'] ?? 'Unknown Service';
				$total_price = $rate_data['total_price'] ?? 0;

				if ( ! empty( $total_price ) ) {
					$rate = array(
						'id'    => $this->id . '_' . sanitize_title( $description ),
						'label' => $description . ' - $' . number_format( $total_price, 2 ),
						'cost'  => $total_price,
					);
					error_log( 'Adding shipping rate: ' . print_r( $rate, true ) );
					$this->add_rate( $rate );
				} else {
					error_log( "Skipping rate for {$description} due to invalid total_price." );
				}
			}
		}

		private function get_access_token() {
			return aramex_shipping_aunz_get_access_token( $this->api_key, $this->secret, $this->origin_country );
		}

		private function get_location_details_key( $access_token, $package ) {
			error_log( "Fetching locationDetailsKey with access token: {$access_token}" );

			$destination = $package['destination'];
			$url = 'https://api.myfastway.co.nz/api/location';

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

		private function get_quote_rates( $access_token, $location_details_key ) {
			error_log( "Fetching quote rates with locationDetailsKey: {$location_details_key}" );

			$url = 'https://api.myfastway.co.nz/api/consignments/quote?api-version=2';

			$cart = WC()->cart->get_cart();
			$total_weight = 0;
			$lengths = array();
			$heights = array();
			$max_length = 0;
			$max_width = 0;
			$max_height = 0;

			foreach ( $cart as $cart_item ) {
				$product = $cart_item['data'];

				$total_weight += (float) $product->get_weight() * $cart_item['quantity'];
				$lengths[] = (float) $product->get_length();
				$heights[] = (float) $product->get_height();
				$max_width = max( $max_width, (float) $product->get_width() );
			}

			// Check stackability (90% rule)
			$stackable = true;
			if ( ! empty( $lengths ) ) {
				$first_length = $lengths[0];
				foreach ( $lengths as $length ) {
					if ( abs( $length - $first_length ) > 0.1 * $first_length ) {
						$stackable = false;
						break;
					}
				}
			}

			if ( ! empty( $heights ) ) {
				$first_height = $heights[0];
				foreach ( $heights as $height ) {
					if ( abs( $height - $first_height ) > 0.1 * $first_height ) {
						$stackable = false;
						break;
					}
				}
			}

			if ( $stackable ) {
				$max_length = max( $lengths );
				$max_height = max( $heights );
				error_log( 'Products are stackable. Using max dimensions.' );
			} else {
				$max_length = array_sum( $lengths );
				$max_height = array_sum( $heights );
				error_log( 'Products are not stackable. Using total dimensions.' );
			}

			// Default to 1 if dimensions or weight are not set
			$total_weight = max( $total_weight, 1 );
			$max_length   = max( $max_length, 1 );
			$max_width    = max( $max_width, 1 );

			// Determine smallest satchel size that fits
			$satchel_sizes = array(
				'DL' => array( 'length' => 12.6, 'width' => 24.0, 'weight' => 5 ),
				'A5' => array( 'length' => 19.0, 'width' => 26.0, 'weight' => 5 ),
				'A4' => array( 'length' => 25.0, 'width' => 32.5, 'weight' => 5 ),
				'A3' => array( 'length' => 32.5, 'width' => 44.0, 'weight' => 5 ),
				'A2' => array( 'length' => 45.0, 'width' => 61.0, 'weight' => 5 ),
			);

			$selected_satchel = null;
			foreach ( $satchel_sizes as $size => $dimensions ) {
				if ( $max_length <= $dimensions['length'] &&
					 $max_width <= $dimensions['width'] &&
					 $total_weight <= $dimensions['weight'] ) {
					$selected_satchel = $size;
					break;
				}
			}

			if ( ! $selected_satchel ) {
				error_log( 'Cart exceeds all satchel sizes.' );
				return array();
			}

			error_log( "Selected satchel size: {$selected_satchel}" );

			$body = array(
				'LocationDetailsKey' => $location_details_key,
				'Items' => array(
					array(
						'Quantity'    => 1,
						'Reference'   => '',
						'PackageType' => 'S',
						'SatchelSize' => $selected_satchel,
					),
				),
				'Services' => array(),
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
				error_log( 'Error fetching quote rates: ' . $response->get_error_message() );
				return array();
			}

			$response_body = wp_remote_retrieve_body( $response );
			error_log( 'Quote API Response: ' . $response_body );

			$data = json_decode( $response_body, true );

			if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
				error_log( 'Invalid or missing data in Quote API response.' );
				return array();
			}

			$parsed_services = array();
			foreach ( $data['data'] as $service ) {
				if ( isset( $service['items'] ) && is_array( $service['items'] ) ) {
					foreach ( $service['items'] as $item ) {
						if ( isset( $item['productType'] ) && $item['productType'] === 'Labels' ) {
							$parsed_services[] = array(
								'description' => $item['description'] ?? 'Unknown Service',
								'total_price' => $item['total'] ?? 0,
								'details'     => $item,
							);
						}
					}
				}
			}

			error_log( 'Parsed Services: ' . print_r( $parsed_services, true ) );

			return $parsed_services;
		}
	}

}
