# TASK-05: REFACTOR PHP CLASSES

**Phase:** 2 - Logic  
**Thời gian:** 2 ngày  
**Độ ưu tiên:** 🟡 HIGH  
**Prerequisite:** TASK-04 hoàn thành  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

1. Tách business logic thành các classes riêng biệt
2. Tạo helper functions
3. Tổ chức hooks/shortcodes
4. Thêm comment tiếng Việt đầy đủ

---

## 📊 MAPPING LEGACY → NEW

### Classes cần tạo mới

| Legacy Files | New Class | Chức năng |
|--------------|-----------|-----------|
| `class-bookings.php` + `class-ajax.php` | `class-booking-manager.php` | Quản lý đặt phòng |
| `class-helpers.php` (pricing logic) | `class-pricing-engine.php` | Tính giá |
| `class-admin.php` | `class-room-manager.php` | CRUD phòng |
| `class-email-manager.php` | Copy & refactor | Gửi email |
| `class-database.php` | `helpers/database.php` | DB utilities |

### Files copy trực tiếp (refactor nhẹ)

| Legacy | Target | Action |
|--------|--------|--------|
| `class-google-auth.php` | `inc/classes/` | Copy + header |
| `class-google-sheets-api.php` | `inc/classes/` | Copy + header |
| `class-cache-manager.php` | `inc/classes/` | Copy + header |
| `class-sepay-*.php` | `inc/classes/class-sepay-gateway.php` | Merge |
| `class-coupon-*.php` | `inc/classes/class-coupon-manager.php` | Merge |

---

## 📋 NGÀY 1: HELPERS & CORE CLASSES

### BƯỚC 1: Tạo Helper Functions

#### 1.1 File `inc/helpers/formatting.php`

| # | Task | Status |
|---|------|--------|
| 1.1.1 | Tạo file với header block | ⬜ |
| 1.1.2 | Migrate `format_currency()` từ legacy | ⬜ |
| 1.1.3 | Migrate `format_date()` từ legacy | ⬜ |
| 1.1.4 | Thêm các helper format khác | ⬜ |

**Template:**
```php
<?php
/**
 * ============================================================================
 * TÊN FILE: formatting.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Các hàm format dữ liệu: tiền tệ, ngày tháng, text
 * 
 * CHỨC NĂNG:
 * - vie_format_currency(): Format số tiền VNĐ
 * - vie_format_date(): Format ngày tháng
 * - vie_format_phone(): Format số điện thoại
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Helpers
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Format số tiền theo định dạng Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   float   $amount     Số tiền cần format
 * @param   bool    $with_unit  Có thêm "VNĐ" không. Default true.
 * 
 * @return  string  Số tiền đã format (VD: "1.500.000 VNĐ")
 * 
 * @example
 * vie_format_currency(1500000);       // "1.500.000 VNĐ"
 * vie_format_currency(1500000, false); // "1.500.000"
 */
function vie_format_currency(float $amount, bool $with_unit = true): string {
    $formatted = number_format($amount, 0, ',', '.');
    return $with_unit ? $formatted . ' VNĐ' : $formatted;
}

/**
 * Format ngày theo định dạng Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   string|DateTime  $date    Date string (Y-m-d) hoặc DateTime object
 * @param   string           $format  'short' (dd/mm/yyyy) | 'long' | 'iso'
 * 
 * @return  string
 */
function vie_format_date($date, string $format = 'short'): string {
    if (empty($date)) {
        return '';
    }
    
    if (is_string($date)) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '';
        }
    } elseif ($date instanceof DateTime) {
        $timestamp = $date->getTimestamp();
    } else {
        return '';
    }
    
    switch ($format) {
        case 'long':
            // Thứ Hai, 29/11/2024
            $days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
            $day_name = $days[date('w', $timestamp)];
            return $day_name . ', ' . date('d/m/Y', $timestamp);
            
        case 'iso':
            return date('Y-m-d', $timestamp);
            
        case 'short':
        default:
            return date('d/m/Y', $timestamp);
    }
}

/**
 * Format số điện thoại Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   string  $phone  Số điện thoại thô
 * 
 * @return  string  Số điện thoại đã format (VD: "0901 234 567")
 */
function vie_format_phone(string $phone): string {
    // Loại bỏ ký tự không phải số
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format: 0xxx xxx xxx
    if (strlen($phone) === 10) {
        return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7);
    }
    
    // Format: 0xxxx xxx xxx
    if (strlen($phone) === 11) {
        return substr($phone, 0, 5) . ' ' . substr($phone, 5, 3) . ' ' . substr($phone, 8);
    }
    
    return $phone;
}

/**
 * Tính số đêm giữa 2 ngày
 * 
 * @since   2.0.0
 * 
 * @param   string  $check_in   Ngày nhận phòng (Y-m-d)
 * @param   string  $check_out  Ngày trả phòng (Y-m-d)
 * 
 * @return  int     Số đêm (0 nếu invalid)
 */
function vie_calculate_nights(string $check_in, string $check_out): int {
    $date_in = strtotime($check_in);
    $date_out = strtotime($check_out);
    
    if (!$date_in || !$date_out || $date_out <= $date_in) {
        return 0;
    }
    
    return (int) floor(($date_out - $date_in) / DAY_IN_SECONDS);
}
```

#### 1.2 File `inc/helpers/security.php`

| # | Task | Status |
|---|------|--------|
| 1.2.1 | Tạo file với header block | ⬜ |
| 1.2.2 | Tạo function `vie_sanitize_booking_data()` | ⬜ |
| 1.2.3 | Tạo function `vie_validate_date()` | ⬜ |
| 1.2.4 | Tạo function `vie_validate_phone()` | ⬜ |

#### 1.3 File `inc/helpers/database.php`

| # | Task | Status |
|---|------|--------|
| 1.3.1 | Tạo file với header block | ⬜ |
| 1.3.2 | Migrate table name getters | ⬜ |
| 1.3.3 | Migrate common query functions | ⬜ |

---

### BƯỚC 2: Tạo class Pricing Engine

| # | Task | Status |
|---|------|--------|
| 2.1 | Tạo file `inc/classes/class-pricing-engine.php` | ⬜ |
| 2.2 | Migrate logic `get_pricing_for_dates()` từ `class-ajax.php` | ⬜ |
| 2.3 | Migrate logic `calculate_surcharges()` từ `class-ajax.php` | ⬜ |
| 2.4 | Thêm comment tiếng Việt đầy đủ | ⬜ |

**Tham khảo code:**
- Legacy: `_backup_legacy_v1_*/inc/hotel-rooms/frontend/class-ajax.php`
- Methods cần migrate:
  - `get_pricing_for_dates()` (line ~200)
  - `calculate_surcharges()` (line ~300)
  - `check_dates_availability()` (line ~400)

---

### BƯỚC 3: Tạo class Room Manager

| # | Task | Status |
|---|------|--------|
| 3.1 | Tạo file `inc/classes/class-room-manager.php` | ⬜ |
| 3.2 | Migrate CRUD methods từ legacy | ⬜ |
| 3.3 | Migrate `get_hotel_rooms()` từ `class-shortcode.php` | ⬜ |

---

### BƯỚC 4: Copy Core Classes (refactor nhẹ)

| # | Task | Command | Status |
|---|------|---------|--------|
| 4.1 | Copy Google Auth | Copy + thêm header tiếng Việt | ⬜ |
| 4.2 | Copy Google Sheets API | Copy + thêm header tiếng Việt | ⬜ |
| 4.3 | Copy Cache Manager | Copy + thêm header tiếng Việt | ⬜ |

---

## 📋 NGÀY 2: BOOKING MANAGER & ADMIN

### BƯỚC 5: Tạo class Booking Manager

| # | Task | Status |
|---|------|--------|
| 5.1 | Tạo file `inc/classes/class-booking-manager.php` | ⬜ |
| 5.2 | Migrate `create_booking()` logic | ⬜ |
| 5.3 | Migrate `update_booking_status()` | ⬜ |
| 5.4 | Migrate `generate_booking_code()` | ⬜ |
| 5.5 | Thêm comment tiếng Việt đầy đủ | ⬜ |

**Tham khảo:**
- Legacy `class-ajax.php`: `submit_booking()` method
- Legacy `class-bookings.php`: admin management methods

---

### BƯỚC 6: Tạo Admin Controllers

#### 6.1 Admin Rooms

| # | Task | Status |
|---|------|--------|
| 6.1.1 | Tạo `inc/admin/class-admin-rooms.php` | ⬜ |
| 6.1.2 | Migrate menu registration | ⬜ |
| 6.1.3 | Migrate AJAX handlers cho room CRUD | ⬜ |

#### 6.2 Admin Bookings

| # | Task | Status |
|---|------|--------|
| 6.2.1 | Tạo `inc/admin/class-admin-bookings.php` | ⬜ |
| 6.2.2 | Migrate danh sách booking | ⬜ |
| 6.2.3 | Migrate chi tiết booking | ⬜ |
| 6.2.4 | Migrate update status | ⬜ |

---

### BƯỚC 7: Tạo Hooks Files

#### 7.1 File `inc/hooks/ajax.php`

| # | Task | Status |
|---|------|--------|
| 7.1.1 | Tạo file với header block | ⬜ |
| 7.1.2 | Đăng ký tất cả AJAX actions | ⬜ |

**Template:**
```php
<?php
/**
 * ============================================================================
 * TÊN FILE: ajax.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Đăng ký tất cả AJAX handlers cho theme.
 * File này chỉ ĐĂNG KÝ hooks, không chứa logic xử lý.
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Hooks
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * FRONTEND AJAX (Public - không cần đăng nhập)
 * ============================================================================
 */

// Tính giá booking
add_action('wp_ajax_vie_calculate_price', 'vie_ajax_calculate_price');
add_action('wp_ajax_nopriv_vie_calculate_price', 'vie_ajax_calculate_price');

function vie_ajax_calculate_price() {
    check_ajax_referer('vie_booking_nonce', 'nonce');
    
    $pricing_engine = Vie_Pricing_Engine::get_instance();
    $result = $pricing_engine->calculate_from_request($_POST);
    
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    
    wp_send_json_success($result);
}

// Kiểm tra phòng trống
add_action('wp_ajax_vie_check_availability', 'vie_ajax_check_availability');
add_action('wp_ajax_nopriv_vie_check_availability', 'vie_ajax_check_availability');

// Submit đặt phòng
add_action('wp_ajax_vie_submit_booking', 'vie_ajax_submit_booking');
add_action('wp_ajax_nopriv_vie_submit_booking', 'vie_ajax_submit_booking');

// ... thêm các handlers khác

/**
 * ============================================================================
 * ADMIN AJAX (Yêu cầu đăng nhập + capability)
 * ============================================================================
 */

// Cập nhật trạng thái booking
add_action('wp_ajax_vie_update_booking_status', 'vie_ajax_update_booking_status');

function vie_ajax_update_booking_status() {
    check_ajax_referer('vie_admin_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized'], 403);
    }
    
    $booking_manager = Vie_Booking_Manager::get_instance();
    // ... xử lý
}

// ... thêm các admin handlers khác
```

#### 7.2 File `inc/hooks/admin-menu.php`

| # | Task | Status |
|---|------|--------|
| 7.2.1 | Tạo file với header block | ⬜ |
| 7.2.2 | Đăng ký admin menus | ⬜ |

#### 7.3 File `inc/hooks/shortcodes.php`

| # | Task | Status |
|---|------|--------|
| 7.3.1 | Tạo file với header block | ⬜ |
| 7.3.2 | Đăng ký shortcode `[hotel_room_list]` | ⬜ |

---

### BƯỚC 8: Tạo Frontend Controllers

| # | Task | Status |
|---|------|--------|
| 8.1 | Tạo `inc/frontend/class-shortcode-rooms.php` | ⬜ |
| 8.2 | Migrate render logic từ legacy | ⬜ |
| 8.3 | Tạo `inc/frontend/class-ajax-handlers.php` | ⬜ |

---

### BƯỚC 9: Cập nhật functions.php

| # | Task | Status |
|---|------|--------|
| 9.1 | Uncomment các require đã có | ⬜ |
| 9.2 | Verify thứ tự require đúng | ⬜ |
| 9.3 | Test không có fatal error | ⬜ |

---

### BƯỚC 10: Testing & Commit

| # | Test Case | Status |
|---|-----------|--------|
| 10.1 | Website không lỗi trắng trang | ⬜ |
| 10.2 | Admin menu hiển thị | ⬜ |
| 10.3 | AJAX calculate price hoạt động | ⬜ |
| 10.4 | Shortcode render rooms | ⬜ |

| # | Task | Command | Status |
|---|------|---------|--------|
| 10.5 | Git add | `git add inc/` | ⬜ |
| 10.6 | Git commit | `git commit -m "feat: refactor PHP classes với comment tiếng Việt"` | ⬜ |
| 10.7 | Git push | `git push origin main` | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] Helper functions đã tạo trong `inc/helpers/`
- [ ] Core classes đã tạo trong `inc/classes/`
- [ ] Admin controllers đã tạo trong `inc/admin/`
- [ ] Frontend controllers đã tạo trong `inc/frontend/`
- [ ] Hooks đã đăng ký trong `inc/hooks/`
- [ ] Tất cả files có header block tiếng Việt
- [ ] Tất cả functions/methods có docblock
- [ ] Website hoạt động không lỗi
- [ ] AJAX endpoints hoạt động
- [ ] Đã commit và push

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-06-TEMPLATES.md](./TASK-06-TEMPLATES.md)**
