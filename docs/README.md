# TÀI LIỆU DỰ ÁN VIELIMOUSINE CHILD THEME V2.0

---

## 📋 MỤC LỤC TÀI LIỆU

| File | Mô tả | Bắt buộc đọc |
|------|-------|--------------|
| [TECHNICAL-DESIGN-DOCUMENT-V2.md](../TECHNICAL-DESIGN-DOCUMENT-V2.md) | Tài liệu thiết kế kỹ thuật tổng quan | ✅ TẤT CẢ |
| [rules/RULE-01-FILE-HEADER.md](./rules/RULE-01-FILE-HEADER.md) | Quy chuẩn Header file | ✅ TẤT CẢ |
| [rules/RULE-02-CLASS-DOCS.md](./rules/RULE-02-CLASS-DOCS.md) | Quy chuẩn Document Class/Function | ✅ TẤT CẢ |
| [rules/RULE-03-CSS-STRUCTURE.md](./rules/RULE-03-CSS-STRUCTURE.md) | Quy chuẩn CSS | Frontend Dev |
| [rules/RULE-04-JS-MODULES.md](./rules/RULE-04-JS-MODULES.md) | Quy chuẩn JavaScript | Frontend Dev |
| [rules/RULE-05-NAMING-CONVENTION.md](./rules/RULE-05-NAMING-CONVENTION.md) | Quy chuẩn đặt tên | ✅ TẤT CẢ |
| [rules/RULE-06-SECURITY.md](./rules/RULE-06-SECURITY.md) | Quy chuẩn bảo mật | ✅ TẤT CẢ |

---

## 🚀 QUICK START CHO DEVELOPER MỚI

### Bước 1: Đọc tài liệu (theo thứ tự)

1. **TECHNICAL-DESIGN-DOCUMENT-V2.md** - Hiểu tổng quan dự án
2. **RULE-05-NAMING-CONVENTION.md** - Nắm quy tắc đặt tên
3. **RULE-06-SECURITY.md** - Hiểu các nguyên tắc bảo mật
4. Các rules còn lại tùy theo vai trò

### Bước 2: Setup môi trường

```bash
# Clone project
git clone [repo-url]

# Cấu trúc thư mục sau khi clone
/vielimousine-child/
├── _backup_legacy_v1_291124/   # Code cũ (đừng sửa)
├── assets/                      # CSS/JS/Images
├── inc/                         # PHP Logic
├── template-parts/              # Templates
├── docs/                        # Tài liệu (bạn đang ở đây)
├── functions.php
└── style.css
```

### Bước 3: Coding Standards

```php
<?php
/**
 * ============================================================================
 * TÊN FILE: ten-file.php
 * ============================================================================
 * MÔ TẢ: [Mô tả file]
 */

// ✅ Luôn có comment tiếng Việt
// ✅ Luôn sanitize input
// ✅ Luôn escape output
// ✅ Luôn verify nonce
```

---

## 📁 CẤU TRÚC THƯ MỤC V2.0

```
/vielimousine-child/
│
├── assets/                       # Static files
│   ├── css/
│   │   ├── _variables.css        # ★ CSS Variables - Single Source
│   │   ├── admin/                # Admin CSS
│   │   └── frontend/             # Frontend CSS
│   ├── js/
│   │   ├── admin/                # Admin JS modules
│   │   └── frontend/             # Frontend JS modules
│   └── images/
│
├── inc/                          # PHP Logic
│   ├── classes/                  # Business Logic
│   ├── helpers/                  # Utility functions
│   ├── hooks/                    # WP Hooks (assets, ajax, shortcodes)
│   ├── admin/                    # Admin Controllers
│   ├── frontend/                 # Frontend Controllers
│   └── config/                   # Configuration
│
├── template-parts/               # View Templates
│   ├── admin/
│   ├── frontend/
│   └── email/
│
├── docs/                         # Documentation
│   ├── rules/                    # Coding Standards
│   └── README.md                 # This file
│
├── languages/                    # Translation
├── logs/                         # Log files (protected)
├── credentials/                  # Sensitive files (protected)
│
├── functions.php                 # Bootstrap
└── style.css                     # Theme meta
```

---

## 🔧 MODULES CHÍNH

### 1. Hotel Room Management

| Component | File | Chức năng |
|-----------|------|-----------|
| Room Manager | `inc/classes/class-room-manager.php` | CRUD phòng |
| Pricing Engine | `inc/classes/class-pricing-engine.php` | Tính giá theo ngày |
| Booking Manager | `inc/classes/class-booking-manager.php` | Quản lý đặt phòng |

### 2. Payment Integration

| Component | File | Chức năng |
|-----------|------|-----------|
| SePay Gateway | `inc/classes/class-sepay-gateway.php` | Thanh toán QR |
| Email Manager | `inc/classes/class-email-manager.php` | Gửi email xác nhận |

### 3. External APIs

| Component | File | Chức năng |
|-----------|------|-----------|
| Google Sheets | `inc/classes/class-google-sheets-api.php` | Đồng bộ mã giảm giá |

---

## 🔒 BẢO MẬT - QUAN TRỌNG

### KHÔNG BAO GIỜ:

- ❌ Hardcode mật khẩu, API keys trong code
- ❌ Echo trực tiếp user input
- ❌ Dùng SQL query không có prepare()
- ❌ Skip nonce verification
- ❌ Commit file credentials lên git

### LUÔN LUÔN:

- ✅ Sanitize tất cả input
- ✅ Escape tất cả output
- ✅ Verify nonce cho form/AJAX
- ✅ Check capability cho admin functions
- ✅ Dùng hash thay vì ID trong public URLs

---

## 🤝 QUY TRÌNH LÀM VIỆC

### Git Workflow

```bash
# 1. Tạo branch mới
git checkout -b feature/ten-tinh-nang

# 2. Code + commit thường xuyên
git add .
git commit -m "feat: mô tả ngắn gọn"

# 3. Push và tạo PR
git push origin feature/ten-tinh-nang

# 4. Code review -> Merge
```

### Commit Message Format

```
<type>: <description>

Types:
- feat:     Tính năng mới
- fix:      Sửa bug
- docs:     Cập nhật tài liệu
- style:    Format code (không thay đổi logic)
- refactor: Refactor code
- test:     Thêm test
- chore:    Maintenance

Ví dụ:
feat: thêm chức năng tính giá combo
fix: sửa lỗi hiển thị giá sai trên mobile
docs: cập nhật hướng dẫn cài đặt
```

---

## ❓ CÂU HỎI THƯỜNG GẶP

### Q: Sao không dùng React/Vue?

**A:** Theme này cần tương thích với WordPress ecosystem và dễ maintain bởi team không chuyên frontend. jQuery + vanilla JS đủ đáp ứng yêu cầu và dễ debug hơn.

### Q: Sao comment phải tiếng Việt?

**A:** Team bảo trì chủ yếu là người Việt. Comment tiếng Việt giúp hiểu code nhanh hơn, đặc biệt với business logic phức tạp (tính giá, phụ thu, v.v.).

### Q: File CSS/JS sao nhiều thế?

**A:** Tách nhỏ để:
1. Load đúng file cho đúng trang (performance)
2. Dễ maintain từng component
3. Dễ debug khi có lỗi

### Q: Legacy code trong _backup_legacy_v1 còn dùng được không?

**A:** Dùng để tham khảo logic. KHÔNG import trực tiếp. Copy logic cần thiết và refactor theo quy chuẩn mới.

---

## 📞 LIÊN HỆ

- **Technical Lead:** [Tên]
- **Email:** dev@vielimousine.com
- **Slack:** #vielimousine-dev

---

**Cập nhật lần cuối:** 29/11/2024  
**Version:** 2.0.0
