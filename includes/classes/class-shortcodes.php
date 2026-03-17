<?php
/**
 * All Shortcodes here
 */

defined( 'ABSPATH' ) || exit;

class ED_CONNECT_SHORTCODES {
	public function __construct() {
		add_shortcode( 'ed_address_footer', array( $this, 'render_address_footer' ) );
		add_shortcode( 'ed_categories', array( $this, 'render_categories' ) );
		add_shortcode( 'ed_business_name', array( $this, 'render_business_name' ) );
		add_shortcode( 'ed_founding_year', array( $this, 'render_founding_year' ) );
		add_shortcode( 'ed_product_category', array( $this, 'render_product_category' ) );
		add_shortcode( 'ed_local_address', array( $this, 'render_local_address' ) );
	}

	public function render_local_address() {
		$store_address   = get_option( 'woocommerce_store_address', '' );
		$store_address_2 = get_option( 'woocommerce_store_address_2', '' );
		$store_city      = get_option( 'woocommerce_store_city', '' );
		$store_postcode  = get_option( 'woocommerce_store_postcode', '' );

		return sprintf( '%s, %s, %s %s', $store_address, $store_address_2, $store_city, $store_postcode );
	}

	public function render_product_category() {
		$categories_html = [];
		$categories_args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
		);

		foreach ( get_terms( $categories_args ) as $category ) {
			$categories_html[] = sprintf( '<a href="%s">%s</a>', $category->slug, $category->name );
		}

		return implode( ', ', $categories_html );
	}

	public function render_founding_year() {
		return get_option( 'easydokan_founding_year', current_time( 'Y' ) );
	}

	public function render_business_name() {
		return get_option( 'easydokan_store_name', 'Default Store' );
	}

	public function render_categories( $atts = array() ) {
		$theme = $atts['theme'] ?? '1';

		ob_start();
		include get_stylesheet_directory() . '/templates/shortcodes/categories-' . $theme . '.php';

		return ob_get_clean();
	}

	public function render_address_footer( $atts ) {
		ob_start();
		include get_stylesheet_directory() . '/templates/shortcodes/address-footer.php';

		return ob_get_clean();
	}
}

new ED_CONNECT_SHORTCODES();