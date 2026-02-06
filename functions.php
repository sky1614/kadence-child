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
        <label>Base T-shirt image</label>
        <input type="hidden" id="tee_base_image_id" name="tee_base_image_id">
        <button type="button" class="button tee-upload-image">Upload / Select</button>
        <div class="tee-image-preview" style="margin-top:10px;"></div>
    </div>
    <?php
});

add_action(TEE_COLOR_TAX . '_edit_form_fields', function ($term) {
    $image_id = (int) get_term_meta($term->term_id, 'tee_base_image_id', true);
    ?>
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
});

add_action('edited_' . TEE_COLOR_TAX, function ($term_id) {
    if (isset($_POST['tee_base_image_id'])) {
        update_term_meta($term_id, 'tee_base_image_id', (int) $_POST['tee_base_image_id']);
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
 * Unified preview renderer
 */
add_filter('woocommerce_single_product_image_thumbnail_html', 'unified_tee_color_preview', 10, 2);

function unified_tee_color_preview($html) {
    if (!is_product()) return $html;

    global $product, $post;
    if (!$product || !$post || !$product->is_type('variable')) return $html;

    $product_id = $post->ID;
    $is_tshirt  = has_term('tshirts', 'product_cat', $product_id);
    $is_designer = is_arton360_designer_product($product_id);

    if (!$is_tshirt && !$is_designer) return $html;

    $artwork_url = get_the_post_thumbnail_url($product_id, 'large');
    if (!$artwork_url) return $html;

    $color_map = array();

    if ($is_designer) {
        $base_url = plugins_url('assets/tshirts/', 'arton360-designer-windows/arton360.php');
        $colors = [
            'white','black','red','gray','grey','navy','ash','azalea','baby-blue',
            'cardinal','charcoal','dark-chocolate','daisy','forest','gold','kelly',
            'light-blue','light-pink','lime','maroon','mint','natural','olive',
            'orange','pink','purple','royal','sand','silver','sport-grey','teal','yellow'
        ];
        foreach ($colors as $c) {
            $color_map[$c] = $base_url . $c . '.png';
        }
    } else {
        $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $img_id = (int) get_term_meta($term->term_id, 'tee_base_image_id', true);
                if ($img_id) {
                    $url = wp_get_attachment_image_url($img_id, 'full');
                    if ($url) $color_map[$term->slug] = $url;
                }
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

    ob_start();
    ?>
    <div class="tee-layered-wrapper">
        <div class="tee-layered-inner">
            <img class="tee-base-image" src="<?php echo esc_url($color_map[$default_color]); ?>" />
            <img class="tee-artwork-overlay" src="<?php echo esc_url($artwork_url); ?>" />
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Frontend styles + JS
 */
add_action('wp_enqueue_scripts', function () {
    if (!is_product()) return;

    wp_enqueue_script(
        'tee-color-swap',
        get_stylesheet_directory_uri() . '/js/tee-color-swap.js',
        ['jquery'],
        '2.0.3',
        true
    );

    if (wp_style_is('kadence-global', 'enqueued')) {
        wp_add_inline_style('kadence-global', '
            .tee-layered-wrapper { max-width:700px; margin:auto; }
            .tee-layered-inner { position:relative; padding-bottom:100%; }
            .tee-layered-inner img { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; }
            .tee-artwork-overlay { pointer-events:none; z-index:10; }
        ');
    }
});
