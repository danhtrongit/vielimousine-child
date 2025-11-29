<?php
/**
 * Google Sheets Coupon System - Helper Functions
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

/**
 * Sanitize mã giảm giá
 * Chuyển về uppercase, loại bỏ khoảng trắng và ký tự đặc biệt
 * 
 * @param string $code Raw coupon code
 * @return string Sanitized code
 */
function vl_sanitize_coupon_code($code)
{
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9_-]/', '', $code);
    return $code;
}

/**
 * Format tiền tệ VNĐ
 * 
 * @param float $amount Số tiền
 * @return string Số tiền đã format
 */
function vl_format_currency($amount)
{
    return number_format($amount, 0, ',', '.') . 'đ';
}

/**
 * Kiểm tra IP rate limiting
 * 
 * @param string $action Action name (vd: 'validate_coupon')
 * @param int $limit Số lần tối đa trong timeframe
 * @param int $timeframe Timeframe tính bằng giây
 * @return bool True nếu còn trong giới hạn, False nếu vượt quá
 */
function vl_check_rate_limit($action, $limit = null, $timeframe = 60)
{
    if ($limit === null) {
        $limit = VL_COUPON_RATE_LIMIT;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'vl_rate_limit_' . $action . '_' . md5($ip);

    $attempts = get_transient($key);

    if ($attempts === false) {
        // Chưa có record, tạo mới
        set_transient($key, 1, $timeframe);
        return true;
    }

    if ($attempts >= $limit) {
        VL_Logger::warning('Rate limit exceeded', [
            'action' => $action,
            'ip' => $ip,
            'attempts' => $attempts
        ]);
        return false;
    }

    // Tăng counter
    set_transient($key, $attempts + 1, $timeframe);
    return true;
}

/**
 * Try to lock coupon code (race condition protection)
 * 
 * @param string $coupon_code Mã coupon
 * @return bool True nếu lock thành công
 */
function vl_try_lock_coupon($coupon_code)
{
    $lock_key = 'vl_coupon_lock_' . $coupon_code;

    if (get_transient($lock_key)) {
        VL_Logger::debug('Coupon already locked', ['code' => $coupon_code]);
        return false;
    }

    set_transient($lock_key, time(), VL_COUPON_LOCK_TIMEOUT);
    VL_Logger::debug('Coupon locked', ['code' => $coupon_code]);
    return true;
}

/**
 * Unlock coupon code
 * 
 * @param string $coupon_code Mã coupon
 */
function vl_unlock_coupon($coupon_code)
{
    $lock_key = 'vl_coupon_lock_' . $coupon_code;
    delete_transient($lock_key);
    VL_Logger::debug('Coupon unlocked', ['code' => $coupon_code]);
}

/**
 * Kiểm tra xem coupon có đang bị lock không
 * 
 * @param string $coupon_code Mã coupon
 * @return bool True nếu đang bị lock
 */
function vl_is_coupon_locked($coupon_code)
{
    $lock_key = 'vl_coupon_lock_' . $coupon_code;
    return (bool) get_transient($lock_key);
}

/**
 * Parse Google Sheets range notation
 * Example: "Coupons!A2:F1000" => ['sheet' => 'Coupons', 'range' => 'A2:F1000']
 * 
 * @param string $range Range string
 * @return array Parsed range
 */
function vl_parse_sheet_range($range)
{
    $parts = explode('!', $range);

    if (count($parts) === 2) {
        return [
            'sheet' => $parts[0],
            'range' => $parts[1]
        ];
    }

    return [
        'sheet' => '',
        'range' => $range
    ];
}

/**
 * Convert column letter to number (A=0, B=1, ...)
 * 
 * @param string $column Column letter (A, B, AA, etc.)
 * @return int Column number (0-based)
 */
function vl_column_letter_to_number($column)
{
    $column = strtoupper($column);
    $length = strlen($column);
    $number = 0;

    for ($i = 0; $i < $length; $i++) {
        $number = $number * 26 + (ord($column[$i]) - ord('A') + 1);
    }

    return $number - 1; // 0-based
}

/**
 * Convert column number to letter (0=A, 1=B, ...)
 * 
 * @param int $number Column number (0-based)
 * @return string Column letter
 */
function vl_column_number_to_letter($number)
{
    $letter = '';
    $number++; // Convert to 1-based

    while ($number > 0) {
        $number--;
        $letter = chr($number % 26 + ord('A')) . $letter;
        $number = intval($number / 26);
    }

    return $letter;
}
