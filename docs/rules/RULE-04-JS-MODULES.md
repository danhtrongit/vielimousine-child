# RULE-04: QUY CHUẨN JAVASCRIPT MODULES

**Phiên bản:** 1.0  
**Áp dụng cho:** Tất cả file JavaScript trong theme  
**Bắt buộc:** ✅ CÓ

---

## 1. CẤU TRÚC THƯ MỤC JS

```
assets/js/
├── admin/                      # JS cho Admin Panel
│   ├── common.js               # Utilities dùng chung
│   ├── room-manager.js         # Quản lý CRUD phòng
│   ├── booking-manager.js      # Quản lý booking
│   ├── calendar-manager.js     # Lịch giá
│   └── bulk-matrix.js          # Cập nhật giá hàng loạt
│
└── frontend/                   # JS cho Frontend
    ├── core.js                 # ★ Core utilities - load đầu tiên
    ├── datepicker.js           # Datepicker module
    ├── room-listing.js         # Room cards, filters, swiper
    ├── booking-popup.js        # Modal đặt phòng
    └── payment.js              # Thanh toán SePay
```

---

## 2. MODULE PATTERN

Sử dụng **Revealing Module Pattern** hoặc **IIFE** để đóng gói code.

### Template cơ bản

```javascript
/**
 * ============================================================================
 * FILE: module-name.js
 * ============================================================================
 * 
 * [Mô tả module]
 * 
 * DEPENDENCIES:
 * - jQuery
 * - core.js (vie.utils)
 * - vieBooking (localized data)
 * ============================================================================
 */

(function($) {
    'use strict';

    /**
     * =========================================================================
     * MODULE: VieModuleName
     * =========================================================================
     */
    var VieModuleName = {

        /**
         * ---------------------------------------------------------------------
         * PROPERTIES
         * ---------------------------------------------------------------------
         */
        
        /** @type {jQuery} Cached element */
        $container: null,
        
        /** @type {boolean} Module initialized flag */
        initialized: false,

        /**
         * ---------------------------------------------------------------------
         * INITIALIZATION
         * ---------------------------------------------------------------------
         */

        /**
         * Khởi tạo module
         * Gọi khi document ready
         */
        init: function() {
            if (this.initialized) return;
            
            this.cacheElements();
            this.bindEvents();
            this.initialized = true;
            
            console.log('[VieModuleName] Initialized');
        },

        /**
         * Cache jQuery elements để tái sử dụng
         */
        cacheElements: function() {
            this.$container = $('#vie-container');
        },

        /**
         * Bind tất cả event listeners
         */
        bindEvents: function() {
            var self = this;
            
            this.$container.on('click', '.js-action', function(e) {
                e.preventDefault();
                self.handleAction($(this));
            });
        },

        /**
         * ---------------------------------------------------------------------
         * PUBLIC METHODS
         * ---------------------------------------------------------------------
         */

        /**
         * Xử lý action
         * 
         * @param {jQuery} $trigger Element trigger event
         */
        handleAction: function($trigger) {
            // Implementation
        },

        /**
         * ---------------------------------------------------------------------
         * PRIVATE METHODS
         * ---------------------------------------------------------------------
         */

        /**
         * Helper method nội bộ
         * @private
         */
        _privateHelper: function() {
            // Implementation
        }
    };

    /**
     * =========================================================================
     * INITIALIZATION
     * =========================================================================
     */
    $(document).ready(function() {
        VieModuleName.init();
    });

    // Export to global namespace nếu cần
    window.VieModuleName = VieModuleName;

})(jQuery);
```

---

## 3. CORE.JS - NAMESPACE & UTILITIES

File `core.js` định nghĩa namespace global và utilities dùng chung.

```javascript
/**
 * ============================================================================
 * FILE: core.js
 * ============================================================================
 * 
 * Core utilities và namespace cho tất cả module JS của theme.
 * PHẢI load file này đầu tiên trước các module khác.
 * 
 * EXPORTS:
 * - window.vie (global namespace)
 * - vie.utils (utility functions)
 * - vie.ajax (AJAX helpers)
 * - vie.ui (UI helpers)
 * 
 * DEPENDENCIES:
 * - jQuery
 * - vieBooking (localized data từ PHP)
 * ============================================================================
 */

(function($) {
    'use strict';

    /**
     * =========================================================================
     * GLOBAL NAMESPACE
     * =========================================================================
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
         * @param {number} amount   Số tiền
         * @param {boolean} withUnit Có thêm "VNĐ" không
         * @returns {string}
         */
        formatCurrency: function(amount, withUnit) {
            withUnit = withUnit !== false;
            var formatted = new Intl.NumberFormat('vi-VN').format(amount);
            return withUnit ? formatted + ' VNĐ' : formatted;
        },

        /**
         * Format ngày theo định dạng Việt Nam
         * 
         * @param {Date|string} date    Date object hoặc date string
         * @param {string} format       Format output: 'short' | 'long' | 'iso'
         * @returns {string}
         */
        formatDate: function(date, format) {
            if (typeof date === 'string') {
                date = new Date(date);
            }
            
            format = format || 'short';
            
            switch (format) {
                case 'long':
                    return date.toLocaleDateString('vi-VN', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                case 'iso':
                    return date.toISOString().split('T')[0];
                default: // short
                    return date.toLocaleDateString('vi-VN');
            }
        },

        /**
         * Debounce function
         * 
         * @param {Function} func   Function cần debounce
         * @param {number} wait     Delay ms
         * @returns {Function}
         */
        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
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
            return new Date(parts[2], parts[1] - 1, parts[0]);
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
         * Scroll tới element
         * 
         * @param {jQuery|string} target    Element hoặc selector
         * @param {number} offset           Offset từ top (default: 100)
         */
        scrollTo: function(target, offset) {
            var $target = $(target);
            if (!$target.length) return;
            
            offset = offset || 100;
            $('html, body').animate({
                scrollTop: $target.offset().top - offset
            }, 500);
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
         */
        post: function(action, data, options) {
            options = options || {};
            
            var ajaxData = $.extend({
                action: action,
                nonce: vieBooking.nonce
            }, data);

            return $.ajax({
                url: vieBooking.ajaxUrl,
                type: 'POST',
                data: ajaxData,
                beforeSend: options.beforeSend,
                success: options.success,
                error: options.error,
                complete: options.complete
            });
        },

        /**
         * Hiển thị loading state cho button
         * 
         * @param {jQuery} $btn     Button element
         * @param {boolean} loading Loading state
         */
        toggleButtonLoading: function($btn, loading) {
            if (loading) {
                $btn.prop('disabled', true)
                    .data('original-text', $btn.text())
                    .text(vieBooking.i18n.calculating || 'Đang xử lý...');
            } else {
                $btn.prop('disabled', false)
                    .text($btn.data('original-text') || 'Submit');
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
         * @param {string} message  Nội dung message
         * @param {string} type     Type: 'success' | 'error' | 'warning' | 'info'
         * @param {number} duration Duration ms (default: 3000)
         */
        toast: function(message, type, duration) {
            type = type || 'info';
            duration = duration || 3000;

            var $toast = $('<div class="vie-toast vie-toast--' + type + '">')
                .text(message)
                .appendTo('body');

            setTimeout(function() {
                $toast.addClass('vie-toast--visible');
            }, 10);

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
         * @param {Function} onConfirm  Callback khi confirm
         * @param {Function} onCancel   Callback khi cancel
         */
        confirm: function(message, onConfirm, onCancel) {
            if (window.confirm(message)) {
                if (typeof onConfirm === 'function') onConfirm();
            } else {
                if (typeof onCancel === 'function') onCancel();
            }
        },

        /**
         * Lock body scroll (khi mở modal)
         */
        lockScroll: function() {
            $('body').css('overflow', 'hidden');
        },

        /**
         * Unlock body scroll
         */
        unlockScroll: function() {
            $('body').css('overflow', '');
        }
    };

    /**
     * =========================================================================
     * GLOBAL EVENTS
     * =========================================================================
     */
    $(document).ready(function() {
        console.log('[vie.core] Initialized');
    });

})(jQuery);
```

---

## 4. EVENTS SYSTEM

Sử dụng custom events để modules giao tiếp với nhau.

### Trigger Events

```javascript
// Trong module booking-popup.js
VieBookingPopup.onPriceCalculated = function(data) {
    // Trigger custom event
    $(document).trigger('vie:price:calculated', [data]);
    
    // Hoặc dùng vie namespace
    $(document).trigger('vie:booking:priceUpdated', [{
        roomId: data.room_id,
        total: data.grand_total,
        breakdown: data.price_breakdown
    }]);
};
```

### Listen Events

```javascript
// Trong module khác
$(document).on('vie:price:calculated', function(e, data) {
    console.log('Giá mới:', data.total);
    // Update UI...
});
```

### Event Naming Convention

```
vie:[module]:[action]

Ví dụ:
- vie:booking:opened      # Popup đặt phòng mở
- vie:booking:closed      # Popup đóng
- vie:booking:submitted   # Form đã submit
- vie:price:calculating   # Đang tính giá
- vie:price:calculated    # Đã tính xong
- vie:room:selected       # Chọn phòng
- vie:modal:opened        # Modal mở
- vie:modal:closed        # Modal đóng
```

---

## 5. AJAX PATTERN

### Standard AJAX Call

```javascript
/**
 * Tính giá booking
 */
calculatePrice: function() {
    var self = this;
    var formData = this.collectFormData();
    
    // Hiển thị loading
    this.showPriceLoading();
    
    vie.ajax.post('vie_frontend_calculate_price', formData, {
        success: function(response) {
            if (response.success) {
                self.pricingData = response.data;
                self.displayPrice(response.data);
                
                // Trigger event cho modules khác
                $(document).trigger('vie:price:calculated', [response.data]);
            } else {
                self.showPriceError(response.data.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('[VieBooking] AJAX Error:', error);
            self.showPriceError(vieBooking.i18n.error);
        },
        complete: function() {
            self.hidePriceLoading();
        }
    });
}
```

### Chained AJAX

```javascript
/**
 * Submit booking (multi-step)
 */
submitBooking: function() {
    var self = this;
    
    // Step 1: Validate
    this.validateForm()
        .then(function() {
            // Step 2: Calculate final price
            return self.calculateFinalPrice();
        })
        .then(function(priceData) {
            // Step 3: Create booking
            return self.createBooking(priceData);
        })
        .then(function(bookingData) {
            // Step 4: Redirect to checkout
            window.location.href = bookingData.checkout_url;
        })
        .catch(function(error) {
            self.showError(error.message);
        });
}
```

---

## 6. ERROR HANDLING

```javascript
/**
 * Standard error handler
 */
handleAjaxError: function(xhr, status, error) {
    var message = vieBooking.i18n.error; // Default message
    
    // Try to get server error message
    if (xhr.responseJSON && xhr.responseJSON.data) {
        message = xhr.responseJSON.data.message || message;
    }
    
    // Log for debugging
    console.error('[VieBooking] Error:', {
        status: status,
        error: error,
        response: xhr.responseJSON
    });
    
    // Show user-friendly message
    vie.ui.toast(message, 'error');
},

/**
 * Validate form before submit
 * 
 * @returns {boolean|string} True if valid, error message if invalid
 */
validateForm: function() {
    var errors = [];
    
    // Check required fields
    var $requiredFields = this.$form.find('[required]');
    $requiredFields.each(function() {
        var $field = $(this);
        if (!$field.val().trim()) {
            errors.push($field.attr('name') + ' là bắt buộc');
            $field.addClass('is-invalid');
        } else {
            $field.removeClass('is-invalid');
        }
    });
    
    // Check email format
    var $email = this.$form.find('input[type="email"]');
    if ($email.val() && !this._isValidEmail($email.val())) {
        errors.push('Email không hợp lệ');
        $email.addClass('is-invalid');
    }
    
    // Check phone format
    var $phone = this.$form.find('input[name="customer_phone"]');
    if ($phone.val() && !this._isValidPhone($phone.val())) {
        errors.push('Số điện thoại không hợp lệ');
        $phone.addClass('is-invalid');
    }
    
    if (errors.length > 0) {
        return errors.join('. ');
    }
    
    return true;
}
```

---

## 7. PERFORMANCE BEST PRACTICES

### Cache Elements

```javascript
// ✅ ĐÚNG - Cache một lần
init: function() {
    this.$container = $('#vie-booking-popup');
    this.$form = this.$container.find('form');
    this.$priceDisplay = this.$container.find('.vie-price-display');
}

// ❌ SAI - Query mỗi lần
handleClick: function() {
    $('#vie-booking-popup').find('form').submit(); // Query 2 lần!
}
```

### Debounce Input Events

```javascript
// ✅ ĐÚNG - Debounce input
$('#search-input').on('input', vie.utils.debounce(function() {
    self.search($(this).val());
}, 300));

// ❌ SAI - Fire mỗi keystroke
$('#search-input').on('input', function() {
    self.search($(this).val()); // Quá nhiều requests!
});
```

### Event Delegation

```javascript
// ✅ ĐÚNG - Delegate events
this.$container.on('click', '.vie-room-card', this.handleRoomClick.bind(this));

// ❌ SAI - Bind trực tiếp (không work với dynamic elements)
$('.vie-room-card').on('click', this.handleRoomClick.bind(this));
```

---

## 8. CHECKLIST REVIEW

- [ ] File có header block đầy đủ?
- [ ] Sử dụng IIFE để đóng gói?
- [ ] `'use strict';` ở đầu IIFE?
- [ ] Elements được cache trong init?
- [ ] Events dùng delegation cho dynamic elements?
- [ ] Input events có debounce?
- [ ] AJAX có error handling?
- [ ] Console.log chỉ dùng cho development?
- [ ] Custom events follow naming convention?
