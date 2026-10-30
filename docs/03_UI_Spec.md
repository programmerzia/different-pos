# 03 — UI Specification & Design System

> Implementation: tokens are CSS custom properties in `resources/css/app.css`, exposed to Tailwind CSS v4 via `@theme inline`. Components are **shadcn/ui (React, Radix primitives)** restyled with these tokens. **Never use raw hex or Tailwind palette classes (`bg-blue-500`) in components. Use semantic tokens only (`bg-primary`, `text-muted-foreground`).**
> Design principles:
> 1. **Feels like a desktop app.** It is dense, keyboard-first, uses right-click menus and has a status bar. Nothing ever makes the user reach for the mouse.
> 2. **Speed over decoration.**
> 3. **Numbers are the hero.** Money is large, tabular and right-aligned.
> 4. **Calm by default, loud on exception.** Colour signals state, never decoration.
> 5. **Bangla-first legibility.**
> 6. **Offline is a normal state**, not an error. It shows as amber, never red.

---

## 1. Colour tokens

### 1.1 Brand & neutral scales (reference; do not use directly in components)
| Step | Brand (Teal) | Accent (Amber) | Neutral (Slate) |
|---|---|---|---|
| 50 | `#F0FDFA` | `#FFFBEB` | `#F8FAFC` |
| 100 | `#CCFBF1` | `#FEF3C7` | `#F1F5F9` |
| 200 | `#99F6E4` | `#FDE68A` | `#E2E8F0` |
| 300 | `#5EEAD4` | `#FCD34D` | `#CBD5E1` |
| 400 | `#2DD4BF` | `#FBBF24` | `#94A3B8` |
| 500 | `#14B8A6` | `#F59E0B` | `#64748B` |
| 600 | `#0D9488` | `#D97706` | `#475569` |
| 700 | `#0F766E` | `#B45309` | `#334155` |
| 800 | `#115E59` | `#92400E` | `#1E293B` |
| 900 | `#134E4A` | `#78350F` | `#0F172A` |
| 950 | `#042F2E` | `#451A03` | `#020617` |

### 1.2 Semantic tokens (what components use)
| Token | Light | Dark | Use |
|---|---|---|---|
| `--background` | `#F8FAFC` | `#0B1120` | app canvas |
| `--surface` (`card`) | `#FFFFFF` | `#111827` | cards, panels, cart |
| `--surface-raised` (`popover`) | `#FFFFFF` | `#1F2937` | menus, dialogs, dropdowns |
| `--surface-sunken` (`muted`) | `#F1F5F9` | `#0F172A` | table header, input wells, disabled fill |
| `--foreground` | `#0F172A` | `#F1F5F9` | body text |
| `--muted-foreground` | `#475569` | `#94A3B8` | secondary text, labels |
| `--subtle-foreground` | `#64748B` | `#64748B` | placeholders, captions (≥ 14px only) |
| `--border` | `#E2E8F0` | `#1E293B` | dividers, card borders |
| `--input` | `#CBD5E1` | `#334155` | input borders |
| `--ring` | `#14B8A6` | `#2DD4BF` | focus ring (2px + 2px offset) |
| `--primary` | `#0F766E` | `#14B8A6` | primary buttons, active nav, links |
| `--primary-hover` | `#115E59` | `#2DD4BF` | |
| `--primary-foreground` | `#FFFFFF` | `#042F2E` | text on primary |
| `--primary-soft` | `#CCFBF1` | `#042F2E` | selected rows, active chips |
| `--accent` | `#D97706` | `#FBBF24` | highlights, "held" badge, attention CTA |
| `--accent-foreground` | `#FFFFFF` | `#451A03` | |
| `--success` | `#15803D` | `#4ADE80` | paid, completed, in stock |
| `--success-soft` | `#DCFCE7` | `#052E16` | |
| `--warning` | `#B45309` | `#FBBF24` | low stock, partial, due soon |
| `--warning-soft` | `#FEF3C7` | `#422006` | |
| `--danger` (`destructive`) | `#B91C1C` | `#F87171` | errors, overdue, void, out of stock |
| `--danger-soft` | `#FEE2E2` | `#450A0A` | |
| `--info` | `#1D4ED8` | `#60A5FA` | info toasts, draft |
| `--info-soft` | `#DBEAFE` | `#172554` | |
| `--money-positive` | `#15803D` | `#4ADE80` | profit, cash in |
| `--money-negative` | `#B91C1C` | `#F87171` | loss, cash out, due |

Contrast: every text/background pair above is **≥ 4.5:1** (AA) at body size. Soft backgrounds carry text in the matching strong colour (e.g. `--warning` on `--warning-soft`). Verify with a contrast check in CI (Phase 4).

### 1.3 Chart palette (categorical, in order)
`#0F766E`, `#D97706`, `#1D4ED8`, `#BE185D`, `#7C3AED`, `#15803D`, `#475569`. Dark: `#2DD4BF`, `#FBBF24`, `#60A5FA`, `#F472B6`, `#A78BFA`, `#4ADE80`, `#94A3B8`. Max 7 series; group the rest as "Other".

### 1.4 Colour schemes (theme switcher)
A **scheme** overrides only the brand tokens: `--primary`, `--primary-hover`, `--primary-foreground`, `--primary-soft` and `--ring`. Neutrals and semantic colours stay fixed, so status colours keep the same meaning in every scheme. Applied as `data-scheme="…"` on `<html>`.

| Scheme | Light primary / hover / soft / on-primary | Dark primary / hover / soft / on-primary |
|---|---|---|
| **Teal** (default) | `#0F766E` / `#115E59` / `#CCFBF1` / `#FFFFFF` | `#14B8A6` / `#2DD4BF` / `#042F2E` / `#042F2E` |
| **Indigo** | `#4338CA` / `#3730A3` / `#E0E7FF` / `#FFFFFF` | `#818CF8` / `#A5B4FC` / `#1E1B4B` / `#1E1B4B` |
| **Violet** | `#6D28D9` / `#5B21B6` / `#EDE9FE` / `#FFFFFF` | `#A78BFA` / `#C4B5FD` / `#2E1065` / `#2E1065` |
| **Rose** | `#BE123C` / `#9F1239` / `#FFE4E6` / `#FFFFFF` | `#FB7185` / `#FDA4AF` / `#4C0519` / `#4C0519` |
| **Ocean** | `#0369A1` / `#075985` / `#E0F2FE` / `#FFFFFF` | `#38BDF8` / `#7DD3FC` / `#082F49` / `#082F49` |
| **Graphite** | `#334155` / `#1E293B` / `#E2E8F0` / `#FFFFFF` | `#CBD5E1` / `#E2E8F0` / `#1E293B` / `#0F172A` |

`--ring` = the scheme's dark-mode primary in light mode and its light-mode hover in dark mode. With the Rose scheme, the `--danger` contrast with primary is still distinct because danger badges always carry an icon.
**Density:** `comfortable` (default) or `compact`. Compact reduces table row height from 44 px to 32 px, card padding from 24 to 16, and font size in tables to 13 px. **POS touch targets never shrink.**
**White-label (post-MVP, PRD Epic J):** a tenant brand colour becomes an extra scheme generated server-side (12-step scale from one hex, AA contrast-checked, rejected if it fails).

### 1.5 Theme rules
- Three modes: `light` · `dark` · `system` (default), plus the scheme (§1.4) and density. All are stored per user (`users.preferences.theme/scheme/density`) and mirrored to a cookie so SSR has no flash.
- Dark mode is applied through the `.dark` class on `<html>`. Every token has a dark value. **No component may hard-code a light-only colour.**
- Images/logos: tenant logos are shown on a `--surface` chip so they stay legible in dark mode.
- Receipts and PDFs are **always light** (print).
- Status colour is never the only signal. Always pair it with an icon or a text label (colour-blind safe).

## 2. Typography

| Role | Family | Fallback |
|---|---|---|
| UI (Latin) | **Inter** (variable, 400–700) | `ui-sans-serif, system-ui` |
| UI (Bangla) | **Hind Siliguri** (400, 500, 600, 700) | `"Noto Sans Bengali", sans-serif` |
| Numbers | Inter with `font-variant-numeric: tabular-nums` (utility `.num`) | — |
| Mono (SKU, codes) | **JetBrains Mono** 400/500 | `ui-monospace` |

The font stack is `Inter, "Hind Siliguri", ui-sans-serif, system-ui`, so Bangla glyphs fall through to Hind Siliguri automatically. Fonts are self-hosted (`@fontsource`), not loaded from a CDN, so a shop with bad internet still renders correctly. Bangla needs a line-height ≥ 1.6. The scale below already accounts for it.

| Token | Size / line-height | Weight | Use |
|---|---|---|---|
| `text-xs` | 12 / 18 | 500 | badges, table meta (never Bangla body) |
| `text-sm` | 14 / 22 | 400 | table cells, inputs, secondary |
| `text-base` | 16 / 26 | 400 | body default |
| `text-lg` | 18 / 28 | 600 | card titles, cart line names |
| `text-xl` | 20 / 30 | 600 | section titles |
| `text-2xl` | 24 / 32 | 700 | page titles |
| `text-3xl` | 30 / 38 | 700 | KPI values |
| `display-xl` | 44 / 52 | 700, tabular | POS total, change due |

Money formatting: `৳ 1,23,456.00` (South Asian grouping, `Intl.NumberFormat('en-IN')` or `bn-BD`), and the currency symbol is never wrapped onto its own line (`&nbsp;`). Negative amounts show as `−৳ 500.00` in `--money-negative` (no parentheses). Dates: `29 Oct 2026` / `২৯ অক্টো ২০২৬`. Time is 12 h with am/pm.

## 3. Spacing, layout, shape, elevation

- **Base unit: 4 px.** Allowed spacings: 0, 2, 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 (Tailwind `0 0.5 1 2 3 4 5 6 8 10 12 16`). No arbitrary values (`p-[13px]`).
- Page gutter: 16 px phone · 24 px tablet · 32 px desktop. Content max width 1440 px (POS is full-bleed).
- Card padding 16 (compact) / 24 (default). Gap between cards 16 / 24.
- Form: label→input 6 px, field→field 16 px, section→section 32 px. Max form width 720 px; two columns ≥ 1024 px.
- Breakpoints: `sm 640 · md 768 · lg 1024 · xl 1280 · 2xl 1536`. Design mobile-first and test at 360 px.
- **Touch targets ≥ 44×44 px** everywhere. POS: buttons **56 px** tall, product tiles ≥ 120×120 px, qty steppers 44 px.
- Radius: `--radius-sm 6px` (inputs, badges) · `--radius 10px` (buttons, cards) · `--radius-lg 14px` (dialogs, sheets) · `full` (avatars, pills).
- Elevation: `shadow-xs` (cards, light only) · `shadow-md` (dropdowns) · `shadow-xl` (dialogs). In dark mode, shadows are replaced by a 1 px `--border` plus a lighter surface.
- Z-index: dropdown 50 · sticky 40 · overlay 60 · dialog 70 · toast 80 · print-hidden UI uses `print:hidden`.
- Motion: 150 ms ease-out (hover, small), 200 ms (sheets, dialogs). Respect `prefers-reduced-motion`. Never animate money values while a sale is in progress.

## 4. Iconography & imagery
- **Lucide** icons (`lucide-react`), 16 px in tables, 20 px in buttons, 24 px in nav. Stroke 1.75.
- Product placeholder: neutral-200 tile with the product initials (2 letters) on a deterministic colour from the chart palette.
- Empty-state illustrations: simple line art in `--muted-foreground` + `--primary` accents (one SVG set, light/dark aware via `currentColor`).

## 5. Core components & states

Every interactive component implements these states: **default · hover · focus-visible · active/pressed · disabled · loading · error** (where applicable). Focus is always visible: a 2 px `--ring` with a 2 px offset. Never remove outlines.

| Component | Variants | State rules |
|---|---|---|
| **Button** | `primary` · `secondary` (surface + border) · `ghost` · `destructive` · `accent` · `link`. Sizes `sm 32` · `md 40` · `lg 48` · `pos 56` | hover = `*-hover` colour. pressed = scale 0.98. disabled = 50 % opacity + `cursor-not-allowed`, still focusable via `aria-disabled` when it has a tooltip. loading = spinner replaces the leading icon, the label stays, the button keeps its width, double-submit blocked |
| **Input / Textarea / Select / Combobox** | default · with prefix (৳) / suffix (unit) · `num` (right-aligned, tabular) | focus = ring. error = `--danger` border + message below (icon + text) + `aria-invalid` + `aria-describedby`. disabled = sunken fill. readonly = no border, sunken fill |
| **Money input** | — | Accepts only digits and one `.`, max 2 decimals. Formats on blur and shows the raw value on focus. Selects all on focus. Bangla digits are accepted and normalised |
| **Qty stepper** | integer · decimal | `−`/`+` 44 px. Long-press repeats. Respects min sale qty and unit decimals. Shows an error tooltip when it exceeds available stock |
| **Badge** | `neutral` · `success` · `warning` · `danger` · `info` · `accent` | soft background + strong text + optional dot. Fixed map: Paid=success, Partial=warning, Unpaid/Due=danger, Draft=info, Held=accent, Void=neutral struck-through |
| **Table (DataTable)** | default · compact | sticky header. Zebra off, row hover `--surface-sunken`. Selected row `--primary-soft`. Numeric columns right-aligned, `num`. Loading = 8 skeleton rows. Empty = §AppFlow 5.1. On phone (< 768) rows render as stacked cards with key fields |
| **Card / KPI tile** | default · interactive | KPI: label (`text-sm muted`), value (`text-3xl num`), delta chip (▲ success / ▼ danger, vs previous period), sparkline. Skeleton while loading |
| **Dialog / Sheet** | dialog (centered, max 560) · sheet (right on desktop, bottom on phone) · confirm | Focus is trapped. `Esc` closes unless the dialog is destructive and mid-submit. The destructive confirm button repeats the record name |
| **Toast** | success · error · info · warning | Bottom-right desktop, top on phone. 4 s auto-dismiss (errors 8 s, with a close button). Max 3 stacked. `aria-live=polite` (`assertive` for errors) |
| **Command palette** | — | `Ctrl/⌘+K`. Sections: Pages, Products, Customers, Invoices. Recent items first |
| **Tabs, Pagination, Breadcrumb, Dropdown, Tooltip, Avatar, Switch, Checkbox, Radio, DatePicker (range presets), Skeleton, Alert/Banner** | shadcn/ui defaults restyled with tokens | — |
| **Context menu** (Radix ContextMenu) | items · checkable · submenu · separator · destructive | `surface-raised`, `shadow-md`, min-width 220 px, items 32 px (28 compact). Each item: icon 16 · label · right-aligned `Kbd` hint. Hover/arrow = `--primary-soft` bg. Destructive last, `--danger` text. Opens on right-click, `Shift+F10`, `≣ Menu` key or long-press. Type-ahead to jump to an item. Never replaces the native menu inside text inputs |
| **Kbd (key hint)** | inline · on-button | `text-xs` mono, 1 px `--border`, `--surface-sunken` bg, radius-sm, 2 px × 6 px. On buttons it sits right-aligned at 70 % opacity. Hidden on touch-only devices (`pointer: coarse`) |
| **Top bar** | — | 52 px high (44 compact), `--surface`, bottom border. Holds: tenant/branch, command trigger (looks like a search field with a `Ctrl K` hint), sync pill, register pill, notifications bell, clock, language, theme, avatar |
| **Sync pill** | online · offline · syncing · error | Online: success dot + "Online". Offline: `--warning-soft` bg + wifi-off icon + "Offline". Syncing: accent + rotating icon + "n unsynced". Error: danger + "Sync error". Always shows the last sync relative time in its tooltip |
| **Notifications bell + panel** | — | Bell with a count badge (`--danger` bg, max "99+"). The panel is a 380 px popover with tabs All · Alerts · Exports. Each item has a type icon in a soft-colour circle, title, one-line body, relative time and an unread dot. Footer: Mark all read · Settings. Empty state: "You're all caught up" |
| **Clock** | — | `text-sm num` time `hh:mm:ss a` + `text-xs muted` day/date. Ticks every second, with no layout shift (tabular nums, fixed width) |
| **Theme popover** | — | Mode segmented control (☀ Light · ☾ Dark · 🖥 System), a scheme swatch grid (6 circles 28 px, selected = ring + check), density toggle. Live preview while hovering a swatch |
| **Status bar** (desktop) | — | 28 px, `--surface-sunken`, `text-xs muted`. Segments separated by `│`. Device indicators: printer, scanner, sync, local DB size, version |
| **PIN pad dialog** | staff · customer | Large masked dots (6 max), 3×4 keypad (buttons 72 px, `num` 28 px), physical digit keys work, `Backspace`, `Enter`. Customer variant: a high-contrast full-screen overlay "Please enter your PIN" in EN+BN, with the customer name and no staff UI visible. The error shake animation is disabled under reduced motion. Never shows digits |
| **Membership card** (print) | CR80 85.6×54 mm · A4 10-up (2×5, 3 mm crop marks) | **Front:** tenant logo (top-left, max 18 mm wide), tenant name, the "MEMBER" label in the scheme primary, customer name (`14pt` bold, max 2 lines, Bangla-safe font), customer no. formatted `1000 0017` (`16pt` mono), group/tier chip, "Member since 10/2026", Code128 barcode (`MC{no}{issue}`) bottom, QR right. **Back:** terms (max 6 lines), branch phone(s), "PIN required for every purchase / প্রতিবার কেনাকাটায় পিন প্রয়োজন", issue no., signature line. Safe area 3 mm. Colour background band uses the tenant scheme. Barcode is always black on white |
| **Location combobox** | per level | Shows `name_en · name_bn` and a level badge (Upazila / Thana / City Corp / Ward / Union). Results are grouped by parent. Auto-filled parents flash `--primary-soft` for 600 ms |
| **Customer chip** (POS) | walk-in · member verified · pending (offline) · override · blocked | Shows number, name and group. The state is shown as an icon + colour: ✓ success, ⏳ warning, ⚠ accent, ⛔ danger |
| **POS product tile** | default · selected (just added: 300 ms `--primary-soft` flash) · low stock (warning badge "3 left") · out of stock (dimmed 60 %, "Out" danger badge, click shows a toast instead of adding unless negative stock is allowed) | — |
| **POS cart line** | default · selected (left 3 px `--primary` bar) · error (`--danger-soft` bg + message) · discounted (original price struck-through) | — |
| **Receipt (print)** | thermal 80 mm (72 mm printable) · thermal 58 mm (48 mm) · A4 | Monochrome only. Font 12 px (80) / 10 px (58). Logo max 40 mm wide, greyscale. QR to `/r/{token}`. Barcode of the invoice no (Code128) |

## 6. Accessibility & i18n rules
- WCAG 2.2 AA. Every icon-only button has an `aria-label`. Form inputs have visible labels (no placeholder-as-label).
- All strings go through `react-i18next` (client) / Laravel `__()` (server). **No hard-coded UI text.** Keys are namespaced: `pos.pay`, `sales.status.paid`.
- Locale switch changes UI text, number/date formats, and optionally Bangla digits (`users.preferences.bn_digits`). Data entry accepts both digit sets.
- Tables and charts expose a text summary for screen readers (chart → "View as table").
- Keyboard: every POS action has a shortcut (02_AppFlow §4.3). Tab order follows the visual order. Shortcuts are listed in a `?` overlay.
