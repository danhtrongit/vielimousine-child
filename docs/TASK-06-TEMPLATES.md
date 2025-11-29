# TASK-06: REFACTOR TEMPLATES

**Phase:** 2 - Logic  
**Thời gian:** 1 ngày  
**Độ ưu tiên:** 🟡 HIGH  
**Prerequisite:** TASK-05 hoàn thành  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

1. Tách HTML từ các class PHP vào template-parts
2. Refactor page-checkout.php (30KB)
3. Tổ chức templates theo chuẩn WordPress

---

## 📊 MAPPING LEGACY → NEW

| Legacy Location | New Location |
|-----------------|--------------|
| `class-shortcode.php` (inline HTML) | `template-parts/frontend/room-card.php` |
| `class-shortcode.php` (modals) | `template-parts/frontend/room-detail-modal.php` |
| `class-shortcode.php` (popup) | `template-parts/frontend/booking-popup.php` |
| `page-checkout.php` | `template-parts/frontend/checkout-form.php` |
| `admin/views/*.php` | `template-parts/admin/*` |
| `templates/email-*.php` | `template-parts/email/*.php` |

---

## 📋 CHECKLIST CHI TIẾT

### BƯỚC 1: Tạo Frontend Templates

#### 1.1 Room Card Template

| # | Task | Status |
|---|------|--------|
| 1.1.1 | Tạo file `template-parts/frontend/room-card.php` | ⬜ |
| 1.1.2 | Extract HTML từ `class-shortcode.php` | ⬜ |
| 1.1.3 | Thêm header block + inline comments | ⬜ |

**Template: `template-parts/frontend/room-card.php`**
```php
<?php
/**
 * ============================================================================
 * TEMPLATE: Room Card
 * ============================================================================
 * 
 * MÔ TẢ:
 * Hiển thị 1 card phòng trong grid danh sách phòng
 * 
 * BIẾN TRUYỀN VÀO:
 * @var object $room           Dữ liệu phòng từ database
 * @var int    $hotel_id       ID của khách sạn
 * @var array  $price_range    [min_price, max_price] của phòng
 * 
 * SỬ DỤNG:
 * vie_get_template('frontend/room-card', [
 *     'room' => $room,
 *     'hotel_id' => $hotel_id,
 *     'price_range' => $price_range
 * ]);
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

// Validate required variables
if (empty($room)) {
    return;
}

// Parse gallery images
$gallery_ids = !empty($room->gallery_ids) ? json_decode($room->gallery_ids, true) : [];
$has_gallery = is_array($gallery_ids) && count($gallery_ids) > 0;

// Format price
$min_price = $price_range['min'] ?? $room->base_price;
$formatted_price = vie_format_currency($min_price);

// Room status
$is_active = ($room->status === 'active');
$status_class = $is_active ? '' : 'vie-room-card--inactive';
?>

<div class="vie-room-card <?php echo esc_attr($status_class); ?>" 
     data-room-id="<?php echo esc_attr($room->id); ?>"
     data-room-name="<?php echo esc_attr($room->name); ?>">
    
    <!-- Ảnh phòng -->
    <div class="vie-room-card__image">
        <?php if ($has_gallery): ?>
            <div class="vie-room-card__swiper swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($gallery_ids as $image_id): 
                        $image_url = wp_get_attachment_image_url($image_id, 'medium_large');
                        if (!$image_url) continue;
                    ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url($image_url); ?>" 
                                 alt="<?php echo esc_attr($room->name); ?>"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        <?php else: ?>
            <img src="<?php echo esc_url(VIE_THEME_URL . '/assets/images/room-placeholder.jpg'); ?>" 
                 alt="<?php echo esc_attr($room->name); ?>">
        <?php endif; ?>
        
        <?php if (!$is_active): ?>
            <div class="vie-room-card__badge vie-room-card__badge--inactive">
                <?php esc_html_e('Tạm ngừng', 'viechild'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Nội dung -->
    <div class="vie-room-card__content">
        <h3 class="vie-room-card__title">
            <?php echo esc_html($room->name); ?>
        </h3>
        
        <!-- Thông tin phòng -->
        <div class="vie-room-card__meta">
            <?php if (!empty($room->max_adults)): ?>
                <span class="vie-room-card__meta-item">
                    <svg class="vie-icon" width="16" height="16">
                        <use href="#icon-user"></use>
                    </svg>
                    <?php echo esc_html($room->max_adults); ?> người
                </span>
            <?php endif; ?>
            
            <?php if (!empty($room->area)): ?>
                <span class="vie-room-card__meta-item">
                    <svg class="vie-icon" width="16" height="16">
                        <use href="#icon-area"></use>
                    </svg>
                    <?php echo esc_html($room->area); ?>m²
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Giá -->
        <div class="vie-room-card__price">
            <span class="vie-room-card__price-label">
                <?php esc_html_e('Giá từ', 'viechild'); ?>
            </span>
            <span class="vie-room-card__price-value">
                <?php echo esc_html($formatted_price); ?>
            </span>
            <span class="vie-room-card__price-unit">
                /<?php esc_html_e('đêm', 'viechild'); ?>
            </span>
        </div>
        
        <!-- Buttons -->
        <div class="vie-room-card__actions">
            <button type="button" 
                    class="vie-btn vie-btn--outline vie-btn-detail js-open-room-detail"
                    data-room-id="<?php echo esc_attr($room->id); ?>">
                <?php esc_html_e('Xem chi tiết', 'viechild'); ?>
            </button>
            
            <?php if ($is_active): ?>
                <button type="button" 
                        class="vie-btn vie-btn--primary vie-btn-book js-open-booking"
                        data-room-id="<?php echo esc_attr($room->id); ?>"
                        data-room-name="<?php echo esc_attr($room->name); ?>"
                        data-base-price="<?php echo esc_attr($room->base_price); ?>">
                    <?php esc_html_e('Đặt ngay', 'viechild'); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>
```

#### 1.2 Booking Popup Template

| # | Task | Status |
|---|------|--------|
| 1.2.1 | Tạo file `template-parts/frontend/booking-popup.php` | ⬜ |
| 1.2.2 | Extract HTML từ `class-shortcode.php` | ⬜ |
| 1.2.3 | Tách thành các step partials | ⬜ |

---

### BƯỚC 2: Refactor page-checkout.php

| # | Task | Status |
|---|------|--------|
| 2.1 | Analyze file legacy (894 dòng) | ⬜ |
| 2.2 | Extract business logic vào class | ⬜ |
| 2.3 | Tạo `template-parts/frontend/checkout-form.php` | ⬜ |
| 2.4 | Tạo `template-parts/frontend/checkout-summary.php` | ⬜ |
| 2.5 | Tạo file page template mới nhẹ nhàng | ⬜ |

**File mới `page-checkout.php` (slim version):**
```php
<?php
/**
 * ============================================================================
 * Template Name: Page Checkout
 * Template Post Type: page
 * ============================================================================
 * 
 * MÔ TẢ:
 * Trang thanh toán đặt phòng
 * 
 * SECURITY:
 * - Sử dụng booking_hash thay vì ID (chống IDOR)
 * - Verify nonce cho form submission
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

// Lấy và validate booking
$booking_hash = sanitize_text_field($_GET['code'] ?? '');
$checkout = new Vie_Checkout_Handler($booking_hash);

// Redirect nếu booking không hợp lệ
if (!$checkout->is_valid()) {
    wp_redirect(home_url('/'));
    exit;
}

// Lấy dữ liệu đã chuẩn bị
$booking = $checkout->get_booking();
$room = $checkout->get_room();
$hotel = $checkout->get_hotel();
$pricing = $checkout->get_pricing_breakdown();

get_header();
?>

<div class="vie-checkout-page">
    <div class="vie-container">
        <div class="vie-checkout-wrapper">
            
            <!-- Form thanh toán (bên trái) -->
            <main class="vie-checkout-main">
                <?php 
                vie_get_template('frontend/checkout-form', [
                    'booking' => $booking,
                ]);
                ?>
            </main>
            
            <!-- Tóm tắt đơn hàng (bên phải) -->
            <aside class="vie-checkout-sidebar">
                <?php 
                vie_get_template('frontend/checkout-summary', [
                    'booking' => $booking,
                    'room' => $room,
                    'hotel' => $hotel,
                    'pricing' => $pricing,
                ]);
                ?>
            </aside>
            
        </div>
    </div>
</div>

<?php get_footer(); ?>
```

---

### BƯỚC 3: Tạo Template Helper Function

| # | Task | Status |
|---|------|--------|
| 3.1 | Thêm function `vie_get_template()` vào helpers | ⬜ |

**Thêm vào `inc/helpers/templates.php`:**
```php
<?php
/**
 * ============================================================================
 * TÊN FILE: templates.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Helper functions cho việc load templates
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Load template part với biến truyền vào
 * 
 * @since   2.0.0
 * 
 * @param   string  $template_name  Tên template (không có .php)
 * @param   array   $args           Biến truyền vào template
 * @param   bool    $echo           Echo hay return
 * 
 * @return  string|void
 * 
 * @example
 * vie_get_template('frontend/room-card', ['room' => $room]);
 */
function vie_get_template(string $template_name, array $args = [], bool $echo = true) {
    $template_path = VIE_THEME_PATH . '/template-parts/' . $template_name . '.php';
    
    if (!file_exists($template_path)) {
        if (VIE_DEBUG) {
            error_log("[VIE Template] File not found: {$template_path}");
        }
        return '';
    }
    
    // Extract biến để dùng trong template
    extract($args);
    
    if ($echo) {
        include $template_path;
    } else {
        ob_start();
        include $template_path;
        return ob_get_clean();
    }
}

/**
 * Load admin template
 * 
 * @since   2.0.0
 */
function vie_get_admin_template(string $template_name, array $args = []) {
    vie_get_template('admin/' . $template_name, $args);
}

/**
 * Load email template
 * 
 * @since   2.0.0
 * 
 * @return  string  HTML content của email
 */
function vie_get_email_template(string $template_name, array $args = []): string {
    return vie_get_template('email/' . $template_name, $args, false);
}
```

---

### BƯỚC 4: Migrate Admin Templates

| # | Task | Status |
|---|------|--------|
| 4.1 | Copy `admin/views/rooms-list.php` | ⬜ |
| 4.2 | Copy `admin/views/room-form.php` | ⬜ |
| 4.3 | Copy `admin/views/calendar.php` | ⬜ |
| 4.4 | Copy `admin/views/price-matrix.php` | ⬜ |
| 4.5 | Thêm header blocks cho mỗi file | ⬜ |

**Target structure:**
```
template-parts/admin/
├── rooms/
│   ├── list.php
│   ├── form.php
│   └── calendar.php
├── bookings/
│   ├── list.php
│   └── detail.php
└── settings/
    └── general.php
```

---

### BƯỚC 5: Migrate Email Templates

| # | Task | Status |
|---|------|--------|
| 5.1 | Copy `templates/email-booking-confirmation.php` | ⬜ |
| 5.2 | Refactor để dùng CSS inline chuẩn | ⬜ |
| 5.3 | Tạo template payment-success.php | ⬜ |

---

### BƯỚC 6: Testing

| # | Test Case | Status |
|---|-----------|--------|
| 6.1 | Room cards render đúng | ⬜ |
| 6.2 | Booking popup hiển thị | ⬜ |
| 6.3 | Checkout page load đúng | ⬜ |
| 6.4 | Admin pages render | ⬜ |
| 6.5 | Email gửi đúng format | ⬜ |

---

### BƯỚC 7: Commit

| # | Task | Command | Status |
|---|------|---------|--------|
| 7.1 | Git add | `git add template-parts/ page-checkout.php` | ⬜ |
| 7.2 | Git commit | `git commit -m "feat: tách templates theo chuẩn WordPress"` | ⬜ |
| 7.3 | Git push | `git push origin main` | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] Frontend templates đã tạo trong `template-parts/frontend/`
- [ ] Admin templates đã migrate vào `template-parts/admin/`
- [ ] Email templates đã migrate vào `template-parts/email/`
- [ ] Function `vie_get_template()` hoạt động
- [ ] `page-checkout.php` đã refactor gọn gàng
- [ ] Tất cả templates có header block
- [ ] UI hiển thị đúng như trước
- [ ] Đã commit và push

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-07-TESTING.md](./TASK-07-TESTING.md)**
