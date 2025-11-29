# TASK-04: REFACTOR JAVASCRIPT

**Phase:** 1 - Assets  
**Thời gian:** 2 ngày  
**Độ ưu tiên:** 🟡 HIGH  
**Prerequisite:** TASK-03 hoàn thành  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

1. Tạo file `core.js` với namespace và utilities
2. Tách `frontend.js` (52KB) thành modules nhỏ
3. Tổ chức lại JS admin
4. Áp dụng Module Pattern

---

## 📊 PHÂN TÍCH FILE LEGACY

### Frontend JS (cần tách)

| File Legacy | Size | Tách thành | Ưu tiên |
|-------------|------|------------|---------|
| `frontend.js` | 52KB | 5 modules | P0 |
| `sepay-payment.js` | 8KB | Giữ nguyên | P1 |
| `transport-metabox.js` | 2KB | Giữ nguyên | P2 |

### Admin JS (copy & refactor nhẹ)

| File Legacy | Size | Action |
|-------------|------|--------|
| `common.js` | 1KB | Copy |
| `page-bookings.js` | 4KB | Copy |
| `page-bulk-matrix.js` | 32KB | Copy (refactor sau) |
| `page-calendar.js` | 11KB | Copy |
| `page-rooms.js` | 27KB | Copy (refactor sau) |

---

## 📋 NGÀY 1: CORE.JS & FRONTEND MODULES

### BƯỚC 1: Tạo file core.js

| # | Task | Status |
|---|------|--------|
| 1.1 | Tạo file `assets/js/frontend/core.js` | ⬜ |
| 1.2 | Định nghĩa namespace `vie` | ⬜ |
| 1.3 | Viết utility functions | ⬜ |

**File: `assets/js/frontend/core.js`**
```javascript
/**
 * ============================================================================
 * FILE: core.js
 * ============================================================================
 * 
 * Core utilities và namespace cho tất cả module JS của theme.
 * LOAD FILE NÀY ĐẦU TIÊN trước các module khác.
 * 
 * EXPORTS:
 * - window.vie (global namespace)
 * - vie.utils (utility functions)
 * - vie.ajax (AJAX helpers)
 * - vie.ui (UI helpers)
 * 
 * DEPENDENCIES:
 * - jQuery (WordPress Core)
 * - vieBooking (localized data từ PHP)
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

(function($) {
    'use strict';

    /**
     * =========================================================================
     * GLOBAL NAMESPACE
     * =========================================================================
     * Tạo namespace để tránh conflict với code khác
     */
    window.vie = window.vie || {};

    /**
     * =========================================================================
     * UTILITIES
     * =========================================================================
     */
    vie.utils = {

        /**
         * Format số tiền theo định dạng VND
         * 
         * @param {number} amount       Số tiền
         * @param {boolean} withUnit    Có thêm "VNĐ" không (default: true)
         * @returns {string}            Số tiền đã format
         * 
         * @example
         * vie.utils.formatCurrency(1500000) // "1.500.000 VNĐ"
         * vie.utils.formatCurrency(1500000, false) // "1.500.000"
         */
        formatCurrency: function(amount, withUnit) {
            if (typeof withUnit === 'undefined') withUnit = true;
            
            var formatted = new Intl.NumberFormat('vi-VN').format(amount);
            return withUnit ? formatted + ' VNĐ' : formatted;
        },

        /**
         * Format ngày theo định dạng Việt Nam
         * 
         * @param {Date|string} date    Date object hoặc date string
         * @param {string} format       'short' (dd/mm/yyyy) | 'long' (Thứ X, dd/mm/yyyy)
         * @returns {string}
         */
        formatDate: function(date, format) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            if (!date || isNaN(date.getTime())) {
                return '';
            }
            
            format = format || 'short';
            
            if (format === 'long') {
                var days = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
                var dayName = days[date.getDay()];
                return dayName + ', ' + this.formatDate(date, 'short');
            }
            
            // short format: dd/mm/yyyy
            var dd = String(date.getDate()).padStart(2, '0');
            var mm = String(date.getMonth() + 1).padStart(2, '0');
            var yyyy = date.getFullYear();
            
            return dd + '/' + mm + '/' + yyyy;
        },

        /**
         * Parse date từ format dd/mm/yyyy
         * 
         * @param {string} dateStr  Date string "dd/mm/yyyy"
         * @returns {Date|null}
         */
        parseDateVN: function(dateStr) {
            if (!dateStr) return null;
            
            var parts = dateStr.split('/');
            if (parts.length !== 3) return null;
            
            var day = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1; // JS months are 0-indexed
            var year = parseInt(parts[2], 10);
            
            var date = new Date(year, month, day);
            
            // Validate
            if (date.getDate() !== day || date.getMonth() !== month) {
                return null;
            }
            
            return date;
        },

        /**
         * Debounce function - Trì hoãn thực thi
         * 
         * @param {Function} func   Function cần debounce
         * @param {number} wait     Delay ms
         * @returns {Function}
         */
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this;
                var args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

        /**
         * Throttle function - Giới hạn tần suất thực thi
         * 
         * @param {Function} func   Function cần throttle
         * @param {number} limit    Thời gian tối thiểu giữa các lần gọi (ms)
         * @returns {Function}
         */
        throttle: function(func, limit) {
            var inThrottle;
            return function() {
                var context = this;
                var args = arguments;
                if (!inThrottle) {
                    func.apply(context, args);
                    inThrottle = true;
                    setTimeout(function() {
                        inThrottle = false;
                    }, limit);
                }
            };
        },

        /**
         * Kiểm tra có phải mobile không
         * 
         * @returns {boolean}
         */
        isMobile: function() {
            return window.innerWidth < 768;
        },

        /**
         * Kiểm tra có phải tablet không
         * 
         * @returns {boolean}
         */
        isTablet: function() {
            return window.innerWidth >= 768 && window.innerWidth < 1024;
        },

        /**
         * Scroll mượt tới element
         * 
         * @param {jQuery|string} target    Element hoặc selector
         * @param {number} offset           Offset từ top (default: 100)
         * @param {number} duration         Duration ms (default: 500)
         */
        scrollTo: function(target, offset, duration) {
            var $target = $(target);
            if (!$target.length) return;
            
            offset = offset || 100;
            duration = duration || 500;
            
            $('html, body').animate({
                scrollTop: $target.offset().top - offset
            }, duration);
        },

        /**
         * Generate random string
         * 
         * @param {number} length   Độ dài (default: 8)
         * @returns {string}
         */
        randomString: function(length) {
            length = length || 8;
            var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            var result = '';
            for (var i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        }
    };

    /**
     * =========================================================================
     * AJAX HELPERS
     * =========================================================================
     */
    vie.ajax = {

        /**
         * Gửi AJAX request
         * 
         * @param {string} action       WordPress AJAX action name
         * @param {Object} data         Data gửi đi
         * @param {Object} options      Options bổ sung
         * @returns {jQuery.Deferred}
         * 
         * @example
         * vie.ajax.post('vie_calculate_price', { room_id: 5 })
         *     .done(function(response) { console.log(response); })
         *     .fail(function(error) { console.error(error); });
         */
        post: function(action, data, options) {
            options = options || {};
            
            // Kiểm tra vieBooking có tồn tại không
            if (typeof vieBooking === 'undefined') {
                console.error('[vie.ajax] vieBooking not defined');
                return $.Deferred().reject('vieBooking not defined');
            }
            
            var ajaxData = $.extend({
                action: action,
                nonce: vieBooking.nonce
            }, data);

            return $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                beforeSend: options.beforeSend,
                complete: options.complete
            });
        },

        /**
         * Toggle loading state cho button
         * 
         * @param {jQuery} $btn         Button element
         * @param {boolean} isLoading   Loading state
         */
        toggleButtonLoading: function($btn, isLoading) {
            if (isLoading) {
                $btn.prop('disabled', true)
                    .data('original-text', $btn.html())
                    .html('<span class="vie-spinner"></span> Đang xử lý...');
            } else {
                $btn.prop('disabled', false)
                    .html($btn.data('original-text') || 'Submit');
            }
        }
    };

    /**
     * =========================================================================
     * UI HELPERS
     * =========================================================================
     */
    vie.ui = {

        /**
         * Hiển thị toast notification
         * 
         * @param {string} message      Nội dung message
         * @param {string} type         'success' | 'error' | 'warning' | 'info'
         * @param {number} duration     Duration ms (default: 3000)
         */
        toast: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 3000;
            
            // Remove existing toast
            $('.vie-toast').remove();
            
            var iconMap = {
                success: '✓',
                error: '✕',
                warning: '⚠',
                info: 'ℹ'
            };
            
            var $toast = $('<div class="vie-toast vie-toast--' + type + '">' +
                '<span class="vie-toast__icon">' + iconMap[type] + '</span>' +
                '<span class="vie-toast__message">' + message + '</span>' +
                '</div>');
            
            $('body').append($toast);
            
            // Trigger animation
            setTimeout(function() {
                $toast.addClass('vie-toast--visible');
            }, 10);
            
            // Auto hide
            setTimeout(function() {
                $toast.removeClass('vie-toast--visible');
                setTimeout(function() {
                    $toast.remove();
                }, 300);
            }, duration);
        },

        /**
         * Hiển thị confirm dialog
         * 
         * @param {string} message      Message confirm
         * @param {Object} options      { title, confirmText, cancelText }
         * @returns {Promise}
         */
        confirm: function(message, options) {
            options = options || {};
            
            return new Promise(function(resolve, reject) {
                if (window.confirm(message)) {
                    resolve(true);
                } else {
                    reject(false);
                }
            });
        },

        /**
         * Lock body scroll (khi mở modal)
         */
        lockScroll: function() {
            var scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
            $('body').css({
                'overflow': 'hidden',
                'padding-right': scrollbarWidth + 'px'
            });
        },

        /**
         * Unlock body scroll
         */
        unlockScroll: function() {
            $('body').css({
                'overflow': '',
                'padding-right': ''
            });
        },

        /**
         * Show loading overlay
         * 
         * @param {jQuery|string} container     Container element
         * @param {string} message              Loading message (optional)
         */
        showLoading: function(container, message) {
            var $container = $(container);
            message = message || 'Đang tải...';
            
            var $overlay = $('<div class="vie-loading-overlay">' +
                '<div class="vie-loading-spinner"></div>' +
                '<div class="vie-loading-text">' + message + '</div>' +
                '</div>');
            
            $container.css('position', 'relative').append($overlay);
        },

        /**
         * Hide loading overlay
         * 
         * @param {jQuery|string} container     Container element
         */
        hideLoading: function(container) {
            $(container).find('.vie-loading-overlay').remove();
        }
    };

    /**
     * =========================================================================
     * INITIALIZATION
     * =========================================================================
     */
    $(document).ready(function() {
        // Log version nếu debug mode
        if (typeof vieBooking !== 'undefined' && vieBooking.debug) {
            console.log('[vie.core] Initialized v2.0.0');
        }
    });

})(jQuery);
```

---

### BƯỚC 2: Tạo file datepicker.js

| # | Task | Status |
|---|------|--------|
| 2.1 | Tạo file `assets/js/frontend/datepicker.js` | ⬜ |
| 2.2 | Tách logic datepicker từ legacy `frontend.js` | ⬜ |
| 2.3 | Refactor theo Module Pattern | ⬜ |

**Template structure:**
```javascript
/**
 * ============================================================================
 * FILE: datepicker.js
 * ============================================================================
 * 
 * Module xử lý jQuery UI Datepicker với price display
 * 
 * DEPENDENCIES:
 * - jQuery
 * - jQuery UI Datepicker
 * - core.js (vie.utils, vie.ajax)
 * - vieBooking (localized data)
 * ============================================================================
 */

(function($) {
    'use strict';

    /**
     * Module: VieDatepicker
     */
    var VieDatepicker = {
        
        // Cache giá theo ngày
        priceCache: {},
        
        // Config mặc định
        defaults: {
            dateFormat: 'dd/mm/yy',
            minDate: 0,
            showOtherMonths: true,
            selectOtherMonths: true
        },
        
        /**
         * Khởi tạo datepicker
         * 
         * @param {jQuery} $input   Input element
         * @param {Object} options  Custom options
         */
        init: function($input, options) {
            // TODO: Implement - copy từ legacy initDatepickers()
        },
        
        /**
         * Lấy giá cho tháng
         * 
         * @param {number} year
         * @param {number} month
         */
        fetchMonthPrices: function(year, month) {
            // TODO: Implement - copy từ legacy preloadCalendarPrices()
        },
        
        /**
         * Render giá vào calendar cell
         * 
         * @param {Date} date
         */
        renderPriceCell: function(date) {
            // TODO: Implement - copy từ legacy beforeShowDay
        }
    };
    
    // Export
    window.VieDatepicker = VieDatepicker;
    vie.datepicker = VieDatepicker;

})(jQuery);
```

---

### BƯỚC 3: Tạo file room-listing.js

| # | Task | Status |
|---|------|--------|
| 3.1 | Tạo file `assets/js/frontend/room-listing.js` | ⬜ |
| 3.2 | Tách logic room grid, filters, swiper từ legacy | ⬜ |
| 3.3 | Refactor theo Module Pattern | ⬜ |

---

### BƯỚC 4: Tạo file booking-popup.js

| # | Task | Status |
|---|------|--------|
| 4.1 | Tạo file `assets/js/frontend/booking-popup.js` | ⬜ |
| 4.2 | Tách logic popup, steps, form, price calculation từ legacy | ⬜ |
| 4.3 | Refactor theo Module Pattern | ⬜ |

**Đây là file lớn nhất, cần tách các methods:**
- `openPopup()` / `closePopup()`
- `initSteps()` / `nextStep()` / `prevStep()`
- `collectFormData()` / `validateForm()`
- `calculatePrice()` / `displayPrice()`
- `submitBooking()`

---

## 📋 NGÀY 2: ADMIN JS & FINALIZE

### BƯỚC 5: Copy và organize Admin JS

| # | Task | Command | Status |
|---|------|---------|--------|
| 5.1 | Copy common.js | `cp _backup_legacy_*/inc/hotel-rooms/assets/admin/js/common.js assets/js/admin/` | ⬜ |
| 5.2 | Copy page-bookings.js | `cp ...page-bookings.js assets/js/admin/booking-manager.js` | ⬜ |
| 5.3 | Copy page-calendar.js | `cp ...page-calendar.js assets/js/admin/calendar-manager.js` | ⬜ |
| 5.4 | Copy page-rooms.js | `cp ...page-rooms.js assets/js/admin/room-manager.js` | ⬜ |
| 5.5 | Copy page-bulk-matrix.js | `cp ...page-bulk-matrix.js assets/js/admin/bulk-matrix.js` | ⬜ |

---

### BƯỚC 6: Thêm header comment cho Admin JS

| # | Task | Status |
|---|------|--------|
| 6.1 | Thêm header block cho mỗi file admin | ⬜ |
| 6.2 | Đổi tên biến global nếu cần | ⬜ |

---

### BƯỚC 7: Copy Frontend JS còn lại

| # | Task | Status |
|---|------|--------|
| 7.1 | Copy `sepay-payment.js` → `assets/js/frontend/payment.js` | ⬜ |
| 7.2 | Copy `transport-metabox.js` → `assets/js/admin/transport-metabox.js` | ⬜ |
| 7.3 | Copy `coupon-form.js` → `assets/js/frontend/coupon.js` | ⬜ |

---

### BƯỚC 8: Cập nhật inc/hooks/assets.php

| # | Task | Status |
|---|------|--------|
| 8.1 | Thêm logic load JS modules | ⬜ |
| 8.2 | Cập nhật wp_localize_script | ⬜ |

**Thêm vào function `vie_enqueue_frontend_assets()`:**
```php
// JS Modules
wp_enqueue_script(
    'vie-core',
    VIE_THEME_URL . '/assets/js/frontend/core.js',
    ['jquery'],
    VIE_THEME_VERSION,
    true
);

wp_enqueue_script(
    'vie-datepicker',
    VIE_THEME_URL . '/assets/js/frontend/datepicker.js',
    ['vie-core', 'jquery-ui-datepicker'],
    VIE_THEME_VERSION,
    true
);

wp_enqueue_script(
    'vie-room-listing',
    VIE_THEME_URL . '/assets/js/frontend/room-listing.js',
    ['vie-core'],
    VIE_THEME_VERSION,
    true
);

wp_enqueue_script(
    'vie-booking-popup',
    VIE_THEME_URL . '/assets/js/frontend/booking-popup.js',
    ['vie-core', 'vie-datepicker'],
    VIE_THEME_VERSION,
    true
);

// Localize cho core.js
wp_localize_script('vie-core', 'vieBooking', [
    'ajaxUrl'     => admin_url('admin-ajax.php'),
    'nonce'       => wp_create_nonce('vie_booking_nonce'),
    'hotelId'     => get_the_ID(),
    'homeUrl'     => home_url(),
    'checkoutUrl' => home_url('/checkout/'),
    'currency'    => 'VNĐ',
    'dateFormat'  => 'dd/mm/yy',
    'debug'       => defined('WP_DEBUG') && WP_DEBUG,
    'i18n'        => [
        'selectDates'     => 'Vui lòng chọn ngày',
        'calculating'     => 'Đang tính giá...',
        'error'           => 'Có lỗi xảy ra',
        // ... thêm các strings khác
    ]
]);
```

---

### BƯỚC 9: Testing JavaScript

| # | Test Case | Expected | Status |
|---|-----------|----------|--------|
| 9.1 | Console không có lỗi | No errors | ⬜ |
| 9.2 | vie namespace tồn tại | `typeof vie === 'object'` | ⬜ |
| 9.3 | Datepicker mở được | Calendar hiển thị | ⬜ |
| 9.4 | Filter rooms hoạt động | Rooms filter đúng | ⬜ |
| 9.5 | Booking popup mở được | Popup hiển thị | ⬜ |
| 9.6 | Tính giá hoạt động | Giá hiển thị | ⬜ |
| 9.7 | Submit booking | Redirect checkout | ⬜ |
| 9.8 | Admin tables hoạt động | AJAX load đúng | ⬜ |

---

### BƯỚC 10: Commit

| # | Task | Command | Status |
|---|------|---------|--------|
| 10.1 | Git add | `git add assets/js/ inc/hooks/assets.php` | ⬜ |
| 10.2 | Git commit | `git commit -m "feat: refactor JavaScript thành modules"` | ⬜ |
| 10.3 | Git push | `git push origin main` | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] File `core.js` đã tạo với namespace `vie` và utilities
- [ ] Frontend JS đã tách thành modules: `datepicker.js`, `room-listing.js`, `booking-popup.js`
- [ ] Admin JS đã copy và có header comments
- [ ] `inc/hooks/assets.php` load JS đúng thứ tự dependencies
- [ ] Không có lỗi JavaScript trong Console
- [ ] Tất cả chức năng hoạt động như cũ
- [ ] Đã commit và push

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-05-PHP-CLASSES.md](./TASK-05-PHP-CLASSES.md)**
