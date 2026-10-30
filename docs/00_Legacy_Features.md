# 00 — Legacy System Feature Inventory

> Source: `/home/ziaur-rahman/WorkGround/Vivacom/different` (git `8938d78`), plus the DB dump `ims_backup_on_29-10-26 05_46.zip` (`ims_backup.sql`, 2026-10-29).
> Purpose: the full list of what the old POS does, so the rewrite keeps every capability that matters (**parity**) and drops the rest on purpose.
> Status legend: **KEEP** = must exist in v1 · **IMPROVE** = keep, but redesigned · **DROP** = not carried over (reason given).

---

## 1. Technical snapshot

| Aspect | Legacy |
|---|---|
| Framework | CodeIgniter 3 (custom `Bindu_Controller` / `Bindu_Model` / `Bindu_Loader` core) |
| PHP | Written for PHP 5/7. It breaks on PHP 8: `error_log` shows mPDF curly-brace offset fatals and `ini_set()` disabled |
| UI | Server-rendered PHP views, Bootstrap 3, jQuery 1.10/2.0/3.2 (three copies loaded), Chosen, DataTables, Morris charts, bootstrap-datepicker |
| PDF / Excel | mPDF 5.7 (bundled), a custom `to_excel_helper`, and Spreadsheet_Excel_Reader |
| DB | MySQL. 24 tables, all prefixed `store_`. Charset is a mix of latin1 and utf8mb3 |
| Tenancy | One install serves ~10 shops (`store_storelist`). Almost every row carries `store_id`, set from the logged-in user's session |
| Auth | Session login with captcha. Passwords are **unsalted MD5** (`store_admin.passwd`) |
| Data volume (dump) | 2,486 products · 2,129 customers · 4,360 sales invoices · 1,249 stock invoices · 8 vendors · 609 accounting entries · ~29k INSERT rows in total |
| Stores in dump | 1 ArhSeh Sports Gallery · 2 ArhSeh Fashion Kalai · 3 ArhSeh Panchbibi · 4 ArhSeh Mobile Gadgets · 5 ArhSeh Khela Ghor · 6 EI Dom Holdings · **7 DIFFERENT FASHION** · 8 Kushi Traders · 9 মেসার্স রিপন ট্রেডার্স · **10 DIFFERENT FASHION-2** |
| **Live customer (migration scope)** | **DIFFERENT FASHION** = stores 7 + 10. Store 7 holds 3,090 of the 4,360 sales and 1,310 of the 2,129 customers |

---

## 2. Feature inventory by module

### 2.1 Authentication & session
| Feature | Detail | Status |
|---|---|---|
| Login | Username + password + captcha (`Login::login_check`) | IMPROVE (email/username/phone login, rate limiting, optional 2FA) |
| Logout | Records logout time in `store_admin_access_record` | KEEP |
| Change password | Self-service (`Change_password`) | KEEP |
| Login logs | `store_login_activity` (user, IP, time). Viewed at `user/user_logs` | IMPROVE (adds user-agent, device, failed attempts) |
| Store binding | The user's `store_id` decides which shop's data they see. A user cannot switch shops | IMPROVE (a user can belong to several branches and switch between them) |

### 2.2 Users, roles & permissions
| Feature | Detail | Status |
|---|---|---|
| Users | `store_admin`: username, password, group, store, mobile, email, address, status ACTIVE/INACTIVE/PENDING | KEEP |
| Roles ("Admin groups") | 1 superadmin · 2 admin · 3 Salesman · 4 Accountant | IMPROVE (named permission sets, per tenant) |
| Menu manager | `store_menu` is a tree (parent_id, link, icon, order, PUBLISH/UNPUBLISH). Each row holds a CSV of allowed group ids, and the sidebar is built from it | DROP as a runtime-editable menu. Replaced by a code-defined navigation filtered by permissions |
| Menu permission screen | Matrix of menus × groups (`menu/menu_permission`) | IMPROVE (role × permission matrix UI) |
| Access check | `Bindu_Controller::access_check`. **Defect:** it allows any page that is not in the menu table when an HTTP referer is present | Replaced by policies and route middleware |
| Hard-coded rules | Only group 1 may edit sales invoices. `store_id == 9` gets customer group 3 for new customers | Becomes permissions and branch settings |

### 2.3 Shops (Store setting)
| Feature | Detail | Status |
|---|---|---|
| Store CRUD | Name, address, contact no, logo, status | KEEP (as **Branch**) |
| `is_use_stock_invoice` flag | **0** = the cashier manually picks which purchase batch (stock invoice no.) each line comes from. **1** = automatic batch pick | IMPROVE (always automatic FIFO, with an optional manual batch override) |
| Store info on invoice | Logo, name, address and phone are printed on the receipt | KEEP |

### 2.4 Catalog
| Feature | Detail | Status |
|---|---|---|
| Products | Name, code, category, brand, vendor, quantity, **MRP** (sale price), buy price (last), min sale qty, stock-out alert level, status | KEEP + IMPROVE (barcode, unit, tax, image, reorder level, variants later) |
| Categories | Name, plus a (vestigial) `brand_id` | KEEP (adds a hierarchy, drops `brand_id`) |
| Brands | Name | KEEP |
| Enable/disable/delete | `set_status`, `del` on each catalog entity | KEEP (soft delete) |
| AJAX product lookup | `Ajax::get_category`, `get_stock_list`, `Product::product_details` | IMPROVE (instant search: barcode / SKU / name) |
| Decimal quantities | `query.txt` converts quantity columns to FLOAT(10,2), so items can be sold by weight or length | KEEP (DECIMAL(14,3) + unit) |

### 2.5 Purchasing / Stock-in ("Stock")
| Feature | Detail | Status |
|---|---|---|
| Stock invoice | `ST-n` number, with lines of product, buy price, quantity and `available_stock` (the batch remainder) | KEEP as **Purchase** (+ vendor, vendor ref, discount, payment, due) |
| Stock invoice list / view / edit | Standard list, detail and edit | KEEP |
| Stock check | `Stock::stock_check` (availability lookup) | KEEP |
| Product qty and buy price update | Done by a **MySQL trigger** on `store_stock_invoice_details` INSERT (from `query.txt`): `quantity = quantity + new.quantity, buy_price = new.buy_price` | IMPROVE (an application service writes a stock ledger, inside a transaction) |
| Vendors | Name, mobile, email, address, description. The profile shows purchase history | KEEP + vendor balance/payables |

### 2.6 Sales
| Feature | Detail | Status |
|---|---|---|
| New invoice | Two steps: pick products and quantities, then a **confirm** screen, then save, then auto-print | IMPROVE (single-screen register, barcode-first, keyboard-driven) |
| Invoice number | `S-n` per store, computed as "last + 1" (**race condition**) | IMPROVE (locked sequence per branch) |
| Customer at sale | Pick an existing customer, or create a new one inline (name, mobile, address, shop name). Mobile number is checked for duplicates | KEEP + walk-in default |
| Line discount | An absolute amount per line | KEEP + % discount + order-level discount |
| Payment status | `Paid` or `Due`. When Due, a `paid_amount` is recorded and `total_due` = total − discount − paid | IMPROVE (split tender, partial, change due) |
| Due collection | Add a payment on the invoice view. It must not exceed the due amount, and payment history is listed | KEEP + collect across multiple invoices |
| Batch cost capture | Each sale line stores the `buy_price` and `stock_invoice_no` of the batch it came from (`Stockmodel::get_buy_price`). In auto mode it picks the **first single batch with available ≥ qty** (not a true FIFO split) | IMPROVE (true FIFO allocated across batches) |
| Stock decrement | **Not in the PHP code.** It must be done by DB triggers on the live server (the dump has none). **Inspect the live DB triggers before migration** | IMPROVE (stock ledger) |
| Back-dated sale | `sale_date` can be overridden | KEEP (permission-gated) |
| Courier service | Free-text courier name stored on the invoice | KEEP (+ tracking no.) |
| Invoice view / A4 print / direct print | `invoice_details`, `invoice_details_print` | IMPROVE (80mm/58mm thermal + A4 + PDF + share link/QR/WhatsApp share URL) |
| Edit invoice | Admin only. Can change date, payment status and customer | IMPROVE (edit only allowed while it is a draft. Completed sales are **voided or returned**, never silently edited) |
| Invoice list | Filter by invoice no, customer, payment status and date range. Shows qty, amount, discount, total, due, status and seller | KEEP |

### 2.7 Returns
| Feature | Detail | Status |
|---|---|---|
| Sales return | Look up a sales invoice, choose items and qty (validated qty ≤ sold qty), confirm, save the return invoice. Lines keep `sales_invoice_id` and `stock_invoice_id` | KEEP + refund method (cash/credit to account), restock flag |
| Return list / view | Standard list and detail | KEEP |
| Purchase return | Not present | NEW (post-MVP) |

### 2.8 Customers
| Feature | Detail | Status |
|---|---|---|
| Customer CRUD | Name, shop name, mobile (unique per store), email, address (one free-text field), group, DOB, marriage date. The only identifier is the internal auto-increment id | KEEP + structured BD location, customer number, membership card, PIN |
| Customer groups | 1 Whole Sale · 2 Retail · 3 (store-specific) | KEEP (+ group discount / price tier) |
| Customer profile | Invoice history and invoice detail | KEEP + statement/ledger & balance |
| Irregular customers | List of customers with no recent purchases (`index_irregular`) | KEEP (as a "Lapsed customers" segment) |
| Export | Customer list download | KEEP (CSV/XLSX) |
| Customer portal fields | `username`, `passwd` on `store_customer_list` (no UI uses them) | DROP |

### 2.9 Customer orders
| Feature | Detail | Status |
|---|---|---|
| Wholesale / retail order lists, view, edit, status | `CustomerOrder` controller + `Ordermodel` on `customer_order_list` | **DROP**: the table is **not in the DB dump** and nothing creates orders. Half-built. Replaced post-MVP by quotations / sales orders |

### 2.10 Accounting
| Feature | Detail | Status |
|---|---|---|
| Accounting types | Expenditure, Salary (`Sellary`), Withdraw, Income, Room rent (`Room Vara`) | KEEP (as category groups) |
| Accounting heads | Title, type, status, per store | KEEP (as categories) |
| Entries | Head, amount, transaction date, description, created by | KEEP (+ which account/cash drawer) |
| Accounting PDF report | Filtered list exported to PDF | KEEP |
| Fixed assets | Name, purchase date, price, description, invoice image, status, view | KEEP |

### 2.11 Dashboard & reports
| Feature | Detail | Status |
|---|---|---|
| Dashboard | Total stock units, top-10 selling products, stock-out list (**threshold hard-coded ≤ 20**, it ignores each product's `stock_out_alert`), with downloads | IMPROVE (KPI tiles, trend charts, per-product reorder level) |
| Sales report | By date range (+ customer-wise "Top customer list") | KEEP |
| Daily sales report | Present but UNPUBLISHED in the menu | KEEP |
| Stock report | Batch-level stock value (buy price × available) | KEEP |
| Stock count report | Product quantities snapshot | KEEP (+ stocktake workflow) |
| Product list report | Catalog export | KEEP |
| Sale price report | Products with MRP/sale price | KEEP |
| Profit & loss | From sales lines (price − buy_price) and accounting entries | KEEP (from COGS + expenses) |
| Due payment report | Outstanding customer dues | KEEP |
| Due collection report | Collections in a period, overall and **by user** | KEEP |
| Export | Every report has PDF and Excel/CSV download | KEEP |

### 2.12 Operations / misc
| Feature | Detail | Status |
|---|---|---|
| DB backup | `Backup` controller + `backup.php` (FTP upload with **hard-coded DB and FTP credentials**) | IMPROVE (scheduled encrypted backups to object storage) |
| Adminer | `hidden.php` = Adminer 4.8.1, publicly reachable | **DROP**. Never ship DB tools in the web root |
| SMS API hook | `access_check` whitelists an `sms_api` controller that does not exist in the repo | DEFERRED (provider + adapter decided later) |

### 2.13 New in the rewrite (no legacy equivalent)
Offline-first register · keyboard-first desktop-style UI (command palette, right-click menus, status bar) · colour schemes + density · top-bar notifications & clock · structured BD customer location · numeric customer number with check digit · membership cards (CR80/A4) · customer PIN on every purchase · split tender · register Today summary + daily cash summary report · stocktake · transfers · standard VAT fields · white-label (post-MVP).

---

## 3. Legacy data model (for migration)

| Legacy table | Key columns | Maps to (new) |
|---|---|---|
| `store_storelist` | id, store_name, address, contact_no, is_use_stock_invoice, status, logo | `tenants` + `branches` (see 05_Schema §Migration) |
| `store_admin` | id, id_admin_group, username, passwd(md5), store_id, mobile, email, address, status, last_login_time | `users` (+ `branch_user`, role) |
| `store_admin_group` | id, title | roles |
| `store_admin_access_record`, `store_login_activity` | login/logout history | `login_activities` (optional import) |
| `store_menu` | menu tree + CSV group ids | not migrated |
| `store_brand_list` | brand_name, store_id | `brands` |
| `store_category_list` | category_name, brand_id, store_id | `categories` |
| `store_product_list` | product_name, product_code, category_id, brand_id, vendor_id, quantity, mrp, buy_price, min_sale_qty, stock_out_alert, status | `products` + opening `stock_batches`/`branch_stocks` |
| `store_vendor_list` | name, mobile, email, address, description | `vendors` |
| `store_customer_group` | title | `customer_groups` |
| `store_customer_list` | name, shop_name, mobile, email, address, customer_group_id, dob, marriage_date | `customers` |
| `store_stock_invoice_list` / `_details` | invoice_no, created_at / product_id, buy_price, quantity, available_stock | `purchases` / `purchase_items` + `stock_batches` (remaining = available_stock) |
| `store_sales_invoice_list` / `_details` | invoice_no, customer_id, totals, total_due, payment_status, courier_service, created_by / product_id, price, buy_price, quantity, discount, stock_invoice_no | `sales` / `sale_items` (+ `sale_item_batches`) |
| `store_sales_invoice_payment_list` | invoice_id, paid_amount, created_at, created_by | `payments` + `payment_allocations` |
| `store_return_invoice_list` / `_details` | invoice_no, totals / sales_invoice_id, stock_invoice_id, product_id, qty, price, discount | `sale_returns` / `sale_return_items` |
| `store_accounting_type` / `_head` / `store_accounting` | type → head → entry (amount, transection_date) | `transaction_categories` (2 levels) / `cash_transactions` |
| fixed assets table (`Fixedassetmodel`) | name, purchase_date, price, description, invoice_image | `fixed_assets` |

### Pre-migration checklist (must run against the **live** DB, not the dump)
1. `SHOW TRIGGERS;` on the live DB. Record every trigger body, because sales stock decrement and return restock logic live there.
2. Check whether `customer_order_list` and the fixed-asset table exist live.
3. Compare `store_product_list.quantity` with `SUM(available_stock)` per product. Expect drift: the migration uses batch remainders as the source of truth, and drift is reported, not hidden.
4. Inventory the latin1 columns and verify that Bangla text survives conversion to utf8mb4.

---

## 4. Known legacy defects (the rewrite must not reproduce them)
1. Money and quantity are stored as FLOAT/DOUBLE, which causes rounding drift.
2. A sale and a stock-in each write 3–4 tables **without a DB transaction**.
3. Invoice numbers are generated "last + 1" with no lock, so duplicates are possible under concurrency.
4. Access control falls back to allow when a page is missing from the menu table.
5. Passwords are unsalted MD5. The password rule is `alpha_numeric` (blocks symbols).
6. Secrets are committed to git (`backup.php`), and Adminer sits in the web root.
7. Business logic is split between PHP and invisible DB triggers.
8. Hard-coded tenant ids (`store_id == 9`) and thresholds (`<= 20`).
9. `memory_limit` is forced to 44M, so PDF generation runs out of memory.
10. Completed invoices can be edited in place, so there is no audit trail.
11. Timestamps are written as `date("Y-m-d h:i:s")`: **12-hour with no AM/PM**, so afternoon times are stored as morning (`Bindu_Controller::current_date`, `Bindu_Model::curdate`).
12. `SET SESSION sql_mode=""` on every model accepts `0000-00-00` dates and truncation.
13. Editing a sale to "Due" deletes its payment history. `Stock::edit` can update the wrong batch row.
14. 18 of 22 tables are `latin1`, and Bangla is stored as UTF-8 bytes inside them. There are no foreign keys. Customer `username`/`passwd` are plaintext.
15. CSRF and XSS filtering are off, with SQL injection in `Ajaxmodel.php:19`, `Categorymodel.php:95`, `Menumodel.php` and the `sort_on` URL segment. `backup.php` is public with credentials, and `/upload_images/` executes PHP.
16. The due-collection reports call model methods that do not exist, and the PDFs fail on PHP 8 (mPDF 5.7). These reports are effectively broken in production today.

Migration handling for all of these: [ADR 0002](adr/0002-legacy-migration-strategy.md).
