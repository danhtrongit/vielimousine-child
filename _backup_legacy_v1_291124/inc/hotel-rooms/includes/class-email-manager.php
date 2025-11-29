<?php
/**
 * Email Manager
 * 
 * Quản lý gửi email và templates
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Email_Manager
{

    /**
     * Instance
     */
    private static $instance = null;

    /**
     * Get instance
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
     */
    private function __construct()
    {
        // Init settings if needed
    }

    /**
     * Get email template
     * 
     * @param string $type pending|processing|completed
     * @return array {subject, body}
     */
    public function get_template($type)
    {
        $defaults = $this->get_default_templates();
        $saved = get_option('vie_hotel_email_' . $type, array());

        return wp_parse_args($saved, $defaults[$type] ?? array('subject' => '', 'body' => ''));
    }

    /**
     * Send email
     * 
     * @param string $type pending|processing|completed
     * @param int $booking_id
     * @return bool
     */
    public function send_email($type, $booking_id)
    {
        $booking = $this->get_booking_data($booking_id);
        if (!$booking) {
            return false;
        }

        $template = $this->get_template($type);
        if (empty($template['subject']) || empty($template['body'])) {
            return false;
        }

        // Replace shortcodes
        $subject = $this->replace_shortcodes($template['subject'], $booking);
        $body = $this->replace_shortcodes($template['body'], $booking);

        // Add header/footer to body (optional, simple wrapper)
        $body = wpautop($body);

        $to = $booking->customer_email;
        $headers = array('Content-Type: text/html; charset=UTF-8');

        // Send to customer
        $sent = wp_mail($to, $subject, $body, $headers);

        // Send copy to admin for 'pending'
        if ($type === 'pending') {
            $admin_email = get_option('admin_email');
            $admin_subject = "[Admin Notification] " . $subject;
            wp_mail($admin_email, $admin_subject, $body, $headers);
        }

        return $sent;
    }

    /**
     * Replace shortcodes
     */
    private function replace_shortcodes($content, $booking)
    {
        $hotel_name = get_the_title($booking->hotel_id);
        $hotel_address = get_post_meta($booking->hotel_id, 'address', true) ?: 'Đang cập nhật';

        // Get room details
        global $wpdb;
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}hotel_rooms WHERE id = %d", $booking->room_id));
        $room_name = $room ? $room->name : '';
        $bed_type = $room ? ($room->bed_type ?: 'Tiêu chuẩn') : 'Tiêu chuẩn';

        // Package Type
        $package_type = ($booking->price_type === 'combo') ? 'Gói Combo' : 'Đặt phòng lẻ';

        // Payment QR
        $payment_qr = ''; // Placeholder

        $replacements = array(
            '{customer_name}' => $booking->customer_name,
            '{booking_id}' => $booking->booking_code,
            '{hotel_name}' => $hotel_name,
            '{hotel_address}' => $hotel_address,
            '{room_name}' => $room_name,
            '{package_type}' => $package_type,
            '{bed_type}' => $bed_type,
            '{adults}' => $booking->num_adults,
            '{children}' => $booking->num_children,
            '{check_in}' => date('d/m/Y', strtotime($booking->check_in)),
            '{check_out}' => date('d/m/Y', strtotime($booking->check_out)),
            '{room_code}' => $booking->room_code ?? '(Đang cập nhật)',
            '{total_amount}' => Vie_Hotel_Rooms_Helpers::format_currency($booking->total_amount),
            '{payment_qr}' => $payment_qr
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Get booking data
     */
    private function get_booking_data($booking_id)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hotel_bookings';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
    }

    /**
     * Default templates (Modern Card Layout)
     */
    private function get_default_templates()
    {
        $common_style = "font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; line-height: 1.6; color: #333;";
        $wrapper_style = "background-color: #f4f4f4; padding: 20px;";
        $container_style = "max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05);";
        $header_style = "background-color: #003580; color: #ffffff; padding: 20px; text-align: center;";
        $body_style = "padding: 20px;";
        $table_style = "width: 100%; border-collapse: collapse; margin-bottom: 20px;";
        $td_label_style = "padding: 8px; border-bottom: 1px solid #eee; color: #666; width: 40%;";
        $td_value_style = "padding: 8px; border-bottom: 1px solid #eee; font-weight: 600; color: #333;";
        $footer_style = "background-color: #f9f9f9; padding: 15px; text-align: center; font-size: 12px; color: #888;";
        $btn_style = "display: inline-block; padding: 10px 20px; background-color: #e03d25; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: bold;";

        // Common HTML Parts
        $header_html = "
            <div style='{$header_style}'>
                <h2 style='margin:0; font-size: 20px;'>{STATUS_TITLE}</h2>
            </div>
        ";

        $info_table_html = "
            <table style='{$table_style}'>
                <tr>
                    <td style='{$td_label_style}'>Khách sạn:</td>
                    <td style='{$td_value_style}'>{hotel_name}<br><span style='font-size:12px; font-weight:normal; color:#888;'>{hotel_address}</span></td>
                </tr>
                <tr>
                    <td style='{$td_label_style}'>Mã đơn hàng:</td>
                    <td style='{$td_value_style}'>#{booking_id}</td>
                </tr>
                <tr>
                    <td style='{$td_label_style}'>Loại phòng:</td>
                    <td style='{$td_value_style}'>{room_name}</td>
                </tr>
                <tr>
                    <td style='{$td_label_style}'>Gói dịch vụ:</td>
                    <td style='{$td_value_style}'><span style='color: #e03d25;'>{package_type}</span></td>
                </tr>
                <tr>
                    <td style='{$td_label_style}'>Loại giường:</td>
                    <td style='{$td_value_style}'>{bed_type}</td>
                </tr>
                <tr>
                    <td style='{$td_label_style}'>Thời gian:</td>
                    <td style='{$td_value_style}'>Check-in: {check_in}<br>Check-out: {check_out}</td>
                </tr>
                <tr>
                    <td style='{$td_label_style}'>Khách:</td>
                    <td style='{$td_value_style}'>{adults} Người lớn, {children} Trẻ em</td>
                </tr>
            </table>
        ";

        $pricing_html = "
            <div style='background: #fdfdfd; border: 1px solid #eee; border-radius: 4px; padding: 15px; margin-bottom: 20px;'>
                <table style='width: 100%;'>
                    <tr>
                        <td style='padding: 5px 0; font-weight: bold; font-size: 16px;'>Tổng thanh toán:</td>
                        <td style='padding: 5px 0; text-align: right; font-weight: bold; font-size: 18px; color: #e03d25;'>{total_amount}</td>
                    </tr>
                </table>
            </div>
        ";

        $footer_html = "
            <div style='{$footer_style}'>
                <p>Cần hỗ trợ? Liên hệ: <a href='mailto:info@vietnew-entertainment.com.vn' style='color:#003580;'>info@vietnew-entertainment.com.vn</a></p>
                <p>Chính sách hủy phòng: Vui lòng xem chi tiết trên website.</p>
            </div>
        ";

        return array(
            'pending' => array(
                'subject' => 'Xác nhận tiếp nhận đặt phòng #{booking_id}',
                'body' => "
                    <div style=\"{$common_style}\">
                        <div style=\"{$wrapper_style}\">
                            <div style=\"{$container_style}\">
                                " . str_replace('{STATUS_TITLE}', 'VUI LÒNG THANH TOÁN', $header_html) . "
                                <div style=\"{$body_style}\">
                                    <p>Xin chào <strong>{customer_name}</strong>,</p>
                                    <p>Cảm ơn bạn đã lựa chọn <strong>{hotel_name}</strong>. Đơn đặt phòng của bạn đang ở trạng thái <strong>Chờ thanh toán</strong>.</p>
                                    
                                    {$info_table_html}
                                    {$pricing_html}
                                    
                                    <div style='text-align: center; margin-top: 20px;'>
                                        <a href='" . home_url('/checkout/') . "?booking={booking_id}' style='{$btn_style}'>Thanh toán ngay</a>
                                    </div>
                                </div>
                                {$footer_html}
                            </div>
                        </div>
                    </div>
                "
            ),
            'processing' => array(
                'subject' => 'Đã nhận thanh toán - Đang xử lý #{booking_id}',
                'body' => "
                    <div style=\"{$common_style}\">
                        <div style=\"{$wrapper_style}\">
                            <div style=\"{$container_style}\">
                                " . str_replace('{STATUS_TITLE}', 'ĐANG XỬ LÝ', $header_html) . "
                                <div style=\"{$body_style}\">
                                    <p>Xin chào <strong>{customer_name}</strong>,</p>
                                    <p>Chúng tôi đã nhận được thanh toán cho đơn hàng <strong>#{booking_id}</strong>.</p>
                                    <p>Hệ thống đang liên hệ với khách sạn để lấy <strong>Mã nhận phòng</strong>. Vui lòng chờ email xác nhận trong ít phút.</p>
                                    
                                    {$info_table_html}
                                    {$pricing_html}
                                </div>
                                {$footer_html}
                            </div>
                        </div>
                    </div>
                "
            ),
            'completed' => array(
                'subject' => 'Xác nhận đặt phòng thành công - Mã: {room_code}',
                'body' => "
                    <div style=\"{$common_style}\">
                        <div style=\"{$wrapper_style}\">
                            <div style=\"{$container_style}\">
                                " . str_replace('{STATUS_TITLE}', 'ĐẶT PHÒNG THÀNH CÔNG', $header_html) . "
                                <div style=\"{$body_style}\">
                                    <p>Xin chào <strong>{customer_name}</strong>,</p>
                                    <p>Chúc mừng! Đơn đặt phòng của bạn tại <strong>{hotel_name}</strong> đã được xác nhận.</p>
                                    
                                    <div style='background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; padding: 15px; border-radius: 4px; text-align: center; margin-bottom: 20px;'>
                                        <div style='font-size: 13px; text-transform: uppercase; letter-spacing: 1px;'>Mã nhận phòng</div>
                                        <div style='font-size: 24px; font-weight: bold; margin-top: 5px;'>{room_code}</div>
                                    </div>

                                    {$info_table_html}
                                    
                                    <p>Vui lòng xuất trình Mã nhận phòng này cho lễ tân khi làm thủ tục check-in.</p>
                                </div>
                                {$footer_html}
                            </div>
                        </div>
                    </div>
                "
            )
        );
    }
}
