# Final Gemini mockup prompt (v2: full scope)

This file supersedes `gemini_prompt.md` and `gemini_followup_prompt.md`. Paste everything inside the fence into Gemini with **Canvas** on.

```text
ROLE
You are a principal product designer + senior front-end engineer. Build a complete, high-fidelity, CLICKABLE mockup of a modern retail Point-of-Sale + back-office system for shops in Bangladesh. It replaces an old PHP POS, so EVERY legacy feature listed below must be present, upgraded with the new features. Benchmark: Shopify POS, Square, Lightspeed, Linear, Raycast. It must feel like a premium DESKTOP APP: dense, fast, keyboard-first, right-click menus everywhere, works offline at the till.

DELIVERY RULES (important)
- Build ONE self-contained HTML file in Canvas: React 18 (ESM CDN), Tailwind CDN, lucide icons, fonts Inter + Hind Siliguri + JetBrains Mono. All data is mocked in JS, with realistic volume (≥ 40 products with size/colour variants, ≥ 30 customers, ≥ 60 sales, 10 vendors, 25 purchases).
- NO screen may be blank or a placeholder. NO "toast-only" features: every action listed opens a real modal/drawer/page with fields. If you run out of output space, stop at a module boundary, write "▶ NEXT: <module>", and when I say "continue", EXTEND the same file. Never restart it or drop existing parts.
- Every route must be reachable from the sidebar, the command palette (Ctrl+K) AND its shortcut. All shortcuts listed must actually work.
- Colours come only from the CSS-variable tokens below (no raw emerald-/rose-/amber- classes). Mode, scheme and density switch live.
- Desktop first at 1440×900. Also usable at 768 px and 390 px (the sidebar becomes a drawer, tables become cards).

PRODUCT CONTEXT
- The product name is NOT decided: show a neutral logo mark + "Retail POS". The tenant is "DIFFERENT FASHION" (clothing), with branches "Different Fashion - Main" (code DF1) and "Different Fashion - 2" (DF2). Items: Panjabi, Saree, Three-piece, T-shirt, Polo, Jeans, Shirt, Kurti, Hijab, Shoes, Belt, Kids wear. Sizes S–XXL and 28–40, several colours.
- Currency BDT: "৳ 1,23,450.00" (South Asian grouping, tabular numbers, right-aligned). Mixed Bangla/English names. An EN/বাং toggle translates the navigation, buttons, POS and receipts.
- Roles: Super Admin (platform), Owner, Manager, Cashier, Accountant, Stock Keeper. The current user is "Sumi (Cashier)". The avatar menu has "View as role" to demo permission-trimmed menus.
- Offline is a normal state (amber, never red). Offline: the POS keeps selling. Back-office pages show "Needs internet → Go to POS (F1)".
- Invoice no. DF1-R1-26000412. Customer no. = 8 digits with a Luhn check digit, shown as "1000 0017". Card barcode "MC1000001701". Standard VAT 15 %, shop BIN shown on receipts.

DESIGN TOKENS (light / dark)
- background #F8FAFC/#0B1120 · surface #FFFFFF/#111827 · surface-raised #FFFFFF/#1F2937 · surface-sunken #F1F5F9/#0F172A
- foreground #0F172A/#F1F5F9 · muted-foreground #475569/#94A3B8 · border #E2E8F0/#1E293B · input #CBD5E1/#334155
- success #15803D/#4ADE80 (soft #DCFCE7/#052E16) · warning #B45309/#FBBF24 (soft #FEF3C7/#422006) · danger #B91C1C/#F87171 (soft #FEE2E2/#450A0A) · info #1D4ED8/#60A5FA · accent #D97706/#FBBF24
- Schemes (primary/hover/soft; light | dark):
  Teal (default) #0F766E/#115E59/#CCFBF1 | #14B8A6/#2DD4BF/#042F2E
  Indigo #4338CA/#3730A3/#E0E7FF | #818CF8/#A5B4FC/#1E1B4B
  Violet #6D28D9/#5B21B6/#EDE9FE | #A78BFA/#C4B5FD/#2E1065
  Rose #BE123C/#9F1239/#FFE4E6 | #FB7185/#FDA4AF/#4C0519
  Ocean #0369A1/#075985/#E0F2FE | #38BDF8/#7DD3FC/#082F49
  Graphite #334155/#1E293B/#E2E8F0 | #CBD5E1/#E2E8F0/#1E293B
- Radius 6/10/14. 4 px grid. Focus ring 2 px primary + 2 px offset. Type 12/14/16/18/20/24/30 + 44 bold display (POS total). Bangla line-height ≥ 1.6.
- Density: Comfortable (44 px rows) / Compact (32 px). POS buttons ≥ 56 px always.

APP SHELL (every page)
1. TOP BAR 52 px:
   - logo + tenant
   - branch switcher
   - GLOBAL SEARCH "Search anything… Ctrl K"
   - sync pill (Online / Offline / 3 unsynced / Sync error) → Sync center popover (queue, last sync, Retry)
   - register pill "R1 · 12 sales today" → Today summary, Sync center (no shifts: decided 2026-10-30)
   - notifications bell, badge 4: tabs All/Alerts/Messages/Exports. Items include low stock, flagged sync, wrong PIN, export ready, transfer incoming, due reminder delivered. Unread dots, Mark all read
   - LIVE CLOCK ticking every second + date
   - EN/বাং
   - theme popover: mode, 6 scheme swatches with live preview, density
   - avatar menu: Profile, My login history, Switch user (PIN), View as role, Shortcuts (?), Lock (Ctrl+L), Log out
2. SIDEBAR, collapsible (Ctrl+B), grouped, with muted mono key hints:
   - SELL: POS (F1), Sales (G S), Returns (G T), Due Collection (G U)
   - CUSTOMERS: Customers (G C), Membership Cards, Messaging (G W)
   - CATALOG & STOCK: Products (G I), Stock (G K), Purchases (G P), Vendors (G V)
   - MONEY: Accounts & Expenses (G M), Fixed Assets
   - INSIGHTS: Dashboard (G D), Reports (G R)
   - ADMIN: Users & Roles, Settings (G ,), Backup, Audit Log, Super Admin (visible only for that role)
3. STATUS BAR 28 px: Branch · User · Printer ✓ · Scanner ✓ · Sync ✓ 2m ago · Local DB 12 MB · v1.0.0
4. GLOBAL SEARCH / COMMAND PALETTE (Ctrl+K):
   - real fuzzy search over mock data, grouped: Pages, Actions, Products (name/SKU/barcode), Customers (customer no., name, phone, card scan), Sales (invoice no.), Purchases, Vendors
   - prefixes: "#" customer no., ">" actions, "/" invoices, "@" vendors
   - arrows + Enter open the result. Recent searches are shown
5. RIGHT-CLICK MENUS on every table row, product tile, cart line, customer chip and dashboard widget:
   - icons, shortcut hints, separators, a submenu, destructive items last in red
   - Shift+F10 opens the menu for the SELECTED row
6. EVERY LIST PAGE follows the same pattern:
   - search box (/ focuses it), filter chips + "More filters" drawer, saved views, column chooser, sort
   - date-range presets (Today, Yesterday, This week, This month, Custom)
   - pagination with a total count, totals footer row
   - keyboard row navigation (↑↓ / J K, Enter open, Space select, X bulk select)
   - bulk action bar, Export (Excel/CSV/PDF), Print, empty state, skeleton loading
7. "?" opens the shortcut overlay. Primary buttons show <kbd> hints. Toasts appear bottom-right.

MODULES & SCREENS (build ALL)

1) AUTH
- Login (username/phone + password, remember device, show/hide password, lockout message after failed attempts), then branch picker.
- Forgot password (email link).
- Quick switch-user PIN screen for cashiers. Lock screen.
- My account: profile, change password, my login history (time, IP, device, browser, success/failed), active sessions with "Sign out other devices".

2) POS REGISTER (default screen, the most polished)
- Left 60 %:
  - always-focused scan/search box (F2): barcode, SKU, name EN/BN
  - category chips (Alt+1..9)
  - product grid with variant chips (size/colour). Selecting a parent opens a quick variant picker grid (size × colour with stock per cell)
  - stock badges: low = amber "3 left", out = dimmed red "Out"
  - arrows + Enter add to the cart
- Right 40 %, the cart:
  - customer chip "#1000 0017 · রহিম উদ্দিন · Retail · ✓ PIN verified · Due ৳1,200" (right-click: profile, purchase history, collect due, remove)
  - lines with qty steppers, line discount (struck-through original), salesman tag, note
  - Subtotal, Discount, VAT 15 %, big TOTAL
  - buttons: Hold F8, Held list Shift+F8, Discount F6 (line/order, % or ৳, reason, manager PIN above a limit), Today summary F7, Qty F3 (inline editor), New customer Ctrl+N, Price check F5, Return/Exchange F11, PAY F9
- Key-hints bar at the bottom.
- An Offline toggle shows the amber banner "Offline — sales saved on this device, will sync (3 waiting)". Due/Credit is disabled offline ("Needs internet").
- CUSTOMER ATTACH (F4):
  - search by card scan, customer no., phone or name. The results filter live
  - a wrong check digit shows "Invalid customer number"
  - an inline "+ New customer" quick form (name, phone, district, area)
  - each result shows: number, name, masked phone, group, due, card status, last visit
- CUSTOMER PIN PAD (full-screen, high-contrast):
  - "Please enter your PIN / আপনার পিন দিন", 6 masked dots, a big keypad, physical keys work
  - states: success, wrong "2 attempts left" (shake), locked "30 min", offline "will be verified when online"
  - a "No PIN" variant: Set PIN now / Continue as walk-in / Manager override (manager PIN + reason)
- PAYMENT (F9):
  - split tender: Cash, Card, bKash, Nagad, Rocket, Bank, Due (Alt+C/K/B/N/R/A/D)
  - quick cash ৳500/1,000/2,000/Exact, change in green, Complete F10
- SUCCESS screen:
  - 80 mm receipt preview: logo, invoice no., items, VAT summary, BIN, customer no., points/due, QR, barcode
  - buttons: Print (Ctrl+P), A4 invoice, Share link, WhatsApp, New sale (Enter)
- RETURN / EXCHANGE inside the POS (F11): scan the invoice, pick lines, then refund or exchange for new items, with the difference paid or refunded.

3) SALES (G S)
- List:
  - SEARCH by invoice no., customer no./name/phone, product, amount
  - filters: date range, branch, register, cashier, payment status (Paid/Partial/Due/Void), payment method, sync flag, has return, courier
  - columns: invoice, date/time, customer, items, total, discount, VAT, paid, due, status, cashier, sync badge
  - totals footer
- Sale detail page:
  - header with all metadata and a back-dated badge
  - lines with batch/cost (Owner only) and profit
  - payments, returns, timeline/audit
  - courier name + tracking no.
  - an amber "Synced with flag: price mismatch" alert with Accept/Adjust
  - actions: Reprint 80mm, A4 invoice preview, PDF, Share link, WhatsApp, Return, Collect due, Void (same day, manager PIN + reason)
- Rule shown in the UI: completed sales can't be edited, only voided or returned. Drafts and held sales are editable.

4) RETURNS (G T)
- Returns list with search and filters.
- New return wizard:
  1. find the invoice (scan/search)
  2. pick lines, qty ≤ sold − already returned, reason, restock yes/no per line
  3. refund method (cash/MFS/credit to customer account)
  4. confirm, then the return receipt
- Return detail page.

5) DUE COLLECTION (G U)
- Dues list with aging buckets 0–30/31–60/61–90/90+, search, and filters (district, group, amount, last reminder).
- Collect payment modal: pick the customer, see their open invoices, auto-allocate oldest-first (editable), pick the tender, see the remaining balance, print a money receipt.
- Per row: "Send reminder" (WhatsApp / SMS) and "Call" (tel link).
- Collection history by user and date.

6) CUSTOMERS (G C)
- List:
  - SEARCH by customer no. (with check-digit validation), name EN/BN, phone, card scan, shop name
  - filters: district/upazila, group, has due, has card, PIN status, segment (New / Regular / VIP / Lapsed 90 days / Never purchased), birthday this month
  - columns: customer no., name, phone, district, group, total spent, due, card, PIN, last purchase
  - bulk: Print membership cards, Send WhatsApp/SMS, Change group, Export
  - Import customers (CSV/XLSX with a dry-run preview and per-row errors)
  - a "Merge duplicates" tool
- Customer form:
  - identity: name EN/BN, phone with duplicate warning, alt phone, shop name, email, gender, DOB, anniversary, group, credit limit, VAT BIN, notes, tags
  - LOCATION as cascading type-ahead comboboxes: Division → District → City/Upazila/Thana (type badge) → Union/Ward/Pourashava → Area → Post code → Address line. Typing "Mirpur" auto-fills District + Division with a flash. Options show "English · বাংলা"
  - membership: customer no. (read-only + copy), cards list, PIN status, Set/Reset PIN
  - Ctrl+S saves
- CUSTOMER 360 PROFILE:
  - header: big customer no., name, location breadcrumb, group, member since, lifetime spend, visits, avg basket, due, credit limit used (bar)
  - tabs:
    - Overview: spend trend, favourite categories/sizes, last 5 purchases
    - Purchase History: every invoice with a line-level expand, search within it, and filters
    - Items Bought: product-level history
    - Payments
    - Statement: running-balance ledger, PDF
    - Returns
    - Cards
    - Messages: WhatsApp/SMS log
    - Activity
  - actions: New sale, Collect due, Send reminder, Print card, Statement PDF, Edit
  - Typing a customer no. anywhere (palette, POS F4, customer search) jumps straight to this history.

7) MEMBERSHIP CARDS
- List of issued cards (number, customer, issue no., status Active/Lost/Replaced, issued by, date) with search.
- Print designer:
  - layouts: CR80 card printer (85.6×54 mm), A4 10-up with crop marks, PDF
  - front: logo, "DIFFERENT FASHION", MEMBER label, name, "1000 0017", group chip, member since, Code128 barcode "MC1000001701", QR
  - back: terms, branch phones, "PIN required for every purchase / প্রতিবার কেনাকাটায় পিন প্রয়োজন", issue 01, signature line
- Bulk print queue. Report lost → Reissue (Issue 02).

8) MESSAGING (G W): WhatsApp + SMS
- Composer:
  - channel: WhatsApp (works now via click-to-chat wa.me link with a prefilled message) or SMS (badge "Provider not connected — configure in Settings"; still show the UI and the cost estimate)
  - recipients: one customer, a filtered segment, or all customers with due > X
  - template picker
  - live preview in EN/BN with variables {name} {customer_no} {due} {last_invoice} {shop} {branch_phone}
  - character/segment counter for SMS
- Templates library: Due reminder (soft/firm), Payment received thanks, Receipt link, Birthday wish, New arrivals/offer, PIN set reminder.
- Automation rules (toggle + schedule): "Remind dues older than 30 days every Saturday 11 AM", "Send receipt link after sale", "Birthday wish 10 AM". Opt-out list.
- Message log: date, customer, channel, template, status (Queued/Sent/Opened link/Failed), sent by. Search and filters.

9) PRODUCTS / CATALOG (G I)
- Products list:
  - SEARCH by name EN/BN, SKU, barcode (scan)
  - filters: category, brand, vendor, status, stock status (in/low/out), price range, has variants, no barcode
  - columns: image, name, SKU, category, brand, price, cost, margin %, stock per branch, total stock, status
  - inline price edit
  - bulk: change price (% or ৳), change category, print labels, activate/deactivate, export
- PRODUCT FORM (tabs):
  - General: name EN/BN, category, brand, vendor, unit (pcs/m/kg, decimal qty), description, images (drag-drop, multiple), tags
  - Pricing: MRP/sale price, cost, auto margin, VAT rate, wholesale price by customer group, min sale qty
  - Variants: size × colour matrix generator, each cell with its own SKU, barcode (Generate), price override, opening stock per branch
  - Inventory: reorder level, reorder qty, preferred vendor, stock-out alert per branch
  - Status
  - Ctrl+S saves, "Save & add another"
- PRODUCT DETAIL page:
  - stock per branch and variant (heat-map grid)
  - FIFO batches with cost and remaining
  - movement ledger
  - sales history and purchase history
  - price history, charts, profit
- Categories (tree, drag to reorder), Brands, Units, Attributes (Size/Colour): CRUD with search.
- Import products (CSV/XLSX template, dry-run, row errors), Export.
- BARCODE LABEL designer: label size presets, fields (name, price, size, barcode), sheet preview, qty per variant.

10) STOCK / INVENTORY (G K)
- Stock overview:
  - per product/variant per branch, stock value at cost and at retail
  - search and filters (branch, category, low/out/overstock, dead stock 90 days)
- STOCK CHECK quick lookup: scan → stock in every branch + batches + incoming transfers.
- Low stock & REORDER suggestions (based on reorder level + 30-day sales velocity) → "Create purchase" pre-filled.
- Movement ledger:
  - columns: date, product, type (sale, return, purchase, purchase return, transfer in/out, adjustment, stocktake), qty ±, balance, ref, user
  - filters and search
- Adjustments: reason (damaged, lost, theft, gift, correction), approval by a manager.
- TRANSFERS between branches: create, then in transit, then receive (with a discrepancy check). A list with status.
- STOCKTAKE: create a count (full/category), count sheet with scan-to-count, blind count option, variance report, post adjustments.
- Valuation: FIFO.

11) PURCHASES (G P)
- List:
  - SEARCH by purchase no. (ST-…), vendor, vendor bill no., product
  - filters: date, branch, vendor, payment status
  - totals
- New purchase:
  - vendor (quick-add), vendor bill no., date, branch
  - lines by scan/search with qty, buy price, sale price update option, variant grid entry
  - discount, transport cost, paid now (tender) / due
  - attach bill photo
  - on save: "Stock +120 added, 8 batches created, 3 sale prices updated"
- Purchase detail (lines, batches, payments, returns), print.
- Purchase RETURN to vendor.
- Purchase orders (draft → sent → received partially/fully) as an upgrade.

12) VENDORS (G V)
- List: search, payable balance, last purchase.
- Vendor profile: info, purchases, payments, returns, statement ledger.
- Pay vendor modal: allocate to bills.

13) MONEY (G M)
- Accounts: Cash drawer per register, Main cash, Bank, bKash/Nagad, each with its balance. Transfer between accounts.
- Expense & income entries: head, amount, date, account, note, attachment, created by. Search, filters, totals.
- Heads grouped by type: Expense, Salary, Withdraw, Income, Room rent.
- Cash book / day book: opening, in, out, closing per day.
- Daily cash summary report per branch per day (cash received − refunds − cash expenses). No shifts, X/Z reports or cash counts (decided 2026-10-30).
- Fixed assets: list + form (name, purchase date, price, invoice image, status, depreciation note).

14) DASHBOARD (G D)
- Period selector + branch selector.
- KPIs with deltas: Sales, Gross profit, Transactions, Avg basket, Dues outstanding, Collections today, Expenses, Stock value, Low-stock count.
- Charts: sales trend, hourly heat-map, payment-method mix, branch comparison, sales by district.
- Lists: top products, top customers, low-stock list (reorder button), recent sales, dues to chase (Send reminder).
- Every widget has a right-click menu: Refresh, Change period ▸, Open report, Export.

15) REPORTS (G R)
- A report hub with search and favourites, grouped. Every report has a filter bar (date, branch, user, category…), table + chart, totals, Export PDF/Excel/CSV, Print, Schedule by email.
- Reports:
  - Sales summary (by day/product/category/brand/cashier/payment method/hour)
  - Daily sales
  - Sales by customer & top customers
  - Product sales & profit
  - Returns
  - Stock value
  - Stock count snapshot
  - Product list
  - Sale price list
  - Low stock / reorder
  - Dead stock
  - Movement report
  - Purchase report
  - Vendor payables
  - Profit & Loss statement (sales − COGS − expenses)
  - Expense report
  - Customer due aging
  - Due collection (by user)
  - VAT summary (output VAT by rate, BIN-wise B2B sales)
  - Shift/Z history
  - Cashier performance
  - Messaging report

16) USERS & ROLES (admin)
- Users list (search, role, branch, status Active/Inactive/Pending, last login).
- User form: name, username, phone, email, role, branches allowed, POS PIN, discount limit, status.
- Actions: reset password, force logout, deactivate.
- Roles & PERMISSIONS MATRIX (roles × grouped permissions with checkboxes, "copy role"). Role presets: Owner, Manager, Cashier, Accountant, Stock Keeper.
- Login logs: all users, IP, device, success/failed, with filters.

17) SETTINGS (G ,), left sub-nav
- Business profile: name, BIN, logo, address, phones.
- Branches: CRUD, code, address, phone, receipt header.
- Registers & devices: pair a device by QR code, revoke, last seen, local DB size.
- Tax/VAT.
- Payment methods.
- Receipt & printers: 80/58 mm/A4, header/footer, test print.
- Invoice numbering.
- Customer PIN policy: Always / Credit only / Off.
- Membership card design.
- Messaging providers: WhatsApp (click-to-chat on; Business API "coming soon"), SMS (provider not connected; fields for sender ID/API key, disabled).
- Notifications preferences.
- Appearance.
- Language.
- Keyboard shortcuts.
- Data import/export center.

18) BACKUP (admin)
- Status card: last backup time, size, location (encrypted cloud storage), next scheduled.
- Backup history list with download.
- "Backup now" with a progress bar.
- Schedule settings: daily time, keep 30 days.
- Restore, for Super Admin only: typed confirmation, then a warning.
- Export my data (ZIP of CSVs).

19) AUDIT LOG
- Who / what / when / before→after diff.
- Filters: user, module, action (void, price change, adjustment, PIN reset, override, permission change, login).
- Search. Export.

20) SUPER ADMIN console (platform role only)
- Tenants (shops) list: status, branches, users, devices, last activity, storage.
- Create tenant wizard. Impersonate (audited).
- System health: queue, sync errors, failed jobs.
- Global backup status. Announcements banner.

21) STATES
- Empty states with an illustration + CTA on every list.
- Skeleton loading.
- Offline back-office page.
- 403 "You don't have permission" and 404 pages.
- Error toast with Retry.
- First-run setup checklist on the dashboard: add branch, add products, add users, connect printer.

QUALITY BAR
- Fully keyboard-operable. After any POS action, focus returns to the scan box.
- Status is never shown by colour alone (icon + text). Tabular numbers. Money right-aligned.
- Micro-interactions 150 ms. A new cart line flashes. The sync icon pulses while syncing.
- Realistic data only, no lorem.
- Calm, premium, information-dense. A cashier can use it for 10 hours. An owner finds any customer, sale or product in 2 keystrokes.
- Start on the POS Register. Build order: shell → POS → Sales/Returns/Due → Customers/Cards/Messaging → Products/Stock/Purchases/Vendors → Money → Dashboard/Reports → Users/Settings/Backup/Audit/Super Admin → States.
```
