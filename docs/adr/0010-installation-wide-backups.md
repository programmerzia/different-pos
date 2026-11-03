# ADR 0010: Backups are installation-wide; the platform console provisions and impersonates

Date: 2026-11-04. Status: accepted.

## Context
The mockup shows a per-shop "Backup & Disaster Recovery" screen (SQLite exports) and a SaaS console with MRR, SMS balance and edge-latency tiles. The product is one Laravel installation with one MySQL database shared by every tenant (row-level isolation, 05_Schema §0), so a "tenant backup" would be a filtered export, not a restorable backup, and the console tiles have no real data source.

## Decisions
1. **One backup = the whole installation.** spatie/laravel-backup dumps the database and `storage/app/public` into one encrypted zip (`config/backup.php`). Every run, nightly or manual, is a row in the global `backups` table (no `tenant_id`). Tenant Owners see the shared history read-only (`backup.view`); starting a run or downloading an archive needs `backup.run` (Owner only by default). Cloud archives (`s3`) are never served through the app; only local-disk archives are downloadable. A per-tenant data export stays a future candidate (06_Plan 6.3 "backup restore UI").
2. **The runner is a contract.** `App\Backup\BackupRunner` (`run()`, `cleanup()`) is implemented by `SpatieBackupRunner`; tests bind a fake so no database is ever dumped in CI. The job (`RunBackupJob`) is platform-level and runs without a tenant context.
3. **Platform console shows measured values only.** KPIs = tenant counts, active staff, 30-day GMV net of returns across tenants (`TenantContext::withoutTenancy()`), bytes under `storage/app/public`. Health = DB version, queue driver with pending/failed counts, cache driver, last completed backup, disk free space. MRR, SMS balance and edge latency from the mockup are omitted until a real source exists.
4. **Impersonation is a real login.** The admin is signed in as the tenant's first active Owner; the admin id, tenant id and start time live in the session only. Exit or a 60-minute expiry (`ExpireImpersonation`) restores the admin. Both ends are audited on the tenant.
5. **Provisioning reuses the seed set** of the demo seeder and the legacy importer: Main branch `MAIN`, register `R1`, Cash + system tenders (`EnsureSystemPaymentMethods`), a `Piece` unit, the five system roles (`SeedTenantRoles`), the walk-in customer (first Luhn customer number) and an Owner with a mailed temporary password and `must_change_password`. The slug is permanent; a duplicate slug fails without partial rows.

## Consequences
- `Audit::prepare()` tolerates subjects without a `tenant_id` column (platform rows get `tenant_id NULL`).
- `RoleRouteMatrixTest` treats `{backup}` like `{report}`: not a tenant record, so the cross-tenant 404 rule does not apply.
- Restore is an operations procedure (06_Plan 5.7), not a UI.
