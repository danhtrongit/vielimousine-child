# TASK-11: MIGRATE HOTEL ROOMS FRONTEND

**Phase:** 4 - Business Logic Migration  
**Thời gian:** 1 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-10 hoàn thành  

---

## 🎯 MỤC TIÊU

Di chuyển các frontend classes:
- Shortcode hiển thị danh sách phòng
- AJAX handlers cho booking
- SePay payment frontend
- Payment info views

---

## 📋 CHECKLIST

### PHẦN 1: Frontend Classes

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 1.1 | `frontend/class-shortcode.php` | `inc/frontend/class-shortcode-rooms.php` | ⬜ |
| 1.2 | `frontend/class-ajax.php` | `inc/frontend/class-ajax-handlers.php` | ⬜ |
| 1.3 | `frontend/class-sepay-frontend.php` | `inc/frontend/class-sepay-frontend.php` | ⬜ |

### PHẦN 2: Frontend Views

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 2.1 | `frontend/views/payment-info.php` | `template-parts/frontend/payment-info.php` | ⬜ |

### PHẦN 3: Includes (Business Logic)

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 3.1 | `includes/class-database.php` | `inc/classes/class-database.php` | ⬜ |
| 3.2 | `includes/class-helpers.php` | `inc/classes/class-helpers.php` | ⬜ |
| 3.3 | `includes/class-email-manager.php` | `inc/classes/class-email-manager.php` | ⬜ |
| 3.4 | `includes/class-sepay-helper.php` | `inc/classes/class-sepay-helper.php` | ⬜ |
| 3.5 | `includes/class-sepay-webhook.php` | `inc/classes/class-sepay-webhook.php` | ⬜ |

---

## 📝 HƯỚNG DẪN CHI TIẾT

### Bước 1: Tạo thư mục

```bash
mkdir -p inc/frontend
```

### Bước 2: Copy frontend classes

```bash
cp _backup_legacy_v1_291124/inc/hotel-rooms/frontend/class-shortcode.php inc/frontend/class-shortcode-rooms.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/frontend/class-ajax.php inc/frontend/class-ajax-handlers.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/frontend/class-sepay-frontend.php inc/frontend/class-sepay-frontend.php
```

### Bước 3: Copy frontend views

```bash
cp _backup_legacy_v1_291124/inc/hotel-rooms/frontend/views/payment-info.php template-parts/frontend/
```

### Bước 4: Copy includes (business logic classes)

```bash
cp _backup_legacy_v1_291124/inc/hotel-rooms/includes/class-database.php inc/classes/
cp _backup_legacy_v1_291124/inc/hotel-rooms/includes/class-helpers.php inc/classes/
cp _backup_legacy_v1_291124/inc/hotel-rooms/includes/class-email-manager.php inc/classes/
cp _backup_legacy_v1_291124/inc/hotel-rooms/includes/class-sepay-helper.php inc/classes/
cp _backup_legacy_v1_291124/inc/hotel-rooms/includes/class-sepay-webhook.php inc/classes/
```

### Bước 5: Refactor shortcode class

**File: `inc/frontend/class-shortcode-rooms.php`**

Cần sửa:
1. Asset paths: Đổi sang `assets/css/frontend/` và `assets/js/frontend/`
2. Template paths: Dùng `vie_get_template()` thay vì inline HTML
3. File header: Thêm comment tiếng Việt

**Ví dụ refactor enqueue:**
```php
// OLD
wp_enqueue_style('vie-hotel-frontend', 
    VIE_HOTEL_ROOMS_URL . '/assets/css/frontend.css');

// NEW
wp_enqueue_style('vie-room-listing', 
    VIE_THEME_URL . '/assets/css/frontend/room-listing.css',
    ['vie-variables'],
    VIE_THEME_VERSION);
```

### Bước 6: Refactor AJAX handlers

**File: `inc/frontend/class-ajax-handlers.php`**

- Cập nhật để sử dụng helper functions từ `inc/helpers/`
- Sử dụng `vie_sanitize_booking_data()` từ security.php
- Sử dụng `vie_format_currency()` từ formatting.php

---

## 🔧 CẬP NHẬT functions.php

Thêm vào PHẦN 7:

```php
/**
 * ============================================================================
 * PHẦN 7: LOAD FRONTEND CONTROLLERS
 * ============================================================================
 */

// Business logic classes
require_once VIE_THEME_PATH . '/inc/classes/class-database.php';
require_once VIE_THEME_PATH . '/inc/classes/class-helpers.php';
require_once VIE_THEME_PATH . '/inc/classes/class-email-manager.php';
require_once VIE_THEME_PATH . '/inc/classes/class-sepay-helper.php';
require_once VIE_THEME_PATH . '/inc/classes/class-sepay-webhook.php';

// Frontend controllers
require_once VIE_THEME_PATH . '/inc/frontend/class-shortcode-rooms.php';
require_once VIE_THEME_PATH . '/inc/frontend/class-ajax-handlers.php';
require_once VIE_THEME_PATH . '/inc/frontend/class-sepay-frontend.php';
```

---

## ✅ DEFINITION OF DONE

- [ ] Tất cả frontend classes đã copy và refactor
- [ ] Includes classes đã copy
- [ ] Paths trong classes đã cập nhật
- [ ] Shortcode [hotel_room_list] hoạt động
- [ ] AJAX booking hoạt động
- [ ] Không có PHP errors
- [ ] Git commit

---

## ⏭️ TASK TIẾP THEO

[TASK-12-COUPONS-MODULE.md](./TASK-12-COUPONS-MODULE.md)
