# Mockup gap review v3: `index.html` build of 2026-10-29 19:20

Method: the file was served locally and driven in headless Chrome at 1440×900 (every G-shortcut, every POS function key, each overlay opened on a fresh page, right-click on the first table row, palette typing, dark mode, 390 px width), plus static checks of the source. Measured against `gemini_prompt_final.md` and `gemini_followup_v2.md`. No JavaScript errors were thrown.

## 1. Blockers (fix first)

| # | Defect | Evidence |
|---|---|---|
| B1 | Five POS dialogs cannot be closed with Esc or a backdrop click: Attach customer (F4), Line discount (F6), Order discount (Shift+F6), Collect due (Ctrl+D), Quick new customer (Ctrl+N). They also stack on each other. | Each opened on a fresh page; Esc and an outside click left them open. Focus goes to `<body>`, not back to the scan box. |
| B2 | POS keys differ from the spec and collide. **F11 is labelled on both "Exact Cash" and "Return".** | Spec → mock: scan F2 → F3 · Hold F8 → F2 · Price check F5 → F7 · Pay F9 → F10. F9 does nothing. |
| B3 | Command palette does not search. Typing "saree" or "1000 0017" leaves the static list unchanged. Prefixes `# > / @` do nothing. | Tested with real input events. |
| B4 | Permission leak: the sidebar hides Catalog, Money, Insights and Admin from the Cashier (correct), but G P, G M, G D, G R and G , still open those screens for her. | Chords run as Sumi (Cashier). |
| B5 | Shifts not fully removed (follow-up v2 §1). | Status bar shows "Shift: Float ৳5,000"; Dashboard card says "EOD Cash Reconciled". |

## 2. Screens missing or unreachable

| Screen | State |
|---|---|
| Vendor profile | Declared, never rendered. No route. |
| Product detail | Rendered, but nothing navigates to it. |
| Sale detail | Reachable only from a row click; no back-dated badge, courier, flag alert actions checked. |
| Auth: login, branch picker, forgot password, My account, login history, sessions | Not present. Only the lock screen exists. |
| Membership print designer, Messaging templates / automation / log | Thin pages (Messaging 927 characters, no table). |
| Returns list | 2 rows, one button, no filters or detail page. |
| Dashboard | 4 KPI cards and an **empty** hourly chart. Missing: period/branch selectors, 5 other KPIs, 4 charts, all 5 lists, widget right-click menus. |
| Settings | 5 tabs out of 15 sections (no branches, registers and devices, numbering, PIN policy, messaging providers, notifications, appearance, language, shortcuts, import/export). Content is centred at 896 px instead of full width. |
| Users and roles, Backup, Audit log, Super admin, Fixed assets, States demo | Exist but only reachable as Owner via the sidebar; not in palette or shortcuts. Not inspected in depth this round. |

## 3. List pattern (prompt "every list page", §6) not applied

On Sales, Due, Customers, Products, Stock, Purchases, Vendors, Money:
- **No totals footer** on any list (0 of 8).
- Filters: at most a search and one or two selects. No filter chips, "More filters" drawer, saved views, column chooser or date presets beyond a select.
- No pagination with total count; Sales renders 65 rows in one scroll.
- No bulk-select bar, no J/K row navigation, no Export CSV/PDF or Print (Sales has Export Excel only).
- Right-click works on table rows (8 of 8 lists). Shift+F10 for the selected row was not verified to target that row.
- Sales list lacks Discount, VAT, Branch, Register columns and filters for cashier, method, sync flag, has return, courier.

## 4. Behaviour gaps inside built features

- **Toast-only actions remain**: 98 `addToast` calls, several standing in for real dialogs ("Add brand modal opened", "Add Vendor dialog opened", "CSV Import dialog opened").
- Payment opens on F10 and works; Complete-sale key, split-tender Alt keys and Rocket input not verified this round.
- Customer numbers in Sales show as "Customer #10000249" instead of name + "1000 0249".
- POS product grid has no images and no size × colour variant picker on parent items.
- Totals logic is correct: Subtotal 4,790 − line discounts 290 → VAT 15 % 675 → Total 5,175.

## 5. Visual and token issues

- 11 raw Tailwind palette classes remain (emerald/amber/rose…), against "colours only from tokens". The sync pill uses them.
- The Taka sign renders as "ь" in amounts set in JetBrains Mono, which has no ৳ glyph. Give money a font stack with Hind Siliguri as fallback for the symbol. Check on your machine; headless fonts can differ.
- Monospace is used for whole amount columns and some labels, which makes tables noisy.
- Dark mode is coherent. 390 px width has no horizontal overflow.

## 6. Priority order for the next Gemini round

1. B1–B5.
2. List pattern on all 8 lists (totals, filters drawer, date presets, pagination, bulk bar, J/K, export).
3. Dashboard complete; Settings full width with all 15 sections.
4. Auth screens; Vendor profile; Product detail navigation; Returns list + wizard + detail.
5. Replace every toast-only action with a real dialog.
6. Token cleanup and the ৳ font fallback.
