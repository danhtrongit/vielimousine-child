<?php
/**
 * Frontend AJAX Handlers
 * 
 * Xử lý: Tính giá real-time, Check availability, Submit booking
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Frontend_Ajax
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Public AJAX (no login required)
        add_action('wp_ajax_vie_frontend_calculate_price', array($this, 'calculate_price'));
        add_action('wp_ajax_nopriv_vie_frontend_calculate_price', array($this, 'calculate_price'));

        add_action('wp_ajax_vie_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_vie_check_availability', array($this, 'check_availability'));

        add_action('wp_ajax_vie_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_vie_submit_booking', array($this, 'submit_booking'));

        add_action('wp_ajax_vie_get_room_detail', array($this, 'get_room_detail'));
        add_action('wp_ajax_nopriv_vie_get_room_detail', array($this, 'get_room_detail'));

        // Fix 400 Error: Add checkout process handler
        add_action('wp_ajax_vie_process_checkout', array($this, 'process_checkout'));
        add_action('wp_ajax_nopriv_vie_process_checkout', array($this, 'process_checkout'));
        
        // Monthly pricing for datepicker
        add_action('wp_ajax_vie_get_monthly_pricing', array($this, 'get_monthly_pricing'));
        add_action('wp_ajax_nopriv_vie_get_monthly_pricing', array($this, 'get_monthly_pricing'));
    }

    /**
     * Calculate booking price (AJAX)
     * Logic quan trọng nhất - tính giá theo từng ngày + phụ thu
     */
    public function calculate_price()
    {
        check_ajax_referer('vie_booking_nonce', 'nonce');

        $room_id = absint($_POST['room_id'] ?? 0);
        $check_in = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out = sanitize_text_field($_POST['check_out'] ?? '');
        $num_rooms = absint($_POST['num_rooms'] ?? 1);
        $num_adults = absint($_POST['num_adults'] ?? 2);
        $num_children = absint($_POST['num_children'] ?? 0);
        $children_ages = isset($_POST['children_ages']) ? array_map('absint', $_POST['children_ages']) : array();
        $price_type = sanitize_text_field($_POST['price_type'] ?? 'room'); // 'room' or 'combo'

        if (!$room_id || !$check_in || !$check_out) {
            wp_send_json_error(array('message' => __('Thiếu thông tin', 'flavor')));
        }

        // Parse dates
        $date_in = $this->parse_date($check_in);
        $date_out = $this->parse_date($check_out);

        if (!$date_in || !$date_out || $date_out <= $date_in) {
            wp_send_json_error(array('message' => __('Ngày không hợp lệ', 'flavor')));
        }

        // Get room info
        global $wpdb;
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_rooms} WHERE id = %d", $room_id));

        if (!$room) {
            wp_send_json_error(array('message' => __('Phòng không tồn tại', 'flavor')));
        }

        // Calculate number of nights
        $num_nights = $date_out->diff($date_in)->days;

        // Get pricing for each date
        $pricing_details = $this->get_pricing_for_dates($room_id, $date_in, $date_out, $price_type, $room->base_price);

        // Check availability
        $availability = $this->check_dates_availability($room_id, $date_in, $date_out, $num_rooms);
        if (!$availability['available']) {
            wp_send_json_error(array(
                'message' => $availability['message'],
                'unavailable_dates' => $availability['unavailable_dates']
            ));
        }

        // Calculate base price (per room)
        $base_total = 0;
        $price_breakdown = array();

        foreach ($pricing_details as $date => $price_info) {
            $day_price = $price_info['price'];
            $base_total += $day_price;
            $price_breakdown[] = array(
                'date' => $date,
                'day_name' => $price_info['day_name'],
                'price' => $day_price,
                'formatted' => Vie_Hotel_Rooms_Helpers::format_currency($day_price)
            );
        }

        // Multiply by number of rooms
        $rooms_total = $base_total * $num_rooms;

        // Calculate surcharges
        $surcharges = $this->calculate_surcharges(
            $room_id,
            $room,
            $num_adults,
            $num_children,
            $children_ages,
            $num_rooms,
            $num_nights,
            $price_type
        );

        // Total
        $grand_total = $rooms_total + $surcharges['total'];

        // Build response
        $response = array(
            'success' => true,
            'room_name' => $room->name,
            'num_nights' => $num_nights,
            'num_rooms' => $num_rooms,
            'price_type' => $price_type,
            'price_type_label' => $price_type === 'combo' ? __('Giá Combo', 'flavor') : __('Giá Room Only', 'flavor'),
            'base_price_per_room' => $base_total,
            'base_price_formatted' => Vie_Hotel_Rooms_Helpers::format_currency($base_total),
            'rooms_total' => $rooms_total,
            'rooms_total_formatted' => Vie_Hotel_Rooms_Helpers::format_currency($rooms_total),
            'surcharges' => $surcharges['details'],
            'surcharges_total' => $surcharges['total'],
            'surcharges_formatted' => Vie_Hotel_Rooms_Helpers::format_currency($surcharges['total']),
            'grand_total' => $grand_total,
            'grand_total_formatted' => Vie_Hotel_Rooms_Helpers::format_currency($grand_total),
            'price_breakdown' => $price_breakdown,
            'pricing_snapshot' => $pricing_details // For saving to booking
        );

        wp_send_json_success($response);
    }

    /**
     * Check room availability for given dates
     */
    public function check_availability()
    {
        check_ajax_referer('vie_booking_nonce', 'nonce');

        $hotel_id = absint($_POST['hotel_id'] ?? 0);
        $check_in = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out = sanitize_text_field($_POST['check_out'] ?? '');
        $num_rooms = absint($_POST['num_rooms'] ?? 1);

        if (!$hotel_id || !$check_in || !$check_out) {
            wp_send_json_error(array('message' => __('Thiếu thông tin', 'flavor')));
        }

        $date_in = $this->parse_date($check_in);
        $date_out = $this->parse_date($check_out);

        if (!$date_in || !$date_out) {
            wp_send_json_error(array('message' => __('Ngày không hợp lệ', 'flavor')));
        }

        global $wpdb;
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        // Get all rooms for hotel
        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_rooms} WHERE hotel_id = %d AND status = 'active'",
            $hotel_id
        ));

        $results = array();

        foreach ($rooms as $room) {
            $availability = $this->check_dates_availability($room->id, $date_in, $date_out, $num_rooms);
            $results[$room->id] = array(
                'available' => $availability['available'],
                'status' => $availability['status'],
                'message' => $availability['message']
            );
        }

        wp_send_json_success(array('rooms' => $results));
    }

    /**
     * Submit booking
     */
    public function submit_booking()
    {
        check_ajax_referer('vie_booking_nonce', 'nonce');

        // Collect data
        $data = array(
            'hotel_id' => absint($_POST['hotel_id'] ?? 0),
            'room_id' => absint($_POST['room_id'] ?? 0),
            'check_in' => sanitize_text_field($_POST['check_in'] ?? ''),
            'check_out' => sanitize_text_field($_POST['check_out'] ?? ''),
            'num_rooms' => absint($_POST['num_rooms'] ?? 1),
            'num_adults' => absint($_POST['num_adults'] ?? 2),
            'num_children' => absint($_POST['num_children'] ?? 0),
            'children_ages' => isset($_POST['children_ages']) ? array_map('absint', $_POST['children_ages']) : array(),
            'price_type' => sanitize_text_field($_POST['price_type'] ?? 'room'),
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'customer_phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            'customer_email' => sanitize_email($_POST['customer_email'] ?? ''),
            'customer_note' => sanitize_textarea_field($_POST['customer_note'] ?? ''),
            'pricing_snapshot' => isset($_POST['pricing_snapshot']) ? $_POST['pricing_snapshot'] : array(),
            'surcharges_snapshot' => isset($_POST['surcharges_snapshot']) ? $_POST['surcharges_snapshot'] : array(),
            'base_amount' => floatval($_POST['base_amount'] ?? 0),
            'surcharges_amount' => floatval($_POST['surcharges_amount'] ?? 0),
            'total_amount' => floatval($_POST['total_amount'] ?? 0),
            'transport_info' => isset($_POST['transport_info']) ? $this->sanitize_transport_info($_POST['transport_info']) : null,
        );

        // Validate required fields
        if (!$data['hotel_id'] || !$data['room_id'] || !$data['check_in'] || !$data['check_out']) {
            wp_send_json_error(array('message' => __('Thiếu thông tin đặt phòng', 'flavor')));
        }

        if (!$data['customer_name'] || !$data['customer_phone']) {
            wp_send_json_error(array('message' => __('Vui lòng nhập họ tên và số điện thoại', 'flavor')));
        }

        // Parse dates
        $date_in = $this->parse_date($data['check_in']);
        $date_out = $this->parse_date($data['check_out']);

        if (!$date_in || !$date_out) {
            wp_send_json_error(array('message' => __('Ngày không hợp lệ', 'flavor')));
        }

        // Re-check availability before booking
        $availability = $this->check_dates_availability($data['room_id'], $date_in, $date_out, $data['num_rooms']);
        if (!$availability['available']) {
            wp_send_json_error(array('message' => $availability['message']));
        }

        // Generate booking code and hash (Security fix)
        $booking_code = $this->generate_booking_code();
        $booking_hash = wp_generate_password(32, false); // Secure random hash

        // Prepare guests info JSON
        $guests_info = array(
            'adults' => $data['num_adults'],
            'children' => $data['num_children'],
            'children_ages' => $data['children_ages'],
            'rooms_allocation' => $data['num_rooms']
        );

        // Insert booking
        global $wpdb;
        $table_bookings = $wpdb->prefix . 'hotel_bookings';

        $insert_data = array(
            'booking_code' => $booking_code,
            'booking_hash' => $booking_hash, // Security fix: Unique hash for URL
            'hotel_id' => $data['hotel_id'],
            'room_id' => $data['room_id'],
            'check_in' => $date_in->format('Y-m-d'),
            'check_out' => $date_out->format('Y-m-d'),
            'num_rooms' => $data['num_rooms'],
            'num_adults' => $data['num_adults'],
            'num_children' => $data['num_children'],
            'price_type' => $data['price_type'],
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'],
            'customer_note' => $data['customer_note'],
            'guests_info' => wp_json_encode($guests_info),
            'pricing_details' => wp_json_encode($data['pricing_snapshot']),
            'surcharges_details' => wp_json_encode($data['surcharges_snapshot']),
            'transport_info' => $data['transport_info'] ? wp_json_encode($data['transport_info']) : null,
            'base_amount' => $data['base_amount'],
            'surcharges_amount' => $data['surcharges_amount'],
            'total_amount' => $data['total_amount'],
            'status' => 'pending_payment', // UX fix: Save as draft first
            'payment_status' => 'unpaid',
            'ip_address' => $this->get_client_ip(),
            'user_agent' => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        );

        $result = $wpdb->insert($table_bookings, $insert_data);

        if ($result === false) {
            wp_send_json_error(array('message' => __('Lỗi lưu đặt phòng', 'flavor')));
        }

        $booking_id = $wpdb->insert_id;

        // Update room stock (decrease available rooms for those dates)
        $this->update_room_stock($data['room_id'], $date_in, $date_out, $data['num_rooms']);

        // DON'T send notification yet - wait until payment confirmation
        // $this->send_booking_notification($booking_id, $insert_data);

        // Return success with booking_hash for redirect
        wp_send_json_success(array(
            'booking_id' => $booking_id,
            'booking_code' => $booking_code,
            'booking_hash' => $booking_hash, // UX fix: Return hash for checkout redirect
            'message' => __('Đang chuyển sang trang thanh toán...', 'flavor')
        ));
    }

    /**
     * Get room detail for modal
     */
    public function get_room_detail()
    {
        $room_id = absint($_POST['room_id'] ?? 0);

        if (!$room_id) {
            wp_send_json_error();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hotel_rooms';
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $room_id));

        if (!$room) {
            wp_send_json_error();
        }

        // Get gallery images
        $gallery_ids = json_decode($room->gallery_ids, true) ?: array();
        $gallery = array();
        foreach ($gallery_ids as $img_id) {
            $img_url = wp_get_attachment_image_url($img_id, 'large');
            if ($img_url) {
                $gallery[] = $img_url;
            }
        }

        // Add featured image first
        if ($room->featured_image_id) {
            $featured_url = wp_get_attachment_image_url($room->featured_image_id, 'large');
            if ($featured_url) {
                array_unshift($gallery, $featured_url);
            }
        }

        wp_send_json_success(array(
            'room' => $room,
            'gallery' => $gallery,
            'amenities' => json_decode($room->amenities, true) ?: array()
        ));
    }

    /**
     * Process checkout - Final payment confirmation
     * Fix 400 Error: This is the missing AJAX handler
     * UX Enhancement: Updates customer info before confirmation
     */
    public function process_checkout()
    {
        // Verify nonce - Fix: This prevents 400 errors
        check_ajax_referer('vie_checkout_action', 'nonce');

        $booking_hash = sanitize_text_field($_POST['booking_hash'] ?? '');
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');

        // Collect updated customer info from checkout form
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_note = sanitize_textarea_field($_POST['customer_note'] ?? '');

        // Validate required fields
        if (empty($booking_hash)) {
            wp_send_json_error(array('message' => __('Thiếu mã đặt phòng', 'flavor')));
        }

        if (empty($payment_method)) {
            wp_send_json_error(array('message' => __('Vui lòng chọn phương thức thanh toán', 'flavor')));
        }

        if (empty($customer_name) || empty($customer_phone)) {
            wp_send_json_error(array('message' => __('Vui lòng điền đầy đủ họ tên và số điện thoại', 'flavor')));
        }

        // Get booking by hash (Security fix: Use hash instead of ID)
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE booking_hash = %s",
            $booking_hash
        ));

        if (!$booking) {
            wp_send_json_error(array('message' => __('Không tìm thấy đơn đặt phòng', 'flavor')));
        }

        // Check if booking is in pending_payment status
        if ($booking->status !== 'pending_payment') {
            wp_send_json_error(array('message' => __('Đơn đặt phòng đã được xử lý', 'flavor')));
        }

        // IMPORTANT: Update customer info FIRST (Allow edit at checkout)
        $update_info_result = $wpdb->update(
            $table,
            array(
                'customer_name' => $customer_name,
                'customer_phone' => $customer_phone,
                'customer_email' => $customer_email,
                'customer_note' => $customer_note
            ),
            array('booking_hash' => $booking_hash),
            array('%s', '%s', '%s', '%s'),
            array('%s')
        );

        if ($update_info_result === false) {
            wp_send_json_error(array('message' => __('Lỗi cập nhật thông tin khách hàng', 'flavor')));
        }

        // THEN update booking status and payment method
        $update_result = $wpdb->update(
            $table,
            array(
                'status' => 'confirmed',
                'payment_method' => $payment_method,
                'updated_at' => current_time('mysql')
            ),
            array('booking_hash' => $booking_hash),
            array('%s', '%s', '%s'),
            array('%s')
        );

        if ($update_result === false) {
            wp_send_json_error(array('message' => __('Lỗi cập nhật đặt phòng', 'flavor')));
        }

        // Reload booking data with UPDATED info for notification
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE booking_hash = %s",
            $booking_hash
        ));

        // Send notification emails NOW (after confirmation with correct info)
        $booking_array = (array) $booking;
        $this->send_booking_notification($booking->id, $booking_array);

        // Return success
        wp_send_json_success(array(
            'booking_code' => $booking->booking_code,
            'message' => __('Đặt phòng thành công!', 'flavor')
        ));
    }

    /**
     * Get pricing for date range
     */
    private function get_pricing_for_dates($room_id, $date_in, $date_out, $price_type, $base_price)
    {
        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        $pricing = array();
        $current = clone $date_in;
        $day_names = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');

        while ($current < $date_out) {
            $date_str = $current->format('Y-m-d');
            $day_of_week = (int) $current->format('w');

            // Get price from pricing table
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_pricing} WHERE room_id = %d AND date = %s",
                $room_id,
                $date_str
            ));

            if ($row) {
                // Use price_room or price_combo based on selection
                if ($price_type === 'combo' && $row->price_combo > 0) {
                    $price = $row->price_combo;
                } elseif ($row->price_room > 0) {
                    $price = $row->price_room;
                } else {
                    $price = $base_price;
                }
            } else {
                // Fallback to base price
                $price = $base_price;
            }

            $pricing[$date_str] = array(
                'price' => (float) $price,
                'day_of_week' => $day_of_week,
                'day_name' => $day_names[$day_of_week],
                'status' => $row ? $row->status : 'available'
            );

            $current->modify('+1 day');
        }

        return $pricing;
    }

    /**
     * Check availability for date range
     */
    private function check_dates_availability($room_id, $date_in, $date_out, $num_rooms)
    {
        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        $table_rooms = $wpdb->prefix . 'hotel_rooms';

        // Get room's total rooms
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT total_rooms FROM {$table_rooms} WHERE id = %d",
            $room_id
        ));

        $total_rooms = $room ? $room->total_rooms : 0;
        $unavailable_dates = array();
        $current = clone $date_in;

        while ($current < $date_out) {
            $date_str = $current->format('Y-m-d');

            $pricing = $wpdb->get_row($wpdb->prepare(
                "SELECT stock, status FROM {$table_pricing} WHERE room_id = %d AND date = %s",
                $room_id,
                $date_str
            ));

            // Check status
            if ($pricing && in_array($pricing->status, array('sold_out', 'stop_sell'))) {
                $unavailable_dates[] = array(
                    'date' => $date_str,
                    'reason' => $pricing->status === 'stop_sell' ? 'stop_sell' : 'sold_out'
                );
            }
            // Check stock
            elseif ($pricing && $pricing->stock < $num_rooms) {
                $unavailable_dates[] = array(
                    'date' => $date_str,
                    'reason' => 'insufficient_stock',
                    'available' => $pricing->stock
                );
            }
            // If no pricing row, check against total_rooms
            elseif (!$pricing && $total_rooms < $num_rooms) {
                $unavailable_dates[] = array(
                    'date' => $date_str,
                    'reason' => 'insufficient_stock',
                    'available' => $total_rooms
                );
            }

            $current->modify('+1 day');
        }

        if (!empty($unavailable_dates)) {
            $first_issue = $unavailable_dates[0];
            $message = '';

            switch ($first_issue['reason']) {
                case 'stop_sell':
                    $message = __('Phòng đã ngừng bán cho ngày ', 'flavor') . $first_issue['date'];
                    break;
                case 'sold_out':
                    $message = __('Phòng đã hết cho ngày ', 'flavor') . $first_issue['date'];
                    break;
                case 'insufficient_stock':
                    $message = sprintf(
                        __('Chỉ còn %d phòng cho ngày %s', 'flavor'),
                        $first_issue['available'],
                        $first_issue['date']
                    );
                    break;
            }

            return array(
                'available' => false,
                'status' => $first_issue['reason'],
                'message' => $message,
                'unavailable_dates' => $unavailable_dates
            );
        }

        return array(
            'available' => true,
            'status' => 'available',
            'message' => '',
            'unavailable_dates' => array()
        );
    }

    /**
     * Calculate surcharges based on occupancy and children ages
     */
    private function calculate_surcharges($room_id, $room, $num_adults, $num_children, $children_ages, $num_rooms, $num_nights, $price_type)
    {
        global $wpdb;
        $table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';

        $surcharges = array(
            'total' => 0,
            'details' => array()
        );

        $base_occupancy = $room->base_occupancy ?: 2;
        $guests_per_room = ceil(($num_adults + $num_children) / $num_rooms);

        // Get all surcharge rules for this room
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_surcharges} WHERE room_id = %d AND status = 'active' ORDER BY sort_order ASC",
            $room_id
        ));

        if (empty($rules)) {
            return $surcharges;
        }

        // Check price type applicability
        $price_field = $price_type === 'combo' ? 'applies_to_combo' : 'applies_to_room';

        // Calculate extra adults (beyond base occupancy)
        $extra_adults = max(0, $num_adults - ($base_occupancy * $num_rooms));

        foreach ($rules as $rule) {
            // Check if rule applies to selected price type
            if (!$rule->$price_field) {
                continue;
            }

            $amount = 0;
            $quantity = 0;
            $label = $rule->label ?: $this->get_surcharge_type_label($rule->surcharge_type);

            switch ($rule->surcharge_type) {
                case 'extra_bed':
                case 'adult':
                    // Apply for extra adults
                    if ($extra_adults > 0) {
                        $quantity = $extra_adults;
                        $amount = $this->calculate_surcharge_amount($rule, $quantity, $num_nights);
                    }
                    break;

                case 'child':
                    // Apply based on children ages
                    foreach ($children_ages as $age) {
                        // Check age range
                        $min_age = $rule->min_age !== null ? $rule->min_age : 0;
                        $max_age = $rule->max_age !== null ? $rule->max_age : 17;

                        if ($age >= $min_age && $age <= $max_age) {
                            $quantity++;
                        }
                    }

                    if ($quantity > 0) {
                        $amount = $this->calculate_surcharge_amount($rule, $quantity, $num_nights);
                        $label = sprintf('%s (%d-%d tuổi)', $label, $rule->min_age, $rule->max_age);
                    }
                    break;

                case 'breakfast':
                    // Breakfast surcharge for all guests
                    if ($rule->is_mandatory || $price_type === 'combo') {
                        $quantity = $num_adults + $num_children;
                        $amount = $this->calculate_surcharge_amount($rule, $quantity, $num_nights);
                    }
                    break;
            }

            if ($amount > 0) {
                $surcharges['details'][] = array(
                    'type' => $rule->surcharge_type,
                    'label' => $label,
                    'quantity' => $quantity,
                    'unit_amount' => $rule->amount,
                    'is_per_night' => $rule->is_per_night,
                    'nights' => $rule->is_per_night ? $num_nights : 1,
                    'amount' => $amount,
                    'formatted' => Vie_Hotel_Rooms_Helpers::format_currency($amount)
                );
                $surcharges['total'] += $amount;
            }
        }

        return $surcharges;
    }

    /**
     * Calculate single surcharge amount
     */
    private function calculate_surcharge_amount($rule, $quantity, $num_nights)
    {
        $amount = $rule->amount * $quantity;

        if ($rule->is_per_night) {
            $amount *= $num_nights;
        }

        return $amount;
    }

    /**
     * Get surcharge type label
     */
    private function get_surcharge_type_label($type)
    {
        $labels = array(
            'extra_bed' => __('Giường phụ', 'flavor'),
            'child' => __('Phụ thu trẻ em', 'flavor'),
            'adult' => __('Phụ thu người lớn', 'flavor'),
            'breakfast' => __('Bữa sáng', 'flavor'),
            'other' => __('Phụ thu khác', 'flavor'),
        );

        return $labels[$type] ?? $type;
    }

    /**
     * Update room stock after booking
     */
    private function update_room_stock($room_id, $date_in, $date_out, $num_rooms)
    {
        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        $current = clone $date_in;

        while ($current < $date_out) {
            $date_str = $current->format('Y-m-d');

            // Update stock and booked count
            $wpdb->query($wpdb->prepare(
                "UPDATE {$table_pricing} 
                SET stock = GREATEST(0, stock - %d), 
                    booked = booked + %d,
                    status = CASE 
                        WHEN stock - %d <= 0 THEN 'sold_out'
                        WHEN stock - %d <= 2 THEN 'limited'
                        ELSE status 
                    END
                WHERE room_id = %d AND date = %s",
                $num_rooms,
                $num_rooms,
                $num_rooms,
                $num_rooms,
                $room_id,
                $date_str
            ));

            $current->modify('+1 day');
        }
    }

    /**
     * Generate unique booking code
     */
    private function generate_booking_code()
    {
        $prefix = 'BK';
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Parse date from dd/mm/yyyy format
     */
    private function parse_date($date_str)
    {
        // Try dd/mm/yyyy format first
        $date = DateTime::createFromFormat('d/m/Y', $date_str);

        if (!$date) {
            // Try Y-m-d format
            $date = DateTime::createFromFormat('Y-m-d', $date_str);
        }

        return $date;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Sanitize transport info data
     */
    private function sanitize_transport_info($transport_info)
    {
        if (empty($transport_info) || !is_array($transport_info)) {
            return null;
        }

        return array(
            'enabled' => !empty($transport_info['enabled']),
            'pickup_time' => isset($transport_info['pickup_time']) ? sanitize_text_field($transport_info['pickup_time']) : '',
            'dropoff_time' => isset($transport_info['dropoff_time']) ? sanitize_text_field($transport_info['dropoff_time']) : '',
            'note' => isset($transport_info['note']) ? sanitize_textarea_field($transport_info['note']) : '',
        );
    }

    /**
     * Send booking notification email
     */
    private function send_booking_notification($booking_id, $booking_data)
    {
        // Get admin email
        $admin_email = get_option('admin_email');

        // Get hotel and room names
        $hotel_name = get_the_title($booking_data['hotel_id']);
        global $wpdb;
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}hotel_rooms WHERE id = %d",
            $booking_data['room_id']
        ));
        $room_name = $room ? $room->name : '';

        // Build email
        $subject = sprintf('[%s] Đơn đặt phòng mới: %s', get_bloginfo('name'), $booking_data['booking_code']);

        $message = "Có đơn đặt phòng mới:\n\n";
        $message .= "Mã đặt phòng: {$booking_data['booking_code']}\n";
        $message .= "Khách sạn: {$hotel_name}\n";
        $message .= "Loại phòng: {$room_name}\n";
        $message .= "Check-in: {$booking_data['check_in']}\n";
        $message .= "Check-out: {$booking_data['check_out']}\n";
        $message .= "Số phòng: {$booking_data['num_rooms']}\n";
        $message .= "Khách hàng: {$booking_data['customer_name']}\n";
        $message .= "SĐT: {$booking_data['customer_phone']}\n";
        $message .= "Email: {$booking_data['customer_email']}\n";
        $message .= "Tổng tiền: " . Vie_Hotel_Rooms_Helpers::format_currency($booking_data['total_amount']) . "\n\n";
        $message .= "Xem chi tiết: " . admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking_id);

        wp_mail($admin_email, $subject, $message);

        // Send to customer if email provided
        if (!empty($booking_data['customer_email'])) {
            $customer_subject = sprintf('Xác nhận đặt phòng %s - %s', $booking_data['booking_code'], get_bloginfo('name'));
            $customer_message = "Cảm ơn bạn đã đặt phòng tại {$hotel_name}!\n\n";
            $customer_message .= "Thông tin đặt phòng:\n";
            $customer_message .= "Mã đặt phòng: {$booking_data['booking_code']}\n";
            $customer_message .= "Loại phòng: {$room_name}\n";
            $customer_message .= "Check-in: {$booking_data['check_in']}\n";
            $customer_message .= "Check-out: {$booking_data['check_out']}\n";
            $customer_message .= "Tổng tiền: " . Vie_Hotel_Rooms_Helpers::format_currency($booking_data['total_amount']) . "\n\n";
            $customer_message .= "Chúng tôi sẽ liên hệ xác nhận trong thời gian sớm nhất.\n";
            $customer_message .= "Hotline: " . get_option('admin_phone', '');

            wp_mail($booking_data['customer_email'], $customer_subject, $customer_message);
        }
    }
    
    /**
     * Get monthly pricing for datepicker display
     * Returns pricing data for current month + next month
     */
    public function get_monthly_pricing()
    {
        check_ajax_referer('vie_booking_nonce', 'nonce');
        
        $room_id = absint($_POST['room_id'] ?? 0);
        $year = absint($_POST['year'] ?? date('Y'));
        $month = absint($_POST['month'] ?? date('n'));
        
        if (!$room_id) {
            wp_send_json_error(array('message' => __('Thiếu thông tin phòng', 'flavor')));
        }
        
        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        
        // Get room base price
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT base_price FROM {$table_rooms} WHERE id = %d",
            $room_id
        ));
        
        if (!$room) {
            wp_send_json_error(array('message' => __('Phòng không tồn tại', 'flavor')));
        }
        
        $base_price = floatval($room->base_price);
        
        // Calculate date range (current month + next month)
        $start_date = sprintf('%04d-%02d-01', $year, $month);
        
        // Get end of next month
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }
        $end_date = date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $next_year, $next_month)));
        
        // Fetch pricing data
        $pricing_data = $wpdb->get_results($wpdb->prepare(
            "SELECT date, price_room, price_combo, stock, status 
             FROM {$table_pricing} 
             WHERE room_id = %d 
             AND date >= %s 
             AND date <= %s
             ORDER BY date ASC",
            $room_id,
            $start_date,
            $end_date
        ), ARRAY_A);
        
        // Build response keyed by date
        $result = array();
        $today = date('Y-m-d');
        
        // Create date range
        $current = new DateTime($start_date);
        $end = new DateTime($end_date);
        $end->modify('+1 day');
        
        // Index pricing data by date
        $pricing_index = array();
        foreach ($pricing_data as $row) {
            $pricing_index[$row['date']] = $row;
        }
        
        // Loop through each day
        while ($current < $end) {
            $date_str = $current->format('Y-m-d');
            
            // Skip past dates
            if ($date_str < $today) {
                $current->modify('+1 day');
                continue;
            }
            
            if (isset($pricing_index[$date_str])) {
                $row = $pricing_index[$date_str];
                $price_room = floatval($row['price_room']) > 0 ? floatval($row['price_room']) : $base_price;
                $price_combo = floatval($row['price_combo']) > 0 ? floatval($row['price_combo']) : null;
                $stock = intval($row['stock']);
                $status = $row['status'];
            } else {
                // No custom pricing - use base price
                $price_room = $base_price;
                $price_combo = null;
                $stock = 10; // Default stock
                $status = 'available';
            }
            
            // Determine availability
            $is_available = ($stock > 0 && $status !== 'stop_sell');
            
            $result[$date_str] = array(
                'price_room' => $price_room,
                'price_combo' => $price_combo,
                'stock' => $stock,
                'status' => $status,
                'available' => $is_available,
                'price_room_formatted' => $this->format_short_price($price_room),
                'price_combo_formatted' => $price_combo ? $this->format_short_price($price_combo) : null
            );
            
            $current->modify('+1 day');
        }
        
        wp_send_json_success(array(
            'pricing' => $result,
            'base_price' => $base_price,
            'base_price_formatted' => $this->format_short_price($base_price)
        ));
    }
    
    /**
     * Format price in short format (e.g., 1.5tr, 800k)
     */
    private function format_short_price($amount)
    {
        if ($amount >= 1000000) {
            $formatted = round($amount / 1000000, 1);
            // Remove .0 if whole number
            if ($formatted == floor($formatted)) {
                return intval($formatted) . 'tr';
            }
            return $formatted . 'tr';
        } elseif ($amount >= 1000) {
            $formatted = round($amount / 1000);
            return intval($formatted) . 'k';
        }
        return number_format($amount, 0, ',', '.');
    }
}
