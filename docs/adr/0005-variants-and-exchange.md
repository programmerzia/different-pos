# ADR 0005: Size × colour variants and POS exchange in v1

- **Status:** Accepted (2026-10-30). Owner decision on open question 9: variants and exchange are **in** v1. Loyalty, gift cards, coupons, purchase orders and purchase returns stay **out** (post-MVP).
- **Affects:** 01_PRD (B5, E5, non-goal 3), 02_AppFlow (routes, keys, flows), 04_TRD §3.9, 05_Schema §2 + §6, 06_Plan.

## Context
DIFFERENT FASHION sells clothing. One style comes in several sizes and colours. Customers exchange a size or colour often. The legacy app has neither feature: each size is a separate product, and an exchange is a return followed by a new invoice.

## Decision

### V1. A variant *is* a product; a family groups them
- `products` stays the **only sellable, stock-keeping unit**. Each size/colour combination is one `products` row with its own SKU, barcode, prices and stock.
- New `product_families` (the style/parent) holds the shared attributes: name, category, brand, unit, tax rate, image and description. `products.family_id` points to it (NULL = standalone product, e.g. every migrated legacy product).
- Options are tenant-defined (`variant_options`: Size, Colour; `variant_option_values`: S, M, L…). A family uses **at most 2 options** in v1, so it fits a 2-D matrix in the UI.
- `GenerateFamilyVariants` creates the missing combinations from the matrix. Default SKU is `{family_code}-{value1}-{value2}`, and blank barcodes are auto-generated. `AttachProductsToFamily` groups existing standalone products (e.g. imported legacy "Shirt M" + "Shirt L") into a family after migration.
- **Unchanged because of this choice:** `StockLedger`, FIFO batches, the pricing twin (lines are still products), sale/return/purchase lines, numbering and offline sync of sales. Variants are a catalog and UI concern only.
- POS: scanning a variant barcode adds it directly. A search hit on a family opens a **variant picker** (size × colour grid with local stock per cell). The device pulls `families`, `variant_options` and `variant_option_values`. Products carry `family_id` + `variant_label`.
- Reports gain a "by family (style)" roll-up.

### V2. Exchange = one return + one sale, in one transaction, online only
- `CreateExchange` (final Action, one `DB::transaction()`, standard locking order) runs `CreateSaleReturn` (`refund_mode=exchange`) and then `CompleteSale` for the replacement items. The return value is applied to the new sale as a tender of the seeded payment method **Exchange credit** (type `exchange_credit`, no account, never cash).
- **Difference:** if the new sale costs more, the customer pays the rest with normal tenders. If it costs less, the surplus is refunded (cash/tender) or credited to the customer's account (member only). It is never left unallocated.
- The return value comes from the **server** (original net line prices, discount prorated). The POS shows a server quote (`POST /api/pos/v1/exchanges/quote`) and then submits (`POST /api/pos/v1/exchanges`). There is no offline exchange in v1, the same rule as returns: the original sale, its returnable quantities and the customer balance live on the server.
- Numbering: the return gets a back-office number (`DocumentNumberer`). The replacement sale gets the **device number** (the device sends its next number; the server enforces `> registers.last_number`, as for pushed sales), so the register's receipts stay one sequence. The device increments its counter only after success.
- Links: `sale_returns.exchange_sale_id` ↔ `sales.exchange_return_id`. The receipt prints both parts and the net amount.
- Permissions: `returns.create` + `pos.sell`. Return rules apply (qty ≤ sold − already returned).

## Consequences
- Small schema addition, no change to the money or stock core.
- A POS exchange needs internet. When offline, F11 shows "Exchange needs a connection" and offers a hold.
- The legacy importer stays as is (standalone products). Grouping into families is an owner task after go-live, with `AttachProductsToFamily`.
