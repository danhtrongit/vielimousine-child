# TASK-08: DEPLOY PRODUCTION

**Phase:** 3 - Finalize  
**Thời gian:** 0.5 ngày  
**Độ ưu tiên:** 🔴 CRITICAL  
**Prerequisite:** TASK-07 hoàn thành, tất cả tests PASSED  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

1. Deploy code lên staging để final check
2. Deploy lên production
3. Verify sau deploy
4. Cleanup và documentation

---

## ⚠️ PRE-DEPLOY CHECKLIST

### Bắt buộc hoàn thành trước khi deploy:

| # | Item | Status |
|---|------|--------|
| 1 | TASK-07 Testing hoàn thành | ⬜ |
| 2 | Không có bug Critical/High open | ⬜ |
| 3 | Code đã push lên git | ⬜ |
| 4 | Backup database production | ⬜ |
| 5 | Thông báo team về maintenance | ⬜ |
| 6 | Có access SSH/FTP production | ⬜ |
| 7 | Biết cách rollback nếu cần | ⬜ |

---

## 📋 PHASE 1: STAGING DEPLOY

### BƯỚC 1: Chuẩn bị Staging

| # | Task | Command/Action | Status |
|---|------|----------------|--------|
| 1.1 | SSH vào staging server | `ssh user@staging.vielimousine.com` | ⬜ |
| 1.2 | Backup staging theme | `cp -r themes/vielimousine-child themes/vielimousine-child-backup` | ⬜ |
| 1.3 | Pull code mới | `cd themes/vielimousine-child && git pull origin main` | ⬜ |
| 1.4 | Clear cache | Xóa cache trong WP Admin | ⬜ |

### BƯỚC 2: Verify Staging

| # | Test | Expected | Status |
|---|------|----------|--------|
| 2.1 | Homepage load | Không lỗi | ⬜ |
| 2.2 | Hotel page load | Room listing hiển thị | ⬜ |
| 2.3 | Booking popup | Tính giá đúng | ⬜ |
| 2.4 | Checkout page | Form hoạt động | ⬜ |
| 2.5 | Admin pages | Menus hiển thị | ⬜ |
| 2.6 | Console errors | 0 errors | ⬜ |

### BƯỚC 3: Sign-off Staging

| Người review | Chữ ký | Ngày |
|--------------|--------|------|
| Developer | ___ | ___/___/___ |
| QA (nếu có) | ___ | ___/___/___ |
| Product Owner | ___ | ___/___/___ |

---

## 📋 PHASE 2: PRODUCTION DEPLOY

### BƯỚC 4: Backup Production

| # | Task | Command | Status |
|---|------|---------|--------|
| 4.1 | SSH vào production | `ssh user@vielimousine.com` | ⬜ |
| 4.2 | Backup theme | `cp -r themes/vielimousine-child themes/vielimousine-child-pre-v2` | ⬜ |
| 4.3 | Backup database | `wp db export backup-pre-v2-$(date +%Y%m%d).sql` | ⬜ |
| 4.4 | Download backup về local | `scp user@server:backup.sql ./` | ⬜ |

**Checkpoint:** Backup đã tạo và verify được ✅

### BƯỚC 5: Deploy Code

| # | Task | Command | Status |
|---|------|---------|--------|
| 5.1 | Pull code | `cd themes/vielimousine-child && git pull origin main` | ⬜ |
| 5.2 | Check file permissions | `find . -type f -exec chmod 644 {} \;` | ⬜ |
| 5.3 | Check folder permissions | `find . -type d -exec chmod 755 {} \;` | ⬜ |
| 5.4 | Verify .htaccess | Check logs/, credentials/ protected | ⬜ |

### BƯỚC 6: Post-Deploy Tasks

| # | Task | Action | Status |
|---|------|--------|--------|
| 6.1 | Clear all caches | WP Admin → Clear cache | ⬜ |
| 6.2 | Clear CDN cache | Cloudflare/CDN purge | ⬜ |
| 6.3 | Clear OPcache | PHP OPcache reset | ⬜ |
| 6.4 | Regenerate assets | Nếu dùng build tool | ⬜ |

---

## 📋 PHASE 3: PRODUCTION VERIFICATION

### BƯỚC 7: Smoke Tests

| # | Test | URL | Status |
|---|------|-----|--------|
| 7.1 | Homepage | vielimousine.com | ⬜ |
| 7.2 | Hotel page | vielimousine.com/hotel/[slug] | ⬜ |
| 7.3 | Booking popup | Click "Đặt ngay" | ⬜ |
| 7.4 | Checkout | Complete 1 test booking | ⬜ |
| 7.5 | Admin | /wp-admin/ | ⬜ |
| 7.6 | Admin Rooms | /wp-admin/admin.php?page=vie-hotel-rooms | ⬜ |

### BƯỚC 8: Monitor

| # | Task | Tool | Status |
|---|------|------|--------|
| 8.1 | Check error logs | `tail -f /var/log/apache2/error.log` | ⬜ |
| 8.2 | Check PHP errors | WP Debug log | ⬜ |
| 8.3 | Monitor uptime | UptimeRobot/Pingdom | ⬜ |
| 8.4 | Check analytics | Real-time visitors | ⬜ |

**Theo dõi trong 30 phút sau deploy:**
- [ ] Không có spike error logs
- [ ] Response time bình thường
- [ ] Không có user complaints

---

## 🚨 ROLLBACK PROCEDURE

### Nếu cần rollback:

```bash
# 1. SSH vào server
ssh user@vielimousine.com

# 2. Đổi tên theme hiện tại
cd wp-content/themes
mv vielimousine-child vielimousine-child-v2-failed

# 3. Restore từ backup
mv vielimousine-child-pre-v2 vielimousine-child

# 4. (Nếu cần) Restore database
wp db import backup-pre-v2-YYYYMMDD.sql

# 5. Clear caches
wp cache flush

# 6. Verify site hoạt động
curl -I https://vielimousine.com
```

### Rollback Decision Matrix

| Tình huống | Action |
|------------|--------|
| White screen of death | Rollback ngay |
| Lỗi 500 trên pages chính | Rollback ngay |
| Lỗi nhỏ UI | Hotfix, không cần rollback |
| Lỗi 1 tính năng phụ | Disable tính năng, fix sau |
| Performance chậm > 50% | Investigate, rollback nếu không fix được trong 1h |

---

## 📋 PHASE 4: POST-DEPLOY CLEANUP

### BƯỚC 9: Cleanup

| # | Task | Action | Status |
|---|------|--------|--------|
| 9.1 | Xóa backup cũ trên server | Giữ lại 2 versions gần nhất | ⬜ |
| 9.2 | Update version trong style.css | Nếu chưa update | ⬜ |
| 9.3 | Close JIRA/Trello tickets | Mark as Done | ⬜ |
| 9.4 | Thông báo team | "Deploy thành công" | ⬜ |

### BƯỚC 10: Documentation

| # | Task | Status |
|---|------|--------|
| 10.1 | Cập nhật CHANGELOG.md | ⬜ |
| 10.2 | Ghi chú deploy log | ⬜ |
| 10.3 | Update technical docs nếu cần | ⬜ |

**CHANGELOG Entry:**
```markdown
## [2.0.0] - YYYY-MM-DD

### Added
- Cấu trúc theme mới theo chuẩn MVC
- CSS Variables với Single Source of Truth
- JavaScript modules pattern
- Comment tiếng Việt đầy đủ

### Changed
- Tách CSS thành modules nhỏ
- Tách JS thành modules
- Refactor PHP classes
- Templates tách riêng vào template-parts/

### Fixed
- [Liệt kê bugs đã fix]

### Security
- Di chuyển SMTP credentials ra khỏi code
- IDOR fix với booking hash
```

---

## ✅ DEFINITION OF DONE

- [ ] Code đã deploy lên production
- [ ] Smoke tests passed
- [ ] Không có lỗi trong error logs
- [ ] Monitoring OK trong 30 phút
- [ ] Team đã được thông báo
- [ ] Documentation updated
- [ ] CHANGELOG updated

---

## 🎉 MIGRATION COMPLETE

### Tổng kết:

| Metric | Value |
|--------|-------|
| Tổng thời gian | ___ ngày |
| Số files mới | ___ files |
| Số bugs fixed | ___ bugs |
| Tests passed | ___/___  |

### Lessons Learned:
```
[Ghi lại những điều học được trong quá trình migration]
1. 
2. 
3. 
```

### Recommendations cho tương lai:
```
[Đề xuất cải tiến]
1. 
2. 
3. 
```

---

## 📞 SUPPORT CONTACTS

| Vai trò | Tên | Contact | Khi nào liên hệ |
|---------|-----|---------|-----------------|
| Technical Lead | ___ | ___ | Lỗi critical |
| DevOps | ___ | ___ | Server issues |
| Product Owner | ___ | ___ | Business decisions |

---

**🏆 Chúc mừng! Migration v2.0 hoàn thành!**
