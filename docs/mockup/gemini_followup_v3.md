# Follow-up v3 (send after mockup_gap_review_v3.md)

Paste everything inside the fence into the same Gemini Canvas.

```text
Keep the same Canvas file. Do NOT restart, rename or remove any module, data or styling that works.
Fix the items below in this exact order. After each part, re-test the listed checks yourself and
only then continue. If you run out of output space, stop at a part boundary, write
"▶ NEXT: Part <n>" and wait. Return the complete file each time.

PART 1 — BLOCKERS
1.1 One modal system for every overlay
- Build one <Modal> component and use it for EVERY dialog, drawer and popover: Attach customer (F4),
  Line discount (F6), Order discount (Shift+F6), Collect due (Ctrl+D), Quick new customer (Ctrl+N),
  Payment, Hold list, Price check, Return mode dialogs, shortcut overlay, lock screen, and all
  back-office dialogs.
- Rules: Esc closes the top-most overlay only; clicking the backdrop closes it (except Payment
  with tendered money and the PIN pad, which ask "Discard?"); a visible × close button top-right;
  focus is trapped inside; on close, focus returns to the element that opened it (on the POS that
  is always the scan box).
- Only ONE modal may be open at a time. Opening a new one closes the current one first
  (exception: Manager PIN may sit above a discount dialog).
- Pressing a function key while a modal is open must not open another modal behind it.
1.2 POS key map exactly as the spec (remove all conflicts)
- F1 POS · F2 focus scan box · F3 qty of selected line · F4 attach customer · F5 price check ·
  F6 line discount · Shift+F6 order discount · F8 hold · Shift+F8 held list · F9 PAY ·
  F10 complete sale (inside Payment) · F11 return/exchange · Ctrl+D collect due · Ctrl+N new
  customer · Ctrl+L lock · Ctrl+K palette · Ctrl+B sidebar · ? shortcuts.
- "Exact cash" is NOT a function key: it is a quick-cash chip inside Payment (Alt+E).
- Update every <kbd> label, button, key-hints bar, shortcut overlay and palette entry to match.
  No key may appear on two actions.
1.3 Command palette really searches
- Fuzzy search over Pages, Actions, Products (name EN/BN, SKU, barcode), Customers (customer no.
  with or without space, name, phone, card barcode MC…), Sales (invoice no.), Purchases (ST-…),
  Vendors. Results grouped with counts; top 5 per group; ↑↓ and Enter open; recent searches.
- Prefixes: "#" customers, ">" actions, "/" invoices, "@" vendors.
- Typing "1000 0017" opens Rahim Uddin's 360 profile; an invalid check digit shows
  "Invalid customer number".
1.4 Permissions apply to shortcuts and palette too
- One function canAccess(screenId) used by the sidebar, G-shortcuts, palette and deep links.
- As Cashier, G P / G M / G D / G R / G , and palette entries for restricted screens show a toast
  "Not allowed for Cashier — ask a Manager" and do not navigate. "View as role" changes all of them.
1.5 Remove every shift remnant
- Status bar "Shift: Float ৳5,000" → "Today ৳84,250 · 63 sales".
- Dashboard "Cash in drawer / EOD Cash Reconciled" → "Cash sales today" with cash/bKash/card split.
- Search the whole file for "shift", "float", "Z-report", "X-report", "EOD" and remove them from
  UI text and mock data.

PART 2 — THE LIST PATTERN ON ALL 8 LISTS
Apply ONE reusable <DataList> to Sales, Returns, Due Collection, Customers, Products, Stock,
Purchases, Vendors, and the Money entries list. Each must have:
- search box (/ focuses it) · filter chips + "More filters" drawer with the filters the spec lists
  for that module · date presets (Today, Yesterday, This week, This month, Custom range)
- saved views (dropdown: default + 2 saved) · column chooser · sortable headers
- pagination 25/50/100 with "1–25 of 65" · a TOTALS FOOTER row for every money and qty column
- row keyboard: ↑↓ and J/K move, Enter opens, Space/X selects; a bulk bar appears on selection
  with the module's bulk actions
- Export (Excel, CSV, PDF) and Print · empty state with illustration + CTA · skeleton loading
- right-click menu on rows (keep the current ones) and Shift+F10 opens it for the SELECTED row
Sales list additionally: columns Discount, VAT, Branch, Register; filters cashier, payment method,
sync flag, has return, courier; customer column shows name + formatted no. "1000 0249", never
"Customer #10000249".

PART 3 — DASHBOARD AND SETTINGS
Dashboard (G D):
- period selector (Today / Yesterday / 7 days / 30 days / This month / Custom) + branch selector,
  both recompute every widget from mock data
- 9 KPIs with deltas: Sales, Gross profit, Transactions, Avg basket, Dues outstanding,
  Collections today, Expenses, Stock value, Low-stock count
- charts (inline SVG, no empty axes): sales trend line, hourly heat-map, payment-method mix,
  branch comparison bars, sales by district
- lists: top products, top customers, low-stock with "Reorder", recent sales, dues to chase with
  "Send reminder"
- every widget has a right-click menu: Refresh, Change period ▸, Open report, Export
- first-run checklist card (add branch, add products, add users, connect printer)
Settings (G ,):
- FULL WIDTH (remove the centred max-width). Left sub-nav with all 15 sections from the spec:
  Business profile, Branches, Registers & devices (QR pairing), Tax/VAT, Payment methods,
  Receipt & printers (test print), Invoice numbering, Customer PIN policy, Membership card design,
  Messaging providers, Returns policy, Notifications, Appearance, Language, Keyboard shortcuts,
  Data import/export. Each section has real fields and a Save (Ctrl+S) with a dirty-state bar.

PART 4 — MISSING AND THIN SCREENS
- AUTH: Login (username/phone, password show/hide, remember device, lockout after 5 tries) →
  branch picker; Forgot password; Switch user PIN; My account (profile, change password, login
  history, active sessions with "Sign out other devices"). Log out returns to Login.
- VENDOR PROFILE: info, purchases, payments, returns, statement ledger, Pay vendor modal with
  allocation to bills. Opened from the vendors list, palette "@", and purchase detail.
- PRODUCT DETAIL: reachable from the products list (Enter / click), palette and POS tile
  right-click. Stock heat-map per branch × variant, FIFO batches, movement ledger, sales and
  purchase history, price history chart.
- POS VARIANT PICKER: a parent product opens a size × colour grid with stock per cell; product
  tiles show a thumbnail image.
- RETURNS: full list via <DataList>, the return wizard (find invoice → lines with returnable qty →
  refund method → receipt) and a return detail page.
- MESSAGING: Composer, Templates library, Automation rules, Message log (<DataList>), Opt-out list
  as tabs, exactly as the spec.
- MEMBERSHIP CARDS: print designer (CR80 / A4 10-up / PDF) with front and back preview and a bulk
  print queue; Report lost → Reissue.
- Admin screens (Users & Roles with permission matrix, Backup, Audit log, Super Admin, Fixed
  assets) must be reachable from the palette and, for Owner, from the sidebar.

PART 5 — NO TOAST-ONLY ACTIONS
- Find every addToast call that stands in for a feature (e.g. "Add brand modal opened",
  "Add Vendor dialog opened", "CSV Import dialog opened", stock movement, transfers, stocktake,
  label print). Replace each with the real modal/drawer/page with fields and a working save that
  updates mock data. Toasts may only CONFIRM a completed action.
- Import dialogs: upload → column mapping → dry-run preview with per-row errors → Import.

PART 6 — TOKENS AND TYPE
- Remove all raw Tailwind palette classes (emerald-, amber-, rose-, red-, green-, blue-, slate-,
  gray-, teal- with a number). Use only the CSS-variable tokens (success/warning/danger/info/
  primary and their -soft variants).
- Money font: font-family "Inter", "Hind Siliguri", sans-serif with font-variant-numeric:
  tabular-nums. The ৳ sign must render correctly in light and dark. Use JetBrains Mono only for
  SKUs, barcodes, invoice/customer numbers and the clock, never for whole amount columns or labels.
- Fix the React vendor paths so the file works offline from docs/mockup (use "../../vendor/…").

SELF-CHECK BEFORE RETURNING (report each as ✓ or ✗)
1. On the POS, open each of F4, F5, F6, Shift+F6, F8, Shift+F8, F9, F11, Ctrl+D, Ctrl+N: Esc
   closes it, backdrop closes it, × closes it, focus returns to the scan box, never two open.
2. No key is shown on two actions anywhere.
3. Palette: "saree" lists sarees; "1000 0017" opens Rahim's profile; "/26000412" opens that sale.
4. As Cashier, G M shows "Not allowed"; as Owner it opens Money.
5. Searching the file for "Shift:" or "Float" in UI text finds nothing.
6. Every list has a totals footer, pagination and Export.
7. Dashboard has no empty chart; Settings has 15 sections and is full width.
8. Zero raw palette classes; ৳ renders in amounts.
```
