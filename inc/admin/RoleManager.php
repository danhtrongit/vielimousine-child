<?php
/**
 * ============================================================================
 * TÊN FILE: RoleManager.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Quản lý phân quyền người dùng trong hệ thống.
 * Tạo và quản lý role "Quản lý Khách sạn" với quyền hạn giới hạn.
 * 
 * CHỨC NĂNG:
 * - Tạo role "Hotel Manager" (hotel_manager)
 * - Cấp quyền quản lý Hotel post type
 * - Cấp quyền truy cập Vie Hotel menu
 * - Ẩn các menu không liên quan đối với Hotel Manager
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin
 * @version     1.0.0
 * @since       1.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

class Vie_Role_Manager {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Role slug
     */
    const HOTEL_MANAGER_ROLE = 'hotel_manager';

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
        // Tạo role khi activate theme
        add_action('after_switch_theme', array($this, 'create_hotel_manager_role'));
        
        // Ẩn menu không liên quan
        add_action('admin_menu', array($this, 'restrict_admin_menu_for_hotel_manager'), 999);
        
        // Ẩn admin bar items không liên quan
        add_action('admin_bar_menu', array($this, 'restrict_admin_bar_for_hotel_manager'), 999);
        
        // Giới hạn quyền truy cập dashboard widgets
        add_action('wp_dashboard_setup', array($this, 'restrict_dashboard_widgets_for_hotel_manager'), 999);
        
        // Redirect sau khi login
        add_filter('login_redirect', array($this, 'hotel_manager_login_redirect'), 10, 3);
    }

    /**
     * Tạo role "Quản lý Khách sạn"
     * 
     * @since 1.0.0
     */
    public function create_hotel_manager_role() {
        // Xóa role cũ nếu tồn tại (để cập nhật capabilities)
        remove_role(self::HOTEL_MANAGER_ROLE);

        // Tạo role mới với đầy đủ capabilities
        $capabilities = $this->get_hotel_manager_capabilities();
        
        add_role(
            self::HOTEL_MANAGER_ROLE,
            __('Quản lý Khách sạn', 'vielimousine'),
            $capabilities
        );

        // Cấp quyền cho Administrator role
        $this->grant_admin_vie_hotel_capabilities();

        // Log khi tạo role thành công
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Vie Role Manager] Created Hotel Manager role with ' . count($capabilities) . ' capabilities');
        }
    }

    /**
     * Cấp quyền Vie Hotel cho Administrator role
     * 
     * @since 1.0.0
     */
    private function grant_admin_vie_hotel_capabilities() {
        $admin_role = get_role('administrator');
        
        if (!$admin_role) {
            return;
        }

        // Danh sách capabilities cho Vie Hotel
        $vie_hotel_caps = array(
            'manage_vie_hotel',
            'view_vie_hotel_bookings',
            'edit_vie_hotel_rooms',
            'edit_vie_hotel_calendar',
            'view_vie_hotel_settings',
        );

        // Thêm từng capability vào Administrator role
        foreach ($vie_hotel_caps as $cap) {
            $admin_role->add_cap($cap);
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Vie Role Manager] Granted Vie Hotel capabilities to Administrator role');
        }
    }

    /**
     * Lấy danh sách capabilities cho Hotel Manager
     * 
     * @since 1.0.0
     * @return array
     */
    private function get_hotel_manager_capabilities() {
        return array(
            // WordPress Core Capabilities (cơ bản)
            'read' => true,
            
            // Dashboard Access
            'edit_dashboard' => true,
            
            // Hotel Post Type Capabilities (quản lý khách sạn)
            'edit_hotels' => true,
            'edit_others_hotels' => true,
            'publish_hotels' => true,
            'read_private_hotels' => true,
            'delete_hotels' => true,
            'delete_private_hotels' => true,
            'delete_published_hotels' => true,
            'delete_others_hotels' => true,
            'edit_private_hotels' => true,
            'edit_published_hotels' => true,
            
            // Taxonomy Capabilities (quản lý danh mục khách sạn)
            'manage_categories' => true,
            'manage_hotel_categories' => true,
            'edit_hotel_categories' => true,
            'delete_hotel_categories' => true,
            'assign_hotel_categories' => true,
            
            // Taxonomy hotel-location (quản lý địa điểm)
            'manage_hotel_location' => true,
            'edit_hotel_location' => true,
            'delete_hotel_location' => true,
            'assign_hotel_location' => true,
            
            // Taxonomy hotel-category (quản lý danh mục)
            'manage_hotel_category' => true,
            'edit_hotel_category' => true,
            'delete_hotel_category' => true,
            'assign_hotel_category' => true,
            
            // Taxonomy hotel-rank (quản lý hạng sao)
            'manage_hotel_rank' => true,
            'edit_hotel_rank' => true,
            'delete_hotel_rank' => true,
            'assign_hotel_rank' => true,
            
            // Taxonomy hotel-convenient (quản lý tiện ích)
            'manage_hotel_convenient' => true,
            'edit_hotel_convenient' => true,
            'delete_hotel_convenient' => true,
            'assign_hotel_convenient' => true,
            
            // Media Library (upload ảnh khách sạn)
            'upload_files' => true,
            'edit_files' => true,
            
            // Vie Hotel Menu Capabilities (truy cập menu Vie Hotel)
            'manage_vie_hotel' => true,
            'view_vie_hotel_bookings' => true,
            'edit_vie_hotel_rooms' => true,
            'edit_vie_hotel_calendar' => true,
            'view_vie_hotel_settings' => true,
            
            // Comments (quản lý đánh giá khách sạn)
            'moderate_comments' => true,
            'edit_comment' => true,
        );
    }

    /**
     * Giới hạn menu admin cho Hotel Manager
     * Chỉ hiển thị: Dashboard, Khách sạn, Vie Hotel, Media, Profile
     * 
     * @since 1.0.0
     */
    public function restrict_admin_menu_for_hotel_manager() {
        // Chỉ áp dụng cho Hotel Manager
        if (!current_user_can(self::HOTEL_MANAGER_ROLE)) {
            return;
        }

        global $menu, $submenu;

        // Danh sách menu được phép hiển thị (menu slugs)
        $allowed_menus = array(
            'index.php',                    // Dashboard
            'edit.php?post_type=hotel',     // Khách sạn
            'vie-hotel-rooms',              // Vie Hotel
            'upload.php',                   // Media
            'profile.php',                  // Profile
        );

        // Xóa các menu không được phép
        foreach ($menu as $key => $item) {
            $menu_slug = $item[2] ?? '';
            
            if (!in_array($menu_slug, $allowed_menus)) {
                remove_menu_page($menu_slug);
            }
        }

        // Giới hạn submenu của Dashboard (chỉ giữ Home và Updates)
        if (isset($submenu['index.php'])) {
            foreach ($submenu['index.php'] as $key => $item) {
                $submenu_slug = $item[2] ?? '';
                if (!in_array($submenu_slug, array('index.php', 'update-core.php'))) {
                    unset($submenu['index.php'][$key]);
                }
            }
        }

        // QUAN TRỌNG: KHÔNG xóa submenu của Hotel post type
        // Các submenu taxonomy (Địa điểm, Danh mục, Hạng sao, Tiện ích) 
        // sẽ tự động hiển thị dựa trên capabilities
        
        // Log menu restrictions
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Vie Role Manager] Restricted admin menu for Hotel Manager (kept Hotel submenus)');
        }
    }

    /**
     * Giới hạn admin bar cho Hotel Manager
     * 
     * @since 1.0.0
     * @param WP_Admin_Bar $wp_admin_bar
     */
    public function restrict_admin_bar_for_hotel_manager($wp_admin_bar) {
        // Chỉ áp dụng cho Hotel Manager
        if (!current_user_can(self::HOTEL_MANAGER_ROLE)) {
            return;
        }

        // Xóa các items không cần thiết
        $wp_admin_bar->remove_node('wp-logo');          // WordPress logo
        $wp_admin_bar->remove_node('new-content');      // + New (trừ New Hotel)
        $wp_admin_bar->remove_node('comments');         // Comments
        $wp_admin_bar->remove_node('appearance');       // Appearance
        $wp_admin_bar->remove_node('themes');           // Themes
        $wp_admin_bar->remove_node('widgets');          // Widgets
        $wp_admin_bar->remove_node('menus');            // Menus
        $wp_admin_bar->remove_node('customize');        // Customize

        // Thêm quick link "Thêm Khách sạn mới"
        $wp_admin_bar->add_node(array(
            'id'    => 'new-hotel',
            'title' => '<span class="ab-icon dashicons-before dashicons-admin-multisite"></span> ' . __('Thêm Khách sạn', 'vielimousine'),
            'href'  => admin_url('post-new.php?post_type=hotel'),
            'parent' => 'top-secondary',
        ));
    }

    /**
     * Giới hạn dashboard widgets cho Hotel Manager
     * 
     * @since 1.0.0
     */
    public function restrict_dashboard_widgets_for_hotel_manager() {
        // Chỉ áp dụng cho Hotel Manager
        if (!current_user_can(self::HOTEL_MANAGER_ROLE)) {
            return;
        }

        global $wp_meta_boxes;

        // Xóa tất cả widgets mặc định
        unset($wp_meta_boxes['dashboard']['normal']);
        unset($wp_meta_boxes['dashboard']['side']);
        unset($wp_meta_boxes['dashboard']['column3']);
        unset($wp_meta_boxes['dashboard']['column4']);

        // Thêm widget tùy chỉnh cho Hotel Manager
        wp_add_dashboard_widget(
            'vie_hotel_manager_welcome',
            __('Chào mừng đến với Quản lý Khách sạn', 'vielimousine'),
            array($this, 'render_hotel_manager_welcome_widget')
        );

        // Widget thống kê nhanh
        wp_add_dashboard_widget(
            'vie_hotel_manager_stats',
            __('Thống kê Khách sạn', 'vielimousine'),
            array($this, 'render_hotel_stats_widget')
        );
    }

    /**
     * Render widget chào mừng cho Hotel Manager
     * 
     * @since 1.0.0
     */
    public function render_hotel_manager_welcome_widget() {
        $current_user = wp_get_current_user();
        ?>
        <div class="vie-hotel-manager-welcome">
            <h3><?php echo esc_html(sprintf(__('Xin chào, %s!', 'vielimousine'), $current_user->display_name)); ?></h3>
            <p><?php _e('Bạn đang sử dụng tài khoản Quản lý Khách sạn. Bạn có thể:', 'vielimousine'); ?></p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><?php _e('Quản lý thông tin các khách sạn', 'vielimousine'); ?></li>
                <li><?php _e('Quản lý phòng và giá phòng trong Vie Hotel', 'vielimousine'); ?></li>
                <li><?php _e('Xem và xử lý đặt phòng', 'vielimousine'); ?></li>
                <li><?php _e('Upload và quản lý ảnh khách sạn', 'vielimousine'); ?></li>
            </ul>
            <p>
                <a href="<?php echo admin_url('edit.php?post_type=hotel'); ?>" class="button button-primary">
                    <?php _e('Quản lý Khách sạn', 'vielimousine'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=vie-hotel-bookings'); ?>" class="button">
                    <?php _e('Xem Đặt phòng', 'vielimousine'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Render widget thống kê cho Hotel Manager
     * 
     * @since 1.0.0
     */
    public function render_hotel_stats_widget() {
        // Đếm số lượng hotels
        $hotel_counts = wp_count_posts('hotel');
        $total_hotels = $hotel_counts->publish ?? 0;
        $draft_hotels = $hotel_counts->draft ?? 0;

        // Đếm số lượng bookings (nếu có)
        global $wpdb;
        $bookings_table = $wpdb->prefix . 'vie_bookings';
        $total_bookings = 0;
        $pending_bookings = 0;

        if ($wpdb->get_var("SHOW TABLES LIKE '$bookings_table'") == $bookings_table) {
            $total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table");
            $pending_bookings = $wpdb->get_var("SELECT COUNT(*) FROM $bookings_table WHERE status = 'pending'");
        }
        ?>
        <div class="vie-hotel-stats">
            <div class="stats-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="stat-box" style="padding: 15px; background: #f0f6fc; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #2271b1;">
                        <?php echo esc_html($total_hotels); ?>
                    </div>
                    <div style="color: #646970; margin-top: 5px;">
                        <?php _e('Khách sạn đã đăng', 'vielimousine'); ?>
                    </div>
                </div>
                <div class="stat-box" style="padding: 15px; background: #fcf9e8; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #996800;">
                        <?php echo esc_html($draft_hotels); ?>
                    </div>
                    <div style="color: #646970; margin-top: 5px;">
                        <?php _e('Khách sạn nháp', 'vielimousine'); ?>
                    </div>
                </div>
                <div class="stat-box" style="padding: 15px; background: #f0fcf0; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #2c7a2c;">
                        <?php echo esc_html($total_bookings); ?>
                    </div>
                    <div style="color: #646970; margin-top: 5px;">
                        <?php _e('Tổng đặt phòng', 'vielimousine'); ?>
                    </div>
                </div>
                <div class="stat-box" style="padding: 15px; background: #fcf0f1; border-radius: 4px;">
                    <div style="font-size: 28px; font-weight: 600; color: #d63638;">
                        <?php echo esc_html($pending_bookings); ?>
                    </div>
                    <div style="color: #646970; margin-top: 5px;">
                        <?php _e('Đặt phòng chờ xử lý', 'vielimousine'); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Redirect sau khi login cho Hotel Manager
     * 
     * @since 1.0.0
     * @param string $redirect_to
     * @param string $request
     * @param WP_User $user
     * @return string
     */
    public function hotel_manager_login_redirect($redirect_to, $request, $user) {
        // Kiểm tra xem user có phải là Hotel Manager không
        if (isset($user->roles) && is_array($user->roles)) {
            if (in_array(self::HOTEL_MANAGER_ROLE, $user->roles)) {
                // Redirect đến trang quản lý khách sạn
                return admin_url('edit.php?post_type=hotel');
            }
        }
        return $redirect_to;
    }

    /**
     * Kiểm tra xem user hiện tại có phải Hotel Manager không
     * 
     * @since 1.0.0
     * @return bool
     */
    public static function is_hotel_manager() {
        return current_user_can(self::HOTEL_MANAGER_ROLE);
    }

    /**
     * Xóa role khi deactivate theme
     * 
     * @since 1.0.0
     */
    public static function remove_hotel_manager_role() {
        remove_role(self::HOTEL_MANAGER_ROLE);
    }
}

// Initialize
Vie_Role_Manager::get_instance();

// Hook để xóa role khi switch theme
add_action('switch_theme', array('Vie_Role_Manager', 'remove_hotel_manager_role'));
