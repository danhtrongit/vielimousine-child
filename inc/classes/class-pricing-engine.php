<?php
/**
 * ============================================================================
 * TÊN FILE: class-pricing-engine.php
 * ============================================================================
 * 
 * MÔ TẢ:
 * Engine tính giá phòng khách sạn. Xử lý logic tính giá theo ngày,
 * phụ thu người lớn/trẻ em, và cung cấp dữ liệu cho calendar display.
 * 
 * CHỨC NĂNG CHÍNH:
 * - Tính giá booking cho khoảng ngày
 * - Tính phụ thu (người lớn, trẻ em, giường phụ)
 * - Lấy giá theo ngày từ bảng pricing
 * - Cung cấp dữ liệu giá cho datepicker calendar
 * 
 * SỬ DỤNG:
 * $engine = Vie_Pricing_Engine::get_instance();
 * $result = $engine->calculate_booking_price($params);
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
 * CLASS: Vie_Pricing_Engine
 * ============================================================================
 * 
 * Engine tính giá phòng với nhiều rule phức tạp.
 * Triển khai Singleton Pattern.
 * 
 * @since   2.0.0
 */
class Vie_Pricing_Engine {

    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /** @var Vie_Pricing_Engine|null Singleton instance */
    private static $instance = null;

    /** @var string Tên bảng rooms */
    private $table_rooms;

    /** @var string Tên bảng pricing */
    private $table_pricing;

    /** @var string Tên bảng surcharges */
    private $table_surcharges;

    /** @var array Cache tên ngày trong tuần */
    private static $day_names = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     * 
     * @since   2.0.0
     * @return  Vie_Pricing_Engine
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
        
        $this->table_rooms      = $wpdb->prefix . 'hotel_rooms';
        $this->table_pricing    = $wpdb->prefix . 'hotel_room_pricing';
        $this->table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
    }

    /**
     * -------------------------------------------------------------------------
     * MAIN CALCULATION METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Tính giá booking đầy đủ
     * 
     * Hàm chính để tính toàn bộ giá cho một booking request.
     * Bao gồm: giá phòng theo ngày + phụ thu
     * 
     * @since   2.0.0
     * 
     * @param   array   $params {
     *     Tham số tính giá
     * 
     *     @type int      $room_id        ID phòng
     *     @type string   $check_in       Ngày nhận phòng (d/m/Y hoặc Y-m-d)
     *     @type string   $check_out      Ngày trả phòng (d/m/Y hoặc Y-m-d)
     *     @type int      $num_rooms      Số lượng phòng
     *     @type int      $num_adults     Số người lớn
     *     @type int      $num_children   Số trẻ em
     *     @type array    $children_ages  Tuổi từng trẻ em
     *     @type string   $price_type     'room' hoặc 'combo'
     * }
     * 
     * @return  array|WP_Error {
     *     Kết quả tính giá
     * 
     *     @type string   $room_name              Tên phòng
     *     @type int      $num_nights             Số đêm
     *     @type int      $num_rooms              Số phòng
     *     @type string   $price_type             Loại giá
     *     @type string   $price_type_label       Label loại giá
     *     @type float    $base_price_per_room    Giá cơ bản 1 phòng
     *     @type float    $rooms_total            Tổng tiền phòng (x số phòng)
     *     @type array    $surcharges             Chi tiết phụ thu
     *     @type float    $surcharges_total       Tổng phụ thu
     *     @type float    $grand_total            Tổng cộng
     *     @type array    $price_breakdown        Chi tiết giá từng ngày
     *     @type array    $pricing_snapshot       Snapshot để lưu vào booking
     * }
     */
    public function calculate_booking_price(array $params) {
        global $wpdb;

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 1: VALIDATE VÀ PARSE THAM SỐ
         * -------------------------------------------------------------------------
         */
        $room_id       = absint($params['room_id'] ?? 0);
        $check_in      = $params['check_in'] ?? '';
        $check_out     = $params['check_out'] ?? '';
        $num_rooms     = max(1, absint($params['num_rooms'] ?? 1));
        $num_adults    = max(1, absint($params['num_adults'] ?? 2));
        $num_children  = absint($params['num_children'] ?? 0);
        $children_ages = isset($params['children_ages']) ? array_map('absint', $params['children_ages']) : array();
        $price_type    = sanitize_text_field($params['price_type'] ?? 'room');

        if (!$room_id || !$check_in || !$check_out) {
            return new WP_Error('missing_params', 'Thiếu thông tin tính giá');
        }

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 2: PARSE DATES
         * -------------------------------------------------------------------------
         */
        $date_in  = $this->parse_date($check_in);
        $date_out = $this->parse_date($check_out);

        if (!$date_in || !$date_out || $date_out <= $date_in) {
            return new WP_Error('invalid_dates', 'Ngày không hợp lệ');
        }

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 3: LẤY THÔNG TIN PHÒNG
         * -------------------------------------------------------------------------
         */
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_rooms} WHERE id = %d",
            $room_id
        ));

        if (!$room) {
            return new WP_Error('room_not_found', 'Phòng không tồn tại');
        }

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 4: TÍNH SỐ ĐÊM
         * -------------------------------------------------------------------------
         */
        $num_nights = $date_out->diff($date_in)->days;

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 5: LẤY GIÁ THEO TỪNG NGÀY
         * -------------------------------------------------------------------------
         */
        $pricing_details = $this->get_pricing_for_dates(
            $room_id,
            $date_in,
            $date_out,
            $price_type
        );

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 6: TÍNH TỔNG GIÁ PHÒNG
         * -------------------------------------------------------------------------
         */
        $base_total      = 0;
        $price_breakdown = array();

        foreach ($pricing_details as $date => $price_info) {
            $day_price    = $price_info['price'];
            $base_total  += $day_price;

            $price_breakdown[] = array(
                'date'      => $date,
                'day_name'  => $price_info['day_name'],
                'price'     => $day_price,
                'formatted' => vie_format_currency($day_price)
            );
        }

        // Nhân với số phòng
        $rooms_total = $base_total * $num_rooms;

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 7: TÍNH PHỤ THU
         * -------------------------------------------------------------------------
         */
        $surcharges = $this->calculate_surcharges(
            $room_id,
            $room,
            $num_adults,
            $num_children,
            $children_ages,
            $num_rooms,
            $num_nights,
            $price_type
        );

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 8: TÍNH TỔNG CỘNG
         * -------------------------------------------------------------------------
         */
        $grand_total = $rooms_total + $surcharges['total'];

        /**
         * -------------------------------------------------------------------------
         * BƯỚC 9: BUILD RESPONSE
         * -------------------------------------------------------------------------
         */
        return array(
            'success'               => true,
            'room_name'             => $room->name,
            'num_nights'            => $num_nights,
            'num_rooms'             => $num_rooms,
            'price_type'            => $price_type,
            'price_type_label'      => $price_type === 'combo' ? 'Giá Combo' : 'Giá Room Only',
            'base_price_per_room'   => $base_total,
            'base_price_formatted'  => vie_format_currency($base_total),
            'rooms_total'           => $rooms_total,
            'rooms_total_formatted' => vie_format_currency($rooms_total),
            'surcharges'            => $surcharges['details'],
            'surcharges_total'      => $surcharges['total'],
            'surcharges_formatted'  => vie_format_currency($surcharges['total']),
            'grand_total'           => $grand_total,
            'grand_total_formatted' => vie_format_currency($grand_total),
            'price_breakdown'       => $price_breakdown,
            'pricing_snapshot'      => $pricing_details,
        );
    }

    /**
     * -------------------------------------------------------------------------
     * PRICING BY DATE METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy giá theo từng ngày trong khoảng
     *
     * @since   2.0.0
     *
     * @param   int       $room_id      ID phòng
     * @param   DateTime  $date_in      Ngày check-in
     * @param   DateTime  $date_out     Ngày check-out
     * @param   string    $price_type   'room' hoặc 'combo'
     *
     * @return  array     Mảng giá theo ngày [date => ['price' => x, 'day_of_week' => y, ...]]
     */
    public function get_pricing_for_dates($room_id, $date_in, $date_out, $price_type) {
        global $wpdb;

        $pricing = array();
        $current = clone $date_in;

        while ($current < $date_out) {
            $date_str    = $current->format('Y-m-d');
            $day_of_week = (int) $current->format('w');

            // Get price from pricing table
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$this->table_pricing} WHERE room_id = %d AND date = %s",
                $room_id,
                $date_str
            ));

            if ($row) {
                // Use price_room or price_combo based on selection
                if ($price_type === 'combo' && $row->price_combo > 0) {
                    $price = $row->price_combo;
                } elseif ($row->price_room > 0) {
                    $price = $row->price_room;
                } else {
                    $price = 0;
                }
            } else {
                // No schedule pricing = 0
                $price = 0;
            }

            $pricing[$date_str] = array(
                'price'       => (float) $price,
                'day_of_week' => $day_of_week,
                'day_name'    => self::$day_names[$day_of_week],
                'status'      => $row ? $row->status : 'stop_sell'
            );

            $current->modify('+1 day');
        }

        return $pricing;
    }

    /**
     * Lấy giá cho 1 ngày cụ thể
     *
     * @since   2.0.0
     *
     * @param   int     $room_id      ID phòng
     * @param   string  $date         Ngày (Y-m-d)
     * @param   string  $price_type   'room' hoặc 'combo'
     *
     * @return  float   Giá cho ngày đó (0 nếu không có giá lịch)
     */
    public function get_daily_price($room_id, $date, $price_type = 'room') {
        global $wpdb;

        // Get specific date price
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT price_room, price_combo FROM {$this->table_pricing} WHERE room_id = %d AND date = %s",
            $room_id,
            $date
        ));

        if ($row) {
            if ($price_type === 'combo' && $row->price_combo > 0) {
                return (float) $row->price_combo;
            } elseif ($row->price_room > 0) {
                return (float) $row->price_room;
            }
        }

        return 0;
    }

    /**
     * -------------------------------------------------------------------------
     * SURCHARGE CALCULATION
     * -------------------------------------------------------------------------
     */

    /**
     * Tính phụ thu dựa trên occupancy và tuổi trẻ em
     * 
     * @since   2.0.0
     * 
     * @param   int     $room_id        ID phòng
     * @param   object  $room           Room object
     * @param   int     $num_adults     Số người lớn
     * @param   int     $num_children   Số trẻ em
     * @param   array   $children_ages  Tuổi từng trẻ em
     * @param   int     $num_rooms      Số phòng
     * @param   int     $num_nights     Số đêm
     * @param   string  $price_type     'room' hoặc 'combo'
     * 
     * @return  array {
     *     @type float  $total    Tổng phụ thu
     *     @type array  $details  Chi tiết từng loại phụ thu
     * }
     */
    public function calculate_surcharges($room_id, $room, $num_adults, $num_children, $children_ages, $num_rooms, $num_nights, $price_type) {
        global $wpdb;

        $surcharges = array(
            'total'   => 0,
            'details' => array()
        );

        $base_occupancy = $room->base_occupancy ?: 2;

        // Get all surcharge rules for this room
        $rules = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_surcharges} WHERE room_id = %d AND status = 'active' ORDER BY sort_order ASC",
            $room_id
        ));

        if (empty($rules)) {
            return $surcharges;
        }

        // Check price type applicability
        $price_field = $price_type === 'combo' ? 'applies_to_combo' : 'applies_to_room';

        // Calculate extra adults (beyond base occupancy)
        $extra_adults = max(0, $num_adults - ($base_occupancy * $num_rooms));

        foreach ($rules as $rule) {
            // Check if rule applies to selected price type
            if (!$rule->$price_field) {
                continue;
            }

            $amount   = 0;
            $quantity = 0;
            $label    = $rule->label ?: $this->get_surcharge_type_label($rule->surcharge_type);

            switch ($rule->surcharge_type) {
                case 'extra_bed':
                case 'adult':
                    // Apply for extra adults
                    if ($extra_adults > 0) {
                        $quantity = $extra_adults;
                        $amount   = $this->calculate_surcharge_amount($rule, $quantity, $num_nights);
                    }
                    break;

                case 'child':
                    // Apply based on children ages
                    foreach ($children_ages as $age) {
                        // Check age range
                        $min_age = $rule->min_age !== null ? $rule->min_age : 0;
                        $max_age = $rule->max_age !== null ? $rule->max_age : 17;

                        if ($age >= $min_age && $age <= $max_age) {
                            $quantity++;
                        }
                    }

                    if ($quantity > 0) {
                        $amount = $this->calculate_surcharge_amount($rule, $quantity, $num_nights);
                        $label  = sprintf('%s (%d-%d tuổi)', $label, $rule->min_age, $rule->max_age);
                    }
                    break;

                case 'breakfast':
                    // Breakfast surcharge for all guests
                    if ($rule->is_mandatory || $price_type === 'combo') {
                        $quantity = $num_adults + $num_children;
                        $amount   = $this->calculate_surcharge_amount($rule, $quantity, $num_nights);
                    }
                    break;
            }

            if ($amount > 0) {
                $surcharges['details'][] = array(
                    'type'        => $rule->surcharge_type,
                    'label'       => $label,
                    'quantity'    => $quantity,
                    'unit_amount' => $rule->amount,
                    'is_per_night'=> $rule->is_per_night,
                    'nights'      => $rule->is_per_night ? $num_nights : 1,
                    'amount'      => $amount,
                    'formatted'   => vie_format_currency($amount)
                );
                $surcharges['total'] += $amount;
            }
        }

        return $surcharges;
    }

    /**
     * Tính số tiền cho 1 rule phụ thu
     * 
     * @since   2.0.0
     * @param   object  $rule       Surcharge rule object
     * @param   int     $quantity   Số lượng
     * @param   int     $num_nights Số đêm
     * @return  float
     */
    private function calculate_surcharge_amount($rule, $quantity, $num_nights) {
        $amount = $rule->amount * $quantity;

        if ($rule->is_per_night) {
            $amount *= $num_nights;
        }

        return $amount;
    }

    /**
     * Lấy label cho loại phụ thu
     * 
     * @since   2.0.0
     * @param   string  $type   Loại phụ thu
     * @return  string
     */
    private function get_surcharge_type_label($type) {
        $labels = array(
            'extra_bed' => 'Giường phụ',
            'child'     => 'Phụ thu trẻ em',
            'adult'     => 'Phụ thu người lớn',
            'breakfast' => 'Bữa sáng',
            'other'     => 'Phụ thu khác',
        );

        return $labels[$type] ?? $type;
    }

    /**
     * -------------------------------------------------------------------------
     * CALENDAR DATA METHODS
     * -------------------------------------------------------------------------
     */

    /**
     * Lấy giá cho calendar (datepicker) của 1 tháng
     * 
     * Trả về giá thấp nhất (cả Room và Combo) cho mỗi ngày trong tháng.
     * Dùng để hiển thị giá trên datepicker.
     * 
     * @since   2.0.0
     * 
     * @param   int  $hotel_id  ID khách sạn
     * @param   int  $year      Năm
     * @param   int  $month     Tháng
     * 
     * @return  array {
     *     Mảng giá theo ngày
     *     
     *     @type array $prices {
     *         [date] => array(
     *             'room'        => float,
     *             'room_label'  => string,
     *             'combo'       => float|null,
     *             'combo_label' => string|null,
     *             'sold_out'    => bool,
     *             'status'      => string
     *         )
     *     }
     * }
     */
    public function get_calendar_prices($hotel_id, $year, $month) {
        global $wpdb;

        // Get all active rooms for this hotel
        $rooms = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$this->table_rooms} WHERE hotel_id = %d AND status = 'active'",
            $hotel_id
        ));

        if (empty($rooms)) {
            return array('prices' => array());
        }

        // Calculate date range (include overlap for calendar)
        $start_date      = sprintf('%04d-%02d-01', $year, $month);
        $end_date        = date('Y-m-t', strtotime($start_date));
        $next_month_end  = date('Y-m-d', strtotime($end_date . ' +7 days'));
        $prev_month_start= date('Y-m-d', strtotime($start_date . ' -7 days'));

        // Build room IDs
        $room_ids = array_map(function($r) { return $r->id; }, $rooms);
        $room_ids_placeholder = implode(',', array_fill(0, count($room_ids), '%d'));

        // Get all pricing data
        $query = $wpdb->prepare(
            "SELECT date, room_id, price_room, price_combo, status, stock
             FROM {$this->table_pricing}
             WHERE room_id IN ({$room_ids_placeholder})
             AND date >= %s AND date <= %s",
            array_merge($room_ids, array($prev_month_start, $next_month_end))
        );

        $pricing_data = $wpdb->get_results($query);

        // Organize pricing by date
        $prices_by_date = array();

        foreach ($pricing_data as $row) {
            $date = $row->date;

            if (!isset($prices_by_date[$date])) {
                $prices_by_date[$date] = array(
                    'min_room'  => PHP_INT_MAX,
                    'min_combo' => PHP_INT_MAX,
                    'status'    => 'available',
                    'sold_out'  => true
                );
            }

            // Check if this room-date is available
            $is_available = !in_array($row->status, array('sold_out', 'stop_sell'));

            if ($is_available) {
                $prices_by_date[$date]['sold_out'] = false;

                // Track minimum ROOM price
                $room_price = $row->price_room > 0 ? (float) $row->price_room : 0;
                if ($room_price > 0 && $room_price < $prices_by_date[$date]['min_room']) {
                    $prices_by_date[$date]['min_room'] = $room_price;
                }

                // Track minimum COMBO price (if set)
                if ($row->price_combo > 0) {
                    $combo_price = (float) $row->price_combo;
                    if ($combo_price < $prices_by_date[$date]['min_combo']) {
                        $prices_by_date[$date]['min_combo'] = $combo_price;
                    }
                }
            }

            // Update status
            if ($row->status === 'stop_sell') {
                $prices_by_date[$date]['status'] = 'stop_sell';
            } elseif ($row->status === 'sold_out' && $prices_by_date[$date]['status'] !== 'stop_sell') {
                $prices_by_date[$date]['status'] = 'sold_out';
            }
        }

        // Format response
        $result = array();

        foreach ($prices_by_date as $date => $info) {
            $room_price  = $info['min_room'] !== PHP_INT_MAX ? $info['min_room'] : 0;
            $combo_price = $info['min_combo'] !== PHP_INT_MAX ? $info['min_combo'] : null;

            $result[$date] = array(
                'room'        => $room_price,
                'room_label'  => $room_price > 0 ? $this->format_price_short($room_price) : 'Chưa có giá',
                'combo'       => $combo_price,
                'combo_label' => $combo_price ? $this->format_price_short($combo_price) : null,
                'sold_out'    => $info['sold_out'],
                'status'      => $info['status']
            );
        }

        return array(
            'prices' => $result,
            'month'  => $month,
            'year'   => $year
        );
    }

    /**
     * -------------------------------------------------------------------------
     * HELPER METHODS
     * -------------------------------------------------------------------------
     */

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
     * Format giá ngắn gọn cho calendar
     * 
     * @since   2.0.0
     * @param   float   $price  Giá
     * @return  string
     */
    private function format_price_short($price) {
        $price = (float) $price;
        return number_format($price, 0, ',', '.') . ' VNĐ';
    }
}
