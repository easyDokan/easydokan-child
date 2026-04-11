<?php
/**
 * Recommended way to include parent theme styles.
 * (Please see http://codex.wordpress.org/Child_Themes#How_to_Create_a_Child_Theme)
 *
 */

add_action( 'wp_enqueue_scripts', 'kadence_for_easydokan_style' );
function kadence_for_easydokan_style() {
	wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
	wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'parent-style' ) );
}

function ed_allow_svg_filetype( $data, $file, $filename, $mimes ) {
	$filetype = wp_check_filetype( $filename, $mimes );

	if ( ! empty( $filetype['ext'] ) && $filetype['ext'] === 'svg' ) {
		$data['ext']  = 'svg';
		$data['type'] = 'image/svg+xml';
	}

	return $data;
}

add_filter( 'wp_check_filetype_and_ext', 'ed_allow_svg_filetype', 10, 4 );

function ed_allow_svg_uploads( $mimes ) {
	$mimes['svg']  = 'image/svg+xml';
	$mimes['svgz'] = 'image/svg+xml';

	return $mimes;
}

add_filter( 'upload_mimes', 'ed_allow_svg_uploads' );

/**
 * EasyDokan Core Integrations
 */
require_once get_stylesheet_directory() . '/includes/classes/class-theme-updater.php';
require_once get_stylesheet_directory() . '/includes/classes/class-api-endpoint.php';
require_once get_stylesheet_directory() . '/includes/classes/class-wc-adjustments.php';
require_once get_stylesheet_directory() . '/includes/classes/class-checkout-manager.php';
require_once get_stylesheet_directory() . '/includes/classes/class-shortcodes.php';

if ( ! function_exists( 'easydokan_enqueue_scripts' ) ) {
	function easydokan_enqueue_scripts() {

		$localize_scripts = array(
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'copy_text' => esc_html__( 'Copied.', 'tinypress' ),
		);

		wp_enqueue_script( 'easydokan', get_stylesheet_directory_uri() . '/assets/front/js/scripts.js', array( 'jquery' ) );
		wp_localize_script( 'easydokan', 'tinypress', $localize_scripts );

		wp_enqueue_style( 'easydokan-theme', get_stylesheet_directory_uri() . '/style.css' );
		wp_enqueue_style( 'easydokan', get_stylesheet_directory_uri() . '/assets/front/css/main.min.css' );
	}
}
add_action( 'wp_enqueue_scripts', 'easydokan_enqueue_scripts' );


add_filter( 'woocommerce_product_get_image', function ( $image, $product, $size ) {

	if ( $product instanceof WC_Product_Simple || $product instanceof WC_Product_Variable ) {

		$thumbnail_url = get_post_meta( $product->get_id(), '_ed_product_thumbnail_url', true );
		$thumbnail_url = esc_url( $thumbnail_url );
		$image_alt     = esc_attr( $product->get_title() );

		return sprintf( '<img src="%s" alt="%s" />', $thumbnail_url, $image_alt );
	}

	return $image;
}, 10, 3 );

remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
add_action( 'woocommerce_after_shop_loop_item', function () {
	global $product;

	echo '<a class="ed-product-details" href="' . get_permalink( $product->get_id() ) . '">Order Now</a>';

}, 15 );

add_action( 'wp_head', function () {
	if ( isset( $_GET['debug'] ) && sanitize_text_field( $_GET['debug'] ) === 'yes' ) {
		$name    = 'Jaed';
		$phone   = '01727967674';
		$address = 'Chartola Mor, Rangpur';
		$zone    = 'inside';

		$order           = wc_create_order();
		$address_payload = array(
			'first_name' => $name,
			'last_name'  => '',
			'email'      => $phone . '@localhost.local', // Placeholder to prevent WC crashes if auth plugins require it
			'phone'      => $phone,
			'address_1'  => $address,
			'city'       => '',
			'state'      => '',
			'postcode'   => '',
			'country'    => 'BD'
		);
		$order->set_address( $address_payload, 'billing' );
		$order->set_address( $address_payload, 'shipping' );

		// Add Cart Items
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$order->add_product( $cart_item['data'], $cart_item['quantity'] );
		}

		// Calculate Shipping
		$base_loc = get_option( 'easydokan_shipping_base_location', 'Dhaka' );

		$cost = 0;
		if ( $zone === 'inside' ) {
			$regular = (float) get_option( 'easydokan_shipping_inside_regular_cost', 0 );
			$sale    = (float) get_option( 'easydokan_shipping_inside_sale_cost', 0 );
			$cost    = $sale > 0 ? $sale : $regular;
		} else {
			$regular = (float) get_option( 'easydokan_shipping_outside_regular_cost', 0 );
			$sale    = (float) get_option( 'easydokan_shipping_outside_sale_cost', 0 );
			$cost    = $sale > 0 ? $sale : $regular;
		}

		$shipping = new WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Flat rate' );
		$shipping->set_method_id( 'flat_rate' );
		$shipping->set_total( $cost );
		$order->add_item( $shipping );

		// Set Payment
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		if ( isset( $payment_gateways['cod'] ) ) {
			$order->set_payment_method( $payment_gateways['cod'] );
			$order->set_payment_method_title( $payment_gateways['cod']->get_title() );
		} else {
			$order->set_payment_method( 'cod' );
			$order->set_payment_method_title( 'Cash on Delivery' );
		}

		// Calculate Totals Natively
		$order->calculate_totals();

		// Set Order Status (typically processing for COD if stock reduces)
		$order->update_status( 'processing', 'Custom checkout order created via AJAX.' );

		// Sync to the Webhook Node.js Backend!
		$store_id = get_option( 'easydokan_store_id' );
		if ( $store_id ) {
			$backend_url = get_option( 'easydokan_backend_api_url', 'http://localhost:5001' ); // Standard fallback

			// Fetch complete mapping items for webhook payload
			$products_payload = array();
			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();
				if ( ! $product instanceof WC_Product ) {
					continue;
				}

				$ezd_id = get_post_meta( $product->get_id(), '_ezd_id', true );
				$ezd_id = empty( $ezd_id ) ? get_post_meta( $product->get_parent_id(), '_ezd_id', true ) : $ezd_id;

				if ( $ezd_id ) {
					$attributes = array();

					foreach ( $item->get_meta_data() as $meta_id => $meta ) {
						if ( $meta instanceof WC_Meta_Data ) {

							$meta_data  = $meta->get_data();
							$meta_key   = $meta_data['key'] ?? '';
							$meta_value = $meta_data['value'] ?? '';

							$attributes[] = array(
								'name'   => wp_strip_all_tags( $meta_key ),
								'option' => wp_strip_all_tags( $meta_value )
							);
						}
					}

					$variation_key = null;
					if ( $product->is_type( 'variation' ) ) {
						$variation_key = get_post_meta( $product->get_id(), '_ezd_exact_variation_key', true );
					}

					$products_payload[] = array(
						'product_id'    => $ezd_id,
						'variation_key' => $variation_key,
						'name'          => $product->get_name(),
						'quantity'      => $item->get_quantity(),
						'price'         => $item->get_total(),
						'attributes'    => $attributes
					);
				}
			}

			$webhook_payload = array(
				'mobile_number'    => $phone,
				'name'             => $name,
				'shipping_address' => array( 'street' => $address, 'country' => 'BD' ),
				'products'         => $products_payload,
				'total_amount'     => $order->get_total()
			);

			$response = wp_remote_post( $backend_url . '/api/orders/webhook/' . $store_id, array(
				'body'     => wp_json_encode( $webhook_payload ),
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'timeout'  => 15,
				'blocking' => true // Blocking -> Verify backend explicitly!
			) );

			// Intercept Backend Node.js rejections
			if ( is_wp_error( $response ) ) {
				$order->update_status( 'failed', 'Node JS Connectivity Error.' );
				wp_send_json_error( 'API Database unreachable: ' . $response->get_error_message() );
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			if ( $status_code >= 400 ) {
				$order->update_status( 'failed', 'Node JS rejected validation.' );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				$msg  = isset( $body['error'] ) ? $body['error'] : ( isset( $body['message'] ) ? $body['message'] : 'Backend rejection code: ' . $status_code );
				wp_send_json_error( 'Backend validation failed: ' . $msg );
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $body['_id'] ) ) {
				$backend_order_id = $body['_id'];
			}
		}

		die();
	}
}, 0 );