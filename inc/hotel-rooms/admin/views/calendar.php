<?php
/**
 * Admin View: Calendar - Sử dụng WP native classes
 * @package VieHotelRooms
 */
if (!defined('ABSPATH')) exit;
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Lịch & Giá Phòng', 'viechild'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=vie-hotel-rooms-bulk'); ?>" class="page-title-action"><?php _e('Bulk Update', 'viechild'); ?></a>
    <hr class="wp-header-end">
    
    <!-- Toolbar -->
    <div class="tablenav top vie-cal-toolbar">
        <div class="selectors">
            <label for="cal-hotel-select"><?php _e('Khách sạn:', 'viechild'); ?></label>
            <select id="cal-hotel-select">
                <option value=""><?php _e('-- Chọn --', 'viechild'); ?></option>
                <?php foreach ($hotels as $h) : ?>
                <option value="<?php echo esc_attr($h->ID); ?>" <?php selected($hotel_id, $h->ID); ?>><?php echo esc_html($h->post_title); ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="cal-room-select"><?php _e('Phòng:', 'viechild'); ?></label>
            <select id="cal-room-select" <?php echo empty($rooms) ? 'disabled' : ''; ?>>
                <option value=""><?php _e('-- Chọn --', 'viechild'); ?></option>
                <?php foreach ($rooms as $r) : ?>
                <option value="<?php echo esc_attr($r->id); ?>" <?php selected($room_id, $r->id); ?>><?php echo esc_html($r->name); ?></option>
                <?php endforeach; ?>
            </select>
            
            <?php if ($room) : ?>
            <span class="description" style="margin-left:10px"><?php printf('%d phòng | Giá gốc: %s', $room->total_rooms, Vie_Hotel_Rooms_Helpers::format_currency($room->base_price)); ?></span>
            <?php endif; ?>
        </div>
        <div class="vie-cal-legend">
            <span class="available"><?php _e('Còn', 'viechild'); ?></span>
            <span class="limited"><?php _e('Sắp hết', 'viechild'); ?></span>
            <span class="sold-out"><?php _e('Hết', 'viechild'); ?></span>
            <span class="stop-sell"><?php _e('Ngừng', 'viechild'); ?></span>
        </div>
    </div>
    
    <!-- Calendar -->
    <div class="postbox">
        <div class="inside" style="padding:15px">
            <?php if ($room_id) : ?>
            <div id="vie-pricing-calendar"></div>
            <?php else : ?>
            <div class="vie-empty">
                <span class="dashicons dashicons-calendar-alt"></span>
                <h3><?php _e('Chọn loại phòng để xem lịch giá', 'viechild'); ?></h3>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal -->
<div id="vie-date-modal" class="vie-modal" style="display:none">
    <div class="vie-modal-box">
        <div class="header">
            <h3><?php _e('Cập nhật giá', 'viechild'); ?> <span id="modal-date-display"></span></h3>
            <button type="button" class="close">&times;</button>
        </div>
        <div class="body">
            <form id="vie-date-pricing-form">
                <input type="hidden" name="room_id" id="modal-room-id" value="<?php echo esc_attr($room_id); ?>">
                <input type="hidden" name="date" id="modal-date">
                <?php wp_nonce_field('vie_hotel_rooms_nonce', 'modal_nonce'); ?>
                
                <div class="row">
                    <div class="col">
                        <label for="modal-price-room"><?php _e('Giá Room', 'viechild'); ?></label>
                        <input type="number" id="modal-price-room" name="price_room" min="0" step="10000" placeholder="VNĐ">
                    </div>
                    <div class="col">
                        <label for="modal-price-combo"><?php _e('Giá Combo', 'viechild'); ?></label>
                        <input type="number" id="modal-price-combo" name="price_combo" min="0" step="10000" placeholder="VNĐ">
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <label for="modal-stock"><?php _e('Phòng trống', 'viechild'); ?></label>
                        <input type="number" id="modal-stock" name="stock" min="0" value="0">
                    </div>
                    <div class="col">
                        <label for="modal-status"><?php _e('Trạng thái', 'viechild'); ?></label>
                        <select id="modal-status" name="status">
                            <option value="available"><?php _e('Còn phòng', 'viechild'); ?></option>
                            <option value="limited"><?php _e('Sắp hết', 'viechild'); ?></option>
                            <option value="sold_out"><?php _e('Hết phòng', 'viechild'); ?></option>
                            <option value="stop_sell"><?php _e('Ngừng bán', 'viechild'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <label for="modal-notes"><?php _e('Ghi chú', 'viechild'); ?></label>
                        <input type="text" id="modal-notes" name="notes" placeholder="VD: Ngày lễ">
                    </div>
                </div>
            </form>
        </div>
        <div class="footer">
            <button type="button" class="button" id="vie-modal-cancel"><?php _e('Hủy', 'viechild'); ?></button>
            <button type="button" class="button button-primary" id="vie-modal-save"><?php _e('Lưu', 'viechild'); ?></button>
        </div>
    </div>
</div>

<script>var vieCalendarRoomId = <?php echo $room_id ? $room_id : 'null'; ?>;</script>
