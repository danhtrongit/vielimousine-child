<?php
/**
 * ============================================================================
 * CLASS: Vie_Database_Installer
 * ============================================================================
 * 
 * Quản lý tạo và migrate database tables cho Hotel Booking System V2.
 * 
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

class Vie_Database_Installer {

    /** @var Vie_Database_Installer|null */
    private static $instance = null;

    /** @var string Database version */
    const DB_VERSION = '2.0.0';

    /** @var string Option key for DB version */
    const VERSION_OPTION = 'vie_hotel_db_version';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Hook for admin check
        add_action('admin_init', array($this, 'check_and_install'));
    }

    /**
     * Check and install/upgrade database
     */
    public function check_and_install() {
        $current_version = get_option(self::VERSION_OPTION, '0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            $this->install();
            update_option(self::VERSION_OPTION, self::DB_VERSION);
            
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[VIE DB] Database installed/upgraded to version ' . self::DB_VERSION);
            }
        }
    }

    /**
     * Install all database tables
     */
    public function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create all tables
        $this->create_table_rooms($charset_collate);
        $this->create_table_pricing($charset_collate);
        $this->create_table_surcharges($charset_collate);
        $this->create_table_bookings($charset_collate);
        $this->create_table_payment_logs($charset_collate);

        // Run migrations
        $this->run_migrations();
    }

    /**
     * Table: wp_hotel_rooms
     */
    private function create_table_rooms($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_rooms';

        $sql = "CREATE TABLE {$table} (
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
            KEY idx_status (status),
            KEY idx_sort_order (sort_order)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Table: wp_hotel_room_pricing
     */
    private function create_table_pricing($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_room_pricing';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT(20) UNSIGNED NOT NULL,
            date DATE NOT NULL,
            day_of_week TINYINT(1) UNSIGNED NOT NULL,
            price_room DECIMAL(15,2) DEFAULT NULL,
            price_combo DECIMAL(15,2) DEFAULT NULL,
            price_weekday DECIMAL(15,2) DEFAULT NULL,
            price_weekend DECIMAL(15,2) DEFAULT NULL,
            stock INT(11) UNSIGNED DEFAULT 0,
            booked INT(11) UNSIGNED DEFAULT 0,
            status ENUM('available', 'limited', 'sold_out', 'stop_sell') DEFAULT 'available',
            min_stay INT(11) UNSIGNED DEFAULT 1,
            max_stay INT(11) UNSIGNED DEFAULT 30,
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY idx_room_date (room_id, date),
            KEY idx_room_id (room_id),
            KEY idx_date (date),
            KEY idx_day_of_week (day_of_week),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Table: wp_hotel_room_surcharges
     */
    private function create_table_surcharges($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_room_surcharges';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            room_id BIGINT(20) UNSIGNED NOT NULL,
            surcharge_type ENUM('extra_bed', 'child', 'adult', 'breakfast', 'other') NOT NULL,
            label VARCHAR(255) DEFAULT '',
            min_age TINYINT(3) UNSIGNED DEFAULT NULL,
            max_age TINYINT(3) UNSIGNED DEFAULT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            amount_type ENUM('fixed', 'percent') DEFAULT 'fixed',
            is_per_night TINYINT(1) DEFAULT 1,
            is_mandatory TINYINT(1) DEFAULT 0,
            applies_to_combo TINYINT(1) DEFAULT 1,
            applies_to_room TINYINT(1) DEFAULT 1,
            conditions LONGTEXT,
            notes TEXT,
            sort_order INT(11) DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_room_id (room_id),
            KEY idx_surcharge_type (surcharge_type),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Table: wp_hotel_bookings
     */
    private function create_table_bookings($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';

        $sql = "CREATE TABLE {$table} (
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
            bed_type VARCHAR(50) DEFAULT 'double',
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
            KEY idx_check_out (check_out),
            KEY idx_status (status),
            KEY idx_payment_status (payment_status),
            KEY idx_customer_phone (customer_phone),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Table: wp_hotel_payment_logs
     */
    private function create_table_payment_logs($charset_collate) {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_payment_logs';

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_id BIGINT(20) UNSIGNED NOT NULL,
            transaction_id VARCHAR(100) DEFAULT '',
            payment_method VARCHAR(50) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending',
            gateway_response LONGTEXT,
            note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_booking_id (booking_id),
            KEY idx_transaction_id (transaction_id),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Run database migrations
     */
    private function run_migrations() {
        global $wpdb;

        // Migration: Add missing columns to existing tables
        $this->add_column_if_not_exists('hotel_room_pricing', 'price_weekday', 'DECIMAL(15,2) DEFAULT NULL AFTER price_combo');
        $this->add_column_if_not_exists('hotel_room_pricing', 'price_weekend', 'DECIMAL(15,2) DEFAULT NULL AFTER price_weekday');
        $this->add_column_if_not_exists('hotel_room_pricing', 'max_stay', 'INT(11) UNSIGNED DEFAULT 30 AFTER min_stay');
        
        $this->add_column_if_not_exists('hotel_room_surcharges', 'amount_type', "ENUM('fixed', 'percent') DEFAULT 'fixed' AFTER amount");
        $this->add_column_if_not_exists('hotel_room_surcharges', 'is_mandatory', 'TINYINT(1) DEFAULT 0 AFTER is_per_night');
        $this->add_column_if_not_exists('hotel_room_surcharges', 'conditions', 'LONGTEXT AFTER applies_to_room');
        
        $this->add_column_if_not_exists('hotel_bookings', 'room_name', "VARCHAR(255) DEFAULT '' AFTER room_id");
        $this->add_column_if_not_exists('hotel_bookings', 'bed_type', "VARCHAR(50) DEFAULT 'double' AFTER num_children");
    }

    /**
     * Add column if not exists
     */
    private function add_column_if_not_exists($table_name, $column_name, $column_definition) {
        global $wpdb;
        $table = $wpdb->prefix . $table_name;

        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s",
            DB_NAME,
            $table,
            $column_name
        ));

        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column_name} {$column_definition}");
            
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log("[VIE DB] Added column {$column_name} to {$table}");
            }
        }
    }

    /**
     * Get status of all tables
     */
    public function get_tables_status() {
        global $wpdb;

        $tables = array(
            'hotel_rooms' => array('name' => 'Rooms', 'required' => true),
            'hotel_room_pricing' => array('name' => 'Pricing', 'required' => true),
            'hotel_room_surcharges' => array('name' => 'Surcharges', 'required' => true),
            'hotel_bookings' => array('name' => 'Bookings', 'required' => true),
            'hotel_payment_logs' => array('name' => 'Payment Logs', 'required' => false),
        );

        $status = array();

        foreach ($tables as $table_key => $table_info) {
            $table_name = $wpdb->prefix . $table_key;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
            $row_count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") : 0;

            $status[$table_key] = array(
                'name' => $table_info['name'],
                'table' => $table_name,
                'exists' => $exists,
                'required' => $table_info['required'],
                'row_count' => $row_count,
                'status' => $exists ? 'ok' : ($table_info['required'] ? 'missing' : 'optional')
            );
        }

        return $status;
    }

    /**
     * Check if all required tables exist
     */
    public function is_installed() {
        $status = $this->get_tables_status();
        
        foreach ($status as $table) {
            if ($table['required'] && !$table['exists']) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Force reinstall all tables
     */
    public function force_reinstall() {
        delete_option(self::VERSION_OPTION);
        $this->install();
        update_option(self::VERSION_OPTION, self::DB_VERSION);
        
        return $this->get_tables_status();
    }

    /**
     * Get current DB version
     */
    public function get_version() {
        return get_option(self::VERSION_OPTION, '0');
    }
}

// Initialize
Vie_Database_Installer::get_instance();
