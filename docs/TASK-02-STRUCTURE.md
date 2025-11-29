# TASK-02: TẠO CẤU TRÚC THƯ MỤC MỚI

**Phase:** 0 - Chuẩn bị  
**Thời gian:** 2-3 giờ  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-01 hoàn thành  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

Tạo cấu trúc thư mục chuẩn v2.0 và các file bootstrap cơ bản.

---

## 📋 CHECKLIST CHI TIẾT

### BƯỚC 1: Tạo cấu trúc thư mục Assets

| # | Task | Command | Status |
|---|------|---------|--------|
| 1.1 | Tạo thư mục assets | `mkdir -p assets/{css/{admin,frontend},js/{admin,frontend},images/icons,vendor}` | ⬜ |
| 1.2 | Verify cấu trúc | `tree assets/` hoặc `find assets -type d` | ⬜ |

**Expected output:**
```
assets/
├── css/
│   ├── admin/
│   └── frontend/
├── js/
│   ├── admin/
│   └── frontend/
├── images/
│   └── icons/
└── vendor/
```

---

### BƯỚC 2: Tạo cấu trúc thư mục Inc

| # | Task | Command | Status |
|---|------|---------|--------|
| 2.1 | Tạo thư mục inc | `mkdir -p inc/{classes,helpers,hooks,admin,frontend,config}` | ⬜ |
| 2.2 | Verify cấu trúc | `tree inc/` | ⬜ |

**Expected output:**
```
inc/
├── admin/
├── classes/
├── config/
├── frontend/
├── helpers/
└── hooks/
```

---

### BƯỚC 3: Tạo cấu trúc thư mục Template Parts

| # | Task | Command | Status |
|---|------|---------|--------|
| 3.1 | Tạo template-parts | `mkdir -p template-parts/{admin/{rooms,bookings,settings},frontend,email}` | ⬜ |
| 3.2 | Verify cấu trúc | `tree template-parts/` | ⬜ |

---

### BƯỚC 4: Tạo thư mục phụ trợ

| # | Task | Command | Status |
|---|------|---------|--------|
| 4.1 | Tạo thư mục languages | `mkdir languages` | ⬜ |
| 4.2 | Tạo thư mục data | `mkdir data` | ⬜ |
| 4.3 | Tạo thư mục logs | `mkdir logs` | ⬜ |
| 4.4 | Tạo thư mục credentials | `mkdir credentials` | ⬜ |

---

### BƯỚC 5: Tạo file .htaccess bảo vệ

| # | Task | File | Content | Status |
|---|------|------|---------|--------|
| 5.1 | Bảo vệ logs | `logs/.htaccess` | Xem code bên dưới | ⬜ |
| 5.2 | Bảo vệ credentials | `credentials/.htaccess` | Xem code bên dưới | ⬜ |
| 5.3 | Bảo vệ data | `data/.htaccess` | Xem code bên dưới | ⬜ |

**Nội dung file .htaccess:**
```apache
# Chặn truy cập trực tiếp vào thư mục này
<FilesMatch ".*">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

**Command tạo nhanh:**
```bash
echo '<FilesMatch ".*">
    Order Allow,Deny
    Deny from all
</FilesMatch>' | tee logs/.htaccess credentials/.htaccess data/.htaccess
```

---

### BƯỚC 6: Tạo file style.css

| # | Task | Status |
|---|------|--------|
| 6.1 | Tạo file style.css với metadata | ⬜ |

**Nội dung file `style.css`:**
```css
/**
 * ============================================================================
 * Theme Name:   Vielimousine Child
 * Template:     vielimousine
 * Author:       Vie Development Team
 * Author URI:   https://vielimousine.com
 * Description:  Child theme tùy biến cho hệ thống đặt phòng khách sạn Vie Limousine
 * Requires PHP: 8.0
 * Version:      2.0.0
 * Text Domain:  viechild
 * Domain Path:  /languages
 * ============================================================================
 * 
 * CHANGELOG:
 * 
 * v2.0.0 (29/11/2024)
 * - Tái cấu trúc toàn bộ theme theo chuẩn MVC
 * - Tách CSS/JS thành modules
 * - Áp dụng BEM naming convention
 * - Thêm comment tiếng Việt đầy đủ
 * 
 * v1.x.x (Legacy)
 * - Xem trong _backup_legacy_v1_* folder
 * ============================================================================
 */

/* 
 * NOTE: File này chỉ chứa metadata của theme.
 * Tất cả styles nằm trong /assets/css/
 */
```

---

### BƯỚC 7: Tạo file functions.php

| # | Task | Status |
|---|------|--------|
| 7.1 | Tạo file functions.php bootstrap | ⬜ |

**Nội dung file `functions.php`:**
```php
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
```

---

### BƯỚC 8: Tạo file placeholder cho các module

| # | Task | Command | Status |
|---|------|---------|--------|
| 8.1 | Tạo placeholder hooks | Xem code bên dưới | ⬜ |
| 8.2 | Tạo placeholder helpers | Xem code bên dưới | ⬜ |

**File `inc/hooks/assets.php` (placeholder):**
```php
<?php
/**
 * ============================================================================
 * TÊN FILE: assets.php
 * ============================================================================
 * MÔ TẢ: Quản lý việc load CSS/JS cho theme
 * TODO: Implement trong TASK-03, TASK-04
 * ============================================================================
 */

defined('ABSPATH') || exit;

// Placeholder - sẽ implement sau
add_action('wp_enqueue_scripts', function() {
    // TODO: Load frontend assets
}, 99);

add_action('admin_enqueue_scripts', function() {
    // TODO: Load admin assets
});
```

**File `inc/helpers/formatting.php` (placeholder):**
```php
<?php
/**
 * ============================================================================
 * TÊN FILE: formatting.php
 * ============================================================================
 * MÔ TẢ: Các hàm format dữ liệu (tiền, ngày tháng)
 * TODO: Migrate từ legacy code
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Format số tiền theo định dạng Việt Nam
 * 
 * @param float $amount Số tiền
 * @param bool $with_unit Có thêm "VNĐ" không
 * @return string
 */
function vie_format_currency(float $amount, bool $with_unit = true): string {
    $formatted = number_format($amount, 0, ',', '.');
    return $with_unit ? $formatted . ' VNĐ' : $formatted;
}

/**
 * Format ngày theo định dạng Việt Nam
 * 
 * @param string $date Date string (Y-m-d)
 * @return string dd/mm/yyyy
 */
function vie_format_date(string $date): string {
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '';
}
```

---

### BƯỚC 9: Copy screenshot từ backup

| # | Task | Command | Status |
|---|------|---------|--------|
| 9.1 | Copy screenshot | `cp _backup_legacy_v1_*/screenshot.png ./` | ⬜ |

---

### BƯỚC 10: Verify và commit

| # | Task | Command | Status |
|---|------|---------|--------|
| 10.1 | Kiểm tra cấu trúc | `tree -L 3 --dirsfirst` | ⬜ |
| 10.2 | Kiểm tra functions.php | `php -l functions.php` (syntax check) | ⬜ |
| 10.3 | Git add | `git add -A` | ⬜ |
| 10.4 | Git commit | `git commit -m "feat: khởi tạo cấu trúc theme v2.0"` | ⬜ |
| 10.5 | Git push | `git push origin main` | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] Tất cả thư mục đã tạo đúng cấu trúc
- [ ] File style.css có metadata v2.0
- [ ] File functions.php load được (không lỗi PHP)
- [ ] Files .htaccess đã tạo để bảo vệ thư mục nhạy cảm
- [ ] Đã commit và push lên git
- [ ] Website hiển thị (có thể blank nhưng không lỗi 500)

---

## 📝 EXPECTED STRUCTURE AFTER COMPLETION

```
/vielimousine-child/
├── _backup_legacy_v1_291124/    # Backup code cũ
├── assets/
│   ├── css/
│   │   ├── admin/
│   │   └── frontend/
│   ├── js/
│   │   ├── admin/
│   │   └── frontend/
│   ├── images/
│   │   └── icons/
│   └── vendor/
├── credentials/
│   └── .htaccess
├── data/
│   └── .htaccess
├── docs/                        # Tài liệu (đã có)
├── inc/
│   ├── admin/
│   ├── classes/
│   ├── config/
│   ├── frontend/
│   ├── helpers/
│   │   └── formatting.php
│   └── hooks/
│       └── assets.php
├── languages/
├── logs/
│   └── .htaccess
├── template-parts/
│   ├── admin/
│   │   ├── bookings/
│   │   ├── rooms/
│   │   └── settings/
│   ├── email/
│   └── frontend/
├── functions.php                # ✅ Mới tạo
├── screenshot.png
└── style.css                    # ✅ Mới tạo
```

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-03-CSS-REFACTOR.md](./TASK-03-CSS-REFACTOR.md)**
