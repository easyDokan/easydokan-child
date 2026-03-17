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

// Load API Routing for external Node.js syncs
require_once get_stylesheet_directory() . '/includes/classes/class-api-endpoint.php';

// Load WooCommerce Adjustments & Hooks securely
require_once get_stylesheet_directory() . '/includes/classes/class-wc-adjustments.php';

// Load Checkout Customizations securely
require_once get_stylesheet_directory() . '/includes/classes/class-checkout-manager.php';

require_once get_stylesheet_directory() . '/includes/classes/class-shortcodes.php';


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



