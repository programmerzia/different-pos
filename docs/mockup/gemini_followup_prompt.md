# Gemini follow-up prompts (paste into the SAME chat, one batch at a time)

## Batch 0: rules (paste first, with batch 1)
```text
Keep the existing file. Do NOT restart it or remove anything. Keep the same shell, tokens, mock data style and shortcuts. Add the following as new screens/modals, wire each into the sidebar AND the keyboard map, and return the COMPLETE updated file. Every new list screen needs: filters bar, keyboard row navigation (↑↓ / J K, Enter opens, Space selects), right-click row menu (also Shift+F10 on the selected row), Export (Excel/PDF) button, empty state. Use only CSS-variable tokens for colours (no emerald-/rose-/amber- classes).

FIX FIRST:
1. The sidebar gets this full order: POS (F1), Dashboard (G D), Sales (G S), Returns (G T), Customers (G C), Membership Cards, Inventory (G I), Purchases (G P), Vendors (G V), Money (G M), Reports (G R), Settings (G ,). "Shift Close" must open the Z-report modal, not a blank page. No route may render a blank page.
2. POS: VAT 15 % is applied and shown as its own line. F6 opens a Discount modal (line or order, % or ৳, reason). F7 opens a Cash in/out modal (amount, reason). Shift+F8 opens a Held sales list (resume / delete). F3 opens an inline qty editor, not the browser prompt().
3. Payment: add the Rocket row. Alt+C/K/B/N/R/D jump to the tender inputs. Due counts toward paid. Due is enabled only when a customer is attached and PIN-verified.
4. Customer attach: the search really filters. A wrong 8-digit number shows "Invalid customer number (check digit)".
5. PIN pad: 6 dots, physical digit keys and Backspace work. Add the states "Offline: PIN will be verified when online" and a "No PIN" variant with "Set PIN now / Continue as walk-in / Manager override (manager PIN)".
6. Customer form:
   - Add alt phone, shop name, email, gender, DOB, anniversary, credit limit, VAT BIN and notes.
   - Show a duplicate-phone warning example.
   - The location section becomes 7 type-ahead comboboxes: Division → District → City/Upazila/Thana (type badge) → Union/Ward/Pourashava → Area → Post code → Address line. Typing "Mirpur" auto-fills District and Division with a flash.
   - REMOVE "Send PIN SMS". Replace it with "Set PIN at counter".
   - Add the cards list with statuses.
7. Customer profile: add tabs Overview / Sales / Payments / Statement / Cards / Activity, and the actions Collect due and Statement PDF.
8. Customers list:
   - filters: district, group, has card, PIN status, and Lapsed (no purchase in 90 days)
   - a Last purchase column
   - a bulk bar when rows are selected: "Print membership cards", "Export"
9. Membership cards: a layout chooser CR80 / A4 10-up with crop marks / PDF, with a preview for each.
```

## Batch 1: Sales, Returns, Due collection
```text
Add:
A) SALE DETAIL page, opened with Enter or a double-click on a sales row:
   - header: invoice no., date, cashier, branch, register, customer chip, payment status, sync badge
   - lines table: qty, unit price, discount, VAT, total, batch/cost (visible to Owner only)
   - payments table and returns table
   - an amber alert "Synced with flag: price mismatch (device ৳1,250, server ৳1,300) — Accept / Adjust"
   - actions: Reprint 80mm, Print A4 invoice (show an A4 preview), Share link, Return items, Collect due, Void (same day only, manager PIN)
   - courier name and tracking no. fields
   - back-dated badge if sale_date ≠ created date
B) SALES LIST:
   - a filter bar: invoice no., customer, status, cashier, date range, branch
   - Due and Discount columns, and a totals footer row
C) RETURNS flow (G T):
   - step 1: scan or type the invoice number
   - step 2: pick lines and qty (validated ≤ sold − already returned), a reason, and a restock checkbox per line
   - step 3: refund method (Cash / bKash / Credit to customer account), then a summary and confirm
   - after confirm: a return receipt
   Also a RETURNS LIST with filters.
D) DUE COLLECTION modal:
   - choose a customer
   - their open invoices with checkboxes and auto-allocation oldest-first
   - tender (Cash/bKash/Nagad/Card), then the remaining balance and a receipt
```

## Batch 2: Catalog, Purchases, Vendors, Inventory
```text
Add:
A) PRODUCT FORM (create/edit):
   - name EN/BN, SKU, barcode (with a Generate button), category, brand, unit (pcs/m/kg, decimal-qty toggle), sale price (MRP), cost (read-only, last buy), VAT rate, min sale qty, reorder level, image upload, status, variants hint (size/colour)
   - Ctrl+S saves
B) CATEGORIES (tree) and BRANDS: simple CRUD lists with inline edit.
C) PURCHASES (G P):
   - a list with filters
   - a NEW PURCHASE screen:
     - vendor picker
     - vendor ref/bill no.
     - date and branch
     - lines (scan or search product, qty, buy price, line total)
     - discount, payment (paid now / due)
     - attach bill photo
   - on save, show "Stock +N added, batches created"
   - a purchase detail page
D) VENDORS (G V): a list with a payable balance column. The vendor profile has tabs: Purchases, Payments, Statement. A "Pay vendor" modal.
E) INVENTORY improvements:
   - a low-stock filter and a "Stock check" quick lookup (scan → stock per branch + batches)
   - a STOCK MOVEMENT DRAWER: a ledger with type sale/purchase/return/transfer/adjust/stocktake, qty ±, balance, ref, user
   - a BATCHES view: FIFO batches with cost and remaining
   - TRANSFER between branches: create → in transit → receive
   - STOCKTAKE: count sheet, scan to count, variance, post adjustments
   - a BARCODE LABEL print preview (sheet of labels)
```

## Batch 3: Money, Reports
```text
Add:
A) MONEY (G M):
   - an Expense/Income ENTRIES list and new-entry modal: head, amount, date, account (Cash drawer / Bank / bKash), note, attachment
   - HEADS management grouped by type: Expense, Salary, Withdraw, Income, Rent
   - FIXED ASSETS: list and form (name, purchase date, price, invoice image, status)
   - cash book / day book view with an opening balance and a closing balance
B) REPORTS (G R): a report hub with cards grouped by Sales, Inventory, Customers, Finance. Each report has a filter bar (date range, branch, user), a table, a chart where useful, a totals footer and Export PDF / Excel buttons. Build these reports:
   1. Sales summary (by day/product/category/cashier/payment method)
   2. Daily sales
   3. Top customers
   4. Stock value (batch × cost)
   5. Stock count snapshot
   6. Product list
   7. Sale price list
   8. Profit & Loss (sales − COGS − expenses, as a statement layout)
   9. Customer due (aging 0–30/31–60/61–90/90+)
   10. Due collection (by user)
   11. VAT report (Mushak-style summary)
   12. Shift / Z-report history
```

## Batch 4: Settings, Users, Auth
```text
Add:
A) LOGIN screen: username/phone + password, "Remember this device", branch select after login, failed-attempt lockout message. Also a PIN-based quick switch-user screen for cashiers.
B) SETTINGS (G ,) with a left sub-nav:
   - Business (name, BIN, logo, receipt header/footer)
   - Branches (CRUD, address, phone, branch code)
   - Registers & devices (register codes, paired devices, revoke)
   - Users (list, invite, status, branches)
   - Roles & permissions matrix (roles × permissions with checkboxes)
   - Customer PIN policy (Always / Credit only / Off)
   - Tax/VAT
   - Payment methods
   - Receipt & printers (80mm/58mm/A4, test print)
   - Membership card design
   - Backup (schedule, last backup, download)
   - Appearance (same as the theme popover)
   - Keyboard shortcuts list
C) My account: change password, my login history (time, IP, device, success/failed).
D) The OFFLINE back-office state: when offline, any non-POS page shows "You're offline. Back office needs internet. → Go to POS (F1)".
```
