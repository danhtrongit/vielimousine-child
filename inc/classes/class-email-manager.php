<?php
/**
 * ============================================================================
 * TÊN FILE: class-email-manager.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Quản lý gửi email thông báo đặt phòng.
 * 
 * CHỨC NĂNG CHÍNH:
 * - Gửi email xác nhận đặt phòng cho khách
 * - Gửi email thông báo cho admin
 * - Gửi email mã nhận phòng

 * 
 * EMAIL TYPES:
 * - pending: Email chờ thanh toán
 * - processing: Email đang xử lý
 * - confirmed: Email xác nhận
 * - room_code: Email mã nhận phòng
 * 
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Classes
 * @version     2.0.0
 * @since       2.0.0
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Email_Manager
 * ============================================================================
 */
class Vie_Email_Manager
{

    /** @var Vie_Email_Manager|null Singleton instance */
    private static $instance = null;

    /** @var string Email admin */
    private $admin_email;

    /** @var string Tên site */
    private $site_name;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     * 
     * @since   2.0.0
     * @return  Vie_Email_Manager
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     * 
     * @since   2.0.0
     */
    private function __construct()
    {
        // Get admin emails from settings (supports multiple emails)
        $email_settings = get_option('vie_hotel_email_settings', array());
        $this->admin_email = !empty($email_settings['admin_email']) 
            ? $email_settings['admin_email'] 
            : get_option('admin_email');
        $this->site_name = get_bloginfo('name');
    }

    /**
     * -------------------------------------------------------------------------
     * PUBLIC METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Gửi email theo loại
     * 
     * @since   2.0.0
     * @param   string  $type       Loại email: pending, processing, confirmed, room_code
     * @param   int     $booking_id Booking ID
     * @param   array   $extra      Dữ liệu bổ sung (VD: room_code)
     * @return  bool
     */
    public function send_email($type, $booking_id, $extra = array())
    {
        $manager = Vie_Booking_Manager::get_instance();
        $booking = $manager->get_booking($booking_id);

        if (!$booking) {
            return false;
        }

        switch ($type) {
            case 'pending':
                return $this->send_pending_email($booking);
            case 'processing':
                return $this->send_processing_email($booking);
            case 'confirmed':
                return $this->send_confirmed_email($booking);
            case 'room_code':
                return $this->send_room_code_email($booking, $extra['room_code'] ?? '');
            default:
                return false;
        }
    }

    /**
     * Gửi email xác nhận đặt phòng (helper alias)
     * 
     * @since   2.0.0
     * @param   int     $booking_id
     * @return  bool
     */
    public function send_booking_confirmation($booking_id)
    {
        return $this->send_email('processing', $booking_id);
    }

    /**
     * Gửi email mã nhận phòng (helper alias)
     * 
     * @since   2.0.0
     * @param   int     $booking_id
     * @param   string  $room_code
     * @return  bool
     */
    public function send_room_code_email_helper($booking_id, $room_code)
    {
        return $this->send_email('room_code', $booking_id, array('room_code' => $room_code));
    }

    /**
     * -------------------------------------------------------------------------
     * PRIVATE EMAIL METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Gửi email chờ thanh toán
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @return  bool
     */
    private function send_pending_email($booking)
    {
        if (empty($booking->customer_email)) {
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
        $subject = sprintf('[%s] Xác nhận đặt phòng #%s', $this->site_name, $booking->booking_code);

        $message = $this->get_email_header();
        $message .= '<h2 style="color:#1d4ed8;">Cảm ơn bạn đã đặt phòng!</h2>';
        $message .= '<p>Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
        $message .= '<p>Chúng tôi đã nhận được yêu cầu đặt phòng của bạn. Vui lòng hoàn tất thanh toán để xác nhận.</p>';
        $message .= $this->get_booking_details_html($booking, $hotel_name);
        $message .= '<p style="text-align:center;margin-top:30px;">';
        $message .= '<a href="' . esc_url(home_url('/checkout/?booking=' . $booking->booking_hash)) . '" ';
        $message .= 'style="display:inline-block;background:#1d4ed8;color:#fff;padding:12px 30px;text-decoration:none;border-radius:6px;font-weight:bold;">';
        $message .= 'Hoàn tất thanh toán</a></p>';
        $message .= $this->get_email_footer();

        return $this->send($booking->customer_email, $subject, $message);
    }

    /**
     * Gửi email đang xử lý (sau khi thanh toán)
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @return  bool
     */
    private function send_processing_email($booking)
    {
        $hotel_name = get_the_title($booking->hotel_id);

        // Email cho khách
        if (!empty($booking->customer_email)) {
            // Try custom template first
            $template = $this->get_custom_template('processing');
            if ($template) {
                $subject = $this->replace_shortcodes($template['subject'], $booking);
                $body = $this->replace_shortcodes($template['body'], $booking);
                $message = $this->get_email_header() . wpautop($body) . $this->get_email_footer();
                $this->send($booking->customer_email, $subject, $message);
            } else {
                // Fallback
                $subject = sprintf('[%s] Đặt phòng thành công - #%s', $this->site_name, $booking->booking_code);

                $message = $this->get_email_header();
                $message .= '<h2 style="color:#10b981;">Đặt phòng thành công!</h2>';
                $message .= '<p>Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
                $message .= '<p>Đơn đặt phòng của bạn đã được xác nhận. Chúng tôi sẽ liên hệ trong thời gian sớm nhất.</p>';
                $message .= $this->get_booking_details_html($booking, $hotel_name);
                $message .= '<p><strong>Lưu ý:</strong> Mã nhận phòng sẽ được gửi qua email sau khi chúng tôi xác nhận với khách sạn.</p>';
                $message .= $this->get_email_footer();

                $this->send($booking->customer_email, $subject, $message);
            }
        }

        // Email cho admin - Try custom template first
        $admin_template = $this->get_custom_template('admin_notification');
        if ($admin_template) {
            $admin_subject = $this->replace_shortcodes($admin_template['subject'], $booking);
            $admin_body = $this->replace_shortcodes($admin_template['body'], $booking);
            $admin_message = $this->get_email_header() . wpautop($admin_body) . $this->get_email_footer();
        } else {
            // Fallback to default admin notification
            $admin_subject = sprintf('[%s] Đơn đặt phòng mới - #%s', $this->site_name, $booking->booking_code);
            $admin_message = $this->get_admin_notification_html($booking, $hotel_name);
        }

        return $this->send($this->admin_email, $admin_subject, $admin_message);
    }

    /**
     * Gửi email xác nhận hoàn tất
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @return  bool
     */
    private function send_confirmed_email($booking)
    {
        if (empty($booking->customer_email)) {
            return false;
        }

        $hotel_name = get_the_title($booking->hotel_id);
        $subject = sprintf('[%s] Xác nhận đặt phòng hoàn tất - #%s', $this->site_name, $booking->booking_code);

        $message = $this->get_email_header();
        $message .= '<h2 style="color:#10b981;">Đặt phòng đã được xác nhận!</h2>';
        $message .= '<p>Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
        $message .= '<p>Đơn đặt phòng của bạn đã được xác nhận hoàn tất.</p>';
        $message .= $this->get_booking_details_html($booking, $hotel_name);
        $message .= $this->get_email_footer();

        return $this->send($booking->customer_email, $subject, $message);
    }

    /**
     * Gửi email mã nhận phòng (completed)
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @param   string  $room_code
     * @return  bool
     */
    private function send_room_code_email($booking, $room_code)
    {
        if (empty($booking->customer_email) || empty($room_code)) {
            return false;
        }

        // Try custom template first
        $template = $this->get_custom_template('completed');
        if ($template) {
            // Add room_code to booking object temporarily
            $booking->room_code = $room_code;
            $subject = $this->replace_shortcodes($template['subject'], $booking);
            $body = $this->replace_shortcodes($template['body'], $booking);
            $message = $this->get_email_header() . wpautop($body) . $this->get_email_footer();
            return $this->send($booking->customer_email, $subject, $message);
        }

        // Fallback
        $hotel_name = get_the_title($booking->hotel_id);
        $subject = sprintf('[%s] Mã nhận phòng - #%s', $this->site_name, $booking->booking_code);

        $message = $this->get_email_header();
        $message .= '<h2 style="color:#10b981;">Mã nhận phòng của bạn!</h2>';
        $message .= '<p>Xin chào <strong>' . esc_html($booking->customer_name) . '</strong>,</p>';
        $message .= '<p>Dưới đây là mã nhận phòng của bạn:</p>';
        $message .= '<div style="background:#f0f9ff;border:2px solid #1d4ed8;padding:20px;text-align:center;margin:20px 0;border-radius:8px;">';
        $message .= '<p style="margin:0;font-size:14px;color:#666;">Mã nhận phòng</p>';
        $message .= '<p style="margin:10px 0 0;font-size:28px;font-weight:bold;color:#1d4ed8;letter-spacing:2px;">' . esc_html($room_code) . '</p>';
        $message .= '</div>';
        $message .= '<p><strong>Vui lòng xuất trình mã này khi làm thủ tục nhận phòng.</strong></p>';
        $message .= $this->get_booking_details_html($booking, $hotel_name);
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
     * @since   2.0.0
     * @return  string
     */
    private function get_email_header()
    {
        $logo_url = get_option('vie_email_logo', '');

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">';

        if ($logo_url) {
            $html .= '<div style="text-align:center;margin-bottom:30px;">';
            $html .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($this->site_name) . '" style="max-height:60px;">';
            $html .= '</div>';
        }

        return $html;
    }

    /**
     * Get email footer HTML
     * 
     * @since   2.0.0
     * @return  string
     */
    private function get_email_footer()
    {
        $hotline = get_option('vie_hotline', '');

        $html = '<hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb;">';
        $html .= '<div style="text-align:center;color:#666;font-size:13px;">';
        $html .= '<p><strong>' . esc_html($this->site_name) . '</strong></p>';

        if ($hotline) {
            $html .= '<p>Hotline: <a href="tel:' . esc_attr($hotline) . '">' . esc_html($hotline) . '</a></p>';
        }

        $html .= '<p>Email: <a href="mailto:' . esc_attr($this->admin_email) . '">' . esc_html($this->admin_email) . '</a></p>';
        $html .= '<p style="margin-top:20px;font-size:11px;color:#999;">Email này được gửi tự động, vui lòng không trả lời.</p>';
        $html .= '</div></body></html>';

        return $html;
    }

    /**
     * Get booking details HTML
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @param   string  $hotel_name
     * @return  string
     */
    private function get_booking_details_html($booking, $hotel_name)
    {
        $date_in = new DateTime($booking->check_in);
        $date_out = new DateTime($booking->check_out);
        $num_nights = $date_out->diff($date_in)->days;

        $html = '<div style="background:#f9fafb;padding:20px;border-radius:8px;margin:20px 0;">';
        $html .= '<h3 style="margin-top:0;color:#374151;">Chi tiết đặt phòng</h3>';
        $html .= '<table style="width:100%;border-collapse:collapse;">';

        $rows = array(
            'Mã đặt phòng' => '<strong>' . esc_html($booking->booking_code) . '</strong>',
            'Khách sạn' => esc_html($hotel_name),
            'Loại phòng' => esc_html($booking->room_name ?? ''),
            'Ngày nhận phòng' => date('d/m/Y', strtotime($booking->check_in)),
            'Ngày trả phòng' => date('d/m/Y', strtotime($booking->check_out)),
            'Số đêm' => $num_nights . ' đêm',
            'Số phòng' => $booking->num_rooms . ' phòng',
            'Số khách' => $booking->num_adults . ' người lớn' . ($booking->num_children > 0 ? ', ' . $booking->num_children . ' trẻ em' : ''),
            'Tổng tiền' => '<strong style="color:#1d4ed8;font-size:18px;">' . vie_format_currency($booking->total_amount) . '</strong>',
        );

        foreach ($rows as $label => $value) {
            $html .= '<tr>';
            $html .= '<td style="padding:8px 0;color:#666;width:40%;">' . esc_html($label) . ':</td>';
            $html .= '<td style="padding:8px 0;">' . $value . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></div>';

        return $html;
    }

    /**
     * Get admin notification HTML
     * 
     * @since   2.0.0
     * @param   object  $booking
     * @param   string  $hotel_name
     * @return  string
     */
    private function get_admin_notification_html($booking, $hotel_name)
    {
        $html = $this->get_email_header();
        $html .= '<h2 style="color:#1d4ed8;">Có đơn đặt phòng mới!</h2>';
        $html .= '<p><strong>Thông tin khách hàng:</strong></p>';
        $html .= '<ul>';
        $html .= '<li>Họ tên: ' . esc_html($booking->customer_name) . '</li>';
        $html .= '<li>SĐT: <a href="tel:' . esc_attr($booking->customer_phone) . '">' . esc_html($booking->customer_phone) . '</a></li>';

        if (!empty($booking->customer_email)) {
            $html .= '<li>Email: <a href="mailto:' . esc_attr($booking->customer_email) . '">' . esc_html($booking->customer_email) . '</a></li>';
        }

        $html .= '</ul>';
        $html .= $this->get_booking_details_html($booking, $hotel_name);
        $html .= '<p style="text-align:center;margin-top:30px;">';
        $html .= '<a href="' . esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)) . '" ';
        $html .= 'style="display:inline-block;background:#1d4ed8;color:#fff;padding:12px 30px;text-decoration:none;border-radius:6px;font-weight:bold;">';
        $html .= 'Xem chi tiết</a></p>';
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
     * @since   2.0.0
     * @param   string|array  $to      Địa chỉ email hoặc mảng email
     * @param   string        $subject Tiêu đề
     * @param   string        $message Nội dung HTML
     * @return  bool
     */
    private function send($to, $subject, $message)
    {
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
        );

        // Handle multiple recipients (comma-separated string or array)
        if (is_string($to) && strpos($to, ',') !== false) {
            // Split comma-separated emails and trim whitespace
            $to = array_map('trim', explode(',', $to));
        }

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * -------------------------------------------------------------------------
     * TEMPLATE HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Get custom email template from options
     * 
     * @since   2.0.0
     * @param   string  $type   pending|processing|completed
     * @return  array|false     {subject, body} or false if not set
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
     * @since   2.0.0
     * @param   string  $content    Content with shortcodes
     * @param   object  $booking    Booking object
     * @return  string
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

        // Get status text
        $status_map = array(
            'pending' => 'Chờ thanh toán',
            'processing' => 'Đang xử lý',
            'confirmed' => 'Đã xác nhận',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        );
        $status = $status_map[$booking->status] ?? $booking->status;

        // Replacements
        $replacements = array(
            '{customer_name}' => esc_html($booking->customer_name),
            '{customer_email}' => esc_html($booking->customer_email ?? ''),
            '{customer_phone}' => esc_html($booking->customer_phone ?? ''),
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
            '{room_code}' => esc_html($booking->room_code ?? '(Đang cập nhật)'),
            '{status}' => esc_html($status),
            '{admin_order_url}' => esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}
