# TASK-10: MIGRATE HOTEL ROOMS ADMIN

**Phase:** 4 - Business Logic Migration  
**Thời gian:** 1.5 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-09 hoàn thành  

---

## 🎯 MỤC TIÊU

Di chuyển các admin classes quản lý phòng khách sạn:
- Admin dashboard & menus
- Room management (CRUD)
- Booking management
- Calendar & Pricing
- Settings page
- SePay Admin integration
- Transport metabox

---

## 📋 CHECKLIST

### PHẦN 1: Admin Classes

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 1.1 | `inc/hotel-rooms/admin/class-admin.php` | `inc/admin/class-admin-rooms.php` | ⬜ |
| 1.2 | `inc/hotel-rooms/admin/class-ajax-handlers.php` | `inc/admin/class-admin-ajax.php` | ⬜ |
| 1.3 | `inc/hotel-rooms/admin/class-bookings.php` | `inc/admin/class-admin-bookings.php` | ⬜ |
| 1.4 | `inc/hotel-rooms/admin/class-settings.php` | `inc/admin/class-admin-settings.php` | ⬜ |
| 1.5 | `inc/hotel-rooms/admin/class-sepay-admin.php` | `inc/admin/class-admin-sepay.php` | ⬜ |
| 1.6 | `inc/hotel-rooms/admin/class-transport-metabox.php` | `inc/admin/class-admin-transport.php` | ⬜ |

### PHẦN 2: Admin Views (Templates)

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 2.1 | `admin/views/rooms-list.php` | `template-parts/admin/rooms/list.php` | ⬜ |
| 2.2 | `admin/views/room-form.php` | `template-parts/admin/rooms/form.php` | ⬜ |
| 2.3 | `admin/views/calendar.php` | `template-parts/admin/rooms/calendar.php` | ⬜ |
| 2.4 | `admin/views/price-matrix.php` | `template-parts/admin/rooms/price-matrix.php` | ⬜ |

---

## 📝 HƯỚNG DẪN CHI TIẾT

### Bước 1: Tạo thư mục admin

```bash
mkdir -p inc/admin
mkdir -p template-parts/admin/rooms
```

### Bước 2: Copy admin classes

```bash
# Copy và rename theo convention mới
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/class-admin.php inc/admin/class-admin-rooms.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/class-ajax-handlers.php inc/admin/class-admin-ajax.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/class-bookings.php inc/admin/class-admin-bookings.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/class-settings.php inc/admin/class-admin-settings.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/class-sepay-admin.php inc/admin/class-admin-sepay.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/class-transport-metabox.php inc/admin/class-admin-transport.php
```

### Bước 3: Copy admin views

```bash
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/views/rooms-list.php template-parts/admin/rooms/list.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/views/room-form.php template-parts/admin/rooms/form.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/views/calendar.php template-parts/admin/rooms/calendar.php
cp _backup_legacy_v1_291124/inc/hotel-rooms/admin/views/price-matrix.php template-parts/admin/rooms/price-matrix.php
```

### Bước 4: Refactor các class

**Cần sửa trong mỗi file:**

1. **File header:** Thêm comment tiếng Việt theo RULE-01
2. **Paths:** Cập nhật đường dẫn require_once
3. **Template paths:** Đổi từ `views/` sang `template-parts/admin/`
4. **Constants:** Sử dụng VIE_THEME_PATH thay vì VIE_HOTEL_ROOMS_PATH

**Ví dụ refactor path:**
```php
// OLD
require_once VIE_HOTEL_ROOMS_PATH . '/admin/views/rooms-list.php';

// NEW
vie_get_admin_template('rooms/list', $args);
```

---

## 🔧 CẬP NHẬT functions.php

Thêm vào PHẦN 6 của functions.php:

```php
/**
 * ============================================================================
 * PHẦN 6: LOAD ADMIN CONTROLLERS (Chỉ trong admin)
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
```

---

## ✅ DEFINITION OF DONE

- [ ] Tất cả admin classes đã copy và refactor
- [ ] Tất cả admin views đã di chuyển vào template-parts
- [ ] Paths đã cập nhật
- [ ] functions.php đã cập nhật để load admin classes
- [ ] Admin menu hiển thị trong WP Admin
- [ ] Không có PHP errors
- [ ] Git commit

---

## ⏭️ TASK TIẾP THEO

[TASK-11-HOTEL-ROOMS-FRONTEND.md](./TASK-11-HOTEL-ROOMS-FRONTEND.md)
