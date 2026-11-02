# Retail POS

A multi-tenant, multi-branch retail POS for Bangladesh with an offline-first register. Built with Laravel 13, Inertia, React 19, TypeScript, Tailwind CSS 4 and MySQL 8.4.

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
docker compose up -d          # MySQL 8.4 and Redis
php artisan migrate:fresh --seed
composer dev                  # app server, queue worker and Vite
```

Demo login: `owner@demo.test` / `password`.

## Quality gates

```bash
composer ci                   # everything below, plus build and audits
composer lint                 # Pint + Rector (dry run)
composer analyse              # PHPStan / Larastan
composer test                 # Pest
npm run lint
npm run typecheck
npm run test
npm run e2e                   # Playwright
```

## Docs

| Doc | What it covers |
|---|---|
| [00_Legacy_Features.md](docs/00_Legacy_Features.md) | What the old POS does, parity status, legacy data model, defects |
| [01_PRD.md](docs/01_PRD.md) | Problem, personas, user stories, metrics, non-goals |
| [02_AppFlow.md](docs/02_AppFlow.md) | Shell, keyboard map, context menus, routes, POS/offline state machines |
| [03_UI_Spec.md](docs/03_UI_Spec.md) | Tokens, colour schemes, density, components |
| [04_TRD.md](docs/04_TRD.md) | Stack, architecture, offline sync, security, quality gates |
| [05_Schema.md](docs/05_Schema.md) | DB schema, invariants, auth/device flow, legacy migration |
| [06_Plan.md](docs/06_Plan.md) | Ordered checklist, open questions, progress log |
