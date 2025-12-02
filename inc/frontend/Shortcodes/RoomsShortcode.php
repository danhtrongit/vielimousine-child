<?php
/**
 * ============================================================================
 * TÊN FILE: RoomsShortcode.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Shortcode Controller cho hotel rooms trên frontend.
 * Quản lý 3 shortcodes: hotel rooms list, room search, checkout.
 *
 * CHỨC NĂNG CHÍNH:
 * - [vie_hotel_rooms] - Hiển thị danh sách phòng của khách sạn
 * - [vie_room_search] - Form tìm kiếm phòng
 * - [vie_checkout] - Trang checkout/thanh toán
 *
 * SHORTCODE CONTROLLER PATTERN:
 * - Controller: Xử lý data fetching và logic
 * - Views: Render HTML (via vie_get_template())
 * - Clean separation of concerns
 *
 * USAGE:
 * [vie_hotel_rooms hotel_id="123" show_price="yes" columns="2"]
 * [vie_room_search hotel_id="123" style="horizontal"]
 * [vie_checkout]
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Frontend/Shortcodes
 * @version     2.1.0
 * @since       2.0.0 (Refactored to Shortcode Controller pattern in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Rooms_Shortcode
 * ============================================================================
 *
 * Shortcode Controller cho hotel rooms.
 *
 * ARCHITECTURE:
 * - Shortcode Controller Pattern
 * - Data fetching methods (private)
 * - Shortcode rendering methods (public)
 * - Template loading via vie_get_template()
 *
 * SHORTCODES (3):
 * - vie_hotel_rooms: Room listing
 * - vie_room_search: Search form
 * - vie_checkout: Checkout page
 *
 * @since   2.0.0
 */
class Vie_Rooms_Shortcode
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /** @var string Rooms table name */
    private $table_rooms;

    /** @var string Pricing table name */
    private $table_pricing;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Initialize table names và register shortcodes.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->table_rooms   = $wpdb->prefix . 'hotel_rooms';
        $this->table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        // Register shortcodes
        $this->register_shortcodes();
    }

    /**
     * Register all shortcodes
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_shortcodes()
    {
        add_shortcode('vie_hotel_rooms', array($this, 'shortcode_hotel_rooms'));
        add_shortcode('vie_room_search', array($this, 'shortcode_room_search'));
        add_shortcode('vie_checkout', array($this, 'shortcode_checkout'));
    }

    /**
     * -------------------------------------------------------------------------
     * SHORTCODE: Hotel Rooms List
     * -------------------------------------------------------------------------
     */

    /**
     * Shortcode hiển thị danh sách phòng của khách sạn
     *
     * Displays room listing with prices, stock, and booking functionality.
     *
     * ATTRIBUTES:
     * - hotel_id: Hotel post ID (default: current post or 0)
     * - show_price: Show prices (yes/no, default: yes)
     * - columns: Grid columns (1-4, default: 2)
     * - limit: Max rooms to show (-1 = all, default: -1)
     *
     * USAGE:
     * [vie_hotel_rooms hotel_id="123" show_price="yes" columns="2"]
     *
     * @since   2.0.0
     * @param   array   $atts   Shortcode attributes
     * @return  string          HTML output
     */
    public function shortcode_hotel_rooms($atts)
    {
        // Parse attributes
        $atts = shortcode_atts(array(
            'hotel_id'   => 0,
            'show_price' => 'yes',
            'columns'    => 2,
            'limit'      => -1,
        ), $atts);

        // Get hotel ID
        $hotel_id = $this->get_hotel_id($atts['hotel_id']);

        if ($hotel_id <= 0) {
            return '<p class="vie-error">' . esc_html__('Vui lòng chỉ định hotel_id', 'vielimousine') . '</p>';
        }

        // Get rooms data
        $rooms = $this->get_rooms($hotel_id, $atts['limit']);

        if (empty($rooms)) {
            return '<p class="vie-no-rooms">' . esc_html__('Chưa có phòng nào', 'vielimousine') . '</p>';
        }

        // Get pricing data for rooms
        $prices = $this->get_rooms_pricing($rooms);

        // Render output
        return $this->render_hotel_rooms($hotel_id, $rooms, $prices, $atts);
    }

    /**
     * -------------------------------------------------------------------------
     * SHORTCODE: Room Search Form
     * -------------------------------------------------------------------------
     */

    /**
     * Shortcode form tìm kiếm phòng
     *
     * Display room search form with hotel, dates, and guest selection.
     *
     * ATTRIBUTES:
     * - hotel_id: Hotel post ID (0 = show hotel dropdown)
     * - style: Form style (horizontal/vertical, default: horizontal)
     *
     * USAGE:
     * [vie_room_search hotel_id="123" style="horizontal"]
     *
     * @since   2.0.0
     * @param   array   $atts   Shortcode attributes
     * @return  string          HTML output
     */
    public function shortcode_room_search($atts)
    {
        // Parse attributes
        $atts = shortcode_atts(array(
            'hotel_id' => 0,
            'style'    => 'horizontal',
        ), $atts);

        // Render search form
        return $this->render_search_form($atts);
    }

    /**
     * -------------------------------------------------------------------------
     * SHORTCODE: Checkout Page
     * -------------------------------------------------------------------------
     */

    /**
     * Shortcode trang checkout
     *
     * Display checkout page with booking summary and payment form.
     *
     * REQUIRES:
     * - ?booking=HASH in URL (booking hash from booking session)
     *
     * USAGE:
     * [vie_checkout]
     *
     * @since   2.0.0
     * @param   array   $atts   Shortcode attributes (not used)
     * @return  string          HTML output
     */
    public function shortcode_checkout($atts)
    {
        // Get booking hash from URL
        $booking_hash = isset($_GET['booking']) ? sanitize_text_field($_GET['booking']) : '';

        if (empty($booking_hash)) {
            return '<div class="vie-error">' . esc_html__('Không tìm thấy thông tin đặt phòng', 'vielimousine') . '</div>';
        }

        // Get booking data
        $booking = $this->get_booking_by_hash($booking_hash);

        if (!$booking) {
            return '<div class="vie-error">' . esc_html__('Đơn đặt phòng không tồn tại hoặc đã hết hạn', 'vielimousine') . '</div>';
        }

        // Check booking status
        if ($booking->status !== 'pending_payment') {
            return '<div class="vie-info">' . esc_html__('Đơn đặt phòng này đã được xử lý', 'vielimousine') . '</div>';
        }

        // Render checkout page
        return $this->render_checkout($booking, $booking_hash);
    }

    /**
     * -------------------------------------------------------------------------
     * DATA FETCHING METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get hotel ID from attribute or current post
     *
     * @since   2.1.0
     * @param   int     $attr_hotel_id  Hotel ID from shortcode attribute
     * @return  int                     Hotel ID or 0
     */
    private function get_hotel_id($attr_hotel_id)
    {
        $hotel_id = absint($attr_hotel_id);

        // If no hotel_id provided, try to get from current post
        if ($hotel_id <= 0) {
            global $post;
            if ($post && $post->post_type === 'hotel') {
                $hotel_id = $post->ID;
            }
        }

        return $hotel_id;
    }

    /**
     * Get rooms for hotel
     *
     * Fetch active rooms for specified hotel.
     *
     * @since   2.1.0
     * @param   int     $hotel_id   Hotel post ID
     * @param   int     $limit      Max rooms (-1 = all)
     * @return  array               Rooms array
     */
    private function get_rooms($hotel_id, $limit = -1)
    {
        global $wpdb;

        $limit_sql = $limit > 0 ? $wpdb->prepare('LIMIT %d', $limit) : '';

        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_rooms}
             WHERE hotel_id = %d AND status = 'active'
             ORDER BY sort_order, name {$limit_sql}",
            $hotel_id
        ));

        return $rooms ?: array();
    }

    /**
     * Get pricing data for rooms
     *
     * Get today's pricing or min pricing from next 30 days.
     *
     * @since   2.1.0
     * @param   array   $rooms  Rooms array
     * @return  array           Pricing data indexed by room_id
     */
    private function get_rooms_pricing($rooms)
    {
        if (empty($rooms)) {
            return array();
        }

        global $wpdb;

        $today    = date('Y-m-d');
        $room_ids = wp_list_pluck($rooms, 'id');

        // Query prices and stock for today
        $prices = $wpdb->get_results($wpdb->prepare(
            "SELECT room_id, price_room, price_combo, stock, status
             FROM {$this->table_pricing}
             WHERE room_id IN (" . implode(',', array_fill(0, count($room_ids), '%d')) . ")
             AND date = %s",
            array_merge($room_ids, array($today))
        ), OBJECT_K);

        // If no today price, get min price from next 30 days
        if (empty($prices)) {
            $prices = $wpdb->get_results($wpdb->prepare(
                "SELECT room_id,
                        MIN(CASE WHEN price_room > 0 THEN price_room ELSE NULL END) as price_room,
                        MIN(CASE WHEN price_combo > 0 THEN price_combo ELSE NULL END) as price_combo,
                        MIN(stock) as stock,
                        status
                 FROM {$this->table_pricing}
                 WHERE room_id IN (" . implode(',', array_fill(0, count($room_ids), '%d')) . ")
                 AND date >= %s
                 AND date <= DATE_ADD(%s, INTERVAL 30 DAY)
                 AND status IN ('available', 'limited')
                 GROUP BY room_id",
                array_merge($room_ids, array($today, $today))
            ), OBJECT_K);
        }

        return $prices ?: array();
    }

    /**
     * Get booking by hash
     *
     * @since   2.1.0
     * @param   string  $hash   Booking hash
     * @return  object|null     Booking object or null
     */
    private function get_booking_by_hash($hash)
    {
        // Use BookingService if available (v2.1)
        if (class_exists('Vie_Booking_Service')) {
            $service = Vie_Booking_Service::get_instance();
            return $service->get_booking_by_hash($hash);
        }

        // Fallback to old manager (backward compatibility)
        if (class_exists('Vie_Booking_Manager')) {
            $manager = Vie_Booking_Manager::get_instance();
            return $manager->get_booking_by_hash($hash);
        }

        return null;
    }

    /**
     * -------------------------------------------------------------------------
     * RENDERING METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Render hotel rooms listing
     *
     * @since   2.1.0
     * @param   int     $hotel_id       Hotel ID
     * @param   array   $rooms          Rooms array
     * @param   array   $prices         Pricing data
     * @param   array   $atts           Shortcode attributes
     * @return  string                  HTML output
     */
    private function render_hotel_rooms($hotel_id, $rooms, $prices, $atts)
    {
        ob_start();

        $columns     = absint($atts['columns']);
        $show_prices = $atts['show_price'] === 'yes';
        ?>
        <div class="vie-room-listing" data-hotel-id="<?php echo esc_attr($hotel_id); ?>">
            <!-- Booking Filters -->
            <?php vie_get_template('frontend/booking-filters', array('hotel_id' => $hotel_id)); ?>

            <!-- Rooms Grid -->
            <div class="vie-rooms-grid columns-<?php echo $columns; ?>">
                <?php foreach ($rooms as $room) :
                    // Get today's price data
                    $today_price = isset($prices[$room->id]) ? $prices[$room->id] : null;

                    // Prepare price range with room, combo prices, and stock
                    $price_range = array(
                        'min'   => $today_price && $today_price->price_room > 0
                                    ? $today_price->price_room
                                    : 0,
                        'combo' => $today_price && $today_price->price_combo > 0
                                    ? $today_price->price_combo
                                    : null,
                        'stock' => $today_price && isset($today_price->stock)
                                    ? intval($today_price->stock)
                                    : 99
                    );

                    // Load room card template with variables
                    vie_get_template('frontend/room-card', array(
                        'room'        => $room,
                        'hotel_id'    => $hotel_id,
                        'price_range' => $price_range,
                        'show_prices' => $show_prices
                    ));
                endforeach; ?>
            </div>

            <!-- Room Detail Modal -->
            <?php vie_get_template('frontend/room-detail-modal'); ?>

            <!-- Booking Popup -->
            <?php vie_get_template('frontend/booking-popup', array('hotel_id' => $hotel_id)); ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render search form
     *
     * @since   2.1.0
     * @param   array   $atts   Shortcode attributes
     * @return  string          HTML output
     */
    private function render_search_form($atts)
    {
        ob_start();
        ?>
        <div class="vie-room-search vie-search-<?php echo esc_attr($atts['style']); ?>">
            <form id="vie-search-form" method="get">
                <?php if (empty($atts['hotel_id'])) : ?>
                <div class="vie-form-group">
                    <label for="vie-search-hotel"><?php esc_html_e('Khách sạn', 'vielimousine'); ?></label>
                    <select name="hotel_id" id="vie-search-hotel" required>
                        <option value=""><?php esc_html_e('-- Chọn khách sạn --', 'vielimousine'); ?></option>
                        <?php
                        $hotels = get_posts(array(
                            'post_type'      => 'hotel',
                            'posts_per_page' => -1,
                            'orderby'        => 'title',
                            'order'          => 'ASC'
                        ));
                        foreach ($hotels as $hotel) :
                        ?>
                        <option value="<?php echo esc_attr($hotel->ID); ?>"><?php echo esc_html($hotel->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else : ?>
                <input type="hidden" name="hotel_id" value="<?php echo esc_attr($atts['hotel_id']); ?>">
                <?php endif; ?>

                <div class="vie-form-group">
                    <label for="vie-search-checkin"><?php esc_html_e('Ngày nhận phòng', 'vielimousine'); ?></label>
                    <input type="text" name="check_in" id="vie-search-checkin" class="vie-datepicker"
                           placeholder="dd/mm/yyyy" required readonly>
                </div>

                <div class="vie-form-group">
                    <label for="vie-search-checkout"><?php esc_html_e('Ngày trả phòng', 'vielimousine'); ?></label>
                    <input type="text" name="check_out" id="vie-search-checkout" class="vie-datepicker"
                           placeholder="dd/mm/yyyy" required readonly>
                </div>

                <div class="vie-form-group">
                    <label for="vie-search-rooms"><?php esc_html_e('Số phòng', 'vielimousine'); ?></label>
                    <select name="num_rooms" id="vie-search-rooms">
                        <?php for ($i = 1; $i <= 10; $i++) : ?>
                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="vie-form-group vie-form-submit">
                    <button type="submit" class="vie-btn vie-btn-primary">
                        <?php esc_html_e('Tìm phòng', 'vielimousine'); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render checkout page
     *
     * @since   2.1.0
     * @param   object  $booking        Booking object
     * @param   string  $booking_hash   Booking hash
     * @return  string                  HTML output
     */
    private function render_checkout($booking, $booking_hash)
    {
        // Get hotel and room info
        $hotel_name  = get_the_title($booking->hotel_id);
        $guests_info = json_decode($booking->guests_info, true) ?: array();

        // Calculate nights
        $date_in    = new DateTime($booking->check_in);
        $date_out   = new DateTime($booking->check_out);
        $num_nights = $date_out->diff($date_in)->days;

        ob_start();
        ?>
        <div class="vie-checkout-wrapper">
            <div class="vie-checkout-grid">
                <!-- Booking Summary -->
                <div class="vie-checkout-summary">
                    <h3><?php esc_html_e('Thông tin đặt phòng', 'vielimousine'); ?></h3>

                    <div class="vie-summary-item">
                        <span class="vie-label"><?php esc_html_e('Mã đặt phòng:', 'vielimousine'); ?></span>
                        <strong><?php echo esc_html($booking->booking_code); ?></strong>
                    </div>

                    <div class="vie-summary-item">
                        <span class="vie-label"><?php esc_html_e('Khách sạn:', 'vielimousine'); ?></span>
                        <span><?php echo esc_html($hotel_name); ?></span>
                    </div>

                    <div class="vie-summary-item">
                        <span class="vie-label"><?php esc_html_e('Loại phòng:', 'vielimousine'); ?></span>
                        <span><?php echo esc_html($booking->room_name); ?></span>
                    </div>

                    <div class="vie-summary-item">
                        <span class="vie-label"><?php esc_html_e('Ngày:', 'vielimousine'); ?></span>
                        <span>
                            <?php echo date('d/m/Y', strtotime($booking->check_in)); ?>
                            →
                            <?php echo date('d/m/Y', strtotime($booking->check_out)); ?>
                            (<?php printf(esc_html__('%d đêm', 'vielimousine'), $num_nights); ?>)
                        </span>
                    </div>

                    <div class="vie-summary-item">
                        <span class="vie-label"><?php esc_html_e('Số phòng:', 'vielimousine'); ?></span>
                        <span><?php echo esc_html($booking->num_rooms); ?></span>
                    </div>

                    <div class="vie-summary-item">
                        <span class="vie-label"><?php esc_html_e('Số khách:', 'vielimousine'); ?></span>
                        <span>
                            <?php echo esc_html($booking->num_adults); ?> người lớn
                            <?php if ($booking->num_children > 0) : ?>
                                , <?php echo esc_html($booking->num_children); ?> trẻ em
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="vie-summary-total">
                        <span class="vie-label"><?php esc_html_e('Tổng tiền:', 'vielimousine'); ?></span>
                        <strong class="vie-total-amount"><?php echo vie_format_currency($booking->total_amount); ?></strong>
                    </div>
                </div>

                <!-- Customer Form -->
                <div class="vie-checkout-form">
                    <h3><?php esc_html_e('Thông tin khách hàng', 'vielimousine'); ?></h3>

                    <form id="vie-checkout-form">
                        <input type="hidden" name="booking_hash" value="<?php echo esc_attr($booking_hash); ?>">

                        <div class="vie-form-group">
                            <label for="customer_name"><?php esc_html_e('Họ và tên', 'vielimousine'); ?> <span class="required">*</span></label>
                            <input type="text" name="customer_name" id="customer_name" required
                                   value="<?php echo esc_attr($booking->customer_name); ?>">
                        </div>

                        <div class="vie-form-group">
                            <label for="customer_phone"><?php esc_html_e('Số điện thoại', 'vielimousine'); ?> <span class="required">*</span></label>
                            <input type="tel" name="customer_phone" id="customer_phone" required
                                   value="<?php echo esc_attr($booking->customer_phone); ?>">
                        </div>

                        <div class="vie-form-group">
                            <label for="customer_email"><?php esc_html_e('Email', 'vielimousine'); ?></label>
                            <input type="email" name="customer_email" id="customer_email"
                                   value="<?php echo esc_attr($booking->customer_email); ?>">
                        </div>

                        <div class="vie-form-group">
                            <label for="customer_note"><?php esc_html_e('Ghi chú', 'vielimousine'); ?></label>
                            <textarea name="customer_note" id="customer_note" rows="3"><?php echo esc_textarea($booking->customer_note); ?></textarea>
                        </div>

                        <h4><?php esc_html_e('Phương thức thanh toán', 'vielimousine'); ?></h4>

                        <div class="vie-payment-methods">
                            <label class="vie-payment-option">
                                <input type="radio" name="payment_method" value="bank_transfer" checked>
                                <span><?php esc_html_e('Chuyển khoản ngân hàng', 'vielimousine'); ?></span>
                            </label>
                            <label class="vie-payment-option">
                                <input type="radio" name="payment_method" value="sepay">
                                <span><?php esc_html_e('Thanh toán QR (SePay)', 'vielimousine'); ?></span>
                            </label>
                        </div>

                        <button type="submit" class="vie-btn vie-btn-primary vie-btn-block" id="vie-submit-checkout">
                            <?php esc_html_e('Xác nhận đặt phòng', 'vielimousine'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
        jQuery(function($) {
            $('#vie-checkout-form').on('submit', function(e) {
                e.preventDefault();

                var $btn = $('#vie-submit-checkout');
                $btn.prop('disabled', true).text('<?php esc_html_e('Đang xử lý...', 'vielimousine'); ?>');

                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'vie_process_checkout',
                    nonce: '<?php echo wp_create_nonce('vie_checkout_action'); ?>',
                    booking_hash: $('input[name="booking_hash"]').val(),
                    payment_method: $('input[name="payment_method"]:checked').val(),
                    customer_name: $('input[name="customer_name"]').val(),
                    customer_phone: $('input[name="customer_phone"]').val(),
                    customer_email: $('input[name="customer_email"]').val(),
                    customer_note: $('textarea[name="customer_note"]').val()
                }, function(res) {
                    if (res.success) {
                        alert(res.data.message);
                        window.location.href = '<?php echo home_url(); ?>';
                    } else {
                        alert(res.data.message || '<?php esc_html_e('Có lỗi xảy ra', 'vielimousine'); ?>');
                        $btn.prop('disabled', false).text('<?php esc_html_e('Xác nhận đặt phòng', 'vielimousine'); ?>');
                    }
                });
            });
        });
        </script>
        <?php

        return ob_get_clean();
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Class alias for backward compatibility
if (!class_exists('Vie_Shortcode_Rooms')) {
    class_alias('Vie_Rooms_Shortcode', 'Vie_Shortcode_Rooms');
}

// Auto-initialize (maintains original behavior)
new Vie_Rooms_Shortcode();
