<?php
/**
 * Theme functions and definitions
 *
 * @author Gaudev
 */

/**
 * Load Hotel Rooms Management Module
 * 
 * Module quản lý loại phòng khách sạn với custom database tables
 * Tối ưu hiệu năng cho dữ liệu lịch & giá
 */
require_once get_stylesheet_directory() . '/inc/hotel-rooms/hotel-rooms.php';

/**
 * Load Google Sheets Coupon System
 * 
 * Hệ thống mã giảm giá đồng bộ với Google Sheets
 * Không lưu database, sử dụng Google Sheets làm Master Data
 */

// Load configuration
require_once get_stylesheet_directory() . '/inc/config/constants.php';
require_once get_stylesheet_directory() . '/inc/config/credentials.php';

// Load utilities
require_once get_stylesheet_directory() . '/inc/utils/class-logger.php';
require_once get_stylesheet_directory() . '/inc/utils/helpers.php';

// Load core classes
require_once get_stylesheet_directory() . '/inc/core/class-google-auth.php';
require_once get_stylesheet_directory() . '/inc/core/class-google-sheets-api.php';
require_once get_stylesheet_directory() . '/inc/core/class-cache-manager.php';

// Load coupon module
require_once get_stylesheet_directory() . '/inc/modules/coupons/class-coupon-validator.php';
require_once get_stylesheet_directory() . '/inc/modules/coupons/class-coupon-ajax.php';
require_once get_stylesheet_directory() . '/inc/modules/coupons/hooks.php';


/**
 * Cấu hình SMTP Zoho Mail cho WordPress
 */
add_action('phpmailer_init', 'config_zoho_smtp');

function config_zoho_smtp($phpmailer)
{
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.zoho.com';
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 465; // Cổng SSL
    $phpmailer->Username = 'info@vietnew-entertainment.com.vn'; // Thay bằng email của bạn
    $phpmailer->Password = 'MinHC48Cig9Z';   // Thay bằng App Password của bạn
    $phpmailer->SMTPSecure = 'ssl'; // Sử dụng SSL
    $phpmailer->From = 'info@vietnew-entertainment.com.vn'; // Email gửi đi
    $phpmailer->FromName = 'Vie Limousine';  // Tên hiển thị người gửi
}

