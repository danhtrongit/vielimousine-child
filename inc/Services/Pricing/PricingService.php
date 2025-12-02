<?php
/**
 * ============================================================================
 * TÊN FILE: PricingService.php
 * ============================================================================
 *
 * MÔ TẢ:
 * Service tính giá phòng khách sạn. Xử lý logic tính giá theo ngày,
 * phụ thu người lớn/trẻ em, và cung cấp dữ liệu cho calendar display.
 *
 * CHỨC NĂNG CHÍNH:
 * - Tính giá booking cho khoảng ngày (calculate_booking_price)
 * - Tính phụ thu (người lớn, trẻ em, giường phụ, bữa sáng)
 * - Lấy giá theo ngày từ bảng pricing
 * - Cung cấp dữ liệu giá cho datepicker calendar
 * - Hỗ trợ 2 loại giá: Room Only và Combo
 *
 * PRICING TYPES:
 * - room:  Giá phòng trống (Room Only)
 * - combo: Giá combo (Room + Breakfast/Services)
 *
 * SURCHARGE TYPES:
 * - extra_bed: Giường phụ
 * - adult:     Phụ thu người lớn thứ 3+
 * - child:     Phụ thu trẻ em (theo độ tuổi)
 * - breakfast: Phụ thu bữa sáng
 * - other:     Phụ thu khác
 *
 * DATABASE TABLES:
 * - hotel_rooms:          Thông tin phòng (base_occupancy)
 * - hotel_room_pricing:   Giá theo ngày (price_room, price_combo, status)
 * - hotel_room_surcharges: Rules phụ thu (amount, is_per_night, age ranges)
 *
 * CALCULATION FLOW:
 * 1. Validate params (room_id, dates, guests)
 * 2. Get room data
 * 3. Tính số đêm
 * 4. Lấy giá từng ngày (price_room hoặc price_combo từ lịch)
 * 5. Tính tổng giá phòng (sum daily prices × số phòng)
 * 6. Tính phụ thu (based on occupancy và children ages)
 * 7. Grand total = Rooms total + Surcharges total
 *
 * SỬ DỤNG:
 * $pricing = Vie_Pricing_Service::get_instance();
 * $result = $pricing->calculate_booking_price([
 *     'room_id' => 123,
 *     'check_in' => '01/12/2025',
 *     'check_out' => '03/12/2025',
 *     'num_rooms' => 1,
 *     'num_adults' => 2,
 *     'num_children' => 1,
 *     'children_ages' => [5],
 *     'price_type' => 'combo'
 * ]);
 *
 * ----------------------------------------------------------------------------
 * @package     VielimousineChild
 * @subpackage  Services/Pricing
 * @version     2.1.0
 * @since       2.0.0 (Di chuyển từ inc/classes trong v2.1)
 * @author      Vie Development Team
 * ============================================================================
 */

defined('ABSPATH') || exit;

/**
 * ============================================================================
 * CLASS: Vie_Pricing_Service
 * ============================================================================
 *
 * Service tính giá phòng với nhiều rule phức tạp.
 *
 * ARCHITECTURE:
 * - Singleton pattern
 * - Direct database queries (wpdb)
 * - Support multiple pricing types (room/combo)
 * - Dynamic surcharge calculation
 * - Calendar integration (datepicker)
 *
 * PRICING STRATEGY:
 * - Daily pricing: Giá luôn lấy từ lịch theo ngày
 * - No fallback: Nếu ngày không có trong pricing table, giá = 0
 * - Minimum price tracking: Calendar hiển thị giá thấp nhất
 * - Occupancy-based surcharges: Phụ thu dựa trên số khách
 * - Age-based child surcharges: Phụ thu trẻ em theo độ tuổi
 *
 * DEPENDENCIES:
 * - WordPress $wpdb
 * - vie_format_currency() helper function
 * - Database tables: hotel_rooms, hotel_room_pricing, hotel_room_surcharges
 *
 * @since   2.0.0
 */
class Vie_Pricing_Service
{
    /**
     * -------------------------------------------------------------------------
     * THUỘC TÍNH
     * -------------------------------------------------------------------------
     */

    /**
     * Singleton instance
     * @var Vie_Pricing_Service|null
     */
    private static $instance = null;

    /**
     * Tên bảng rooms
     * @var string
     */
    private $table_rooms;

    /**
     * Tên bảng pricing (giá theo ngày)
     * @var string
     */
    private $table_pricing;

    /**
     * Tên bảng surcharges (phụ thu)
     * @var string
     */
    private $table_surcharges;

    /**
     * Cache tên ngày trong tuần (tiếng Việt)
     *
     * Index 0 = Chủ Nhật, 1 = Thứ 2, ..., 6 = Thứ 7
     *
     * @var array
     */
    private static $day_names = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');

    /**
     * -------------------------------------------------------------------------
     * KHỞI TẠO (SINGLETON PATTERN)
     * -------------------------------------------------------------------------
     */

    /**
     * Get singleton instance
     *
     * @since   2.0.0
     * @return  Vie_Pricing_Service
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
     * Khởi tạo table names từ WordPress $wpdb.
     *
     * @since   2.0.0
     */
    private function __construct()
    {
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
     * CALCULATION STEPS:
     * 1. Validate và parse tham số
     * 2. Parse dates (hỗ trợ d/m/Y và Y-m-d)
     * 3. Lấy thông tin phòng từ database
     * 4. Tính số đêm
     * 5. Lấy giá từng ngày (price_room hoặc price_combo)
     * 6. Tính tổng giá phòng (sum × num_rooms)
     * 7. Tính phụ thu (based on occupancy rules)
     * 8. Tính grand total
     * 9. Build response với đầy đủ breakdown
     *
     * ERROR HANDLING:
     * - Return WP_Error nếu thiếu params
     * - Return WP_Error nếu dates invalid
     * - Return WP_Error nếu room không tồn tại
     *
     * @since   2.0.0
     *
     * @param   array   $params {
     *     Tham số tính giá
     *
     *     @type int      $room_id        ID phòng
     *     @type string   $check_in       Ngày nhận phòng (d/m/Y hoặc Y-m-d)
     *     @type string   $check_out      Ngày trả phòng (d/m/Y hoặc Y-m-d)
     *     @type int      $num_rooms      Số lượng phòng (default: 1)
     *     @type int      $num_adults     Số người lớn (default: 2)
     *     @type int      $num_children   Số trẻ em (default: 0)
     *     @type array    $children_ages  Tuổi từng trẻ em (VD: [5, 8, 12])
     *     @type string   $price_type     'room' hoặc 'combo' (default: 'room')
     * }
     *
     * @return  array|WP_Error {
     *     Kết quả tính giá hoặc WP_Error nếu lỗi
     *
     *     @type bool     $success                true
     *     @type string   $room_name              Tên phòng
     *     @type int      $num_nights             Số đêm
     *     @type int      $num_rooms              Số phòng
     *     @type string   $price_type             'room' hoặc 'combo'
     *     @type string   $price_type_label       'Giá Room Only' hoặc 'Giá Combo'
     *     @type float    $base_price_per_room    Giá cơ bản 1 phòng (sum all nights)
     *     @type string   $base_price_formatted   Formatted base price
     *     @type float    $rooms_total            Tổng tiền phòng (base × num_rooms)
     *     @type string   $rooms_total_formatted  Formatted rooms total
     *     @type array    $surcharges             Chi tiết từng phụ thu
     *     @type float    $surcharges_total       Tổng phụ thu
     *     @type string   $surcharges_formatted   Formatted surcharges
     *     @type float    $grand_total            Tổng cộng (rooms + surcharges)
     *     @type string   $grand_total_formatted  Formatted grand total
     *     @type array    $price_breakdown        Chi tiết giá từng ngày
     *     @type array    $pricing_snapshot       Snapshot giá để lưu vào booking
     * }
     */
    public function calculate_booking_price(array $params)
    {
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
     * Loop qua từng ngày trong khoảng check-in → check-out,
     * lấy giá từ bảng pricing (giá = 0 nếu không có trong lịch).
     *
     * PRICING PRIORITY:
     * 1. Giá từ pricing table (price_room hoặc price_combo)
     * 2. Nếu không có: giá = 0
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
    public function get_pricing_for_dates($room_id, $date_in, $date_out, $price_type)
    {
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
     * Helper method để lấy giá của 1 ngày riêng lẻ.
     * Hữu ích cho API hoặc quick price check.
     *
     * @since   2.0.0
     *
     * @param   int     $room_id      ID phòng
     * @param   string  $date         Ngày (Y-m-d format)
     * @param   string  $price_type   'room' hoặc 'combo'
     *
     * @return  float   Giá cho ngày đó (0 nếu không có giá lịch)
     */
    public function get_daily_price($room_id, $date, $price_type = 'room')
    {
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
     * Đọc surcharge rules từ database và apply các rule phù hợp.
     *
     * SURCHARGE TYPES:
     * - extra_bed/adult: Phụ thu người lớn thứ 3+ (vượt base_occupancy)
     * - child: Phụ thu trẻ em (theo age range, VD: 6-11 tuổi)
     * - breakfast: Phụ thu bữa sáng (tính cho tất cả khách)
     * - other: Phụ thu khác
     *
     * CALCULATION RULES:
     * - Số người lớn vượt base_occupancy → phụ thu extra_bed
     * - Mỗi trẻ em kiểm tra age range → apply rule phù hợp
     * - Breakfast: tính cho tất cả nếu is_mandatory hoặc price_type=combo
     * - Mỗi rule có thể is_per_night (× số đêm) hoặc one-time
     *
     * @since   2.0.0
     *
     * @param   int     $room_id        ID phòng
     * @param   object  $room           Room object (có base_occupancy)
     * @param   int     $num_adults     Số người lớn
     * @param   int     $num_children   Số trẻ em
     * @param   array   $children_ages  Tuổi từng trẻ em (VD: [5, 8, 12])
     * @param   int     $num_rooms      Số phòng
     * @param   int     $num_nights     Số đêm
     * @param   string  $price_type     'room' hoặc 'combo'
     *
     * @return  array {
     *     Kết quả tính phụ thu
     *
     *     @type float  $total    Tổng phụ thu
     *     @type array  $details  Chi tiết từng loại phụ thu
     * }
     */
    public function calculate_surcharges($room_id, $room, $num_adults, $num_children, $children_ages, $num_rooms, $num_nights, $price_type)
    {
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

        // Calculate extra children (beyond max_children)
        // Logic: Exempt the first N children (where N = max_children * num_rooms)
        // We sort ages descending to exempt the oldest (most expensive) children first
        $max_children_per_room = isset($room->max_children) ? intval($room->max_children) : 0;
        $free_child_slots      = $max_children_per_room * $num_rooms;
        
        // Sort ages descending
        rsort($children_ages);
        
        // Keep only the chargeable children (those beyond free slots)
        $chargeable_ages = array_slice($children_ages, $free_child_slots);

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
                    // Apply based on children ages (using only chargeable kids)
                    foreach ($chargeable_ages as $age) {
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
     * Formula: amount × quantity × nights (nếu is_per_night)
     *
     * @since   2.0.0
     * @param   object  $rule       Surcharge rule object
     * @param   int     $quantity   Số lượng (số khách, số giường, etc.)
     * @param   int     $num_nights Số đêm
     * @return  float               Số tiền phụ thu
     */
    private function calculate_surcharge_amount($rule, $quantity, $num_nights)
    {
        $amount = $rule->amount * $quantity;

        if ($rule->is_per_night) {
            $amount *= $num_nights;
        }

        return $amount;
    }

    /**
     * Lấy label tiếng Việt cho loại phụ thu
     *
     * @since   2.0.0
     * @param   string  $type   Loại phụ thu (extra_bed, child, adult, breakfast, other)
     * @return  string          Label tiếng Việt
     */
    private function get_surcharge_type_label($type)
    {
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
     * FLOW:
     * 1. Lấy tất cả active rooms của hotel
     * 2. Lấy pricing data cho tháng (+ 7 ngày overlap)
     * 3. Track minimum price cho mỗi ngày
     * 4. Format response với sold_out status
     *
     * RESPONSE FORMAT:
     * {
     *   '2025-12-01': {
     *     room: 2500000,
     *     room_label: '2.500.000 VNĐ',
     *     combo: 3000000,
     *     combo_label: '3.000.000 VNĐ',
     *     sold_out: false,
     *     status: 'available'
     *   },
     *   ...
     * }
     *
     * @since   2.0.0
     *
     * @param   int  $hotel_id  ID khách sạn
     * @param   int  $year      Năm (VD: 2025)
     * @param   int  $month     Tháng (1-12)
     *
     * @return  array {
     *     Mảng giá theo ngày
     *
     *     @type array $prices {
     *         [date] => array(
     *             'room'        => float,        Giá Room Only thấp nhất
     *             'room_label'  => string,       Formatted room price
     *             'combo'       => float|null,   Giá Combo thấp nhất (null nếu không có)
     *             'combo_label' => string|null,  Formatted combo price
     *             'sold_out'    => bool,         true nếu tất cả phòng đều sold out
     *             'status'      => string        'available', 'sold_out', hoặc 'stop_sell'
     *         )
     *     }
     *     @type int   $month  Tháng
     *     @type int   $year   Năm
     * }
     */
    public function get_calendar_prices($hotel_id, $year, $month)
    {
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
     * Hỗ trợ 2 formats:
     * - dd/mm/yyyy (format hiển thị cho user)
     * - Y-m-d (format database)
     *
     * @since   2.0.0
     * @param   string  $date_str   Date string
     * @return  DateTime|false      DateTime object hoặc false nếu invalid
     */
    private function parse_date($date_str)
    {
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
     * VD: 2500000 → "2.500.000 VNĐ"
     *
     * @since   2.0.0
     * @param   float   $price  Giá
     * @return  string          Formatted price
     */
    private function format_price_short($price)
    {
        $price = (float) $price;
        return number_format($price, 0, ',', '.') . ' VNĐ';
    }
}

/**
 * ============================================================================
 * BACKWARD COMPATIBILITY
 * ============================================================================
 */

// Alias cho code cũ vẫn dùng Vie_Pricing_Engine
if (!class_exists('Vie_Pricing_Engine')) {
    class_alias('Vie_Pricing_Service', 'Vie_Pricing_Engine');
}
