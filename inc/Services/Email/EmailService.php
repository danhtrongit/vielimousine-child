<?php
/**
 * ============================================================================
 * TÊN FILE: EmailService.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service quản lý gửi email thông báo đặt phòng.
 * Hỗ trợ nhiều loại email với custom templates và shortcodes.
 *
 * CHỨC NĂNG CHÍNH:
 * - Gửi email xác nhận đặt phòng cho khách hàng
 * - Gửi email thông báo cho admin
 * - Gửi email mã nhận phòng
 * - Custom email templates (từ wp_options)
 * - Shortcode replacement cho personalization
 * - Beautiful HTML email design
 *
 * EMAIL TYPES:
 * - pending:    Email chờ thanh toán (với link payment)
 * - processing: Email đang xử lý (sau khi thanh toán)
 * - confirmed:  Email xác nhận hoàn tất
 * - room_code:  Email mã nhận phòng (hoàn thành)
 *
 * SHORTCODES SUPPORTED:
 * {customer_name}, {booking_id}, {hotel_name}, {hotel_address},
 * {room_name}, {package_type}, {bed_type}, {check_in}, {check_out},
 * {adults}, {children}, {total_amount}, {room_code}
 *
 * SỬ DỤNG:
 * $email = Vie_Email_Service::get_instance();
 * $email->send_email('processing', $booking_id);
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Email
 * @version     2.1.0
 * @since       2.0.0 (Di chuyển từ inc/classes trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Email_Service
 * ============================================================================
 *
 * Service xử lý gửi email cho hệ thống đặt phòng.
 *
 * ARCHITECTURE:
 * - Singleton pattern
 * - Template-based design (custom hoặc fallback)
 * - Shortcode replacement system
 * - HTML email với responsive design
 * - Uses WordPress wp_mail() function

 *
 * DEPENDENCIES:
 * - Vie_Booking_Manager (để lấy booking data)
 * - WordPress wp_mail() function

 *
 * @since   2.0.0
 * @uses    Vie_Booking_Manager    Lấy booking data
 */
class Vie_Email_Service
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Singleton instance
     * @var Vie_Email_Service|null
     */
    private static $instance = null;

    /**
     * Admin email address
     * @var string
     */
    private $admin_email;

    /**
     * From email address (Contact email)
     * @var string
     */
    private $from_email;

    /**
     * Site name
     * @var string
     */
    private $site_name;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO (SINGLETON PATTERN)
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     *
     * @since   2.0.0
     * @return  Vie_Email_Service
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private để enforce Singleton)
     *
     * Khởi tạo admin email và site name từ WordPress settings.
     *
     * @since   2.0.0
     */
    private function __construct()
    {
        $settings = get_option('vie_hotel_email_settings', array());
        $this->admin_email = !empty($settings['admin_email']) ? $settings['admin_email'] : get_option('admin_email');
        $this->from_email = !empty($settings['from_email']) ? $settings['from_email'] : get_option('admin_email');
        $this->site_name = get_bloginfo('name');
    }

    /**
     * -------------------------------------------------------------------------
     * PUBLIC API
     * -------------------------------------------------------------------------
     */

    /**
     * Gửi email theo loại
     *
     * Main method để gửi email. Dispatch đến method phù hợp dựa trên type.
     *
     * EMAIL TYPES:
     * - pending:    Chờ thanh toán (có link checkout)
     * - processing: Đang xử lý (sau khi thanh toán)
     * - confirmed:  Xác nhận hoàn tất
     * - room_code:  Gửi mã nhận phòng
     *
     * @since   2.0.0
     * @param   string  $type       Loại email (pending|processing|confirmed|room_code)
     * @param   int     $booking_id Booking ID
     * @param   array   $extra      Dữ liệu bổ sung (VD: ['room_code' => 'ABC123'])
     * @return  bool                True nếu gửi thành công, false nếu lỗi
     */
    public function send_email($type, $booking_id, $extra = array())
    {
        // Get booking data
        $manager = Vie_Booking_Manager::get_instance();
        $booking = $manager->get_booking($booking_id);

        if (!$booking) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log(sprintf('[Vie Email] Cannot send email: booking #%d not found', $booking_id));
            }
            return false;
        }

        // Dispatch to appropriate method
        switch ($type) {
            case 'pending':
                return $this->send_pending_email($booking);

            case 'processing':
                return $this->send_processing_email($booking);

            case 'confirmed':
                return $this->send_confirmed_email($booking);

            case 'room_code':
                $room_code = $extra['room_code'] ?? '';
                return $this->send_room_code_email($booking, $room_code);

            default:
                if (defined('VIE_DEBUG') && VIE_DEBUG) {
                    error_log(sprintf('[Vie Email] Unknown email type: %s', $type));
                }
                return false;
        }
    }

    /**
     * Gửi email xác nhận đặt phòng (helper alias)
     *
     * Convenience method cho send_email('processing', $booking_id)
     *
     * @since   2.0.0
     * @param   int     $booking_id Booking ID
     * @return  bool                True nếu thành công
     */
    public function send_booking_confirmation($booking_id)
    {
        return $this->send_email('processing', $booking_id);
    }

    /**
     * Gửi email mã nhận phòng (helper alias)
     *
     * Convenience method cho send_email('room_code', $booking_id, ['room_code' => $room_code])
     *
     * @since   2.0.0
     * @param   int     $booking_id Booking ID
     * @param   string  $room_code  Mã nhận phòng
     * @return  bool                True nếu thành công
     */
    public function send_room_code_email_helper($booking_id, $room_code)
    {
        return $this->send_email('room_code', $booking_id, array('room_code' => $room_code));
    }

    /**
     * -------------------------------------------------------------------------
     * EMAIL SENDING METHODS (PRIVATE)
     * -------------------------------------------------------------------------
     */

    /**
     * Gửi email chờ thanh toán
     *
     * Email này được gửi khi booking mới tạo, chưa thanh toán.
     * Có link đến checkout page để hoàn tất thanh toán.
     *
     * FLOW:
     * 1. Check custom template (từ wp_options)
     * 2. Nếu có template, replace shortcodes và send
     * 3. Nếu không, dùng hardcoded template
     *
     * @since   2.0.0
     * @param   object  $booking    Booking object từ database
     * @return  bool                True nếu gửi thành công
     */
    private function send_pending_email($booking)
    {
        if (empty($booking->customer_email)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[Vie Email] Cannot send pending email: customer_email is empty');
            }
            return false;
        }

        // Try custom template first
        $template = $this->get_custom_template('pending');
        if ($template) {
            $subject = $this->replace_shortcodes($template['subject'], $booking);
            $body = $this->replace_shortcodes($template['body'], $booking);
            $message = $this->get_email_header() . wpautop($body) . $this->get_email_footer();
            return $this->send($booking->customer_email, $subject, $message);
        }

        // Fallback to hardcoded template
        $hotel_name = get_the_title($booking->hotel_id);
        $subject = sprintf('[%s] Xác nhận đặt phòng #%s - Chờ thanh toán', $this->site_name, $booking->booking_code);

        $message = $this->get_email_header();

        // Hero Section
        $message .= '<div class="hero">';
        $message .= '<h2>Xác nhận yêu cầu đặt phòng</h2>';
        $message .= '<p>Cảm ơn bạn đã lựa chọn dịch vụ của chúng tôi</p>';
        $message .= '</div>';

        // Content
        $message .= '<div class="content">';
        $message .= '<p class="greeting">Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
        $message .= '<p>Chúng tôi đã nhận được yêu cầu đặt phòng của bạn. Để hoàn tất việc giữ phòng, vui lòng thực hiện thanh toán cho đơn hàng.</p>';

        $message .= $this->get_booking_details_html($booking, $hotel_name);

        $message .= '<div class="btn-container">';
        $message .= '<a href="' . esc_url(home_url('/checkout/?booking=' . $booking->booking_hash)) . '" class="btn">Thanh toán ngay</a>';
        $message .= '</div>';

        $message .= '<p style="margin-top: 30px; font-size: 14px; color: #64748b; text-align: center;">Nếu bạn gặp khó khăn trong quá trình thanh toán, vui lòng liên hệ với chúng tôi qua hotline.</p>';
        $message .= '</div>'; // End content

        $message .= $this->get_email_footer();

        return $this->send($booking->customer_email, $subject, $message);
    }

    /**
     * Gửi email đang xử lý (sau khi thanh toán)
     *
     * Email này được gửi sau khi khách hàng thanh toán thành công.
     * Gửi cả cho khách hàng và admin.
     *
     * RECIPIENTS:
     * - Customer: Email xác nhận đã nhận booking
     * - Admin: Email thông báo có booking mới
     *
     * @since   2.0.0
     * @param   object  $booking    Booking object
     * @return  bool                True nếu gửi admin email thành công
     */
    private function send_processing_email($booking)
    {
        $hotel_name = get_the_title($booking->hotel_id);

        // Email cho khách hàng
        if (!empty($booking->customer_email)) {
            // Try custom template first
            $template = $this->get_custom_template('processing');
            if ($template) {
                $subject = $this->replace_shortcodes($template['subject'], $booking);
                $body = $this->replace_shortcodes($template['body'], $booking);
                $message = $this->get_email_header() . wpautop($body) . $this->get_email_footer();
                $this->send($booking->customer_email, $subject, $message);
            } else {
                // Fallback to hardcoded template
                $subject = sprintf('[%s] Đặt phòng thành công - #%s', $this->site_name, $booking->booking_code);

                $message = $this->get_email_header();

                // Hero Section
                $message .= '<div class="hero">';
                $message .= '<h2>Đặt phòng thành công!</h2>';
                $message .= '<p>Đơn đặt phòng của bạn đã được xác nhận</p>';
                $message .= '</div>';

                // Content
                $message .= '<div class="content">';
                $message .= '<p class="greeting">Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
                $message .= '<p>Cảm ơn bạn đã tin tưởng và lựa chọn dịch vụ của chúng tôi. Đơn đặt phòng của bạn đã được ghi nhận và thanh toán thành công.</p>';

                $message .= $this->get_booking_details_html($booking, $hotel_name);

                $message .= '<div style="background-color: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin-top: 20px; font-size: 14px; color: #0c4a6e;">';
                $message .= '<strong>Lưu ý:</strong> Mã nhận phòng sẽ được gửi qua email riêng sau khi chúng tôi hoàn tất thủ tục với khách sạn. Vui lòng kiểm tra email thường xuyên.';
                $message .= '</div>';

                $message .= '</div>'; // End content

                $message .= $this->get_email_footer();

                $this->send($booking->customer_email, $subject, $message);
            }
        }

        // Email cho admin
        $this->send_admin_notification($booking);

        return true;
    }

    /**
     * Gửi email xác nhận hoàn tất
     *
     * Email này được gửi khi booking chuyển sang trạng thái confirmed.
     *
     * @since   2.0.0
     * @param   object  $booking    Booking object
     * @return  bool                True nếu gửi thành công
     */
    private function send_confirmed_email($booking)
    {
        if (empty($booking->customer_email)) {
            return false;
        }

        $hotel_name = get_the_title($booking->hotel_id);
        $subject = sprintf('[%s] Xác nhận đặt phòng hoàn tất - #%s', $this->site_name, $booking->booking_code);

        $message = $this->get_email_header();

        // Hero
        $message .= '<div class="hero">';
        $message .= '<h2>Xác nhận hoàn tất</h2>';
        $message .= '<p>Đơn đặt phòng của bạn đã sẵn sàng</p>';
        $message .= '</div>';

        // Content
        $message .= '<div class="content">';
        $message .= '<p class="greeting">Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
        $message .= '<p>Chúng tôi vui mừng thông báo đơn đặt phòng của bạn đã được xác nhận hoàn tất.</p>';

        $message .= $this->get_booking_details_html($booking, $hotel_name);

        $message .= '<p>Chúc bạn có một kỳ nghỉ tuyệt vời!</p>';
        $message .= '</div>';

        $message .= $this->get_email_footer();

        return $this->send($booking->customer_email, $subject, $message);
    }

    /**
     * Gửi email mã nhận phòng (completed)
     *
     * Email này được gửi khi admin tạo mã nhận phòng cho booking.
     * Mã này dùng để nhận phòng tại khách sạn.
     *
     * TEMPLATE:
     * - Hiển thị mã nhận phòng nổi bật (large, bold)
     * - Kèm thông tin booking đầy đủ
     * - Hướng dẫn xuất trình mã khi check-in
     *
     * @since   2.0.0
     * @param   object  $booking    Booking object
     * @param   string  $room_code  Mã nhận phòng (VD: "ABC123")
     * @return  bool                True nếu gửi thành công
     */
    private function send_room_code_email($booking, $room_code)
    {
        if (empty($booking->customer_email) || empty($room_code)) {
            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                error_log('[Vie Email] Cannot send room code email: missing email or room_code');
            }
            return false;
        }

        // Try custom template first
        $template = $this->get_custom_template('completed');
        if ($template) {
            // Add room_code to booking object temporarily for shortcode replacement
            $booking->room_code = $room_code;
            $subject = $this->replace_shortcodes($template['subject'], $booking);
            $body = $this->replace_shortcodes($template['body'], $booking);
            $message = $this->get_email_header() . wpautop($body) . $this->get_email_footer();
            return $this->send($booking->customer_email, $subject, $message);
        }

        // Fallback to hardcoded template
        $hotel_name = get_the_title($booking->hotel_id);
        $subject = sprintf('[%s] Mã nhận phòng của bạn - #%s', $this->site_name, $booking->booking_code);

        $message = $this->get_email_header();

        // Hero
        $message .= '<div class="hero">';
        $message .= '<h2>Mã nhận phòng của bạn</h2>';
        $message .= '<p>Sẵn sàng cho chuyến đi</p>';
        $message .= '</div>';

        // Content
        $message .= '<div class="content">';
        $message .= '<p class="greeting">Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
        $message .= '<p>Dưới đây là mã nhận phòng chính thức của bạn. Vui lòng xuất trình mã này tại quầy lễ tân khi làm thủ tục nhận phòng.</p>';

        $message .= '<div class="code-box">';
        $message .= '<div class="code-label">Mã nhận phòng</div>';
        $message .= '<p class="code-value">' . esc_html($room_code) . '</p>';
        $message .= '</div>';

        $message .= $this->get_booking_details_html($booking, $hotel_name);

        $message .= '<p style="text-align: center; color: #64748b;">Chúc quý khách có một kỳ nghỉ vui vẻ và thoải mái!</p>';
        $message .= '</div>';

        $message .= $this->get_email_footer();

        return $this->send($booking->customer_email, $subject, $message);
    }

    /**
     * -------------------------------------------------------------------------
     * HTML BUILDERS
     * -------------------------------------------------------------------------
     */

    /**
     * Get email header HTML
     *
     * Header chứa:
     * - Logo (nếu có configure trong wp_options)
     * - DOCTYPE và HTML wrapper
     * - Basic styles
     *
     * @since   2.0.0
     * @return  string  HTML header
     */
    private function get_email_header()
    {
        $logo_url = get_option('vie_email_logo', '');
        $site_name = $this->site_name;

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($site_name); ?></title>
            <style>
                /* Reset & Basics */
                body {
                    margin: 0;
                    padding: 0;
                    width: 100%;
                    background-color: #f3f4f6;
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    -webkit-font-smoothing: antialiased;
                    line-height: 1.6;
                    color: #374151;
                }

                table {
                    border-collapse: collapse;
                    width: 100%;
                }

                img {
                    border: 0;
                    height: auto;
                    line-height: 100%;
                    outline: none;
                    text-decoration: none;
                    max-width: 100%;
                }

                /* Layout */
                .email-wrapper {
                    width: 100%;
                    background-color: #f3f4f6;
                    padding: 40px 0;
                }

                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 12px;
                    overflow: hidden;
                    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                }

                /* Header */
                .header {
                    text-align: center;
                    padding: 25px 20px;
                    background-color: #ffffff;
                    border-bottom: 1px solid #f0f0f0;
                }

                .header img {
                    max-height: 60px;
                    width: auto;
                }

                .header-title {
                    color: #0A5C36;
                    font-size: 24px;
                    font-weight: 800;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin: 0;
                }

                /* Hero Section */
                .hero {
                    background: linear-gradient(135deg, #0A5C36 0%, #064025 100%);
                    color: #ffffff;
                    padding: 40px 30px;
                    text-align: center;
                }

                .hero-icon {
                    font-size: 48px;
                    margin-bottom: 15px;
                    display: block;
                }

                .hero h2 {
                    margin: 0 0 10px;
                    font-size: 26px;
                    font-weight: 700;
                    color: #ffffff;
                }

                .hero p {
                    margin: 0;
                    opacity: 0.9;
                    font-size: 16px;
                    color: #e2e8f0;
                }

                /* Content */
                .content {
                    padding: 40px 30px;
                }

                .greeting {
                    font-size: 18px;
                    color: #111827;
                    margin-bottom: 20px;
                }

                /* Info Box */
                .info-box {
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    padding: 0;
                    margin: 25px 0;
                    overflow: hidden;
                }

                .info-header {
                    background-color: #0A5C36;
                    color: #ffffff;
                    padding: 12px 20px;
                    font-weight: 600;
                    font-size: 16px;
                }

                .info-table td {
                    padding: 12px 20px;
                    border-bottom: 1px solid #e2e8f0;
                    font-size: 15px;
                }

                .info-table tr:last-child td {
                    border-bottom: none;
                }

                .info-label {
                    color: #64748b;
                    font-weight: 500;
                    width: 40%;
                    background-color: #f1f5f9;
                }

                .info-value {
                    color: #0f172a;
                    font-weight: 600;
                }

                .total-row td {
                    background-color: #f0fdf4;
                    color: #0A5C36;
                    font-weight: 700;
                    font-size: 18px;
                    border-top: 2px solid #0A5C36;
                }

                /* Room Code Box */
                .code-box {
                    background: #f0fdf4;
                    border: 2px dashed #0A5C36;
                    padding: 25px;
                    text-align: center;
                    margin: 25px 0;
                    border-radius: 8px;
                }

                .code-label {
                    color: #64748b;
                    font-size: 14px;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-bottom: 10px;
                }

                .code-value {
                    color: #0A5C36;
                    font-size: 32px;
                    font-weight: 800;
                    letter-spacing: 4px;
                    margin: 0;
                    font-family: 'Courier New', monospace;
                }

                /* Buttons */
                .btn-container {
                    text-align: center;
                    margin-top: 30px;
                }

                .btn {
                    display: inline-block;
                    background-color: #0A5C36;
                    color: #ffffff !important;
                    padding: 16px 36px;
                    text-decoration: none;
                    border-radius: 50px;
                    font-weight: 700;
                    font-size: 16px;
                    box-shadow: 0 4px 6px rgba(10, 92, 54, 0.2);
                    transition: all 0.2s;
                }

                .btn:hover {
                    background-color: #084428;
                    transform: translateY(-1px);
                    box-shadow: 0 6px 8px rgba(10, 92, 54, 0.3);
                }

                /* Footer */
                .footer {
                    background-color: #1e293b;
                    color: #94a3b8;
                    padding: 40px 20px;
                    text-align: center;
                    font-size: 13px;
                    border-top: 4px solid #0A5C36;
                }

                .footer strong {
                    color: #ffffff;
                    font-size: 16px;
                    display: block;
                    margin-bottom: 10px;
                }

                .footer a {
                    color: #38bdf8;
                    text-decoration: none;
                }

                .footer-divider {
                    border: 0;
                    border-top: 1px solid #334155;
                    margin: 20px auto;
                    width: 60px;
                }

                /* Mobile */
                @media only screen and (max-width: 600px) {
                    .email-wrapper {
                        padding: 0;
                    }

                    .email-container {
                        border-radius: 0;
                        width: 100% !important;
                    }

                    .content {
                        padding: 25px 20px;
                    }

                    .header {
                        padding: 20px 15px;
                    }

                    .hero {
                        padding: 30px 20px;
                    }

                    .info-table td {
                        display: block;
                        width: 100%;
                        padding: 8px 15px;
                    }

                    .info-label {
                        background-color: transparent;
                        color: #64748b;
                        padding-bottom: 0;
                        font-size: 13px;
                        text-transform: uppercase;
                    }

                    .info-value {
                        padding-top: 5px;
                        font-size: 16px;
                    }
                }
            </style>
        </head>

        <body>
            <div class="email-wrapper">
                <div class="email-container">
                    <div class="header">
                        <?php if ($logo_url): ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>">
                        <?php else: ?>
                            <h1 class="header-title"><?php echo esc_html($site_name); ?></h1>
                        <?php endif; ?>
                    </div>
                    <?php
                    return ob_get_clean();
    }

    /**
     * Get email footer HTML
     *
     * Footer chứa:
     * - Site name
     * - Hotline (nếu có)
     * - Email contact
     * - Disclaimer text
     *
     * @since   2.0.0
     * @return  string  HTML footer
     */
    private function get_email_footer()
    {
        $hotline = get_option('vie_hotline', '');
        $site_name = $this->site_name;
        $year = date('Y');

        ob_start();
        ?>
                    <div class="footer">
                        <strong><?php echo esc_html($site_name); ?></strong>
                        <?php if ($hotline): ?>
                            <p>Hotline: <a href="tel:<?php echo esc_attr($hotline); ?>"><?php echo esc_html($hotline); ?></a></p>
                        <?php endif; ?>
                        <p>Email: <a
                                href="mailto:<?php echo esc_attr($this->from_email); ?>"><?php echo esc_html($this->from_email); ?></a>
                        </p>

                        <hr class="footer-divider">

                        <p style="opacity: 0.7;">Bạn nhận được email này vì đã đặt phòng tại
                            <?php echo esc_html($site_name); ?>.<br>
                            Vui lòng không trả lời trực tiếp email này.
                        </p>

                        <p style="margin-top: 20px; font-size: 11px; opacity: 0.5;">&copy; <?php echo $year; ?>
                            <?php echo esc_html($site_name); ?>. All rights reserved.
                        </p>
                    </div>
                </div> <!-- End email-container -->
            </div> <!-- End email-wrapper -->
        </body>

        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get booking details HTML
     *
     * Hiển thị bảng thông tin booking:
     * - Mã đặt phòng
     * - Khách sạn
     * - Loại phòng
     * - Check-in/out dates
     * - Số đêm, số phòng, số khách
     * - Tổng tiền (highlighted)
     *
     * @since   2.0.0
     * @param   object  $booking    Booking object
     * @param   string  $hotel_name Hotel name
     * @return  string              HTML table
     */
    private function get_booking_details_html($booking, $hotel_name)
    {
        $date_in = new DateTime($booking->check_in);
        $date_out = new DateTime($booking->check_out);
        $num_nights = $date_out->diff($date_in)->days;

        $total_amount = function_exists('vie_format_currency')
            ? vie_format_currency($booking->total_amount)
            : number_format($booking->total_amount) . ' VNĐ';

        ob_start();
        ?>
        <div class="info-box">
            <div class="info-header">
                Chi tiết đặt phòng
            </div>
            <table class="info-table">
                <tr>
                    <td class="info-label">Mã đặt phòng</td>
                    <td class="info-value">#<?php echo esc_html($booking->booking_code); ?></td>
                </tr>
                <tr>
                    <td class="info-label">Khách sạn</td>
                    <td class="info-value"><?php echo esc_html($hotel_name); ?></td>
                </tr>
                <tr>
                    <td class="info-label">Loại phòng</td>
                    <td class="info-value"><?php echo esc_html($booking->room_name ?? ''); ?></td>
                </tr>
                <tr>
                    <td class="info-label">Thời gian</td>
                    <td class="info-value">
                        <?php echo date('d/m/Y', strtotime($booking->check_in)); ?> -
                        <?php echo date('d/m/Y', strtotime($booking->check_out)); ?><br>
                        <span style="font-weight:normal; font-size:13px; color:#64748b;">(<?php echo $num_nights; ?> đêm)</span>
                    </td>
                </tr>
                <tr>
                    <td class="info-label">Số lượng</td>
                    <td class="info-value">
                        <?php echo $booking->num_rooms; ?> phòng<br>
                        <span style="font-weight:normal; font-size:13px; color:#64748b;">
                            (<?php echo $booking->num_adults; ?> người
                            lớn<?php echo $booking->num_children > 0 ? ', ' . $booking->num_children . ' trẻ em' : ''; ?>)
                        </span>
                    </td>
                </tr>
                <tr class="total-row">
                    <td class="info-label" style="background-color: #f0fdf4; color: #0A5C36;">Tổng thanh toán</td>
                    <td class="info-value" style="color: #0A5C36;"><?php echo $total_amount; ?></td>
                </tr>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Gửi email thông báo cho admin
     *
     * @since   2.1.0
     * @param   object  $booking    Booking object
     * @return  bool                True nếu gửi thành công
     */
    private function send_admin_notification($booking)
    {
        if (empty($this->admin_email)) {
            return false;
        }

        $hotel_name = get_the_title($booking->hotel_id);

        // Try custom template first
        $template = $this->get_custom_template('admin_notification');
        if ($template) {
            $subject = $this->replace_shortcodes($template['subject'], $booking);
            $body = $this->replace_shortcodes($template['body'], $booking);
            $message = $this->get_email_header() . wpautop($body) . $this->get_email_footer();
            return $this->send($this->admin_email, $subject, $message);
        }

        // Fallback to hardcoded template
        $subject = sprintf('[%s] Đơn đặt phòng mới - #%s', $this->site_name, $booking->booking_code);
        $message = $this->get_admin_notification_html($booking, $hotel_name);

        return $this->send($this->admin_email, $subject, $message);
    }

    /**
     * Get admin notification HTML (Fallback)
     *
     * @since   2.0.0
     * @param   object  $booking    Booking object
     * @param   string  $hotel_name Hotel name
     * @return  string              Complete HTML email
     */
    private function get_admin_notification_html($booking, $hotel_name)
    {
        $html = $this->get_email_header();

        // Hero
        $html .= '<div class="hero">';
        $html .= '<h2>Đơn đặt phòng mới</h2>';
        $html .= '<p>Có một yêu cầu đặt phòng mới cần xử lý</p>';
        $html .= '</div>';

        // Content
        $html .= '<div class="content">';
        $html .= '<div class="info-box">';
        $html .= '<div class="info-header">Thông tin khách hàng</div>';
        $html .= '<table class="info-table">';
        $html .= '<tr><td class="info-label">Họ tên</td><td class="info-value">' . esc_html($booking->customer_name) . '</td></tr>';
        $html .= '<tr><td class="info-label">Số điện thoại</td><td class="info-value"><a href="tel:' . esc_attr($booking->customer_phone) . '" style="color:#0A5C36;text-decoration:none;">' . esc_html($booking->customer_phone) . '</a></td></tr>';

        if (!empty($booking->customer_email)) {
            $html .= '<tr><td class="info-label">Email</td><td class="info-value"><a href="mailto:' . esc_attr($booking->customer_email) . '" style="color:#0A5C36;text-decoration:none;">' . esc_html($booking->customer_email) . '</a></td></tr>';
        }
        $html .= '</table></div>';

        $html .= $this->get_booking_details_html($booking, $hotel_name);

        $html .= '<div class="btn-container">';
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)) . '" class="btn">Xem chi tiết & Xử lý</a>';
        $html .= '</div>';

        $html .= '</div>'; // End content

        $html .= $this->get_email_footer();

        return $html;
    }

    /**
     * -------------------------------------------------------------------------
     * SEND METHOD
     * -------------------------------------------------------------------------
     */

    /**
     * Gửi email
     *
     * Core method để gửi email qua WordPress wp_mail().

     *
     * HEADERS:
     * - Content-Type: text/html; charset=UTF-8

     *
     * @since   2.0.0
     * @param   string  $to      Địa chỉ email người nhận
     * @param   string  $subject Tiêu đề email
     * @param   string  $message Nội dung HTML
     * @return  bool             True nếu gửi thành công (queued)
     */
    private function send($to, $subject, $message)
    {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );



        $result = wp_mail($to, $subject, $message, $headers);

        if (!$result && defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log(sprintf('[Vie Email ERROR] Failed to send email to: %s, subject: %s', $to, $subject));
        }

        return $result;
    }

    /**
     * -------------------------------------------------------------------------
     * TEMPLATE HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Get custom email template from options
     *
     * Admin có thể customize email templates trong Settings.
     * Templates được lưu trong wp_options với key 'vie_hotel_email_{type}'.
     *
     * TEMPLATE STRUCTURE:
     * [
     *   'subject' => 'Email subject with {shortcodes}',
     *   'body'    => 'Email body with {shortcodes}'
     * ]
     *
     * @since   2.0.0
     * @param   string      $type   Template type (pending|processing|completed)
     * @return  array|false         {subject, body} hoặc false nếu chưa set
     */
    private function get_custom_template($type)
    {
        $template = get_option('vie_hotel_email_' . $type, array());

        // Return false if template is empty
        if (empty($template['subject']) || empty($template['body'])) {
            return false;
        }

        return $template;
    }

    /**
     * Replace shortcodes in template
     *
     * Replace tất cả shortcodes trong content bằng giá trị thực.
     *
     * SUPPORTED SHORTCODES:
     * - {customer_name}: Tên khách hàng
     * - {booking_id}: Mã đặt phòng
     * - {hotel_name}: Tên khách sạn
     * - {hotel_address}: Địa chỉ khách sạn
     * - {room_name}: Tên loại phòng
     * - {package_type}: Loại gói (Combo/Lẻ)
     * - {bed_type}: Loại giường
     * - {check_in}: Ngày nhận phòng (dd/mm/yyyy)
     * - {check_out}: Ngày trả phòng (dd/mm/yyyy)
     * - {adults}: Số người lớn
     * - {children}: Số trẻ em
     * - {total_amount}: Tổng tiền (formatted)
     * - {room_code}: Mã nhận phòng (nếu có)
     *
     * @since   2.0.0
     * @param   string  $content Content chứa shortcodes
     * @param   object  $booking Booking object
     * @return  string           Content đã replace shortcodes
     */
    private function replace_shortcodes($content, $booking)
    {
        // Get hotel data
        $hotel_name = get_the_title($booking->hotel_id);
        $hotel_address = get_post_meta($booking->hotel_id, 'address', true) ?: '';

        // Get room data
        global $wpdb;
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}hotel_rooms WHERE id = %d",
            $booking->room_id
        ));
        $room_name = $room ? $room->name : '';
        $bed_type = $room ? ($room->bed_type ?: 'Tiêu chuẩn') : 'Tiêu chuẩn';

        // Package type
        $package_type = ($booking->price_type === 'combo') ? 'Gói Combo' : 'Đặt phòng lẻ';

        // Format total amount
        $total_amount = function_exists('vie_format_currency')
            ? vie_format_currency($booking->total_amount)
            : number_format($booking->total_amount) . ' VNĐ';

        // Replacements mapping
        $replacements = array(
            '{customer_name}' => esc_html($booking->customer_name),
            '{customer_email}' => esc_html($booking->customer_email),
            '{customer_phone}' => esc_html($booking->customer_phone),
            '{booking_id}' => esc_html($booking->booking_code),
            '{hotel_name}' => esc_html($hotel_name),
            '{hotel_address}' => esc_html($hotel_address),
            '{room_name}' => esc_html($room_name),
            '{package_type}' => esc_html($package_type),
            '{bed_type}' => esc_html($bed_type),
            '{check_in}' => date('d/m/Y', strtotime($booking->check_in)),
            '{check_out}' => date('d/m/Y', strtotime($booking->check_out)),
            '{adults}' => intval($booking->num_adults),
            '{children}' => intval($booking->num_children),
            '{total_amount}' => $total_amount,
            '{status}' => esc_html($booking->status),
            '{room_code}' => esc_html($booking->room_code ?? '(Đang cập nhật)'),
            '{admin_order_url}' => esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Alias cho code cũ vẫn dùng Vie_Email_Manager
if (!class_exists('Vie_Email_Manager')) {
    class_alias('Vie_Email_Service', 'Vie_Email_Manager');
}
