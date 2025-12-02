<?php
/**
 * ============================================================================
 * TÊN FILE: class-sepay-gateway.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Tích hợp cổng thanh toán SePay với OAuth2 và Webhook.
 * Hỗ trợ thanh toán QR tự động xác nhận.
 * 
 * CHỨC NĂNG CHÍNH:
 * - OAuth2 kết nối SePay
 * - Quản lý bank accounts
 * - Generate QR code thanh toán
 * - Webhook xử lý thanh toán tự động
 * - API helpers
 * 
 * SỬ DỤNG:
 * $sepay = Vie_SePay_Gateway::get_instance();
 * $qr_url = $sepay->generate_qr_url($booking_id, $amount);
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

// Constants
if (!defined('SEPAY_API_URL')) {
    define('SEPAY_API_URL', 'https://my.sepay.vn');
}

/**
 * ============================================================================
 * CLASS: Vie_SePay_Gateway
 * ============================================================================
 */
class Vie_SePay_Gateway {

    /**
     * -------------------------------------------------------------------------
     * CONSTANTS & PROPERTIES
     * -------------------------------------------------------------------------
     */

    const OPTION_NAME        = 'vie_sepay_settings';
    const OPT_ACCESS_TOKEN   = 'vie_sepay_access_token';
    const OPT_REFRESH_TOKEN  = 'vie_sepay_refresh_token';
    const OPT_TOKEN_EXPIRES  = 'vie_sepay_token_expires';
    const OPT_WEBHOOK_ID     = 'vie_sepay_webhook_id';
    const OPT_WEBHOOK_API_KEY= 'vie_sepay_webhook_api_key';
    const OPT_LAST_CONNECTED = 'vie_sepay_last_connected_at';

    /** @var Vie_SePay_Gateway|null Singleton instance */
    private static $instance = null;

    /** @var array Settings cache */
    private $settings = null;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     * 
     * @since   2.0.0
     * @return  Vie_SePay_Gateway
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
        // Register REST API endpoint for webhook
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));

        // OAuth callback
        add_action('admin_init', array($this, 'handle_oauth_callback'));

        // AJAX for checking payment status
        add_action('wp_ajax_nopriv_vie_check_booking_payment', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_vie_check_booking_payment', array($this, 'ajax_check_payment_status'));
    }

    /**
     * -------------------------------------------------------------------------
     * OAUTH2 METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Check if connected to SePay
     * 
     * @since   2.0.0
     * @return  bool
     */
    public function is_connected() {
        $access_token  = get_option(self::OPT_ACCESS_TOKEN);
        $refresh_token = get_option(self::OPT_REFRESH_TOKEN);
        $token_expires = (int) get_option(self::OPT_TOKEN_EXPIRES);

        return !empty($access_token) 
            && !empty($refresh_token) 
            && $token_expires > time() + 300;
    }

    /**
     * Get OAuth URL for connecting
     * 
     * @since   2.0.0
     * @return  string|null
     */
    public function get_oauth_url() {
        // Check rate limit
        $rate_limit = get_transient('vie_sepay_oauth_rate_limited');
        if ($rate_limit && $rate_limit > time()) {
            return null;
        }

        // Get cached URL
        $cached = get_transient('vie_sepay_oauth_url');
        if ($cached) {
            return $cached;
        }

        // Generate state
        $state = $this->get_or_create_oauth_state();

        // Request OAuth URL
        $response = wp_remote_post(SEPAY_API_URL . '/woo/oauth/init', array(
            'body' => array(
                'redirect_uri' => $this->get_callback_url(),
                'state'        => $state,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            $this->log('OAuth init failed: ' . $response->get_error_message());
            return null;
        }

        // Handle rate limit
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code === 429) {
            $retry_after   = wp_remote_retrieve_header($response, 'retry-after');
            $retry_seconds = $retry_after ? intval($retry_after) : 60;
            set_transient('vie_sepay_oauth_rate_limited', time() + $retry_seconds, $retry_seconds);
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['oauth_url'])) {
            return null;
        }

        set_transient('vie_sepay_oauth_url', $data['oauth_url'], 300);

        return $data['oauth_url'];
    }

    /**
     * Get OAuth callback URL
     * 
     * @since   2.0.0
     * @return  string
     */
    public function get_callback_url() {
        return add_query_arg('vie-sepay-oauth', '1', admin_url('admin.php'));
    }

    /**
     * Handle OAuth callback
     * 
     * @since   2.0.0
     */
    public function handle_oauth_callback() {
        if (empty($_GET['vie-sepay-oauth'])) {
            return;
        }

        if (empty($_GET['access_token']) || empty($_GET['refresh_token']) || empty($_GET['state'])) {
            return;
        }

        $saved_state = get_transient('vie_sepay_oauth_state');
        if ($_GET['state'] !== $saved_state) {
            return;
        }

        // Save tokens
        update_option(self::OPT_ACCESS_TOKEN, sanitize_text_field($_GET['access_token']));
        update_option(self::OPT_REFRESH_TOKEN, sanitize_text_field($_GET['refresh_token']));
        
        $expires_in = isset($_GET['expires_in']) ? intval($_GET['expires_in']) : 3600;
        update_option(self::OPT_TOKEN_EXPIRES, time() + $expires_in);
        update_option(self::OPT_LAST_CONNECTED, current_time('mysql'));

        // Clear transients
        delete_transient('vie_sepay_rate_limited');
        delete_transient('vie_sepay_oauth_url');
        delete_transient('vie_sepay_oauth_state');

        // Redirect to settings page
        wp_redirect(admin_url('admin.php?page=vie-hotel-settings&tab=sepay&connected=1'));
        exit;
    }

    /**
     * Get or create OAuth state
     * 
     * @since   2.0.0
     * @return  string
     */
    private function get_or_create_oauth_state() {
        $state = get_transient('vie_sepay_oauth_state');
        if (!$state) {
            $state = wp_generate_password(32, false);
            set_transient('vie_sepay_oauth_state', $state, 300);
        }
        return $state;
    }

    /**
     * Get access token (auto refresh if needed)
     * 
     * @since   2.0.0
     * @return  string|null
     */
    public function get_access_token() {
        $access_token = get_option(self::OPT_ACCESS_TOKEN);
        if (empty($access_token)) {
            return null;
        }

        $token_expires = (int) get_option(self::OPT_TOKEN_EXPIRES);
        if ($token_expires < time() + 300) {
            $access_token = $this->refresh_token();
        }

        return $access_token;
    }

    /**
     * Refresh access token
     * 
     * @since   2.0.0
     * @return  string|null
     */
    public function refresh_token() {
        $refresh_token = get_option(self::OPT_REFRESH_TOKEN);
        if (empty($refresh_token)) {
            return null;
        }

        $response = wp_remote_post(SEPAY_API_URL . '/woo/oauth/refresh', array(
            'body'    => array('refresh_token' => $refresh_token),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            if (isset($data['error']) && in_array($data['error'], array('invalid_grant', 'invalid_token'))) {
                $this->disconnect();
            }
            return null;
        }

        // Update tokens
        update_option(self::OPT_ACCESS_TOKEN, $data['access_token']);
        if (!empty($data['refresh_token'])) {
            update_option(self::OPT_REFRESH_TOKEN, $data['refresh_token']);
        }
        update_option(self::OPT_TOKEN_EXPIRES, time() + intval($data['expires_in']));

        return $data['access_token'];
    }

    /**
     * Make API request to SePay
     * 
     * @since   2.0.0
     * @param   string  $endpoint
     * @param   string  $method
     * @param   array   $data
     * @return  array|null
     */
    public function make_request($endpoint, $method = 'GET', $data = null) {
        if (!$this->is_connected()) {
            return null;
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            return null;
        }

        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        if ($data !== null && $method !== 'GET') {
            $args['body'] = json_encode($data);
        } elseif ($data !== null && $method === 'GET') {
            $endpoint .= '?' . http_build_query($data);
        }

        $url      = SEPAY_API_URL . '/api/v1/' . $endpoint;
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return null;
        }

        $result = json_decode(wp_remote_retrieve_body($response), true);

        // Handle access denied - try refresh
        if (isset($result['error']) && $result['error'] === 'access_denied') {
            $this->refresh_token();
            return $this->make_request($endpoint, $method, $data);
        }

        return $result;
    }

    /**
     * Disconnect from SePay
     * 
     * @since   2.0.0
     */
    public function disconnect() {
        delete_option(self::OPT_ACCESS_TOKEN);
        delete_option(self::OPT_REFRESH_TOKEN);
        delete_option(self::OPT_TOKEN_EXPIRES);
        delete_option(self::OPT_WEBHOOK_ID);
        delete_option(self::OPT_LAST_CONNECTED);

        // Clear caches
        $transients = array(
            'vie_sepay_bank_accounts',
            'vie_sepay_user_info',
            'vie_sepay_company',
        );
        foreach ($transients as $t) {
            delete_transient($t);
        }
    }

    /**
     * -------------------------------------------------------------------------
     * BANK ACCOUNT METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get bank accounts from SePay
     * 
     * @since   2.0.0
     * @param   bool    $cache  Use cache
     * @return  array
     */
    public function get_bank_accounts($cache = true) {
        if (!$this->is_connected()) {
            return array();
        }

        if ($cache) {
            $cached = get_transient('vie_sepay_bank_accounts');
            if ($cached) {
                return $cached;
            }
        }

        $response = $this->make_request('bank-accounts');
        $data     = $response['data'] ?? array();

        if ($cache && !empty($data)) {
            set_transient('vie_sepay_bank_accounts', $data, 3600);
        }

        return $data;
    }

    /**
     * Get single bank account
     * 
     * @since   2.0.0
     * @param   int     $id     Bank account ID
     * @return  array|null
     */
    public function get_bank_account($id) {
        $cached = get_transient('vie_sepay_bank_account_' . $id);
        if ($cached) {
            return $cached;
        }

        $response = $this->make_request('bank-accounts/' . $id);
        $data     = $response['data'] ?? null;

        if ($data) {
            set_transient('vie_sepay_bank_account_' . $id, $data, 3600);
        }

        return $data;
    }

    /**
     * -------------------------------------------------------------------------
     * PAYMENT METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get payment code for booking
     * 
     * @since   2.0.0
     * @param   int     $booking_id
     * @return  string
     */
    public function get_payment_code($booking_id) {
        $prefix = $this->get_setting('pay_code_prefix', 'VL');
        $code   = $prefix . $booking_id;

        // Handle special banks
        $bank_account_id = $this->get_setting('bank_account');
        if ($bank_account_id) {
            $bank = $this->get_bank_account($bank_account_id);
            if ($bank && in_array($bank['bank']['bin'], array('970415', '970425'))) {
                // VietinBank, ABBANK
                $code = "SEVQR " . $code;
            }
        }

        return $code;
    }

    /**
     * Generate QR code URL
     * 
     * @since   2.0.0
     * @param   int     $booking_id
     * @param   float   $amount
     * @return  string
     */
    public function generate_qr_url($booking_id, $amount) {
        $bank_account_id = $this->get_setting('bank_account');
        if (!$bank_account_id) {
            return '';
        }

        $bank = $this->get_bank_account($bank_account_id);
        if (!$bank) {
            return '';
        }

        $remark = $this->get_payment_code($booking_id);

        return sprintf(
            'https://qr.sepay.vn/img?acc=%s&bank=%s&amount=%s&des=%s&template=compact',
            urlencode($bank['account_number']),
            urlencode($bank['bank']['bin']),
            urlencode($amount),
            urlencode($remark)
        );
    }

    /**
     * Extract booking ID from payment code
     * 
     * @since   2.0.0
     * @param   string  $code
     * @return  int|false
     */
    public function extract_booking_id($code) {
        $prefix = $this->get_setting('pay_code_prefix', 'VL');
        
        // Remove SEVQR prefix
        $code = str_replace(array('SEVQR ', 'SEVQR'), '', $code);
        $code = str_replace($prefix, '', $code);
        $code = trim($code);
        
        return is_numeric($code) ? intval($code) : false;
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Register webhook REST API endpoint
     * 
     * @since   2.0.0
     */
    public function register_webhook_endpoint() {
        register_rest_route('vie-hotel/v1', '/sepay-webhook', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Get webhook URL
     * 
     * @since   2.0.0
     * @return  string
     */
    public function get_webhook_url() {
        $path = 'vie-hotel/v1/sepay-webhook';
        if (get_option('permalink_structure')) {
            return home_url('/wp-json/' . $path);
        }
        return home_url('/?rest_route=/' . $path);
    }

    /**
     * Setup webhook on SePay
     * 
     * @since   2.0.0
     * @param   int     $bank_account_id
     * @return  bool
     */
    public function setup_webhook($bank_account_id) {
        if (!$this->is_connected()) {
            return false;
        }

        $api_key = get_option(self::OPT_WEBHOOK_API_KEY);
        if (empty($api_key)) {
            $api_key = wp_generate_password(32, false);
        }

        $webhook_url = $this->get_webhook_url();
        $webhook_id  = get_option(self::OPT_WEBHOOK_ID);

        $webhook_data = array(
            'bank_account_id'      => (int) $bank_account_id,
            'event_type'           => 'In_only',
            'authen_type'          => 'Api_Key',
            'request_content_type' => 'Json',
            'api_key'              => $api_key,
            'webhook_url'          => $webhook_url,
            'name'                 => sprintf('[%s] Hotel Booking Webhook', get_bloginfo('name')),
            'is_verify_payment'    => 1,
            'skip_if_no_code'      => 1,
            'only_va'              => 0,
            'active'               => 1,
        );

        if ($webhook_id) {
            $response = $this->make_request('webhooks/' . $webhook_id, 'PATCH', $webhook_data);
        } else {
            $response = $this->make_request('webhooks', 'POST', $webhook_data);
        }

        if (isset($response['status']) && $response['status'] === 'success') {
            update_option(self::OPT_WEBHOOK_ID, $response['data']['id'] ?? $webhook_id);
            update_option(self::OPT_WEBHOOK_API_KEY, $api_key);
            return true;
        }

        return false;
    }

    /**
     * Handle incoming webhook from SePay
     * 
     * @since   2.0.0
     * @param   WP_REST_Request $request
     * @return  array
     */
    public function handle_webhook($request) {
        $parameters    = $request->get_json_params();
        $authorization = $request->get_header('Authorization');

        // Log if debug
        $this->log('Webhook received', $parameters);

        // Validate API key
        $api_key = $this->extract_api_key($authorization);

        if (!$this->validate_api_key($api_key)) {
            $this->log('Invalid API Key');
            return array('success' => false, 'message' => 'Invalid API Key');
        }

        // Validate parameters
        if (!is_array($parameters)) {
            return array('success' => false, 'message' => 'Invalid JSON request');
        }

        // Check required fields
        $required = array('accountNumber', 'gateway', 'code', 'transferType', 'transferAmount');
        foreach ($required as $field) {
            if (!isset($parameters[$field])) {
                return array('success' => false, 'message' => 'Missing: ' . $field);
            }
        }

        // Only process incoming transfers
        if ($parameters['transferType'] !== 'in') {
            return array('success' => false, 'message' => 'transferType must be "in"');
        }

        // Extract booking ID
        $booking_id = $this->extract_booking_id($parameters['code']);

        if (!$booking_id) {
            return array('success' => false, 'message' => "Booking ID not found from code: {$parameters['code']}");
        }

        // Get booking
        $manager = Vie_Booking_Manager::get_instance();
        $booking = $manager->get_booking($booking_id);

        if (!$booking) {
            return array('success' => false, 'message' => "Booking #{$booking_id} not found");
        }

        // Check if already paid
        if (in_array($booking->payment_status, array('paid'))) {
            return array('success' => false, 'message' => 'Already paid');
        }

        // Process payment
        return $this->process_payment($booking, $parameters);
    }

    /**
     * Process payment from webhook
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @param   array   $params
     * @return  array
     */
    private function process_payment($booking, $params) {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';

        $booking_total   = (int) $booking->total_amount;
        $transfer_amount = (int) $params['transferAmount'];

        // Build note
        $note = sprintf(
            "SePay: Đã nhận %s vào TK %s (%s) lúc %s",
            vie_format_currency($transfer_amount),
            $params['accountNumber'],
            $params['gateway'],
            $params['transactionDate'] ?? current_time('mysql')
        );

        // Determine status
        if ($transfer_amount >= $booking_total) {
            $new_payment_status = 'paid';
            $new_status         = 'confirmed';

            if ($transfer_amount > $booking_total) {
                $note .= '. THỪA: ' . vie_format_currency($transfer_amount - $booking_total);
            }
        } else {
            $new_payment_status = 'partial';
            $new_status         = $booking->status;
            $note .= '. THIẾU: ' . vie_format_currency($booking_total - $transfer_amount);
        }

        // Update admin note
        $timestamp   = current_time('d/m/Y H:i');
        $admin_note  = $booking->admin_note;
        $admin_note .= (!empty($admin_note) ? "\n\n" : '') . "[{$timestamp}] {$note}";

        // Update booking
        $updated = $wpdb->update(
            $table,
            array(
                'payment_status' => $new_payment_status,
                'payment_method' => 'sepay',
                'status'         => $new_status,
                'admin_note'     => $admin_note,
                'updated_at'     => current_time('mysql'),
            ),
            array('id' => $booking->id),
            array('%s', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($updated === false) {
            return array('success' => false, 'message' => 'Update failed');
        }

        // Send email if paid
        if ($new_payment_status === 'paid' && class_exists('Vie_Email_Manager')) {
            Vie_Email_Manager::get_instance()->send_email('processing', $booking->id);
        }

        // Trigger action
        do_action('vie_hotel_booking_payment_received', $booking->id, $transfer_amount, $new_payment_status);

        return array(
            'success'        => true,
            'message'        => $note,
            'booking_id'     => $booking->id,
            'payment_status' => $new_payment_status,
        );
    }

    /**
     * Validate API key
     * 
     * @since   2.0.0
     * @param   string  $key
     * @return  bool
     */
    public function validate_api_key($key) {
        $stored = get_option(self::OPT_WEBHOOK_API_KEY);
        return !empty($key) && $key === $stored;
    }

    /**
     * Extract API key from Authorization header
     * 
     * @since   2.0.0
     * @param   string  $authorization
     * @return  string|null
     */
    private function extract_api_key($authorization) {
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
     * AJAX: Check booking payment status
     * 
     * @since   2.0.0
     */
    public function ajax_check_payment_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vie_check_payment')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
        }

        $booking_id   = absint($_POST['booking_id'] ?? 0);
        $booking_hash = sanitize_text_field($_POST['booking_hash'] ?? '');

        if (!$booking_id || !$booking_hash) {
            wp_send_json_error(array('message' => 'Missing booking info'));
        }

        $manager = Vie_Booking_Manager::get_instance();
        $booking = $manager->get_booking_by_hash($booking_hash);

        if (!$booking || $booking->id != $booking_id) {
            wp_send_json_error(array('message' => 'Booking not found'));
        }

        $is_paid = in_array($booking->payment_status, array('paid')) ||
                   in_array($booking->status, array('confirmed', 'completed'));

        wp_send_json_success(array(
            'status'         => $booking->status,
            'payment_status' => $booking->payment_status,
            'booking_code'   => $booking->booking_code,
            'is_paid'        => $is_paid,
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * SETTINGS METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get all settings
     * 
     * @since   2.0.0
     * @return  array
     */
    public function get_settings() {
        if ($this->settings === null) {
            $this->settings = get_option(self::OPTION_NAME, array(
                'enabled'         => 'yes',
                'bank_account'    => '',
                'pay_code_prefix' => 'VL',
            ));
        }
        return $this->settings;
    }

    /**
     * Get single setting
     * 
     * @since   2.0.0
     * @param   string  $key
     * @param   mixed   $default
     * @return  mixed
     */
    public function get_setting($key, $default = '') {
        $settings = $this->get_settings();
        return $settings[$key] ?? $default;
    }

    /**
     * Update settings
     * 
     * @since   2.0.0
     * @param   array   $new_settings
     * @return  bool
     */
    public function update_settings($new_settings) {
        $settings = wp_parse_args($new_settings, $this->get_settings());
        update_option(self::OPTION_NAME, $settings);
        $this->settings = $settings;
        return true;
    }

    /**
     * Check if SePay is enabled
     * 
     * @since   2.0.0
     * @return  bool
     */
    public function is_enabled() {
        return $this->is_connected() && $this->get_setting('enabled') === 'yes';
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Log message
     * 
     * @since   2.0.0
     * @param   string  $message
     * @param   array   $context
     */
    private function log($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[VIE_SEPAY] ' . $message . (!empty($context) ? ' | ' . json_encode($context) : ''));
        }
    }
}

/**
 * Get SePay instance (helper function)
 * 
 * @since   2.0.0
 * @return  Vie_SePay_Gateway
 */
function vie_sepay() {
    return Vie_SePay_Gateway::get_instance();
}
