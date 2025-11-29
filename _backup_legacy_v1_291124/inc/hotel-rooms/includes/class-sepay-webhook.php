<?php
/**
 * SePay Webhook Handler for Hotel Booking
 * 
 * Xử lý webhook từ SePay để xác nhận thanh toán tự động
 * 
 * @package VieHotelRooms
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_SePay_Webhook
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * SePay Helper
     */
    private $sepay;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->sepay = vie_sepay();
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        // Register REST API endpoint for webhook
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));

        // AJAX for checking booking payment status (frontend)
        add_action('wp_ajax_nopriv_vie_check_booking_payment', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_vie_check_booking_payment', array($this, 'ajax_check_payment_status'));
    }

    /**
     * Register webhook REST API endpoint
     */
    public function register_webhook_endpoint()
    {
        register_rest_route('vie-hotel/v1', '/sepay-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));

        // Backward compatibility endpoint
        register_rest_route('vie-hotel/v2', '/sepay-webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Handle incoming webhook from SePay
     * 
     * @param WP_REST_Request $request
     * @return array
     */
    public function handle_webhook($request)
    {
        // Get parameters
        $parameters = $request->get_json_params();
        $authorization = $request->get_header('Authorization');

        // Log incoming webhook (debug mode only)
        if (WP_DEBUG) {
            $this->sepay->log_error('Webhook received', [
                'params' => $parameters,
                'auth_header' => $authorization ? 'present' : 'missing'
            ]);
        }

        // Validate API key
        $api_key = $this->extract_api_key($authorization);

        if (!$this->sepay->validate_api_key($api_key)) {
            $this->sepay->log_error('Invalid API Key', ['api_key' => $api_key]);
            return array(
                'success' => false,
                'message' => 'Invalid API Key',
            );
        }

        // Validate parameters
        if (!is_array($parameters)) {
            return array(
                'success' => false,
                'message' => 'Invalid JSON request',
            );
        }

        // Check required fields
        $required_fields = ['accountNumber', 'gateway', 'code', 'transferType', 'transferAmount'];
        foreach ($required_fields as $field) {
            if (!isset($parameters[$field])) {
                return array(
                    'success' => false,
                    'message' => 'Not enough required parameters: ' . $field,
                );
            }
        }

        // Only process incoming transfers
        if ($parameters['transferType'] !== 'in') {
            return array(
                'success' => false,
                'message' => 'transferType must be "in"',
            );
        }

        // Extract booking ID from payment code
        $booking_id = $this->sepay->extract_booking_id($parameters['code']);

        if (!$booking_id) {
            $this->sepay->log_error('Booking ID not found from code', [
                'code' => $parameters['code']
            ]);
            return array(
                'success' => false,
                'message' => "Booking ID not found from pay code {$parameters['code']}",
            );
        }

        // Get booking from database
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $booking_id
        ));

        if (!$booking) {
            $this->sepay->log_error('Booking not found', ['booking_id' => $booking_id]);
            return array(
                'success' => false,
                'message' => "Booking ID {$booking_id} not found",
            );
        }

        // Check if already paid
        if (in_array($booking->payment_status, ['paid'])) {
            return array(
                'success' => false,
                'message' => 'This booking has already been paid!',
            );
        }

        if (in_array($booking->status, ['confirmed', 'completed'])) {
            return array(
                'success' => false,
                'message' => 'This booking has already been confirmed!',
            );
        }

        // Get booking total
        $booking_total = (int) $booking->total_amount;
        $transfer_amount = (int) $parameters['transferAmount'];

        if ($booking_total <= 0) {
            return array(
                'success' => false,
                'message' => 'Booking total is <= 0',
            );
        }

        // Process payment
        $result = $this->process_payment($booking, $parameters, $transfer_amount);

        return $result;
    }

    /**
     * Process payment and update booking
     */
    private function process_payment($booking, $parameters, $transfer_amount)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';

        $booking_total = (int) $booking->total_amount;
        $old_status = $booking->status;
        $old_payment_status = $booking->payment_status;

        // Build admin note
        $note = sprintf(
            "SePay: Đã nhận thanh toán %s vào tài khoản %s tại ngân hàng %s vào lúc %s",
            $this->sepay->format_currency($transfer_amount),
            $parameters['accountNumber'],
            $parameters['gateway'],
            isset($parameters['transactionDate']) ? $parameters['transactionDate'] : current_time('mysql')
        );

        // Determine new status based on payment amount
        $new_payment_status = 'unpaid';
        $new_status = $booking->status;

        if ($transfer_amount >= $booking_total) {
            // Full payment or overpayment
            $new_payment_status = 'paid';
            $new_status = 'confirmed';

            if ($transfer_amount > $booking_total) {
                $overpayment = $this->sepay->format_currency($transfer_amount - $booking_total);
                $note .= ". Khách hàng thanh toán THỪA: {$overpayment}";
            } else {
                $note .= ". Đơn đặt phòng đã được xác nhận tự động.";
            }
        } else {
            // Partial payment
            $new_payment_status = 'partial';
            $underpayment = $this->sepay->format_currency($booking_total - $transfer_amount);
            $note .= ". Khách hàng thanh toán THIẾU: {$underpayment}";
        }

        // Append to existing admin note
        $admin_note = $booking->admin_note;
        $timestamp = current_time('d/m/Y H:i');
        $new_note_entry = "[{$timestamp}] {$note}";
        $updated_admin_note = !empty($admin_note) ? $admin_note . "\n\n" . $new_note_entry : $new_note_entry;

        // Update booking
        $updated = $wpdb->update(
            $table,
            array(
                'payment_status' => $new_payment_status,
                'payment_method' => 'sepay',
                'status' => $new_status,
                'admin_note' => $updated_admin_note,
                'updated_at' => current_time('mysql'),
            ),
            array('id' => $booking->id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            $this->sepay->log_error('Failed to update booking', [
                'booking_id' => $booking->id,
                'wpdb_error' => $wpdb->last_error
            ]);
            return array(
                'success' => false,
                'message' => 'Failed to update booking',
            );
        }

        // Log success
        $this->sepay->log_error('Payment processed successfully', [
            'booking_id' => $booking->id,
            'transfer_amount' => $transfer_amount,
            'booking_total' => $booking_total,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'old_payment_status' => $old_payment_status,
            'new_payment_status' => $new_payment_status,
        ]);

        // Send notification email (optional)
        $this->maybe_send_confirmation_email($booking, $new_payment_status, $transfer_amount);

        // Trigger action for other integrations
        do_action('vie_hotel_booking_payment_received', $booking->id, $transfer_amount, $new_payment_status);

        return array(
            'success' => true,
            'message' => $note,
            'booking_id' => $booking->id,
            'new_status' => $new_status,
            'payment_status' => $new_payment_status,
        );
    }

    /**
     * Validate API key from webhook - use OAuth stored key
     */
    public function validate_api_key($api_key)
    {
        return $this->sepay->validate_api_key($api_key);
    }

    /**
     * Extract API key from Authorization header
     */
    private function extract_api_key($authorization)
    {
        if (empty($authorization)) {
            return null;
        }

        $parts = explode(' ', $authorization);

        if (count($parts) === 2 && $parts[0] === 'Apikey' && strlen($parts[1]) >= 10) {
            return $parts[1];
        }

        return null;
    }

    /**
     * Send confirmation email if payment is complete
     */
    private function maybe_send_confirmation_email($booking, $payment_status, $amount)
    {
        if ($payment_status !== 'paid') {
            return;
        }

        if (empty($booking->customer_email)) {
            return;
        }

        // Use Email Manager to send 'processing' email (Email 2)
        if (class_exists('Vie_Hotel_Rooms_Email_Manager')) {
            Vie_Hotel_Rooms_Email_Manager::get_instance()->send_email('processing', $booking->id);
        }
    }

    /**
     * AJAX handler to check booking payment status
     */
    public function ajax_check_payment_status()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'vie_check_payment')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        // Get booking ID and hash
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $booking_hash = isset($_POST['booking_hash']) ? sanitize_text_field(wp_unslash($_POST['booking_hash'])) : '';

        if (!$booking_id || !$booking_hash) {
            wp_send_json_error(array('message' => 'Missing booking info'));
        }

        // Get booking
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status, payment_status, booking_code FROM {$table} WHERE id = %d AND booking_hash = %s",
            $booking_id,
            $booking_hash
        ));

        if (!$booking) {
            wp_send_json_error(array('message' => 'Booking not found'));
        }

        $is_paid = in_array($booking->payment_status, ['paid']) ||
            in_array($booking->status, ['confirmed', 'completed']);

        wp_send_json_success(array(
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'booking_code' => $booking->booking_code,
            'is_paid' => $is_paid,
        ));
    }
}

/**
 * Initialize webhook handler
 */
function vie_sepay_webhook()
{
    return Vie_SePay_Webhook::get_instance();
}
