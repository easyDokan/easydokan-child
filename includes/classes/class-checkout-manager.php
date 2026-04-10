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

		// Inject JS to handle dependent locations
		add_action( 'wp_footer', array( $this, 'inject_checkout_scripts' ) );

		add_action( 'wp', array( $this, 'woocommerce_checkout' ) );

		// Custom Checkout Shortcode
		add_shortcode( 'easydokan_custom_checkout', array( $this, 'render_custom_checkout' ) );

		// Custom Checkout AJAX Processor
		add_action( 'wp_ajax_easydokan_submit_custom_checkout', array( $this, 'process_custom_checkout' ) );
		add_action( 'wp_ajax_nopriv_easydokan_submit_custom_checkout', array( $this, 'process_custom_checkout' ) );
		// Remove the 'Added to Cart' WooCommerce message
		add_filter( 'wc_add_to_cart_message_html', '__return_false' );
	}

	public function render_custom_checkout() {
		if ( WC()->cart->is_empty() ) {
			return '<p class="cart-empty woocommerce-info">Your cart is currently empty. <a class="button wc-backward" href="' . esc_url( apply_filters( 'woocommerce_return_to_shop_redirect', wc_get_page_permalink( 'shop' ) ) ) . '">Return to shop</a></p>';
		}

		$base_loc = get_option( 'easydokan_shipping_base_location', 'Dhaka' );

		$inside_reg  = (float) get_option( 'easydokan_shipping_inside_regular_cost', 0 );
		$inside_sale = (float) get_option( 'easydokan_shipping_inside_sale_cost', 0 );
		$cost_inside = $inside_sale > 0 ? $inside_sale : $inside_reg;

		$outside_reg  = (float) get_option( 'easydokan_shipping_outside_regular_cost', 0 );
		$outside_sale = (float) get_option( 'easydokan_shipping_outside_sale_cost', 0 );
		$cost_outside = $outside_sale > 0 ? $outside_sale : $outside_reg;

		// Calculate Subtotal float dynamically
		$raw_subtotal = 0;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$raw_subtotal += $cart_item['line_subtotal'] + $cart_item['line_subtotal_tax'];
		}
		$subtotal_html = WC()->cart->get_cart_subtotal(); // Gets formatted string (e.g., `<span...>100৳</span>`)

		ob_start();
		?>
        <style>
            .ed-checkout-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
                margin-top: 20px;
                align-items: start;
            }

            @media (max-width: 768px) {
                .ed-checkout-grid {
                    grid-template-columns: 1fr;
                }
            }

            .ed-box {
                background: #fafafa;
                border: 1px solid #eee;
                border-radius: 8px;
                padding: 25px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            }

            .ed-box-title {
                font-size: 18px;
                font-weight: 700;
                color: #1a365d;
                margin-bottom: 20px;
            }

            .ed-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                border: none;
            }

            .ed-table th {
                text-align: left;
                padding-bottom: 10px;
                border-bottom: 1px solid #ddd;
                color: #1a365d;
                border: none;
            }

            .ed-table td {
                padding: 15px 0;
                border-bottom: 1px solid #eee;
                vertical-align: middle;
                border: none;
                background-color: transparent !important;
            }

            .ed-table tfoot td {
                font-weight: 600;
                font-size: 15px;
                border: none;
            }

            .ed-table tfoot tr:last-child td {
                font-size: 18px;
                border-bottom: none;
            }

            .ed-product-thumb {
                width: 50px;
                height: 50px;
                border-radius: 4px;
                object-fit: cover;
                margin-right: 15px;
                border: 1px solid #eee;
            }

            .ed-product-meta {
                display: flex;
                align-items: center;
            }

            .ed-box input[type=date],
            .ed-box input[type=email],
            .ed-box input[type=number],
            .ed-box input[type=password],
            .ed-box input[type=search],
            .ed-box input[type=tel],
            .ed-box input[type=text],
            .ed-box input[type=url],
            .ed-box select,
            .ed-box textarea {
                width: 100%;
                padding: 12px 15px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                margin-bottom: 15px;
                font-size: 14px;
                background: #fff;
                transition: border-color 0.2s;
            }

            .ed-input:focus {
                outline: none;
                border-color: #1a365d;
            }

            .ed-radio-label {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 15px;
                border-radius: 6px;
                cursor: pointer;
                border: 1px solid transparent;
                transition: background-color 0.2s;
                background-color: #f1f5f9;
                border-color: #e2e8f0;
            }

            .ed-radio-label.selected {
                background-color: #e6ffea;
                border-color: #bbf7d0;
            }

            .ed-radio-label input {
                display: none;
            }

            .ed-btn {
                width: 100%;
                padding: 15px;
                background: var(--e-global-color-primary);
                color: #fff;
                border: none;
                border-radius: 6px;
                font-size: 16px;
                font-weight: 700;
                cursor: pointer;
                transition: background 0.2s;
            }

            .ed-btn:disabled {
                opacity: 0.7;
                cursor: not-allowed;
            }

            .ed-error {
                color: #dc2626;
                font-size: 13px;
                margin-top: -10px;
                margin-bottom: 10px;
                display: none;
            }

            .ed-cod-box {
                padding: 15px;
                border: 1px solid #e2e8f0;
                border-radius: 6px;
                background: #f8fafc;
                margin-bottom: 15px;
            }

            .ed-terms {
                font-size: 12px;
                color: #64748b;
                line-height: 1.5;
                margin-bottom: 20px !important;
            }

            .ed-shipping-regular {
                text-decoration: line-through;
                margin-right: 10px;
            }

            .ed-success-box {
                text-align: center;
                padding: 50px 30px;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 12px;
                display: none;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            }

            .ed-success-icon {
                font-size: 64px;
                color: #10b981;
                line-height: 1;
                margin-bottom: 24px;
                animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            }

            @keyframes popIn {
                0% {
                    transform: scale(0);
                    opacity: 0;
                }

                100% {
                    transform: scale(1);
                    opacity: 1;
                }
            }

            .ed-success-title {
                font-size: 26px;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 12px;
                letter-spacing: -0.5px;
            }

            .ed-success-text {
                font-size: 15px;
                color: #64748b;
                line-height: 1.6;
                margin-bottom: 24px !important;
            }

            .ed-tracking-box {
                background: #f8fafc;
                border: 1px dashed #cbd5e1;
                border-radius: 8px;
                padding: 16px;
                display: inline-block;
                width: 100%;
                max-width: 350px;
                margin-bottom: 30px;
            }

            .ed-tracking-label {
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                color: #94a3b8;
                font-weight: 600;
                margin-bottom: 8px;
                display: block;
            }

            .ed-tracking-id {
                font-size: 24px;
                font-weight: 800;
                color: #334155;
                font-family: monospace;
            }

            .ed-btn-track {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 12px 24px;
                background: var(--e-global-color-primary, #3b82f6);
                color: #ffffff !important;
                font-weight: 600;
                font-size: 14px;
                border-radius: 6px;
                text-decoration: none !important;
                transition: opacity 0.2s;
            }

            .ed-btn-track:hover {
                opacity: 0.9;
            }
        </style>

        <div class="easydokan-custom-checkout">
            <!-- Inline Success Message -->
            <div id="ed_success_msg" class="ed-success-box">
                <div class="ed-success-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h2 class="ed-success-title">Order Complete!</h2>
                <p class="ed-success-text">Your order has been successfully placed. We've sent a confirmation email to you.</p>

                <div class="ed-tracking-box">
                    <span class="ed-tracking-label">Your Order ID</span>
                    <div class="ed-tracking-id" id="ed_success_tracking_id">#ERR</div>
                </div>

                <div>
                    <a href="#" id="ed_success_tracking_link" class="ed-btn-track">Track Your Order</a>
                </div>
            </div>

            <!-- Main Checkout UI -->
            <div class="ed-checkout-grid">

                <!-- Left Panel: Cart & Order Review -->
                <div class="ed-box">
                    <h3 class="ed-box-title">শপিং ব্যাগ (<?php echo WC()->cart->get_cart_contents_count(); ?> টি পন্য)</h3>
                    <table class="ed-table">
                        <thead>
                        <tr>
                            <th>পন্যের নাম</th>
                            <th style="text-align: right;">মোট</th>
                        </tr>
                        </thead>
                        <tbody>
						<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
							$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

							if ( ! $_product instanceof WC_Product ) {
								continue;
							}

							$parent_id     = $_product->get_parent_id();
							$thumbnail_url = get_post_meta( $_product->get_id(), '_ed_product_thumbnail_url', true );
							$thumbnail_url = empty( $thumbnail_url ) && ! empty( $parent_id ) ? get_post_meta($parent_id, '_ed_product_thumbnail_url', true ) : $thumbnail_url;
                            ?>
                            <tr>
                                <td>
                                    <div class="ed-product-meta">
                                        <div style="width: 50px; height: 50px; overflow: hidden; border-radius: 4px; margin-right: 15px; border: 1px solid #eee;">
                                            <img src="<?php echo $thumbnail_url; ?>" alt="<?php echo $_product->get_title() ?>">
                                        </div>
                                        <div>
                                            <a href="<?php echo $_product->get_permalink(); ?>"><strong><?php echo $_product->get_name(); ?>
                                                    x <?php echo $cart_item['quantity']; ?></strong></a>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
									<?php echo WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ); ?>
                                </td>
                            </tr>
						<?php } ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <td>মোট মূল্য</td>
                            <td style="text-align: right;"><?php echo $subtotal_html; ?></td>
                        </tr>
                        <tr>
                            <td>পরিবহন খরচ</td>
                            <td style="text-align: right;" id="ed_shipping_preview">
                                <span class="ed-shipping-regular"><?php echo wc_price( $inside_reg ); ?></span>
                                <span class="ed-shipping-sale"><?php echo wc_price( $cost_inside ); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <td>সর্বমোট মূল্য</td>
                            <td style="text-align: right;" id="ed_total_preview">
								<?php echo wc_price( $raw_subtotal + $cost_inside ); ?>
                            </td>
                        </tr>
                        </tfoot>
                    </table>

                    <div class="ed-cod-box">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; font-weight: 500;">
                            পন্য হাতে পেয়ে মূল্য পরিশোধ
                        </label>
                    </div>

                    <p class="ed-terms">Your personal data will be used to process your order, support your experience
                        throughout this website, and for other purposes described in our privacy policy.</p>

                    <button type="button" id="ed_confirm_order" class="ed-btn">আপনার অর্ডার নিশ্চিত করুন</button>
                </div>

                <!-- Right Panel: Address & Selection -->
                <div class="ed-box">
                    <h3 class="ed-box-title">আপনার নাম ও ঠিকানা দিন</h3>

                    <input type="text" id="ed_cf_name" class="ed-input" placeholder="আপনার নাম লিখুন"/>
                    <div id="ed_cf_name_error" class="ed-error">নাম দেওয়া আবশ্যক</div>

                    <input type="text" id="ed_cf_address" class="ed-input"
                           placeholder="হাউস নাম্বার, রোড, ইউনিট/ফ্ল্যাট, থানা, জেলা"/>
                    <div id="ed_cf_address_error" class="ed-error">সম্পূর্ণ ঠিকানা দেওয়া আবশ্যক</div>

                    <input type="tel" id="ed_cf_phone" class="ed-input" placeholder="আপনার মোবাইল নাম্বার লিখুন"/>
                    <div id="ed_cf_phone_error" class="ed-error">সঠিক ১১ ডিজিটের মোবাইল নাম্বার প্রয়োজন</div>

                    <h3 class="ed-box-title" style="margin-top: 10px; margin-bottom: 15px;">আপনার এরিয়া সিলেক্ট করুন</h3>

                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <label class="ed-radio-label inside selected">
                            <span>
                                <input type="radio" name="ed_cf_shipping_zone" value="inside"
                                       data-cost-reg="<?php echo esc_attr( $inside_reg ); ?>"
                                       data-cost="<?php echo esc_attr( $cost_inside ); ?>" checked/>
                                <?php echo esc_html( $base_loc ); ?> এর ভিতরে
                            </span>
                            <span style="font-weight: 700;">
                                <?php if ( $inside_sale > 0 ): ?>
                                    <del style="color: #999; font-size: 13px; font-weight: 400; margin-right: 5px;">Tk
                                        <?php echo esc_html( number_format( $inside_reg, 2 ) ); ?></del>
                                <?php endif; ?>
                                Tk <?php echo esc_html( number_format( $cost_inside, 2 ) ); ?>
                            </span>
                        </label>

                        <label class="ed-radio-label outside">
                            <span>
                                <input type="radio" name="ed_cf_shipping_zone" value="outside"
                                       data-cost-reg="<?php echo esc_attr( $outside_reg ); ?>"
                                       data-cost="<?php echo esc_attr( $cost_outside ); ?>"/>
                                <?php echo esc_html( $base_loc ); ?> এর বাহিরে
                            </span>
                            <span style="font-weight: 700;">
                                <?php if ( $outside_sale > 0 ): ?>
                                    <del style="color: #999; font-size: 13px; font-weight: 400; margin-right: 5px;">Tk
                                        <?php echo esc_html( number_format( $outside_reg, 2 ) ); ?></del>
                                <?php endif; ?>
                                Tk <?php echo esc_html( number_format( $cost_outside, 2 ) ); ?>
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                var subTotal = parseFloat("<?php echo $raw_subtotal; ?>");

                // Handle radio click to update totals visually
                $('input[name="ed_cf_shipping_zone"]').on('change', function () {
                    $('.ed-radio-label').removeClass('selected');
                    $(this).closest('.ed-radio-label').addClass('selected');

                    var cost = parseFloat($(this).attr('data-cost')) || 0;
                    var cost_reg = parseFloat($(this).attr('data-cost-reg')) || 0;
                    var total = subTotal + cost;


                    $('#ed_shipping_preview').find('.ed-shipping-regular').html(wc_price_format(cost_reg));
                    $('#ed_shipping_preview').find('.ed-shipping-sale').html(wc_price_format(cost));
                    $('#ed_total_preview').html(wc_price_format(total));
                });

                function wc_price_format(amount) {
                    // Formatting specific to BDT standard ৳
                    return amount.toFixed(2) + '৳';
                }

                // Submit order
                $('#ed_confirm_order').on('click', function (e) {
                    e.preventDefault();
                    var btn = $(this);

                    // Clear errors
                    $('.ed-error').hide();
                    $('.ed-input').css('border-color', '#e2e8f0');

                    var name = $('#ed_cf_name').val().trim();
                    var address = $('#ed_cf_address').val().trim();
                    var phone = $('#ed_cf_phone').val().trim();
                    var shippingZone = $('input[name="ed_cf_shipping_zone"]:checked').val();

                    var hasError = false;

                    if (!name) {
                        $('#ed_cf_name_error').show();
                        $('#ed_cf_name').css('border-color', '#dc2626');
                        hasError = true;
                    }
                    if (!address) {
                        $('#ed_cf_address_error').show();
                        $('#ed_cf_address').css('border-color', '#dc2626');
                        hasError = true;
                    }
                    if (!phone || !/^[0-9]{11}$/.test(phone)) {
                        $('#ed_cf_phone_error').show();
                        $('#ed_cf_phone').css('border-color', '#dc2626');
                        hasError = true;
                    }

                    if (hasError) return;

                    btn.prop('disabled', true).text('প্রসেস করা হচ্ছে...');

                    $.ajax({
                        url: "<?php echo admin_url( 'admin-ajax.php' ); ?>",
                        type: "POST",
                        data: {
                            action: "easydokan_submit_custom_checkout",
                            name: name,
                            address: address,
                            phone: phone,
                            shipping_zone: shippingZone,
                            security: "<?php echo wp_create_nonce( 'easydokan_custom_checkout_nonce' ); ?>"
                        },
                        success: function (response) {
                            if (response.success && response.data) {
                                // Provide success UI inline
                                $('.ed-checkout-grid').fadeOut(400, function () {
                                    if (response.data.backend_order_id) {
                                        var fullId = response.data.backend_order_id;
                                        var shortId = fullId.substring(fullId.length - 8).toUpperCase();
                                        $('#ed_success_tracking_id').text('#' + shortId);

                                        // Create a tracking link mapping appropriately
                                        var baseUrl = response.data.tracking_base_url || window.location.origin;
                                        var trackUrl = baseUrl + '/track/' + shortId;
                                        $('#ed_success_tracking_link').attr('href', trackUrl);
                                    } else if (response.data.order_id) {
                                        $('#ed_success_tracking_id').text('#' + response.data.order_id);
                                        $('#ed_success_tracking_link').hide();
                                    }
                                    $('#ed_success_msg').fadeIn(400);
                                });
                                $('html, body').animate({scrollTop: $('.easydokan-custom-checkout').offset().top - 100}, 500);
                            } else {
                                alert(response.data || 'Something went wrong while verifying backend data.');
                                btn.prop('disabled', false).text('আপনার অর্ডার নিশ্চিত করুন');
                            }
                        },
                        error: function () {
                            alert('Network Error! Please try again.');
                            btn.prop('disabled', false).text('আপনার অর্ডার নিশ্চিত করুন');
                        }
                    });
                });
            });
        </script>
		<?php
		return ob_get_clean();
	}

	public function process_custom_checkout() {
		check_ajax_referer( 'easydokan_custom_checkout_nonce', 'security' );

		if ( WC()->cart->is_empty() ) {
			wp_send_json_error( 'Cart is empty.' );
		}

		$name    = sanitize_text_field( $_POST['name'] ?? '' );
		$address = sanitize_text_field( $_POST['address'] ?? '' );
		$phone   = sanitize_text_field( $_POST['phone'] ?? '' );
		$zone    = sanitize_text_field( $_POST['shipping_zone'] ?? 'inside' );

		if ( empty( $name ) || empty( $address ) || empty( $phone ) ) {
			wp_send_json_error( 'Fields cannot be empty.' );
		}

		try {
			$order = wc_create_order();

			// Setup Addresses
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

						$variant_id = null;
						if ( $product->is_type('variation') ) {
							$variant_id = get_post_meta( $product->get_id(), '_ezd_variant_id', true );
						}

						$products_payload[] = array(
							'product_id'    => $ezd_id,
							'variant_id'    => $variant_id,
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

			// Empty standard WC cart
			WC()->cart->empty_cart();

			wc_clear_notices();

			// Return Success Payload INLINE
			$frontend_url  = get_option( 'easydokan_frontend_app_url' );
			$tracking_base = $frontend_url ? preg_replace( '/\/$/', '', $frontend_url ) : '';

			wp_send_json_success( array(
				'order_id'          => $order->get_id(),
				'backend_order_id'  => isset( $backend_order_id ) ? $backend_order_id : null,
				'tracking_base_url' => $tracking_base
			) );

		} catch ( Exception $e ) {
			wp_send_json_error( 'Checkout failure: ' . $e->getMessage() );
		}
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

	public function inject_checkout_scripts() {
		if ( is_checkout() && ! is_order_received_page() ) {
			$json_path    = get_stylesheet_directory() . '/assets/json/bd-locations.json';
			$bd_locations = file_exists( $json_path ) ? file_get_contents( $json_path ) : '{}';

			?>
            <script>
                jQuery(document).ready(function ($) {
                    var bdLocations = <?php echo $bd_locations; ?>;

                    function updateSelect(selectId, optionsArray, placeholder, currentValue) {
                        var select = $('#' + selectId);
                        if (select.length === 0) return;

                        select.empty();
                        select.append('<option value="">' + placeholder + '</option>');

                        if (optionsArray && optionsArray.length > 0) {
                            optionsArray.forEach(function (opt) {
                                var selected = (opt === currentValue) ? ' selected="selected"' : '';
                                select.append('<option value="' + opt + '"' + selected + '>' + opt + '</option>');
                            });
                        }

                        if (select.hasClass('select2-hidden-accessible')) {
                            select.trigger('change.select2');
                        }
                    }

                    function refreshDependentDropdowns() {
                        var divSelect = $('#billing_state');
                        var distSelect = $('#billing_city');
                        var thanaSelect = $('#billing_address_2');

                        var savedDiv = divSelect.val() || divSelect.attr('data-current') || '';
                        var savedDist = distSelect.val() || distSelect.attr('data-current') || '';
                        var savedThana = thanaSelect.val() || thanaSelect.attr('data-current') || '';

                        // Divisions
                        var divisions = Object.keys(bdLocations).sort();
                        updateSelect('billing_state', divisions, 'Select a Division', savedDiv);

                        // Districts
                        if (savedDiv && bdLocations[savedDiv]) {
                            var districts = Object.keys(bdLocations[savedDiv]).sort();
                            updateSelect('billing_city', districts, 'Select a District', savedDist);
                        } else {
                            updateSelect('billing_city', [], 'Select a District', '');
                        }

                        // Thanas
                        if (savedDiv && savedDist && bdLocations[savedDiv][savedDist]) {
                            var thanas = bdLocations[savedDiv][savedDist].sort();
                            updateSelect('billing_address_2', thanas, 'Select a Thana', savedThana);
                        } else {
                            updateSelect('billing_address_2', [], 'Select a Thana', '');
                        }
                    }

                    function bindEvents() {
                        $(document.body).on('change', '#billing_state', function () {
                            $(this).attr('data-current', $(this).val());
                            $('#billing_city').attr('data-current', '').val('');
                            $('#billing_address_2').attr('data-current', '').val('');
                            refreshDependentDropdowns();
                        });

                        $(document.body).on('change', '#billing_city', function () {
                            $(this).attr('data-current', $(this).val());
                            $('#billing_address_2').attr('data-current', '').val('');
                            refreshDependentDropdowns();
                        });

                        $(document.body).on('change', '#billing_address_2', function () {
                            $(this).attr('data-current', $(this).val());
                        });
                    }

                    $(document.body).on('updated_checkout', function () {
                        refreshDependentDropdowns();
                    });

                    bindEvents();
                    refreshDependentDropdowns();
                });
            </script>
			<?php
		}
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
		$locales['BD']['city']      = array(
			'label'    => 'District',
			'required' => true,
			'hidden'   => false,
			'priority' => 40,
		);

		// echo "<pre>";
		// print_r($locales['BD']);
		// echo "</pre>";

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
		$fields['billing']['billing_phone']['class']    = array( 'form-row-wide' );
		$fields['billing']['billing_phone']['clear']    = true;
		$fields['billing']['billing_phone']['required'] = true;

		// 4. Modify Address logic for BD
		// We explicitly omit 'address-field' from the class arrays to block WooCommerce's native `address-i18n.js` from forcefully overwriting our selects back into text fields!

		// Division (formerly State)
		$fields['billing']['billing_state']['label']    = 'Division';
		$fields['billing']['billing_state']['type']     = 'select'; // WC typically uses country-state relationships, but we'll manually handle this in JS.
		$fields['billing']['billing_state']['options']  = array( '' => 'Select a Division' );
		$fields['billing']['billing_state']['required'] = true;
		$fields['billing']['billing_state']['class']    = array( 'form-row-first', 'update_totals_on_change' ); // No address-field
		$fields['billing']['billing_state']['clear']    = false;

		// District (formerly City)
		$fields['billing']['billing_city']['label']    = 'District';
		$fields['billing']['billing_city']['type']     = 'select';
		$fields['billing']['billing_city']['options']  = array( '' => 'Select a District' );
		$fields['billing']['billing_city']['required'] = true;
		$fields['billing']['billing_city']['class']    = array( 'form-row-last', 'update_totals_on_change' ); // No address-field
		$fields['billing']['billing_city']['clear']    = true;

		// Thana (formerly Address 2)
		$fields['billing']['billing_address_2']['label']    = 'Thana';
		$fields['billing']['billing_address_2']['type']     = 'select';
		$fields['billing']['billing_address_2']['options']  = array( '' => 'Select a Thana' );
		$fields['billing']['billing_address_2']['required'] = true;
		$fields['billing']['billing_address_2']['class']    = array( 'form-row-wide', 'update_totals_on_change' ); // No address-field
		$fields['billing']['billing_address_2']['clear']    = true;

		// Street Address (formerly Address 1)
		$fields['billing']['billing_address_1']['label']       = 'Street Address';
		$fields['billing']['billing_address_1']['placeholder'] = 'House number and street name';
		$fields['billing']['billing_address_1']['required']    = true;
		$fields['billing']['billing_address_1']['class']       = array( 'form-row-wide' );

		// Reorder fields to make sense visually
		$fields['billing']['billing_first_name']['priority'] = 10;
		$fields['billing']['billing_phone']['priority']      = 20;
		$fields['billing']['billing_state']['priority']      = 30; // Division
		$fields['billing']['billing_city']['priority']       = 40;  // District
		$fields['billing']['billing_address_2']['priority']  = 50; // Thana
		$fields['billing']['billing_address_1']['priority']  = 60; // Street Address

		return $fields;
	}

	public function inject_dummy_billing_email() {
		// WooCommerce core REQUIRES a billing_email to create the order class correctly.
		// Since we unset it from the frontend UI UI entirely, we spoof it during the request if it's empty so the WC cart processor does not crash.
		if ( empty( $_POST['billing_email'] ) ) {
			$phone                  = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : 'guest';
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