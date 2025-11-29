# RULE-05: QUY CHUẨN ĐẶT TÊN

**Phiên bản:** 1.0  
**Áp dụng cho:** Tất cả code trong theme  
**Bắt buộc:** ✅ CÓ

---

## 1. TỔNG QUAN

| Loại | Convention | Ví dụ |
|------|------------|-------|
| PHP Class | `PascalCase` với prefix `Vie_` | `Vie_Booking_Manager` |
| PHP Function | `snake_case` với prefix `vie_` | `vie_format_currency()` |
| PHP Variable | `snake_case` | `$booking_data` |
| PHP Constant | `UPPER_SNAKE_CASE` với prefix `VIE_` | `VIE_THEME_VERSION` |
| CSS Class | `kebab-case` với prefix `vie-` | `.vie-room-card` |
| JS Module | `PascalCase` với prefix `Vie` | `VieBookingPopup` |
| JS Variable | `camelCase` | `bookingData` |
| JS Function | `camelCase` | `calculatePrice()` |
| File PHP | `kebab-case` hoặc `class-*.php` | `class-booking-manager.php` |
| File CSS/JS | `kebab-case` | `booking-popup.css` |
| Database Table | `snake_case` với prefix `wp_hotel_` | `wp_hotel_bookings` |
| Hook/Action | `snake_case` với prefix `vie_` | `vie_after_booking_created` |

---

## 2. PHP NAMING

### 2.1 Classes

```php
// ✅ ĐÚNG
class Vie_Booking_Manager { }
class Vie_Room_Pricing_Engine { }
class Vie_Google_Sheets_API { }
class Vie_SePay_Gateway { }

// ❌ SAI
class BookingManager { }           // Thiếu prefix
class vie_booking_manager { }      // Không đúng format
class VieBookingManager { }        // Thiếu underscore
```

### 2.2 Functions

```php
// ✅ ĐÚNG - Global functions
function vie_format_currency( $amount ) { }
function vie_get_room_price( $room_id, $date ) { }
function vie_send_booking_email( $booking_id ) { }

// ✅ ĐÚNG - Private/internal functions (thêm underscore)
function _vie_calculate_surcharge( $data ) { }

// ❌ SAI
function formatCurrency() { }      // Thiếu prefix, sai format
function VieFormatCurrency() { }   // Sai format
function vie_FormatCurrency() { }  // Mix format
```

### 2.3 Variables

```php
// ✅ ĐÚNG
$booking_data = [];
$room_id = 123;
$customer_name = 'Nguyễn Văn A';
$total_price = 1500000;
$is_available = true;
$has_breakfast = false;

// ❌ SAI
$bookingData = [];     // camelCase - dùng cho JS
$BookingData = [];     // PascalCase - dùng cho Class
$BOOKING_DATA = [];    // UPPER - dùng cho constant
```

### 2.4 Constants

```php
// ✅ ĐÚNG
define('VIE_THEME_VERSION', '2.0.0');
define('VIE_HOTEL_ROOMS_PATH', dirname(__FILE__));
define('VIE_GOOGLE_API_TIMEOUT', 10);
define('VIE_COUPON_CACHE_DURATION', 300);

// ❌ SAI
define('THEME_VERSION', '2.0.0');      // Thiếu prefix
define('vie_theme_version', '2.0.0');  // Sai format
define('VieThemeVersion', '2.0.0');    // Sai format
```

### 2.5 Class Methods

```php
class Vie_Booking_Manager {
    
    // ✅ ĐÚNG - Public methods: snake_case
    public function create_booking( $data ) { }
    public function get_booking_by_id( $id ) { }
    public function update_booking_status( $id, $status ) { }
    
    // ✅ ĐÚNG - Private methods: có thể thêm underscore prefix
    private function _validate_booking_data( $data ) { }
    private function _generate_booking_code() { }
    
    // ✅ ĐÚNG - Magic methods
    public function __construct() { }
    public function __toString() { }
    
    // ❌ SAI
    public function createBooking() { }    // camelCase
    public function CreateBooking() { }    // PascalCase
}
```

---

## 3. CSS NAMING

### 3.1 BEM Convention

```
.block                      # Component chính
.block__element             # Phần tử con
.block--modifier            # Biến thể của block
.block__element--modifier   # Biến thể của element
```

### 3.2 Prefix Rules

| Prefix | Ý nghĩa | Ví dụ |
|--------|---------|-------|
| `vie-` | Component của theme | `.vie-room-card` |
| `vie-layout-` | Layout components | `.vie-layout-grid` |
| `vie-u-` | Utility classes | `.vie-u-hidden` |
| `is-` | State classes | `.is-active`, `.is-loading` |
| `has-` | State với content | `.has-error`, `.has-children` |
| `js-` | JavaScript hooks | `.js-toggle-modal` |

### 3.3 Ví dụ đầy đủ

```css
/* Block */
.vie-room-card { }

/* Elements */
.vie-room-card__image { }
.vie-room-card__content { }
.vie-room-card__title { }
.vie-room-card__price { }
.vie-room-card__button { }

/* Modifiers */
.vie-room-card--featured { }
.vie-room-card--sold-out { }
.vie-room-card--compact { }

/* Element với modifier */
.vie-room-card__price--discounted { }
.vie-room-card__button--primary { }

/* State classes */
.vie-room-card.is-selected { }
.vie-room-card.is-loading { }
.vie-room-card.has-discount { }

/* JavaScript hook (không style trực tiếp) */
.js-open-booking-popup { }
```

### 3.4 Các lỗi thường gặp

```css
/* ❌ SAI - Thiếu prefix */
.room-card { }
.booking-popup { }

/* ❌ SAI - Dùng camelCase */
.vieRoomCard { }

/* ❌ SAI - Nesting quá sâu */
.vie-room-card__content__header__title__text { }

/* ❌ SAI - Dùng ID làm selector */
#vie-room-card { }

/* ✅ ĐÚNG - Tách thành block con */
.vie-room-card { }
.vie-room-card__header { }
.vie-room-card-header__title { }  /* Block con */
```

---

## 4. JAVASCRIPT NAMING

### 4.1 Modules/Objects

```javascript
// ✅ ĐÚNG
var VieBookingPopup = { };
var VieRoomListing = { };
var VieDatepicker = { };
var ViePriceCalculator = { };

// ❌ SAI
var bookingPopup = { };        // Thiếu prefix
var vieBookingPopup = { };     // camelCase
var Vie_Booking_Popup = { };   // Snake case
```

### 4.2 Functions/Methods

```javascript
// ✅ ĐÚNG
function calculatePrice() { }
function validateForm() { }
function showBookingPopup() { }
function handleSubmitClick() { }

// ✅ ĐÚNG - Event handlers
function onDateChange() { }
function onFormSubmit() { }
function handleRoomSelect() { }

// ✅ ĐÚNG - Private (underscore prefix)
function _validateEmail() { }
function _formatCurrency() { }

// ❌ SAI
function CalculatePrice() { }     // PascalCase
function calculate_price() { }    // snake_case
```

### 4.3 Variables

```javascript
// ✅ ĐÚNG
var bookingData = {};
var roomId = 123;
var customerName = 'Nguyễn Văn A';
var totalPrice = 1500000;
var isAvailable = true;
var hasBreakfast = false;

// ✅ ĐÚNG - jQuery elements (prefix $)
var $container = $('#vie-container');
var $form = $('.vie-form');
var $submitButton = $form.find('.js-submit');

// ✅ ĐÚNG - Constants trong module
var MAX_ROOMS = 10;
var API_TIMEOUT = 30000;
var DATE_FORMAT = 'dd/mm/yy';

// ❌ SAI
var booking_data = {};     // snake_case
var BookingData = {};      // PascalCase
```

### 4.4 Custom Events

```javascript
// ✅ ĐÚNG - Format: vie:[module]:[action]
$(document).trigger('vie:booking:created', [data]);
$(document).trigger('vie:price:calculated', [priceData]);
$(document).trigger('vie:modal:opened', [modalId]);
$(document).trigger('vie:room:selected', [roomId]);

// ❌ SAI
$(document).trigger('bookingCreated');     // Thiếu namespace
$(document).trigger('vie-booking-created'); // Sai format
$(document).trigger('VIE_BOOKING_CREATED'); // Sai format
```

---

## 5. FILE NAMING

### 5.1 PHP Files

```
# Class files (có prefix class-)
class-booking-manager.php
class-pricing-engine.php
class-google-sheets-api.php

# Helper files
helpers.php
formatting.php
security.php

# Hook files
hooks.php
ajax.php
shortcodes.php

# Template files
single-hotel.php
page-checkout.php
template-room-card.php
```

### 5.2 CSS Files

```
# Component files
room-card.css
booking-popup.css
checkout-form.css

# Page-specific
page-rooms.css
page-bookings.css

# Base files (underscore prefix = không load trực tiếp)
_variables.css
_mixins.css
_reset.css
```

### 5.3 JS Files

```
# Module files
core.js
room-listing.js
booking-popup.js
datepicker.js

# Page-specific
page-rooms.js
page-calendar.js
```

---

## 6. DATABASE NAMING

### 6.1 Tables

```sql
-- ✅ ĐÚNG
wp_hotel_rooms
wp_hotel_room_pricing
wp_hotel_bookings
wp_hotel_surcharges

-- ❌ SAI
wp_rooms              -- Thiếu prefix hotel_
wp_hotelRooms         -- camelCase
wp_Hotel_Rooms        -- PascalCase
```

### 6.2 Columns

```sql
-- ✅ ĐÚNG
id
room_id
hotel_id
check_in
check_out
customer_name
customer_phone
created_at
updated_at
is_active
has_breakfast

-- ❌ SAI
roomId            -- camelCase
CheckIn           -- PascalCase
CUSTOMER_NAME     -- UPPER_CASE
```

---

## 7. WORDPRESS HOOKS

### 7.1 Actions

```php
// ✅ ĐÚNG
do_action('vie_before_booking_created', $booking_data);
do_action('vie_after_booking_created', $booking_id, $booking_data);
do_action('vie_booking_status_changed', $booking_id, $old_status, $new_status);
do_action('vie_payment_completed', $booking_id, $payment_data);

// ❌ SAI
do_action('before_booking_created');     // Thiếu prefix
do_action('vieBeforeBookingCreated');    // camelCase
```

### 7.2 Filters

```php
// ✅ ĐÚNG
$price = apply_filters('vie_room_price', $base_price, $room_id, $date);
$email_content = apply_filters('vie_booking_email_content', $content, $booking);
$is_available = apply_filters('vie_room_availability', $available, $room_id, $dates);

// ❌ SAI
apply_filters('room_price', $price);           // Thiếu prefix
apply_filters('vieRoomPrice', $price);         // camelCase
```

---

## 8. CHECKLIST REVIEW

### PHP
- [ ] Class có prefix `Vie_` và dùng PascalCase?
- [ ] Function có prefix `vie_` và dùng snake_case?
- [ ] Variable dùng snake_case?
- [ ] Constant có prefix `VIE_` và dùng UPPER_SNAKE_CASE?

### CSS
- [ ] Class có prefix `vie-`?
- [ ] Tuân thủ BEM naming?
- [ ] State classes dùng `is-` hoặc `has-`?
- [ ] JS hooks dùng prefix `js-`?

### JavaScript
- [ ] Module dùng PascalCase với prefix `Vie`?
- [ ] Function/variable dùng camelCase?
- [ ] jQuery elements có prefix `$`?
- [ ] Custom events format `vie:[module]:[action]`?

### Files
- [ ] PHP class files có prefix `class-`?
- [ ] Tên file dùng kebab-case?
- [ ] Không có khoảng trắng hoặc ký tự đặc biệt?
