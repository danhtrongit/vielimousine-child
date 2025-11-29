<?php
/**
 * ============================================================================
 * TÊN FILE: security.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Các hàm sanitize và validate input để đảm bảo bảo mật
 * 
 * CHỨC NĂNG:
 * - vie_sanitize_booking_data(): Sanitize toàn bộ dữ liệu booking
 * - vie_sanitize_date(): Sanitize và validate ngày tháng
 * - vie_validate_phone(): Validate số điện thoại VN
 * - vie_validate_email(): Validate email
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Helpers
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Sanitize toàn bộ dữ liệu booking từ form
 * 
 * @since   2.0.0
 * 
 * @param   array   $raw_data   Dữ liệu thô từ $_POST
 * 
 * @return  array   Dữ liệu đã sanitize
 */
function vie_sanitize_booking_data(array $raw_data): array {
    return [
        // Integer fields
        'room_id'       => absint($raw_data['room_id'] ?? 0),
        'hotel_id'      => absint($raw_data['hotel_id'] ?? 0),
        'num_rooms'     => absint($raw_data['num_rooms'] ?? 1),
        'num_adults'    => absint($raw_data['num_adults'] ?? 2),
        'num_children'  => absint($raw_data['num_children'] ?? 0),
        
        // Text fields
        'customer_name'  => sanitize_text_field($raw_data['customer_name'] ?? ''),
        'customer_phone' => vie_sanitize_phone($raw_data['customer_phone'] ?? ''),
        'customer_email' => sanitize_email($raw_data['customer_email'] ?? ''),
        'customer_note'  => sanitize_textarea_field($raw_data['customer_note'] ?? ''),
        
        // Date fields (convert to Y-m-d format)
        'check_in'  => vie_sanitize_date($raw_data['check_in'] ?? ''),
        'check_out' => vie_sanitize_date($raw_data['check_out'] ?? ''),
        
        // Enum fields (whitelist)
        'price_type' => in_array($raw_data['price_type'] ?? '', ['room', 'combo'], true) 
                        ? $raw_data['price_type'] 
                        : 'room',
        
        // Array fields
        'children_ages' => isset($raw_data['children_ages']) 
                          ? array_map('absint', (array) $raw_data['children_ages'])
                          : [],
        
        // Transport fields
        'transport_go'     => sanitize_text_field($raw_data['transport_go'] ?? ''),
        'transport_return' => sanitize_text_field($raw_data['transport_return'] ?? ''),
        
        // Invoice fields
        'need_invoice'   => !empty($raw_data['need_invoice']),
        'company_name'   => sanitize_text_field($raw_data['company_name'] ?? ''),
        'tax_code'       => sanitize_text_field($raw_data['tax_code'] ?? ''),
        'company_address'=> sanitize_text_field($raw_data['company_address'] ?? ''),
        
        // Bed type
        'bed_type' => sanitize_text_field($raw_data['bed_type'] ?? ''),
    ];
}

/**
 * Sanitize và convert date string sang format Y-m-d
 * 
 * @since   2.0.0
 * 
 * @param   string  $date   Date string (dd/mm/yyyy hoặc Y-m-d)
 * 
 * @return  string  Date format Y-m-d hoặc empty string nếu invalid
 */
function vie_sanitize_date(string $date): string {
    $date = sanitize_text_field($date);
    
    if (empty($date)) {
        return '';
    }
    
    // Nếu format dd/mm/yyyy
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];
        
        // Validate date
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        return '';
    }
    
    // Nếu format Y-m-d
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $parts = explode('-', $date);
        if (checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
            return $date;
        }
    }
    
    return '';
}

/**
 * Sanitize số điện thoại - chỉ giữ lại số
 * 
 * @since   2.0.0
 * 
 * @param   string  $phone  Số điện thoại thô
 * 
 * @return  string  Số điện thoại chỉ chứa số
 */
function vie_sanitize_phone(string $phone): string {
    return preg_replace('/[^0-9]/', '', sanitize_text_field($phone));
}

/**
 * Validate số điện thoại Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   string  $phone  Số điện thoại
 * 
 * @return  bool    True nếu hợp lệ
 */
function vie_validate_phone(string $phone): bool {
    $phone = vie_sanitize_phone($phone);
    
    // Số điện thoại VN: 10-11 số, bắt đầu bằng 0
    if (strlen($phone) < 10 || strlen($phone) > 11) {
        return false;
    }
    
    if ($phone[0] !== '0') {
        return false;
    }
    
    // Các đầu số hợp lệ
    $valid_prefixes = [
        '03', '05', '07', '08', '09',  // Mobile
        '02'                            // Landline
    ];
    
    $prefix = substr($phone, 0, 2);
    return in_array($prefix, $valid_prefixes, true);
}

/**
 * Validate email
 * 
 * @since   2.0.0
 * 
 * @param   string  $email  Email address
 * 
 * @return  bool    True nếu hợp lệ
 */
function vie_validate_email(string $email): bool {
    return !empty($email) && is_email($email);
}

/**
 * Validate dữ liệu booking
 * 
 * @since   2.0.0
 * 
 * @param   array   $data   Dữ liệu đã sanitize
 * 
 * @return  array|WP_Error  Dữ liệu hoặc WP_Error nếu invalid
 */
function vie_validate_booking_data(array $data) {
    $errors = [];
    
    // Required fields
    if (empty($data['room_id'])) {
        $errors[] = 'Vui lòng chọn loại phòng';
    }
    
    if (empty($data['check_in'])) {
        $errors[] = 'Vui lòng chọn ngày nhận phòng';
    }
    
    if (empty($data['check_out'])) {
        $errors[] = 'Vui lòng chọn ngày trả phòng';
    }
    
    // Validate dates
    if (!empty($data['check_in']) && !empty($data['check_out'])) {
        $check_in = strtotime($data['check_in']);
        $check_out = strtotime($data['check_out']);
        
        if ($check_out <= $check_in) {
            $errors[] = 'Ngày trả phòng phải sau ngày nhận phòng';
        }
        
        if ($check_in < strtotime('today')) {
            $errors[] = 'Ngày nhận phòng không thể trong quá khứ';
        }
    }
    
    if (empty($data['customer_name'])) {
        $errors[] = 'Vui lòng nhập họ tên';
    }
    
    if (empty($data['customer_phone'])) {
        $errors[] = 'Vui lòng nhập số điện thoại';
    } elseif (!vie_validate_phone($data['customer_phone'])) {
        $errors[] = 'Số điện thoại không hợp lệ';
    }
    
    if (!empty($data['customer_email']) && !vie_validate_email($data['customer_email'])) {
        $errors[] = 'Email không hợp lệ';
    }
    
    // Validate num_adults
    if ($data['num_adults'] < 1) {
        $errors[] = 'Số người lớn phải ít nhất là 1';
    }
    
    // Validate children ages
    if ($data['num_children'] > 0) {
        if (count($data['children_ages']) !== $data['num_children']) {
            $errors[] = 'Vui lòng nhập tuổi của tất cả trẻ em';
        }
    }
    
    if (!empty($errors)) {
        return new WP_Error('validation_error', implode('. ', $errors), ['errors' => $errors]);
    }
    
    return $data;
}

/**
 * Generate booking hash an toàn
 * 
 * @since   2.0.0
 * 
 * @return  string  Hash 32 ký tự
 */
function vie_generate_booking_hash(): string {
    return wp_generate_password(32, false);
}

/**
 * Generate mã đặt phòng
 * 
 * @since   2.0.0
 * 
 * @param   string  $prefix     Prefix (default: 'VIE')
 * 
 * @return  string  Mã đặt phòng format VIE-YYYYMMDD-XXXX
 */
function vie_generate_booking_code(string $prefix = 'VIE'): string {
    $date_part = date('ymd');
    $random_part = strtoupper(wp_generate_password(4, false));
    
    return $prefix . '-' . $date_part . '-' . $random_part;
}
