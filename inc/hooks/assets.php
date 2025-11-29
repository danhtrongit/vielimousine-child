<?php
/**
 * ============================================================================
 * TÊN FILE: assets.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Quản lý việc load CSS/JS cho theme
 * Tối ưu: Chỉ load file cần thiết cho từng trang
 * 
 * HOOKS ĐĂNG KÝ:
 * - wp_enqueue_scripts: Load frontend assets
 * - admin_enqueue_scripts: Load admin assets
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Hooks
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Đăng ký và load CSS/JS cho Frontend
 * 
 * @since   2.0.0
 * @hook    wp_enqueue_scripts
 */
function vie_enqueue_frontend_assets() {
    $version = VIE_THEME_VERSION;
    $css_url = VIE_THEME_URL . '/assets/css/frontend/';
    $js_url  = VIE_THEME_URL . '/assets/js/frontend/';

    /**
     * -------------------------------------------------------------------------
     * CSS CHUNG (Load trên tất cả trang)
     * -------------------------------------------------------------------------
     */
    if (file_exists(VIE_THEME_PATH . '/assets/css/_variables.css')) {
        wp_enqueue_style(
            'vie-variables',
            VIE_THEME_URL . '/assets/css/_variables.css',
            [],
            $version
        );
    }

    /**
     * -------------------------------------------------------------------------
     * CSS/JS CHO TRANG HOTEL (Single Hotel Post)
     * -------------------------------------------------------------------------
     * Chỉ load khi xem chi tiết 1 khách sạn
     */
    if (is_singular('hotel')) {
        // jQuery UI Datepicker (đã có sẵn trong WP)
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker');

        // CSS Main (buttons, forms, utilities)
        if (file_exists(VIE_THEME_PATH . '/assets/css/frontend/main.css')) {
            wp_enqueue_style('vie-main', $css_url . 'main.css', ['vie-variables'], $version);
        }

        // CSS cho room listing
        if (file_exists(VIE_THEME_PATH . '/assets/css/frontend/room-listing.css')) {
            wp_enqueue_style('vie-room-listing', $css_url . 'room-listing.css', ['vie-main'], $version);
        }
        
        // CSS cho booking popup
        if (file_exists(VIE_THEME_PATH . '/assets/css/frontend/booking-popup.css')) {
            wp_enqueue_style('vie-booking-popup', $css_url . 'booking-popup.css', ['vie-main'], $version);
        }
        
        // CSS cho datepicker custom
        if (file_exists(VIE_THEME_PATH . '/assets/css/frontend/datepicker.css')) {
            wp_enqueue_style('vie-datepicker-styles', $css_url . 'datepicker.css', ['jquery-ui-datepicker'], $version);
        }

        // JS Modules
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/core.js')) {
            wp_enqueue_script('vie-core', $js_url . 'core.js', ['jquery'], $version, true);
            
            // Localize script data
            wp_localize_script('vie-core', 'vieBooking', vie_get_booking_localize_data());
        }
        
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/datepicker.js')) {
            wp_enqueue_script('vie-datepicker', $js_url . 'datepicker.js', ['vie-core', 'jquery-ui-datepicker'], $version, true);
        }
        
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/room-listing.js')) {
            wp_enqueue_script('vie-room-listing', $js_url . 'room-listing.js', ['vie-core'], $version, true);
        }
        
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/booking-popup.js')) {
            wp_enqueue_script('vie-booking-popup', $js_url . 'booking-popup.js', ['vie-core', 'vie-datepicker'], $version, true);
        }
    }

    /**
     * -------------------------------------------------------------------------
     * CSS/JS CHO TRANG CHECKOUT
     * -------------------------------------------------------------------------
     */
    if (is_page('checkout')) {
        if (file_exists(VIE_THEME_PATH . '/assets/css/frontend/checkout.css')) {
            wp_enqueue_style('vie-checkout', $css_url . 'checkout.css', ['vie-variables'], $version);
        }
        
        if (file_exists(VIE_THEME_PATH . '/assets/css/frontend/payment.css')) {
            wp_enqueue_style('vie-payment', $css_url . 'payment.css', ['vie-variables'], $version);
        }
        
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/core.js')) {
            wp_enqueue_script('vie-core', $js_url . 'core.js', ['jquery'], $version, true);
            wp_localize_script('vie-core', 'vieBooking', vie_get_booking_localize_data());
        }
        
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/payment.js')) {
            wp_enqueue_script('vie-payment', $js_url . 'payment.js', ['vie-core'], $version, true);
        }
    }
}
add_action('wp_enqueue_scripts', 'vie_enqueue_frontend_assets', 99);

/**
 * Đăng ký và load CSS/JS cho Admin
 * 
 * @since   2.0.0
 * @hook    admin_enqueue_scripts
 * 
 * @param   string  $hook_suffix    Hook suffix của trang admin hiện tại
 */
function vie_enqueue_admin_assets($hook_suffix) {
    // Chỉ load trên các trang admin của theme
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    
    if (strpos($page, 'vie-hotel') === false) {
        return;
    }

    $version = VIE_THEME_VERSION;
    $css_url = VIE_THEME_URL . '/assets/css/admin/';
    $js_url  = VIE_THEME_URL . '/assets/js/admin/';

    // Variables (dùng chung)
    if (file_exists(VIE_THEME_PATH . '/assets/css/_variables.css')) {
        wp_enqueue_style('vie-variables', VIE_THEME_URL . '/assets/css/_variables.css', [], $version);
    }
    
    // Common admin styles
    if (file_exists(VIE_THEME_PATH . '/assets/css/admin/common.css')) {
        wp_enqueue_style('vie-admin-common', $css_url . 'common.css', ['vie-variables'], $version);
    }
    
    if (file_exists(VIE_THEME_PATH . '/assets/js/admin/common.js')) {
        wp_enqueue_script('vie-admin-common', $js_url . 'common.js', ['jquery'], $version, true);
    }

    // Localize admin data
    wp_localize_script('vie-admin-common', 'vieAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vie_admin_nonce'),
        'i18n'    => [
            'confirm_delete' => __('Bạn có chắc muốn xóa?', 'viechild'),
            'saving'         => __('Đang lưu...', 'viechild'),
            'saved'          => __('Đã lưu!', 'viechild'),
            'error'          => __('Có lỗi xảy ra', 'viechild'),
        ]
    ]);
}
add_action('admin_enqueue_scripts', 'vie_enqueue_admin_assets');

/**
 * Lấy dữ liệu localize cho booking scripts
 * 
 * @since   2.0.0
 * @return  array   Dữ liệu cho wp_localize_script
 */
function vie_get_booking_localize_data(): array {
    return [
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('vie_booking_nonce'),
        'hotelId'     => get_the_ID(),
        'homeUrl'     => home_url(),
        'checkoutUrl' => home_url('/checkout/'),
        'currency'    => 'VNĐ',
        'dateFormat'  => 'dd/mm/yy',
        'minDate'     => 0,
        'debug'       => VIE_DEBUG,
        'i18n'        => [
            'selectDates'       => __('Vui lòng chọn ngày', 'viechild'),
            'calculating'       => __('Đang tính giá...', 'viechild'),
            'roomUnavailable'   => __('Phòng không khả dụng', 'viechild'),
            'soldOut'           => __('Hết phòng', 'viechild'),
            'stopSell'          => __('Ngừng bán', 'viechild'),
            'book'              => __('Đặt ngay', 'viechild'),
            'viewDetail'        => __('Xem chi tiết', 'viechild'),
            'close'             => __('Đóng', 'viechild'),
            'next'              => __('Tiếp tục', 'viechild'),
            'back'              => __('Quay lại', 'viechild'),
            'confirm'           => __('Xác nhận đặt phòng', 'viechild'),
            'success'           => __('Đặt phòng thành công!', 'viechild'),
            'error'             => __('Có lỗi xảy ra', 'viechild'),
            'required'          => __('Vui lòng điền đầy đủ thông tin', 'viechild'),
            'requiredTransport' => __('Vui lòng chọn giờ đi và giờ về', 'viechild'),
            'nights'            => __('đêm', 'viechild'),
            'adults'            => __('người lớn', 'viechild'),
            'children'          => __('trẻ em', 'viechild'),
            'rooms'             => __('phòng', 'viechild'),
            'childAge'          => __('Tuổi bé', 'viechild'),
            'priceFrom'         => __('Giá từ', 'viechild'),
            'perNight'          => __('/đêm', 'viechild'),
        ]
    ];
}
