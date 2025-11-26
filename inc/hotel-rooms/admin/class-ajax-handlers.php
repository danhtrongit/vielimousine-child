<?php
/**
 * AJAX Handlers for Hotel Rooms Module
 * 
 * Xử lý tất cả AJAX requests từ Admin
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Ajax
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
        // Room CRUD
        add_action('wp_ajax_vie_save_room', array($this, 'save_room'));
        add_action('wp_ajax_vie_delete_room', array($this, 'delete_room'));
        add_action('wp_ajax_vie_get_room', array($this, 'get_room'));
        add_action('wp_ajax_vie_get_rooms_by_hotel', array($this, 'get_rooms_by_hotel'));

        // Surcharges
        add_action('wp_ajax_vie_save_surcharges', array($this, 'save_surcharges'));

        // Pricing/Calendar
        add_action('wp_ajax_vie_get_pricing_calendar', array($this, 'get_pricing_calendar'));
        add_action('wp_ajax_vie_save_single_date_pricing', array($this, 'save_single_date_pricing'));
        add_action('wp_ajax_vie_bulk_update_pricing', array($this, 'bulk_update_pricing'));

        // Price calculation
        add_action('wp_ajax_vie_calculate_price', array($this, 'calculate_price'));
        add_action('wp_ajax_nopriv_vie_calculate_price', array($this, 'calculate_price'));
    }

    /**
     * Verify nonce
     */
    private function verify_nonce()
    {
        if (!check_ajax_referer('vie_hotel_rooms_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
    }

    /**
     * Save room (create or update)
     */
    public function save_room()
    {
        $this->verify_nonce();

        global $wpdb;
        $table_rooms = $wpdb->prefix . 'hotel_rooms';

        // Get and sanitize data
        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $data = Vie_Hotel_Rooms_Helpers::sanitize_room_data($_POST);

        // Validation
        if (empty($data['name'])) {
            wp_send_json_error(array('message' => 'Tên phòng không được để trống'));
        }

        if ($data['hotel_id'] <= 0) {
            wp_send_json_error(array('message' => 'Vui lòng chọn khách sạn'));
        }

        if ($data['base_price'] < 0) {
            wp_send_json_error(array('message' => 'Giá không được âm'));
        }

        if ($data['max_adults'] < $data['base_occupancy']) {
            wp_send_json_error(array('message' => 'Số người lớn tối đa phải >= số người tiêu chuẩn'));
        }

        if ($room_id > 0) {
            // Update existing room
            $result = $wpdb->update(
                $table_rooms,
                $data,
                array('id' => $room_id),
                null,
                array('%d')
            );

            if ($result === false) {
                wp_send_json_error(array('message' => 'Lỗi cập nhật: ' . $wpdb->last_error));
            }

            $message = 'Cập nhật loại phòng thành công!';
        } else {
            // Insert new room
            $result = $wpdb->insert($table_rooms, $data);

            if ($result === false) {
                wp_send_json_error(array('message' => 'Lỗi thêm mới: ' . $wpdb->last_error));
            }

            $room_id = $wpdb->insert_id;
            $message = 'Thêm loại phòng mới thành công!';
        }

        // Save surcharges if provided
        if (isset($_POST['surcharges']) && is_array($_POST['surcharges'])) {
            $this->save_room_surcharges($room_id, $_POST['surcharges']);
        }

        wp_send_json_success(array(
            'message' => $message,
            'room_id' => $room_id
        ));
    }

    /**
     * Save surcharges for a room
     */
    private function save_room_surcharges($room_id, $surcharges)
    {
        global $wpdb;
        $table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';

        // Delete existing surcharges
        $wpdb->delete($table_surcharges, array('room_id' => $room_id), array('%d'));

        // Insert new surcharges
        foreach ($surcharges as $surcharge) {
            if (empty($surcharge['surcharge_type'])) {
                continue;
            }

            $data = array(
                'room_id' => $room_id,
                'surcharge_type' => sanitize_text_field($surcharge['surcharge_type']),
                'label' => sanitize_text_field($surcharge['label'] ?? ''),
                'min_age' => isset($surcharge['min_age']) && $surcharge['min_age'] !== ''
                    ? absint($surcharge['min_age']) : null,
                'max_age' => isset($surcharge['max_age']) && $surcharge['max_age'] !== ''
                    ? absint($surcharge['max_age']) : null,
                'amount' => floatval($surcharge['amount'] ?? 0),
                'amount_type' => in_array($surcharge['amount_type'] ?? '', array('fixed', 'percent'))
                    ? $surcharge['amount_type'] : 'fixed',
                'is_per_night' => isset($surcharge['is_per_night']) ? 1 : 0,
                'is_mandatory' => isset($surcharge['is_mandatory']) ? 1 : 0,
                'applies_to_combo' => isset($surcharge['applies_to_combo']) ? 1 : 0,
                'applies_to_room' => isset($surcharge['applies_to_room']) ? 1 : 0,
                'notes' => sanitize_textarea_field($surcharge['notes'] ?? ''),
                'sort_order' => intval($surcharge['sort_order'] ?? 0),
                'status' => 'active'
            );

            $wpdb->insert($table_surcharges, $data);
        }
    }

    /**
     * Delete room
     */
    public function delete_room()
    {
        $this->verify_nonce();

        global $wpdb;

        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;

        if ($room_id <= 0) {
            wp_send_json_error(array('message' => 'ID phòng không hợp lệ'));
        }

        // Delete surcharges
        $wpdb->delete(
            $wpdb->prefix . 'hotel_room_surcharges',
            array('room_id' => $room_id),
            array('%d')
        );

        // Delete pricing
        $wpdb->delete(
            $wpdb->prefix . 'hotel_room_pricing',
            array('room_id' => $room_id),
            array('%d')
        );

        // Delete room
        $result = $wpdb->delete(
            $wpdb->prefix . 'hotel_rooms',
            array('id' => $room_id),
            array('%d')
        );

        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi xóa phòng'));
        }

        wp_send_json_success(array('message' => 'Đã xóa loại phòng!'));
    }

    /**
     * Get single room data
     */
    public function get_room()
    {
        $this->verify_nonce();

        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;

        if ($room_id <= 0) {
            wp_send_json_error(array('message' => 'ID phòng không hợp lệ'));
        }

        $room = Vie_Hotel_Rooms_Helpers::get_room($room_id);

        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        $surcharges = Vie_Hotel_Rooms_Helpers::get_room_surcharges($room_id);

        wp_send_json_success(array(
            'room' => $room,
            'surcharges' => $surcharges
        ));
    }

    /**
     * Get rooms by hotel
     */
    public function get_rooms_by_hotel()
    {
        $this->verify_nonce();

        $hotel_id = isset($_POST['hotel_id']) ? absint($_POST['hotel_id']) : 0;

        $rooms = Vie_Hotel_Rooms_Helpers::get_rooms_by_hotel($hotel_id);

        wp_send_json_success(array('rooms' => $rooms));
    }

    /**
     * Get pricing calendar data
     */
    public function get_pricing_calendar()
    {
        $this->verify_nonce();

        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $start = isset($_POST['start']) ? sanitize_text_field($_POST['start']) : date('Y-m-01');
        $end = isset($_POST['end']) ? sanitize_text_field($_POST['end']) : date('Y-m-t');

        if ($room_id <= 0) {
            wp_send_json_error(array('message' => 'Vui lòng chọn loại phòng'));
        }

        $room = Vie_Hotel_Rooms_Helpers::get_room($room_id);
        $pricing = Vie_Hotel_Rooms_Helpers::get_room_pricing($room_id, $start, $end);

        // Format data for FullCalendar
        $events = array();

        foreach ($pricing as $p) {
            $event = array(
                'id' => $p->id,
                'start' => $p->date,
                'allDay' => true,
                'extendedProps' => array(
                    'price_room' => floatval($p->price_room),
                    'price_combo' => floatval($p->price_combo),
                    'stock' => intval($p->stock),
                    'booked' => intval($p->booked),
                    'status' => $p->status,
                    'day_of_week' => intval($p->day_of_week)
                )
            );

            // Set title based on prices
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
            'room' => $room,
            'events' => $events,
            'start' => $start,
            'end' => $end
        ));
    }

    /**
     * Save single date pricing
     */
    public function save_single_date_pricing()
    {
        $this->verify_nonce();

        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';

        if ($room_id <= 0 || empty($date)) {
            wp_send_json_error(array('message' => 'Dữ liệu không hợp lệ'));
        }

        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $date);
        if (!$date_obj) {
            wp_send_json_error(array('message' => 'Định dạng ngày không hợp lệ'));
        }

        $day_of_week = $date_obj->format('w'); // 0=Sunday, 6=Saturday

        // Get room for total_rooms
        $room = Vie_Hotel_Rooms_Helpers::get_room($room_id);
        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        $data = array(
            'room_id' => $room_id,
            'date' => $date,
            'day_of_week' => $day_of_week,
            'price_room' => isset($_POST['price_room']) && $_POST['price_room'] !== ''
                ? floatval($_POST['price_room']) : null,
            'price_combo' => isset($_POST['price_combo']) && $_POST['price_combo'] !== ''
                ? floatval($_POST['price_combo']) : null,
            'stock' => isset($_POST['stock']) ? absint($_POST['stock']) : $room->total_rooms,
            'status' => in_array($_POST['status'] ?? '', array('available', 'limited', 'sold_out', 'stop_sell'))
                ? $_POST['status'] : 'available',
            'min_stay' => isset($_POST['min_stay']) ? absint($_POST['min_stay']) : 1,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '')
        );

        // Validate prices
        if ($data['price_room'] !== null && $data['price_room'] < 0) {
            wp_send_json_error(array('message' => 'Giá phòng không được âm'));
        }
        if ($data['price_combo'] !== null && $data['price_combo'] < 0) {
            wp_send_json_error(array('message' => 'Giá combo không được âm'));
        }

        // Check if record exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_pricing} WHERE room_id = %d AND date = %s",
            $room_id,
            $date
        ));

        if ($existing) {
            // Update
            $result = $wpdb->update(
                $table_pricing,
                $data,
                array('id' => $existing)
            );
        } else {
            // Insert
            $result = $wpdb->insert($table_pricing, $data);
        }

        if ($result === false) {
            wp_send_json_error(array('message' => 'Lỗi lưu dữ liệu: ' . $wpdb->last_error));
        }

        wp_send_json_success(array(
            'message' => 'Đã lưu giá cho ngày ' . $date,
            'data' => $data
        ));
    }

    /**
     * Bulk update pricing with daily rules (critical feature)
     * 
     * Nhận daily_rules: mảng cấu hình cho từng ngày trong tuần (1=T2 ... 7=CN)
     * Mỗi rule gồm: enabled, price_room, price_combo, stock, status
     * 
     * Sử dụng Batch INSERT ... ON DUPLICATE KEY UPDATE để tối ưu hiệu năng
     */
    public function bulk_update_pricing()
    {
        $this->verify_nonce();

        global $wpdb;
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';

        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : '';
        $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : '';
        $daily_rules = isset($_POST['daily_rules']) ? (array) $_POST['daily_rules'] : array();

        if ($room_id <= 0 || empty($start_date) || empty($end_date)) {
            wp_send_json_error(array('message' => 'Dữ liệu không hợp lệ'));
        }

        // Validate dates
        $start = DateTime::createFromFormat('Y-m-d', $start_date);
        $end = DateTime::createFromFormat('Y-m-d', $end_date);

        if (!$start || !$end || $end < $start) {
            wp_send_json_error(array('message' => 'Khoảng ngày không hợp lệ'));
        }

        // Get room info
        $room = Vie_Hotel_Rooms_Helpers::get_room($room_id);
        if (!$room) {
            wp_send_json_error(array('message' => 'Không tìm thấy phòng'));
        }

        // Parse và validate daily_rules
        // daily_rules[day_num] = { enabled, price_room, price_combo, stock, status }
        // day_num: 1=Thứ 2, 2=Thứ 3, ..., 7=Chủ nhật (tương ứng với date('N'))
        $parsed_rules = array();
        foreach ($daily_rules as $day_num => $rule) {
            $day_num = intval($day_num);
            if ($day_num < 1 || $day_num > 7) continue;
            if (empty($rule['enabled'])) continue;
            
            $parsed_rules[$day_num] = array(
                'price_room' => isset($rule['price_room']) && $rule['price_room'] !== '' 
                    ? floatval($rule['price_room']) : null,
                'price_combo' => isset($rule['price_combo']) && $rule['price_combo'] !== '' 
                    ? floatval($rule['price_combo']) : null,
                'stock' => isset($rule['stock']) && $rule['stock'] !== '' 
                    ? absint($rule['stock']) : null,
                'status' => isset($rule['status']) && !empty($rule['status']) 
                    && in_array($rule['status'], array('available', 'stop_sell'))
                    ? sanitize_text_field($rule['status']) : null
            );
            
            // Validate giá không âm
            if ($parsed_rules[$day_num]['price_room'] !== null && $parsed_rules[$day_num]['price_room'] < 0) {
                wp_send_json_error(array('message' => 'Giá phòng không được âm'));
            }
            if ($parsed_rules[$day_num]['price_combo'] !== null && $parsed_rules[$day_num]['price_combo'] < 0) {
                wp_send_json_error(array('message' => 'Giá combo không được âm'));
            }
        }

        if (empty($parsed_rules)) {
            wp_send_json_error(array('message' => 'Không có ngày nào được chọn để cập nhật'));
        }

        // Collect all dates data for batch insert
        $batch_data = array();
        $current = clone $start;

        while ($current <= $end) {
            // date('N'): 1=Monday, ..., 7=Sunday
            $current_dow_n = intval($current->format('N'));
            
            // Kiểm tra ngày này có trong daily_rules không
            if (!isset($parsed_rules[$current_dow_n])) {
                $current->modify('+1 day');
                continue;
            }
            
            $rule = $parsed_rules[$current_dow_n];
            $date_str = $current->format('Y-m-d');
            
            // Chỉ thêm nếu rule có ít nhất 1 giá trị để cập nhật
            if ($rule['price_room'] !== null || $rule['price_combo'] !== null 
                || $rule['stock'] !== null || $rule['status'] !== null) {
                
                // day_of_week for DB uses format('w'): 0=Sunday, 6=Saturday
                $day_of_week_w = intval($current->format('w'));
                
                $batch_data[] = array(
                    'room_id' => $room_id,
                    'date' => $date_str,
                    'day_of_week' => $day_of_week_w,
                    'price_room' => $rule['price_room'],
                    'price_combo' => $rule['price_combo'],
                    'stock' => $rule['stock'] !== null ? $rule['stock'] : $room->total_rooms,
                    'status' => $rule['status'] !== null ? $rule['status'] : 'available'
                );
            }
            
            $current->modify('+1 day');
        }

        if (empty($batch_data)) {
            wp_send_json_error(array('message' => 'Không có dữ liệu để cập nhật'));
        }

        // Batch INSERT ... ON DUPLICATE KEY UPDATE
        // Giới hạn batch size để tránh memory issues
        $batch_size = 100;
        $total_affected = 0;
        $batches = array_chunk($batch_data, $batch_size);

        foreach ($batches as $batch) {
            $values = array();
            $placeholders = array();
            
            foreach ($batch as $row) {
                $placeholders[] = "(%d, %s, %d, %s, %s, %d, %s)";
                $values[] = $row['room_id'];
                $values[] = $row['date'];
                $values[] = $row['day_of_week'];
                $values[] = $row['price_room'] !== null ? $row['price_room'] : 0;
                $values[] = $row['price_combo'] !== null ? $row['price_combo'] : 0;
                $values[] = $row['stock'];
                $values[] = $row['status'];
            }

            $sql = "INSERT INTO {$table_pricing} (room_id, date, day_of_week, price_room, price_combo, stock, status) 
                    VALUES " . implode(', ', $placeholders) . "
                    ON DUPLICATE KEY UPDATE 
                        price_room = IF(VALUES(price_room) > 0, VALUES(price_room), price_room),
                        price_combo = IF(VALUES(price_combo) > 0, VALUES(price_combo), price_combo),
                        stock = VALUES(stock),
                        status = VALUES(status)";
            
            $prepared = $wpdb->prepare($sql, $values);
            $wpdb->query($prepared);
            
            $total_affected += $wpdb->rows_affected;
        }

        wp_send_json_success(array(
            'message' => sprintf(
                'Đã xử lý %d ngày thành công',
                count($batch_data)
            ),
            'updated' => $total_affected,
            'created' => count($batch_data) - $total_affected,
            'total_dates' => count($batch_data)
        ));
    }

    /**
     * Calculate price (public AJAX)
     */
    public function calculate_price()
    {
        $room_id = isset($_POST['room_id']) ? absint($_POST['room_id']) : 0;
        $checkin = isset($_POST['checkin']) ? sanitize_text_field($_POST['checkin']) : '';
        $checkout = isset($_POST['checkout']) ? sanitize_text_field($_POST['checkout']) : '';
        $price_type = isset($_POST['price_type']) ? sanitize_text_field($_POST['price_type']) : 'room';
        $guests = isset($_POST['guests']) ? (array) $_POST['guests'] : array();

        if ($room_id <= 0 || empty($checkin) || empty($checkout)) {
            wp_send_json_error(array('message' => 'Dữ liệu không hợp lệ'));
        }

        $result = Vie_Hotel_Rooms_Helpers::get_room_price($room_id, $checkin, $checkout, $price_type, $guests);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}

// Initialize
Vie_Hotel_Rooms_Ajax::get_instance();
