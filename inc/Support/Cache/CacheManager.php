<?php
/**
 * ============================================================================
 * TÊN FILE: CacheManager.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Quản lý cache system cho theme sử dụng WordPress Transients API.
 * Hỗ trợ auto-refresh với WP Cron và fallback strategy.
 *
 * CHỨC NĂNG CHÍNH:
 * - Cache danh sách coupons từ Google Sheets
 * - Auto-refresh cache mỗi 5 phút (WP Cron)
 * - Fallback to stale cache khi API failed
 * - Cache statistics và monitoring
 *
 * SỬ DỤNG:
 * $cache = Vie_Cache_Manager::get_instance();
 * $coupons = $cache->get_coupons();
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Support/Cache
 * @version     2.1.0
 * @since       2.0.0 (Di chuyển từ inc/classes trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Cache_Manager
 * ============================================================================
 *
 * Lớp quản lý cache sử dụng WordPress Transients API.
 * Triển khai Singleton Pattern và WP Cron integration.
 *
 * ARCHITECTURE:
 * - Singleton pattern đảm bảo 1 instance duy nhất
 * - Sử dụng WordPress Transients (object cache nếu có)
 * - WP Cron để auto-refresh background
 * - Graceful degradation khi API fails
 *
 * @since   2.0.0
 * @uses    VL_Google_Sheets_API    Đọc dữ liệu từ Google Sheets
 */
class Vie_Cache_Manager
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Google Sheets API instance
     * @var VL_Google_Sheets_API
     */
    private $sheets_api;

    /**
     * Cache key cho danh sách coupons
     * @var string
     */
    private $cache_key = 'vie_coupons_cache';

    /**
     * Singleton instance
     * @var Vie_Cache_Manager|null
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
     * @return  Vie_Cache_Manager   Instance duy nhất của class
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
     * Khởi tạo Google Sheets API và setup WP Cron.
     *
     * @since   2.0.0
     */
    private function __construct()
    {
        $this->sheets_api = new VL_Google_Sheets_API();
        $this->setup_cron();
    }

    /**
     * -------------------------------------------------------------------------
     * WP CRON SETUP
     * -------------------------------------------------------------------------
     */

    /**
     * Setup WP Cron để auto refresh cache
     *
     * Đăng ký custom cron interval (5 phút) và schedule job.
     * Job sẽ tự động refresh cache mỗi 5 phút.
     *
     * @since   2.0.0
     */
    private function setup_cron()
    {
        // Register custom cron interval (5 phút)
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Schedule cron job nếu chưa có
        if (!wp_next_scheduled('vie_refresh_coupons_cache')) {
            wp_schedule_event(time(), 'every_5_minutes', 'vie_refresh_coupons_cache');

            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[Vie Cache] Cron job scheduled for cache refresh');
            }
        }

        // Hook vào cron action
        add_action('vie_refresh_coupons_cache', [$this, 'refresh_coupons']);
    }

    /**
     * Add custom cron interval (5 phút)
     *
     * WordPress mặc định không có interval 5 phút,
     * function này thêm custom interval.
     *
     * @since   2.0.0
     * @param   array   $schedules  Danh sách cron schedules hiện tại
     * @return  array               Schedules đã được thêm interval mới
     */
    public function add_cron_interval($schedules)
    {
        if (!isset($schedules['every_5_minutes'])) {
            $schedules['every_5_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display'  => __('Every 5 Minutes', 'viechild')
            ];
        }
        return $schedules;
    }

    /**
     * -------------------------------------------------------------------------
     * CACHE OPERATIONS
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy danh sách coupons (từ cache hoặc fresh read)
     *
     * Flow:
     * 1. Nếu $force_refresh = true, bỏ qua cache
     * 2. Check cache, nếu có thì return
     * 3. Cache miss -> refresh từ API
     *
     * @since   2.0.0
     * @param   bool    $force_refresh  Force refresh từ Google Sheets
     * @return  array|false             Array of coupons hoặc false nếu lỗi
     */
    public function get_coupons($force_refresh = false)
    {
        if ($force_refresh) {
            return $this->refresh_coupons();
        }

        // Try cache first
        $cached = get_transient($this->cache_key);

        if ($cached !== false) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log(sprintf('[Vie Cache] Coupons loaded from cache (%d items)', count($cached)));
            }
            return $cached;
        }

        // Cache miss, load fresh
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Cache] Cache miss, loading fresh coupons');
        }

        return $this->refresh_coupons();
    }

    /**
     * Refresh cache từ Google Sheets
     *
     * Đọc dữ liệu mới từ Google Sheets và cập nhật cache.
     * Có fallback strategy khi API failed.
     *
     * FALLBACK STRATEGY:
     * - Nếu API failed, sử dụng stale cache (nếu có)
     * - Log error để admin biết
     *
     * @since   2.0.0
     * @return  array|false Array of coupons hoặc false nếu lỗi
     */
    public function refresh_coupons()
    {
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Cache] Refreshing coupons cache from Google Sheets');
        }

        $raw_data = $this->sheets_api->read_range();

        if ($raw_data === false) {
            error_log('[Vie Cache ERROR] Failed to refresh coupons: API read failed');

            // Fallback: trả về cached data cũ nếu còn
            $old_cache = get_transient($this->cache_key);
            if ($old_cache !== false) {
                error_log('[Vie Cache WARNING] Using stale cache data as fallback');
                return $old_cache;
            }

            return false;
        }

        // Parse raw data thành structured array
        $coupons = $this->parse_sheet_data($raw_data);

        // Lưu vào cache
        $cache_duration = defined('VL_COUPON_CACHE_DURATION') ? VL_COUPON_CACHE_DURATION : 5 * MINUTE_IN_SECONDS;
        set_transient($this->cache_key, $coupons, $cache_duration);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf(
                '[Vie Cache] Coupons cache refreshed successfully (%d items, TTL: %d seconds)',
                count($coupons),
                $cache_duration
            ));
        }

        return $coupons;
    }

    /**
     * -------------------------------------------------------------------------
     * DATA PARSING
     * -------------------------------------------------------------------------
     */

    /**
     * Parse dữ liệu từ Google Sheets thành structured array
     *
     * SHEET STRUCTURE:
     * Column A: code (Mã giảm giá)
     * Column B: discount_type (fixed | percent)
     * Column C: discount_value (Giá trị giảm)
     * Column D: min_order (Đơn tối thiểu)
     * Column E: max_usage (Số lần dùng tối đa)
     * Column F: used_count (Đã dùng bao nhiêu lần)
     *
     * @since   2.0.0
     * @param   array   $raw_data   Raw data từ Google Sheets API
     * @return  array               Structured coupons array, indexed by code
     */
    private function parse_sheet_data($raw_data)
    {
        $coupons = [];

        foreach ($raw_data as $index => $row) {
            // Skip empty rows
            if (empty($row) || !isset($row[0])) {
                continue;
            }

            // Sanitize coupon code
            $code = function_exists('vl_sanitize_coupon_code')
                ? vl_sanitize_coupon_code($row[0])
                : strtoupper(trim($row[0]));

            if (empty($code)) {
                continue;
            }

            $coupons[$code] = [
                'code'           => $code,
                'discount_type'  => isset($row[1]) ? trim($row[1]) : 'fixed', // fixed | percent
                'discount_value' => isset($row[2]) ? floatval($row[2]) : 0,
                'min_order'      => isset($row[3]) ? floatval($row[3]) : 0,
                'max_usage'      => isset($row[4]) ? intval($row[4]) : 0,
                'used_count'     => isset($row[5]) ? intval($row[5]) : 0,
                'row_index'      => $index + 2, // +2 vì Google Sheets bắt đầu từ 1, và có header row
                'raw_row'        => $row
            ];
        }

        return $coupons;
    }

    /**
     * -------------------------------------------------------------------------
     * COUPON QUERIES
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy thông tin một mã cụ thể
     *
     * @since   2.0.0
     * @param   string      $code   Mã coupon (không phân biệt hoa thường)
     * @return  array|false         Coupon data hoặc false nếu không tìm thấy
     */
    public function get_coupon($code)
    {
        $code = function_exists('vl_sanitize_coupon_code')
            ? vl_sanitize_coupon_code($code)
            : strtoupper(trim($code));

        $coupons = $this->get_coupons();

        if ($coupons === false) {
            return false;
        }

        return isset($coupons[$code]) ? $coupons[$code] : false;
    }

    /**
     * -------------------------------------------------------------------------
     * CACHE INVALIDATION
     * -------------------------------------------------------------------------
     */

    /**
     * Invalidate cache của một mã cụ thể
     *
     * Do cache toàn bộ danh sách, nên phải invalidate hết.
     * Lần get_coupons() tiếp theo sẽ refresh từ API.
     *
     * @since   2.0.0
     * @param   string  $code   Mã coupon (để logging)
     */
    public function invalidate_coupon($code)
    {
        delete_transient($this->cache_key);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf('[Vie Cache] Cache invalidated for coupon: %s', $code));
        }
    }

    /**
     * Clear toàn bộ cache
     *
     * Xóa cache và force refresh ở lần gọi tiếp theo.
     *
     * @since   2.0.0
     */
    public function clear_cache()
    {
        delete_transient($this->cache_key);

        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[Vie Cache] Coupon cache cleared');
        }
    }

    /**
     * -------------------------------------------------------------------------
     * MONITORING & STATS
     * -------------------------------------------------------------------------
     */

    /**
     * Get cache statistics
     *
     * Thông tin hữu ích cho debug và monitoring.
     *
     * @since   2.0.0
     * @return  array   Cache stats với các keys:
     *                  - has_cache: bool
     *                  - coupon_count: int
     *                  - expires_at: string
     *                  - ttl_seconds: int
     */
    public function get_cache_stats()
    {
        $cached = get_transient($this->cache_key);
        $timeout = get_option('_transient_timeout_' . $this->cache_key);

        return [
            'has_cache'     => ($cached !== false),
            'coupon_count'  => $cached ? count($cached) : 0,
            'expires_at'    => $timeout ? date('Y-m-d H:i:s', $timeout) : 'N/A',
            'ttl_seconds'   => $timeout ? max(0, $timeout - time()) : 0
        ];
    }
}

// Backward compatibility alias
if (!class_exists('VL_Cache_Manager')) {
    class_alias('Vie_Cache_Manager', 'VL_Cache_Manager');
}
