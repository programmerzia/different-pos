#!/usr/bin/env python3
"""
scripts/convert_modals_1_to_9.py
Converts Modals 1, 2, 3, 4, 7, 8, 9 in render_all_modals.py to use <Modal ...>
"""

import os
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET_FILE = os.path.join(BASE_DIR, "scripts", "parts", "render_all_modals.py")

with open(TARGET_FILE, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Customer Attach (F4)
old_attach_pattern = re.compile(
    r"\{\s*/\*\s*1\.\s*CUSTOMER ATTACH MODAL \(F4\)\s*\*/\s*\}\s*\{showCustomerAttachModal && \(.*?\)\s*\}\s*\{/\* 2\.\s*QUICK NEW CUSTOMER",
    re.DOTALL
)

new_attach = """{/* 1. CUSTOMER ATTACH MODAL (F4) */}
          {showCustomerAttachModal && (
            <Modal
              isOpen={showCustomerAttachModal}
              onClose={() => setShowCustomerAttachModal(false)}
              title="Attach Customer / Member (F4)"
              subtitle="Search by phone, name, or #1000 0017"
              icon="user"
              size="lg"
            >
              <div className="flex gap-2">
                <div className="relative flex-1">
                  <input
                    type="text"
                    autoFocus
                    value={customerSearchQuery}
                    onChange={(e) => setCustomerSearchQuery(e.target.value)}
                    placeholder="Search phone, name, or #1000 0017..."
                    className="w-full h-8 pl-8 pr-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                  />
                  <Icon name="search" size={14} className="absolute left-2.5 top-2 text-[var(--muted-foreground)]" />
                </div>
                <button
                  onClick={() => { setShowCustomerAttachModal(false); setShowQuickNewCustomerModal(true); }}
                  className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1 shadow-xs cursor-pointer"
                >
                  <Icon name="plus" size={14} />
                  <span>New (Ctrl+N)</span>
                </button>
              </div>

              <div className="max-h-80 overflow-y-auto divide-y divide-[var(--border)] mt-2">
                {MOCK_CUSTOMERS.filter(c => {
                  const q = customerSearchQuery.toLowerCase();
                  return !q || c.name_en.toLowerCase().includes(q) || c.phone.includes(q) || c.customer_no.includes(q);
                }).map(cust => (
                  <div
                    key={cust.id}
                    onClick={() => {
                      setAttachedCustomer(cust);
                      setIsGroupDiscountRemoved(false);
                      setShowCustomerAttachModal(false);
                      addToast(`Attached ${cust.name_en} to sale`, "success");
                    }}
                    className="p-2.5 hover:bg-[var(--surface-sunken)] cursor-pointer flex items-center justify-between transition-colors rounded-lg"
                  >
                    <div>
                      <div className="font-bold text-xs text-[var(--foreground)]">{cust.name_en}</div>
                      <div className="text-[11px] text-[var(--muted-foreground)] flex items-center gap-2">
                        <span>{cust.phone}</span>
                        <span>·</span>
                        <span className="font-mono">#{cust.customer_no}</span>
                      </div>
                    </div>
                    <div className="text-right">
                      <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-[var(--surface-sunken)] border border-[var(--border)]">
                        {cust.group}
                      </span>
                      {cust.balance < 0 && (
                        <div className="text-[10px] text-amber-600 font-bold mt-0.5">
                          Due: {formatMoney(Math.abs(cust.balance))}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </Modal>
          )}

          {/* 2. QUICK NEW CUSTOMER"""

match = old_attach_pattern.search(content)
if match:
    content = content[:match.start()] + new_attach + content[match.end():]
    print("Wrapped Modal 1 (Customer Attach) in <Modal>.")
else:
    print("Warning: old_attach_pattern not matched!")

# 2. Price Check (F5)
old_price_pattern = re.compile(
    r"\{\s*/\*\s*7\.\s*PRICE CHECK MODAL.*?\}\s*\{showPriceCheckModal && \(.*?\)\s*\}\s*\{\s*/\*\s*8B\.",
    re.DOTALL
)

new_price = """{/* 7. PRICE & STOCK CHECK MODAL (F5) */}
          {showPriceCheckModal && (
            <Modal
              isOpen={showPriceCheckModal}
              onClose={() => setShowPriceCheckModal(false)}
              title="Price & Stock Check (F5)"
              subtitle="Scan barcode or type SKU to instantly verify price & stock"
              icon="tag"
              size="lg"
            >
              <div className="p-1">
                <input
                  type="text"
                  autoFocus
                  value={priceCheckQuery}
                  onChange={(e) => setPriceCheckQuery(e.target.value)}
                  placeholder="Scan barcode or type product name/SKU..."
                  className="w-full h-9 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-mono"
                />
              </div>

              <div className="max-h-72 overflow-y-auto divide-y divide-[var(--border)] mt-2">
                {INITIAL_PRODUCTS.filter(p => {
                  const q = priceCheckQuery.toLowerCase();
                  return !q || p.name_en.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || p.barcode.includes(q);
                }).slice(0, 10).map(prod => (
                  <div key={prod.id} className="p-2.5 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded-lg">
                    <div>
                      <div className="font-semibold text-xs text-[var(--foreground)]">{prod.name_en}</div>
                      <div className="text-[11px] text-[var(--muted-foreground)] font-mono">{prod.sku} · {prod.barcode}</div>
                    </div>
                    <div className="text-right">
                      <div className="font-bold text-sm text-[var(--primary)] font-mono">{formatMoney(prod.price)}</div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Main Stock: {prod.stock_main} pcs</div>
                    </div>
                  </div>
                ))}
              </div>
            </Modal>
          )}

          {/* 8B."""

match = old_price_pattern.search(content)
if match:
    content = content[:match.start()] + new_price + content[match.end():]
    print("Wrapped Modal 7 (Price Check) in <Modal>.")
else:
    print("Warning: old_price_pattern not matched!")

# 3. Line / Order Discount (F6 / Shift+F6)
old_discount_pattern = re.compile(
    r"\{\s*/\*\s*8\.\s*DISCOUNT POPOVER / MODAL.*?\}\s*\{showDiscountModal && \(.*?\)\s*\}\s*\{\s*/\*\s*9\.\s*COLLECT DUE",
    re.DOTALL
)

# In Modal 8, we keep the existing inner form but wrap with <Modal>
# Let's inspect old discount modal structure
# It starts at: {showDiscountModal && (
# <div className="fixed inset-0 bg-black/60 ...
# and ends right before {/* 9. COLLECT DUE
old_disc_header = """          {/* 8. DISCOUNT POPOVER / MODAL (F6 line, Shift+F6 order) */}
          {showDiscountModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowDiscountModal(false)}></div>
              <div className="relative z-50 w-full max-w-md bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-5 space-y-4 text-xs animate-in zoom-in-95 duration-100">
                <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="percent" size={16} className="text-[var(--primary)]" />
                    <span>{discountTarget === 'line' ? 'Line Discount (F6)' : 'Order Discount (Shift+F6)'}</span>
                  </div>
                  <button onClick={() => setShowDiscountModal(false)}><Icon name="x" size={14} /></button>
                </div>"""

new_disc_header = """          {/* 8. DISCOUNT POPOVER / MODAL (F6 line, Shift+F6 order) */}
          {showDiscountModal && (
            <Modal
              isOpen={showDiscountModal}
              onClose={() => setShowDiscountModal(false)}
              title={discountTarget === 'line' ? "Line Item Discount (F6)" : "Order / Bill Discount (Shift+F6)"}
              subtitle={discountTarget === 'line' ? `Applying discount to: ${cart[selectedCartLineIndex]?.product?.name_en || 'Selected line'}` : "Applying discount to entire cart payable before VAT"}
              icon="percent"
              size="md"
            >"""

if old_disc_header in content:
    content = content.replace(old_disc_header, new_disc_header)
    # also fix the 3 closing divs
    old_disc_footer = """                    <button
                      type="button"
                      onClick={() => {
                        const val = Number(discountValue) || 0;
                        if (discountTarget === 'line') {
                          if (val > currentRoleLimit && discountManagerPin !== '1234' && discountManagerPin !== '9999') {
                            setShowManagerPinInline(true);
                            addToast(`Discounts > ${currentRoleLimit}% require Manager PIN`, "warning");
                            return;
                          }
                          applyLineDiscount(selectedCartLineIndex, val, discountTab, discountReason);
                        } else {
                          if (discountTab === 'percent' && val > currentRoleLimit && discountManagerPin !== '1234' && discountManagerPin !== '9999') {
                            setShowManagerPinInline(true);
                            addToast(`Order discounts > ${currentRoleLimit}% require Manager PIN`, "warning");
                            return;
                          }
                          applyOrderDiscount(val, discountTab, discountReason);
                        }
                        setShowDiscountModal(false);
                      }}
                      className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-white font-semibold shadow-xs"
                    >
                      Apply Discount
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}"""
    new_disc_footer = """                    <button
                      type="button"
                      onClick={() => {
                        const val = Number(discountValue) || 0;
                        if (discountTarget === 'line') {
                          if (val > currentRoleLimit && discountManagerPin !== '1234' && discountManagerPin !== '9999') {
                            setShowManagerPinInline(true);
                            addToast(`Discounts > ${currentRoleLimit}% require Manager PIN`, "warning");
                            return;
                          }
                          applyLineDiscount(selectedCartLineIndex, val, discountTab, discountReason);
                        } else {
                          if (discountTab === 'percent' && val > currentRoleLimit && discountManagerPin !== '1234' && discountManagerPin !== '9999') {
                            setShowManagerPinInline(true);
                            addToast(`Order discounts > ${currentRoleLimit}% require Manager PIN`, "warning");
                            return;
                          }
                          applyOrderDiscount(val, discountTab, discountReason);
                        }
                        setShowDiscountModal(false);
                      }}
                      className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-white font-semibold shadow-xs cursor-pointer"
                    >
                      Apply Discount
                    </button>
                  </div>
                </div>
            </Modal>
          )}"""
    content = content.replace(old_disc_footer, new_disc_footer)
    print("Wrapped Modal 8 (Discount) in <Modal>.")

# 4. Payment Modal (F9)
old_payment_header = """          {/* 4. SPLIT PAYMENT & AUTOMATIC DUE / PARTIAL PANEL (F10) */}
          {showPaymentModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-2xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl overflow-hidden flex flex-col">
                <div className="p-3.5 border-b border-[var(--border)] bg-[var(--surface-sunken)] flex items-center justify-between">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="creditCard" size={16} />
                    <span>Payment Tender & Due Settlement (F10)</span>
                  </div>
                  <button onClick={() => setShowPaymentModal(false)}><Icon name="x" size={16} /></button>
                </div>"""

new_payment_header = """          {/* 4. SPLIT PAYMENT & AUTOMATIC DUE / PARTIAL PANEL (F9) */}
          {showPaymentModal && (
            <Modal
              isOpen={showPaymentModal}
              onClose={() => setShowPaymentModal(false)}
              title="Payment & Due Settlement (F9)"
              subtitle="Settle sale with Cash, Card, MFS or Due"
              icon="creditCard"
              size="2xl"
              preventBackdropClose={totalTendered > 0}
              onDiscardConfirm={true}
            >"""

if old_payment_header in content:
    content = content.replace(old_payment_header, new_payment_header)
    # and update payment modal footer closing
    content = content.replace(
        "                    </button>\n                  </div>\n                </div>\n              </div>\n            </div>\n          )}",
        "                    </button>\n                  </div>\n                </div>\n            </Modal>\n          )}"
    )
    print("Wrapped Modal 4 (Payment) in <Modal>.")

# 5. Quick New Customer (Ctrl+N)
old_quick_header = """          {/* 2. QUICK NEW CUSTOMER MODAL (Ctrl+N) */}
          {showQuickNewCustomerModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowQuickNewCustomerModal(false)}></div>
              <div className="relative z-50 w-full max-w-md bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-5 space-y-3.5 text-xs animate-in zoom-in-95 duration-100">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="userPlus" size={16} className="text-[var(--primary)]" />
                    <span>Quick New Customer (Ctrl+N) — দ্রুত গ্রাহক নিবন্ধন</span>
                  </div>
                  <button onClick={() => setShowQuickNewCustomerModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">
                    <Icon name="x" size={16} />
                  </button>
                </div>"""

new_quick_header = """          {/* 2. QUICK NEW CUSTOMER MODAL (Ctrl+N) */}
          {showQuickNewCustomerModal && (
            <Modal
              isOpen={showQuickNewCustomerModal}
              onClose={() => setShowQuickNewCustomerModal(false)}
              title="Quick New Customer (Ctrl+N)"
              subtitle="Instantly register customer without leaving checkout flow"
              icon="user"
              size="md"
            >"""

if old_quick_header in content:
    content = content.replace(old_quick_header, new_quick_header)
    content = content.replace(
        "                      setShowQuickNewCustomerModal(false);\n                    }}\n                    className=\"w-full h-8 rounded-lg bg-[var(--primary)] text-white font-semibold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer\"\n                  >\n                    <Icon name=\"check\" size={14} />\n                    <span>Save & Attach</span>\n                  </button>\n                </div>\n              </div>\n            </div>\n          )}",
        "                      setShowQuickNewCustomerModal(false);\n                    }}\n                    className=\"w-full h-8 rounded-lg bg-[var(--primary)] text-white font-semibold shadow-xs flex items-center justify-center gap-1.5 cursor-pointer\"\n                  >\n                    <Icon name=\"check\" size={14} />\n                    <span>Save & Attach</span>\n                  </button>\n                </div>\n            </Modal>\n          )}"
    )
    print("Wrapped Modal 2 (Quick Customer) in <Modal>.")

# 6. Collect Due Modal (Ctrl+D)
old_due_header = """          {/* 9. COLLECT DUE MODAL (Ctrl+D) */}
          {showDueCollectModal && dueCollectCustomer && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl overflow-hidden flex flex-col text-xs">
                <div className="p-3.5 border-b border-[var(--border)] bg-[var(--surface-sunken)] flex items-center justify-between">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="banknote" size={16} className="text-amber-500" />
                    <span>Collect Customer Due (Ctrl+D)</span>
                  </div>
                  <button onClick={() => setShowDueCollectModal(false)}><Icon name="x" size={16} /></button>
                </div>"""

new_due_header = """          {/* 9. COLLECT DUE MODAL (Ctrl+D) */}
          {showDueCollectModal && dueCollectCustomer && (
            <Modal
              isOpen={showDueCollectModal}
              onClose={() => setShowDueCollectModal(false)}
              title="Collect Customer Due (Ctrl+D)"
              subtitle={`Outstanding balance for ${dueCollectCustomer.name_en} (#${dueCollectCustomer.customer_no})`}
              icon="banknote"
              size="lg"
            >"""

if old_due_header in content:
    content = content.replace(old_due_header, new_due_header)
    content = content.replace(
        "                    onClick={handleExecuteCollectDue}\n                    className=\"flex-1 h-9 rounded-lg bg-[var(--primary)] text-white font-semibold shadow-xs\"\n                  >\n                    Confirm Collection & Print MR\n                  </button>\n                </div>\n              </div>\n            </div>\n          )}",
        "                    onClick={handleExecuteCollectDue}\n                    className=\"flex-1 h-9 rounded-lg bg-[var(--primary)] text-white font-semibold shadow-xs cursor-pointer\"\n                  >\n                    Confirm Collection & Print MR\n                  </button>\n                </div>\n            </Modal>\n          )}"
    )
    print("Wrapped Modal 9 (Collect Due) in <Modal>.")

with open(TARGET_FILE, "w", encoding="utf-8") as f:
    f.write(content)

print("Modal wrapping completed.")
