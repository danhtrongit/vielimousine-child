<?php
/**
 * ============================================================================
 * TÊN FILE: SepayBankAccountService.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service quản lý bank accounts từ SePay API.
 * Lấy danh sách bank accounts và thông tin chi tiết từng account.
 *
 * CHỨC NĂNG CHÍNH:
 * - Get all bank accounts (với caching)
 * - Get single bank account by ID (với caching)
 * - Clear bank account cache
 *
 * CACHING STRATEGY:
 * - Cache duration: 1 hour (3600 seconds)
 * - Transient keys: 'vie_sepay_bank_accounts', 'vie_sepay_bank_account_{id}'
 * - Cache có thể bypass bằng $cache = false parameter
 *
 * API ENDPOINT:
 * - GET /bank-accounts - List all accounts
 * - GET /bank-accounts/{id} - Get single account
 *
 * SỬ DỤNG:
 * $bank_service = new Vie_SePay_Bank_Account_Service($api_client);
 * $accounts = $bank_service->get_bank_accounts();
 * $account = $bank_service->get_bank_account('123');
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
 * CLASS: Vie_SePay_Bank_Account_Service
 * ============================================================================
 *
 * Service quản lý bank accounts từ SePay.
 *
 * ARCHITECTURE:
 * - Depends on: SepayAPIClient
 * - Uses: WordPress Transients API for caching
 * - Returns: Arrays of bank account data
 *
 * BANK ACCOUNT STRUCTURE:
 * {
 *   "id": "123",
 *   "account_number": "1234567890",
 *   "account_name": "NGUYEN VAN A",
 *   "bank_name": "Vietcombank",
 *   "bank_code": "VCB",
 *   "branch": "Ho Chi Minh"
 * }
 *
 * @since   2.1.0
 */
class Vie_SePay_Bank_Account_Service
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * Cache duration (1 hour)
     * @var int
     */
    const CACHE_DURATION = 3600;

    /**
     * Cache key cho danh sách accounts
     * @var string
     */
    const CACHE_KEY_LIST = 'vie_sepay_bank_accounts';

    /**
     * Cache key prefix cho single account
     * @var string
     */
    const CACHE_KEY_PREFIX = 'vie_sepay_bank_account_';

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
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * @since   2.1.0
     * @param   Vie_SePay_API_Client $api_client API client instance
     */
    public function __construct($api_client)
    {
        $this->api_client = $api_client;
    }

    /**
     * -------------------------------------------------------------------------
     * BANK ACCOUNT METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy danh sách bank accounts
     *
     * Lấy tất cả bank accounts từ SePay API.
     * Kết quả được cache trong 1 giờ để giảm API calls.
     *
     * CACHING:
     * - Cache enabled by default
     * - Cache duration: 1 hour
     * - Set $cache = false để force fresh data
     *
     * @since   2.1.0
     * @param   bool    $cache  Enable caching (default: true)
     * @return  array           Array of bank accounts hoặc empty array
     */
    public function get_bank_accounts($cache = true)
    {
        // Check cache first
        if ($cache) {
            $cached = get_transient(self::CACHE_KEY_LIST);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Fetch from API
        $response = $this->api_client->get('bank-accounts');

        // Handle API errors
        if ($response === null) {
            return array();
        }

        // Extract data
        $data = $response['data'] ?? array();

        // Cache if enabled và có data
        if ($cache && !empty($data)) {
            set_transient(self::CACHE_KEY_LIST, $data, self::CACHE_DURATION);
        }

        return $data;
    }

    /**
     * Lấy single bank account by ID
     *
     * Lấy thông tin chi tiết của 1 bank account.
     * Kết quả được cache trong 1 giờ.
     *
     * @since   2.1.0
     * @param   string  $id     Bank account ID
     * @return  array|null      Bank account data hoặc null nếu không tìm thấy
     */
    public function get_bank_account($id)
    {
        // Check cache first
        $cache_key = self::CACHE_KEY_PREFIX . $id;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        // Fetch from API
        $response = $this->api_client->get('bank-accounts/' . $id);

        // Handle API errors
        if ($response === null) {
            return null;
        }

        // Extract data
        $data = $response['data'] ?? null;

        // Cache if có data
        if ($data !== null) {
            set_transient($cache_key, $data, self::CACHE_DURATION);
        }

        return $data;
    }

    /**
     * -------------------------------------------------------------------------
     * CACHE MANAGEMENT
     * -------------------------------------------------------------------------
     */

    /**
     * Clear tất cả bank account cache
     *
     * Xóa cache của bank accounts list.
     * Note: Không xóa individual account caches (vì không biết IDs).
     *
     * @since   2.1.0
     * @return  bool    true nếu xóa thành công
     */
    public function clear_cache()
    {
        return delete_transient(self::CACHE_KEY_LIST);
    }

    /**
     * Clear cache của single account
     *
     * @since   2.1.0
     * @param   string  $id     Bank account ID
     * @return  bool            true nếu xóa thành công
     */
    public function clear_account_cache($id)
    {
        $cache_key = self::CACHE_KEY_PREFIX . $id;
        return delete_transient($cache_key);
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Find bank account by account number
     *
     * Search trong danh sách accounts để tìm account by account number.
     *
     * @since   2.1.0
     * @param   string  $account_number Account number cần tìm
     * @return  array|null              Bank account data hoặc null
     */
    public function find_by_account_number($account_number)
    {
        $accounts = $this->get_bank_accounts();

        foreach ($accounts as $account) {
            if (isset($account['account_number']) && $account['account_number'] === $account_number) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Get account numbers list
     *
     * Helper để lấy array of account numbers (cho dropdown, etc.).
     *
     * @since   2.1.0
     * @return  array   Array of account numbers
     */
    public function get_account_numbers()
    {
        $accounts = $this->get_bank_accounts();
        $numbers = array();

        foreach ($accounts as $account) {
            if (isset($account['account_number'])) {
                $numbers[] = $account['account_number'];
            }
        }

        return $numbers;
    }

    /**
     * Format account for display
     *
     * Format bank account data cho display (VD: dropdown options).
     *
     * @since   2.1.0
     * @param   array   $account    Bank account data
     * @return  string              Formatted string
     */
    public function format_account_display($account)
    {
        if (empty($account)) {
            return '';
        }

        $bank_name = $account['bank_name'] ?? '';
        $account_number = $account['account_number'] ?? '';
        $account_name = $account['account_name'] ?? '';

        return sprintf(
            '%s - %s (%s)',
            $bank_name,
            $account_number,
            $account_name
        );
    }
}
