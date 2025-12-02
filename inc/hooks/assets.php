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
function vie_enqueue_frontend_assets()
{
    $version = VIE_THEME_VERSION;
    $css_url = VIE_THEME_URL . '/assets/css/frontend/';
    $js_url = VIE_THEME_URL . '/assets/js/frontend/';

    /**
     * -------------------------------------------------------------------------
     * CSS CHUNG (Load trên tất cả trang)
     * -------------------------------------------------------------------------
     */
    if (file_exists(VIE_THEME_PATH . '/assets/css/shared/_variables.css')) {
        wp_enqueue_style(
            'vie-variables',
            VIE_THEME_URL . '/assets/css/shared/_variables.css',
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

        // Swiper Library (CDN)
        wp_enqueue_style('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css', [], '11.0.0');
        wp_enqueue_script('swiper', 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js', [], '11.0.0', true);

        // JS Modules
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/core.js')) {
            wp_enqueue_script('vie-core', $js_url . 'core.js', ['jquery'], $version, true);

            // Localize script data
            wp_localize_script('vie-core', 'vieBooking', vie_get_booking_localize_data());
        }

        // Booking popup (includes datepicker initialization internally)
        if (file_exists(VIE_THEME_PATH . '/assets/js/frontend/booking-popup.js')) {
            wp_enqueue_script(
                'vie-booking-popup',
                $js_url . 'booking-popup.js',
                ['vie-core', 'jquery-ui-datepicker', 'swiper'],
                $version,
                true
            );
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
function vie_enqueue_admin_assets($hook_suffix)
{
    // Chỉ load trên các trang admin của theme
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

    if (strpos($page, 'vie-hotel') === false) {
        return;
    }

    $version = VIE_THEME_VERSION;
    $css_url = VIE_THEME_URL . '/assets/css/admin/';
    $js_url = VIE_THEME_URL . '/assets/js/admin/';

    /**
     * -------------------------------------------------------------------------
     * CSS FILES
     * -------------------------------------------------------------------------
     */

    // Shared Variables (định nghĩa --hotel-primary, --status-*, etc.)
    if (file_exists(VIE_THEME_PATH . '/assets/css/shared/_variables.css')) {
        wp_enqueue_style('vie-admin-variables', VIE_THEME_URL . '/assets/css/shared/_variables.css', [], $version);
    }

    // Common admin styles
    if (file_exists(VIE_THEME_PATH . '/assets/css/admin/common.css')) {
        wp_enqueue_style('vie-admin-common', $css_url . 'common.css', ['vie-admin-variables'], $version);
    }

    // Load page-specific CSS
    switch ($page) {
        case 'vie-hotel-bookings':
            if (file_exists(VIE_THEME_PATH . '/assets/css/admin/page-bookings.css')) {
                wp_enqueue_style('vie-admin-bookings', $css_url . 'page-bookings.css', ['vie-admin-common'], $version);
            }
            break;

        case 'vie-hotel-rooms':
            if (file_exists(VIE_THEME_PATH . '/assets/css/admin/page-rooms.css')) {
                wp_enqueue_style('vie-admin-rooms', $css_url . 'page-rooms.css', ['vie-admin-common'], $version);
            }
            // Enqueue media scripts cho upload ảnh
            wp_enqueue_media();
            break;

        case 'vie-hotel-calendar':
            if (file_exists(VIE_THEME_PATH . '/assets/css/admin/page-bulk-matrix.css')) {
                wp_enqueue_style('vie-admin-calendar', $css_url . 'page-bulk-matrix.css', ['vie-admin-common'], $version);
            }
            break;

        case 'vie-hotel-bulk-update':
            if (file_exists(VIE_THEME_PATH . '/assets/css/admin/page-bulk-matrix.css')) {
                wp_enqueue_style('vie-admin-bulk-matrix', $css_url . 'page-bulk-matrix.css', ['vie-admin-common'], $version);
            }
            // Load JS
            if (file_exists(VIE_THEME_PATH . '/assets/js/admin/page-bulk-matrix.js')) {
                wp_enqueue_script(
                    'vie-admin-bulk-matrix',
                    VIE_THEME_URL . '/assets/js/admin/page-bulk-matrix.js',
                    ['jquery'],
                    $version,
                    true
                );
                wp_localize_script('vie-admin-bulk-matrix', 'vieHotelRooms', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vie_hotel_rooms_nonce')
                ));
            }
            break;

        case 'vie-hotel-settings':
            if (file_exists(VIE_THEME_PATH . '/assets/css/admin/page-settings.css')) {
                wp_enqueue_style('vie-admin-settings', $css_url . 'page-settings.css', ['vie-admin-common'], $version);
            }
            break;
    }

    /**
     * -------------------------------------------------------------------------
     * JSFILES
     * -------------------------------------------------------------------------
     */

    if (file_exists(VIE_THEME_PATH . '/assets/js/admin/common.js')) {
        wp_enqueue_script('vie-admin-common', $js_url . 'common.js', ['jquery'], $version, true);

        // Localize admin data
        wp_localize_script('vie-admin-common', 'vieAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vie_admin_nonce'),
            'i18n' => [
                'confirm_delete' => __('Bạn có chắc muốn xóa?', 'viechild'),
                'saving' => __('Đang lưu...', 'viechild'),
                'saved' => __('Đã lưu!', 'viechild'),
                'error' => __('Có lỗi xảy ra', 'viechild'),
            ]
        ]);
    }
}
add_action('admin_enqueue_scripts', 'vie_enqueue_admin_assets');

/**
 * Lấy dữ liệu localize cho booking scripts
 * 
 * @since   2.0.0
 * @return  array   Dữ liệu cho wp_localize_script
 */
function vie_get_booking_localize_data(): array
{
    $data = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vie_booking_nonce'),
        'hotelId' => get_the_ID(),
        'homeUrl' => home_url(),
        'checkoutUrl' => home_url('/checkout/'),
        'currency' => 'VNĐ',
        'dateFormat' => 'dd/mm/yy',
        'minDate' => 0,
        'debug' => VIE_DEBUG,
        'i18n' => [
            'selectDates' => __('Vui lòng chọn ngày', 'viechild'),
            'calculating' => __('Đang tính giá...', 'viechild'),
            'roomUnavailable' => __('Phòng không khả dụng', 'viechild'),
            'soldOut' => __('Hết phòng', 'viechild'),
            'stopSell' => __('Ngừng bán', 'viechild'),
            'book' => __('Đặt ngay', 'viechild'),
            'viewDetail' => __('Xem chi tiết', 'viechild'),
            'close' => __('Đóng', 'viechild'),
            'next' => __('Tiếp tục', 'viechild'),
            'back' => __('Quay lại', 'viechild'),
            'confirm' => __('Xác nhận đặt phòng', 'viechild'),
            'success' => __('Đặt phòng thành công!', 'viechild'),
            'error' => __('Có lỗi xảy ra', 'viechild'),
            'required' => __('Vui lòng điền đầy đủ thông tin', 'viechild'),
            'requiredTransport' => __('Vui lòng chọn giờ đi và giờ về', 'viechild'),
            'nights' => __('đêm', 'viechild'),
            'adults' => __('người lớn', 'viechild'),
            'children' => __('trẻ em', 'viechild'),
            'rooms' => __('phòng', 'viechild'),
            'childAge' => __('Tuổi bé', 'viechild'),
            'priceFrom' => __('Giá từ', 'viechild'),
            'perNight' => __('/đêm', 'viechild'),
        ]
    ];

    // Get transport data from current hotel's location
    if (is_singular('hotel')) {
        $hotel_id = get_the_ID();
        $terms = get_the_terms($hotel_id, 'hotel-location');

        if ($terms && !is_wp_error($terms)) {
            $location = $terms[0];
            $pickup_times = get_term_meta($location->term_id, 'pickup_times', true);
            $dropoff_times = get_term_meta($location->term_id, 'dropoff_times', true);
            $transport_note = get_term_meta($location->term_id, 'transport_note', true);

            $data['transport'] = [
                'enabled' => !empty($pickup_times) || !empty($dropoff_times),
                'pickup_times' => !empty($pickup_times) ? array_map('trim', explode("\n", $pickup_times)) : [],
                'dropoff_times' => !empty($dropoff_times) ? array_map('trim', explode("\n", $dropoff_times)) : [],
                'pickup_note' => $transport_note
            ];
        }
    }

    return $data;
}