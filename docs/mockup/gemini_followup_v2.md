# Follow-up to gemini_prompt_final.md (send after Gemini finishes the full build)

```text
Keep the same Canvas file. Do NOT restart or remove other modules. Apply these changes and return the complete file.

1) REMOVE SHIFTS COMPLETELY (the client does not use shifts)
- Delete:
  - the register/shift pill, shift menu, Close shift / Z-report modal, X-report and denomination grid
  - "Shifts" under Money, the Shift/Z history report, the Cash in/out (F7) modal and button
  - every "shift" mention in notifications and mock data
- Replace the register pill with a "Today" pill: "Today ৳84,250 · 63 sales" → popover with today's summary (sales, returns, discounts, cash/bKash/card totals, dues created, dues collected) + "Print day summary".
- Add a "Daily cash summary" report under Reports instead.

2) DISCOUNT: fully built, fast, visible
- LINE discount:
  - select a cart line, press F6 (or click its price) → small popover
  - tabs: % · ৳ amount · Set price
  - quick chips 5 % / 10 % / 15 % / 20 %
  - optional reason
  - the line shows the struck-through original price + a "−10 %" badge
- ORDER discount, Shift+F6: % or ৳ on the whole bill, shown as its own totals row "Discount (order) −৳300".
- GROUP discount: if the attached customer's group has a discount (e.g. Wholesale 10 %), it applies automatically, shown as "Group discount −10 %" with a remove (×) button (permission-gated).
- LIMITS: each role has a max discount (Cashier 10 %). Above it, an inline "Manager PIN" prompt appears in the popover. Show this flow.
- Totals order: Subtotal → Line discounts → Order discount → VAT 15 % → TOTAL. VAT is calculated after discount.
- Receipt + sale detail show discount per line and total discount. Add a "Discount report" (by cashier, reason, product) in Reports.

3) DUE / PARTIAL PAYMENT: fully built
- In the payment panel, if the tendered amount < total, the remainder becomes DUE automatically, shown in amber "Paid ৳3,000 · Due ৳1,690" (icon + text).
- A due requires an attached customer:
  - Walk-in → inline message "Attach a customer to sell on due (F4)" with the button
  - the customer's credit limit bar "Used ৳8,200 of ৳20,000" is shown. Over the limit → Manager PIN
  - customer PIN required
  - optional "Promise date" + note
- Full due (৳0 paid) is allowed with permission and shows "Full credit sale".
- Offline: due is disabled with the tooltip "Needs internet". Show this.
- Receipt shows: This bill due ৳1,690 · Previous due ৳1,200 · TOTAL DUE ৳2,890.
- Sales list: status Paid / Partial / Due badges, plus Paid and Due columns and totals.
- COLLECT DUE, reachable from:
  - POS: Ctrl+D when a customer is attached, or the customer chip menu
  - customer profile
  - sales row menu
  - Due Collection page
- The collect modal:
  - total due
  - open invoices with oldest-first auto allocation (editable)
  - partial amount allowed
  - tender (Cash/bKash/Nagad/Card/Bank)
  - remaining due shown live
  - Print money receipt + "Send WhatsApp thanks"
- Customer profile + statement reflect every partial payment.

4) RETURN: make it FAST (target: done in under 15 seconds)
- START from anywhere in the POS:
  - press F11, or SCAN THE RECEIPT barcode/QR or type the invoice no. into the normal scan box (it auto-detects an invoice and switches to return mode)
  - right-click a sales row → "Return items" opens the same mode
- NO RECEIPT: in return mode press F4. Find the customer by customer no./phone → their recent purchases → pick the item. If the customer is unknown, Manager PIN is required.
- RETURN MODE uses the same cart, with an amber "RETURN — DF1-R1-26000412" header:
  - the original lines are listed with sold qty, already returned qty and returnable qty
  - Space toggles a line, +/− or arrows set the qty (default = all returnable)
  - prices include the original line + pro-rated order discount; VAT is reversed
  - reason quick chips: Size issue · Colour · Defect · Changed mind · Other
  - "Defect" sets restock = Damaged (not sellable); others restock to sellable
- EXCHANGE in the same screen: keep scanning new items. They appear below as normal lines, and the footer shows the NET: "Customer pays ৳350" or "Refund ৳620".
- REFUND METHOD:
  - Cash
  - Original method (e.g. bKash)
  - Adjust against customer's due (default when the customer has due)
  - Store credit to the customer account
- POLICY (Settings → Returns): return window 7 days, exchange-only after 3 days, manager approval after the window, non-returnable categories (e.g. Hijab sale items). The return mode shows the policy result as a badge "Within 7-day window ✓".
- Multiple partial returns on one invoice are supported. The sale detail shows all returns.
- Finish: F10 → return/exchange receipt (80 mm) showing original invoice, returned items, new items, net, refund method, and the customer's new due/credit balance.
- Returns list (G T): search by return no., invoice no., customer no., product; filters (date, reason, refund method, cashier); return detail page.
- Reports: Returns by reason/product/cashier, return rate %.

Update the shortcut overlay, command palette and key-hints bar for: F6 line discount, Shift+F6 order discount, Ctrl+D collect due, F11 return/exchange.
```
