<?php
/**
 * ============================================================================
 * TÊN FILE: functions.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * File bootstrap chính của Child Theme v2.0
 * CHỈ chứa logic require các module, KHÔNG viết business logic ở đây
 * 
 * QUY TẮC:
 * - Mọi logic phải nằm trong /inc/
 * - File này chỉ định nghĩa constants và require files
 * - Thứ tự require rất quan trọng (dependencies)
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * PHẦN 1: ĐỊNH NGHĨA CONSTANTS
 * ============================================================================
 */

/** Phiên bản theme - dùng cho cache busting CSS/JS */
define('VIE_THEME_VERSION', '2.0.0');

/** Đường dẫn tuyệt đối đến thư mục theme */
define('VIE_THEME_PATH', get_stylesheet_directory());

/** URL đến thư mục theme */
define('VIE_THEME_URL', get_stylesheet_directory_uri());

/** Bật/tắt debug mode */
define('VIE_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

/**
 * ============================================================================
 * PHẦN 2: LOAD CẤU HÌNH
 * ============================================================================
 * Các file config phải load đầu tiên vì các file khác phụ thuộc vào constants
 */

// Constants cho các module (API URLs, cache duration, etc.)
if (file_exists(VIE_THEME_PATH . '/inc/config/constants.php')) {
    require_once VIE_THEME_PATH . '/inc/config/constants.php';
}

/**
 * ============================================================================
 * PHẦN 3: LOAD HELPER FUNCTIONS
 * ============================================================================
 * Các hàm tiện ích dùng chung trong toàn bộ theme
 */

// Hàm format tiền, ngày tháng
if (file_exists(VIE_THEME_PATH . '/inc/helpers/formatting.php')) {
    require_once VIE_THEME_PATH . '/inc/helpers/formatting.php';
}

// Hàm sanitize, validate input
if (file_exists(VIE_THEME_PATH . '/inc/helpers/security.php')) {
    require_once VIE_THEME_PATH . '/inc/helpers/security.php';
}

// Hàm thao tác database
if (file_exists(VIE_THEME_PATH . '/inc/helpers/database.php')) {
    require_once VIE_THEME_PATH . '/inc/helpers/database.php';
}

// Hàm load templates
if (file_exists(VIE_THEME_PATH . '/inc/helpers/templates.php')) {
    require_once VIE_THEME_PATH . '/inc/helpers/templates.php';
}

/**
 * ============================================================================
 * PHẦN 4: LOAD CORE CLASSES
 * ============================================================================
 * Các class xử lý business logic chính
 * Thứ tự load rất quan trọng - classes phụ thuộc phải load sau
 */

// Google Auth (v2.1: Di chuyển vào Services/Integration/)
if (file_exists(VIE_THEME_PATH . '/inc/Services/Integration/GoogleAuth.php')) {
    require_once VIE_THEME_PATH . '/inc/Services/Integration/GoogleAuth.php';
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-google-auth.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-google-auth.php';
}

// Google Sheets API (v2.1: Di chuyển vào Services/Integration/)
if (file_exists(VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php')) {
    require_once VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php';
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-google-sheets-api.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-google-sheets-api.php';
}

// Cache Manager (v2.1: Di chuyển vào Support/Cache/)
if (file_exists(VIE_THEME_PATH . '/inc/Support/Cache/CacheManager.php')) {
    require_once VIE_THEME_PATH . '/inc/Support/Cache/CacheManager.php';
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-cache-manager.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-cache-manager.php';
}

/**
 * ============================================================================
 * LEGACY MODULE: HOTEL ROOMS (REMOVED IN v2.1)
 * ============================================================================
 *
 * Module cũ đã được tách thành các class riêng trong v2.0 và XÓA HOÀN TOÀN trong v2.1:
 * - class-booking-manager.php (Quản lý đặt phòng)
 * - class-pricing-engine.php (Engine tính giá)
 * - class-email-manager.php (Gửi email)
 * - class-sepay-gateway.php (Payment gateway)
 *
 * File class-hotel-rooms.php đã bị XÓA vào v2.1.0 (2025-12-01)
 * Tất cả chức năng đã được refactor vào các module riêng biệt.
 */

// Pricing Service (v2.1: Di chuyển vào Services/Pricing/)
if (file_exists(VIE_THEME_PATH . '/inc/Services/Pricing/PricingService.php')) {
    require_once VIE_THEME_PATH . '/inc/Services/Pricing/PricingService.php';
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-pricing-engine.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-pricing-engine.php';
}

// Booking Service (v2.1: Di chuyển vào Services/Booking/)
if (file_exists(VIE_THEME_PATH . '/inc/Services/Booking/BookingService.php')) {
    require_once VIE_THEME_PATH . '/inc/Services/Booking/BookingService.php';
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-booking-manager.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-booking-manager.php';
}

// Custom Fields for Hotel Location (Transport)
if (file_exists(VIE_THEME_PATH . '/inc/admin/Taxonomy/TransportFields.php')) {
    require_once VIE_THEME_PATH . '/inc/admin/Taxonomy/TransportFields.php';
}

// Coupon Service (v2.1: Di chuyển vào Services/Coupon/)
if (file_exists(VIE_THEME_PATH . '/inc/Services/Coupon/CouponService.php')) {
    require_once VIE_THEME_PATH . '/inc/Services/Coupon/CouponService.php';
    // Initialize service to register AJAX hooks
    Vie_Coupon_Service::get_instance();
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-coupon-manager.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-coupon-manager.php';
}

// Email Service (v2.1: Di chuyển vào Services/Email/)
if (file_exists(VIE_THEME_PATH . '/inc/Services/Email/EmailService.php')) {
    require_once VIE_THEME_PATH . '/inc/Services/Email/EmailService.php';
} elseif (file_exists(VIE_THEME_PATH . '/inc/classes/class-email-manager.php')) {
    // Fallback to old location (backward compatibility)
    require_once VIE_THEME_PATH . '/inc/classes/class-email-manager.php';
}

// SePay Payment Gateway
// Sử dụng file cũ class-sepay-gateway.php để giữ nguyên logic OAuth đang hoạt động
// Services mới (v2.1) bị tạm disable do lỗi "Invalid redirect URI"
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-sepay-gateway.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-sepay-gateway.php';
}

// Custom Fields for Hotel Location (Transport)
if (file_exists(VIE_THEME_PATH . '/inc/admin/Taxonomy/TransportFields.php')) {
    require_once VIE_THEME_PATH . '/inc/admin/Taxonomy/TransportFields.php';
}

// Hotel Post Type - Đảm bảo Hotel post type sử dụng custom capabilities
if (file_exists(VIE_THEME_PATH . '/inc/admin/HotelPostType.php')) {
    require_once VIE_THEME_PATH . '/inc/admin/HotelPostType.php';
}

// Role Manager - Quản lý phân quyền người dùng (Hotel Manager role)
if (file_exists(VIE_THEME_PATH . '/inc/admin/RoleManager.php')) {
    require_once VIE_THEME_PATH . '/inc/admin/RoleManager.php';
}

// Database Installer - Tự động tạo và migrate database tables
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-database-installer.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-database-installer.php';
}

/**
 * ============================================================================
 * PHẦN 5: LOAD WORDPRESS HOOKS
 * ============================================================================
 * Đăng ký actions, filters, shortcodes
 */

// Đăng ký và load CSS/JS
if (file_exists(VIE_THEME_PATH . '/inc/hooks/assets.php')) {
    require_once VIE_THEME_PATH . '/inc/hooks/assets.php';
}

// Đăng ký AJAX handlers
if (file_exists(VIE_THEME_PATH . '/inc/hooks/ajax.php')) {
    require_once VIE_THEME_PATH . '/inc/hooks/ajax.php';
}

// Đăng ký Admin menus
if (file_exists(VIE_THEME_PATH . '/inc/hooks/admin-menu.php')) {
    require_once VIE_THEME_PATH . '/inc/hooks/admin-menu.php';
}

// Đăng ký Shortcodes
if (file_exists(VIE_THEME_PATH . '/inc/hooks/shortcodes.php')) {
    require_once VIE_THEME_PATH . '/inc/hooks/shortcodes.php';
}

/**
 * ============================================================================
 * PHẦN 6: LOAD ADMIN PAGES (Chỉ trong admin)
 * ============================================================================
 * v2.1: Page Controller pattern (old files removed in cleanup)
 */
if (is_admin()) {
    // Bookings Page
    require_once VIE_THEME_PATH . '/inc/admin/Pages/BookingsPage.php';

    // Calendar Page
    require_once VIE_THEME_PATH . '/inc/admin/Pages/CalendarPage.php';

    // Rooms Page
    require_once VIE_THEME_PATH . '/inc/admin/Pages/RoomsPage.php';

    // Settings Page
    require_once VIE_THEME_PATH . '/inc/admin/Pages/SettingsPage.php';
}

/**
 * ============================================================================
 * PHẦN 7: LOAD FRONTEND CONTROLLERS
 * ============================================================================
 * v2.1: Shortcode Controller + AJAX Handler patterns (old files removed in cleanup)
 */

// Rooms Shortcode
// Registers 3 shortcodes: [vie_hotel_rooms], [vie_room_search], [vie_checkout]
require_once VIE_THEME_PATH . '/inc/frontend/Shortcodes/RoomsShortcode.php';

// Frontend AJAX Handlers (split into focused classes)
// Booking AJAX: calculate_price, check_availability, submit_booking, get_room_detail
require_once VIE_THEME_PATH . '/inc/frontend/AJAX/BookingAjax.php';

// Payment AJAX: process_checkout, update_booking_info
require_once VIE_THEME_PATH . '/inc/frontend/AJAX/PaymentAjax.php';

// Calendar AJAX: get_calendar_prices
require_once VIE_THEME_PATH . '/inc/frontend/AJAX/CalendarAjax.php';
/**
 * ============================================================================
 * DEBUG LOG
 * ============================================================================
 */
if (VIE_DEBUG) {
    error_log('[VIE Theme] Loaded v' . VIE_THEME_VERSION);
}
