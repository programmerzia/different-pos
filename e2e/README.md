# Keyboard-only browser tests

`npm run e2e` runs Playwright against real Chrome (`channel: 'chrome'`, no downloaded browsers).
`global-setup.ts` creates the throwaway `pos_e2e` database (MySQL container `pos-rewrite-mysql-1`), runs `migrate:fresh --seed` plus `E2eSeeder`, then builds the front end (skip with `E2E_SKIP_BUILD=1`).
The `webServer` entry starts `php artisan serve` on port 8123 with `DB_DATABASE=pos_e2e`, so the dev `pos` database is never touched.
Specs run in file order on one worker (`01-` to `05-`): the sale feeds the due, return and list checks. Only `page.keyboard` is used.
Login as `owner@demo.test` / `password` (email login needs no business code); shared helpers are in `e2e/support.ts`.
