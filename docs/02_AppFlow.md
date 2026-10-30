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
│ Dashboard            G D                │ Breadcrumb · Page title · primary actions (with key hints)       │
│ POS Register         F1                 │                                                                   │
│ Sales ▸              G S                │                                                                   │
│ Customers ▸          G C                │                                                                   │
│ Inventory ▸          G I                │                                                                   │
│ Purchases ▸          G P                │                                                                   │
│ Money ▸              G M                │                                                                   │
│ Reports              G R                │                                                                   │
│ Settings ▸           G ,                │                                                                   │
├─────────────────────────────────────────┴───────────────────────────────────────────────────────────────────┤
│ STATUS BAR (desktop only, 28 px): Branch · User · Printer ✓ · Scanner ✓ · Sync ✓ · DB size 12 MB · v1.0.0    │
└──────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
Mobile (< 768 px): the sidebar becomes a bottom tab bar [Home · POS · Sales · Stock · More]. The top bar is reduced to logo · sync dot · 🔔 · avatar.
```

### 1.1 Top bar elements
| Element | Behavior | Key |
|---|---|---|
| Branch switcher | Dropdown of the user's branches. Switching confirms if the POS cart is non-empty | `Alt+B` |
| Global search / command palette | Pages, actions ("New sale", "Receive purchase"), products, customers (`#12345678` = customer no.), invoices (`S-…`), settings | `Ctrl+K` |
| Connectivity + sync pill | `● Online` (success) · `⚠ Offline` (warning) · `⟳ n unsynced` (accent, pulses while syncing) · `✕ Sync error` (danger). Click opens the **Sync center** popover (queue, last pull/push, retry, conflicts) | `Alt+Y` |
| Register pill | "R1 · 12 sales today". Click: Today summary, Sync center | — |
| **Notifications 🔔** | Badge count. The panel has tabs **All · Alerts · Exports**. Types: low stock, sync exception, member PIN failure / unverified member, export ready, backup failed, stocktake ready to review, transfer incoming, due over 90 days, new login from a new device. Mark read, mark all read, click to go to the item. Real-time via polling every 60 s (Reverb later) | `Alt+N` |
| **Clock** | Live time (tenant timezone) + day/date. Hover shows the business day. Click opens a mini calendar | — |
| Language | EN / বাং toggle | `Alt+L` |
| **Theme 🎨** | Popover: mode **Light / Dark / System** + **colour scheme** swatches (03_UI_Spec §1.4) + density **Comfortable / Compact** | `Alt+T` |
| Avatar | Profile, Switch user (staff PIN), Keyboard shortcuts (`?`), Lock screen (`Ctrl+L`), Log out | — |

### 1.2 Right-click context menus (desktop vibe)
A custom context menu replaces the browser menu on **app surfaces only**. It is never used inside text inputs: native copy/paste stays there. `Shift+F10` or the `≣ Menu` key opens the same menu for the focused item (keyboard parity). Long-press opens it on touch. The menu shows icons, labels, **shortcut hints**, separators and submenus. Destructive items are last and red. Items the user lacks permission for are hidden, not disabled.

| Surface | Context menu items |
|---|---|
| Any table row (generic) | Open `Enter` · Open in new tab `Ctrl+Enter` · Edit `E` · Duplicate · Copy ▸ (ID, number, row as text) · Export selected · Deactivate/Delete `Del` |
| Product row / POS product tile | Add to cart `Enter` · Add qty… `*` · View stock by branch · Movement history · Print label `Ctrl+Shift+P` · Edit product · Copy SKU/barcode |
| POS cart line | Change qty `F3` · Line discount `F6` · Price override (perm) · Remove `Del` · View product |
| Customer row / POS customer chip | Open profile · New sale for customer · Collect due · Statement · Print membership card · Report card lost · Reset PIN (perm) · Copy customer no. · Share WhatsApp reminder |
| Sale row | Open · Reprint receipt `Ctrl+P` · Share receipt link · Return items · Exchange items · Collect payment · Void (perm, same day) |
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
| 1 | GET/POST | `/login` | `login` | `auth/login`: one identifier field (email / username / phone) + **business code** shown for non-email identifiers, remembered per browser in cookie `pos_tenant` (ADR 0006) | guest |
| 2 | POST | `/logout` | `logout` | redirect | auth |
| 3 | GET/POST | `/forgot-password`, `/reset-password/{token}` | `password.*` | `auth/forgot-password`, `auth/reset-password` | guest |
| 4 | GET/POST | `/two-factor-challenge` | `two-factor.login` | `auth/two-factor-challenge` | pending 2FA |
| 5 | GET/POST | `/invite/{token}` | `invites.accept` | `auth/accept-invite` | signed |
| 6 | GET | `/app/branch/select` | `branch.select` | `branch/select` *(no branch)* | auth |
| 7 | POST | `/app/branch/{branch}/switch` | `branch.switch` | redirect | assigned |
| 8 | POST | `/app/lock`, `/app/unlock` | `lock.*` | lock screen | auth |
| **Dashboard & shell** |
| 10 | GET | `/app` | `dashboard` | `dashboard` | `dashboard.view` |
| 11 | GET | `/app/api/notifications` · POST `/{id}/read` · POST `/read-all` | `api.notifications.*` | JSON | auth |
| 12 | GET | `/app/api/search?q=` | `api.search` | JSON (palette) | auth (results permission-filtered) |
| 13 | PUT | `/app/api/preferences` | `api.preferences` | JSON (theme, scheme, density, locale) | auth |
| **POS** |
| 20 | GET | `/pos` | `pos.index` | **static Blade shell** `pos.blade.php` + Vite entry `resources/js/pos/main.tsx` (standalone SPA, **not** an Inertia page; no server data, no session; precached by the service worker). See ADR 0001 | none on the shell (static, no data). `pos.sell` is enforced by local staff unlock and on every pushed op |
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
| 30 | GET | `/app/sales` | `sales.index` | `sales/index` | `sales.view` |
| 31 | GET | `/app/sales/{sale}` | `sales.show` | `sales/show` | `sales.view` |
| 32 | GET | `/app/sales/{sale}/receipt?format=` | `sales.receipt` | print view / PDF | `sales.view` |
| 33 | POST | `/app/sales/{sale}/void` | `sales.void` | redirect | `sales.void` |
| 34 | POST | `/app/sales/{sale}/payments` | `sales.payments.store` | redirect | `payments.collect` |
| 35 | GET | `/r/{token}` | `receipts.public` | public receipt (no phone/address) | public |
| 36 | GET | `/app/sync-exceptions` | `sync-exceptions.index` | `sales/sync-exceptions` (flagged offline sales: resolve/acknowledge) | `sales.void` |
| **Returns** |
| 40 | GET | `/app/returns` | `returns.index` | `returns/index` | `returns.view` |
| 41 | GET/POST | `/app/returns/create?sale=` · `/app/returns` | `returns.create/store` | `returns/create` | `returns.create` |
| 42 | GET | `/app/returns/{return}` | `returns.show` | `returns/show` | `returns.view` |
| 43 | GET/POST | `/app/exchanges/create?sale=` · `/app/exchanges` | `exchanges.create/store` | `returns/exchange` (return lines + replacement lines + difference) | `returns.create` + `pos.sell` |
| 44 | POST | `/api/pos/v1/exchanges/quote` · `/api/pos/v1/exchanges` | `pos-api.exchanges.quote/store` | server-valued return + replacement sale with the device number (online only, ADR 0005) | device + `returns.create` |
| **Customers & membership** |
| 50 | resource | `/app/customers` | `customers.*` | `customers/index\|create\|edit\|show` | `customers.view/create/update/delete` |
| 51 | GET | `/app/customers/{customer}/statement` | `customers.statement` | page / PDF | `customers.view` |
| 52 | GET/POST | `/app/customers/{customer}/collect` | `customers.collect` | `customers/collect` | `payments.collect` |
| 53 | POST | `/app/customers/{customer}/pin/reset` | `customers.pin.reset` | redirect (reason required) | `customers.reset_pin` |
| 54 | POST | `/app/customers/{customer}/pin/unlock` | `customers.pin.unlock` | redirect | `customers.reset_pin` |
| 55 | GET | `/app/dues` | `dues.index` | `dues/index` (aging + share reminder) | `customers.view` |
| 56 | resource | `/app/customer-groups` | `customer-groups.*` | `customer-groups/*` | `customers.update` |
| 57 | GET | `/app/membership-cards` | `cards.index` | `cards/index` (issued cards, filter status, print queue) | `cards.view` |
| 58 | POST | `/app/membership-cards/issue` | `cards.issue` | issue for customer ids → redirect to print | `cards.issue` |
| 59 | GET | `/app/membership-cards/print?ids=&layout=cr80\|a4_10` | `cards.print` | `cards/print` preview → PDF | `cards.issue` |
| 60 | POST | `/app/membership-cards/{card}/lost` · `/reissue` | `cards.lost/reissue` | redirect | `cards.issue` |
| 61 | GET | `/app/api/geo/search?q=&level=` · `/app/api/geo/{level}?parent=` | `api.geo.*` | JSON location lookup (cached, global) | auth |
| **Inventory** |
| 70 | resource + import/export | `/app/products` | `products.*` | `products/*` | `products.*` |
| 71 | resource | `/app/categories`, `/app/brands`, `/app/units` | … | `catalog/*` | `products.update` |
| 72 | GET | `/app/stock` · `/app/stock/{product}/movements` | `stock.*` | `stock/*` | `stock.view` |
| 73 | resource | `/app/adjustments` | `adjustments.*` | `adjustments/*` | `stock.adjust` |
| 74 | resource + `post` | `/app/stocktakes` | `stocktakes.*` | `stocktakes/*` | `stock.count` |
| 75 | resource + `send/receive` | `/app/transfers` | `transfers.*` | `transfers/*` | `stock.transfer` |
| 76 | GET/POST | `/app/labels` | `labels.*` | `labels/index` → PDF | `products.view` |
| **Purchases** |
| 80 | resource + `receive/cancel/payments` | `/app/purchases` | `purchases.*` | `purchases/*` | `purchases.*`, `payments.pay` |
| 81 | resource + statement | `/app/vendors` | `vendors.*` | `vendors/*` | `vendors.*` |
| **Money** |
| 90 | resource | `/app/cash-book`, `/app/accounts`, `/app/transaction-categories`, `/app/fixed-assets` | … | `money/*` | `money.*` |
| **Reports** |
| 95 | GET | `/app/reports` · `/app/reports/{report}` | `reports.*` | `reports/index`, `reports/show` | `reports.view` (+ `reports.profit` for cost/profit) |
| 96 | POST | `/app/reports/{report}/export` | `reports.export` | queued → notification | `reports.export` |
| **Settings** |
| 100 | GET/PUT | `/app/settings/business` (incl. VAT BIN, customer PIN policy, offline credit cap) | `settings.business` | `settings/business` | `settings.manage` |
| 101 | resource | `/app/settings/branches` (+ registers, **devices**: list/revoke) | `branches.*`, `devices.*` | `settings/branches*` | `settings.manage`, `devices.manage` |
| 102 | resource + invite | `/app/settings/users` | `users.*` | `settings/users*` | `users.manage` |
| 103 | resource | `/app/settings/roles` | `roles.*` | `settings/roles*` | `users.manage` |
| 104 | resource | `/app/settings/payment-methods`, `/taxes` | … | `settings/*` | `settings.manage` |
| 105 | GET/PUT | `/app/settings/receipt`, `/app/settings/card` (card template text/colours) | … | `settings/*` | `settings.manage` |
| 106 | GET | `/app/settings/audit` · `/app/settings/backups` | … | `settings/*` | `audit.view` / Owner |
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
| Search/scan | `F2` (auto-focus after every action) | Barcode exact → add. `MC…` barcode → membership card → customer flow. Text → results (`↑↓`, `Enter`) |
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
| Pay | `F9` | Exact amount prefilled. `Alt+C` cash, `Alt+K` card, `Alt+B` bKash, `Alt+N` Nagad, `Alt+D` due. Quick cash `1`–`4` for ৳500/1000/2000/exact |
| Complete | `F10` / `Enter` in payment | |
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
Sections:
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
- **Return:** scan the receipt → returnable qty → restock → refund or credit.
- **Exchange:** scan the receipt → returnable qty → add replacements → server quote → pay the difference / refund / credit → one receipt showing both parts (ADR 0005).
- **Due collection:** oldest-first auto-allocation, editable, overpay → advance.
- **Stocktake:** draft → counting → review → posted.
- **Transfer:** draft → in_transit → received (short-receive with a reason).

### 5.9 Reports runner
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
