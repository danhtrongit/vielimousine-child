<?php
/**
 * Google Sheets Coupon System - Utility Logger
 * 
 * @package VielimousineChild
 */

defined('ABSPATH') || exit;

/**
 * Simple file-based logger cho Coupon System
 */
class VL_Logger
{

    /**
     * Log level constants
     */
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';

    /**
     * Log một message vào file
     * 
     * @param string $message Message cần log
     * @param string $level Log level (ERROR, WARNING, INFO, DEBUG)
     * @param array $context Additional context data
     */
    public static function log($message, $level = self::LEVEL_INFO, $context = [])
    {
        if (!VL_COUPON_ENABLE_LOGGING) {
            return;
        }

        $log_file = VL_COUPON_LOG_FILE;

        // Kiểm tra kích thước file log, rotate nếu quá lớn
        if (file_exists($log_file) && filesize($log_file) > VL_COUPON_MAX_LOG_SIZE) {
            self::rotate_log($log_file);
        }

        // Format log entry
        $timestamp = current_time('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            $level,
            $message,
            $context_str
        );

        // Write to file
        error_log($log_entry, 3, $log_file);
    }

    /**
     * Convenience method: Log error
     */
    public static function error($message, $context = [])
    {
        self::log($message, self::LEVEL_ERROR, $context);
    }

    /**
     * Convenience method: Log warning
     */
    public static function warning($message, $context = [])
    {
        self::log($message, self::LEVEL_WARNING, $context);
    }

    /**
     * Convenience method: Log info
     */
    public static function info($message, $context = [])
    {
        self::log($message, self::LEVEL_INFO, $context);
    }

    /**
     * Convenience method: Log debug
     */
    public static function debug($message, $context = [])
    {
        self::log($message, self::LEVEL_DEBUG, $context);
    }

    /**
     * Rotate log file khi quá lớn
     */
    private static function rotate_log($log_file)
    {
        $backup_file = $log_file . '.' . time() . '.bak';
        rename($log_file, $backup_file);

        // Giữ tối đa 5 backup files
        $backup_pattern = dirname($log_file) . '/*.log.*.bak';
        $backups = glob($backup_pattern);

        if (count($backups) > 5) {
            // Sort by time, xóa cái cũ nhất
            usort($backups, function ($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $to_delete = array_slice($backups, 0, count($backups) - 5);
            foreach ($to_delete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Lấy nội dung log gần nhất
     * 
     * @param int $lines Số dòng cần lấy
     * @return array Array of log lines
     */
    public static function get_recent_logs($lines = 50)
    {
        $log_file = VL_COUPON_LOG_FILE;

        if (!file_exists($log_file)) {
            return [];
        }

        $file = new SplFileObject($log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key() + 1;

        $start_line = max(0, $total_lines - $lines);
        $logs = [];

        $file->seek($start_line);
        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $logs[] = $line;
            }
            $file->next();
        }

        return $logs;
    }

    /**
     * Clear toàn bộ log
     */
    public static function clear_logs()
    {
        $log_file = VL_COUPON_LOG_FILE;
        if (file_exists($log_file)) {
            file_put_contents($log_file, '');
            return true;
        }
        return false;
    }
}
