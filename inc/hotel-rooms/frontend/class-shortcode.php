<?php
/**
 * Frontend Shortcode: [hotel_room_list]
 * 
 * Hiển thị danh sách phòng trên single hotel post
 * Bao gồm: Filters, Room Grid, Detail Modal, Booking Popup
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Shortcode
{

    /**
     * Constructor
     */
    public function __construct()
    {
        add_shortcode('hotel_room_list', array($this, 'render_room_list'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'), 99);
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        // Only load on hotel single posts
        if (!is_singular('hotel')) {
            return;
        }

        $version = VIE_HOTEL_ROOMS_VERSION;
        $base_url = VIE_HOTEL_ROOMS_URL . 'assets/';

        // CSS
        wp_enqueue_style(
            'vie-hotel-booking',
            $base_url . 'css/frontend.css',
            array(),
            $version
        );

        // jQuery UI Datepicker CSS
        wp_enqueue_style(
            'jquery-ui-datepicker-style',
            'https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css'
        );

        // Swiper (load riêng vì theme dùng module, không có global Swiper)
        wp_enqueue_script(
            'vie-swiper-global',
            'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
            array(),
            '11.0.0',
            true
        );

        // Main JS - sau jquery-ui-js (có datepicker) và swiper
        wp_enqueue_script(
            'vie-hotel-booking',
            $base_url . 'js/frontend.js',
            array('jquery', 'jquery-ui-js', 'vie-swiper-global'),
            $version,
            true
        );

        // Get transport config for this hotel
        $transport_config = Vie_Hotel_Transport_Metabox::get_transport_config(get_the_ID());

        // Localize script
        wp_localize_script('vie-hotel-booking', 'vieBooking', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vie_booking_nonce'),
            'hotelId' => get_the_ID(),
            'homeUrl' => home_url(),
            'checkoutUrl' => home_url('/checkout/'),
            'currency' => 'VNĐ',
            'dateFormat' => 'dd/mm/yy',
            'minDate' => 0,
            'transport' => $transport_config,
            'i18n' => array(
                'selectDates' => __('Vui lòng chọn ngày', 'flavor'),
                'calculating' => __('Đang tính giá...', 'flavor'),
                'roomUnavailable' => __('Phòng không khả dụng', 'flavor'),
                'soldOut' => __('Hết phòng', 'flavor'),
                'stopSell' => __('Ngừng bán', 'flavor'),
                'book' => __('Đặt ngay', 'flavor'),
                'viewDetail' => __('Xem chi tiết', 'flavor'),
                'close' => __('Đóng', 'flavor'),
                'next' => __('Tiếp tục', 'flavor'),
                'back' => __('Quay lại', 'flavor'),
                'confirm' => __('Xác nhận đặt phòng', 'flavor'),
                'success' => __('Đặt phòng thành công!', 'flavor'),
                'error' => __('Có lỗi xảy ra', 'flavor'),
                'required' => __('Vui lòng điền đầy đủ thông tin', 'flavor'),
                'requiredTransport' => __('Vui lòng chọn giờ đi và giờ về', 'flavor'),
                'nights' => __('đêm', 'flavor'),
                'adults' => __('người lớn', 'flavor'),
                'children' => __('trẻ em', 'flavor'),
                'rooms' => __('phòng', 'flavor'),
                'childAge' => __('Tuổi bé', 'flavor'),
                'priceFrom' => __('Giá từ', 'flavor'),
                'perNight' => __('/đêm', 'flavor'),
            )
        ));
    }

    /**
     * Render room list shortcode
     */
    public function render_room_list($atts)
    {
        $atts = shortcode_atts(array(
            'hotel_id' => 0,
            'view' => 'grid', // grid or list
            'columns' => 2,
            'show_filters' => 'yes',
        ), $atts);

        // Get hotel ID
        $hotel_id = $atts['hotel_id'] ? absint($atts['hotel_id']) : get_the_ID();

        // Verify hotel post type
        if (get_post_type($hotel_id) !== 'hotel') {
            return '<p class="vie-error">' . __('Shortcode chỉ hoạt động trên trang hotel', 'flavor') . '</p>';
        }

        // Get rooms for this hotel
        $rooms = $this->get_hotel_rooms($hotel_id);

        if (empty($rooms)) {
            return '<div class="vie-no-rooms">' . __('Chưa có loại phòng nào', 'flavor') . '</div>';
        }

        // Get price range for rooms
        $rooms = $this->attach_price_info($rooms);

        ob_start();
        ?>
        <div class="vie-room-listing" data-hotel-id="<?php echo esc_attr($hotel_id); ?>"
            data-view="<?php echo esc_attr($atts['view']); ?>">

            <?php if ($atts['show_filters'] === 'yes'): ?>
                <!-- Booking Filters -->
                <div class="vie-booking-filters">
                    <div class="vie-filter-row">
                        <div class="vie-filter-item vie-filter-dates">
                            <label><?php _e('Ngày nhận phòng', 'flavor'); ?></label>
                            <input type="text" id="vie-checkin" class="vie-datepicker"
                                placeholder="<?php _e('Check-in', 'flavor'); ?>" readonly>
                        </div>
                        <div class="vie-filter-item vie-filter-dates">
                            <label><?php _e('Ngày trả phòng', 'flavor'); ?></label>
                            <input type="text" id="vie-checkout" class="vie-datepicker"
                                placeholder="<?php _e('Check-out', 'flavor'); ?>" readonly>
                        </div>
                        <div class="vie-filter-item vie-filter-rooms">
                            <label><?php _e('Số phòng', 'flavor'); ?></label>
                            <select id="vie-num-rooms">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?>                 <?php _e('phòng', 'flavor'); ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="vie-filter-item vie-filter-guests">
                            <label><?php _e('Người lớn', 'flavor'); ?></label>
                            <select id="vie-num-adults">
                                <?php for ($i = 1; $i <= 10; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="vie-filter-item vie-filter-guests">
                            <label><?php _e('Trẻ em', 'flavor'); ?></label>
                            <select id="vie-num-children">
                                <?php for ($i = 0; $i <= 6; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="vie-filter-item vie-filter-action">
                            <button type="button" id="vie-check-availability" class="vie-btn vie-btn-primary">
                                <?php _e('Kiểm tra', 'flavor'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Children ages (hidden by default) -->
                    <div class="vie-children-ages" id="vie-children-ages" style="display:none">
                        <label><?php _e('Tuổi của các bé:', 'flavor'); ?></label>
                        <div class="vie-ages-inputs"></div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Room List -->
            <div class="vie-rooms-grid columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php foreach ($rooms as $room): ?>
                    <?php echo $this->render_room_card($room); ?>
                <?php endforeach; ?>
            </div>

            <!-- Room Detail Modal -->
            <?php echo $this->render_detail_modal(); ?>

            <!-- Booking Popup -->
            <?php echo $this->render_booking_popup(); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get hotel rooms
     */
    private function get_hotel_rooms($hotel_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_rooms';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE hotel_id = %d AND status = 'active' ORDER BY sort_order ASC, id ASC",
            $hotel_id
        ));
    }

    /**
     * Attach price info to rooms
     * CRO Enhancement: Query both Room & Combo min prices for next 30 days
     */
    private function attach_price_info($rooms)
    {
        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        foreach ($rooms as &$room) {
            // Get min Room price and min Combo price from pricing table for next 30 days
            $prices = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    MIN(CASE WHEN price_room > 0 THEN price_room ELSE %f END) as min_price_room,
                    MAX(CASE WHEN price_room > 0 THEN price_room ELSE %f END) as max_price_room,
                    MIN(CASE WHEN price_combo > 0 THEN price_combo ELSE NULL END) as min_price_combo,
                    MAX(CASE WHEN price_combo > 0 THEN price_combo ELSE NULL END) as max_price_combo
                FROM {$table_pricing} 
                WHERE room_id = %d 
                AND date >= CURDATE() 
                AND date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                AND status IN ('available', 'limited')",
                $room->base_price,
                $room->base_price,
                $room->id
            ));

            // Room price (always available)
            $room->min_price = $prices && $prices->min_price_room ? $prices->min_price_room : $room->base_price;
            $room->max_price = $prices && $prices->max_price_room ? $prices->max_price_room : $room->base_price;
            
            // Combo price (may not be set)
            $room->min_price_combo = $prices && $prices->min_price_combo ? $prices->min_price_combo : null;
            $room->max_price_combo = $prices && $prices->max_price_combo ? $prices->max_price_combo : null;
            
            // Calculate savings percentage if combo exists
            if ($room->min_price_combo && $room->min_price > 0) {
                // Combo usually includes breakfast, so we show value proposition
                $room->combo_value = true;
            } else {
                $room->combo_value = false;
            }

            // Parse JSON fields
            $room->gallery_ids = json_decode($room->gallery_ids, true) ?: array();
            $room->amenities = json_decode($room->amenities, true) ?: array();
        }

        return $rooms;
    }

    /**
     * Render single room card
     * CRO Enhancement: Dual price display (Room vs Combo) with visual comparison
     */
    private function render_room_card($room)
    {
        $featured_img = $room->featured_image_id ? wp_get_attachment_image_url($room->featured_image_id, 'medium_large') : '';
        $price_room_display = Vie_Hotel_Rooms_Helpers::format_currency($room->min_price);
        $price_combo_display = $room->min_price_combo ? Vie_Hotel_Rooms_Helpers::format_currency($room->min_price_combo) : null;
        
        // Get gallery images for slider
        $gallery_images = array();
        if ($room->featured_image_id) {
            $gallery_images[] = wp_get_attachment_image_url($room->featured_image_id, 'medium_large');
        }
        if (!empty($room->gallery_ids)) {
            foreach (array_slice($room->gallery_ids, 0, 4) as $img_id) {
                $img_url = wp_get_attachment_image_url($img_id, 'medium_large');
                if ($img_url) $gallery_images[] = $img_url;
            }
        }

        ob_start();
        ?>
        <div class="vie-room-card" data-room-id="<?php echo esc_attr($room->id); ?>">
            <!-- Image Slider -->
            <div class="vie-room-image">
                <?php if (!empty($gallery_images)): ?>
                    <?php if (count($gallery_images) > 1): ?>
                        <div class="swiper vie-card-swiper">
                            <div class="swiper-wrapper">
                                <?php foreach ($gallery_images as $img_url): ?>
                                    <div class="swiper-slide">
                                        <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($room->name); ?>" loading="lazy">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>
                    <?php else: ?>
                        <img src="<?php echo esc_url($gallery_images[0]); ?>" alt="<?php echo esc_attr($room->name); ?>" loading="lazy">
                    <?php endif; ?>
                <?php else: ?>
                    <div class="vie-no-image"><span class="dashicons dashicons-format-image"></span></div>
                <?php endif; ?>
                <div class="vie-room-badge vie-availability" data-room-id="<?php echo esc_attr($room->id); ?>"></div>
            </div>

            <div class="vie-room-info">
                <h3 class="vie-room-name"><?php echo esc_html($room->name); ?></h3>

                <!-- Room Meta Icons -->
                <div class="vie-room-meta">
                    <span class="vie-meta-item" title="<?php _e('Sức chứa', 'flavor'); ?>">
                        <i class="dashicons dashicons-groups"></i>
                        <?php printf(__('%d người', 'flavor'), $room->max_adults); ?>
                    </span>
                    <?php if ($room->max_children > 0): ?>
                        <span class="vie-meta-item" title="<?php _e('Trẻ em', 'flavor'); ?>">
                            <i class="dashicons dashicons-admin-users"></i>
                            +<?php echo $room->max_children; ?> <?php _e('bé', 'flavor'); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($room->room_size): ?>
                        <span class="vie-meta-item" title="<?php _e('Diện tích', 'flavor'); ?>">
                            <i class="dashicons dashicons-editor-expand"></i>
                            <?php echo esc_html($room->room_size); ?>m²
                        </span>
                    <?php endif; ?>
                    <?php if ($room->bed_type): ?>
                        <span class="vie-meta-item" title="<?php _e('Loại giường', 'flavor'); ?>">
                            <i class="dashicons dashicons-bed"></i>
                            <?php echo esc_html($room->bed_type); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($room->short_description): ?>
                    <p class="vie-room-desc"><?php echo esc_html(wp_trim_words($room->short_description, 18)); ?></p>
                <?php endif; ?>

                <?php if (!empty($room->amenities)): ?>
                    <div class="vie-room-amenities">
                        <?php
                        $show_amenities = array_slice($room->amenities, 0, 4);
                        foreach ($show_amenities as $amenity):
                            ?>
                            <span class="vie-amenity"><?php echo esc_html($amenity); ?></span>
                        <?php endforeach; ?>
                        <?php if (count($room->amenities) > 4): ?>
                            <span class="vie-amenity vie-more">+<?php echo count($room->amenities) - 4; ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- CRO: Dual Price Display -->
                <div class="vie-room-footer">
                    <div class="vie-price-comparison">
                        <!-- Room Only Price -->
                        <div class="vie-price-option vie-price-room">
                            <span class="vie-price-type-label"><?php _e('Room Only', 'flavor'); ?></span>
                            <div class="vie-price-amount">
                                <span class="vie-price-from"><?php _e('Từ', 'flavor'); ?></span>
                                <span class="vie-price-value"><?php echo $price_room_display; ?></span>
                            </div>
                            <span class="vie-price-unit"><?php _e('/đêm', 'flavor'); ?></span>
                        </div>
                        
                        <?php if ($price_combo_display): ?>
                        <!-- Combo Price (Highlighted) -->
                        <div class="vie-price-option vie-price-combo">
                            <span class="vie-best-deal-badge"><?php _e('Khuyên dùng', 'flavor'); ?></span>
                            <span class="vie-price-type-label"><?php _e('Combo', 'flavor'); ?></span>
                            <div class="vie-price-amount">
                                <span class="vie-price-from"><?php _e('Từ', 'flavor'); ?></span>
                                <span class="vie-price-value"><?php echo $price_combo_display; ?></span>
                            </div>
                            <span class="vie-price-unit"><?php _e('/đêm', 'flavor'); ?></span>
                            <span class="vie-combo-includes"><?php _e('Bao gồm ăn sáng', 'flavor'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="vie-room-actions">
                        <button type="button" class="vie-btn vie-btn-outline vie-btn-detail"
                            data-room='<?php echo esc_attr(wp_json_encode($room)); ?>'>
                            <?php _e('Xem chi tiết', 'flavor'); ?>
                        </button>
                        <button type="button" class="vie-btn vie-btn-primary vie-btn-book"
                            data-room-id="<?php echo esc_attr($room->id); ?>"
                            data-room-name="<?php echo esc_attr($room->name); ?>">
                            <?php _e('Đặt ngay', 'flavor'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render detail modal
     */
    private function render_detail_modal()
    {
        ob_start();
        ?>
        <div id="vie-room-detail-modal" class="vie-modal" style="display:none">
            <div class="vie-modal-overlay"></div>
            <div class="vie-modal-container vie-modal-lg">
                <button type="button" class="vie-modal-close">&times;</button>
                <div class="vie-modal-body">
                    <!-- Gallery -->
                    <div class="vie-detail-gallery">
                        <div class="swiper vie-gallery-swiper">
                            <div class="swiper-wrapper"></div>
                            <div class="swiper-pagination"></div>
                            <div class="swiper-button-prev"></div>
                            <div class="swiper-button-next"></div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="vie-detail-content">
                        <h2 class="vie-detail-title"></h2>

                        <div class="vie-detail-meta"></div>

                        <div class="vie-detail-section vie-detail-amenities">
                            <h4><?php _e('Tiện nghi', 'flavor'); ?></h4>
                            <div class="vie-amenities-list"></div>
                        </div>

                        <div class="vie-detail-section vie-detail-description">
                            <h4><?php _e('Mô tả', 'flavor'); ?></h4>
                            <div class="vie-description-text"></div>
                        </div>

                        <div class="vie-detail-footer">
                            <div class="vie-detail-price">
                                <span class="vie-price-label"><?php _e('Giá từ', 'flavor'); ?></span>
                                <span class="vie-price-value"></span>
                                <span class="vie-price-unit"><?php _e('/đêm', 'flavor'); ?></span>
                            </div>
                            <button type="button" class="vie-btn vie-btn-primary vie-btn-book-from-detail">
                                <?php _e('Đặt phòng này', 'flavor'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render booking popup
     */
    private function render_booking_popup()
    {
        ob_start();
        ?>
        <div id="vie-booking-popup" class="vie-modal" style="display:none">
            <div class="vie-modal-overlay"></div>
            <div class="vie-modal-container vie-modal-md">
                <button type="button" class="vie-modal-close">&times;</button>

                <div class="vie-booking-header">
                    <h2><?php _e('Đặt phòng', 'flavor'); ?></h2>
                    <p class="vie-booking-room-name"></p>
                </div>

                <div class="vie-booking-steps">
                    <div class="vie-step active" data-step="1">
                        <span class="vie-step-num">1</span>
                        <span class="vie-step-label"><?php _e('Chọn ngày & Giá', 'flavor'); ?></span>
                    </div>
                    <div class="vie-step" data-step="2">
                        <span class="vie-step-num">2</span>
                        <span class="vie-step-label"><?php _e('Thông tin & Thanh toán', 'flavor'); ?></span>
                    </div>
                </div>

                <form id="vie-booking-form">
                    <input type="hidden" name="hotel_id" id="booking-hotel-id">
                    <input type="hidden" name="room_id" id="booking-room-id">

                    <!-- Step 1: Date & Price -->
                    <div class="vie-booking-step-content" data-step="1">
                        <div class="vie-form-row">
                            <div class="vie-form-group vie-form-half">
                                <label><?php _e('Ngày nhận phòng', 'flavor'); ?> <span class="required">*</span></label>
                                <input type="text" name="check_in" id="booking-checkin" class="vie-datepicker" required
                                    readonly>
                            </div>
                            <div class="vie-form-group vie-form-half">
                                <label><?php _e('Ngày trả phòng', 'flavor'); ?> <span class="required">*</span></label>
                                <input type="text" name="check_out" id="booking-checkout" class="vie-datepicker" required
                                    readonly>
                            </div>
                        </div>

                        <div class="vie-form-row">
                            <div class="vie-form-group vie-form-third">
                                <label><?php _e('Số phòng', 'flavor'); ?></label>
                                <select name="num_rooms" id="booking-num-rooms">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="vie-form-group vie-form-third">
                                <label><?php _e('Người lớn', 'flavor'); ?></label>
                                <select name="num_adults" id="booking-num-adults">
                                    <?php for ($i = 1; $i <= 10; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($i, 2); ?>><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="vie-form-group vie-form-third">
                                <label><?php _e('Trẻ em', 'flavor'); ?></label>
                                <select name="num_children" id="booking-num-children">
                                    <?php for ($i = 0; $i <= 6; $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Children Ages -->
                        <div class="vie-form-group vie-children-ages-booking" id="booking-children-ages" style="display:none">
                            <label><?php _e('Tuổi của từng bé', 'flavor'); ?></label>
                            <div class="vie-ages-inputs"></div>
                            <p class="vie-help-text">
                                <?php _e('Bé dưới 6 tuổi: Miễn phí | 6-11 tuổi: Phụ thu | Từ 12 tuổi: Tính như người lớn', 'flavor'); ?>
                            </p>
                        </div>

                        <!-- Price Type -->
                        <div class="vie-form-group vie-price-type">
                            <label><?php _e('Loại giá', 'flavor'); ?></label>
                            <div class="vie-radio-group">
                                <label class="vie-radio">
                                    <input type="radio" name="price_type" value="room" checked>
                                    <span class="vie-radio-label">
                                        <strong><?php _e('Giá phòng (Room Only)', 'flavor'); ?></strong>
                                        <small><?php _e('Chỉ bao gồm phòng nghỉ', 'flavor'); ?></small>
                                    </span>
                                </label>
                                <label class="vie-radio">
                                    <input type="radio" name="price_type" value="combo">
                                    <span class="vie-radio-label">
                                        <strong><?php _e('Giá Combo', 'flavor'); ?></strong>
                                        <small><?php _e('Bao gồm phòng + ăn sáng', 'flavor'); ?></small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Price Summary -->
                        <div class="vie-price-summary" id="vie-price-summary">
                            <div class="vie-summary-placeholder">
                                <span class="dashicons dashicons-calculator"></span>
                                <p><?php _e('Chọn ngày để xem giá', 'flavor'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Customer Info -->
                    <div class="vie-booking-step-content" data-step="2" style="display:none">
                        <div class="vie-form-group">
                            <label><?php _e('Họ và tên', 'flavor'); ?> <span class="required">*</span></label>
                            <input type="text" name="customer_name" id="booking-name" required>
                        </div>

                        <div class="vie-form-row">
                            <div class="vie-form-group vie-form-half">
                                <label><?php _e('Số điện thoại', 'flavor'); ?> <span class="required">*</span></label>
                                <input type="tel" name="customer_phone" id="booking-phone" required>
                            </div>
                            <div class="vie-form-group vie-form-half">
                                <label><?php _e('Email', 'flavor'); ?></label>
                                <input type="email" name="customer_email" id="booking-email">
                            </div>
                        </div>

                        <div class="vie-form-group">
                            <label><?php _e('Ghi chú', 'flavor'); ?></label>
                            <textarea name="customer_note" id="booking-note" rows="3"
                                placeholder="<?php _e('Yêu cầu đặc biệt, giờ nhận phòng...', 'flavor'); ?>"></textarea>
                        </div>

                        <!-- Transport Section (conditionally shown via JS) -->
                        <div class="vie-transport-section" id="vie-transport-section" style="display:none">
                            <div class="vie-transport-header">
                                <label class="vie-transport-checkbox">
                                    <input type="checkbox" name="transport_enabled" id="booking-transport-enabled" value="1">
                                    <span class="vie-checkbox-label">
                                        <strong><?php _e('Tôi muốn đăng ký xe đưa đón', 'flavor'); ?></strong>
                                    </span>
                                </label>
                            </div>

                            <div class="vie-transport-options" id="vie-transport-options" style="display:none">
                                <div class="vie-form-row">
                                    <div class="vie-form-group vie-form-half">
                                        <label><?php _e('Chọn giờ đi', 'flavor'); ?> <span class="required">*</span></label>
                                        <select name="transport_pickup_time" id="booking-transport-pickup">
                                            <option value=""><?php _e('-- Chọn giờ đi --', 'flavor'); ?></option>
                                        </select>
                                    </div>
                                    <div class="vie-form-group vie-form-half">
                                        <label><?php _e('Chọn giờ về', 'flavor'); ?> <span class="required">*</span></label>
                                        <select name="transport_dropoff_time" id="booking-transport-dropoff">
                                            <option value=""><?php _e('-- Chọn giờ về --', 'flavor'); ?></option>
                                        </select>
                                    </div>
                                </div>
                                <div class="vie-transport-note" id="vie-transport-note"></div>
                            </div>
                        </div>

                        <!-- Booking Summary -->
                        <div class="vie-booking-summary" id="vie-booking-summary"></div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="vie-booking-footer">
                        <button type="button" class="vie-btn vie-btn-outline vie-btn-back" style="display:none">
                            <?php _e('Quay lại', 'flavor'); ?>
                        </button>
                        <button type="button" class="vie-btn vie-btn-primary vie-btn-next">
                            <?php _e('Tiếp tục', 'flavor'); ?>
                        </button>
                        <button type="submit" class="vie-btn vie-btn-primary vie-btn-submit" style="display:none">
                            <?php _e('Xác nhận đặt phòng', 'flavor'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Success Modal -->
        <div id="vie-booking-success" class="vie-modal" style="display:none">
            <div class="vie-modal-overlay"></div>
            <div class="vie-modal-container vie-modal-sm">
                <div class="vie-success-content">
                    <div class="vie-success-icon">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                    <h2><?php _e('Đặt phòng thành công!', 'flavor'); ?></h2>
                    <p class="vie-booking-code"></p>
                    <p class="vie-success-msg"><?php _e('Chúng tôi sẽ liên hệ xác nhận trong thời gian sớm nhất.', 'flavor'); ?>
                    </p>
                    <div class="vie-success-actions">
                        <a href="#"
                            class="vie-btn vie-btn-primary vie-btn-checkout"><?php _e('Tiến hành thanh toán', 'flavor'); ?></a>
                        <button type="button"
                            class="vie-btn vie-btn-outline vie-close-success"><?php _e('Đóng', 'flavor'); ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
