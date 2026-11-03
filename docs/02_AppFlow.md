# 02 — App Flow: Screens, Routes, Keyboard, State Machines

> Conventions
> - Web routes return **Inertia pages** (`resources/js/pages/**/*.tsx`, React).
> - `/app/api/...` = session-authenticated JSON for in-page interactions (back office).
> - `/api/pos/v1/...` = **device-token** (Sanctum bearer) sync API for the offline register (04_TRD §3.9).
> - Every `/app` route requires `auth`, `tenant.active` and `branch.selected` unless marked *(no branch)*.
> - The **permission** column uses exact names from 04_TRD §3.8. A denial gives a 403 page (pages) or `403 {message}` (JSON). Cross-tenant ids return **404**.
> - Route names (`{module}.{action}`) are consumed through Wayfinder-generated TS helpers. Never hard-code URLs.

---

## 1. App shell (desktop-app feel)

```
┌ TOP BAR ──────────────────────────────────────────────────────────────────────────────────────────────────┐
│ [≡] Logo/Tenant · Branch ▾ │ ⌕ Search or jump… (Ctrl+K) │ ● Online / ⚠ Offline · ⟳ 3 unsynced · last sync 2m │
│                              Register R1 · 12 sales today │ 🔔 4 │ ⏰ 10:42:15 AM · Wed 29 Oct │ EN/বাং │ 🎨 │ Avatar ▾ │
├ SIDEBAR (collapsible to icons, Ctrl+B) ─┬ CONTENT ──────────────────────────────────────────────────────────┤
│ ACTIVE ROLE  [Owner]  name · branch     │                                                                   │
│ SELL                                    │                                                                   │
│   POS Register       F1                 │                                                                   │
│   Sales History      G S                │                                                                   │
│   Returns & Exchange G T                │                                                                   │
│   Due Collection     G U                │                                                                   │
│ CUSTOMERS                               │                                                                   │
│   Customers          G C                │                                                                   │
│   Customer Statement G A                │                                                                   │
│   Membership Cards                      │                                                                   │
│   WhatsApp & SMS     G W                │                                                                   │
│ CATALOG & STOCK                         │                                                                   │
│   Products & SKUs    G I                │                                                                   │
│   Stock & Transfer   G K                │                                                                   │
│   Purchases (GRN)    G P                │                                                                   │
│   Vendor Directory   G V                │                                                                   │
│ INSIGHTS                                │                                                                   │
│   Analytics Dashboard  G D              │                                                                   │
│   Reports Directory  G R                │                                                                   │
│ ADMIN & SYSTEM                          │                                                                   │
│   Users & Roles                         │                                                                   │
│   Settings ▸         G ,                │                                                                   │
│   Audit Security Log                    │                                                                   │
├─────────────────────────────────────────┴───────────────────────────────────────────────────────────────────┤
│ STATUS BAR (desktop only, 28 px): Branch · User (role) · Sync ✓ │ ? Shortcuts · v1.0.0 (+ printer/scanner/DB)  │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
Sidebar = the baseline mockup (tools/mockup/baseline, commit 2078d6c): an "Active role" card, then permission-gated groups SELL · CUSTOMERS · CATALOG & STOCK · MONEY · INSIGHTS · ADMIN & SYSTEM with small uppercase headers (hidden when no item is permitted). MONEY (Accounts & Expense, Fixed Assets), Backup & Restore and SaaS Platform Admin join when those modules exist. Customer Statement is a later owner request added under CUSTOMERS. Stock adjustments (`/app/adjustments`) are folded into "Stock & Transfer" (no own item; the palette keeps it). Exactly one item is active (longest matching path; `/app` only exact). Footer: Shortcuts Guide `?`. Settings children: business, branches, registers, payment methods, taxes, units, roles, SMS.
Mobile (< 768 px): the sidebar becomes a bottom tab bar [Home · POS · Sales · Stock · More]. The top bar is reduced to logo · sync dot · 🔔 · avatar.
```

### 1.1 Top bar elements
| Element | Behavior | Key |
|---|---|---|
| Branch switcher | Owner only (`branches.switch`): dropdown of the user's branches; everyone else sees their branch name as plain text. Switching confirms if the POS cart is non-empty | `Alt+B` |
| Global search / command palette | Pages, actions ("New sale", "Receive purchase"), products, customers (`#12345678` = customer no.), invoices (`S-…`), settings | `Ctrl+K` |
| Connectivity + sync pill | `● Online` (success) · `⚠ Offline` (warning) · `⟳ n unsynced` (accent, pulses while syncing) · `✕ Sync error` (danger). Click opens the **Sync center** popover (queue, last pull/push, retry, conflicts) | `Alt+Y` |
| Register pill | "R1 · 12 sales today". Click: Today summary, Sync center | — |
| **Today ticker** | Baseline mockup pill "● Today ৳… · N sales": net sales (after returns) and completed invoices of the business day at the current branch. Shared prop `today: {total_paisa, sales}` (lazy, 60 s cache per tenant + branch + day); null and hidden without `dashboard.view`. Click opens the dashboard | — |
| **Notifications 🔔** | Badge count. The panel has tabs **All · Alerts · Exports**. Types: low stock, sync exception, member PIN failure / unverified member, export ready, backup failed, stocktake ready to review, transfer incoming, due over 90 days, new login from a new device. Mark read, mark all read, click to go to the item. Real-time via polling every 60 s (Reverb later) | `Alt+N` |
| **Clock** | Live time (tenant timezone) + day/date. Hover shows the business day. Click opens a mini calendar | — |
| Language | EN / বাং toggle | `Alt+L` |
| **Theme 🎨** | Popover: mode **Light / Dark / System** + **colour scheme** swatches (03_UI_Spec §1.4) + density **Comfortable / Compact** | `Alt+T` |
| Avatar | User chip as the mockup (initials, name, first role); opens Profile, Switch user (staff PIN), Keyboard shortcuts (`?`), Lock screen (`Ctrl+L`), Log out | — |

### 1.2 Right-click context menus (desktop vibe)
A custom context menu replaces the browser menu on **app surfaces only**. It is never used inside text inputs: native copy/paste stays there. `Shift+F10` or the `≣ Menu` key opens the same menu for the focused item (keyboard parity). Long-press opens it on touch. The menu shows icons, labels, **shortcut hints**, separators and submenus. Destructive items are last and red. Items the user lacks permission for are hidden, not disabled.

| Surface | Context menu items |
|---|---|
| Any table row (generic) | Open `Enter` · Open in new tab `Ctrl+Enter` · Edit `E` · Duplicate · Copy ▸ (ID, number, row as text) · Export selected · Deactivate/Delete `Del` |
| Product row / POS product tile | Add to cart `Enter` · Add qty… `*` · View stock by branch · Movement history · Print label `Ctrl+Shift+P` · Edit product · Copy SKU/barcode |
| POS cart line | Change qty `F3` · Line discount `F6` · Price override (perm) · Remove `Del` · View product |
| Customer row / POS customer chip | Open profile · New sale for customer · Collect due · Statement · Print membership card · Report card lost · Reset PIN (perm) · Copy customer no. · Share WhatsApp reminder |
| Sale row | Open · Reprint receipt `Ctrl+P` · Share receipt link · Return items · Exchange items · Collect payment · Void (perm, same day) |
| Due row (`/app/dues`) | Collect due `Ctrl+D` (also the row's Collect button and a row click; Enter opens the customer's dues page) |
| Purchase / transfer / stocktake row | Open · Continue (draft) · Receive · Cancel |
| Dashboard widget | Refresh · Change period ▸ · Open full report · Export |
| Sidebar item | Open · Open in new tab · Pin to favourites |
| Empty canvas area of a list | New `N` · Import · Export all · Refresh `R` |

### 1.3 Global keyboard map (shown in the `?` overlay; every page also shows key hints on buttons)
| Key | Action |
|---|---|
| `Ctrl+K` | Command palette |
| `F1` | Go to POS Register |
| `G` then `D/S/C/I/P/M/R/,` | Go to Dashboard/Sales/Customers/Inventory/Purchases/Money/Reports/Settings |
| `/` | Focus the page search |
| `N` | New record (on list pages) |
| `↑ ↓` or `J K` | Move row selection · `Enter` open · `Space` toggle select · `Shift+↑↓` extend selection |
| `E` / `Del` | Edit / delete the selected row |
| `Ctrl+S` / `Ctrl+Enter` | Save the form |
| `Esc` | Close dialog / clear selection / back |
| `[` `]` | Previous / next page of a list |
| `Alt+←` | Back |
| `Shift+F10` | Context menu for the focused item |
| `Ctrl+B` | Toggle sidebar |
| `Ctrl+L` | Lock screen (staff PIN to unlock) |
| `?` | Shortcut overlay |

Rules: shortcuts never fire while typing in an input, except `Esc`, `F-keys`, `Ctrl+…` and `Enter`. Single-letter shortcuts are page-scoped. Every shortcut is also discoverable in the command palette.

## 2. Route table

| # | Method | URI | Route name | Page / response | Permission |
|---|---|---|---|---|---|
| **Auth** |
| 1 | GET/POST | `/login` | `login` | `auth/login`: one identifier field `login` (email / username / phone) + **business code** (`business`) shown for non-email identifiers, remembered per browser in cookie `pos_tenant` (ADR 0006); the `business` prop is `{code, name, logo_url, tagline}` for a known remembered code, else null. Brand layout (`layouts/auth/auth-brand-layout.tsx`, every auth page), ported from the mockup login screen (`tools/mockup/baseline`, mockup commit 2078d6c, demo-role chips omitted): one centred card with the brand header (business logo where the mockup's mark sits, else the generic mark; business name, else the app name; tagline), then the page; below the card the EN/BN switch (`Alt+L`, cookie `locale`, read by `SetLocale` for guests; Fortify routes run the `locale` middleware) and a staff-only line. Fields are 36 px from md, 44 px below. Form: password show/hide, Caps Lock warning, error summary (`role=alert`, focused on error) plus inline errors. Same error for unknown business / user / password; 5 attempts per minute per login+IP (form post: redirect with `errors.login` = `auth.throttle` and the seconds, shown as a countdown that disables submit; JSON: 429; every attempt written to `login_activities`); a deactivated user is refused after a correct password. A migrated user whose password is still the legacy MD5 is upgraded to argon2id on this login and sent to `password.change` | guest |
| 2 | POST | `/logout` | `logout` | redirect | auth |
| 3 | GET/POST | `/forgot-password`, `/reset-password/{token}` | `password.*` | `auth/forgot-password`, `auth/reset-password` | guest |
| 4 | GET/POST | `/two-factor-challenge` | `two-factor.login` | `auth/two-factor-challenge` | pending 2FA |
| 5 | GET/POST | `/invite/{token}` | `invites.accept` | `auth/accept-invite` | signed |
| 6 | GET | `/app/branch/select` | `branch.select` | `branch/select` *(no branch)*: keyboard list (arrows, Enter, 1-9), last used branch first; with no session branch the last used one (`users.preferences.last_branch_id`) is restored instead; a user without `branches.switch` is redirected to their pivot-default branch (else last used, else first) and never sees the picker | auth |
| 7 | POST | `/app/branch/{branch}/switch` | `branch.switch` | redirect; session `branch_id` + `last_branch_id`; 403 without `branches.switch`; unassigned, inactive or foreign branch = 404 | `branches.switch` |
| 9 | GET/PUT | `/app/password/change` | `password.change`, `password.change.update` | `auth/change-password` (forced after a legacy login: every other `/app` page and `/app/api` call is blocked by `password.changed` until done; JSON answers 409 `password_change_required`) | auth |
| 8 | POST | `/app/lock`, `/app/unlock` | `lock.*` | lock screen | auth |
| **Dashboard & shell** |
| 10 | GET | `/app` | `dashboard` | `dashboard` | `dashboard.view` |
| 11 | GET | `/app/api/notifications` · POST `/{id}/read` · POST `/read-all` | `api.notifications.*` | JSON | auth |
| 12 | GET | `/app/api/search?q=` | `api.search` | JSON (palette): products (name/sku/barcode), customers (`#number`, phone, name via CustomerSearch), sales (by number); min 2 chars, 8 per group | auth (results permission-filtered) |
| 13 | PATCH | `/app/api/preferences` | `api.preferences` | JSON (theme, scheme, density, locale, bn_digits; the user can only change themselves, `UserPolicy::updatePreferences`) | auth |
| **POS** |
| 20 | GET | `/pos` | `pos.index` | **static Blade shell** `pos.blade.php` + Vite entry `resources/js/pos/main.tsx` (standalone SPA, **not** an Inertia page; no server data, no session; precached by the service worker). See ADR 0001 | none on the shell (static, no data). `pos.sell` is enforced by local staff unlock and on every pushed op |
| 20a | GET | `/app/pos?customer={uuid}` | `pos.register` | `pos/register`: the **online** register (ADR 0007) with payment methods, categories, settings, discount cap, optional pre-attached customer | `pos.sell` |
| 20b | POST | `/app/pos/sales` | `pos.sales.store` | JSON: CompleteSale (`origin=web`); 201 new, 200 for a resubmitted `client_uuid`; business-rule failures 422 `{message, detail, code}` | `pos.sell` |
| 20c | GET | `/app/api/pos/products?q=&category=` | `pos.products` | JSON: families with all variants and stock at the current branch; an exact barcode/SKU hit returns just that product. No cost | `pos.sell` |
| 20d | GET | `/app/api/pos/customers?q=` · `/app/api/pos/customers/{uuid}` | `pos.customers` · `pos.customers.show` | JSON: picker via CustomerSearch; card with group discount, balance, effective credit limit, spendable advance. No PIN data | `pos.sell` |
| 20e | GET | `/app/api/pos/today?register_id=` | `pos.today` | JSON: today's sales of the register (or of the cashier), received by method, last 10 sales | `pos.sell` |
| 20f | POST | `/app/api/pos/authorize` | `pos.authorize` | JSON: manager login + password/staff PIN + permission → 120 s encrypted approval token for this cashier. Throttled 10/min | `pos.sell` (the manager needs the asked permission) |
| 21 | POST | `/app/settings/devices/pairing-codes` | `devices.pairing-codes.store` | JSON: one-time pairing code (8 chars, 10 min TTL, single use) + QR for a chosen register | `devices.manage` |
| 21a | POST | `/api/pos/v1/enroll` | `pos-api.enroll` | exchange a pairing code for a device token bound to (tenant, branch, register). Rate limited 5/min per IP | pairing code |
| **Device sync API** (bearer device token, ability `pos:device`) |
| 22 | GET | `/api/pos/v1/bootstrap` | `pos-api.bootstrap` | settings, register, staff verifiers, tender list, tax, sequence high-water mark | device |
| 23 | GET | `/api/pos/v1/pull?entity=&cursor=` | `pos-api.pull` | delta changes + tombstones (products, prices, stock, customers-min, cards, groups) | device |
| 24 | POST | `/api/pos/v1/push` | `pos-api.push` | batch of outbox ops → per-op result (ok / duplicate / rejected / accepted_with_flags) | device |
| 25 | POST | `/api/pos/v1/customers/verify-pin` | `pos-api.customers.verify-pin` | online PIN check → short-lived member token | device + staff |
| 26 | POST | `/api/pos/v1/customers/{customer}/pin` | `pos-api.customers.pin` | set PIN (online only) | device + `customers.update` |
| 27 | POST | `/api/pos/v1/authorize` | `pos-api.authorize` | Manager PIN override token (60 s, one permission, one cart hash) | device |
| 28 | GET | `/api/pos/v1/ping` | `pos-api.ping` | 204 (connectivity probe) | device (until step 2.22 the route is unauthenticated; it carries no data) |
| **Sales** |
| 30 | GET | `/app/sales` | `sales.index` | `sales/index` (mockup columns incl. discount, VAT, branch, register, cashier, sync status; status chips, View/Date presets, More filters `cashier`/`sync`/`returns`, totals row over the filtered set, CSV of the page) | `sales.view` |
| 31 | GET | `/app/sales/{sale}` | `sales.show` | `sales/index` with the invoice dialog open (`detail` prop; the list props are lazy, so a row click reloads only `detail`; the query string keeps the list state). Cost & batches only with `products.view_cost`; Resend SMS (failed receipt SMS → `messages.resend`) only with `settings.manage` | `sales.view` |
| 32 | GET | `/app/sales/{sale}/receipt?paper=80\|58\|a4&lang=&print=1` | `sales.receipt` | print view (Blade, `@page` per paper); `format=pdf` answers 501 until mPDF | `sales.view` |
| 33 | POST | `/app/sales/{sale}/void` | `sales.void` | redirect | `sales.void` |
| 34 | POST | `/app/sales/{sale}/payments` | `sales.payments.store` | redirect (allocates to this invoice, excess = advance) | `payments.collect` |
| 34a | POST | `/app/sales/{sale}/share` | `sales.share` | JSON `{url}`: gives the sale a public token if it has none (imported sales) | `sales.view` |
| 35 | GET | `/r/{token}` | `receipts.public` | public receipt (no customer phone, number, BIN or address; no cost/PIN), throttled 30/min, `noindex` | public |
| 36 | GET | `/app/sync-exceptions` | `sync-exceptions.index` | `sales/sync-exceptions` (flagged offline sales: resolve/acknowledge) | `sales.void` |
| **Returns** |
| 40 | GET | `/app/returns` | `returns.index` | `returns/index` (ledger: returned items, return amount, exchange net, refund method, reason chips, cashier; totals row). Return Wizard / POS Return (F11, `n`) open the wizard dialog: lazy `wizard` prop reloaded with `?wizard_q=` / `?wizard_sale=` (`returns.create`); it posts to `returns.store`, a direct exchange continues on `exchanges.create` | `returns.view` |
| 41 | GET/POST | `/app/returns/create?sale=` · `/app/returns` | `returns.create/store` | `returns/create` | `returns.create` |
| 42 | GET | `/app/returns/{return}` | `returns.show` | `returns/show` | `returns.view` |
| 42a | GET | `/app/returns/{return}/receipt?paper=` | `returns.receipt` | return / exchange receipt print view | `returns.view` |
| 43 | GET/POST | `/app/exchanges/create?sale=` · `/app/exchanges` | `exchanges.create/store` | `returns/create` in exchange mode (return lines + replacement lines + difference) | `returns.create` + `pos.sell` |
| 44 | POST | `/api/pos/v1/exchanges/quote` · `/api/pos/v1/exchanges` | `pos-api.exchanges.quote/store` | server-valued return + replacement sale with the device number (online only, ADR 0005) | device + `returns.create` |
| **Customers & membership** |
| 50 | resource · GET | `/app/customers?q=&sort=&per_page=&filter[view]=overdue\|inactive&filter[group]=&filter[balance]=due\|zero\|advance&filter[card]=yes\|no&filter[date]=today\|7d\|30d\|90d\|year` · `/app/customers/export.csv?(same query)` | `customers.*` · `customers.export` | `customers/index` = Customers & Members (baseline mockup): 10 columns (Customer #, Name EN/BN + shop line, Mobile, Area & Address, Tier/Group chip, Balance/Due, Credit limit, Card status, PIN status, Actions), View / Date (last purchase) / group chips / More filters drawer, Columns · Export (CSV, BOM, the filtered list) · Print, totals row over the whole filtered list (`totals` = count, balance, credit_limit), rows per page. **Add and edit are a dialog** (`components/customers/customer-dialog.tsx`, mockup form sections 1–3 + dates/notes): `create` renders `customers/index` with `dialog: 'create'`; `edit` renders `customers/show` with `editing: true` (deep links; closing returns to the plain URL). `customers/show` = 360 profile: banner (Collect due · SMS (statement summary, `customers.statement.sms`) · New sale · Statement · Edit), `kpis` (lifetime_spend net of returns, visits, last_visit_at, due, credit_limit/used/available; sales figures over the current branch, all branches with `reports.all_branches`; null without `sales.view`), tabs Overview · Sales · Returns · Payments · Messages, membership card panel on the right. Keys: list `N` add, `/` search, `Shift+E` export, `Shift+F` more filters; profile `N` new sale, `E` edit, `C` collect, `M` SMS, `T` statement; dialog `Ctrl+Enter`/`Ctrl+S` save, `Esc` (asks if changed) | `customers.view` (export too) / `create` / `update` / `delete` |
| 51 | GET · POST | `/app/customers/statement` · `/app/customers/{customer}/statement?from=&to=` · `POST …/statement/sms` | `customers.statement.index` · `customers.statement` · `customers.statement.sms` | `customers/statement` (search, period chips, totals row, print CSS; SMS summary via `App\Sms\SendCustomerStatement`) | `customers.view` (+ `settings.manage` for SMS) |
| 51a | GET · POST | `/app/customers/search?q=` · `/app/customers/check-phone?phone=&except=` · `POST /app/customers/{customer}/deactivate` · `/merge` | `customers.search` · `customers.check-phone` · `customers.deactivate` · `customers.merge` | JSON picker / live duplicate-phone check; redirects | `customers.view` · `customers.delete` |
| 52 | GET/POST | `/app/customers/{customer}/collect` | `customers.collect` | not built: due collection lives on `dues.show` (55a/55b) | `payments.collect` |
| 53 | POST | `/app/customers/{customer}/pin/reset` | `customers.pin.reset` | redirect (reason required) | `customers.reset_pin` |
| 54 | POST | `/app/customers/{customer}/pin/unlock` | `customers.pin.unlock` | redirect | `customers.reset_pin` |
| 55 | GET | `/app/dues` | `dues.index` | `dues/index` (aging tiles 0–30/31–60/61–90/90+ with debtor counts, credit-limit meter, last purchase, group chips, More filters `bucket`/`credit`, totals row; row click / Collect / Ctrl+D open the collect dialog: lazy `collect` prop reloaded with `?collect=<customer uuid>`, posting to `dues.payments.store`) | `customers.view` |
| 55a | GET | `/app/dues/{customer}?amount=` | `dues.show` | `dues/show`: open invoices oldest first, allocation preview, recent payments (customer uuid) | `customers.view` |
| 55b | POST | `/app/dues/{customer}/payments` | `dues.payments.store` | redirect to `dues.show?receipt=` (oldest first, or the given `allocations`) | `payments.collect` |
| 55c | GET | `/app/payments/{payment}/receipt?paper=` | `payments.receipt` | money receipt print view | `payments.collect` or `payments.pay` |
| 55a | GET | `/app/messages?tab=composer\|templates\|log` | `messages.index` | `messages/index` = WhatsApp & SMS hub (baseline mockup screen 10): tabs Composer & Dispatch · Templates Library · Scheduled (next phase, disabled) · Message Log · SMS Settings (link to row 100a). Composer (`composer` prop, only with `settings.manage`): channel SMS (WhatsApp shown disabled, next phase), recipient target (all with a due / one customer via `customers.search` / a customer group / all, with counts of reachable customers), template from the tenant's SMS templates, variable chips `{name} {shop} {due} {number}` inserted at the caret, characters + SMS parts (GSM-7 160/153, Unicode 70/67) and the cost estimate from `tenants.settings.sms.cost_per_segment`, phone preview; Send (`Ctrl+Enter`) asks to confirm with recipient count and total cost. Log: search, status chips, cost per message, Resend (`R`) on failed rows. Keys `Alt+1..3` switch tabs | `customers.view` (log) / `settings.manage` (composer) |
| 55c | POST | `/app/messages/send` (`target` = `customer`\|`group`\|`due`\|`all`, `customer` uuid, `group_id`, `body` ≤ 1000) | `messages.send` | redirect + toast (queued count). `App\Sms\SendAdHocSms` queues one logged `SendSms` job per reachable customer (named, active, not merged, valid mobile), event `manual`, via the `SmsGateway` only; audit `sms.ad_hoc_sent` keeps audience + counts, never text or numbers. Throttle 10/min. Tenant-scoped `exists` rules | `settings.manage` |
| 55b | POST | `/app/messages/{message}/resend` | `messages.resend` | redirect back (failed messages only) | `settings.manage` |
| 56 | resource | `/app/customer-groups` | `customer-groups.*` | `customer-groups/*` | `customers.update` |
| 57 | GET | `/app/membership-cards?q=&filter[group]=&filter[status]=issued\|not_issued\|lost&page=` | `cards.index` | `cards/index` = Membership Card Studio & Production (baseline mockup): every customer is a member (active, not walk-in, numbered), 40 per page, search name/BN name/shop/phone/number/`MC…` code; props `members[]` (uuid, name, name_bn, shop_name, phone, customer_no formatted, member_since, group, `card` = active card else latest: id, code, status, issue_no, print_count, printed_at), `meta`, `totals` (members, issued, not_issued, lost), `groups` (id, name, count), `brand` (name, logo_url, phone, branch, bin), `can` (issue, customers). Member picker (`/`, ↑↓; first row "All members" = every listed member; Enter shows only that member, Space selects; the selection is the print queue: one member → single flip card, several/all → card gallery), flip (`F`), card number masked `•••• 0017` on screen until hover/focus or Reveal number (`R`; print always full), QR = `name|shop|number`, CR80 PVC / A4 10-up (`Alt+1`/`Alt+2`), bulk print queue, Print Batch (`P`), press export (bleed PDF ± crop marks, CSV `Shift+E`), Report lost → Reissue | `cards.view` |
| 58 | POST | `/app/membership-cards/issue` | `cards.issue` | issue for customer uuids (`customers[]`) → back with toast | `cards.issue` |
| 59 | POST · GET · GET · POST | `POST /app/membership-cards/prepare` · `/print?ids=&layout=cr80\|bleed\|a4&crop=0\|1` · `/export.csv?ids=` · `POST /printed` | `cards.prepare` · `cards.print` · `cards.export` · `cards.printed` | print-ready HTML with `@page` (browser print dialog saves the PDF; only active cards): CR80 85.6×54 front/back pages, bleed 91.6×60 (crop marks on a 101.6×70 slug page), A4 10-up (2×5, fronts then column-mirrored backs per sheet, crop marks by default) · press data CSV · records print_count (`a4` → `a4_10`). GET print/export and `printed` take existing card `ids` only (≤500) and never issue (404 when none is active). `POST prepare` (`customers` uuids and/or `ids`, ≤500) issues a missing card per customer first (`EnsureActiveCard`; a lost card is never reused; walk-in/inactive skipped; unknown or foreign uuids are not found → 404 when nothing is printable) and returns JSON `{card_ids}`; the studio POSTs prepare, then opens the GET URL with those ids | `cards.issue` |
| 60 | POST | `/app/membership-cards/{card}/lost` · `/reissue` | `cards.lost/reissue` | redirect | `cards.issue` |
| 61 | GET | `/app/api/geo/search?q=&level=` · `/app/api/geo/{level}?parent=` | `api.geo.*` | JSON location lookup (cached, global) | auth |
| **Inventory** |
| 70 | resource + import/export | `/app/products?q=&sort=&per_page=&filter[category]=&filter[brand]=&filter[status]=active\|inactive\|low&filter[stock]=in\|low\|out&filter[added]=today\|7d\|30d\|90d` · `/app/products/create` · `/app/products/{product}/edit` | `products.*` | `products/index` = Product Catalog & Inventory Hub (baseline mockup): sub-tabs Products & SKUs · Category Management (→ `categories.index`) · Bulk Barcodes Generator (label printing is step 76, so a "later step" toast); list columns Photo (placeholder when no image), SKU, Barcode, Name EN + BN line, Category, Cost (`products.view_cost` only), Retail, one stock column per branch + Total (`branches`: every active branch with `reports.all_branches`, else the current branch only), status chip (in/low/out of stock, inactive, not tracked), row actions (label, edit); View (default/low/out/inactive) · Date (added) · category chips · More filters (brand, stock level, status); `totals` over the whole filtered list (sale_price, cost_price, stock per branch, total_stock); Columns · Export (CSV of the page, `reports.export`) · Print; rows per page. **Add and edit are a dialog** (`components/products/product-dialog.tsx`): `create`/`edit` render `products/index` with `editor` = `{product: null\|detail, families}` and `form` (categories, brands, units, taxRates, vendors, variantOptions), so deep links work; opened from the list, closing goes Back; opened by a deep link, closing replaces the URL with the list. `store`/`update`/`destroy` redirect to the list with the request's list query (`q`, `sort`, `page`, `per_page`, `filter`) kept; the dialog posts to `…?{list query}` with `replace`. Photo: `image` (png/jpg/webp ≤ 4 MB) stored on the `public` disk under `products/`, `remove_image`, or **`existing_image`** = a path already on one of this tenant's live products (tenant-scoped `exists`; "Reuse uploaded" opens a picker over `GET /app/products/images?q=` = `products.images`, JSON `{images: [{path, url, name, uses}]}`: distinct `image_path` of the tenant's products, newest first, max 60, search by name / BN name / SKU prefix, `products.view`). The product points at the same file (no copy); a replaced or removed photo file is deleted only when no product or family still uses it. Keys: list `N` add, `/` search, Enter edit; dialog `Ctrl+Enter`/`Ctrl+S` save, `Esc` (asks if changed) | `products.view` / `create` / `update` / `delete` |
| 71 | resource | `/app/products/categories`, `/app/products/brands` (inline CRUD; `categories.*`, `brands.*`), `/app/products/families/{family}` (`families.show/variants/attach`), `/app/variant-options` (JSON chips) | … | `catalog/*`, `products/family` | `products.update` |
| 72 | GET | `/app/stock?q=&sort=&per_page=&filter[category]=&filter[status]=low\|out\|ok&filter[moved]=today\|7d\|30d\|90d\|idle_90d` · `/app/stock/{product}/movements` | `stock.*` | `stock/index` = Stock Management & Transfers (baseline mockup): header Stock Adjustment (→ `adjustments.create`, key `N`) · Branch Transfer and Stocktake Count (shown with `stock.transfer`/`stock.count`, `aria-disabled` + "next phase" toast until steps 74/75) · Columns · Export · Print; columns Photo, SKU, Name, one stock column per branch (same rule as the product list) + Total Combined, Unit Cost and Stock Value at FIFO cost summed over the shown branches (`products.view_cost`), row Transfer (deferred) / Adjust (→ `adjustments.create?product={uuid}`); View (default/low/out) · Date (last stock movement at this branch; `idle_90d` = none in 90 days) · category chips (one scrolling line, long names truncated) · More filters (stock health); `totals` (stock per branch, total_stock, cost_price, value) over the filtered list; Enter opens movements | `stock.view` |
| 73 | resource | `/app/adjustments` · `/app/adjustments/create?product={uuid}` | `adjustments.*` | `adjustments/*`; `create` with `product` (a stock-tracked product of this tenant) starts with that line (`prefill`) | `stock.adjust` |
| 74 | resource + `post` | `/app/stocktakes` | `stocktakes.*` | `stocktakes/*` | `stock.count` |
| 75 | resource + `send/receive` | `/app/transfers` | `transfers.*` | `transfers/*` | `stock.transfer` |
| 76 | GET/POST | `/app/labels` | `labels.*` | `labels/index` → PDF | `products.view` |
| **Purchases** |
| 80 | resource + `receive/cancel/payments` | `/app/purchases` | `purchases.*` | `purchases/*`; the list's branch chips (`filter[branch]=all\|{id}`) exist only with `reports.all_branches` and default to All Branches; other branches' bills are read-only | `purchases.*`, `payments.pay` |
| 81 | resource + statement | `/app/vendors` | `vendors.*` | `vendors/*`; list filters `filter[status]`, `filter[hub]`, `filter[ordered]=today\|7d\|30d\|90d\|idle_90d` (last received order, the "Date:" select); Actions always shows Pay Vendor with `payments.pay` (disabled "Nothing payable" at balance ≤ 0) | `vendors.*` |
| **Money** |
| 90 | resource | `/app/cash-book`, `/app/accounts`, `/app/transaction-categories`, `/app/fixed-assets` | … | `money/*` | `money.*` |
| **Reports** |
| 95 | GET | `/app/reports` · `/app/reports/{report}` | `reports.*` | `reports/index`, `reports/show` | `reports.view` (+ `reports.profit` for cost/profit) |
| 96 | POST | `/app/reports/{report}/export` | `reports.export` | CSV streamed as a download (queued export deferred; D4.2) | `reports.export` |
| **Settings** |
| 100 | GET/PUT | `/app/settings/business` (profile, VAT BIN, locale, timezone, currency, tax-inclusive, back-date days, return window, default credit limit, customer PIN policy, offline credit cap) | `settings.business`, `settings.business.update` | `settings/business` | `settings.manage` |
| 100h | — | **Settings hub** (baseline mockup screen 21, `layouts/settings/layout.tsx`): every business settings page (rows 100–104) and the account pages (profile, security, appearance) render inside it: header "Store & System Settings" with Save Changes (`Ctrl+S`, the page hands its form over via `useSettingsSave`), the unsaved-changes bar (Discard / Save), the left Configuration menu in the mockup's order (Business Profile, Branches, Registers & Devices, Tax & VAT, Units, Payment Methods, Customer PIN Policy → `business#pin-policy`, Membership Card Design → `/app/membership-cards`, Messaging Providers → SMS, Returns & Exchanges → `business#sales-rules`, Appearance, Language & Regional → `business#regional`, Keyboard Shortcuts → the `?` overlay, Users & Roles, Audit Log, then My account: Profile, Security; Receipt & Printers, Invoice Numbering, Notifications, Data Import & Export are shown disabled "Next phase"); items need the page's permission. The audit page keeps its own layout | as each page |
| 100c | POST/DELETE | `/app/settings/business/logo` (PNG/JPG/WebP ≤ 2 MB, no SVG; replace deletes the old file; audit `settings.logo_updated`) | `settings.business.logo`, `settings.business.logo.destroy` | redirect + toast. The logo shows at sign-in, in the top bar (shared prop `tenant: {name, logo_url}`, null outside a tenant), on receipts and membership cards | `settings.manage` |
| 100a | GET/PUT | `/app/settings/sms` | `settings.sms.edit`, `settings.sms.update` | `settings/sms` (provider, credentials, price per SMS part `cost_per_segment` in paisa, default ৳0.35, for the composer estimate only; templates; test SMS) | `settings.manage` |
| 100b | POST | `/app/settings/sms/test` | `settings.sms.test` | redirect + toast (sends a test SMS now) | `settings.manage` |
| 101 | GET + POST/PUT | `/app/settings/branches[/{branch}]`, `/app/settings/registers[/{register}]` (+ **devices**: list/revoke, later) | `branches.index/store/update`, `registers.index/store/update`, `devices.*` | `settings/branches`, `settings/registers` (list + dialog form) | `settings.manage`, `devices.manage` |
| 102 | resource | `/app/settings/users` (list, create, edit, `POST {user}/deactivate`, `POST {user}/pin`; invite later) | `users.index/create/store/edit/update/deactivate`, `users.pin.reset` | `settings/users/index` = Users & Roles (baseline mockup screen 20) on one page: staff table (role chips, assigned branch, last active, status + PIN state, Reset PIN), the Add/Edit Staff dialog (`create`/`edit` render the same page with `dialog` = `create`\|`edit`; fields as before plus an optional initial 4–6 digit register PIN) and the permission matrix below. Reset PIN (`ResetStaffPin`: argon2id, clears the lockout, audit `pin.reset` without the PIN; throttle 20/min). Keys `N` add, `E` edit, `P` reset PIN, `Shift+R` jump to the matrix | `users.manage` |
| 103 | GET + POST/PUT/DELETE | `/app/settings/roles[/{role}]` (permission matrix; system roles keep their name, the Owner keeps every permission, custom roles can be created and deleted when unused) | `roles.index/store/update/destroy` | `roles.index` is a deep link: it renders `settings/users/index` with `focus: 'roles'` (scrolled to and focusing the matrix: ✓ / — toggles per role, plain-language labels, per-role Save) | `users.manage` |
| 104 | GET + POST/PUT | `/app/settings/payment-methods`, `/taxes`, `/units` | `payment-methods.*`, `taxes.*`, `units.*` | `settings/payment-methods`, `settings/taxes`, `settings/units` | `settings.manage` |
| 105 | GET/PUT | `/app/settings/receipt`, `/app/settings/card` (card template text/colours) | … | `settings/*` | `settings.manage` |
| 106 | GET | `/app/settings/audit` (filters: date range, user, record type, text; details drawer) · `/app/settings/backups` | `settings.audit` | `settings/audit` | `audit.view` / Owner |
| **Profile** |
| 110 | GET/PUT | `/app/profile[/password\|/pin\|/2fa]` | `profile.*` | `profile/*` *(no branch)* | auth |
| **Platform** |
| 120 | GET (placeholder `/platform` = `platform.index`), resource + impersonate | `/platform/tenants` | `platform.*` | `platform/*` | `is_platform_admin` (`EnsurePlatformAdmin`) |

## 3. Auth & session flow
```
Login ─invalid→ generic error · throttled→ countdown · inactive/suspended→ message
      ─2FA→ challenge ─ok→
      ─ok→ 1 branch → /app · >1 → /app/branch/select · 0 → "No branch assigned"
Legacy user (md5) → verify → rehash argon2id → must_change_password if weak → forced /app/profile/password
Lock screen (Ctrl+L or 5 min idle on POS, setting) → staff PIN or password to unlock; works offline on an enrolled register.
Staff PIN switch (POS): the session stays, active_operator changes, auto-revert after 10 min idle.
```

## 4. POS Register

### 4.1 Layout (desktop ≥ 1280 px, also tablet landscape)
```
┌ Scan / search (always focused) [F2] ────────────── Customer: [F4] #1000017-4 Rahim ✓PIN · Retail · Due ৳1,200 ┐
│ Category chips [Alt+1..9]                                   │ CART (selected line highlighted)               │
│ Product grid / result list (↑↓ to move, Enter to add)       │  1 Panjabi Cotton M   2 × ৳1,450   −৳100  2,800 │
│  tiles show: image · name · price · stock badge             │  2 Jeans Slim 32      1 × ৳1,890          1,890 │
│                                                             │ ─────────────────────────────────────────────  │
│                                                             │ Subtotal 4,790 · Discount −100 · VAT 0          │
│                                                             │ TOTAL                          ৳ 4,690.00      │
│                                                             │ [F8 Hold] [F6 Disc] [F7 Today     ]           │
│                                                             │ [         F9  PAY          ]                   │
├ Key hints bar: F2 Search · F3 Qty · F4 Customer · F6 Discount · F8 Hold · F9 Pay · Del Remove · Esc Esc Clear ┤
```
Built to the baseline mockup (2026-11-03), reworked on owner feedback the same day: left = scan field (F2) + **Browse catalogue (`Alt+G`)** + Price Check (F5) + Return (F11); below it the **bill ledger** is the main view (no., item + SKU/variant, unit price struck through when discounted, − qty +, discount chip, VAT amount + rate, line total, remove; "Cart is empty" with a Browse button when empty; the completed-sale card after payment). No product tiles by default: typing shows up to 8 hits (photo, name, code, variants, stock badge, price) in a **dropdown under the field** (↑↓ move, Enter adds, "Show all in the catalogue" opens the grid); **Browse catalogue** (or that link) opens the **browse surface** over the ledger: header (title / `Search: "…"`, count, Back to cart (Esc)), category chips, product tiles with photo (SKU chip, stock badge, barcode or "Variants", price); typing while it is open filters the grid, ↓ from the field enters it. Adding a plain product, or **Add in the variant picker**, closes the surface and returns to the cart; cancelling the picker keeps the surface. Esc peels one layer: completed card → typed search → browse surface → "Clear the bill?" (an Esc a closing dialog consumed does nothing else). Rules in `pos/register/browse.ts`. Function bar (Hold F8 · Held List ⇧F8 · Customer F4 · Line Disc F6 · Order Disc ⇧F6 · Collect Due Ctrl+D · Return/Exch F11 · Clear); right = customer strip (amber "attach customer" while walk-in), held-bills banner (count + Recall ⇧F8) when any, the breakdown (subtotal, line item discounts and VAT always shown (৳0.00 when zero), group discount, order-discount box, rounding, Total Payable) and the PAY F9 bar. F7 Today stays a key and a palette entry.
Tablet portrait (768–1023): a result list plus a cart column at 45 %. Phone: the cart is a bottom sheet with a sticky total bar.

### 4.2 Register lifecycle states
```
UNENROLLED ──enter pairing code from Settings → Devices (POST /api/pos/v1/enroll)──▶ LOCKED
LOCKED ──staff login / PIN──▶ IDLE   (no shifts: decided 2026-10-30)
IDLE ⇄ CART_ACTIVE (add/remove) · CART_ACTIVE ──F8──▶ IDLE (held) · IDLE ──resume──▶ CART_ACTIVE
CART_ACTIVE ──F4──▶ CUSTOMER_LOOKUP ──select member──▶ CUSTOMER_PIN (see 4.4) ──ok──▶ CART_ACTIVE
CART_ACTIVE ──F9──▶ PAYMENT ──Esc──▶ CART_ACTIVE
PAYMENT ──tenders ≥ total──▶ COMMITTING
PAYMENT ──due/credit/advance tender──▶ requires: member PIN-verified (online) & within limit, else MANAGER_AUTH
COMMITTING (local, < 100 ms): write sale to the local DB + outbox in ONE IndexedDB transaction, decrement local stock,
           assign the device number {BR}-{REG}-{YY}{seq} → COMPLETED (print) → IDLE
Outbox (background, independent): PENDING ─push→ SYNCED | DUPLICATE(=SYNCED) | ACCEPTED_WITH_FLAGS (shown in Sync center)
           | REJECTED (only auth/revoked/validation corruption → Sync center error + manager notification; data kept locally)
IDLE ──F7──▶ TODAY_SUMMARY (read-only, local) ──Esc──▶ IDLE
Any state ──device revoked (server 401 device)──▶ WIPE local data ──▶ UNENROLLED
```
Rules:
- **The sale path never awaits the network.** Online and offline use the same commit path. Online only means the outbox flushes immediately.
- Totals are computed by the shared deterministic pricing engine (04_TRD §3.9.4). The server recomputes on push. A difference is accepted as charged and flagged (`price_mismatch`).
- Local stock blocks overselling when `allow_negative_stock=false`, **using the local snapshot**. A sale accepted offline that the server finds oversold is accepted and flagged (`oversold`). It is never rejected, because the goods already left.
- The cart, held carts and outbox persist in IndexedDB, so they survive refresh, crash and power loss.

### 4.3 POS keys
| Control | Key | Notes |
|---|---|---|
| Search/scan | `F2` (auto-focus after every action) | Barcode exact → add. `MC…` barcode → membership card → customer flow. Text → dropdown of hits under the field (`↑↓`, `Enter`) |
| Browse catalogue | `Alt+G` (toggle) | Product grid with category chips over the bill; `Esc` / Back to cart closes it; adding a product (or the variant picker's Add) returns to the cart |
| Category chips | `Alt+1..9` | |
| Qty of selected line | `F3` or type `*` then a number | Decimal only if the unit allows it |
| Select line | `↑↓` while the search is empty | |
| Remove line | `Del` | |
| Customer | `F4` | Accepts card scan, customer no. (`#` optional), phone or name |
| Line / order discount | `F6` / `Shift+F6` | Role cap, else Manager PIN |
| Today summary | `F7` | Read-only, works offline |
| Exchange | `F11` | Scan/enter the receipt → pick returned lines → add replacements → pay or refund the difference. Online only |
| Variant picker | `Enter` on a family search hit | Size × colour grid with stock per cell. Arrows move, `Enter` adds, `Esc` closes |
| Hold / held list | `F8` / `Shift+F8` | |
| Collect due | `Ctrl+D` | Opens `/app/dues/{customer}` for the attached customer; the function-bar button is disabled without a customer or due |
| Pay | `F9` | Payment & Due Settlement panel (mockup): one row per active payment method, cash prefilled with the exact amount and focused. `Alt+C` cash, `Alt+K` card, `Alt+B` bKash, `Alt+N` Nagad, `Alt+R` Rocket, `Alt+A` bank, `Alt+E` exact cash, `Alt+D` rest as due. Quick cash `1`–`3` add ৳100/500/1000 (focus outside a field) |
| Complete | `F10` / `Enter` in payment | Button "Complete Sale & Print Receipt" (auto-print branch) or "Complete Sale" when fully paid, "Confirm Partial / Due Sale" otherwise |
| Reprint last | `Ctrl+P` | |
| Clear cart | `Esc` `Esc` | Confirms |
| Lock | `Ctrl+L` | |

### 4.4 Customer attach + PIN flow
```
F4 → input: scan MC100000174 01 | type 10000017 | 017xxxxxxxx | "rahim"
   → local lookup (offline-capable) → candidate list (number, name, phone last 3, group, card status)
   → select → card status lost/revoked? → error "Card blocked: issued replacement #02" (staff can still look up by number)
   → policy=off → attached
   → policy=always (default) or (credit_only and a credit tender is used):
       customer has no PIN → dialog "Set PIN now?" [Set (online only) | Continue as walk-in | Manager override]
       PIN pad dialog (turned to the customer; masked dots; digits by keyboard or on-screen pad)
         ONLINE  → POST verify-pin → ok: chip shows "✓ PIN" · fail: "Wrong PIN (n left)" · locked: "PIN locked 30 min"
         OFFLINE → PIN sealed-box-encrypted into the sale; chip shows "PIN pending verification"; credit tenders
                   disabled unless the tenant offline credit cap > 0; verified at sync → failure ⇒ flag + manager notification
   → Manager override (perm pos.customer_pin_override + reason) → chip "Override by {name}"
```

## 5. Back-office flows

### 5.1 Standard list → detail → form pattern
- **List:** server pagination (25 default), sortable, filters in the query string. Keyboard row navigation (§1.3), multi-select with `Space`/`Shift`, a bulk action bar, a right-click menu (§1.2). The column chooser and saved views are stored per user. Density follows the theme setting.
- **Empty states:** with no records, an illustration + explanation + primary CTA + import CTA. With no filter results, "No results" + [Clear filters].
- **Loading:** skeleton rows. **Form:** inline validation. A 422 maps to fields and focuses the first error. Unsaved-changes guard. `Ctrl+S` saves.
- **Delete:** confirm with the record name. Referenced records are deactivated instead, with an explanation.
- **Detail:** header + status badge + actions with key hints, tabs (Overview · Activity · Related).

### 5.2 Customer form (create/edit), keyboard-ordered
Built as the add/edit **dialog** on the list and the profile (baseline mockup sections: 1. identity & contact with shop name next to the name, 2. address (area, post code, street; the geo pickers below come with the geo data), 3. group/tier, credit limit, group discount (read-only, from the group), 4. dates & notes). Target sections:
1. **Identity:** name*, phone* (BD format, unique, duplicate warning with a link), alt phone, shop/company, email, gender, DOB, anniversary, group, credit limit, VAT BIN, notes.
2. **Location:** Division → District → City/Upazila/Thana → Union/Ward → Area → Post code → Address line.
   - Each field is a type-ahead combobox that accepts EN or BN text.
   - **Typing into any level searches all levels below its selected parent.** Choosing a deeper value auto-fills its parents. Changing a parent clears the incompatible children (with an undo toast).
   - The post code suggests from the chosen upazila.
3. **Membership** (edit only): customer no. (read-only, copy), card(s) with status, [Issue/Print card], PIN status (Set / Locked / Not set) + [Reset PIN] (perm).
On create, the success toast shows the **new customer number** with [Print card] and [New sale] actions.

### 5.3 Membership card printing
`cards.index` or the customer context menu → select customers → **Issue & print** → layout (CR80 card printer · A4 10-up · PDF) → live preview (front/back, 1:1 scale toggle) → Print / Download. Status flow: `active ──lost──▶ lost` (blocked) → reissue → new card `active` (issue+1). A reprint of the same issue increments `print_count` (audited).

### 5.4 Purchase receive · 5.5 Sales return · 5.6 Due collection · 5.7 Stocktake · 5.8 Transfer
Unchanged from v1.0 of this document (all **online-only**):
- **Purchase:** draft → received (irreversible) / cancelled.
- **Return:** scan the receipt → returnable qty → restock → refund or credit. Within the tenant setting `return_days` (none = no limit). The value first settles the sale's open due; refund pays the rest (cash by default), credit leaves it on the account. Returns happen in the branch of the sale.
- **Void:** same business day (tenant timezone), `sales.void`, not after a return and not for an exchange's replacement sale. Payments that only settle this sale are voided; a due collection that also paid other sales keeps the rest as an advance.
- **Exchange:** scan the receipt → returnable qty → add replacements → server quote → pay the difference / refund / credit → one receipt showing both parts (ADR 0005).
- **Due collection:** oldest-first auto-allocation, editable, overpay → advance. Allocates to the dues of the current branch.
- **Due on a sale:** named customer only; customer `credit_limit`, else tenant `default_credit_limit`; above it needs a manager (`pos.customer_pin_override`, actor or approving manager). Discount above the role cap needs `pos.discount_override`; the customer-group discount does not count against the cap.
- **Stocktake:** draft → counting → review → posted.
- **Transfer:** draft → in_transit → received (short-receive with a reason).

### 5.9 Reports runner
Reports: sales-by-day, sales-by-product, sales-by-user, sales-by-tender, returns, top-customers, customers-last-purchase, dues-aging, stock-valuation, daily-cash. `branch=all` or a branch id needs `reports.all_branches`, otherwise the current branch. Cost/profit columns need `reports.profit`. Dashboard (`/app`, `dashboard.view`): query `period` = `today` (default) | `yesterday` | `7d` | `30d` | `this_month` | `custom` + `from`/`to` (max 366 days), each compared with the window before it (month to date vs the same days of last month); `branch` = `current` (default), `all` or a branch id, the last two only with `reports.all_branches` (which also adds the branch comparison widget). Cached 60 s per tenant + branch scope + period. 9 KPIs (net sales, gross profit, transactions, avg basket, dues outstanding, due collections, returns, FIFO stock value, low-stock count; profit and stock value only with `reports.profit`), revenue line (hourly for one day, daily otherwise, previous window dashed), payment-method donut, weekday × hour heat-map (at least the last 28 days), branch comparison, dues aging, top products/customers, low stock, recent sales, debtors, and a first-run checklist (logo, payment methods, products, customers, first sale; hidden when complete). Reports directory: the baseline mockup's card grid (category, `Format: CSV / XLSX / PDF`, Run Report); cards for reports not built yet stay visible but disabled ("next phase"), XLSX/PDF are shown as not yet available.
Filters (branch, date presets, user, category, **division/district**) → tiles + chart + table → export (queued; a notification when ready).

## 6. Global error, offline & edge handling

| Situation | Behavior |
|---|---|
| Offline on a POS page | Fully functional per PRD C12. The pill shows `⚠ Offline`. Online-only buttons are disabled with the tooltip "Needs internet" |
| Offline on a back-office page | The service worker serves the shell + a cached "You're offline" view with [Go to POS] and the last-synced dashboard snapshot (read-only) |
| Back online | Outbox flush → toast "Synced n sales" (or "n need attention" → Sync center) |
| Sync op flagged (`price_mismatch`, `oversold`, `member_unverified`, `customer_merged`) | Sale accepted. Notification to Managers. Listed in `/app/sync-exceptions` until acknowledged |
| Device revoked | Wipe local data → enroll screen |
| Storage quota near full (> 80 %) | Warning in the status bar. Prune synced sales older than 7 days from local storage |
| 401/403/404/409/419/422/429/500 | As in v1.0: login redirect (POS cart kept), 403 page, 404 (cross-tenant too), "record changed" reload, CSRF retry, field errors, countdown, error ref + Sentry |
| Printer unavailable | The sale is already saved. The receipt view stays with [Print again] [PDF] |
| Scanner input while a dialog is open | Routed to the dialog if it has a scan target (customer/PIN-free), else buffered and toasted "Close dialog to scan" |
