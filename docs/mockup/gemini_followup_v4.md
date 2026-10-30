# Follow-up v4: align the mockup with the finalized architecture and v1 scope (2026-10-30)

Source decisions: [ADR 0001](../adr/0001-application-architecture.md) (POS SPA + pairing), [ADR 0005](../adr/0005-variants-and-exchange.md) (variants + exchange in v1), 06_Plan open questions 9–12 (shifts dropped, post-MVP list).

Paste everything inside the fence into the same Gemini Canvas.

```text
Keep the same Canvas file. Do NOT restart, rename or remove any module, data or styling that works,
and keep every v3 fix (single modal system, key map, palette, permissions). Apply the parts below in
order. After each part, run its checks yourself, then continue. If you run out of output space, stop
at a part boundary, write "▶ NEXT: Part <n>" and wait. Return the complete file each time.

GLOBAL RULE FOR OUT-OF-SCOPE FEATURES
Anything listed as POST-MVP below must not look usable. Do not delete its data or code; instead:
- remove it from the sidebar, command palette, shortcuts and context menus;
- where a screen or setting already exists, replace its body with one neutral "Coming later" empty
  state (icon, title, one-line description, no buttons);
- remove its fields from forms, tables, KPI tiles, receipts and profiles.

PART 1: SCOPE CLEAN-UP (post-MVP → "Coming later" or removed)
1.1 Loyalty points, tiers, gift cards, vouchers, coupons, promotion engine: remove from customer
    profiles/lists (loyalty_points, tier), POS payment tenders, discounts dialog, receipts, reports,
    dashboard and settings. The membership card stays: it is an identity card (customer number +
    PIN), not a points card.
1.2 SMS: remove the automation rules screen, SMS channel options, SMS templates and SMS credits.
    Keep only "Share" actions that open a WhatsApp link (https://wa.me/?text=...) or copy a link.
    The Messaging centre becomes a "Share history" list, or "Coming later" if that is simpler.
1.3 VAT: replace every "NBR Mushak 6.3" / "Mushak" label with plain VAT wording, e.g.
    "15% Standard VAT". Keep BIN fields, per-line VAT and the VAT summary on A4 invoices.
    Remove any Mushak form generation screen.
1.4 Payment gateways: no bKash/Nagad/SSLCommerz API connection screens. bKash, Nagad, Rocket, Card
    and Bank stay as manual tenders with a "Reference / TrxID" field.
1.5 Purchase orders and purchase returns: "Coming later". Purchases stay: draft → received / cancelled.
1.6 Backup restore UI: remove. Backups settings show the schedule, last backup time and status only.
1.7 Super Admin: keep only the tenants list, "Provision tenant" and "Impersonate" (with a visible
    banner while impersonating). Remove health dashboards, billing and plan screens.
Checks: search the whole UI for "points", "tier", "gift", "voucher", "coupon", "SMS", "Mushak",
"gateway", "purchase order", "restore": nothing usable remains.

PART 2: NO SHIFTS, TODAY SUMMARY, DAILY CASH SUMMARY
2.1 There are no shifts, opening float, cash count, cash in/out or X/Z reports anywhere. Remove any
    leftover shift pill, menu, modal, report or permission.
2.2 Top bar register pill: "R1 · 12 sales today · 2 unsynced". Click opens a popover with
    "Today summary" and "Sync center".
2.3 F7 = Today summary on the POS (read-only modal, works offline): number of sales, gross, discounts,
    returns, net, totals per tender, cash taken, unsynced count, and the last sync time. Print button
    (80 mm). Update the key-hints bar, shortcut overlay and palette (F7 is no longer "Cash in/out").
2.4 Reports → "Daily cash summary": per branch per day, cash received (sales + due collections) −
    cash refunds − cash expenses from the cash book = net cash. Date range, branch filter, export.
Checks: F7 opens Today summary; grep for "shift", "float", "Z report", "cash in": no matches.

PART 3: SIZE × COLOUR VARIANTS (in v1)
Data model to mock (keep it consistent everywhere):
- A product FAMILY (a style) has up to 2 options, e.g. Size (S, M, L, XL, XXL, Free) and Colour
  (White, Black, Navy, Maroon...). Each combination is its own sellable PRODUCT (variant) with its
  own SKU (FAMILYCODE-M-BLK), barcode, sale price, cost and stock per branch. Standalone products
  (no family) still exist.
3.1 Catalog → Products list: a family row expands (→ / ← or click) to show its variants with stock per
    branch. Columns: name + variant label ("M / Black"), SKU, barcode, price, stock.
3.2 "New product" asks: Single product, or Product with variants. Variant flow: family details
    (name EN/BN, code, category, brand, unit, VAT, image) → pick options + values → a size × colour
    MATRIX of checkboxes → generated variant table (editable SKU, barcode (blank = auto), sale price,
    cost; "apply to all" for price and cost) → save.
3.3 Settings → Catalog → Variant options: manage options and values (value, Bangla value, short code).
3.4 "Group into family": select standalone products in the list → context menu → choose or create a
    family and map each product to its size/colour.
3.5 POS: scanning a variant barcode adds that variant directly. Searching a family name shows one
    result "Polo Shirt (12 variants)"; Enter opens the VARIANT PICKER: size columns × colour rows,
    each cell shows local stock (greyed at 0), arrows move, Enter adds, Esc closes. The cart line
    shows "Polo Shirt · M / Black".
3.6 Receipts, sales detail, returns and stock pages show the variant label.
3.7 Reports: "Sales by family (style)" with drill-down to variants; low stock lists variants.
Checks: create a family with 3 sizes × 2 colours → 6 variants; scan one variant; open the picker
with the keyboard only.

PART 4: EXCHANGE (in v1, online only)
4.1 POS F11 = Exchange (no longer "Return/Exchange"; returns stay in the back office and in the
    Sale row context menu). Flow in one modal with 3 steps:
    1) scan or type the receipt number → show the original sale lines with returnable qty;
    2) choose the lines and qty coming back (reason per line) + add replacement items with the
       normal scan box and variant picker;
    3) settlement: "Return value ৳X (from server)", "New items ৳Y", difference.
       - Y > X: customer pays Y − X with normal tenders (cash, card, bKash...).
       - Y < X: refund X − Y by tender, or "Credit to customer account" (members only).
       - Y = X: "Even exchange", complete with one key.
    Complete prints ONE receipt with both parts and the net amount.
4.2 The payment panel shows a read-only tender line "Exchange credit ৳X" (never cash).
4.3 Offline: F11 shows "Exchange needs an internet connection" and offers to hold the cart.
4.4 Back office: Sales → sale row → "Exchange items" opens the same 3-step flow as a page.
4.5 Sales list: exchanges show a badge "Exchange" and link return ↔ new sale both ways.
Checks: even, pay-more and refund-less exchanges all complete; offline F11 shows the message.

PART 5: POS IS A STANDALONE REGISTER + DEVICE PAIRING
5.1 The POS opens at /pos as its own full-screen app (no back-office sidebar). A small "Back office"
    link opens the back office in a new tab.
5.2 First-run (unpaired) screen: "Pair this register". An 8-character code input in 2 groups
    (e.g. K7QF-2MXD) with auto-uppercase and paste support, plus "Scan QR with camera". Errors:
    "Code expired", "Code already used", "Wrong code (3 attempts left)". On success: "Paired to
    DIFFERENT FASHION · Branch DF1 · Register R1" → staff login.
5.3 Back office → Settings → Registers & devices: each register shows its paired device (name,
    browser, last seen, app version, unsynced count, local storage used) and "Revoke" (confirm,
    with the warning "the device erases its local data on next connection"). "Pair register" opens
    a dialog with the code in large monospace text, a QR code and a 10:00 countdown, then flips to
    "Paired ✓" when the device uses it. Remove "Mobile POS Companion" wording.
5.4 Revoked device demo: the POS shows "This register was revoked. Local data erased." → pairing screen.
Checks: pairing works by keyboard only; countdown expiry shows "Code expired"; revoke → wipe screen.

PART 6: FINAL CONSISTENCY PASS
- Every new action has a shortcut or palette entry and a context-menu entry where it applies.
  Shift+F10 opens the context menu on rows and tiles.
- Every new string exists in English and Bangla.
- No product name or brand anywhere except the demo tenant "DIFFERENT FASHION".
- Semantic colour tokens only; visible focus; 44 px touch targets; 360 px phone layout still works.
```
