<?php
/**
 * ============================================================================
 * TEMPLATE: Booking Filters
 * ============================================================================
 * 
 * MÔ TẢ:
 * Filter form cho danh sách phòng (ngày, số người)
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

<div class="vie-booking-filters" data-hotel-id="<?php echo esc_attr($hotel_id); ?>">
    <div class="vie-filter-row">
        <!-- Ngày nhận phòng -->
        <div class="vie-filter-item">
            <label for="vie-filter-checkin"><?php esc_html_e('Ngày nhận phòng', 'viechild'); ?></label>
            <input type="text" 
                   id="vie-filter-checkin" 
                   class="vie-datepicker vie-filter-checkin" 
                   placeholder="<?php esc_attr_e('Chọn ngày', 'viechild'); ?>"
                   readonly>
        </div>
        
        <!-- Ngày trả phòng -->
        <div class="vie-filter-item">
            <label for="vie-filter-checkout"><?php esc_html_e('Ngày trả phòng', 'viechild'); ?></label>
            <input type="text" 
                   id="vie-filter-checkout" 
                   class="vie-datepicker vie-filter-checkout" 
                   placeholder="<?php esc_attr_e('Chọn ngày', 'viechild'); ?>"
                   readonly>
        </div>
        
        <!-- Số người lớn -->
        <div class="vie-filter-item">
            <label for="vie-filter-adults"><?php esc_html_e('Người lớn', 'viechild'); ?></label>
            <select id="vie-filter-adults" class="vie-filter-adults">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>>
                        <?php echo $i; ?> <?php esc_html_e('người', 'viechild'); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <!-- Số trẻ em -->
        <div class="vie-filter-item">
            <label for="vie-filter-children"><?php esc_html_e('Trẻ em', 'viechild'); ?></label>
            <select id="vie-filter-children" class="vie-filter-children">
                <?php for ($i = 0; $i <= 5; $i++): ?>
                    <option value="<?php echo $i; ?>">
                        <?php echo $i; ?> <?php esc_html_e('bé', 'viechild'); ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        
        <!-- Nút tìm kiếm -->
        <div class="vie-filter-action">
            <button type="button" class="vie-btn vie-btn-primary vie-filter-submit">
                <?php esc_html_e('Kiểm tra', 'viechild'); ?>
            </button>
        </div>
    </div>
    
    <!-- Tuổi trẻ em (ẩn mặc định) -->
    <div class="vie-children-ages" style="display: none;">
        <label><?php esc_html_e('Tuổi trẻ em', 'viechild'); ?></label>
        <div class="vie-ages-inputs"></div>
    </div>
</div>
