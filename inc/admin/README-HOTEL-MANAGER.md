# Quản lý Khách sạn (Hotel Manager) Role

## Tổng quan

Role **"Quản lý Khách sạn"** (Hotel Manager) được tạo để giới hạn quyền truy cập cho người dùng chỉ quản lý các chức năng liên quan đến khách sạn và Vie Hotel.

## Chức năng

### Quyền truy cập
Người dùng với role này có thể:

1. ✅ **Quản lý Khách sạn (Hotel Post Type)**
   - Thêm, sửa, xóa khách sạn
   - Đăng/Hủy đăng khách sạn
   - Quản lý tất cả taxonomy liên quan:
     - **Địa điểm** (hotel-location)
     - **Danh mục** (hotel-category)
     - **Hạng sao** (hotel-rank)
     - **Tiện ích** (hotel-convenient)

2. ✅ **Vie Hotel Menu**
   - Quản lý Phòng
   - Xem và xử lý Đặt phòng
   - Quản lý Lịch Giá
   - Bulk Update giá
   - Xem Cài đặt (chỉ đọc)

3. ✅ **Media Library**
   - Upload ảnh khách sạn
   - Quản lý thư viện ảnh

4. ✅ **Dashboard**
   - Xem thống kê khách sạn
   - Xem thống kê đặt phòng

5. ✅ **Profile**
   - Chỉnh sửa thông tin cá nhân

### Giới hạn
Người dùng với role này **KHÔNG THỂ**:

- ❌ Quản lý Tours
- ❌ Quản lý Car Rental
- ❌ Quản lý Posts/Pages
- ❌ Truy cập Appearance (Themes, Menus, Widgets)
- ❌ Quản lý Plugins
- ❌ Quản lý Users
- ❌ Truy cập Settings (WordPress core)
- ❌ Truy cập Database Status (chỉ Administrator)

## Cách sử dụng

### 1. Tạo người dùng mới với role Hotel Manager

**Bước 1:** Vào WordPress Admin → Users → Add New

**Bước 2:** Điền thông tin người dùng:
- Username
- Email
- Password

**Bước 3:** Trong dropdown **Role**, chọn "Quản lý Khách sạn"

**Bước 4:** Click "Add New User"

### 2. Chuyển đổi người dùng hiện tại sang Hotel Manager

**Bước 1:** Vào WordPress Admin → Users → All Users

**Bước 2:** Hover vào người dùng cần chuyển đổi, click "Edit"

**Bước 3:** Trong dropdown **Role**, chọn "Quản lý Khách sạn"

**Bước 4:** Click "Update User"

### 3. Đăng nhập với Hotel Manager

Sau khi đăng nhập, người dùng sẽ tự động được redirect đến trang **Quản lý Khách sạn**.

Dashboard sẽ hiển thị:
- Widget chào mừng với hướng dẫn sử dụng
- Widget thống kê khách sạn và đặt phòng

## Menu hiển thị cho Hotel Manager

Sau khi đăng nhập, Hotel Manager chỉ thấy các menu sau:

```
📊 Dashboard
🏨 Khách sạn (Hotel Post Type)
🏢 Vie Hotel
   ├── Phòng
   ├── Đặt phòng
   ├── Lịch Giá
   ├── Bulk Update
   └── Cài đặt
📷 Media
👤 Profile
```

## Admin Bar (Thanh công cụ trên cùng)

Các quick links xuất hiện:
- 🏢 Vie Hotel → Đặt phòng
- 🏢 Vie Hotel → + Thêm phòng
- ➕ Thêm Khách sạn (góc phải)

## Capabilities (Chi tiết kỹ thuật)

### WordPress Core
- `read`: Đọc nội dung
- `edit_dashboard`: Truy cập dashboard

### Hotel Post Type
- `edit_hotels`
- `edit_others_hotels`
- `publish_hotels`
- `read_private_hotels`
- `delete_hotels`
- `delete_private_hotels`
- `delete_published_hotels`
- `delete_others_hotels`
- `edit_private_hotels`
- `edit_published_hotels`

### Taxonomy Capabilities

**General:**
- `manage_categories`

**hotel-location (Địa điểm):**
- `manage_hotel_location`
- `edit_hotel_location`
- `delete_hotel_location`
- `assign_hotel_location`

**hotel-category (Danh mục):**
- `manage_hotel_category`
- `edit_hotel_category`
- `delete_hotel_category`
- `assign_hotel_category`

**hotel-rank (Hạng sao):**
- `manage_hotel_rank`
- `edit_hotel_rank`
- `delete_hotel_rank`
- `assign_hotel_rank`

**hotel-convenient (Tiện ích):**
- `manage_hotel_convenient`
- `edit_hotel_convenient`
- `delete_hotel_convenient`
- `assign_hotel_convenient`

### Media
- `upload_files`
- `edit_files`

### Vie Hotel Menu
- `manage_vie_hotel`
- `view_vie_hotel_bookings`
- `edit_vie_hotel_rooms`
- `edit_vie_hotel_calendar`
- `view_vie_hotel_settings`

### Comments
- `moderate_comments`
- `edit_comment`

## Kích hoạt Role

Role được tự động tạo khi:
1. Theme được activate lần đầu
2. Bạn có thể force tạo lại bằng cách:
   - Deactivate theme → Activate lại
   - Hoặc run: `Vie_Role_Manager::get_instance()->create_hotel_manager_role()`

### Quan trọng: Hotel Post Type Capabilities

Hệ thống tự động map capabilities cho Hotel post type để Hotel Manager có thể chỉnh sửa:
- File `HotelPostType.php` đảm bảo Hotel post type sử dụng custom capabilities
- Khi theme activate, Hotel post type được cấu hình với đúng capabilities
- Administrator role cũng tự động được cấp quyền Hotel post type

## Xóa Role

Role được tự động xóa khi:
- Switch sang theme khác

## Troubleshooting

### Vấn đề: Không thấy menu Vie Hotel
**Giải pháp:**
1. Kiểm tra role đã được tạo chưa: WordPress Admin → Users → Add New → kiểm tra dropdown Role
2. Nếu chưa có, deactivate theme và activate lại
3. Kiểm tra file `/inc/admin/RoleManager.php` đã được load trong `functions.php`

### Vấn đề: Hotel Manager thấy quá nhiều menu
**Giải pháp:**
1. Clear cache trình duyệt
2. Logout và login lại
3. Kiểm tra không có plugin nào override quyền (ví dụ: User Role Editor)

### Vấn đề: Không truy cập được Hotel post type
**Giải pháp:**
1. Kiểm tra Hotel post type có được đăng ký với đúng capabilities không
2. Role cần có `edit_hotels`, `publish_hotels`, etc.

### Vấn đề: Không chỉnh sửa được taxonomy (Địa điểm, Danh mục, v.v.)
**Giải pháp:**
1. Deactivate theme và activate lại để cấu hình lại capabilities
2. Kiểm tra file `HotelPostType.php` đã load trong `functions.php`
3. Clear cache trình duyệt
4. Trong WordPress admin, vào Hotels → Categories/Tags để kiểm tra

## Bảo mật

- ✅ Role này **KHÔNG THỂ** thay đổi code
- ✅ Role này **KHÔNG THỂ** install/delete plugins
- ✅ Role này **KHÔNG THỂ** thay đổi theme
- ✅ Role này **KHÔNG THỂ** quản lý users
- ✅ All actions được log nếu WP_DEBUG = true

## Files liên quan

- `RoleManager.php`: Quản lý role và capabilities
- `HotelPostType.php`: Map capabilities cho Hotel post type
- `admin-menu.php`: Đăng ký menu với capabilities phù hợp
- `README-HOTEL-MANAGER.md`: Tài liệu này

## Changelog

### Version 1.0.0 (2025-12-02)
- ✨ Tạo role "Quản lý Khách sạn"
- ✨ Giới hạn menu admin
- ✨ Tùy chỉnh dashboard widgets
- ✨ Redirect sau login
- ✨ Tùy chỉnh admin bar
- ✨ Cấp quyền Vie Hotel cho Administrator
- ✨ Map capabilities cho Hotel post type
- ✨ Cho phép Hotel Manager chỉnh sửa Hotel posts
- ✨ Map capabilities cho tất cả Hotel taxonomies
- ✨ Cho phép quản lý Địa điểm, Danh mục, Hạng sao, Tiện ích

## Liên hệ hỗ trợ

Nếu có vấn đề hoặc đề xuất, vui lòng liên hệ Vie Development Team.

---

**Phiên bản:** 1.0.0  
**Ngày cập nhật:** 02/12/2025  
**Tác giả:** Vie Development Team
