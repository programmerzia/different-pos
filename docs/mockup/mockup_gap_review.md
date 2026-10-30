# Mockup gap review: `index.html` (Gemini, 2026-10-29)

This review checks the mockup against the legacy features ([00_Legacy_Features.md](../00_Legacy_Features.md)) and against the screens the Gemini prompt asked for ([gemini_prompt.md](gemini_prompt.md)).

**Legend:** ✅ done · 🟡 partial or toast-only · ❌ missing

## 1. Shell (what works)
✅ Top bar:
- tenant name and branch switcher
- sync pill and Sync center
- register pill, notifications with tabs, live clock, EN/বাং, theme popover (mode, 6 schemes, density), avatar menu

✅ Ctrl+K palette, Ctrl+B sidebar, Ctrl+L lock screen, `?` overlay, right-click menus, toasts, status bar, offline banner.

🟡 Shift+F10 opens a generic menu, not the menu for the selected row.

🟡 The command palette doesn't really search. Only the "#10000017" demo works.

❌ Navigation holes:
- Sidebar **Shift Close** opens a blank page. Only the register pill opens the Z-report.
- **G R** and **G ,** open blank pages.
- **G P** and **G M** do nothing.
- **Purchases, Money/Accounting, Reports and Settings are not in the sidebar at all.**

## 2. Legacy feature parity

| Legacy module | Legacy feature | Mockup |
|---|---|---|
| Auth | Login, logout, change password, login logs | ❌ (lock screen only) |
| Users/roles | Users CRUD, roles, permission matrix | ❌ |
| Shops | Branch CRUD, logo/invoice header, `is_use_stock_invoice` | ❌ |
| Catalog | Product **create/edit form** (code, barcode, unit, MRP, buy price, min qty, alert level) | ❌ |
| Catalog | Categories, Brands | ❌ (category chips only) |
| Catalog | Enable/disable/delete, decimal qty + units | ❌ |
| Purchasing | **Purchase / stock-in invoice** (lines, buy price, qty, batch) | ❌ |
| Purchasing | Purchase list/view/edit, **stock check** | ❌ |
| Purchasing | **Vendors** + purchase history + payables | ❌ |
| Inventory | Product table with stock per branch | ✅ |
| Inventory | Low-stock filter, movement drawer, batch/FIFO view | ❌ (movement is a toast) |
| Inventory | Transfers, stocktake, adjustments, barcode label print | 🟡 toast only |
| Sales | Single-screen register, barcode-first | ✅ |
| Sales | Customer pick + **inline new customer** at the sale | 🟡 pick only |
| Sales | Line discount, order discount (F6) | 🟡 F6 modal doesn't render |
| Sales | Split tender, partial, change | 🟡 the Rocket input is missing and Alt keys aren't wired |
| Sales | **Due collection** (per invoice and across invoices) | 🟡 toast only |
| Sales | Back-dated sale, courier name and tracking | ❌ |
| Sales | Invoice print 80mm | ✅ |
| Sales | Invoice A4 / PDF | ❌ |
| Sales | Invoice list with filters (no., customer, status, date range) | 🟡 list only, no filters and no Due column |
| Sales | **Sale detail** (lines, payments, returns, flag alert) | ❌ |
| Sales | Void / edit rules | 🟡 toast |
| Returns | **Sales return flow** (find invoice, qty ≤ sold, refund method, restock) | ❌ (toast) |
| Returns | Return list/view | ❌ |
| Customers | List | 🟡 no filters, no bulk bar, no J/K, no Last purchase column |
| Customers | Customer groups management | ❌ |
| Customers | **Lapsed (irregular) customers** | ❌ |
| Customers | Export | ❌ |
| Customers | Profile with invoice history | 🟡 statement table only, no tabs |
| Accounting | Heads/types (expense, salary, withdraw, income, rent) | ❌ |
| Accounting | Entries, PDF | ❌ |
| Accounting | **Fixed assets** | ❌ |
| Dashboard | KPIs, trend, top products, branch compare, district | ✅ |
| Dashboard | Low-stock list widget, top customers | ❌ (KPI tile only) |
| Reports | Sales, daily, stock value, stock count, product list, sale price, **P&L**, due, due collection by user, top customers, PDF/Excel | ❌ **no Reports screen at all** |
| Ops | Backup settings | ❌ |
| Shift | Z-report denomination grid | ✅ (expected vs counted per tender missing, cash only) |
| Shift | Cash in/out (F7), held sales list (Shift+F8) | ❌ the modals don't render |

## 3. New features: deviations from the docs and prompt

| Area | Problem |
|---|---|
| POS totals | **VAT is hard-coded 0 and the VAT line isn't shown.** The spec is 15 % standard VAT. |
| POS | F3 uses the browser `prompt()`. The F6, F7 and Shift+F8 modals have state but **no UI**. |
| Customer attach | The search box doesn't filter. There's no "invalid check digit" message. |
| PIN pad | **4 digits; the spec says up to 6.** The "offline: verified when online" state, the **no-PIN variant** (set PIN now / walk-in / manager override) and digit keys from the physical keyboard are all missing. |
| Customer form | The geo picker is 3 plain dropdowns. **Union/Ward/Pourashava, Area, Post code and Address line are missing.** It isn't a type-ahead combobox and has no type badges. |
| Customer form | Missing fields: alt phone, shop name, email, gender, DOB, anniversary, credit limit, VAT BIN, notes. There's no duplicate-phone warning example. |
| Customer form | It has **"Send PIN SMS"**, which conflicts with SMS being deferred. Remove it. The cards list is also missing. |
| Customer profile | No tabs: Overview, Sales, Payments, Statement, Cards and Activity. Collect due and Statement PDF are missing. The location breadcrumb is hard-coded to "Rajshahi". |
| Membership card | Only CR80 is shown. The **A4 10-up with crop marks** and PDF options are missing, and so is the bulk print from the customer list. |
| Sales | No sale detail page and no price-mismatch alert panel. |
| States | The offline back-office view ("You're offline → Go to POS") is missing. |
| Tokens | Badges use raw `emerald-/rose-/amber-` classes, not the tokens, so they ignore the scheme and dark mode. This is acceptable in a mockup, but don't copy it. |

## 4. Verdict
The mockup is fine for the **shell and the POS flow**. It covers the scope of roughly **35 %**. The biggest missing pieces are **Purchases + Vendors, the Product form, Returns, Reports, Accounting, Settings/Users/Roles and Login**.

To fill the gaps, use the follow-up prompt in [gemini_followup_prompt.md](gemini_followup_prompt.md). Paste the batches one at a time into the same Gemini chat.
