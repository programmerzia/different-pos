# ADR 0003: Contain the live legacy system's security exposure now

- **Status:** Accepted (2026-10-30). The owner asked for the standard practice.
- **Scope:** the live legacy app (`../different`), **outside this repo**. The work is done by whoever has server/hosting access (owner or hosting admin). It does **not** block Phase 0 of the rewrite.
- **Evidence:** legacy survey 2026-10-30 (file locations in 00_Legacy_Features §4, items 6 and 14–15).

## Context
The legacy app stays live until cut-over (ADR 0002 M2), which is weeks to months away. It currently exposes:
- `backup.php`: public, unauthenticated, with hard-coded DB and FTP credentials. It tries an FTP upload on every request.
- `hidden.php`: Adminer 4.8.1, a full DB client, reachable on the web root.
- `/upload_images/`: PHP execution is not blocked.
- The `Backup` controller lets any user who passes the (bypassable) ACL download the whole database, including password hashes.
- `error_log` on the web root leaks server paths. The credentials are also in git history.

## Decision (standard practice: assume compromised, contain, rotate, verify)
**Within 24 hours:**
1. **Contain:** delete `hidden.php`, `backup.php`, `error_log` and `upload_images/option.php` from the server. Add a rule that denies PHP execution in `/upload_images/` (`<FilesMatch "\.(php\d?|phtml|phar)$"> Require all denied </FilesMatch>`). Restrict the `Backup` controller to the superadmin group, or disable it.
2. **Rotate** (treat as leaked): the DB app user password, the FTP account password, and the CodeIgniter `encryption_key` (this logs everyone out, which is acceptable). Put new values only in server config, never in git.

**Within 1 week:**
3. **Check for misuse:** web access logs for any hit on `hidden.php`, `backup.php` or unknown `.php` files under `upload_images/`, since the date those files were added. Check DB users/grants for unknown accounts. Check the FTP target for unknown files.
4. **Migration access:** create a separate read-only DB user `legacy_ro` (`SELECT`, `SHOW VIEW`, `TRIGGER`) restricted to the rewrite's IP. The importer uses only this user (ADR 0002).
5. **If step 3 finds misuse:** treat the customer data as exposed (it includes staff MD5 hashes and plaintext customer ordering-app passwords). The owner decides on customer notification. Force a password reset for all staff.

**Accepted risk until cut-over:** the SQL injection points, missing CSRF and unescaped views stay. Fixing a system that is being replaced costs more than it saves. Mitigations: the steps above, and legacy goes read-only at cut-over, then offline after 90 days. If cut-over slips past **2026-12-31**, revisit this and patch the injection points.

Rewriting git history in the legacy repo is not required once the credentials are rotated.

## Consequences
- Prerequisite for 06_Plan 5.1: live-DB inspection uses `legacy_ro`, never the app's credentials.
- None of this applies to the rewrite, whose rules (04_TRD §5) already forbid each of these defects.
