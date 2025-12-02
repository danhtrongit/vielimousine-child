<?php
/**
 * ============================================================================
 * TÊN FILE: SepayOAuthService.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service xử lý OAuth2 authentication flow với SePay.
 * Quản lý OAuth URL generation, callback handling, và connection status.
 *
 * CHỨC NĂNG CHÍNH:
 * - Generate OAuth authorization URL
 * - Handle OAuth callback từ SePay
 * - Check connection status
 * - Disconnect từ SePay
 * - OAuth state management (CSRF protection)
 *
 * OAUTH FLOW:
 * 1. User click "Connect SePay" → get_oauth_url()
 * 2. Redirect user đến SePay authorization page
 * 3. User authorize → SePay redirect về callback URL
 * 4. handle_oauth_callback() receives tokens
 * 5. Store tokens và redirect về admin
 *
 * SECURITY:
 * - OAuth state parameter (CSRF protection)
 * - Rate limiting on OAuth requests
 * - Transient-based state storage
 *
 * SỬ DỤNG:
 * $oauth = new Vie_SePay_OAuth_Service($token_manager, $settings);
 * $url = $oauth->get_oauth_url();
 * // Redirect user to $url
 * // SePay redirects back to callback
 * // handle_oauth_callback() is called
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
 * CLASS: Vie_SePay_OAuth_Service
 * ============================================================================
 *
 * Service xử lý OAuth2 flow với SePay.
 *
 * ARCHITECTURE:
 * - Depends on: SepayTokenManager, SepaySettingsManager
 * - OAuth 2.0 Authorization Code Flow
 * - State parameter for CSRF protection
 * - Transient-based caching và rate limiting
 *
 * OAUTH ENDPOINTS:
 * - Init: POST /woo/oauth/init
 * - Callback: GET {callback_url}?access_token=...&refresh_token=...&state=...
 *
 * @since   2.1.0
 */
class Vie_SePay_OAuth_Service
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * Transient keys
     */
    const TRANSIENT_OAUTH_URL = 'vie_sepay_oauth_url';
    const TRANSIENT_OAUTH_STATE = 'vie_sepay_oauth_state';
    const TRANSIENT_RATE_LIMITED = 'vie_sepay_oauth_rate_limited';

    /**
     * WordPress option name
     */
    const OPT_LAST_CONNECTED = 'vie_sepay_last_connected_at';

    /**
     * OAuth URL cache duration (seconds)
     * @var int
     */
    const OAUTH_URL_CACHE = 300;

    /**
     * OAuth state expiration (seconds)
     * @var int
     */
    const STATE_EXPIRATION = 300;

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
     * Settings manager instance
     *
     * @var Vie_SePay_Settings_Manager
     */
    private $settings;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * @since   2.1.0
     * @param   Vie_SePay_Token_Manager     $token_manager  Token manager
     * @param   Vie_SePay_Settings_Manager  $settings       Settings manager
     */
    public function __construct($token_manager, $settings)
    {
        $this->token_manager = $token_manager;
        $this->settings = $settings;
    }

    /**
     * -------------------------------------------------------------------------
     * CONNECTION STATUS
     * -------------------------------------------------------------------------
     */

    /**
     * Check if connected to SePay
     *
     * Check xem có tokens hợp lệ không.
     *
     * CONNECTION CRITERIA:
     * - Có access token
     * - Có refresh token
     * - Token chưa hết hạn (or expires > 5 minutes)
     *
     * @since   2.1.0
     * @return  bool    true nếu connected
     */
    public function is_connected()
    {
        return $this->token_manager->is_valid();
    }

    /**
     * Disconnect from SePay
     *
     * Xóa tất cả tokens và clear caches.
     *
     * @since   2.1.0
     * @return  bool    true nếu disconnect thành công
     */
    public function disconnect()
    {
        // Clear tokens
        $this->token_manager->clear_tokens();

        // Clear last connected timestamp
        delete_option(self::OPT_LAST_CONNECTED);

        // Clear OAuth transients
        delete_transient(self::TRANSIENT_OAUTH_URL);
        delete_transient(self::TRANSIENT_OAUTH_STATE);
        delete_transient(self::TRANSIENT_RATE_LIMITED);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay OAuth] Disconnected successfully');
        }

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * OAUTH URL GENERATION
     * -------------------------------------------------------------------------
     */

    /**
     * Get OAuth authorization URL
     *
     * Generate URL để redirect user đến SePay authorization page.
     *
     * FLOW:
     * 1. Check rate limit
     * 2. Check cached URL
     * 3. Generate OAuth state (CSRF protection)
     * 4. Call SePay /oauth/init API
     * 5. Cache OAuth URL
     * 6. Return URL
     *
     * RATE LIMITING:
     * - SePay có rate limit 429 Too Many Requests
     * - Nếu hit rate limit → cache retry-after time
     * - Return null nếu rate limited
     *
     * @since   2.1.0
     * @return  string|null     OAuth URL hoặc null nếu lỗi/rate limited
     */
    public function get_oauth_url()
    {
        // Check rate limit
        $rate_limit = get_transient(self::TRANSIENT_RATE_LIMITED);
        if ($rate_limit && $rate_limit > time()) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay OAuth] Rate limited, retry after: ' . ($rate_limit - time()) . ' seconds');
            }
            return null;
        }

        // Check cached URL
        $cached = get_transient(self::TRANSIENT_OAUTH_URL);
        if ($cached) {
            return $cached;
        }

        // Generate OAuth state
        $state = $this->get_or_create_oauth_state();

        // Request OAuth URL từ SePay
        $response = wp_remote_post(SEPAY_API_URL . '/woo/oauth/init', array(
            'body' => array(
                'redirect_uri' => $this->get_callback_url(),
                'state'        => $state,
            ),
            'timeout' => 30,
        ));

        // Handle network errors
        if (is_wp_error($response)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay OAuth] Init failed: ' . $response->get_error_message());
            }
            return null;
        }

        // Handle rate limit (429)
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code === 429) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');
            $retry_seconds = $retry_after ? intval($retry_after) : 60;

            set_transient(self::TRANSIENT_RATE_LIMITED, time() + $retry_seconds, $retry_seconds);

            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay OAuth] Rate limited, retry after ' . $retry_seconds . ' seconds');
            }

            return null;
        }

        // Parse response
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($data['oauth_url'])) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay OAuth] No OAuth URL in response');
            }
            return null;
        }

        // Cache OAuth URL
        set_transient(self::TRANSIENT_OAUTH_URL, $data['oauth_url'], self::OAUTH_URL_CACHE);

        return $data['oauth_url'];
    }

    /**
     * Get OAuth callback URL
     *
     * URL mà SePay sẽ redirect về sau khi authorize.
     *
     * FORMAT:
     * {admin_url}/admin.php?vie-sepay-oauth=1
     *
     * @since   2.1.0
     * @return  string  Callback URL
     */
    public function get_callback_url()
    {
        return add_query_arg('vie-sepay-oauth', '1', admin_url('admin.php'));
    }

    /**
     * -------------------------------------------------------------------------
     * OAUTH CALLBACK HANDLING
     * -------------------------------------------------------------------------
     */

    /**
     * Handle OAuth callback
     *
     * Xử lý callback từ SePay sau khi user authorize.
     *
     * EXPECTED PARAMETERS:
     * - vie-sepay-oauth: '1' (marker)
     * - access_token: Access token
     * - refresh_token: Refresh token
     * - expires_in: Expiration time (seconds)
     * - state: OAuth state (CSRF protection)
     *
     * FLOW:
     * 1. Check if this is OAuth callback
     * 2. Validate required parameters
     * 3. Verify OAuth state (CSRF)
     * 4. Store tokens
     * 5. Clear transients
     * 6. Redirect to settings page
     *
     * @since   2.1.0
     */
    public function handle_oauth_callback()
    {
        // Check if this is OAuth callback
        if (empty($_GET['vie-sepay-oauth'])) {
            return;
        }

        // Validate required parameters
        if (empty($_GET['access_token']) || empty($_GET['refresh_token']) || empty($_GET['state'])) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay OAuth] Callback missing required parameters');
            }
            return;
        }

        // Verify OAuth state (CSRF protection)
        $saved_state = get_transient(self::TRANSIENT_OAUTH_STATE);

        if ($_GET['state'] !== $saved_state) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay OAuth] State mismatch - possible CSRF attack');
            }
            return;
        }

        // Store tokens
        $access_token = sanitize_text_field($_GET['access_token']);
        $refresh_token = sanitize_text_field($_GET['refresh_token']);
        $expires_in = isset($_GET['expires_in']) ? intval($_GET['expires_in']) : 3600;

        $this->token_manager->store_tokens($access_token, $refresh_token, $expires_in);

        // Store last connected timestamp
        update_option(self::OPT_LAST_CONNECTED, current_time('mysql'));

        // Clear transients
        delete_transient(self::TRANSIENT_RATE_LIMITED);
        delete_transient(self::TRANSIENT_OAUTH_URL);
        delete_transient(self::TRANSIENT_OAUTH_STATE);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay OAuth] Connection successful');
        }

        // Redirect to settings page
        wp_redirect(admin_url('admin.php?page=vie-hotel-settings&tab=sepay&connected=1'));
        exit;
    }

    /**
     * -------------------------------------------------------------------------
     * OAUTH STATE MANAGEMENT
     * -------------------------------------------------------------------------
     */

    /**
     * Get or create OAuth state
     *
     * OAuth state dùng cho CSRF protection.
     *
     * STATE FLOW:
     * 1. Check transient
     * 2. If exists → return
     * 3. If not → generate random string
     * 4. Store in transient (5 minutes)
     * 5. Return state
     *
     * @since   2.1.0
     * @return  string  OAuth state
     */
    private function get_or_create_oauth_state()
    {
        $state = get_transient(self::TRANSIENT_OAUTH_STATE);

        if (!$state) {
            $state = wp_generate_password(32, false);
            set_transient(self::TRANSIENT_OAUTH_STATE, $state, self::STATE_EXPIRATION);
        }

        return $state;
    }

    /**
     * -------------------------------------------------------------------------
     * HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Get last connected timestamp
     *
     * @since   2.1.0
     * @return  string|false    MySQL datetime hoặc false
     */
    public function get_last_connected()
    {
        return get_option(self::OPT_LAST_CONNECTED, false);
    }

    /**
     * Clear OAuth caches
     *
     * Clear all OAuth-related transients.
     *
     * @since   2.1.0
     * @return  bool    true
     */
    public function clear_oauth_cache()
    {
        delete_transient(self::TRANSIENT_OAUTH_URL);
        delete_transient(self::TRANSIENT_OAUTH_STATE);
        delete_transient(self::TRANSIENT_RATE_LIMITED);

        return true;
    }
}
