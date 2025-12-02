<?php
/**
 * ============================================================================
 * TÊN FILE: admin-menu.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Đăng ký Admin menu pages cho Hotel Rooms Management module.
 * 
 * CHỨC NĂNG:
 * - Tạo top-level menu "Vie Hotel"
 * - Đăng ký các submenu: Rooms, Bookings, Calendar, Settings
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Hooks
 * @version     2.0.0
 * @since       2.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Đăng ký Admin Menus
 * 
 * @since   2.0.0
 * @hook    admin_menu
 */
function vie_register_admin_menus() {
    /**
     * -------------------------------------------------------------------------
     * MENU CHÍNH: Vie Hotel
     * -------------------------------------------------------------------------
     * Capability: manage_vie_hotel - cho phép cả Admin và Hotel Manager truy cập
     */
    add_menu_page(
        __('Vie Hotel', 'flavor'),           // Page title
        __('Vie Hotel', 'flavor'),           // Menu title
        'manage_vie_hotel',                   // Capability (changed from manage_options)
        'vie-hotel-rooms',                    // Menu slug
        'vie_render_admin_rooms_page',        // Callback function
        'dashicons-building',                 // Icon
        26                                    // Position
    );

    /**
     * -------------------------------------------------------------------------
     * SUBMENU: Quản lý Phòng (mặc định - cùng slug với parent)
     * -------------------------------------------------------------------------
     */
    add_submenu_page(
        'vie-hotel-rooms',                    // Parent slug
        __('Quản lý Phòng', 'flavor'),       // Page title
        __('Phòng', 'flavor'),               // Menu title
        'edit_vie_hotel_rooms',               // Capability (changed from manage_options)
        'vie-hotel-rooms',                    // Menu slug (same as parent = default)
        'vie_render_admin_rooms_page'         // Callback
    );

    /**
     * -------------------------------------------------------------------------
     * SUBMENU: Quản lý Đặt phòng
     * -------------------------------------------------------------------------
     */
    add_submenu_page(
        'vie-hotel-rooms',
        __('Quản lý Đặt phòng', 'flavor'),
        __('Đặt phòng', 'flavor'),
        'view_vie_hotel_bookings',            // Capability (changed from manage_options)
        'vie-hotel-bookings',
        'vie_render_admin_bookings_page'
    );

    /**
     * -------------------------------------------------------------------------
     * SUBMENU: Lịch Giá
     * -------------------------------------------------------------------------
     */
    add_submenu_page(
        'vie-hotel-rooms',
        __('Lịch Giá', 'flavor'),
        __('Lịch Giá', 'flavor'),
        'edit_vie_hotel_calendar',            // Capability (changed from manage_options)
        'vie-hotel-calendar',
        'vie_render_admin_calendar_page'
    );

    /**
     * -------------------------------------------------------------------------
     * SUBMENU: Bulk Update Giá
     * -------------------------------------------------------------------------
     */
    add_submenu_page(
        'vie-hotel-rooms',
        __('Cập nhật giá hàng loạt', 'flavor'),
        __('Bulk Update', 'flavor'),
        'edit_vie_hotel_calendar',            // Capability (changed from manage_options)
        'vie-hotel-bulk-update',
        'vie_render_admin_bulk_update_page'
    );

    /**
     * -------------------------------------------------------------------------
     * SUBMENU: Cài đặt
     * -------------------------------------------------------------------------
     */
    add_submenu_page(
        'vie-hotel-rooms',
        __('Cài đặt', 'flavor'),
        __('Cài đặt', 'flavor'),
        'view_vie_hotel_settings',            // Capability (changed from manage_options)
        'vie-hotel-settings',
        'vie_render_admin_settings_page'
    );

    /**
     * -------------------------------------------------------------------------
     * SUBMENU: Database Status (chỉ Admin)
     * -------------------------------------------------------------------------
     */
    add_submenu_page(
        'vie-hotel-rooms',
        __('Database', 'flavor'),
        __('Database', 'flavor'),
        'manage_options',                     // Giữ nguyên - chỉ Admin
        'vie-hotel-database',
        'vie_render_admin_database_page'
    );
}
add_action('admin_menu', 'vie_register_admin_menus');

/**
 * ============================================================================
 * PLACEHOLDER RENDER FUNCTIONS
 * ============================================================================
 * Các hàm render tạm - sẽ được thay thế khi migrate Admin Controllers
 */

/**
 * Render trang Quản lý Phòng
 * 
 * @since   2.0.0
 */
function vie_render_admin_rooms_page() {
    // Check if Admin Controller exists (v2.1: class alias support)
    if (class_exists('Vie_Admin_Rooms')) {
        $controller = new Vie_Admin_Rooms();
        $controller->render();
        return;
    }

    // Fallback placeholder
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Quản lý Phòng', 'flavor') . '</h1>';
    echo '<div class="notice notice-info"><p>';
    echo esc_html__('Module đang được phát triển. Vui lòng quay lại sau.', 'flavor');
    echo '</p></div>';
    echo '</div>';
}

/**
 * Render trang Quản lý Đặt phòng
 * 
 * @since   2.0.0
 */
function vie_render_admin_bookings_page() {
    // Check if Admin Controller exists (v2.1: class alias support)
    if (class_exists('Vie_Admin_Bookings')) {
        $controller = new Vie_Admin_Bookings();
        $controller->render();
        return;
    }

    // Fallback placeholder
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Quản lý Đặt phòng', 'flavor') . '</h1>';
    echo '<div class="notice notice-info"><p>';
    echo esc_html__('Module đang được phát triển. Vui lòng quay lại sau.', 'flavor');
    echo '</p></div>';
    echo '</div>';
}

/**
 * Render trang Lịch Giá
 * 
 * @since   2.0.0
 */
function vie_render_admin_calendar_page() {
    // Check if Admin Controller exists (v2.1: class alias support)
    if (class_exists('Vie_Admin_Calendar')) {
        $controller = new Vie_Admin_Calendar();
        $controller->render();
        return;
    }

    // Fallback placeholder
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Lịch Giá', 'flavor') . '</h1>';
    echo '<div class="notice notice-info"><p>';
    echo esc_html__('Module đang được phát triển. Vui lòng quay lại sau.', 'flavor');
    echo '</p></div>';
    echo '</div>';
}

/**
 * Render trang Bulk Update Giá
 *
 * @since   2.0.0
 */
function vie_render_admin_bulk_update_page() {
    // Lấy tham số từ URL
    $current_month  = isset($_GET['month']) ? absint($_GET['month']) : (int) date('n');
    $current_year   = isset($_GET['year']) ? absint($_GET['year']) : (int) date('Y');
    $selected_hotel = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;

    // Validate month/year
    if ($current_month < 1 || $current_month > 12) {
        $current_month = (int) date('n');
    }
    if ($current_year < 2020 || $current_year > 2030) {
        $current_year = (int) date('Y');
    }

    // Lấy danh sách hotels
    $hotels = get_posts(array(
        'post_type'      => 'hotel',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC'
    ));

    // Load template (v2.1: try new location first)
    $new_template = VIE_THEME_PATH . '/inc/admin/Views/calendar/bulk-matrix.php';
    $old_template = VIE_THEME_PATH . '/template-parts/admin/calendar/bulk-matrix.php';

    if (file_exists($new_template)) {
        include $new_template;
    } elseif (file_exists($old_template)) {
        include $old_template;
    } else {
        // Fallback placeholder
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Cập nhật giá hàng loạt', 'flavor') . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Template không tìm thấy.', 'flavor');
        echo '</p></div>';
        echo '</div>';
    }
}

/**
 * Render trang Cài đặt
 * 
 * @since   2.0.0
 */
function vie_render_admin_settings_page() {
    // Check if Admin Controller exists (v2.1: class alias support)
    if (class_exists('Vie_Admin_Settings')) {
        $controller = new Vie_Admin_Settings();
        $controller->render();
        return;
    }

    // Fallback placeholder
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('Cài đặt', 'flavor') . '</h1>';
    echo '<div class="notice notice-info"><p>';
    echo esc_html__('Module đang được phát triển. Vui lòng quay lại sau.', 'flavor');
    echo '</p></div>';
    echo '</div>';
}

/**
 * Render trang Database Status
 * 
 * @since   2.0.0
 */
function vie_render_admin_database_page() {
    // Load template
    $template = VIE_THEME_PATH . '/template-parts/admin/database-status.php';
    if (file_exists($template)) {
        include $template;
    } else {
        // Fallback
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Database Status', 'flavor') . '</h1>';
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Template không tìm thấy.', 'flavor');
        echo '</p></div>';
        echo '</div>';
    }
}

/**
 * ============================================================================
 * ADMIN BAR MENU
 * ============================================================================
 */

/**
 * Thêm quick links vào Admin Bar
 * 
 * @since   2.0.0
 * @param   WP_Admin_Bar  $wp_admin_bar
 */
function vie_add_admin_bar_menu($wp_admin_bar) {
    // Cho phép cả Admin và Hotel Manager
    if (!current_user_can('manage_vie_hotel')) {
        return;
    }

    // Parent menu
    $wp_admin_bar->add_node(array(
        'id'    => 'vie-hotel',
        'title' => '<span class="ab-icon dashicons dashicons-building"></span> Vie Hotel',
        'href'  => admin_url('admin.php?page=vie-hotel-rooms'),
    ));

    // Child: Bookings
    $wp_admin_bar->add_node(array(
        'parent' => 'vie-hotel',
        'id'     => 'vie-hotel-bookings',
        'title'  => __('Đặt phòng', 'flavor'),
        'href'   => admin_url('admin.php?page=vie-hotel-bookings'),
    ));

    // Child: Add Room
    $wp_admin_bar->add_node(array(
        'parent' => 'vie-hotel',
        'id'     => 'vie-hotel-add-room',
        'title'  => __('+ Thêm phòng', 'flavor'),
        'href'   => admin_url('admin.php?page=vie-hotel-rooms&action=add'),
    ));
}
add_action('admin_bar_menu', 'vie_add_admin_bar_menu', 100);
