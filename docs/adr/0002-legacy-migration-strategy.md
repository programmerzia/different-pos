# ADR 0002: Legacy data migration strategy

- **Status:** Accepted (2026-10-30)
- **Affects:** 05_Schema §12, 00_Legacy_Features §3–4, 06_Plan Phase 5
- **Evidence:** code survey of `../different` (CodeIgniter 3.1.11) and a schema-only read of `ims_backup.sql` (2026-10-29). No customer rows were read or copied.

## Context

The legacy POS is live, so its data keeps changing until cut-over. The data is small (about 29k rows), but it is dirty in ways that silent conversion would corrupt.

**Confirmed from the dump schema:**
- Money is `float(10,2)`, `double` and `double(18,2)`. There are no integer or decimal money columns.
- 18 of 22 tables are `latin1`. The rest are `utf8mb3` or `utf8mb4`. Bangla text exists **as UTF-8 bytes inside `latin1` columns** (seen in `store_storelist`). A naive `CONVERT(... USING utf8mb4)` would turn it into mojibake.
- There are **no foreign keys**. Products, users, brands and categories are hard-deleted, so orphans are certain.
- The dump has **no triggers**. The app's own backup omits triggers, so this proves nothing about the live database. The PHP code never decrements stock on a sale or return, so live triggers almost certainly exist.
- `created_at` is a mix of `date`, `datetime` and `timestamp`. `stock_invoice_details.quantity` and `available_stock` are `int`.

**Found in the code (`../different/project`):**
- **Timestamps are 12-hour with no AM/PM** (`date("Y-m-d h:i:s")` in `Bindu_Controller::current_date`, `Bindu_Model::curdate`). Every afternoon time is stored as morning. **The date part is correct. The time of day is ambiguous for all historical rows.**
- `SET SESSION sql_mode=""` on every model, so `0000-00-00` dates and truncated values were accepted.
- Editing a sale to "Due" **deletes its payment history** and resets `total_due`, so Σ payments ≠ total − due for some invoices.
- Invoice numbers are "last + 1" with no lock, so **duplicates within a store are possible**.
- `sales_invoice_details.stock_invoice_no` holds a stock invoice **id**. `return_invoice_details.stock_invoice_id` can hold an id **or** an `ST…` string.
- `product_list.vendor_id` and `category_list.brand_id` are **CSV lists**.
- `payment_status` appears as `Paid`, `paid`, `Due` and `due`.
- Customer `username`/`passwd` are stored in **plaintext** (for an external ordering app). Staff passwords are unsalted MD5.
- An external app writes `customer_order_list` (LPG brand columns; another store's business). The table is not in the dump.

## Decision

### M1. One importer, store-generic, through import Actions
`php artisan legacy:import --connection=legacy --stores=7,10 --tenant=different-fashion [--dry-run] [--fresh]`

- **Generic by store.** Nothing in the code is specific to DIFFERENT FASHION. Store quirks (group id meanings, branch codes, which stores form a tenant) live in `config/legacy.php` maps. Any other legacy store (1–6, 8, 9) can be onboarded later with the same command and a new map entry.
- **Pipeline per entity:** Extract (read-only connection, **explicit column lists**, never `SELECT *`) → Transform (pure, unit-tested functions in `app/Legacy/Transform/*`) → Load (final Actions in `app/Actions/Legacy/*`, one `DB::transaction()` per chunk of 500).
- **Historical facts are not replayed through live Actions.** `CompleteSale` would re-price, re-number and decrement stock. Import Actions write the historical sale as it was charged (`origin=import`) and write stock as `opening` movements (05_Schema §12 step 8). They still use `StockLedger`, `Money` casts and `legacy_id_maps`.
- Arch tests: `App\Legacy\*` may not use `select('*')`. The columns `customer_list.username` and `customer_list.passwd` may not appear anywhere in `app/Legacy` (they are never read).

### M2. Full reload, not delta sync
Legacy rows have no reliable `updated_at`, and hard deletes leave no trace, so a correct incremental sync is impossible. The volume is small, so **every run imports the whole scope into a fresh tenant** (`--fresh` drops and recreates the target tenant's rows, guarded by `APP_ENV != production` or an explicit `--i-know` flag before go-live).
- **Rehearse** on every new dump during development: import → `integrity:check` → `reconciliation.csv` + `migration_report.csv`. Record the import duration. **Target: under 15 minutes**, so the cut-over freeze stays short.
- **Cut-over:** announce → put legacy into read-only (revoke the app user's write grants) → final dump → `legacy:import --fresh` → reconcile → owner signs off on the report → enroll devices → go live. Legacy stays read-only for 90 days.

### M3. Transformation rules (new; added to 05_Schema §12 "Cross-cutting rules")
| Problem | Rule |
|---|---|
| Float money | Select as `CAST(col AS DECIMAL(20,4))`, i.e. as a string, then `brick/money` half-up to paisa. The value never becomes a PHP float |
| `latin1` holding UTF-8 | Select text as `BINARY`. Decode as strict UTF-8, fall back to Windows-1252, then trim and NFC-normalise. Log every fallback row in `migration_report.csv` |
| 12-hour timestamps | Keep the legacy **date** (it is correct). Store the time as written, interpreted as Asia/Dhaka. All imported rows have `origin=import`. Hour-of-day reports and the void window **exclude** `origin=import`. Daily, monthly and yearly reports stay correct because the date is preserved |
| `0000-00-00` / invalid dates | `NULL` (DOB, anniversary). For a document date: fall back to the header date, then the id-neighbour date, and report it |
| Future-dated sales | Imported as-is and listed for owner review (unchanged rule) |
| Duplicate invoice numbers | The first row (by id) keeps the number. Later ones get `-D2`, `-D3`… and are reported |
| Header vs line totals differ | The **header is what the customer was charged** and wins. The difference goes into the report |
| Σ payments ≠ total − due | `total_due` is the receivable truth. Synthesise one `Legacy balancing payment` (Cash, at the invoice date) for the gap and report it. Never create a negative payment: if payments exceed the paid amount, report and keep the payments |
| `payment_status` casing | Case-insensitive. The status is derived from the due amount, not trusted |
| Ambiguous stock-invoice refs | Resolve by id first. If that fails, by `ST` number within the store. If that fails, no batch link (COGS from the line's `buy_price`) and report it |
| CSV `vendor_id` / `brand_id` | Products: the first valid vendor becomes the preferred vendor, and the rest are reported. Category `brand_id` is dropped (already decided) |
| Orphans (deleted product/user/customer) | Create one inactive soft-deleted placeholder per missing id ("Deleted product #123") so history keeps its lines. Report them |
| Negative `available_stock` | Batch remainder 0. Report the shortfall |
| Customer `username`/`passwd`, staff MD5 | Customer credentials are **never read**. Staff MD5 goes to `legacy_password_md5` only (unchanged rule) |

### M4. Reconciliation is the go-live gate (unchanged, sharpened)
`reconciliation.csv` per store: counts, Σ sales totals, Σ discounts, Σ dues, Σ payments, stock units and stock value at cost, legacy vs new. Each difference must map to a line in `migration_report.csv` with a rule from M3. **Zero unexplained differences** before go-live.

## Consequences
- The importer is throwaway code with a long tail of rules. Isolating it under `app/Legacy` + `app/Actions/Legacy` keeps it removable (06_Plan 5.9).
- Imported sales carry no reliable time of day. Hour-of-day analytics start at go-live.
- Onboarding the other legacy stores later is a config change plus a rehearsal, not new code.

## Must do before step 5.3 (against the live server, not the dump)
1. `SHOW TRIGGERS` and `SHOW CREATE TABLE` for all `store_%` tables → `docs/adr/0004-legacy-db-findings.md`.
2. Confirm whether anything external (the ordering app behind `customer_order_list`) touches stores 7 or 10.
3. Legacy security containment is done first ([ADR 0003](0003-legacy-security-containment.md)). Live inspection and the importer use only the read-only `legacy_ro` user.
