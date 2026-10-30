# CLAUDE.md

This is a multi-tenant, multi-branch retail POS for Bangladesh, built with Laravel 13 + Inertia 2 + React 19 (TypeScript) + Tailwind CSS 4 on MySQL 8.4. It has an **offline-first POS register** and a **keyboard-first, desktop-style UI**. It is a ground-up rewrite of a legacy CodeIgniter POS. The product has **no name yet**: never hard-code one.

## Before ANY task: read the context docs
They are the source of truth and win over your assumptions:

1. `docs/01_PRD.md`: scope, personas, user stories, **Non-Goals** (never build a non-goal).
2. `docs/02_AppFlow.md`: shell, keyboard map, context menus, routes and permissions, POS state machine, offline behavior, error states.
3. `docs/03_UI_Spec.md`: design tokens, colour schemes, density, component states.
4. `docs/04_TRD.md`: the locked stack, folder structure, architecture, **offline sync design (§3.9)**, security.
5. `docs/05_Schema.md`: tables, invariants, tenancy/auth/device flow, legacy migration.
6. `docs/06_Plan.md`: the ordered checklist. **Work only on the first unchecked step.**

Also `docs/00_Legacy_Features.md` (parity) and `docs/adr/` (decisions). For small tasks, read the relevant sections only, but always read `06_Plan.md` and the matching parts of 04/05.

## After EVERY completed step
1. Run the relevant gates (`composer lint`, `composer analyse`, `php artisan test --filter …`, `npm run lint`, `npm run typecheck`, `npm run test`), or `composer ci` for everything.
2. Update `docs/06_Plan.md` in the same change: `[x]` + ` — ✅ YYYY-MM-DD <note>`, plus a **Progress log** line.
3. If you changed a schema, route, permission, dependency or decision, update docs 02/04/05 in the same change.
4. Report what changed, which tests ran, and what is unresolved.

## Hard rules
- **Money = integer paisa** (BIGINT, brick/money; integers in TS via `engine/money.ts`). Quantity = DECIMAL(14,3) (milli-units in TS). **No floats for money anywhere.**
- **Business writes go only through final Action classes** in `DB::transaction()`, honouring the locking order (04_TRD §3.5). Controllers stay thin.
- **Tenant isolation:** `BelongsToTenant`/`BelongsToBranch` and tenant-scoped `exists` rules. Cross-tenant access returns 404. Add isolation tests for every new model, route or endpoint (including the device API).
- **POS is offline-first:** the sale path never awaits the network. Online and offline use the same local commit plus outbox. The server never rejects a synced sale for business reasons: it **flags** it.
- **Pricing has two identical implementations** (`engine/pricing.ts`, `PriceCalculator.php`). Any change needs both, plus golden fixtures.
- **Stock changes only through `StockLedger`** (append-only movements + FIFO batches).
- **Completed sales are immutable** (void the same day, or return).
- **The customer PIN never leaves the server in usable form:** no logs, exports or resources, and only a sealed box on devices.
- **Customer numbers are server-assigned, Luhn-checked, permanent and never reused.**
- **Keyboard-first:** every action has a shortcut registered in the central hotkey registry, a context menu entry where applicable, and a command palette entry. Visible focus. `Shift+F10` gives context menu parity.
- UI: semantic tokens only (no hex, no palette classes). All text goes through i18n (EN + BN). `dangerouslySetInnerHTML` is banned. Touch targets ≥ 44 px.
- Every controller action authorizes. Permission names match 04_TRD §3.8, and route names match 02_AppFlow §2.
- Dependencies change only by updating 04_TRD first (human approval for §1–2).
- `declare(strict_types=1)`, final classes, full types, Larastan 8, Pest tests (happy + failure + edge) for every Action.
- No secrets in git. No DB/admin tools in `public/`. No SMS code (deferred).
- Ambiguity → add it to `06_Plan.md` §Open questions and ask. Don't guess.

## Common commands
```bash
composer dev                         # serve + queue + vite
composer ci                          # all quality gates
php artisan test --parallel
npm run lint && npm run typecheck && npm run test
npx playwright test
php artisan migrate:fresh --seed     # geo + demo tenant
php artisan integrity:check
php artisan legacy:import --connection=legacy --stores=7,10 --tenant=different-fashion --dry-run
```
