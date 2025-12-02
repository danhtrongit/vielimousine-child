<?php
/**
 * ============================================================================
 * TÊN FILE: SepayGateway.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Main facade/gateway class cho SePay payment integration.
 * Orchestrates tất cả SePay services và cung cấp unified interface.
 *
 * CHỨC NĂNG CHÍNH:
 * - Initialize và manage tất cả SePay services
 * - Provide convenient facade methods
 * - Handle WordPress hooks registration
 * - AJAX handlers cho payment status
 * - Singleton pattern
 *
 * ARCHITECTURE (Facade Pattern):
 * SepayGateway (Facade)
 * ├── SepaySettingsManager
 * ├── SepaySecurityValidator
 * ├── SepayTokenManager
 * ├── SepayAPIClient
 * ├── SepayOAuthService
 * ├── SepayBankAccountService
 * ├── SepayPaymentService
 * └── SepayWebhookHandler
 *
 * DEPENDENCIES:
 * - Requires all 8 SePay services
 * - Uses WordPress hooks system
 * - Uses WordPress AJAX API
 *
 * SỬ DỤNG:
 * $gateway = Vie_SePay_Gateway::get_instance();
 * $is_connected = $gateway->is_connected();
 * $qr_url = $gateway->generate_qr_url(123, 5000000);
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Payment
 * @version     2.1.0
 * @since       1.0.0 (Refactored to service architecture in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_SePay_Gateway
 * ============================================================================
 *
 * Main gateway class cho SePay payment integration.
 * Facade pattern orchestrating 8 specialized services.
 *
 * ARCHITECTURE:
 * - Singleton pattern
 * - Facade pattern (delegates to services)
 * - Lazy initialization of services
 * - WordPress hooks integration
 *
 * SERVICE DEPENDENCIES:
 * 1. SettingsManager - Settings management
 * 2. SecurityValidator - Webhook security
 * 3. TokenManager - OAuth token management
 * 4. APIClient - HTTP client
 * 5. OAuthService - OAuth flow
 * 6. BankAccountService - Bank account management
 * 7. PaymentService - Payment processing
 * 8. WebhookHandler - Webhook handling
 *
 * @since   1.0.0
 */
class Vie_SePay_Gateway
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Singleton instance
     *
     * @var Vie_SePay_Gateway|null
     */
    private static $instance = null;

    /**
     * Settings manager
     *
     * @var Vie_SePay_Settings_Manager
     */
    private $settings;

    /**
     * Security validator
     *
     * @var Vie_SePay_Security_Validator
     */
    private $security;

    /**
     * Token manager
     *
     * @var Vie_SePay_Token_Manager
     */
    private $token_manager;

    /**
     * API client
     *
     * @var Vie_SePay_API_Client
     */
    private $api_client;

    /**
     * OAuth service
     *
     * @var Vie_SePay_OAuth_Service
     */
    private $oauth;

    /**
     * Bank account service
     *
     * @var Vie_SePay_Bank_Account_Service
     */
    private $bank_service;

    /**
     * Payment service
     *
     * @var Vie_SePay_Payment_Service
     */
    private $payment_service;

    /**
     * Webhook handler
     *
     * @var Vie_SePay_Webhook_Handler
     */
    private $webhook_handler;

    /**
     * -------------------------------------------------------------------------
     * SINGLETON
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     *
     * @since   1.0.0
     * @return  Vie_SePay_Gateway   Singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor (private để enforce singleton)
     *
     * Initialize tất cả SePay services theo dependency order.
     *
     * INITIALIZATION ORDER:
     * 1. Foundation services (no dependencies)
     *    - SettingsManager
     *    - SecurityValidator
     * 2. Token management
     *    - TokenManager
     * 3. API client (depends on TokenManager)
     *    - APIClient
     * 4. Higher-level services
     *    - OAuthService
     *    - BankAccountService
     *    - PaymentService
     *    - WebhookHandler
     * 5. Initialize WordPress hooks
     *
     * @since   1.0.0
     */
    private function __construct()
    {
        // STEP 1: Foundation services (no dependencies)
        $this->settings = new Vie_SePay_Settings_Manager();
        $this->security = new Vie_SePay_Security_Validator();

        // STEP 2: Token management
        $this->token_manager = new Vie_SePay_Token_Manager();

        // STEP 3: API client
        $this->api_client = new Vie_SePay_API_Client($this->token_manager);

        // STEP 4: Higher-level services
        $this->oauth = new Vie_SePay_OAuth_Service(
            $this->token_manager,
            $this->settings
        );

        $this->bank_service = new Vie_SePay_Bank_Account_Service(
            $this->api_client
        );

        $this->payment_service = new Vie_SePay_Payment_Service(
            $this->settings,
            $this->bank_service
        );

        $this->webhook_handler = new Vie_SePay_Webhook_Handler(
            $this->api_client,
            $this->security,
            $this->payment_service
        );

        // STEP 5: Initialize WordPress hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     *
     * Register tất cả WordPress hooks cần thiết.
     *
     * HOOKS REGISTERED:
     * - rest_api_init: Register webhook endpoint
     * - admin_init: Handle OAuth callback
     * - wp_ajax_vie_check_payment_status: AJAX check payment
     * - wp_ajax_nopriv_vie_check_payment_status: AJAX check payment (public)
     *
     * @since   1.0.0
     * @return  void
     */
    public function init_hooks()
    {
        // Register REST API webhook endpoint
        add_action('rest_api_init', array($this->webhook_handler, 'register_webhook_endpoint'));

        // Handle OAuth callback
        add_action('admin_init', array($this->oauth, 'handle_oauth_callback'));

        // AJAX handlers
        add_action('wp_ajax_vie_check_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_vie_check_payment_status', array($this, 'ajax_check_payment_status'));
    }

    /**
     * -------------------------------------------------------------------------
     * OAUTH & CONNECTION
     * -------------------------------------------------------------------------
     */

    /**
     * Check if connected to SePay
     *
     * Delegate to OAuthService.
     *
     * @since   1.0.0
     * @return  bool    true nếu connected
     */
    public function is_connected()
    {
        return $this->oauth->is_connected();
    }

    /**
     * Get OAuth authorization URL
     *
     * Delegate to OAuthService.
     *
     * @since   1.0.0
     * @return  string|null     OAuth URL hoặc null nếu lỗi
     */
    public function get_oauth_url()
    {
        return $this->oauth->get_oauth_url();
    }

    /**
     * Get OAuth callback URL
     *
     * Delegate to OAuthService.
     *
     * @since   1.0.0
     * @return  string  Callback URL
     */
    public function get_callback_url()
    {
        return $this->oauth->get_callback_url();
    }

    /**
     * Disconnect from SePay
     *
     * Delegate to OAuthService.
     *
     * @since   1.0.0
     * @return  bool    true nếu thành công
     */
    public function disconnect()
    {
        return $this->oauth->disconnect();
    }

    /**
     * -------------------------------------------------------------------------
     * BANK ACCOUNTS
     * -------------------------------------------------------------------------
     */

    /**
     * Get all bank accounts
     *
     * Delegate to BankAccountService.
     *
     * @since   1.0.0
     * @param   bool    $cache  Enable caching
     * @return  array           Bank accounts
     */
    public function get_bank_accounts($cache = true)
    {
        return $this->bank_service->get_bank_accounts($cache);
    }

    /**
     * Get single bank account
     *
     * Delegate to BankAccountService.
     *
     * @since   1.0.0
     * @param   string  $id     Bank account ID
     * @return  array|null      Bank account data
     */
    public function get_bank_account($id)
    {
        return $this->bank_service->get_bank_account($id);
    }

    /**
     * -------------------------------------------------------------------------
     * PAYMENT METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get payment code cho booking
     *
     * Delegate to PaymentService.
     *
     * @since   1.0.0
     * @param   int     $booking_id Booking ID
     * @return  string              Payment code
     */
    public function get_payment_code($booking_id)
    {
        return $this->payment_service->get_payment_code($booking_id);
    }

    /**
     * Generate QR code URL
     *
     * Delegate to PaymentService.
     *
     * @since   1.0.0
     * @param   int     $booking_id Booking ID
     * @param   float   $amount     Payment amount
     * @return  string              QR code URL
     */
    public function generate_qr_url($booking_id, $amount)
    {
        return $this->payment_service->generate_qr_url($booking_id, $amount);
    }

    /**
     * Extract booking ID từ payment code
     *
     * Delegate to PaymentService.
     *
     * @since   1.0.0
     * @param   string  $code       Payment code
     * @return  int|false           Booking ID hoặc false
     */
    public function extract_booking_id($code)
    {
        return $this->payment_service->extract_booking_id($code);
    }

    /**
     * Process payment confirmation
     *
     * Delegate to PaymentService.
     *
     * @since   1.0.0
     * @param   int     $booking_id Booking ID
     * @return  bool                true nếu thành công
     */
    public function process_payment($booking_id)
    {
        return $this->payment_service->process_payment($booking_id);
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get webhook URL
     *
     * Delegate to WebhookHandler.
     *
     * @since   1.0.0
     * @return  string  Webhook URL
     */
    public function get_webhook_url()
    {
        return $this->webhook_handler->get_webhook_url();
    }

    /**
     * Setup webhook on SePay
     *
     * Delegate to WebhookHandler.
     *
     * @since   1.0.0
     * @return  array|null  Response data
     */
    public function setup_webhook()
    {
        return $this->webhook_handler->setup_webhook();
    }

    /**
     * Check if webhook is setup
     *
     * Delegate to WebhookHandler.
     *
     * @since   1.0.0
     * @return  bool    true nếu đã setup
     */
    public function is_webhook_setup()
    {
        return $this->webhook_handler->is_webhook_setup();
    }

    /**
     * -------------------------------------------------------------------------
     * SETTINGS METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get all settings
     *
     * Delegate to SettingsManager.
     *
     * @since   1.0.0
     * @return  array   Settings array
     */
    public function get_settings()
    {
        return $this->settings->get_settings();
    }

    /**
     * Update settings
     *
     * Delegate to SettingsManager.
     *
     * @since   1.0.0
     * @param   array   $new_settings   New settings
     * @return  bool                    true nếu thành công
     */
    public function update_settings($new_settings)
    {
        return $this->settings->update_settings($new_settings);
    }

    /**
     * Check if gateway enabled
     *
     * Delegate to SettingsManager.
     *
     * @since   1.0.0
     * @return  bool    true nếu enabled
     */
    public function is_enabled()
    {
        return $this->settings->is_enabled();
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX handler: Check payment status
     *
     * Check xem booking đã được thanh toán chưa.
     *
     * AJAX REQUEST:
     * - Action: vie_check_payment_status
     * - Method: POST
     * - Params: booking_id
     *
     * AJAX RESPONSE:
     * {
     *   "success": true,
     *   "paid": true/false,
     *   "booking": {...}
     * }
     *
     * @since   1.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_check_payment_status()
    {
        // Validate nonce (nếu có)
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'vie_check_payment')) {
            wp_send_json_error(array('message' => 'Invalid nonce'));
            return;
        }

        // Get booking ID
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;

        if (!$booking_id) {
            wp_send_json_error(array('message' => 'Invalid booking ID'));
            return;
        }

        // Get booking
        $booking_service = Vie_Booking_Service::get_instance();
        $booking = $booking_service->get_booking($booking_id);

        if (!$booking) {
            wp_send_json_error(array('message' => 'Booking not found'));
            return;
        }

        // Check payment status
        $is_paid = ($booking->payment_status === 'paid');

        // Return response
        wp_send_json_success(array(
            'paid'    => $is_paid,
            'status'  => $booking->status,
            'booking' => array(
                'id'             => $booking->id,
                'booking_code'   => $booking->booking_code,
                'payment_status' => $booking->payment_status,
                'status'         => $booking->status,
            ),
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * SERVICE ACCESSORS
     * -------------------------------------------------------------------------
     */

    /**
     * Get settings manager instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_Settings_Manager
     */
    public function get_settings_manager()
    {
        return $this->settings;
    }

    /**
     * Get security validator instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_Security_Validator
     */
    public function get_security_validator()
    {
        return $this->security;
    }

    /**
     * Get token manager instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_Token_Manager
     */
    public function get_token_manager()
    {
        return $this->token_manager;
    }

    /**
     * Get API client instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_API_Client
     */
    public function get_api_client()
    {
        return $this->api_client;
    }

    /**
     * Get OAuth service instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_OAuth_Service
     */
    public function get_oauth_service()
    {
        return $this->oauth;
    }

    /**
     * Get bank account service instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_Bank_Account_Service
     */
    public function get_bank_service()
    {
        return $this->bank_service;
    }

    /**
     * Get payment service instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_Payment_Service
     */
    public function get_payment_service()
    {
        return $this->payment_service;
    }

    /**
     * Get webhook handler instance
     *
     * @since   2.1.0
     * @return  Vie_SePay_Webhook_Handler
     */
    public function get_webhook_handler()
    {
        return $this->webhook_handler;
    }

    /**
     * -------------------------------------------------------------------------
     * HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Log message
     *
     * Helper method cho debug logging.
     *
     * @since   1.0.0
     * @param   string  $message    Log message
     * @param   string  $level      Log level (info, error, warning)
     * @return  void
     */
    public function log($message, $level = 'info')
    {
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            $prefix = '[SePay Gateway]';

            switch ($level) {
                case 'error':
                    $prefix .= ' ERROR:';
                    break;
                case 'warning':
                    $prefix .= ' WARNING:';
                    break;
                default:
                    $prefix .= ' INFO:';
            }

            error_log($prefix . ' ' . $message);
        }
    }

    /**
     * Get gateway status
     *
     * Lấy status tổng hợp của gateway.
     *
     * @since   2.1.0
     * @return  array   Status information
     */
    public function get_status()
    {
        return array(
            'enabled'        => $this->is_enabled(),
            'connected'      => $this->is_connected(),
            'webhook_setup'  => $this->is_webhook_setup(),
            'has_token'      => $this->token_manager->is_valid(),
            'bank_accounts'  => count($this->get_bank_accounts()),
            'last_connected' => $this->oauth->get_last_connected(),
        );
    }
}
