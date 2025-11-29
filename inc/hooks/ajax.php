<?php
/**
 * ============================================================================
 * TÊN FILE: ajax.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Đăng ký tất cả AJAX handlers cho theme.
 * File này chỉ ĐĂNG KÝ hooks, logic xử lý nằm trong các class riêng.
 * 
 * AJAX ACTIONS:
 * Frontend (Public):
 * - vie_calculate_price: Tính giá booking
 * - vie_check_availability: Kiểm tra phòng trống
 * - vie_submit_booking: Submit đặt phòng
 * - vie_get_room_detail: Lấy chi tiết phòng
 * - vie_get_calendar_prices: Lấy giá theo tháng
 * 
 * Admin:
 * - vie_update_booking_status: Cập nhật trạng thái booking
 * - vie_save_room: Lưu thông tin phòng
 * - vie_delete_room: Xóa phòng
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Hooks
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * FRONTEND AJAX (Public - không cần đăng nhập)
 * ============================================================================
 */

/**
 * Tính giá booking
 */
add_action('wp_ajax_vie_calculate_price', 'vie_ajax_calculate_price');
add_action('wp_ajax_nopriv_vie_calculate_price', 'vie_ajax_calculate_price');

function vie_ajax_calculate_price() {
    // Verify nonce
    check_ajax_referer('vie_booking_nonce', 'nonce');
    
    // Sanitize input
    $room_id = absint($_POST['room_id'] ?? 0);
    $hotel_id = absint($_POST['hotel_id'] ?? 0);
    $check_in = vie_sanitize_date($_POST['check_in'] ?? '');
    $check_out = vie_sanitize_date($_POST['check_out'] ?? '');
    $num_rooms = absint($_POST['num_rooms'] ?? 1);
    $num_adults = absint($_POST['num_adults'] ?? 2);
    $num_children = absint($_POST['num_children'] ?? 0);
    $children_ages = isset($_POST['children_ages']) ? array_map('absint', (array)$_POST['children_ages']) : [];
    $price_type = in_array($_POST['price_type'] ?? '', ['room', 'combo'], true) ? $_POST['price_type'] : 'room';
    
    // Validate required
    if (!$room_id || !$check_in || !$check_out) {
        wp_send_json_error(['message' => 'Thiếu thông tin bắt buộc']);
    }
    
    // Kiểm tra class tồn tại
    if (class_exists('Vie_Pricing_Engine')) {
        $pricing = Vie_Pricing_Engine::get_instance();
        $result = $pricing->calculate([
            'room_id' => $room_id,
            'hotel_id' => $hotel_id,
            'check_in' => $check_in,
            'check_out' => $check_out,
            'num_rooms' => $num_rooms,
            'num_adults' => $num_adults,
            'num_children' => $num_children,
            'children_ages' => $children_ages,
            'price_type' => $price_type,
        ]);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success($result);
    }
    
    // Fallback - basic calculation
    wp_send_json_error(['message' => 'Pricing engine not available']);
}

/**
 * Kiểm tra phòng trống
 */
add_action('wp_ajax_vie_check_availability', 'vie_ajax_check_availability');
add_action('wp_ajax_nopriv_vie_check_availability', 'vie_ajax_check_availability');

function vie_ajax_check_availability() {
    check_ajax_referer('vie_booking_nonce', 'nonce');
    
    $room_id = absint($_POST['room_id'] ?? 0);
    $check_in = vie_sanitize_date($_POST['check_in'] ?? '');
    $check_out = vie_sanitize_date($_POST['check_out'] ?? '');
    $num_rooms = absint($_POST['num_rooms'] ?? 1);
    
    if (!$room_id || !$check_in || !$check_out) {
        wp_send_json_error(['message' => 'Thiếu thông tin bắt buộc']);
    }
    
    $result = vie_check_room_availability($room_id, $check_in, $check_out, $num_rooms);
    
    wp_send_json_success($result);
}

/**
 * Submit đặt phòng
 */
add_action('wp_ajax_vie_submit_booking', 'vie_ajax_submit_booking');
add_action('wp_ajax_nopriv_vie_submit_booking', 'vie_ajax_submit_booking');

function vie_ajax_submit_booking() {
    check_ajax_referer('vie_booking_nonce', 'nonce');
    
    // Sanitize và validate data
    $data = vie_sanitize_booking_data($_POST);
    $validated = vie_validate_booking_data($data);
    
    if (is_wp_error($validated)) {
        wp_send_json_error(['message' => $validated->get_error_message()]);
    }
    
    // Kiểm tra class tồn tại
    if (class_exists('Vie_Booking_Manager')) {
        $booking_manager = Vie_Booking_Manager::get_instance();
        $result = $booking_manager->create_booking($validated);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }
        
        wp_send_json_success([
            'booking_id' => $result['booking_id'],
            'booking_code' => $result['booking_code'],
            'booking_hash' => $result['booking_hash'],
            'checkout_url' => add_query_arg('code', $result['booking_hash'], home_url('/checkout/')),
            'message' => 'Đặt phòng thành công!'
        ]);
    }
    
    wp_send_json_error(['message' => 'Booking manager not available']);
}

/**
 * Lấy chi tiết phòng
 */
add_action('wp_ajax_vie_get_room_detail', 'vie_ajax_get_room_detail');
add_action('wp_ajax_nopriv_vie_get_room_detail', 'vie_ajax_get_room_detail');

function vie_ajax_get_room_detail() {
    check_ajax_referer('vie_booking_nonce', 'nonce');
    
    $room_id = absint($_POST['room_id'] ?? 0);
    
    if (!$room_id) {
        wp_send_json_error(['message' => 'Thiếu room ID']);
    }
    
    $room = vie_get_room_by_id($room_id);
    
    if (!$room) {
        wp_send_json_error(['message' => 'Không tìm thấy phòng']);
    }
    
    // Parse gallery
    $gallery = [];
    if (!empty($room->gallery_ids)) {
        $ids = json_decode($room->gallery_ids, true);
        if (is_array($ids)) {
            foreach ($ids as $id) {
                $url = wp_get_attachment_image_url($id, 'large');
                if ($url) {
                    $gallery[] = [
                        'id' => $id,
                        'url' => $url,
                        'thumb' => wp_get_attachment_image_url($id, 'thumbnail')
                    ];
                }
            }
        }
    }
    
    wp_send_json_success([
        'id' => $room->id,
        'name' => $room->name,
        'description' => $room->description,
        'amenities' => json_decode($room->amenities, true) ?: [],
        'max_adults' => $room->max_adults,
        'max_children' => $room->max_children,
        'area' => $room->area,
        'bed_types' => json_decode($room->bed_types, true) ?: [],
        'base_price' => $room->base_price,
        'gallery' => $gallery,
    ]);
}

/**
 * Lấy giá theo tháng cho calendar
 */
add_action('wp_ajax_vie_get_calendar_prices', 'vie_ajax_get_calendar_prices');
add_action('wp_ajax_nopriv_vie_get_calendar_prices', 'vie_ajax_get_calendar_prices');

function vie_ajax_get_calendar_prices() {
    check_ajax_referer('vie_booking_nonce', 'nonce');
    
    $room_id = absint($_POST['room_id'] ?? 0);
    $hotel_id = absint($_POST['hotel_id'] ?? 0);
    $year = absint($_POST['year'] ?? date('Y'));
    $month = absint($_POST['month'] ?? date('n'));
    
    if (!$room_id && !$hotel_id) {
        wp_send_json_error(['message' => 'Thiếu room_id hoặc hotel_id']);
    }
    
    // Get first and last day of month
    $date_from = sprintf('%04d-%02d-01', $year, $month);
    $date_to = date('Y-m-t', strtotime($date_from));
    
    // Nếu có room_id, lấy giá của room đó
    // Nếu chỉ có hotel_id, lấy giá thấp nhất từ tất cả rooms
    if ($room_id) {
        $prices = vie_get_room_prices_range($room_id, $date_from, $date_to);
        $room = vie_get_room_by_id($room_id);
        $base_price = $room ? $room->base_price : 0;
        
        $result = [];
        $current = strtotime($date_from);
        $end = strtotime($date_to);
        
        while ($current <= $end) {
            $date = date('Y-m-d', $current);
            $price_data = $prices[$date] ?? null;
            
            $result[$date] = [
                'room_price' => $price_data->price ?? $base_price,
                'combo_price' => $price_data->combo_price ?? null,
                'stop_sell' => !empty($price_data->stop_sell),
                'available_rooms' => $price_data->available_rooms ?? 99,
            ];
            
            $current = strtotime('+1 day', $current);
        }
        
        wp_send_json_success($result);
    }
    
    wp_send_json_error(['message' => 'Cần room_id để lấy giá']);
}

/**
 * ============================================================================
 * ADMIN AJAX (Yêu cầu đăng nhập + capability)
 * ============================================================================
 */

/**
 * Cập nhật trạng thái booking
 */
add_action('wp_ajax_vie_update_booking_status', 'vie_ajax_update_booking_status');

function vie_ajax_update_booking_status() {
    check_ajax_referer('vie_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }
    
    $booking_id = absint($_POST['booking_id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    
    $valid_statuses = ['pending', 'confirmed', 'paid', 'cancelled', 'completed'];
    
    if (!$booking_id || !in_array($status, $valid_statuses, true)) {
        wp_send_json_error(['message' => 'Dữ liệu không hợp lệ']);
    }
    
    global $wpdb;
    $table = vie_get_table_bookings();
    
    $updated = $wpdb->update(
        $table,
        ['status' => $status, 'updated_at' => current_time('mysql')],
        ['id' => $booking_id],
        ['%s', '%s'],
        ['%d']
    );
    
    if ($updated === false) {
        wp_send_json_error(['message' => 'Cập nhật thất bại']);
    }
    
    wp_send_json_success(['message' => 'Cập nhật thành công']);
}
