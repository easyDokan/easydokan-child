<?php
/**
 * Single Product Price
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/single-product/price.php.
 *
 * @package WooCommerce\Templates
 * @version 3.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

global $product;

$whatsapp_number = get_option('easydokan_store_mobile', '8801841650668'); // Fallback to image number if not set
$product_url = get_permalink($product->get_id());
$whatsapp_msg = rawurlencode("I want to purchase this product: " . $product_url);
$whatsapp_url = 'https://wa.me/' . preg_replace('/[^0-9]/', '', $whatsapp_number) . '?text=' . $whatsapp_msg;

// Get product data
$product_id = $product->get_id();
$is_variable = $product->is_type('variable');
$variations = $is_variable ? $product->get_available_variations() : array();

/**
 * Handle Multiple Attributes for Variable Products
 */
$attributes_map = array();
$default_attributes = array();

if ($is_variable && !empty($variations)) {
    // Use the first variation as default
    $first_variation = reset($variations);
    $default_attributes = $first_variation['attributes'];

    foreach ($variations as $variation) {
        foreach ($variation['attributes'] as $attr_name => $attr_value) {
            // Initialize the attribute group if not exists
            if (!isset($attributes_map[$attr_name])) {
                $attributes_map[$attr_name] = array();
            }

            // Add the value to the group (ensure uniqueness)
            if (!in_array($attr_value, $attributes_map[$attr_name])) {
                $attributes_map[$attr_name][] = $attr_value;
            }
        }
    }
}

// echo "<pre>";
// print_r($attributes_map);
// echo "</pre>";

//error_log( var_export( $variations, true ) )
?>

<style>
    .ed-product-price {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        margin-bottom: 20px;
        color: #333;
    }

    /* Variations */
    .ed-variation-selector {
        margin-bottom: 25px;
    }

    .ed-variation-group {
        margin-bottom: 20px;
    }

    .ed-variation-label {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 12px;
        color: #444;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .ed-variation-options {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
    }

    .ed-variation-btn {
        padding: 10px 20px;
        border: 2px solid #edf2f7;
        border-radius: 12px;
        background: #fff;
        cursor: pointer;
        font-size: 15px;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        color: #4a5568;
        font-weight: 600;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        min-width: 60px;
        text-align: center;
    }

    .ed-variation-btn.active {
        background: #007bff;
        color: #fff;
        border-color: #007bff;
        box-shadow: 0 4px 14px rgba(0, 123, 255, 0.25);
    }

    .ed-variation-btn.disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: #f1f5f9;
        border-color: #e2e8f0;
        color: #94a3b8;
    }

    .ed-variation-btn:hover:not(.active):not(.disabled) {
        border-color: #007bff;
        color: #007bff;
        background: #f0f7ff;
    }

    /* Price Box */
    .ed-price-box {
        background: #f8fafc;
        padding: 24px;
        border-radius: 16px;
        margin-bottom: 24px;
        border: 1px solid #f1f5f9;
    }

    .ed-price-row {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 10px;
    }

    .ed-regular-price {
        text-decoration: line-through;
        color: #94a3b8;
        font-size: 28px;
        font-weight: 600;
    }

    .ed-sale-price {
        color: #ef4444;
        font-size: 36px;
        font-weight: 800;
    }

    .ed-discount-badge {
        background: #ef4444;
        color: #fff;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
    }

    .ed-stock-status {
        color: #64748b;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .ed-stock-status::before {
        content: '';
        display: inline-block;
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
    }

    /* Action Buttons */
    .ed-actions-row {
        display: flex;
        gap: 12px;
        margin-bottom: 12px;
        align-items: center;
    }

    .ed-qty-container {
        position: relative;
        width: 160px;
        display: flex;
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        height: 56px;
        overflow: hidden;
    }

    .ed-qty-input,
    .ed-qty-input:hover,
    .ed-qty-input:focus,
    .ed-qty-input:active {
        flex: 1;
        width: 100%;
        height: 100%;
        border: none !important;
        text-align: center;
        font-size: 18px;
        font-weight: 700;
        background: transparent;
        -moz-appearance: textfield;
        appearance: none;
        padding: 0;
        outline: none;
    }

    .ed-qty-btn {
        width: 40px;
        height: 100%;
        border: none;
        background: transparent;
        color: #64748b;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background-color 0.2s, color 0.2s;
    }

    .ed-qty-btn:hover {
        background-color: #e2e8f0;
        color: #0f172a;
    }

    .ed-qty-input::-webkit-outer-spin-button,
    .ed-qty-input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }

    .ed-btn {
        flex: 1;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 800;
        color: #fff;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        text-decoration: none;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .ed-btn:active {
        transform: scale(0.97);
    }

    .ed-btn-cart {
        background: #4b5563;
        color: #fff;
    }

    .ed-btn-cart:hover {
        background: #374151;
        box-shadow: 0 4px 12px rgba(75, 85, 99, 0.25);
    }

    .ed-btn-buy {
        background: #f43f5e;
        color: #fff;
    }

    .ed-btn-buy:hover {
        background: #e11d48;
        box-shadow: 0 4px 12px rgba(244, 63, 94, 0.25);
    }

    .ed-btn-whatsapp {
        width: 100%;
        background: #10a37f;
        margin-top: 5px;
        text-transform: none;
        font-size: 18px;
        height: 56px;
        color: #fff;
    }

    .ed-btn-whatsapp:hover {
        background: #0d8a6a;
        box-shadow: 0 4px 12px rgba(16, 163, 127, 0.25);
        color: #fff;
    }

    /* Hide standard WooCommerce elements to avoid duplication */
    .single-product .summary form.cart,
    .single-product .summary .price {
        display: none !important;
    }
</style>

<div class="ed-product-price" id="ed-product-container" data-product-id="<?php echo esc_attr($product_id); ?>"
    data-type="<?php echo esc_attr($product->get_type()); ?>">

    <?php if ($is_variable): ?>
        <div class="ed-variation-selector">
            <?php foreach ($attributes_map as $attr_name => $options):
                $label = str_replace('attribute_', '', $attr_name);
                $label = ucwords(str_replace(array('-', '_'), ' ', $label));
                ?>
                <div class="ed-variation-group" data-attribute-name="<?php echo esc_attr($attr_name); ?>">
                    <div class="ed-variation-label"><?php echo esc_html($label); ?>: <span style="color: #ef4444;">*</span>
                    </div>
                    <div class="ed-variation-options">
                        <?php foreach ($options as $option):
                            $is_active = (isset($default_attributes[$attr_name]) && $default_attributes[$attr_name] === $option);
                            ?>
                            <button type="button" class="ed-variation-btn <?php echo $is_active ? 'active' : ''; ?>"
                                data-value="<?php echo esc_attr($option); ?>">
                                <?php echo esc_html($option); ?>
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
        <button type="button" class="ed-btn ed-btn-cart" id="ed-add-to-cart">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                stroke-linecap="round" stroke-linejoin="round">
                <circle cx="9" cy="21" r="1" />
                <circle cx="20" cy="21" r="1" />
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" />
            </svg>
            ADD TO CART
        </button>
        <a href="<?php echo esc_url($whatsapp_url); ?>" class="ed-btn ed-btn-whatsapp" target="_blank">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
                <path
                        d="M12.031 6.172c-2.32 0-4.525.903-6.205 2.541a8.814 8.814 0 0 0-2.5 6.208c0 1.6.438 3.167 1.258 4.536l-1.334 4.888 4.996-1.314c1.32.723 2.805 1.103 4.316 1.104h.004c2.32 0 4.526-.902 6.205-2.54 1.678-1.638 2.5-3.815 2.5-6.108 0-4.81-3.906-8.724-8.74-8.724zm4.49 12.326c-.19.531-1.077 1.018-1.493 1.082-.375.058-.698.058-2.02-.455-1.57-.61-2.583-2.185-2.66-2.288-.078-.103-.64-.852-.64-1.626 0-.773.407-1.155.552-1.31s.315-.194.42-.194c.105 0 .21 0 .302.006.096.002.226-.037.352.261.13.308.442 1.082.481 1.16.04.077.065.167.013.27-.052.103-.078.167-.156.257-.078.091-.163.203-.233.274-.078.077-.158.162-.068.318.09.155.398.656.853 1.062.585.52 1.078.681 1.233.758.156.077.247.065.338-.039s.39-.455.494-.61c.105-.154.21-.129.352-.077s.9.425 1.056.502c.156.077.26.116.299.18.038.065.038.374-.152.905zM12 2C6.477 2 2 6.477 2 12c0 1.892.527 3.66 1.442 5.167L2 22l4.98-1.303C8.428 21.583 10.143 22 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2z" />
            </svg>
		    <?php echo esc_html($whatsapp_number); ?>
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

        const variations = <?php echo json_encode($variations); ?>;
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

        function triggerAddToCart(productId, quantity, variationId, isBuyNow = false) {
            // Use a hidden form to submit to WooCommerce
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = window.location.href;

            const fields = {
                'add-to-cart': productId,
                'quantity': quantity
            };

            if (variationId) {
                fields['variation_id'] = variationId;
                fields['product_id'] = productId;
            }

            for (const key in fields) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = fields[key];
                form.appendChild(input);
            }

            if (isBuyNow) {
                // If Buy Now, we use a different approach: redirect with URL params
                let url = '<?php echo wc_get_checkout_url(); ?>?add-to-cart=' + productId + '&quantity=' + quantity;
                if (variationId) {
                    url += '&variation_id=' + variationId;
                }
                window.location.href = url;
                return;
            }

            document.body.appendChild(form);
            form.submit();
        }

        addToCartBtn.addEventListener('click', function () {
            const id = isVariable ? currentVariationId : container.dataset.productId;
            if (isVariable && !id) {
                alert('Please select all options');
                return;
            }
            triggerAddToCart(container.dataset.productId, qtyInput.value, isVariable ? id : null, false);
        });
    });
</script>