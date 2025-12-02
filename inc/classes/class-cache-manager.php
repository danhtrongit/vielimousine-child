<?php
/**
 * Google Sheets Coupon System - Cache Manager
 * 
 * Class quản lý cache danh sách mã giảm giá từ Google Sheets
 * Sử dụng WordPress Transients API + WP Cron để tự động refresh
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

class VL_Cache_Manager
{

    /**
     * Google Sheets API instance
     * @var VL_Google_Sheets_API
     */
    private $sheets_api;

    /**
     * Cache key cho danh sách coupons
     * @var string
     */
    private $cache_key = 'vl_coupons_cache';

    /**
     * Singleton instance
     * @var VL_Cache_Manager
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private for singleton)
     */
    private function __construct()
    {
        $this->sheets_api = new VL_Google_Sheets_API();
        $this->setup_cron();
    }

    /**
     * Setup WP Cron để auto refresh cache
     */
    private function setup_cron()
    {
        // Register custom cron interval (5 phút)
        add_filter('cron_schedules', [$this, 'add_cron_interval']);

        // Schedule cron job nếu chưa có
        if (!wp_next_scheduled('vl_refresh_coupons_cache')) {
            wp_schedule_event(time(), 'every_5_minutes', 'vl_refresh_coupons_cache');
            VL_Logger::info('Cron job scheduled for cache refresh');
        }

        // Hook vào cron action
        add_action('vl_refresh_coupons_cache', [$this, 'refresh_coupons']);
    }

    /**
     * Add custom cron interval
     */
    public function add_cron_interval($schedules)
    {
        if (!isset($schedules['every_5_minutes'])) {
            $schedules['every_5_minutes'] = [
                'interval' => 5 * MINUTE_IN_SECONDS,
                'display' => __('Every 5 Minutes', 'vielimousine')
            ];
        }
        return $schedules;
    }

    /**
     * Lấy danh sách coupons (từ cache hoặc fresh read)
     * 
     * @param bool $force_refresh Force refresh từ Google Sheets
     * @return array|false Array of coupons hoặc false nếu lỗi
     */
    public function get_coupons($force_refresh = false)
    {
        if ($force_refresh) {
            return $this->refresh_coupons();
        }

        // Try cache first
        $cached = get_transient($this->cache_key);

        if ($cached !== false) {
            VL_Logger::debug('Coupons loaded from cache', [
                'count' => count($cached)
            ]);
            return $cached;
        }

        // Cache miss, load fresh
        VL_Logger::info('Cache miss, loading fresh coupons');
        return $this->refresh_coupons();
    }

    /**
     * Refresh cache từ Google Sheets
     * 
     * @return array|false Array of coupons hoặc false nếu lỗi
     */
    public function refresh_coupons()
    {
        VL_Logger::info('Refreshing coupons cache from Google Sheets');

        $raw_data = $this->sheets_api->read_range();

        if ($raw_data === false) {
            VL_Logger::error('Failed to refresh coupons: API read failed');

            // Fallback: trả về cached data cũ nếu còn
            $old_cache = get_transient($this->cache_key);
            if ($old_cache !== false) {
                VL_Logger::warning('Using stale cache data as fallback');
                return $old_cache;
            }

            return false;
        }

        // Parse raw data thành structured array
        $coupons = $this->parse_sheet_data($raw_data);

        // Lưu vào cache
        set_transient($this->cache_key, $coupons, VL_COUPON_CACHE_DURATION);

        VL_Logger::info('Coupons cache refreshed successfully', [
            'count' => count($coupons),
            'ttl' => VL_COUPON_CACHE_DURATION
        ]);

        return $coupons;
    }

    /**
     * Parse dữ liệu từ Google Sheets thành structured array
     * 
     * Sheet structure:
     * A: code | B: discount_type | C: discount_value | D: min_order | E: max_usage | F: used_count
     * 
     * @param array $raw_data Raw data từ Google Sheets
     * @return array Structured coupons array
     */
    private function parse_sheet_data($raw_data)
    {
        $coupons = [];

        foreach ($raw_data as $index => $row) {
            // Skip empty rows
            if (empty($row) || !isset($row[0])) {
                continue;
            }

            $code = vl_sanitize_coupon_code($row[0]);

            if (empty($code)) {
                continue;
            }

            $coupons[$code] = [
                'code' => $code,
                'discount_type' => isset($row[1]) ? trim($row[1]) : 'fixed', // fixed | percent
                'discount_value' => isset($row[2]) ? floatval($row[2]) : 0,
                'min_order' => isset($row[3]) ? floatval($row[3]) : 0,
                'max_usage' => isset($row[4]) ? intval($row[4]) : 0,
                'used_count' => isset($row[5]) ? intval($row[5]) : 0,
                'row_index' => $index + 2, // +2 vì Google Sheets bắt đầu từ 1, và có header row
                'raw_row' => $row
            ];
        }

        return $coupons;
    }

    /**
     * Lấy thông tin một mã cụ thể
     * 
     * @param string $code Mã coupon
     * @return array|false Coupon data hoặc false nếu không tìm thấy
     */
    public function get_coupon($code)
    {
        $code = vl_sanitize_coupon_code($code);
        $coupons = $this->get_coupons();

        if ($coupons === false) {
            return false;
        }

        return isset($coupons[$code]) ? $coupons[$code] : false;
    }

    /**
     * Invalidate cache của một mã cụ thể (force refresh ở lần sau)
     * 
     * @param string $code Mã coupon
     */
    public function invalidate_coupon($code)
    {
        // Vì cache toàn bộ danh sách, nên phải invalidate hết
        delete_transient($this->cache_key);
        VL_Logger::debug('Cache invalidated for coupon update', ['code' => $code]);
    }

    /**
     * Clear toàn bộ cache
     */
    public function clear_cache()
    {
        delete_transient($this->cache_key);
        VL_Logger::info('Coupon cache cleared');
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache stats
     */
    public function get_cache_stats()
    {
        $cached = get_transient($this->cache_key);
        $timeout = get_option('_transient_timeout_' . $this->cache_key);

        return [
            'has_cache' => ($cached !== false),
            'coupon_count' => $cached ? count($cached) : 0,
            'expires_at' => $timeout ? date('Y-m-d H:i:s', $timeout) : 'N/A',
            'ttl_seconds' => $timeout ? max(0, $timeout - time()) : 0
        ];
    }
}
