<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ED_CONNECT_Checkout_Manager {

    public function __construct() {
        // Enqueue checkout scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_scripts' ) );

        // Force BD Locale visibility definitions
        add_filter( 'woocommerce_get_country_locale', array( $this, 'override_bd_locale_visibility' ), 999, 1 );

        // Force Ship to Billing Address Only (Removes 'Ship to a different address')
        add_filter( 'wc_ship_to_billing_address_only', '__return_true' );

        // Modify checkout fields
        add_filter( 'woocommerce_checkout_fields', array( $this, 'custom_override_checkout_fields' ), 9999 );

        // Validate phone number
        add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_custom_checkout_fields' ), 10, 2 );

        // Inject dummy email if guest checkout is firing without an email
        add_action( 'woocommerce_checkout_process', array( $this, 'inject_dummy_billing_email' ) );

        // Inject custom CSS to hide specific native fields
        add_action( 'wp_head', array( $this, 'inject_checkout_styles' ) );

	    add_action( 'wp', array( $this, 'woocommerce_checkout' ) );
    }

	public function woocommerce_checkout() {
		if ( is_admin() ) {
			return;
		}

        // Checkout Page Hooks.
        remove_action( 'woocommerce_checkout_shipping', array( WC()->checkout(), 'checkout_form_shipping' ) );

        // Custom Coupon Relocation
        add_action( 'woocommerce_review_order_before_payment', array( $this, 'inject_custom_coupon_field' ) );
    }

    public function inject_custom_coupon_field() {
        echo '<div class="easydokan-checkout-coupon" style="margin-bottom:20px; border-top: 1px solid #d3ced2; padding-top: 20px;">
                <div class="coupon" style="display:flex; gap:10px;">
                    <input type="text" name="ed_coupon_code" class="input-text" placeholder="Coupon code" id="ed_coupon_code" value="" style="flex-grow:1;" />
                    <button type="button" class="button alt" name="apply_coupon" id="ed_apply_coupon" value="Apply coupon">Apply</button>
                    <div style="clear:both;"></div>
                </div>
              </div>';
    }

    public function inject_checkout_styles() {
        if ( is_checkout() ) {
            echo '<style>
                .easydokan-hidden-field { display: none !important; }
                .woocommerce-form-coupon-toggle { display: none !important; } /* Hide default WC top coupon toggle */
            </style>';
        }
    }

    public function enqueue_checkout_scripts() {

    }

    public function override_bd_locale_visibility( $locales ) {
        if ( ! isset( $locales['BD'] ) ) {
            $locales['BD'] = array();
        }

	    $locales['BD']['address_2'] = array(
		    'label'    => 'Thana',
		    'required' => true,
		    'hidden'   => false,
            'priority' => 50,
	    );
        $locales['BD']['city'] = array(
            'label'    => 'District',
            'required' => true,
            'hidden'   => false,
            'priority' => 40,
        );

	    $locales['BD']['state'] = array(
		    'label'    => 'Division',
		    'required' => true,
		    'hidden'   => false,
            'priority' => 30,
	    );

        $locales['BD']['address_1'] = array(
            'priority' => 60,
        );

        return $locales;
    }

    public function custom_override_checkout_fields( $fields ) {
        // 1. Remove unwanted fields
        unset( $fields['billing']['billing_last_name'] );
        unset( $fields['billing']['billing_company'] );
        unset( $fields['billing']['billing_postcode'] );
        unset( $fields['billing']['billing_email'] );
        
        // Remove Order Notes
        unset( $fields['order']['order_comments'] );

        // Hide Country, but do NOT unset it. Unsetting breaks WooCommerce native JS dependent location rendering rules.
        $fields['billing']['billing_country']['class'] = array( 'form-row-wide', 'hidden', 'easydokan-hidden-field' );

        // 2. Modify First Name (Make it Full Name)
        $fields['billing']['billing_first_name']['label'] = 'Full Name';
        $fields['billing']['billing_first_name']['class'] = array( 'form-row-wide' );
        $fields['billing']['billing_first_name']['clear'] = true;

        // 3. Modify Phone Number
        $fields['billing']['billing_phone']['class'] = array( 'form-row-wide' );
        $fields['billing']['billing_phone']['clear'] = true;
        $fields['billing']['billing_phone']['required'] = true;

        // 4. Modify Address logic for BD
        // We explicitly omit 'address-field' from the class arrays to block WooCommerce's native `address-i18n.js` from forcefully overwriting our selects back into text fields!

        // Division (formerly State)
        $fields['billing']['billing_state']['label'] = 'Division';
        $fields['billing']['billing_state']['type'] = 'select'; // WC typically uses country-state relationships, but we'll manually handle this in JS.
        $fields['billing']['billing_state']['options'] = array( '' => 'Select a Division' );
        $fields['billing']['billing_state']['required'] = true;
        $fields['billing']['billing_state']['class'] = array( 'form-row-first' ); // No address-field
        $fields['billing']['billing_state']['clear'] = false;
        
        // District (formerly City)
        $fields['billing']['billing_city']['label'] = 'District';
        $fields['billing']['billing_city']['type'] = 'select';
        $fields['billing']['billing_city']['options'] = array( '' => 'Select a District' );
        $fields['billing']['billing_city']['required'] = true;
        $fields['billing']['billing_city']['class'] = array( 'form-row-last' ); // No address-field
        $fields['billing']['billing_city']['clear'] = true;

        // Thana (formerly Address 2)
        $fields['billing']['billing_address_2']['label'] = 'Thana';
        $fields['billing']['billing_address_2']['type'] = 'select';
        $fields['billing']['billing_address_2']['options'] = array( '' => 'Select a Thana' );
        $fields['billing']['billing_address_2']['required'] = true;
        $fields['billing']['billing_address_2']['class'] = array( 'form-row-wide' ); // No address-field
        $fields['billing']['billing_address_2']['clear'] = true;

        // Street Address (formerly Address 1)
        $fields['billing']['billing_address_1']['label'] = 'Street Address';
        $fields['billing']['billing_address_1']['placeholder'] = 'House number and street name';
        $fields['billing']['billing_address_1']['required'] = true;
        $fields['billing']['billing_address_1']['class'] = array( 'form-row-wide' );

        // Reorder fields to make sense visually
        $fields['billing']['billing_first_name']['priority'] = 10;
        $fields['billing']['billing_phone']['priority'] = 20;
        $fields['billing']['billing_state']['priority'] = 30; // Division
        $fields['billing']['billing_city']['priority'] = 40;  // District
        $fields['billing']['billing_address_2']['priority'] = 50; // Thana
        $fields['billing']['billing_address_1']['priority'] = 60; // Street Address

        return $fields;
    }

    public function inject_dummy_billing_email() {
        // WooCommerce core REQUIRES a billing_email to create the order class correctly.
        // Since we unset it from the frontend UI UI entirely, we spoof it during the request if it's empty so the WC cart processor does not crash.
        if ( empty( $_POST['billing_email'] ) ) {
            $phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : 'guest';
            $_POST['billing_email'] = $phone . '@localhost.local'; 
        }
        
        // Fallback for country to avoid calculation errors
        if ( empty( $_POST['billing_country'] ) ) {
            $_POST['billing_country'] = 'BD';
        }
    }

    public function validate_custom_checkout_fields( $data, $errors ) {
        if ( isset( $data['billing_phone'] ) && ! empty( $data['billing_phone'] ) ) {
            $phone = $data['billing_phone'];
            // Exactly 11 digits logic mapping to BDT MSISDN configurations
            if ( ! preg_match( '/^[0-9]{11}$/', $phone ) ) {
                $errors->add( 'validation', '<strong>Mobile Number</strong> must be exactly 11 digits long.' );
            }
        }
    }
}

new ED_CONNECT_Checkout_Manager();