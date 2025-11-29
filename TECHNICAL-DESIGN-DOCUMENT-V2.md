# TÀI LIỆU THIẾT KẾ KỸ THUẬT - CHILD THEME V2.0

**Dự án:** Vielimousine Child Theme  
**Phiên bản hiện tại:** 0.25.3  
**Phiên bản mục tiêu:** 2.0.0  
**Ngày lập:** 29/11/2024  
**Tác giả:** Senior WordPress System Architect

---

## PHẦN A: PHÂN TÍCH HIỆN TRẠNG (CODE AUDIT)

### 1. TỔNG QUAN CẤU TRÚC HIỆN TẠI

```
/vielimousine-child/
├── .git/
├── BG_ COMBO Y2025_SALES THẤP ĐIỂM 21.10 SALEE.xlsx  ⚠️ FILE DATA Ở ROOT
├── credentials/                 ✅ Đã tách riêng
│   ├── .htaccess
│   └── service-account.json.example
├── functions.php               ⚠️ CHỨA SMTP CREDENTIALS HARDCODE
├── inc/
│   ├── config/                 ✅ Đã tách cấu hình
│   ├── core/                   ✅ Class API tốt
│   ├── hotel-rooms/            ⚠️ MODULE LỚN, CẤU TRÚC CHƯA CHUẨN
│   │   ├── admin/
│   │   ├── assets/             ⚠️ Assets nằm trong logic folder
│   │   ├── frontend/
│   │   ├── includes/
│   │   └── templates/
│   ├── modules/
│   │   └── coupons/            ⚠️ Assets nằm lẫn với logic
│   └── utils/                  ✅ Helpers tốt
├── logs/                       ✅ Đã bảo vệ
├── page-checkout.php           ⚠️ TEMPLATE 30KB - SPAGHETTI CODE
├── screenshot.png
└── style.css
```

### 2. VẤN ĐỀ PHÁT HIỆN

#### 🔴 CRITICAL ISSUES (Phải sửa ngay)

| # | Vấn đề | File | Mức độ |
|---|--------|------|--------|
| 1 | **SMTP Password hardcode** | `functions.php:54` | 🔴 CRITICAL |
| 2 | **File Excel ở root** | `BG_ COMBO...xlsx` | 🟡 MEDIUM |
| 3 | **Template 30KB spaghetti** | `page-checkout.php` | 🔴 HIGH |

#### 🟡 CODE SMELL - Architecture Issues

| # | Vấn đề | Mô tả |
|---|--------|-------|
| 1 | **Assets nằm trong logic folder** | `/inc/hotel-rooms/assets/` thay vì `/assets/` |
| 2 | **Không có biến CSS global** | Mỗi file CSS định nghĩa riêng `--vie-primary` |
| 3 | **File JS quá lớn** | `frontend.js` = 52KB, `page-bulk-matrix.js` = 32KB |
| 4 | **Duplicate require** | `class-transport-metabox.php` được require 2 lần |
| 5 | **Comment thiếu nhất quán** | Mix tiếng Anh + tiếng Việt |

#### 🟢 ĐÃ LÀM TỐT

| # | Điểm tốt | Mô tả |
|---|----------|-------|
| 1 | **Class-based architecture** | Các module đã dùng OOP |
| 2 | **Singleton pattern** | `Vie_Hotel_Rooms` đã áp dụng |
| 3 | **Nonce security** | AJAX có verify nonce |
| 4 | **Prepared statements** | SQL queries đã dùng `$wpdb->prepare()` |
| 5 | **Constants file** | Đã tách config ra `/inc/config/` |

### 3. ĐÁNH GIÁ TÀI NGUYÊN CSS/JS

#### CSS FILES (9 files riêng lẻ)

```
FRONTEND:
├── frontend.css           33KB  ⚠️ Cần tách nhỏ
├── sepay-payment.css       9KB  ✅ OK
├── transport-metabox.css   3KB  ✅ OK

ADMIN:
├── _variables.css          2KB  ✅ Đã có Single Source
├── common.css              6KB  ✅ OK
├── page-bookings.css       3KB  ✅ OK
├── page-bulk-matrix.css   24KB  ⚠️ Quá lớn
├── page-rooms.css         11KB  ✅ OK
├── page-settings.css      0.3KB ✅ OK

COUPONS:
└── coupon-form.css         3KB  ⚠️ Nằm sai vị trí
```

#### JS FILES (8 files)

```
FRONTEND:
├── frontend.js            52KB  ⚠️ CẦN TÁCH THÀNH MODULES
├── sepay-payment.js        8KB  ✅ OK
├── transport-metabox.js    2KB  ✅ OK

ADMIN:
├── common.js               1KB  ✅ OK
├── page-bookings.js        4KB  ✅ OK
├── page-bulk-matrix.js    32KB  ⚠️ Quá lớn
├── page-calendar.js       11KB  ✅ OK
├── page-rooms.js          27KB  ⚠️ Cần review

COUPONS:
└── coupon-form.js          7KB  ⚠️ Nằm sai vị trí
```

#### VẤN ĐỀ LOAD ASSETS

1. **CDN External** - jQuery UI CSS/JS load từ CDN (có thể chậm)
2. **Swiper CDN** - Load từ jsdelivr thay vì bundle local
3. **Không có lazy load** - Tất cả CSS/JS load cùng lúc
4. **Không minify** - File nguồn chưa được nén

---

## PHẦN B: KIẾN TRÚC MỚI V2.0

### 1. CẤU TRÚC THƯ MỤC MỚI

```
/vielimousine-child/
│
├── _backup_legacy_v1_291124/    # ★ Code cũ backup an toàn
│
├── assets/                       # ★ CHỈ CHỨA FILE TĨNH
│   ├── css/
│   │   ├── _variables.css        # Single Source of Truth
│   │   ├── admin/
│   │   │   ├── common.css
│   │   │   ├── page-bookings.css
│   │   │   ├── page-rooms.css
│   │   │   ├── page-calendar.css
│   │   │   └── page-settings.css
│   │   └── frontend/
│   │       ├── main.css          # Tách từ frontend.css
│   │       ├── room-listing.css
│   │       ├── booking-popup.css
│   │       ├── checkout.css
│   │       └── payment.css
│   │
│   ├── js/
│   │   ├── admin/
│   │   │   ├── common.js
│   │   │   ├── booking-manager.js
│   │   │   ├── room-manager.js
│   │   │   ├── calendar-manager.js
│   │   │   └── bulk-matrix.js
│   │   └── frontend/
│   │       ├── core.js           # Core utilities
│   │       ├── datepicker.js     # Datepicker module
│   │       ├── room-listing.js   # Room cards + filters
│   │       ├── booking-popup.js  # Booking modal
│   │       └── payment.js        # SePay integration
│   │
│   ├── images/
│   │   └── icons/
│   │
│   └── vendor/                   # Third-party libraries
│       ├── swiper/
│       └── jquery-ui/
│
├── inc/                          # ★ CORE LOGIC (PHP)
│   ├── classes/                  # Business Logic Classes
│   │   ├── class-room-manager.php
│   │   ├── class-booking-manager.php
│   │   ├── class-pricing-engine.php
│   │   ├── class-email-manager.php
│   │   ├── class-google-sheets-api.php
│   │   └── class-sepay-gateway.php
│   │
│   ├── helpers/                  # Utility Functions
│   │   ├── formatting.php        # Format tiền, ngày tháng
│   │   ├── security.php          # Sanitize, validate
│   │   └── database.php          # DB helpers
│   │
│   ├── hooks/                    # WordPress Hooks
│   │   ├── assets.php            # wp_enqueue_scripts
│   │   ├── ajax.php              # AJAX handlers registry
│   │   ├── admin-menu.php        # Admin menus
│   │   └── shortcodes.php        # Shortcode definitions
│   │
│   ├── admin/                    # Admin Controllers
│   │   ├── class-admin-rooms.php
│   │   ├── class-admin-bookings.php
│   │   ├── class-admin-calendar.php
│   │   └── class-admin-settings.php
│   │
│   ├── frontend/                 # Frontend Controllers
│   │   ├── class-shortcode-rooms.php
│   │   └── class-ajax-handlers.php
│   │
│   └── config/                   # Configuration
│       ├── constants.php
│       ├── database-schema.php
│       └── credentials.php
│
├── template-parts/               # ★ VIEW TEMPLATES
│   ├── admin/
│   │   ├── rooms/
│   │   │   ├── list.php
│   │   │   ├── form.php
│   │   │   └── calendar.php
│   │   ├── bookings/
│   │   │   ├── list.php
│   │   │   └── detail.php
│   │   └── settings/
│   │       └── general.php
│   │
│   ├── frontend/
│   │   ├── room-card.php
│   │   ├── room-detail-modal.php
│   │   ├── booking-popup.php
│   │   ├── checkout-form.php
│   │   └── payment-section.php
│   │
│   └── email/
│       ├── booking-confirmation.php
│       ├── payment-success.php
│       └── admin-notification.php
│
├── languages/                    # Translation files
│   └── viechild-vi.po
│
├── data/                         # Data files (protected)
│   ├── .htaccess
│   └── sample-data.xlsx
│
├── logs/                         # Log files (protected)
│   ├── .htaccess
│   └── system.log
│
├── credentials/                  # Sensitive files (protected)
│   ├── .htaccess
│   └── google-service-account.json
│
├── functions.php                 # ★ BOOTSTRAP ONLY
├── style.css                     # Theme metadata
├── screenshot.png
└── README.md
```

### 2. SƠ ĐỒ MODULE DEPENDENCY

```
                    ┌─────────────────┐
                    │  functions.php  │
                    │   (Bootstrap)   │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        ▼                    ▼                    ▼
┌───────────────┐    ┌───────────────┐    ┌───────────────┐
│  inc/config/  │    │  inc/helpers/ │    │  inc/hooks/   │
│  (Constants)  │    │  (Utilities)  │    │  (WP Hooks)   │
└───────┬───────┘    └───────┬───────┘    └───────┬───────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             ▼
                    ┌─────────────────┐
                    │  inc/classes/   │
                    │ (Business Logic)│
                    └────────┬────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼                             ▼
      ┌───────────────┐             ┌───────────────┐
      │  inc/admin/   │             │ inc/frontend/ │
      │ (Controllers) │             │ (Controllers) │
      └───────┬───────┘             └───────┬───────┘
              │                             │
              ▼                             ▼
      ┌───────────────┐             ┌───────────────┐
      │template-parts/│             │template-parts/│
      │    admin/     │             │   frontend/   │
      └───────────────┘             └───────────────┘
```

---

## PHẦN C: QUY CHUẨN CODING & COMMENT

### 1. QUY CHUẨN FILE HEADER

```php
<?php
/**
 * ============================================================================
 * TÊN FILE: class-booking-manager.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Quản lý toàn bộ logic đặt phòng: tạo booking, cập nhật trạng thái,
 * tính toán giá và xử lý thanh toán.
 * 
 * CHỨC NĂNG CHÍNH:
 * - Tạo đơn đặt phòng mới
 * - Cập nhật trạng thái đơn hàng
 * - Tính giá theo ngày và phụ thu
 * - Kiểm tra khả dụng phòng
 * 
 * SỬ DỤNG:
 * $booking = new Vie_Booking_Manager();
 * $result = $booking->create_booking($data);
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
```

### 2. QUY CHUẨN CLASS HEADER

```php
/**
 * ============================================================================
 * CLASS: Vie_Booking_Manager
 * ============================================================================
 * 
 * Lớp xử lý nghiệp vụ đặt phòng khách sạn.
 * Triển khai Singleton Pattern để đảm bảo chỉ có 1 instance.
 * 
 * @since   2.0.0
 * @uses    Vie_Pricing_Engine   Tính giá phòng
 * @uses    Vie_Email_Manager    Gửi email xác nhận
 * @uses    Vie_Database_Helper  Thao tác database
 */
class Vie_Booking_Manager {
    // ...
}
```

### 3. QUY CHUẨN FUNCTION HEADER

```php
/**
 * Tạo đơn đặt phòng mới
 * 
 * Hàm này thực hiện các bước:
 * 1. Validate dữ liệu đầu vào
 * 2. Kiểm tra phòng còn trống
 * 3. Tính tổng tiền (giá phòng + phụ thu)
 * 4. Lưu vào database
 * 5. Gửi email xác nhận
 * 
 * @since   2.0.0
 * 
 * @param   array   $booking_data {
 *     Dữ liệu đặt phòng
 * 
 *     @type int      $room_id        ID của phòng
 *     @type int      $hotel_id       ID của khách sạn
 *     @type string   $check_in       Ngày nhận phòng (Y-m-d)
 *     @type string   $check_out      Ngày trả phòng (Y-m-d)
 *     @type int      $num_rooms      Số lượng phòng
 *     @type int      $num_adults     Số người lớn
 *     @type int      $num_children   Số trẻ em
 *     @type array    $children_ages  Tuổi từng trẻ em
 *     @type string   $customer_name  Tên khách hàng
 *     @type string   $customer_phone Số điện thoại
 *     @type string   $customer_email Email (tùy chọn)
 * }
 * 
 * @return  array|WP_Error {
 *     Kết quả tạo booking
 * 
 *     @type bool     $success        True nếu thành công
 *     @type int      $booking_id     ID của booking vừa tạo
 *     @type string   $booking_code   Mã đặt phòng (VD: VIE-20241129-001)
 *     @type string   $booking_hash   Hash bảo mật cho URL checkout
 *     @type float    $total_amount   Tổng tiền
 * }
 * 
 * @throws  Exception  Nếu phòng không còn trống
 * 
 * @example
 * $manager = Vie_Booking_Manager::get_instance();
 * $result = $manager->create_booking([
 *     'room_id'    => 5,
 *     'hotel_id'   => 123,
 *     'check_in'   => '2024-12-01',
 *     'check_out'  => '2024-12-03',
 *     'num_rooms'  => 1,
 *     'num_adults' => 2,
 *     'customer_name'  => 'Nguyễn Văn A',
 *     'customer_phone' => '0901234567'
 * ]);
 */
public function create_booking( array $booking_data ) {
    // Logic xử lý...
}
```

### 4. QUY CHUẨN INLINE COMMENT

```php
/**
 * -------------------------------------------------------------------------
 * BƯỚC 1: VALIDATE DỮ LIỆU ĐẦU VÀO
 * -------------------------------------------------------------------------
 * Kiểm tra các trường bắt buộc và định dạng dữ liệu
 */
$required_fields = ['room_id', 'hotel_id', 'check_in', 'check_out'];
foreach ( $required_fields as $field ) {
    if ( empty( $booking_data[ $field ] ) ) {
        return new WP_Error( 'missing_field', "Thiếu trường bắt buộc: {$field}" );
    }
}

/**
 * -------------------------------------------------------------------------
 * BƯỚC 2: TÍNH GIÁ THEO TỪNG NGÀY
 * -------------------------------------------------------------------------
 * Logic tính giá phức tạp:
 * - Lấy giá từ bảng pricing theo từng ngày
 * - Nếu không có giá riêng, dùng base_price của phòng
 * - Cộng thêm phụ thu người lớn/trẻ em nếu có
 */
$pricing_engine = Vie_Pricing_Engine::get_instance();
$price_breakdown = $pricing_engine->calculate_for_dates(
    $booking_data['room_id'],
    $date_in,
    $date_out,
    $booking_data['price_type'] ?? 'room' // 'room' = Room Only, 'combo' = Có xe
);

// Tổng giá cơ bản (chưa bao gồm phụ thu)
$base_total = array_sum( array_column( $price_breakdown, 'price' ) );

// Nhân với số phòng
$rooms_total = $base_total * $booking_data['num_rooms'];
```

### 5. QUY CHUẨN CSS COMMENT

```css
/**
 * ============================================================================
 * FILE: booking-popup.css
 * ============================================================================
 * 
 * Styles cho popup đặt phòng trên frontend
 * 
 * MỤC LỤC:
 * 1. Modal Container
 * 2. Header & Close Button
 * 3. Step Indicator
 * 4. Form Elements
 * 5. Price Summary
 * 6. Navigation Buttons
 * 7. Success State
 * 8. Responsive
 * ============================================================================
 */

/* ==========================================================================
   1. MODAL CONTAINER
   ==========================================================================
   Container chính của popup, sử dụng flexbox để căn giữa
*/
.vie-booking-popup {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

/* Overlay nền mờ, click để đóng popup */
.vie-popup-overlay {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px); /* Hiệu ứng blur cho modern browsers */
}
```

### 6. QUY CHUẨN JAVASCRIPT COMMENT

```javascript
/**
 * ============================================================================
 * FILE: booking-popup.js
 * ============================================================================
 * 
 * Module xử lý popup đặt phòng
 * Sử dụng Module Pattern để đóng gói logic
 * 
 * DEPENDENCIES:
 * - jQuery (WP Core)
 * - jQuery UI Datepicker
 * - vieBooking (localized data từ PHP)
 * 
 * @since   2.0.0
 * ============================================================================
 */

(function($) {
    'use strict';

    /**
     * =========================================================================
     * MODULE: VieBookingPopup
     * =========================================================================
     * Quản lý popup đặt phòng: mở/đóng, chuyển step, validate, submit
     */
    var VieBookingPopup = {

        /**
         * ---------------------------------------------------------------------
         * THUỘC TÍNH
         * ---------------------------------------------------------------------
         */
        
        /** @type {number} Step hiện tại (1-3) */
        currentStep: 1,
        
        /** @type {Object|null} Thông tin phòng đang chọn */
        selectedRoom: null,
        
        /** @type {Object|null} Dữ liệu giá đã tính */
        pricingData: null,

        /**
         * ---------------------------------------------------------------------
         * KHỞI TẠO
         * ---------------------------------------------------------------------
         */
        
        /**
         * Khởi tạo module
         * Được gọi khi document ready
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.initDatepickers();
        },

        /**
         * Cache các jQuery elements để tái sử dụng
         * Tối ưu performance, tránh query DOM nhiều lần
         */
        cacheElements: function() {
            this.$popup = $('#vie-booking-popup');
            this.$form = $('#vie-booking-form');
            this.$priceDisplay = $('#vie-price-display');
            this.$stepIndicator = $('.vie-step-indicator');
        },

        /**
         * ---------------------------------------------------------------------
         * XỬ LÝ TÍNH GIÁ
         * ---------------------------------------------------------------------
         */

        /**
         * Tính giá booking qua AJAX
         * 
         * Flow xử lý:
         * 1. Thu thập dữ liệu form (ngày, số phòng, số người...)
         * 2. Gọi API tính giá
         * 3. Hiển thị kết quả hoặc thông báo lỗi
         * 
         * @returns {void}
         */
        calculatePrice: function() {
            var self = this;
            var formData = this.collectFormData();

            // Hiển thị loading state
            this.showPriceLoading();

            $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vie_frontend_calculate_price',
                    nonce: vieBooking.nonce,
                    ...formData
                },
                success: function(response) {
                    if (response.success) {
                        // Lưu dữ liệu giá để dùng khi submit
                        self.pricingData = response.data;
                        self.displayPrice(response.data);
                    } else {
                        self.showPriceError(response.data.message);
                    }
                },
                error: function() {
                    self.showPriceError(vieBooking.i18n.error);
                }
            });
        }
    };

    // Khởi tạo khi DOM ready
    $(document).ready(function() {
        VieBookingPopup.init();
    });

})(jQuery);
```

---

## PHẦN D: KẾ HOẠCH TRIỂN KHAI CHI TIẾT

### PHASE 0: BACKUP & KHỞI TẠO (Ngày 1)

#### Task 0.1: Tạo thư mục backup

```bash
# Trong thư mục theme
mkdir _backup_legacy_v1_291124

# Di chuyển tất cả file/folder hiện tại (trừ .git)
mv BG_*.xlsx _backup_legacy_v1_291124/
mv credentials/ _backup_legacy_v1_291124/
mv functions.php _backup_legacy_v1_291124/
mv inc/ _backup_legacy_v1_291124/
mv logs/ _backup_legacy_v1_291124/
mv page-checkout.php _backup_legacy_v1_291124/
mv style.css _backup_legacy_v1_291124/
mv screenshot.png _backup_legacy_v1_291124/
```

#### Task 0.2: Tạo cấu trúc thư mục mới

```bash
# Tạo cấu trúc thư mục mới
mkdir -p assets/{css/{admin,frontend},js/{admin,frontend},images/icons,vendor}
mkdir -p inc/{classes,helpers,hooks,admin,frontend,config}
mkdir -p template-parts/{admin/{rooms,bookings,settings},frontend,email}
mkdir -p {languages,data,logs,credentials}
```

#### Task 0.3: Tạo file bootstrap functions.php mới

```php
<?php
/**
 * ============================================================================
 * VIELIMOUSINE CHILD THEME - VERSION 2.0
 * ============================================================================
 * 
 * File bootstrap chính - CHỈ chứa logic require các module
 * Không viết business logic ở đây
 * 
 * @package     VielimousineChild
 * @version     2.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * -------------------------------------------------------------------------
 * ĐỊNH NGHĨA CONSTANTS
 * -------------------------------------------------------------------------
 */
define('VIE_THEME_VERSION', '2.0.0');
define('VIE_THEME_PATH', get_stylesheet_directory());
define('VIE_THEME_URL', get_stylesheet_directory_uri());

/**
 * -------------------------------------------------------------------------
 * LOAD CẤU HÌNH
 * -------------------------------------------------------------------------
 */
require_once VIE_THEME_PATH . '/inc/config/constants.php';
require_once VIE_THEME_PATH . '/inc/config/credentials.php';

/**
 * -------------------------------------------------------------------------
 * LOAD HELPER FUNCTIONS
 * -------------------------------------------------------------------------
 */
require_once VIE_THEME_PATH . '/inc/helpers/formatting.php';
require_once VIE_THEME_PATH . '/inc/helpers/security.php';
require_once VIE_THEME_PATH . '/inc/helpers/database.php';

/**
 * -------------------------------------------------------------------------
 * LOAD CORE CLASSES
 * -------------------------------------------------------------------------
 */
require_once VIE_THEME_PATH . '/inc/classes/class-room-manager.php';
require_once VIE_THEME_PATH . '/inc/classes/class-booking-manager.php';
require_once VIE_THEME_PATH . '/inc/classes/class-pricing-engine.php';
require_once VIE_THEME_PATH . '/inc/classes/class-email-manager.php';
require_once VIE_THEME_PATH . '/inc/classes/class-google-sheets-api.php';
require_once VIE_THEME_PATH . '/inc/classes/class-sepay-gateway.php';

/**
 * -------------------------------------------------------------------------
 * LOAD HOOKS (WordPress Integration)
 * -------------------------------------------------------------------------
 */
require_once VIE_THEME_PATH . '/inc/hooks/assets.php';
require_once VIE_THEME_PATH . '/inc/hooks/ajax.php';
require_once VIE_THEME_PATH . '/inc/hooks/admin-menu.php';
require_once VIE_THEME_PATH . '/inc/hooks/shortcodes.php';

/**
 * -------------------------------------------------------------------------
 * LOAD ADMIN CONTROLLERS (Chỉ trong admin)
 * -------------------------------------------------------------------------
 */
if (is_admin()) {
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-rooms.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-bookings.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-calendar.php';
    require_once VIE_THEME_PATH . '/inc/admin/class-admin-settings.php';
}

/**
 * -------------------------------------------------------------------------
 * LOAD FRONTEND CONTROLLERS
 * -------------------------------------------------------------------------
 */
require_once VIE_THEME_PATH . '/inc/frontend/class-shortcode-rooms.php';
require_once VIE_THEME_PATH . '/inc/frontend/class-ajax-handlers.php';
```

---

### PHASE 1: REFACTOR ASSETS (Ngày 2-3)

#### Task 1.1: Tạo file _variables.css toàn cục

```css
/**
 * ============================================================================
 * FILE: _variables.css
 * ============================================================================
 * 
 * Single Source of Truth cho tất cả biến CSS
 * Import file này đầu tiên trong mọi file CSS khác
 * 
 * MỤC LỤC:
 * 1. Colors
 * 2. Typography
 * 3. Spacing
 * 4. Borders & Shadows
 * 5. Breakpoints
 * 6. Z-index Scale
 * ============================================================================
 */

:root {
    /* =========== 1. COLORS =========== */
    
    /* Primary Brand Colors */
    --vie-primary: #2563eb;
    --vie-primary-light: #3b82f6;
    --vie-primary-dark: #1d4ed8;
    --vie-primary-50: #eff6ff;
    --vie-primary-100: #dbeafe;
    
    /* Secondary Colors */
    --vie-secondary: #64748b;
    --vie-secondary-light: #94a3b8;
    --vie-secondary-dark: #475569;
    
    /* Semantic Colors */
    --vie-success: #10b981;
    --vie-success-light: #34d399;
    --vie-danger: #ef4444;
    --vie-danger-light: #f87171;
    --vie-warning: #f59e0b;
    --vie-warning-light: #fbbf24;
    --vie-info: #0ea5e9;
    
    /* Neutral Colors */
    --vie-white: #ffffff;
    --vie-black: #000000;
    --vie-gray-50: #f8fafc;
    --vie-gray-100: #f1f5f9;
    --vie-gray-200: #e2e8f0;
    --vie-gray-300: #cbd5e1;
    --vie-gray-400: #94a3b8;
    --vie-gray-500: #64748b;
    --vie-gray-600: #475569;
    --vie-gray-700: #334155;
    --vie-gray-800: #1e293b;
    --vie-gray-900: #0f172a;
    
    /* Text Colors */
    --vie-text: var(--vie-gray-800);
    --vie-text-muted: var(--vie-gray-500);
    --vie-text-light: var(--vie-gray-400);
    
    /* Background Colors */
    --vie-bg: var(--vie-white);
    --vie-bg-light: var(--vie-gray-50);
    --vie-bg-dark: var(--vie-gray-100);
    
    /* Border Colors */
    --vie-border: var(--vie-gray-200);
    --vie-border-light: var(--vie-gray-100);
    --vie-border-dark: var(--vie-gray-300);
    
    /* =========== 2. TYPOGRAPHY =========== */
    
    --vie-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    --vie-font-mono: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
    
    /* Font Sizes */
    --vie-text-xs: 0.75rem;      /* 12px */
    --vie-text-sm: 0.875rem;     /* 14px */
    --vie-text-base: 1rem;       /* 16px */
    --vie-text-lg: 1.125rem;     /* 18px */
    --vie-text-xl: 1.25rem;      /* 20px */
    --vie-text-2xl: 1.5rem;      /* 24px */
    --vie-text-3xl: 1.875rem;    /* 30px */
    
    /* Font Weights */
    --vie-font-normal: 400;
    --vie-font-medium: 500;
    --vie-font-semibold: 600;
    --vie-font-bold: 700;
    
    /* Line Heights */
    --vie-leading-tight: 1.25;
    --vie-leading-normal: 1.5;
    --vie-leading-relaxed: 1.625;
    
    /* =========== 3. SPACING =========== */
    
    --vie-space-1: 0.25rem;      /* 4px */
    --vie-space-2: 0.5rem;       /* 8px */
    --vie-space-3: 0.75rem;      /* 12px */
    --vie-space-4: 1rem;         /* 16px */
    --vie-space-5: 1.25rem;      /* 20px */
    --vie-space-6: 1.5rem;       /* 24px */
    --vie-space-8: 2rem;         /* 32px */
    --vie-space-10: 2.5rem;      /* 40px */
    --vie-space-12: 3rem;        /* 48px */
    
    /* =========== 4. BORDERS & SHADOWS =========== */
    
    --vie-radius-sm: 4px;
    --vie-radius: 8px;
    --vie-radius-md: 12px;
    --vie-radius-lg: 16px;
    --vie-radius-xl: 24px;
    --vie-radius-full: 9999px;
    
    --vie-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --vie-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --vie-shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    --vie-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    --vie-shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    
    /* =========== 5. TRANSITIONS =========== */
    
    --vie-transition-fast: 150ms ease;
    --vie-transition: 200ms ease;
    --vie-transition-slow: 300ms ease;
    
    /* =========== 6. Z-INDEX SCALE =========== */
    
    --vie-z-dropdown: 100;
    --vie-z-sticky: 200;
    --vie-z-fixed: 300;
    --vie-z-modal-backdrop: 400;
    --vie-z-modal: 500;
    --vie-z-popover: 600;
    --vie-z-tooltip: 700;
}
```

#### Task 1.2: Tạo inc/hooks/assets.php

```php
<?php
/**
 * ============================================================================
 * FILE: assets.php
 * ============================================================================
 * 
 * Quản lý việc load CSS/JS cho theme
 * Tối ưu: Chỉ load file cần thiết cho từng trang
 * 
 * @package     VielimousineChild
 * @subpackage  Hooks
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Đăng ký và load CSS/JS cho Frontend
 * 
 * @since   2.0.0
 * @hook    wp_enqueue_scripts
 */
function vie_enqueue_frontend_assets() {
    $version = VIE_THEME_VERSION;
    $css_url = VIE_THEME_URL . '/assets/css/frontend/';
    $js_url  = VIE_THEME_URL . '/assets/js/frontend/';

    /**
     * -------------------------------------------------------------------------
     * CSS CHUNG (Load trên tất cả trang)
     * -------------------------------------------------------------------------
     */
    wp_enqueue_style(
        'vie-variables',
        VIE_THEME_URL . '/assets/css/_variables.css',
        [],
        $version
    );

    /**
     * -------------------------------------------------------------------------
     * CSS/JS CHO TRANG HOTEL (Single Hotel Post)
     * -------------------------------------------------------------------------
     * Chỉ load khi xem chi tiết 1 khách sạn
     */
    if ( is_singular('hotel') ) {
        // jQuery UI Datepicker (đã có sẵn trong WP)
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker');

        // CSS cho room listing
        wp_enqueue_style('vie-room-listing', $css_url . 'room-listing.css', ['vie-variables'], $version);
        wp_enqueue_style('vie-booking-popup', $css_url . 'booking-popup.css', ['vie-variables'], $version);
        
        // Swiper (local vendor)
        wp_enqueue_style('swiper', VIE_THEME_URL . '/assets/vendor/swiper/swiper-bundle.min.css', [], '11.0.0');
        wp_enqueue_script('swiper', VIE_THEME_URL . '/assets/vendor/swiper/swiper-bundle.min.js', [], '11.0.0', true);

        // JS Modules
        wp_enqueue_script('vie-core', $js_url . 'core.js', ['jquery'], $version, true);
        wp_enqueue_script('vie-datepicker', $js_url . 'datepicker.js', ['vie-core', 'jquery-ui-datepicker'], $version, true);
        wp_enqueue_script('vie-room-listing', $js_url . 'room-listing.js', ['vie-core', 'swiper'], $version, true);
        wp_enqueue_script('vie-booking-popup', $js_url . 'booking-popup.js', ['vie-core', 'vie-datepicker'], $version, true);

        // Localize script data
        wp_localize_script('vie-core', 'vieBooking', vie_get_booking_localize_data());
    }

    /**
     * -------------------------------------------------------------------------
     * CSS/JS CHO TRANG CHECKOUT
     * -------------------------------------------------------------------------
     */
    if ( is_page_template('template-parts/frontend/checkout.php') || is_page('checkout') ) {
        wp_enqueue_style('vie-checkout', $css_url . 'checkout.css', ['vie-variables'], $version);
        wp_enqueue_style('vie-payment', $css_url . 'payment.css', ['vie-variables'], $version);
        
        wp_enqueue_script('vie-core', $js_url . 'core.js', ['jquery'], $version, true);
        wp_enqueue_script('vie-payment', $js_url . 'payment.js', ['vie-core'], $version, true);

        wp_localize_script('vie-core', 'vieBooking', vie_get_booking_localize_data());
    }
}
add_action('wp_enqueue_scripts', 'vie_enqueue_frontend_assets', 99);

/**
 * Đăng ký và load CSS/JS cho Admin
 * 
 * @since   2.0.0
 * @hook    admin_enqueue_scripts
 * 
 * @param   string  $hook_suffix    Hook suffix của trang admin hiện tại
 */
function vie_enqueue_admin_assets( $hook_suffix ) {
    // Chỉ load trên các trang admin của theme
    if ( strpos($hook_suffix, 'vie-hotel') === false ) {
        return;
    }

    $version = VIE_THEME_VERSION;
    $css_url = VIE_THEME_URL . '/assets/css/admin/';
    $js_url  = VIE_THEME_URL . '/assets/js/admin/';

    // Variables (dùng chung)
    wp_enqueue_style('vie-variables', VIE_THEME_URL . '/assets/css/_variables.css', [], $version);
    
    // Common admin styles
    wp_enqueue_style('vie-admin-common', $css_url . 'common.css', ['vie-variables'], $version);
    wp_enqueue_script('vie-admin-common', $js_url . 'common.js', ['jquery'], $version, true);

    // Page-specific assets
    $page_assets = [
        'vie-hotel-rooms'     => ['page-rooms', 'room-manager'],
        'vie-hotel-bookings'  => ['page-bookings', 'booking-manager'],
        'vie-hotel-calendar'  => ['page-calendar', 'calendar-manager'],
        'vie-hotel-settings'  => ['page-settings', null],
    ];

    // Lấy page slug từ hook suffix
    $page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
    
    if ( isset($page_assets[$page]) ) {
        list($css_file, $js_file) = $page_assets[$page];
        
        if ( $css_file ) {
            wp_enqueue_style("vie-admin-{$css_file}", $css_url . "{$css_file}.css", ['vie-admin-common'], $version);
        }
        
        if ( $js_file ) {
            wp_enqueue_script("vie-admin-{$js_file}", $js_url . "{$js_file}.js", ['vie-admin-common'], $version, true);
        }
    }

    // Localize admin data
    wp_localize_script('vie-admin-common', 'vieAdmin', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('vie_admin_nonce'),
        'i18n'    => [
            'confirm_delete' => __('Bạn có chắc muốn xóa?', 'viechild'),
            'saving'         => __('Đang lưu...', 'viechild'),
            'saved'          => __('Đã lưu!', 'viechild'),
            'error'          => __('Có lỗi xảy ra', 'viechild'),
        ]
    ]);
}
add_action('admin_enqueue_scripts', 'vie_enqueue_admin_assets');

/**
 * Lấy dữ liệu localize cho booking scripts
 * 
 * @since   2.0.0
 * @return  array   Dữ liệu cho wp_localize_script
 */
function vie_get_booking_localize_data() {
    return [
        'ajaxUrl'     => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('vie_booking_nonce'),
        'hotelId'     => get_the_ID(),
        'homeUrl'     => home_url(),
        'checkoutUrl' => home_url('/checkout/'),
        'currency'    => 'VNĐ',
        'dateFormat'  => 'dd/mm/yy',
        'minDate'     => 0,
        'i18n'        => [
            'selectDates'       => __('Vui lòng chọn ngày', 'viechild'),
            'calculating'       => __('Đang tính giá...', 'viechild'),
            'roomUnavailable'   => __('Phòng không khả dụng', 'viechild'),
            'soldOut'           => __('Hết phòng', 'viechild'),
            'book'              => __('Đặt ngay', 'viechild'),
            'close'             => __('Đóng', 'viechild'),
            'next'              => __('Tiếp tục', 'viechild'),
            'back'              => __('Quay lại', 'viechild'),
            'confirm'           => __('Xác nhận đặt phòng', 'viechild'),
            'success'           => __('Đặt phòng thành công!', 'viechild'),
            'error'             => __('Có lỗi xảy ra', 'viechild'),
            'required'          => __('Vui lòng điền đầy đủ thông tin', 'viechild'),
        ]
    ];
}
```

---

### PHASE 2: REFACTOR LOGIC (Ngày 4-7)

#### Task 2.1: Tách class-booking-manager.php

Di chuyển logic từ các file cũ:
- `_backup_legacy_v1_291124/inc/hotel-rooms/admin/class-bookings.php`
- `_backup_legacy_v1_291124/inc/hotel-rooms/frontend/class-ajax.php`

#### Task 2.2: Tách class-pricing-engine.php

Di chuyển logic tính giá từ:
- `_backup_legacy_v1_291124/inc/hotel-rooms/frontend/class-ajax.php` (function `calculate_price`)
- `_backup_legacy_v1_291124/inc/hotel-rooms/includes/class-helpers.php`

#### Task 2.3: Tách template views

Di chuyển HTML từ các class PHP vào `template-parts/`:
- Admin views: `class-bookings.php` render_page() → `template-parts/admin/bookings/list.php`
- Frontend views: `class-shortcode.php` → `template-parts/frontend/room-card.php`

---

### PHASE 3: TESTING & DEPLOY (Ngày 8-10)

#### Task 3.1: Checklist testing

- [ ] Homepage loads OK
- [ ] Hotel single page: Room listing hiển thị
- [ ] Datepicker hoạt động (filter + popup)
- [ ] Tính giá chính xác
- [ ] Đặt phòng thành công
- [ ] Checkout page hiển thị
- [ ] Thanh toán SePay hoạt động
- [ ] Email xác nhận gửi được
- [ ] Admin: Danh sách phòng
- [ ] Admin: Thêm/sửa phòng
- [ ] Admin: Lịch giá
- [ ] Admin: Danh sách booking
- [ ] Admin: Cập nhật trạng thái booking

#### Task 3.2: Rollback nếu cần

Nếu có lỗi nghiêm trọng:

```bash
# Xóa code v2.0
rm -rf assets/ inc/ template-parts/ functions.php style.css

# Khôi phục từ backup
cp -r _backup_legacy_v1_291124/* ./
```

---

## PHẦN E: DANH SÁCH FILE CẦN TẠO

### FILES MỚI CẦN TẠO

| # | Đường dẫn | Mô tả | Ưu tiên |
|---|-----------|-------|---------|
| 1 | `assets/css/_variables.css` | Biến CSS global | P0 |
| 2 | `inc/hooks/assets.php` | Load CSS/JS | P0 |
| 3 | `inc/hooks/ajax.php` | Registry AJAX handlers | P0 |
| 4 | `inc/hooks/admin-menu.php` | Admin menus | P0 |
| 5 | `inc/hooks/shortcodes.php` | Shortcode registry | P0 |
| 6 | `inc/helpers/formatting.php` | Format tiền, ngày | P1 |
| 7 | `inc/helpers/security.php` | Sanitize, validate | P1 |
| 8 | `inc/helpers/database.php` | DB utilities | P1 |
| 9 | `inc/classes/class-room-manager.php` | Quản lý phòng | P1 |
| 10 | `inc/classes/class-booking-manager.php` | Quản lý booking | P1 |
| 11 | `inc/classes/class-pricing-engine.php` | Tính giá | P1 |
| 12 | `template-parts/frontend/room-card.php` | Card phòng | P2 |
| 13 | `template-parts/frontend/booking-popup.php` | Popup đặt phòng | P2 |
| 14 | `template-parts/admin/bookings/list.php` | Danh sách booking | P2 |

### FILES CẦN MIGRATE TỪ LEGACY

| Legacy File | Target File |
|-------------|-------------|
| `inc/hotel-rooms/admin/class-bookings.php` | `inc/admin/class-admin-bookings.php` + `template-parts/admin/bookings/` |
| `inc/hotel-rooms/frontend/class-ajax.php` | `inc/frontend/class-ajax-handlers.php` + `inc/classes/class-booking-manager.php` |
| `inc/hotel-rooms/frontend/class-shortcode.php` | `inc/frontend/class-shortcode-rooms.php` + `template-parts/frontend/` |
| `inc/hotel-rooms/assets/css/frontend.css` | `assets/css/frontend/*.css` (tách nhỏ) |
| `inc/hotel-rooms/assets/js/frontend.js` | `assets/js/frontend/*.js` (tách modules) |
| `page-checkout.php` | `template-parts/frontend/checkout-form.php` + `assets/css/frontend/checkout.css` |

---

## PHỤ LỤC: RULE TASKS

Xem các file rule chi tiết trong thư mục `docs/rules/`:

1. `RULE-01-FILE-HEADER.md` - Quy chuẩn header file
2. `RULE-02-CLASS-DOCS.md` - Quy chuẩn document class/function
3. `RULE-03-CSS-STRUCTURE.md` - Quy chuẩn tổ chức CSS
4. `RULE-04-JS-MODULES.md` - Quy chuẩn JavaScript modules
5. `RULE-05-NAMING-CONVENTION.md` - Quy chuẩn đặt tên
6. `RULE-06-SECURITY.md` - Quy chuẩn bảo mật
