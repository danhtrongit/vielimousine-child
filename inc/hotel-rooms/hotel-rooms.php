<?php
/**
 * Hotel Rooms Management Module
 * 
 * Module quản lý loại phòng khách sạn với custom database tables
 * Tối ưu hiệu năng cho dữ liệu lịch & giá
 * 
 * @package VieHotelRooms
 * @version 1.0.0
 * @author Vie Development Team
 */

if (!defined('ABSPATH')) {
    exit;
}

// Module constants
define('VIE_HOTEL_ROOMS_VERSION', '1.2.0');
define('VIE_HOTEL_ROOMS_PATH', dirname(__FILE__));
define('VIE_HOTEL_ROOMS_URL', get_stylesheet_directory_uri() . '/inc/hotel-rooms/');

/**
 * Main Hotel Rooms Module Class
 */
class Vie_Hotel_Rooms
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Database tables
     */
    public $table_rooms;
    public $table_pricing;
    public $table_surcharges;
    public $table_bookings;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        global $wpdb;

        // Define table names
        $this->table_rooms = $wpdb->prefix . 'hotel_rooms';
        $this->table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        $this->table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
        $this->table_bookings = $wpdb->prefix . 'hotel_bookings';

        // Load dependencies
        $this->load_dependencies();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies()
    {
        // Core classes
        require_once VIE_HOTEL_ROOMS_PATH . '/includes/class-database.php';
        require_once VIE_HOTEL_ROOMS_PATH . '/includes/class-helpers.php';

        // Admin classes
        require_once VIE_HOTEL_ROOMS_PATH . '/admin/class-admin.php';
        require_once VIE_HOTEL_ROOMS_PATH . '/admin/class-ajax-handlers.php';
        require_once VIE_HOTEL_ROOMS_PATH . '/admin/class-bookings.php';
        require_once VIE_HOTEL_ROOMS_PATH . '/admin/class-transport-metabox.php';

        // Frontend classes
        require_once VIE_HOTEL_ROOMS_PATH . '/frontend/class-shortcode.php';
        require_once VIE_HOTEL_ROOMS_PATH . '/frontend/class-ajax.php';

        // Initialize classes
        new Vie_Hotel_Rooms_Admin_Bookings();
        new Vie_Hotel_Rooms_Shortcode();
        new Vie_Hotel_Rooms_Frontend_Ajax();
        new Vie_Hotel_Transport_Metabox();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks()
    {
        // Activation hook for database tables
        add_action('after_switch_theme', array($this, 'activate'));

        // Admin init
        if (is_admin()) {
            add_action('admin_init', array($this, 'check_database_tables'));
        }
    }

    /**
     * Activation - Create database tables
     */
    public function activate()
    {
        Vie_Hotel_Rooms_Database::create_tables();
    }

    /**
     * Check and create database tables if needed
     */
    public function check_database_tables()
    {
        $db_version = get_option('vie_hotel_rooms_db_version', '0');

        if (version_compare($db_version, VIE_HOTEL_ROOMS_VERSION, '<')) {
            Vie_Hotel_Rooms_Database::create_tables();
            Vie_Hotel_Rooms_Database::check_and_migrate_hash(); // Security fix: Add booking_hash
            update_option('vie_hotel_rooms_db_version', VIE_HOTEL_ROOMS_VERSION);
        }
    }

    /**
     * Get table name helper
     */
    public function get_table($table)
    {
        switch ($table) {
            case 'rooms':
                return $this->table_rooms;
            case 'pricing':
                return $this->table_pricing;
            case 'surcharges':
                return $this->table_surcharges;
            case 'bookings':
                return $this->table_bookings;
            default:
                return false;
        }
    }
}

/**
 * Initialize module
 */
function vie_hotel_rooms()
{
    return Vie_Hotel_Rooms::get_instance();
}

// Boot the module
vie_hotel_rooms();
