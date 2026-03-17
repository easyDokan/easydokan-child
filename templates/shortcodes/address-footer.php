<?php
/**
 * Render Address Footer
 */

defined( 'ABSPATH' ) || exit;

$store_name      = get_option( 'easydokan_store_name', 'Default Store' );
$store_email     = get_option( 'easydokan_store_email', '' );
$store_mobile    = get_option( 'easydokan_store_mobile', '' );
$store_address   = get_option( 'woocommerce_store_address', '' );
$store_address_2 = get_option( 'woocommerce_store_address_2', '' );
$store_city      = get_option( 'woocommerce_store_city', '' );
$store_postcode  = get_option( 'woocommerce_store_postcode', '' );

?>
<div class="ed-store-address">
	<?php if ( ! empty ( $store_address ) ) : ?>
        <div class="ed-store-address__row ed-store-address__row--top">
            <span class="ed-store-address__icon-wrapper ed-store-address__icon-wrapper--location">
                <svg xmlns="http://www.w3.org/2000/svg" class="ed-store-address__icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7zm0 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/>
                </svg>
            </span>
            <p class="ed-store-address__text">
				<?php echo $store_address; ?>
				<?php echo $store_address_2; ?>
				<?php echo $store_city; ?>
				<?php echo $store_postcode; ?>
            </p>
        </div>
	<?php endif; ?>

	<?php if ( ! empty ( $store_mobile ) ) : ?>
        <div class="ed-store-address__row">
            <span class="ed-store-address__icon-wrapper ed-store-address__icon-wrapper--phone">
                <svg xmlns="http://www.w3.org/2000/svg" class="ed-store-address__icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6.62 10.79a15.053 15.053 0 0 0 6.59 6.59l2.2-2.2a1 1 0 0 1 1.01-.24 11.72 11.72 0 0 0 3.68.59 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.47a1 1 0 0 1 1 1 11.72 11.72 0 0 0 .59 3.68 1 1 0 0 1-.24 1.01l-2.2 2.2z"/>
                </svg>
            </span>
            <a href="tel:<?php echo $store_mobile; ?>" class="ed-store-address__link">
				<?php echo $store_mobile; ?>
            </a>
        </div>
	<?php endif; ?>

	<?php if ( ! empty ( $store_email ) ) : ?>
        <div class="ed-store-address__row">
            <span class="ed-store-address__icon-wrapper ed-store-address__icon-wrapper--email">
                <svg xmlns="http://www.w3.org/2000/svg" class="ed-store-address__icon" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2zm0 2v.01L12 13l8-6.99V6H4zm0 4.25V18h16v-7.75l-7.4 5.16a2 2 0 0 1-2.2 0L4 10.25z"/>
                </svg>
            </span>
            <a href="mailto:<?php echo $store_email; ?>" class="ed-store-address__link">
				<?php echo $store_email; ?>
            </a>
        </div>
	<?php endif; ?>
</div>


<style>
    .ed-store-address {
        display: flex;
        flex-direction: column;
        row-gap: 1.5rem; /* space-y-6 */
    }

    /* Each row (address / phone / email) */
    .ed-store-address__row {
        display: flex; /* flex */
        align-items: center; /* items-center */
        column-gap: 1rem; /* space-x-4 */
    }

    /* First row had items-start + mt-1 on the icon */
    .ed-store-address__row--top {
        align-items: flex-start; /* items-start */
    }

    /* Icon circle */
    .ed-store-address__icon-wrapper {
        display: inline-flex; /* inline-flex */
        align-items: center; /* items-center */
        justify-content: center; /* justify-center */
        height: 2rem; /* h-8 */
        width: 2rem; /* w-8 */
        min-width: 32px; /* min-w-[32px] */
        border-radius: 0.5rem; /* rounded (approx 8px; adjust as needed) */
        background-color: var(--global-palette-btn-bg); /* bg-[var(--global-palette-btn-bg)] [web:9] */
    }

    /* Extra top margin for the first icon (mt-1) */
    .ed-store-address__row--top .ed-store-address__icon-wrapper {
        margin-top: 0.25rem; /* mt-1 */
    }

    /* SVG icon */
    .ed-store-address__icon {
        height: 1.25rem; /* h-5 */
        width: 1.25rem; /* w-5 */
        color: #ffffff; /* text-white */
    }

    /* Address text */
    .ed-store-address__text {
        font-size: 1rem; /* text-base */
        line-height: 1.625rem; /* leading-relaxed */
        color: rgb(229, 231, 235); /* text-gray-200 [web:7][web:10] */
        margin: 0;
    }

    /* Links (phone and email) */
    .ed-store-address__link {
        font-size: 1rem; /* text-base */
        color: rgb(229, 231, 235); /* text-gray-200 [web:7][web:10] */
        text-decoration: none;
        transition: color 0.15s ease-in-out;
    }

    /* Hover state equivalent to hover:text-white */
    .ed-store-address__link:hover {
        color: #ffffff;
    }
</style>