# RULE-03: QUY CHUẨN TỔ CHỨC CSS

**Phiên bản:** 1.0  
**Áp dụng cho:** Tất cả file CSS trong theme  
**Bắt buộc:** ✅ CÓ

---

## 1. CẤU TRÚC THƯ MỤC CSS

```
assets/css/
├── _variables.css          # ★ Single Source of Truth - Import đầu tiên
│
├── admin/                  # CSS cho Admin Panel
│   ├── common.css          # Styles dùng chung trong admin
│   ├── page-rooms.css      # Trang quản lý phòng
│   ├── page-bookings.css   # Trang quản lý booking
│   ├── page-calendar.css   # Trang lịch giá
│   └── page-settings.css   # Trang cài đặt
│
└── frontend/               # CSS cho Frontend
    ├── main.css            # Base styles, typography
    ├── room-listing.css    # Grid phòng, room cards
    ├── booking-popup.css   # Modal đặt phòng
    ├── checkout.css        # Trang thanh toán
    └── payment.css         # Section thanh toán SePay
```

---

## 2. FILE _VARIABLES.CSS

Đây là **Single Source of Truth** - file duy nhất định nghĩa biến CSS.

### Cấu trúc

```css
/**
 * ============================================================================
 * FILE: _variables.css
 * ============================================================================
 * 
 * Single Source of Truth cho tất cả biến CSS trong theme.
 * PHẢI import file này đầu tiên trong mọi file CSS khác.
 * 
 * MỤC LỤC:
 * 1. Colors
 * 2. Typography  
 * 3. Spacing
 * 4. Borders & Shadows
 * 5. Transitions
 * 6. Z-index Scale
 * ============================================================================
 */

:root {
    /* ===== 1. COLORS ===== */
    
    /* Brand Primary */
    --vie-primary: #2563eb;
    --vie-primary-light: #3b82f6;
    --vie-primary-dark: #1d4ed8;
    
    /* Brand Secondary */
    --vie-secondary: #64748b;
    
    /* Semantic */
    --vie-success: #10b981;
    --vie-danger: #ef4444;
    --vie-warning: #f59e0b;
    --vie-info: #0ea5e9;
    
    /* Neutrals - Gray Scale */
    --vie-white: #ffffff;
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
    
    /* Semantic Mappings */
    --vie-text: var(--vie-gray-800);
    --vie-text-muted: var(--vie-gray-500);
    --vie-border: var(--vie-gray-200);
    --vie-bg: var(--vie-white);
    --vie-bg-light: var(--vie-gray-50);
    
    /* ===== 2. TYPOGRAPHY ===== */
    
    --vie-font-sans: 'Inter', -apple-system, sans-serif;
    
    --vie-text-xs: 0.75rem;
    --vie-text-sm: 0.875rem;
    --vie-text-base: 1rem;
    --vie-text-lg: 1.125rem;
    --vie-text-xl: 1.25rem;
    --vie-text-2xl: 1.5rem;
    
    --vie-font-normal: 400;
    --vie-font-medium: 500;
    --vie-font-semibold: 600;
    --vie-font-bold: 700;
    
    /* ===== 3. SPACING ===== */
    
    --vie-space-1: 0.25rem;
    --vie-space-2: 0.5rem;
    --vie-space-3: 0.75rem;
    --vie-space-4: 1rem;
    --vie-space-5: 1.25rem;
    --vie-space-6: 1.5rem;
    --vie-space-8: 2rem;
    --vie-space-10: 2.5rem;
    --vie-space-12: 3rem;
    
    /* ===== 4. BORDERS & SHADOWS ===== */
    
    --vie-radius-sm: 4px;
    --vie-radius: 8px;
    --vie-radius-md: 12px;
    --vie-radius-lg: 16px;
    --vie-radius-full: 9999px;
    
    --vie-shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --vie-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    --vie-shadow-md: 0 10px 15px -3px rgba(0,0,0,0.1);
    --vie-shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.1);
    
    /* ===== 5. TRANSITIONS ===== */
    
    --vie-transition-fast: 150ms ease;
    --vie-transition: 200ms ease;
    --vie-transition-slow: 300ms ease;
    
    /* ===== 6. Z-INDEX ===== */
    
    --vie-z-dropdown: 100;
    --vie-z-sticky: 200;
    --vie-z-modal-bg: 400;
    --vie-z-modal: 500;
    --vie-z-tooltip: 700;
}
```

---

## 3. CẤU TRÚC MỘT FILE CSS

### Template

```css
/**
 * ============================================================================
 * FILE: [tên-file.css]
 * ============================================================================
 * 
 * [Mô tả file]
 * 
 * MỤC LỤC:
 * 1. [Section 1]
 * 2. [Section 2]
 * 3. [Section 3]
 * ...
 * N. Responsive
 * ============================================================================
 */

/* ==========================================================================
   1. SECTION NAME
   ==========================================================================
   Mô tả section này (nếu cần)
*/

.selector {
    property: value;
}

/* ==========================================================================
   2. SECTION NAME
   ========================================================================== */

/* ... */

/* ==========================================================================
   N. RESPONSIVE
   ========================================================================== */

/* Tablet: 768px - 1024px */
@media (max-width: 1024px) {
    /* ... */
}

/* Mobile: < 768px */
@media (max-width: 767px) {
    /* ... */
}
```

---

## 4. QUY TẮC ĐẶT TÊN (BEM)

Sử dụng **BEM Methodology** (Block__Element--Modifier)

### Cấu trúc

```
.block                  # Block - Component độc lập
.block__element         # Element - Phần tử con của block
.block--modifier        # Modifier - Biến thể của block
.block__element--modifier   # Modifier của element
```

### Ví dụ

```css
/* Block: Room Card */
.vie-room-card {
    background: var(--vie-bg);
    border-radius: var(--vie-radius-md);
    box-shadow: var(--vie-shadow);
}

/* Element: Image container */
.vie-room-card__image {
    position: relative;
    aspect-ratio: 16/10;
    overflow: hidden;
}

/* Element: Content area */
.vie-room-card__content {
    padding: var(--vie-space-4);
}

/* Element: Title */
.vie-room-card__title {
    font-size: var(--vie-text-lg);
    font-weight: var(--vie-font-semibold);
    color: var(--vie-text);
}

/* Element: Price */
.vie-room-card__price {
    font-size: var(--vie-text-xl);
    font-weight: var(--vie-font-bold);
    color: var(--vie-primary);
}

/* Modifier: Sold out state */
.vie-room-card--sold-out {
    opacity: 0.6;
    pointer-events: none;
}

.vie-room-card--sold-out .vie-room-card__price {
    text-decoration: line-through;
    color: var(--vie-text-muted);
}

/* Modifier: Featured */
.vie-room-card--featured {
    border: 2px solid var(--vie-primary);
}
```

---

## 5. PREFIX NAMING

Tất cả class phải có prefix `vie-` để tránh conflict với theme parent hoặc plugins.

### Quy tắc

| Loại | Prefix | Ví dụ |
|------|--------|-------|
| Component | `vie-` | `vie-room-card`, `vie-modal` |
| Layout | `vie-layout-` | `vie-layout-grid`, `vie-layout-sidebar` |
| Utility | `vie-u-` | `vie-u-hidden`, `vie-u-text-center` |
| State | `is-`, `has-` | `is-active`, `is-loading`, `has-error` |
| JavaScript | `js-` | `js-toggle-modal`, `js-submit-form` |

---

## 6. QUY TẮC VIẾT CSS

### 6.1 Thứ tự Properties

```css
.selector {
    /* 1. Layout & Position */
    position: relative;
    top: 0;
    left: 0;
    z-index: 10;
    display: flex;
    flex-direction: column;
    
    /* 2. Box Model */
    width: 100%;
    height: auto;
    margin: 0;
    padding: var(--vie-space-4);
    
    /* 3. Visual */
    background: var(--vie-bg);
    border: 1px solid var(--vie-border);
    border-radius: var(--vie-radius);
    box-shadow: var(--vie-shadow);
    
    /* 4. Typography */
    font-size: var(--vie-text-base);
    font-weight: var(--vie-font-medium);
    color: var(--vie-text);
    text-align: left;
    
    /* 5. Animation & Misc */
    transition: var(--vie-transition);
    cursor: pointer;
}
```

### 6.2 Sử dụng CSS Variables

```css
/* ✅ ĐÚNG - Dùng biến */
.vie-button {
    background: var(--vie-primary);
    border-radius: var(--vie-radius);
    padding: var(--vie-space-3) var(--vie-space-6);
    font-size: var(--vie-text-sm);
    transition: var(--vie-transition);
}

/* ❌ SAI - Hard-code giá trị */
.vie-button {
    background: #2563eb;
    border-radius: 8px;
    padding: 12px 24px;
    font-size: 14px;
    transition: 200ms ease;
}
```

### 6.3 Không nesting quá sâu

```css
/* ✅ ĐÚNG - Tối đa 3 level */
.vie-modal { }
.vie-modal__header { }
.vie-modal__header-title { }

/* ❌ SAI - Nesting quá sâu */
.vie-modal .header .content .title .text { }
```

### 6.4 Avoid !important

```css
/* ✅ ĐÚNG - Tăng specificity nếu cần */
.vie-room-card.is-featured {
    border-color: var(--vie-primary);
}

/* ❌ SAI - Dùng !important */
.vie-room-card {
    border-color: var(--vie-primary) !important;
}
```

---

## 7. RESPONSIVE BREAKPOINTS

```css
/* Mobile First Approach */

/* Base styles cho mobile */
.vie-room-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--vie-space-4);
}

/* Tablet: 768px+ */
@media (min-width: 768px) {
    .vie-room-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: var(--vie-space-6);
    }
}

/* Desktop: 1024px+ */
@media (min-width: 1024px) {
    .vie-room-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Large Desktop: 1280px+ */
@media (min-width: 1280px) {
    .vie-room-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: var(--vie-space-8);
    }
}
```

---

## 8. CHECKLIST REVIEW

- [ ] File có header block đầy đủ?
- [ ] Import `_variables.css` nếu dùng biến?
- [ ] Tất cả class có prefix `vie-`?
- [ ] Tuân thủ BEM naming?
- [ ] Không hard-code colors/spacing?
- [ ] Properties theo thứ tự chuẩn?
- [ ] Có responsive styles ở cuối file?
- [ ] Không dùng `!important` (trừ override 3rd party)?
