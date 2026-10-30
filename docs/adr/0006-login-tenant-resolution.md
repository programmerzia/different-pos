# ADR 0006: How login finds the tenant (remembered business code)

- **Status:** Accepted (2026-10-31, owner decision)
- **Affects:** 02_AppFlow route 1 (login), 05_Schema §1 (`users` uniques), 06_Plan 1.2 + 1.9

## Context
All tenants share one app and one domain. Username and phone are only unique inside a tenant (two shops may both have `cashier1`). The login page has one identifier field, so it needs another way to know the tenant.

## Decision
1. **Email is unique across the whole app** (`UNIQUE(email)`, NULLs allowed). Logging in with an email needs nothing else.
2. **Username and phone stay unique per tenant** (`UNIQUE(tenant_id, username)`, `UNIQUE(tenant_id, phone)`). Logging in with either one also needs the **business code** = `tenants.slug` (e.g. `different-fashion`).
3. The login page shows the business code field only when the identifier is not an email. After a successful login the code is **remembered per browser** in a plain, non-secret cookie `pos_tenant` (1 year, SameSite=Lax). The next visit shows "Business: DIFFERENT FASHION · change", so shop PCs never ask again.
4. **Lookup rules:**
   - identifier contains `@` → find by email;
   - otherwise resolve the tenant by slug (case-insensitive), then find by username, or by phone normalised to E.164.
   - Unknown tenant, unknown user and wrong password all return the **same** generic error and count toward the same throttle key (`login + IP`), so the form does not reveal which businesses or users exist.
5. Platform admins (`tenant_id IS NULL`) log in by email only.
6. **POS devices are not affected.** A device is bound to its tenant at pairing (ADR 0001 D3), and staff unlock the register by choosing their name + PIN.

## Consequences
- One person cannot have the same email in two tenants. That is acceptable; they can use a second address. Revisit if multi-tenant staff become common.
- The business code is not a secret. Security comes from the password, throttling and 2FA.
