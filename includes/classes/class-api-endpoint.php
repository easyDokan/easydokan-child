<?php
defined( 'ABSPATH' ) || exit;

class ED_CONNECT_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'easydokan/v1', '/sync/products', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'sync_products' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( 'easydokan/v1', '/sync/discounts', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'sync_discounts' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( 'easydokan/v1', '/sync/store', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'sync_store' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );
	}

	public function check_permissions() {
		return current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' );
	}

	public function sync_products( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'wc_missing', 'WooCommerce is not active.', array( 'status' => 500 ) );
		}

		$parameters = $request->get_json_params();
		if ( ! isset( $parameters['products'] ) || ! is_array( $parameters['products'] ) ) {
			return new WP_Error( 'invalid_data', 'Invalid products data', array( 'status' => 400 ) );
		}

		$results = array( 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => array() );

		foreach ( $parameters['products'] as $p_data ) {
			try {
				$ezd_id = sanitize_text_field( $p_data['_id'] );

				$existing_products = wc_get_products( array(
					'meta_key'   => '_ezd_id',
					'meta_value' => $ezd_id,
					'limit'      => 1,
					'return'     => 'objects'
				) );

				$has_variations = ! empty( $p_data['variations'] ) && is_array( $p_data['variations'] );
				$product_type   = $has_variations ? 'variable' : 'simple';

				if ( ! empty( $existing_products ) ) {
					$existing_id = $existing_products[0]->get_id();
					// Class name dynamic factory
					$classname = WC_Product_Factory::get_classname_from_product_type( $product_type );
					$product   = new $classname( $existing_id );
					$is_new    = false;
				} else {
					$classname = WC_Product_Factory::get_classname_from_product_type( $product_type );
					$product   = new $classname();
					$is_new    = true;
				}

				$product->set_name( sanitize_text_field( $p_data['name'] ) );
				$product->set_status( 'publish' );

				if ( isset( $p_data['slug'] ) && ! empty( $p_data['slug'] ) ) {
					$product->set_slug( sanitize_title( $p_data['slug'] ) );
				}

				if ( isset( $p_data['regular_price'] ) ) {
					$product->set_regular_price( floatval( $p_data['regular_price'] ) );
				} elseif ( isset( $p_data['price'] ) ) {
					$product->set_regular_price( floatval( $p_data['price'] ) );
				}

				if ( isset( $p_data['sale_price'] ) && floatval( $p_data['sale_price'] ) > 0 ) {
					$product->set_sale_price( floatval( $p_data['sale_price'] ) );
				} else {
					$product->set_sale_price( '' );
				}

				if ( isset( $p_data['weight'] ) ) {
					$product->set_weight( floatval( $p_data['weight'] ) );
				}

				if ( isset( $p_data['description'] ) ) {
					$product->set_description( wp_kses_post( $p_data['description'] ) );
				}

				if ( isset( $p_data['short_description'] ) ) {
					$product->set_short_description( wp_kses_post( $p_data['short_description'] ) );
				}

				if ( isset( $p_data['sku'] ) && ! empty( $p_data['sku'] ) ) {
					$product->set_sku( sanitize_text_field( $p_data['sku'] ) );
				}

				if ( isset( $p_data['stock_quantity'] ) ) {
					$product->set_manage_stock( true );
					$product->set_stock_quantity( intval( $p_data['stock_quantity'] ) );
				}

				// Map Categories
				$category_ids = array();
				if ( isset( $p_data['categories'] ) && is_array( $p_data['categories'] ) ) {
					foreach ( $p_data['categories'] as $cat_name ) {
						$term = term_exists( $cat_name, 'product_cat' );
						if ( ! $term ) {
							$term = wp_insert_term( $cat_name, 'product_cat' );
						}
						if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
							$category_ids[] = (int) $term['term_id'];
						}
					}
				}
				$product->set_category_ids( $category_ids );

				// Map Attributes
				$wc_attributes = array();
				if ( isset( $p_data['attributes'] ) && is_array( $p_data['attributes'] ) ) {
					$position = 0;
					foreach ( $p_data['attributes'] as $attr_data ) {
						$attribute = new WC_Product_Attribute();
						$attribute->set_id( 0 ); // Local attribute
						$attribute->set_name( sanitize_text_field( $attr_data['name'] ) );
						$attribute->set_options( array_map( 'sanitize_text_field', current( (array) $attr_data['options'] ) ? $attr_data['options'] : array() ) );
						$attribute->set_position( $position ++ );
						$attribute->set_visible( true );
						$attribute->set_variation( $has_variations );
						$wc_attributes[] = $attribute;
					}
				}
				$product->set_attributes( $wc_attributes );

				// Override logic: Clear existing variations and attributes if requested
				if ( ! empty( $p_data['override'] ) && $p_data['override'] === true && ! $is_new ) {
					// Clear product attributes (set_attributes above handles current, but metadata might persist)
					// Clear existing variations
					$children = $product->get_children();
					foreach ( $children as $child_id ) {
						$variation = wc_get_product( $child_id );
						if ( $variation ) {
							$variation->delete( true );
						}
					}
				}

				$product->update_meta_data( '_ezd_id', $ezd_id );

				if ( isset( $p_data['thumbnail_url'] ) && ! empty( $p_data['thumbnail_url'] ) ) {
					$product->update_meta_data( '_ed_product_thumbnail_url', esc_url_raw( $p_data['thumbnail_url'] ) );
				}

				if ( isset( $p_data['image_urls'] ) && is_array( $p_data['image_urls'] ) ) {
					$product->update_meta_data( '_ed_product_gallery_urls', array_map( 'esc_url_raw', $p_data['image_urls'] ) );
				}

				$product_id = $product->save();

				error_log( 'attributes_come:' . var_export( $p_data['variations'], true ) );

				// Map Variations
				if ( $has_variations && $product_id ) {
					foreach ( $p_data['variations'] as $v_data ) {
						$key_parts            = explode( '#', $v_data['key'] );
						$variation_attributes = array();

						foreach ( $p_data['attributes'] as $index => $attr_data ) {
							// WooCommerce requires variation meta keys to be prefixed with 'attribute_'
							// and the attribute name should be sanitized consistently (lowercase slug).
							$attr_slug     = sanitize_title( $attr_data['name'] );
							$attr_meta_key = 'attribute_' . $attr_slug;

							if ( isset( $key_parts[ $index ] ) ) {
								$variation_attributes[ $attr_meta_key ] = sanitize_text_field( $key_parts[ $index ] );
							}
						}

						$var_meta_id = $ezd_id . '_' . sanitize_title( $v_data['key'] );

						$existing_vars = wc_get_products( array(
							'type'       => 'variation',
							'parent'     => $product_id,
							'meta_key'   => '_ezd_var_id',
							'meta_value' => $var_meta_id,
							'limit'      => 1,
							'return'     => 'objects'
						) );

						$variation = ! empty( $existing_vars ) ? $existing_vars[0] : new WC_Product_Variation();

						$variation->set_parent_id( $product_id );
						$variation->set_attributes( $variation_attributes );

						error_log( 'attributes_saving:' . var_export( $variation_attributes, true ) );

						if ( isset( $v_data['regular_price'] ) ) {
							$variation->set_regular_price( floatval( $v_data['regular_price'] ) );
						}

						if ( isset( $v_data['sale_price'] ) && floatval( $v_data['sale_price'] ) > 0 ) {
							$variation->set_sale_price( floatval( $v_data['sale_price'] ) );
						} else {
							$variation->set_sale_price( '' );
						}

						if ( isset( $v_data['stock'] ) ) {
							$variation->set_manage_stock( true );
							$variation->set_stock_quantity( intval( $v_data['stock'] ) );
						}

						if ( isset( $v_data['weight'] ) ) {
							$variation->set_weight( floatval( $v_data['weight'] ) );
						}

						$variation->update_meta_data( '_ezd_var_id', $var_meta_id );
						$variation->save();
					}
				}

				if ( $is_new ) {
					$results['created'] ++;
				} else {
					$results['updated'] ++;
				}

			} catch ( Exception $e ) {
				$results['failed'] ++;
				$results['errors'][] = $e->getMessage();
			}
		}

		// --- Categories Independent Sync Addon ---
		if ( isset( $parameters['categories'] ) && is_array( $parameters['categories'] ) ) {
			foreach ( $parameters['categories'] as $cat_data ) {
				try {
					$cat_name = sanitize_text_field( $cat_data['name'] );
					$cat_desc = wp_kses_post( $cat_data['description'] );
					$cat_img  = esc_url_raw( $cat_data['image_url'] );

					// 1. Check if term exists natively
					$term = term_exists( $cat_name, 'product_cat' );
					if ( ! $term ) {
						// Create it if the product sequence somehow missed it
						$term = wp_insert_term( $cat_name, 'product_cat', array(
							'description' => $cat_desc
						) );
					} else {
						// It exists, attempt to safely update description
						if ( ! is_wp_error( $term ) ) {
							wp_update_term( $term['term_id'], 'product_cat', array(
								'description' => $cat_desc
							) );
						}
					}

					// 2. Map Image URL as a custom term_meta field (WooCommerce inherently relies on Attachment IDs for thumbnail_id natively, so we are bypassing it)
					if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
						if ( ! empty( $cat_img ) ) {
							update_term_meta( $term['term_id'], '_ed_category_thumbnail_url', $cat_img );
						} else {
							delete_term_meta( $term['term_id'], '_ed_category_thumbnail_url' ); // Clean up if they delete the image locally
						}
					}
				} catch ( Exception $e ) {
					error_log( 'Category Sync Error: ' . $e->getMessage() );
				}
			}
		}

		return rest_ensure_response( $results );
	}

	public function sync_discounts( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'wc_missing', 'WooCommerce is not active.', array( 'status' => 500 ) );
		}

		$parameters = $request->get_json_params();
		if ( ! isset( $parameters['discounts'] ) || ! is_array( $parameters['discounts'] ) ) {
			return new WP_Error( 'invalid_data', 'Invalid discounts data', array( 'status' => 400 ) );
		}

		$results = array( 'created' => 0, 'updated' => 0, 'failed' => 0, 'errors' => array() );

		foreach ( $parameters['discounts'] as $d_data ) {
			try {
				$code = sanitize_text_field( $d_data['code'] );

				$coupon = new WC_Coupon( $code );
				$is_new = ! $coupon->get_id();

				$coupon->set_code( $code );
				$coupon->set_discount_type( isset( $d_data['type'] ) && $d_data['type'] === 'fixed' ? 'fixed_cart' : 'percent' );
				$coupon->set_amount( floatval( $d_data['amount'] ) );

				if ( isset( $d_data['expiry_date'] ) ) {
					$coupon->set_date_expires( strtotime( $d_data['expiry_date'] ) );
				}

				$coupon->save();

				if ( $is_new ) {
					$results['created'] ++;
				} else {
					$results['updated'] ++;
				}

			} catch ( Exception $e ) {
				$results['failed'] ++;
				$results['errors'][] = $e->getMessage();
			}
		}

		return rest_ensure_response( $results );
	}

	public function sync_store( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'wc_missing', 'WooCommerce is not active.', array( 'status' => 500 ) );
		}

		$parameters = $request->get_json_params();

		try {
			// 1. Basic Store Info Sync
			if ( isset( $parameters['name'] ) ) {
				update_option( 'easydokan_store_name', sanitize_text_field( $parameters['name'] ) );
			}
			if ( isset( $parameters['email'] ) ) {
				update_option( 'easydokan_store_email', sanitize_email( $parameters['email'] ) );
			}
			if ( isset( $parameters['mobile_number'] ) ) {
				update_option( 'easydokan_store_mobile', sanitize_text_field( $parameters['mobile_number'] ) );
			}

			// 2. Address Sync
			if ( isset( $parameters['address'] ) ) {
				$address = $parameters['address'];
				if ( isset( $address['street'] ) ) {
					update_option( 'woocommerce_store_address', sanitize_text_field( $address['street'] ) );
				}
				if ( isset( $address['thana'] ) ) {
					update_option( 'woocommerce_store_address_2', sanitize_text_field( $address['thana'] ) );
				}
				if ( isset( $address['district'] ) ) {
					update_option( 'woocommerce_store_city', sanitize_text_field( $address['district'] ) );
				}
				if ( isset( $address['postcode'] ) ) {
					update_option( 'woocommerce_store_postcode', sanitize_text_field( $address['postcode'] ) );
				}
				update_option( 'woocommerce_default_country', 'BD' ); // Ensure Bangladesh locale base
			}

			// 2. Tax / VAT Sync
			if ( isset( $parameters['tax'] ) ) {
				$tax        = $parameters['tax'];
				$is_enabled = ! empty( $tax['enabled'] ) && $tax['enabled'] !== 'false' && $tax['enabled'] !== false;
				update_option( 'woocommerce_calc_taxes', $is_enabled ? 'yes' : 'no' );

				if ( $is_enabled && isset( $tax['percentage'] ) ) {
					global $wpdb;
					$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_name = %s", 'VAT' ) );

					$wpdb->insert( "{$wpdb->prefix}woocommerce_tax_rates", array(
						'tax_rate_country'  => '',
						'tax_rate_state'    => '',
						'tax_rate'          => floatval( $tax['percentage'] ),
						'tax_rate_name'     => 'VAT',
						'tax_rate_priority' => 1,
						'tax_rate_compound' => 0,
						'tax_rate_shipping' => 1,
						'tax_rate_order'    => 0,
						'tax_rate_class'    => ''
					) );
				}
			}

			// 3. Shipping Sync (Dynamic Divisions & Pricing Cascade)
			if ( isset( $parameters['shipping'] ) ) {
				$shipping      = $parameters['shipping'];
				$base_division = sanitize_text_field( $shipping['base_division'] ?? 'Dhaka' );

				$inside  = $shipping['inside'] ?? array();
				$outside = $shipping['outside'] ?? array();

				// Inherit Priority cascade: sale pricing truncates regular if greater than 0
				$cost_inside  = isset( $inside['sale'] ) && floatval( $inside['sale'] ) > 0 ? floatval( $inside['sale'] ) : floatval( $inside['regular'] ?? 0 );
				$cost_outside = isset( $outside['sale'] ) && floatval( $outside['sale'] ) > 0 ? floatval( $outside['sale'] ) : floatval( $outside['regular'] ?? 0 );

				// Helper to get or create zone
				$get_or_create_zone = function ( $zone_name ) {
					$zones = WC_Shipping_Zones::get_zones();
					foreach ( $zones as $zone_arr ) {
						if ( $zone_arr['zone_name'] === $zone_name ) {
							return new WC_Shipping_Zone( $zone_arr['zone_id'] );
						}
					}
					$zone = new WC_Shipping_Zone();
					$zone->set_zone_name( $zone_name );
					$zone->save();

					return $zone;
				};

				// Helper to set flat rate on zone
				$set_flat_rate = function ( $zone, $cost ) {
					$methods      = $zone->get_shipping_methods();
					$flat_rate_id = null;

					foreach ( $methods as $method ) {
						if ( $method->id === 'flat_rate' ) {
							$flat_rate_id                       = $method->instance_id;
							$method->instance_settings['cost']  = strval( $cost );
							$method->instance_settings['title'] = 'Flat Rate';
							update_option( $method->get_instance_option_key(), $method->instance_settings );
							break;
						}
					}

					if ( ! $flat_rate_id ) {
						$instance_id                        = $zone->add_shipping_method( 'flat_rate' );
						$method                             = new WC_Shipping_Flat_Rate( $instance_id );
						$method->instance_settings['cost']  = strval( $cost );
						$method->instance_settings['title'] = 'Flat Rate';
						update_option( $method->get_instance_option_key(), $method->instance_settings );
					}
				};

				$zone_inside = $get_or_create_zone( 'Inside ' . $base_division );
				$set_flat_rate( $zone_inside, $cost_inside );

				$zone_outside = $get_or_create_zone( 'Outside ' . $base_division );
				$set_flat_rate( $zone_outside, $cost_outside );
			}

			return rest_ensure_response( array( 'success' => true, 'message' => 'Store options synchronized successfully.' ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'sync_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}

new ED_CONNECT_API();
