<?php
/**
 * Kadence Child – TeePublic-style color swap (STABLE + SAFE)
 */

if (!defined('TEE_COLOR_TAX')) {
    define('TEE_COLOR_TAX', 'pa_color');
}

/**
 * ADMIN: Base T-shirt image field for color terms
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
 * PRODUCT PAGE: Layered base + artwork
 */
add_action('woocommerce_single_product_summary', function () {
    if (!is_product()) return;

    global $product;
    if (!$product || !is_a($product, 'WC_Product')) return;

    // Only variable products
    if (!$product->is_type('variable')) return;

    $product_id = $product->get_id();

    // IMPORTANT: temporarily DISABLE category restriction for debugging
    // if (!has_term('tshirts', 'product_cat', $product_id)) return;

    $artwork = get_the_post_thumbnail_url($product_id, 'full');
    if (!$artwork) return;

    $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
    if (is_wp_error($terms) || empty($terms)) return;

    $map = [];
    foreach ($terms as $term) {
        $img_id = (int) get_term_meta($term->term_id, 'tee_base_image_id', true);
        if ($img_id) {
            $url = wp_get_attachment_image_url($img_id, 'full');
            if ($url) $map[$term->slug] = $url;
        }
    }

    if (empty($map)) return;

    $base = reset($map);

    // Make sure JS has the map
    wp_enqueue_script(
        'tee-color-swap',
        get_stylesheet_directory_uri() . '/js/tee-color-swap.js',
        ['jquery'],
        '1.0.5',
        true
    );

    wp_localize_script('tee-color-swap', 'TEE_SWAP', [
        'tax' => TEE_COLOR_TAX,
        'map' => $map,
    ]);

    ?>
    <div class="tee-layered-wrap" style="position:relative;max-width:700px;margin:0 0 18px 0;">
        <img id="tee-base-image" src="<?php echo esc_url($base); ?>" style="width:100%;height:auto;display:block;" />
        <img id="tee-artwork-overlay" src="<?php echo esc_url($artwork); ?>" style="position:absolute;left:0;top:0;width:100%;height:auto;pointer-	events:none;" />
    </div>
    <?php
}, 6);

/**
 * Hide default gallery only for tshirts
 */
add_action('wp_head', function () {
    if (function_exists('is_product') && is_product() && has_term('tshirts', 'product_cat')) {
        echo '<style>.woocommerce-product-gallery{display:none!important}</style>';
    }
});

/**
 * Enqueue JS + localize map
 */
add_action('wp_enqueue_scripts', function () {
    if (!function_exists('is_product') || !is_product()) return;

    global $product;
    if (!$product || !is_a($product, 'WC_Product')) return;
    if (!$product->is_type('variable')) return;
    if (!has_term('tshirts', 'product_cat', $product->get_id())) return;

    $map = [];
    $terms = get_terms(['taxonomy' => TEE_COLOR_TAX, 'hide_empty' => false]);
    if (!is_wp_error($terms)) {
        foreach ($terms as $t) {
            $img_id = (int) get_term_meta($t->term_id, 'tee_base_image_id', true);
            if ($img_id) $map[$t->slug] = wp_get_attachment_image_url($img_id, 'full');
        }
    }

    wp_enqueue_script(
        'tee-color-swap',
        get_stylesheet_directory_uri() . '/js/tee-color-swap.js',
        ['jquery'],
        '1.0.6',
        true
    );

    wp_localize_script('tee-color-swap', 'TEE_SWAP', [
        'tax' => TEE_COLOR_TAX,
        'map' => $map,
    ]);
}, 20);
