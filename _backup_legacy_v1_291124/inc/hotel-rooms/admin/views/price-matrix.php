<?php
/**
 * Admin View: Price Matrix Grid (All-in-One)
 * 
 * Giao diện Ma trận Giá Tổng thể - Hiển thị và sửa giá của tất cả khách sạn 
 * trong một tháng trên một màn hình duy nhất
 * 
 * @package VieHotelRooms
 */
if (!defined('ABSPATH'))
    exit;

// Current month/year defaults
$current_month = isset($_GET['month']) ? absint($_GET['month']) : (int) date('n');
$current_year = isset($_GET['year']) ? absint($_GET['year']) : (int) date('Y');

// Validate month/year
if ($current_month < 1 || $current_month > 12)
    $current_month = (int) date('n');
if ($current_year < 2020 || $current_year > 2030)
    $current_year = (int) date('Y');

// Get all hotels for filter (optional)
$hotels = Vie_Hotel_Rooms_Helpers::get_hotels();
$selected_hotel = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;

// Month/Year options
$months = [
    1 => 'Tháng 1',
    2 => 'Tháng 2',
    3 => 'Tháng 3',
    4 => 'Tháng 4',
    5 => 'Tháng 5',
    6 => 'Tháng 6',
    7 => 'Tháng 7',
    8 => 'Tháng 8',
    9 => 'Tháng 9',
    10 => 'Tháng 10',
    11 => 'Tháng 11',
    12 => 'Tháng 12'
];

$years = range(date('Y') - 1, date('Y') + 2);
?>
<div class="wrap vie-matrix-wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-grid-view"></span>
        <?php _e('Cập nhật Hàng loạt', 'viechild'); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-calendar'); ?>" class="page-title-action">
        <?php _e('Xem Lịch', 'viechild'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- Filter Bar -->
    <div class="vie-matrix-toolbar">
        <form id="vie-matrix-filter" method="get" action="">
            <input type="hidden" name="page" value="vie-hotel-rooms-bulk">

            <div class="filter-group">
                <label for="matrix-month"><?php _e('Tháng', 'viechild'); ?></label>
                <select id="matrix-month" name="month">
                    <?php foreach ($months as $num => $label): ?>
                        <option value="<?php echo $num; ?>" <?php selected($num, $current_month); ?>><?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="matrix-year"><?php _e('Năm', 'viechild'); ?></label>
                <select id="matrix-year" name="year">
                    <?php foreach ($years as $year): ?>
                        <option value="<?php echo $year; ?>" <?php selected($year, $current_year); ?>><?php echo $year; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label for="matrix-hotel"><?php _e('Khách sạn', 'viechild'); ?></label>
                <select id="matrix-hotel" name="hotel_id">
                    <option value="0"><?php _e('-- Tất cả --', 'viechild'); ?></option>
                    <?php foreach ($hotels as $hotel): ?>
                        <option value="<?php echo esc_attr($hotel->ID); ?>" <?php selected($hotel->ID, $selected_hotel); ?>>
                            <?php echo esc_html($hotel->post_title); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="button button-primary" id="btn-load-matrix">
                <span class="dashicons dashicons-search"></span> <?php _e('Tải dữ liệu', 'viechild'); ?>
            </button>
        </form>

        <div class="toolbar-actions">
            <button type="button" class="button" id="btn-expand-all" title="<?php _e('Mở rộng tất cả', 'viechild'); ?>">
                <span class="dashicons dashicons-editor-expand"></span>
            </button>
            <button type="button" class="button" id="btn-collapse-all"
                title="<?php _e('Thu gọn tất cả', 'viechild'); ?>">
                <span class="dashicons dashicons-editor-contract"></span>
            </button>
            <span class="separator"></span>
            <button type="button" class="button button-primary" id="btn-save-matrix" disabled>
                <span class="dashicons dashicons-saved"></span> <?php _e('Lưu tất cả', 'viechild'); ?>
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
        <span class="legend-title"><?php _e('Chú thích:', 'viechild'); ?></span>
        <span class="legend-item"><span class="color-box combo"></span> Giá Combo</span>
        <span class="legend-item"><span class="color-box room"></span> Giá Room</span>
        <span class="legend-item"><span class="color-box stock"></span> Quỹ phòng</span>
        <span class="legend-item weekend"><span class="color-box"></span> Cuối tuần</span>
        <span class="legend-item today"><span class="color-box"></span> Hôm nay</span>
    </div>

    <!-- Matrix Container (New Single Table Layout) -->
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
            <h3><?php _e('Chọn tháng và bấm "Tải dữ liệu"', 'viechild'); ?></h3>
            <p><?php _e('Ma trận giá sẽ hiển thị tất cả khách sạn và phòng của bạn.', 'viechild'); ?></p>
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
                <label><?php _e('Giá Combo', 'viechild'); ?></label>
                <input type="number" id="popup-price-combo" min="0" step="1000" placeholder="0">
            </div>
            <div class="popup-field">
                <label><?php _e('Giá Room', 'viechild'); ?></label>
                <input type="number" id="popup-price-room" min="0" step="1000" placeholder="0">
            </div>
            <div class="popup-field">
                <label><?php _e('Quỹ phòng', 'viechild'); ?></label>
                <input type="number" id="popup-stock" min="0" placeholder="0">
            </div>
            <div class="popup-field">
                <label><?php _e('Trạng thái', 'viechild'); ?></label>
                <select id="popup-status">
                    <option value="available"><?php _e('Còn phòng', 'viechild'); ?></option>
                    <option value="limited"><?php _e('Còn ít', 'viechild'); ?></option>
                    <option value="stop_sell"><?php _e('Ngừng bán', 'viechild'); ?></option>
                </select>
            </div>
        </div>
        <div class="popup-footer">
            <button type="button" class="button" id="popup-cancel"><?php _e('Hủy', 'viechild'); ?></button>
            <button type="button" class="button button-primary"
                id="popup-apply"><?php _e('Áp dụng', 'viechild'); ?></button>
        </div>
    </div>

    <!-- Row Action Menu -->
    <div class="vie-row-actions-menu" id="row-actions-menu" style="display:none;">
        <button type="button" data-action="copy-first"><?php _e('Áp dụng ngày 1 cho cả tháng', 'viechild'); ?></button>
        <button type="button" data-action="copy-weekday"><?php _e('Áp dụng T2 cho T3-T5', 'viechild'); ?></button>
        <button type="button" data-action="copy-weekend"><?php _e('Áp dụng T6 cho T7-CN', 'viechild'); ?></button>
        <hr>
        <button type="button" data-action="clear-row"><?php _e('Xóa cả dòng', 'viechild'); ?></button>
    </div>

    <!-- Hidden Data -->
    <?php wp_nonce_field('vie_hotel_rooms_nonce', 'matrix_nonce'); ?>
    <input type="hidden" id="matrix-month-val" value="<?php echo $current_month; ?>">
    <input type="hidden" id="matrix-year-val" value="<?php echo $current_year; ?>">
    <input type="hidden" id="matrix-hotel-val" value="<?php echo $selected_hotel; ?>">
</div>