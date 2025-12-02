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
    return $wpdb->prefix . 'hotel_rooms';
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
    return $wpdb->prefix . 'hotel_room_surcharges';
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
 * Lấy phụ thu của room
 * 
 * @since   2.0.0
 * 
 * @param   int     $room_id   Room ID
 * 
 * @return  array   Array of surcharge objects
 */
function vie_get_room_surcharges(int $room_id): array {
    global $wpdb;
    
    $table = vie_get_table_surcharges();
    
    return $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$table} 
             WHERE room_id = %d 
             AND status = 'active'
             ORDER BY sort_order ASC",
            $room_id
        )
    ) ?: [];
}

/**
 * ============================================================================
 * DATABASE SCHEMA SETUP
 * ============================================================================
 */

/**
 * Tạo tất cả database tables cần thiết
 * 
 * @since   2.0.0
 */
function vie_create_database_tables(): void {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Table: wp_hotel_rooms
    $table_rooms = $wpdb->prefix . 'hotel_rooms';
    $sql_rooms = "CREATE TABLE {$table_rooms} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        hotel_id BIGINT(20) UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) DEFAULT '',
        description LONGTEXT,
        short_description TEXT,
        gallery_ids LONGTEXT,
        featured_image_id BIGINT(20) UNSIGNED DEFAULT 0,
        amenities LONGTEXT,
        room_size VARCHAR(50) DEFAULT '',
        bed_type VARCHAR(100) DEFAULT '',
        view_type VARCHAR(100) DEFAULT '',
        base_occupancy TINYINT(2) UNSIGNED DEFAULT 2,
        max_adults TINYINT(2) UNSIGNED DEFAULT 2,
        max_children TINYINT(2) UNSIGNED DEFAULT 2,
        max_occupancy TINYINT(2) UNSIGNED DEFAULT 4,
        total_rooms INT(11) UNSIGNED DEFAULT 1,
        price_includes TEXT,
        cancellation_policy LONGTEXT,
        sort_order INT(11) DEFAULT 0,
        status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_hotel_id (hotel_id),
        KEY idx_status (status)
    ) {$charset_collate};";
    dbDelta($sql_rooms);

    // Table: wp_hotel_room_pricing
    $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
    $sql_pricing = "CREATE TABLE {$table_pricing} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id BIGINT(20) UNSIGNED NOT NULL,
        date DATE NOT NULL,
        day_of_week TINYINT(1) UNSIGNED NOT NULL,
        price_room DECIMAL(15,2) DEFAULT NULL,
        price_combo DECIMAL(15,2) DEFAULT NULL,
        stock INT(11) UNSIGNED DEFAULT 0,
        booked INT(11) UNSIGNED DEFAULT 0,
        status ENUM('available', 'limited', 'sold_out', 'stop_sell') DEFAULT 'available',
        min_stay INT(11) UNSIGNED DEFAULT 1,
        notes TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_room_date (room_id, date),
        KEY idx_date (date),
        KEY idx_status (status)
    ) {$charset_collate};";
    dbDelta($sql_pricing);

    // Table: wp_hotel_room_surcharges
    $table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
    $sql_surcharges = "CREATE TABLE {$table_surcharges} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id BIGINT(20) UNSIGNED NOT NULL,
        surcharge_type ENUM('extra_bed', 'child', 'adult', 'breakfast', 'other') NOT NULL,
        label VARCHAR(255) DEFAULT '',
        min_age TINYINT(3) UNSIGNED DEFAULT NULL,
        max_age TINYINT(3) UNSIGNED DEFAULT NULL,
        amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        is_per_night TINYINT(1) DEFAULT 1,
        applies_to_combo TINYINT(1) DEFAULT 1,
        applies_to_room TINYINT(1) DEFAULT 1,
        notes TEXT,
        sort_order INT(11) DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_room_id (room_id),
        KEY idx_status (status)
    ) {$charset_collate};";
    dbDelta($sql_surcharges);

    // Table: wp_hotel_bookings
    $table_bookings = $wpdb->prefix . 'hotel_bookings';
    $sql_bookings = "CREATE TABLE {$table_bookings} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        booking_code VARCHAR(50) NOT NULL,
        room_code VARCHAR(50) DEFAULT NULL,
        booking_hash VARCHAR(64) NOT NULL DEFAULT '',
        hotel_id BIGINT(20) UNSIGNED NOT NULL,
        room_id BIGINT(20) UNSIGNED NOT NULL,
        room_name VARCHAR(255) DEFAULT '',
        check_in DATE NOT NULL,
        check_out DATE NOT NULL,
        num_rooms TINYINT(3) UNSIGNED DEFAULT 1,
        num_adults TINYINT(3) UNSIGNED DEFAULT 2,
        num_children TINYINT(3) UNSIGNED DEFAULT 0,
        price_type ENUM('room', 'combo') DEFAULT 'room',
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20) NOT NULL,
        customer_email VARCHAR(255) DEFAULT '',
        customer_note TEXT,
        invoice_info LONGTEXT,
        guests_info LONGTEXT,
        pricing_details LONGTEXT,
        surcharges_details LONGTEXT,
        transport_info LONGTEXT,
        base_amount DECIMAL(15,2) DEFAULT 0.00,
        surcharges_amount DECIMAL(15,2) DEFAULT 0.00,
        discount_amount DECIMAL(15,2) DEFAULT 0.00,
        coupon_code VARCHAR(50) DEFAULT '',
        total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        payment_method VARCHAR(50) DEFAULT '',
        payment_status ENUM('unpaid', 'partial', 'paid', 'refunded') DEFAULT 'unpaid',
        status ENUM('pending', 'pending_payment', 'processing', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'pending',
        admin_note TEXT,
        ip_address VARCHAR(45) DEFAULT '',
        user_agent TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY idx_booking_code (booking_code),
        UNIQUE KEY idx_booking_hash (booking_hash),
        KEY idx_hotel_id (hotel_id),
        KEY idx_room_id (room_id),
        KEY idx_check_in (check_in),
        KEY idx_status (status),
        KEY idx_payment_status (payment_status),
        KEY idx_created_at (created_at)
    ) {$charset_collate}";
    dbDelta($sql_bookings);

    // Update DB version
    update_option('vie_hotel_rooms_db_version', '2.0.0');
}

/**
 * Kiểm tra tables đã tồn tại chưa
 * 
 * @since   2.0.0
 * @return  bool
 */
function vie_database_tables_exist(): bool {
    global $wpdb;
    $table = $wpdb->prefix . 'hotel_rooms';
    return $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
}
