# TASK-15: FINAL TESTING & CLEANUP

**Phase:** 5 - Finalize  
**Thời gian:** 1 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-14 hoàn thành  

---

## 🎯 MỤC TIÊU

Test toàn bộ hệ thống sau khi migration hoàn tất:
- Test tất cả chức năng frontend
- Test tất cả chức năng admin
- Cleanup code không cần thiết
- Performance check

---

## 📋 FULL TESTING CHECKLIST

### PHẦN 1: FRONTEND TESTS

#### 1.1 Room Listing

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 1.1.1 | Shortcode [hotel_room_list] render | ⬜ | |
| 1.1.2 | Room cards hiển thị đúng | ⬜ | |
| 1.1.3 | Room images load (Swiper) | ⬜ | |
| 1.1.4 | Filters hoạt động | ⬜ | |
| 1.1.5 | Datepicker mở được | ⬜ | |
| 1.1.6 | Giá hiển thị trong datepicker | ⬜ | |

#### 1.2 Booking Popup

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 1.2.1 | Popup mở khi click "Đặt ngay" | ⬜ | |
| 1.2.2 | Step 1: Chọn ngày hoạt động | ⬜ | |
| 1.2.3 | Tính giá tự động | ⬜ | |
| 1.2.4 | Hiển thị breakdown giá đúng | ⬜ | |
| 1.2.5 | Phụ thu trẻ em tính đúng | ⬜ | |
| 1.2.6 | Step 2: Form thông tin | ⬜ | |
| 1.2.7 | Validation hoạt động | ⬜ | |
| 1.2.8 | Submit booking thành công | ⬜ | |
| 1.2.9 | Redirect sang checkout | ⬜ | |

#### 1.3 Checkout Page

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 1.3.1 | Page load với booking hash | ⬜ | |
| 1.3.2 | Thông tin booking hiển thị | ⬜ | |
| 1.3.3 | Coupon input hoạt động | ⬜ | |
| 1.3.4 | Áp dụng mã giảm giá | ⬜ | |
| 1.3.5 | QR code payment hiển thị | ⬜ | |
| 1.3.6 | Auto check payment status | ⬜ | |

### PHẦN 2: ADMIN TESTS

#### 2.1 Menu & Navigation

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 2.1.1 | Menu "Quản lý phòng" hiển thị | ⬜ | |
| 2.1.2 | Tất cả submenus hoạt động | ⬜ | |

#### 2.2 Room Management

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 2.2.1 | Danh sách phòng load | ⬜ | |
| 2.2.2 | Thêm phòng mới | ⬜ | |
| 2.2.3 | Sửa phòng | ⬜ | |
| 2.2.4 | Xóa phòng | ⬜ | |
| 2.2.5 | Upload ảnh gallery | ⬜ | |

#### 2.3 Calendar & Pricing

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 2.3.1 | Calendar view load | ⬜ | |
| 2.3.2 | Set giá theo ngày | ⬜ | |
| 2.3.3 | Bulk update giá | ⬜ | |
| 2.3.4 | Stop sell hoạt động | ⬜ | |

#### 2.4 Booking Management

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 2.4.1 | Danh sách bookings load | ⬜ | |
| 2.4.2 | Filter theo status | ⬜ | |
| 2.4.3 | Search hoạt động | ⬜ | |
| 2.4.4 | Chi tiết booking | ⬜ | |
| 2.4.5 | Đổi trạng thái booking | ⬜ | |

### PHẦN 3: INTEGRATION TESTS

| # | Test Case | Status | Notes |
|---|-----------|--------|-------|
| 3.1 | Full booking flow end-to-end | ⬜ | |
| 3.2 | Email gửi thành công | ⬜ | |
| 3.3 | Google Sheets sync | ⬜ | |
| 3.4 | SePay webhook hoạt động | ⬜ | |

### PHẦN 4: PERFORMANCE

| # | Check | Target | Actual | Status |
|---|-------|--------|--------|--------|
| 4.1 | Page load time | < 3s | | ⬜ |
| 4.2 | No console errors | 0 | | ⬜ |
| 4.3 | Assets minified | Yes | | ⬜ |

---

## 🧹 CLEANUP TASKS

| # | Task | Status |
|---|------|--------|
| 1 | Xóa console.log trong JS production | ⬜ |
| 2 | Xóa commented code không cần | ⬜ |
| 3 | Verify .htaccess bảo mật | ⬜ |
| 4 | Cập nhật version trong style.css | ⬜ |
| 5 | Cập nhật CHANGELOG.md | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] Tất cả test cases PASSED
- [ ] Không có console errors
- [ ] Không có PHP errors
- [ ] Cleanup hoàn tất
- [ ] CHANGELOG cập nhật
- [ ] Git commit cuối cùng

---

## 🎉 MIGRATION HOÀN TẤT!

Sau khi task này hoàn thành, migration v2.0 chính thức hoàn tất.
