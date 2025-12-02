<?php
/**
 * ============================================================================
 * TÊN FILE: BookingsPage.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Admin Page Controller quản lý trang Bookings.
 * Xử lý routing, data fetching, và render views.
 *
 * CHỨC NĂNG CHÍNH:
 * - Hiển thị danh sách bookings với filters và pagination
 * - Xem chi tiết từng booking
 * - AJAX handlers cho update status, delete, room code
 * - Helper methods cho status badges
 *
 * PAGE CONTROLLER PATTERN:
 * - Controller: Xử lý logic và data
 * - Views: Render HTML (separated)
 * - Services: Business logic (BookingService, EmailService)
 *
 * ROUTING:
 * - ?action=list (default) → render_list()
 * - ?action=view&id=X → render_detail()
 *
 * SỬ DỤNG:
 * $page = new Vie_Admin_Bookings_Page();
 * $page->render();
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Admin/Pages
 * @version     2.1.0
 * @since       2.0.0 (Refactored to Page Controller pattern in v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Admin_Bookings_Page
 * ============================================================================
 *
 * Page Controller cho Bookings admin page.
 *
 * ARCHITECTURE:
 * - Page Controller Pattern
 * - Separation of Concerns (Logic + View)
 * - Depends on: BookingService, EmailService
 * - Views: Admin/Views/bookings/*.php
 *
 * AJAX HANDLERS:
 * - vie_update_booking_status
 * - vie_delete_booking
 * - vie_update_room_code
 *
 * @since   2.0.0
 */
class Vie_Admin_Bookings_Page
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Booking service instance
     *
     * @var Vie_Booking_Service
     */
    private $booking_service;

    /**
     * Email service instance
     *
     * @var Vie_Email_Service
     */
    private $email_service;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Initialize services và register hooks.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        // Initialize services
        $this->booking_service = Vie_Booking_Service::get_instance();
        $this->email_service   = Vie_Email_Service::get_instance();

        // Register AJAX handlers
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
        add_action('wp_ajax_vie_update_booking_status', array($this, 'ajax_update_status'));
        add_action('wp_ajax_vie_delete_booking', array($this, 'ajax_delete_booking'));
        add_action('wp_ajax_vie_update_room_code', array($this, 'ajax_update_room_code'));
    }

    /**
     * -------------------------------------------------------------------------
     * PAGE RENDERING
     * -------------------------------------------------------------------------
     */

    /**
     * Render main page (router)
     *
     * Route request đến appropriate render method.
     *
     * ACTIONS:
     * - list (default): Booking list
     * - view: Booking detail
     *
     * @since   2.0.0
     * @return  void
     */
    public function render()
    {
        $action     = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'list';
        $booking_id = isset($_GET['id']) ? absint($_GET['id']) : 0;

        switch ($action) {
            case 'view':
                $this->render_detail($booking_id);
                break;

            default:
                $this->render_list();
                break;
        }
    }

    /**
     * Render bookings list page
     *
     * Hiển thị danh sách bookings với filters và pagination.
     *
     * FILTERS:
     * - status: Filter by booking status
     * - hotel_id: Filter by hotel
     * - date_from/date_to: Filter by date range
     * - s: Search query
     *
     * PAGINATION:
     * - per_page: 20 items
     * - paged: Current page
     *
     * @since   2.0.0
     * @return  void
     */
    private function render_list()
    {
        // Get filter parameters
        $filter_status    = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $filter_hotel     = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;
        $filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
        $filter_date_to   = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
        $search           = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged            = isset($_GET['paged']) ? max(1, absint($_GET['paged'])) : 1;

        // Get bookings từ service
        $result = $this->booking_service->get_bookings_list(array(
            'status'    => $filter_status,
            'hotel_id'  => $filter_hotel,
            'date_from' => $filter_date_from,
            'date_to'   => $filter_date_to,
            'search'    => $search,
            'paged'     => $paged,
            'per_page'  => 20,
        ));

        $bookings    = $result['items'];
        $total_items = $result['total'];
        $total_pages = $result['total_pages'];

        // Get hotels cho filter dropdown
        $hotels = get_posts(array(
            'post_type'      => 'hotel',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Status labels
        $statuses = Vie_Booking_Service::$statuses;

        // Load view
        $this->load_view('bookings/list', compact(
            'bookings',
            'total_items',
            'total_pages',
            'paged',
            'hotels',
            'statuses',
            'filter_status',
            'filter_hotel',
            'filter_date_from',
            'filter_date_to',
            'search'
        ));
    }

    /**
     * Render booking detail page
     *
     * Hiển thị chi tiết 1 booking.
     *
     * @since   2.0.0
     * @param   int     $booking_id     Booking ID
     * @return  void
     */
    private function render_detail($booking_id)
    {
        // Get booking từ service
        $booking = $this->booking_service->get_booking($booking_id);

        if (!$booking) {
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            echo esc_html__('Không tìm thấy đơn đặt phòng', 'vielimousine');
            echo '</p></div></div>';
            return;
        }

        // Parse JSON fields
        $hotel_name         = get_the_title($booking->hotel_id);
        $guests_info        = json_decode($booking->guests_info, true) ?: array();
        $pricing_details    = json_decode($booking->pricing_details, true) ?: array();
        $surcharges_details = json_decode($booking->surcharges_details, true) ?: array();
        $transport_info     = json_decode($booking->transport_info ?? '', true) ?: array();
        $invoice_info       = json_decode($booking->invoice_info ?? '', true) ?: array();

        // Calculate nights
        $date_in    = new DateTime($booking->check_in);
        $date_out   = new DateTime($booking->check_out);
        $num_nights = $date_out->diff($date_in)->days;

        // Get status labels
        $statuses         = Vie_Booking_Service::$statuses;
        $payment_statuses = Vie_Booking_Service::$payment_statuses;

        // Load view
        $this->load_view('bookings/detail', compact(
            'booking',
            'hotel_name',
            'guests_info',
            'pricing_details',
            'surcharges_details',
            'transport_info',
            'invoice_info',
            'num_nights',
            'statuses',
            'payment_statuses'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX: Update booking status
     *
     * Update booking status và payment status.
     *
     * REQUEST PARAMS:
     * - booking_id: Booking ID
     * - status: New status
     * - payment_status: New payment status
     * - admin_note: Admin note
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_update_status()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        // Get params
        $booking_id     = absint($_POST['booking_id'] ?? 0);
        $status         = sanitize_text_field($_POST['status'] ?? '');
        $payment_status = sanitize_text_field($_POST['payment_status'] ?? '');
        $admin_note     = sanitize_textarea_field($_POST['admin_note'] ?? '');

        if (!$booking_id) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }

        // Update via service
        $result = $this->booking_service->update_booking($booking_id, array(
            'status'         => $status,
            'payment_status' => $payment_status,
            'admin_note'     => $admin_note,
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success(array('message' => 'Đã cập nhật'));
    }

    /**
     * AJAX: Delete booking
     *
     * Xóa booking.
     *
     * REQUEST PARAMS:
     * - booking_id: Booking ID
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_delete_booking()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        // Get params
        $booking_id = absint($_POST['booking_id'] ?? 0);

        if (!$booking_id) {
            wp_send_json_error(array('message' => 'ID không hợp lệ'));
        }

        // Delete via service
        $result = $this->booking_service->delete_booking($booking_id);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Update room code
     *
     * Update room code và gửi email cho khách.
     *
     * REQUEST PARAMS:
     * - booking_id: Booking ID
     * - room_code: Mã nhận phòng
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_update_room_code()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        // Get params
        $booking_id = absint($_POST['booking_id'] ?? 0);
        $room_code  = sanitize_text_field($_POST['room_code'] ?? '');

        if (!$booking_id || empty($room_code)) {
            wp_send_json_error(array('message' => 'Thiếu thông tin'));
        }

        // Update room code và status via service
        $result = $this->booking_service->update_booking($booking_id, array(
            'room_code' => $room_code,
            'status'    => 'completed',
        ));

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Send completion email với mã nhận phòng
        $this->email_service->send_email('room_code', $booking_id, array(
            'room_code' => $room_code,
        ));

        wp_send_json_success(array(
            'message' => 'Đã cập nhật mã nhận phòng và gửi email cho khách',
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * VIEW LOADING
     * -------------------------------------------------------------------------
     */

    /**
     * Load view template
     *
     * Load view file từ Admin/Views/ directory.
     *
     * @since   2.1.0
     * @param   string  $template   Template name (e.g., 'bookings/list')
     * @param   array   $data       Data to extract into view scope
     * @return  void
     */
    private function load_view($template, $data = array())
    {
        // Extract data into local scope
        extract($data);

        // Load view from new location
        $view_path = VIE_THEME_PATH . '/inc/admin/Views/' . $template . '.php';

        if (file_exists($view_path)) {
            include $view_path;
        } else {
            // Template not found
            echo '<div class="wrap"><div class="notice notice-error"><p>';
            echo esc_html(sprintf('View template not found: %s', $template));
            echo '</p></div></div>';
        }
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Get status badge HTML
     *
     * Generate HTML badge cho booking status.
     *
     * @since   2.0.0
     * @param   string  $status     Status code
     * @return  string              HTML badge
     */
    public static function get_status_badge($status)
    {
        $colors = array(
            'pending_payment' => '#f59e0b',
            'pending'         => '#f59e0b',
            'confirmed'       => '#3b82f6',
            'processing'      => '#8b5cf6',
            'paid'            => '#10b981',
            'completed'       => '#10b981',
            'cancelled'       => '#ef4444',
            'no_show'         => '#6b7280',
        );

        $label = Vie_Booking_Service::get_status_label($status);
        $color = $colors[$status] ?? '#6b7280';

        return sprintf(
            '<span class="vie-status-badge" style="background:%s;color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Get payment status badge HTML
     *
     * Generate HTML badge cho payment status.
     *
     * @since   2.0.0
     * @param   string  $status     Payment status code
     * @return  string              HTML badge
     */
    public static function get_payment_status_badge($status)
    {
        $colors = array(
            'unpaid'   => '#ef4444',
            'partial'  => '#f59e0b',
            'paid'     => '#10b981',
            'refunded' => '#6b7280',
        );

        $label = Vie_Booking_Service::get_payment_status_label($status);
        $color = $colors[$status] ?? '#6b7280';

        return sprintf(
            '<span class="vie-payment-badge" style="background:%s;color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Class alias for backward compatibility
if (!class_exists('Vie_Admin_Bookings')) {
    class_alias('Vie_Admin_Bookings_Page', 'Vie_Admin_Bookings');
}

// Auto-initialize (maintains original behavior)
new Vie_Admin_Bookings_Page();
