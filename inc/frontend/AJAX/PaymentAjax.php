<?php
/**
 * ============================================================================
 * TÊN FILE: PaymentAjax.php
 * ============================================================================
 *
 * MÔ TẢ:
 * AJAX Handler cho payment/checkout operations trên frontend.
 * Xử lý: process checkout, update booking info for SePay.
 *
 * CHỨC NĂNG CHÍNH:
 * - Process checkout confirmation
 * - Update customer info before SePay payment
 *
 * AJAX ENDPOINTS (2):
 * - vie_process_checkout
 * - vie_update_booking_info
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Frontend/AJAX
 * @version     2.1.0
 * @since       2.0.0 (Refactored to AJAX Handler pattern in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Payment_Ajax
 * ============================================================================
 *
 * AJAX Handler cho payment operations.
 *
 * ARCHITECTURE:
 * - AJAX Handler Pattern
 * - Public endpoints (nopriv)
 * - Service layer integration
 * - Nonce verification
 *
 * @since   2.0.0
 */
class Vie_Payment_Ajax
{
    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Register AJAX endpoints.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_ajax_handlers()
    {
        // Process checkout
        add_action('wp_ajax_vie_process_checkout', array($this, 'process_checkout'));
        add_action('wp_ajax_nopriv_vie_process_checkout', array($this, 'process_checkout'));

        // Update booking info (before SePay payment)
        add_action('wp_ajax_vie_update_booking_info', array($this, 'update_booking_info'));
        add_action('wp_ajax_nopriv_vie_update_booking_info', array($this, 'update_booking_info'));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: PROCESS CHECKOUT
     * -------------------------------------------------------------------------
     */

    /**
     * Process checkout - Confirm payment
     *
     * Update customer info and confirm booking.
     * Used for bank transfer payment method.
     *
     * REQUEST PARAMS:
     * - booking_hash: Booking hash from URL
     * - payment_method: bank_transfer or sepay
     * - customer_name: Customer full name
     * - customer_phone: Customer phone
     * - customer_email: Customer email (optional)
     * - customer_note: Customer note (optional)
     *
     * RESPONSE:
     * - booking_code: Booking code
     * - message: Success message
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function process_checkout()
    {
        check_ajax_referer('vie_checkout_action', 'nonce');

        $booking_hash   = sanitize_text_field($_POST['booking_hash'] ?? '');
        $payment_method = sanitize_text_field($_POST['payment_method'] ?? '');
        $customer_name  = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_note  = sanitize_textarea_field($_POST['customer_note'] ?? '');

        // Validate
        if (empty($booking_hash)) {
            wp_send_json_error(array('message' => 'Thiếu mã đặt phòng'));
        }

        if (empty($payment_method)) {
            wp_send_json_error(array('message' => 'Vui lòng chọn phương thức thanh toán'));
        }

        if (empty($customer_name) || empty($customer_phone)) {
            wp_send_json_error(array('message' => 'Vui lòng điền đầy đủ họ tên và số điện thoại'));
        }

        // Get booking by hash
        $booking = $this->get_booking_by_hash($booking_hash);

        if (!$booking) {
            wp_send_json_error(array('message' => 'Không tìm thấy đơn đặt phòng'));
        }

        if ($booking->status !== 'pending_payment') {
            wp_send_json_error(array('message' => 'Đơn đặt phòng đã được xử lý'));
        }

        // Update customer info
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';

        $update_result = $wpdb->update(
            $table,
            array(
                'customer_name'  => $customer_name,
                'customer_phone' => $customer_phone,
                'customer_email' => $customer_email,
                'customer_note'  => $customer_note,
                'status'         => 'confirmed',
                'payment_method' => $payment_method,
                'updated_at'     => current_time('mysql')
            ),
            array('booking_hash' => $booking_hash),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );

        if ($update_result === false) {
            wp_send_json_error(array('message' => 'Lỗi cập nhật đặt phòng'));
        }

        // Send confirmation email
        $this->send_booking_email($booking->id, 'confirmed');

        wp_send_json_success(array(
            'booking_code' => $booking->booking_code,
            'message'      => 'Đặt phòng thành công!'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: UPDATE BOOKING INFO
     * -------------------------------------------------------------------------
     */

    /**
     * Update booking info before SePay payment
     *
     * Update customer info only (không thay đổi status).
     * Used before redirecting to SePay QR payment.
     *
     * REQUEST PARAMS:
     * - booking_hash: Booking hash
     * - customer_name: Customer name
     * - customer_phone: Customer phone
     * - customer_email: Customer email (optional)
     * - customer_note: Customer note (optional)
     *
     * RESPONSE:
     * - message: Success message
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function update_booking_info()
    {
        check_ajax_referer('vie_checkout_action', 'nonce');

        $booking_hash   = sanitize_text_field($_POST['booking_hash'] ?? '');
        $customer_name  = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_note  = sanitize_textarea_field($_POST['customer_note'] ?? '');

        if (empty($booking_hash)) {
            wp_send_json_error(array('message' => 'Thiếu mã đặt phòng'));
        }

        if (empty($customer_name) || empty($customer_phone)) {
            wp_send_json_error(array('message' => 'Vui lòng điền họ tên và số điện thoại'));
        }

        // Get booking
        $booking = $this->get_booking_by_hash($booking_hash);

        if (!$booking) {
            wp_send_json_error(array('message' => 'Không tìm thấy đơn đặt phòng'));
        }

        if ($booking->status !== 'pending_payment') {
            wp_send_json_error(array('message' => 'Đơn đặt phòng đã được xử lý'));
        }

        // Update customer info only (không thay đổi status)
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';

        $result = $wpdb->update(
            $table,
            array(
                'customer_name'  => $customer_name,
                'customer_phone' => $customer_phone,
                'customer_email' => $customer_email,
                'customer_note'  => $customer_note,
                'payment_method' => 'sepay',
                'updated_at'     => current_time('mysql')
            ),
            array('booking_hash' => $booking_hash),
            array('%s', '%s', '%s', '%s', '%s', '%s'),
            array('%s')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi cập nhật thông tin'));
        }

        wp_send_json_success(array(
            'message' => 'Đã lưu thông tin. Vui lòng thanh toán.'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * SERVICE INTEGRATION METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get booking by hash
     *
     * @since   2.1.0
     * @param   string  $hash   Booking hash
     * @return  object|null     Booking object or null
     */
    private function get_booking_by_hash($hash)
    {
        // Use BookingService if available (v2.1)
        if (class_exists('Vie_Booking_Service')) {
            $service = Vie_Booking_Service::get_instance();
            return $service->get_booking_by_hash($hash);
        }

        // Fallback to old manager (backward compatibility)
        if (class_exists('Vie_Booking_Manager')) {
            $manager = Vie_Booking_Manager::get_instance();
            return $manager->get_booking_by_hash($hash);
        }

        return null;
    }

    /**
     * Send booking email
     *
     * @since   2.1.0
     * @param   int     $booking_id     Booking ID
     * @param   string  $type           Email type
     * @return  void
     */
    private function send_booking_email($booking_id, $type = 'confirmed')
    {
        // Use EmailService if available (v2.1)
        if (class_exists('Vie_Email_Service')) {
            $service = Vie_Email_Service::get_instance();
            $service->send_email($type, $booking_id);
            return;
        }

        // Fallback to old manager (backward compatibility)
        if (class_exists('Vie_Email_Manager')) {
            $manager = Vie_Email_Manager::get_instance();
            $manager->send_booking_confirmation($booking_id);
        }
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Auto-initialize
new Vie_Payment_Ajax();
