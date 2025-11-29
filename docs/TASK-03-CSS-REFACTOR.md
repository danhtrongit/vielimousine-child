# TASK-03: REFACTOR CSS

**Phase:** 1 - Assets  
**Thời gian:** 2 ngày  
**Độ ưu tiên:** 🟡 HIGH  
**Prerequisite:** TASK-02 hoàn thành  
**Người thực hiện:** _______________

---

## 🎯 MỤC TIÊU

1. Tạo file `_variables.css` - Single Source of Truth
2. Tách `frontend.css` (33KB) thành các modules nhỏ
3. Tổ chức lại CSS admin
4. Áp dụng BEM naming convention

---

## 📋 NGÀY 1: CSS VARIABLES & FRONTEND

### BƯỚC 1: Tạo file _variables.css

| # | Task | Status |
|---|------|--------|
| 1.1 | Tạo file `assets/css/_variables.css` | ⬜ |
| 1.2 | Copy CSS variables từ legacy `frontend.css` | ⬜ |
| 1.3 | Mở rộng thêm variables mới | ⬜ |

**File: `assets/css/_variables.css`**
```css
/**
 * ============================================================================
 * FILE: _variables.css
 * ============================================================================
 * 
 * Single Source of Truth cho tất cả biến CSS trong theme.
 * IMPORT file này ĐẦU TIÊN trong mọi file CSS khác.
 * 
 * MỤC LỤC:
 * 1. Colors
 * 2. Typography
 * 3. Spacing
 * 4. Borders & Shadows
 * 5. Transitions
 * 6. Z-index Scale
 * 7. Breakpoints (comment only)
 * ============================================================================
 */

:root {
    /* =====================================================================
       1. COLORS
       ===================================================================== */
    
    /* Brand Primary - Màu chủ đạo */
    --vie-primary: #2563eb;
    --vie-primary-light: #3b82f6;
    --vie-primary-dark: #1d4ed8;
    --vie-primary-50: #eff6ff;
    --vie-primary-100: #dbeafe;
    
    /* Brand Secondary */
    --vie-secondary: #64748b;
    --vie-secondary-light: #94a3b8;
    --vie-secondary-dark: #475569;
    
    /* Semantic Colors - Màu theo ý nghĩa */
    --vie-success: #10b981;
    --vie-success-light: #34d399;
    --vie-success-bg: #ecfdf5;
    
    --vie-danger: #ef4444;
    --vie-danger-light: #f87171;
    --vie-danger-bg: #fef2f2;
    
    --vie-warning: #f59e0b;
    --vie-warning-light: #fbbf24;
    --vie-warning-bg: #fffbeb;
    
    --vie-info: #0ea5e9;
    --vie-info-light: #38bdf8;
    --vie-info-bg: #f0f9ff;
    
    /* Neutral Colors - Thang xám */
    --vie-white: #ffffff;
    --vie-black: #000000;
    --vie-gray-50: #f8fafc;
    --vie-gray-100: #f1f5f9;
    --vie-gray-200: #e2e8f0;
    --vie-gray-300: #cbd5e1;
    --vie-gray-400: #94a3b8;
    --vie-gray-500: #64748b;
    --vie-gray-600: #475569;
    --vie-gray-700: #334155;
    --vie-gray-800: #1e293b;
    --vie-gray-900: #0f172a;
    
    /* Semantic Mappings - Ánh xạ theo mục đích sử dụng */
    --vie-text: var(--vie-gray-800);
    --vie-text-muted: var(--vie-gray-500);
    --vie-text-light: var(--vie-gray-400);
    
    --vie-bg: var(--vie-white);
    --vie-bg-light: var(--vie-gray-50);
    --vie-bg-dark: var(--vie-gray-100);
    
    --vie-border: var(--vie-gray-200);
    --vie-border-light: var(--vie-gray-100);
    --vie-border-dark: var(--vie-gray-300);
    
    /* =====================================================================
       2. TYPOGRAPHY
       ===================================================================== */
    
    /* Font Family */
    --vie-font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --vie-font-mono: 'JetBrains Mono', 'Fira Code', Consolas, monospace;
    
    /* Font Sizes */
    --vie-text-xs: 0.75rem;      /* 12px */
    --vie-text-sm: 0.875rem;     /* 14px */
    --vie-text-base: 1rem;       /* 16px */
    --vie-text-lg: 1.125rem;     /* 18px */
    --vie-text-xl: 1.25rem;      /* 20px */
    --vie-text-2xl: 1.5rem;      /* 24px */
    --vie-text-3xl: 1.875rem;    /* 30px */
    --vie-text-4xl: 2.25rem;     /* 36px */
    
    /* Font Weights */
    --vie-font-normal: 400;
    --vie-font-medium: 500;
    --vie-font-semibold: 600;
    --vie-font-bold: 700;
    
    /* Line Heights */
    --vie-leading-none: 1;
    --vie-leading-tight: 1.25;
    --vie-leading-snug: 1.375;
    --vie-leading-normal: 1.5;
    --vie-leading-relaxed: 1.625;
    
    /* =====================================================================
       3. SPACING
       ===================================================================== */
    
    --vie-space-0: 0;
    --vie-space-1: 0.25rem;      /* 4px */
    --vie-space-2: 0.5rem;       /* 8px */
    --vie-space-3: 0.75rem;      /* 12px */
    --vie-space-4: 1rem;         /* 16px */
    --vie-space-5: 1.25rem;      /* 20px */
    --vie-space-6: 1.5rem;       /* 24px */
    --vie-space-8: 2rem;         /* 32px */
    --vie-space-10: 2.5rem;      /* 40px */
    --vie-space-12: 3rem;        /* 48px */
    --vie-space-16: 4rem;        /* 64px */
    --vie-space-20: 5rem;        /* 80px */
    
    /* =====================================================================
       4. BORDERS & SHADOWS
       ===================================================================== */
    
    /* Border Radius */
    --vie-radius-none: 0;
    --vie-radius-sm: 4px;
    --vie-radius: 8px;
    --vie-radius-md: 12px;
    --vie-radius-lg: 16px;
    --vie-radius-xl: 24px;
    --vie-radius-full: 9999px;
    
    /* Box Shadows */
    --vie-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --vie-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    --vie-shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    --vie-shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    --vie-shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    
    /* Focus Ring */
    --vie-ring: 0 0 0 3px rgba(37, 99, 235, 0.2);
    --vie-ring-danger: 0 0 0 3px rgba(239, 68, 68, 0.2);
    
    /* =====================================================================
       5. TRANSITIONS
       ===================================================================== */
    
    --vie-transition-fast: 150ms ease;
    --vie-transition: 200ms ease;
    --vie-transition-slow: 300ms ease;
    --vie-transition-slower: 500ms ease;
    
    /* =====================================================================
       6. Z-INDEX SCALE
       ===================================================================== */
    
    --vie-z-below: -1;
    --vie-z-base: 0;
    --vie-z-dropdown: 100;
    --vie-z-sticky: 200;
    --vie-z-fixed: 300;
    --vie-z-modal-backdrop: 400;
    --vie-z-modal: 500;
    --vie-z-popover: 600;
    --vie-z-tooltip: 700;
    --vie-z-toast: 800;
    
    /* =====================================================================
       7. BREAKPOINTS (Reference - dùng trong @media)
       =====================================================================
       
       Mobile:        < 640px
       Tablet:        640px - 1023px     @media (min-width: 640px)
       Desktop:       1024px - 1279px    @media (min-width: 1024px)
       Large Desktop: >= 1280px          @media (min-width: 1280px)
       
       ===================================================================== */
}
```

---

### BƯỚC 2: Phân tích file legacy frontend.css

| # | Task | Status |
|---|------|--------|
| 2.1 | Mở file `_backup_legacy_v1_*/inc/hotel-rooms/assets/css/frontend.css` | ⬜ |
| 2.2 | Đọc và note lại các sections | ⬜ |
| 2.3 | Lập kế hoạch tách file | ⬜ |

**Kế hoạch tách file:**

| Section trong Legacy | File mới | Dòng ước tính |
|---------------------|----------|---------------|
| Booking Filters | `room-listing.css` | ~100 |
| Room Grid/Cards | `room-listing.css` | ~200 |
| Buttons | `main.css` | ~50 |
| Room Detail Modal | `room-detail-modal.css` | ~150 |
| Booking Popup | `booking-popup.css` | ~300 |
| Datepicker Custom | `datepicker.css` | ~150 |
| Price Display | `booking-popup.css` | ~100 |
| Form Elements | `main.css` | ~100 |
| Responsive | Mỗi file tự có | ~200 |

---

### BƯỚC 3: Tạo file main.css

| # | Task | Status |
|---|------|--------|
| 3.1 | Tạo file `assets/css/frontend/main.css` | ⬜ |
| 3.2 | Copy base styles, buttons, forms từ legacy | ⬜ |
| 3.3 | Cập nhật để dùng CSS variables | ⬜ |

**Template file: `assets/css/frontend/main.css`**
```css
/**
 * ============================================================================
 * FILE: main.css
 * ============================================================================
 * 
 * Base styles cho frontend: typography, buttons, forms, utilities
 * 
 * MỤC LỤC:
 * 1. Base Reset
 * 2. Typography
 * 3. Buttons
 * 4. Form Elements
 * 5. Utilities
 * 6. Responsive
 * ============================================================================
 */

/* ==========================================================================
   1. BASE RESET
   ========================================================================== */

.vie-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--vie-space-4);
}

/* ==========================================================================
   2. TYPOGRAPHY
   ========================================================================== */

.vie-heading {
    font-weight: var(--vie-font-bold);
    color: var(--vie-text);
    line-height: var(--vie-leading-tight);
}

.vie-heading--xl {
    font-size: var(--vie-text-3xl);
}

.vie-heading--lg {
    font-size: var(--vie-text-2xl);
}

.vie-heading--md {
    font-size: var(--vie-text-xl);
}

.vie-text-muted {
    color: var(--vie-text-muted);
}

/* ==========================================================================
   3. BUTTONS
   ========================================================================== */

.vie-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--vie-space-2);
    padding: var(--vie-space-3) var(--vie-space-6);
    font-size: var(--vie-text-sm);
    font-weight: var(--vie-font-medium);
    line-height: 1;
    border: none;
    border-radius: var(--vie-radius);
    cursor: pointer;
    transition: var(--vie-transition);
    text-decoration: none;
}

.vie-btn:focus {
    outline: none;
    box-shadow: var(--vie-ring);
}

.vie-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Primary Button */
.vie-btn--primary {
    background: var(--vie-primary);
    color: var(--vie-white);
}

.vie-btn--primary:hover:not(:disabled) {
    background: var(--vie-primary-dark);
}

/* Secondary Button */
.vie-btn--secondary {
    background: var(--vie-gray-100);
    color: var(--vie-text);
}

.vie-btn--secondary:hover:not(:disabled) {
    background: var(--vie-gray-200);
}

/* Outline Button */
.vie-btn--outline {
    background: transparent;
    border: 1px solid var(--vie-border-dark);
    color: var(--vie-text);
}

.vie-btn--outline:hover:not(:disabled) {
    background: var(--vie-gray-50);
}

/* Size Variants */
.vie-btn--sm {
    padding: var(--vie-space-2) var(--vie-space-4);
    font-size: var(--vie-text-xs);
}

.vie-btn--lg {
    padding: var(--vie-space-4) var(--vie-space-8);
    font-size: var(--vie-text-base);
}

.vie-btn--full {
    width: 100%;
}

/* ==========================================================================
   4. FORM ELEMENTS
   ========================================================================== */

.vie-form-group {
    margin-bottom: var(--vie-space-4);
}

.vie-form-label {
    display: block;
    font-size: var(--vie-text-sm);
    font-weight: var(--vie-font-medium);
    color: var(--vie-text);
    margin-bottom: var(--vie-space-2);
}

.vie-form-label .required {
    color: var(--vie-danger);
}

.vie-form-input,
.vie-form-select,
.vie-form-textarea {
    width: 100%;
    padding: var(--vie-space-3) var(--vie-space-4);
    font-size: var(--vie-text-base);
    border: 1px solid var(--vie-border);
    border-radius: var(--vie-radius);
    background: var(--vie-bg);
    color: var(--vie-text);
    transition: var(--vie-transition);
}

.vie-form-input:focus,
.vie-form-select:focus,
.vie-form-textarea:focus {
    outline: none;
    border-color: var(--vie-primary);
    box-shadow: var(--vie-ring);
}

.vie-form-input.is-invalid,
.vie-form-select.is-invalid {
    border-color: var(--vie-danger);
}

.vie-form-input.is-invalid:focus {
    box-shadow: var(--vie-ring-danger);
}

.vie-form-error {
    font-size: var(--vie-text-sm);
    color: var(--vie-danger);
    margin-top: var(--vie-space-1);
}

/* ==========================================================================
   5. UTILITIES
   ========================================================================== */

.vie-u-hidden {
    display: none !important;
}

.vie-u-sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}

.vie-u-text-center {
    text-align: center;
}

.vie-u-text-right {
    text-align: right;
}

.vie-u-flex {
    display: flex;
}

.vie-u-flex-center {
    display: flex;
    align-items: center;
    justify-content: center;
}

.vie-u-gap-2 {
    gap: var(--vie-space-2);
}

.vie-u-gap-4 {
    gap: var(--vie-space-4);
}

/* ==========================================================================
   6. RESPONSIVE
   ========================================================================== */

@media (max-width: 767px) {
    .vie-btn--lg {
        padding: var(--vie-space-3) var(--vie-space-6);
    }
}
```

---

### BƯỚC 4: Tạo file room-listing.css

| # | Task | Status |
|---|------|--------|
| 4.1 | Tạo file `assets/css/frontend/room-listing.css` | ⬜ |
| 4.2 | Copy styles cho filters, grid, room cards từ legacy | ⬜ |
| 4.3 | Refactor theo BEM + CSS variables | ⬜ |

**Ghi chú khi refactor:**
- Legacy: `.vie-filter-row` → Giữ nguyên (đã đúng BEM)
- Legacy: `.vie-room-card` → Giữ nguyên
- Thay `#2563eb` → `var(--vie-primary)`
- Thay `12px` → `var(--vie-radius-md)`

---

### BƯỚC 5: Tạo file booking-popup.css

| # | Task | Status |
|---|------|--------|
| 5.1 | Tạo file `assets/css/frontend/booking-popup.css` | ⬜ |
| 5.2 | Copy styles cho popup, steps, forms từ legacy | ⬜ |
| 5.3 | Refactor theo BEM + CSS variables | ⬜ |

---

## 📋 NGÀY 2: CSS ADMIN & FINALIZE

### BƯỚC 6: Tổ chức CSS Admin

| # | Task | Status |
|---|------|--------|
| 6.1 | Copy `_variables.css` từ legacy admin | ⬜ |
| 6.2 | Merge với `_variables.css` mới (nếu có khác biệt) | ⬜ |
| 6.3 | Copy `common.css` → `assets/css/admin/common.css` | ⬜ |
| 6.4 | Copy `page-*.css` → `assets/css/admin/` | ⬜ |

**Files cần copy:**
```bash
# Từ _backup_legacy_v1_*/inc/hotel-rooms/assets/admin/css/
cp common.css assets/css/admin/
cp page-bookings.css assets/css/admin/
cp page-bulk-matrix.css assets/css/admin/
cp page-rooms.css assets/css/admin/
cp page-settings.css assets/css/admin/
```

| # | Task | Status |
|---|------|--------|
| 6.5 | Cập nhật import paths trong mỗi file | ⬜ |
| 6.6 | Thay hardcoded values → CSS variables | ⬜ |

---

### BƯỚC 7: Copy CSS từ module khác

| # | Task | Status |
|---|------|--------|
| 7.1 | Copy `sepay-payment.css` | ⬜ |
| 7.2 | Copy `sepay-admin.css` | ⬜ |
| 7.3 | Copy `transport-metabox.css` | ⬜ |
| 7.4 | Copy `coupon-form.css` | ⬜ |

**Target locations:**
```
assets/css/frontend/payment.css      ← sepay-payment.css
assets/css/admin/page-sepay.css      ← sepay-admin.css
assets/css/admin/metabox-transport.css ← transport-metabox.css
assets/css/frontend/coupon-form.css  ← coupon-form.css
```

---

### BƯỚC 8: Tạo file inc/hooks/assets.php

| # | Task | Status |
|---|------|--------|
| 8.1 | Cập nhật file `inc/hooks/assets.php` | ⬜ |

**Xem code đầy đủ trong TECHNICAL-DESIGN-DOCUMENT-V2.md, Phần D, Task 1.2**

---

### BƯỚC 9: Testing CSS

| # | Test Case | Expected | Status |
|---|-----------|----------|--------|
| 9.1 | Mở trang hotel single | Room cards hiển thị đúng | ⬜ |
| 9.2 | Mở booking popup | Popup styled đúng | ⬜ |
| 9.3 | Test responsive (mobile) | Layout không bể | ⬜ |
| 9.4 | Mở admin rooms page | Table styled đúng | ⬜ |
| 9.5 | Mở admin bookings page | Filters, table đúng | ⬜ |
| 9.6 | Check DevTools Console | Không có 404 CSS | ⬜ |

---

### BƯỚC 10: Commit

| # | Task | Command | Status |
|---|------|---------|--------|
| 10.1 | Git add | `git add assets/css/ inc/hooks/assets.php` | ⬜ |
| 10.2 | Git commit | `git commit -m "feat: refactor CSS với BEM và CSS variables"` | ⬜ |
| 10.3 | Git push | `git push origin main` | ⬜ |

---

## ✅ DEFINITION OF DONE

- [ ] File `_variables.css` đã tạo với đầy đủ variables
- [ ] CSS frontend đã tách thành: `main.css`, `room-listing.css`, `booking-popup.css`, etc.
- [ ] CSS admin đã copy và update import paths
- [ ] File `inc/hooks/assets.php` load CSS đúng cho từng trang
- [ ] Không có lỗi 404 CSS trong DevTools
- [ ] UI hiển thị giống như trước khi refactor
- [ ] Đã test responsive trên mobile
- [ ] Đã commit và push

---

## ⏭️ TASK TIẾP THEO

Sau khi hoàn thành task này, chuyển sang: **[TASK-04-JS-REFACTOR.md](./TASK-04-JS-REFACTOR.md)**
