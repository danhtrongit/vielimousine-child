<?php
/**
 * Helper Functions for Hotel Rooms Module
 * 
 * Các hàm tiện ích và tính toán giá phòng
 * 
 * @package VieHotelRooms
 */

if (!defined('ABSPATH')) {
    exit;
}

class Vie_Hotel_Rooms_Helpers {
    
    /**
     * Tính tổng tiền phòng cho khoảng ngày
     * 
     * @param int    $room_id   ID loại phòng
     * @param string $checkin   Ngày nhận phòng (YYYY-MM-DD)
     * @param string $checkout  Ngày trả phòng (YYYY-MM-DD)
     * @param string $price_type 'room' hoặc 'combo'
     * @param array  $guests    Thông tin khách ['adults' => 2, 'children' => [{'age' => 5}, {'age' => 8}]]
     * @return array
     */
    public static function get_room_price($room_id, $checkin, $checkout, $price_type = 'room', $guests = array()) {
        global $wpdb;
        
        $result = array(
            'success' => false,
            'room_total' => 0,
            'surcharge_total' => 0,
            'grand_total' => 0,
            'nights' => 0,
            'daily_breakdown' => array(),
            'surcharge_breakdown' => array(),
            'errors' => array(),
            'warnings' => array()
        );
        
        // Validate dates
        $checkin_date = DateTime::createFromFormat('Y-m-d', $checkin);
        $checkout_date = DateTime::createFromFormat('Y-m-d', $checkout);
        
        if (!$checkin_date || !$checkout_date) {
            $result['errors'][] = 'Invalid date format. Use YYYY-MM-DD.';
            return $result;
        }
        
        if ($checkout_date <= $checkin_date) {
            $result['errors'][] = 'Checkout date must be after checkin date.';
            return $result;
        }
        
        // Calculate nights
        $interval = $checkin_date->diff($checkout_date);
        $nights = $interval->days;
        $result['nights'] = $nights;
        
        // Get room info
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_rooms} WHERE id = %d AND status = 'active'",
            $room_id
        ));
        
        if (!$room) {
            $result['errors'][] = 'Room not found or inactive.';
            return $result;
        }
        
        // Get pricing data for date range
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        $price_column = ($price_type === 'combo') ? 'price_combo' : 'price_room';
        
        $pricing = $wpdb->get_results($wpdb->prepare(
            "SELECT date, {$price_column} as price, stock, status, day_of_week 
             FROM {$table_pricing} 
             WHERE room_id = %d 
             AND date >= %s 
             AND date < %s 
             ORDER BY date ASC",
            $room_id,
            $checkin,
            $checkout
        ));
        
        // Create pricing map
        $pricing_map = array();
        foreach ($pricing as $p) {
            $pricing_map[$p->date] = $p;
        }
        
        // Calculate daily prices
        $current_date = clone $checkin_date;
        $room_total = 0;
        $missing_dates = array();
        
        while ($current_date < $checkout_date) {
            $date_str = $current_date->format('Y-m-d');
            $day_name = $current_date->format('l');
            
            $daily = array(
                'date' => $date_str,
                'day' => $day_name,
                'price' => 0,
                'available' => true,
                'source' => ''
            );
            
            if (isset($pricing_map[$date_str])) {
                $price_data = $pricing_map[$date_str];
                
                // Check availability
                if ($price_data->status === 'stop_sell' || $price_data->status === 'sold_out') {
                    $daily['available'] = false;
                    $result['errors'][] = "Room not available on {$date_str}";
                }
                
                if ($price_data->price !== null && $price_data->price > 0) {
                    $daily['price'] = floatval($price_data->price);
                    $daily['source'] = 'calendar';
                } else {
                    // Fallback to base price
                    $daily['price'] = floatval($room->base_price);
                    $daily['source'] = 'base_price';
                    $result['warnings'][] = "Using base price for {$date_str}";
                }
            } else {
                // No pricing data for this date
                $missing_dates[] = $date_str;
                $daily['price'] = floatval($room->base_price);
                $daily['source'] = 'base_price_fallback';
                $result['warnings'][] = "No pricing set for {$date_str}, using base price";
            }
            
            $room_total += $daily['price'];
            $result['daily_breakdown'][] = $daily;
            
            $current_date->modify('+1 day');
        }
        
        $result['room_total'] = $room_total;
        
        // Calculate surcharges
        if (!empty($guests)) {
            $surcharge_result = self::calculate_surcharges($room_id, $nights, $guests, $price_type);
            $result['surcharge_total'] = $surcharge_result['total'];
            $result['surcharge_breakdown'] = $surcharge_result['breakdown'];
        }
        
        // Grand total
        $result['grand_total'] = $result['room_total'] + $result['surcharge_total'];
        
        // Set success if no errors
        $result['success'] = empty($result['errors']);
        
        return $result;
    }
    
    /**
     * Tính phụ thu
     */
    public static function calculate_surcharges($room_id, $nights, $guests, $price_type = 'room') {
        global $wpdb;
        
        $result = array(
            'total' => 0,
            'breakdown' => array()
        );
        
        $table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
        $applies_column = ($price_type === 'combo') ? 'applies_to_combo' : 'applies_to_room';
        
        // Get active surcharges for room
        $surcharges = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_surcharges} 
             WHERE room_id = %d 
             AND status = 'active'
             AND {$applies_column} = 1
             ORDER BY sort_order ASC",
            $room_id
        ));
        
        if (empty($surcharges)) {
            return $result;
        }
        
        $adults = isset($guests['adults']) ? intval($guests['adults']) : 2;
        $children = isset($guests['children']) ? $guests['children'] : array();
        
        // Get room info for occupancy check
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        $room = $wpdb->get_row($wpdb->prepare(
            "SELECT base_occupancy, max_adults, max_children FROM {$table_rooms} WHERE id = %d",
            $room_id
        ));
        
        foreach ($surcharges as $surcharge) {
            $apply_count = 0;
            $surcharge_amount = 0;
            
            switch ($surcharge->surcharge_type) {
                case 'adult':
                    // Extra adult beyond base occupancy
                    if ($adults > $room->base_occupancy) {
                        $apply_count = $adults - $room->base_occupancy;
                    }
                    break;
                    
                case 'child':
                    // Children within age range
                    foreach ($children as $child) {
                        $age = isset($child['age']) ? intval($child['age']) : 0;
                        
                        $min_age = ($surcharge->min_age !== null) ? intval($surcharge->min_age) : 0;
                        $max_age = ($surcharge->max_age !== null) ? intval($surcharge->max_age) : 999;
                        
                        if ($age >= $min_age && $age <= $max_age) {
                            $apply_count++;
                        }
                    }
                    break;
                    
                case 'extra_bed':
                    // Extra bed request
                    if (isset($guests['extra_bed']) && $guests['extra_bed']) {
                        $apply_count = intval($guests['extra_bed']);
                    }
                    break;
                    
                case 'breakfast':
                    // Breakfast surcharge per person
                    if (isset($guests['breakfast']) && $guests['breakfast']) {
                        $apply_count = $adults + count($children);
                    }
                    break;
                    
                default:
                    // Other surcharges
                    if (isset($guests[$surcharge->surcharge_type])) {
                        $apply_count = intval($guests[$surcharge->surcharge_type]);
                    }
                    break;
            }
            
            if ($apply_count > 0) {
                $surcharge_amount = floatval($surcharge->amount) * $apply_count;
                
                // Apply per night if needed
                if ($surcharge->is_per_night) {
                    $surcharge_amount *= $nights;
                }
                
                $result['total'] += $surcharge_amount;
                $result['breakdown'][] = array(
                    'type' => $surcharge->surcharge_type,
                    'label' => $surcharge->label ?: ucfirst($surcharge->surcharge_type),
                    'unit_amount' => floatval($surcharge->amount),
                    'quantity' => $apply_count,
                    'nights' => $surcharge->is_per_night ? $nights : 1,
                    'total' => $surcharge_amount,
                    'notes' => $surcharge->notes
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Kiểm tra phòng trống
     */
    public static function check_availability($room_id, $checkin, $checkout, $quantity = 1) {
        global $wpdb;
        
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        
        // Get minimum stock in date range
        $min_stock = $wpdb->get_var($wpdb->prepare(
            "SELECT MIN(stock) FROM {$table_pricing} 
             WHERE room_id = %d 
             AND date >= %s 
             AND date < %s 
             AND status IN ('available', 'limited')",
            $room_id,
            $checkin,
            $checkout
        ));
        
        if ($min_stock === null) {
            // No pricing data, check room total
            $table_rooms = $wpdb->prefix . 'hotel_rooms';
            $total = $wpdb->get_var($wpdb->prepare(
                "SELECT total_rooms FROM {$table_rooms} WHERE id = %d",
                $room_id
            ));
            $min_stock = $total ?: 0;
        }
        
        return array(
            'available' => intval($min_stock) >= $quantity,
            'stock' => intval($min_stock),
            'requested' => $quantity
        );
    }
    
    /**
     * Get hotels (post type) for dropdown
     */
    public static function get_hotels() {
        $args = array(
            'post_type' => 'hotel',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        );
        
        $hotels = get_posts($args);
        
        return $hotels;
    }
    
    /**
     * Get rooms by hotel
     */
    public static function get_rooms_by_hotel($hotel_id = 0, $status = 'active') {
        global $wpdb;
        
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        
        $where = array("1=1");
        $values = array();
        
        if ($hotel_id > 0) {
            $where[] = "hotel_id = %d";
            $values[] = $hotel_id;
        }
        
        if ($status !== 'all') {
            $where[] = "status = %s";
            $values[] = $status;
        }
        
        $where_sql = implode(' AND ', $where);
        
        if (!empty($values)) {
            $sql = $wpdb->prepare(
                "SELECT * FROM {$table_rooms} WHERE {$where_sql} ORDER BY sort_order ASC, name ASC",
                $values
            );
        } else {
            $sql = "SELECT * FROM {$table_rooms} WHERE {$where_sql} ORDER BY sort_order ASC, name ASC";
        }
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Get single room
     */
    public static function get_room($room_id) {
        global $wpdb;
        
        $table_rooms = $wpdb->prefix . 'hotel_rooms';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_rooms} WHERE id = %d",
            $room_id
        ));
    }
    
    /**
     * Get room pricing for date range
     */
    public static function get_room_pricing($room_id, $start_date, $end_date) {
        global $wpdb;
        
        $table_pricing = $wpdb->prefix . 'hotel_room_pricing';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_pricing} 
             WHERE room_id = %d 
             AND date >= %s 
             AND date <= %s 
             ORDER BY date ASC",
            $room_id,
            $start_date,
            $end_date
        ));
    }
    
    /**
     * Get room surcharges
     */
    public static function get_room_surcharges($room_id) {
        global $wpdb;
        
        $table_surcharges = $wpdb->prefix . 'hotel_room_surcharges';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_surcharges} 
             WHERE room_id = %d 
             ORDER BY sort_order ASC, surcharge_type ASC",
            $room_id
        ));
    }
    
    /**
     * Format currency
     */
    public static function format_currency($amount) {
        return number_format($amount, 0, ',', '.') . ' ₫';
    }
    
    /**
     * Format date Vietnamese
     */
    public static function format_date_vn($date) {
        $timestamp = strtotime($date);
        $days = array('CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7');
        $day_of_week = $days[date('w', $timestamp)];
        return $day_of_week . ', ' . date('d/m/Y', $timestamp);
    }
    
    /**
     * Sanitize room data
     */
    public static function sanitize_room_data($data) {
        // Handle gallery_ids - can be array or JSON string
        $gallery_ids = $data['gallery_ids'] ?? array();
        if (is_string($gallery_ids)) {
            $gallery_ids = json_decode($gallery_ids, true);
            if (!is_array($gallery_ids)) {
                $gallery_ids = array();
            }
        }
        
        // Handle amenities - can be array or JSON string
        $amenities = $data['amenities'] ?? array();
        if (is_string($amenities)) {
            $amenities = json_decode($amenities, true);
            if (!is_array($amenities)) {
                $amenities = array();
            }
        }
        
        return array(
            'hotel_id' => absint($data['hotel_id'] ?? 0),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'slug' => sanitize_title($data['slug'] ?? $data['name'] ?? ''),
            'description' => wp_kses_post($data['description'] ?? ''),
            'short_description' => sanitize_textarea_field($data['short_description'] ?? ''),
            'gallery_ids' => wp_json_encode(array_map('absint', $gallery_ids)),
            'featured_image_id' => absint($data['featured_image_id'] ?? 0),
            'amenities' => wp_json_encode($amenities),
            'room_size' => sanitize_text_field($data['room_size'] ?? ''),
            'bed_type' => sanitize_text_field($data['bed_type'] ?? ''),
            'view_type' => sanitize_text_field($data['view_type'] ?? ''),
            'base_occupancy' => absint($data['base_occupancy'] ?? 2),
            'max_adults' => absint($data['max_adults'] ?? 2),
            'max_children' => absint($data['max_children'] ?? 2),
            'max_occupancy' => absint($data['max_occupancy'] ?? 4),
            'total_rooms' => absint($data['total_rooms'] ?? 1),
            'base_price' => floatval($data['base_price'] ?? 0),
            'sort_order' => intval($data['sort_order'] ?? 0),
            'status' => in_array($data['status'] ?? '', array('active', 'inactive', 'draft')) 
                        ? $data['status'] : 'active'
        );
    }
}

/**
 * Wrapper function for price calculation
 */
function vie_get_room_price($room_id, $checkin, $checkout, $price_type = 'room', $guests = array()) {
    return Vie_Hotel_Rooms_Helpers::get_room_price($room_id, $checkin, $checkout, $price_type, $guests);
}

/**
 * Wrapper function for availability check
 */
function vie_check_room_availability($room_id, $checkin, $checkout, $quantity = 1) {
    return Vie_Hotel_Rooms_Helpers::check_availability($room_id, $checkin, $checkout, $quantity);
}
