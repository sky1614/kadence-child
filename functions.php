<?php
/**
 * Kadence Child – CORRECTED VERSION
 * Works alongside Arton360 Designer plugin
 */

if (!defined('TEE_COLOR_TAX')) {
    define('TEE_COLOR_TAX', 'pa_color');
}

/**
 * ADMIN: Base T-shirt image field for color terms
 * (Keep this - it's for manual product setup)
 */
add_action(TEE_COLOR_TAX . '_add_form_fields', function () { ?>
    <div class="form-field term-group">
        <label>Base T-shirt image</label>
        <input type="hidden" id="tee_base_image_id" name="tee_base_image_id">
        <button type="button" class="button tee-upload-image">Upload / Select</button>
        <div class="tee-image-preview" style="margin-top:10px;"></div>
    </div>
<?php });

add_action(TEE_COLOR_TAX . '_edit_form_fields', function ($term) {
    $image_id = (int) get_term_meta($term->term_id, 'tee_base_image_id', true); ?>
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
<?php });

add_action('created_' . TEE_COLOR_TAX, function ($term_id) {
    if (isset($_POST['tee_base_image_id'])) {
        update_term_meta($term_id, 'tee_base_image_id', (int) $_POST['tee_base_image_id']);
    }
});
add_action('edited_' . TEE_COLOR_TAX, function ($term_id) {
    if (isset($_POST['tee_base_image_id'])) {
        update_term_meta($term_id, 'tee_base_image_id', (int) $_POST['tee_base_image_id']);
    }
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['edit-tags.php', 'term.php'], true)) return;
    wp_enqueue_media();
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
 * Check if this is an Arton360 designer product
 */
function is_arton360_designer_product($product_id) {
    return (bool) get_post_meta($product_id, '_arton360_canvas_json', true);
}

/**
 * UNIFIED: Handle color swapping for BOTH manual and designer products
 * This replaces the gallery image with layered preview
 */
add_filter('woocommerce_single_product_image_thumbnail_html', 'unified_tee_color_preview', 10, 2);

function unified_tee_color_preview($html, $attachment_id) {
    if (!is_product()) {
        return $html;
    }

    global $post, $product;
    if (!$post || !$product) {
        return $html;
    }

    $product_id = $post->ID;

    // Skip if not variable product
    if (!$product->is_type('variable')) {
        return $html;
    }

    // Check if it's a t-shirt product
    $is_tshirt = has_term('tshirts', 'product_cat', $product_id);
    $is_designer = is_arton360_designer_product($product_id);

    if (!$is_tshirt && !$is_designer) {
        return $html;
    }

    // Get artwork (featured image)
    $artwork_url = get_the_post_thumbnail_url($product_id, 'large');
    if (!$artwork_url) {
        return $html;
    }

    // Build color-to-base-image map
    $color_map = array();

    if ($is_designer) {
        // Use Arton360 plugin's hardcoded images
        $base_url = plugin_dir_url(__FILE__ . '/../../plugins/arton360-designer-windows/arton360-designer.php') . 'arton360-designer-windows/assets/tshirts/';
        $color_map = array(
            'white' => $base_url . 'white.png',
            'black' => $base_url . 'black.png',
            'red'   => $base_url . 'red.png',
            'gray'  => $base_url . 'gray.png',
            'navy'  => $base_url . 'navy.png',
        );
    } else {
        // Use term meta images (for manual products)
        $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $img_id = (int) get_term_meta($term->term_id, 'tee_base_image_id', true);
                if ($img_id) {
                    $url = wp_get_attachment_image_url($img_id, 'full');
                    if ($url) {
                        $color_map[$term->slug] = $url;
                    }
                }
            }
        }
    }

    if (empty($color_map)) {
        return $html;
    }

    // Get default color
    $default_color = '';
    $defaults = $product->get_default_attributes();
    if (isset($defaults['pa_color'])) {
        $default_color = sanitize_title($defaults['pa_color']);
    }
    if (empty($default_color) || !isset($color_map[$default_color])) {
        $default_color = array_key_first($color_map);
    }

    $base_img = $color_map[$default_color];

    // Output unified HTML structure
    ob_start();
    ?>
    <div class="tee-layered-wrapper" data-product-id="<?php echo esc_attr($product_id); ?>">
        <div class="tee-layered-inner">
            <img
                id="tee-base-image"
                class="tee-base-image"
                src="<?php echo esc_url($base_img); ?>"
                alt="T-shirt base"
            />
            <img
                id="tee-artwork-overlay"
                class="tee-artwork-overlay"
                src="<?php echo esc_url($artwork_url); ?>"
                alt="Design artwork"
            />
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * UNIFIED: Enqueue color swap script for ALL t-shirt products
 * (Only runs ONCE, on wp_enqueue_scripts)
 */
add_action('wp_enqueue_scripts', 'unified_tee_enqueue_scripts', 20);

function unified_tee_enqueue_scripts() {
    if (!is_product()) {
        return;
    }

    global $post, $product;
    if (!$post || !$product) {
        return;
    }

    $product_id = $post->ID;

    // Only for variable products
    if (!$product->is_type('variable')) {
        return;
    }

    // Check if it's a t-shirt or designer product
    $is_tshirt = has_term('tshirts', 'product_cat', $product_id);
    $is_designer = is_arton360_designer_product($product_id);

    if (!$is_tshirt && !$is_designer) {
        return;
    }

    // Build color map
    $color_map = array();

    if ($is_designer) {
        // Use Arton360 plugin's images
        $base_url = plugin_dir_url(__FILE__ . '/../../plugins/arton360-designer-windows/arton360-designer.php') . 'arton360-designer-windows/assets/tshirts/';
        $color_map = array(
            'white' => $base_url . 'white.png',
            'black' => $base_url . 'black.png',
            'red'   => $base_url . 'red.png',
            'gray'  => $base_url . 'gray.png',
            'navy'  => $base_url . 'navy.png',
        );
    } else {
        // Use term meta images
        $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $img_id = (int) get_term_meta($term->term_id, 'tee_base_image_id', true);
                if ($img_id) {
                    $url = wp_get_attachment_image_url($img_id, 'full');
                    if ($url) {
                        $color_map[$term->slug] = $url;
                    }
                }
            }
        }
    }

    if (empty($color_map)) {
        return;
    }

    // Enqueue CSS
    wp_add_inline_style('kadence-global', '
        .tee-layered-wrapper {
            position: relative;
            width: 100%;
            max-width: 700px;
            margin: 0 auto;
        }
        .tee-layered-inner {
            position: relative;
            width: 100%;
            padding-bottom: 100%; /* 1:1 aspect ratio */
            overflow: hidden;
        }
        .tee-base-image,
        .tee-artwork-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }
        .tee-artwork-overlay {
            pointer-events: none;
            z-index: 10;
        }
        
        /* Hide default WooCommerce gallery for t-shirts */
        body.single-product.product-cat-tshirts .woocommerce-product-gallery:not(.tee-layered-wrapper),
        body.arton360-designer-product .woocommerce-product-gallery:not(.tee-layered-wrapper) {
            display: none !important;
        }
    ');

    // Enqueue JS
    wp_enqueue_script(
        'tee-color-swap',
        get_stylesheet_directory_uri() . '/js/tee-color-swap.js',
        array('jquery'),
        '2.0.0', // Incremented version
        true
    );

    // Localize with unified data structure
    wp_localize_script('tee-color-swap', 'TEE_SWAP', array(
        'tax' => TEE_COLOR_TAX,
        'map' => $color_map,
    ));
}

/**
 * REMOVE duplicate gallery hiding (handled by CSS above)
 * This was causing conflicts
 */
// REMOVED: add_action('wp_head', ...) that hides gallery