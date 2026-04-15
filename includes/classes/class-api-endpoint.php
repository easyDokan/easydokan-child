<?php
defined( 'ABSPATH' ) || exit;

class ED_CONNECT_API {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	private function sideload_image( string $url, int $post_id = 0 ): int {
		if ( empty( $url ) ) {
			return 0;
		}

		$existing = get_posts( array(
			'post_type'   => 'attachment',
			'post_status' => 'inherit',
			'meta_key'    => '_ed_source_url',
			'meta_value'  => $url,
			'numberposts' => 1,
			'fields'      => 'ids',
		) );

		if ( ! empty( $existing ) ) {
			$attachment_id = $existing[0];

			if ( $post_id > 0 ) {
				wp_update_post( array(
					'ID'          => $attachment_id,
					'post_parent' => $post_id,
				) );
			}

			return $attachment_id;
		}

		$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			error_log( 'ED Image Sideload Failed: ' . $url );

			return 0;
		}

		$image_data = wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			return 0;
		}

		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		if ( empty( $filename ) || strpos( $filename, '.' ) === false ) {
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			$ext          = 'jpg';
			if ( strpos( $content_type, 'png' ) !== false ) {
				$ext = 'png';
			} elseif ( strpos( $content_type, 'webp' ) !== false ) {
				$ext = 'webp';
			} elseif ( strpos( $content_type, 'gif' ) !== false ) {
				$ext = 'gif';
			}
			$filename = 'ed-product-' . md5( $url ) . '.' . $ext;
		}

		$upload = wp_upload_bits( $filename, null, $image_data );

		if ( ! empty( $upload['error'] ) ) {
			error_log( 'ED Image Upload Error: ' . $upload['error'] );

			return 0;
		}

		$file_type = wp_check_filetype( $upload['file'], null );

		$attachment = array(
			'post_mime_type' => $file_type['type'],
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		update_post_meta( $attachment_id, '_ed_source_url', $url );

		return $attachment_id;
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
		register_rest_route( 'easydokan/v1', '/generate-login-url', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'generate_login_url' ),
			'permission_callback' => array( $this, 'check_permissions' ), // Basic Auth protected
		) );

		register_rest_route( 'easydokan/v1', '/magic-login', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'process_magic_login' ),
			'permission_callback' => '__return_true', // Public, secured by token
		) );
	}

	public function generate_login_url( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'unauthorized', 'User not authenticated', array( 'status' => 401 ) );
		}

		$token = wp_generate_password( 32, false );
		// Store transient for 60 seconds
		set_transient( 'ed_magic_login_' . $token, $user_id, 60 );

		$login_url = rest_url( 'easydokan/v1/magic-login?token=' . $token );

		return rest_ensure_response( array(
			'success' => true,
			'url'     => $login_url
		) );
	}

	public function process_magic_login( WP_REST_Request $request ) {
		$token = $request->get_param( 'token' );
		if ( empty( $token ) ) {
			wp_die( 'Invalid or missing token.', 'Login Failed', array( 'response' => 400 ) );
		}

		$user_id = get_transient( 'ed_magic_login_' . $token );
		if ( ! $user_id ) {
			wp_die( 'This magic login link is invalid or has expired.', 'Login Expired', array( 'response' => 403 ) );
		}

		// Delete the transient to ensure single use
		delete_transient( 'ed_magic_login_' . $token );

		// Log the user in
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		// Redirect to dashboard
		wp_safe_redirect( admin_url() );
		exit;
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

		$results = array( 
			'created' => 0, 
			'updated' => 0, 
			'failed' => 0, 
			'errors' => array(),
			'product_urls' => array()
		);

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
					$classname   = WC_Product_Factory::get_classname_from_product_type( $product_type );
					$product     = new $classname( $existing_id );
					$is_new      = false;
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

				$wc_attributes = array();
				if ( isset( $p_data['attributes'] ) && is_array( $p_data['attributes'] ) ) {
					$position = 0;
					foreach ( $p_data['attributes'] as $attr_data ) {
						$attribute = new WC_Product_Attribute();
						$attribute->set_id( 0 );
						$attribute->set_name( sanitize_text_field( $attr_data['name'] ) );
						$attribute->set_options( array_map( 'sanitize_text_field', current( (array) $attr_data['options'] ) ? $attr_data['options'] : array() ) );
						$attribute->set_position( $position ++ );
						$attribute->set_visible( true );
						$attribute->set_variation( $has_variations );
						$wc_attributes[] = $attribute;
					}
				}
				$product->set_attributes( $wc_attributes );

				if ( ! empty( $p_data['override'] ) && $p_data['override'] === true && ! $is_new ) {
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
					$thumb_attachment_id = $this->sideload_image( esc_url_raw( $p_data['thumbnail_url'] ), $product->get_id() ?: 0 );
					if ( $thumb_attachment_id > 0 ) {
						$product->set_image_id( $thumb_attachment_id );
					}
				}

				if ( isset( $p_data['image_urls'] ) && is_array( $p_data['image_urls'] ) ) {
					$product->update_meta_data( '_ed_product_gallery_urls', array_map( 'esc_url_raw', $p_data['image_urls'] ) );
				}

				$product_id = $product->save();
				
				// Return the permanent link for this product
				$results['product_urls'][$ezd_id] = get_permalink($product_id);

				if ( isset( $p_data['image_urls'] ) && is_array( $p_data['image_urls'] ) && ! empty( $p_data['image_urls'] ) ) {
					$gallery_ids = array();

					foreach ( $p_data['image_urls'] as $index => $img_url ) {
						$attachment_id = $this->sideload_image( esc_url_raw( $img_url ), $product_id );

						if ( $attachment_id > 0 ) {
							if ( $index === 0 ) {
								set_post_thumbnail( $product_id, $attachment_id );
							} else {
								$gallery_ids[] = $attachment_id;
							}
						}
					}

					if ( ! empty( $gallery_ids ) ) {
						update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
					}
				}

				if ( $has_variations && $product_id ) {
					foreach ( $p_data['variations'] as $v_data ) {
						$key_parts            = explode( '#', $v_data['key'] );
						$variation_attributes = array();

						foreach ( $p_data['attributes'] as $index => $attr_data ) {
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
						$variation->update_meta_data( '_ezd_exact_variation_key', sanitize_text_field( $v_data['key'] ) );

						if ( isset( $v_data['_id'] ) ) {
							$variation->update_meta_data( '_ezd_variant_id', sanitize_text_field( $v_data['_id'] ) );
						}

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

		if ( isset( $parameters['categories'] ) && is_array( $parameters['categories'] ) ) {
			foreach ( $parameters['categories'] as $cat_data ) {
				try {
					$cat_name = sanitize_text_field( $cat_data['name'] );
					$cat_desc = wp_kses_post( $cat_data['description'] );
					$cat_img  = esc_url_raw( $cat_data['image_url'] );

					$term = term_exists( $cat_name, 'product_cat' );
					if ( ! $term ) {
						$term = wp_insert_term( $cat_name, 'product_cat', array(
							'description' => $cat_desc
						) );
					} else {
						if ( ! is_wp_error( $term ) ) {
							wp_update_term( $term['term_id'], 'product_cat', array(
								'description' => $cat_desc
							) );
						}
					}

					if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
						if ( ! empty( $cat_img ) ) {
							update_term_meta( $term['term_id'], '_ed_category_thumbnail_url', $cat_img );

							$cat_attachment_id = $this->sideload_image( $cat_img );
							if ( $cat_attachment_id > 0 ) {
								update_term_meta( $term['term_id'], 'thumbnail_id', $cat_attachment_id );
							}
						} else {
							delete_term_meta( $term['term_id'], '_ed_category_thumbnail_url' );
							delete_term_meta( $term['term_id'], 'thumbnail_id' );
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
			if ( isset( $parameters['store_id'] ) ) {
				update_option( 'easydokan_store_id', sanitize_text_field( $parameters['store_id'] ) );
			}
			if ( isset( $parameters['backend_api_url'] ) ) {
				update_option( 'easydokan_backend_api_url', esc_url_raw( $parameters['backend_api_url'] ) );
			}
			if ( isset( $parameters['frontend_app_url'] ) ) {
				update_option( 'easydokan_frontend_app_url', esc_url_raw( $parameters['frontend_app_url'] ) );
			}
			if ( isset( $parameters['name'] ) ) {
				update_option( 'easydokan_store_name', sanitize_text_field( $parameters['name'] ) );
			}
			if ( isset( $parameters['email'] ) ) {
				update_option( 'easydokan_store_email', sanitize_email( $parameters['email'] ) );
			}
			if ( isset( $parameters['mobile_number'] ) ) {
				update_option( 'easydokan_store_mobile', sanitize_text_field( $parameters['mobile_number'] ) );
			}

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
				update_option( 'woocommerce_default_country', 'BD' );
			}

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

			if ( isset( $parameters['shipping'] ) ) {
				$shipping      = $parameters['shipping'];
				$base_location = sanitize_text_field( $shipping['base_division'] ?? 'Dhaka' );

				$inside  = $shipping['inside'] ?? array();
				$outside = $shipping['outside'] ?? array();

				update_option( 'easydokan_shipping_base_location', $base_location );
				update_option( 'easydokan_shipping_inside_regular_cost', floatval( $inside['regular'] ?? 0 ) );
				update_option( 'easydokan_shipping_inside_sale_cost', floatval( $inside['sale'] ?? 0 ) );
				update_option( 'easydokan_shipping_outside_regular_cost', floatval( $outside['regular'] ?? 0 ) );
				update_option( 'easydokan_shipping_outside_sale_cost', floatval( $outside['sale'] ?? 0 ) );
			}

			return rest_ensure_response( array( 'success' => true, 'message' => 'Store options synchronized successfully.' ) );
		} catch ( Exception $e ) {
			return new WP_Error( 'sync_failed', $e->getMessage(), array( 'status' => 500 ) );
		}
	}
}

new ED_CONNECT_API();
