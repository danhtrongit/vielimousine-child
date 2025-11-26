<?php
/**
 * Admin View: Bulk Update - Sử dụng WP postbox
 * @package VieHotelRooms
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Cập nhật Giá Hàng loạt', 'viechild'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-calendar'); ?>" class="page-title-action"><?php _e('Xem Lịch', 'viechild'); ?></a>
    <hr class="wp-header-end">
    
    <div class="notice notice-info inline" style="margin:15px 0">
        <p><?php _e('Cập nhật giá theo khoảng thời gian và ngày trong tuần. VD: Set giá T6, T7 trong tháng 12.', 'viechild'); ?></p>
    </div>
    
    <div class="vie-bulk-container">
        <form id="vie-bulk-update-form">
            <?php wp_nonce_field('vie_hotel_rooms_nonce', 'nonce'); ?>
            
            <!-- Step 1 -->
            <div class="vie-bulk-step">
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><span class="step-num">1</span> <?php _e('Chọn phòng', 'viechild'); ?></h2></div>
                    <div class="inside">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label for="bulk-hotel-select"><?php _e('Khách sạn', 'viechild'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="bulk-hotel-select" name="hotel_id" required>
                                        <option value=""><?php _e('-- Chọn --', 'viechild'); ?></option>
                                        <?php foreach ($hotels as $hotel) : ?>
                                        <option value="<?php echo esc_attr($hotel->ID); ?>"><?php echo esc_html($hotel->post_title); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="bulk-room-select"><?php _e('Loại phòng', 'viechild'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="bulk-room-select" name="room_id" required disabled>
                                        <option value=""><?php _e('-- Chọn --', 'viechild'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                        <div id="bulk-room-info" class="vie-info-grid" style="display:none">
                            <div class="item"><span class="label"><?php _e('Tổng phòng', 'viechild'); ?></span><span class="value" id="info-total-rooms">-</span></div>
                            <div class="item"><span class="label"><?php _e('Giá gốc', 'viechild'); ?></span><span class="value" id="info-base-price">-</span></div>
                            <div class="item"><span class="label"><?php _e('Sức chứa', 'viechild'); ?></span><span class="value" id="info-occupancy">-</span></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Step 2 -->
            <div class="vie-bulk-step">
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><span class="step-num">2</span> <?php _e('Khoảng thời gian', 'viechild'); ?></h2></div>
                    <div class="inside">
                        <table class="form-table" role="presentation">
                            <tr>
                                <th><label><?php _e('Từ ngày', 'viechild'); ?> <span class="required">*</span></label></th>
                                <td><input type="text" id="bulk-start-date" name="start_date" class="vie-datepicker regular-text" required></td>
                            </tr>
                            <tr>
                                <th><label><?php _e('Đến ngày', 'viechild'); ?> <span class="required">*</span></label></th>
                                <td><input type="text" id="bulk-end-date" name="end_date" class="vie-datepicker regular-text" required></td>
                            </tr>
                        </table>
                        <div class="vie-presets">
                            <span class="label"><?php _e('Nhanh:', 'viechild'); ?></span>
                            <button type="button" class="button button-small vie-date-preset" data-preset="this-month"><?php _e('Tháng này', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-date-preset" data-preset="next-month"><?php _e('Tháng sau', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-date-preset" data-preset="next-3-months"><?php _e('3 tháng tới', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-date-preset" data-preset="year-2025"><?php _e('2025', 'viechild'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Cấu hình theo ngày trong tuần -->
            <div class="vie-bulk-step">
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><span class="step-num">3</span> <?php _e('Cấu hình theo Thứ', 'viechild'); ?></h2></div>
                    <div class="inside">
                        <p class="description"><?php _e('Nhập giá trị cho từng ngày trong tuần. Bỏ tick "Áp dụng" để bỏ qua ngày đó.', 'viechild'); ?></p>
                        
                        <div class="vie-daily-presets">
                            <span class="label"><?php _e('Áp dụng nhanh:', 'viechild'); ?></span>
                            <button type="button" class="button button-small vie-preset-check-all"><?php _e('Chọn tất cả', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-preset-uncheck-all"><?php _e('Bỏ chọn tất cả', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-preset-weekday"><?php _e('T2-T5', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-preset-weekend"><?php _e('T6-CN', 'viechild'); ?></button>
                        </div>
                        
                        <table class="vie-daily-rules-table widefat striped">
                            <thead>
                                <tr>
                                    <th class="col-apply"><?php _e('Áp dụng', 'viechild'); ?></th>
                                    <th class="col-day"><?php _e('Ngày', 'viechild'); ?></th>
                                    <th class="col-price"><?php _e('Giá Room (VNĐ)', 'viechild'); ?></th>
                                    <th class="col-price"><?php _e('Giá Combo (VNĐ)', 'viechild'); ?></th>
                                    <th class="col-stock"><?php _e('Số lượng', 'viechild'); ?></th>
                                    <th class="col-status"><?php _e('Trạng thái', 'viechild'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                // date('N') returns: 1=Monday, 7=Sunday
                                $days_config = [
                                    1 => 'Thứ 2',
                                    2 => 'Thứ 3', 
                                    3 => 'Thứ 4',
                                    4 => 'Thứ 5',
                                    5 => 'Thứ 6',
                                    6 => 'Thứ 7',
                                    7 => 'Chủ nhật'
                                ];
                                foreach ($days_config as $day_num => $day_label) : 
                                    $is_weekend = in_array($day_num, [5, 6, 7]);
                                ?>
                                <tr class="vie-day-row <?php echo $is_weekend ? 'weekend' : 'weekday'; ?>" data-day="<?php echo $day_num; ?>">
                                    <td class="col-apply">
                                        <label class="vie-checkbox-wrap">
                                            <input type="checkbox" name="daily_rules[<?php echo $day_num; ?>][enabled]" value="1" checked class="day-enabled-check">
                                            <span class="checkmark"></span>
                                        </label>
                                    </td>
                                    <td class="col-day">
                                        <strong><?php echo $day_label; ?></strong>
                                        <?php if ($is_weekend) : ?>
                                        <span class="vie-badge weekend"><?php _e('Cuối tuần', 'viechild'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="col-price">
                                        <input type="number" name="daily_rules[<?php echo $day_num; ?>][price_room]" 
                                               min="0" step="10000" class="day-price-room" placeholder="<?php _e('Để trống = giữ nguyên', 'viechild'); ?>">
                                    </td>
                                    <td class="col-price">
                                        <input type="number" name="daily_rules[<?php echo $day_num; ?>][price_combo]" 
                                               min="0" step="10000" class="day-price-combo" placeholder="<?php _e('Để trống = giữ nguyên', 'viechild'); ?>">
                                    </td>
                                    <td class="col-stock">
                                        <input type="number" name="daily_rules[<?php echo $day_num; ?>][stock]" 
                                               min="0" class="day-stock small-text" placeholder="--">
                                    </td>
                                    <td class="col-status">
                                        <select name="daily_rules[<?php echo $day_num; ?>][status]" class="day-status">
                                            <option value=""><?php _e('-- Không đổi --', 'viechild'); ?></option>
                                            <option value="available"><?php _e('Còn phòng', 'viechild'); ?></option>
                                            <option value="stop_sell"><?php _e('Ngừng bán', 'viechild'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div class="vie-copy-values">
                            <span class="label"><?php _e('Sao chép giá trị:', 'viechild'); ?></span>
                            <button type="button" class="button button-small vie-copy-mon-to-weekday"><?php _e('T2 → T3,T4,T5', 'viechild'); ?></button>
                            <button type="button" class="button button-small vie-copy-fri-to-weekend"><?php _e('T6 → T7,CN', 'viechild'); ?></button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Step 4: Xác nhận & Lưu -->
            <div class="vie-bulk-step">
                <div class="postbox">
                    <div class="postbox-header"><h2 class="hndle"><span class="step-num">4</span> <?php _e('Xác nhận & Lưu', 'viechild'); ?></h2></div>
                    <div class="inside">
                        <div id="bulk-preview-summary" class="vie-preview">
                            <p class="placeholder"><?php _e('Điền đủ thông tin để xem tóm tắt', 'viechild'); ?></p>
                        </div>
                        <p>
                            <button type="submit" class="button button-primary button-large" id="vie-bulk-submit" disabled><?php _e('Cập nhật Hàng loạt', 'viechild'); ?></button>
                            <span id="bulk-submit-status" class="vie-msg"></span>
                        </p>
                    </div>
                </div>
            </div>
        </form>
        
        <!-- Log -->
        <div id="vie-bulk-log" class="vie-log postbox" style="display:none">
            <div class="postbox-header"><h2 class="hndle"><?php _e('Kết quả', 'viechild'); ?></h2></div>
            <div class="inside"><pre id="bulk-log-content"></pre></div>
        </div>
    </div>
</div>
