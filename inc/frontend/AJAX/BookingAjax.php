<?php
/**
 * ============================================================================
 * TÊN FILE: BookingAjax.php
 * ============================================================================
 *
 * MÔ TẢ:
 * AJAX Handler cho booking operations trên frontend.
 * Xử lý: calculate price, check availability, submit booking, room detail.
 *
 * CHỨC NĂNG CHÍNH:
 * - Calculate booking price real-time
 * - Check room availability
 * - Create new booking
 * - Get room details for modal
 *
 * AJAX ENDPOINTS (4):
 * - vie_frontend_calculate_price
 * - vie_check_availability
 * - vie_submit_booking
 * - vie_get_room_detail
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Frontend/AJAX
 * @version     2.1.0
 * @since       2.0.0 (Refactored to AJAX Handler pattern in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Booking_Ajax
 * ============================================================================
 *
 * AJAX Handler cho booking operations.
 *
 * ARCHITECTURE:
 * - AJAX Handler Pattern
 * - Public endpoints (nopriv)
 * - Service layer integration
 * - Nonce verification
 *
 * @since   2.0.0
 */
class Vie_Booking_Ajax
{
    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Register AJAX endpoints.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        $this->register_ajax_handlers();
    }

    /**
     * Register AJAX handlers
     *
     * @since   2.1.0
     * @return  void
     */
    private function register_ajax_handlers()
    {
        // Calculate price
        add_action('wp_ajax_vie_frontend_calculate_price', array($this, 'calculate_price'));
        add_action('wp_ajax_nopriv_vie_frontend_calculate_price', array($this, 'calculate_price'));

        // Check availability
        add_action('wp_ajax_vie_check_availability', array($this, 'check_availability'));
        add_action('wp_ajax_nopriv_vie_check_availability', array($this, 'check_availability'));

        // Submit booking
        add_action('wp_ajax_vie_submit_booking', array($this, 'submit_booking'));
        add_action('wp_ajax_nopriv_vie_submit_booking', array($this, 'submit_booking'));

        // Get room detail
        add_action('wp_ajax_vie_get_room_detail', array($this, 'get_room_detail'));
        add_action('wp_ajax_nopriv_vie_get_room_detail', array($this, 'get_room_detail'));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: CALCULATE PRICE
     * -------------------------------------------------------------------------
     */

    /**
     * Calculate booking price real-time
     *
     * Calculate price based on dates, rooms, guests with surcharges.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     * - check_in: Check-in date (dd/mm/yyyy or Y-m-d)
     * - check_out: Check-out date
     * - num_rooms: Number of rooms
     * - num_adults: Number of adults
     * - num_children: Number of children
     * - children_ages: Array of children ages
     * - price_type: room or combo
     *
     * RESPONSE:
     * - base_amount: Base room price
     * - surcharges: Array of surcharge items
     * - surcharges_amount: Total surcharges
     * - total_amount: Grand total
     * - pricing_details: Daily pricing breakdown
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function calculate_price()
    {
        // Verify nonce
        check_ajax_referer('vie_booking_nonce', 'nonce');

        // Collect params
        $params = array(
            'room_id' => absint($_POST['room_id'] ?? 0),
            'check_in' => sanitize_text_field($_POST['check_in'] ?? ''),
            'check_out' => sanitize_text_field($_POST['check_out'] ?? ''),
            'num_rooms' => absint($_POST['num_rooms'] ?? 1),
            'num_adults' => absint($_POST['num_adults'] ?? 2),
            'num_children' => absint($_POST['num_children'] ?? 0),
            'children_ages' => isset($_POST['children_ages']) ? array_map('absint', $_POST['children_ages']) : array(),
            'price_type' => sanitize_text_field($_POST['price_type'] ?? 'room'),
        );

        // Validate required
        if (!$params['room_id'] || !$params['check_in'] || !$params['check_out']) {
            wp_send_json_error(array('message' => 'Thiếu thông tin'));
        }

        // Calculate using service
        $result = $this->calculate_booking_price($params);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Check availability
        $availability = $this->check_room_availability(
            $params['room_id'],
            $this->parse_date($params['check_in']),
            $this->parse_date($params['check_out']),
            $params['num_rooms']
        );

        if (!$availability['available']) {
            wp_send_json_error(array(
                'message' => $availability['message'],
                'unavailable_dates' => $availability['unavailable_dates']
            ));
        }

        wp_send_json_success($result);
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: CHECK AVAILABILITY
     * -------------------------------------------------------------------------
     */

    /**
     * Check room availability for hotel
     *
     * Check all rooms in hotel for availability.
     *
     * REQUEST PARAMS:
     * - hotel_id: Hotel post ID
     * - check_in: Check-in date
     * - check_out: Check-out date
     * - num_rooms: Number of rooms
     *
     * RESPONSE:
     * - rooms: Object with room_id as keys, availability status as values
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function check_availability()
    {
        check_ajax_referer('vie_booking_nonce', 'nonce');

        $hotel_id = absint($_POST['hotel_id'] ?? 0);
        $check_in = sanitize_text_field($_POST['check_in'] ?? '');
        $check_out = sanitize_text_field($_POST['check_out'] ?? '');
        $num_rooms = absint($_POST['num_rooms'] ?? 1);

        if (!$hotel_id || !$check_in || !$check_out) {
            wp_send_json_error(array('message' => 'Thiếu thông tin'));
        }

        global $wpdb;
        $table_rooms = $wpdb->prefix . 'hotel_rooms';

        // Get all rooms for hotel
        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$table_rooms} WHERE hotel_id = %d AND status = 'active'",
            $hotel_id
        ));

        $results = array();

        foreach ($rooms as $room) {
            $availability = $this->check_room_availability(
                $room->id,
                $this->parse_date($check_in),
                $this->parse_date($check_out),
                $num_rooms
            );

            $results[$room->id] = array(
                'available' => $availability['available'],
                'status' => $availability['status'],
                'message' => $availability['message']
            );
        }

        wp_send_json_success(array('rooms' => $results));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: SUBMIT BOOKING
     * -------------------------------------------------------------------------
     */

    /**
     * Submit new booking
     *
     * Create booking with customer info and pricing snapshot.
     *
     * REQUEST PARAMS:
     * - hotel_id, room_id, dates, guests
     * - customer_name, customer_phone, customer_email
     * - price_type, bed_type
     * - pricing_snapshot, surcharges_snapshot
     * - base_amount, surcharges_amount, total_amount
     * - transport_info, invoice_info (optional)
     *
     * RESPONSE:
     * - booking_id: New booking ID
     * - booking_code: Booking code
     * - booking_hash: Booking hash for checkout
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function submit_booking()
    {
        check_ajax_referer('vie_booking_nonce', 'nonce');



        // Collect booking data
        $data = array(
            'hotel_id' => absint($_POST['hotel_id'] ?? 0),
            'room_id' => absint($_POST['room_id'] ?? 0),
            'check_in' => sanitize_text_field($_POST['check_in'] ?? ''),
            'check_out' => sanitize_text_field($_POST['check_out'] ?? ''),
            'num_rooms' => absint($_POST['num_rooms'] ?? 1),
            'num_adults' => absint($_POST['num_adults'] ?? 2),
            'num_children' => absint($_POST['num_children'] ?? 0),
            'children_ages' => isset($_POST['children_ages']) ? array_map('absint', $_POST['children_ages']) : array(),
            'price_type' => sanitize_text_field($_POST['price_type'] ?? 'room'),
            'bed_type' => sanitize_text_field($_POST['bed_type'] ?? 'double'),
            'customer_name' => sanitize_text_field($_POST['customer_name'] ?? ''),
            'customer_phone' => sanitize_text_field($_POST['customer_phone'] ?? ''),
            'customer_email' => sanitize_email($_POST['customer_email'] ?? ''),
            'customer_note' => sanitize_textarea_field($_POST['customer_note'] ?? ''),
            'pricing_snapshot' => isset($_POST['pricing_snapshot']) ? $_POST['pricing_snapshot'] : array(),
            'surcharges_snapshot' => isset($_POST['surcharges_snapshot']) ? $_POST['surcharges_snapshot'] : array(),
            // Fix: Ensure proper float conversion from POST data
            'base_amount' => isset($_POST['base_amount']) ? (float) $_POST['base_amount'] : 0,
            'surcharges_amount' => isset($_POST['surcharges_amount']) ? (float) $_POST['surcharges_amount'] : 0,
            'total_amount' => isset($_POST['total_amount']) ? (float) $_POST['total_amount'] : 0,
            'coupon_code' => sanitize_text_field($_POST['coupon_code'] ?? ''),
            'discount_amount' => isset($_POST['discount_amount']) ? (float) $_POST['discount_amount'] : 0,
            'transport_info' => isset($_POST['transport_info']) ? $this->sanitize_transport_info($_POST['transport_info']) : null,
            'invoice_info' => isset($_POST['invoice_info']) ? $this->sanitize_invoice_info($_POST['invoice_info']) : null,
        );

        // Debug: Log collected data
        if (defined('VIE_DEBUG') && VIE_DEBUG) {
            error_log('[VieBooking AJAX] Collected data: base_amount=' . $data['base_amount'] . ', total_amount=' . $data['total_amount']);
        }

        // Create booking
        $result = $this->create_booking($data);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Send pending email to customer
        $this->send_booking_email($result['booking_id'], 'pending');

        // Send notification email to admin
        $this->send_admin_notification($result['booking_id']);

        // COUPON FIX: Update coupon status in Google Sheets
        if (!empty($data['coupon_code']) && class_exists('Vie_Coupon_Service')) {
            $coupon_service = Vie_Coupon_Service::get_instance();

            // Construct customer info for the sheet
            $customer_info = sprintf(
                '%s - %s',
                $data['customer_name'],
                $data['customer_phone']
            );

            // Use total_amount + discount_amount to get original total
            // Because apply_coupon might re-calculate discount based on original total
            $original_total = $data['total_amount'] + $data['discount_amount'];

            // Apply coupon (this updates the sheet)
            $coupon_result = $coupon_service->apply_coupon(
                $data['coupon_code'],
                $original_total,
                $result['booking_id'],
                $customer_info
            );

            if (defined('VIE_DEBUG') && VIE_DEBUG) {
                if ($coupon_result['valid']) {
                    error_log('[VieBooking] Coupon applied and sheet updated successfully for booking ' . $result['booking_id']);
                } else {
                    error_log('[VieBooking ERROR] Failed to update coupon sheet: ' . $coupon_result['message']);
                }
            }
        }

        wp_send_json_success(array(
            'booking_id' => $result['booking_id'],
            'booking_code' => $result['booking_code'],
            'booking_hash' => $result['booking_hash'],
            'message' => 'Đang chuyển sang trang thanh toán...'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX: GET ROOM DETAIL
     * -------------------------------------------------------------------------
     */

    /**
     * Get room detail for modal
     *
     * Get room info, gallery, amenities.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     *
     * RESPONSE:
     * - room: Room object
     * - gallery: Array of image URLs
     * - amenities: Array of amenities
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function get_room_detail()
    {
        $room_id = absint($_POST['room_id'] ?? 0);

        if (!$room_id) {
            wp_send_json_error();
        }

        global $wpdb;
        $table = $wpdb->prefix . 'hotel_rooms';
        $room = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $room_id));

        if (!$room) {
            wp_send_json_error();
        }

        // Get gallery images
        $gallery_ids = json_decode($room->gallery_ids, true) ?: array();
        $gallery = array();

        foreach ($gallery_ids as $img_id) {
            $img_url = wp_get_attachment_image_url($img_id, 'large');
            $thumb_url = wp_get_attachment_image_url($img_id, 'thumbnail');
            if ($img_url) {
                $gallery[] = array(
                    'id' => $img_id,
                    'url' => $img_url,
                    'thumb' => $thumb_url
                );
            }
        }

        // Add featured image first
        if ($room->featured_image_id) {
            $featured_url = wp_get_attachment_image_url($room->featured_image_id, 'large');
            $featured_thumb = wp_get_attachment_image_url($room->featured_image_id, 'thumbnail');
            if ($featured_url) {
                array_unshift($gallery, array(
                    'id' => $room->featured_image_id,
                    'url' => $featured_url,
                    'thumb' => $featured_thumb
                ));
            }
        }

        wp_send_json_success(array(
            'room' => $room,
            'gallery' => $gallery,
            'amenities' => json_decode($room->amenities, true) ?: array(),
            'surcharge_help' => function_exists('vie_get_surcharge_help_text') ? vie_get_surcharge_help_text($room_id) : ''
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * SERVICE INTEGRATION METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Calculate booking price using service
     *
     * @since   2.1.0
     * @param   array   $params     Booking parameters
     * @return  array|WP_Error      Price calculation result
     */
    private function calculate_booking_price($params)
    {
        // Use PricingService if available (v2.1)
        if (class_exists('Vie_Pricing_Service')) {
            $service = Vie_Pricing_Service::get_instance();
            return $service->calculate_booking_price($params);
        }

        // Fallback to old engine (backward compatibility)
        if (class_exists('Vie_Pricing_Engine')) {
            $engine = Vie_Pricing_Engine::get_instance();
            return $engine->calculate_booking_price($params);
        }

        return new WP_Error('no_pricing_service', 'Pricing service not available');
    }

    /**
     * Check room availability
     *
     * @since   2.1.0
     * @param   int     $room_id    Room ID
     * @param   string  $check_in   Check-in date (Y-m-d)
     * @param   string  $check_out  Check-out date (Y-m-d)
     * @param   int     $num_rooms  Number of rooms
     * @return  array               Availability result
     */
    private function check_room_availability($room_id, $check_in, $check_out, $num_rooms)
    {
        // Use BookingService if available (v2.1)
        if (class_exists('Vie_Booking_Service')) {
            $service = Vie_Booking_Service::get_instance();
            return $service->check_room_availability($room_id, $check_in, $check_out, $num_rooms);
        }

        // Fallback to old manager (backward compatibility)
        if (class_exists('Vie_Booking_Manager')) {
            $manager = Vie_Booking_Manager::get_instance();
            return $manager->check_room_availability($room_id, $check_in, $check_out, $num_rooms);
        }

        return array(
            'available' => false,
            'message' => 'Booking service not available'
        );
    }

    /**
     * Create booking
     *
     * @since   2.1.0
     * @param   array   $data   Booking data
     * @return  array|WP_Error  Result with booking_id, code, hash
     */
    private function create_booking($data)
    {
        // Use BookingService if available (v2.1)
        if (class_exists('Vie_Booking_Service')) {
            $service = Vie_Booking_Service::get_instance();
            return $service->create_booking($data);
        }

        // Fallback to old manager (backward compatibility)
        if (class_exists('Vie_Booking_Manager')) {
            $manager = Vie_Booking_Manager::get_instance();
            return $manager->create_booking($data);
        }

        return new WP_Error('no_booking_service', 'Booking service not available');
    }

    /**
     * Send booking email
     *
     * @since   2.1.0
     * @param   int     $booking_id     Booking ID
     * @param   string  $type           Email type (pending, processing, completed)
     * @return  void
     */
    private function send_booking_email($booking_id, $type = 'pending')
    {
        // Use EmailService if available (v2.1)
        if (class_exists('Vie_Email_Service')) {
            $service = Vie_Email_Service::get_instance();
            $service->send_email($type, $booking_id);
            return;
        }

        // Fallback to old manager (backward compatibility)
        if (class_exists('Vie_Email_Manager')) {
            $manager = Vie_Email_Manager::get_instance();
            $manager->send_email($type, $booking_id);
        }
    }

    /**
     * Send admin notification email
     *
     * @since   2.1.1
     * @param   int     $booking_id     Booking ID
     * @return  void
     */
    private function send_admin_notification($booking_id)
    {
        if (class_exists('Vie_Email_Manager')) {
            $email_manager = Vie_Email_Manager::get_instance();
            
            // Get booking details
            if (class_exists('Vie_Booking_Manager')) {
                $booking_manager = Vie_Booking_Manager::get_instance();
                $booking = $booking_manager->get_booking($booking_id);
                
                if ($booking) {
                    $hotel_name = get_the_title($booking->hotel_id);
                    
                    // Get admin emails from settings
                    $email_settings = get_option('vie_hotel_email_settings', array());
                    $admin_email = !empty($email_settings['admin_email']) 
                        ? $email_settings['admin_email'] 
                        : get_option('admin_email');
                    
                    // Try custom admin notification template first
                    $template = get_option('vie_hotel_email_admin_notification', array());
                    
                    if (!empty($template['subject']) && !empty($template['body'])) {
                        // Use custom template
                        $subject = $this->replace_email_shortcodes($template['subject'], $booking);
                        $body = $this->replace_email_shortcodes($template['body'], $booking);
                        $message = $this->get_email_wrapper($body);
                    } else {
                        // Use default template
                        $subject = sprintf('[%s] Đơn đặt phòng mới - #%s', get_bloginfo('name'), $booking->booking_code);
                        $message = $this->get_admin_notification_html($booking, $hotel_name);
                    }
                    
                    // Send email
                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    
                    // Handle multiple recipients
                    $to = $admin_email;
                    if (is_string($to) && strpos($to, ',') !== false) {
                        $to = array_map('trim', explode(',', $to));
                    }
                    
                    wp_mail($to, $subject, $message, $headers);
                }
            }
        }
    }

    /**
     * Get email wrapper HTML
     *
     * @since   2.1.1
     * @param   string  $content    Email content
     * @return  string
     */
    private function get_email_wrapper($content)
    {
        $logo_url = get_option('vie_email_logo', '');
        $site_name = get_bloginfo('name');
        $hotline = get_option('vie_hotline', '');
        $admin_email = get_option('admin_email');
        
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">';
        
        if ($logo_url) {
            $html .= '<div style="text-align:center;margin-bottom:30px;">';
            $html .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-height:60px;">';
            $html .= '</div>';
        }
        
        $html .= wpautop($content);
        
        $html .= '<hr style="margin:30px 0;border:none;border-top:1px solid #e5e7eb;">';
        $html .= '<div style="text-align:center;color:#666;font-size:13px;">';
        $html .= '<p><strong>' . esc_html($site_name) . '</strong></p>';
        
        if ($hotline) {
            $html .= '<p>Hotline: <a href="tel:' . esc_attr($hotline) . '">' . esc_html($hotline) . '</a></p>';
        }
        
        $html .= '<p>Email: <a href="mailto:' . esc_attr($admin_email) . '">' . esc_html($admin_email) . '</a></p>';
        $html .= '<p style="margin-top:20px;font-size:11px;color:#999;">Email này được gửi tự động, vui lòng không trả lời.</p>';
        $html .= '</div></body></html>';
        
        return $html;
    }

    /**
     * Get admin notification HTML
     *
     * @since   2.1.1
     * @param   object  $booking        Booking object
     * @param   string  $hotel_name     Hotel name
     * @return  string
     */
    private function get_admin_notification_html($booking, $hotel_name)
    {
        $html = $this->get_email_wrapper('<h2 style="color:#1d4ed8;">Có đơn đặt phòng mới!</h2>
<p><strong>Thông tin khách hàng:</strong></p>
<ul>
<li>Họ tên: ' . esc_html($booking->customer_name) . '</li>
<li>SĐT: <a href="tel:' . esc_attr($booking->customer_phone) . '">' . esc_html($booking->customer_phone) . '</a></li>' .
(!empty($booking->customer_email) ? '<li>Email: <a href="mailto:' . esc_attr($booking->customer_email) . '">' . esc_html($booking->customer_email) . '</a></li>' : '') .
'</ul>
<div style="background:#f9fafb;padding:20px;border-radius:8px;margin:20px 0;">
<h3 style="margin-top:0;color:#374151;">Chi tiết đặt phòng</h3>
<ul>
<li><strong>Mã đặt phòng:</strong> ' . esc_html($booking->booking_code) . '</li>
<li><strong>Khách sạn:</strong> ' . esc_html($hotel_name) . '</li>
<li><strong>Loại phòng:</strong> ' . esc_html($booking->room_name ?? '') . '</li>
<li><strong>Ngày nhận phòng:</strong> ' . date('d/m/Y', strtotime($booking->check_in)) . '</li>
<li><strong>Ngày trả phòng:</strong> ' . date('d/m/Y', strtotime($booking->check_out)) . '</li>
<li><strong>Số phòng:</strong> ' . $booking->num_rooms . ' phòng</li>
<li><strong>Số khách:</strong> ' . $booking->num_adults . ' người lớn' . ($booking->num_children > 0 ? ', ' . $booking->num_children . ' trẻ em' : '') . '</li>
<li><strong>Tổng tiền:</strong> <strong style="color:#1d4ed8;font-size:18px;">' . (function_exists('vie_format_currency') ? vie_format_currency($booking->total_amount) : number_format($booking->total_amount) . ' VNĐ') . '</strong></li>
</ul>
</div>
<p style="text-align:center;margin-top:30px;">
<a href="' . esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)) . '" style="display:inline-block;background:#1d4ed8;color:#fff;padding:12px 30px;text-decoration:none;border-radius:6px;font-weight:bold;">Xem chi tiết</a>
</p>');
        
        return $html;
    }

    /**
     * Replace email shortcodes
     *
     * @since   2.1.1
     * @param   string  $content    Content with shortcodes
     * @param   object  $booking    Booking object
     * @return  string
     */
    private function replace_email_shortcodes($content, $booking)
    {
        $hotel_name = get_the_title($booking->hotel_id);
        
        $replacements = array(
            '{customer_name}' => esc_html($booking->customer_name),
            '{customer_email}' => esc_html($booking->customer_email ?? ''),
            '{customer_phone}' => esc_html($booking->customer_phone ?? ''),
            '{booking_id}' => esc_html($booking->booking_code),
            '{hotel_name}' => esc_html($hotel_name),
            '{room_name}' => esc_html($booking->room_name ?? ''),
            '{check_in}' => date('d/m/Y', strtotime($booking->check_in)),
            '{check_out}' => date('d/m/Y', strtotime($booking->check_out)),
            '{adults}' => intval($booking->num_adults),
            '{children}' => intval($booking->num_children),
            '{total_amount}' => function_exists('vie_format_currency') ? vie_format_currency($booking->total_amount) : number_format($booking->total_amount) . ' VNĐ',
            '{status}' => esc_html($booking->status),
            '{admin_order_url}' => esc_url(admin_url('admin.php?page=vie-hotel-bookings&action=view&id=' . $booking->id)),
        );
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Parse date from various formats
     *
     * @since   2.0.0
     * @param   string  $date_str   Date string
     * @return  string              Date in Y-m-d format
     */
    private function parse_date($date_str)
    {
        // Try dd/mm/yyyy format first
        $date = DateTime::createFromFormat('d/m/Y', $date_str);

        if (!$date) {
            // Try Y-m-d format
            $date = DateTime::createFromFormat('Y-m-d', $date_str);
        }

        return $date ? $date->format('Y-m-d') : $date_str;
    }

    /**
     * Sanitize transport info
     *
     * @since   2.0.0
     * @param   array   $transport_info     Raw transport info
     * @return  array|null
     */
    private function sanitize_transport_info($transport_info)
    {
        if (empty($transport_info) || !is_array($transport_info)) {
            return null;
        }

        return array(
            'enabled' => !empty($transport_info['enabled']),
            'pickup_time' => isset($transport_info['pickup_time']) ? sanitize_text_field($transport_info['pickup_time']) : '',
            'dropoff_time' => isset($transport_info['dropoff_time']) ? sanitize_text_field($transport_info['dropoff_time']) : '',
            'note' => isset($transport_info['note']) ? sanitize_textarea_field($transport_info['note']) : '',
        );
    }

    /**
     * Sanitize invoice info
     *
     * @since   2.0.0
     * @param   array   $invoice_info   Raw invoice info
     * @return  array|null
     */
    private function sanitize_invoice_info($invoice_info)
    {
        if (empty($invoice_info) || !is_array($invoice_info)) {
            return null;
        }

        return array(
            'company_name' => isset($invoice_info['company_name']) ? sanitize_text_field($invoice_info['company_name']) : '',
            'tax_id' => isset($invoice_info['tax_id']) ? sanitize_text_field($invoice_info['tax_id']) : '',
            'email' => isset($invoice_info['email']) ? sanitize_email($invoice_info['email']) : '',
        );
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Auto-initialize
new Vie_Booking_Ajax();
