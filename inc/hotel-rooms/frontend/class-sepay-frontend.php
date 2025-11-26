<?php
/**
 * SePay Frontend Handler for Hotel Booking
 * 
 * Hiển thị trang thanh toán SePay ở frontend
 * Sử dụng OAuth2 API để lấy thông tin ngân hàng
 * 
 * @package VieHotelRooms
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_SePay_Frontend
{
    private static $instance = null;
    private $sepay;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->sepay = vie_sepay();
        $this->init_hooks();
    }

    private function init_hooks()
    {
        add_shortcode('vie_sepay_payment', [$this, 'render_payment_shortcode']);
        add_action('vie_hotel_booking_after_confirmation', [$this, 'render_payment_section'], 10, 1);
    }

    /**
     * Render payment shortcode
     */
    public function render_payment_shortcode($atts)
    {
        $atts = shortcode_atts([
            'booking_id' => isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0,
            'hash' => isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '',
        ], $atts, 'vie_sepay_payment');

        if (!$atts['booking_id'] || !$atts['hash']) {
            return '<div class="vie-error">Không tìm thấy thông tin đặt phòng.</div>';
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND booking_hash = %s",
            $atts['booking_id'],
            $atts['hash']
        ));

        if (!$booking) {
            return '<div class="vie-error">Đơn đặt phòng không tồn tại hoặc đã bị hủy.</div>';
        }

        if (in_array($booking->payment_status, ['paid']) || in_array($booking->status, ['confirmed', 'completed'])) {
            return $this->render_already_paid($booking);
        }

        return $this->render_payment_form($booking);
    }

    /**
     * Render payment section after booking confirmation
     */
    public function render_payment_section($booking)
    {
        if (!$this->sepay->is_enabled()) {
            return;
        }

        if (!in_array($booking->status, ['pending', 'pending_payment'])) {
            return;
        }

        if ($booking->payment_status === 'paid') {
            return;
        }

        echo $this->render_payment_form($booking);
    }

    /**
     * Render payment form
     */
    public function render_payment_form($booking)
    {
        if (!$this->sepay->is_enabled()) {
            return '<div class="vie-notice">Thanh toán trực tuyến hiện chưa được kích hoạt.</div>';
        }

        // Get bank account from settings (stored via OAuth setup)
        $bank_account_id = $this->sepay->get_setting('bank_account');
        if (!$bank_account_id) {
            return '<div class="vie-notice">Chưa cấu hình tài khoản ngân hàng.</div>';
        }

        // Get bank info from API
        $bank = $this->sepay->get_bank_account($bank_account_id);
        if (!$bank) {
            return '<div class="vie-notice">Không thể lấy thông tin ngân hàng.</div>';
        }

        // Prepare variables for template
        $bank_info = $bank['bank'];
        $account_number = $bank['account_number'];
        $account_holder = $bank['account_holder_name'];
        $bank_logo_url = $bank['bank']['logo_url'];
        $remark = $this->sepay->get_payment_code($booking->id);
        $qr_code_url = $this->sepay->generate_qr_url($booking->id, $booking->total_amount);

        // Buffer output
        ob_start();
        include VIE_HOTEL_ROOMS_PATH . '/frontend/views/payment-info.php';
        return ob_get_clean();
    }

    /**
     * Render already paid message
     */
    public function render_already_paid($booking)
    {
        $success_message = $this->sepay->get_setting('success_message');
        
        ob_start();
        ?>
        <section class="vie-sepay-payment">
            <div class="sepay-header">
                <h3>Thanh toán thành công</h3>
            </div>
            <div class="sepay-body">
                <div class="sepay-paid-notification">
                    <div class="paid-icon">
                        <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 130.2 130.2">
                            <circle class="path circle" fill="none" stroke="#73AF55" stroke-width="6" stroke-miterlimit="10" cx="65.1" cy="65.1" r="62.1"/>
                            <polyline class="path check" fill="none" stroke="#73AF55" stroke-width="6" stroke-linecap="round" stroke-miterlimit="10" points="100.2,40.2 51.5,88.8 29.8,67.5"/>
                        </svg>
                    </div>
                    <div class="paid-message">
                        <?php echo wp_kses_post($success_message); ?>
                    </div>
                    <div class="paid-booking-code">
                        <span>Mã đặt phòng: <strong><?php echo esc_html($booking->booking_code); ?></strong></span>
                    </div>
                </div>
            </div>
        </section>
        <?php
        
        wp_enqueue_style(
            'vie-sepay-payment',
            VIE_HOTEL_ROOMS_URL . 'assets/css/sepay-payment.css',
            [],
            VIE_HOTEL_ROOMS_VERSION
        );
        
        return ob_get_clean();
    }

    /**
     * Get payment page URL
     */
    public function get_payment_url($booking_id, $booking_hash)
    {
        $page = $this->get_payment_page();
        
        if ($page) {
            return add_query_arg([
                'booking_id' => $booking_id,
                'hash' => $booking_hash,
            ], get_permalink($page));
        }

        return add_query_arg([
            'booking_id' => $booking_id,
            'hash' => $booking_hash,
            'action' => 'payment',
        ], home_url('/'));
    }

    private function get_payment_page()
    {
        global $wpdb;
        
        $page = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} 
             WHERE post_type = 'page' 
             AND post_status = 'publish' 
             AND post_content LIKE '%[vie_sepay_payment%' 
             LIMIT 1"
        );

        return $page ? intval($page) : null;
    }
}

function vie_sepay_frontend()
{
    return Vie_SePay_Frontend::get_instance();
}
