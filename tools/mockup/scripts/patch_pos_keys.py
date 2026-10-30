#!/usr/bin/env python3
"""
scripts/patch_pos_keys.py
Updates render_pos_sales_returns.py with the exact spec key map:
- F2 for scan input (placeholder + kbd)
- F5 for price check
- F8 for hold, Shift+F8 for held list
- F9 for PAY button (replacing split buttons)
- F3 for line quantity editing with input id `cart-line-qty-${idx}`
"""

import os

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET_FILE = os.path.join(BASE_DIR, "scripts", "parts", "render_pos_sales_returns.py")

with open(TARGET_FILE, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Update Scan Input
old_scan_box = """placeholder={posMode === 'return' ? 'Scan exchange item barcode or product name...' : 'Scan barcode, SKU, or receipt barcode/invoice DF1-... (F3)'}
                            className={`w-full h-9 pl-8 pr-16 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              posMode === 'return' ? 'border-amber-500 focus:border-amber-600' : 'border-[var(--border)] focus:border-[var(--primary)]'
                            }`}
                          />
                          <Icon name="search" size={14} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)]" />
                          <div className="absolute right-2 top-2 flex items-center gap-1">
                            <kbd className="text-[10px]">F3</kbd>
                          </div>"""

new_scan_box = """id="pos-barcode-input"
                            placeholder={posMode === 'return' ? 'Scan exchange item barcode or product name...' : 'Scan barcode, SKU, or receipt barcode/invoice DF1-... (F2)'}
                            className={`w-full h-9 pl-8 pr-16 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              posMode === 'return' ? 'border-amber-500 focus:border-amber-600' : 'border-[var(--border)] focus:border-[var(--primary)]'
                            }`}
                          />
                          <Icon name="search" size={14} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)]" />
                          <div className="absolute right-2 top-2 flex items-center gap-1">
                            <kbd className="text-[10px]">F2</kbd>
                          </div>"""

if old_scan_box in content:
    content = content.replace(old_scan_box, new_scan_box)
    print("Replaced scan box placeholder and shortcut.")
else:
    print("Warning: old_scan_box not found directly, checking variations...")

# 2. Update Price Check Button
old_price_btn = """title="Price & Stock Check (F7)"
                        >
                          <Icon name="tag" size={14} />
                          <span className="hidden sm:inline">Price Check</span>
                          <kbd className="text-[10px]">F7</kbd>"""

new_price_btn = """title="Price & Stock Check (F5)"
                        >
                          <Icon name="tag" size={14} />
                          <span className="hidden sm:inline">Price Check</span>
                          <kbd className="text-[10px]">F5</kbd>"""

if old_price_btn in content:
    content = content.replace(old_price_btn, new_price_btn)
    print("Replaced price check button shortcut (F5).")

# 3. Update Function Bar (F8 hold, Shift+F8 held list, F4, F6, Shift+F6, Ctrl+D, F11)
old_func_bar = """{/* POS Function Bar (F1-F11 shortcuts) */}
                    <div className="p-1.5 bg-[var(--surface-sunken)] border-t border-[var(--border)] flex items-center justify-between overflow-x-auto text-[11px] gap-1 shrink-0">
                      <button onClick={holdSale} className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium">
                        <Icon name="pause" size={12} />
                        <span>Hold</span>
                        <kbd className="text-[9px]">F2</kbd>
                      </button>
                      <button onClick={() => setShowCustomerAttachModal(true)} className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium">
                        <Icon name="user" size={12} />
                        <span>Customer</span>
                        <kbd className="text-[9px]">F4</kbd>
                      </button>"""

new_func_bar = """{/* POS Function Bar (Exact Hotkeys from Spec) */}
                    <div className="p-1.5 bg-[var(--surface-sunken)] border-t border-[var(--border)] flex items-center justify-between overflow-x-auto text-[11px] gap-1 shrink-0">
                      <button onClick={holdSale} className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium cursor-pointer" title="Hold current sale (F8)">
                        <Icon name="pause" size={12} />
                        <span>Hold</span>
                        <kbd className="text-[9px]">F8</kbd>
                      </button>
                      <button onClick={() => setShowHeldSalesModal(true)} className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium cursor-pointer" title="Parked / Held Sales List (Shift+F8)">
                        <Icon name="layers" size={12} />
                        <span>Held List</span>
                        <kbd className="text-[9px]">⇧F8</kbd>
                      </button>
                      <button onClick={() => setShowCustomerAttachModal(true)} className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium cursor-pointer" title="Attach Customer (F4)">
                        <Icon name="user" size={12} />
                        <span>Customer</span>
                        <kbd className="text-[9px]">F4</kbd>
                      </button>"""

if old_func_bar in content:
    content = content.replace(old_func_bar, new_func_bar)
    print("Replaced POS function bar buttons.")

# 4. Update Qty Stepper to support direct input with id `cart-line-qty-${idx}`
old_qty_stepper = """{/* Qty Stepper */}
                              <div className="flex items-center gap-1">
                                <button
                                  onClick={(e) => { e.stopPropagation(); updateCartQty(idx, -1); }}
                                  className="w-6 h-6 rounded bg-[var(--surface-sunken)] hover:bg-[var(--border)] flex items-center justify-center text-[var(--foreground)]"
                                >
                                  <Icon name="minus" size={12} />
                                </button>
                                <span className="w-6 text-center font-bold text-xs num">{item.qty}</span>
                                <button
                                  onClick={(e) => { e.stopPropagation(); updateCartQty(idx, 1); }}
                                  className="w-6 h-6 rounded bg-[var(--surface-sunken)] hover:bg-[var(--border)] flex items-center justify-center text-[var(--foreground)]"
                                >
                                  <Icon name="plus" size={12} />
                                </button>
                              </div>"""

new_qty_stepper = """{/* Qty Stepper with direct numeric edit (F3) */}
                              <div className="flex items-center gap-1">
                                <button
                                  type="button"
                                  onClick={(e) => { e.stopPropagation(); updateCartQty(idx, -1); }}
                                  className="w-6 h-6 rounded bg-[var(--surface-sunken)] hover:bg-[var(--border)] flex items-center justify-center text-[var(--foreground)] cursor-pointer"
                                  title="Decrease quantity"
                                >
                                  <Icon name="minus" size={12} />
                                </button>
                                <input
                                  type="number"
                                  id={`cart-line-qty-${idx}`}
                                  value={item.qty}
                                  onChange={(e) => {
                                    const val = Math.max(1, parseInt(e.target.value, 10) || 1);
                                    setCart(prev => {
                                      const next = [...prev];
                                      next[idx] = { ...next[idx], qty: val };
                                      return next;
                                    });
                                  }}
                                  onFocus={() => setSelectedCartLineIndex(idx)}
                                  className="w-10 h-6 text-center font-bold font-mono text-xs num bg-[var(--surface-sunken)] border border-[var(--border)] rounded"
                                  title="Line Quantity (F3 to edit)"
                                />
                                <button
                                  type="button"
                                  onClick={(e) => { e.stopPropagation(); updateCartQty(idx, 1); }}
                                  className="w-6 h-6 rounded bg-[var(--surface-sunken)] hover:bg-[var(--border)] flex items-center justify-center text-[var(--foreground)] cursor-pointer"
                                  title="Increase quantity"
                                >
                                  <Icon name="plus" size={12} />
                                </button>
                              </div>"""

if old_qty_stepper in content:
    content = content.replace(old_qty_stepper, new_qty_stepper)
    print("Replaced qty stepper with direct numeric input.")

# 5. Update bottom payment section to use single prominent PAY (F9) button
old_pay_section = """{posMode === 'sale' && (
                      <div className="p-2.5 border-t border-[var(--border)] bg-[var(--surface)] grid grid-cols-2 gap-2 shrink-0">
                        <button
                          onClick={() => completeSale('exact_cash')}
                          disabled={cart.length === 0}
                          className="h-14 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-bold text-[var(--foreground)] flex flex-col items-center justify-center transition-colors disabled:opacity-40"
                        >
                          <span>Exact Cash (F11)</span>
                          <span className="text-[10px] font-mono text-[var(--muted-foreground)]">{formatMoney(totalPayable)}</span>
                        </button>

                        <button
                          onClick={() => {
                            if (cart.length > 0) {
                              setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                              setShowPaymentModal(true);
                            }
                          }}
                          disabled={cart.length === 0}
                          className="h-14 rounded-xl bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-sm font-bold flex flex-col items-center justify-center transition-colors shadow-md disabled:opacity-40"
                        >
                          <div className="flex items-center gap-1.5">
                            <Icon name="creditCard" size={16} />
                            <span>Pay / Partial / Due (F10)</span>
                          </div>
                          <span className="text-[11px] font-mono font-medium opacity-90">Pay Now or Record Due</span>
                        </button>
                      </div>
                    )}"""

new_pay_section = """{posMode === 'sale' && (
                      <div className="p-2.5 border-t border-[var(--border)] bg-[var(--surface)] shrink-0">
                        <button
                          onClick={() => {
                            if (cart.length > 0) {
                              setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                              setShowPaymentModal(true);
                            }
                          }}
                          disabled={cart.length === 0}
                          className="w-full h-14 rounded-xl bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-sm font-bold flex items-center justify-between px-5 transition-colors shadow-md disabled:opacity-40 cursor-pointer"
                          title="Open Payment & Due Settlement (F9)"
                        >
                          <div className="flex items-center gap-2.5">
                            <div className="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center">
                              <Icon name="creditCard" size={18} />
                            </div>
                            <div className="text-left">
                              <div className="flex items-center gap-2">
                                <span className="text-base tracking-wide">PAY</span>
                                <kbd className="px-1.5 py-0.5 rounded bg-black/30 text-white text-[10px] font-mono font-bold">F9</kbd>
                              </div>
                              <div className="text-[11px] font-normal opacity-90">Cash, Card, MFS or Due Sale</div>
                            </div>
                          </div>
                          <div className="text-right">
                            <div className="text-base font-mono font-bold num">{formatMoney(totalPayable)}</div>
                            <div className="text-[10px] opacity-80">{totalItemCount} items</div>
                          </div>
                        </button>
                      </div>
                    )}"""

if old_pay_section in content:
    content = content.replace(old_pay_section, new_pay_section)
    print("Replaced pay section with single prominent PAY (F9) button.")

with open(TARGET_FILE, "w", encoding="utf-8") as f:
    f.write(content)

print("render_pos_sales_returns.py patch complete.")
