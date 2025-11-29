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

// Credentials (API keys, SMTP, etc.) - file này KHÔNG được commit lên git
if (file_exists(VIE_THEME_PATH . '/inc/config/credentials.php')) {
    require_once VIE_THEME_PATH . '/inc/config/credentials.php';
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
 */

// Quản lý phòng
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-room-manager.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-room-manager.php';
}

// Quản lý đặt phòng
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-booking-manager.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-booking-manager.php';
}

// Engine tính giá
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-pricing-engine.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-pricing-engine.php';
}

// Gửi email
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-email-manager.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-email-manager.php';
}

// Google Sheets API
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-google-sheets-api.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-google-sheets-api.php';
}

// SePay Payment Gateway
if (file_exists(VIE_THEME_PATH . '/inc/classes/class-sepay-gateway.php')) {
    require_once VIE_THEME_PATH . '/inc/classes/class-sepay-gateway.php';
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
 * PHẦN 6: LOAD ADMIN CONTROLLERS (Chỉ trong admin)
 * ============================================================================
 */
if (is_admin()) {
    // Controller quản lý phòng
    if (file_exists(VIE_THEME_PATH . '/inc/admin/class-admin-rooms.php')) {
        require_once VIE_THEME_PATH . '/inc/admin/class-admin-rooms.php';
    }
    
    // Controller quản lý đặt phòng
    if (file_exists(VIE_THEME_PATH . '/inc/admin/class-admin-bookings.php')) {
        require_once VIE_THEME_PATH . '/inc/admin/class-admin-bookings.php';
    }
    
    // Controller lịch giá
    if (file_exists(VIE_THEME_PATH . '/inc/admin/class-admin-calendar.php')) {
        require_once VIE_THEME_PATH . '/inc/admin/class-admin-calendar.php';
    }
    
    // Controller cài đặt
    if (file_exists(VIE_THEME_PATH . '/inc/admin/class-admin-settings.php')) {
        require_once VIE_THEME_PATH . '/inc/admin/class-admin-settings.php';
    }
}

/**
 * ============================================================================
 * PHẦN 7: LOAD FRONTEND CONTROLLERS
 * ============================================================================
 */

// Shortcode hiển thị danh sách phòng
if (file_exists(VIE_THEME_PATH . '/inc/frontend/class-shortcode-rooms.php')) {
    require_once VIE_THEME_PATH . '/inc/frontend/class-shortcode-rooms.php';
}

// AJAX handlers cho frontend
if (file_exists(VIE_THEME_PATH . '/inc/frontend/class-ajax-handlers.php')) {
    require_once VIE_THEME_PATH . '/inc/frontend/class-ajax-handlers.php';
}

/**
 * ============================================================================
 * DEBUG LOG
 * ============================================================================
 */
if (VIE_DEBUG) {
    error_log('[VIE Theme] Loaded v' . VIE_THEME_VERSION);
}
