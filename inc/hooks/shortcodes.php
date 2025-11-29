<?php
/**
 * ============================================================================
 * TÊN FILE: shortcodes.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Đăng ký tất cả shortcodes cho theme
 * 
 * SHORTCODES:
 * - [hotel_room_list]: Hiển thị danh sách phòng của khách sạn
 * - [vie_booking_form]: Form đặt phòng độc lập
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Hooks
 * @version     2.0.0
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * Shortcode hiển thị danh sách phòng khách sạn
 * 
 * @since   2.0.0
 * 
 * @param   array   $atts   Attributes từ shortcode
 * 
 * @return  string  HTML output
 * 
 * @example
 * [hotel_room_list]
 * [hotel_room_list hotel_id="123" columns="3"]
 * [hotel_room_list show_filters="yes" show_prices="yes"]
 */
function vie_shortcode_hotel_room_list($atts) {
    // Parse attributes với defaults
    $atts = shortcode_atts([
        'hotel_id'      => get_the_ID(),
        'columns'       => 2,
        'show_filters'  => 'yes',
        'show_prices'   => 'yes',
        'status'        => 'active',
    ], $atts, 'hotel_room_list');
    
    // Sanitize
    $hotel_id = absint($atts['hotel_id']);
    $columns = min(4, max(1, absint($atts['columns'])));
    $show_filters = $atts['show_filters'] === 'yes';
    $show_prices = $atts['show_prices'] === 'yes';
    $status = sanitize_text_field($atts['status']);
    
    // Validate hotel
    if (!$hotel_id || get_post_type($hotel_id) !== 'hotel') {
        if (VIE_DEBUG) {
            return '<p class="vie-error">Hotel không hợp lệ</p>';
        }
        return '';
    }
    
    // Lấy danh sách phòng
    $rooms = vie_get_rooms_by_hotel($hotel_id, $status);
    
    if (empty($rooms)) {
        return '<div class="vie-no-rooms"><p>Không có phòng nào</p></div>';
    }
    
    // Lấy giá min/max cho mỗi phòng
    $today = date('Y-m-d');
    $room_prices = [];
    foreach ($rooms as $room) {
        $room_prices[$room->id] = [
            'min' => $room->base_price,
            'max' => $room->base_price,
        ];
    }
    
    // Start output buffering
    ob_start();
    
    // Render filters
    if ($show_filters) {
        vie_get_template('frontend/booking-filters', [
            'hotel_id' => $hotel_id,
        ]);
    }
    
    // Render room grid
    ?>
    <div class="vie-rooms-grid columns-<?php echo esc_attr($columns); ?>">
        <?php foreach ($rooms as $room): ?>
            <?php 
            vie_get_template('frontend/room-card', [
                'room' => $room,
                'hotel_id' => $hotel_id,
                'price_range' => $room_prices[$room->id] ?? [],
                'show_prices' => $show_prices,
            ]); 
            ?>
        <?php endforeach; ?>
    </div>
    
    <?php
    // Render modals (chỉ 1 lần)
    vie_get_template('frontend/room-detail-modal');
    vie_get_template('frontend/booking-popup', [
        'hotel_id' => $hotel_id,
    ]);
    
    return ob_get_clean();
}
add_shortcode('hotel_room_list', 'vie_shortcode_hotel_room_list');

/**
 * Shortcode form đặt phòng độc lập
 * 
 * @since   2.0.0
 * 
 * @param   array   $atts   Attributes
 * 
 * @return  string  HTML output
 * 
 * @example
 * [vie_booking_form hotel_id="123"]
 */
function vie_shortcode_booking_form($atts) {
    $atts = shortcode_atts([
        'hotel_id' => 0,
        'room_id'  => 0,
    ], $atts, 'vie_booking_form');
    
    $hotel_id = absint($atts['hotel_id']);
    $room_id = absint($atts['room_id']);
    
    if (!$hotel_id && !$room_id) {
        return '';
    }
    
    ob_start();
    
    vie_get_template('frontend/standalone-booking-form', [
        'hotel_id' => $hotel_id,
        'room_id' => $room_id,
    ]);
    
    return ob_get_clean();
}
add_shortcode('vie_booking_form', 'vie_shortcode_booking_form');
