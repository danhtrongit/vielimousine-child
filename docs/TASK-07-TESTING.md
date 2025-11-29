# TASK-07: TESTING

**Phase:** 3 - Finalize  
**Thời gian:** 1.5 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-06 hoàn thành  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

1. Test toàn bộ chức năng frontend
2. Test toàn bộ chức năng admin
3. Test responsive trên các thiết bị
4. Fix bugs phát hiện được
5. Performance check

---

## 📋 TEST CHECKLIST

### PHẦN 1: FRONTEND TESTS

#### 1.1 Trang Hotel Single

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 1.1.1 | Page load | Mở trang hotel bất kỳ | Load không lỗi, hiển thị rooms | ⬜ | |
| 1.1.2 | Room grid | Scroll xuống room listing | Grid hiển thị đúng columns | ⬜ | |
| 1.1.3 | Room card images | Xem các room cards | Ảnh load, swiper hoạt động | ⬜ | |
| 1.1.4 | Room card info | Đọc thông tin trên card | Tên, giá, meta hiển thị đúng | ⬜ | |
| 1.1.5 | Lazy loading | Scroll nhanh | Ảnh load khi cần | ⬜ | |

#### 1.2 Filters

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 1.2.1 | Datepicker mở | Click vào input ngày | Calendar hiển thị | ⬜ | |
| 1.2.2 | Chọn ngày check-in | Chọn 1 ngày | Input cập nhật | ⬜ | |
| 1.2.3 | Chọn ngày check-out | Chọn ngày sau check-in | Input cập nhật | ⬜ | |
| 1.2.4 | Số người lớn | Thay đổi dropdown | Giá trị thay đổi | ⬜ | |
| 1.2.5 | Số trẻ em | Chọn 2 trẻ em | Hiện input tuổi | ⬜ | |
| 1.2.6 | Filter apply | Click kiểm tra | Rooms filter đúng | ⬜ | |

#### 1.3 Room Detail Modal

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 1.3.1 | Mở modal | Click "Xem chi tiết" | Modal hiển thị | ⬜ | |
| 1.3.2 | Ảnh gallery | Xem ảnh trong modal | Swiper hoạt động | ⬜ | |
| 1.3.3 | Thông tin phòng | Đọc nội dung | Đầy đủ info | ⬜ | |
| 1.3.4 | Đóng modal - X | Click nút X | Modal đóng | ⬜ | |
| 1.3.5 | Đóng modal - Overlay | Click nền mờ | Modal đóng | ⬜ | |
| 1.3.6 | Đóng modal - ESC | Nhấn phím ESC | Modal đóng | ⬜ | |
| 1.3.7 | Nút đặt phòng | Click "Đặt ngay" | Mở booking popup | ⬜ | |

#### 1.4 Booking Popup

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 1.4.1 | Mở popup | Click "Đặt ngay" trên card | Popup hiển thị | ⬜ | |
| 1.4.2 | Step 1 hiển thị | Xem step đầu tiên | Form chọn ngày/người | ⬜ | |
| 1.4.3 | Chọn ngày | Pick dates | Datepicker hoạt động | ⬜ | |
| 1.4.4 | Tính giá auto | Chọn xong ngày | Giá tự động tính | ⬜ | |
| 1.4.5 | Giá breakdown | Xem chi tiết giá | Hiển thị đúng format | ⬜ | |
| 1.4.6 | Phụ thu trẻ em | Thêm trẻ em 8 tuổi | Phụ thu tính đúng | ⬜ | |
| 1.4.7 | Next step | Click "Tiếp tục" | Chuyển step 2 | ⬜ | |
| 1.4.8 | Step 2 - Form | Điền thông tin | Các field hoạt động | ⬜ | |
| 1.4.9 | Validation | Submit form trống | Hiện lỗi validation | ⬜ | |
| 1.4.10 | Back button | Click "Quay lại" | Về step 1 | ⬜ | |
| 1.4.11 | Submit booking | Điền đủ + Submit | Redirect checkout | ⬜ | |

#### 1.5 Checkout Page

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 1.5.1 | Page load | Truy cập từ booking | Load đúng | ⬜ | |
| 1.5.2 | Booking info | Xem sidebar | Đúng thông tin đặt | ⬜ | |
| 1.5.3 | Customer form | Điền form | Các field hoạt động | ⬜ | |
| 1.5.4 | Coupon apply | Nhập mã giảm giá | Áp dụng thành công | ⬜ | |
| 1.5.5 | Payment QR | Xem QR code | QR hiển thị đúng | ⬜ | |
| 1.5.6 | Auto check payment | Đợi 30s | Tự động check | ⬜ | |
| 1.5.7 | Invalid hash | Sửa URL hash | Redirect home | ⬜ | |

#### 1.6 Responsive - Mobile

| # | Test Case | Screen | Expected | Status | Bug ID |
|---|-----------|--------|----------|--------|--------|
| 1.6.1 | Room grid | 375px | 1 column | ⬜ | |
| 1.6.2 | Filters | 375px | Stack vertical | ⬜ | |
| 1.6.3 | Booking popup | 375px | Full screen | ⬜ | |
| 1.6.4 | Checkout | 375px | 1 column layout | ⬜ | |
| 1.6.5 | Touch gestures | Mobile device | Swipe hoạt động | ⬜ | |

#### 1.7 Responsive - Tablet

| # | Test Case | Screen | Expected | Status | Bug ID |
|---|-----------|--------|----------|--------|--------|
| 1.7.1 | Room grid | 768px | 2 columns | ⬜ | |
| 1.7.2 | Popup width | 768px | Max 90% width | ⬜ | |
| 1.7.3 | Checkout | 768px | 2 columns | ⬜ | |

---

### PHẦN 2: ADMIN TESTS

#### 2.1 Menu & Navigation

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 2.1.1 | Menu hiển thị | Vào WP Admin | Menu "Quản lý phòng" có | ⬜ | |
| 2.1.2 | Submenus | Hover menu | Submenus hiển thị | ⬜ | |
| 2.1.3 | Navigation | Click từng submenu | Chuyển trang đúng | ⬜ | |

#### 2.2 Quản lý Phòng

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 2.2.1 | Danh sách phòng | Mở trang | Table hiển thị | ⬜ | |
| 2.2.2 | Thêm phòng mới | Click "Thêm mới" | Form hiển thị | ⬜ | |
| 2.2.3 | Upload ảnh | Chọn ảnh gallery | Media Library mở | ⬜ | |
| 2.2.4 | Lưu phòng | Điền form + Save | Lưu thành công | ⬜ | |
| 2.2.5 | Edit phòng | Click Edit | Form load đúng data | ⬜ | |
| 2.2.6 | Xóa phòng | Click Delete | Confirm + xóa | ⬜ | |

#### 2.3 Lịch Giá

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 2.3.1 | Calendar load | Mở trang lịch giá | Calendar hiển thị | ⬜ | |
| 2.3.2 | Chọn phòng | Dropdown chọn phòng | Data load | ⬜ | |
| 2.3.3 | Set giá 1 ngày | Click ngày + nhập giá | Lưu thành công | ⬜ | |
| 2.3.4 | Bulk update | Chọn range + set giá | Cập nhật hàng loạt | ⬜ | |
| 2.3.5 | Stop sell | Đánh dấu ngày stop | Hiển thị "Ngừng bán" | ⬜ | |

#### 2.4 Quản lý Đặt phòng

| # | Test Case | Steps | Expected | Status | Bug ID |
|---|-----------|-------|----------|--------|--------|
| 2.4.1 | Danh sách booking | Mở trang | Table hiển thị | ⬜ | |
| 2.4.2 | Filter theo status | Chọn "Chờ xác nhận" | Filter đúng | ⬜ | |
| 2.4.3 | Search | Tìm theo SĐT | Kết quả đúng | ⬜ | |
| 2.4.4 | Xem chi tiết | Click booking | Modal/page chi tiết | ⬜ | |
| 2.4.5 | Đổi trạng thái | Chọn status mới | Cập nhật + gửi email | ⬜ | |
| 2.4.6 | Pagination | Click trang 2 | Load trang mới | ⬜ | |

---

### PHẦN 3: INTEGRATION TESTS

#### 3.1 Full Booking Flow

| # | Step | Expected | Status | Bug ID |
|---|------|----------|--------|--------|
| 3.1.1 | Mở trang hotel | Page load OK | ⬜ | |
| 3.1.2 | Chọn phòng + đặt | Popup mở | ⬜ | |
| 3.1.3 | Chọn ngày 2 đêm | Giá tính đúng | ⬜ | |
| 3.1.4 | Thêm 1 trẻ em 8 tuổi | Phụ thu cộng thêm | ⬜ | |
| 3.1.5 | Điền thông tin | Form submit OK | ⬜ | |
| 3.1.6 | Redirect checkout | Checkout page load | ⬜ | |
| 3.1.7 | Nhập coupon | Giảm giá áp dụng | ⬜ | |
| 3.1.8 | Thanh toán QR | QR code đúng số tiền | ⬜ | |
| 3.1.9 | Webhook nhận payment | Status đổi thành "confirmed" | ⬜ | |
| 3.1.10 | Email gửi đi | Khách nhận được email | ⬜ | |
| 3.1.11 | Admin thấy booking | Hiển thị trong danh sách | ⬜ | |

#### 3.2 Error Cases

| # | Test Case | Expected | Status | Bug ID |
|---|-----------|----------|--------|--------|
| 3.2.1 | Đặt ngày đã stop sell | Hiện thông báo lỗi | ⬜ | |
| 3.2.2 | Đặt quá số phòng trống | Hiện thông báo lỗi | ⬜ | |
| 3.2.3 | Coupon hết hạn | "Mã không hợp lệ" | ⬜ | |
| 3.2.4 | Checkout hash sai | Redirect về home | ⬜ | |
| 3.2.5 | AJAX timeout | Hiện retry hoặc lỗi | ⬜ | |

---

### PHẦN 4: PERFORMANCE TESTS

#### 4.1 Page Speed

| # | Page | Target | Actual | Status |
|---|------|--------|--------|--------|
| 4.1.1 | Hotel single (Desktop) | < 3s | ___s | ⬜ |
| 4.1.2 | Hotel single (Mobile) | < 4s | ___s | ⬜ |
| 4.1.3 | Checkout (Desktop) | < 2s | ___s | ⬜ |
| 4.1.4 | Admin Bookings | < 2s | ___s | ⬜ |

#### 4.2 Asset Loading

| # | Check | Expected | Status |
|---|-------|----------|--------|
| 4.2.1 | CSS không 404 | All 200 | ⬜ |
| 4.2.2 | JS không 404 | All 200 | ⬜ |
| 4.2.3 | Images optimized | WebP/compressed | ⬜ |
| 4.2.4 | Không load CSS/JS không cần | Conditional load | ⬜ |

#### 4.3 Console Errors

| # | Page | Errors | Status |
|---|------|--------|--------|
| 4.3.1 | Hotel single | 0 errors | ⬜ |
| 4.3.2 | Checkout | 0 errors | ⬜ |
| 4.3.3 | Admin rooms | 0 errors | ⬜ |
| 4.3.4 | Admin bookings | 0 errors | ⬜ |

---

## 🐛 BUG TRACKING

### Bug Template

```markdown
## BUG-001: [Tiêu đề ngắn]

**Severity:** 🔴 Critical / 🟡 High / 🟢 Medium / ⚪ Low

**Found in:** Test case #___

**Steps to reproduce:**
1. 
2. 
3. 

**Expected:** 

**Actual:** 

**Screenshot:** [Link]

**Assigned to:** ___

**Status:** Open / In Progress / Fixed / Verified

**Fixed in commit:** ___
```

### Bug List

| ID | Title | Severity | Status | Assigned |
|----|-------|----------|--------|----------|
| BUG-001 | | | | |
| BUG-002 | | | | |
| BUG-003 | | | | |

---

## ✅ DEFINITION OF DONE

- [ ] Tất cả test cases PASSED
- [ ] Không có bug Critical hoặc High chưa fix
- [ ] Console không có lỗi
- [ ] Responsive test passed trên 3 breakpoints
- [ ] Full booking flow hoạt động end-to-end
- [ ] Performance targets đạt

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-08-DEPLOY.md](./TASK-08-DEPLOY.md)**
