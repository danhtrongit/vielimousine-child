<?php
/**
 * Google Sheets Coupon System - Credentials Loader
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

/**
 * Lấy thông tin Service Account credentials từ file JSON
 * 
 * Security layers:
 * 1. File được bảo vệ bởi .htaccess (deny all)
 * 2. Script này check ABSPATH để chặn direct access
 * 3. Credentials được cache trong memory để tránh đọc file nhiều lần
 * 
 * @return array|false Credentials array hoặc false nếu lỗi
 */
function vl_get_service_account_credentials()
{
    // Cache trong memory (static variable)
    static $credentials = null;

    if ($credentials !== null) {
        return $credentials;
    }

    // Đường dẫn tới file JSON
    $json_path = get_stylesheet_directory() . '/credentials/service-account.json';

    // Kiểm tra file tồn tại
    if (!file_exists($json_path)) {
        if (VL_COUPON_ENABLE_LOGGING) {
            error_log('[VL Coupon] Service account JSON file not found: ' . $json_path);
        }
        return false;
    }

    // Kiểm tra quyền đọc file
    if (!is_readable($json_path)) {
        if (VL_COUPON_ENABLE_LOGGING) {
            error_log('[VL Coupon] Service account JSON file is not readable');
        }
        return false;
    }

    // Đọc và parse JSON
    $json_content = file_get_contents($json_path);

    if ($json_content === false) {
        if (VL_COUPON_ENABLE_LOGGING) {
            error_log('[VL Coupon] Failed to read service account JSON file');
        }
        return false;
    }

    $credentials = json_decode($json_content, true);

    // Validate cấu trúc JSON
    if (!is_array($credentials) || !isset($credentials['private_key']) || !isset($credentials['client_email'])) {
        if (VL_COUPON_ENABLE_LOGGING) {
            error_log('[VL Coupon] Invalid service account JSON structure');
        }
        $credentials = null;
        return false;
    }

    return $credentials;
}

/**
 * Kiểm tra credentials đã được cấu hình đúng chưa
 * 
 * @return bool True nếu credentials hợp lệ
 */
function vl_is_credentials_configured()
{
    $credentials = vl_get_service_account_credentials();

    if (!$credentials) {
        return false;
    }

    // Check các field bắt buộc
    $required_fields = [
        'type',
        'project_id',
        'private_key',
        'client_email',
        'token_uri'
    ];

    foreach ($required_fields as $field) {
        if (!isset($credentials[$field]) || empty($credentials[$field])) {
            return false;
        }
    }

    // Check Sheet ID đã được config
    if (VL_COUPON_SHEET_ID === 'YOUR_SHEET_ID_HERE') {
        return false;
    }

    return true;
}
