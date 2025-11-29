/**
 * ============================================================================
 * FILE: core.js
 * ============================================================================
 * 
 * MÔ TẢ:
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
        },

        /**
         * Tính số đêm giữa 2 ngày
         * 
         * @param {Date} checkIn    Ngày check-in
         * @param {Date} checkOut   Ngày check-out
         * @returns {number}        Số đêm
         */
        calculateNights: function(checkIn, checkOut) {
            if (!checkIn || !checkOut) return 0;
            
            var diffTime = checkOut.getTime() - checkIn.getTime();
            var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            
            return diffDays > 0 ? diffDays : 0;
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
            }).addClass('vie-modal-open');
        },

        /**
         * Unlock body scroll
         */
        unlockScroll: function() {
            $('body').css({
                'overflow': '',
                'padding-right': ''
            }).removeClass('vie-modal-open');
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
                '<div class="vie-spinner"></div>' +
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
     * EVENT SYSTEM
     * =========================================================================
     * Hệ thống custom events để các modules giao tiếp với nhau
     */
    vie.events = {

        /**
         * Trigger custom event
         * 
         * @param {string} eventName    Tên event (format: vie:module:action)
         * @param {any} data            Data truyền đi
         * 
         * @example
         * vie.events.trigger('vie:booking:created', { id: 123 });
         */
        trigger: function(eventName, data) {
            $(document).trigger(eventName, [data]);
            
            if (vie.debug) {
                console.log('[vie.events] Triggered:', eventName, data);
            }
        },

        /**
         * Listen custom event
         * 
         * @param {string} eventName    Tên event
         * @param {Function} callback   Callback function
         * 
         * @example
         * vie.events.on('vie:booking:created', function(e, data) {
         *     console.log('Booking created:', data);
         * });
         */
        on: function(eventName, callback) {
            $(document).on(eventName, callback);
        },

        /**
         * Remove event listener
         * 
         * @param {string} eventName    Tên event
         * @param {Function} callback   Callback function (optional)
         */
        off: function(eventName, callback) {
            $(document).off(eventName, callback);
        }
    };

    /**
     * =========================================================================
     * DEBUG MODE
     * =========================================================================
     */
    vie.debug = (typeof vieBooking !== 'undefined' && vieBooking.debug) || false;

    /**
     * =========================================================================
     * INITIALIZATION
     * =========================================================================
     */
    $(document).ready(function() {
        // Log version nếu debug mode
        if (vie.debug) {
            console.log('[vie.core] Initialized v2.0.0');
            console.log('[vie.core] vieBooking:', typeof vieBooking !== 'undefined' ? vieBooking : 'not defined');
        }
    });

})(jQuery);
