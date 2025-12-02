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

/**
 * Get booking status badge HTML
 * 
 * @since   2.0.0
 * 
 * @param   string  $status      Status slug
 * @param   bool    $html_output Return HTML badge or array
 * 
 * @return  mixed
 */
function vie_get_booking_status_badge(string $status, bool $html_output = true) {
    $status_map = array(
        'pending_payment' => array(
            'label'    => 'Chờ thanh toán',
            'color'    => '#f59e0b',
            'bg_color' => '#fef3c7',
        ),
        'processing' => array(
            'label'    => 'Đang xử lý',
            'color'    => '#3b82f6',
            'bg_color' => '#dbeafe',
        ),
        'confirmed' => array(
            'label'    => 'Đã xác nhận',
            'color'    => '#10b981',
            'bg_color' => '#d1fae5',
        ),
        'completed' => array(
            'label'    => 'Hoàn tất',
            'color'    => '#8b5cf6',
            'bg_color' => '#ede9fe',
        ),
        'cancelled' => array(
            'label'    => 'Đã hủy',
            'color'    => '#ef4444',
            'bg_color' => '#fee2e2',
        ),
    );

    $info = $status_map[$status] ?? array(
        'label'    => ucfirst($status),
        'color'    => '#6b7280',
        'bg_color' => '#f3f4f6',
    );

    if (!$html_output) {
        return $info;
    }

    return sprintf(
        '<span class="vie-status-badge" style="background:%s;color:%s;padding:4px 8px;border-radius:4px;font-size:12px;">%s</span>',
        esc_attr($info['bg_color']),
        esc_attr($info['color']),
        esc_html($info['label'])
    );
}

/**
 * Get payment status badge HTML
 * 
 * @since   2.0.0
 * 
 * @param   string  $status
 * 
 * @return  string
 */
function vie_get_payment_status_badge(string $status): string {
    $status_map = array(
        'unpaid' => array(
            'label'    => 'Chưa thanh toán',
            'color'    => '#dc2626',
            'bg_color' => '#fee2e2',
        ),
        'partial' => array(
            'label'    => 'Thanh toán một phần',
            'color'    => '#f59e0b',
            'bg_color' => '#fef3c7',
        ),
        'paid' => array(
            'label'    => 'Đã thanh toán',
            'color'    => '#10b981',
            'bg_color' => '#d1fae5',
        ),
        'refunded' => array(
            'label'    => 'Đã hoàn tiền',
            'color'    => '#6b7280',
            'bg_color' => '#f3f4f6',
        ),
    );

    $info = $status_map[$status] ?? array(
        'label'    => ucfirst($status),
        'color'    => '#6b7280',
        'bg_color' => '#f3f4f6',
    );

    return sprintf(
        '<span class="vie-payment-badge" style="background:%s;color:%s;padding:4px 8px;border-radius:4px;font-size:12px;">%s</span>',
        esc_attr($info['bg_color']),
        esc_attr($info['color']),
        esc_html($info['label'])
    );
}

/**
 * Sanitize coupon code
 * 
 * @since   2.0.0
 * 
 * @param   string  $code
 * 
 * @return  string
 */
function vie_sanitize_coupon_code(string $code): string {
    $code = sanitize_text_field($code);
    $code = strtoupper(trim($code));
    $code = preg_replace('/[^A-Z0-9]/', '', $code);
    return $code;
}

/**
 * Generate booking code
 * 
 * @since   2.0.0
 * 
 * @param   string  $prefix  Prefix (default: VL)
 * 
 * @return  string
 */
function vie_generate_booking_code(string $prefix = 'VL'): string {
    return $prefix . strtoupper(substr(uniqid(), -8));
}

/**
 * Generate booking hash
 *
 * @since   2.0.0
 *
 * @return  string
 */
function vie_generate_booking_hash(): string {
    return wp_generate_password(32, false);
}

/**
 * Get formatted surcharge help text for children based on room's surcharge settings
 *
 * @since   2.0.0
 *
 * @param   int  $room_id  ID của phòng
 *
 * @return  string  Formatted help text (VD: "Bé dưới 6 tuổi: Miễn phí | 6-11 tuổi: Phụ thu | Từ 12 tuổi: Tính như người lớn")
 */
function vie_get_surcharge_help_text(int $room_id): string {
    global $wpdb;

    if (empty($room_id)) {
        return '';
    }

    // Lấy tất cả surcharges cho trẻ em của phòng này
    $table = $wpdb->prefix . 'hotel_room_surcharges';
    $surcharges = $wpdb->get_results($wpdb->prepare(
        "SELECT min_age, max_age, amount, label
         FROM {$table}
         WHERE room_id = %d
         AND surcharge_type = 'child'
         AND status = 'active'
         ORDER BY min_age ASC",
        $room_id
    ));

    if (empty($surcharges)) {
        // Fallback: dùng text mặc định nếu không có cài đặt
        return __('Bé dưới 6 tuổi: Miễn phí | 6-11 tuổi: Phụ thu | Từ 12 tuổi: Tính như người lớn', 'viechild');
    }

    // Format surcharges thành text
    $parts = [];

    foreach ($surcharges as $surcharge) {
        $min_age = $surcharge->min_age;
        $max_age = $surcharge->max_age;
        $amount = floatval($surcharge->amount);
        $label = !empty($surcharge->label) ? $surcharge->label : '';

        // Xây dựng text cho range tuổi
        if ($min_age === null || $min_age === 0) {
            $age_text = sprintf(__('Dưới %d tuổi', 'viechild'), $max_age + 1);
        } elseif ($max_age === null || $max_age >= 17) {
            $age_text = sprintf(__('Từ %d tuổi', 'viechild'), $min_age);
        } else {
            $age_text = sprintf(__('%d-%d tuổi', 'viechild'), $min_age, $max_age);
        }

        // Xây dựng text cho phụ thu
        if ($amount == 0) {
            $price_text = __('Miễn phí', 'viechild');
        } else {
            $price_text = !empty($label) ? $label : sprintf(__('Phụ thu %s', 'viechild'), vie_format_currency($amount));
        }

        $parts[] = $age_text . ': ' . $price_text;
    }

    return implode(' | ', $parts);
}
