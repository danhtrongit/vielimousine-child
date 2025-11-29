<?php
/**
 * ============================================================================
 * TÊN FILE: database.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Các hàm tiện ích thao tác với database
 * 
 * CHỨC NĂNG:
 * - vie_get_table_*(): Lấy tên các table
 * - vie_get_booking_by_*(): Query booking
 * - vie_get_room_by_*(): Query room
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Helpers
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * TABLE NAMES
 * ============================================================================
 */

/**
 * Lấy tên table rooms
 * 
 * @since   2.0.0
 * @return  string
 */
function vie_get_table_rooms(): string {
    global $wpdb;
    return $wpdb->prefix . 'hotel_room_types';
}

/**
 * Lấy tên table bookings
 * 
 * @since   2.0.0
 * @return  string
 */
function vie_get_table_bookings(): string {
    global $wpdb;
    return $wpdb->prefix . 'hotel_bookings';
}

/**
 * Lấy tên table room pricing
 * 
 * @since   2.0.0
 * @return  string
 */
function vie_get_table_pricing(): string {
    global $wpdb;
    return $wpdb->prefix . 'hotel_room_pricing';
}

/**
 * Lấy tên table surcharges
 * 
 * @since   2.0.0
 * @return  string
 */
function vie_get_table_surcharges(): string {
    global $wpdb;
    return $wpdb->prefix . 'hotel_surcharges';
}

/**
 * ============================================================================
 * BOOKING QUERIES
 * ============================================================================
 */

/**
 * Lấy booking theo ID
 * 
 * @since   2.0.0
 * 
 * @param   int     $booking_id     Booking ID
 * 
 * @return  object|null     Booking object hoặc null
 */
function vie_get_booking_by_id(int $booking_id): ?object {
    global $wpdb;
    
    $table = vie_get_table_bookings();
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $booking_id
        )
    );
}

/**
 * Lấy booking theo hash
 * 
 * @since   2.0.0
 * 
 * @param   string  $hash   Booking hash
 * 
 * @return  object|null     Booking object hoặc null
 */
function vie_get_booking_by_hash(string $hash): ?object {
    global $wpdb;
    
    $table = vie_get_table_bookings();
    $hash = sanitize_text_field($hash);
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE booking_hash = %s",
            $hash
        )
    );
}

/**
 * Lấy booking theo mã đặt phòng
 * 
 * @since   2.0.0
 * 
 * @param   string  $code   Booking code
 * 
 * @return  object|null     Booking object hoặc null
 */
function vie_get_booking_by_code(string $code): ?object {
    global $wpdb;
    
    $table = vie_get_table_bookings();
    $code = sanitize_text_field($code);
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE booking_code = %s",
            $code
        )
    );
}

/**
 * ============================================================================
 * ROOM QUERIES
 * ============================================================================
 */

/**
 * Lấy room theo ID
 * 
 * @since   2.0.0
 * 
 * @param   int     $room_id    Room ID
 * 
 * @return  object|null     Room object hoặc null
 */
function vie_get_room_by_id(int $room_id): ?object {
    global $wpdb;
    
    $table = vie_get_table_rooms();
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $room_id
        )
    );
}

/**
 * Lấy tất cả rooms của 1 hotel
 * 
 * @since   2.0.0
 * 
 * @param   int     $hotel_id   Hotel post ID
 * @param   string  $status     Filter theo status ('active', 'inactive', 'all')
 * 
 * @return  array   Array of room objects
 */
function vie_get_rooms_by_hotel(int $hotel_id, string $status = 'active'): array {
    global $wpdb;
    
    $table = vie_get_table_rooms();
    
    $sql = "SELECT * FROM {$table} WHERE hotel_id = %d";
    $params = [$hotel_id];
    
    if ($status !== 'all') {
        $sql .= " AND status = %s";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY sort_order ASC, id ASC";
    
    return $wpdb->get_results(
        $wpdb->prepare($sql, $params)
    ) ?: [];
}

/**
 * ============================================================================
 * PRICING QUERIES
 * ============================================================================
 */

/**
 * Lấy giá của room theo ngày
 * 
 * @since   2.0.0
 * 
 * @param   int     $room_id    Room ID
 * @param   string  $date       Ngày (Y-m-d format)
 * 
 * @return  object|null     Pricing object hoặc null
 */
function vie_get_room_price_by_date(int $room_id, string $date): ?object {
    global $wpdb;
    
    $table = vie_get_table_pricing();
    
    return $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table} WHERE room_id = %d AND date = %s",
            $room_id,
            $date
        )
    );
}

/**
 * Lấy giá của room trong khoảng thời gian
 * 
 * @since   2.0.0
 * 
 * @param   int     $room_id    Room ID
 * @param   string  $date_from  Ngày bắt đầu (Y-m-d)
 * @param   string  $date_to    Ngày kết thúc (Y-m-d)
 * 
 * @return  array   Array of pricing objects, keyed by date
 */
function vie_get_room_prices_range(int $room_id, string $date_from, string $date_to): array {
    global $wpdb;
    
    $table = vie_get_table_pricing();
    
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE room_id = %d 
             AND date >= %s 
             AND date <= %s
             ORDER BY date ASC",
            $room_id,
            $date_from,
            $date_to
        )
    );
    
    // Index by date for easy lookup
    $prices = [];
    if ($results) {
        foreach ($results as $row) {
            $prices[$row->date] = $row;
        }
    }
    
    return $prices;
}

/**
 * ============================================================================
 * AVAILABILITY QUERIES
 * ============================================================================
 */

/**
 * Kiểm tra phòng có khả dụng trong khoảng thời gian không
 * 
 * @since   2.0.0
 * 
 * @param   int     $room_id        Room ID
 * @param   string  $check_in       Check-in date (Y-m-d)
 * @param   string  $check_out      Check-out date (Y-m-d)
 * @param   int     $num_rooms      Số phòng cần
 * 
 * @return  array   [
 *     'available' => bool,
 *     'unavailable_dates' => array,
 *     'stop_sell_dates' => array
 * ]
 */
function vie_check_room_availability(int $room_id, string $check_in, string $check_out, int $num_rooms = 1): array {
    $prices = vie_get_room_prices_range($room_id, $check_in, $check_out);
    $room = vie_get_room_by_id($room_id);
    
    $unavailable_dates = [];
    $stop_sell_dates = [];
    
    // Loop qua từng ngày (trừ ngày checkout)
    $current = strtotime($check_in);
    $end = strtotime($check_out);
    
    while ($current < $end) {
        $date = date('Y-m-d', $current);
        
        // Check giá có tồn tại không
        if (!isset($prices[$date])) {
            // Không có giá = dùng base price, vẫn available
            $current = strtotime('+1 day', $current);
            continue;
        }
        
        $price_data = $prices[$date];
        
        // Check stop sell
        if (!empty($price_data->stop_sell) && $price_data->stop_sell == 1) {
            $stop_sell_dates[] = $date;
        }
        
        // Check số phòng
        $available_rooms = (int) ($price_data->available_rooms ?? ($room->total_rooms ?? 99));
        if ($available_rooms < $num_rooms) {
            $unavailable_dates[] = $date;
        }
        
        $current = strtotime('+1 day', $current);
    }
    
    return [
        'available' => empty($unavailable_dates) && empty($stop_sell_dates),
        'unavailable_dates' => $unavailable_dates,
        'stop_sell_dates' => $stop_sell_dates,
    ];
}

/**
 * ============================================================================
 * SURCHARGE QUERIES
 * ============================================================================
 */

/**
 * Lấy phụ thu của hotel
 * 
 * @since   2.0.0
 * 
 * @param   int     $hotel_id   Hotel post ID
 * 
 * @return  array   Array of surcharge objects
 */
function vie_get_hotel_surcharges(int $hotel_id): array {
    global $wpdb;
    
    $table = vie_get_table_surcharges();
    
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE hotel_id = %d 
             AND status = 'active'
             ORDER BY sort_order ASC",
            $hotel_id
        )
    ) ?: [];
}
