# INC/ - THEME CORE STRUCTURE

Cấu trúc thư mục mới cho VIE Limousine Child Theme v2.1.0

---

## 📁 Cấu trúc thư mục

```
inc/
├── Core/               [Core classes - Bootstrap, Autoloader]
├── Services/           [Business Logic Services]
│   ├── Booking/       [Booking management]
│   ├── Pricing/       [Pricing engine]
│   ├── Payment/       [Payment gateways]
│   ├── Email/         [Email services]
│   ├── Coupon/        [Coupon management]
│   ├── Integration/   [3rd party integrations - Google Sheets, etc]
│   └── Cache/         [Deprecated - use Support/Cache]
├── Support/            [Helpers & Utilities]
│   ├── Helpers/       [Helper functions]
│   └── Cache/         [Cache management]
├── Database/           [Database management]
│   ├── Schema/        [Table schemas]
│   └── Migrations/    [Database migrations]
├── Admin/              [Admin controllers]
├── Frontend/           [Frontend controllers]
├── Config/             [Configuration files]
└── Hooks/              [WordPress hooks registration]
```

---

## 📋 Mô tả chi tiết

### Core/
**Mục đích:** Core classes cho theme initialization

**Files:**
- `Bootstrap.php` - Theme initialization
- `Autoloader.php` - PSR-4 autoloader (TBD)
- `ServiceContainer.php` - Dependency injection (TBD)

**Load order:** First

---

### Services/
**Mục đích:** Business logic services theo domain

#### Services/Booking/
- `BookingService.php` - Main booking service
- `BookingValidator.php` - Booking validation
- `BookingRepository.php` - Data access layer

#### Services/Pricing/
- `PricingService.php` - Main pricing service
- `PricingCalculator.php` - Price calculation logic
- `SurchargeCalculator.php` - Surcharge calculation

#### Services/Payment/
- `PaymentGatewayInterface.php` - Payment gateway interface
- `SepayGateway.php` - SePay integration (main facade)
- `SepayOAuthService.php` - OAuth2 service
- `SepayTokenManager.php` - Token management
- `SepayAPIClient.php` - API client
- `SepayWebhookHandler.php` - Webhook handler
- `SepaySecurityValidator.php` - Security validation
- `SepaySettingsManager.php` - Settings management

#### Services/Email/
- `EmailService.php` - Email sending service
- `EmailTemplate.php` - Email templates
- `EmailQueue.php` - Email queue (TBD)

#### Services/Coupon/
- `CouponService.php` - Main coupon service
- `CouponValidator.php` - Coupon validation
- `CouponRepository.php` - Data access

#### Services/Integration/
- `GoogleAuth.php` - Google OAuth2
- `GoogleSheetsAPI.php` - Google Sheets client

**Load order:** After Support/

---

### Support/
**Mục đích:** Helper utilities và support classes

#### Support/Helpers/
- `DateHelper.php` - Date utilities
- `FormatHelper.php` - Formatting functions
- `SecurityHelper.php` - Security utilities

#### Support/Cache/
- `CacheManager.php` - Cache management service

**Load order:** After Core, before Services

---

### Database/
**Mục đích:** Database management

#### Database/Schema/
- `BookingsTable.php` - Bookings table schema
- `RoomsTable.php` - Rooms table schema
- `PricingTable.php` - Pricing table schema

#### Database/Migrations/
- `Migration_001_InitialSchema.php` - Initial schema
- `Migration_002_AddIndexes.php` - Add indexes

**Load order:** After Core

---

### Admin/
**Mục đích:** Admin page controllers

**Files:**
- `class-admin-bookings.php` - Bookings admin page
- `class-admin-rooms.php` - Rooms admin page
- `class-admin-calendar.php` - Calendar admin page
- `class-admin-settings.php` - Settings admin page

**Load order:** Late (in is_admin() block)

---

### Frontend/
**Mục đích:** Frontend controllers

**Files:**
- `class-shortcode-rooms.php` - Rooms shortcode
- `class-ajax-handlers.php` - Frontend AJAX handlers

**Load order:** After Services

---

### Config/
**Mục đích:** Configuration files

**Files:**
- `constants.php` - Theme constants

- `assets-manifest.php` - CSS/JS manifest

**Load order:** First (after Core constants)

---

### Hooks/
**Mục đích:** WordPress hooks registration

**Files:**
- `assets.php` - Enqueue CSS/JS
- `ajax.php` - AJAX endpoints
- `admin-menu.php` - Admin menus
- `shortcodes.php` - Shortcodes registration

**Load order:** After all classes loaded

---

## 🔄 Migration từ cấu trúc cũ

### inc/classes/ (OLD) → inc/Services/ (NEW)

| Old File | New Location | Status |
|----------|--------------|--------|
| `class-cache-manager.php` | `Support/Cache/CacheManager.php` | ✅ Migrated |
| `class-booking-manager.php` | `Services/Booking/BookingService.php` | 🔜 Pending |
| `class-pricing-engine.php` | `Services/Pricing/PricingService.php` | 🔜 Pending |
| `class-email-manager.php` | `Services/Email/EmailService.php` | 🔜 Pending |
| `class-sepay-gateway.php` | `Services/Payment/SepayGateway.php` | 🔜 Pending |
| `class-coupon-manager.php` | `Services/Coupon/CouponService.php` | 🔜 Pending |
| `class-google-auth.php` | `Services/Integration/GoogleAuth.php` | 🔜 Pending |
| `class-google-sheets-api.php` | `Services/Integration/GoogleSheetsAPI.php` | 🔜 Pending |
| `class-database-installer.php` | `Database/Installer.php` | 🔜 Pending |
| `class-hotel-rooms.php` | - | ❌ Deleted (legacy) |

---

## 📚 Naming Conventions

### Class Names
- **Pattern:** `Vie_{Domain}_{Type}`
- **Examples:**
  - `Vie_Booking_Service`
  - `Vie_Pricing_Calculator`
  - `Vie_SePay_OAuth_Service`

### File Names
- **Pattern:** PascalCase
- **Examples:**
  - `BookingService.php`
  - `PricingCalculator.php`

### Namespace (Future)
```php
namespace VielimousineChild\Services\Booking;

class BookingService {
    // ...
}
```

---

## 🔧 Autoloading (Future)

Kế hoạch implement PSR-4 autoloader:

```php
// inc/Core/Autoloader.php
spl_autoload_register(function($class) {
    $prefix = 'VielimousineChild\\';
    $base_dir = VIE_THEME_PATH . '/inc/';

    // Class mapping...
});
```

---

## 📖 Documentation Standards

Mỗi file PHẢI có:
1. **File header** (PHPDoc) bằng tiếng Việt
2. **Class docblock** mô tả chức năng
3. **Method docblock** cho tất cả public methods
4. **Inline comments** cho logic phức tạp

Xem `Support/Cache/CacheManager.php` làm reference.

---

**Version:** 2.1.0
**Last Updated:** 2025-12-01
