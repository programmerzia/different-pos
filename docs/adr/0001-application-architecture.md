# ADR 0001: Application architecture (Inertia back office + standalone offline POS)

- **Status:** Accepted (2026-10-30)
- **Supersedes:** the "POS register = an Inertia page" wording in 02_AppFlow §2 route 20 and 04_TRD §3.1
- **Affects:** 02_AppFlow §2, 04_TRD §3.1, §3.2, §3.5, §3.9.7, 06_Plan 2.22, 3.23, 3.25

## Context

We are replacing a live CodeIgniter 3 POS (see 00_Legacy_Features). Requirements that shape the architecture:

1. **Offline-first register.** A sale never waits for the network (PRD, 04_TRD §3.9).
2. **Online back office.** Catalog, purchasing, stock, money, reports and settings are used online. They are data-heavy, form-heavy and permission-heavy.
3. **Small team, one codebase**, multi-tenant SaaS, Laravel 13.
4. **The live legacy data must migrate cleanly**, with a repeatable rehearsal and reconciliation (ADR 0002).
5. **Non-goals:** native apps, a customer portal, e-commerce, third-party integrations (PRD §5).

The question we asked: keep Inertia, or move to a "pure" REST API with a separate React SPA?

## Options considered

| | A. API + separate SPA for everything | B. Inertia for everything (incl. POS) | **C. Hybrid (chosen)** |
|---|---|---|---|
| Back-office dev speed | Slower: a REST resource, a client fetch layer, client routing, client auth state and duplicated validation for every screen | Fast | Fast (Inertia) |
| Offline POS | Works | **Does not work well.** An Inertia page bakes server props + CSRF into the HTML and navigates by XHR. A cached copy is stale, and every visit fails offline | Works: the POS is its own SPA bundle on the device API |
| Auth surfaces | Token or SPA-cookie auth everywhere | Session only | Session (back office) + device token (POS). Both needed anyway |
| Future native app / integrations | Ready | Needs an API later | The device API already exists. A public API is cheap later because all writes are Actions |
| Moving parts | Two apps, two deploys, CORS, versioning on every endpoint | One | One app, one deploy, **one versioned API surface** (`/api/pos/v1`) |

Pure API + SPA buys us nothing that the non-goals ask for, and costs every back-office screen roughly twice the plumbing. Inertia for everything breaks the one hard requirement (offline). The hybrid isolates the API to the place that genuinely needs it.

## Decision

**One Laravel modular monolith with two front-end entry points and three HTTP surfaces.**

```mermaid
flowchart LR
  subgraph Browser
    BO["Back office<br/>Inertia 2 + React 19<br/>entry: resources/js/app.tsx"]
    POS["POS register SPA<br/>React 19 + Dexie + SW<br/>entry: resources/js/pos/main.tsx"]
    IDB[(IndexedDB<br/>pos_{tenant}_{register})]
    POS <--> IDB
  end
  subgraph Laravel["Laravel 13 (one app, one deploy)"]
    WEB["/app/*  Inertia controllers<br/>session + CSRF"]
    INT["/app/api/*  JSON for widgets<br/>session"]
    DEV["/api/pos/v1/*  device API<br/>Sanctum device token"]
    ACT["Actions (final, DB::transaction)<br/>Queries / Reports"]
    LED["StockLedger · PriceCalculator<br/>DocumentNumberer · PinSealer"]
    WEB --> ACT
    INT --> ACT
    DEV --> ACT
    ACT --> LED
  end
  DB[(MySQL 8.4)]
  R[(Redis: cache, queue, locks)]
  BO --> WEB
  BO --> INT
  POS -- "pull / push outbox" --> DEV
  ACT --> DB
  ACT --> R
  LEG[(Legacy MySQL<br/>read-only)] -. "legacy:import" .-> ACT
```

### D1. Back office = Inertia 2 (unchanged)
Server-driven pages, FormRequest validation, policies, Wayfinder typed routes. Small JSON endpoints under `/app/api` only for widgets (notifications, geo search, palette search).

### D2. POS register = a standalone local-first SPA (changed)
- **Its own Vite entry** `resources/js/pos/main.tsx`, rendered by a static Blade shell `resources/views/pos.blade.php` that contains **no server data, no session, no CSRF token**. The route is `Route::view('/pos', 'pos')->name('pos.index')`.
- The service worker precaches this shell + POS chunk. Offline, the device boots from cache and reads everything from IndexedDB.
- The POS SPA **never** calls Inertia or session routes. It talks only to `/api/pos/v1` with the device token.
- No client router dependency. The register is one screen; enrollment, lock, register, payment, completion and sync center are **states** in the POS state machine (02_AppFlow §5).
- Shared with the back office through plain imports: `components/ui`, `i18n`, the hotkey registry, `engine/money.ts`, tokens CSS. Separate chunks, so the POS stays within its 300 KB budget.
- **Authorization exemption:** the shell route renders a static view with no data. Authorization for the POS happens (a) at enrollment, (b) by local staff unlock, and (c) on the server for every pushed op (device token + signed operator id + `pos.sell`). The "every controller action authorizes" arch test lists `pos.index` as the only exempt route.

### D3. Device enrollment by pairing code (changed)
The Inertia page cannot safely hand a token to the POS SPA. So:
1. A Manager (`devices.manage`) opens **Settings → Devices → Pair register** in the back office. The server issues a one-time **pairing code** (8 chars, Crockford base32, 10 min TTL, single use, hashed at rest) plus a QR.
2. On the device, the POS SPA (state `UNENROLLED`) takes the code and calls `POST /api/pos/v1/enroll`.
3. The server returns the device token bound to (tenant, branch, register), and the SPA stores it in IndexedDB.

This keeps session cookies out of the POS entirely, works on any browser, and needs no 2FA flow inside the POS.

### D4. Device API is the only versioned contract
`/api/pos/v1` is versioned and backward compatible within `v1`. Devices send `X-Pos-Client: <build>` and `X-Pos-Schema: <dexie version>`. The server can answer `426 Upgrade Required` for builds below a minimum, and the SW update flow (04_TRD §3.9.7) handles it. Request/response shapes have JSON fixtures in `tests/fixtures/pos-api/*.json`, checked by Pest (server) and Vitest (client), the same pattern as the pricing fixtures.

### D5. Domain core is shared, transport-agnostic
Every write is a final Action (`handle()`, `DB::transaction()`, locking order). An Action never knows whether it was called from Inertia, the device API, a queued job or `legacy:import`. This is what makes a public API (post-MVP) and the legacy importer cheap.

Module boundaries inside `app/` follow the Action namespaces (Catalog, Customers, Membership, Inventory, Purchases, Sales, Returns, Money, Pos, Sync, Tenancy, Settings). A Pest arch test forbids `Http\*` from being used by `Actions\*`, and forbids `Models` from depending on `Http`.

### D6. Sync-version allocation is part of the locking order
`sync_version` comes from `tenants.sync_counter` incremented `FOR UPDATE`. Because the lock is held until commit, versions become visible in order, so a puller can never skip a row (no "cursor gap"). The cost is serialization, so the counter is **acquired last**, immediately before commit (04_TRD §3.5). Bulk writers (product import, `legacy:import`) take one range per chunk, not one per row. At the expected volume (a few branches, a few registers per tenant) this is not a bottleneck. Revisit if a tenant exceeds about 20 concurrent registers.

## Consequences

- Two Vite entries and two layouts to maintain. That is accepted and much cheaper than a full API layer.
- The back office does not work offline (it shows the offline banner, 02_AppFlow §6). This is intended.
- A future native app or third-party integration adds `/api/v1` controllers that call the existing Actions. No domain rewrite.
- Revisit this ADR if: native apps become a goal, the back office must work offline, or a second front-end team needs independent deploys.
