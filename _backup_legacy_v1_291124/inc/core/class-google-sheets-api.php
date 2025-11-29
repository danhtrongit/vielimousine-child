<?php
/**
 * Google Sheets Coupon System - Google Sheets API Client
 * 
 * Class xử lý HTTP requests tới Google Sheets API v4
 * Sử dụng wp_remote_request() - không cần thư viện ngoài
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

class VL_Google_Sheets_API
{

    /**
     * Google Auth instance
     * @var VL_Google_Auth
     */
    private $auth;

    /**
     * Sheet ID
     * @var string
     */
    private $sheet_id;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->auth = new VL_Google_Auth();
        $this->sheet_id = VL_COUPON_SHEET_ID;

        if (!$this->auth->is_valid()) {
            VL_Logger::error('Google Auth is not properly configured');
        }
    }

    /**
     * Đọc dữ liệu từ Google Sheets
     * 
     * GET https://sheets.googleapis.com/v4/spreadsheets/{spreadsheetId}/values/{range}
     * 
     * @param string $range Range notation (vd: "Coupons!A2:F1000")
     * @return array|false Dữ liệu hoặc false nếu lỗi
     */
    public function read_range($range = null)
    {
        if ($range === null) {
            $range = VL_COUPON_SHEET_RANGE;
        }

        $access_token = $this->auth->get_access_token();
        if (!$access_token) {
            VL_Logger::error('Cannot read sheet: no access token');
            return false;
        }

        $url = sprintf(
            '%s/%s/values/%s',
            VL_GOOGLE_SHEETS_API_URL,
            $this->sheet_id,
            urlencode($range)
        );

        $response = wp_remote_get($url, [
            'timeout' => VL_GOOGLE_API_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Accept' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            VL_Logger::error('HTTP error when reading sheet', [
                'error' => $response->get_error_message(),
                'range' => $range
            ]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            VL_Logger::error('Google Sheets API error (read)', [
                'status' => $status_code,
                'body' => $body,
                'range' => $range
            ]);

            // Nếu unauthorized, thử refresh token và retry
            if ($status_code === 401) {
                VL_Logger::info('Token expired, attempting refresh');
                $this->auth->refresh_token();
                // Có thể retry 1 lần nữa ở đây nếu cần
            }

            return false;
        }

        $data = json_decode($body, true);

        if (!isset($data['values'])) {
            VL_Logger::warning('No values found in sheet range', ['range' => $range]);
            return [];
        }

        VL_Logger::debug('Sheet read successfully', [
            'range' => $range,
            'rows' => count($data['values'])
        ]);

        return $data['values'];
    }

    /**
     * Cập nhật dữ liệu trong Google Sheets
     * 
     * PUT https://sheets.googleapis.com/v4/spreadsheets/{spreadsheetId}/values/{range}?valueInputOption=RAW
     * 
     * @param string $range Range notation (vd: "Coupons!F2")
     * @param array $values Mảng 2 chiều [[row1_col1, row1_col2], [row2_col1, row2_col2]]
     * @return bool True nếu thành công
     */
    public function update_range($range, $values)
    {
        $access_token = $this->auth->get_access_token();
        if (!$access_token) {
            VL_Logger::error('Cannot update sheet: no access token');
            return false;
        }

        $url = sprintf(
            '%s/%s/values/%s?valueInputOption=RAW',
            VL_GOOGLE_SHEETS_API_URL,
            $this->sheet_id,
            urlencode($range)
        );

        $body = json_encode([
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values
        ]);

        $response = wp_remote_request($url, [
            'method' => 'PUT',
            'timeout' => VL_GOOGLE_API_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => $body
        ]);

        if (is_wp_error($response)) {
            VL_Logger::error('HTTP error when updating sheet', [
                'error' => $response->get_error_message(),
                'range' => $range
            ]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            VL_Logger::error('Google Sheets API error (update)', [
                'status' => $status_code,
                'body' => $response_body,
                'range' => $range
            ]);
            return false;
        }

        VL_Logger::info('Sheet updated successfully', [
            'range' => $range,
            'rows' => count($values)
        ]);

        return true;
    }

    /**
     * Append dữ liệu vào cuối sheet
     * 
     * POST https://sheets.googleapis.com/v4/spreadsheets/{spreadsheetId}/values/{range}:append
     * 
     * @param string $range Range notation (vd: "Coupons!A:F")
     * @param array $values Mảng 2 chiều
     * @return bool True nếu thành công
     */
    public function append_range($range, $values)
    {
        $access_token = $this->auth->get_access_token();
        if (!$access_token) {
            VL_Logger::error('Cannot append to sheet: no access token');
            return false;
        }

        $url = sprintf(
            '%s/%s/values/%s:append?valueInputOption=RAW&insertDataOption=INSERT_ROWS',
            VL_GOOGLE_SHEETS_API_URL,
            $this->sheet_id,
            urlencode($range)
        );

        $body = json_encode([
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values
        ]);

        $response = wp_remote_post($url, [
            'timeout' => VL_GOOGLE_API_TIMEOUT,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => $body
        ]);

        if (is_wp_error($response)) {
            VL_Logger::error('HTTP error when appending to sheet', [
                'error' => $response->get_error_message(),
                'range' => $range
            ]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $response_body = wp_remote_retrieve_body($response);
            VL_Logger::error('Google Sheets API error (append)', [
                'status' => $status_code,
                'body' => $response_body,
                'range' => $range
            ]);
            return false;
        }

        VL_Logger::info('Data appended to sheet successfully', [
            'range' => $range,
            'rows' => count($values)
        ]);

        return true;
    }

    /**
     * Tìm row number của một mã coupon cụ thể
     * 
     * @param string $coupon_code Mã coupon cần tìm
     * @param string $range Range để tìm kiếm
     * @return int|false Row number (1-based) hoặc false nếu không tìm thấy
     */
    public function find_coupon_row($coupon_code, $range = null)
    {
        $data = $this->read_range($range);

        if ($data === false) {
            return false;
        }

        $coupon_code = vl_sanitize_coupon_code($coupon_code);

        // Parse range để lấy starting row number
        if ($range === null) {
            $range = VL_COUPON_SHEET_RANGE;
        }

        $parsed = vl_parse_sheet_range($range);
        preg_match('/([A-Z]+)(\d+)/', $parsed['range'], $matches);
        $start_row = isset($matches[2]) ? (int) $matches[2] : 2;

        foreach ($data as $index => $row) {
            if (isset($row[0]) && vl_sanitize_coupon_code($row[0]) === $coupon_code) {
                return $start_row + $index; // Return 1-based row number
            }
        }

        return false;
    }

    /**
     * Check sheet connection (test method)
     * 
     * @return bool True nếu kết nối thành công
     */
    public function test_connection()
    {
        VL_Logger::info('Testing Google Sheets connection...');

        $result = $this->read_range(VL_COUPON_SHEET_NAME . '!A1:A1');

        if ($result !== false) {
            VL_Logger::info('Connection test successful');
            return true;
        }

        VL_Logger::error('Connection test failed');
        return false;
    }
}
