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

// Format price - Dual price support (Room Only + Combo)
$min_price = $price_range['min'] ?? 0;
$combo_price = $price_range['combo'] ?? null;
$formatted_price = function_exists('vie_format_currency') ? vie_format_currency($min_price) : number_format($min_price, 0, ',', '.') . ' VNĐ';
$formatted_combo_price = ($combo_price && $combo_price > 0)
    ? (function_exists('vie_format_currency') ? vie_format_currency($combo_price) : number_format($combo_price, 0, ',', '.') . ' VNĐ')
    : null;

// Room status
$is_active = ($room->status ?? 'active') === 'active';
$status_class = $is_active ? '' : 'vie-room-card--inactive';

// Stock/Availability - Tạo sự khan hiếm
$stock = $price_range['stock'] ?? $room->stock ?? 99;
$is_limited = $stock > 0 && $stock <= 5;
$is_sold_out = $stock <= 0;
$has_price = ($min_price > 0) || ($combo_price && $combo_price > 0);

// Amenities
$amenities = !empty($room->amenities) ? json_decode($room->amenities, true) : [];
$amenities_display = array_slice($amenities, 0, 4);
$amenities_more = count($amenities) - 4;
?>

<div class="vie-room-card <?php echo esc_attr($status_class); ?>" data-room-id="<?php echo esc_attr($room->id); ?>"
    data-room-name="<?php echo esc_attr($room->name); ?>" data-hotel-id="<?php echo esc_attr($hotel_id); ?>"
    data-base-price="<?php echo esc_attr($min_price); ?>"
    data-max-adults="<?php echo esc_attr($room->max_adults ?? 2); ?>"
    data-max-children="<?php echo esc_attr($room->max_children ?? 0); ?>"
    data-room-size="<?php echo esc_attr($room->room_size ?? ''); ?>"
    data-bed-type="<?php echo esc_attr($room->bed_type ?? ''); ?>"
    data-amenities="<?php echo esc_attr(json_encode($amenities)); ?>"
    data-combo-price="<?php echo esc_attr($combo_price ?? 0); ?>" data-room-price="<?php echo esc_attr($min_price); ?>">

    <!-- Ảnh phòng -->
    <div class="vie-room-image">
        <?php if ($has_gallery): ?>
            <div class="vie-card-swiper swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($gallery_ids as $image_id):
                        $image_url = wp_get_attachment_image_url($image_id, 'medium_large');
                        if (!$image_url)
                            continue;
                        ?>
                        <div class="swiper-slide">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($room->name); ?>"
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

        <?php if ($is_sold_out): ?>
            <div class="vie-room-badge sold-out">
                <?php esc_html_e('Hết phòng', 'viechild'); ?>
            </div>
        <?php elseif ($is_limited): ?>
            <div class="vie-room-badge limited">
                <?php printf(esc_html__('Chỉ còn %d phòng!', 'viechild'), $stock); ?>
            </div>
        <?php elseif (!$is_active): ?>
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

            <?php if (!empty($room->bed_type)): ?>
                <span class="vie-meta-item">
                    <span class="dashicons dashicons-bed"></span>
                    <?php echo esc_html($room->bed_type); ?>
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

        <!-- Footer: Giá & Actions (CRO: Dual Price Display) -->
        <div class="vie-room-footer">
            <?php if ($show_prices): ?>
                <div class="vie-price-comparison">
                    <!-- Room Only Price -->
                    <div class="vie-price-option vie-price-room">
                        <span
                            class="vie-price-type-label vie-price-label js-price-label"><?php esc_html_e('Room Only', 'viechild'); ?></span>
                        <div class="vie-price-amount">
                            <span class="vie-price-from"><?php esc_html_e('Từ', 'viechild'); ?></span>
                            <span
                                class="vie-price-value js-room-price-value"><?php echo esc_html($formatted_price); ?></span>
                        </div>
                        <span class="vie-price-unit"><?php esc_html_e('/đêm', 'viechild'); ?></span>
                    </div>

                    <?php if ($formatted_combo_price): ?>
                        <!-- Combo Price (Highlighted) -->
                        <div class="vie-price-option vie-price-combo">
                            <span class="vie-best-deal-badge"><?php esc_html_e('Khuyên dùng', 'viechild'); ?></span>
                            <span class="vie-price-type-label"><?php esc_html_e('Combo', 'viechild'); ?></span>
                            <div class="vie-price-amount">
                                <span class="vie-price-from"><?php esc_html_e('Từ', 'viechild'); ?></span>
                                <span
                                    class="vie-price-value js-combo-price-value"><?php echo esc_html($formatted_combo_price); ?></span>
                            </div>
                            <span class="vie-price-unit"><?php esc_html_e('/đêm', 'viechild'); ?></span>
                            <span class="vie-combo-includes"><?php esc_html_e('Bao gồm xe đưa đón', 'viechild'); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif (!$has_price): ?>
                <!-- No Price - Contact -->
                <div class="vie-price-contact">
                    <span
                        class="vie-price-value vie-text-contact"><?php esc_html_e('Liên hệ báo giá', 'viechild'); ?></span>
                </div>
            <?php endif; ?>

            <div class="vie-room-actions">
                <button type="button" class="vie-btn vie-btn-outline js-open-room-detail"
                    data-room-id="<?php echo esc_attr($room->id); ?>">
                    <?php esc_html_e('Chi tiết', 'viechild'); ?>
                </button>

                <?php if ($is_active): ?>
                    <?php
                    // Overbooking: Change button text and style when sold out
                    $btn_class = ($stock > 0) ? 'vie-btn-primary' : 'vie-btn-secondary';
                    $btn_text = ($stock > 0) ? __('Đặt ngay', 'viechild') : __('Gửi yêu cầu đặt phòng', 'viechild');
                    $is_overbooking = ($stock <= 0);
                    ?>
                    <button type="button" class="vie-btn <?php echo esc_attr($btn_class); ?> js-open-booking"
                        data-room-id="<?php echo esc_attr($room->id); ?>"
                        data-room-name="<?php echo esc_attr($room->name); ?>"
                        data-base-price="<?php echo esc_attr($min_price); ?>"
                        data-image-url="<?php echo esc_url(wp_get_attachment_image_url($gallery_ids[0] ?? 0, 'medium_large')); ?>"
                        data-surcharge-help="<?php echo esc_attr(function_exists('vie_get_surcharge_help_text') ? vie_get_surcharge_help_text($room->id) : ''); ?>"
                        <?php if ($is_overbooking): ?>data-overbooking="true" <?php endif; ?>>
                        <?php echo esc_html($btn_text); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>