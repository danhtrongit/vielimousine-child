<?php
/**
 * ============================================================================
 * TÊN FILE: SepayTokenManager.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service quản lý OAuth2 access tokens cho SePay API.
 * Xử lý token refresh, storage, và expiration checking.
 *
 * CHỨC NĂNG CHÍNH:
 * - Store/retrieve access tokens và refresh tokens
 * - Auto-refresh tokens khi sắp hết hạn
 * - Token expiration checking
 * - Handle token invalidation
 *
 * TOKEN STORAGE:
 * - access_token: Stored in wp_options (vie_sepay_access_token)
 * - refresh_token: Stored in wp_options (vie_sepay_refresh_token)
 * - expires_at: Unix timestamp (vie_sepay_token_expires)
 *
 * TOKEN LIFECYCLE:
 * 1. Get access token
 * 2. Check if expired (or expires in < 5 minutes)
 * 3. If expired → refresh token via API
 * 4. Update stored tokens
 * 5. Return fresh access token
 *
 * REFRESH FLOW:
 * 1. Call SePay API với refresh_token
 * 2. Receive new access_token (và có thể new refresh_token)
 * 3. Update stored tokens
 * 4. If refresh fails (invalid_grant) → clear tokens
 *
 * SỬ DỤNG:
 * $token_manager = new Vie_SePay_Token_Manager();
 * $access_token = $token_manager->get_access_token();
 * // Auto-refreshes if needed
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
 * CLASS: Vie_SePay_Token_Manager
 * ============================================================================
 *
 * Service quản lý OAuth2 tokens cho SePay.
 *
 * ARCHITECTURE:
 * - Stateless service
 * - Token storage in WordPress options
 * - Auto-refresh logic
 * - HTTP client for refresh API calls
 *
 * TOKEN REFRESH STRATEGY:
 * - Refresh 5 minutes before expiration (safety buffer)
 * - Refresh on-demand khi get_access_token()
 * - Handle refresh failures gracefully
 *
 * ERROR HANDLING:
 * - invalid_grant → Clear tokens (user must reconnect)
 * - invalid_token → Clear tokens
 * - Network errors → Return null (keep tokens for retry)
 *
 * @since   2.1.0
 */
class Vie_SePay_Token_Manager
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * WordPress option names
     */
    const OPT_ACCESS_TOKEN  = 'vie_sepay_access_token';
    const OPT_REFRESH_TOKEN = 'vie_sepay_refresh_token';
    const OPT_TOKEN_EXPIRES = 'vie_sepay_token_expires';

    /**
     * Refresh buffer time (seconds)
     *
     * Refresh token khi còn 5 phút để tránh race conditions.
     *
     * @var int
     */
    const REFRESH_BUFFER = 300;

    /**
     * -------------------------------------------------------------------------
     * TOKEN RETRIEVAL
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy access token (với auto-refresh)
     *
     * Main method để get access token.
     * Tự động refresh nếu token sắp hết hạn (< 5 phút).
     *
     * FLOW:
     * 1. Lấy access token từ wp_options
     * 2. Check expiration time
     * 3. Nếu hết hạn hoặc sắp hết hạn (< 5 phút) → refresh
     * 4. Return access token
     *
     * @since   2.1.0
     * @return  string|null     Access token hoặc null nếu chưa có/không thể refresh
     */
    public function get_access_token()
    {
        $access_token = get_option(self::OPT_ACCESS_TOKEN);

        if (empty($access_token)) {
            return null;
        }

        // Check if token is expired or will expire soon
        $token_expires = (int) get_option(self::OPT_TOKEN_EXPIRES);

        if ($token_expires < time() + self::REFRESH_BUFFER) {
            // Token expired or expiring soon, refresh it
            $access_token = $this->refresh_token();
        }

        return $access_token;
    }

    /**
     * -------------------------------------------------------------------------
     * TOKEN REFRESH
     * -------------------------------------------------------------------------
     */

    /**
     * Refresh access token
     *
     * Call SePay API để refresh access token bằng refresh token.
     *
     * API ENDPOINT:
     * POST https://my.sepay.vn/woo/oauth/refresh
     *
     * REQUEST:
     * {
     *   "refresh_token": "{refresh_token}"
     * }
     *
     * RESPONSE (Success):
     * {
     *   "access_token": "{new_access_token}",
     *   "refresh_token": "{new_refresh_token}", // Optional
     *   "expires_in": 3600 // Seconds
     * }
     *
     * RESPONSE (Error):
     * {
     *   "error": "invalid_grant" // hoặc "invalid_token"
     * }
     *
     * ERROR HANDLING:
     * - invalid_grant/invalid_token → Clear tất cả tokens
     * - Network error → Return null (keep tokens for retry)
     *
     * @since   2.1.0
     * @return  string|null     New access token hoặc null nếu failed
     */
    public function refresh_token()
    {
        $refresh_token = get_option(self::OPT_REFRESH_TOKEN);

        if (empty($refresh_token)) {
            return null;
        }

        // Call SePay refresh API
        $response = wp_remote_post(SEPAY_API_URL . '/woo/oauth/refresh', array(
            'body'    => array('refresh_token' => $refresh_token),
            'timeout' => 30,
        ));

        // Handle network errors
        if (is_wp_error($response)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[SePay Token] Refresh failed: ' . $response->get_error_message());
            }
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        // Handle missing access token
        if (empty($data['access_token'])) {
            // Check for token invalidation errors
            if (isset($data['error']) && in_array($data['error'], array('invalid_grant', 'invalid_token'))) {
                if (defined('VIE_DEBUG') && VIE_DEBUG) {
                    error_log('[SePay Token] Token invalidated: ' . $data['error']);
                }
                // Clear all tokens (user must reconnect)
                $this->clear_tokens();
            }
            return null;
        }

        // Update access token
        update_option(self::OPT_ACCESS_TOKEN, $data['access_token']);

        // Update refresh token if provided (may rotate)
        if (!empty($data['refresh_token'])) {
            update_option(self::OPT_REFRESH_TOKEN, $data['refresh_token']);
        }

        // Update expiration time
        $expires_in = isset($data['expires_in']) ? intval($data['expires_in']) : 3600;
        update_option(self::OPT_TOKEN_EXPIRES, time() + $expires_in);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Token] Token refreshed successfully, expires in ' . $expires_in . ' seconds');
        }

        return $data['access_token'];
    }

    /**
     * -------------------------------------------------------------------------
     * TOKEN STORAGE
     * -------------------------------------------------------------------------
     */

    /**
     * Store tokens after OAuth callback
     *
     * Lưu tokens sau khi OAuth flow hoàn tất.
     *
     * @since   2.1.0
     * @param   string  $access_token   Access token
     * @param   string  $refresh_token  Refresh token
     * @param   int     $expires_in     Expiration time in seconds
     * @return  bool                    true nếu lưu thành công
     */
    public function store_tokens($access_token, $refresh_token, $expires_in)
    {
        update_option(self::OPT_ACCESS_TOKEN, $access_token);
        update_option(self::OPT_REFRESH_TOKEN, $refresh_token);
        update_option(self::OPT_TOKEN_EXPIRES, time() + intval($expires_in));

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Token] Tokens stored, expires in ' . $expires_in . ' seconds');
        }

        return true;
    }

    /**
     * Clear tất cả tokens
     *
     * Xóa tokens khỏi database (khi disconnect hoặc token invalid).
     *
     * @since   2.1.0
     * @return  bool    true nếu xóa thành công
     */
    public function clear_tokens()
    {
        delete_option(self::OPT_ACCESS_TOKEN);
        delete_option(self::OPT_REFRESH_TOKEN);
        delete_option(self::OPT_TOKEN_EXPIRES);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[SePay Token] Tokens cleared');
        }

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * TOKEN STATUS
     * -------------------------------------------------------------------------
     */

    /**
     * Check if có tokens
     *
     * @since   2.1.0
     * @return  bool    true nếu có access token và refresh token
     */
    public function has_tokens()
    {
        $access_token  = get_option(self::OPT_ACCESS_TOKEN);
        $refresh_token = get_option(self::OPT_REFRESH_TOKEN);

        return !empty($access_token) && !empty($refresh_token);
    }

    /**
     * Check if tokens hợp lệ (chưa hết hạn)
     *
     * @since   2.1.0
     * @return  bool    true nếu tokens valid (expires > now + buffer)
     */
    public function is_valid()
    {
        if (!$this->has_tokens()) {
            return false;
        }

        $token_expires = (int) get_option(self::OPT_TOKEN_EXPIRES);

        return $token_expires > time() + self::REFRESH_BUFFER;
    }

    /**
     * Get token expiration time
     *
     * @since   2.1.0
     * @return  int|false   Unix timestamp hoặc false nếu chưa có token
     */
    public function get_expiration_time()
    {
        if (!$this->has_tokens()) {
            return false;
        }

        return (int) get_option(self::OPT_TOKEN_EXPIRES);
    }

    /**
     * Get seconds until expiration
     *
     * @since   2.1.0
     * @return  int|false   Seconds until expiration hoặc false nếu chưa có token
     */
    public function get_seconds_until_expiration()
    {
        $expires = $this->get_expiration_time();

        if ($expires === false) {
            return false;
        }

        return max(0, $expires - time());
    }
}
