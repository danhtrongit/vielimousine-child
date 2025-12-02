<?php
/**
 * ============================================================================
 * TÊN FILE: SepaySettingsManager.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service quản lý settings cho SePay Gateway.
 * Xử lý lưu trữ và truy xuất cấu hình SePay từ WordPress options.
 *
 * CHỨC NĂNG CHÍNH:
 * - Load settings từ wp_options
 * - Get/Set individual settings
 * - Update settings
 * - Check enabled status
 *
 * SETTINGS STORED:
 * - enabled: 'yes' hoặc 'no' (gateway enabled/disabled)
 * - bank_account: Selected bank account ID
 * - pay_code_prefix: Prefix cho payment code (VD: 'VL')
 *
 * STORAGE:
 * - Option name: 'vie_sepay_settings'
 * - Format: Array with keys above
 * - Cached in memory để tránh multiple DB queries
 *
 * SỬ DỤNG:
 * $settings = new Vie_SePay_Settings_Manager();
 * $bank = $settings->get_setting('bank_account');
 * $settings->update_settings(['bank_account' => '123']);
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
 * CLASS: Vie_SePay_Settings_Manager
 * ============================================================================
 *
 * Service quản lý settings cho SePay Gateway.
 *
 * ARCHITECTURE:
 * - Single responsibility: chỉ quản lý settings
 * - In-memory cache để giảm DB queries
 * - No external dependencies (pure WordPress options API)
 *
 * SETTINGS DEFAULT:
 * - enabled: 'yes'
 * - bank_account: '' (empty, user must select)
 * - pay_code_prefix: 'VL'
 *
 * @since   2.1.0
 */
class Vie_SePay_Settings_Manager
{
    /**
     * -------------------------------------------------------------------------
     * CONSTANTS
     * -------------------------------------------------------------------------
     */

    /**
     * WordPress option name cho settings
     * @var string
     */
    const OPTION_NAME = 'vie_sepay_settings';

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Settings cache
     *
     * Cached in memory để tránh multiple get_option() calls.
     *
     * @var array|null
     */
    private $settings = null;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Không load settings ngay, sẽ load lazy khi cần (on first get_settings() call).
     *
     * @since   2.1.0
     */
    public function __construct()
    {
        // Settings will be loaded lazily on first access
    }

    /**
     * -------------------------------------------------------------------------
     * SETTINGS METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy tất cả settings
     *
     * Load từ wp_options lần đầu, sau đó cache lại trong memory.
     *
     * DEFAULT SETTINGS:
     * - enabled: 'yes' (gateway enabled by default)
     * - bank_account: '' (user must select bank account)
     * - pay_code_prefix: 'VL' (prefix cho payment codes)
     *
     * @since   2.1.0
     * @return  array   Settings array
     */
    public function get_settings()
    {
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
     * Lấy một setting cụ thể
     *
     * Helper method để get single setting value.
     *
     * @since   2.1.0
     * @param   string  $key        Setting key
     * @param   mixed   $default    Default value nếu key không tồn tại
     * @return  mixed               Setting value hoặc default
     */
    public function get_setting($key, $default = '')
    {
        $settings = $this->get_settings();
        return $settings[$key] ?? $default;
    }

    /**
     * Cập nhật settings
     *
     * Merge new settings với existing settings, sau đó lưu vào database.
     *
     * AUTO-MERGE:
     * - New settings được merge với existing settings
     * - Không ghi đè settings không được truyền vào
     * - Cache được refresh sau update
     *
     * @since   2.1.0
     * @param   array   $new_settings   Settings mới cần update
     * @return  bool                    true nếu update thành công
     */
    public function update_settings($new_settings)
    {
        $settings = wp_parse_args($new_settings, $this->get_settings());
        update_option(self::OPTION_NAME, $settings);
        $this->settings = $settings;
        return true;
    }

    /**
     * Check xem SePay có enabled không
     *
     * Chỉ check setting 'enabled' = 'yes'.
     * Note: Không check connection status (phụ thuộc OAuth service).
     *
     * @since   2.1.0
     * @return  bool    true nếu enabled setting = 'yes'
     */
    public function is_enabled()
    {
        return $this->get_setting('enabled') === 'yes';
    }

    /**
     * Enable SePay gateway
     *
     * Helper method để enable gateway.
     *
     * @since   2.1.0
     * @return  bool
     */
    public function enable()
    {
        return $this->update_settings(array('enabled' => 'yes'));
    }

    /**
     * Disable SePay gateway
     *
     * Helper method để disable gateway.
     *
     * @since   2.1.0
     * @return  bool
     */
    public function disable()
    {
        return $this->update_settings(array('enabled' => 'no'));
    }

    /**
     * Get bank account ID
     *
     * Helper method để lấy selected bank account.
     *
     * @since   2.1.0
     * @return  string  Bank account ID hoặc empty string
     */
    public function get_bank_account()
    {
        return $this->get_setting('bank_account');
    }

    /**
     * Set bank account ID
     *
     * Helper method để set bank account.
     *
     * @since   2.1.0
     * @param   string  $account_id Bank account ID
     * @return  bool
     */
    public function set_bank_account($account_id)
    {
        return $this->update_settings(array('bank_account' => sanitize_text_field($account_id)));
    }

    /**
     * Get payment code prefix
     *
     * Helper method để lấy prefix cho payment codes.
     *
     * @since   2.1.0
     * @return  string  Prefix (default: 'VL')
     */
    public function get_pay_code_prefix()
    {
        return $this->get_setting('pay_code_prefix', 'VL');
    }

    /**
     * Set payment code prefix
     *
     * Helper method để set prefix.
     *
     * @since   2.1.0
     * @param   string  $prefix Prefix (VD: 'VL', 'BK', etc.)
     * @return  bool
     */
    public function set_pay_code_prefix($prefix)
    {
        return $this->update_settings(array('pay_code_prefix' => sanitize_text_field($prefix)));
    }

    /**
     * Reset settings về default
     *
     * Xóa settings khỏi database, force reload defaults.
     *
     * @since   2.1.0
     * @return  bool
     */
    public function reset()
    {
        delete_option(self::OPTION_NAME);
        $this->settings = null;
        return true;
    }
}
