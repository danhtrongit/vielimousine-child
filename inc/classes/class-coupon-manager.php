<?php
/**
 * ============================================================================
 * TÊN FILE: class-coupon-manager.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Quản lý mã giảm giá từ Google Sheets (SIMPLIFIED VERSION - 4 COLUMNS).
 * Hỗ trợ validate, apply coupon với 2-phase locking.
 *
 * CHỨC NĂNG CHÍNH:
 * - Validate coupon code
 * - Calculate discount (fixed amount only)
 * - Apply coupon (mark as used in Google Sheets)
 * - Rate limiting
 * - Caching with auto-refresh
 *
 * GOOGLE SHEET FORMAT (SIMPLIFIED - 4 COLUMNS):
 * Column A: Code (unique) - Mã giảm giá
 * Column B: Amount (VNĐ) - Số tiền giảm (fixed)
 * Column C: Used At (datetime) - Ngày sử dụng (empty = chưa dùng)
 * Column D: Used By (customer info) - Người sử dụng (tên + SDT)
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Classes
 * @version     2.0.0
 * @since       2.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Coupon_Manager
 * ============================================================================
 */
class Vie_Coupon_Manager {

    /** @var Vie_Coupon_Manager|null Singleton instance */
    private static $instance = null;

    /** @var string Cache key for coupons */
    const CACHE_KEY = 'vie_coupons_data';

    /** @var int Cache duration (seconds) */
    const CACHE_DURATION = 300; // 5 minutes

    /** @var string Transient prefix for locks */
    const LOCK_PREFIX = 'vie_coupon_lock_';

    /** @var int Lock duration (seconds) */
    const LOCK_DURATION = 30;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     * 
     * @since   2.0.0
     * @return  Vie_Coupon_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * 
     * @since   2.0.0
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     * 
     * @since   2.0.0
     */
    private function init_hooks() {
        // AJAX handlers
        add_action('wp_ajax_vie_validate_coupon', array($this, 'ajax_validate_coupon'));
        add_action('wp_ajax_nopriv_vie_validate_coupon', array($this, 'ajax_validate_coupon'));

        add_action('wp_ajax_vie_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_nopriv_vie_apply_coupon', array($this, 'ajax_apply_coupon'));

        add_action('wp_ajax_vie_refresh_coupons', array($this, 'ajax_refresh_coupons'));
    }

    /**
     * -------------------------------------------------------------------------
     * PUBLIC METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Validate coupon code
     * 
     * @since   2.0.0
     * @param   string  $code           Coupon code
     * @param   float   $order_total    Order total
     * @return  array   ['valid' => bool, 'message' => string, 'discount' => float, 'data' => array]
     */
    public function validate_coupon($code, $order_total = 0) {
        $code = $this->sanitize_code($code);

        // Check empty
        if (empty($code)) {
            return $this->error_response('Vui lòng nhập mã giảm giá');
        }

        // Check rate limit
        if (!$this->check_rate_limit('validate')) {
            return $this->error_response('Bạn đã thử quá nhiều lần. Vui lòng đợi một chút.');
        }

        // Get coupon from cache
        $coupon = $this->get_coupon($code);

        if (!$coupon) {
            return $this->error_response('Mã giảm giá không tồn tại hoặc đã hết hạn');
        }

        // Validate rules
        $validation = $this->validate_rules($coupon, $order_total);
        if (!$validation['valid']) {
            return $validation;
        }

        // Calculate discount
        $discount = $this->calculate_discount($coupon, $order_total);

        return array(
            'valid'    => true,
            'message'  => sprintf('Áp dụng thành công! Giảm %s', vie_format_currency($discount)),
            'discount' => $discount,
            'data'     => $coupon
        );
    }

    /**
     * Apply coupon (mark as used in Google Sheets - SIMPLIFIED)
     *
     * @since   2.0.0
     * @param   string  $code           Coupon code
     * @param   float   $order_total    Order total
     * @param   int     $booking_id     Booking ID for logging
     * @param   string  $customer_info  Customer info (name + phone)
     * @return  array
     */
    public function apply_coupon($code, $order_total, $booking_id = 0, $customer_info = '') {
        $code = $this->sanitize_code($code);

        // Step 1: Try lock
        if (!$this->try_lock($code)) {
            return $this->error_response('Mã đang được xử lý. Vui lòng thử lại sau vài giây.');
        }

        // Step 2: Fresh read from Google Sheets
        $fresh_coupons = $this->fetch_coupons_from_sheets();

        if ($fresh_coupons === false) {
            $this->unlock($code);
            return $this->error_response('Không thể kết nối Google Sheets. Vui lòng thử lại.');
        }

        // Find coupon in fresh data
        $coupon = null;
        foreach ($fresh_coupons as $c) {
            if (strtoupper($c['code']) === $code) {
                $coupon = $c;
                break;
            }
        }

        if (!$coupon) {
            $this->unlock($code);
            return $this->error_response('Mã không tồn tại');
        }

        // Step 3: Validate with fresh data
        $validation = $this->validate_rules($coupon, $order_total);
        if (!$validation['valid']) {
            $this->unlock($code);
            return $validation;
        }

        // Step 4: Mark as used in Sheet (Update Column C and D)
        $row_number = $coupon['row_index'] ?? 0;

        if ($row_number > 0) {
            $update_success = $this->update_coupon_usage($row_number, $customer_info);

            if (!$update_success) {
                $this->unlock($code);
                return $this->error_response('Lỗi khi cập nhật mã. Vui lòng liên hệ hỗ trợ.');
            }
        }

        // Step 5: Unlock
        $this->unlock($code);

        // Step 6: Invalidate cache
        delete_transient(self::CACHE_KEY);

        // Calculate discount (fixed amount only)
        $discount = $this->calculate_discount($coupon, $order_total);

        // Log
        $this->log('Coupon applied', array(
            'code'          => $code,
            'booking_id'    => $booking_id,
            'discount'      => $discount,
            'customer_info' => $customer_info,
        ));

        return array(
            'valid'    => true,
            'message'  => 'Áp dụng mã thành công!',
            'discount' => $discount,
            'data'     => $coupon
        );
    }

    /**
     * Calculate discount amount (SIMPLIFIED - Fixed amount only)
     *
     * @since   2.0.0
     * @param   array   $coupon
     * @param   float   $order_total
     * @return  float
     */
    public function calculate_discount($coupon, $order_total) {
        // In simplified version, discount is always a fixed amount (Column B)
        $discount = floatval($coupon['amount'] ?? 0);

        // Don't exceed order total
        $discount = min($discount, $order_total);

        return floatval($discount);
    }

    /**
     * Get all coupons (from cache or Google Sheets)
     * 
     * @since   2.0.0
     * @param   bool    $force_refresh
     * @return  array
     */
    public function get_coupons($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if ($cached !== false) {
                return $cached;
            }
        }

        $coupons = $this->fetch_coupons_from_sheets();

        if ($coupons !== false) {
            set_transient(self::CACHE_KEY, $coupons, self::CACHE_DURATION);
        }

        return $coupons ?: array();
    }

    /**
     * Get single coupon by code
     * 
     * @since   2.0.0
     * @param   string  $code
     * @return  array|null
     */
    public function get_coupon($code) {
        $code    = $this->sanitize_code($code);
        $coupons = $this->get_coupons();

        foreach ($coupons as $coupon) {
            if (strtoupper($coupon['code']) === $code) {
                return $coupon;
            }
        }

        return null;
    }

    /**
     * -------------------------------------------------------------------------
     * PRIVATE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Validate business rules (SIMPLIFIED)
     *
     * @since   2.0.0
     * @param   array   $coupon
     * @param   float   $order_total
     * @return  array
     */
    private function validate_rules($coupon, $order_total) {
        // Check if coupon is already used (Column C has value)
        if (!empty($coupon['used_at'])) {
            return $this->error_response('Mã giảm giá đã được sử dụng');
        }

        // No other validation needed in simplified version
        return array('valid' => true);
    }

    /**
     * Fetch coupons from Google Sheets (SIMPLIFIED - 4 COLUMNS)
     *
     * @since   2.0.0
     * @return  array|false
     */
    private function fetch_coupons_from_sheets() {
        // Check if Google Sheets API is available
        if (!class_exists('Vie_Google_Sheets_API')) {
            $this->log('Google Sheets API class not found');
            return false;
        }

        $sheets_api = new Vie_Google_Sheets_API();

        // Get coupon sheet config
        $sheet_id   = defined('VIE_COUPON_SHEET_ID') ? VIE_COUPON_SHEET_ID : '';
        $sheet_name = defined('VIE_COUPON_SHEET_NAME') ? VIE_COUPON_SHEET_NAME : 'Coupons';
        $range      = $sheet_name . '!A2:D'; // Only 4 columns: A, B, C, D (skip header row)

        if (empty($sheet_id)) {
            $this->log('Coupon Sheet ID not configured');
            return false;
        }

        $data = $sheets_api->read_range($sheet_id, $range);

        if ($data === false) {
            return false;
        }

        // Parse data (SIMPLIFIED - 4 columns)
        $coupons = array();
        $row_index = 2; // Start from row 2 (after header)

        foreach ($data as $row) {
            if (empty($row[0])) {
                $row_index++;
                continue;
            }

            $coupons[] = array(
                'code'      => strtoupper(trim($row[0] ?? '')),      // Column A: Code
                'amount'    => floatval($row[1] ?? 0),                // Column B: Amount (VNĐ)
                'used_at'   => trim($row[2] ?? ''),                   // Column C: Used At (empty = available)
                'used_by'   => trim($row[3] ?? ''),                   // Column D: Used By
                'row_index' => $row_index,                            // For updating later
            );

            $row_index++;
        }

        return $coupons;
    }

    /**
     * Update coupon usage in Google Sheets (SIMPLIFIED - Mark as used)
     *
     * @since   2.0.0
     * @param   int     $row_number     Row number in sheet
     * @param   string  $customer_info  Customer info (name + phone)
     * @return  bool
     */
    private function update_coupon_usage($row_number, $customer_info = '') {
        if (!class_exists('Vie_Google_Sheets_API')) {
            return false;
        }

        $sheets_api = new Vie_Google_Sheets_API();

        $sheet_id   = defined('VIE_COUPON_SHEET_ID') ? VIE_COUPON_SHEET_ID : '';
        $sheet_name = defined('VIE_COUPON_SHEET_NAME') ? VIE_COUPON_SHEET_NAME : 'Coupons';

        // Update both Column C (Used At) and Column D (Used By)
        $range_c = sprintf('%s!C%d', $sheet_name, $row_number); // Column C: Used At
        $range_d = sprintf('%s!D%d', $sheet_name, $row_number); // Column D: Used By

        $current_datetime = current_time('Y-m-d H:i:s');

        // Update Column C: Used At
        $result_c = $sheets_api->update_range($sheet_id, $range_c, array(array($current_datetime)));

        // Update Column D: Used By
        $result_d = $sheets_api->update_range($sheet_id, $range_d, array(array($customer_info)));

        return $result_c && $result_d;
    }

    /**
     * Try to acquire lock for coupon
     * 
     * @since   2.0.0
     * @param   string  $code
     * @return  bool
     */
    private function try_lock($code) {
        $key = self::LOCK_PREFIX . md5($code);

        // Check if already locked
        if (get_transient($key)) {
            return false;
        }

        // Set lock
        set_transient($key, true, self::LOCK_DURATION);

        return true;
    }

    /**
     * Unlock coupon
     * 
     * @since   2.0.0
     * @param   string  $code
     */
    private function unlock($code) {
        $key = self::LOCK_PREFIX . md5($code);
        delete_transient($key);
    }

    /**
     * Check rate limit
     * 
     * @since   2.0.0
     * @param   string  $action
     * @return  bool
     */
    private function check_rate_limit($action) {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'vie_coupon_rate_' . md5($ip . '_' . $action);

        $attempts = get_transient($key) ?: 0;

        if ($attempts >= 10) {
            return false;
        }

        set_transient($key, $attempts + 1, 60); // Reset after 60 seconds

        return true;
    }

    /**
     * Sanitize coupon code
     * 
     * @since   2.0.0
     * @param   string  $code
     * @return  string
     */
    private function sanitize_code($code) {
        $code = sanitize_text_field($code);
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);
        return $code;
    }

    /**
     * Error response helper
     * 
     * @since   2.0.0
     * @param   string  $message
     * @return  array
     */
    private function error_response($message) {
        return array(
            'valid'    => false,
            'message'  => $message,
            'discount' => 0,
            'data'     => null
        );
    }

    /**
     * Log helper
     * 
     * @since   2.0.0
     * @param   string  $message
     * @param   array   $context
     */
    private function log($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VIE_COUPON] ' . $message . (!empty($context) ? ' | ' . json_encode($context) : ''));
        }
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX: Validate coupon
     * 
     * @since   2.0.0
     */
    public function ajax_validate_coupon() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vie_coupon_nonce')) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        $code        = sanitize_text_field($_POST['coupon_code'] ?? '');
        $order_total = floatval($_POST['order_total'] ?? 0);

        $result = $this->validate_coupon($code, $order_total);

        if ($result['valid']) {
            wp_send_json_success(array(
                'message'       => $result['message'],
                'discount'      => $result['discount'],
                'coupon_code'   => $result['data']['code'],
                'discount_type' => $result['data']['discount_type'],
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Apply coupon
     * 
     * @since   2.0.0
     */
    public function ajax_apply_coupon() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vie_coupon_apply_nonce')) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        $code        = sanitize_text_field($_POST['coupon_code'] ?? '');
        $order_total = floatval($_POST['order_total'] ?? 0);
        $booking_id  = absint($_POST['booking_id'] ?? 0);

        $result = $this->apply_coupon($code, $order_total, $booking_id);

        if ($result['valid']) {
            wp_send_json_success(array(
                'message'     => $result['message'],
                'discount'    => $result['discount'],
                'coupon_code' => $code,
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Refresh coupons (admin only)
     * 
     * @since   2.0.0
     */
    public function ajax_refresh_coupons() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vie_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        $coupons = $this->get_coupons(true);

        if ($coupons !== false) {
            wp_send_json_success(array(
                'message' => 'Đã đồng bộ thành công',
                'count'   => count($coupons),
            ));
        } else {
            wp_send_json_error(array('message' => 'Không thể kết nối Google Sheets'));
        }
    }
}

/**
 * Get Coupon Manager instance (helper function)
 * 
 * @since   2.0.0
 * @return  Vie_Coupon_Manager
 */
function vie_coupon() {
    return Vie_Coupon_Manager::get_instance();
}
