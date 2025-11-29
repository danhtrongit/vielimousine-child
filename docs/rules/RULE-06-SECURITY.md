# RULE-06: QUY CHUẨN BẢO MẬT

**Phiên bản:** 1.0  
**Áp dụng cho:** Tất cả code trong theme  
**Mức độ:** 🔴 CRITICAL - Bắt buộc tuân thủ 100%

---

## 1. NGUYÊN TẮC CHUNG

1. **Never Trust User Input** - Luôn validate & sanitize mọi dữ liệu từ người dùng
2. **Defense in Depth** - Nhiều lớp bảo vệ tốt hơn một lớp
3. **Principle of Least Privilege** - Chỉ cấp quyền tối thiểu cần thiết
4. **Fail Securely** - Khi lỗi xảy ra, mặc định từ chối access

---

## 2. INPUT VALIDATION & SANITIZATION

### 2.1 Sanitize Functions phổ biến

| Function | Dùng cho | Ví dụ |
|----------|----------|-------|
| `sanitize_text_field()` | Text đơn giản | Tên, tiêu đề |
| `sanitize_email()` | Email | customer_email |
| `sanitize_textarea_field()` | Textarea | Ghi chú, mô tả |
| `absint()` | Integer dương | ID, số lượng |
| `intval()` | Integer (có thể âm) | Offset, delta |
| `floatval()` | Số thực | Giá tiền |
| `wp_kses_post()` | HTML an toàn | Content từ editor |
| `esc_url()` | URL | Links |
| `sanitize_file_name()` | Tên file | Upload files |

### 2.2 Ví dụ thực tế

```php
/**
 * Xử lý dữ liệu booking từ form
 * 
 * @param array $raw_data Dữ liệu thô từ $_POST
 * @return array Dữ liệu đã sanitize
 */
function vie_sanitize_booking_data( array $raw_data ): array {
    return [
        // Integer
        'room_id'       => absint( $raw_data['room_id'] ?? 0 ),
        'hotel_id'      => absint( $raw_data['hotel_id'] ?? 0 ),
        'num_rooms'     => absint( $raw_data['num_rooms'] ?? 1 ),
        'num_adults'    => absint( $raw_data['num_adults'] ?? 2 ),
        'num_children'  => absint( $raw_data['num_children'] ?? 0 ),
        
        // Text
        'customer_name'  => sanitize_text_field( $raw_data['customer_name'] ?? '' ),
        'customer_phone' => sanitize_text_field( $raw_data['customer_phone'] ?? '' ),
        'customer_email' => sanitize_email( $raw_data['customer_email'] ?? '' ),
        'customer_note'  => sanitize_textarea_field( $raw_data['customer_note'] ?? '' ),
        
        // Date (validate format)
        'check_in'  => vie_sanitize_date( $raw_data['check_in'] ?? '' ),
        'check_out' => vie_sanitize_date( $raw_data['check_out'] ?? '' ),
        
        // Enum (whitelist)
        'price_type' => in_array( $raw_data['price_type'] ?? '', ['room', 'combo'], true ) 
                        ? $raw_data['price_type'] 
                        : 'room',
        
        // Array
        'children_ages' => isset( $raw_data['children_ages'] ) 
                          ? array_map( 'absint', (array) $raw_data['children_ages'] )
                          : [],
    ];
}

/**
 * Sanitize date string
 */
function vie_sanitize_date( string $date ): string {
    // Nếu format dd/mm/yyyy
    if ( preg_match( '/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches ) ) {
        return sprintf( '%04d-%02d-%02d', $matches[3], $matches[2], $matches[1] );
    }
    
    // Nếu format Y-m-d
    if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return $date;
    }
    
    return '';
}
```

---

## 3. OUTPUT ESCAPING

### 3.1 Escape Functions

| Function | Dùng cho | Context |
|----------|----------|---------|
| `esc_html()` | Text trong HTML | `<p><?php echo esc_html($text); ?></p>` |
| `esc_attr()` | Attributes | `<input value="<?php echo esc_attr($value); ?>">` |
| `esc_url()` | URLs | `<a href="<?php echo esc_url($url); ?>">` |
| `esc_js()` | JavaScript | `<script>var x = "<?php echo esc_js($str); ?>"</script>` |
| `esc_textarea()` | Textarea content | `<textarea><?php echo esc_textarea($text); ?></textarea>` |
| `wp_kses_post()` | HTML từ editor | Post content có format |

### 3.2 Quy tắc ECHO

```php
<!-- ✅ ĐÚNG - Luôn escape khi echo -->
<h1><?php echo esc_html( $booking->customer_name ); ?></h1>
<input type="text" name="phone" value="<?php echo esc_attr( $booking->customer_phone ); ?>">
<a href="<?php echo esc_url( $checkout_url ); ?>">Thanh toán</a>

<!-- ✅ ĐÚNG - Dùng shorthand escaping -->
<h1><?php esc_html_e( 'Đặt phòng thành công', 'viechild' ); ?></h1>
<p><?php echo esc_html__( 'Mã đặt phòng:', 'viechild' ); ?> <?php echo esc_html( $code ); ?></p>

<!-- ❌ SAI - Echo trực tiếp -->
<h1><?php echo $booking->customer_name; ?></h1>
<input value="<?php echo $_GET['search']; ?>">
```

### 3.3 JSON Output

```php
// ✅ ĐÚNG - Escape JSON cho JavaScript
<script>
var bookingData = <?php echo wp_json_encode( $data ); ?>;
</script>

// ❌ SAI
<script>
var bookingData = <?php echo json_encode( $data ); ?>; // Không escape
</script>
```

---

## 4. NONCE VERIFICATION

### 4.1 Form Submissions

```php
// Trong form HTML
<form method="post" action="">
    <?php wp_nonce_field( 'vie_booking_action', 'vie_booking_nonce' ); ?>
    <!-- form fields -->
</form>

// Trong handler
function vie_handle_booking_form() {
    // ✅ ĐÚNG - Verify nonce TRƯỚC khi xử lý
    if ( ! isset( $_POST['vie_booking_nonce'] ) || 
         ! wp_verify_nonce( $_POST['vie_booking_nonce'], 'vie_booking_action' ) 
    ) {
        wp_die( 'Security check failed', 'Error', ['response' => 403] );
    }
    
    // Sau đó mới xử lý data
    $data = vie_sanitize_booking_data( $_POST );
    // ...
}
```

### 4.2 AJAX Requests

```php
// PHP: Tạo nonce và localize
wp_localize_script( 'vie-booking', 'vieBooking', [
    'ajaxUrl' => admin_url( 'admin-ajax.php' ),
    'nonce'   => wp_create_nonce( 'vie_booking_nonce' ),
] );

// PHP: AJAX handler
add_action( 'wp_ajax_vie_submit_booking', 'vie_ajax_submit_booking' );
add_action( 'wp_ajax_nopriv_vie_submit_booking', 'vie_ajax_submit_booking' );

function vie_ajax_submit_booking() {
    // ✅ ĐÚNG - check_ajax_referer ở đầu function
    check_ajax_referer( 'vie_booking_nonce', 'nonce' );
    
    // Sau đó xử lý
    $data = vie_sanitize_booking_data( $_POST );
    // ...
    
    wp_send_json_success( $result );
}
```

```javascript
// JavaScript: Gửi nonce trong request
$.ajax({
    url: vieBooking.ajaxUrl,
    type: 'POST',
    data: {
        action: 'vie_submit_booking',
        nonce: vieBooking.nonce,  // ← Luôn gửi nonce
        // ... other data
    }
});
```

---

## 5. CAPABILITY CHECKS

### 5.1 Admin Functions

```php
/**
 * Xóa booking - Chỉ admin mới được phép
 */
function vie_delete_booking( int $booking_id ): bool {
    // ✅ ĐÚNG - Check capability
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Bạn không có quyền thực hiện hành động này.' );
    }
    
    // ... xử lý xóa
}

/**
 * AJAX handler cho admin
 */
function vie_ajax_admin_action() {
    check_ajax_referer( 'vie_admin_nonce', 'nonce' );
    
    // ✅ ĐÚNG - Check capability cho AJAX
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( ['message' => 'Unauthorized'], 403 );
    }
    
    // ... xử lý
}
```

### 5.2 Capability Matrix

| Action | Capability cần |
|--------|---------------|
| Xem danh sách booking | `manage_options` |
| Sửa booking | `manage_options` |
| Xóa booking | `manage_options` |
| Quản lý phòng | `manage_options` |
| Cài đặt hệ thống | `manage_options` |
| Xem báo cáo | `manage_options` |

---

## 6. SQL INJECTION PREVENTION

### 6.1 Luôn dùng Prepared Statements

```php
global $wpdb;

// ✅ ĐÚNG - Dùng $wpdb->prepare()
$booking = $wpdb->get_row( 
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hotel_bookings WHERE id = %d",
        $booking_id
    )
);

// ✅ ĐÚNG - Multiple placeholders
$bookings = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hotel_bookings 
         WHERE hotel_id = %d 
         AND status = %s 
         AND check_in >= %s",
        $hotel_id,
        $status,
        $date_from
    )
);

// ✅ ĐÚNG - LIKE với esc_like
$search = '%' . $wpdb->esc_like( $search_term ) . '%';
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}hotel_bookings 
         WHERE customer_name LIKE %s",
        $search
    )
);

// ❌ SAI - Trực tiếp nhúng biến
$booking = $wpdb->get_row( 
    "SELECT * FROM {$wpdb->prefix}hotel_bookings WHERE id = $booking_id"
);

// ❌ SAI - String concatenation
$booking = $wpdb->get_row( 
    "SELECT * FROM {$wpdb->prefix}hotel_bookings WHERE id = " . $_GET['id']
);
```

### 6.2 Placeholder Types

| Placeholder | Dùng cho | Ví dụ |
|-------------|----------|-------|
| `%d` | Integer | ID, counts |
| `%f` | Float | Prices |
| `%s` | String | Names, dates |

---

## 7. XSS PREVENTION

### 7.1 Stored XSS

```php
// Khi LƯU dữ liệu - Sanitize
$customer_name = sanitize_text_field( $_POST['customer_name'] );
$customer_note = sanitize_textarea_field( $_POST['customer_note'] );

// Khi HIỂN THỊ - Escape
echo '<p>' . esc_html( $booking->customer_name ) . '</p>';
echo '<p>' . esc_html( $booking->customer_note ) . '</p>';
```

### 7.2 Reflected XSS

```php
// ❌ SAI - Hiển thị trực tiếp GET parameter
echo 'Tìm kiếm: ' . $_GET['search'];

// ✅ ĐÚNG - Escape GET parameter
echo 'Tìm kiếm: ' . esc_html( $_GET['search'] ?? '' );
```

### 7.3 DOM-based XSS (JavaScript)

```javascript
// ❌ SAI - innerHTML với user data
$('#result').html(userInput);

// ✅ ĐÚNG - textContent hoặc jQuery text()
$('#result').text(userInput);

// ✅ ĐÚNG - Nếu cần HTML, sanitize trước
var sanitized = DOMPurify.sanitize(userInput);
$('#result').html(sanitized);
```

---

## 8. IDOR PREVENTION

IDOR = Insecure Direct Object References

### 8.1 Vấn đề

```php
// ❌ SAI - Dùng ID trực tiếp trong URL
// URL: /checkout/?booking_id=123
// Attacker có thể thử: /checkout/?booking_id=124, 125, 126...
$booking_id = $_GET['booking_id'];
$booking = get_booking( $booking_id );
```

### 8.2 Giải pháp: Dùng Hash Token

```php
// ✅ ĐÚNG - Dùng random hash thay vì ID
// URL: /checkout/?code=a8f5f167f44f4964

// Khi tạo booking, generate hash
$booking_hash = wp_generate_password( 32, false );
$wpdb->insert( $table, [
    // ... other fields
    'booking_hash' => $booking_hash,
] );

// Redirect đến checkout với hash
$checkout_url = add_query_arg( 'code', $booking_hash, home_url( '/checkout/' ) );

// Trong checkout, verify bằng hash
$booking_hash = sanitize_text_field( $_GET['code'] ?? '' );
$booking = $wpdb->get_row( 
    $wpdb->prepare(
        "SELECT * FROM {$table} WHERE booking_hash = %s",
        $booking_hash
    )
);

if ( ! $booking ) {
    wp_redirect( home_url() );
    exit;
}
```

---

## 9. FILE UPLOAD SECURITY

```php
/**
 * Validate uploaded file
 */
function vie_validate_upload( array $file ): bool|WP_Error {
    // Check for upload errors
    if ( $file['error'] !== UPLOAD_ERR_OK ) {
        return new WP_Error( 'upload_error', 'Upload failed' );
    }
    
    // Allowed file types (whitelist)
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open( FILEINFO_MIME_TYPE );
    $mime = finfo_file( $finfo, $file['tmp_name'] );
    finfo_close( $finfo );
    
    if ( ! in_array( $mime, $allowed_types, true ) ) {
        return new WP_Error( 'invalid_type', 'File type không được phép' );
    }
    
    // Max file size (5MB)
    $max_size = 5 * 1024 * 1024;
    if ( $file['size'] > $max_size ) {
        return new WP_Error( 'too_large', 'File quá lớn (max 5MB)' );
    }
    
    // Sanitize filename
    $filename = sanitize_file_name( $file['name'] );
    
    // Check extension matches mime
    $ext = pathinfo( $filename, PATHINFO_EXTENSION );
    $valid_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if ( ! in_array( strtolower( $ext ), $valid_exts, true ) ) {
        return new WP_Error( 'invalid_ext', 'Extension không hợp lệ' );
    }
    
    return true;
}
```

---

## 10. SENSITIVE DATA

### 10.1 KHÔNG Hardcode Credentials

```php
// ❌ NGHIÊM CẤM - Credentials trong code
$phpmailer->Password = 'MySecretPassword123';
$api_key = 'sk_live_abc123xyz';

// ✅ ĐÚNG - Dùng constants trong wp-config.php hoặc environment
$phpmailer->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
$api_key = defined('VIE_API_KEY') ? VIE_API_KEY : '';

// ✅ ĐÚNG - Hoặc dùng WordPress options (encrypted)
$api_key = get_option('vie_api_key');
```

### 10.2 Bảo vệ file nhạy cảm

```
# .htaccess trong thư mục credentials/
<FilesMatch "\.(json|php|log)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

### 10.3 Logging an toàn

```php
// ❌ SAI - Log thông tin nhạy cảm
error_log( 'User login: ' . $username . ' / ' . $password );
error_log( 'API Response: ' . json_encode( $response ) ); // Có thể chứa tokens

// ✅ ĐÚNG - Chỉ log thông tin cần thiết
error_log( 'User login attempt: ' . $username );
error_log( 'API Response status: ' . $response['status'] );
```

---

## 11. CHECKLIST BẢO MẬT

### Trước khi commit code:

- [ ] Tất cả input từ user đã được sanitize?
- [ ] Tất cả output đã được escape?
- [ ] Form có nonce field?
- [ ] AJAX handlers có `check_ajax_referer()`?
- [ ] Admin functions có `current_user_can()` check?
- [ ] SQL queries dùng `$wpdb->prepare()`?
- [ ] Không có credentials hardcode?
- [ ] Không log sensitive data?
- [ ] File uploads được validate đúng cách?
- [ ] Dùng hash thay vì ID trong URLs public?

### Security Review Quarterly:

- [ ] Review all AJAX endpoints
- [ ] Check for exposed sensitive files
- [ ] Update dependencies có security patches
- [ ] Review error logs for attack attempts
- [ ] Test with security scanner (WPScan, etc.)
