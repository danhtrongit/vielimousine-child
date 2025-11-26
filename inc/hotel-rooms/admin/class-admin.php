<?php
/**
 * Admin Handler for Hotel Rooms Module
 * 
 * Quản lý giao diện Admin Dashboard
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Admin {
    
    /**
     * Instance
     */
    private static $instance = null;
    
    /**
     * Get instance
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add metabox to hotel post type
        add_action('add_meta_boxes', array($this, 'add_hotel_metabox'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Main menu
        add_menu_page(
            __('Quản lý Phòng', 'viechild'),
            __('Quản lý Phòng', 'viechild'),
            'manage_options',
            'vie-hotel-rooms',
            array($this, 'render_rooms_page'),
            'dashicons-building',
            30
        );
        
        // Submenu - All Rooms
        add_submenu_page(
            'vie-hotel-rooms',
            __('Tất cả Loại Phòng', 'viechild'),
            __('Tất cả Phòng', 'viechild'),
            'manage_options',
            'vie-hotel-rooms',
            array($this, 'render_rooms_page')
        );
        
        // Submenu - Add New
        add_submenu_page(
            'vie-hotel-rooms',
            __('Thêm Loại Phòng', 'viechild'),
            __('Thêm mới', 'viechild'),
            'manage_options',
            'vie-hotel-rooms-add',
            array($this, 'render_add_room_page')
        );
        
        // Submenu - Calendar/Pricing
        add_submenu_page(
            'vie-hotel-rooms',
            __('Lịch & Giá Phòng', 'viechild'),
            __('Lịch & Giá', 'viechild'),
            'manage_options',
            'vie-hotel-rooms-calendar',
            array($this, 'render_calendar_page')
        );
        
        // Submenu - Bulk Update
        add_submenu_page(
            'vie-hotel-rooms',
            __('Cập nhật Hàng loạt', 'viechild'),
            __('Bulk Update', 'viechild'),
            'manage_options',
            'vie-hotel-rooms-bulk',
            array($this, 'render_bulk_update_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our pages
        if (strpos($hook, 'vie-hotel-rooms') === false && get_post_type() !== 'hotel') {
            return;
        }
        
        // WordPress media uploader
        wp_enqueue_media();
        
        // jQuery UI for datepicker
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css');
        
        // FullCalendar
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css');
        wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js', array(), '6.1.8', true);
        
        // Custom admin styles
        wp_enqueue_style(
            'vie-hotel-rooms-admin',
            VIE_HOTEL_ROOMS_URL . '/assets/css/admin.css',
            array(),
            VIE_HOTEL_ROOMS_VERSION
        );
        
        // Custom admin scripts
        wp_enqueue_script(
            'vie-hotel-rooms-admin',
            VIE_HOTEL_ROOMS_URL . '/assets/js/admin.js',
            array('jquery', 'jquery-ui-datepicker'),
            VIE_HOTEL_ROOMS_VERSION,
            true
        );
        
        // Calendar specific script
        if (strpos($hook, 'calendar') !== false || strpos($hook, 'bulk') !== false) {
            wp_enqueue_script(
                'vie-hotel-rooms-calendar',
                VIE_HOTEL_ROOMS_URL . '/assets/js/calendar.js',
                array('jquery', 'fullcalendar-js'),
                VIE_HOTEL_ROOMS_VERSION,
                true
            );
        }
        
        // Localize script
        wp_localize_script('vie-hotel-rooms-admin', 'vieHotelRooms', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vie_hotel_rooms_nonce'),
            'i18n' => array(
                'confirmDelete' => __('Bạn có chắc muốn xóa?', 'viechild'),
                'saving' => __('Đang lưu...', 'viechild'),
                'saved' => __('Đã lưu!', 'viechild'),
                'error' => __('Có lỗi xảy ra!', 'viechild'),
                'selectDates' => __('Vui lòng chọn khoảng ngày', 'viechild'),
                'invalidPrice' => __('Giá không hợp lệ', 'viechild')
            )
        ));
    }
    
    /**
     * Add metabox to hotel post type
     */
    public function add_hotel_metabox() {
        add_meta_box(
            'vie_hotel_rooms_metabox',
            __('Danh sách Loại Phòng', 'viechild'),
            array($this, 'render_hotel_metabox'),
            'hotel',
            'normal',
            'high'
        );
    }
    
    /**
     * Render hotel metabox
     */
    public function render_hotel_metabox($post) {
        $rooms = Vie_Hotel_Rooms_Helpers::get_rooms_by_hotel($post->ID, 'all');
        ?>
        <div class="vie-hotel-rooms-metabox">
            <?php if (!empty($rooms)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Tên phòng', 'viechild'); ?></th>
                            <th width="100"><?php _e('Sức chứa', 'viechild'); ?></th>
                            <th width="120"><?php _e('Giá cơ bản', 'viechild'); ?></th>
                            <th width="100"><?php _e('Trạng thái', 'viechild'); ?></th>
                            <th width="150"><?php _e('Thao tác', 'viechild'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($room->name); ?></strong>
                                </td>
                                <td>
                                    <?php echo esc_html($room->base_occupancy); ?> người
                                </td>
                                <td>
                                    <?php echo Vie_Hotel_Rooms_Helpers::format_currency($room->base_price); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($room->status); ?>">
                                        <?php echo esc_html(ucfirst($room->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-add&room_id=' . $room->id); ?>" 
                                       class="button button-small">
                                        <?php _e('Sửa', 'viechild'); ?>
                                    </a>
                                    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-calendar&room_id=' . $room->id); ?>" 
                                       class="button button-small">
                                        <?php _e('Lịch giá', 'viechild'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('Chưa có loại phòng nào.', 'viechild'); ?></p>
            <?php endif; ?>
            
            <p style="margin-top: 15px;">
                <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-add&hotel_id=' . $post->ID); ?>" 
                   class="button button-primary">
                    <?php _e('+ Thêm Loại Phòng', 'viechild'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Render rooms list page
     */
    public function render_rooms_page() {
        $hotels = Vie_Hotel_Rooms_Helpers::get_hotels();
        $selected_hotel = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;
        $rooms = Vie_Hotel_Rooms_Helpers::get_rooms_by_hotel($selected_hotel, 'all');
        
        include VIE_HOTEL_ROOMS_PATH . '/admin/views/rooms-list.php';
    }
    
    /**
     * Render add/edit room page
     */
    public function render_add_room_page() {
        $room_id = isset($_GET['room_id']) ? absint($_GET['room_id']) : 0;
        $hotel_id = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;
        
        $room = null;
        $surcharges = array();
        
        if ($room_id > 0) {
            $room = Vie_Hotel_Rooms_Helpers::get_room($room_id);
            $surcharges = Vie_Hotel_Rooms_Helpers::get_room_surcharges($room_id);
            
            if ($room) {
                $hotel_id = $room->hotel_id;
            }
        }
        
        $hotels = Vie_Hotel_Rooms_Helpers::get_hotels();
        
        include VIE_HOTEL_ROOMS_PATH . '/admin/views/room-form.php';
    }
    
    /**
     * Render calendar page
     */
    public function render_calendar_page() {
        $room_id = isset($_GET['room_id']) ? absint($_GET['room_id']) : 0;
        $hotel_id = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;
        
        $hotels = Vie_Hotel_Rooms_Helpers::get_hotels();
        $rooms = array();
        
        if ($hotel_id > 0) {
            $rooms = Vie_Hotel_Rooms_Helpers::get_rooms_by_hotel($hotel_id);
        }
        
        $room = null;
        if ($room_id > 0) {
            $room = Vie_Hotel_Rooms_Helpers::get_room($room_id);
            if ($room) {
                $hotel_id = $room->hotel_id;
                $rooms = Vie_Hotel_Rooms_Helpers::get_rooms_by_hotel($hotel_id);
            }
        }
        
        include VIE_HOTEL_ROOMS_PATH . '/admin/views/calendar.php';
    }
    
    /**
     * Render bulk update page
     */
    public function render_bulk_update_page() {
        $hotels = Vie_Hotel_Rooms_Helpers::get_hotels();
        
        include VIE_HOTEL_ROOMS_PATH . '/admin/views/bulk-update.php';
    }
}

// Initialize
Vie_Hotel_Rooms_Admin::get_instance();
