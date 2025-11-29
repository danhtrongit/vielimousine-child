<?php
/**
 * ============================================================================
 * TEMPLATE: Room Detail Modal
 * ============================================================================
 * 
 * MÔ TẢ:
 * Modal hiển thị chi tiết phòng (ảnh, tiện nghi, mô tả)
 * Nội dung được load động qua AJAX
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;
?>

<!-- Modal chi tiết phòng -->
<div id="vie-room-detail-modal" class="vie-modal" style="display: none;">
    <div class="vie-modal-overlay js-close-modal"></div>
    
    <div class="vie-modal-container vie-modal-lg">
        <button type="button" class="vie-modal-close js-close-modal" aria-label="<?php esc_attr_e('Đóng', 'viechild'); ?>">
            &times;
        </button>
        
        <div class="vie-modal-body">
            <!-- Gallery -->
            <div class="vie-detail-gallery">
                <div class="vie-gallery-swiper swiper">
                    <div class="swiper-wrapper" id="vie-detail-gallery-wrapper">
                        <!-- Ảnh sẽ được inject qua JS -->
                    </div>
                    <div class="swiper-pagination"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="vie-detail-content">
                <h2 class="vie-detail-title" id="vie-detail-title">
                    <!-- Tên phòng sẽ được inject -->
                </h2>
                
                <!-- Meta info -->
                <div class="vie-detail-meta" id="vie-detail-meta">
                    <!-- Max guests, area, etc -->
                </div>
                
                <!-- Tiện nghi -->
                <div class="vie-detail-section">
                    <h4><?php esc_html_e('Tiện nghi phòng', 'viechild'); ?></h4>
                    <div class="vie-amenities-list" id="vie-detail-amenities">
                        <!-- Amenities sẽ được inject -->
                    </div>
                </div>
                
                <!-- Mô tả -->
                <div class="vie-detail-section">
                    <h4><?php esc_html_e('Mô tả', 'viechild'); ?></h4>
                    <div class="vie-description-text" id="vie-detail-description">
                        <!-- Mô tả sẽ được inject -->
                    </div>
                </div>
                
                <!-- Footer: Giá & Đặt phòng -->
                <div class="vie-detail-footer">
                    <div class="vie-detail-price">
                        <span class="vie-price-label"><?php esc_html_e('Giá từ', 'viechild'); ?></span>
                        <span class="vie-price-value" id="vie-detail-price">--</span>
                    </div>
                    
                    <button type="button" 
                            class="vie-btn vie-btn-primary vie-btn--lg js-book-from-detail"
                            id="vie-detail-book-btn"
                            data-room-id="">
                        <?php esc_html_e('Đặt ngay', 'viechild'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
