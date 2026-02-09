<?php
/**
 * Kadence Child - CLEAN & SAFE VERSION
 * Compatible with PHP 7.0+
 * Works with Arton360 Designer
 */

if (!defined('TEE_COLOR_TAX')) {
    define('TEE_COLOR_TAX', 'pa_color');
}

/**
 * ADMIN: Base T-shirt image field for color terms
 */
add_action(TEE_COLOR_TAX . '_add_form_fields', function () {
    ?>
    <div class="form-field term-group">
        <label>Hex Color Code</label>
        <input type="text" id="tee_color_hex" name="tee_color_hex" placeholder="#ffffff" style="max-width:120px;">
        <p class="description">Hex color for swatch display (e.g. #ff0000 for red)</p>
    </div>
    <div class="form-field term-group">
        <label>Base T-shirt image</label>
        <input type="hidden" id="tee_base_image_id" name="tee_base_image_id">
        <button type="button" class="button tee-upload-image">Upload / Select</button>
        <div class="tee-image-preview" style="margin-top:10px;"></div>
    </div>
    <?php
});

add_action(TEE_COLOR_TAX . '_edit_form_fields', function ($term) {
    $image_id  = (int) get_term_meta($term->term_id, 'tee_base_image_id', true);
    $color_hex = get_term_meta($term->term_id, 'tee_color_hex', true);
    ?>
    <tr class="form-field">
        <th><label>Hex Color Code</label></th>
        <td>
            <input type="text" id="tee_color_hex" name="tee_color_hex" value="<?php echo esc_attr($color_hex); ?>" placeholder="#ffffff" style="max-width:120px;">
            <?php if ($color_hex) : ?>
                <span style="display:inline-block;width:24px;height:24px;border-radius:50%;background:<?php echo esc_attr($color_hex); ?>;border:1px solid #ccc;vertical-align:middle;margin-left:8px;"></span>
            <?php endif; ?>
            <p class="description">Hex color for swatch display (e.g. #ff0000 for red)</p>
        </td>
    </tr>
    <tr class="form-field">
        <th><label>Base T-shirt image</label></th>
        <td>
            <input type="hidden" id="tee_base_image_id" name="tee_base_image_id" value="<?php echo esc_attr($image_id); ?>">
            <button type="button" class="button tee-upload-image">Upload / Select</button>
            <button type="button" class="button tee-remove-image">Remove</button>
            <div class="tee-image-preview" style="margin-top:10px;">
                <?php if ($image_id) echo wp_get_attachment_image($image_id, 'medium'); ?>
            </div>
        </td>
    </tr>
    <?php
});

add_action('created_' . TEE_COLOR_TAX, function ($term_id) {
    if (isset($_POST['tee_base_image_id'])) {
        update_term_meta($term_id, 'tee_base_image_id', (int) $_POST['tee_base_image_id']);
    }
    if (isset($_POST['tee_color_hex'])) {
        update_term_meta($term_id, 'tee_color_hex', sanitize_text_field($_POST['tee_color_hex']));
    }
});

add_action('edited_' . TEE_COLOR_TAX, function ($term_id) {
    if (isset($_POST['tee_base_image_id'])) {
        update_term_meta($term_id, 'tee_base_image_id', (int) $_POST['tee_base_image_id']);
    }
    if (isset($_POST['tee_color_hex'])) {
        update_term_meta($term_id, 'tee_color_hex', sanitize_text_field($_POST['tee_color_hex']));
    }
});

/**
 * Admin media uploader
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) return;

    wp_enqueue_media();
    wp_enqueue_script('jquery');

    wp_add_inline_script('jquery', "
        jQuery(document).on('click', '.tee-upload-image', function(e){
            e.preventDefault();
            const button = jQuery(this);
            const frame = wp.media({ title: 'Select image', multiple: false });
            frame.on('select', function(){
                const a = frame.state().get('selection').first().toJSON();
                button.siblings('#tee_base_image_id').val(a.id);
                button.siblings('.tee-image-preview').html('<img src=\"'+a.url+'\" style=\"max-width:220px;\" />');
            });
            frame.open();
        });
        jQuery(document).on('click', '.tee-remove-image', function(){
            jQuery(this).siblings('#tee_base_image_id').val('');
            jQuery(this).siblings('.tee-image-preview').html('');
        });
    ");
});

/**
 * Helper: Arton360 product check
 */
function is_arton360_designer_product($product_id) {
    return (bool) get_post_meta($product_id, '_arton360_canvas_json', true);
}

/**
 * Helper: Check if product is a full-print (graphic) T-shirt
 * Full-print products have isFullPrint=true and printType="mask" in their canvas JSON
 */
function is_full_print_product($product_id) {
    $canvas_json = get_post_meta($product_id, '_arton360_canvas_json', true);
    if (!$canvas_json) return false;
    $canvas = json_decode($canvas_json, true);
    if (is_array($canvas) && isset($canvas[0]['sides']['front']['isFullPrint'])) {
        return !empty($canvas[0]['sides']['front']['isFullPrint']);
    }
    return false;
}

/**
 * Helper: Get base T-shirt image ID for a color term.
 * Checks our custom field first, then falls back to common swatch plugin meta keys.
 */
function tee_get_base_image_id($term_id) {
    // Our custom field first
    $img_id = (int) get_term_meta($term_id, 'tee_base_image_id', true);
    if ($img_id) return $img_id;

    // Fallback: common swatch plugin meta keys
    $fallback_keys = array(
        'product_attribute_image',         // GetWooPlugins Variation Swatches (image type)
        'tooltip_image',                   // GetWooPlugins Pro (Custom Tooltip image)
        'tooltip_image_id',                // GetWooPlugins Pro (alternate key)
        'gwp_swatch_image',                // GetWooPlugins (alternate)
        'cfvsw_image',                     // CartFlows Variation Swatches
        'image',                           // Generic
        'swatches_id',                     // Some swatch plugins
        'display_image',                   // Some swatch plugins
        'pa_colour_swatches_id_photo',     // WC Variation Swatches & Photos
    );

    foreach ($fallback_keys as $key) {
        $img_id = (int) get_term_meta($term_id, $key, true);
        if ($img_id) return $img_id;
    }

    // Last resort: check ALL term meta for any attachment ID or image URL
    $all_meta = get_term_meta($term_id);
    if (is_array($all_meta)) {
        foreach ($all_meta as $key => $values) {
            // Skip known non-image keys
            if (in_array($key, ['tee_color_hex', 'order', 'display_type', 'order_pa_color', 'product_attribute_color', 'secondary_color', 'is_dual_color', 'show_tooltip', 'group_name'], true)) continue;
            $raw = is_array($values) ? $values[0] : $values;

            // Try as integer attachment ID
            $val = (int) $raw;
            if ($val > 0 && wp_attachment_is_image($val)) {
                return $val;
            }

            // Try as URL string — use attachment_url_to_postid to convert URL to ID
            if (is_string($raw) && preg_match('/^https?:\/\/.+\.(png|jpe?g|gif|webp|svg)/i', $raw)) {
                $id_from_url = attachment_url_to_postid($raw);
                if ($id_from_url > 0) {
                    return $id_from_url;
                }
            }
        }
    }

    return 0;
}

/**
 * DEBUG: Check product flags (visit /wp-json/tee/v1/debug-product?id=PRODUCT_ID)
 */
add_action('rest_api_init', function () {
    register_rest_route('tee/v1', '/debug-product', array(
        'methods'  => 'GET',
        'callback' => function ($req) {
            $pid = (int) $req->get_param('id');
            if (!$pid) return new WP_Error('no_id', 'Pass ?id=PRODUCT_ID');
            $cats = wp_get_post_terms($pid, 'product_cat', array('fields' => 'slugs'));
            $print_box_raw = get_post_meta($pid, '_arton360_print_box', true);
            $print_box = $print_box_raw ? json_decode($print_box_raw, true) : null;
            $canvas_json = get_post_meta($pid, '_arton360_canvas_json', true);
            $canvas = $canvas_json ? json_decode($canvas_json, true) : null;
            $front_side = null;
            $is_full_print = false;
            $print_type = '';
            if (is_array($canvas) && isset($canvas[0]['sides']['front'])) {
                $front_side = $canvas[0]['sides']['front'];
                $is_full_print = !empty($front_side['isFullPrint']);
                $print_type = isset($front_side['printType']) ? $front_side['printType'] : '';
            }
            $wc_product = wc_get_product($pid);
            return array(
                'file_version'    => 'v7-feb9',
                'mature_flag'     => get_post_meta($pid, '_vendor_mature_flag', true),
                'product_type'    => $wc_product ? $wc_product->get_type() : 'not_found',
                'product_id'      => $pid,
                'categories'      => $cats,
                'is_designer'     => (bool) $canvas_json,
                'is_graphic_tee'  => has_term(array('graphic-tshirt', 'graphic_tshirt', 'graphic-tshirts'), 'product_cat', $pid),
                'art_type'        => get_post_meta($pid, '_arton360_art_type', true),
                'style'           => get_post_meta($pid, '_arton360_style', true),
                'is_full_print'   => $is_full_print,
                'print_type'      => $print_type,
                'artwork_url'     => get_post_meta($pid, '_arton360_artwork_url', true),
                'print_box_raw'   => $print_box_raw,
                'print_box'       => $print_box,
            );
        },
        'permission_callback' => '__return_true',
    ));
});

/**
 * Unified preview renderer
 */
add_filter('woocommerce_single_product_image_thumbnail_html', 'unified_tee_color_preview', 10, 2);

function unified_tee_color_preview($html) {
    if (!is_product()) return $html;

    global $product, $post;
    if (!$product || !$post || !$product->is_type('variable')) return $html;

    $product_id = $post->ID;
    $is_designer   = is_arton360_designer_product($product_id);
    $is_full_print = is_full_print_product($product_id);

    // Only apply layered color-swap for regular (non-full-print) designer T-shirts
    // Full-print / graphic tshirts keep their normal WooCommerce product image untouched
    if (!$is_designer || $is_full_print) return $html;

    // For designer products, prefer the artwork-only PNG (transparent bg)
    $artwork_url = get_post_meta($product_id, '_arton360_artwork_url', true);
    if (!$artwork_url) {
        $artwork_url = get_the_post_thumbnail_url($product_id, 'large');
    }
    if (!$artwork_url) return $html;

    // Build color map from pa_color term meta (single source of truth)
    $color_map = array();
    $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $img_id = tee_get_base_image_id($term->term_id);
            if ($img_id) {
                $url = wp_get_attachment_image_url($img_id, 'full');
                if ($url) $color_map[$term->slug] = $url;
            }
        }
    }

    if (empty($color_map)) return $html;

    $defaults = $product->get_default_attributes();
    $default_color = isset($defaults['pa_color']) ? sanitize_title($defaults['pa_color']) : '';

    if (!$default_color || !isset($color_map[$default_color])) {
        $keys = array_keys($color_map);
        $default_color = $keys[0];
    }

    // The artwork PNG is the FULL canvas (600x700) with transparent background.
    // The artwork is already positioned correctly within that canvas.
    // The mockup image occupies the same canvas area, so we overlay them 1:1.
    // Both images share the same coordinate system — just stack them directly.

    $is_mature = get_post_meta($product_id, '_vendor_mature_flag', true) === '1';

    ob_start();
    ?>
    <div class="tee-layered-wrapper" style="max-width:700px;margin:auto;position:relative;">
        <div class="tee-layered-inner" style="position:relative;width:100%;padding-bottom:116.67%;<?php echo $is_mature ? 'filter:blur(20px);transition:filter 0.3s;' : ''; ?>" <?php echo $is_mature ? 'data-mature="1"' : ''; ?>>
            <img id="tee-base-image" class="tee-base-image" src="<?php echo esc_url($color_map[$default_color]); ?>" style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:contain;" />
            <img class="tee-artwork-overlay" src="<?php echo esc_url($artwork_url); ?>" style="position:absolute;pointer-events:none;z-index:10;top:0;left:0;width:100%;height:100%;object-fit:contain;" />
        </div>
        <?php if ($is_mature) : ?>
        <div class="mature-overlay" onclick="this.style.display='none';this.previousElementSibling.style.filter='none';" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:20;background:rgba(0,0,0,0.1);">
            <span style="background:rgba(0,0,0,0.7);color:#fff;padding:12px 24px;border-radius:8px;font-size:14px;text-align:center;line-height:1.5;">&#x1F512; Mature Content<br><small>Click to reveal</small></span>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Mature content blur helper - wraps any HTML with blur + click-to-reveal
 */
function tee_wrap_mature_blur($html, $small = false) {
    $pad = $small ? '8px 16px' : '12px 24px';
    $fs  = $small ? '12px' : '14px';
    $rad = $small ? '6px' : '8px';
    $label = $small ? 'Mature' : 'Mature Content';
    return '<div style="position:relative;overflow:hidden;">'
        . '<div class="mature-blur-inner" style="filter:blur(20px);transition:filter 0.3s;">' . $html . '</div>'
        . '<div class="mature-overlay" onclick="event.preventDefault();event.stopPropagation();this.style.display=\'none\';this.previousElementSibling.style.filter=\'none\';" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:20;background:rgba(0,0,0,0.1);">'
        . '<span style="background:rgba(0,0,0,0.7);color:#fff;padding:' . $pad . ';border-radius:' . $rad . ';font-size:' . $fs . ';text-align:center;line-height:1.4;">&#x1F512; ' . $label . '<br><small>Click to reveal</small></span>'
        . '</div></div>';
}

/**
 * Mature content blur for shop/catalog thumbnails (post_thumbnail_html)
 */
add_filter('post_thumbnail_html', 'tee_mature_blur_thumbnail', 20, 5);
function tee_mature_blur_thumbnail($html, $post_id, $thumb_id, $size, $attr) {
    if (is_admin() || is_product()) return $html;
    if (get_post_type($post_id) !== 'product') return $html;
    if (get_post_meta($post_id, '_vendor_mature_flag', true) !== '1') return $html;
    return tee_wrap_mature_blur($html, true);
}

/**
 * Mature content blur for WooCommerce product loop image
 * Catches shop, archive, category, and home page product grids
 */
add_filter('woocommerce_product_get_image', 'tee_mature_blur_loop_image', 20, 5);
function tee_mature_blur_loop_image($image, $product, $size, $attr, $placeholder) {
    if (is_admin() || is_product()) return $image;
    $product_id = $product->get_id();
    if (get_post_meta($product_id, '_vendor_mature_flag', true) !== '1') return $image;
    // Avoid double-wrapping
    if (strpos($image, 'mature-blur-inner') !== false) return $image;
    return tee_wrap_mature_blur($image, true);
}

/**
 * Mature content blur for single product page (graphic/full-print tees)
 * These bypass the layered wrapper, so we blur the WooCommerce gallery image directly
 */
add_filter('woocommerce_single_product_image_thumbnail_html', 'tee_mature_blur_product_image', 30, 2);
function tee_mature_blur_product_image($html, $thumb_id) {
    if (!is_product()) return $html;
    global $post;
    if (!$post) return $html;

    $product_id = $post->ID;
    if (get_post_meta($product_id, '_vendor_mature_flag', true) !== '1') return $html;

    // Skip if already handled by our layered wrapper (has tee-layered-wrapper)
    if (strpos($html, 'tee-layered-wrapper') !== false) return $html;

    return tee_wrap_mature_blur($html, false);
}

/**
 * Frontend styles + JS
 */
// Deregister competing plugin script early (before Jetpack static file concatenation)
add_action('wp_enqueue_scripts', function () {
    wp_deregister_script('arton360-color-sync');
    wp_dequeue_script('arton360-color-sync');
}, 1);

add_action('wp_enqueue_scripts', function () {
    if (!is_product()) return;

    global $post;
    if (!$post) return;

    $product_id = $post->ID;
    $product = wc_get_product($product_id);
    $is_designer   = is_arton360_designer_product($product_id);
    $is_full_print = is_full_print_product($product_id);

    // Only load color-swap JS for regular (non-full-print) designer T-shirts
    if (!$is_designer || $is_full_print) return;

    // Deregister again in case plugin enqueued after priority 1
    wp_deregister_script('arton360-color-sync');
    wp_dequeue_script('arton360-color-sync');

    // Build color map from pa_color term meta
    $color_map = array();
    $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $img_id = tee_get_base_image_id($term->term_id);
            if ($img_id) {
                $url = wp_get_attachment_image_url($img_id, 'full');
                if ($url) $color_map[$term->slug] = $url;
            }
        }
    }

    // Get default color from product attributes
    $default_color = '';
    if ($product && $product->is_type('variable')) {
        $defaults = $product->get_default_attributes();
        $default_color = isset($defaults['pa_color']) ? sanitize_title($defaults['pa_color']) : '';
    }
    if (!$default_color && !empty($color_map)) {
        $keys = array_keys($color_map);
        $default_color = $keys[0];
    }

    wp_enqueue_script(
        'tee-color-swap',
        get_stylesheet_directory_uri() . '/js/tee-color-swap.js',
        ['jquery'],
        '3.1.0',
        true
    );

    // Localize color map so JS can swap base images
    wp_localize_script('tee-color-swap', 'TEE_SWAP', array(
        'map'         => $color_map,
        'defaultSlug' => $default_color,
    ));

});
