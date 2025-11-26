<?php
/**
 * SePay Helper Class for Hotel Booking
 * 
 * Tích hợp thanh toán SePay với OAuth2 tự động
 * Tương tự plugin WooCommerce gốc - Chỉ cần 1 nút kết nối
 * 
 * @package VieHotelRooms
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constants
if (!defined('SEPAY_API_URL')) {
    define('SEPAY_API_URL', 'https://my.sepay.vn');
}

class Vie_SePay_Helper
{
    // Option keys
    const OPTION_NAME = 'vie_hotel_sepay_settings';
    const OPT_ACCESS_TOKEN = 'vie_sepay_access_token';
    const OPT_REFRESH_TOKEN = 'vie_sepay_refresh_token';
    const OPT_TOKEN_EXPIRES = 'vie_sepay_token_expires';
    const OPT_WEBHOOK_ID = 'vie_sepay_webhook_id';
    const OPT_WEBHOOK_API_KEY = 'vie_sepay_webhook_api_key';
    const OPT_LAST_CONNECTED = 'vie_sepay_last_connected_at';

    private static $instance = null;
    private $settings = null;
    private $bank_data = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->init_bank_data();
    }

    // ==================== OAuth2 Methods ====================

    /**
     * Check if connected to SePay via OAuth2
     */
    public function is_connected()
    {
        $access_token = get_option(self::OPT_ACCESS_TOKEN);
        $refresh_token = get_option(self::OPT_REFRESH_TOKEN);
        $token_expires = (int) get_option(self::OPT_TOKEN_EXPIRES);

        return !empty($access_token) 
            && !empty($refresh_token) 
            && $token_expires > time() + 300;
    }

    /**
     * Get OAuth2 URL for connecting
     */
    public function get_oauth_url()
    {
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
        $response = wp_remote_post(SEPAY_API_URL . '/woo/oauth/init', [
            'body' => [
                'redirect_uri' => $this->get_callback_url(),
                'state' => $state,
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('OAuth init failed', ['error' => $response->get_error_message()]);
            return null;
        }

        // Handle rate limit
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');
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
     */
    public function get_callback_url()
    {
        return add_query_arg('vie-sepay-oauth', '1', admin_url('admin.php'));
    }

    /**
     * Get or create OAuth state
     */
    private function get_or_create_oauth_state()
    {
        $state = get_transient('vie_sepay_oauth_state');
        if (!$state) {
            $state = wp_generate_password(32, false);
            set_transient('vie_sepay_oauth_state', $state, 300);
        }
        return $state;
    }

    /**
     * Handle OAuth callback
     */
    public function handle_oauth_callback()
    {
        if (empty($_GET['access_token']) || empty($_GET['refresh_token']) || empty($_GET['state'])) {
            return false;
        }

        $saved_state = get_transient('vie_sepay_oauth_state');
        if ($_GET['state'] !== $saved_state) {
            return false;
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

        return true;
    }

    /**
     * Get access token (auto refresh if needed)
     */
    public function get_access_token()
    {
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
     */
    public function refresh_token()
    {
        $refresh_token = get_option(self::OPT_REFRESH_TOKEN);
        if (empty($refresh_token)) {
            return null;
        }

        $response = wp_remote_post(SEPAY_API_URL . '/woo/oauth/refresh', [
            'body' => ['refresh_token' => $refresh_token],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['access_token'])) {
            // Token invalid, disconnect
            if (isset($data['error']) && in_array($data['error'], ['invalid_grant', 'invalid_token'])) {
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
     */
    public function make_request($endpoint, $method = 'GET', $data = null)
    {
        if (!$this->is_connected()) {
            return null;
        }

        $access_token = $this->get_access_token();
        if (!$access_token) {
            return null;
        }

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
            ],
            'timeout' => 30,
        ];

        if ($data !== null && $method !== 'GET') {
            $args['body'] = json_encode($data);
        } elseif ($data !== null && $method === 'GET') {
            $endpoint .= '?' . http_build_query($data);
        }

        $url = SEPAY_API_URL . '/api/v1/' . $endpoint;
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
     */
    public function disconnect()
    {
        delete_option(self::OPT_ACCESS_TOKEN);
        delete_option(self::OPT_REFRESH_TOKEN);
        delete_option(self::OPT_TOKEN_EXPIRES);
        delete_option(self::OPT_WEBHOOK_ID);
        delete_option(self::OPT_LAST_CONNECTED);

        // Clear caches
        $transients = [
            'vie_sepay_bank_accounts',
            'vie_sepay_user_info',
            'vie_sepay_company',
        ];
        foreach ($transients as $t) {
            delete_transient($t);
        }
    }

    // ==================== Bank Account Methods ====================

    /**
     * Get bank accounts from SePay
     */
    public function get_bank_accounts($cache = true)
    {
        if (!$this->is_connected()) {
            return [];
        }

        if ($cache) {
            $cached = get_transient('vie_sepay_bank_accounts');
            if ($cached) {
                return $cached;
            }
        }

        $response = $this->make_request('bank-accounts');
        $data = $response['data'] ?? [];

        if ($cache && !empty($data)) {
            set_transient('vie_sepay_bank_accounts', $data, 3600);
        }

        return $data;
    }

    /**
     * Get single bank account
     */
    public function get_bank_account($id)
    {
        $cached = get_transient('vie_sepay_bank_account_' . $id);
        if ($cached) {
            return $cached;
        }

        $response = $this->make_request('bank-accounts/' . $id);
        $data = $response['data'] ?? null;

        if ($data) {
            set_transient('vie_sepay_bank_account_' . $id, $data, 3600);
        }

        return $data;
    }

    /**
     * Get user info from SePay
     */
    public function get_user_info()
    {
        if (!$this->is_connected()) {
            return null;
        }

        $cached = get_transient('vie_sepay_user_info');
        if ($cached) {
            return $cached;
        }

        $response = $this->make_request('me');
        $data = $response['data'] ?? null;

        if ($data) {
            set_transient('vie_sepay_user_info', $data, 3600);
        }

        return $data;
    }

    /**
     * Get company info (for pay code prefix)
     */
    public function get_company_info($cache = true)
    {
        if (!$this->is_connected()) {
            return null;
        }

        if ($cache) {
            $cached = get_transient('vie_sepay_company');
            if ($cached) {
                return $cached;
            }
        }

        $response = $this->make_request('company');
        $data = $response['data'] ?? null;

        if ($cache && $data) {
            set_transient('vie_sepay_company', $data, 3600);
        }

        return $data;
    }

    /**
     * Get pay code prefixes
     */
    public function get_pay_code_prefixes()
    {
        $company = $this->get_company_info();

        if (empty($company['configurations']['paycode']) || 
            $company['configurations']['paycode'] !== true) {
            return [];
        }

        $formats = $company['configurations']['payment_code_formats'] ?? [];
        $prefixes = [];

        foreach ($formats as $format) {
            if ($format['is_active']) {
                $prefixes[] = [
                    'prefix' => $format['prefix'],
                    'suffix_from' => $format['suffix_from'],
                    'suffix_to' => $format['suffix_to'],
                ];
            }
        }

        return $prefixes;
    }

    // ==================== Webhook Methods ====================

    /**
     * Create or update webhook
     */
    public function setup_webhook($bank_account_id)
    {
        if (!$this->is_connected()) {
            return false;
        }

        $api_key = get_option(self::OPT_WEBHOOK_API_KEY);
        if (empty($api_key)) {
            $api_key = wp_generate_password(32, false);
        }

        $webhook_url = $this->get_webhook_url();
        $webhook_id = get_option(self::OPT_WEBHOOK_ID);

        $webhook_data = [
            'bank_account_id' => (int) $bank_account_id,
            'event_type' => 'In_only',
            'authen_type' => 'Api_Key',
            'request_content_type' => 'Json',
            'api_key' => $api_key,
            'webhook_url' => $webhook_url,
            'name' => sprintf('[%s] Hotel Booking Webhook', get_bloginfo('name')),
            'is_verify_payment' => 1,
            'skip_if_no_code' => 1,
            'only_va' => 0,
            'active' => 1,
        ];

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
     * Get webhook URL
     */
    public function get_webhook_url()
    {
        $path = 'vie-hotel/v1/sepay-webhook';
        if (get_option('permalink_structure')) {
            return home_url('/wp-json/' . $path);
        }
        return home_url('/?rest_route=/' . $path);
    }

    /**
     * Validate webhook API key
     */
    public function validate_api_key($key)
    {
        $stored = get_option(self::OPT_WEBHOOK_API_KEY);
        return !empty($key) && $key === $stored;
    }

    // ==================== Settings Methods ====================

    public function get_settings()
    {
        if ($this->settings === null) {
            $this->settings = get_option(self::OPTION_NAME, [
                'enabled' => 'yes',
                'bank_account' => '',
                'pay_code_prefix' => 'VL',
                'success_message' => '<h2 style="color:#73AF55;">Thanh toán thành công!</h2>',
            ]);
        }
        return $this->settings;
    }

    public function get_setting($key, $default = '')
    {
        $settings = $this->get_settings();
        return $settings[$key] ?? $default;
    }

    public function update_settings($new_settings)
    {
        $settings = wp_parse_args($new_settings, $this->get_settings());
        update_option(self::OPTION_NAME, $settings);
        $this->settings = $settings;
        return true;
    }

    public function is_enabled()
    {
        return $this->is_connected() && $this->get_setting('enabled') === 'yes';
    }

    // ==================== Payment Methods ====================

    /**
     * Get payment code for booking
     */
    public function get_payment_code($booking_id)
    {
        $prefix = $this->get_setting('pay_code_prefix', 'VL');
        $code = $prefix . $booking_id;

        // Get bank info for special banks
        $bank_account_id = $this->get_setting('bank_account');
        if ($bank_account_id) {
            $bank = $this->get_bank_account($bank_account_id);
            if ($bank && in_array($bank['bank']['bin'], ['970415', '970425'])) {
                // VietinBank, ABBANK
                $code = "SEVQR " . $code;
            }
        }

        return $code;
    }

    /**
     * Generate QR code URL
     */
    public function generate_qr_url($booking_id, $amount)
    {
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
     */
    public function extract_booking_id($code)
    {
        $prefix = $this->get_setting('pay_code_prefix', 'VL');
        
        // Remove SEVQR prefix
        $code = str_replace(['SEVQR ', 'SEVQR'], '', $code);
        $code = str_replace($prefix, '', $code);
        $code = trim($code);
        
        return is_numeric($code) ? intval($code) : false;
    }

    /**
     * Format currency VND
     */
    public function format_currency($amount)
    {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }

    // ==================== Helpers ====================

    public function log_error($message, $context = [])
    {
        if (WP_DEBUG) {
            error_log('[VIE_SEPAY] ' . $message . ' | ' . json_encode($context));
        }
    }

    private function init_bank_data()
    {
        $this->bank_data = [
            'vietcombank' => ['bin' => '970436', 'short_name' => 'Vietcombank'],
            'vpbank' => ['bin' => '970432', 'short_name' => 'VPBank'],
            'acb' => ['bin' => '970416', 'short_name' => 'ACB'],
            'techcombank' => ['bin' => '970407', 'short_name' => 'Techcombank'],
            'mbbank' => ['bin' => '970422', 'short_name' => 'MBBank'],
            'bidv' => ['bin' => '970418', 'short_name' => 'BIDV'],
            'vietinbank' => ['bin' => '970415', 'short_name' => 'VietinBank'],
            'tpbank' => ['bin' => '970423', 'short_name' => 'TPBank'],
        ];
    }
}

/**
 * Get SePay instance
 */
function vie_sepay()
{
    return Vie_SePay_Helper::get_instance();
}
