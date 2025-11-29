<?php
/**
 * Google Sheets Coupon System - AJAX Handlers
 * 
 * Xử lý AJAX requests từ frontend
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

class VL_Coupon_AJAX
{

    /**
     * Coupon Validator instance
     * @var VL_Coupon_Validator
     */
    private $validator;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->validator = new VL_Coupon_Validator();
        $this->register_ajax_actions();
    }

    /**
     * Register AJAX actions
     */
    private function register_ajax_actions()
    {
        // Validate coupon (check only, không update sheet)
        add_action('wp_ajax_vl_validate_coupon', [$this, 'ajax_validate_coupon']);
        add_action('wp_ajax_nopriv_vl_validate_coupon', [$this, 'ajax_validate_coupon']);

        // Apply coupon (thực sự apply và update sheet)
        add_action('wp_ajax_vl_apply_coupon', [$this, 'ajax_apply_coupon']);
        add_action('wp_ajax_nopriv_vl_apply_coupon', [$this, 'ajax_apply_coupon']);

        // Admin actions
        add_action('wp_ajax_vl_refresh_coupon_cache', [$this, 'ajax_refresh_cache']);
        add_action('wp_ajax_vl_test_google_connection', [$this, 'ajax_test_connection']);
    }

    /**
     * AJAX: Validate coupon
     * 
     * POST params:
     * - coupon_code: string
     * - order_total: float
     * - nonce: string
     */
    public function ajax_validate_coupon()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vl_coupon_nonce')) {
            wp_send_json_error([
                'message' => 'Yêu cầu không hợp lệ'
            ], 403);
        }

        // Get params
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        $order_total = isset($_POST['order_total']) ? floatval($_POST['order_total']) : 0;

        // Validate
        $result = $this->validator->validate_code($coupon_code, $order_total);

        if ($result['valid']) {
            wp_send_json_success([
                'message' => $result['message'],
                'discount' => $result['discount'],
                'coupon_code' => $result['data']['code'],
                'discount_type' => $result['data']['discount_type']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message']
            ], 400);
        }
    }

    /**
     * AJAX: Apply coupon (update usage trong Google Sheets)
     * 
     * POST params:
     * - coupon_code: string
     * - order_total: float
     * - booking_id: int (optional)
     * - nonce: string
     */
    public function ajax_apply_coupon()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vl_coupon_apply_nonce')) {
            wp_send_json_error([
                'message' => 'Yêu cầu không hợp lệ'
            ], 403);
        }

        // Get params
        $coupon_code = isset($_POST['coupon_code']) ? sanitize_text_field($_POST['coupon_code']) : '';
        $order_total = isset($_POST['order_total']) ? floatval($_POST['order_total']) : 0;
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        // Apply coupon
        $result = $this->validator->apply_coupon($coupon_code, $order_total, $booking_id);

        if ($result['valid']) {
            wp_send_json_success([
                'message' => $result['message'],
                'discount' => $result['discount'],
                'coupon_code' => $coupon_code
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message']
            ], 400);
        }
    }

    /**
     * AJAX: Refresh cache (admin only)
     */
    public function ajax_refresh_cache()
    {
        // Check admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Không có quyền'
            ], 403);
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vl_admin_nonce')) {
            wp_send_json_error([
                'message' => 'Yêu cầu không hợp lệ'
            ], 403);
        }

        $cache = VL_Cache_Manager::get_instance();
        $coupons = $cache->refresh_coupons();

        if ($coupons !== false) {
            wp_send_json_success([
                'message' => 'Đã đồng bộ thành công',
                'count' => count($coupons)
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Không thể kết nối Google Sheets'
            ], 500);
        }
    }

    /**
     * AJAX: Test Google Sheets connection (admin only)
     */
    public function ajax_test_connection()
    {
        // Check admin capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error([
                'message' => 'Không có quyền'
            ], 403);
        }

        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vl_admin_nonce')) {
            wp_send_json_error([
                'message' => 'Yêu cầu không hợp lệ'
            ], 403);
        }

        $sheets_api = new VL_Google_Sheets_API();
        $success = $sheets_api->test_connection();

        if ($success) {
            wp_send_json_success([
                'message' => 'Kết nối Google Sheets thành công!'
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Không thể kết nối. Vui lòng kiểm tra credentials và Sheet ID.'
            ], 500);
        }
    }
}

// Initialize AJAX handlers
new VL_Coupon_AJAX();
