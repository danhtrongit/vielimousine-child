# RULE-02: QUY CHUẨN DOCUMENT CLASS & FUNCTION

**Phiên bản:** 1.0  
**Áp dụng cho:** Tất cả Class và Function trong PHP  
**Bắt buộc:** ✅ CÓ

---

## 1. MỤC ĐÍCH

- IDE có thể hiển thị tooltip khi hover
- Auto-complete hoạt động chính xác
- Generate API documentation tự động
- Developer mới hiểu code nhanh hơn

---

## 2. CLASS DOCBLOCK

### Template

```php
/**
 * ============================================================================
 * CLASS: [TênClass]
 * ============================================================================
 * 
 * [Mô tả 1-2 câu về class này làm gì]
 * 
 * @since   [version]
 * @uses    [Class phụ thuộc 1]    [Mô tả ngắn]
 * @uses    [Class phụ thuộc 2]    [Mô tả ngắn]
 * 
 * @property    [type]  $property1  [Mô tả]
 * @property    [type]  $property2  [Mô tả]
 */
class TenClass {
    // ...
}
```

### Ví dụ thực tế

```php
/**
 * ============================================================================
 * CLASS: Vie_Booking_Manager
 * ============================================================================
 * 
 * Quản lý nghiệp vụ đặt phòng: tạo mới, cập nhật trạng thái, xử lý thanh toán.
 * Triển khai Singleton Pattern.
 * 
 * @since   2.0.0
 * @uses    Vie_Pricing_Engine      Tính giá booking
 * @uses    Vie_Email_Manager       Gửi email xác nhận
 * @uses    Vie_Database_Helper     Thao tác database
 * @uses    Vie_SePay_Gateway       Xử lý thanh toán
 * 
 * @property    wpdb    $db             WordPress Database object
 * @property    string  $table_bookings Tên bảng bookings
 * @property    array   $status_labels  Mapping status => label
 */
class Vie_Booking_Manager {
    
    /**
     * Singleton instance
     * @var Vie_Booking_Manager|null
     */
    private static $instance = null;
    
    /**
     * WordPress Database object
     * @var wpdb
     */
    private $db;
    
    /**
     * Tên bảng bookings trong database
     * @var string
     */
    private $table_bookings;
}
```

---

## 3. FUNCTION/METHOD DOCBLOCK

### Template đầy đủ

```php
/**
 * [Mô tả ngắn gọn - dòng đầu tiên]
 * 
 * [Mô tả chi tiết hơn nếu cần, có thể nhiều dòng.
 * Giải thích logic phức tạp, các bước xử lý chính.]
 * 
 * @since   [version]
 * @access  [public|private|protected]
 * 
 * @see     [Class/Function liên quan]
 * @link    [URL documentation nếu có]
 * 
 * @global  [type]  $global_var  [Mô tả biến global được sử dụng]
 * 
 * @param   [type]      $param1     [Mô tả parameter 1]
 * @param   [type]      $param2     [Mô tả parameter 2] {
 *     [Mô tả array/object structure nếu cần]
 * 
 *     @type   [type]  $key1   [Mô tả]
 *     @type   [type]  $key2   [Mô tả]
 * }
 * @param   [type]      $param3     Optional. [Mô tả]. Default [giá trị].
 * 
 * @return  [type]|WP_Error {
 *     [Mô tả return value]
 * 
 *     @type   [type]  $key1   [Mô tả]
 *     @type   [type]  $key2   [Mô tả]
 * }
 * 
 * @throws  [ExceptionType]  [Điều kiện throw exception]
 * 
 * @example
 * // Ví dụ cách sử dụng
 * $result = $this->function_name($param1, $param2);
 */
```

### Ví dụ: Function đơn giản

```php
/**
 * Format số tiền theo định dạng Việt Nam
 * 
 * @since   2.0.0
 * 
 * @param   float   $amount     Số tiền cần format
 * @param   bool    $with_unit  Có hiển thị "VNĐ" không. Default true.
 * 
 * @return  string  Số tiền đã format (VD: "1.500.000 VNĐ")
 */
function vie_format_currency( float $amount, bool $with_unit = true ): string {
    $formatted = number_format( $amount, 0, ',', '.' );
    return $with_unit ? $formatted . ' VNĐ' : $formatted;
}
```

### Ví dụ: Function phức tạp

```php
/**
 * Tạo đơn đặt phòng mới
 * 
 * Xử lý toàn bộ flow tạo booking:
 * 1. Validate dữ liệu đầu vào
 * 2. Kiểm tra phòng còn trống trong khoảng ngày
 * 3. Tính tổng tiền (giá phòng + phụ thu)
 * 4. Generate booking code unique
 * 5. Lưu vào database
 * 6. Gửi email xác nhận cho khách
 * 7. Gửi notification cho admin
 * 
 * @since   2.0.0
 * @access  public
 * 
 * @see     Vie_Pricing_Engine::calculate_for_dates()
 * @see     Vie_Email_Manager::send_booking_confirmation()
 * 
 * @param   array   $booking_data {
 *     Dữ liệu đặt phòng từ form
 * 
 *     @type   int     $room_id            ID phòng trong bảng hotel_rooms
 *     @type   int     $hotel_id           ID hotel (post_id)
 *     @type   string  $check_in           Ngày nhận phòng (format: Y-m-d)
 *     @type   string  $check_out          Ngày trả phòng (format: Y-m-d)
 *     @type   int     $num_rooms          Số lượng phòng đặt (1-10)
 *     @type   int     $num_adults         Số người lớn (1-20)
 *     @type   int     $num_children       Số trẻ em (0-10)
 *     @type   array   $children_ages      Mảng tuổi từng trẻ em [5, 8, 12]
 *     @type   string  $price_type         Loại giá: 'room' hoặc 'combo'
 *     @type   string  $customer_name      Họ tên khách hàng
 *     @type   string  $customer_phone     Số điện thoại (10-11 số)
 *     @type   string  $customer_email     Email (optional)
 *     @type   string  $customer_note      Ghi chú (optional)
 * }
 * @param   bool    $send_email     Có gửi email xác nhận không. Default true.
 * 
 * @return  array|WP_Error {
 *     Kết quả tạo booking nếu thành công
 * 
 *     @type   bool    $success        Luôn true nếu thành công
 *     @type   int     $booking_id     ID của booking trong database
 *     @type   string  $booking_code   Mã đặt phòng (VD: VIE-20241129-001)
 *     @type   string  $booking_hash   Hash 32 ký tự cho URL checkout
 *     @type   float   $total_amount   Tổng tiền đã tính
 *     @type   string  $checkout_url   URL trang thanh toán
 * }
 * 
 * @throws  InvalidArgumentException  Nếu thiếu trường bắt buộc
 * @throws  RuntimeException          Nếu phòng đã hết trong ngày đó
 * 
 * @example
 * $manager = Vie_Booking_Manager::get_instance();
 * 
 * $result = $manager->create_booking([
 *     'room_id'        => 5,
 *     'hotel_id'       => 123,
 *     'check_in'       => '2024-12-01',
 *     'check_out'      => '2024-12-03',
 *     'num_rooms'      => 1,
 *     'num_adults'     => 2,
 *     'num_children'   => 1,
 *     'children_ages'  => [8],
 *     'price_type'     => 'combo',
 *     'customer_name'  => 'Nguyễn Văn A',
 *     'customer_phone' => '0901234567',
 *     'customer_email' => 'email@example.com',
 * ]);
 * 
 * if ( is_wp_error($result) ) {
 *     $error_message = $result->get_error_message();
 * } else {
 *     $checkout_url = $result['checkout_url'];
 *     wp_redirect( $checkout_url );
 * }
 */
public function create_booking( array $booking_data, bool $send_email = true ) {
    // Implementation...
}
```

---

## 4. PROPERTY DOCBLOCK

```php
class Vie_Room_Manager {
    
    /**
     * Singleton instance
     * 
     * @since   2.0.0
     * @var     Vie_Room_Manager|null
     */
    private static $instance = null;
    
    /**
     * WordPress database object
     * 
     * @since   2.0.0
     * @var     wpdb
     */
    private $db;
    
    /**
     * Cache các phòng đã query
     * Key là room_id, value là object room
     * 
     * @since   2.0.0
     * @var     array<int, object>
     */
    private $rooms_cache = [];
    
    /**
     * Mapping status code => display info
     * 
     * @since   2.0.0
     * @var     array<string, array{label: string, color: string}>
     */
    private $status_map = [
        'active'   => ['label' => 'Hoạt động', 'color' => '#10b981'],
        'inactive' => ['label' => 'Tạm ngừng', 'color' => '#f59e0b'],
        'draft'    => ['label' => 'Nháp', 'color' => '#6b7280'],
    ];
}
```

---

## 5. INLINE COMMENTS

### Khi nào cần inline comment?

1. **Logic phức tạp** - Giải thích WHY, không phải WHAT
2. **Workaround/Hack** - Giải thích tại sao cần làm vậy
3. **Magic numbers** - Giải thích ý nghĩa con số
4. **Business logic** - Giải thích quy tắc nghiệp vụ

### Template Section Comment

```php
/**
 * -------------------------------------------------------------------------
 * SECTION: [TÊN SECTION]
 * -------------------------------------------------------------------------
 * [Mô tả section này làm gì]
 */
```

### Ví dụ

```php
public function calculate_surcharges( $room, $num_adults, $num_children, $children_ages ) {
    $surcharges = [];
    $total = 0;
    
    /**
     * -------------------------------------------------------------------------
     * BƯỚC 1: PHỤ THU NGƯỜI LỚN THÊM
     * -------------------------------------------------------------------------
     * Mỗi phòng có capacity mặc định (VD: 2 người lớn)
     * Nếu số người lớn > capacity, tính phụ thu cho mỗi người thêm
     */
    $default_capacity = (int) $room->default_adults;
    $extra_adults = max( 0, $num_adults - $default_capacity );
    
    if ( $extra_adults > 0 && $room->extra_adult_price > 0 ) {
        // Phụ thu = số người thêm × giá/người × số đêm
        $adult_surcharge = $extra_adults * (float) $room->extra_adult_price;
        $surcharges[] = [
            'type'   => 'extra_adult',
            'label'  => sprintf( 'Phụ thu %d người lớn', $extra_adults ),
            'amount' => $adult_surcharge,
        ];
        $total += $adult_surcharge;
    }
    
    /**
     * -------------------------------------------------------------------------
     * BƯỚC 2: PHỤ THU TRẺ EM
     * -------------------------------------------------------------------------
     * Quy tắc nghiệp vụ:
     * - Trẻ dưới 6 tuổi: MIỄN PHÍ
     * - Trẻ 6-11 tuổi: Tính 50% giá người lớn (child_price)
     * - Trẻ 12+ tuổi: Tính như người lớn (extra_adult_price)
     */
    $child_threshold_free = 6;   // Dưới tuổi này miễn phí
    $child_threshold_full = 12;  // Từ tuổi này tính như người lớn
    
    foreach ( $children_ages as $age ) {
        if ( $age < $child_threshold_free ) {
            // Miễn phí, không tính phụ thu
            continue;
        }
        
        if ( $age < $child_threshold_full ) {
            // Tính giá trẻ em (thường = 50% người lớn)
            $surcharge_amount = (float) $room->child_price;
            $surcharge_label = sprintf( 'Trẻ em %d tuổi', $age );
        } else {
            // Tính như người lớn
            $surcharge_amount = (float) $room->extra_adult_price;
            $surcharge_label = sprintf( 'Trẻ %d tuổi (tính như NL)', $age );
        }
        
        $surcharges[] = [
            'type'   => 'child',
            'age'    => $age,
            'label'  => $surcharge_label,
            'amount' => $surcharge_amount,
        ];
        $total += $surcharge_amount;
    }
    
    return [
        'details' => $surcharges,
        'total'   => $total,
    ];
}
```

---

## 6. CHECKLIST REVIEW

- [ ] Mọi class public đều có docblock?
- [ ] Mọi method public/protected đều có docblock?
- [ ] @param type khớp với type hint trong code?
- [ ] @return type khớp với return type trong code?
- [ ] Logic phức tạp có inline comment giải thích?
- [ ] Mô tả viết bằng tiếng Việt có dấu?

---

## 7. IDE INTEGRATION

### PHPStorm

Cài đặt để generate docblock tự động:
- **Settings** → **Editor** → **File and Code Templates** → **Includes** → **PHP Function Doc Comment**

### VSCode

Cài extension:
- **PHP DocBlocker** - Tự động generate docblock khi gõ `/**`
- **PHP Intelephense** - Hiển thị tooltip từ docblock
