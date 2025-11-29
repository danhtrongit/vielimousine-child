# 📧 Email Template - Booking Confirmation

## 📋 Tổng quan

Mẫu email xác nhận đặt phòng (Booking Confirmation) được thiết kế theo phong cách hiện đại, chuyên nghiệp với các đặc điểm:

- ✅ **Responsive**: Hiển thị tốt trên mọi thiết bị (Desktop, Tablet, Mobile)
- ✅ **Table-based Layout**: Sử dụng HTML Table để đảm bảo tương thích với mọi email client
- ✅ **Inline CSS**: Tất cả styles đều được inline để tránh bị email client lọc bỏ
- ✅ **Card Layout**: Thiết kế dạng thẻ với nền xám nhạt bao quanh, nội dung chính nằm trong khung trắng bo góc
- ✅ **Brand Colors**: Sử dụng màu chủ đạo #e03d25 (Cam/Đỏ) của Vie Limousine

---

## 📂 Cấu trúc File

```
/inc/hotel-rooms/templates/
├── email-booking-confirmation.php       # Template chính (PHP với biến động)
└── email-booking-confirmation-demo.html # File demo để preview
```

---

## 🎨 Cấu trúc Email

### 1. **Header** (Nhận diện thương hiệu)
- Logo công ty (có thể custom)
- Tiêu đề: "XÁC NHẬN ĐẶT PHÒNG"
- Trạng thái thanh toán (nếu pending)
- Gradient background màu brand

### 2. **Greeting** (Lời chào)
- Xưng hô cá nhân hóa: "Xin chào {customer_name}"
- Câu cảm ơn và dẫn dắt

### 3. **Mã đơn hàng**
- Hiển thị nổi bật với background highlight
- Dễ dàng copy/paste

### 4. **Thông tin khách sạn**
- Tên khách sạn (in đậm, font to)
- Địa chỉ (font nhỏ hơn, màu xám)

### 5. **Chi tiết đặt phòng** (⭐ Phần quan trọng)
Bảng 2 cột hiển thị đầy đủ thông tin:

| Cột 1                  | Cột 2                          |
|------------------------|--------------------------------|
| **Loại phòng**         | **⭐ Gói áp dụng** (Highlight) |
| Loại giường            | Số khách                       |
| ✅ Nhận phòng          | 📤 Trả phòng                   |

**Đặc biệt**: Phần "Gói áp dụng" được làm nổi bật với:
- Background màu #FFF9F5
- Chữ màu đỏ cam (#e03d25)
- Font weight bold
- Icon ⭐

### 6. **Chi tiết thanh toán**
Bảng giá minh bạch:
- Đơn giá × Số đêm
- Tạm tính
- Phụ thu (nếu có) - Màu đỏ
- Giảm giá (nếu có) - Màu xanh lá
- **Tổng cộng** - Font size 28px, màu cam, in đậm

### 7. **CTA Buttons**
- Nút "💳 Thanh toán ngay" (nếu chưa thanh toán)
- Nút "📋 Xem chi tiết"
- Responsive: full width trên mobile

### 8. **Thông tin hỗ trợ**
- Hotline
- Email CSKH
- Box với background #F9F9F9

### 9. **Chính sách hủy phòng**
- Tóm tắt ngắn gọn
- Link đến chính sách chi tiết

### 10. **Footer**
- Tên công ty
- Copyright
- Background tối (#2C2C2C)

---

## 🔧 Hướng dẫn Tích hợp

### Bước 1: Include Template trong Email Manager

```php
// Trong file class-email-manager.php hoặc tương tự

public function send_booking_confirmation($booking_data) {
    // Prepare data
    $customer_name = $booking_data['customer_name'];
    $booking_id = $booking_data['booking_id'];
    $hotel_name = $booking_data['hotel_name'];
    $hotel_address = $booking_data['hotel_address'];
    $room_name = $booking_data['room_name'];
    $package_type = $booking_data['package_type']; // ⭐ Quan trọng
    $bed_type = $booking_data['bed_type'];
    $check_in_date = $booking_data['check_in_date'];
    $check_in_time = $booking_data['check_in_time'];
    $check_out_date = $booking_data['check_out_date'];
    $check_out_time = $booking_data['check_out_time'];
    $adults = $booking_data['adults'];
    $children = $booking_data['children'];
    $nights = $booking_data['nights'];
    $price_per_night = $booking_data['price_per_night'];
    $subtotal = $booking_data['subtotal'];
    $extra_charges = $booking_data['extra_charges'];
    $discount = $booking_data['discount'];
    $total_amount = $booking_data['total_amount'];
    $payment_status = $booking_data['payment_status'];
    $booking_url = $booking_data['booking_url'];
    $payment_url = $booking_data['payment_url'];
    $company_name = 'Vie Limousine';
    $support_hotline = '1900 xxxx';
    $support_email = 'support@vielimousine.com';
    $logo_url = get_stylesheet_directory_uri() . '/assets/images/logo.png';
    
    // Start output buffering
    ob_start();
    
    // Include template
    include get_stylesheet_directory() . '/inc/hotel-rooms/templates/email-booking-confirmation.php';
    
    // Get content
    $email_content = ob_get_clean();
    
    // Email headers
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $company_name . ' <noreply@vielimousine.com>'
    );
    
    // Send email
    wp_mail(
        $booking_data['customer_email'],
        'Xác nhận đặt phòng #' . $booking_id,
        $email_content,
        $headers
    );
}
```

### Bước 2: Format Dữ liệu Ngày Tháng

```php
// Format ngày tháng theo yêu cầu
$check_in_date = date_i18n('l, d/m/Y', strtotime($booking_data['check_in']));
// Output: Thứ 6, 29/11/2025

$check_in_time = date('H:i', strtotime($booking_data['check_in']));
// Output: 14:00
```

### Bước 3: Format Số Tiền

```php
// Số tiền đã được format trong template với number_format()
// VD: 4,800,000 ₫
```

---

## ✅ Checklist Kiểm tra

### Desktop View
- [ ] Logo hiển thị đủ rõ, đẹp?
- [ ] Màu brand (#e03d25) hiển thị chính xác?
- [ ] Phần "Gói áp dụng" có được highlight với background cam nhạt?
- [ ] Bảng 2 cột "Chi tiết đặt phòng" căn chỉnh đều?
- [ ] Bảng giá hiển thị rõ ràng, dễ đọc?
- [ ] Số tiền "Tổng cộng" đủ nổi bật (size 28px)?
- [ ] CTA buttons có shadow, gradient đẹp?
- [ ] Border radius các card là 6-8px?

### Mobile View (< 600px)
- [ ] Font size tối thiểu 14px?
- [ ] Bảng 2 cột chuyển thành 1 cột (stack)?
- [ ] Padding giảm xuống còn 15px?
- [ ] Button full width, dễ bấm?
- [ ] "Gói áp dụng" vẫn nổi bật trên mobile?
- [ ] Số tiền "Tổng cộng" vẫn đủ lớn (24px trên mobile)?
- [ ] Header title responsive (24px)?

### Email Client Compatibility
- [ ] Test trên Gmail (Desktop & Mobile App)?
- [ ] Test trên Outlook (Desktop)?
- [ ] Test trên Apple Mail (iOS)?
- [ ] Inline CSS working properly?
- [ ] Images load correctly?
- [ ] Links clickable?

### Content
- [ ] Tất cả biến đều được map đúng?
- [ ] Ngày tháng format đúng "Thứ X, DD/MM/YYYY"?
- [ ] Số tiền có dấu phân cách phẩy?
- [ ] Tone giọng chuyên nghiệp, ấm áp?
- [ ] Không có lỗi chính tả?
- [ ] CTA rõ ràng, dễ hiểu?

### Functional
- [ ] Link "Thanh toán ngay" hoạt động?
- [ ] Link "Xem chi tiết" hoạt động?
- [ ] Hotline link `tel:` clickable?
- [ ] Email link `mailto:` clickable?
- [ ] Logo có alt text?

---

## 🎯 Điểm Đặc biệt - Package Type Highlight

Theo yêu cầu, phần **"Gói áp dụng"** (Package Type) được thiết kế nổi bật nhất:

```html
<td style="background-color: #FFF9F5;">
    <p style="color: #888888; font-size: 13px;">
        ⭐ Gói áp dụng
    </p>
    <p style="color: #e03d25; font-size: 16px; font-weight: 700;">
        {package_type}
    </p>
</td>
```

**Ví dụ giá trị**:
- "Gói Combo 3N2Đ - Bao gồm Spa & Ăn sáng"
- "Gói Honeymoon đặc biệt"
- "Đặt phòng lẻ"

---

## 📱 Preview

### Cách xem Demo:

1. **Mở file demo trong trình duyệt**:
   ```
   /inc/hotel-rooms/templates/email-booking-confirmation-demo.html
   ```

2. **Test responsive**:
   - Mở Chrome DevTools (F12)
   - Toggle Device Toolbar (Ctrl+Shift+M)
   - Chọn các device: iPhone, iPad, Desktop

3. **Test trên email client thực**:
   - Gửi test email đến Gmail, Outlook
   - Kiểm tra trên mobile app

---

## 🚀 Tối ưu Performance

- **Images**: Nén logo xuống < 50KB
- **Inline CSS**: Đã tối ưu, không cần external CSS
- **Table Layout**: Load nhanh, tương thích cao
- **Font**: Sử dụng system fonts (Segoe UI, Tahoma)

---

## 📝 Notes

1. **Không sử dụng**:
   - ❌ External CSS files
   - ❌ JavaScript
   - ❌ CSS Grid/Flexbox (chỉ dùng Table)
   - ❌ Background images (chỉ solid colors/gradients)

2. **Best Practices**:
   - ✅ Inline CSS cho tất cả styles
   - ✅ Table-based layout
   - ✅ Max width 600px
   - ✅ Font size >= 14px
   - ✅ Touch-friendly buttons (min 44px height)

3. **Accessibility**:
   - Alt text cho images
   - Semantic HTML
   - High contrast colors
   - Clear CTA labels

---

## 🆘 Support

Nếu cần tùy chỉnh thêm, vui lòng liên hệ team dev hoặc tham khảo:
- Email Template Best Practices
- HTML Email Guidelines
- Responsive Email Design

---

**Created by**: AI Assistant  
**Last Updated**: 2025-11-28  
**Version**: 1.0
