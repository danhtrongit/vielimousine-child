<?php
/**
 * ============================================================================
 * TÊN FILE: class-booking-manager.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Quản lý toàn bộ logic đặt phòng: tạo booking, cập nhật trạng thái,
 * truy vấn danh sách và xử lý các thao tác liên quan đến đơn đặt phòng.
 * 
 * CHỨC NĂNG CHÍNH:
 * - Tạo đơn đặt phòng mới
 * - Cập nhật trạng thái đơn hàng
 * - Truy vấn booking theo ID, hash, hoặc các filter
 * - Kiểm tra khả dụng phòng
 * - Cập nhật stock sau khi đặt
 * 
 * SỬ DỤNG:
 * $manager = Vie_Booking_Manager::get_instance();
 * $result = $manager->create_booking($data);
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
 * CLASS: Vie_Booking_Manager
 * ============================================================================
 * 
 * Lớp xử lý nghiệp vụ đặt phòng khách sạn.
 * Triển khai Singleton Pattern để đảm bảo chỉ có 1 instance.
 * 
 * @since   2.0.0
 * @uses    Vie_Pricing_Engine   Tính giá phòng
 */
class Vie_Booking_Manager {

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /** @var Vie_Booking_Manager|null Singleton instance */
    private static $instance = null;

    /** @var string Tên bảng bookings */
    private $table_bookings;

    /** @var string Tên bảng rooms */
    private $table_rooms;

    /** @var string Tên bảng pricing */
    private $table_pricing;

    /** @var array Danh sách trạng thái booking */
    public static $statuses = array(
        'pending_payment' => 'Chờ thanh toán',
        'pending'         => 'Chờ xác nhận',
        'confirmed'       => 'Đã xác nhận',
        'processing'      => 'Đang xử lý',
        'paid'            => 'Đã thanh toán',
        'completed'       => 'Hoàn thành',
        'cancelled'       => 'Đã hủy',
        'no_show'         => 'Không đến',
    );

    /** @var array Danh sách trạng thái thanh toán */
    public static $payment_statuses = array(
        'unpaid'   => 'Chưa thanh toán',
        'partial'  => 'Thanh toán một phần',
        'paid'     => 'Đã thanh toán',
        'refunded' => 'Đã hoàn tiền',
    );

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     * 
     * @since   2.0.0
     * @return  Vie_Booking_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - Private để enforce Singleton
     * 
     * @since   2.0.0
     */
    private function __construct() {
        global $wpdb;
        
        $this->table_bookings = $wpdb->prefix . 'hotel_bookings';
        $this->table_rooms    = $wpdb->prefix . 'hotel_rooms';
        $this->table_pricing  = $wpdb->prefix . 'hotel_room_pricing';
    }

    /**
     * -------------------------------------------------------------------------
     * CREATE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Tạo đơn đặt phòng mới
     * 
     * @since   2.0.0
     * 
     * @param   array   $data {
     *     Dữ liệu đặt phòng
     * 
     *     @type int      $hotel_id        ID của khách sạn
     *     @type int      $room_id         ID của phòng
     *     @type string   $check_in        Ngày nhận phòng (d/m/Y hoặc Y-m-d)
     *     @type string   $check_out       Ngày trả phòng (d/m/Y hoặc Y-m-d)
     *     @type int      $num_rooms       Số lượng phòng
     *     @type int      $num_adults      Số người lớn
     *     @type int      $num_children    Số trẻ em
     *     @type array    $children_ages   Tuổi từng trẻ em
     *     @type string   $price_type      Loại giá: 'room' hoặc 'combo'
     *     @type string   $bed_type        Loại giường: 'double' hoặc 'twin'
     *     @type string   $customer_name   Tên khách hàng
     *     @type string   $customer_phone  Số điện thoại
     *     @type string   $customer_email  Email (tùy chọn)
     *     @type string   $customer_note   Ghi chú (tùy chọn)
     *     @type array    $pricing_snapshot    Chi tiết giá theo ngày
     *     @type array    $surcharges_snapshot Chi tiết phụ thu
     *     @type float    $base_amount     Tổng tiền phòng
     *     @type float    $surcharges_amount   Tổng phụ thu
     *     @type float    $total_amount    Tổng cộng
     *     @type array    $transport_info  Thông tin xe đưa đón
     *     @type array    $invoice_info    Thông tin xuất hóa đơn
     * }
     * 
     * @return  array|WP_Error {
     *     Kết quả tạo booking
     * 
     *     @type bool     $success        True nếu thành công
     *     @type int      $booking_id     ID của booking vừa tạo
     *     @type string   $booking_code   Mã đặt phòng (VD: BK-20241129-A1B2)
     *     @type string   $booking_hash   Hash bảo mật cho URL checkout
     * }
     */
    public function create_booking(array $data) {
        global $wpdb;

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 1: VALIDATE DỮ LIỆU ĐẦU VÀO
         * -------------------------------------------------------------------------
         */
        $validation = $this->validate_booking_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 2: PARSE VÀ VALIDATE NGÀY
         * -------------------------------------------------------------------------
         */
        $date_in  = $this->parse_date($data['check_in']);
        $date_out = $this->parse_date($data['check_out']);

        if (!$date_in || !$date_out || $date_out <= $date_in) {
            return new WP_Error('invalid_dates', 'Ngày check-in/check-out không hợp lệ');
        }

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 3: KIỂM TRA KHẢ DỤNG PHÒNG
         * -------------------------------------------------------------------------
         */
        $availability = $this->check_room_availability(
            $data['room_id'],
            $date_in->format('Y-m-d'),
            $date_out->format('Y-m-d'),
            $data['num_rooms'] ?? 1
        );

        if (!$availability['available']) {
            return new WP_Error('room_unavailable', $availability['message']);
        }

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 4: GENERATE BOOKING CODE VÀ HASH
         * -------------------------------------------------------------------------
         */
        $booking_code = $this->generate_booking_code();
        $booking_hash = wp_generate_password(32, false);

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 5: CHUẨN BỊ DỮ LIỆU
         * -------------------------------------------------------------------------
         */
        $guests_info = array(
            'adults'          => $data['num_adults'] ?? 2,
            'children'        => $data['num_children'] ?? 0,
            'children_ages'   => $data['children_ages'] ?? array(),
            'rooms_allocation'=> $data['num_rooms'] ?? 1,
            'bed_type'        => $data['bed_type'] ?? 'double',
            'bed_type_label'  => ($data['bed_type'] ?? 'double') === 'twin' 
                                 ? '2 Giường đơn (Twin Beds)' 
                                 : '1 Giường đôi lớn (Double Bed)'
        );

        $insert_data = array(
            'booking_code'       => $booking_code,
            'booking_hash'       => $booking_hash,
            'hotel_id'           => absint($data['hotel_id']),
            'room_id'            => absint($data['room_id']),
            'check_in'           => $date_in->format('Y-m-d'),
            'check_out'          => $date_out->format('Y-m-d'),
            'num_rooms'          => absint($data['num_rooms'] ?? 1),
            'num_adults'         => absint($data['num_adults'] ?? 2),
            'num_children'       => absint($data['num_children'] ?? 0),
            'price_type'         => sanitize_text_field($data['price_type'] ?? 'room'),
            'customer_name'      => sanitize_text_field($data['customer_name']),
            'customer_phone'     => sanitize_text_field($data['customer_phone']),
            'customer_email'     => sanitize_email($data['customer_email'] ?? ''),
            'customer_note'      => sanitize_textarea_field($data['customer_note'] ?? ''),
            'guests_info'        => wp_json_encode($guests_info),
            'pricing_details'    => wp_json_encode($data['pricing_snapshot'] ?? array()),
            'surcharges_details' => wp_json_encode($data['surcharges_snapshot'] ?? array()),
            'transport_info'     => !empty($data['transport_info']) ? wp_json_encode($data['transport_info']) : null,
            'invoice_info'       => !empty($data['invoice_info']) ? wp_json_encode($data['invoice_info']) : null,
            'base_amount'        => floatval($data['base_amount'] ?? 0),
            'surcharges_amount'  => floatval($data['surcharges_amount'] ?? 0),
            'discount_amount'    => floatval($data['discount_amount'] ?? 0),
            'coupon_code'        => sanitize_text_field($data['coupon_code'] ?? ''),
            'total_amount'       => floatval($data['total_amount'] ?? 0),
            'status'             => 'pending_payment',
            'payment_status'     => 'unpaid',
            'ip_address'         => $this->get_client_ip(),
            'user_agent'         => sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''),
        );

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 6: INSERT VÀO DATABASE
         * -------------------------------------------------------------------------
         */
        $result = $wpdb->insert($this->table_bookings, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', 'Lỗi lưu đặt phòng vào database');
        }

        $booking_id = $wpdb->insert_id;

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 7: CẬP NHẬT STOCK
         * -------------------------------------------------------------------------
         */
        $this->update_room_stock(
            $data['room_id'],
            $date_in,
            $date_out,
            $data['num_rooms'] ?? 1
        );

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 8: TRẢ VỀ KẾT QUẢ
         * -------------------------------------------------------------------------
         */
        return array(
            'success'      => true,
            'booking_id'   => $booking_id,
            'booking_code' => $booking_code,
            'booking_hash' => $booking_hash,
        );
    }

    /**
     * Generate mã booking unique
     * 
     * Format: BK-YYYYMMDD-XXXX
     * 
     * @since   2.0.0
     * @return  string
     */
    public function generate_booking_code() {
        $prefix = 'BK';
        $date   = date('Ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));

        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * -------------------------------------------------------------------------
     * READ METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy booking theo ID
     * 
     * @since   2.0.0
     * @param   int     $id     Booking ID
     * @return  object|null     Booking object hoặc null
     */
    public function get_booking($id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, r.name as room_name, r.base_occupancy 
             FROM {$this->table_bookings} b 
             LEFT JOIN {$this->table_rooms} r ON b.room_id = r.id 
             WHERE b.id = %d",
            absint($id)
        ));
    }

    /**
     * Lấy booking theo hash (dùng cho checkout URL)
     * 
     * @since   2.0.0
     * @param   string  $hash   Booking hash
     * @return  object|null     Booking object hoặc null
     */
    public function get_booking_by_hash($hash) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT b.*, r.name as room_name 
             FROM {$this->table_bookings} b 
             LEFT JOIN {$this->table_rooms} r ON b.room_id = r.id 
             WHERE b.booking_hash = %s",
            sanitize_text_field($hash)
        ));
    }

    /**
     * Lấy booking theo mã booking code
     * 
     * @since   2.0.0
     * @param   string  $code   Booking code
     * @return  object|null     Booking object hoặc null
     */
    public function get_booking_by_code($code) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_bookings} WHERE booking_code = %s",
            sanitize_text_field($code)
        ));
    }

    /**
     * Lấy danh sách bookings với filters
     * 
     * @since   2.0.0
     * 
     * @param   array   $args {
     *     Các tham số filter
     * 
     *     @type string  $status       Filter theo status
     *     @type int     $hotel_id     Filter theo hotel
     *     @type string  $date_from    Filter từ ngày
     *     @type string  $date_to      Filter đến ngày
     *     @type string  $search       Tìm kiếm theo code, tên, SĐT
     *     @type int     $per_page     Số items mỗi trang (default: 20)
     *     @type int     $paged        Trang hiện tại (default: 1)
     *     @type string  $orderby      Sắp xếp theo (default: created_at)
     *     @type string  $order        Thứ tự sắp xếp (default: DESC)
     * }
     * 
     * @return  array {
     *     @type array   $items        Danh sách bookings
     *     @type int     $total        Tổng số bookings
     *     @type int     $total_pages  Tổng số trang
     * }
     */
    public function get_bookings_list(array $args = array()) {
        global $wpdb;

        // Default arguments
        $defaults = array(
            'status'    => '',
            'hotel_id'  => 0,
            'date_from' => '',
            'date_to'   => '',
            'search'    => '',
            'per_page'  => 20,
            'paged'     => 1,
            'orderby'   => 'created_at',
            'order'     => 'DESC',
        );
        $args = wp_parse_args($args, $defaults);

        // Build WHERE clause
        $where  = array('1=1');
        $params = array();

        if (!empty($args['status'])) {
            $where[]  = 'b.status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['hotel_id'])) {
            $where[]  = 'b.hotel_id = %d';
            $params[] = absint($args['hotel_id']);
        }

        if (!empty($args['date_from'])) {
            $where[]  = 'b.check_in >= %s';
            $params[] = $args['date_from'];
        }

        if (!empty($args['date_to'])) {
            $where[]  = 'b.check_in <= %s';
            $params[] = $args['date_to'];
        }

        if (!empty($args['search'])) {
            $where[]     = '(b.booking_code LIKE %s OR b.customer_name LIKE %s OR b.customer_phone LIKE %s)';
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[]    = $search_like;
            $params[]    = $search_like;
            $params[]    = $search_like;
        }

        $where_sql = implode(' AND ', $where);

        // Count total
        $count_sql = "SELECT COUNT(*) FROM {$this->table_bookings} b WHERE {$where_sql}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = (int) $wpdb->get_var($count_sql);

        // Pagination
        $per_page    = max(1, absint($args['per_page']));
        $paged       = max(1, absint($args['paged']));
        $offset      = ($paged - 1) * $per_page;
        $total_pages = ceil($total / $per_page);

        // Validate orderby
        $allowed_orderby = array('created_at', 'check_in', 'total_amount', 'status');
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Get items
        $sql = "SELECT b.*, r.name as room_name 
                FROM {$this->table_bookings} b 
                LEFT JOIN {$this->table_rooms} r ON b.room_id = r.id 
                WHERE {$where_sql} 
                ORDER BY b.{$orderby} {$order} 
                LIMIT %d OFFSET %d";

        $params[] = $per_page;
        $params[] = $offset;

        $items = $wpdb->get_results($wpdb->prepare($sql, $params));

        return array(
            'items'       => $items,
            'total'       => $total,
            'total_pages' => $total_pages,
        );
    }

    /**
     * -------------------------------------------------------------------------
     * UPDATE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Cập nhật thông tin booking
     * 
     * @since   2.0.0
     * @param   int     $id     Booking ID
     * @param   array   $data   Dữ liệu cần update
     * @return  bool|WP_Error
     */
    public function update_booking($id, array $data) {
        global $wpdb;

        $allowed_fields = array(
            'status', 'payment_status', 'payment_method',
            'customer_name', 'customer_phone', 'customer_email', 'customer_note',
            'admin_note', 'room_code'
        );

        $update_data = array();
        $formats     = array();

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $update_data[$field] = sanitize_text_field($data[$field]);
                $formats[] = '%s';
            }
        }

        if (empty($update_data)) {
            return new WP_Error('no_data', 'Không có dữ liệu để cập nhật');
        }

        // Add updated_at
        $update_data['updated_at'] = current_time('mysql');
        $formats[] = '%s';

        $result = $wpdb->update(
            $this->table_bookings,
            $update_data,
            array('id' => absint($id)),
            $formats,
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Lỗi cập nhật database');
        }

        return true;
    }

    /**
     * Cập nhật trạng thái booking
     * 
     * @since   2.0.0
     * @param   int     $id         Booking ID
     * @param   string  $status     Trạng thái mới
     * @return  bool|WP_Error
     */
    public function update_status($id, $status) {
        if (!array_key_exists($status, self::$statuses)) {
            return new WP_Error('invalid_status', 'Trạng thái không hợp lệ');
        }

        return $this->update_booking($id, array('status' => $status));
    }

    /**
     * Đánh dấu booking đã thanh toán
     * 
     * @since   2.0.0
     * @param   int     $id     Booking ID
     * @return  bool|WP_Error
     */
    public function mark_as_paid($id) {
        return $this->update_booking($id, array(
            'status'         => 'paid',
            'payment_status' => 'paid',
        ));
    }

    /**
     * -------------------------------------------------------------------------
     * DELETE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Xóa booking
     * 
     * @since   2.0.0
     * @param   int     $id     Booking ID
     * @return  bool|WP_Error
     */
    public function delete_booking($id) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_bookings,
            array('id' => absint($id)),
            array('%d')
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Lỗi xóa booking');
        }

        return true;
    }

    /**
     * -------------------------------------------------------------------------
     * AVAILABILITY & STOCK
     * -------------------------------------------------------------------------
     */

    /**
     * Kiểm tra khả dụng phòng cho khoảng ngày
     * 
     * @since   2.0.0
     * 
     * @param   int     $room_id    ID phòng
     * @param   string  $check_in   Ngày check-in (Y-m-d)
     * @param   string  $check_out  Ngày check-out (Y-m-d)
     * @param   int     $num_rooms  Số phòng cần đặt
     * 
     * @return  array {
     *     @type bool    $available          True nếu còn phòng
     *     @type string  $status             'available', 'sold_out', 'stop_sell', 'insufficient_stock'
     *     @type string  $message            Thông báo nếu không khả dụng
     *     @type array   $unavailable_dates  Danh sách ngày không khả dụng
     * }
     */
    public function check_room_availability($room_id, $check_in, $check_out, $num_rooms = 1) {
        global $wpdb;

        // Get room's total rooms
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT total_rooms FROM {$this->table_rooms} WHERE id = %d",
            absint($room_id)
        ));

        $total_rooms = $room ? $room->total_rooms : 0;
        $unavailable_dates = array();

        $date_in  = new DateTime($check_in);
        $date_out = new DateTime($check_out);
        $current  = clone $date_in;

        while ($current < $date_out) {
            $date_str = $current->format('Y-m-d');

            $pricing = $wpdb->get_row($wpdb->prepare(
                "SELECT stock, status FROM {$this->table_pricing} WHERE room_id = %d AND date = %s",
                $room_id,
                $date_str
            ));

            // Check status
            if ($pricing && in_array($pricing->status, array('sold_out', 'stop_sell'))) {
                $unavailable_dates[] = array(
                    'date'   => $date_str,
                    'reason' => $pricing->status === 'stop_sell' ? 'stop_sell' : 'sold_out'
                );
            }
            // Check stock
            elseif ($pricing && $pricing->stock < $num_rooms) {
                $unavailable_dates[] = array(
                    'date'      => $date_str,
                    'reason'    => 'insufficient_stock',
                    'available' => $pricing->stock
                );
            }
            // If no pricing row, check against total_rooms
            elseif (!$pricing && $total_rooms < $num_rooms) {
                $unavailable_dates[] = array(
                    'date'      => $date_str,
                    'reason'    => 'insufficient_stock',
                    'available' => $total_rooms
                );
            }

            $current->modify('+1 day');
        }

        if (!empty($unavailable_dates)) {
            $first_issue = $unavailable_dates[0];
            $message = '';

            switch ($first_issue['reason']) {
                case 'stop_sell':
                    $message = 'Phòng đã ngừng bán cho ngày ' . $first_issue['date'];
                    break;
                case 'sold_out':
                    $message = 'Phòng đã hết cho ngày ' . $first_issue['date'];
                    break;
                case 'insufficient_stock':
                    $message = sprintf(
                        'Chỉ còn %d phòng cho ngày %s',
                        $first_issue['available'],
                        $first_issue['date']
                    );
                    break;
            }

            return array(
                'available'         => false,
                'status'            => $first_issue['reason'],
                'message'           => $message,
                'unavailable_dates' => $unavailable_dates
            );
        }

        return array(
            'available'         => true,
            'status'            => 'available',
            'message'           => '',
            'unavailable_dates' => array()
        );
    }

    /**
     * Cập nhật stock sau khi đặt phòng
     * 
     * @since   2.0.0
     * @param   int       $room_id    ID phòng
     * @param   DateTime  $date_in    Ngày check-in
     * @param   DateTime  $date_out   Ngày check-out
     * @param   int       $num_rooms  Số phòng đã đặt
     */
    public function update_room_stock($room_id, $date_in, $date_out, $num_rooms) {
        global $wpdb;

        $current = clone $date_in;

        while ($current < $date_out) {
            $date_str = $current->format('Y-m-d');

            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->table_pricing} 
                SET stock = GREATEST(0, stock - %d), 
                    booked = booked + %d,
                    status = CASE 
                        WHEN stock - %d <= 0 THEN 'sold_out'
                        WHEN stock - %d <= 2 THEN 'limited'
                        ELSE status 
                    END
                WHERE room_id = %d AND date = %s",
                $num_rooms,
                $num_rooms,
                $num_rooms,
                $num_rooms,
                $room_id,
                $date_str
            ));

            $current->modify('+1 day');
        }
    }

    /**
     * -------------------------------------------------------------------------
     * VALIDATION & HELPERS
     * -------------------------------------------------------------------------
     */

    /**
     * Validate dữ liệu booking
     * 
     * @since   2.0.0
     * @param   array   $data   Dữ liệu cần validate
     * @return  bool|WP_Error
     */
    public function validate_booking_data(array $data) {
        // Required fields
        $required = array('hotel_id', 'room_id', 'check_in', 'check_out', 'customer_name', 'customer_phone');

        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new WP_Error('missing_field', "Thiếu trường bắt buộc: {$field}");
            }
        }

        // Validate hotel exists
        $hotel = get_post($data['hotel_id']);
        if (!$hotel || $hotel->post_type !== 'hotel') {
            return new WP_Error('invalid_hotel', 'Khách sạn không tồn tại');
        }

        // Validate room exists
        global $wpdb;
        $room = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_rooms} WHERE id = %d",
            $data['room_id']
        ));
        if (!$room) {
            return new WP_Error('invalid_room', 'Phòng không tồn tại');
        }

        // Validate phone format (Vietnam)
        $phone = preg_replace('/[^0-9]/', '', $data['customer_phone']);
        if (strlen($phone) < 10 || strlen($phone) > 11) {
            return new WP_Error('invalid_phone', 'Số điện thoại không hợp lệ');
        }

        return true;
    }

    /**
     * Parse date từ nhiều format
     * 
     * @since   2.0.0
     * @param   string  $date_str   Date string
     * @return  DateTime|false
     */
    private function parse_date($date_str) {
        // Try dd/mm/yyyy format first
        $date = DateTime::createFromFormat('d/m/Y', $date_str);

        if (!$date) {
            // Try Y-m-d format
            $date = DateTime::createFromFormat('Y-m-d', $date_str);
        }

        return $date;
    }

    /**
     * Lấy IP của client
     * 
     * @since   2.0.0
     * @return  string
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Lấy label của status
     * 
     * @since   2.0.0
     * @param   string  $status     Status code
     * @return  string              Status label
     */
    public static function get_status_label($status) {
        return self::$statuses[$status] ?? $status;
    }

    /**
     * Lấy label của payment status
     * 
     * @since   2.0.0
     * @param   string  $status     Payment status code
     * @return  string              Status label
     */
    public static function get_payment_status_label($status) {
        return self::$payment_statuses[$status] ?? $status;
    }
}
