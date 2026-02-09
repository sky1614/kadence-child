/**
 * Tee Color Swap - Kadence Child Theme
 * Swaps the base T-shirt image when customer selects a different color.
 * Works with: WooCommerce dropdowns, radio swatches, Shop Kit swatches.
 * Version 3.1.0
 */
jQuery(function ($) {
    console.log('[TeeSwap v3.1.0] Initializing...');

    // Validate TEE_SWAP data was localized from PHP
    if (typeof TEE_SWAP === 'undefined' || !TEE_SWAP.map) {
        console.error('[TeeSwap] TEE_SWAP not defined - wp_localize_script missing');
        return;
    }

    var colorCount = Object.keys(TEE_SWAP.map).length;
    console.log('[TeeSwap] Loaded ' + colorCount + ' colors, default: ' + TEE_SWAP.defaultSlug);

    /**
     * Always re-find the base image element (it may be replaced by WooCommerce)
     */
    function getBaseImage() {
        var $el = $('#tee-base-image');
        if ($el.length === 0) $el = $('.tee-base-image');
        return $el;
    }

    // Initial check
    if (getBaseImage().length === 0) {
        console.warn('[TeeSwap] No base image element found');
        return;
    }

    /**
     * Get the currently selected color slug from any input type
     */
    function getCurrentColor() {
        // Radio buttons (Shop Kit / visual swatches)
        var $radio = $('input[type="radio"][name="attribute_pa_color"]:checked');
        if ($radio.length) return $radio.val();

        // Standard dropdown
        var $select = $('select[name="attribute_pa_color"]');
        if ($select.length && $select.val()) return $select.val();

        // Hidden input (some swatch plugins)
        var $hidden = $('input[type="hidden"][name="attribute_pa_color"]');
        if ($hidden.length && $hidden.val()) return $hidden.val();

        return '';
    }

    /**
     * Swap the base T-shirt image to match the selected color.
     * Re-finds the element each time to survive WooCommerce DOM replacements.
     */
    function swapBase(colorSlug) {
        if (!colorSlug) return;

        var newUrl = TEE_SWAP.map[colorSlug];
        if (!newUrl) {
            console.warn('[TeeSwap] No image for color:', colorSlug);
            return;
        }

        var $baseImage = getBaseImage();
        if ($baseImage.length === 0) {
            console.warn('[TeeSwap] Base image element lost');
            return;
        }

        console.log('[TeeSwap] Swapping to:', colorSlug);
        $baseImage.attr('src', newUrl);
        $baseImage.attr('srcset', '');

        // Update anchor wrapper if present (zoom/lightbox)
        var $anchor = $baseImage.closest('a');
        if ($anchor.length) {
            $anchor.attr('href', newUrl);
            $anchor.attr('data-large_image', newUrl);
        }
    }

    // --- Prevent WooCommerce from replacing our layered preview ---
    // WooCommerce fires 'wc-product-gallery-after-init' and replaces images
    // on found_variation / reset_image. We intercept and prevent image replacement.
    $(document).on('reset_image', '.woocommerce-product-gallery', function (e) {
        // If our layered wrapper exists, prevent WooCommerce from resetting to featured image
        if ($('.tee-layered-wrapper').length) {
            e.stopImmediatePropagation();
            // Instead, swap back to default color
            var defaultColor = TEE_SWAP.defaultSlug;
            if (defaultColor) swapBase(defaultColor);
        }
    });

    // --- Event Listeners ---

    // Radio button changes (Shop Kit swatches)
    $(document).on('change', 'input[type="radio"][name="attribute_pa_color"]', function () {
        swapBase($(this).val());
    });

    // Dropdown changes (standard WooCommerce)
    $(document).on('change', 'select[name="attribute_pa_color"]', function () {
        swapBase($(this).val());
    });

    // WooCommerce variation found event
    $(document).on('found_variation', 'form.variations_form', function (event, variation) {
        if (variation && variation.attributes && variation.attributes.attribute_pa_color) {
            // Small delay to let WooCommerce finish its DOM updates first
            var color = variation.attributes.attribute_pa_color;
            setTimeout(function () {
                swapBase(color);
            }, 10);
        }
    });

    // WooCommerce variation reset event
    $(document).on('reset_data', 'form.variations_form', function () {
        setTimeout(function () {
            var defaultColor = TEE_SWAP.defaultSlug;
            if (defaultColor) swapBase(defaultColor);
        }, 10);
    });

    // Generic form change (backup)
    $('form.variations_form').on('change', function () {
        var color = getCurrentColor();
        if (color) {
            setTimeout(function () { swapBase(color); }, 20);
        }
    });

    // Visual swatch clicks (div/button based swatch plugins)
    $(document).on('click', '[data-attribute_name="attribute_pa_color"] .swatch-wrapper, [data-attribute_name="attribute_pa_color"] .variable-item', function () {
        setTimeout(function () {
            var color = getCurrentColor();
            if (color) swapBase(color);
        }, 50);
    });

    // --- MutationObserver: re-apply swap if WooCommerce replaces gallery HTML ---
    var galleryEl = document.querySelector('.woocommerce-product-gallery');
    if (galleryEl) {
        var observer = new MutationObserver(function () {
            // If our layered wrapper was removed and re-added, re-apply the current color
            if (getBaseImage().length > 0) {
                var color = getCurrentColor() || TEE_SWAP.defaultSlug;
                if (color) swapBase(color);
            }
        });
        observer.observe(galleryEl, { childList: true, subtree: true });
    }

    // --- Initial swap ---
    var initialColor = getCurrentColor() || TEE_SWAP.defaultSlug;
    if (initialColor) {
        swapBase(initialColor);
    }

    console.log('[TeeSwap] Ready');
});
