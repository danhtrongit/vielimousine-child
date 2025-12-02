/**
 * ============================================================================
 * TÊN FILE: payment.js
 * ============================================================================
 * 
 * MÔ TẢ:
 * JavaScript xử lý thanh toán SePay cho trang checkout
 * - Tự động kiểm tra trạng thái thanh toán
 * - Copy thông tin tài khoản
 * - Hiển thị thông báo khi thanh toán thành công
 * 
 * @package     VielimousineChild
 * @subpackage  Assets/JS
 * @version     2.0.0
 * @since       2.0.0 (Migrated from V1)
 * ============================================================================
 */

(function($) {
    'use strict';

    /**
     * =========================================================================
     * VIE SEPAY PAYMENT CHECKER
     * =========================================================================
     * 
     * Kiểm tra trạng thái thanh toán tự động và cập nhật UI
     */
    var VieSePayChecker = {
        
        // =====================================================================
        // SETTINGS
        // =====================================================================
        settings: {
            checkInterval: 5000,    // 5 giây
            maxChecks: 360,         // Tối đa 30 phút (360 * 5 giây)
            currentChecks: 0
        },

        // =====================================================================
        // STATE
        // =====================================================================
        isPaid: false,
        intervalId: null,

        /**
         * ---------------------------------------------------------------------
         * KHỞI TẠO
         * ---------------------------------------------------------------------
         */
        init: function() {
            // Kiểm tra biến cấu hình
            if (typeof vie_sepay_vars === 'undefined') {
                console.error('[VIE SePay] Thiếu cấu hình vie_sepay_vars');
                return;
            }

            this.bindEvents();
            this.startChecking();
            
            console.log('[VIE SePay] Đã khởi tạo, bắt đầu kiểm tra thanh toán...');
        },

        /**
         * ---------------------------------------------------------------------
         * BIND EVENTS
         * ---------------------------------------------------------------------
         */
        bindEvents: function() {
            var self = this;

            // Copy số tài khoản
            $(document).on('click', '#sepay_copy_account_number', function(e) {
                e.preventDefault();
                self.copyToClipboard(vie_sepay_vars.account_number, $(this));
            });

            // Copy số tiền
            $(document).on('click', '#sepay_copy_amount', function(e) {
                e.preventDefault();
                self.copyToClipboard(vie_sepay_vars.amount, $(this));
            });

            // Copy nội dung chuyển khoản
            $(document).on('click', '#sepay_copy_remark', function(e) {
                e.preventDefault();
                self.copyToClipboard(vie_sepay_vars.remark, $(this));
            });
            
            // Generic copy button handler
            $(document).on('click', '.sepay-copy-btn[data-copy]', function(e) {
                e.preventDefault();
                var textToCopy = $(this).data('copy');
                self.copyToClipboard(textToCopy, $(this));
            });
        },

        /**
         * ---------------------------------------------------------------------
         * COPY TO CLIPBOARD
         * ---------------------------------------------------------------------
         * 
         * Sử dụng Clipboard API (modern) hoặc fallback (legacy)
         */
        copyToClipboard: function(text, $button) {
            var self = this;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                // Modern browsers
                navigator.clipboard.writeText(text).then(function() {
                    self.showCopiedFeedback($button);
                }).catch(function() {
                    self.fallbackCopy(text, $button);
                });
            } else {
                // Fallback for older browsers
                self.fallbackCopy(text, $button);
            }
        },

        /**
         * Fallback copy method cho browsers cũ
         */
        fallbackCopy: function(text, $button) {
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(text).select();
            document.execCommand('copy');
            $temp.remove();
            this.showCopiedFeedback($button);
        },

        /**
         * Hiển thị feedback sau khi copy
         */
        showCopiedFeedback: function($button) {
            var $icon = $button.find('.copy-icon');
            var originalHtml = $icon.html();
            
            // Thay icon thành checkmark
            $icon.html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4bbf73" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>');
            $button.addClass('copied');

            // Reset sau 2 giây
            setTimeout(function() {
                $icon.html(originalHtml);
                $button.removeClass('copied');
            }, 2000);
        },

        /**
         * ---------------------------------------------------------------------
         * PAYMENT STATUS CHECKING
         * ---------------------------------------------------------------------
         */
        
        /**
         * Bắt đầu kiểm tra trạng thái thanh toán
         */
        startChecking: function() {
            var self = this;

            // Kiểm tra lần đầu
            this.checkPaymentStatus();

            // Bắt đầu interval
            this.intervalId = setInterval(function() {
                if (self.isPaid) {
                    self.stopChecking();
                    return;
                }

                self.settings.currentChecks++;

                // Kiểm tra timeout
                if (self.settings.currentChecks >= self.settings.maxChecks) {
                    self.stopChecking();
                    self.showTimeout();
                    return;
                }

                self.checkPaymentStatus();
            }, this.settings.checkInterval);
        },

        /**
         * Dừng kiểm tra
         */
        stopChecking: function() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },

        /**
         * Gọi AJAX kiểm tra trạng thái thanh toán
         */
        checkPaymentStatus: function() {
            var self = this;

            $.ajax({
                url: vie_sepay_vars.ajax_url,
                type: 'POST',
                data: {
                    action: 'vie_check_booking_payment',
                    nonce: vie_sepay_vars.nonce,
                    booking_id: vie_sepay_vars.booking_id,
                    booking_hash: vie_sepay_vars.booking_hash
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.data.is_paid) {
                        self.isPaid = true;
                        self.showSuccess(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[VIE SePay] Lỗi kiểm tra trạng thái:', error);
                }
            });
        },

        /**
         * ---------------------------------------------------------------------
         * UI NOTIFICATIONS
         * ---------------------------------------------------------------------
         */
        
        /**
         * Hiển thị thông báo thanh toán thành công
         */
        showSuccess: function(data) {
            var successMessage = vie_sepay_vars.success_message || '<h2 style="color: #73AF55;">Thanh toán thành công!</h2>';

            // Tạo HTML thông báo thành công
            var successHtml = '<div class="sepay-paid-notification">' +
                '<div class="paid-icon">' +
                    '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">' +
                        '<circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>' +
                        '<polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5"/>' +
                    '</svg>' +
                '</div>' +
                '<div class="paid-message">' + successMessage + '</div>' +
                '<div class="paid-booking-code">' +
                    '<span>Mã đặt phòng: <strong>' + data.booking_code + '</strong></span>' +
                '</div>' +
            '</div>';

            // Ẩn thông tin thanh toán, hiện thông báo thành công
            $('.sepay-pay-info').fadeOut(300, function() {
                $('.sepay-message').html(successHtml).hide().fadeIn(500);
            });

            $('.sepay-pay-footer').fadeOut(300);

            // Scroll đến thông báo
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $('.sepay-message').offset().top - 100
                }, 500);
            }, 300);

            // Redirect nếu có cấu hình
            if (vie_sepay_vars.redirect_url) {
                setTimeout(function() {
                    window.location.href = vie_sepay_vars.redirect_url;
                }, 5000);
            }
        },

        /**
         * Hiển thị thông báo timeout
         */
        showTimeout: function() {
            var timeoutHtml = '<div class="sepay-timeout-notification">' +
                '<p>⏱️ Đã hết thời gian chờ thanh toán tự động.</p>' +
                '<p>Nếu bạn đã chuyển khoản, vui lòng liên hệ với chúng tôi để xác nhận.</p>' +
                '<button type="button" class="btn btn-primary" onclick="location.reload()">🔄 Tải lại trang</button>' +
            '</div>';

            $('.sepay-pay-footer').html(timeoutHtml);
        }
    };

    /**
     * =========================================================================
     * INITIALIZATION
     * =========================================================================
     */
    $(document).ready(function() {
        // Khởi tạo khi có payment container
        if ($('.vie-sepay-payment').length > 0) {
            VieSePayChecker.init();
        }
    });

})(jQuery);
