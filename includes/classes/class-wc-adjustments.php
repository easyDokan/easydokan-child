<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ED_CONNECT_WC_Adjustments {

	public function __construct() {
		add_filter( 'option_woocommerce_cod_settings', array( $this, 'force_cod_enabled' ) );
		add_filter( 'post_thumbnail_html', array( $this, 'override_product_thumbnail' ), 10, 5 );
		add_action( 'woocommerce_before_subcategory_title', array( $this, 'override_category_thumbnail' ), 1 );

		if ( defined( 'EASYDOKAN_CONTROL' ) && EASYDOKAN_CONTROL == false ) {
			add_action( 'admin_menu', array( $this, 'hide_woocommerce_menu' ), 999 );
			add_action( 'admin_init', array( $this, 'disable_access_to_admin_pages' ), 999 );
		}
	}

	public function disable_access_to_admin_pages() {
		global $pagenow;

		$restricted_urls  = [
			'plugins.php',
			'plugin-install.php',
			'plugin-editor.php',
			'themes.php',
			'theme-install.php',
			'theme-editor.php'
		];
		$restricted_pages = [
			'wc-settings',
			'wc-admin',
			'hello-elementor',
		];
		$current_page     = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';

		if ( in_array( $pagenow, $restricted_urls ) || in_array( $current_page, $restricted_pages ) ) {
			wp_die( 'Access to this page has been disabled for security reasons.', 'Access Denied', [ 'response' => 403 ] );
		}

		if ( isset( $_GET['page'] ) && $_GET['page'] === 'wc-admin' ) {
			if ( isset( $_GET['path'] ) && ( strpos( $_GET['path'], '/analytics' ) !== false || strpos( $_GET['path'], '/payments' ) !== false ) ) {
				wp_die( 'Access Denied.', 'Access Denied', [ 'response' => 403 ] );
			}
		}
	}

	public function hide_woocommerce_menu() {
		remove_menu_page( 'woocommerce' );
		remove_menu_page( 'woocommerce-marketing' );
		remove_menu_page( 'plugins.php' );
		remove_menu_page( 'themes.php' );
		remove_menu_page( 'wc-admin&path=/payments/overview' );
		remove_menu_page( 'wc-admin&path=/analytics/overview' );
		remove_menu_page( 'hello-elementor' );

		remove_menu_page( 'admin.php?page=wc-settings&tab=checkout' );
		remove_menu_page( 'admin.php?page=wc-settings&tab=checkout&from=PAYMENTS_MENU_ITEM' );
		remove_menu_page( 'wc-admin&path=/payments/connect' );
	}

	public function override_category_thumbnail( $category ) {
		if ( isset( $category->term_id ) ) {
			$image_url = get_term_meta( $category->term_id, '_ed_category_thumbnail_url', true );

			if ( ! empty( $image_url ) ) {
				// If the external URL is set, we must remove the default hook to prevent double rendering
				remove_action( 'woocommerce_before_subcategory_title', 'woocommerce_subcategory_thumbnail', 10 );

				echo '<img src="' . esc_url( $image_url ) . '" alt="' . esc_attr( $category->name ) . '" />';
			}
		}
	}

	public function force_cod_enabled( $settings ) {
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings['enabled'] = 'yes';

		return $settings;
	}

	public function override_product_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		// Only run for products
		if ( get_post_type( $post_id ) !== 'product' ) {
			return $html;
		}

		$image_url = get_post_meta( $post_id, '_ed_product_thumbnail_url', true );

		if ( ! empty( $image_url ) ) {
			$alt = get_the_title( $post_id );

			// Generate standard img tag that handles WooCommerce sizing cleanly
			return sprintf(
				'<img src="%s" alt="%s" class="wp-post-image attachment-shop_catalog size-shop_catalog" loading="lazy" />',
				esc_url( $image_url ),
				esc_attr( $alt )
			);
		}

		return $html;
	}
}

new ED_CONNECT_WC_Adjustments();
