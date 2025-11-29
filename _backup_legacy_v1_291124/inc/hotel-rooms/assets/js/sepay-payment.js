/**
 * SePay Payment Status Checker for Hotel Booking
 * 
 * Kiểm tra trạng thái thanh toán tự động và cập nhật UI
 * 
 * @package VieHotelRooms
 * @version 1.0.0
 */

(function($) {
    'use strict';

    // Payment checker object
    var VieSePayChecker = {
        // Settings
        settings: {
            checkInterval: 5000, // 5 seconds
            maxChecks: 360, // Maximum 30 minutes (360 * 5 seconds)
            currentChecks: 0
        },

        // State
        isPaid: false,
        intervalId: null,

        /**
         * Initialize
         */
        init: function() {
            if (typeof vie_sepay_vars === 'undefined') {
                console.error('VIE SePay: Missing vie_sepay_vars');
                return;
            }

            this.bindEvents();
            this.startChecking();
        },

        /**
         * Bind DOM events
         */
        bindEvents: function() {
            var self = this;

            // Copy account number
            $(document).on('click', '#sepay_copy_account_number', function(e) {
                e.preventDefault();
                self.copyToClipboard(vie_sepay_vars.account_number, $(this));
            });

            // Copy amount
            $(document).on('click', '#sepay_copy_amount', function(e) {
                e.preventDefault();
                self.copyToClipboard(vie_sepay_vars.amount, $(this));
            });

            // Copy transfer content/remark
            $(document).on('click', '#sepay_copy_remark', function(e) {
                e.preventDefault();
                self.copyToClipboard(vie_sepay_vars.remark, $(this));
            });
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text, $button) {
            var self = this;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    self.showCopiedFeedback($button);
                }).catch(function() {
                    self.fallbackCopy(text, $button);
                });
            } else {
                self.fallbackCopy(text, $button);
            }
        },

        /**
         * Fallback copy method
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
         * Show copied feedback
         */
        showCopiedFeedback: function($button) {
            var $icon = $button.find('.copy-icon');
            var originalHtml = $icon.html();
            
            $icon.html('<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#4bbf73" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>');
            $button.addClass('copied');

            setTimeout(function() {
                $icon.html(originalHtml);
                $button.removeClass('copied');
            }, 2000);
        },

        /**
         * Start checking payment status
         */
        startChecking: function() {
            var self = this;

            // Initial check
            this.checkPaymentStatus();

            // Start interval
            this.intervalId = setInterval(function() {
                if (self.isPaid) {
                    self.stopChecking();
                    return;
                }

                self.settings.currentChecks++;

                if (self.settings.currentChecks >= self.settings.maxChecks) {
                    self.stopChecking();
                    self.showTimeout();
                    return;
                }

                self.checkPaymentStatus();
            }, this.settings.checkInterval);
        },

        /**
         * Stop checking
         */
        stopChecking: function() {
            if (this.intervalId) {
                clearInterval(this.intervalId);
                this.intervalId = null;
            }
        },

        /**
         * Check payment status via AJAX
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
                    console.error('VIE SePay: Check status error', error);
                }
            });
        },

        /**
         * Show payment success
         */
        showSuccess: function(data) {
            var self = this;
            var successMessage = vie_sepay_vars.success_message || '<h2 style="color: #73AF55;">Thanh toán thành công!</h2>';

            // Create success notification
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

            // Hide payment info, show success
            $('.sepay-pay-info').fadeOut(300, function() {
                $('.sepay-message').html(successHtml).hide().fadeIn(500);
            });

            $('.sepay-pay-footer').fadeOut(300);

            // Scroll to success message
            setTimeout(function() {
                $('html, body').animate({
                    scrollTop: $('.sepay-message').offset().top - 100
                }, 500);
            }, 300);

            // Optional: Redirect after delay
            if (vie_sepay_vars.redirect_url) {
                setTimeout(function() {
                    window.location.href = vie_sepay_vars.redirect_url;
                }, 5000);
            }
        },

        /**
         * Show timeout message
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

    // Initialize when DOM ready
    $(document).ready(function() {
        if ($('.vie-sepay-payment').length > 0) {
            VieSePayChecker.init();
        }
    });

})(jQuery);
