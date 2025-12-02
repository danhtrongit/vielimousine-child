<?php
/**
 * ============================================================================
 * TÊN FILE: SepayWebhookHandler.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service xử lý webhook callbacks từ SePay.
 * Register REST API endpoint, handle webhook security, và process payments.
 *
 * CHỨC NĂNG CHÍNH:
 * - Register WordPress REST API endpoint cho webhook
 * - Get webhook URL
 * - Setup webhook trên SePay
 * - Handle webhook callbacks (validate security + process payment)
 * - Verify API key authentication
 *
 * WEBHOOK FLOW:
 * 1. Admin setup webhook → setup_webhook() calls SePay API
 * 2. SePay gửi webhook khi có transaction → handle_webhook()
 * 3. Validate API key → extract_api_key() + SecurityValidator
 * 4. Parse transaction data
 * 5. Extract booking ID từ payment code
 * 6. Process payment → PaymentService
 * 7. Return response
 *
 * SECURITY:
 * - API key validation (Apikey header)
 * - HTTPS recommended
 * - Rate limiting (WordPress default)
 *
 * REST API ENDPOINT:
 * - Route: /vie/v1/sepay-webhook
 * - Method: POST
 * - Authentication: Apikey header
 *
 * SỬ DỤNG:
 * $webhook = new Vie_SePay_Webhook_Handler($api_client, $security, $payment);
 * $webhook->register_webhook_endpoint(); // In init hook
 * $url = $webhook->get_webhook_url(); // For display
 * $webhook->setup_webhook(); // Setup on SePay
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Payment
 * @version     2.1.0
 * @since       2.1.0 (Split from SepayGateway trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_SePay_Webhook_Handler
 * ============================================================================
 *
 * Service xử lý webhook callbacks từ SePay.
 *
 * ARCHITECTURE:
 * - Depends on: SepayAPIClient, SepaySecurityValidator, SepayPaymentService
 * - Uses: WordPress REST API
 * - Returns: WP_REST_Response objects
 *
 * WEBHOOK DATA STRUCTURE:
 * {
 *   "id": "123456",
 *   "gateway": "MB",
 *   "transaction_date": "2024-01-15 10:30:00",
 *   "account_number": "1234567890",
 *   "sub_account": null,
 *   "amount_in": 5000000,
 *   "amount_out": 0,
 *   "accumulated": 10000000,
 *   "code": "VL123",
 *   "transaction_content": "VL123 thanh toan booking",
 *   "reference_number": "FT24015123456",
 *   "body": "..."
 * }
 *
 * @since   2.1.0
 */
class Vie_SePay_Webhook_Handler
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * REST API namespace
     * @var string
     */
    const API_NAMESPACE = 'vie/v1';

    /**
     * Webhook route
     * @var string
     */
    const WEBHOOK_ROUTE = 'sepay-webhook';

    /**
     * Webhook option name
     * @var string
     */
    const OPT_WEBHOOK_ID = 'vie_sepay_webhook_id';

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * API client instance
     *
     * @var Vie_SePay_API_Client
     */
    private $api_client;

    /**
     * Security validator instance
     *
     * @var Vie_SePay_Security_Validator
     */
    private $security;

    /**
     * Payment service instance
     *
     * @var Vie_SePay_Payment_Service
     */
    private $payment_service;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * @since   2.1.0
     * @param   Vie_SePay_API_Client            $api_client         API client
     * @param   Vie_SePay_Security_Validator    $security           Security validator
     * @param   Vie_SePay_Payment_Service       $payment_service    Payment service
     */
    public function __construct($api_client, $security, $payment_service)
    {
        $this->api_client = $api_client;
        $this->security = $security;
        $this->payment_service = $payment_service;
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK ENDPOINT REGISTRATION
     * -------------------------------------------------------------------------
     */

    /**
     * Register webhook REST API endpoint
     *
     * Register route /wp-json/vie/v1/sepay-webhook để nhận webhook từ SePay.
     *
     * ENDPOINT DETAILS:
     * - Namespace: vie/v1
     * - Route: /sepay-webhook
     * - Method: POST
     * - Callback: handle_webhook()
     * - Authentication: API key trong header
     *
     * WORDPRESS HOOK:
     * - Hook: rest_api_init
     * - Priority: Default (10)
     *
     * @since   2.1.0
     * @return  void
     */
    public function register_webhook_endpoint()
    {
        register_rest_route(
            self::API_NAMESPACE,
            '/' . self::WEBHOOK_ROUTE,
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'handle_webhook'),
                'permission_callback' => '__return_true', // Public endpoint, validated in handle_webhook
            )
        );
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK URL
     * -------------------------------------------------------------------------
     */

    /**
     * Get webhook URL
     *
     * Lấy full URL của webhook endpoint để cung cấp cho SePay.
     *
     * URL FORMAT:
     * {site_url}/wp-json/vie/v1/sepay-webhook
     *
     * EXAMPLE:
     * https://vielimousine.com/wp-json/vie/v1/sepay-webhook
     *
     * @since   2.1.0
     * @return  string  Webhook URL
     */
    public function get_webhook_url()
    {
        return rest_url(self::API_NAMESPACE . '/' . self::WEBHOOK_ROUTE);
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK SETUP
     * -------------------------------------------------------------------------
     */

    /**
     * Setup webhook trên SePay
     *
     * Gọi SePay API để register webhook URL.
     *
     * FLOW:
     * 1. Check if already have webhook ID
     * 2. If yes → Update webhook via PUT
     * 3. If no → Create webhook via POST
     * 4. Store webhook ID
     * 5. Return result
     *
     * API ENDPOINT:
     * - POST /webhooks - Create new webhook
     * - PUT /webhooks/{id} - Update existing webhook
     *
     * REQUEST BODY:
     * {
     *   "url": "https://vielimousine.com/wp-json/vie/v1/sepay-webhook"
     * }
     *
     * @since   2.1.0
     * @return  array|null  Response data hoặc null nếu lỗi
     */
    public function setup_webhook()
    {
        $webhook_url = $this->get_webhook_url();
        $webhook_id = get_option(self::OPT_WEBHOOK_ID);

        $data = array('url' => $webhook_url);

        // Update existing webhook
        if ($webhook_id) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Updating webhook ID: ' . $webhook_id);
            }

            $response = $this->api_client->put('webhooks/' . $webhook_id, $data);
        }
        // Create new webhook
        else {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Creating new webhook');
            }

            $response = $this->api_client->post('webhooks', $data);

            // Store webhook ID
            if ($response && isset($response['data']['id'])) {
                update_option(self::OPT_WEBHOOK_ID, $response['data']['id']);

                if (defined('VIE_DEBUG') && VIE_DEBUG) {
                    error_log('[SePay Webhook] Webhook created with ID: ' . $response['data']['id']);
                }
            }
        }

        return $response;
    }

    /**
     * Delete webhook từ SePay
     *
     * Xóa webhook registration từ SePay.
     *
     * @since   2.1.0
     * @return  bool    true nếu xóa thành công
     */
    public function delete_webhook()
    {
        $webhook_id = get_option(self::OPT_WEBHOOK_ID);

        if (!$webhook_id) {
            return false;
        }

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Webhook] Deleting webhook ID: ' . $webhook_id);
        }

        $response = $this->api_client->delete('webhooks/' . $webhook_id);

        // Clear webhook ID
        delete_option(self::OPT_WEBHOOK_ID);

        return $response !== null;
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK HANDLING
     * -------------------------------------------------------------------------
     */

    /**
     * Handle webhook callback
     *
     * Main method xử lý webhook POST request từ SePay.
     *
     * FLOW:
     * 1. Validate API key từ Authorization header
     * 2. Parse request body (JSON)
     * 3. Extract booking ID từ payment code
     * 4. Validate booking ID
     * 5. Process payment via PaymentService
     * 6. Return success/error response
     *
     * EXPECTED REQUEST:
     * - Method: POST
     * - Header: Authorization: Apikey {api_key}
     * - Body: JSON transaction data
     *
     * RESPONSE CODES:
     * - 200: Payment processed successfully
     * - 400: Invalid booking ID
     * - 401: Invalid API key
     * - 500: Payment processing failed
     *
     * @since   2.1.0
     * @param   WP_REST_Request $request    REST request object
     * @return  WP_REST_Response            REST response
     */
    public function handle_webhook($request)
    {
        // STEP 1: Validate API key
        $auth_header = $request->get_header('authorization');

        if (empty($auth_header)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Missing Authorization header');
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Unauthorized: Missing API key',
                ),
                401
            );
        }

        // Extract API key từ header
        $api_key = $this->security->extract_api_key($auth_header);

        if (!$api_key || !$this->security->validate_api_key($api_key)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Invalid API key');
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Unauthorized: Invalid API key',
                ),
                401
            );
        }

        // STEP 2: Parse request body
        $body = $request->get_json_params();

        if (empty($body)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Empty request body');
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Bad Request: Empty body',
                ),
                400
            );
        }

        // Log webhook data
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Webhook] Received: ' . json_encode($body));
        }

        // STEP 3: Extract payment code
        $payment_code = isset($body['code']) ? sanitize_text_field($body['code']) : '';

        if (empty($payment_code)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Missing payment code');
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Bad Request: Missing payment code',
                ),
                400
            );
        }

        // STEP 4: Extract booking ID
        $booking_id = $this->payment_service->extract_booking_id($payment_code);

        if (!$booking_id) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Invalid payment code: ' . $payment_code);
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Bad Request: Invalid payment code',
                ),
                400
            );
        }

        // STEP 5: Process payment
        $result = $this->payment_service->process_payment($booking_id);

        if (!$result) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Webhook] Payment processing failed for booking: ' . $booking_id);
            }

            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Internal Error: Payment processing failed',
                ),
                500
            );
        }

        // STEP 6: Return success
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Webhook] Payment processed successfully for booking: ' . $booking_id);
        }

        return new WP_REST_Response(
            array(
                'success'    => true,
                'message'    => 'Payment processed',
                'booking_id' => $booking_id,
            ),
            200
        );
    }

    /**
     * -------------------------------------------------------------------------
     * WEBHOOK STATUS
     * -------------------------------------------------------------------------
     */

    /**
     * Check if webhook is setup
     *
     * Check xem đã setup webhook trên SePay chưa.
     *
     * @since   2.1.0
     * @return  bool    true nếu đã setup
     */
    public function is_webhook_setup()
    {
        return !empty(get_option(self::OPT_WEBHOOK_ID));
    }

    /**
     * Get webhook ID
     *
     * @since   2.1.0
     * @return  string|false    Webhook ID hoặc false
     */
    public function get_webhook_id()
    {
        return get_option(self::OPT_WEBHOOK_ID, false);
    }

    /**
     * -------------------------------------------------------------------------
     * HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Validate webhook request body
     *
     * Check if webhook body có tất cả required fields.
     *
     * REQUIRED FIELDS:
     * - code: Payment code
     * - amount_in: Amount received
     * - transaction_date: Transaction timestamp
     *
     * @since   2.1.0
     * @param   array   $body   Request body data
     * @return  bool            true nếu valid
     */
    public function validate_webhook_body($body)
    {
        $required_fields = array('code', 'amount_in', 'transaction_date');

        foreach ($required_fields as $field) {
            if (!isset($body[$field]) || empty($body[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get webhook info
     *
     * Lấy thông tin webhook từ SePay API.
     *
     * @since   2.1.0
     * @return  array|null  Webhook data hoặc null
     */
    public function get_webhook_info()
    {
        $webhook_id = get_option(self::OPT_WEBHOOK_ID);

        if (!$webhook_id) {
            return null;
        }

        return $this->api_client->get('webhooks/' . $webhook_id);
    }
}
