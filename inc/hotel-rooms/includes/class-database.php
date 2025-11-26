<?php
/**
 * Database Handler for Hotel Rooms Module
 * 
 * Tạo và quản lý custom database tables
 * Tối ưu cho dữ liệu lịch & giá với proper indexing
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Database
{

    /**
     * Create all database tables
     */
    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Table A: wp_hotel_rooms (Thông tin tĩnh loại phòng)
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        $sql_rooms = "CREATE TABLE {$table_rooms} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            hotel_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK to wp_posts (hotel post type)',
            name VARCHAR(255) NOT NULL COMMENT 'Tên loại phòng - VD: Deluxe Ocean View',
            slug VARCHAR(255) DEFAULT '' COMMENT 'URL-friendly name',
            description LONGTEXT COMMENT 'Mô tả chi tiết phòng',
            short_description TEXT COMMENT 'Mô tả ngắn',
            gallery_ids LONGTEXT COMMENT 'JSON array of attachment IDs',
            featured_image_id BIGINT(20) UNSIGNED DEFAULT 0 COMMENT 'Ảnh đại diện',
            amenities LONGTEXT COMMENT 'JSON array tiện ích phòng',
            room_size VARCHAR(50) DEFAULT '' COMMENT 'Diện tích phòng (m2)',
            bed_type VARCHAR(100) DEFAULT '' COMMENT 'Loại giường',
            view_type VARCHAR(100) DEFAULT '' COMMENT 'Hướng nhìn (Ocean/City/Garden)',
            base_occupancy TINYINT(2) UNSIGNED DEFAULT 2 COMMENT 'Số người tiêu chuẩn',
            max_adults TINYINT(2) UNSIGNED DEFAULT 2 COMMENT 'Tối đa người lớn',
            max_children TINYINT(2) UNSIGNED DEFAULT 2 COMMENT 'Tối đa trẻ em',
            max_occupancy TINYINT(2) UNSIGNED DEFAULT 4 COMMENT 'Tổng sức chứa tối đa',
            total_rooms INT(11) UNSIGNED DEFAULT 1 COMMENT 'Tổng số phòng vật lý',
            base_price DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Giá cơ bản (fallback)',
            sort_order INT(11) DEFAULT 0 COMMENT 'Thứ tự hiển thị',
            status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_hotel_id (hotel_id),
            KEY idx_status (status),
            KEY idx_sort_order (sort_order)
        ) {$charset_collate};";

        dbDelta($sql_rooms);

        // Table B: wp_hotel_room_pricing (Lịch & Giá - Quan trọng nhất)
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        $sql_pricing = "CREATE TABLE {$table_pricing} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK to wp_hotel_rooms',
            date DATE NOT NULL COMMENT 'Ngày cụ thể YYYY-MM-DD',
            day_of_week TINYINT(1) UNSIGNED NOT NULL COMMENT '0=Sunday, 1=Monday...6=Saturday',
            price_room DECIMAL(15,2) DEFAULT NULL COMMENT 'Giá phòng đơn lẻ (Room Only)',
            price_combo DECIMAL(15,2) DEFAULT NULL COMMENT 'Giá theo gói Combo',
            price_weekday DECIMAL(15,2) DEFAULT NULL COMMENT 'Giá ngày thường (CN-T5)',
            price_weekend DECIMAL(15,2) DEFAULT NULL COMMENT 'Giá cuối tuần (T6-T7)',
            stock INT(11) UNSIGNED DEFAULT 0 COMMENT 'Số phòng trống còn lại',
            booked INT(11) UNSIGNED DEFAULT 0 COMMENT 'Số phòng đã đặt',
            status ENUM('available', 'limited', 'sold_out', 'stop_sell') DEFAULT 'available',
            min_stay INT(11) UNSIGNED DEFAULT 1 COMMENT 'Số đêm tối thiểu',
            max_stay INT(11) UNSIGNED DEFAULT 30 COMMENT 'Số đêm tối đa',
            notes TEXT COMMENT 'Ghi chú cho ngày đặc biệt',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_room_date (room_id, date),
            KEY idx_room_id (room_id),
            KEY idx_date (date),
            KEY idx_day_of_week (day_of_week),
            KEY idx_status (status),
            KEY idx_date_range (room_id, date, status)
        ) {$charset_collate};";

        dbDelta($sql_pricing);

        // Table C: wp_hotel_room_surcharges (Cấu hình phụ thu)
        $table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
        $sql_surcharges = "CREATE TABLE {$table_surcharges} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK to wp_hotel_rooms',
            surcharge_type ENUM('extra_bed', 'child', 'adult', 'breakfast', 'other') NOT NULL,
            label VARCHAR(255) DEFAULT '' COMMENT 'Nhãn hiển thị tùy chỉnh',
            min_age TINYINT(3) UNSIGNED DEFAULT NULL COMMENT 'Tuổi tối thiểu (cho child)',
            max_age TINYINT(3) UNSIGNED DEFAULT NULL COMMENT 'Tuổi tối đa (cho child)',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Số tiền phụ thu',
            amount_type ENUM('fixed', 'percent') DEFAULT 'fixed' COMMENT 'Loại tính phụ thu',
            is_per_night TINYINT(1) DEFAULT 1 COMMENT '1=Tính theo đêm, 0=Tính 1 lần',
            is_mandatory TINYINT(1) DEFAULT 0 COMMENT '1=Bắt buộc, 0=Tùy chọn',
            applies_to_combo TINYINT(1) DEFAULT 1 COMMENT 'Áp dụng cho giá Combo',
            applies_to_room TINYINT(1) DEFAULT 1 COMMENT 'Áp dụng cho giá Room Only',
            conditions LONGTEXT COMMENT 'JSON điều kiện áp dụng',
            notes TEXT COMMENT 'Ghi chú - VD: Bắt buộc kê giường phụ',
            sort_order INT(11) DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_room_id (room_id),
            KEY idx_surcharge_type (surcharge_type),
            KEY idx_age_range (min_age, max_age),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql_surcharges);

        // Table D: wp_hotel_bookings (Đơn đặt phòng)
        $table_bookings = $wpdb->prefix . 'hotel_bookings';
        $sql_bookings = "CREATE TABLE {$table_bookings} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_code VARCHAR(50) NOT NULL COMMENT 'Mã đơn hàng VD: BK-20251001-XYZ',
            booking_hash VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'Secure hash for URL - prevents IDOR',
            hotel_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK to wp_posts (hotel)',
            room_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'FK to wp_hotel_rooms',
            check_in DATE NOT NULL,
            check_out DATE NOT NULL,
            num_rooms TINYINT(3) UNSIGNED DEFAULT 1 COMMENT 'Số lượng phòng',
            num_adults TINYINT(3) UNSIGNED DEFAULT 2 COMMENT 'Số người lớn',
            num_children TINYINT(3) UNSIGNED DEFAULT 0 COMMENT 'Số trẻ em',
            price_type ENUM('room', 'combo') DEFAULT 'room' COMMENT 'Loại giá đã chọn',
            customer_name VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(20) NOT NULL,
            customer_email VARCHAR(255) DEFAULT '',
            customer_note TEXT COMMENT 'Ghi chú của khách',
            guests_info LONGTEXT COMMENT 'JSON: Chi tiết tuổi các bé, phân bổ phòng',
            pricing_details LONGTEXT COMMENT 'JSON: Snapshot giá từng ngày tại thời điểm đặt',
            surcharges_details LONGTEXT COMMENT 'JSON: Chi tiết phụ thu',
            transport_info LONGTEXT COMMENT 'JSON: Thông tin xe đưa đón {enabled, pickup_time, dropoff_time, note}',
            base_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tổng giá phòng gốc',
            surcharges_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Tổng phụ thu',
            discount_amount DECIMAL(15,2) DEFAULT 0.00 COMMENT 'Giảm giá (nếu có)',
            total_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tổng tiền thanh toán',
            payment_method VARCHAR(50) DEFAULT '' COMMENT 'Phương thức thanh toán',
            payment_status ENUM('unpaid', 'partial', 'paid', 'refunded') DEFAULT 'unpaid',
            status ENUM('pending', 'pending_payment', 'confirmed', 'cancelled', 'completed', 'no_show') DEFAULT 'pending',
            admin_note TEXT COMMENT 'Ghi chú của admin',
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
            KEY idx_check_out (check_out),
            KEY idx_status (status),
            KEY idx_payment_status (payment_status),
            KEY idx_customer_phone (customer_phone),
            KEY idx_created_at (created_at),
            KEY idx_hotel_dates (hotel_id, check_in, check_out)
        ) {$charset_collate}";

        dbDelta($sql_bookings);

        // Log table creation
        error_log('Vie Hotel Rooms: Database tables created/updated successfully.');
    }

    /**
     * Drop all tables (for uninstall)
     */
    public static function drop_tables()
    {
        global $wpdb;

        $tables = array(
            $wpdb->prefix . 'hotel_bookings',
            $wpdb->prefix . 'hotel_room_surcharges',
            $wpdb->prefix . 'hotel_room_pricing',
            $wpdb->prefix . 'hotel_rooms'
        );

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }

        delete_option('vie_hotel_rooms_db_version');
    }

    /**
     * Check if tables exist
     */
    public static function tables_exist()
    {
        global $wpdb;

        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        $result = $wpdb->get_var("SHOW TABLES LIKE '{$table_rooms}'");

        return ($result === $table_rooms);
    }

    /**
     * Get table status info
     */
    public static function get_table_stats()
    {
        global $wpdb;

        $stats = array();

        $tables = array(
            'rooms' => $wpdb->prefix . 'hotel_rooms',
            'pricing' => $wpdb->prefix . 'hotel_room_pricing',
            'surcharges' => $wpdb->prefix . 'hotel_room_surcharges',
            'bookings' => $wpdb->prefix . 'hotel_bookings'
        );

        foreach ($tables as $key => $table) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $stats[$key] = array(
                'table' => $table,
                'count' => (int) $count
            );
        }

        return $stats;
    }

    /**
     * Migrate existing bookings to add booking_hash
     * Security fix: Prevents IDOR vulnerability
     */
    public static function check_and_migrate_hash()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';

        // Check if column exists
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'booking_hash'");

        if (empty($column_exists)) {
            // Column doesn't exist, create it
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN booking_hash VARCHAR(64) NOT NULL DEFAULT '' AFTER booking_code");
            $wpdb->query("ALTER TABLE {$table} ADD UNIQUE INDEX idx_booking_hash (booking_hash)");
        }

        // Find bookings without hash
        $bookings_without_hash = $wpdb->get_results("SELECT id FROM {$table} WHERE booking_hash = '' OR booking_hash IS NULL");

        if (!empty($bookings_without_hash)) {
            foreach ($bookings_without_hash as $booking) {
                $hash = wp_generate_password(32, false);
                $wpdb->update(
                    $table,
                    array('booking_hash' => $hash),
                    array('id' => $booking->id),
                    array('%s'),
                    array('%d')
                );
            }

            error_log('Vie Hotel Rooms: Migrated ' . count($bookings_without_hash) . ' bookings with booking_hash.');
        }

        return count($bookings_without_hash);
    }
}
