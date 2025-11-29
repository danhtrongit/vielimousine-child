<?php
/**
 * Google Sheets Coupon System - Coupon Validator
 * 
 * Class chứa business logic validate mã giảm giá
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

class VL_Coupon_Validator
{

    /**
     * Cache Manager instance
     * @var VL_Cache_Manager
     */
    private $cache;

    /**
     * Google Sheets API instance
     * @var VL_Google_Sheets_API
     */
    private $sheets_api;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cache = VL_Cache_Manager::get_instance();
        $this->sheets_api = new VL_Google_Sheets_API();
    }

    /**
     * Validate mã coupon với đơn hàng
     * 
     * @param string $code Mã coupon
     * @param float $order_total Tổng giá trị đơn hàng
     * @return array Result: ['valid' => bool, 'message' => string, 'discount' => float, 'data' => array]
     */
    public function validate_code($code, $order_total = 0)
    {
        $code = vl_sanitize_coupon_code($code);

        // Check empty
        if (empty($code)) {
            return $this->error_response('Vui lòng nhập mã giảm giá');
        }

        // Check rate limiting
        if (!vl_check_rate_limit('validate_coupon')) {
            return $this->error_response('Bạn đã thử quá nhiều lần. Vui lòng đợi một chút.');
        }

        // Get coupon từ cache
        $coupon = $this->cache->get_coupon($code);

        if (!$coupon) {
            VL_Logger::info('Coupon not found', ['code' => $code]);
            return $this->error_response('Mã giảm giá không tồn tại hoặc đã hết hạn');
        }

        // Validate business rules
        $validation_result = $this->validate_rules($coupon, $order_total);

        if (!$validation_result['valid']) {
            return $validation_result;
        }

        // Calculate discount
        $discount = $this->calculate_discount($coupon, $order_total);

        VL_Logger::info('Coupon validated successfully', [
            'code' => $code,
            'discount' => $discount,
            'order_total' => $order_total
        ]);

        return [
            'valid' => true,
            'message' => sprintf('Áp dụng thành công! Giảm %s', vl_format_currency($discount)),
            'discount' => $discount,
            'data' => $coupon
        ];
    }

    /**
     * Validate business rules
     * 
     * @param array $coupon Coupon data
     * @param float $order_total Order total
     * @return array Validation result
     */
    private function validate_rules($coupon, $order_total)
    {
        // Rule 1: Check usage limit
        if ($coupon['max_usage'] > 0 && $coupon['used_count'] >= $coupon['max_usage']) {
            return $this->error_response('Mã giảm giá đã hết lượt sử dụng');
        }

        // Rule 2: Check minimum order value
        if ($coupon['min_order'] > 0 && $order_total < $coupon['min_order']) {
            return $this->error_response(
                sprintf('Đơn hàng tối thiểu %s để sử dụng mã này', vl_format_currency($coupon['min_order']))
            );
        }

        // Rule 3: Có thể thêm các rules khác ở đây
        // - Check user-specific coupons
        // - Check expiry date (nếu có cột trong sheet)
        // - Check category/product restrictions

        return ['valid' => true];
    }

    /**
     * Calculate giá trị giảm giá
     * 
     * @param array $coupon Coupon data
     * @param float $order_total Order total
     * @return float Discount amount
     */
    public function calculate_discount($coupon, $order_total)
    {
        $discount = 0;

        switch ($coupon['discount_type']) {
            case 'percent':
                $discount = ($order_total * $coupon['discount_value']) / 100;
                break;

            case 'fixed':
            default:
                $discount = $coupon['discount_value'];
                break;
        }

        // Đảm bảo discount không vượt quá order total
        $discount = min($discount, $order_total);

        return floatval($discount);
    }

    /**
     * Apply coupon (update used_count trong Google Sheets)
     * 
     * Flow:
     * 1. Lock coupon
     * 2. Fresh read từ Google Sheets (2-phase check)
     * 3. Validate lại
     * 4. Update used_count++
     * 5. Unlock coupon
     * 6. Invalidate cache
     * 
     * @param string $code Mã coupon
     * @param float $order_total Order total
     * @param int $booking_id Booking ID để log
     * @return array Result
     */
    public function apply_coupon($code, $order_total, $booking_id = 0)
    {
        $code = vl_sanitize_coupon_code($code);

        // Step 1: Try lock
        if (!vl_try_lock_coupon($code)) {
            return $this->error_response('Mã đang được xử lý. Vui lòng thử lại sau vài giây.');
        }

        // Step 2: Fresh read từ Google Sheets
        VL_Logger::info('Applying coupon - fresh read from Google Sheets', [
            'code' => $code,
            'booking_id' => $booking_id
        ]);

        $fresh_coupons = $this->sheets_api->read_range();
        if ($fresh_coupons === false) {
            vl_unlock_coupon($code);
            return $this->error_response('Không thể kết nối Google Sheets. Vui lòng thử lại.');
        }

        // Parse và tìm coupon
        $cache_manager = new VL_Cache_Manager();
        $parsed = $cache_manager->parse_sheet_data($fresh_coupons);

        if (!isset($parsed[$code])) {
            vl_unlock_coupon($code);
            return $this->error_response('Mã không tồn tại');
        }

        $coupon = $parsed[$code];

        // Step 3: Validate lại với fresh data
        $validation = $this->validate_rules($coupon, $order_total);
        if (!$validation['valid']) {
            vl_unlock_coupon($code);
            return $validation;
        }

        // Step 4: Update used_count trong Sheet
        $new_used_count = $coupon['used_count'] + 1;
        $row_number = $coupon['row_index'];
        $update_range = sprintf('%s!F%d', VL_COUPON_SHEET_NAME, $row_number);

        $success = $this->sheets_api->update_range($update_range, [[$new_used_count]]);

        if (!$success) {
            vl_unlock_coupon($code);
            VL_Logger::error('Failed to update coupon usage in Google Sheets', [
                'code' => $code,
                'booking_id' => $booking_id
            ]);
            return $this->error_response('Lỗi khi cập nhật mã. Vui lòng liên hệ hỗ trợ.');
        }

        // Step 5: Unlock
        vl_unlock_coupon($code);

        // Step 6: Invalidate cache
        $this->cache->invalidate_coupon($code);

        // Calculate discount
        $discount = $this->calculate_discount($coupon, $order_total);

        VL_Logger::info('Coupon applied successfully', [
            'code' => $code,
            'booking_id' => $booking_id,
            'discount' => $discount,
            'new_used_count' => $new_used_count
        ]);

        return [
            'valid' => true,
            'message' => 'Áp dụng mã thành công!',
            'discount' => $discount,
            'data' => $coupon
        ];
    }

    /**
     * Helper: error response format
     */
    private function error_response($message)
    {
        return [
            'valid' => false,
            'message' => $message,
            'discount' => 0,
            'data' => null
        ];
    }
}
