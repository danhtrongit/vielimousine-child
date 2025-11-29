# TASK-01: BACKUP CODE CŨ

**Phase:** 0 - Chuẩn bị  
**Thời gian:** 2-3 giờ  
**Độ ưu tiên:** 🔴 CRITICAL  
**Người thực hiện:** _______________  
**Ngày bắt đầu:** ___/___/2024  
**Ngày hoàn thành:** ___/___/2024

---

## 🎯 MỤC TIÊU

Di chuyển toàn bộ code hiện tại vào thư mục backup để:
1. Bảo toàn code cũ, có thể rollback bất cứ lúc nào
2. Tránh conflict giữa code cũ và mới
3. Có reference khi refactor

---

## 📋 CHECKLIST CHI TIẾT

### BƯỚC 1: Chuẩn bị môi trường

| # | Task | Command/Action | Status |
|---|------|----------------|--------|
| 1.1 | Mở terminal tại thư mục theme | `cd /path/to/vielimousine-child` | ⬜ |
| 1.2 | Kiểm tra đang ở đúng thư mục | `pwd` → phải thấy `/vielimousine-child` | ⬜ |
| 1.3 | Kiểm tra git status | `git status` → phải clean | ⬜ |
| 1.4 | Pull code mới nhất | `git pull origin main` | ⬜ |

**Checkpoint 1:** Terminal đã mở đúng thư mục, git clean ✅

---

### BƯỚC 2: Tạo thư mục backup

| # | Task | Command/Action | Status |
|---|------|----------------|--------|
| 2.1 | Tạo thư mục backup với ngày tháng | `mkdir _backup_legacy_v1_291124` | ⬜ |
| 2.2 | Verify thư mục đã tạo | `ls -la \| grep backup` | ⬜ |

**Lưu ý:** Thay `291124` bằng ngày thực tế (ddmmyy)

---

### BƯỚC 3: Di chuyển files PHP

| # | Task | Command | Status |
|---|------|---------|--------|
| 3.1 | Di chuyển functions.php | `mv functions.php _backup_legacy_v1_291124/` | ⬜ |
| 3.2 | Di chuyển style.css | `mv style.css _backup_legacy_v1_291124/` | ⬜ |
| 3.3 | Di chuyển page-checkout.php | `mv page-checkout.php _backup_legacy_v1_291124/` | ⬜ |
| 3.4 | Di chuyển screenshot.png | `mv screenshot.png _backup_legacy_v1_291124/` | ⬜ |

**Checkpoint 2:** Files ở root đã di chuyển ✅

---

### BƯỚC 4: Di chuyển thư mục

| # | Task | Command | Status |
|---|------|---------|--------|
| 4.1 | Di chuyển thư mục inc | `mv inc/ _backup_legacy_v1_291124/` | ⬜ |
| 4.2 | Di chuyển thư mục credentials | `mv credentials/ _backup_legacy_v1_291124/` | ⬜ |
| 4.3 | Di chuyển thư mục logs | `mv logs/ _backup_legacy_v1_291124/` | ⬜ |

---

### BƯỚC 5: Di chuyển files data

| # | Task | Command | Status |
|---|------|---------|--------|
| 5.1 | Di chuyển file Excel | `mv "BG_ COMBO Y2025_SALES THẤP ĐIỂM 21.10 SALEE.xlsx" _backup_legacy_v1_291124/` | ⬜ |
| 5.2 | Di chuyển các file khác (nếu có) | `mv *.xlsx _backup_legacy_v1_291124/` | ⬜ |

---

### BƯỚC 6: Verify backup

| # | Task | Command | Expected Result | Status |
|---|------|---------|-----------------|--------|
| 6.1 | Liệt kê nội dung backup | `ls -la _backup_legacy_v1_291124/` | Thấy đủ files | ⬜ |
| 6.2 | Kiểm tra thư mục inc | `ls _backup_legacy_v1_291124/inc/` | Thấy config, core, hotel-rooms... | ⬜ |
| 6.3 | Kiểm tra file functions.php | `head -20 _backup_legacy_v1_291124/functions.php` | Thấy code PHP | ⬜ |
| 6.4 | Liệt kê root hiện tại | `ls -la` | Chỉ còn _backup, .git, docs | ⬜ |

---

### BƯỚC 7: Commit backup

| # | Task | Command | Status |
|---|------|---------|--------|
| 7.1 | Add tất cả changes | `git add -A` | ⬜ |
| 7.2 | Kiểm tra staged files | `git status` | ⬜ |
| 7.3 | Commit | `git commit -m "chore: backup legacy code v1 trước khi nâng cấp v2.0"` | ⬜ |
| 7.4 | Push lên remote | `git push origin main` | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] Thư mục `_backup_legacy_v1_[date]` đã tạo
- [ ] Tất cả files/folders cũ đã di chuyển vào backup
- [ ] Thư mục root chỉ còn: `_backup_legacy_v1_*`, `.git`, `docs`
- [ ] Đã commit và push lên git
- [ ] Website vẫn chạy (sẽ lỗi tạm thời - đó là đúng!)

---

## 🚨 ROLLBACK (Nếu cần khôi phục)

```bash
# Di chuyển tất cả từ backup ra ngoài
cp -r _backup_legacy_v1_291124/* ./

# Hoặc dùng git reset
git reset --hard HEAD~1
git push -f origin main
```

---

## 📝 ISSUES & NOTES

### Issues gặp phải:
```
[Ghi lại các vấn đề nếu có]
1. 
2. 
```

### Notes:
```
[Ghi chú thêm]
1. 
2. 
```

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-02-STRUCTURE.md](./TASK-02-STRUCTURE.md)**
