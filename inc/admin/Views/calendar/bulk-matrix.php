<?php
/**
 * ============================================================================
 * TEMPLATE: Admin Bulk Matrix - Cập nhật giá hàng loạt
 * ============================================================================
 * 
 * Giao diện Ma trận Giá Tổng thể - Hiển thị và sửa giá của tất cả khách sạn 
 * trong một tháng trên một màn hình duy nhất.
 * 
 * Layout giữ nguyên từ V1 (price-matrix.php)
 * 
 * Variables available:
 * @var array $hotels        Danh sách hotels
 * @var int   $current_month Tháng hiện tại
 * @var int   $current_year  Năm hiện tại
 * @var int   $selected_hotel Hotel ID được chọn
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Templates
 * @version     2.0.0
 * @since       2.0.0 (Migrated from V1)
 * ============================================================================
 */

defined('ABSPATH') || exit;

// Month labels
$months = array(
    1 => 'Tháng 1', 2 => 'Tháng 2', 3 => 'Tháng 3', 4 => 'Tháng 4',
    5 => 'Tháng 5', 6 => 'Tháng 6', 7 => 'Tháng 7', 8 => 'Tháng 8',
    9 => 'Tháng 9', 10 => 'Tháng 10', 11 => 'Tháng 11', 12 => 'Tháng 12'
);

$years = range(date('Y') - 1, date('Y') + 2);
?>

<div class="wrap vie-matrix-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-grid-view"></span>
        <?php esc_html_e('Cập nhật Hàng loạt', 'flavor'); ?>
    </h1>
    <a href="<?php echo esc_url(admin_url('admin.php?page=vie-hotel-calendar')); ?>" class="page-title-action">
        <?php esc_html_e('Xem Lịch', 'flavor'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- Filter Bar -->
    <div class="vie-matrix-toolbar">
        <form id="vie-matrix-filter" method="get" action="">
            <input type="hidden" name="page" value="vie-hotel-bulk-update">

            <div class="filter-group">
                <label for="matrix-month"><?php esc_html_e('Tháng', 'flavor'); ?></label>
                <select id="matrix-month" name="month">
                    <?php foreach ($months as $num => $label) : ?>
                        <option value="<?php echo $num; ?>" <?php selected($num, $current_month); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="matrix-year"><?php esc_html_e('Năm', 'flavor'); ?></label>
                <select id="matrix-year" name="year">
                    <?php foreach ($years as $year) : ?>
                        <option value="<?php echo $year; ?>" <?php selected($year, $current_year); ?>><?php echo $year; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="matrix-hotel"><?php esc_html_e('Khách sạn', 'flavor'); ?></label>
                <select id="matrix-hotel" name="hotel_id">
                    <option value="0"><?php esc_html_e('-- Tất cả --', 'flavor'); ?></option>
                    <?php foreach ($hotels as $hotel) : ?>
                        <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($hotel->ID, $selected_hotel); ?>>
                            <?php echo esc_html($hotel->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="button button-primary" id="btn-load-matrix">
                <span class="dashicons dashicons-search"></span> <?php esc_html_e('Tải dữ liệu', 'flavor'); ?>
            </button>
        </form>

        <div class="toolbar-actions">
            <button type="button" class="button" id="btn-expand-all" title="<?php esc_attr_e('Mở rộng tất cả', 'flavor'); ?>">
                <span class="dashicons dashicons-editor-expand"></span>
            </button>
            <button type="button" class="button" id="btn-collapse-all" title="<?php esc_attr_e('Thu gọn tất cả', 'flavor'); ?>">
                <span class="dashicons dashicons-editor-contract"></span>
            </button>
            <span class="separator"></span>
            <button type="button" class="button button-primary" id="btn-save-matrix" disabled>
                <span class="dashicons dashicons-saved"></span> <?php esc_html_e('Lưu tất cả', 'flavor'); ?>
            </button>
        </div>
    </div>

    <!-- Status Bar -->
    <div class="vie-matrix-status">
        <div class="status-left">
            <span id="matrix-stats">
                <span class="stat-item"><strong>0</strong> Khách sạn</span>
                <span class="stat-item"><strong>0</strong> Loại phòng</span>
                <span class="stat-item"><strong>0</strong> Ô dữ liệu</span>
            </span>
        </div>
        <div class="status-right">
            <span id="matrix-changes" class="hidden">
                <span class="dashicons dashicons-warning"></span>
                <span class="count">0</span> thay đổi chưa lưu
            </span>
            <span id="matrix-loading" class="hidden">
                <span class="spinner is-active"></span> Đang tải...
            </span>
            <span id="matrix-saved" class="hidden">
                <span class="dashicons dashicons-yes-alt"></span> Đã lưu!
            </span>
        </div>
    </div>

    <!-- Legend -->
    <div class="vie-matrix-legend">
        <span class="legend-title"><?php esc_html_e('Chú thích:', 'flavor'); ?></span>
        <span class="legend-item"><span class="color-box combo"></span> Giá Combo</span>
        <span class="legend-item"><span class="color-box room"></span> Giá Room</span>
        <span class="legend-item"><span class="color-box stock"></span> Quỹ phòng</span>
        <span class="legend-item weekend"><span class="color-box"></span> Cuối tuần</span>
        <span class="legend-item today"><span class="color-box"></span> Hôm nay</span>
    </div>

    <!-- Matrix Container -->
    <div class="vie-matrix-table-wrapper" id="vie-matrix-container">
        <div class="matrix-loading-overlay hidden" id="matrix-loading-overlay">
            <span class="spinner is-active"></span>
        </div>
        <table class="vie-matrix-table" id="vie-matrix-table">
            <thead id="matrix-header">
                <!-- Header will be rendered by JS -->
            </thead>
            <tbody id="matrix-body">
                <!-- Body will be rendered by JS -->
            </tbody>
        </table>

        <div class="matrix-empty-state" id="matrix-empty">
            <span class="dashicons dashicons-grid-view"></span>
            <h3><?php esc_html_e('Chọn tháng và bấm "Tải dữ liệu"', 'flavor'); ?></h3>
            <p><?php esc_html_e('Ma trận giá sẽ hiển thị tất cả khách sạn và phòng của bạn.', 'flavor'); ?></p>
        </div>
    </div>

    <!-- Quick Edit Popup -->
    <div class="vie-matrix-popup" id="matrix-popup" style="display:none;">
        <div class="popup-header">
            <span class="popup-title"></span>
            <button type="button" class="popup-close">&times;</button>
        </div>
        <div class="popup-body">
            <div class="popup-field">
                <label><?php esc_html_e('Giá Combo', 'flavor'); ?></label>
                <input type="number" id="popup-price-combo" min="0" step="1000" placeholder="0">
            </div>
            <div class="popup-field">
                <label><?php esc_html_e('Giá Room', 'flavor'); ?></label>
                <input type="number" id="popup-price-room" min="0" step="1000" placeholder="0">
            </div>
            <div class="popup-field">
                <label><?php esc_html_e('Quỹ phòng', 'flavor'); ?></label>
                <input type="number" id="popup-stock" min="0" placeholder="0">
            </div>
            <div class="popup-field">
                <label><?php esc_html_e('Trạng thái', 'flavor'); ?></label>
                <select id="popup-status">
                    <option value="available"><?php esc_html_e('Còn phòng', 'flavor'); ?></option>
                    <option value="limited"><?php esc_html_e('Còn ít', 'flavor'); ?></option>
                    <option value="stop_sell"><?php esc_html_e('Ngừng bán', 'flavor'); ?></option>
                </select>
            </div>
        </div>
        <div class="popup-footer">
            <button type="button" class="button" id="popup-cancel"><?php esc_html_e('Hủy', 'flavor'); ?></button>
            <button type="button" class="button button-primary" id="popup-apply"><?php esc_html_e('Áp dụng', 'flavor'); ?></button>
        </div>
    </div>

    <!-- Row Action Menu -->
    <div class="vie-row-actions-menu" id="row-actions-menu" style="display:none;">
        <button type="button" data-action="copy-first"><?php esc_html_e('Áp dụng ngày 1 cho cả tháng', 'flavor'); ?></button>
        <button type="button" data-action="copy-weekday"><?php esc_html_e('Áp dụng T2 cho T3-T5', 'flavor'); ?></button>
        <button type="button" data-action="copy-weekend"><?php esc_html_e('Áp dụng T6 cho T7-CN', 'flavor'); ?></button>
        <hr>
        <button type="button" data-action="clear-row"><?php esc_html_e('Xóa cả dòng', 'flavor'); ?></button>
    </div>

    <!-- Hidden Data -->
    <?php wp_nonce_field('vie_hotel_rooms_nonce', 'matrix_nonce'); ?>
    <input type="hidden" id="matrix-month-val" value="<?php echo esc_attr($current_month); ?>">
    <input type="hidden" id="matrix-year-val" value="<?php echo esc_attr($current_year); ?>">
    <input type="hidden" id="matrix-hotel-val" value="<?php echo esc_attr($selected_hotel); ?>">
</div>
