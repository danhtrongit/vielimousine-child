<?php
/**
 * ============================================================================
 * TÊN FILE: GoogleSheetsAPI.php
 * ============================================================================
 *
 * MÔ TẢ:
 * HTTP Client cho Google Sheets API v4.
 * Sử dụng wp_remote_request(), không cần external dependencies.
 *
 * CHỨC NĂNG CHÍNH:
 * - Read data từ Google Sheets
 * - Update data trong ranges cụ thể
 * - Append rows vào cuối sheet
 * - Find row number của data cụ thể
 * - Test connection
 *
 * API ENDPOINTS:
 * - GET    /values/{range}           Read data
 * - PUT    /values/{range}           Update data
 * - POST   /values/{range}:append    Append data
 *
 * SỬ DỤNG:
 * $sheets = new Vie_Google_Sheets_API();
 * $data = $sheets->read_range('Coupons!A2:F100');
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Integration
 * @version     2.1.0
 * @since       2.0.0 (Di chuyển từ inc/classes trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Google_Sheets_API
 * ============================================================================
 *
 * Google Sheets API v4 client implementation.
 *
 * ARCHITECTURE:
 * - Uses Vie_Google_Auth for authentication
 * - All requests use Bearer token authentication
 * - Automatic retry on 401 (token expired)
 * - Comprehensive error logging
 *
 * RATE LIMITS:
 * - Read requests: 100 per 100 seconds per user
 * - Write requests: 100 per 100 seconds per user
 * - Current implementation doesn't handle rate limiting
 *
 * @since   2.0.0
 * @uses    Vie_Google_Auth     For access token management
 * @link    https://developers.google.com/sheets/api/reference/rest
 */
class Vie_Google_Sheets_API
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Google Auth instance
     * @var Vie_Google_Auth
     */
    private $auth;

    /**
     * Google Sheet ID
     *
     * Lấy từ URL: https://docs.google.com/spreadsheets/d/{SHEET_ID}/edit
     *
     * @var string
     */
    private $sheet_id;

    /**
     * API base URL
     * @var string
     */
    private $api_base_url;

    /**
     * API timeout (seconds)
     * @var int
     */
    private $timeout;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Khởi tạo Google Auth và load configuration từ database (priority) hoặc constants (fallback).
     *
     * CONFIGURATION PRIORITY:
     * 1. Database (vie_gsheets_settings option)
     * 2. Constants (VL_COUPON_SHEET_ID, etc.)
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        // Initialize Google Auth
        $this->auth = Vie_Google_Auth::get_instance();

        // Load configuration từ database settings (priority)
        $gsheets_settings = get_option('vie_gsheets_settings', array());

        if (!empty($gsheets_settings['spreadsheet_id'])) {
            // Use database settings
            $this->sheet_id = $gsheets_settings['spreadsheet_id'];
        } elseif (defined('VL_COUPON_SHEET_ID')) {
            // Fallback to constant
            $this->sheet_id = VL_COUPON_SHEET_ID;
        } else {
            $this->sheet_id = '';
        }

        // API configuration
        $this->api_base_url = defined('VL_GOOGLE_SHEETS_API_URL')
            ? VL_GOOGLE_SHEETS_API_URL
            : 'https://sheets.googleapis.com/v4/spreadsheets';

        $this->timeout = defined('VL_GOOGLE_API_TIMEOUT')
            ? VL_GOOGLE_API_TIMEOUT
            : 10;

        // Validate auth configuration
        if (!$this->auth->is_valid() && defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Sheets API] Google Auth is not properly configured');
        }

        if (empty($this->sheet_id) && defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Sheets API] Spreadsheet ID not configured in database or constants');
        }
    }

    /**
     * -------------------------------------------------------------------------
     * READ OPERATIONS
     * -------------------------------------------------------------------------
     */

    /**
     * Đọc dữ liệu từ Google Sheets
     *
     * API ENDPOINT:
     * GET https://sheets.googleapis.com/v4/spreadsheets/{spreadsheetId}/values/{range}
     *
     * RANGE NOTATION:
     * - "Sheet1!A1:B2"      Specific range
     * - "Sheet1!A:B"        Entire columns
     * - "Sheet1!A2:B"       From row 2 to end
     * - "Sheet1"            Entire sheet
     *
     * RESPONSE:
     * {
     *   "range": "Sheet1!A1:B2",
     *   "majorDimension": "ROWS",
     *   "values": [
     *     ["A1", "B1"],
     *     ["A2", "B2"]
     *   ]
     * }
     *
     * @since   2.0.0
     * @param   string      $range  Range notation (null = use default from database/constants)
     * @return  array|false         2D array of values hoặc false nếu lỗi
     */
    public function read_range($range = null)
    {
        if ($range === null) {
            // Load from database settings (priority)
            $gsheets_settings = get_option('vie_gsheets_settings', array());

            if (!empty($gsheets_settings['sheet_name']) && !empty($gsheets_settings['sheet_range'])) {
                $range = $gsheets_settings['sheet_name'] . '!' . $gsheets_settings['sheet_range'];
            } elseif (defined('VL_COUPON_SHEET_RANGE')) {
                // Fallback to constant
                $range = VL_COUPON_SHEET_RANGE;
            } else {
                $range = 'Sheet1!A1:Z1000';
            }
        }

        // Get access token
        $access_token = $this->auth->get_access_token();
        if (!$access_token) {
            error_log('[Vie Google Sheets API ERROR] Cannot read sheet: no access token');
            return false;
        }

        // Build API URL
        $url = sprintf(
            '%s/%s/values/%s',
            $this->api_base_url,
            $this->sheet_id,
            urlencode($range)
        );

        // Make GET request
        $response = wp_remote_get($url, [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept'        => 'application/json'
            ]
        ]);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log(sprintf(
                '[Vie Google Sheets API ERROR] HTTP error when reading sheet (%s): %s',
                $range,
                $response->get_error_message()
            ));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Handle non-200 status codes
        if ($status_code !== 200) {
            error_log(sprintf(
                '[Vie Google Sheets API ERROR] Google Sheets API error (read) - HTTP %d: %s (Range: %s)',
                $status_code,
                $body,
                $range
            ));

            // Auto-retry on 401 (token expired)
            if ($status_code === 401) {
                if (defined('VIE_DEBUG') && VIE_DEBUG) {
                    error_log('[Vie Google Sheets API] Token expired, attempting refresh');
                }
                $this->auth->refresh_token();
                // Note: Could implement automatic retry here
            }

            return false;
        }

        // Parse response
        $data = json_decode($body, true);

        if (!isset($data['values'])) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log(sprintf('[Vie Google Sheets API] No values found in range: %s', $range));
            }
            return [];
        }

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf(
                '[Vie Google Sheets API] Sheet read successfully - Range: %s, Rows: %d',
                $range,
                count($data['values'])
            ));
        }

        return $data['values'];
    }

    /**
     * -------------------------------------------------------------------------
     * WRITE OPERATIONS
     * -------------------------------------------------------------------------
     */

    /**
     * Cập nhật dữ liệu trong Google Sheets
     *
     * API ENDPOINT:
     * PUT https://sheets.googleapis.com/v4/spreadsheets/{spreadsheetId}/values/{range}?valueInputOption=RAW
     *
     * VALUE INPUT OPTIONS:
     * - RAW:         Values are stored as-is
     * - USER_ENTERED: Values are parsed as if typed by user (formulas evaluated)
     *
     * REQUEST BODY:
     * {
     *   "range": "Sheet1!A1:B2",
     *   "majorDimension": "ROWS",
     *   "values": [["A1", "B1"], ["A2", "B2"]]
     * }
     *
     * @since   2.0.0
     * @param   string  $range  Range notation (e.g., "Coupons!F2")
     * @param   array   $values 2D array: [[row1_col1, row1_col2], [row2_col1, row2_col2]]
     * @return  bool            True nếu thành công, false nếu lỗi
     */
    public function update_range($range, $values)
    {
        // Get access token
        $access_token = $this->auth->get_access_token();
        if (!$access_token) {
            error_log('[Vie Google Sheets API ERROR] Cannot update sheet: no access token');
            return false;
        }

        // Build API URL
        $url = sprintf(
            '%s/%s/values/%s?valueInputOption=RAW',
            $this->api_base_url,
            $this->sheet_id,
            urlencode($range)
        );

        // Build request body
        $body = json_encode([
            'range'          => $range,
            'majorDimension' => 'ROWS',
            'values'         => $values
        ]);

        // Make PUT request
        $response = wp_remote_request($url, [
            'method'  => 'PUT',
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ],
            'body' => $body
        ]);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log(sprintf(
                '[Vie Google Sheets API ERROR] HTTP error when updating sheet (%s): %s',
                $range,
                $response->get_error_message()
            ));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // Handle non-200 status codes
        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            error_log(sprintf(
                '[Vie Google Sheets API ERROR] Google Sheets API error (update) - HTTP %d: %s (Range: %s)',
                $status_code,
                $response_body,
                $range
            ));
            return false;
        }

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf(
                '[Vie Google Sheets API] Sheet updated successfully - Range: %s, Rows: %d',
                $range,
                count($values)
            ));
        }

        return true;
    }

    /**
     * Append dữ liệu vào cuối sheet
     *
     * API ENDPOINT:
     * POST https://sheets.googleapis.com/v4/spreadsheets/{spreadsheetId}/values/{range}:append
     *
     * PARAMETERS:
     * - valueInputOption: RAW | USER_ENTERED
     * - insertDataOption: INSERT_ROWS (insert new rows) | OVERWRITE (overwrite existing)
     *
     * @since   2.0.0
     * @param   string  $range  Range notation (e.g., "Coupons!A:F")
     * @param   array   $values 2D array of values to append
     * @return  bool            True nếu thành công, false nếu lỗi
     */
    public function append_range($range, $values)
    {
        // Get access token
        $access_token = $this->auth->get_access_token();
        if (!$access_token) {
            error_log('[Vie Google Sheets API ERROR] Cannot append to sheet: no access token');
            return false;
        }

        // Build API URL
        $url = sprintf(
            '%s/%s/values/%s:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            $this->api_base_url,
            $this->sheet_id,
            urlencode($range)
        );

        // Build request body
        $body = json_encode([
            'range'          => $range,
            'majorDimension' => 'ROWS',
            'values'         => $values
        ]);

        // Make POST request
        $response = wp_remote_post($url, [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json'
            ],
            'body' => $body
        ]);

        // Handle HTTP errors
        if (is_wp_error($response)) {
            error_log(sprintf(
                '[Vie Google Sheets API ERROR] HTTP error when appending to sheet (%s): %s',
                $range,
                $response->get_error_message()
            ));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        // Handle non-200 status codes
        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            error_log(sprintf(
                '[Vie Google Sheets API ERROR] Google Sheets API error (append) - HTTP %d: %s (Range: %s)',
                $status_code,
                $response_body,
                $range
            ));
            return false;
        }

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf(
                '[Vie Google Sheets API] Data appended to sheet successfully - Range: %s, Rows: %d',
                $range,
                count($values)
            ));
        }

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Tìm row number của một mã coupon cụ thể
     *
     * FLOW:
     * 1. Read range chứa coupons
     * 2. Loop qua từng row
     * 3. So sánh column đầu tiên (coupon code)
     * 4. Return row number (1-based) nếu tìm thấy
     *
     * @since   2.0.0
     * @param   string      $coupon_code    Mã coupon cần tìm
     * @param   string|null $range          Range để tìm kiếm (null = use default from database/constants)
     * @return  int|false                   Row number (1-based) hoặc false nếu không tìm thấy
     */
    public function find_coupon_row($coupon_code, $range = null)
    {
        // Read data from sheet
        $data = $this->read_range($range);

        if ($data === false) {
            return false;
        }

        // Sanitize coupon code
        $coupon_code = function_exists('vl_sanitize_coupon_code')
            ? vl_sanitize_coupon_code($coupon_code)
            : strtoupper(trim($coupon_code));

        // Parse range to get starting row number
        if ($range === null) {
            // Load from database settings (priority)
            $gsheets_settings = get_option('vie_gsheets_settings', array());

            if (!empty($gsheets_settings['sheet_name']) && !empty($gsheets_settings['sheet_range'])) {
                $range = $gsheets_settings['sheet_name'] . '!' . $gsheets_settings['sheet_range'];
            } elseif (defined('VL_COUPON_SHEET_RANGE')) {
                // Fallback to constant
                $range = VL_COUPON_SHEET_RANGE;
            } else {
                $range = 'Sheet1!A2:Z1000';
            }
        }

        // Extract start row from range (e.g., "Sheet1!A2:F100" → 2)
        preg_match('/!?[A-Z]+(\d+)/', $range, $matches);
        $start_row = isset($matches[1]) ? (int) $matches[1] : 2;

        // Search for coupon code in first column
        foreach ($data as $index => $row) {
            if (isset($row[0])) {
                $row_code = function_exists('vl_sanitize_coupon_code')
                    ? vl_sanitize_coupon_code($row[0])
                    : strtoupper(trim($row[0]));

                if ($row_code === $coupon_code) {
                    return $start_row + $index; // Return 1-based row number
                }
            }
        }

        return false;
    }

    /**
     * Test Google Sheets connection
     *
     * Đọc cell A1 để test connection.
     * Hữu ích cho debug và setup.
     *
     * @since   2.0.0
     * @return  bool    True nếu connection thành công, false nếu lỗi
     */
    public function test_connection()
    {
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Sheets API] Testing Google Sheets connection...');
        }

        // Get sheet name from database settings
        $gsheets_settings = get_option('vie_gsheets_settings', array());
        $sheet_name = !empty($gsheets_settings['sheet_name'])
            ? $gsheets_settings['sheet_name']
            : (defined('VL_COUPON_SHEET_NAME') ? VL_COUPON_SHEET_NAME : 'Sheet1');

        $result = $this->read_range($sheet_name . '!A1:A1');

        if ($result !== false) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[Vie Google Sheets API] Connection test successful');
            }
            return true;
        }

        error_log('[Vie Google Sheets API ERROR] Connection test failed');
        return false;
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Alias cho code cũ vẫn dùng VL_Google_Sheets_API
if (!class_exists('VL_Google_Sheets_API')) {
    class_alias('Vie_Google_Sheets_API', 'VL_Google_Sheets_API');
}
