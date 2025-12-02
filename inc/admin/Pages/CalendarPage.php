<?php
/**
 * ============================================================================
 * TÊN FILE: CalendarPage.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Admin Page Controller quản lý lịch giá phòng (Pricing Calendar).
 * Xử lý calendar view, single date pricing, và bulk updates.
 *
 * CHỨC NĂNG CHÍNH:
 * - Hiển thị calendar matrix view với FullCalendar
 * - Cập nhật giá từng ngày (single date)
 * - Bulk update giá theo khoảng ngày và ngày trong tuần
 * - Matrix grid view (all-in-one pricing grid)
 * - AJAX handlers cho calendar operations
 *
 * PAGE CONTROLLER PATTERN:
 * - Controller: Xử lý logic, database operations
 * - Views: Render HTML (separated)
 * - Direct DB access: Pricing operations (performance)
 *
 * AJAX HANDLERS:
 * - vie_get_pricing_calendar: Get calendar data
 * - vie_save_single_date_pricing: Save single date
 * - vie_bulk_update_pricing: Bulk update dates
 * - vie_get_matrix_data: Get matrix grid data
 * - vie_save_matrix_data: Save matrix grid
 *
 * SỬ DỤNG:
 * $page = new Vie_Admin_Calendar_Page();
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
 * CLASS: Vie_Admin_Calendar_Page
 * ============================================================================
 *
 * Page Controller cho Pricing Calendar admin page.
 *
 * ARCHITECTURE:
 * - Page Controller Pattern
 * - Direct database access (performance)
 * - FullCalendar integration
 * - Views: Admin/Views/calendar/*.php
 *
 * NOTE: This class uses direct DB access for performance
 * instead of PricingService due to bulk operations.
 *
 * @since   2.0.0
 */
class Vie_Admin_Calendar_Page
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Rooms table name
     *
     * @var string
     */
    private $table_rooms;

    /**
     * Pricing table name
     *
     * @var string
     */
    private $table_pricing;

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Constructor
     *
     * Initialize table names và register hooks.
     *
     * @since   2.0.0
     */
    public function __construct()
    {
        global $wpdb;

        $this->table_rooms   = $wpdb->prefix . 'hotel_rooms';
        $this->table_pricing = $wpdb->prefix . 'hotel_room_pricing';

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
        add_action('wp_ajax_vie_get_pricing_calendar', array($this, 'ajax_get_pricing_calendar'));
        add_action('wp_ajax_vie_save_single_date_pricing', array($this, 'ajax_save_single_date_pricing'));
        add_action('wp_ajax_vie_bulk_update_pricing', array($this, 'ajax_bulk_update_pricing'));
        add_action('wp_ajax_vie_get_matrix_data', array($this, 'ajax_get_matrix_data'));
        add_action('wp_ajax_vie_save_matrix_data', array($this, 'ajax_save_matrix_data'));
    }

    /**
     * -------------------------------------------------------------------------
     * PAGE RENDERING
     * -------------------------------------------------------------------------
     */

    /**
     * Render main calendar page
     *
     * Hiển thị calendar với room/hotel filters.
     *
     * QUERY PARAMS:
     * - room_id: Selected room ID
     * - hotel_id: Selected hotel ID
     *
     * @since   2.0.0
     * @return  void
     */
    public function render()
    {
        global $wpdb;

        $room_id  = isset($_GET['room_id']) ? absint($_GET['room_id']) : 0;
        $hotel_id = isset($_GET['hotel_id']) ? absint($_GET['hotel_id']) : 0;

        // Get hotels
        $hotels = get_posts(array(
            'post_type'      => 'hotel',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));

        // Get rooms và room info
        $rooms = array();
        $room  = null;

        if ($room_id > 0) {
            $room = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_rooms} WHERE id = %d",
                $room_id
            ));

            if ($room) {
                $hotel_id = $room->hotel_id;
            }
        }

        if ($hotel_id > 0) {
            $rooms = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name FROM {$this->table_rooms} WHERE hotel_id = %d AND status = 'active' ORDER BY sort_order, name",
                $hotel_id
            ));
        }

        // Load view
        $this->load_view('calendar/index', compact(
            'hotels',
            'rooms',
            'room',
            'room_id',
            'hotel_id'
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * AJAX HANDLERS
     * -------------------------------------------------------------------------
     */

    /**
     * AJAX: Get pricing calendar data
     *
     * Lấy dữ liệu pricing cho FullCalendar.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     * - start: Start date (Y-m-d)
     * - end: End date (Y-m-d)
     *
     * RESPONSE:
     * - room: Room info
     * - events: Array of FullCalendar events
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_get_pricing_calendar()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        // Get params
        $room_id = absint($_POST['room_id'] ?? 0);
        $start   = sanitize_text_field($_POST['start'] ?? date('Y-m-01'));
        $end     = sanitize_text_field($_POST['end'] ?? date('Y-m-t'));

        if ($room_id <= 0) {
            wp_send_json_error(array('message' => 'Vui lòng chọn loại phòng'));
        }

        // Get room info
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_rooms} WHERE id = %d",
            $room_id
        ));

        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        // Get pricing data
        $pricing = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_pricing} WHERE room_id = %d AND date >= %s AND date <= %s",
            $room_id,
            $start,
            $end
        ));

        // Format for FullCalendar
        $events = array();

        foreach ($pricing as $p) {
            $event = array(
                'id'            => $p->id,
                'start'         => $p->date,
                'allDay'        => true,
                'extendedProps' => array(
                    'price_room'  => floatval($p->price_room),
                    'price_combo' => floatval($p->price_combo),
                    'stock'       => intval($p->stock),
                    'booked'      => intval($p->booked),
                    'status'      => $p->status,
                    'day_of_week' => intval($p->day_of_week),
                ),
            );

            // Build title
            $titles = array();
            if ($p->price_room > 0) {
                $titles[] = 'R: ' . number_format($p->price_room / 1000) . 'k';
            }
            if ($p->price_combo > 0) {
                $titles[] = 'C: ' . number_format($p->price_combo / 1000) . 'k';
            }
            $titles[] = 'S: ' . $p->stock;

            $event['title'] = implode(' | ', $titles);

            // Set color based on status
            switch ($p->status) {
                case 'sold_out':
                    $event['color'] = '#dc3545';
                    break;
                case 'limited':
                    $event['color'] = '#ffc107';
                    break;
                case 'stop_sell':
                    $event['color'] = '#6c757d';
                    break;
                default:
                    $event['color'] = '#28a745';
            }

            $events[] = $event;
        }

        wp_send_json_success(array(
            'room'   => $room,
            'events' => $events,
            'start'  => $start,
            'end'    => $end,
        ));
    }

    /**
     * AJAX: Save single date pricing
     *
     * Lưu giá cho 1 ngày cụ thể.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     * - date: Date (Y-m-d)
     * - price_room: Room price
     * - price_combo: Combo price
     * - stock: Available stock
     * - status: Status (available, limited, sold_out, stop_sell)
     * - min_stay: Minimum stay
     * - notes: Notes
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_single_date_pricing()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        // Get params
        $room_id = absint($_POST['room_id'] ?? 0);
        $date    = sanitize_text_field($_POST['date'] ?? '');

        if ($room_id <= 0 || empty($date)) {
            wp_send_json_error(array('message' => 'Dữ liệu không hợp lệ'));
        }

        // Validate date
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj) {
            wp_send_json_error(array('message' => 'Định dạng ngày không hợp lệ'));
        }

        $day_of_week = $date_obj->format('w');

        // Get room
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT total_rooms FROM {$this->table_rooms} WHERE id = %d",
            $room_id
        ));

        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        // Prepare data
        $data = array(
            'room_id'     => $room_id,
            'date'        => $date,
            'day_of_week' => $day_of_week,
            'price_room'  => isset($_POST['price_room']) && $_POST['price_room'] !== ''
                            ? floatval($_POST['price_room'])
                            : null,
            'price_combo' => isset($_POST['price_combo']) && $_POST['price_combo'] !== ''
                            ? floatval($_POST['price_combo'])
                            : null,
            'stock'       => isset($_POST['stock']) ? absint($_POST['stock']) : $room->total_rooms,
            'status'      => in_array($_POST['status'] ?? '', array('available', 'limited', 'sold_out', 'stop_sell'))
                            ? $_POST['status']
                            : 'available',
            'min_stay'    => isset($_POST['min_stay']) ? absint($_POST['min_stay']) : 1,
            'notes'       => sanitize_textarea_field($_POST['notes'] ?? ''),
        );

        // Check existing
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_pricing} WHERE room_id = %d AND date = %s",
            $room_id,
            $date
        ));

        // Update or insert
        if ($existing) {
            $result = $wpdb->update($this->table_pricing, $data, array('id' => $existing));
        } else {
            $result = $wpdb->insert($this->table_pricing, $data);
        }

        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi lưu dữ liệu: ' . $wpdb->last_error));
        }

        wp_send_json_success(array(
            'message' => 'Đã lưu giá cho ngày ' . $date,
            'data'    => $data,
        ));
    }

    /**
     * AJAX: Bulk update pricing
     *
     * Update giá cho khoảng ngày với rules theo ngày trong tuần.
     *
     * REQUEST PARAMS:
     * - room_id: Room ID
     * - start_date: Start date
     * - end_date: End date
     * - daily_rules: Array of rules by day of week
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_bulk_update_pricing()
    {
        // Security check
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        // Get params
        $room_id     = absint($_POST['room_id'] ?? 0);
        $start_date  = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date    = sanitize_text_field($_POST['end_date'] ?? '');
        $daily_rules = isset($_POST['daily_rules']) ? (array) $_POST['daily_rules'] : array();

        if ($room_id <= 0 || empty($start_date) || empty($end_date)) {
            wp_send_json_error(array('message' => 'Dữ liệu không hợp lệ'));
        }

        // Validate dates
        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end   = DateTime::createFromFormat('Y-m-d', $end_date);

        if (!$start || !$end || $end < $start) {
            wp_send_json_error(array('message' => 'Khoảng ngày không hợp lệ'));
        }

        // Get room
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT total_rooms FROM {$this->table_rooms} WHERE id = %d",
            $room_id
        ));

        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        // Parse daily rules
        $parsed_rules = array();

        foreach ($daily_rules as $day_num => $rule) {
            $day_num = intval($day_num);
            if ($day_num < 1 || $day_num > 7 || empty($rule['enabled'])) {
                continue;
            }

            $parsed_rules[$day_num] = array(
                'price_room'  => isset($rule['price_room']) && $rule['price_room'] !== ''
                                ? floatval($rule['price_room'])
                                : null,
                'price_combo' => isset($rule['price_combo']) && $rule['price_combo'] !== ''
                                ? floatval($rule['price_combo'])
                                : null,
                'stock'       => isset($rule['stock']) ? absint($rule['stock']) : $room->total_rooms,
                'status'      => in_array($rule['status'] ?? '', array('available', 'limited', 'sold_out', 'stop_sell'))
                                ? $rule['status']
                                : 'available',
            );
        }

        if (empty($parsed_rules)) {
            wp_send_json_error(array('message' => 'Vui lòng chọn ít nhất 1 ngày trong tuần'));
        }

        // Iterate through dates và apply rules
        $updated_count = 0;
        $current       = clone $start;

        while ($current <= $end) {
            $date        = $current->format('Y-m-d');
            $day_of_week = intval($current->format('N')); // 1=Mon, 7=Sun

            if (isset($parsed_rules[$day_of_week])) {
                $rule = $parsed_rules[$day_of_week];

                $data = array(
                    'room_id'     => $room_id,
                    'date'        => $date,
                    'day_of_week' => $current->format('w'), // 0=Sun for storage
                    'price_room'  => $rule['price_room'],
                    'price_combo' => $rule['price_combo'],
                    'stock'       => $rule['stock'],
                    'status'      => $rule['status'],
                );

                // Check existing
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->table_pricing} WHERE room_id = %d AND date = %s",
                    $room_id,
                    $date
                ));

                if ($existing) {
                    $wpdb->update($this->table_pricing, $data, array('id' => $existing));
                } else {
                    $wpdb->insert($this->table_pricing, $data);
                }

                $updated_count++;
            }

            $current->modify('+1 day');
        }

        wp_send_json_success(array(
            'message' => sprintf('Đã cập nhật %d ngày', $updated_count),
            'count'   => $updated_count,
        ));
    }

    /**
     * AJAX: Get matrix data
     *
     * Placeholder for matrix view (if implemented).
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    /**
     * AJAX: Get matrix data
     *
     * Get pricing matrix data for bulk update.
     * Returns hotels, rooms, and daily pricing for specified month.
     *
     * REQUEST PARAMS:
     * - hotel_id: Hotel ID (0 = all hotels)
     * - year: Year
     * - month: Month (1-12)
     *
     * RESPONSE:
     * - hotels: Array of hotels with rooms and pricing data
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_get_matrix_data()
    {
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        // Get params
        $hotel_id = absint($_POST['hotel_id'] ?? 0);
        $year     = absint($_POST['year'] ?? date('Y'));
        $month    = absint($_POST['month'] ?? date('n'));

        // Validate
        if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
            wp_send_json_error(array('message' => 'Tháng/năm không hợp lệ'));
        }

        // Get date range
        $first_day = sprintf('%04d-%02d-01', $year, $month);
        $last_day  = date('Y-m-t', strtotime($first_day));

        // Get hotels
        $hotel_args = array(
            'post_type'      => 'hotel',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => 'publish',
        );

        if ($hotel_id > 0) {
            $hotel_args['post__in'] = array($hotel_id);
        }

        $hotels = get_posts($hotel_args);
        $result = array();

        foreach ($hotels as $hotel) {
            // Get rooms for this hotel
            $rooms = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->table_rooms}
                WHERE hotel_id = %d AND status = 'active'
                ORDER BY sort_order, name",
                $hotel->ID
            ));

            if (empty($rooms)) {
                continue; // Skip hotels with no rooms
            }

            $hotel_data = array(
                'id'    => $hotel->ID,
                'name'  => $hotel->post_title,
                'rooms' => array(),
            );

            foreach ($rooms as $room) {
                // Get pricing for this room in the month
                $pricing = $wpdb->get_results($wpdb->prepare(
                    "SELECT date, price_room, price_combo, stock, booked, status
                    FROM {$this->table_pricing}
                    WHERE room_id = %d AND date BETWEEN %s AND %s
                    ORDER BY date",
                    $room->id,
                    $first_day,
                    $last_day
                ));

                // Format pricing by date
                $dates = array();
                foreach ($pricing as $price) {
                    $dates[$price->date] = array(
                        'price_room'  => floatval($price->price_room),
                        'price_combo' => floatval($price->price_combo),
                        'stock'       => intval($price->stock),
                        'booked'      => intval($price->booked),
                        'status'      => $price->status,
                    );
                }

                $hotel_data['rooms'][] = array(
                    'id'          => intval($room->id),
                    'name'        => $room->name,
                    'total_rooms' => intval($room->total_rooms),
                    'pricing'     => $dates, // Changed from 'dates' to match frontend JS
                );
            }

            $result[] = $hotel_data;
        }

        wp_send_json_success(array(
            'hotels'     => $result,
            'first_date' => $first_day,
            'last_date'  => $last_day,
            'month'      => $month,
            'year'       => $year,
        ));
    }

    /**
     * AJAX: Save matrix data
     *
     * Save bulk pricing updates from matrix.
     * Receives array of changes and upserts to pricing table.
     *
     * REQUEST PARAMS:
     * - changes: Array of pricing changes
     *   [{ room_id, date, price_room, price_combo, stock, status }, ...]
     *
     * RESPONSE:
     * - updated: Number of records updated
     *
     * @since   2.0.0
     * @return  void    Outputs JSON response
     */
    public function ajax_save_matrix_data()
    {
        check_ajax_referer('vie_hotel_rooms_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Không có quyền'));
        }

        global $wpdb;

        // Get changes
        $changes = isset($_POST['changes']) ? $_POST['changes'] : array();

        if (empty($changes) || !is_array($changes)) {
            wp_send_json_error(array('message' => 'Không có thay đổi nào'));
        }

        $updated = 0;
        $errors  = array();

        foreach ($changes as $change) {
            $room_id     = absint($change['room_id'] ?? 0);
            $date        = sanitize_text_field($change['date'] ?? '');
            $price_room  = isset($change['price_room']) ? floatval($change['price_room']) : null;
            $price_combo = isset($change['price_combo']) ? floatval($change['price_combo']) : null;
            $stock       = isset($change['stock']) ? absint($change['stock']) : null;
            $status      = sanitize_text_field($change['status'] ?? '');

            // Validate
            if (!$room_id || !$date) {
                $errors[] = 'Invalid data';
                continue;
            }

            if (!in_array($status, array('available', 'limited', 'sold_out', 'stop_sell'))) {
                $status = 'available';
            }

            // Get day of week
            $day_of_week = date('N', strtotime($date)); // 1 (Monday) to 7 (Sunday)

            // Check if pricing exists
            $existing = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$this->table_pricing} WHERE room_id = %d AND date = %s",
                $room_id,
                $date
            ));

            $pricing_data = array(
                'price_room'  => $price_room,
                'price_combo' => $price_combo,
                'stock'       => $stock,
                'status'      => $status,
                'updated_at'  => current_time('mysql'),
            );

            if ($existing) {
                // Update
                $result = $wpdb->update(
                    $this->table_pricing,
                    $pricing_data,
                    array('id' => $existing->id)
                );
            } else {
                // Insert new
                $pricing_data['room_id']     = $room_id;
                $pricing_data['date']        = $date;
                $pricing_data['day_of_week'] = $day_of_week;

                $result = $wpdb->insert($this->table_pricing, $pricing_data);
            }

            if ($result !== false) {
                $updated++;
            } else {
                $errors[] = $wpdb->last_error;
            }
        }

        wp_send_json_success(array(
            'message' => sprintf('Đã cập nhật %d bản ghi', $updated),
            'updated' => $updated,
            'errors'  => $errors,
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
     * @param   string  $template   Template name (e.g., 'calendar/index')
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
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Class alias for backward compatibility
if (!class_exists('Vie_Admin_Calendar')) {
    class_alias('Vie_Admin_Calendar_Page', 'Vie_Admin_Calendar');
}

// Auto-initialize (maintains original behavior)
new Vie_Admin_Calendar_Page();
