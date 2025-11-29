<?php
/**
 * ============================================================================
 * TEMPLATE: Booking Popup
 * ============================================================================
 * 
 * MÔ TẢ:
 * Popup đặt phòng với 2 bước:
 * - Bước 1: Chọn ngày, số người, xem giá
 * - Bước 2: Điền thông tin khách hàng
 * 
 * BIẾN TRUYỀN VÀO:
 * @var int $hotel_id   ID của khách sạn
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

$hotel_id = $hotel_id ?? get_the_ID();
?>

<!-- Booking Popup -->
<div id="vie-booking-popup" class="vie-modal" style="display: none;" data-hotel-id="<?php echo esc_attr($hotel_id); ?>">
    <div class="vie-modal-overlay js-close-modal"></div>
    
    <div class="vie-modal-container vie-modal-md">
        <button type="button" class="vie-modal-close js-close-modal" aria-label="<?php esc_attr_e('Đóng', 'viechild'); ?>">
            &times;
        </button>
        
        <!-- Header -->
        <div class="vie-booking-header">
            <h2><?php esc_html_e('Đặt phòng', 'viechild'); ?></h2>
            <p class="vie-booking-room-name" id="vie-booking-room-name"><!-- Room name --></p>
        </div>
        
        <!-- Steps indicator -->
        <div class="vie-booking-steps">
            <div class="vie-step active" data-step="1">
                <span class="vie-step-num">1</span>
                <span class="vie-step-label"><?php esc_html_e('Chọn ngày', 'viechild'); ?></span>
            </div>
            <div class="vie-step" data-step="2">
                <span class="vie-step-num">2</span>
                <span class="vie-step-label"><?php esc_html_e('Thông tin', 'viechild'); ?></span>
            </div>
        </div>
        
        <form id="vie-booking-form" novalidate>
            <input type="hidden" name="room_id" id="vie-book-room-id" value="">
            <input type="hidden" name="hotel_id" value="<?php echo esc_attr($hotel_id); ?>">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('vie_booking_nonce'); ?>">
            
            <!-- Step 1: Chọn ngày & Xem giá -->
            <div class="vie-booking-step-content" id="vie-booking-step-1">
                
                <!-- Ngày nhận/trả phòng -->
                <div class="vie-form-row">
                    <div class="vie-form-group vie-form-half">
                        <label for="vie-book-checkin">
                            <?php esc_html_e('Ngày nhận phòng', 'viechild'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="check_in" 
                               id="vie-book-checkin" 
                               class="vie-datepicker vie-book-datepicker"
                               placeholder="<?php esc_attr_e('Chọn ngày', 'viechild'); ?>"
                               readonly
                               required>
                    </div>
                    
                    <div class="vie-form-group vie-form-half">
                        <label for="vie-book-checkout">
                            <?php esc_html_e('Ngày trả phòng', 'viechild'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="text" 
                               name="check_out" 
                               id="vie-book-checkout" 
                               class="vie-datepicker vie-book-datepicker"
                               placeholder="<?php esc_attr_e('Chọn ngày', 'viechild'); ?>"
                               readonly
                               required>
                    </div>
                </div>
                
                <!-- Số phòng, Người lớn, Trẻ em -->
                <div class="vie-form-row">
                    <div class="vie-form-group vie-form-third">
                        <label for="vie-book-rooms"><?php esc_html_e('Số phòng', 'viechild'); ?></label>
                        <select name="num_rooms" id="vie-book-rooms">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> <?php esc_html_e('phòng', 'viechild'); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="vie-form-group vie-form-third">
                        <label for="vie-book-adults"><?php esc_html_e('Người lớn', 'viechild'); ?></label>
                        <select name="num_adults" id="vie-book-adults">
                            <?php for ($i = 1; $i <= 10; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="vie-form-group vie-form-third">
                        <label for="vie-book-children"><?php esc_html_e('Trẻ em', 'viechild'); ?></label>
                        <select name="num_children" id="vie-book-children">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Tuổi trẻ em (động) -->
                <div class="vie-children-ages-booking" id="vie-book-children-ages" style="display: none;">
                    <label><?php esc_html_e('Tuổi trẻ em', 'viechild'); ?></label>
                    <div class="vie-ages-inputs"></div>
                </div>
                
                <!-- Bảng giá -->
                <div class="vie-price-summary" id="vie-price-summary">
                    <div class="vie-summary-placeholder">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <p><?php esc_html_e('Chọn ngày để xem giá', 'viechild'); ?></p>
                    </div>
                    
                    <!-- Nội dung giá sẽ được inject qua JS -->
                    <div class="vie-summary-content" style="display: none;"></div>
                </div>
                
            </div>
            
            <!-- Step 2: Thông tin khách hàng -->
            <div class="vie-booking-step-content" id="vie-booking-step-2" style="display: none;">
                
                <!-- Thông tin booking tóm tắt -->
                <div class="vie-booking-summary" id="vie-booking-summary">
                    <h4><?php esc_html_e('Thông tin đặt phòng', 'viechild'); ?></h4>
                    <div class="vie-summary-items"></div>
                </div>
                
                <!-- Form thông tin -->
                <div class="vie-form-group">
                    <label for="vie-book-name">
                        <?php esc_html_e('Họ và tên', 'viechild'); ?>
                        <span class="required">*</span>
                    </label>
                    <input type="text" 
                           name="customer_name" 
                           id="vie-book-name"
                           placeholder="<?php esc_attr_e('Nhập họ tên của bạn', 'viechild'); ?>"
                           required>
                </div>
                
                <div class="vie-form-row">
                    <div class="vie-form-group vie-form-half">
                        <label for="vie-book-phone">
                            <?php esc_html_e('Số điện thoại', 'viechild'); ?>
                            <span class="required">*</span>
                        </label>
                        <input type="tel" 
                               name="customer_phone" 
                               id="vie-book-phone"
                               placeholder="<?php esc_attr_e('0901 234 567', 'viechild'); ?>"
                               required>
                    </div>
                    
                    <div class="vie-form-group vie-form-half">
                        <label for="vie-book-email"><?php esc_html_e('Email', 'viechild'); ?></label>
                        <input type="email" 
                               name="customer_email" 
                               id="vie-book-email"
                               placeholder="<?php esc_attr_e('email@example.com', 'viechild'); ?>">
                    </div>
                </div>
                
                <div class="vie-form-group">
                    <label for="vie-book-note"><?php esc_html_e('Ghi chú', 'viechild'); ?></label>
                    <textarea name="customer_note" 
                              id="vie-book-note" 
                              rows="3"
                              placeholder="<?php esc_attr_e('Yêu cầu đặc biệt...', 'viechild'); ?>"></textarea>
                </div>
                
            </div>
            
            <!-- Footer buttons -->
            <div class="vie-booking-footer">
                <button type="button" 
                        class="vie-btn vie-btn-outline js-booking-back" 
                        style="display: none;">
                    <?php esc_html_e('Quay lại', 'viechild'); ?>
                </button>
                
                <button type="button" 
                        class="vie-btn vie-btn-primary js-booking-next"
                        disabled>
                    <?php esc_html_e('Tiếp tục', 'viechild'); ?>
                </button>
                
                <button type="submit" 
                        class="vie-btn vie-btn-primary js-booking-submit" 
                        style="display: none;">
                    <?php esc_html_e('Đặt phòng', 'viechild'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
