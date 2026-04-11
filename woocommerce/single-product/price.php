<?php
/**
 * Single Product Price
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/price.php.
 *
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $product;

$whatsapp_number = get_option( 'easydokan_store_mobile', '8801841650668' ); // Fallback to image number if not set
$product_url     = get_permalink( $product->get_id() );
$messenger_url   = get_option( 'easydokan_messenger_url', '' );
$whatsapp_msg    = rawurlencode( "I want to purchase this product: " . $product_url );
$whatsapp_url    = 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $whatsapp_number ) . '?text=' . $whatsapp_msg;

// Get product data
$product_id  = $product->get_id();
$is_variable = $product->is_type( 'variable' );
$variations  = $is_variable ? $product->get_available_variations() : array();

/**
 * Handle Multiple Attributes for Variable Products
 */
$attributes_map     = array();
$default_attributes = array();

if ( $is_variable && ! empty( $variations ) ) {
	// Use the first variation as default
	$first_variation    = reset( $variations );
	$default_attributes = $first_variation['attributes'];

	foreach ( $variations as $variation ) {
		foreach ( $variation['attributes'] as $attr_name => $attr_value ) {
			// Initialize the attribute group if not exists
			if ( ! isset( $attributes_map[ $attr_name ] ) ) {
				$attributes_map[ $attr_name ] = array();
			}

			// Add the value to the group (ensure uniqueness)
			if ( ! in_array( $attr_value, $attributes_map[ $attr_name ] ) ) {
				$attributes_map[ $attr_name ][] = $attr_value;
			}
		}
	}
}

// echo "<pre>";
// print_r($attributes_map);
// echo "</pre>";

//error_log( var_export( $variations, true ) )
?>

<div class="ed-product-price" id="ed-product-container" data-product-id="<?php echo esc_attr( $product_id ); ?>"
     data-type="<?php echo esc_attr( $product->get_type() ); ?>">

	<?php if ( $is_variable ): ?>
        <div class="ed-variation-selector">
			<?php foreach ( $attributes_map as $attr_name => $options ):
				$label = str_replace( 'attribute_', '', $attr_name );
				$label = ucwords( str_replace( array( '-', '_' ), ' ', $label ) );
				?>
                <div class="ed-variation-group" data-attribute-name="<?php echo esc_attr( $attr_name ); ?>">
                    <div class="ed-variation-label"><?php echo esc_html( $label ); ?>: <span style="color: #ef4444;">*</span>
                    </div>
                    <div class="ed-variation-options">
						<?php foreach ( $options as $option ):
							$is_active = ( isset( $default_attributes[ $attr_name ] ) && $default_attributes[ $attr_name ] === $option );
							?>
                            <button type="button" class="ed-variation-btn <?php echo $is_active ? 'active' : ''; ?>"
                                    data-value="<?php echo esc_attr( $option ); ?>">
								<?php echo esc_html( $option ); ?>
                            </button>
						<?php endforeach; ?>
                    </div>
                </div>
			<?php endforeach; ?>
        </div>
	<?php endif; ?>

    <div class="ed-price-box">
        <div class="ed-price-row">
            <span class="ed-regular-price" id="ed-display-regular-price"></span>
            <span class="ed-sale-price" id="ed-display-sale-price"></span>
            <span class="ed-discount-badge" id="ed-display-discount"></span>
        </div>
    </div>

    <div class="ed-actions-row">
        <div class="ed-qty-container">
            <button type="button" class="ed-qty-btn ed-qty-minus">-</button>
            <input type="number" id="ed-qty" class="ed-qty-input" value="1" min="1">
            <button type="button" class="ed-qty-btn ed-qty-plus">+</button>
        </div>
        <button type="button" class="ed-sp-btn cart" id="ed-add-to-cart"
                data-redirect="<?php echo wc_get_cart_url(); ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Add to cart
        </button>
        <button type="button" class="ed-sp-btn buy-now" id="ed-buy-now"
                data-redirect="<?php echo wc_get_checkout_url(); ?>">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                 stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1"/>
                <circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            Buy Now
        </button>
    </div>
    <div class="ed-actions-row">
        <a href="<?php echo esc_url( $whatsapp_url ); ?>" class="ed-sp-btn whatsapp" target="_blank">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                <path
                        d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.464 3.488">
                </path>
            </svg>
			<?php echo esc_html( $whatsapp_number ); ?>
        </a>
        <a href="<?php echo esc_url( $messenger_url ); ?>" class="ed-sp-btn messenger" target="_blank">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M8 0C3.49296 0 0 3.30144 0 7.7603C0 10.0927 0.95612 12.108 2.51268 13.5004C2.64308 13.6179 2.72192 13.7805 2.72836 13.9559L2.77184 15.3789C2.78632 15.8328 3.25472 16.1274 3.67004 15.9455L5.25716 15.2453C5.39143 15.1858 5.54206 15.1744 5.68372 15.2131C6.41288 15.4143 7.19036 15.5205 8 15.5205C12.507 15.5205 16 12.2191 16 7.76026C16 3.30144 12.507 0 8 0Z"
                      fill="white"/>
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M3.19686 10.0299L5.54694 6.30191C5.92038 5.70955 6.72198 5.56143 7.28214 5.98158L9.15094 7.38361C9.23443 7.44607 9.33592 7.47968 9.44015 7.47939C9.54437 7.4791 9.64568 7.44493 9.72882 7.38201L12.2528 5.46646C12.5892 5.21056 13.0303 5.61461 12.8033 5.97193L10.4548 9.69832C10.0813 10.2907 9.27974 10.4388 8.71958 10.0187L6.85078 8.61662C6.7673 8.55417 6.6658 8.52055 6.56157 8.52084C6.45735 8.52113 6.35604 8.55531 6.2729 8.61823L3.74734 10.5354C3.41094 10.7913 2.96986 10.3873 3.19686 10.0299Z"
                      fill="#006AFF"/>
            </svg>
            Message on Facebook
        </a>
    </div>


</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.getElementById('ed-product-container');
        const labels = {
            regularPrice: document.getElementById('ed-display-regular-price'),
            salePrice: document.getElementById('ed-display-sale-price'),
            discount: document.getElementById('ed-display-discount'),
        };
        const qtyInput = document.getElementById('ed-qty');
        const qtyMinusBtn = document.querySelector('.ed-qty-minus');
        const qtyPlusBtn = document.querySelector('.ed-qty-plus');
        const addToCartBtn = document.getElementById('ed-add-to-cart');
        const buyNowBtn = document.getElementById('ed-buy-now');

        qtyMinusBtn.addEventListener('click', function () {
            let currentValue = parseInt(qtyInput.value) || 1;
            let minValue = parseInt(qtyInput.min) || 1;
            if (currentValue > minValue) {
                qtyInput.value = currentValue - 1;
            }
        });

        qtyPlusBtn.addEventListener('click', function () {
            let currentValue = parseInt(qtyInput.value) || 1;
            let maxValue = parseInt(qtyInput.max) || 9999;
            if (currentValue < maxValue) {
                qtyInput.value = currentValue + 1;
            }
        });

        const variations = <?php echo json_encode( $variations ); ?>;
        const isVariable = container.dataset.type === 'variable';

        let currentVariationId = null;
        let selectedAttributes = {};

        function formatPrice(price) {
            return '৳' + parseFloat(price).toLocaleString();
        }

        function updateDisplay(price, regularPrice, stock) {
            labels.salePrice.textContent = formatPrice(price);

            if (parseFloat(regularPrice) > parseFloat(price)) {
                labels.regularPrice.style.display = 'inline';
                labels.regularPrice.textContent = formatPrice(regularPrice);

                const percent = Math.round(((regularPrice - price) / regularPrice) * 100);
                labels.discount.style.display = 'inline';
                labels.discount.textContent = percent + '% OFF';
            } else {
                labels.regularPrice.style.display = 'none';
                labels.discount.style.display = 'none';
            }
        }

        function findMatchingVariation() {
            return variations.find(v => {
                return Object.entries(selectedAttributes).every(([key, value]) => {
                    return v.attributes[key] === value;
                });
            });
        }

        if (isVariable) {
            const groups = document.querySelectorAll('.ed-variation-group');

            groups.forEach(group => {
                const attrName = group.dataset.attributeName;
                const buttons = group.querySelectorAll('.ed-variation-btn');

                buttons.forEach(btn => {
                    const val = btn.dataset.value;

                    if (btn.classList.contains('active')) {
                        selectedAttributes[attrName] = val;
                    }

                    btn.addEventListener('click', function () {
                        buttons.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        selectedAttributes[attrName] = val;

                        const match = findMatchingVariation();
                        if (match) {
                            currentVariationId = match.variation_id;
                            updateDisplay(match.display_price, match.display_regular_price, match.is_in_stock ? match.max_qty : 0);
                        }
                    });
                });
            });

            // Trigger initial match
            const initialMatch = findMatchingVariation();
            if (initialMatch) {
                currentVariationId = initialMatch.variation_id;
                updateDisplay(initialMatch.display_price, initialMatch.display_regular_price, initialMatch.is_in_stock ? initialMatch.max_qty : 0);
            }
        } else {
            updateDisplay(
                '<?php echo $product->get_price(); ?>',
                '<?php echo $product->get_regular_price(); ?>',
                '<?php echo $product->get_stock_quantity() ?: 0; ?>'
            );
        }

        function triggerAddToCart(productId, quantity, variationId, btnElement) {
            btnElement.style.opacity = '0.6';
            btnElement.style.pointerEvents = 'none';

            const formData = new FormData();
            // Crucial: Pass the exact target ID as 'add-to-cart', which skips the need for verbose attribute mapping for variations.
            const targetId = variationId ? variationId : productId;
            formData.append('add-to-cart', targetId);
            formData.append('quantity', quantity);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                const redirectUrl = btnElement.getAttribute('data-redirect');
                if (redirectUrl) {
                    window.location.href = redirectUrl;
                } else {
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error adding to cart:', error);
                btnElement.style.opacity = '1';
                btnElement.style.pointerEvents = 'auto';
                alert('Could not add product to cart. Please try again.');
            });
        }

        addToCartBtn.addEventListener('click', function () {
            const id = isVariable ? currentVariationId : container.dataset.productId;
            if (isVariable && !id) {
                alert('Please select all options');
                return;
            }
            triggerAddToCart(container.dataset.productId, qtyInput.value, isVariable ? id : null, this);
        });

        buyNowBtn.addEventListener('click', function () {
            const id = isVariable ? currentVariationId : container.dataset.productId;
            if (isVariable && !id) {
                alert('Please select all options');
                return;
            }
            triggerAddToCart(container.dataset.productId, qtyInput.value, isVariable ? id : null, this);
        });
    });
</script>