<?php
/**
 * Render Category Theme 1
 */

defined( 'ABSPATH' ) || exit;

// Retrieve product categories, hiding empty ones
$categories = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
) );

if ( empty( $categories ) || is_wp_error( $categories ) ) {
	return;
}

?>

<div class="ed-categories">
	<?php foreach ( $categories as $category ) :
		$image_url = get_term_meta( $category->term_id, '_ed_category_thumbnail_url', true );

		if ( empty( $image_url ) ) {
			$thumbnail_id = get_term_meta( $category->term_id, 'thumbnail_id', true );
			if ( $thumbnail_id ) {
				$image_url = wp_get_attachment_url( $thumbnail_id );
			}
		}

		if ( empty( $image_url ) ) {
			$image_url = wc_placeholder_img_src();
		}

		$term_link = get_term_link( $category );
		?>
        <div class="ed-category">
            <a href="<?php echo esc_url( $term_link ); ?>" class="ed-category-image">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $category->name ); ?>" loading="lazy"/>
            </a>
            <h3 class="ed-category-title">
                <a href="<?php echo esc_url( $term_link ); ?>">
			        <?php echo esc_html( $category->name ); ?>
                </a>
            </h3>
            <span class="ed-category-count"><?php echo esc_html( $category->count ); ?></span>
        </div>
	<?php endforeach; ?>
</div>

<style>
    .ed-categories {
        display: flex;
        flex-wrap: wrap;
        gap: 30px;
        justify-content: center;
    }

    .ed-categories .ed-category {
        overflow: hidden;
        flex: 1 1 calc(33.333% - 30px);
        max-width: 280px;
        background: #f1f3f3;
        border-radius: 8px;
        position: relative;
    }

    .ed-categories .ed-category .ed-category-count {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--global-palette-btn-bg);
        width: 44px;
        height: 44px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #f1f1f1;
        font-size: 18px;
        line-height: 18px;
    }

    .ed-categories .ed-category .ed-category-image {
    }

    .ed-categories .ed-category .ed-category-title,
    .ed-categories .ed-category .ed-category-title a,
    .ed-categories .ed-category .ed-category-title a:active,
    .ed-categories .ed-category .ed-category-title a:focus {
        margin: 10px 0;
        font-size: 18px;
        line-height: 1.4;
        text-align: center;
        color: #161b1d;
        font-weight: 500;
    }

    .ed-categories .ed-category .ed-category-title a:hover {
        color: var(--global-palette-btn-bg);
    }
</style>
