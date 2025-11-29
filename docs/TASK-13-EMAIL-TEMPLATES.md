# TASK-13: MIGRATE EMAIL TEMPLATES

**Phase:** 4 - Business Logic Migration  
**Thời gian:** 0.5 ngày  
**Độ ưu tiên:** 🟡 MEDIUM  
**Prerequisite:** TASK-12 hoàn thành  

---

## 🎯 MỤC TIÊU

Di chuyển email templates:
- Booking confirmation email
- Email styling

---

## 📋 CHECKLIST

### PHẦN 1: Email Templates

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 1.1 | `templates/email-booking-confirmation.php` | `template-parts/email/booking-confirmation.php` | ⬜ |

---

## 📝 HƯỚNG DẪN CHI TIẾT

### Bước 1: Copy email templates

```bash
cp _backup_legacy_v1_291124/inc/hotel-rooms/templates/email-booking-confirmation.php template-parts/email/booking-confirmation.php
```

### Bước 2: Refactor email template

**Cần sửa:**
1. Sử dụng CSS variables cho màu sắc
2. Sử dụng `vie_format_currency()` cho format tiền
3. Sử dụng `vie_format_date()` cho format ngày
4. Thêm comment tiếng Việt

### Bước 3: Cập nhật Email Manager

Trong `inc/classes/class-email-manager.php`, cập nhật đường dẫn template:

```php
// OLD
$template_path = VIE_HOTEL_ROOMS_PATH . '/templates/email-booking-confirmation.php';

// NEW
$template_path = VIE_THEME_PATH . '/template-parts/email/booking-confirmation.php';

// Hoặc dùng helper
$email_body = vie_get_email_template('booking-confirmation', [
    'booking' => $booking,
    'room' => $room
]);
```

---

## ✅ DEFINITION OF DONE

- [ ] Email template đã copy
- [ ] Template đã refactor dùng helper functions
- [ ] Email Manager đã cập nhật paths
- [ ] Test gửi email thành công
- [ ] Git commit

---

## ⏭️ TASK TIẾP THEO

[TASK-14-MAIN-BOOTSTRAP.md](./TASK-14-MAIN-BOOTSTRAP.md)
