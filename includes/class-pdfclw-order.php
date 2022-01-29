<?php
 /**
  * Order Class.
  *
  * @link       https://powerfulwp.com
  * @since      1.0.0
  *
  * @package    Pdfclw
  * @subpackage Pdfclw/includes
  */
class Pdfclw_Order {
	/**
	 * Order pickup phone.
	 *
	 * @param object $order order obkect.
	 * @return void
	 */

	/**
	 * Order pickup phone
	 *
	 * @param string $pickup_phone pickup phone.
	 * @param object $order order object.
	 * @return string
	 */
	public function pickup_phone( $pickup_phone, $order ) {
		if ( ! empty( $order ) ) {
			$order_id  = $order->get_id();
			$address_1 = get_post_meta( $order_id, '_pdfclw_pickup_address_1', true );
			$city      = get_post_meta( $order_id, '_pdfclw_pickup_city', true );
			if ( '' !== $address_1 && '' !== $city ) {
				$pickup_phone = get_post_meta( $order_id, '_billing_phone', true );
			}
		}
		return $pickup_phone;
	}


	/**
	 * Order pickup type.
	 *
	 * @param string $pickup_type pickup_type.
	 * @param object $order order object.
	 * @return string
	 */
	public function pickup_type( $pickup_type, $order ) {
		if ( ! empty( $order ) ) {
			$order_id  = $order->get_id();
			$address_1 = get_post_meta( $order_id, '_pdfclw_pickup_address_1', true );
			$city      = get_post_meta( $order_id, '_pdfclw_pickup_city', true );
			if ( '' !== $address_1 && '' !== $city ) {
				$pickup_type = 'customer';
			}
		}
		return $pickup_type;
	}

	/**
	 * Order Pickup location.
	 *
	 * @param string $geocode geocode.
	 * @param object $order order object.
	 * @return statment
	 */
	public function get_order_pickup_geocode( $geocode, $order ) {

		if ( ! empty( $order ) ) {
			$order_id        = $order->get_id();
			$address_1       = get_post_meta( $order_id, '_pdfclw_pickup_address_1', true );
			$city            = get_post_meta( $order_id, '_pdfclw_pickup_city', true );
			$pickup_location = get_post_meta( $order_id, '_plfdd_pickup_location', true );

			if ( '' !== $address_1 && '' !== $city && 'store' !== $pickup_location ) {
				$this->set_pickup_geocode( $order_id );
				$coordinates = $this->get_pickup_geocode( $order_id );
				if ( false !== $coordinates && is_array( $coordinates ) ) {
					$geocode = $coordinates[0] . ',' . $coordinates[1];
				}
			}
		}
		return $geocode;
	}

		/**
		 * Get pickup geocode.
		 *
		 * @param int $order_id order number.
		 * @return statement
		 */
	public function get_pickup_geocode( $order_id ) {
		$result = false;
		if ( '' !== $order_id ) {
			$latitude  = get_post_meta( $order_id, '_pdfclw_pickup_latitude', true );
			$longitude = get_post_meta( $order_id, '_pdfclw_pickup_longitude', true );
			if ( '' !== $latitude && '' !== $longitude ) {
				$result = array( $latitude, $longitude );
			}
		}
		return $result;
	}



	/**
	 * Set pickup Geocode.
	 *
	 * @param int $order_id order  number.
	 * @return void
	 */
	public function set_pickup_geocode( $order_id ) {
		if ( '' !== $order_id && ( '' === get_post_meta( $order_id, '_pdfclw_pickup_latitude', true )
		|| '' === get_post_meta( $order_id, '_pdfclw_pickup_longitude', true ) ) ) {
			$pickup_map_address = $this->get_order_customer_pickup_location( $order_id, 'map_address' );
			if ( ! empty( $pickup_map_address ) ) {

				$geocode = $this->get_geocode( $pickup_map_address );
				if ( 'OK' === $geocode[0] ) {
					update_post_meta( $order_id, '_pdfclw_pickup_latitude', $geocode[4] );
					update_post_meta( $order_id, '_pdfclw_pickup_longitude', $geocode[5] );
				} else {
					if ( '' !== $geocode[0] ) {
						update_post_meta( $order_id, '_pdfclw_pickup_latitude', 0 );
						update_post_meta( $order_id, '_pdfclw_pickup_longitude', 0 );
					}
				}
			}
		}
	}

	/**
	 * Get geocode.
	 *
	 * @param string $map_address address.
	 * @return statement
	 */
	public function get_geocode( $map_address ) {

		$coordinations     = '';
		$formatted_address = '';
		$location_type     = '';
		$status            = '';
		$lat               = '';
		$lng               = '';

		$pdfclw_google_api_key = get_option( 'pdfclw_google_api_key_server', '' );
		if ( '' !== $pdfclw_google_api_key ) {
			$url = 'https://maps.google.com/maps/api/geocode/json?sensor=false&language=en&key=' . $pdfclw_google_api_key . '&address=' . $map_address;
			$ch  = curl_init();
			curl_setopt( $ch, CURLOPT_URL, $url );
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt( $ch, CURLOPT_PROXYPORT, 3128 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
			$response = curl_exec( $ch );
			curl_close( $ch );
			$response_a = json_decode( $response );
			if ( json_last_error() === 0 ) {
				$status = $response_a->status;
				if ( 'OK' === $status ) {
					$lat               = $response_a->results[0]->geometry->location->lat;
					$lng               = $response_a->results[0]->geometry->location->lng;
					$coordinations     = $lat . ',' . $lng;
					$formatted_address = $response_a->results[0]->formatted_address;
					$location_type     = $response_a->results[0]->geometry->location_type;
				}
			}
		}
		return array( $status, $coordinations, $formatted_address, $location_type, $lat, $lng );
	}

	  /**
	   * Get order customer pickup location address.
	   *
	   * @param object $order order object.
	   * @return statement
	   */
	public function get_order_customer_pickup_location( $order_id, $format ) {

		$address = '';

		// Get the location data if it's already been entered.
		$first_name = get_post_meta( $order_id, '_pdfclw_pickup_first_name', true );
		$last_name  = get_post_meta( $order_id, '_pdfclw_pickup_last_name', true );
		$company    = get_post_meta( $order_id, '_pdfclw_pickup_company', true );
		$address_1  = get_post_meta( $order_id, '_pdfclw_pickup_address_1', true );
		$address_2  = get_post_meta( $order_id, '_pdfclw_pickup_address_2', true );
		$city       = get_post_meta( $order_id, '_pdfclw_pickup_city', true );
		$postcode   = get_post_meta( $order_id, '_pdfclw_pickup_postcode', true );
		$country    = get_post_meta( $order_id, '_pdfclw_pickup_country', true );
		$state      = get_post_meta( $order_id, '_pdfclw_pickup_state', true );

		$array = array(
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'company'    => $company,
			'street_1'   => $address_1,
			'street_2'   => $address_2,
			'city'       => $city,
			'zip'        => $postcode,
			'country'    => '' !== $country ? WC()->countries->countries[ $country ] : '',
			'state'      => $state,
		);

		if ( '' !== $address_1 && '' !== $city ) {
			$address = pdfclw_format_address( $format, $array );
		}
		return $address;
	}

	/**
	 * Order Pickup location.
	 *
	 * @param string $format address format.
	 * @param object $order order object.
	 * @param int    $seller_id sellet id.
	 * @param string $address address.
	 * @return string
	 */
	public function order_pickup_loction( $address, $format, $order, $seller_id ) {
		if ( ! empty( $order ) ) {

			$order_id        = $order->get_id();
			$address_1       = get_post_meta( $order_id, '_pdfclw_pickup_address_1', true );
			$city            = get_post_meta( $order_id, '_pdfclw_pickup_city', true );
			$pickup_location = get_post_meta( $order_id, '_plfdd_pickup_location', true );

			if ( '' !== $address_1 && '' !== $city && 'store' !== $pickup_location ) {
				// Customer pickup location.
				$first_name = get_post_meta( $order_id, '_pdfclw_pickup_first_name', true );
				$last_name  = get_post_meta( $order_id, '_pdfclw_pickup_last_name', true );
				$company    = get_post_meta( $order_id, '_pdfclw_pickup_company', true );
				$address_2  = get_post_meta( $order_id, '_pdfclw_pickup_address_2', true );
				$postcode   = get_post_meta( $order_id, '_pdfclw_pickup_postcode', true );
				$country    = get_post_meta( $order_id, '_pdfclw_pickup_country', true );
				$state      = get_post_meta( $order_id, '_pdfclw_pickup_state', true );

				if ( '' !== $country ) {
					$state = pdfclw_states( $country, $state );
				}

				$array   = array(
					'first_name' => $first_name,
					'last_name'  => $last_name,
					'company'    => $company,
					'street_1'   => $address_1,
					'street_2'   => $address_2,
					'city'       => $city,
					'zip'        => $postcode,
					'country'    => '' !== $country ? WC()->countries->countries[ $country ] : '',
					'state'      => $state,
				);
				$address = pdfclw_format_address( $format, $array );
			}
		}
		return $address;
	}

	/**
	 * Function that return order seller id.
	 *
	 * @param object $order order.
	 * @return string
	 */
	public function order_seller( $order ) {
		$result = '';

		global $wpdb;
		$order_id = $order->get_id();
		switch ( PDFCLW_MULTIVENDOR ) {
			case 'dokan':
				$result = $order->get_meta( '_dokan_vendor_id' );
				break;
			case 'wcmp':
				$result = $order->get_meta( '_vendor_id' );
				break;
			case 'wcfm':
				$query = $wpdb->get_results(
					$wpdb->prepare(
						'select vendor_id from ' . $wpdb->prefix . 'wcfm_marketplace_orders where order_id=%s',
						array( $order_id )
					)
				);
				if ( ! empty( $query ) ) {
					$result = $query[0]->vendor_id;
				}
				break;
			default:
				$result = '';
				break;
		}
		return $result;
	}

	/**
	 * Set order geocode action.
	 *
	 * @param int $order_id order number.
	 * @return void
	 */
	public function set_order_geocode_action( $order_id, $order ) {
		if ( 'customer' === $this->pickup_type( '', $order ) ) {
			$this->set_pickup_geocode( $order_id );
		}
	}


}


