<?php
/**
 * ============================================================================
 * TEMPLATE: Room Card
 * ============================================================================
 * 
 * MÔ TẢ:
 * Hiển thị 1 card phòng trong grid danh sách phòng
 * 
 * BIẾN TRUYỀN VÀO:
 * @var object $room           Dữ liệu phòng từ database
 * @var int    $hotel_id       ID của khách sạn
 * @var array  $price_range    [min => x, max => y] của phòng
 * @var bool   $show_prices    Có hiển thị giá không
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

// Validate required variables
if (empty($room)) {
    return;
}

// Defaults
$hotel_id = $hotel_id ?? 0;
$price_range = $price_range ?? [];
$show_prices = $show_prices ?? true;

// Parse gallery images
$gallery_ids = !empty($room->gallery_ids) ? json_decode($room->gallery_ids, true) : [];
$has_gallery = is_array($gallery_ids) && count($gallery_ids) > 0;

// Format price
$min_price = $price_range['min'] ?? $room->base_price ?? 0;
$formatted_price = function_exists('vie_format_currency') ? vie_format_currency($min_price) : number_format($min_price, 0, ',', '.') . ' VNĐ';

// Room status
$is_active = ($room->status ?? 'active') === 'active';
$status_class = $is_active ? '' : 'vie-room-card--inactive';

// Amenities
$amenities = !empty($room->amenities) ? json_decode($room->amenities, true) : [];
$amenities_display = array_slice($amenities, 0, 4);
$amenities_more = count($amenities) - 4;
?>

<div class="vie-room-card <?php echo esc_attr($status_class); ?>" 
     data-room-id="<?php echo esc_attr($room->id); ?>"
     data-room-name="<?php echo esc_attr($room->name); ?>"
     data-hotel-id="<?php echo esc_attr($hotel_id); ?>">
    
    <!-- Ảnh phòng -->
    <div class="vie-room-image">
        <?php if ($has_gallery): ?>
            <div class="vie-card-swiper swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($gallery_ids as $image_id): 
                        $image_url = wp_get_attachment_image_url($image_id, 'medium_large');
                        if (!$image_url) continue;
                    ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url($image_url); ?>" 
                                 alt="<?php echo esc_attr($room->name); ?>"
                                 loading="lazy">
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        <?php else: ?>
            <div class="vie-no-image">
                <span class="dashicons dashicons-format-image"></span>
            </div>
        <?php endif; ?>
        
        <?php if (!$is_active): ?>
            <div class="vie-room-badge stop-sell">
                <?php esc_html_e('Tạm ngừng', 'viechild'); ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Nội dung -->
    <div class="vie-room-info">
        <h3 class="vie-room-name">
            <?php echo esc_html($room->name); ?>
        </h3>
        
        <!-- Thông tin phòng -->
        <div class="vie-room-meta">
            <?php if (!empty($room->max_adults)): ?>
                <span class="vie-meta-item">
                    <span class="dashicons dashicons-admin-users"></span>
                    <?php echo esc_html($room->max_adults); ?> người
                </span>
            <?php endif; ?>
            
            <?php if (!empty($room->area)): ?>
                <span class="vie-meta-item">
                    <span class="dashicons dashicons-admin-home"></span>
                    <?php echo esc_html($room->area); ?>m²
                </span>
            <?php endif; ?>
        </div>
        
        <!-- Mô tả ngắn -->
        <?php if (!empty($room->description)): ?>
            <p class="vie-room-desc">
                <?php echo esc_html(wp_trim_words($room->description, 20)); ?>
            </p>
        <?php endif; ?>
        
        <!-- Amenities -->
        <?php if (!empty($amenities_display)): ?>
            <div class="vie-room-amenities">
                <?php foreach ($amenities_display as $amenity): ?>
                    <span class="vie-amenity"><?php echo esc_html($amenity); ?></span>
                <?php endforeach; ?>
                <?php if ($amenities_more > 0): ?>
                    <span class="vie-amenity vie-more">+<?php echo $amenities_more; ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Footer: Giá & Actions -->
        <div class="vie-room-footer">
            <?php if ($show_prices): ?>
                <div class="vie-room-price">
                    <span class="vie-price-label"><?php esc_html_e('Giá từ', 'viechild'); ?></span>
                    <span class="vie-price-value"><?php echo esc_html($formatted_price); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="vie-room-actions">
                <button type="button" 
                        class="vie-btn vie-btn-outline js-open-room-detail"
                        data-room-id="<?php echo esc_attr($room->id); ?>">
                    <?php esc_html_e('Chi tiết', 'viechild'); ?>
                </button>
                
                <?php if ($is_active): ?>
                    <button type="button" 
                            class="vie-btn vie-btn-primary js-open-booking"
                            data-room-id="<?php echo esc_attr($room->id); ?>"
                            data-room-name="<?php echo esc_attr($room->name); ?>"
                            data-base-price="<?php echo esc_attr($room->base_price); ?>">
                        <?php esc_html_e('Đặt ngay', 'viechild'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
