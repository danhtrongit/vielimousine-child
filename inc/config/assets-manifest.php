<?php
/**
 * ============================================================================
 * TÊN FILE: assets-manifest.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Định nghĩa manifest cho tất cả CSS/JS assets trong theme.
 * File này giúp quản lý dependencies, loading order và conditional loading.
 *
 * CHỨC NĂNG:
 * - Define tất cả assets (CSS/JS) của theme
 * - Quản lý dependencies giữa các files
 * - Conditional loading dựa trên page/context
 * - Version management cho cache busting
 *
 * SỬ DỤNG:
 * $manifest = require VIE_THEME_PATH . '/inc/config/assets-manifest.php';
 * foreach ($manifest['admin']['css'] as $handle => $asset) { ... }
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Config
 * @version     2.1.0
 * @since       2.1.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Asset Manifest Structure
 *
 * Mỗi asset có cấu trúc:
 * 'handle' => [
 *     'src'      => 'đường dẫn file (relative to assets/)',
 *     'deps'     => ['dependencies'],
 *     'version'  => 'version string hoặc null (sẽ dùng VIE_THEME_VERSION)',
 *     'media'    => 'media query (chỉ CSS)',
 *     'in_footer'=> true/false (chỉ JS),
 *     'condition'=> callable hoặc boolean,
 *     'localize' => [ 'object_name' => [...data] ] (chỉ JS)
 * ]
 */

return [
    /**
     * =========================================================================
     * SHARED ASSETS (Dùng chung cả frontend và admin)
     * =========================================================================
     */
    'shared' => [
        'css' => [
            'vie-variables-shared' => [
                'src'     => 'css/shared/_variables.css',
                'deps'    => [],
                'version' => VIE_THEME_VERSION,
                'media'   => 'all',
            ],
        ],
        'js' => [
            // Có thể thêm shared JS utils nếu cần
        ],
    ],

    /**
     * =========================================================================
     * FRONTEND ASSETS
     * =========================================================================
     */
    'frontend' => [
        'css' => [
            /**
             * Core CSS - Load trên tất cả trang frontend
             */
            'vie-variables' => [
                'src'       => 'css/_variables.css',
                'deps'      => [],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => true, // Always load
            ],

            'vie-main' => [
                'src'       => 'css/frontend/main.css',
                'deps'      => ['vie-variables'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => true,
            ],

            /**
             * Hotel Single Page CSS
             */
            'vie-room-listing' => [
                'src'       => 'css/frontend/room-listing.css',
                'deps'      => ['vie-main'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    return is_singular('hotel');
                },
            ],

            'vie-booking-popup' => [
                'src'       => 'css/frontend/booking-popup.css',
                'deps'      => ['vie-main'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    return is_singular('hotel');
                },
            ],

            'vie-datepicker-styles' => [
                'src'       => 'css/frontend/datepicker.css',
                'deps'      => ['jquery-ui-datepicker'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    return is_singular('hotel');
                },
            ],

            /**
             * Checkout Page CSS
             */
            'vie-checkout' => [
                'src'       => 'css/frontend/checkout.css',
                'deps'      => ['vie-variables'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    return is_page('checkout');
                },
            ],

            'vie-payment' => [
                'src'       => 'css/frontend/payment.css',
                'deps'      => ['vie-variables'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    return is_page('checkout');
                },
            ],
        ],

        'js' => [
            /**
             * Core JS - Base functionality
             */
            'vie-core' => [
                'src'       => 'js/frontend/core.js',
                'deps'      => ['jquery'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    return is_singular('hotel') || is_page('checkout');
                },
                'localize'  => [
                    'vieBooking' => function() {
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
                            ],
                        ];
                    },
                ],
            ],

            /**
             * Hotel Single Page JS
             */
            'vie-booking-popup' => [
                'src'       => 'js/frontend/booking-popup.js',
                'deps'      => ['vie-core', 'jquery-ui-datepicker'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    return is_singular('hotel');
                },
            ],

            /**
             * Checkout Page JS
             */
            'vie-payment' => [
                'src'       => 'js/frontend/payment.js',
                'deps'      => ['vie-core'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    return is_page('checkout');
                },
            ],
        ],
    ],

    /**
     * =========================================================================
     * ADMIN ASSETS
     * =========================================================================
     */
    'admin' => [
        'css' => [
            /**
             * Core Admin CSS
             */
            'vie-admin-variables' => [
                'src'       => 'css/admin/_variables.css',
                'deps'      => [],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => true, // Always load in admin
            ],

            'vie-admin-common' => [
                'src'       => 'css/admin/common.css',
                'deps'      => ['vie-admin-variables'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => true,
            ],

            /**
             * Page-specific Admin CSS
             */
            'vie-admin-bookings' => [
                'src'       => 'css/admin/page-bookings.css',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-bookings';
                },
            ],

            'vie-admin-rooms' => [
                'src'       => 'css/admin/page-rooms.css',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-rooms';
                },
            ],

            'vie-admin-calendar' => [
                'src'       => 'css/admin/page-bulk-matrix.css',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return in_array($page, ['vie-hotel-calendar', 'vie-hotel-bulk-update']);
                },
            ],

            'vie-admin-settings' => [
                'src'       => 'css/admin/page-settings.css',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'media'     => 'all',
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-settings';
                },
            ],
        ],

        'js' => [
            /**
             * Core Admin JS
             */
            'vie-admin-common' => [
                'src'       => 'js/admin/common.js',
                'deps'      => ['jquery'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => true,
                'localize'  => [
                    'vieAdmin' => [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('vie_admin_nonce'),
                        'i18n'    => [
                            'confirm_delete' => __('Bạn có chắc muốn xóa?', 'viechild'),
                            'saving'         => __('Đang lưu...', 'viechild'),
                            'saved'          => __('Đã lưu!', 'viechild'),
                            'error'          => __('Có lỗi xảy ra', 'viechild'),
                        ],
                    ],
                ],
            ],

            /**
             * Page-specific Admin JS
             */
            'vie-admin-bookings' => [
                'src'       => 'js/admin/page-bookings.js',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-bookings';
                },
            ],

            'vie-admin-rooms' => [
                'src'       => 'js/admin/page-rooms.js',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-rooms';
                },
            ],

            'vie-admin-calendar' => [
                'src'       => 'js/admin/page-calendar.js',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-calendar';
                },
            ],

            'vie-admin-bulk-matrix' => [
                'src'       => 'js/admin/page-bulk-matrix.js',
                'deps'      => ['vie-admin-common'],
                'version'   => VIE_THEME_VERSION,
                'in_footer' => true,
                'condition' => function() {
                    $page = $_GET['page'] ?? '';
                    return $page === 'vie-hotel-bulk-update';
                },
                'localize'  => [
                    'vieHotelRooms' => [
                        'ajaxUrl' => admin_url('admin-ajax.php'),
                        'nonce'   => wp_create_nonce('vie_hotel_rooms_nonce'),
                    ],
                ],
            ],
        ],
    ],

    /**
     * =========================================================================
     * EXTERNAL LIBRARIES (WordPress core hoặc CDN)
     * =========================================================================
     */
    'external' => [
        'css' => [
            'jquery-ui-datepicker' => [
                'handle'  => 'jquery-ui-datepicker', // WordPress core
                'enqueue' => function() {
                    return is_singular('hotel');
                },
            ],
        ],
        'js' => [
            'jquery-ui-datepicker' => [
                'handle'  => 'jquery-ui-datepicker', // WordPress core
                'enqueue' => function() {
                    return is_singular('hotel');
                },
            ],
        ],
    ],
];
