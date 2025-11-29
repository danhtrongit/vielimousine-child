<?php
/**
 * Google Sheets Coupon System - Configuration Constants
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

/**
 * Google Sheets Configuration
 * 
 * Hướng dẫn lấy thông tin:
 * 1. SHEET_ID: Lấy từ URL Google Sheets
 *    https://docs.google.com/spreadsheets/d/{SHEET_ID}/edit
 * 
 * 2. SHEET_RANGE: Định dạng "SheetName!StartCell:EndCell"
 *    Ví dụ: "Coupons!A2:F1000" (đọc từ dòng 2 đến 1000)
 */

// Google Sheet ID (thay YOUR_SHEET_ID_HERE bằng ID thực tế)
if (!defined('VL_COUPON_SHEET_ID')) {
    define('VL_COUPON_SHEET_ID', 'YOUR_SHEET_ID_HERE');
}

// Sheet range để đọc dữ liệu mã giảm giá
if (!defined('VL_COUPON_SHEET_RANGE')) {
    define('VL_COUPON_SHEET_RANGE', 'Coupons!A2:F1000');
}

// Sheet name (tab name trong Google Sheets)
if (!defined('VL_COUPON_SHEET_NAME')) {
    define('VL_COUPON_SHEET_NAME', 'Coupons');
}

/**
 * Cache Configuration
 */

// Thời gian cache danh sách mã (giây) - Mặc định: 5 phút
if (!defined('VL_COUPON_CACHE_DURATION')) {
    define('VL_COUPON_CACHE_DURATION', 5 * MINUTE_IN_SECONDS);
}

// Thời gian cache Access Token (giây) - Mặc định: 50 phút
if (!defined('VL_GOOGLE_TOKEN_CACHE_DURATION')) {
    define('VL_GOOGLE_TOKEN_CACHE_DURATION', 50 * MINUTE_IN_SECONDS);
}

/**
 * API Configuration
 */

// Timeout cho HTTP requests (giây)
if (!defined('VL_GOOGLE_API_TIMEOUT')) {
    define('VL_GOOGLE_API_TIMEOUT', 10);
}

// Google OAuth2 Token URL
if (!defined('VL_GOOGLE_TOKEN_URL')) {
    define('VL_GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
}

// Google Sheets API Base URL
if (!defined('VL_GOOGLE_SHEETS_API_URL')) {
    define('VL_GOOGLE_SHEETS_API_URL', 'https://sheets.googleapis.com/v4/spreadsheets');
}

/**
 * Security Configuration
 */

// Rate limiting: Số lần tối đa validate mã trong 1 phút
if (!defined('VL_COUPON_RATE_LIMIT')) {
    define('VL_COUPON_RATE_LIMIT', 5);
}

// Lock timeout cho race condition protection (giây)
if (!defined('VL_COUPON_LOCK_TIMEOUT')) {
    define('VL_COUPON_LOCK_TIMEOUT', 30);
}

/**
 * Logging Configuration
 */

// Enable/disable logging
if (!defined('VL_COUPON_ENABLE_LOGGING')) {
    define('VL_COUPON_ENABLE_LOGGING', true);
}

// Log file path
if (!defined('VL_COUPON_LOG_FILE')) {
    define('VL_COUPON_LOG_FILE', get_stylesheet_directory() . '/logs/coupon-errors.log');
}

// Maximum log file size (bytes) - 5MB
if (!defined('VL_COUPON_MAX_LOG_SIZE')) {
    define('VL_COUPON_MAX_LOG_SIZE', 5 * 1024 * 1024);
}
