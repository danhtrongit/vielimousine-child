<?php
/**
 * Google Sheets Coupon System - Google Authentication
 * 
 * Class xử lý authentication với Google API sử dụng Service Account
 * Không dùng Composer, implement JWT signing thuần PHP
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

class VL_Google_Auth
{

    /**
     * Service account credentials
     * @var array
     */
    private $credentials;

    /**
     * Google OAuth2 scopes
     * @var array
     */
    private $scopes = [
        'https://www.googleapis.com/auth/spreadsheets'
    ];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->credentials = vl_get_service_account_credentials();

        if (!$this->credentials) {
            VL_Logger::error('Cannot initialize Google Auth: credentials not found');
        }
    }

    /**
     * Lấy Access Token (với cache)
     * 
     * Flow:
     * 1. Check cache trước
     * 2. Nếu không có, tạo JWT token
     * 3. Exchange JWT -> Access Token
     * 4. Cache access token trong 50 phút
     * 
     * @return string|false Access token hoặc false nếu lỗi
     */
    public function get_access_token()
    {
        // Check cache
        $cached_token = get_transient('vl_google_access_token');
        if ($cached_token) {
            VL_Logger::debug('Using cached access token');
            return $cached_token;
        }

        // Tạo JWT và exchange
        $jwt = $this->create_jwt_token();
        if (!$jwt) {
            VL_Logger::error('Failed to create JWT token');
            return false;
        }

        $access_token = $this->exchange_jwt_for_token($jwt);
        if (!$access_token) {
            VL_Logger::error('Failed to exchange JWT for access token');
            return false;
        }

        // Cache token trong 50 phút (Google token hết hạn sau 60 phút)
        set_transient('vl_google_access_token', $access_token, VL_GOOGLE_TOKEN_CACHE_DURATION);

        VL_Logger::info('New access token generated and cached');
        return $access_token;
    }

    /**
     * Tạo JWT Token theo chuẩn Google Service Account
     * 
     * JWT Structure:
     * - Header: {"alg": "RS256", "typ": "JWT"}
     * - Payload: {iss, scope, aud, exp, iat}
     * - Signature: RS256 (SHA256 với RSA private key)
     * 
     * @return string|false JWT token hoặc false nếu lỗi
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
            'iss' => $this->credentials['client_email'],  // Issuer
            'scope' => implode(' ', $this->scopes),       // Scopes
            'aud' => VL_GOOGLE_TOKEN_URL,                 // Audience
            'exp' => $now + 3600,                          // Expiration (1 hour)
            'iat' => $now                                  // Issued at
        ];

        // Encode header và payload
        $header_encoded = $this->base64url_encode(json_encode($header));
        $payload_encoded = $this->base64url_encode(json_encode($payload));

        // Tạo signature input
        $signature_input = $header_encoded . '.' . $payload_encoded;

        // Sign với private key (RS256 = SHA256 + RSA)
        $private_key = openssl_pkey_get_private($this->credentials['private_key']);

        if (!$private_key) {
            VL_Logger::error('Invalid private key in credentials');
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
            VL_Logger::error('Failed to sign JWT token');
            return false;
        }

        $signature_encoded = $this->base64url_encode($signature);

        // JWT = header.payload.signature
        $jwt = $signature_input . '.' . $signature_encoded;

        VL_Logger::debug('JWT token created successfully');
        return $jwt;
    }

    /**
     * Exchange JWT token -> Access Token
     * 
     * POST https://oauth2.googleapis.com/token
     * Body: grant_type=urn:ietf:params:oauth:grant-type:jwt-bearer&assertion={JWT}
     * 
     * @param string $jwt JWT token
     * @return string|false Access token hoặc false nếu lỗi
     */
    private function exchange_jwt_for_token($jwt)
    {
        $response = wp_remote_post(VL_GOOGLE_TOKEN_URL, [
            'timeout' => VL_GOOGLE_API_TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]
        ]);

        if (is_wp_error($response)) {
            VL_Logger::error('HTTP error when exchanging JWT', [
                'error' => $response->get_error_message()
            ]);
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            VL_Logger::error('Google OAuth2 error', [
                'status' => $status_code,
                'body' => $body
            ]);
            return false;
        }

        $data = json_decode($body, true);

        if (!isset($data['access_token'])) {
            VL_Logger::error('Access token not found in response', [
                'response' => $data
            ]);
            return false;
        }

        return $data['access_token'];
    }

    /**
     * Base64 URL encode (RFC 4648)
     * Khác với base64_encode thông thường:
     * - Replace + với -
     * - Replace / với _
     * - Remove padding =
     * 
     * @param string $data Data to encode
     * @return string Encoded string
     */
    private function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Force refresh access token (clear cache)
     * 
     * @return string|false New access token
     */
    public function refresh_token()
    {
        delete_transient('vl_google_access_token');
        VL_Logger::info('Access token cache cleared, forcing refresh');
        return $this->get_access_token();
    }

    /**
     * Check xem credentials có hợp lệ không
     * 
     * @return bool
     */
    public function is_valid()
    {
        return !empty($this->credentials) &&
            isset($this->credentials['private_key']) &&
            isset($this->credentials['client_email']);
    }
}
