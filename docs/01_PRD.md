# 01 — Product Requirements Document (PRD)

> Product name: **not decided**. The code and UI must never hard-code a product name: use `config('app.name')` and, later, the tenant brand (§Epic J). Repo folder: `pos-rewrite`.
> Status: MVP definition v1.1 (2026-10-29).
> Decisions in force:
> - Bangladesh-first, currency **BDT (৳)**, one currency per tenant, UI in **English + Bangla**.
> - Hosted multi-tenant SaaS: one **tenant** (business) has 1..n **branches** (shops).
> - The **POS register is offline-first**: selling never waits on the network.
> - **The whole app is keyboard-first.** Every action works without a mouse, and touch is still fully supported.
> - First live tenant: **DIFFERENT FASHION** (legacy stores 7 and 10).

---

## 1. Problem

Small and medium retail shops in Bangladesh (fashion, sports, electronics, cosmetics, hardware, general stores) run on paper *khata* books or on old server-rendered POS apps (like our legacy CodeIgniter system). Those apps:
1. **Sell slowly.** They take two page loads per sale, are mouse-driven, and have no barcode-first flow.
2. **Stop when the internet stops.** Internet and power drop often in district towns, and every outage halts the counter.
3. **Get the numbers wrong.** Float money, no transactions and hidden DB triggers make stock and profit drift.
4. **Don't know the customer.** There is no stable customer id, no membership, and no idea where customers come from. Dues (*baki*) are hard to chase.
5. **Are unsafe.** MD5 passwords, a public Adminer, edit-in-place invoices, and anyone can buy on someone else's account.

## 2. Target personas

| Persona | Who | Primary job | Success looks like |
|---|---|---|---|
| **Owner** | Owns 1–3 shops, checks on phone | Today's sales, profit, dues and low stock from anywhere. Trust the numbers | Opens the dashboard daily. Month-end P&L matches the cash |
| **Cashier / Salesman** | Behind the counter, peak-hour rush, unreliable internet | Ring up sales in seconds by keyboard and scanner, take cash/bKash/partial payment, print a receipt | A 5-item barcode sale in **< 20 s**. Zero blocked sales during outages |
| **Manager / Stock keeper** | Runs one branch | Receive purchases, fix stock, handle returns, collect dues, issue membership cards | Stock count matches the shelf |
| **Accountant** | Part-time or owner's relative | Expenses/income, cash drawer reconciliation, exports | Closes the day in < 5 min |
| **Member customer** | Shop's regular buyer | Uses a membership card + PIN to buy, including on account | Nobody else can buy on their account |

## 3. MVP scope: user stories

*As a [persona], I want [capability] so that [benefit].* **AC** = acceptance criteria. Stories with a legacy equivalent are marked **(L)** and trace to `00_Legacy_Features.md`.

### Epic A: Account, tenancy & access
- **A1 (L)** As an Owner, I set up my business and branches, and invite staff with roles. *AC:* roles Owner/Manager/Cashier/Accountant/Stock Keeper are seeded. A user only sees their assigned branches. Invitations go by email or as a copyable link/QR (no SMS in MVP).
- **A2 (L)** As any user, I log in with email, username or phone + password. *AC:* rate-limited. Every login and failure is logged with IP and user-agent.
- **A3** As a Cashier, I switch the active staff member on a register with a 4–6 digit **staff PIN**, also offline (see C12).
- **A4** As an Owner, I can require 2FA (TOTP) for Owner/Manager roles.
- **A5 (L)** As an Owner, I edit role permissions through a permission matrix.
- **A6** As a Manager, I **enroll a device as a register** (one active device per register) and can revoke it remotely. A revoked device wipes its local data on its next connection.

### Epic B: Catalog
- **B1 (L)** As a Manager, I create products with: name (EN/BN), SKU, barcode, category, brand, unit, sale price, cost, VAT rate, HS code (optional), reorder level, min sale qty and image. *AC:* SKU and barcode are unique per tenant. A blank barcode is auto-generated (internal EAN-13 prefix `2`). Barcodes may not start with `MC` (reserved for membership cards).
- **B2 (L)** Categories (2-level), brands, units (pcs, kg, m… with a decimal allowed flag).
- **B3** Bulk import/export products (CSV/XLSX) with a dry-run preview and per-row errors.
- **B4** Print barcode labels (A4 sheet and 38×25 mm roll).
- **B5 (L)** **Size × colour variants** ([ADR 0005](adr/0005-variants-and-exchange.md)). A Manager creates a product family (style) with up to 2 options (e.g. Size, Colour), ticks the combinations in a matrix, and gets one product per combination, each with its own SKU, barcode, prices and stock. *AC:* scanning a variant barcode adds that variant; searching a family name opens a size × colour picker with stock per cell; existing standalone products can be grouped into a family; reports roll up by family.

### Epic C: Point of Sale (register): offline-first, keyboard-first
- **C1 (L)** Add items by barcode scan, or by typing name/SKU. *AC:* search runs **locally** in < 50 ms on 20k products, online or offline. Re-scanning increments qty.
- **C2 (L)** Qty, line discount (৳/%) and order discount. *AC:* a discount above the role's cap needs a Manager PIN.
- **C3 (L)** Attach a customer by **scanning the membership card**, typing the **customer number**, or searching phone/name. Create one inline. *AC:* the default is Walk-in. Customer PIN rules: Epic K.
- **C4 (L)** **Split tender** (cash, card, bKash, Nagad, Rocket, bank, customer due/credit, customer advance balance) with change due. *AC:* due/credit/advance needs a member customer verified by PIN and within the credit limit, or a Manager override.
- **C5** Hold and resume carts (up to 20 per register).
- **C6 (L)** Auto-print the receipt (80/58 mm, or an A4 PDF). Reprint. **Share the receipt link** (copy link, QR, WhatsApp share URL).
- **C7** **Today summary** on the register (`F7`): today's sales count, totals per tender and cash taken at this register, computed locally from synced + unsynced sales. *No shifts, opening float, cash count or Z-report* (the legacy app has none; decided 2026-10-30).
- **C8** **Daily cash summary** report (back office) per branch per day: cash received (sales + due collections) − cash refunds − cash expenses from the cash book. Cash paid out of the till is recorded as a cash-book expense (Epic F), not at the register.
- **C9 (L)** A Manager voids a same-day sale with a reason (online only). After the day closes, only a return is allowed.
- **C10 (L)** Back-dated sale (permission-gated, within N days).
- **C11 (L)** Courier name + tracking number on delivery sales.
- **C12** **Offline selling.** When the internet drops, the register keeps selling. *AC:*
  - The catalog, prices, stock snapshot, customers (minimal fields) and settings are cached locally.
  - Sales queue in an outbox and sync automatically when back online. No duplicates (idempotent).
  - Receipt numbers are generated on the device, unique per register.
  - A clear indicator shows online/offline status, the number of unsynced sales, and the last sync time.
  - What works offline: fully paid sales (any tender except due/credit/advance), member attach (PIN verified at sync), hold/resume, Today summary, new basic customer, print and reprint.
  - **Online-only:** voids, returns, **exchanges**, due collection, due/credit sales (unless the tenant enables an offline credit cap), customer PIN set/reset, card printing, purchases and the back office.
- **C13** The register runs in a browser **fully by keyboard**: every control has a shortcut, there are visible key hints, and focus never gets lost after a scan.

### Epic D: Inventory & purchasing
- **D1 (L)** Receive a purchase from a vendor (vendor ref, items, unit cost, discount, shipping, VAT, paid/due). *AC:* FIFO stock batches, last cost updated, all in one DB transaction.
- **D2 (L)** Per-branch stock with value at cost/sale. Low stock uses each product's own reorder level.
- **D3** Stock adjustments (damage, loss, found, opening, correction) with a reason.
- **D4** Stocktake: freeze, count (scan, works on a phone camera), review variance, post.
- **D5** Transfer stock between branches (send → in transit → receive, short-receive with a reason).
- **D6 (L)** Vendors with purchase history, payable balance and payments.

### Epic E: Returns & dues
- **E1 (L)** Sales return by scanning or searching the receipt. *AC:* qty ≤ sold − already returned. Refund by tender or credit to the customer's account. Restock goes to the original cost batch.
- **E2 (L)** Collect a customer's due across multiple invoices in one payment (oldest first auto-allocation, manual override, overpay becomes an advance).
- **E3 (L)** Customer statement (opening, sales, payments, returns, running balance) as PDF or share link.
- **E5 (L)** **Exchange** at the POS (`F11`) or from a sale row ([ADR 0005](adr/0005-variants-and-exchange.md)): scan the receipt, pick the items coming back, add the replacements, settle only the difference. *AC:* one transaction (a return + a new sale linked to each other); the return value is computed by the server; a higher new total is paid with normal tenders, a lower one is refunded or credited to the member; online only.
- **E4** Dues list with aging (0–30/31–60/61–90/90+) and a per-customer "share reminder" (WhatsApp share URL with a prefilled message). Automated SMS reminders are **deferred** (§5).

### Epic F: Money (cash book)
- **F1 (L)** Income/expense entries with a 2-level category (type → head, e.g. Expense → Rent), account, date, note and attachment.
- **F2** Account balances (Cash/Bank/bKash). Sales payments, purchase payments, refunds and expenses post automatically.
- **F3 (L)** Fixed assets register.

### Epic G: Dashboard & reports
- **G1 (L)** Dashboard: sales/profit/transactions/avg basket (today, week, month, with deltas), dues, low stock, 30-day trend, top products/customers, branch comparison, **sales by district**. *AC:* < 1.5 s, and usable at 360 px.
- **G2 (L)** Reports, all filterable by branch/date/user and exportable to **PDF/XLSX/CSV**:
  - Sales: summary · by day · by product/category/brand · by customer (top) · by user · by tender
  - Profit and stock: P&L · stock on hand & valuation · stock movements · low stock · dead/slow stock
  - Purchases, dues and cash: purchases by vendor · customer dues aging · due collections (by user) · vendor payables · daily cash summary · expense by category · returns
  - VAT: VAT collected by rate
  - Customers: lapsed customers · **customers & sales by division/district/upazila** · membership cards issued
  - Sync: offline sync exceptions (price mismatch, stock oversell, unverified member)
- **G3 (L)** Lapsed customers (no purchase in N days), exportable.

### Epic H: Platform
- **H1 (L)** Daily encrypted DB backups to object storage, kept 30 days.
- **H2** Full audit log (business records, permission changes, voids, price changes, adjustments, PIN resets, overrides, impersonation).
- **H3** EN/BN UI per user. Locale-aware numbers and dates (optional Bangla digits).
- **H4 (L)** One-time legacy migration of **DIFFERENT FASHION** (legacy stores 7 and 10 → one tenant, two branches). See 05_Schema §Migration.

### Epic I: Customers, location & membership (NEW)
- **I1 (L+)** Customer profile: name, shop/company name, phone (unique per tenant), alt phone, email, gender (optional), DOB, anniversary, group, credit limit, VAT BIN (for B2B), notes.
- **I2** **Structured Bangladesh location**: Division → District → City corporation / Upazila / Metro thana → Union / Ward / Pourashava → Area (free text with tenant autocomplete, e.g. market or para) → Post code → Address line. *AC:*
  - Every level is a type-ahead combobox in EN and BN.
  - Choosing a deeper level auto-fills its parents (typing "Mirpur" fills Dhaka district and division).
  - Levels below District are optional. The reference data is seeded and global.
- **I3** **Customer number**: a numeric, permanent, tenant-unique id (8 digits: a 7-digit sequence + 1 Luhn check digit, e.g. `1000001` + check digit). *AC:*
  - Assigned automatically when the customer is created online. Offline-created customers get their number at sync.
  - Searchable everywhere: the POS customer field, the `Ctrl+K` palette (`#12345678`) and every customer filter.
  - A mistyped number fails the check digit instantly ("Invalid customer number").
  - Shown on the customer profile, receipts, statements and the card.
- **I4** **Membership cards**: a Manager prints cards for one or many customers. *AC:*
  - Formats: CR80 PVC card printer (85.6 × 54 mm, front + back), or an A4 sheet with 10 cards and crop marks, or a PDF.
  - Front: tenant logo and name, customer name, customer number, group/tier, "member since", and a Code128 barcode + QR encoding `MC{customer_no}{issue:02}`.
  - Back: terms text, branch phone, and "PIN required for every purchase".
  - Each card has an issue number. Reporting a card **lost** blocks it, and reissuing creates issue +1.
  - Print history is tracked, and a reprint of the same issue is logged.
- **I5** **Customer PIN**: a 4–6 digit secret that the customer sets and enters on every purchase (Epic K).

### Epic K: Customer PIN rules (NEW)
- **K1** The PIN is set by the customer at the counter (entered twice on a PIN pad dialog; the screen is turned to the customer). Weak PINs (`0000`, `1234`, repeated or sequential digits, the last digits of their phone) are rejected.
- **K2** Tenant setting `customer_pin_policy`: **`always`** (default, as requested) · `credit_only` (only for due/credit/advance tenders) · `off`. Under `always`, **attaching a member to any sale requires their PIN**, whether attached by card scan, number or phone.
- **K3** 5 wrong PINs lock the customer's PIN for 30 min (a Manager can unlock). Every failure is logged.
- **K4** A Manager can **reset** a PIN after an ID check (reason required, audited). The customer then sets a new PIN.
- **K5** A member without a PIN is prompted to "Set PIN now". Otherwise the sale continues as Walk-in, or by Manager override with a reason (audited).
- **K6** **Offline:** the PIN is captured and encrypted to the server (sealed box). The device can never verify or store it. The sale is verified at sync. A failed verification flags the sale `member_unverified` and alerts the Manager.
- **K7** Staff never see the PIN. It is never shown, logged or exported.

### Epic J: White-label branding (NEW, **low priority, post-MVP**)
- **J1** Tenant branding: display name, logo, primary colour (auto contrast-checked), receipt and card templates. Later: custom domain.
- **J2** MVP requirement only: no hard-coded product name or brand colour anywhere, so J1 can be added without refactoring.

### Epic V: VAT (standard fields)
- **V1** Tenant VAT BIN and registered address. Customer BIN (optional). Product VAT rate and optional HS code.
- **V2** VAT inclusive/exclusive (tenant setting). Each invoice line stores its rate and amount. The invoice shows a VAT summary by rate, the seller BIN and the buyer BIN (if any).
- **V3** "VAT collected by rate" report. Formal NBR Mushak form generation is a **non-goal**.

## 4. Success metrics

| Metric | Target (90 days post-launch) | How measured |
|---|---|---|
| 5-line barcode sale time | p50 < 20 s, p95 < 35 s | client event `sale.completed.duration_ms` |
| POS local search latency | p95 < 50 ms (20k products) | client RUM |
| Sales blocked by connectivity | **0** | outbox telemetry: sales created offline vs failed |
| Offline sync success | ≥ 99.9 % of queued sales synced within 5 min of reconnect | sync logs |
| Duplicate sales from retries | 0 | unique `client_uuid` audit |
| Stock accuracy at stocktake | ≥ 98 % of SKUs match | stocktake variance |
| Data integrity | 0 violations | nightly `integrity:check` |
| Member identification | ≥ 50 % of sales attached to a numbered member within 90 days | sales with member / total |
| Customers with district set | ≥ 80 % of new customers | profile completeness |
| Owner engagement | ≥ 60 % of owners open the dashboard ≥ 5 days/week | analytics |
| Migration fidelity | 100 % of DIFFERENT FASHION invoices imported, totals reconciled ±৳0.01 | reconciliation report |

## 5. Out of scope (non-goals) for MVP

Do **not** build, scaffold or "prepare" these beyond what the schema already notes.

1. **SMS of any kind** (receipts, reminders, OTP, invites). The provider and adapter will be decided later. MVP uses email, copyable links, QR and WhatsApp *share URLs* (plain `https://wa.me/?text=` links, no API).
2. **Payment gateway / card terminal integration** (bKash/Nagad/SSLCommerz APIs). Tenders are recorded manually with a reference.
3. ~~Product variants matrix~~ → **in v1** (B5, decided 2026-10-30). Still out: more than 2 options per family, per-variant images, variant-level price rules.
4. Full **double-entry GL**, NBR **Mushak** form generation/filing, bank reconciliation.
5. E-commerce, online orders, marketplace sync, a customer self-service portal or app.
6. Loyalty points, stored-value/prepaid card top-ups, gift cards, coupons, promotion engine. (The membership card is an **identity + authorization** card in v1. Advance balance comes only from overpayments and returns.)
7. Quotations / sales orders / layaway, purchase orders (PO → GRN), purchase returns (reconfirmed out of v1 on 2026-10-30).
8. Restaurant, appointments, serial/IMEI tracking, expiry-driven FEFO.
9. Native mobile apps. The responsive web app and the installable PWA are the only clients.
10. HR/payroll, commissions.
11. Multi-currency within a tenant. Languages beyond EN/BN.
12. Public third-party REST API (internal + device sync APIs only).
13. SaaS subscription billing. Tenants are provisioned by a platform admin.
14. **Offline back office.** Only the POS register works offline.
15. White-label (Epic J) beyond the "no hard-coding" rule, until MVP ships.
16. The legacy runtime menu builder and the half-built customer-order module.
17. Migrating legacy stores other than 7 and 10.
