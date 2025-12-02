<?php
/**
 * ============================================================================
 * TÊN FILE: CouponService.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service quản lý mã giảm giá từ Google Sheets.
 * Hỗ trợ validate, apply coupon với 2-phase locking và caching.
 *
 * CHỨC NĂNG CHÍNH:
 * - Validate coupon code với business rules
 * - Calculate discount (fixed amount only)
 * - Apply coupon (mark as used trong Google Sheets)
 * - Rate limiting để chống abuse
 * - Caching với auto-refresh
 * - 2-phase locking cho concurrent requests
 *
 * GOOGLE SHEETS STRUCTURE (4 COLUMNS):
 * - Column A: Code (unique) - Mã giảm giá
 * - Column B: Amount (VNĐ) - Số tiền giảm cố định
 * - Column C: Used At (datetime) - Thời gian sử dụng (empty = chưa dùng)
 * - Column D: Used By (customer) - Khách hàng sử dụng (tên + SĐT)
 *
 * FLOW APPLY COUPON:
 * 1. Try acquire lock (prevent double-use)
 * 2. Fresh read từ Google Sheets (not cache)
 * 3. Validate rules với fresh data
 * 4. Update Google Sheets (Column C & D)
 * 5. Release lock
 * 6. Invalidate cache
 *
 * SECURITY:
 * - Rate limiting: 10 requests/60 seconds per IP
 * - 2-phase locking: 30 seconds timeout
 * - Nonce verification trong AJAX
 * - Input sanitization
 *
 * SỬ DỤNG:
 * $coupon_service = Vie_Coupon_Service::get_instance();
 * $result = $coupon_service->validate_coupon('SUMMER2024', 5000000);
 * $result = $coupon_service->apply_coupon('SUMMER2024', 5000000, 123, 'Nguyễn Văn A - 0123456789');
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Coupon
 * @version     2.1.0
 * @since       2.0.0 (Refactored to service architecture in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Coupon_Service
 * ============================================================================
 *
 * Service quản lý coupon codes từ Google Sheets.
 *
 * ARCHITECTURE:
 * - Singleton pattern
 * - Depends on: GoogleSheetsAPI
 * - Uses: WordPress Transients API for caching & locking
 * - AJAX handlers for frontend interaction
 *
 * CACHING STRATEGY:
 * - Cache duration: 5 minutes (300 seconds)
 * - Cache key: 'vie_coupons_data'
 * - Cache invalidated after apply coupon
 * - Force refresh option available
 *
 * LOCKING STRATEGY (2-Phase Locking):
 * - Lock duration: 30 seconds
 * - Lock key: MD5 hash of coupon code
 * - Prevents concurrent double-use
 * - Auto-release after timeout
 *
 * RATE LIMITING:
 * - 10 attempts per 60 seconds per IP
 * - Separate limits for validate/apply actions
 * - Reset after timeout
 *
 * @since   2.0.0
 */
class Vie_Coupon_Service
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * Cache key cho coupons
     * @var string
     */
    const CACHE_KEY = 'vie_coupons_data';

    /**
     * Cache duration (seconds)
     * @var int
     */
    const CACHE_DURATION = 300; // 5 minutes

    /**
     * Lock prefix cho transients
     * @var string
     */
    const LOCK_PREFIX = 'vie_coupon_lock_';

    /**
     * Lock duration (seconds)
     * @var int
     */
    const LOCK_DURATION = 30;

    /**
     * Rate limit: Max attempts
     * @var int
     */
    const RATE_LIMIT_MAX = 10;

    /**
     * Rate limit: Time window (seconds)
     * @var int
     */
    const RATE_LIMIT_WINDOW = 60;

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Singleton instance
     *
     * @var Vie_Coupon_Service|null
     */
    private static $instance = null;

    /**
     * -------------------------------------------------------------------------
     * SINGLETON
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     *
     * @since   2.0.0
     * @return  Vie_Coupon_Service  Singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor (private để enforce singleton)
     *
     * Initialize WordPress hooks.
     *
     * @since   2.0.0
     */
    private function __construct()
    {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * Register AJAX handlers cho coupon validation/application.
     *
     * AJAX ACTIONS:
     * - vie_save_booking_session: Save booking data vào session (public + logged in)
     * - vie_validate_coupon: Validate coupon (public + logged in)
     * - vie_apply_coupon: Apply coupon (public + logged in)
     * - vie_refresh_coupons: Refresh cache (admin only)
     *
     * @since   2.0.0
     * @return  void
     */
    private function init_hooks()
    {
        // Save booking data to session (SECURITY: Lưu data để tính giá server-side)
        add_action('wp_ajax_vie_save_booking_session', array($this, 'ajax_save_booking_session'));
        add_action('wp_ajax_nopriv_vie_save_booking_session', array($this, 'ajax_save_booking_session'));

        // AJAX handlers (public + logged in)
        add_action('wp_ajax_vie_validate_coupon', array($this, 'ajax_validate_coupon'));
        add_action('wp_ajax_nopriv_vie_validate_coupon', array($this, 'ajax_validate_coupon'));

        add_action('wp_ajax_vie_apply_coupon', array($this, 'ajax_apply_coupon'));
        add_action('wp_ajax_nopriv_vie_apply_coupon', array($this, 'ajax_apply_coupon'));

        // Admin-only refresh
        add_action('wp_ajax_vie_refresh_coupons', array($this, 'ajax_refresh_coupons'));
    }

    /**
     * -------------------------------------------------------------------------
     * VALIDATION METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Validate coupon code
     *
     * Kiểm tra coupon có valid không (chưa dùng, amount hợp lệ, etc.)
     *
     * VALIDATION FLOW:
     * 1. Sanitize code
     * 2. Check empty
     * 3. Check rate limit
     * 4. Get coupon từ cache
     * 5. Validate business rules
     * 6. Calculate discount
     * 7. Return result
     *
     * @since   2.0.0
     * @param   string  $code           Coupon code
     * @param   float   $order_total    Order total (để calculate discount)
     * @return  array                   ['valid' => bool, 'message' => string, 'discount' => float, 'data' => array]
     */
    public function validate_coupon($code, $order_total = 0)
    {
        // STEP 1: Sanitize
        $code = $this->sanitize_code($code);

        // STEP 2: Check empty
        if (empty($code)) {
            return $this->error_response('Vui lòng nhập mã giảm giá');
        }

        // STEP 3: Check rate limit
        if (!$this->check_rate_limit('validate')) {
            return $this->error_response('Bạn đã thử quá nhiều lần. Vui lòng đợi một chút.');
        }

        // STEP 4: Get coupon from cache
        $coupon = $this->get_coupon($code);

        if (!$coupon) {
            return $this->error_response('Mã giảm giá không tồn tại hoặc đã hết hạn');
        }

        // STEP 5: Validate business rules
        $validation = $this->validate_rules($coupon, $order_total);

        if (!$validation['valid']) {
            return $validation;
        }

        // STEP 6: Calculate discount
        $discount = $this->calculate_discount($coupon, $order_total);

        // STEP 7: Success response
        return array(
            'valid'    => true,
            'message'  => sprintf('Áp dụng thành công! Giảm %s', vie_format_currency($discount)),
            'discount' => $discount,
            'data'     => $coupon,
        );
    }

    /**
     * Validate business rules
     *
     * Check các điều kiện business của coupon.
     *
     * RULES (Simplified Version):
     * - Chưa được sử dụng (used_at phải empty)
     *
     * @since   2.0.0
     * @param   array   $coupon         Coupon data
     * @param   float   $order_total    Order total
     * @return  array                   ['valid' => bool, 'message' => string]
     */
    private function validate_rules($coupon, $order_total)
    {
        // Check if already used (Column C has value)
        if (!empty($coupon['used_at'])) {
            return $this->error_response('Mã giảm giá đã được sử dụng');
        }

        // In simplified version, no other validation needed
        return array('valid' => true);
    }

    /**
     * -------------------------------------------------------------------------
     * APPLY COUPON (2-PHASE LOCKING)
     * -------------------------------------------------------------------------
     */

    /**
     * Apply coupon (mark as used in Google Sheets)
     *
     * Đánh dấu coupon đã sử dụng trong Google Sheets với 2-phase locking.
     *
     * APPLY FLOW (2-Phase Locking):
     * 1. Try acquire lock (prevent concurrent access)
     * 2. Fresh read từ Google Sheets (NOT cache!)
     * 3. Validate với fresh data
     * 4. Update Google Sheets (Column C & D)
     * 5. Release lock
     * 6. Invalidate cache
     * 7. Return result
     *
     * WHY 2-PHASE LOCKING:
     * - Prevent double-use khi 2 users cùng apply
     * - Fresh read ensures data consistency
     * - Lock timeout: 30 seconds
     *
     * @since   2.0.0
     * @param   string  $code           Coupon code
     * @param   float   $order_total    Order total
     * @param   int     $booking_id     Booking ID (for logging)
     * @param   string  $customer_info  Customer info (name + phone)
     * @return  array                   ['valid' => bool, 'message' => string, 'discount' => float]
     */
    public function apply_coupon($code, $order_total, $booking_id = 0, $customer_info = '')
    {
        $this->log("--- APPLY COUPON START: $code ---");

        // STEP 1: Sanitize
        $code = $this->sanitize_code($code);

        // STEP 2: Try acquire lock
        if (!$this->try_lock($code)) {
            $this->log("Lock acquisition failed for code: $code");
            return $this->error_response('Mã đang được xử lý. Vui lòng thử lại sau vài giây.');
        }

        // STEP 3: Fresh read từ Google Sheets (NOT cache!)
        $fresh_coupons = $this->fetch_coupons_from_sheets();

        if ($fresh_coupons === false) {
            $this->unlock($code);
            $this->log("Failed to fetch fresh coupons from Google Sheets");
            return $this->error_response('Không thể kết nối Google Sheets. Vui lòng thử lại.');
        }

        // Find coupon trong fresh data
        $coupon = null;
        foreach ($fresh_coupons as $c) {
            if (strtoupper($c['code']) === $code) {
                $coupon = $c;
                break;
            }
        }

        if (!$coupon) {
            $this->unlock($code);
            $this->log("Coupon code not found in fresh data: $code");
            return $this->error_response('Mã không tồn tại');
        }

        // STEP 4: Validate với fresh data
        $validation = $this->validate_rules($coupon, $order_total);

        if (!$validation['valid']) {
            $this->unlock($code);
            $this->log("Validation failed: " . $validation['message']);
            return $validation;
        }

        // STEP 5: Mark as used trong Google Sheets
        // CRITICAL FIX: Re-verify row index using find_coupon_row to ensure accuracy
        $row_number = $this->get_coupon_row_index($code);
        $this->log("Row index determined via search: " . ($row_number ?: 'FALSE'));
        
        if ($row_number === false) {
             // Fallback to cached index if find fails (unlikely if coupon exists)
             $row_number = $coupon['row_index'] ?? 0;
             $this->log("Fallback to row index from fetch: $row_number");
        }

        if ($row_number > 0) {
            $this->log("Attempting to update usage at Row $row_number");
            $update_success = $this->update_coupon_usage($row_number, $customer_info);

            if (!$update_success) {
                $this->unlock($code);
                $this->log("CRITICAL ERROR: update_coupon_usage returned FALSE");
                return $this->error_response('Lỗi khi cập nhật mã. Vui lòng liên hệ hỗ trợ.');
            } else {
                $this->log("SUCCESS: Google Sheets updated successfully for Row $row_number");
            }
        } else {
            $this->unlock($code);
            $this->log("ERROR: Invalid row number detected: $row_number");
            return $this->error_response('Lỗi dữ liệu mã giảm giá (Row Index invalid).');
        }

        // STEP 6: Release lock
        $this->unlock($code);

        // STEP 7: Invalidate cache
        delete_transient(self::CACHE_KEY);

        // Calculate discount
        $discount = $this->calculate_discount($coupon, $order_total);

        // Log activity
        $this->log('Coupon applied successfully', array(
            'code'          => $code,
            'booking_id'    => $booking_id,
            'discount'      => $discount,
            'customer_info' => $customer_info,
            'row_updated'   => $row_number
        ));

        return array(
            'valid'    => true,
            'message'  => 'Áp dụng mã thành công!',
            'discount' => $discount,
            'data'     => $coupon,
        );
    }

    /**
     * Helper to get accurate row index
     */
    private function get_coupon_row_index($code) {
        if (!class_exists('Vie_Google_Sheets_API')) {
             if (defined('VIE_THEME_PATH') && file_exists(VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php')) {
                require_once VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php';
            }
        }
        
        if (class_exists('Vie_Google_Sheets_API')) {
            $sheets_api = new Vie_Google_Sheets_API();
            // Force clear cache or ensure we search properly? find_coupon_row reads fresh.
            return $sheets_api->find_coupon_row($code);
        }
        return false;
    }

    /**
     * -------------------------------------------------------------------------
     * DISCOUNT CALCULATION
     * -------------------------------------------------------------------------
     */

    /**
     * Calculate discount amount
     *
     * Tính số tiền giảm giá (fixed amount only).
     *
     * LOGIC (Simplified):
     * - Discount = Column B value (fixed amount)
     * - Don't exceed order total
     *
     * @since   2.0.0
     * @param   array   $coupon         Coupon data
     * @param   float   $order_total    Order total
     * @return  float                   Discount amount
     */
    public function calculate_discount($coupon, $order_total)
    {
        // Get fixed amount từ Column B
        $discount = floatval($coupon['amount'] ?? 0);

        // Don't exceed order total
        $discount = min($discount, $order_total);

        return floatval($discount);
    }

    /**
     * -------------------------------------------------------------------------
     * COUPON DATA METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get all coupons (from cache or Google Sheets)
     *
     * Lấy danh sách tất cả coupons với caching.
     *
     * CACHING:
     * - Cache duration: 5 minutes
     * - Force refresh option: $force_refresh = true
     * - Cache invalidated after apply coupon
     *
     * @since   2.0.0
     * @param   bool    $force_refresh  Force refresh từ Sheets
     * @return  array                   Array of coupons
     */
    public function get_coupons($force_refresh = false)
    {
        // Check cache first (if not forcing refresh)
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);

            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch từ Google Sheets
        $coupons = $this->fetch_coupons_from_sheets();

        // Cache if successful
        if ($coupons !== false) {
            set_transient(self::CACHE_KEY, $coupons, self::CACHE_DURATION);
        }

        return $coupons ?: array();
    }

    /**
     * Get single coupon by code
     *
     * Tìm coupon theo code từ cached list.
     *
     * @since   2.0.0
     * @param   string      $code   Coupon code
     * @return  array|null          Coupon data hoặc null
     */
    public function get_coupon($code)
    {
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
     * GOOGLE SHEETS INTEGRATION
     * -------------------------------------------------------------------------
     */

    /**
     * Fetch coupons từ Google Sheets
     *
     * Đọc dữ liệu từ Google Sheets (4 columns).
     *
     * SHEET STRUCTURE:
     * - Row 1: Header (skipped)
     * - Column A: Code (unique)
     * - Column B: Amount (VNĐ)
     * - Column C: Used At (datetime)
     * - Column D: Used By (customer)
     *
     * CONFIGURATION:
     * - VIE_COUPON_SHEET_ID: Sheet ID (required)
     * - VIE_COUPON_SHEET_NAME: Sheet name (default: 'Coupons')
     *
     * @since   2.0.0
     * @return  array|false     Array of coupons hoặc false nếu lỗi
     */
    private function fetch_coupons_from_sheets()
    {
        // Check if GoogleSheetsAPI available
        if (!class_exists('Vie_Google_Sheets_API')) {
            // Try to load if missing
            if (defined('VIE_THEME_PATH') && file_exists(VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php')) {
                require_once VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php';
            }

            if (!class_exists('Vie_Google_Sheets_API')) {
                $this->log('Google Sheets API class not found');
                return false;
            }
        }

        $sheets_api = new Vie_Google_Sheets_API();

        // Get config từ DB settings (priority) hoặc constants
        $gsheets_settings = get_option('vie_gsheets_settings', array());
        $sheet_name       = !empty($gsheets_settings['sheet_name']) ? $gsheets_settings['sheet_name'] : '';

        // Fallback to constants
        if (empty($sheet_name)) {
            $sheet_name = defined('VIE_COUPON_SHEET_NAME') ? VIE_COUPON_SHEET_NAME : 'Coupons';
        }

        $sheet_id = defined('VIE_COUPON_SHEET_ID') ? VIE_COUPON_SHEET_ID : '';
        
        // Allow overriding Sheet ID from DB if needed (though API class handles it too)
        if (!empty($gsheets_settings['spreadsheet_id'])) {
             // Note: CouponService primarily relies on constant for ID, but let's stick to current logic for ID
             // The API class handles the ID connection.
        }

        $range = $sheet_name . '!A2:D'; // Columns A-D, skip header row

        if (empty($sheet_id) && empty($gsheets_settings['spreadsheet_id']) && !defined('VL_COUPON_SHEET_ID')) {
            $this->log('Coupon Sheet ID not configured');
            // Continue anyway, maybe API class has it
        }

        // Read data từ Sheets
        $data = $sheets_api->read_range($range);

        if ($data === false) {
            return false;
        }

        // Parse data thành array of coupons
        $coupons   = array();
        // $row_index = 2; // Start from row 2 (after header) - REMOVED insecure counter

        foreach ($data as $index => $row) {
            // Calculate row index based on array index + 2 (assuming A2 start)
            // Google Sheets API returns array indexed from 0 for the requested range.
            // Range starts at Row 2. So Index 0 = Row 2.
            $current_row_index = $index + 2;

            // Skip empty rows (if API returns them)
            if (empty($row[0])) {
                continue;
            }

            // FIX: Sanitize amount (remove dots/commas for formats like 20.000)
            $raw_amount = $row[1] ?? 0;
            $clean_amount = preg_replace('/[^0-9]/', '', $raw_amount);

            $coupons[] = array(
                'code'      => strtoupper(trim($row[0] ?? '')),   // Column A: Code
                'amount'    => floatval($clean_amount),            // Column B: Amount
                'used_at'   => trim($row[2] ?? ''),                // Column C: Used At
                'used_by'   => trim($row[3] ?? ''),                // Column D: Used By
                'row_index' => $current_row_index,                 // For updating later
            );
        }

        return $coupons;
    }

    /**
     * Update coupon usage trong Google Sheets
     *
     * Đánh dấu coupon đã sử dụng bằng cách update Column C và D.
     *
     * UPDATES:
     * - Column C: Current datetime (Y-m-d H:i:s)
     * - Column D: Customer info (name + phone)
     *
     * @since   2.0.0
     * @param   int     $row_number     Row number trong sheet
     * @param   string  $customer_info  Customer info (name + phone)
     * @return  bool                    true nếu thành công
     */
    private function update_coupon_usage($row_number, $customer_info = '')
    {
        if (!class_exists('Vie_Google_Sheets_API')) {
            // Try to load if missing
            if (defined('VIE_THEME_PATH') && file_exists(VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php')) {
                require_once VIE_THEME_PATH . '/inc/Services/Integration/GoogleSheetsAPI.php';
            }

            if (!class_exists('Vie_Google_Sheets_API')) {
                $this->log('Google Sheets API class not found');
                return false;
            }
        }

        $sheets_api = new Vie_Google_Sheets_API();

        // Get config từ DB settings (priority) hoặc constants
        $gsheets_settings = get_option('vie_gsheets_settings', array());
        $sheet_name       = !empty($gsheets_settings['sheet_name']) ? $gsheets_settings['sheet_name'] : '';

        if (empty($sheet_name)) {
            $sheet_name = defined('VIE_COUPON_SHEET_NAME') ? VIE_COUPON_SHEET_NAME : 'Coupons';
        }

        // Build range strings
        $range_c = sprintf('%s!C%d', $sheet_name, $row_number); // Column C: Used At
        $range_d = sprintf('%s!D%d', $sheet_name, $row_number); // Column D: Used By

        $current_datetime = current_time('Y-m-d H:i:s');

        $this->log(sprintf('Updating coupon usage at Row %d (Range: %s)', $row_number, $range_c));

        // Update Column C: Used At
        $result_c = $sheets_api->update_range($range_c, array(array($current_datetime)));
        if (!$result_c) $this->log('Failed to update Column C (Used At)');

        // Update Column D: Used By
        $result_d = $sheets_api->update_range($range_d, array(array($customer_info)));
        if (!$result_d) $this->log('Failed to update Column D (Used By)');

        return $result_c && $result_d;
    }

    /**
     * -------------------------------------------------------------------------
     * LOCKING METHODS (2-Phase Locking)
     * -------------------------------------------------------------------------
     */

    /**
     * Try to acquire lock cho coupon
     *
     * Locking mechanism để prevent concurrent double-use.
     *
     * LOCK STRATEGY:
     * - Lock key: MD5 hash of coupon code
     * - Lock duration: 30 seconds
     * - Uses WordPress transients
     *
     * @since   2.0.0
     * @param   string  $code   Coupon code
     * @return  bool            true nếu acquire thành công
     */
    private function try_lock($code)
    {
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
     * Release lock cho coupon
     *
     * @since   2.0.0
     * @param   string  $code   Coupon code
     * @return  void
     */
    private function unlock($code)
    {
        $key = self::LOCK_PREFIX . md5($code);
        delete_transient($key);
    }

    /**
     * -------------------------------------------------------------------------
     * RATE LIMITING
     * -------------------------------------------------------------------------
     */

    /**
     * Check rate limit
     *
     * Rate limiting để chống abuse.
     *
     * LIMITS:
     * - 10 attempts per 60 seconds per IP
     * - Separate tracking for different actions
     * - Auto-reset after time window
     *
     * @since   2.0.0
     * @param   string  $action Action name (validate, apply)
     * @return  bool            true nếu còn trong limit
     */
    private function check_rate_limit($action)
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'vie_coupon_rate_' . md5($ip . '_' . $action);

        $attempts = get_transient($key) ?: 0;

        // Check if exceeded
        if ($attempts >= self::RATE_LIMIT_MAX) {
            return false;
        }

        // Increment counter
        set_transient($key, $attempts + 1, self::RATE_LIMIT_WINDOW);

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Calculate order total từ session data (SERVER-SIDE)
     *
     * Tính tổng tiền booking từ session data.
     * KHÔNG tin tưởng bất kỳ giá trị nào từ client!
     *
     * CALCULATION LOGIC (Updated to use PricingService):
     * 1. Get room_id từ session
     * 2. Validate room exists và available
     * 3. Use PricingService to calculate price với pricing schedule
     * 4. Return total từ PricingService (trusted source)
     *
     * @since   2.0.0
     * @param   array   $booking_data   Session booking data
     * @return  float                   Total amount (server-calculated)
     */
    private function calculate_order_total_from_session($booking_data)
    {
        error_log('[VIE DEBUG] calculate_order_total_from_session called');
        error_log('[VIE DEBUG] Booking data: ' . print_r($booking_data, true));

        // Validate required fields
        if (empty($booking_data['room_id']) || empty($booking_data['check_in']) || empty($booking_data['check_out'])) {
            error_log('[VIE DEBUG] Missing required fields!');
            return 0;
        }

        // Check if PricingService is available
        if (!class_exists('Vie_Pricing_Service')) {
            error_log('[VIE DEBUG] PricingService not found! Fallback to legacy calculation.');
            return $this->calculate_order_total_legacy($booking_data);
        }

        // Use PricingService to calculate (SECURE - uses pricing schedule)
        $pricing_service = Vie_Pricing_Service::get_instance();

        error_log('[VIE DEBUG] Using PricingService to calculate...');
        error_log('[VIE DEBUG] Params: room_id=' . $booking_data['room_id'] . ', check_in=' . $booking_data['check_in'] . ', check_out=' . $booking_data['check_out']);

        // Calculate price using PricingService
        // Method signature: calculate_booking_price(array $params)
        $result = $pricing_service->calculate_booking_price($booking_data);

        error_log('[VIE DEBUG] PricingService result: ' . print_r($result, true));

        // Check if result is WP_Error
        if (is_wp_error($result)) {
            error_log('[VIE DEBUG] PricingService returned WP_Error: ' . $result->get_error_message());
            return 0;
        }

        if (!$result || !is_array($result)) {
            error_log('[VIE DEBUG] PricingService returned invalid result');
            return 0;
        }

        // Get grand_total from result (includes room price + surcharges)
        $grand_total = floatval($result['grand_total'] ?? 0);

        error_log('[VIE DEBUG] Calculated grand_total: ' . $grand_total);

        return $grand_total;
    }

    /**
     * Legacy calculation (fallback)
     *
     * @deprecated Use PricingService instead
     * @param array $booking_data
     * @return float
     */
    private function calculate_order_total_legacy($booking_data)
    {
        global $wpdb;

        $room_id = absint($booking_data['room_id']);
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hotel_rooms WHERE id = %d",
            $room_id
        ));

        if (!$room) {
            return 0;
        }

        $room_price = floatval($room->price ?? 0);

        if ($room_price <= 0) {
            return 0;
        }

        $check_in = strtotime($booking_data['check_in']);
        $check_out = strtotime($booking_data['check_out']);

        if (!$check_in || !$check_out || $check_out <= $check_in) {
            return 0;
        }

        $num_nights = ceil(($check_out - $check_in) / DAY_IN_SECONDS);
        $num_rooms = absint($booking_data['num_rooms'] ?? 1);

        if ($num_rooms <= 0) {
            $num_rooms = 1;
        }

        $total = $room_price * $num_rooms * $num_nights;

        return floatval($total);
    }

    /**
     * Sanitize coupon code
     *
     * Clean và normalize coupon code.
     *
     * SANITIZATION:
     * - Trim whitespace
     * - Convert to uppercase
     * - Remove non-alphanumeric characters
     *
     * @since   2.0.0
     * @param   string  $code   Raw coupon code
     * @return  string          Sanitized code
     */
    private function sanitize_code($code)
    {
        $code = sanitize_text_field($code);
        $code = strtoupper(trim($code));
        $code = preg_replace('/[^A-Z0-9]/', '', $code);

        return $code;
    }

    /**
     * Error response helper
     *
     * Generate standardized error response.
     *
     * @since   2.0.0
     * @param   string  $message    Error message
     * @return  array               Error response array
     */
    private function error_response($message)
    {
        return array(
            'valid'    => false,
            'message'  => $message,
            'discount' => 0,
            'data'     => null,
        );
    }

    /**
     * Log helper
     *
     * Log messages if debug enabled.
     *
     * @since   2.0.0
     * @param   string  $message    Log message
     * @param   array   $context    Additional context data
     * @return  void
     */
    private function log($message, $context = array())
    {
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            $log_message = '[Coupon Service] ' . $message;

            if (!empty($context)) {
                $log_message .= ' | ' . json_encode($context);
            }

            error_log($log_message);
        }
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX: Save booking data to session
     *
     * Lưu thông tin booking vào session để tính giá server-side.
     * SECURITY: Không tin tưởng order_total từ client!
     *
     * REQUEST PARAMS:
     * - nonce: Security nonce
     * - room_id: Room ID
     * - check_in: Check-in date (Y-m-d)
     * - check_out: Check-out date (Y-m-d)
     * - num_rooms: Number of rooms
     * - num_adults: Number of adults
     * - num_children: Number of children
     * - customer_name: Customer name (optional)
     * - customer_phone: Customer phone (optional)
     *
     * RESPONSE:
     * - Success: {message, session_id}
     * - Error: {message}
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_booking_session()
    {
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'vie_booking_nonce')) {
            wp_send_json_error(array('message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'));
        }

        // Start session
        if (!isset($_SESSION)) {
            session_start();
        }

        // Get and sanitize booking data
        $booking_data = array(
            'room_id'        => absint($_POST['room_id'] ?? 0),
            'check_in'       => sanitize_text_field($_POST['check_in'] ?? ''),
            'check_out'      => sanitize_text_field($_POST['check_out'] ?? ''),
            'num_rooms'      => absint($_POST['num_rooms'] ?? 1),
            'num_adults'     => absint($_POST['num_adults'] ?? 2),
            'num_children'   => absint($_POST['num_children'] ?? 0),
            'children_ages'  => isset($_POST['children_ages']) && is_array($_POST['children_ages']) ? array_map('absint', $_POST['children_ages']) : array(),
            'price_type'     => sanitize_text_field($_POST['price_type'] ?? 'room'),
            'customer_name'  => sanitize_text_field($_POST['customer_name'] ?? ''),
            'customer_phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            'coupon_code'    => sanitize_text_field($_POST['coupon_code'] ?? ''),
            'discount_amount'=> floatval($_POST['discount_amount'] ?? 0),
            'timestamp'      => current_time('timestamp'),
        );

        error_log('[VIE DEBUG] ajax_save_booking_session - booking_data: ' . print_r($booking_data, true));

        // Validate required fields
        if (empty($booking_data['room_id']) || empty($booking_data['check_in']) || empty($booking_data['check_out'])) {
            error_log('[VIE DEBUG] Missing required fields in ajax_save_booking_session');
            wp_send_json_error(array('message' => 'Thiếu thông tin bắt buộc'));
        }

        // Save to session
        $_SESSION['vie_booking_data'] = $booking_data;

        error_log('[VIE DEBUG] Saved to session: ' . print_r($_SESSION['vie_booking_data'], true));

        // Calculate order_total server-side using PricingService
        $order_total = $this->calculate_order_total_from_session($booking_data);

        error_log('[VIE DEBUG] Calculated order_total: ' . $order_total);

        // Return full pricing verification data
        wp_send_json_success(array(
            'message'     => 'Đã lưu thông tin booking',
            'session_id'  => session_id(),
            'order_total' => $order_total, // Server-calculated total for verification
            'booking_data' => array(
                'room_id'      => $booking_data['room_id'],
                'check_in'     => $booking_data['check_in'],
                'check_out'    => $booking_data['check_out'],
                'num_rooms'    => $booking_data['num_rooms'],
                'num_adults'   => $booking_data['num_adults'],
                'num_children' => $booking_data['num_children'],
                'price_type'   => $booking_data['price_type'],
            )
        ));
    }

    /**
     * AJAX: Validate coupon
     *
     * AJAX handler cho validate coupon request.
     *
     * SECURITY:
     * - Order total được tính từ session data, KHÔNG tin tưởng client
     * - Nonce verification
     * - Rate limiting
     *
     * REQUEST PARAMS:
     * - nonce: Security nonce
     * - coupon_code: Coupon code
     *
     * SESSION DATA (Required):
     * - vie_booking_data: {room_id, check_in, check_out, num_rooms, num_adults, num_children, ...}
     *
     * RESPONSE:
     * - Success: {message, discount, coupon_code, discount_type}
     * - Error: {message}
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_validate_coupon()
    {
        // DEBUG: Log request
        error_log('[VIE DEBUG] ajax_validate_coupon called');
        error_log('[VIE DEBUG] POST data: ' . print_r($_POST, true));

        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        error_log('[VIE DEBUG] Nonce received: ' . $nonce);
        error_log('[VIE DEBUG] Nonce expected: vie_booking_nonce');

        if (!wp_verify_nonce($nonce, 'vie_booking_nonce')) {
            error_log('[VIE DEBUG] NONCE VERIFICATION FAILED!');
            wp_send_json_error(array('message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'));
        }

        error_log('[VIE DEBUG] Nonce verified successfully');

        // Get coupon code
        $code = sanitize_text_field($_POST['coupon_code'] ?? '');
        error_log('[VIE DEBUG] Coupon code: ' . $code);

        // SECURITY: Get booking data from session (SERVER-SIDE)
        if (!isset($_SESSION)) {
            error_log('[VIE DEBUG] Starting session...');
            session_start();
        }

        error_log('[VIE DEBUG] Session ID: ' . session_id());
        error_log('[VIE DEBUG] Session data: ' . print_r($_SESSION, true));

        $booking_data = $_SESSION['vie_booking_data'] ?? null;

        if (!$booking_data) {
            error_log('[VIE DEBUG] No booking data in session!');
            wp_send_json_error(array('message' => 'Không tìm thấy thông tin đặt phòng. Vui lòng chọn phòng trước.'));
        }

        error_log('[VIE DEBUG] Booking data found: ' . print_r($booking_data, true));

        // Calculate order_total from session data (SERVER-SIDE)
        $order_total = $this->calculate_order_total_from_session($booking_data);
        error_log('[VIE DEBUG] Calculated order_total: ' . $order_total);

        if ($order_total <= 0) {
            error_log('[VIE DEBUG] Invalid order_total!');
            wp_send_json_error(array('message' => 'Không thể tính tổng tiền. Vui lòng thử lại.'));
        }

        // Validate coupon
        error_log('[VIE DEBUG] Validating coupon...');
        $result = $this->validate_coupon($code, $order_total);
        error_log('[VIE DEBUG] Validation result: ' . print_r($result, true));

        // Send response
        if ($result['valid']) {
            error_log('[VIE DEBUG] Coupon valid! Sending success response');
            wp_send_json_success(array(
                'message'       => $result['message'],
                'discount'      => $result['discount'],
                'coupon_code'   => $result['data']['code'],
                'discount_type' => 'fixed', // Always fixed in simplified version
                'order_total'   => $order_total, // Return calculated total to verify
            ));
        } else {
            error_log('[VIE DEBUG] Coupon invalid: ' . $result['message']);
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Apply coupon (VALIDATE ONLY - không update Google Sheets)
     *
     * AJAX handler để VALIDATE coupon cho booking.
     * Parse booking data từ session (SERVER-SIDE, secure).
     * 
     * QUAN TRỌNG: Function này CHỈ validate coupon, KHÔNG update Google Sheets.
     * Google Sheets sẽ được update trong submit_booking() khi có booking_id thực sự.
     *
     * REQUEST PARAMS:
     * - coupon_code: Mã giảm giá
     * - booking_id: ID booking (có thể là 0 nếu chưa tạo booking)
     * - nonce: Security nonce (vie_booking_nonce)
     *
     * SESSION DATA (SERVER-SIDE):
     * - room_id, check_in, check_out, num_rooms
     * - num_adults, num_children, children_ages
     *
     * RESPONSE:
     * - Success: {message, discount, coupon_code}
     * - Error: {message}
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_apply_coupon()
    {
        // Verify nonce
        $nonce = $_POST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'vie_booking_nonce')) {
            wp_send_json_error(array('message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'));
        }

        // Get params
        $code = sanitize_text_field($_POST['coupon_code'] ?? '');
        $booking_id = absint($_POST['booking_id'] ?? 0);

        // SECURITY: Get booking data from session (SERVER-SIDE)
        if (!isset($_SESSION)) {
            session_start();
        }

        $booking_data = $_SESSION['vie_booking_data'] ?? null;

        if (!$booking_data) {
            wp_send_json_error(array('message' => 'Không tìm thấy thông tin đặt phòng. Vui lòng chọn phòng trước.'));
        }

        // Calculate order_total from session data (SERVER-SIDE)
        $order_total = $this->calculate_order_total_from_session($booking_data);

        if ($order_total <= 0) {
            wp_send_json_error(array('message' => 'Không thể tính tổng tiền. Vui lòng thử lại.'));
        }

        // VALIDATE ONLY - không update Google Sheets
        // Google Sheets sẽ được update trong submit_booking()
        $result = $this->validate_coupon($code, $order_total);

        // Send response
        if ($result['valid']) {
            wp_send_json_success(array(
                'message'     => $result['message'],
                'discount'    => $result['discount'],
                'coupon_code' => $code,
                'order_total' => $order_total, // Return calculated total
            ));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * AJAX: Refresh coupons (admin only)
     *
     * AJAX handler để force refresh coupon cache từ Google Sheets.
     *
     * REQUEST PARAMS:
     * - nonce: Security nonce (vie_admin_nonce)
     *
     * RESPONSE:
     * - Success: {message, count}
     * - Error: {message}
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_refresh_coupons()
    {
        // Check permission
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vie_admin_nonce')) {
            wp_send_json_error(array('message' => 'Invalid request'));
        }

        // Force refresh
        $coupons = $this->get_coupons(true);

        // Send response
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
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Class alias for backward compatibility
if (!class_exists('Vie_Coupon_Manager')) {
    class_alias('Vie_Coupon_Service', 'Vie_Coupon_Manager');
}

/**
 * Helper function: Get Coupon Service instance
 *
 * @since   2.0.0
 * @return  Vie_Coupon_Service
 */
function vie_coupon()
{
    return Vie_Coupon_Service::get_instance();
}
