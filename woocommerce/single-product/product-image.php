<?php
/**
 * Single Product Image
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 10.5.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

$columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
$post_thumbnail_id = $product->get_image_id();
$gallery_ids       = $product->get_gallery_image_ids();
$wrapper_classes   = apply_filters(
	'woocommerce_single_product_image_gallery_classes',
	array(
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $post_thumbnail_id ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $columns ),
		'images',
	)
);
?>
<div id="ed-single-product-images" class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>" data-columns="<?php echo esc_attr( $columns ); ?>" style="opacity: 0; transition: opacity .25s ease-in-out;">
    <div class="woocommerce-product-gallery__wrapper">
		<?php
		if ( $post_thumbnail_id ) {
			$full_url  = wp_get_attachment_image_url( $post_thumbnail_id, 'full' );
			$thumb_url = wp_get_attachment_image_url( $post_thumbnail_id, 'shop_single' );
			$alt       = get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true ) ?: $product->get_title();

			printf(
				'<div data-thumb="%s" class="woocommerce-product-gallery__image"><a href="%s">%s</a></div>',
				esc_url( $thumb_url ),
				esc_url( $full_url ),
				wp_get_attachment_image( $post_thumbnail_id, 'shop_single', false, array( 'class' => 'wp-post-image' ) )
			);
		} else {
			echo '<div class="woocommerce-product-gallery__image"><img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="' . esc_attr__( 'Placeholder', 'woocommerce' ) . '" class="wp-post-image" /></div>';
		}

		if ( ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $gallery_id ) {
				$full_url  = wp_get_attachment_image_url( $gallery_id, 'full' );
				$thumb_url = wp_get_attachment_image_url( $gallery_id, 'shop_single' );

				printf(
					'<div data-thumb="%s" class="woocommerce-product-gallery__image"><a href="%s">%s</a></div>',
					esc_url( $thumb_url ),
					esc_url( $full_url ),
					wp_get_attachment_image( $gallery_id, 'shop_single' )
				);
			}
		}
		?>
    </div>
</div>
