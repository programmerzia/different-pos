# ADR 0009: PDF rendering with mPDF and the bundled Hind Siliguri font

**Status:** accepted, 2026-11-03

## Context
Reports, list exports, receipts and membership cards need server-side PDFs with correctly shaped Bangla
(conjuncts, reordered vowel signs) and `৳` amounts, on a host without a browser. 04_TRD §2 pinned
`mpdf/mpdf ^8.3` for this but deferred it until `ext-gd` existed on the dev machine; it does now.

## Decision
- `mpdf/mpdf` 8.3 is the only PDF engine, wrapped by `App\Support\Pdf\PdfRenderer` (`html()` for any
  document, `table()` for a `ReportResult`: A4, portrait up to 6 columns then landscape, tenant header
  with logo/business/branch, period, generated-at in the tenant timezone, page `n / N` footer, money
  right-aligned via `MoneyFormatter`). `CardRenderer::pdf()` keeps its own mPDF page sizes (CR80) but
  no longer needs a guard.
- Bangla shaping uses mPDF's built-in OpenType layout (`useOTL => 0xFF`, `useKashida => 75`) with
  **Hind Siliguri** (Regular + Bold) in `resources/fonts/`, also the default font so EN and BN mix on one
  line. mPDF's own font cache lives in `storage/app/mpdf` (ignored by git, created on demand).
- Tabular PDFs are rendered synchronously and capped at `PdfRenderer::MAX_TABLE_ROWS` (2 000); above
  that the export answers a validation error pointing at CSV/XLSX, never a truncated file.
- Spreadsheet downloads are real `.xlsx` through `App\Support\Export\XlsxWriter` (spatie/simple-excel over
  openspout, streamed): money and quantities are numeric cells with number formats, dates are date cells.
  The numeric money cell is the one place a taka double exists, computed from integer paisa at the edge.

## Licence
Hind Siliguri is © 2015 Indian Type Foundry, licensed under the SIL Open Font License 1.1. The licence
text ships beside the font files as `resources/fonts/OFL-HindSiliguri.txt`; the OFL allows bundling and
embedding (subsetting) in PDFs. Nothing else in the fonts directory.

## Consequences
- `php -m` must list `gd` (and `mbstring`) wherever PDFs render; production images need the fonts dir readable.
- Every tabular export (reports and list pages) goes through `App\Support\Export\TabularExport`, so a new
  report or list gets CSV/XLSX/PDF by returning a `ReportResult`.
