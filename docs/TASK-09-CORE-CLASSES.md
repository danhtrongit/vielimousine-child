# TASK-09: MIGRATE CORE CLASSES

**Phase:** 4 - Business Logic Migration  
**Thời gian:** 1 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-08 hoàn thành  

---

## 🎯 MỤC TIÊU

Di chuyển các core classes từ legacy sang cấu trúc mới:
- Google Sheets API integration
- Cache Manager
- Logger utilities

---

## 📋 CHECKLIST

### PHẦN 1: Core Classes

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 1.1 | `inc/core/class-google-auth.php` | `inc/classes/class-google-auth.php` | ⬜ |
| 1.2 | `inc/core/class-google-sheets-api.php` | `inc/classes/class-google-sheets-api.php` | ⬜ |
| 1.3 | `inc/core/class-cache-manager.php` | `inc/classes/class-cache-manager.php` | ⬜ |

### PHẦN 2: Utils

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 2.1 | `inc/utils/class-logger.php` | `inc/classes/class-logger.php` | ⬜ |
| 2.2 | `inc/utils/helpers.php` | `inc/helpers/utils.php` | ⬜ |

### PHẦN 3: Config

| # | File Legacy | File Mới | Status |
|---|-------------|----------|--------|
| 3.1 | `inc/config/credentials.php` | `inc/config/credentials.php` | ⬜ |

---

## 📝 HƯỚNG DẪN CHI TIẾT

### Bước 1: Copy và refactor class-google-auth.php

```bash
cp _backup_legacy_v1_291124/inc/core/class-google-auth.php inc/classes/
```

**Cần sửa:**
- Thêm file header comment tiếng Việt
- Cập nhật namespace/paths nếu cần

### Bước 2: Copy và refactor class-google-sheets-api.php

```bash
cp _backup_legacy_v1_291124/inc/core/class-google-sheets-api.php inc/classes/
```

### Bước 3: Copy class-cache-manager.php

```bash
cp _backup_legacy_v1_291124/inc/core/class-cache-manager.php inc/classes/
```

### Bước 4: Copy class-logger.php

```bash
cp _backup_legacy_v1_291124/inc/utils/class-logger.php inc/classes/
```

### Bước 5: Merge utils/helpers.php

Merge nội dung từ `_backup_legacy_v1_291124/inc/utils/helpers.php` vào các helper files hiện có.

### Bước 6: Setup credentials.php

```bash
cp _backup_legacy_v1_291124/inc/config/credentials.php inc/config/
```

⚠️ **QUAN TRỌNG:** File này chứa sensitive data, KHÔNG commit lên git!

---

## ✅ DEFINITION OF DONE

- [ ] Tất cả core classes đã copy
- [ ] File headers đã cập nhật tiếng Việt
- [ ] Paths đã cập nhật cho cấu trúc mới
- [ ] Không có PHP syntax errors
- [ ] Git commit

---

## ⏭️ TASK TIẾP THEO

[TASK-10-HOTEL-ROOMS-ADMIN.md](./TASK-10-HOTEL-ROOMS-ADMIN.md)
