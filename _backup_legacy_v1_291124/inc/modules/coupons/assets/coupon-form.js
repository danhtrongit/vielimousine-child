/**
 * Google Sheets Coupon System - Frontend JavaScript
 * 
 * Xử lý coupon form và AJAX calls
 */

(function($) {
    'use strict';
    
    const VLCoupon = {
        
        /**
         * Current validated coupon
         */
        currentCoupon: null,
        
        /**
         * Initialize
         */
        init() {
            this.cacheElements();
            this.bindEvents();
        },
        
        /**
         * Cache DOM elements
         */
        cacheElements() {
            this.$form = $('#vl-coupon-form');
            this.$input = $('#vl-coupon-code');
            this.$btnValidate = $('#vl-coupon-validate');
            this.$btnRemove = $('#vl-coupon-remove');
            this.$message = $('#vl-coupon-message');
            this.$discountDisplay = $('#vl-coupon-discount');
            this.$finalTotal = $('#vl-final-total');
            this.$hiddenCouponCode = $('#vl-hidden-coupon-code');
            this.$hiddenCouponDiscount = $('#vl-hidden-coupon-discount');
        },
        
        /**
         * Bind events
         */
        bindEvents() {
            this.$btnValidate.on('click', (e) => {
                e.preventDefault();
                this.validateCoupon();
            });
            
            this.$btnRemove.on('click', (e) => {
                e.preventDefault();
                this.removeCoupon();
            });
            
            this.$input.on('keypress', (e) => {
                if (e.which === 13) { // Enter key
                    e.preventDefault();
                    this.validateCoupon();
                }
            });
            
            // Clear message khi nhập lại
            this.$input.on('input', () => {
                if (this.currentCoupon) {
                    this.removeCoupon();
                }
            });
        },
        
        /**
         * Validate coupon via AJAX
         */
        validateCoupon() {
            const code = this.$input.val().trim().toUpperCase();
            
            if (!code) {
                this.showMessage('Vui lòng nhập mã giảm giá', 'error');
                return;
            }
            
            const orderTotal = this.getOrderTotal();
            
            // Show loading
            this.$btnValidate.prop('disabled', true).text('Đang kiểm tra...');
            this.showMessage('Đang xác thực mã giảm giá...', 'info');
            
            $.ajax({
                url: vlCouponData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vl_validate_coupon',
                    coupon_code: code,
                    order_total: orderTotal,
                    nonce: vlCouponData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.onCouponValid(response.data);
                    } else {
                        this.showMessage(response.data.message, 'error');
                        this.$btnValidate.prop('disabled', false).text('Áp dụng');
                    }
                },
                error: (xhr) => {
                    const message = xhr.responseJSON?.data?.message || 'Có lỗi xảy ra. Vui lòng thử lại.';
                    this.showMessage(message, 'error');
                    this.$btnValidate.prop('disabled', false).text('Áp dụng');
                }
            });
        },
        
        /**
         * On coupon valid callback
         */
        onCouponValid(data) {
            this.currentCoupon = {
                code: data.coupon_code,
                discount: data.discount,
                type: data.discount_type
            };
            
            // Update UI
            this.$input.prop('readonly', true);
            this.$btnValidate.hide();
            this.$btnRemove.show();
            
            // Show success message
            this.showMessage(data.message, 'success');
            
            // Update discount display
            this.updateTotalDisplay(data.discount);
            
            // Store in hidden fields for form submission
            this.$hiddenCouponCode.val(data.coupon_code);
            this.$hiddenCouponDiscount.val(data.discount);
        },
        
        /**
         * Remove coupon
         */
        removeCoupon() {
            this.currentCoupon = null;
            
            // Reset UI
            this.$input.val('').prop('readonly', false);
            this.$btnValidate.show().prop('disabled', false).text('Áp dụng');
            this.$btnRemove.hide();
            this.$message.fadeOut();
            
            // Reset totals
            this.updateTotalDisplay(0);
            
            // Clear hidden fields
            this.$hiddenCouponCode.val('');
            this.$hiddenCouponDiscount.val('');
        },
        
        /**
         * Update total display
         */
        updateTotalDisplay(discount) {
            const orderTotal = this.getOrderTotal();
            const finalTotal = Math.max(0, orderTotal - discount);
            
            if (discount > 0) {
                this.$discountDisplay
                    .html(`<strong>Giảm giá:</strong> -${this.formatCurrency(discount)}`)
                    .fadeIn();
            } else {
                this.$discountDisplay.fadeOut();
            }
            
            if (this.$finalTotal.length) {
                this.$finalTotal.html(this.formatCurrency(finalTotal));
            }
        },
        
        /**
         * Get order total from page
         */
        getOrderTotal() {
            // Tùy chỉnh selector dựa trên structure HTML của trang checkout
            const totalText = $('#order-total').text() || '0';
            return parseFloat(totalText.replace(/[^\d]/g, '')) || 0;
        },
        
        /**
         * Format currency (VNĐ)
         */
        formatCurrency(amount) {
            return new Intl.NumberFormat('vi-VN', {
                style: 'currency',
                currency: 'VND'
            }).format(amount);
        },
        
        /**
         * Show message
         */
        showMessage(message, type) {
            this.$message
                .removeClass('success error info warning')
                .addClass(type)
                .html(message)
                .fadeIn();
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => {
        VLCoupon.init();
    });
    
    // Expose globally nếu cần
    window.VLCoupon = VLCoupon;
    
})(jQuery);
