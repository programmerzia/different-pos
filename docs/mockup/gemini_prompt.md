# Gemini mockup prompt

Copy everything inside the fence below into Gemini (Canvas / code mode works best).

```text
You are a senior product designer and front-end engineer. Build a high-fidelity, CLICKABLE UI MOCKUP of a next-generation retail Point-of-Sale web app for shops in Bangladesh. It must look and feel like a premium DESKTOP application (think Linear, Raycast, VS Code, Shopify POS): dense, fast, keyboard-first, with right-click menus. It is not a generic admin template.

OUTPUT FORMAT
- One self-contained HTML file: React 18 via ESM CDN, Tailwind CSS via CDN, lucide icons, Google Fonts "Inter" + "Hind Siliguri" + "JetBrains Mono". No backend; all data is mocked in JS.
- All screens are reachable from the sidebar AND by keyboard shortcuts. Every shortcut listed below must actually work in the mockup.
- Use CSS variables for all colours (define the tokens below). Theme mode, colour scheme and density must switch live.
- Desktop first at 1440×900, but it must also render sensibly at 768 px (tablet) and 390 px (phone).

PRODUCT CONTEXT
- The product name is NOT decided: show a neutral placeholder logo mark + "Retail POS". The tenant (shop) is "DIFFERENT FASHION" with branches "Different Fashion - Main" and "Different Fashion - 2". It sells clothing: Panjabi, Saree, Three-piece, T-shirt, Polo, Jeans, Shirt, Kurti, Hijab, Shoes, Belts, Kids wear.
- Currency is BDT. Format money as "৳ 1,23,450.00" (South Asian digit grouping, tabular numbers, right-aligned). Show some Bangla text in names, e.g. "রহিম উদ্দিন", "সুতি পাঞ্জাবি". Include an EN/বাং toggle that swaps at least the navigation labels and the POS buttons to Bangla.
- Users: Owner, Manager, Cashier. The current user is "Sumi (Cashier)", with an avatar menu to switch user by PIN.
- The POS works OFFLINE. Offline is a normal state, shown in amber, never as a red error.

DESIGN TOKENS (light / dark)
- background #F8FAFC / #0B1120 · surface #FFFFFF / #111827 · surface-raised #FFFFFF / #1F2937 · surface-sunken #F1F5F9 / #0F172A
- foreground #0F172A / #F1F5F9 · muted-foreground #475569 / #94A3B8 · border #E2E8F0 / #1E293B · input #CBD5E1 / #334155
- success #15803D / #4ADE80 (soft #DCFCE7 / #052E16) · warning #B45309 / #FBBF24 (soft #FEF3C7 / #422006) · danger #B91C1C / #F87171 (soft #FEE2E2 / #450A0A) · info #1D4ED8 / #60A5FA · accent #D97706 / #FBBF24
- COLOUR SCHEMES (these change only primary / primary-hover / primary-soft; light | dark):
  Teal (default) #0F766E/#115E59/#CCFBF1 | #14B8A6/#2DD4BF/#042F2E
  Indigo #4338CA/#3730A3/#E0E7FF | #818CF8/#A5B4FC/#1E1B4B
  Violet #6D28D9/#5B21B6/#EDE9FE | #A78BFA/#C4B5FD/#2E1065
  Rose #BE123C/#9F1239/#FFE4E6 | #FB7185/#FDA4AF/#4C0519
  Ocean #0369A1/#075985/#E0F2FE | #38BDF8/#7DD3FC/#082F49
  Graphite #334155/#1E293B/#E2E8F0 | #CBD5E1/#E2E8F0/#1E293B
- Radius 6/10/14 px. 4 px spacing grid. Focus ring 2 px primary + 2 px offset, always visible.
- Type scale: 12/14/16/18/20/24/30, plus a 44 px bold "display" size for the POS total. Line-height ≥ 1.6 for Bangla.
- Density: Comfortable (44 px table rows) and Compact (32 px rows, smaller padding). POS buttons stay ≥ 56 px in both.

APP SHELL (on every screen)
1. TOP BAR, 52 px, left to right:
   - Logo + tenant name
   - Branch switcher dropdown
   - A wide search field that opens the command palette ("Search or jump to…" with a Ctrl K hint)
   - Sync pill: cycle through "● Online" / "⚠ Offline" / "⟳ 3 unsynced" / "✕ Sync error". Clicking it opens a "Sync center" popover listing queued sales, last sync time and a Retry button
   - Register pill "R1 · Shift open 9:02 AM · Float ৳5,000"
   - Notifications bell with a red badge "4". The panel has tabs All / Alerts / Exports, with items such as "Low stock: Panjabi Cotton M (3 left)", "Sale DF1-R1-26000412 synced with price mismatch", "Wrong PIN 3× for customer #1000 0017", "Export ready: Sales Oct 2026.xlsx", "Transfer incoming from Branch 2". Unread dots, Mark all read
   - LIVE CLOCK ticking every second "10:42:15 AM", with "Wed, 29 Oct 2026" below in small text
   - EN/বাং toggle
   - Theme button (palette icon) opening a popover: Light / Dark / System segmented control, 6 colour scheme swatches (selected one ringed with a check, live preview on hover), and a Comfortable / Compact density toggle
   - Avatar menu: Profile, Switch user (PIN), Keyboard shortcuts (?), Lock screen (Ctrl+L), Log out
2. LEFT SIDEBAR, collapsible to icons with Ctrl+B. Items, each showing its key hint in muted mono:
   Dashboard (G D), POS Register (F1), Sales (G S), Customers (G C), Membership Cards, Inventory (G I), Purchases (G P), Money (G M), Reports (G R), Settings (G ,)
3. STATUS BAR at the bottom, 28 px, small muted text: "Branch: Main │ User: Sumi │ Printer ✓ │ Scanner ✓ │ Sync ✓ 2m ago │ Local DB 12 MB │ v1.0.0"
4. COMMAND PALETTE (Ctrl+K): grouped results (Pages, Actions, Products, Customers, Invoices). Typing "#10000017" shows the customer. Arrow keys + Enter work.
5. RIGHT-CLICK CONTEXT MENUS (desktop vibe). Implement a custom context menu that replaces the browser one on table rows, product tiles, cart lines, the customer chip and dashboard widgets:
   - icons, labels, right-aligned shortcut hints, separators, at least one submenu, destructive items last in red
   - opens on right-click and on Shift+F10 for the selected row
   - example for a sale row: Open (Enter) · Reprint receipt (Ctrl+P) · Share receipt link · Return items · Collect payment · ── · Void sale (red)
   - example for a customer row: Open profile · New sale for customer · Collect due · Statement · Print membership card · Report card lost · Reset PIN · Copy customer no.
6. "?" opens a keyboard shortcut overlay listing all shortcuts in grouped columns.
7. Every primary button shows its key hint as a small <kbd> chip (e.g. "Pay  F9").

SCREENS TO BUILD (realistic mock data in all of them)
A) POS REGISTER: the hero screen, the most polished one.
   - Left 60 %:
     - a large always-focused scan/search input ("Scan barcode or search…  F2")
     - category chips (Alt+1..9)
     - a product grid of 12+ tiles: image placeholder with initials, name EN + BN, price, stock badge. Low stock shows an amber "3 left". Out of stock is dimmed with a red "Out"
     - arrow keys move the selection, Enter adds to the cart
   - Right 40 %, the cart panel:
     - customer chip at the top, e.g. "#1000 0017 · রহিম উদ্দিন · Retail · ✓ PIN verified · Due ৳1,200"
     - cart lines with qty steppers, line discount shown as a struck-through original price, and the selected line with a 3 px primary left bar
     - Subtotal / Discount / VAT (15 %) / big TOTAL "৳ 4,690.00"
     - buttons: Hold F8, Discount F6, Cash in/out F7, and a huge primary "PAY  F9" button
   - A bottom key-hints bar: F2 Search · F3 Qty · F4 Customer · F6 Discount · F8 Hold · F9 Pay · Del Remove · Esc Esc Clear
   - An "Offline mode" toggle in the mockup that shows the amber offline banner "Offline: sales are saved on this device and will sync automatically (3 waiting)". In offline mode, "Due/Credit" is disabled with the tooltip "Needs internet".
B) CUSTOMER ATTACH (F4):
   - a modal accepting a card scan, a customer number or a phone/name, with a result list (number, name, masked phone ••• 456, group, card status)
   - typing a wrong 8-digit number shows "Invalid customer number (check digit)"
   - after selecting, show the CUSTOMER PIN PAD: a full-screen high-contrast overlay "Please enter your PIN / আপনার পিন দিন", the customer name, 6 masked dots, a big 3×4 keypad, physical digit keys working. Demo states: success (green check), wrong PIN "2 attempts left" (shake), locked "PIN locked for 30 min", and offline "PIN will be verified when online"
   - also show the variant "Customer has no PIN → Set PIN now / Continue as walk-in / Manager override"
C) PAYMENT PANEL (F9):
   - split tender rows: Cash, Card, bKash, Nagad, Rocket, Due. Alt-key hints on each
   - quick cash buttons ৳500 / ৳1,000 / ৳2,000 / Exact
   - a big "Change ৳310.00" in green
   - "Complete  F10"
   - then a success screen with a receipt preview (80 mm thermal style: shop logo, invoice DF1-R1-26000413, items, VAT summary, BIN, customer no., QR code, barcode) and buttons Print (Ctrl+P), Share link, WhatsApp share, New sale (Enter)
D) DASHBOARD:
   - KPI tiles with deltas vs the previous period: Today's sales ৳84,250 ▲12 %, Gross profit, Transactions 63, Avg basket, Dues outstanding, Low stock items
   - a 30-day sales trend chart
   - top products, top customers
   - a branch comparison bar chart
   - "Sales by district" (horizontal bars: Joypurhat, Bogura, Naogaon, Dhaka, Rajshahi…)
   - a low-stock widget
   - every widget has a right-click menu (Refresh, Change period ▸, Open report, Export)
E) CUSTOMERS LIST:
   - a dense data table with keyboard row navigation (↑↓/J K, Enter, Space select)
   - a bulk action bar when rows are selected: "Print membership cards", "Export"
   - columns: Customer No. (mono, "1000 0017"), Name, Phone, District, Group, Balance (red if due), Card status badge, PIN status badge (Set / Not set / Locked), Last purchase
   - filters: district, group, has card, PIN status
   - right-click row menu
F) CUSTOMER FORM (create/edit):
   - Identity section: name, phone with a duplicate warning example, alt phone, shop name, email, gender, DOB, anniversary, group, credit limit, VAT BIN, notes
   - LOCATION section with cascading type-ahead comboboxes in this order:
     Division → District → City / Upazila / Thana (each option shows a type badge) → Union / Ward / Pourashava → Area (free text with suggestions) → Post code → Address line
   - demo the behaviour: typing "Mirpur" in the City/Upazila field auto-fills District "Dhaka" and Division "Dhaka", and the auto-filled fields flash briefly. Options show "English · বাংলা"
   - Membership section: the customer number (read-only, copy button), the card list with status, an Issue/Print card button, PIN status with a Reset PIN button
   - Ctrl+S save hint
G) CUSTOMER PROFILE:
   - header with the big customer number, name, location breadcrumb (Rajshahi › Joypurhat › Kalai), balance, and "Member since"
   - tabs: Overview, Sales, Payments, Statement (running balance table), Cards, Activity
   - actions: New sale, Collect due, Print card, Statement PDF
H) MEMBERSHIP CARD PRINT:
   - choose layout: "CR80 card printer (85.6×54 mm)" / "A4 sheet, 10 cards" / "PDF"
   - live preview of the FRONT and BACK at true aspect ratio:
     - front: tenant logo, "DIFFERENT FASHION", a "MEMBER" label in the scheme colour, the customer name, the big number "1000 0017" in mono, a group chip "Retail", "Member since 10/2026", a Code128 barcode (render a realistic fake one) encoding "MC1000001701", and a QR code
     - back: terms text, branch phones, "PIN required for every purchase / প্রতিবার কেনাকাটায় পিন প্রয়োজন", "Issue 01", signature line
   - the A4 layout shows 10 cards with crop marks
   - include a "Report lost → Reissue (Issue 02)" action
I) SALES LIST + SALE DETAIL:
   - list: invoice number, time, customer, items, total, paid, due, payment status badge (Paid green / Partial amber / Due red / Void struck grey), cashier, a sync badge (cloud-check / cloud-upload / flag)
   - detail: lines, payments, returns, and a "Synced with flag: price mismatch" alert
J) INVENTORY / PRODUCTS: product table with stock per branch, a low-stock filter, right-click (Add to cart, View stock by branch, Movement history, Print label, Edit), and a stock movement drawer.
K) SHIFT CLOSE (Z-report) dialog: denomination counting grid (1000, 500, 200, 100, 50, 20, 10, 5, 2, 1), expected vs counted per tender, a difference highlighted.
L) EMPTY + LOADING STATES: show at least one empty state (illustration + CTA "Add your first product" + "Import CSV"), one skeleton-loading table, and one "You're offline" back-office view with a "Go to POS" button.

INTERACTION QUALITY BAR
- Everything is reachable by keyboard. After any POS action, focus returns to the scan field.
- Micro-interactions: 150 ms hover/press, a newly added cart line flashes primary-soft, a pulsing sync icon while syncing, and toasts at the bottom-right ("Synced 3 sales").
- Status is never shown by colour only: always icon + text.
- Numbers use tabular figures. Money is right-aligned.
- Use realistic data, not lorem ipsum.
- The overall feel is calm, premium, information-dense, with no wasted whitespace. A cashier could use it for 10 hours.

Deliver the complete working file. Start with the POS Register as the default screen.
```
