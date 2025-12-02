<?php
/**
 * ============================================================================
 * TÊN FILE: GoogleAuth.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Xử lý authentication với Google APIs sử dụng Service Account.
 * Implement JWT signing thuần PHP, không cần Composer dependencies.
 *
 * CHỨC NĂNG CHÍNH:
 * - Tạo JWT token theo chuẩn Google Service Account
 * - Exchange JWT để lấy Access Token
 * - Cache access token (50 phút)
 * - Force refresh token khi cần
 *
 * AUTHENTICATION FLOW:
 * 1. Tạo JWT token với private key
 * 2. POST JWT đến Google OAuth2 endpoint
 * 3. Nhận Access Token (valid 60 phút)
 * 4. Cache token (50 phút để an toàn)
 * 5. Reuse cached token cho requests tiếp theo
 *
 * SỬ DỤNG:
 * $auth = Vie_Google_Auth::get_instance();
 * $token = $auth->get_access_token();
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Integration
 * @version     2.1.0
 * @since       2.0.0 (Di chuyển từ inc/classes trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Google_Auth
 * ============================================================================
 *
 * Lớp xử lý Google OAuth2 authentication cho Service Account.
 *
 * TECHNICAL DETAILS:
 * - JWT Algorithm: RS256 (RSA Signature with SHA-256)
 * - Token lifetime: 3600 seconds (1 hour)
 * - Cache duration: 3000 seconds (50 minutes)
 * - No external dependencies (pure PHP + OpenSSL)
 *
 * CREDENTIALS REQUIRED:
 * - client_email: Service account email
 * - private_key: RSA private key (PEM format)
 *
 * @since   2.0.0
 * @uses    OpenSSL extension    Required for JWT signing
 */
class Vie_Google_Auth
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Service account credentials
     *
     * Expected structure:
     * [
     *   'client_email' => 'xxx@xxx.iam.gserviceaccount.com',
     *   'private_key' => '-----BEGIN PRIVATE KEY-----\n...'
     * ]
     *
     * @var array|null
     */
    private $credentials;

    /**
     * Google OAuth2 scopes
     *
     * Defines what APIs can be accessed.
     * Current: Google Sheets API (read/write)
     *
     * @var array
     */
    private $scopes = [
        'https://www.googleapis.com/auth/spreadsheets'
    ];

    /**
     * Cache key for access token
     * @var string
     */
    private $cache_key = 'vie_google_access_token';

    /**
     * Singleton instance
     * @var Vie_Google_Auth|null
     */
    private static $instance = null;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO (SINGLETON PATTERN)
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     *
     * @since   2.0.0
     * @return  Vie_Google_Auth
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private để enforce Singleton)
     *
     * Load credentials từ database (priority) hoặc file (fallback).
     * Nếu không tìm thấy credentials, log error.
     *
     * PRIORITY:
     * 1. Database (vie_gsheets_settings option)
     * 2. File (vl_get_service_account_credentials helper)
     *
     * @since   2.0.0
     */
    private function __construct()
    {
        // Priority 1: Load từ database settings
        $gsheets_settings = get_option('vie_gsheets_settings', array());

        if (!empty($gsheets_settings['service_account_json'])) {
            $this->credentials = json_decode($gsheets_settings['service_account_json'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[Vie Google Auth] Invalid JSON in database settings: ' . json_last_error_msg());
                $this->credentials = null;
            }
        }

        // Priority 2: Fallback to file-based credentials
        if (!$this->credentials && function_exists('vl_get_service_account_credentials')) {
            $this->credentials = vl_get_service_account_credentials();
        }

        if (!$this->credentials && defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Auth] Cannot initialize: credentials not found in database or file');
        }
    }

    /**
     * -------------------------------------------------------------------------
     * PUBLIC API
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy Access Token (với cache)
     *
     * FLOW:
     * 1. Check cache trước (transient)
     * 2. Nếu không có cache:
     *    a. Tạo JWT token với private key
     *    b. Exchange JWT → Access Token qua Google API
     *    c. Cache access token (50 phút)
     * 3. Return access token
     *
     * ERROR HANDLING:
     * - Return false nếu credentials invalid
     * - Return false nếu JWT creation failed
     * - Return false nếu Google API failed
     *
     * @since   2.0.0
     * @return  string|false    Access token hoặc false nếu lỗi
     */
    public function get_access_token()
    {
        // Check cache
        $cached_token = get_transient($this->cache_key);
        if ($cached_token) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[Vie Google Auth] Using cached access token');
            }
            return $cached_token;
        }

        // Tạo JWT và exchange
        $jwt = $this->create_jwt_token();
        if (!$jwt) {
            error_log('[Vie Google Auth ERROR] Failed to create JWT token');
            return false;
        }

        $access_token = $this->exchange_jwt_for_token($jwt);
        if (!$access_token) {
            error_log('[Vie Google Auth ERROR] Failed to exchange JWT for access token');
            return false;
        }

        // Cache token trong 50 phút (Google token hết hạn sau 60 phút)
        $cache_duration = defined('VL_GOOGLE_TOKEN_CACHE_DURATION')
            ? VL_GOOGLE_TOKEN_CACHE_DURATION
            : 50 * MINUTE_IN_SECONDS;

        set_transient($this->cache_key, $access_token, $cache_duration);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Auth] New access token generated and cached');
        }

        return $access_token;
    }

    /**
     * Force refresh access token
     *
     * Clear cache và force tạo token mới.
     * Hữu ích khi token bị revoked hoặc test authentication.
     *
     * @since   2.0.0
     * @return  string|false    New access token hoặc false nếu lỗi
     */
    public function refresh_token()
    {
        delete_transient($this->cache_key);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Auth] Access token cache cleared, forcing refresh');
        }

        return $this->get_access_token();
    }

    /**
     * Check xem credentials có hợp lệ không
     *
     * Validate credentials structure có đủ thông tin để authenticate.
     *
     * @since   2.0.0
     * @return  bool    True nếu credentials valid, false nếu không
     */
    public function is_valid()
    {
        return !empty($this->credentials) &&
            isset($this->credentials['private_key']) &&
            isset($this->credentials['client_email']);
    }

    /**
     * -------------------------------------------------------------------------
     * JWT TOKEN CREATION
     * -------------------------------------------------------------------------
     */

    /**
     * Tạo JWT Token theo chuẩn Google Service Account
     *
     * JWT STRUCTURE (RFC 7519):
     * - Header: {"alg": "RS256", "typ": "JWT"}
     * - Payload: {iss, scope, aud, exp, iat}
     * - Signature: RS256 (SHA256 with RSA private key)
     *
     * ALGORITHM:
     * 1. Encode header và payload as base64url
     * 2. Concatenate: header.payload
     * 3. Sign with private key (SHA256+RSA)
     * 4. Encode signature as base64url
     * 5. Final JWT: header.payload.signature
     *
     * @since   2.0.0
     * @return  string|false    JWT token hoặc false nếu lỗi
     */
    private function create_jwt_token()
    {
        if (!$this->credentials) {
            return false;
        }

        $now = time();

        // JWT Header
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];

        // JWT Payload (Claims)
        $payload = [
            'iss'   => $this->credentials['client_email'],  // Issuer (Service Account email)
            'scope' => implode(' ', $this->scopes),         // Requested scopes
            'aud'   => defined('VL_GOOGLE_TOKEN_URL') ? VL_GOOGLE_TOKEN_URL : 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,                         // Expiration (1 hour from now)
            'iat'   => $now                                 // Issued at
        ];

        // Encode header và payload
        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));

        // Tạo signature input
        $signature_input = $header_encoded . '.' . $payload_encoded;

        // Sign với private key (RS256 = SHA256 + RSA)
        $private_key = openssl_pkey_get_private($this->credentials['private_key']);

        if (!$private_key) {
            error_log('[Vie Google Auth ERROR] Invalid private key in credentials');
            return false;
        }

        $signature = '';
        $success = openssl_sign(
            $signature_input,
            $signature,
            $private_key,
            OPENSSL_ALGO_SHA256
        );

        openssl_free_key($private_key);

        if (!$success) {
            error_log('[Vie Google Auth ERROR] Failed to sign JWT token');
            return false;
        }

        $signature_encoded = $this->base64url_encode($signature);

        // JWT = header.payload.signature
        $jwt = $signature_input . '.' . $signature_encoded;

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Google Auth] JWT token created successfully');
        }

        return $jwt;
    }

    /**
     * -------------------------------------------------------------------------
     * GOOGLE OAUTH2 TOKEN EXCHANGE
     * -------------------------------------------------------------------------
     */

    /**
     * Exchange JWT token → Access Token
     *
     * Google OAuth2 Token Endpoint:
     * POST https://oauth2.googleapis.com/token
     *
     * REQUEST:
     * Content-Type: application/x-www-form-urlencoded
     * Body: grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion={JWT}
     *
     * RESPONSE (Success):
     * {
     *   "access_token": "ya29.xxx",
     *   "expires_in": 3600,
     *   "token_type": "Bearer"
     * }
     *
     * @since   2.0.0
     * @param   string      $jwt    JWT token
     * @return  string|false        Access token hoặc false nếu lỗi
     */
    private function exchange_jwt_for_token($jwt)
    {
        $token_url = defined('VL_GOOGLE_TOKEN_URL')
            ? VL_GOOGLE_TOKEN_URL
            : 'https://oauth2.googleapis.com/token';

        $timeout = defined('VL_GOOGLE_API_TIMEOUT')
            ? VL_GOOGLE_API_TIMEOUT
            : 10;

        $response = wp_remote_post($token_url, [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt
            ]
        ]);

        // Check for HTTP errors
        if (is_wp_error($response)) {
            error_log(sprintf(
                '[Vie Google Auth ERROR] HTTP error when exchanging JWT: %s',
                $response->get_error_message()
            ));
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Check for non-200 status
        if ($status_code !== 200) {
            error_log(sprintf(
                '[Vie Google Auth ERROR] Google OAuth2 error (HTTP %d): %s',
                $status_code,
                $body
            ));
            return false;
        }

        $data = json_decode($body, true);

        // Validate response structure
        if (!isset($data['access_token'])) {
            error_log(sprintf(
                '[Vie Google Auth ERROR] Access token not found in response: %s',
                $body
            ));
            return false;
        }

        return $data['access_token'];
    }

    /**
     * -------------------------------------------------------------------------
     * UTILITIES
     * -------------------------------------------------------------------------
     */

    /**
     * Base64 URL encode (RFC 4648)
     *
     * JWT requires base64url encoding (not standard base64):
     * - Replace + with -
     * - Replace / with _
     * - Remove padding =
     *
     * @since   2.0.0
     * @param   string  $data   Data to encode
     * @return  string          Base64url encoded string
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Alias cho code cũ vẫn dùng VL_Google_Auth
if (!class_exists('VL_Google_Auth')) {
    class_alias('Vie_Google_Auth', 'VL_Google_Auth');
}
