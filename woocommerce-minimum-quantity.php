<?php
/*
Plugin Name: WooCommerce Minimum Quantity
Description: Adds a custom field to set the minimum order quantity for products in WooCommerce.
Author URI: https://divipro24.com
Plugin URI: https://divipro24.com
Version: 1.0.0
Author: Dmitri Andrejev
Github URI: https://github.com/divipro24/failed-login-logger
License: GPLv2
*/

// Защита от прямого доступа
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Добавляем новое поле на страницу редактирования продукта
add_action('woocommerce_product_options_general_product_data', 'custom_product_field');
function custom_product_field() {
    woocommerce_wp_text_input(
        array(
            'id' => '_custom_minimum_quantity',
            'label' => __('Minimum Order Quantity', 'woocommerce'),
            'desc_tip' => 'true',
            'description' => __('Enter the minimum quantity required to add this product to the cart.', 'woocommerce'),
            'type' => 'number',
            'custom_attributes' => array(
                'min' => '1',
                'step' => '1',
            ),
        )
    );
}

// Сохраняем значение пользовательского поля
add_action('woocommerce_process_product_meta', 'save_custom_product_field');
function save_custom_product_field($post_id) {
    $custom_field_value = isset($_POST['_custom_minimum_quantity']) ? sanitize_text_field($_POST['_custom_minimum_quantity']) : '';
    update_post_meta($post_id, '_custom_minimum_quantity', $custom_field_value);
}

// Устанавливаем минимальное количество в поле "количество" на странице товара и предотвращаем уменьшение ниже минимума
add_filter('woocommerce_quantity_input_args', 'set_minimum_quantity_input_value', 10, 2);
function set_minimum_quantity_input_value($args, $product) {
    $min_quantity = get_post_meta($product->get_id(), '_custom_minimum_quantity', true);
    if ($min_quantity) {
        $args['input_value'] = $min_quantity; // значение по умолчанию
        $args['min_value'] = $min_quantity; // минимальное значение
    }
    return $args;
}

// Проверяем минимальное количество при добавлении товара в корзину
add_filter('woocommerce_add_to_cart_validation', 'check_minimum_quantity', 10, 3);
function check_minimum_quantity($passed, $product_id, $quantity) {
    $min_quantity = get_post_meta($product_id, '_custom_minimum_quantity', true);

    if ($min_quantity && $quantity < $min_quantity) {
        wc_add_notice(
            sprintf(
                __('The minimum order quantity for %s is %s.', 'woocommerce'),
                get_the_title($product_id),
                $min_quantity
            ),
            'error'
        );
        $passed = false;
    }

    return $passed;
}

// Проверяем минимальное количество при обновлении корзины
add_action('woocommerce_after_cart_item_quantity_update', 'update_cart_quantity', 10, 4);
function update_cart_quantity($cart_item_key, $quantity, $old_quantity, $cart) {
    $product_id = $cart->cart_contents[$cart_item_key]['product_id'];
    $min_quantity = get_post_meta($product_id, '_custom_minimum_quantity', true);

    if ($min_quantity && $quantity < $min_quantity) {
        wc_add_notice(
            sprintf(
                __('The minimum order quantity for %s is %s. The quantity has been updated to the minimum allowed.', 'woocommerce'),
                get_the_title($product_id),
                $min_quantity
            ),
            'error'
        );

        $cart->cart_contents[$cart_item_key]['quantity'] = $min_quantity;
        WC()->cart->set_session();
    }
}

// Добавляем поле в интерфейс быстрого редактирования
add_action('quick_edit_custom_box', 'display_custom_quickedit_min_quantity', 10, 2);
function display_custom_quickedit_min_quantity($column_name, $post_type) {
    if ($post_type == 'product' && $column_name == 'name') { // column where the field should be displayed
        ?>
        <fieldset class="inline-edit-col-right inline-edit-product">
            <div class="inline-edit-col">
                <label>
                    <span class="title"><?php _e('Minimum Order Quantity', 'woocommerce'); ?></span>
                    <span class="input-text-wrap">
                        <input type="number" name="_custom_minimum_quantity" class="ptitle" value="">
                    </span>
                </label>
            </div>
        </fieldset>
        <?php
    }
}

// Сохранение значения из интерфейса быстрого редактирования
add_action('save_post', 'save_custom_quickedit_min_quantity');
function save_custom_quickedit_min_quantity($post_id) {
    if (isset($_POST['_custom_minimum_quantity'])) {
        $custom_field_value = sanitize_text_field($_POST['_custom_minimum_quantity']);
        update_post_meta($post_id, '_custom_minimum_quantity', $custom_field_value);
    }
}

// Обновление данных в интерфейсе быстрого редактирования с текущими значениями
add_action('admin_enqueue_scripts', 'enqueue_custom_quickedit_script');
function enqueue_custom_quickedit_script() {
    wp_enqueue_script('custom_quickedit', plugin_dir_url(__FILE__) . 'custom_quickedit.js', array('jquery', 'inline-edit-post'), '', true);
}

// Создание скрипта для обновления данных в интерфейсе быстрого редактирования
add_action('admin_footer', 'add_custom_quickedit_script');
function add_custom_quickedit_script() {
    ?>
    <script type="text/javascript">
        jQuery(function($) {
            var $wp_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                $wp_inline_edit.apply(this, arguments);
                var post_id = 0;
                if (typeof(id) == 'object') {
                    post_id = parseInt(this.getId(id));
                }
                if (post_id > 0) {
                    var $edit_row = $('#edit-' + post_id);
                    var $post_row = $('#post-' + post_id);
                    var min_quantity = $post_row.find('.hidden[data-min_quantity]').data('min_quantity');
                    $edit_row.find('input[name="_custom_minimum_quantity"]').val(min_quantity);
                }
            }
        });
    </script>
    <?php
}

// Добавление данных в колонку для отображения текущего значения
add_filter('manage_product_posts_columns', 'add_min_quantity_column');
add_action('manage_product_posts_custom_column', 'add_min_quantity_column_content', 10, 2);

function add_min_quantity_column($columns) {
    $columns['min_quantity'] = __('Min', 'woocommerce');
    return $columns;
}

function add_min_quantity_column_content($column, $post_id) {
    if ($column == 'min_quantity') {
        $min_quantity = get_post_meta($post_id, '_custom_minimum_quantity', true);
        echo '<span class="min_quantity_value">' . esc_html($min_quantity) . '</span>';
        echo '<span class="hidden" data-min_quantity="' . esc_html($min_quantity) . '"></span>';
    }
}

// Добавление надписи под ценой для товаров с минимальным количеством
add_action('woocommerce_single_product_summary', 'display_minimum_quantity_message', 25);
function display_minimum_quantity_message() {
    global $product;
    $min_quantity = get_post_meta($product->get_id(), '_custom_minimum_quantity', true);
    if ($min_quantity) {
        echo '<p class="minimum-quantity-message">' . sprintf(__('Minimum order quantity: %s', 'woocommerce'), esc_html($min_quantity)) . '</p>';
    }
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/divipro24/woocommerce-minimum-quantity',
    __FILE__,
    'woocommerce-minimum-quantity'
);

$myUpdateChecker->setBranch('main');