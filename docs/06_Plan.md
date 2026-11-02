# 06 — Implementation Plan

> **How to use this file (agents MUST follow):**
> 1. Work **top to bottom**. Never start a step whose predecessors (by order, or by an explicit `⟵ needs #id`) are unchecked.
> 2. One step = one focused change (ideally one commit). Its **Done when** must be verified by a command or test before ticking.
> 3. After finishing, edit this file in the same commit: `[ ]` → `[x]` + ` — ✅ YYYY-MM-DD <short-sha/note>`. If a step is wrong or too big, rewrite or split it here **first**.
> 4. If you need a decision not covered by docs 01–05, add it to **§Open questions** and ask.
> 5. Append one line per working session to **§Progress log**.

Legend: `[ ]` todo · `[~]` in progress (one at a time) · `[x]` done · `[-]` dropped (reason)

---

## Phase 0: Scaffold & tooling
- [x] **0.1** Upgrade Composer to ≥ 2.8. Verify PHP ≥ 8.4, Node 24, MySQL 8.4, Redis, and `ext-sodium`. *Done when:* the versions meet 04_TRD §1. — 2026-10-30 check: ✅ PHP 8.5.12, Composer 2.11.1, MySQL 8.4.11, git 2.53, ext bcmath/exif/intl/mbstring/pcntl/pdo_mysql/sodium/zip. ❌ **missing (needs sudo, owner):** `ext-gd`, `ext-redis`, Redis/Valkey server, **Node 24** (have 22.23.2). Mockup tooling moved from the repo root to `tools/mockup/` so the scaffold can own `vendor/`. — ✅ 2026-10-31 Node 24.22.2 via nvm; MySQL 8.4.13 + Redis 7.4.12 via Docker `compose.yaml`. **Exception:** `ext-gd`/`ext-redis` still need a sudo install (§Out-of-repo L5); mpdf deferred to 2.4, local cache/queue on the database driver until then.
- [x] **0.2** `laravel new` with the **React starter kit** into this directory (keep `docs/`, `CLAUDE.md`, `.cursorrules`, `README.md`). Pest. *Done when:* `php artisan --version` shows 13.x, and `npm run build` + `php artisan test` pass. — ✅ 2026-10-31 Laravel 13.39.0, Inertia 3, Pest 5; tests on MySQL `pos_testing`.
- [x] **0.3** `git init`, `.gitignore` (`.env*` except `.env.example`), first commit. (The owner adds the remote later.) — ✅ 2026-10-31 `4759395`; CLAUDE.md is tracked (the starter kit ignored it).
- [x] **0.4** Install the 04_TRD §2 packages. **Fill the "Pinned" columns.** *Done when:* all resolve for Laravel 13 / React 19, else STOP. — ✅ 2026-10-31 owner-approved major bumps (TRD §2 note); all pinned; `mpdf/mpdf` deferred to 2.4 (needs ext-gd).
- [x] **0.5** Pint, Larastan 8, Rector, Oxlint (incl. a ban on `dangerouslySetInnerHTML`, react-hooks, jsx-a11y; Vite+ toolchain replaces ESLint/Prettier), Oxfmt, strict TS, Vitest (+ fake-indexeddb), Playwright. Scripts: `composer lint|analyse|test|ci`, `npm run lint|typecheck|test|e2e`. *Done when:* all are green. — ✅ 2026-10-31 Pint (`laravel` + `declare_strict_types` + `final_class`), Rector (PHP 8.4 + dead-code/quality/type sets), Larastan level 8 (0 errors, no baseline), Oxlint (`react/no-danger`, hooks, jsx-a11y, `@inertiajs/*` banned under `resources/js/pos/**`), TS `noUncheckedIndexedAccess` + `exactOptionalPropertyTypes`, `vp test` (jsdom + fake-indexeddb, `passWithNoTests`), `playwright.config.ts` (`e2e/`, no browsers installed). Starter-kit fixes: QR code now an `<img data:>` instead of `dangerouslySetInnerHTML`; `jsx-a11y/no-autofocus` off on purpose (keyboard-first).
- [x] **0.6** `composer ci` runs every gate from 04_TRD §6 (provider-agnostic). A CI pipeline file is **deferred** until the owner picks the repo host. — ✅ 2026-10-31 `composer ci` = lint → analyse → test → npm lint/typecheck/test/build → `composer audit` → `npm audit`. **TODO (not in `ci` yet):** `playwright test` (needs browsers, not installed), `gitleaks` (not installed), Pest `--coverage --min=80` (no xdebug/pcov on this machine).
- [x] **0.7** `.env.example` (MySQL, Redis, argon2id, UTC, S3, Sentry). Neutral `APP_NAME` ("POS"); **no product name anywhere** in code or UI strings. — ✅ 2026-10-31 S3 + Sentry entries added (commented); `config/app.php` timezone UTC; the "Laravel" fallbacks/welcome page replaced by `config('app.name')`/neutral page.
- [x] **0.8** Remove public registration. Keep login, reset and 2FA. — ✅ 2026-10-31 `Features::registration()` off, `CreateNewUser`, register page/view, login "Sign up" link and `RegistrationTest` removed; `RegistrationDisabledTest` asserts `/register` is 404.
- [x] **0.9** Pest arch baseline: strict_types, no debug calls, no `DB::` in controllers, final Actions with `handle()`, no `float` in money paths. — ✅ 2026-10-31 `tests/Arch/ArchTest.php` (registered as the `Arch` suite in `phpunit.xml`); `App\Actions\Fortify` excluded. The "no `float` in money paths" check is **not** expressible in Pest arch (it cannot inspect type hints); covered instead by the Money cast tests (1.1) and Larastan level 8.

## Phase 1: Database & Auth
**1A: Foundations**
- [x] **1.1** Money/Quantity casts (PHP) + `engine/money.ts` (paisa, milli-qty helpers) + formatters (en-IN grouping, Bangla digits) + tests. — ✅ 2026-10-31 `App\Support\{Money,Quantity,Numbers}`, `App\Casts\{Money,Quantity}Cast`, `resources/js/engine/{money,quantity,digits}.ts`; shared golden cases `tests/fixtures/money/cases.json` run by Pest **and** Vitest; ±2^53 range on both sides.
- [x] **1.2** Migrations: tenants (incl. VAT BIN, settings, branding, pin box keys, sync_counter), branches, users (preferences: theme/scheme/density), branch_user, user_invitations, login_activities. — ✅ 2026-10-31 + `roles.max_discount_pct_bp/is_system`, `activity_log.tenant_id`, CHECKs (tenant status, platform-admin-or-tenant); permission teams = `tenant_id`.
- [x] **1.3** Models, factories, `HasUuid` (UUIDv7), and the `Syncable` concern (assigns `sync_version` from `tenants.sync_counter` under lock). — ✅ 2026-10-31 Tenant/Branch/User; `Transaction::run()` + `SyncVersionRecorder` (counter lock taken last, one bump per tx, rollback-safe, nested-safe).
- [x] **1.4** `CurrentTenant`/`CurrentBranch` + `BelongsToTenant`/`BelongsToBranch` (scope, auto-fill, cross-tenant guard) + tests. — ✅ 2026-10-31 as `TenantContext` (one class for both); fail-closed scopes; User unscoped by design with a write guard + `inCurrentTenant()`.
- [ ] **1.5** Middleware (EnsureTenantActive, ResolveTenant, EnsureBranchSelected, SetLocale). Route files `app.php`, `api-internal.php`, `pos-api.php`, `platform.php`. — ✅ 2026-10-31 aliases `tenant.active|tenant.resolve|branch.selected|locale|platform.admin`; files wired in `bootstrap/app.php`; `/app` dashboard behind `can:dashboard.view`; placeholder `branch.select` (real page = 1.12); settings/profile routes stay on `auth` only (platform admins and branch-less users need them; route 110 is *no branch*); starter `routes/api.php` removed; `/api/pos/v1/ping` unauthenticated until 2.22 (`AuthenticateDevice`).
- [x] **1.6** `WithTenantContext` job middleware + a test that a job without context fails. — ✅ 2026-10-31 via Laravel `Context` propagation (done before 1.5; independent).
- [x] **1.7** Tenant keypair generation (sodium X25519) on tenant create. `PinSealer` service (seal/open) + tests. — ✅ 2026-10-31 secret key `encrypted` cast + hidden; `SealedPin` hides the PIN from dumps; tamper/foreign-tenant/malformed tests.

**1B: Auth & permissions**
- [ ] **1.8** spatie/permission teams + `RolesAndPermissionsSeeder` (04_TRD §3.8 incl. `customers.reset_pin`, `cards.*`, `devices.manage`, `pos.customer_pin_override`). — ✅ 2026-10-31 `Support\Permissions\{Permission,SystemRoles}` (single list), `Actions\Tenancy\{SeedPermissions,SeedTenantRoles}`, `RolesAndPermissionsSeeder`; `User` uses `HasRoles`; idempotent; team isolation tested.
- [ ] **1.9** Fortify login by email/username/phone + **business code** + **deactivated users are refused at login and logged out on their next request** (gap found in 1.5 review) (ADR 0006: email app-wide unique, username/phone per tenant, remembered `pos_tenant` cookie, uniform error), argon2id, `LegacyMd5Hasher` + rehash + forced password change. Tests.
- [ ] **1.10** Login throttling + `login_activities`.
- [ ] **1.11** 2FA + per-role enforcement.
- [ ] **1.12** Branch select/switch. Remember the last branch.
- [ ] **1.13** Invitations (email + copyable link/QR; **no SMS**).
- [ ] **1.14** Staff PIN (set/change, lockout) + lock screen (`/app/lock`, `/app/unlock`).
- [ ] **1.15** activity_log with `tenant_id`. Log users/roles/branches/permissions.
- [ ] **1.16** Platform admin: tenant provisioning (branch, Owner invite, seeds: units, payment methods, accounts, walk-in customer, sequences incl. `customer`, register R1) + impersonation.

**1C: Business schema (migrations + models + factories; no logic)**
- [ ] **1.17** Geo reference: `geo_divisions/districts/upazilas/unions/post_codes` + `geo:import` command + curated metro-thana JSON + `GeoSeeder`. ADR for the data licence. *Done when:* 8 divisions, 64 districts, all upazilas + Dhaka/Chattogram metro thanas are present in EN+BN.
- [ ] **1.18** Catalog: categories, brands, units, tax_rates, **variant_options, variant_option_values, product_families**, products (family/variant columns, `variant_key` unique guard, hs_code, sync_version, FULLTEXT ngram).
- [ ] **1.19** Parties: customer_groups, **customers (full 05_Schema shape: customer_no, location, PIN fields, sync, merge)**, **membership_cards**, vendors.
- [ ] **1.20** Money: accounts, transaction_categories, cash_transactions, fixed_assets, payment_methods.
- [ ] **1.21** Inventory: branch_stocks, stock_batches, stock_movements, adjustments, stocktakes, transfers.
- [ ] **1.22** Purchasing: purchases, purchase_items.
- [ ] **1.23** Sales & devices: registers (code, last_number), **devices**, device_pairing_codes, sales (+ `register_id`) (provenance/member_auth/sync_flags/VAT BIN), sale_items, sale_item_batches, payments, payment_allocations, sale_returns(+items).
- [ ] **1.24** Platform: document_sequences, idempotency_keys, **sync_ops**, notifications (+tenant_id), exports, legacy_id_maps.
- [ ] **1.25** Backed enums + CHECK constraints + generated-column unique guards (one active device per register, one active card per customer).
- [ ] **1.26** Isolation test harness `assertTenantIsolated()`.

## Phase D: Demo release (single phase, decided 2026-11-03)

Goal: DIFFERENT FASHION's real products, stock, customers and sales history running on the new app, with a working online register, stock, dues, returns/exchange, cards, SMS, reports and user management. Everything below is **one phase**; everything in Phases 2–5 that is not referenced here moves to the next phase (see [ADR 0007](adr/0007-demo-first-ordering.md)). Original step numbers are kept in brackets so docs 02/04/05 stay traceable.

Decisions baked in (ADR 0007): the register is a normal Inertia page talking to the same `CompleteSale` action the offline SPA will use later; **SMS is no longer deferred**; customer PIN, device sync, geo and 2FA wait for the next phase. Legacy facts from the 2026-10-29 dump (stores 7 + 10): 69 products (15 duplicate codes, many with cost 0), 1,311 customers (1,109 with `shop_name`, one duplicate phone, all group "Whole Sale"), 3,091 sales / 9,189 lines (one dated 2027-03-02), 286 open dues (৳95.5 lakh), 197 stock invoices / 204 batch lines, batch stock exceeds product stock by 648 units, no returns, no accounting rows, 2 users, 6 vendors.

### D1. Data in
- [ ] **D1.1** Schema for the slice [1.18–1.25 minus devices/sync_ops/geo]: categories, brands, units, tax_rates, product_families, variant_options/values, products (`shop_name`-free; variants per ADR 0005), customer_groups, customers (**`shop_name` column**, customer_no, no PIN columns used yet), membership_cards, vendors, payment_methods, branch_stocks, stock_batches, stock_movements, stock_adjustments, purchases, purchase_items, registers, sales, sale_items, sale_item_batches, payments, payment_allocations, sale_returns, sale_return_items, document_sequences, legacy_id_maps, messages (SMS log). Backed enums + CHECKs. Factories. Isolation tests per model.
- [ ] **D1.2** `legacy:import` for stores 7 + 10 [5.1–5.4, 05_Schema §12 steps 1–12, 16]: separate read-only `legacy` connection; idempotent via `legacy_id_maps`; `--dry-run`; duplicate SKUs get `-{legacy_id}` and are reported; cost 0 products reported; one future-dated sale reported, not re-dated; stock batches from `store_stock_invoice_details.available_stock`, product-vs-batch differences in `migration_report.csv`; `reconciliation.csv` with counts and sums per store. Rehearsal under 15 min.

### D2. Core writes
- [ ] **D2.1** `DocumentNumberer` + `CustomerNumber` (Luhn) [2.1, 2.2].
- [ ] **D2.2** Policies for every model + arch test that every controller action authorizes [2.5]; `activity_log` with tenant_id on every Action [1.15].
- [ ] **D2.3** `StockLedger` (receive, FIFO issue with split, reverse) + `ReceivePurchase`, `CancelPurchase`, `PayVendor`, stock adjustments, stock queries [2.15–2.17, 2.20].
- [ ] **D2.4** Pricing twin `engine/pricing.ts` + `PriceCalculator.php` + golden fixtures [2.21].
- [ ] **D2.5** `CompleteSale` (origin web), `VoidSale`, `CollectCustomerPayment` (multi-invoice, advance), `CreateSaleReturn`, `CreateExchange` [2.26, 2.30–2.32a].
- [ ] **D2.6** Receipts: thermal 80/58 + A4 PDF (mPDF needs `ext-gd`; HTML print view first, PDF when the extension is installed) [2.4, 2.33].
- [ ] **D2.7** Catalog CRUD incl. family/variant generation from size × colour [2.7, 2.8, 2.8a]; customer CRUD with shop name and duplicate-phone detection [2.11]; cards: `IssueCard`, `ReportCardLost`, `ReissueCard`, CR80 PDF + bleed PDF + CSV for the press [2.13]; vendors [2.14].

### D3. Screens (the mockup is the spec)
- [ ] **D3.1** Tokens, i18n, hotkeys, shell, command palette, context menus, DataTable, shared components [3.1–3.8].
- [ ] **D3.2** Auth: login by email/username/phone + business code, throttling, branch select, forced password change for migrated users [1.9, 1.10, 1.12, 3.10].
- [ ] **D3.3** Products (+ variant matrix), customers (profile with inline card, "New sale", statement), membership cards, vendors + purchases, sales history, returns + exchange, due collection [3.12–3.17].
- [ ] **D3.4** Register at `/app/pos` (online): scan, variant picker with multi size/qty, customer attach highlight, split tender, due rules, receipt print, hold/resume local [3.29–3.33 online subset].
- [ ] **D3.5** Users, roles matrix, branches (single-branch hides branch UI), settings incl. SMS gateway [3.11, 3.22 subset].

### D4. SMS and reports
- [ ] **D4.1** SMS adapter: `SmsGateway` contract, drivers `ssl_wireless`, `log`, `null`; per-tenant settings; queued `SendSms` with `messages` log; events: sale completed (named customer only), payment received, due reminder (manual trigger). [new; replaces 6.2]
- [ ] **D4.2** Reports: top customers by purchase (period), customers ranked by last purchase (not bought for 1/2/3+ months), sales by day/product/category/user, dues aging, stock valuation, daily cash summary; CSV export [2.37, 2.38, 2.40 subset]. Dashboard KPIs [2.41].
- [ ] **D4.3** Audit trail screen (activity log with filters) [part of 3.11].

### D5. Hardening for the demo
- [ ] **D5.1** Tenant/branch isolation across every new route; role × route matrix; concurrency tests for numbering, customer_no and last-unit sale [4.1–4.4, 4.12].
- [ ] **D5.2** Keyboard-only e2e: sale with a member, due collection, return, card export [4.15 subset].
- [ ] **D5.3** Rehearsed import on the latest dump + `integrity:check` green + demo seed on top of real data.

Next phase (not before D5 is done): offline register SPA + device sync [2.22–2.29, 3.23–3.28, 3.34], customer PIN [2.12, 2.28, 4.7], geo [1.17, 2.10, 4.9], 2FA + invitations [1.11, 1.13], platform admin [1.16], money module [2.35, 2.36, 3.19], backups + nightly integrity [2.42, 2.43], remaining reports, UAT and cut-over [5.5–5.9].

## Phase 2: Core domain & API
**2A: Shared services**
- [ ] **2.1** `DocumentNumberer` (FOR UPDATE, yearly reset) + concurrency test.
- [ ] **2.2** `CustomerNumber` service: Luhn generate/validate, sequence-backed allocation. Property tests (every number passes; every single-digit typo and adjacent transposition is detected).
- [ ] **2.3** Idempotency (`Idempotency-Key`, `sync_ops`).
- [ ] **2.4** `PdfRenderer` (mPDF + Hind Siliguri) + a Bangla render test.
- [ ] **2.5** Policies for all models + an arch test that every controller action authorizes.
- [ ] **2.6** Notifications: base notification types (low stock, sync exception, PIN lock/unverified member, export ready, backup failed, transfer incoming, stocktake review, new device login) + `/app/api/notifications` endpoints.

**2B: Catalog & parties**
- [ ] **2.7** Category/Brand/Unit/Tax CRUD.
- [ ] **2.8** Product CRUD (SKU/barcode unique, `MC` prefix forbidden, auto EAN-13, image to S3, deactivate if referenced).
- [ ] **2.8a** Variants (ADR 0005): option/value CRUD, family CRUD, `GenerateFamilyVariants` (matrix → products, SKU `{code}-{v1}-{v2}`, auto barcodes), `AttachProductsToFamily`, invariant tests (values belong to family options, one product per combination).
- [ ] **2.9** Product import/export (dry run, row errors, queued).
- [ ] **2.10** Geo API: `/app/api/geo/search` (cross-level search returning a full parent chain) + `/app/api/geo/{level}?parent=` with ETag caching.
- [ ] **2.11** Customer CRUD: E.164 phone, duplicate detection, **customer_no on create**, location validation (child belongs to parent, parents auto-derived), BIN format, walk-in protected.
- [ ] **2.12** Customer PIN: `SetCustomerPin` (twice-entry, weak-PIN denylist incl. phone suffix), `VerifyCustomerPin` (lockout 5 → 30 min, rate limits), `ResetCustomerPin` / `UnlockCustomerPin` (perm + reason, audit). The PIN is never serialised (test asserts it is absent from every resource, export and log).
- [ ] **2.13** Membership cards: `IssueCard` (issue_no, card_code), `ReportCardLost`, `ReissueCard`, `RecordCardPrint`. `CardRenderer` PDF: CR80 (front+back pages) and A4 10-up with crop marks. Golden PDF text test.
- [ ] **2.14** Vendor CRUD.

**2C: Inventory & purchasing**
- [ ] **2.15** `StockLedger` (receive, FIFO issue with split, reverse, backorder path for synced device sales) + exhaustive tests.
- [ ] **2.16** `ReceivePurchase` / `CancelPurchase` / `PayVendor`.
- [ ] **2.17** Stock adjustments.
- [ ] **2.18** Stocktake lifecycle.
- [ ] **2.19** Transfers.
- [ ] **2.20** Stock queries (on-hand, valuation, movements, low stock).

**2D: Pricing, POS sync, sales, payments, returns**
- [ ] **2.21** **Pricing engine twin:** `engine/pricing.ts` + `PriceCalculator.php` + ≥ 40 golden fixtures (discount caps, group discount, VAT incl/excl, rounding, decimal qty, 100 % discount). Both test suites run the same fixtures.
- [ ] **2.22** Device enrollment (ADR 0001 D3): `IssuePairingCode` (Manager, register; 8-char single-use code, 10 min TTL, hashed) + `POST /api/pos/v1/enroll` → `EnrollDevice` (token with `pos:device`), `RevokeDevice`, `AuthenticateDevice` middleware, token rotation, `426` for outdated clients. Device-API JSON fixtures (`tests/fixtures/pos-api/`).
- [ ] **2.23** `/api/pos/v1/bootstrap` + `/ping` (settings, staff verifiers, tenders, taxes, tenant public key, number high-water mark).
- [ ] **2.24** `/api/pos/v1/pull` (Syncable delta + tombstones, paging) for products, **families, variant options/values**, stock, customers-min (no PIN/address), cards, groups, categories, tenders.
- [-] **2.25** ~~Register/shift Actions~~ (dropped 2026-10-30: no shifts; the legacy app has none. Open question 10.)
- [ ] **2.26** `CompleteSale` (origin web|device): server re-price + flag `price_mismatch`, device numbering + `registers.last_number`, stock issue (block online / backorder + `oversold` for device), payments + allocations + cash transactions, due/credit rule, member auth (online token / sealed box verify → `member_unverified`), customer balance + last purchase, VAT snapshot, public token.
- [ ] **2.27** `/api/pos/v1/push`: per-op transactions, `sync_ops` idempotency, op types (`sale.create`, `customer.create` with phone-dedupe merge → mapping), per-op results.
- [ ] **2.28** `/api/pos/v1/customers/verify-pin` (member token 10 min, bound to cart), `/customers/{id}/pin` (set, online), `/authorize` (manager override token).
- [ ] **2.29** Held carts are local only (no server op in MVP). Document this in the ADR.
- [ ] **2.30** `VoidSale` (online, same business day).
- [ ] **2.31** `CollectCustomerPayment` (multi-invoice, advance).
- [ ] **2.32** `CreateSaleReturn`.
- [ ] **2.32a** `CreateExchange` (ADR 0005): return (`refund_mode=exchange`) + replacement `CompleteSale` in one transaction, Exchange credit tender, difference pay/refund/credit, device number for the replacement sale, quote + store endpoints (web + `/api/pos/v1/exchanges`).
- [ ] **2.33** Receipt builder (thermal 80/58, A4 with VAT summary + BINs, customer no.) + public receipt + share link/QR/WhatsApp share URL.
- [ ] **2.34** Sync exceptions query + acknowledge Action + notifications to Managers.

**2E: Money, reports, ops**
- [ ] **2.35** Accounts, categories, manual cash transactions, balances.
- [ ] **2.36** Fixed assets.
- [ ] **2.37** `ReportContract` + runner + queued export + notification.
- [ ] **2.38** Reports batch 1: sales (summary, by day, by product/category/brand, by customer, by user, by tender), **VAT by rate**.
- [ ] **2.39** Reports batch 2: P&L, stock valuation, movements, low/dead stock.
- [ ] **2.40** Reports batch 3: purchases by vendor, dues aging, collections by user, vendor payables, **daily cash summary**, expenses, returns, lapsed customers, **customers & sales by division/district/upazila**, **membership cards issued**, **sync exceptions**.
- [ ] **2.41** Dashboard query (KPIs + deltas, trend, top lists, branch comparison, **sales by district**), cached 60 s.
- [ ] **2.42** `integrity:check` (05_Schema §10 incl. invariants 9–12), nightly + notification.
- [ ] **2.43** Backups (encrypted, Spaces, daily, 30 days).

## Phase 3: UI integration (React, following 02_AppFlow + 03_UI_Spec)
**3A: Shell & design system**
- [ ] **3.1** Tokens in `app.css`: light/dark + **6 colour schemes** (`data-scheme`) + density (`data-density`). Self-hosted fonts. `.num`. No-flash theme via cookie.
- [ ] **3.2** i18n (react-i18next + Laravel lang), EN/BN switch, Bangla digits option.
- [ ] **3.3** Hotkey registry (`useHotkeys` scopes) + `?` overlay + `Kbd` component.
- [ ] **3.4** **App shell**: sidebar (collapsible, `G` chords), **top bar** (branch switcher, command trigger, sync pill, register pill, **notifications bell + panel**, **live clock/date**, language, **theme popover with schemes + density**, avatar menu), **status bar**, mobile bottom tabs.
- [ ] **3.5** Command palette (cmdk): pages, actions, products, customers (`#number`), invoices, settings.
- [ ] **3.6** **Context menu system**: `RowContextMenu` + per-entity action registry (shared by row buttons, palette and context menu), `Shift+F10` / Menu key / long-press, permission-filtered.
- [ ] **3.7** DataTable (TanStack): server pagination, keyboard row navigation, multi-select, bulk bar, column chooser, saved views, density, context menu, mobile cards.
- [ ] **3.8** Shared components: EmptyState, MoneyInput, QtyStepper, KpiTile, StatusBadge, ConfirmDialog, FormSection, DateRangePicker, Upload, **PinPad (staff/customer variants)**, **LocationFields (cascading type-ahead with auto-parent fill)**.
- [ ] **3.9** Global error handling + offline banner for back office (02_AppFlow §6).

**3B: Back-office pages**
- [ ] **3.10** Auth pages (single login field, 2FA, reset, accept invite, forced password change, branch select, lock screen).
- [ ] **3.11** Settings: business (VAT BIN, PIN policy, offline credit cap), branches + registers + **devices**, users + invite, roles matrix, payment methods, taxes, receipt designer, **card template**, audit log, backups.
- [ ] **3.12** Catalog pages + **family/variant matrix editor** + group-into-family + import wizard + labels.
- [ ] **3.13** **Customers**: list (filters incl. district/upazila, has card, PIN status), create/edit (identity + LocationFields + membership section), profile (number, cards, PIN status, sales, payments, statement, balance), dues aging + WhatsApp share reminder.
- [ ] **3.14** **Membership cards**: list/print queue, issue & print wizard (CR80 / A4 10-up / PDF, live front/back preview), lost/reissue.
- [ ] **3.15** Vendors + purchases (scan-to-add).
- [ ] **3.16** Sales list/show (void, collect, share, reprint), sync exceptions page.
- [ ] **3.17** Returns + **exchange** page.
- [ ] **3.18** Stock, adjustments, stocktake (mobile + camera), transfers.
- [ ] **3.19** Money pages (cash book, accounts, categories, fixed assets).
- [ ] **3.20** Reports catalogue + runner.
- [ ] **3.21** Dashboard (KPIs, trend, tops, low stock, branch comparison, district breakdown), 360 px layout.
- [ ] **3.22** Profile (theme/scheme/density, locale, password, staff PIN, 2FA). Platform admin pages.

**3C: Offline POS register**
- [ ] **3.23** POS as a **separate Vite entry** (`resources/js/pos/main.tsx`) + static shell `pos.blade.php` at `/pos` (ADR 0001 D2), ESLint rule banning `@inertiajs/*` under `resources/js/pos/`. Service worker (vite-plugin-pwa injectManifest): precache, NetworkFirst for `/pos` with shell fallback, image cache, update toast that waits for an empty cart. Installable PWA manifest (neutral name/icon until branding).
- [ ] **3.24** Dexie schema (04_TRD §3.9.1) + migrations + `navigator.storage.persist()` + quota monitor.
- [ ] **3.25** Enrollment state in the POS SPA (pairing code entry / QR scan) + back-office "Pair register" dialog + bootstrap + device token storage + revoke → wipe.
- [ ] **3.26** Puller (cursors, paging, tombstones) + MiniSearch index + connectivity monitor (ping + `online`/`offline` events).
- [ ] **3.27** Outbox + pusher (batching, backoff, Background Sync, per-op result handling) + **Sync center** popover.
- [ ] **3.28** Staff offline unlock (PBKDF2 verifier, expiry window, lockout) + PIN switch.
- [ ] **3.29** Register UI: scan field + keyboard-wedge detector, product grid/list (virtualised), cart, key hints bar, **all shortcuts in 02_AppFlow §4.3**, context menus on tiles and lines, **variant picker** (size × colour grid with stock), **exchange flow `F11`** (online only).
- [ ] **3.30** Customer attach: card scan (`MC…`), number with live Luhn validation, phone/name search, inline create (offline → pending number), **customer PIN pad flow** (online verify / offline sealed box / set PIN / override).
- [ ] **3.31** Payment panel: split tender, quick cash, change, due/credit rules (online/offline cap), manager override dialog.
- [ ] **3.32** Local commit transaction (sale + outbox + stock decrement + device number), completion screen, auto-print (thermal 80/58 CSS, A4), reprint, share link/QR.
- [ ] **3.33** Hold/resume + register **Today summary** (`F7`, local, offline).
- [ ] **3.34** Responsive POS: tablet split, phone bottom-sheet cart.

## Phase 4: Edge-case, security & quality tests
- [ ] **4.1** Tenant isolation for every model/route/report/export/search **and the device API** ⟵ needs #1.26.
- [ ] **4.2** Branch isolation (incl. device bound to branch A pushing to branch B → rejected).
- [ ] **4.3** Concurrency: last-unit online race, numbering race, customer_no allocation race, double due collection.
- [ ] **4.4** Idempotency: replayed ops / `client_uuid` / `Idempotency-Key` → no duplicates.
- [ ] **4.5** **Pricing parity**: all fixtures identical in TS and PHP, plus a randomised property test (10k carts).
- [ ] **4.6** **Offline scenarios** (Playwright with network emulation):
  - offline sale → reconnect → synced
  - 50 offline sales → ordered sync
  - kill tab mid-commit → no partial sale
  - price changed while offline → `price_mismatch`
  - oversold → `oversold`
  - device revoked → wipe
  - SW update with a non-empty cart → no reload
- [ ] **4.7** **Customer PIN**: online verify, lockout and unlock, reset, weak PIN rejected, offline sealed box success and failure → flag + notification. The PIN appears in no response, log, export, IndexedDB dump or activity log.
- [ ] **4.8a** **Variants & exchange**: matrix generation idempotent, duplicate combination rejected, variant scan vs family search, exchange even/up/down difference, exchange of a partly returned sale, exchange credit never reaches cash, offline F11 disabled.
- [ ] **4.8** **Customer number & cards**: Luhn typo detection, lost card scan blocked, reissue increments, one active card, card_code ↔ customer_no consistency, offline-created customer gets a number on sync, duplicate phone merge mapping.
- [ ] **4.9** **Location**: child-parent validation, auto-parent fill, BN search, legacy-parsed rows flagged unverified.
- [ ] **4.10** Money/stock/time edge cases (rounding, 100 % discount, split tender change from cash only, FIFO across 3 batches, decimal qty, return limits, void window across Dhaka midnight, yearly sequence reset).
- [ ] **4.11** Auth edge cases (md5 legacy, throttling, staff PIN lockout, offline login expiry, deactivated user, suspended tenant, 2FA per role, invitation reuse).
- [ ] **4.12** Role × route authorization matrix (data-driven from 04_TRD §3.8).
- [ ] **4.13** FormRequest fuzz (foreign-tenant ids, negative money, precision, Bangla digits).
- [ ] **4.14** `integrity:check` green after 60 simulated days of random operations, including offline pushes.
- [ ] **4.15** Keyboard-only e2e: complete a sale, attach a member with a PIN, collect a due, and print a card without touching the mouse. Context menu via `Shift+F10`.
- [ ] **4.16** Accessibility (axe: 0 serious), contrast of every scheme × mode token pair.
- [ ] **4.17** Performance budgets (04_TRD §6).
- [ ] **4.18** Security review (headers/CSP/SW scope, audits, gitleaks, uploads, public receipt, rate limits, device token handling).

## Phase 5: Legacy migration & launch (DIFFERENT FASHION)
- [ ] **5.1** On the **live** legacy DB: `SHOW TRIGGERS`, table existence checks, `SHOW CREATE TABLE`, latin1 inventory, external writers of `customer_order_list` → `docs/adr/0004-legacy-db-findings.md`. Use the `legacy_ro` user (needs §Out-of-repo L1–L4 done).
- [ ] **5.2** Confirm the branch codes/names for stores 7 and 10 with the owner.
- [ ] **5.3** `legacy:import --stores=7,10 --tenant=different-fashion` steps 1–16 + cross-cutting rules (05_Schema §12, [ADR 0002](adr/0002-legacy-migration-strategy.md)): store-generic (`config/legacy.php`), Extract/Transform/Load under `app/Legacy` + `app/Actions/Legacy`, `--dry-run`, `--fresh` full reload, arch tests (no `select *`, customer `username`/`passwd` never read). Transform unit tests for every M3 rule.
- [ ] **5.4** Rehearse (record duration; target < 15 min) on the latest dump → `reconciliation.csv` + `migration_report.csv` (incl. future-dated sales, duplicate phones, unparsed locations) → zero unexplained differences.
- [ ] **5.5** Pre-print membership cards for active customers (optional, owner's choice). Customers set their PIN on their first visit.
- [ ] **5.6** UAT: 2-day parallel run at one branch, including a deliberate internet-off hour.
- [ ] **5.7** Production infra (hardening, Nginx/FPM, MySQL, Redis, Supervisor, TLS, backups + restore drill, Sentry, uptime).
- [ ] **5.8** Cut-over runbook (announce → legacy read-only by revoking write grants → final dump → import → reconcile → enroll devices → go live; legacy read-only for 90 days).
- [ ] **5.9** Remove `LegacyMd5Hasher` + `legacy_id_maps` 180 days after go-live.

## Phase 6: Post-MVP (low priority, do not start before Phase 5 is done)
- [ ] **6.1** White-label: tenant branding (name, logo, brand colour → generated scheme with contrast check), branded receipts, cards and PWA manifest, custom domain.
- [ ] **6.2** SMS adapter (provider TBD) + receipt/reminder/OTP use cases.
- [ ] **6.3** Candidates: loyalty points, gift cards/coupons, purchase orders/returns, payment gateway integration, backup restore UI.

---

## Out-of-repo tasks (owner / server admin; not agent steps, do not block Phase 0)
Legacy containment per [ADR 0003](adr/0003-legacy-security-containment.md):
- [ ] **L1** (≤ 24 h) Delete `hidden.php`, `backup.php`, `error_log`, `upload_images/option.php`. Deny PHP in `/upload_images/`. Restrict or disable the `Backup` controller.
- [ ] **L2** (≤ 24 h) Rotate the DB app password, the FTP password and the CI `encryption_key`. Server config only.
- [ ] **L3** (≤ 1 week) Review access logs, DB grants and the FTP target for misuse. If found, apply ADR 0003 step 5.
- [ ] **L5** (dev machine) `sudo apt install php-gd php-redis` so mpdf (2.4) and the Redis cache/queue can be used locally.
- [ ] **L4** (≤ 1 week) Create the read-only `legacy_ro` DB user (IP-restricted) for 5.1 and the importer.

## Manual QA checklist (each release)
- [ ] 80 mm and 58 mm thermal: alignment, Bangla, logo, QR, barcode.
- [ ] CR80 card printer + A4 10-up: alignment and crop marks. The card barcode scans back into the POS.
- [ ] USB/Bluetooth scanner: rapid scans, no focus loss, card vs product barcode routing.
- [ ] Internet unplugged for 30 min during sales → all sales sync with no duplicates.
- [ ] Customer-facing PIN entry is readable at arm's length and hides the digits.
- [ ] Every scheme × light/dark × density on the key pages. 360 px phone.

## Open questions
1. ~~SMS provider~~ → **SSL Wireless first** (decided 2026-11-03, ADR 0007); `log` driver for development. Owner to confirm the account before go-live.
2. ~~Store → tenant grouping~~ → **DIFFERENT FASHION = stores 7 + 10** (decided 2026-10-29).
3. ~~VAT fields~~ → **standard fields** (BIN, per-line rate/amount, VAT summary). Mushak forms are a non-goal (decided 2026-10-29).
4. ~~Repo/CI~~ → **separate git repo, the owner pushes later**. CI host TBD; `composer ci` is provider-agnostic (decided 2026-10-29).
5. ~~Product name~~ → **none for now**. Use neutral names, white-label later (decided 2026-10-29).
6. ~~Branch codes~~ → **DF1 "Main", DF2 "Branch 2"** assumed for the demo (store 10 has one sale). Owner can rename in Settings.
7. Customer PIN is deferred to the next phase (ADR 0007); `member_auth=none` for all demo sales. Should the policy default stay `always` for every tenant, or only for DIFFERENT FASHION? (Default `always` assumed.)
8. Card stock: does the client have a CR80 card printer, or will they use A4 + lamination? (Both are supported. This affects which one we QA first.)
9. ~~Mockup v2 scope~~ → **decided 2026-10-30** ([ADR 0005](adr/0005-variants-and-exchange.md)): **in v1:** size × colour variants, in-POS exchange. **Out (post-MVP):** loyalty/points, gift cards/vouchers/coupons, purchase orders + purchase returns, SMS automation, Mushak forms, payment gateways, backup restore UI. Super Admin stays limited to 1.16 (provision + impersonate). Mockup update prompt: `docs/mockup/gemini_followup_v4.md`. Original list:
   - product variants (size × colour matrix)
   - purchase returns + purchase orders
   - in-POS exchange
   - Messaging centre (WhatsApp click-to-chat now; templates, automation rules and SMS UI need a provider → Phase 6.2)
   - customer import/merge, segments (RFM/lapsed)
   - reorder suggestions, dead-stock report, label designer
   - Super Admin console (tenants, impersonation, health)
   - backup restore UI
10. ~~Shifts / Z-report / cash in-out~~ → **dropped** (decided 2026-10-30). The legacy app has no shift, drawer, float or Z-report (checked `../different/project`). Replaced by the register Today summary (`F7`) + Daily cash summary report. Docs 00/01/02/04/05/06 updated.
11. ~~Migration scope~~ → **DIFFERENT FASHION only** (stores 7 + 10), reconfirmed 2026-10-30. The importer stays store-generic (ADR 0002 M1), but no other store is planned.
12. ~~Legacy security~~ → **standard containment** (decided 2026-10-30): [ADR 0003](adr/0003-legacy-security-containment.md). Done by whoever has server access: contain + rotate within 24 h, log review within 1 week. Tracked in §Out-of-repo tasks; it does not block Phase 0.

## Progress log
- 2026-10-29: Docs 00–06, CLAUDE.md and .cursorrules created from the legacy analysis.
- 2026-10-29: v1.1 docs. React chosen. Offline-first POS, keyboard-first desktop UX (context menus, top bar notifications/clock, colour schemes), customer location, customer number, membership cards, customer PIN, VAT fields. SMS deferred. Migration scoped to DIFFERENT FASHION. Repo folder renamed to `pos-rewrite`. Gemini mockup prompt in `docs/mockup/`. No code yet.
- 2026-10-31: Tenancy core 1.2–1.4, 1.6, 1.7 done (36 tests). Removed starter-kit account self-deletion (staff are deactivated via users.manage; history must keep its users). Sanctum `stateful` emptied (no SPA cookie auth, ADR 0001). `composer ci` green: 162 Pest, 72 Vitest.
- 2026-10-31: Phase 0 done (0.1–0.9, `composer ci` green: 127 Pest, 72 Vitest). 1.1 money/quantity twins done. ADR 0006 (login: business code). Disk was full: cleared npm/Docker build caches + unused libreoffice image (owner OK).
- 2026-10-30: Phase 0 started. 0.1 blocked on owner installs (ext-gd, ext-redis, Redis, Node 24). Root `index.html`, `scripts/`, `vendor/` moved to `tools/mockup/` (paths fixed in the build/test scripts and `docs/mockup/index.html`).
- 2026-10-30: Q9 decided: variants + exchange in v1 (ADR 0005); docs 01/02/04/05/06 updated; Gemini mockup prompt v4 written.
- 2026-10-30: Owner decisions: migrate DIFFERENT FASHION only (Q11). Shifts/Z-report/cash in-out dropped, since the legacy app has none (Q10): register Today summary + daily cash summary instead; `register_sessions`/`cash_movements` removed, `register_id` on sales/payments. Legacy security → ADR 0003 standard containment + §Out-of-repo tasks (Q12). Legacy DB findings ADR renumbered to 0004.
- 2026-10-30: Architecture review. ADR 0001: keep the hybrid (Inertia back office + standalone POS SPA on `/api/pos/v1`), POS moved off Inertia to its own Vite entry + static shell at `/pos`, device pairing codes, sync counter acquired last in the locking order. ADR 0002: legacy migration strategy (full reload, store-generic importer, cross-cutting transform rules from the code + dump schema survey). Docs 02/04/05/06 updated. Open questions 11–12 added. No code yet.
- 2026-10-29: Gemini mockup v1 reviewed (`docs/mockup/mockup_gap_review.md`, ~35 % of scope). Full-scope prompt `docs/mockup/gemini_prompt_final.md` written (legacy parity + new features). Open question 9 added.
- 2026-10-31: Phase 0 steps 0.5–0.9 done: tooling gates (Pint/Rector/Larastan 8/Oxlint/tsc strict/Vitest/Playwright config), `composer ci`, neutral branding + `.env.example`, public registration removed, Pest arch tests. Open TODO: gitleaks, Playwright run and Pest coverage in `ci` once tools/browsers/coverage driver exist.
- 2026-10-31: Steps 1.5 and 1.8 done. Middleware stack + route files, `/app` dashboard, 48 permissions, five system roles per tenant (discount caps Owner 10000 / Manager 2000 bp). `composer ci` green.
- 2026-11-02: Cleanup pass: dropped spec-section citations and over-long docblocks from code, removed starter boilerplate (Pest helpers, example tests), User model aligned with the others, branch select page made presentable, README rewritten. `DatabaseSeeder` now seeds a demo tenant/branch/owner (`owner@demo.test`) and a platform admin (`admin@demo.test`); `SeederTest` proves the owner reaches `/app`. All gates green.
- 2026-11-03: Legacy dump (2026-10-29) loaded into the dev MySQL as `legacy` and profiled. Phase D (demo release) written as a single phase; remaining Phase 2–5 work moves to the next phase (ADR 0007). SMS un-deferred.
