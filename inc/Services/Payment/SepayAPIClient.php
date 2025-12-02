<?php
/**
 * ============================================================================
 * TÊN FILE: SepayAPIClient.php
 * ============================================================================
 *
 * MÔ TẢ:
 * HTTP Client cho SePay API.
 * Xử lý tất cả API requests với authentication và error handling.
 *
 * CHỨC NĂNG CHÍNH:
 * - Send authenticated HTTP requests đến SePay API
 * - Auto-retry với token refresh khi access_denied
 * - Handle GET/POST/PUT/DELETE methods
 * - JSON encoding/decoding
 * - Error handling và logging
 *
 * API ENDPOINTS:
 * Base URL: https://my.sepay.vn/api/v1/
 *
 * AUTHENTICATION:
 * - Uses Bearer token authentication
 * - Header: Authorization: Bearer {access_token}
 * - Auto-refreshes token on 401/access_denied
 *
 * REQUEST FLOW:
 * 1. Get access token từ TokenManager
 * 2. Build HTTP request (headers, body, method)
 * 3. Send request via wp_remote_request()
 * 4. Handle response (success/error)
 * 5. If access_denied → refresh token và retry once
 * 6. Return parsed JSON response
 *
 * SỬ DỤNG:
 * $client = new Vie_SePay_API_Client($token_manager);
 * $accounts = $client->request('bank-accounts', 'GET');
 * $result = $client->request('webhooks', 'POST', ['url' => '...']);
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

// SePay API URL constant
if (!defined('SEPAY_API_URL')) {
    define('SEPAY_API_URL', 'https://my.sepay.vn');
}

/**
 * ============================================================================
 * CLASS: Vie_SePay_API_Client
 * ============================================================================
 *
 * HTTP Client cho SePay API với authentication và retry logic.
 *
 * ARCHITECTURE:
 * - Depends on: SepayTokenManager (để get access tokens)
 * - Uses: WordPress HTTP API (wp_remote_request)
 * - Returns: Parsed JSON arrays hoặc null on error
 *
 * ERROR HANDLING:
 * - Network errors → Return null
 * - API errors → Return array với 'error' key
 * - access_denied → Auto-retry once after token refresh
 *
 * RETRY LOGIC:
 * - Chỉ retry once để tránh infinite loops
 * - Chỉ retry trên access_denied errors
 * - Không retry trên network errors
 *
 * @since   2.1.0
 */
class Vie_SePay_API_Client
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * API version
     * @var string
     */
    const API_VERSION = 'v1';

    /**
     * Default timeout (seconds)
     * @var int
     */
    const DEFAULT_TIMEOUT = 30;

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Token manager instance
     *
     * @var Vie_SePay_Token_Manager
     */
    private $token_manager;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * @since   2.1.0
     * @param   Vie_SePay_Token_Manager $token_manager Token manager instance
     */
    public function __construct($token_manager)
    {
        $this->token_manager = $token_manager;
    }

    /**
     * -------------------------------------------------------------------------
     * API REQUEST METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Make API request
     *
     * Main method để gửi HTTP requests đến SePay API.
     *
     * SUPPORTED METHODS:
     * - GET: Retrieve data (data sent as query params)
     * - POST: Create resources (data sent as JSON body)
     * - PUT: Update resources (data sent as JSON body)
     * - DELETE: Delete resources
     *
     * REQUEST HEADERS:
     * - Authorization: Bearer {access_token}
     * - Content-Type: application/json
     *
     * AUTO-RETRY:
     * - On access_denied error → Refresh token và retry once
     * - No retry on network errors (avoid infinite loops)
     *
     * @since   2.1.0
     * @param   string      $endpoint   API endpoint (e.g., 'bank-accounts')
     * @param   string      $method     HTTP method (GET, POST, PUT, DELETE)
     * @param   array|null  $data       Request data (query params hoặc body)
     * @param   bool        $retry      Internal flag để prevent infinite retry
     * @return  array|null              Parsed JSON response hoặc null on error
     */
    public function request($endpoint, $method = 'GET', $data = null, $retry = true)
    {
        // Get access token
        $access_token = $this->token_manager->get_access_token();

        if (!$access_token) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay API] No access token available');
            }
            return null;
        }

        // Build request args
        $args = array(
            'method'  => strtoupper($method),
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => self::DEFAULT_TIMEOUT,
        );

        // Handle request data
        if ($data !== null) {
            if ($method === 'GET') {
                // GET: Add data as query params
                $endpoint .= '?' . http_build_query($data);
            } else {
                // POST/PUT: Add data as JSON body
                $args['body'] = json_encode($data);
            }
        }

        // Build full URL
        $url = SEPAY_API_URL . '/api/' . self::API_VERSION . '/' . ltrim($endpoint, '/');

        // Send request
        $response = wp_remote_request($url, $args);

        // Handle network errors
        if (is_wp_error($response)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log(sprintf(
                    '[SePay API] Request failed: %s %s - %s',
                    $method,
                    $endpoint,
                    $response->get_error_message()
                ));
            }
            return null;
        }

        // Parse response
        $result = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        // Handle access_denied error (token expired or invalid)
        if (isset($result['error']) && $result['error'] === 'access_denied' && $retry) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay API] Access denied, refreshing token and retrying...');
            }

            // Refresh token
            $this->token_manager->refresh_token();

            // Retry once (với $retry = false để prevent infinite loops)
            return $this->request($endpoint, $method, $data, false);
        }

        // Log API errors
        if (isset($result['error']) && defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf(
                '[SePay API] API error: %s %s - HTTP %d - %s',
                $method,
                $endpoint,
                $status_code,
                $result['error']
            ));
        }

        return $result;
    }

    /**
     * -------------------------------------------------------------------------
     * CONVENIENCE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * GET request
     *
     * @since   2.1.0
     * @param   string      $endpoint   API endpoint
     * @param   array|null  $params     Query parameters
     * @return  array|null
     */
    public function get($endpoint, $params = null)
    {
        return $this->request($endpoint, 'GET', $params);
    }

    /**
     * POST request
     *
     * @since   2.1.0
     * @param   string      $endpoint   API endpoint
     * @param   array|null  $data       Request body data
     * @return  array|null
     */
    public function post($endpoint, $data = null)
    {
        return $this->request($endpoint, 'POST', $data);
    }

    /**
     * PUT request
     *
     * @since   2.1.0
     * @param   string      $endpoint   API endpoint
     * @param   array|null  $data       Request body data
     * @return  array|null
     */
    public function put($endpoint, $data = null)
    {
        return $this->request($endpoint, 'PUT', $data);
    }

    /**
     * DELETE request
     *
     * @since   2.1.0
     * @param   string  $endpoint   API endpoint
     * @return  array|null
     */
    public function delete($endpoint)
    {
        return $this->request($endpoint, 'DELETE');
    }

    /**
     * -------------------------------------------------------------------------
     * HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Check if có valid token
     *
     * @since   2.1.0
     * @return  bool    true nếu có valid access token
     */
    public function has_valid_token()
    {
        return $this->token_manager->is_valid();
    }

    /**
     * Get API base URL
     *
     * @since   2.1.0
     * @return  string  API base URL
     */
    public function get_base_url()
    {
        return SEPAY_API_URL . '/api/' . self::API_VERSION . '/';
    }
}
