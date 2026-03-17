<?php
/**
 * Single Product Image
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/product-image.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

$columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$post_thumbnail_id = $product->get_image_id();
$wrapper_classes   = apply_filters(
	'woocommerce_single_product_image_gallery_classes',
	array(
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $post_thumbnail_id ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $columns ),
		'images',
	)
);
$gallery_urls      = get_post_meta( $product->get_id(), '_ed_product_gallery_urls', true );
$featured_url      = get_post_meta( $product->get_id(), '_ed_product_thumbnail_url', true );
$product_title     = $product->get_title();
?>
<div id="ed-single-product-images" class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>" data-columns="<?php echo esc_attr( $columns ); ?>" style="opacity: 0; transition: opacity .25s ease-in-out;">
    <div class="woocommerce-product-gallery__wrapper">
		<?php
		foreach ( $gallery_urls as $index => $image_url ) {
			if ( $index === 0 ) {
				continue;
			}
			$image_alt = $product_title . ' - Gallery Image ' . ( $index + 1 );

			printf( '<div data-thumb="%1$s" class="woocommerce-product-gallery__image"><a href="%1$s"><img src="%1$s" alt="%2$s" class="wp-post-image" /></a></div>', esc_url( $image_url ), esc_attr( $image_alt ) );
		}

		if ( ! empty( $featured_url ) ) {
			printf(
				'<div data-thumb="%s" class="woocommerce-product-gallery__image"><a href="%s"><img src="%s" alt="%s" class="wp-post-image" /></a></div>',
				esc_url( $featured_url ),
				esc_url( $featured_url ),
				esc_url( $featured_url ),
				esc_attr( $product_title )
			);
		}
		?>
    </div>
</div>
