# RULE-01: QUY CHUẨN FILE HEADER

**Phiên bản:** 1.0  
**Áp dụng cho:** Tất cả file PHP, CSS, JS trong theme  
**Bắt buộc:** ✅ CÓ

---

## 1. MỤC ĐÍCH

- Giúp developer mới nhanh chóng hiểu file làm gì
- Dễ dàng tìm kiếm file theo chức năng
- Tracking version và author
- Hỗ trợ IDE generate documentation

---

## 2. TEMPLATE PHP FILE HEADER

```php
<?php
/**
 * ============================================================================
 * TÊN FILE: [tên-file.php]
 * ============================================================================
 * 
 * MÔ TẢ:
 * [Mô tả ngắn gọn file này làm gì, 1-2 câu]
 * 
 * CHỨC NĂNG CHÍNH:
 * - [Chức năng 1]
 * - [Chức năng 2]
 * - [Chức năng 3]
 * 
 * SỬ DỤNG:
 * [Code example nếu cần]
 * 
 * DEPENDENCIES:
 * - [File hoặc class phụ thuộc 1]
 * - [File hoặc class phụ thuộc 2]
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  [Subpackage: Classes|Hooks|Helpers|Admin|Frontend]
 * @version     [X.Y.Z]
 * @since       [Version đầu tiên tạo file]
 * @author      Vie Development Team
 * @link        https://vielimousine.com
 * ============================================================================
 */

defined('ABSPATH') || exit;
```

---

## 3. TEMPLATE CSS FILE HEADER

```css
/**
 * ============================================================================
 * FILE: [tên-file.css]
 * ============================================================================
 * 
 * MÔ TẢ:
 * [Mô tả file CSS này style cho component/page nào]
 * 
 * MỤC LỤC:
 * 1. [Section 1]
 * 2. [Section 2]
 * 3. [Section 3]
 * 4. Responsive
 * 
 * DEPENDENCIES:
 * - _variables.css (REQUIRED)
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */
```

---

## 4. TEMPLATE JS FILE HEADER

```javascript
/**
 * ============================================================================
 * FILE: [tên-file.js]
 * ============================================================================
 * 
 * MÔ TẢ:
 * [Mô tả module JavaScript này xử lý gì]
 * 
 * EXPORTS:
 * - [Object/Function exported 1]
 * - [Object/Function exported 2]
 * 
 * DEPENDENCIES:
 * - jQuery (WordPress Core)
 * - [Dependency 2]
 * - vieBooking (Localized data từ PHP)
 * 
 * EVENTS TRIGGERED:
 * - vie:booking:priceCalculated
 * - vie:booking:submitted
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */
```

---

## 5. VÍ DỤ THỰC TẾ

### 5.1 PHP Class File

```php
<?php
/**
 * ============================================================================
 * TÊN FILE: class-pricing-engine.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Engine tính giá booking theo từng ngày, bao gồm giá cơ bản và phụ thu.
 * Hỗ trợ 2 loại giá: Room Only và Combo (có xe đưa đón).
 * 
 * CHỨC NĂNG CHÍNH:
 * - Lấy giá theo ngày từ bảng pricing
 * - Tính phụ thu người lớn/trẻ em
 * - Tính tổng giá cho khoảng thời gian
 * - Cache kết quả để tối ưu performance
 * 
 * SỬ DỤNG:
 * $engine = Vie_Pricing_Engine::get_instance();
 * $total = $engine->calculate_for_dates($room_id, $date_in, $date_out);
 * 
 * DEPENDENCIES:
 * - inc/helpers/database.php
 * - inc/helpers/formatting.php
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Classes
 * @version     2.0.0
 * @since       2.0.0
 * @author      Vie Development Team
 * @link        https://vielimousine.com
 * ============================================================================
 */

defined('ABSPATH') || exit;

class Vie_Pricing_Engine {
    // ... class code
}
```

### 5.2 CSS Component File

```css
/**
 * ============================================================================
 * FILE: booking-popup.css
 * ============================================================================
 * 
 * MÔ TẢ:
 * Styles cho popup đặt phòng hiển thị trên trang hotel.
 * Bao gồm: Modal container, form steps, price summary, buttons.
 * 
 * MỤC LỤC:
 * 1. Modal Overlay & Container
 * 2. Header & Close Button
 * 3. Step Indicator (1-2-3)
 * 4. Form Elements
 * 5. Price Summary Box
 * 6. Action Buttons
 * 7. Success State
 * 8. Responsive (Tablet & Mobile)
 * 
 * DEPENDENCIES:
 * - _variables.css (REQUIRED - colors, spacing, shadows)
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

@import './_variables.css';

/* ==========================================================================
   1. MODAL OVERLAY & CONTAINER
   ========================================================================== */
```

### 5.3 JS Module File

```javascript
/**
 * ============================================================================
 * FILE: booking-popup.js
 * ============================================================================
 * 
 * MÔ TẢ:
 * Module xử lý toàn bộ logic popup đặt phòng: mở/đóng, form validation,
 * tính giá real-time, submit booking.
 * 
 * EXPORTS:
 * - VieBookingPopup (global object)
 * 
 * DEPENDENCIES:
 * - jQuery (WordPress Core)
 * - jQuery UI Datepicker
 * - core.js (vie.utils)
 * - datepicker.js (vie.datepicker)
 * - vieBooking (Localized data: ajaxUrl, nonce, i18n)
 * 
 * EVENTS TRIGGERED:
 * - vie:popup:opened (data: { roomId, roomName })
 * - vie:popup:closed
 * - vie:price:calculated (data: { total, breakdown })
 * - vie:booking:submitted (data: { bookingCode, checkoutUrl })
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

(function($) {
    'use strict';
    // ... module code
})(jQuery);
```

---

## 6. CHECKLIST REVIEW

Khi review code, kiểm tra:

- [ ] File có header block không?
- [ ] Mô tả có đúng chức năng file không?
- [ ] Version có được update khi thay đổi lớn không?
- [ ] Dependencies có được liệt kê đầy đủ không?
- [ ] `defined('ABSPATH') || exit;` có ở đầu file PHP không?

---

## 7. LƯU Ý

1. **KHÔNG viết header cho file quá nhỏ** (<20 dòng) như file chỉ chứa constants
2. **CẬP NHẬT version** khi có thay đổi breaking changes
3. **SỬ DỤNG tiếng Việt** cho tất cả mô tả
4. **KHÔNG copy-paste** header từ file khác mà không sửa
