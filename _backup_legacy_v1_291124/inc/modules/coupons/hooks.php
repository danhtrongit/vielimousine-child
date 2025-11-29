<?php
/**
 * Google Sheets Coupon System - WordPress Hooks Integration
 * 
 * Hook coupon system vào WordPress lifecycle và booking flow
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

/**
 * Enqueue frontend assets
 */
function vl_coupon_enqueue_assets()
{
    // Chỉ load trên trang checkout
    if (!is_page('checkout') && !is_page_template('page-checkout.php')) {
        return;
    }

    // Enqueue CSS
    wp_enqueue_style(
        'vl-coupon-form',
        get_stylesheet_directory_uri() . '/inc/modules/coupons/assets/coupon-form.css',
        [],
        '1.0.0'
    );

    // Enqueue jQuery (WordPress core)
    wp_enqueue_script('jquery');

    // Enqueue JS
    wp_enqueue_script(
        'vl-coupon-form',
        get_stylesheet_directory_uri() . '/inc/modules/coupons/assets/coupon-form.js',
        ['jquery'],
        '1.0.0',
        true
    );

    // Localize script với data cần thiết
    wp_localize_script('vl-coupon-form', 'vlCouponData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vl_coupon_nonce'),
        'applyNonce' => wp_create_nonce('vl_coupon_apply_nonce')
    ]);
}
add_action('wp_enqueue_scripts', 'vl_coupon_enqueue_assets');

/**
 * Render coupon form trong checkout page
 * Hook này cần tùy chỉnh tùy theo structure của checkout page
 */
function vl_render_coupon_form()
{
    ?>
    <div class="vl-coupon-section">
        <h3>Mã giảm giá</h3>

        <form id="vl-coupon-form">
            <div class="vl-coupon-input-group">
                <input type="text" id="vl-coupon-code" name="coupon_code" placeholder="Nhập mã giảm giá"
                    autocomplete="off" />
            </div>

            <button type="button" id="vl-coupon-validate" class="vl-coupon-btn">
                Áp dụng
            </button>

            <button type="button" id="vl-coupon-remove" class="vl-coupon-btn">
                Hủy
            </button>
        </form>

        <div id="vl-coupon-message"></div>

        <div class="vl-discount-summary">
            <div id="vl-coupon-discount"></div>
        </div>

        <!-- Hidden fields để submit với form -->
        <input type="hidden" id="vl-hidden-coupon-code" name="coupon_code_applied" value="" />
        <input type="hidden" id="vl-hidden-coupon-discount" name="coupon_discount" value="0" />
    </div>
    <?php
}

/**
 * Hook vào booking creation
 * Tùy chỉnh hook này dựa theo plugin booking đang dùng
 */
function vl_process_coupon_on_booking($booking_id, $booking_data)
{
    // Lấy coupon từ POST data
    $coupon_code = isset($_POST['coupon_code_applied']) ? sanitize_text_field($_POST['coupon_code_applied']) : '';
    $coupon_discount = isset($_POST['coupon_discount']) ? floatval($_POST['coupon_discount']) : 0;

    if (empty($coupon_code)) {
        return; // Không có coupon
    }

    VL_Logger::info('Processing coupon for booking', [
        'booking_id' => $booking_id,
        'coupon_code' => $coupon_code
    ]);

    // Lấy order total
    $order_total = isset($booking_data['total_price']) ? floatval($booking_data['total_price']) : 0;

    // Apply coupon (update Google Sheets)
    $validator = new VL_Coupon_Validator();
    $result = $validator->apply_coupon($coupon_code, $order_total, $booking_id);

    if ($result['valid']) {
        // Lưu thông tin coupon vào post meta
        update_post_meta($booking_id, '_coupon_code', $coupon_code);
        update_post_meta($booking_id, '_coupon_discount', $result['discount']);
        update_post_meta($booking_id, '_original_price', $order_total);
        update_post_meta($booking_id, '_final_price', $order_total - $result['discount']);

        VL_Logger::info('Coupon applied to booking successfully', [
            'booking_id' => $booking_id,
            'coupon_code' => $coupon_code,
            'discount' => $result['discount']
        ]);
    } else {
        VL_Logger::error('Failed to apply coupon to booking', [
            'booking_id' => $booking_id,
            'error' => $result['message']
        ]);
    }
}

// Hook vào booking creation
// TÙY CHỈNH: Thay 'your_booking_plugin_hook' bằng hook thực tế của plugin booking
// Ví dụ: 'wp_hotel_bookings_after_create_booking', 'woocommerce_new_order', etc.
// add_action('your_booking_plugin_hook', 'vl_process_coupon_on_booking', 10, 2);

/**
 * Display coupon info trong booking detail (admin)
 */
function vl_display_coupon_in_booking_admin($post)
{
    if ($post->post_type !== 'hotel_booking') { // Tùy chỉnh post type
        return;
    }

    $coupon_code = get_post_meta($post->ID, '_coupon_code', true);

    if (!$coupon_code) {
        return;
    }

    $discount = get_post_meta($post->ID, '_coupon_discount', true);
    $original_price = get_post_meta($post->ID, '_original_price', true);
    $final_price = get_post_meta($post->ID, '_final_price', true);

    ?>
    <div class="vl-coupon-info"
        style="background: #f0f9ff; border: 1px solid #0891b2; padding: 15px; margin: 10px 0; border-radius: 5px;">
        <h4 style="margin: 0 0 10px 0; color: #0891b2;">
            <span class="dashicons dashicons-tag"></span> Mã giảm giá đã áp dụng
        </h4>
        <p style="margin: 5px 0;">
            <strong>Mã:</strong> <?php echo esc_html($coupon_code); ?>
        </p>
        <p style="margin: 5px 0;">
            <strong>Giá gốc:</strong> <?php echo vl_format_currency($original_price); ?>
        </p>
        <p style="margin: 5px 0; color: #16a34a;">
            <strong>Giảm:</strong> -<?php echo vl_format_currency($discount); ?>
        </p>
        <p style="margin: 5px 0; font-size: 16px;">
            <strong>Tổng thanh toán:</strong> <?php echo vl_format_currency($final_price); ?>
        </p>
    </div>
    <?php
}
add_action('edit_form_after_title', 'vl_display_coupon_in_booking_admin');

/**
 * Add coupon column to bookings list (admin)
 */
function vl_add_coupon_column_to_bookings($columns)
{
    $columns['coupon'] = 'Mã giảm giá';
    return $columns;
}

function vl_display_coupon_column_content($column, $post_id)
{
    if ($column === 'coupon') {
        $coupon_code = get_post_meta($post_id, '_coupon_code', true);

        if ($coupon_code) {
            $discount = get_post_meta($post_id, '_coupon_discount', true);
            echo '<strong>' . esc_html($coupon_code) . '</strong><br>';
            echo '<small style="color: #16a34a;">-' . vl_format_currency($discount) . '</small>';
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
}

// TÙY CHỈNH: Thay 'manage_hotel_booking_posts_columns' bằng hook thực tế
// add_filter('manage_hotel_booking_posts_columns', 'vl_add_coupon_column_to_bookings');
// add_action('manage_hotel_booking_posts_custom_column', 'vl_display_coupon_column_content', 10, 2);
