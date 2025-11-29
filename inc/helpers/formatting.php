<?php
/**
 * ============================================================================
 * TÊN FILE: formatting.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Các hàm format dữ liệu: tiền tệ, ngày tháng, text
 * 
 * CHỨC NĂNG:
 * - vie_format_currency(): Format số tiền VNĐ
 * - vie_format_date(): Format ngày tháng
 * - vie_format_phone(): Format số điện thoại
 * - vie_calculate_nights(): Tính số đêm
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Helpers
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Format số tiền theo định dạng Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   float   $amount     Số tiền cần format
 * @param   bool    $with_unit  Có thêm "VNĐ" không. Default true.
 * 
 * @return  string  Số tiền đã format (VD: "1.500.000 VNĐ")
 * 
 * @example
 * vie_format_currency(1500000);        // "1.500.000 VNĐ"
 * vie_format_currency(1500000, false); // "1.500.000"
 */
function vie_format_currency(float $amount, bool $with_unit = true): string {
    $formatted = number_format($amount, 0, ',', '.');
    return $with_unit ? $formatted . ' VNĐ' : $formatted;
}

/**
 * Format ngày theo định dạng Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   string|DateTime  $date    Date string (Y-m-d) hoặc DateTime object
 * @param   string           $format  'short' (dd/mm/yyyy) | 'long' | 'iso'
 * 
 * @return  string
 */
function vie_format_date($date, string $format = 'short'): string {
    if (empty($date)) {
        return '';
    }
    
    if (is_string($date)) {
        $timestamp = strtotime($date);
        if (!$timestamp) {
            return '';
        }
    } elseif ($date instanceof DateTime) {
        $timestamp = $date->getTimestamp();
    } else {
        return '';
    }
    
    switch ($format) {
        case 'long':
            // Thứ Hai, 29/11/2024
            $days = ['Chủ nhật', 'Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy'];
            $day_name = $days[(int) date('w', $timestamp)];
            return $day_name . ', ' . date('d/m/Y', $timestamp);
            
        case 'iso':
            return date('Y-m-d', $timestamp);
            
        case 'short':
        default:
            return date('d/m/Y', $timestamp);
    }
}

/**
 * Format số điện thoại Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   string  $phone  Số điện thoại thô
 * 
 * @return  string  Số điện thoại đã format (VD: "0901 234 567")
 */
function vie_format_phone(string $phone): string {
    // Loại bỏ ký tự không phải số
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Format: 0xxx xxx xxx
    if (strlen($phone) === 10) {
        return substr($phone, 0, 4) . ' ' . substr($phone, 4, 3) . ' ' . substr($phone, 7);
    }
    
    // Format: 0xxxx xxx xxx
    if (strlen($phone) === 11) {
        return substr($phone, 0, 5) . ' ' . substr($phone, 5, 3) . ' ' . substr($phone, 8);
    }
    
    return $phone;
}

/**
 * Tính số đêm giữa 2 ngày
 * 
 * @since   2.0.0
 * 
 * @param   string  $check_in   Ngày nhận phòng (Y-m-d)
 * @param   string  $check_out  Ngày trả phòng (Y-m-d)
 * 
 * @return  int     Số đêm (0 nếu invalid)
 */
function vie_calculate_nights(string $check_in, string $check_out): int {
    $date_in = strtotime($check_in);
    $date_out = strtotime($check_out);
    
    if (!$date_in || !$date_out || $date_out <= $date_in) {
        return 0;
    }
    
    return (int) floor(($date_out - $date_in) / DAY_IN_SECONDS);
}
