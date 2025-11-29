# Changelog

Tất cả thay đổi đáng chú ý của dự án sẽ được ghi lại trong file này.

Định dạng dựa trên [Keep a Changelog](https://keepachangelog.com/vi/1.0.0/)

---

## [2.0.0] - 2024-11-29

### 🏗️ Kiến trúc mới
- Tái cấu trúc toàn bộ theme theo chuẩn MVC-like
- Tách riêng assets, logic, templates
- Áp dụng BEM naming convention cho CSS
- Áp dụng Module Pattern cho JavaScript
- Comment 100% tiếng Việt

### ✨ Thêm mới
- **CSS Variables** (`_variables.css`) - Single Source of Truth
- **JS Core** (`core.js`) - Global namespace và utilities
- **Helper Functions:**
  - `formatting.php` - Format tiền, ngày
  - `security.php` - Sanitize, validate
  - `database.php` - Database queries
  - `templates.php` - Template loading
- **Hooks:**
  - `assets.php` - Conditional asset loading
  - `ajax.php` - AJAX handlers
  - `shortcodes.php` - Shortcode registry
- **Templates:**
  - `booking-filters.php`
  - `room-card.php`
  - `room-detail-modal.php`
  - `booking-popup.php`
- **Documentation:**
  - Technical Design Document
  - 6 Rule documents
  - 8 Task documents
  - Test Results

### 🔄 Thay đổi
- CSS tách thành modules: main, room-listing, booking-popup, datepicker
- JS tách thành modules riêng
- Thư mục `inc/hotel-rooms/` đổi thành cấu trúc phẳng `inc/`
- Assets di chuyển từ `inc/hotel-rooms/assets/` ra `assets/`

### 🔒 Bảo mật
- Thêm `.htaccess` bảo vệ `/logs/`, `/data/`, `/credentials/`
- Tách sensitive data ra khỏi code
- Sử dụng nonce verification cho tất cả AJAX calls
- Input sanitization/validation functions

### 🗃️ Legacy
- Code cũ được backup vào `_backup_legacy_v1_291124/`
- Có thể rollback bằng cách restore từ backup

---

## [1.x.x] - Legacy

Xem trong thư mục `_backup_legacy_v1_291124/`
