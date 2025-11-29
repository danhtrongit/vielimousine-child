# TASK-12: MIGRATE COUPONS MODULE

**Phase:** 4 - Business Logic Migration  
**Thời gian:** 0.5 ngày  
**Độ ưu tiên:** 🟡 MEDIUM  
**Prerequisite:** TASK-11 hoàn thành  

---

## 🎯 MỤC TIÊU

Di chuyển module quản lý mã giảm giá (coupons):
- Coupon validation
- AJAX handlers cho coupon
- Hooks integration

---

## 📋 CHECKLIST

### PHẦN 1: Coupon Classes

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 1.1 | `inc/modules/coupons/class-coupon-validator.php` | `inc/classes/class-coupon-validator.php` | ⬜ |
| 1.2 | `inc/modules/coupons/class-coupon-ajax.php` | `inc/classes/class-coupon-ajax.php` | ⬜ |
| 1.3 | `inc/modules/coupons/hooks.php` | `inc/hooks/coupons.php` | ⬜ |

### PHẦN 2: Coupon Assets

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 2.1 | `coupons/assets/coupon-form.css` | `assets/css/frontend/coupon.css` | ⬜ |
| 2.2 | `coupons/assets/coupon-form.js` | `assets/js/frontend/coupon.js` | ⬜ |

---

## 📝 HƯỚNG DẪN CHI TIẾT

### Bước 1: Copy coupon classes

```bash
cp _backup_legacy_v1_291124/inc/modules/coupons/class-coupon-validator.php inc/classes/
cp _backup_legacy_v1_291124/inc/modules/coupons/class-coupon-ajax.php inc/classes/
cp _backup_legacy_v1_291124/inc/modules/coupons/hooks.php inc/hooks/coupons.php
```

### Bước 2: Copy coupon assets

```bash
cp _backup_legacy_v1_291124/inc/modules/coupons/assets/coupon-form.css assets/css/frontend/coupon.css
cp _backup_legacy_v1_291124/inc/modules/coupons/assets/coupon-form.js assets/js/frontend/coupon.js
```

### Bước 3: Refactor để dùng helper functions

**class-coupon-validator.php:**
- Sử dụng `vie_sanitize_*` functions từ security.php
- Sử dụng database helpers từ database.php

### Bước 4: Cập nhật functions.php

Thêm vào cuối:
```php
// Coupon module
require_once VIE_THEME_PATH . '/inc/classes/class-coupon-validator.php';
require_once VIE_THEME_PATH . '/inc/classes/class-coupon-ajax.php';
require_once VIE_THEME_PATH . '/inc/hooks/coupons.php';
```

### Bước 5: Cập nhật assets.php

Thêm load coupon CSS/JS trong checkout page:
```php
if (is_page('checkout')) {
    // ... existing code ...
    
    // Coupon assets
    wp_enqueue_style('vie-coupon', $css_url . 'coupon.css', ['vie-variables'], $version);
    wp_enqueue_script('vie-coupon', $js_url . 'coupon.js', ['vie-core'], $version, true);
}
```

---

## ✅ DEFINITION OF DONE

- [ ] Coupon classes đã copy và refactor
- [ ] Coupon assets đã copy
- [ ] functions.php đã cập nhật
- [ ] assets.php đã cập nhật
- [ ] Mã giảm giá hoạt động trên checkout
- [ ] Git commit

---

## ⏭️ TASK TIẾP THEO

[TASK-13-EMAIL-TEMPLATES.md](./TASK-13-EMAIL-TEMPLATES.md)
