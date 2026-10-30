# Mockup tooling (not part of the application)

Build scripts and the compiled single-file mockup that used to live in the repo root.
They were moved here on 2026-10-30 so the Laravel scaffold could own the root `vendor/` (Composer).

- `index.html` is the compiled mockup. `docs/mockup/index.html` is the same build with paths adjusted for `docs/mockup/`.
- `vendor/` holds the offline React UMD bundles used by both copies.
- Run the scripts **from this folder** (`cd tools/mockup && node scripts/build_production.js`). They write `../../docs/mockup/index.html`.
