# TASK-14: UPDATE MAIN BOOTSTRAP

**Phase:** 4 - Business Logic Migration  
**Thời gian:** 0.5 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-13 hoàn thành  

---

## 🎯 MỤC TIÊU

Cập nhật file bootstrap chính (functions.php) để load tất cả modules mới và xóa legacy hotel-rooms.php

---

## 📋 CHECKLIST

### PHẦN 1: Xóa Legacy Bootstrap

| # | Task | Status |
|---|------|--------|
| 1.1 | Xóa `inc/classes/class-hotel-rooms.php` (legacy copy) | ⬜ |
| 1.2 | Cập nhật functions.php để load modules mới | ⬜ |

### PHẦN 2: Update functions.php

| # | Section | Status |
|---|---------|--------|
| 2.1 | PHẦN 4: Load Core Classes | ⬜ |
| 2.2 | PHẦN 5: Load WordPress Hooks | ⬜ |
| 2.3 | PHẦN 6: Load Admin Controllers | ⬜ |
| 2.4 | PHẦN 7: Load Frontend Controllers | ⬜ |

---

## 📝 FUNCTIONS.PHP HOÀN CHỈNH

```php
<?php
/**
 * ============================================================================
 * TÊN FILE: functions.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * File bootstrap chính của Child Theme v2.0
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * PHẦN 1: ĐỊNH NGHĨA CONSTANTS
 * ============================================================================
 */
define('VIE_THEME_VERSION', '2.0.0');
define('VIE_THEME_PATH', get_stylesheet_directory());
define('VIE_THEME_URL', get_stylesheet_directory_uri());
define('VIE_DEBUG', defined('WP_DEBUG') && WP_DEBUG);

/**
 * ============================================================================
 * PHẦN 2: LOAD CẤU HÌNH
 * ============================================================================
 */
require_once VIE_THEME_PATH . '/inc/config/constants.php';

if (file_exists(VIE_THEME_PATH . '/inc/config/credentials.php')) {
    require_once VIE_THEME_PATH . '/inc/config/credentials.php';
}

/**
 * ============================================================================
 * PHẦN 3: LOAD HELPER FUNCTIONS
 * ============================================================================
 */
require_once VIE_THEME_PATH . '/inc/helpers/formatting.php';
require_once VIE_THEME_PATH . '/inc/helpers/security.php';
require_once VIE_THEME_PATH . '/inc/helpers/database.php';
require_once VIE_THEME_PATH . '/inc/helpers/templates.php';

/**
 * ============================================================================
 * PHẦN 4: LOAD CORE CLASSES
 * ============================================================================
 */
require_once VIE_THEME_PATH . '/inc/classes/class-google-auth.php';
require_once VIE_THEME_PATH . '/inc/classes/class-google-sheets-api.php';
require_once VIE_THEME_PATH . '/inc/classes/class-cache-manager.php';
require_once VIE_THEME_PATH . '/inc/classes/class-logger.php';
require_once VIE_THEME_PATH . '/inc/classes/class-database.php';
require_once VIE_THEME_PATH . '/inc/classes/class-helpers.php';
require_once VIE_THEME_PATH . '/inc/classes/class-email-manager.php';
require_once VIE_THEME_PATH . '/inc/classes/class-sepay-helper.php';
require_once VIE_THEME_PATH . '/inc/classes/class-sepay-webhook.php';
require_once VIE_THEME_PATH . '/inc/classes/class-coupon-validator.php';
require_once VIE_THEME_PATH . '/inc/classes/class-coupon-ajax.php';

/**
 * ============================================================================
 * PHẦN 5: LOAD WORDPRESS HOOKS
 * ============================================================================
 */
require_once VIE_THEME_PATH . '/inc/hooks/assets.php';
require_once VIE_THEME_PATH . '/inc/hooks/ajax.php';
require_once VIE_THEME_PATH . '/inc/hooks/shortcodes.php';
require_once VIE_THEME_PATH . '/inc/hooks/coupons.php';

/**
 * ============================================================================
 * PHẦN 6: LOAD ADMIN CONTROLLERS
 * ============================================================================
 */
if (is_admin()) {
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-rooms.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-ajax.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-bookings.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-settings.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-sepay.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-transport.php';
}

/**
 * ============================================================================
 * PHẦN 7: LOAD FRONTEND CONTROLLERS
 * ============================================================================
 */
require_once VIE_THEME_PATH . '/inc/frontend/class-shortcode-rooms.php';
require_once VIE_THEME_PATH . '/inc/frontend/class-ajax-handlers.php';
require_once VIE_THEME_PATH . '/inc/frontend/class-sepay-frontend.php';

/**
 * ============================================================================
 * PHẦN 8: KHỞI TẠO MODULES
 * ============================================================================
 */
add_action('after_setup_theme', function() {
    // Initialize Admin
    if (is_admin()) {
        new Vie_Admin_Rooms();
        new Vie_Admin_Bookings();
    }
    
    // Initialize Frontend
    new Vie_Shortcode_Rooms();
    new Vie_Ajax_Handlers();
    new Vie_SePay_Frontend();
    new Vie_Coupon_Ajax();
});

/**
 * ============================================================================
 * DEBUG LOG
 * ============================================================================
 */
if (VIE_DEBUG) {
    error_log('[VIE Theme] Loaded v' . VIE_THEME_VERSION);
}
```

---

## ✅ DEFINITION OF DONE

- [ ] Legacy class-hotel-rooms.php đã xóa
- [ ] functions.php đã cập nhật với tất cả requires
- [ ] Tất cả modules được load đúng thứ tự
- [ ] Không có PHP errors
- [ ] Theme hoạt động bình thường
- [ ] Git commit

---

## ⏭️ TASK TIẾP THEO

[TASK-15-FINAL-TESTING.md](./TASK-15-FINAL-TESTING.md)
