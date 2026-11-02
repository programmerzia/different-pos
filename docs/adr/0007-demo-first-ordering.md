# ADR 0007: Demo-first ordering, online register first, SMS un-deferred

Date: 2026-11-03. Status: accepted.

## Context
The owner needs a working demo on DIFFERENT FASHION's real data: products, stock, customers, sales history, register, dues, returns and exchange, cards, SMS, reports, users and permissions. The original plan builds every table, then every API, then the offline register, then the UI, which puts the first working sale many weeks out. The legacy dump of 2026-10-29 is available and profiled (see 06_Plan Phase D).

## Decisions
1. **One phase, vertical slice.** Phase D in 06_Plan.md is the only active phase until it is done. Steps keep their original numbers in brackets.
2. **Migration first.** Real products, stock batches, customers (with `shop_name`), sales, payments and dues are imported before any screen is built, so every screen is developed and demoed on real data.
3. **Online register first.** The register is an Inertia page under `/app/pos` that calls `CompleteSale` directly. The offline SPA (Dexie, outbox, device sync) is the next phase and will call the same action through `/api/pos/v1/push`. Nothing in the server design changes: `origin=web` now, `origin=device` later. The "sale path never awaits the network" rule applies to the offline SPA, not to this interim page.
4. **SMS is no longer deferred.** A `SmsGateway` contract with `ssl_wireless`, `log` and `null` drivers. Sale-completed messages go only to named customers. PRD non-goal 1 and the "No SMS code" rule are withdrawn.
5. **Deferred to the next phase:** customer PIN and sealed boxes (the keypair infrastructure stays), device enrollment and sync, geo reference, 2FA, invitations, platform admin console, money module, backups, nightly integrity job.
6. **Legacy anomalies are reported, never silently fixed:** duplicate SKUs get a `-{legacy_id}` suffix, cost-0 products and the future-dated sale go to `migration_report.csv`, the 648-unit batch/product stock difference is listed per product.

## Consequences
- CLAUDE.md, .cursorrules, 01_PRD non-goals and 04_TRD §2 updated in the same change.
- The `customers.pin_*` columns are created but unused in this phase.
- Playwright offline scenarios (4.6) move with the offline SPA.
- The online register asks for a manager's password or staff PIN (`POST /app/api/pos/authorize`, a short-lived token bound to the cashier) instead of the device flow's Manager PIN token bound to a cart hash. On the online register F1 starts a new sale, F5 is price check and F11 opens returns; exchange from the register comes with the offline SPA.
