# scripts/fix_purchase_modals.py

with open('scripts/parts/render_all_modals.py', 'r', encoding='utf-8') as f:
    content = f.read()

# We locate from "{/* 19. NEW PURCHASE" up to "{/* 20. ACCOUNT TRANSFER MODAL"
start_marker = "{/* 19. NEW PURCHASE"
end_marker = "{/* 20. ACCOUNT TRANSFER MODAL"

start_idx = content.find(start_marker)
end_idx = content.find(end_marker)

if start_idx == -1 or end_idx == -1:
    print(f"Error finding markers: start={start_idx}, end={end_idx}")
    exit(1)

proper_purchase_modals = '''{/* 19. NEW PURCHASE ORDER & GOODS RECEIVED NOTE (GRN) MODAL WITH LINE ITEMS */}
          {showNewPurchaseModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowNewPurchaseModal(false)}></div>
              <div className="relative z-50 w-full max-w-4xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-5 space-y-4 text-xs max-h-[92vh] overflow-y-auto flex flex-col">
                
                {/* Modal Header */}
                <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                  <div>
                    <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                      <Icon name="package" size={18} className="text-[var(--primary)]" />
                      <span>New Purchase & Goods Received Note (GRN) — পণ্য ক্রয় ও মজুত চালান</span>
                    </div>
                    <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                      Add vendor bill, receive line items with sizes/colors into store stock, and calculate landed cost
                    </div>
                  </div>
                  <button onClick={() => setShowNewPurchaseModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">
                    <Icon name="x" size={16} />
                  </button>
                </div>

                {/* Top Details: Vendor, Bill #, Receiving Branch, Date */}
                <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 bg-[var(--surface-sunken)] p-3 rounded-xl border border-[var(--border)]">
                  <div>
                    <label className="font-semibold text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider">Vendor Supplier *</label>
                    <select
                      value={newPurVendor}
                      onChange={(e) => setNewPurVendor(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                    >
                      {MOCK_VENDORS.map(v => <option key={v.id} value={v.name}>{v.name}</option>)}
                    </select>
                  </div>
                  <div>
                    <label className="font-semibold text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider">Vendor Bill / Challan # *</label>
                    <input
                      type="text"
                      value={newPurBillNo}
                      onChange={(e) => setNewPurBillNo(e.target.value)}
                      placeholder="e.g. INV-HL-9924"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-semibold text-[var(--foreground)]"
                    />
                  </div>
                  <div>
                    <label className="font-semibold text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider">Receiving Branch *</label>
                    <select
                      value={newPurBranch}
                      onChange={(e) => setNewPurBranch(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                    >
                      <option>Different Fashion - Main [DF1]</option>
                      <option>Different Fashion - 2 [DF2]</option>
                    </select>
                  </div>
                  <div>
                    <label className="font-semibold text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider">GRN Date</label>
                    <input
                      type="text"
                      value={newPurDate}
                      onChange={(e) => setNewPurDate(e.target.value)}
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                    />
                  </div>
                </div>

                {/* PRODUCT LINE ITEM ADDER */}
                <div className="p-3.5 rounded-xl border-2 border-dashed border-[var(--border)] bg-[var(--surface)] space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                      <Icon name="plusCircle" size={14} className="text-[var(--primary)]" />
                      <span>Add Product Item to Purchase Batch (পণ্য যুক্ত করুন)</span>
                    </span>
                    <span className="text-[10px] text-[var(--muted-foreground)]">Selecting a product auto-loads its current cost & retail prices</span>
                  </div>

                  <div className="grid grid-cols-2 sm:grid-cols-6 gap-2 pt-1">
                    {/* Product Selector */}
                    <div className="col-span-2">
                      <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Select Product *</label>
                      <select
                        value={purItemProductId}
                        onChange={(e) => {
                          const pid = e.target.value;
                          setPurItemProductId(pid);
                          const prod = INITIAL_PRODUCTS.find(p => p.id === pid);
                          if (prod) {
                            setPurItemCost(prod.cost_price || Math.round(prod.price * 0.55));
                            setPurItemRetail(prod.price);
                          }
                        }}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium"
                      >
                        {INITIAL_PRODUCTS.map(p => (
                          <option key={p.id} value={p.id}>{p.name_en} ({p.sku || p.id})</option>
                        ))}
                      </select>
                    </div>

                    {/* Size Selector */}
                    <div>
                      <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Size / Fit</label>
                      <select
                        value={purItemSize}
                        onChange={(e) => setPurItemSize(e.target.value)}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                      >
                        {['S', 'M', 'L', 'XL', 'XXL', '30', '32', '34', '36', '38', 'Free Size'].map(s => (
                          <option key={s} value={s}>{s}</option>
                        ))}
                      </select>
                    </div>

                    {/* Color / Variant */}
                    <div>
                      <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Color Variant</label>
                      <select
                        value={purItemColor}
                        onChange={(e) => setPurItemColor(e.target.value)}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs"
                      >
                        {['White', 'Off-White', 'Black', 'Navy Blue', 'Maroon', 'Olive Green', 'Sky Blue', 'Beige'].map(c => (
                          <option key={c} value={c}>{c}</option>
                        ))}
                      </select>
                    </div>

                    {/* Quantity */}
                    <div>
                      <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Received Qty</label>
                      <input
                        type="number"
                        min="1"
                        value={purItemQty}
                        onChange={(e) => setPurItemQty(Number(e.target.value) || 1)}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-right"
                      />
                    </div>

                    {/* Unit Cost */}
                    <div>
                      <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Unit Cost (৳)</label>
                      <input
                        type="number"
                        value={purItemCost}
                        onChange={(e) => setPurItemCost(Number(e.target.value) || 0)}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-right"
                      />
                    </div>
                  </div>

                  {/* Pricing & Add Button Row */}
                  <div className="flex items-center justify-between pt-1 border-t border-[var(--border)]">
                    <div className="flex items-center gap-3 text-[11px]">
                      <span className="text-[var(--muted-foreground)]">Target Retail MRP: <strong className="font-mono text-[var(--foreground)]">৳{purItemRetail}</strong></span>
                      <span className="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold font-mono text-[10px]">
                        +{Math.round(((purItemRetail - purItemCost) / Math.max(1, purItemCost)) * 100)}% Margin
                      </span>
                      <span className="text-[var(--muted-foreground)] font-mono">
                        Line Subtotal: <strong className="text-teal-600">{formatMoney(purItemQty * purItemCost)}</strong>
                      </span>
                    </div>

                    <button
                      type="button"
                      onClick={() => {
                        const prod = INITIAL_PRODUCTS.find(p => p.id === purItemProductId) || INITIAL_PRODUCTS[0];
                        const newItem = {
                          id: 'pi-' + Date.now(),
                          product_id: prod.id,
                          name_en: prod.name_en,
                          sku: prod.sku || ('DF-SKU-' + prod.id),
                          size: purItemSize,
                          color: purItemColor,
                          qty: Number(purItemQty) || 1,
                          unit_cost: Number(purItemCost) || 0,
                          retail_price: Number(purItemRetail) || prod.price,
                          total_cost: (Number(purItemQty) || 1) * (Number(purItemCost) || 0)
                        };
                        setNewPurItems(prev => [...prev, newItem]);
                        addToast(`Added ${newItem.qty} pcs of ${newItem.name_en} (${newItem.size}/${newItem.color})`, "success");
                      }}
                      className="h-7 px-3 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white font-bold text-xs flex items-center gap-1 shadow-xs cursor-pointer"
                    >
                      <Icon name="plus" size={13} />
                      <span>Add Line Item</span>
                    </button>
                  </div>
                </div>

                {/* CURRENT LINE ITEMS IN GRN TABLE */}
                <div className="rounded-xl border border-[var(--border)] bg-[var(--surface)] overflow-hidden">
                  <div className="p-2.5 bg-[var(--surface-sunken)] border-b border-[var(--border)] flex items-center justify-between font-bold text-xs">
                    <span>GRN Items List ({newPurItems.length} items · {newPurItems.reduce((acc, it) => acc + (it.qty || 0), 0)} pcs total)</span>
                    <span className="text-[var(--muted-foreground)] text-[10px] font-normal">Verify physical counts against vendor packing slip</span>
                  </div>

                  <div className="max-h-48 overflow-y-auto">
                    {newPurItems.length === 0 ? (
                      <div className="p-6 text-center text-[var(--muted-foreground)] text-xs">
                        No items added yet. Use the product selector above to add items to this purchase.
                      </div>
                    ) : (
                      <table className="w-full text-left border-collapse text-xs">
                        <thead className="bg-[var(--surface-sunken)] text-[var(--muted-foreground)] font-semibold text-[10px] sticky top-0 border-b border-[var(--border)]">
                          <tr>
                            <th className="py-1.5 px-3">Product / SKU</th>
                            <th className="py-1.5 px-3">Variant</th>
                            <th className="py-1.5 px-3 text-right">Qty</th>
                            <th className="py-1.5 px-3 text-right">Cost Price</th>
                            <th className="py-1.5 px-3 text-right">Line Total</th>
                            <th className="py-1.5 px-3 text-right">Retail MRP</th>
                            <th className="py-1.5 px-2 text-center">Action</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border)]">
                          {newPurItems.map((item, idx) => (
                            <tr key={item.id || idx} className="hover:bg-[var(--surface-sunken)]">
                              <td className="py-1.5 px-3 font-semibold text-[var(--foreground)]">
                                <div>{item.name_en}</div>
                                <div className="text-[10px] font-mono text-[var(--muted-foreground)]">{item.sku}</div>
                              </td>
                              <td className="py-1.5 px-3">
                                <span className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] font-mono text-[10px] text-[var(--foreground)] border border-[var(--border)]">
                                  {item.size} / {item.color}
                                </span>
                              </td>
                              <td className="py-1.5 px-3 text-right font-mono font-bold text-[var(--foreground)]">{item.qty} pcs</td>
                              <td className="py-1.5 px-3 text-right font-mono text-[var(--muted-foreground)]">{formatMoney(item.unit_cost)}</td>
                              <td className="py-1.5 px-3 text-right font-mono font-bold text-[var(--foreground)]">{formatMoney(item.qty * item.unit_cost)}</td>
                              <td className="py-1.5 px-3 text-right font-mono text-emerald-600">{formatMoney(item.retail_price)}</td>
                              <td className="py-1.5 px-2 text-center">
                                <button
                                  type="button"
                                  onClick={() => setNewPurItems(prev => prev.filter((_, i) => i !== idx))}
                                  className="text-rose-500 hover:text-rose-700 p-1 rounded hover:bg-rose-500/10"
                                  title="Remove line"
                                >
                                  <Icon name="x" size={13} />
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>
                </div>

                {/* LANDED COSTS & FINANCIAL SETTLEMENT */}
                {(() => {
                  const goodsTotal = newPurItems.reduce((acc, it) => acc + (it.qty * it.unit_cost), 0);
                  const transport = Number(newPurTransport) || 0;
                  const labour = Number(newPurLabour) || 0;
                  const grandTotal = goodsTotal + transport + labour;
                  const paid = Number(newPurPaid) || 0;
                  const balanceDue = Math.max(0, grandTotal - paid);

                  return (
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 p-3 bg-[var(--surface-sunken)] rounded-xl border border-[var(--border)]">
                      {/* Column 1: Freight & Unloading */}
                      <div className="space-y-2">
                        <div className="font-bold text-[11px] text-[var(--foreground)] uppercase tracking-wider">Landed Overhead Costs</div>
                        <div>
                          <label className="text-[10px] text-[var(--muted-foreground)] font-semibold">Transport / Freight (BDT)</label>
                          <input
                            type="number"
                            value={newPurTransport}
                            onChange={(e) => setNewPurTransport(e.target.value)}
                            placeholder="0"
                            className="mt-1 w-full h-7 px-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-right"
                          />
                        </div>
                        <div>
                          <label className="text-[10px] text-[var(--muted-foreground)] font-semibold">Labour & Handling (BDT)</label>
                          <input
                            type="number"
                            value={newPurLabour}
                            onChange={(e) => setNewPurLabour(e.target.value)}
                            placeholder="0"
                            className="mt-1 w-full h-7 px-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-right"
                          />
                        </div>
                      </div>

                      {/* Column 2: Cost Summary */}
                      <div className="space-y-1 text-[11px] border-x border-[var(--border)] px-3 flex flex-col justify-between">
                        <div>
                          <div className="font-bold text-[11px] text-[var(--foreground)] uppercase tracking-wider mb-2">Bill Valuation</div>
                          <div className="flex justify-between py-0.5 text-[var(--muted-foreground)]">
                            <span>Total Goods Value:</span>
                            <span className="font-mono font-bold text-[var(--foreground)]">{formatMoney(goodsTotal)}</span>
                          </div>
                          <div className="flex justify-between py-0.5 text-[var(--muted-foreground)]">
                            <span>Transport & Freight:</span>
                            <span className="font-mono">+{formatMoney(transport)}</span>
                          </div>
                          <div className="flex justify-between py-0.5 text-[var(--muted-foreground)]">
                            <span>Labour / Unloading:</span>
                            <span className="font-mono">+{formatMoney(labour)}</span>
                          </div>
                        </div>
                        <div className="pt-1 border-t border-[var(--border)] flex justify-between font-bold text-xs text-[var(--foreground)]">
                          <span>Grand Landed Cost:</span>
                          <span className="font-mono text-sm text-[var(--primary)]">{formatMoney(grandTotal)}</span>
                        </div>
                      </div>

                      {/* Column 3: Payment & Payables Due */}
                      <div className="space-y-2 flex flex-col justify-between">
                        <div>
                          <div className="font-bold text-[11px] text-[var(--foreground)] uppercase tracking-wider">Payment & Settlement</div>
                          <div className="mt-1.5">
                            <label className="text-[10px] text-[var(--muted-foreground)] font-semibold">Immediate Paid Amount (BDT)</label>
                            <input
                              type="number"
                              value={newPurPaid}
                              onChange={(e) => setNewPurPaid(e.target.value)}
                              placeholder="0"
                              className="mt-1 w-full h-7 px-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-emerald-600 text-right"
                            />
                          </div>
                        </div>

                        <div className="p-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex items-center justify-between">
                          <span className="text-[10px] font-semibold text-[var(--muted-foreground)]">Balance Due:</span>
                          <span className={`font-mono font-bold text-xs ${balanceDue > 0 ? 'text-rose-600' : 'text-emerald-600'}`}>
                            {balanceDue > 0 ? formatMoney(balanceDue) : 'Fully Paid'}
                          </span>
                        </div>
                      </div>
                    </div>
                  );
                })()}

                {/* Footer Buttons */}
                <div className="pt-2 flex items-center justify-between gap-3 border-t border-[var(--border)]">
                  <button
                    type="button"
                    onClick={() => setShowNewPurchaseModal(false)}
                    className="h-9 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs text-[var(--foreground)]"
                  >
                    Cancel
                  </button>

                  <button
                    type="button"
                    onClick={() => {
                      if (newPurItems.length === 0) {
                        addToast("Please add at least one line item to the purchase", "warning");
                        return;
                      }
                      const goodsTotal = newPurItems.reduce((acc, it) => acc + (it.qty * it.unit_cost), 0);
                      const transport = Number(newPurTransport) || 0;
                      const labour = Number(newPurLabour) || 0;
                      const grandTotal = goodsTotal + transport + labour;
                      const paid = Number(newPurPaid) || 0;
                      const balanceDue = Math.max(0, grandTotal - paid);
                      const grnNo = 'DF-GRN-2600' + (purchasesList.length + 1);

                      const newPur = {
                        id: 'pur-' + Date.now(),
                        grn_no: grnNo,
                        purchase_no: grnNo,
                        bill_no: newPurBillNo || ('INV-V-' + Date.now().toString().slice(-4)),
                        date: newPurDate || 'Today, 29 Oct 2026',
                        branch: newPurBranch,
                        vendor: newPurVendor,
                        items_count: newPurItems.reduce((acc, it) => acc + Number(it.qty || 0), 0),
                        items: [...newPurItems],
                        transport: transport,
                        labour: labour,
                        total: grandTotal,
                        paid: paid,
                        balance: balanceDue,
                        due: balanceDue,
                        status: balanceDue <= 0 ? 'Received & Paid' : paid > 0 ? 'Partial Paid' : 'Due Balance'
                      };

                      setPurchasesList(prev => [newPur, ...prev]);
                      addToast(`GRN ${grnNo} saved! Added ${newPur.items_count} pcs to ${newPurBranch} stock.`, "success");
                      setShowNewPurchaseModal(false);
                      setNewPurBillNo('');
                    }}
                    className="h-9 px-5 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer"
                  >
                    <Icon name="checkCircle" size={15} />
                    <span>Receive Stock & Save GRN (পণ্য ইনভেন্টরিতে যুক্ত করুন)</span>
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 19B. PURCHASE / GRN DETAIL MODAL WITH FULL ITEM BREAKDOWN & PRINT VOUCHER */}
          {showPurchaseDetailModal && selectedPurchase && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowPurchaseDetailModal(false)}></div>
              <div className="relative z-50 w-full max-w-3xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-5 space-y-4 text-xs max-h-[92vh] overflow-y-auto flex flex-col">
                
                {/* Header */}
                <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="fileText" size={16} className="text-[var(--primary)]" />
                    <span>Purchase Order & GRN Details — {selectedPurchase.grn_no || selectedPurchase.purchase_no}</span>
                  </div>
                  <button onClick={() => setShowPurchaseDetailModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">
                    <Icon name="x" size={16} />
                  </button>
                </div>

                {/* Printable Isolated GRN Voucher Area */}
                <div className="printable-area p-5 bg-white text-slate-900 rounded-xl border border-gray-300 shadow-xs space-y-4">
                  {/* Top Header of Voucher */}
                  <div className="flex justify-between items-start border-b pb-3">
                    <div>
                      <div className="font-extrabold text-base tracking-wider uppercase text-teal-900">DIFFERENT FASHION</div>
                      <div className="text-[10px] text-gray-600">Uttara Sector 3, Dhaka-1230 · Helpline: 01711-223344</div>
                      <div className="text-[10px] font-mono text-gray-500">BIN: 002847192-0101 · Mushak 6.3 Registered</div>
                      <div className="text-xs font-bold text-teal-800 uppercase tracking-widest mt-1">GOODS RECEIVED NOTE (GRN)</div>
                    </div>
                    <div className="text-right space-y-0.5 text-[11px]">
                      <div>GRN No: <strong className="font-mono font-bold text-teal-900">{selectedPurchase.grn_no || selectedPurchase.purchase_no}</strong></div>
                      <div>Date: <span className="font-mono">{selectedPurchase.date}</span></div>
                      <div>Receiving Branch: <strong>{selectedPurchase.branch || 'Main Branch [DF1]'}</strong></div>
                      <div>Vendor Bill Ref: <span className="font-mono font-semibold">{selectedPurchase.bill_no}</span></div>
                    </div>
                  </div>

                  {/* Vendor Details */}
                  <div className="p-2.5 rounded-lg bg-gray-50 border border-gray-200 flex justify-between items-center text-[11px]">
                    <div>
                      <div className="text-[10px] text-gray-500 font-semibold uppercase">Supplier / Vendor</div>
                      <div className="font-bold text-sm text-gray-900">{selectedPurchase.vendor}</div>
                    </div>
                    <div className="text-right">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                        (selectedPurchase.balance || selectedPurchase.due || 0) <= 0 
                          ? 'bg-emerald-100 text-emerald-800' 
                          : 'bg-rose-100 text-rose-800'
                      }`}>
                        {selectedPurchase.status || 'Received & Recorded'}
                      </span>
                    </div>
                  </div>

                  {/* Items Table */}
                  <div>
                    <div className="font-bold text-xs text-gray-900 mb-1.5 flex items-center justify-between">
                      <span>Received Product Line Items</span>
                      <span className="text-[10px] font-normal text-gray-600">Total: {selectedPurchase.items_count || 35} pcs</span>
                    </div>

                    <table className="w-full text-left border-collapse text-xs">
                      <thead className="bg-gray-100 text-gray-700 font-semibold text-[10px] border-b border-gray-300">
                        <tr>
                          <th className="py-2 px-2.5">#</th>
                          <th className="py-2 px-2.5">Product Description</th>
                          <th className="py-2 px-2.5">Variant / Fit</th>
                          <th className="py-2 px-2.5 text-right">Qty</th>
                          <th className="py-2 px-2.5 text-right">Unit Cost</th>
                          <th className="py-2 px-2.5 text-right">Line Total</th>
                          <th className="py-2 px-2.5 text-right">Retail MRP</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-gray-200">
                        {(selectedPurchase.items || [
                          { id: '1', name_en: 'Semi-fitted Cotton Panjabi', sku: 'DF-PAN-01', size: 'L', color: 'White', qty: 20, unit_cost: 850, retail_price: 1450, total_cost: 17000 },
                          { id: '2', name_en: 'Pure Cotton Formal Shirt', sku: 'DF-SHT-03', size: 'XL', color: 'Sky Blue', qty: 15, unit_cost: 950, retail_price: 1650, total_cost: 14250 }
                        ]).map((it, idx) => (
                          <tr key={it.id || idx}>
                            <td className="py-2 px-2.5 font-mono text-gray-500">{idx + 1}</td>
                            <td className="py-2 px-2.5 font-semibold text-gray-900">
                              <div>{it.name_en}</div>
                              <div className="text-[10px] font-mono text-gray-500">{it.sku}</div>
                            </td>
                            <td className="py-2 px-2.5">
                              <span className="px-1.5 py-0.5 rounded bg-gray-100 border text-[10px] font-mono text-gray-800">
                                {it.size || 'L'} / {it.color || 'Standard'}
                              </span>
                            </td>
                            <td className="py-2 px-2.5 text-right font-mono font-bold text-gray-900">{it.qty} pcs</td>
                            <td className="py-2 px-2.5 text-right font-mono text-gray-700">{formatMoney(it.unit_cost)}</td>
                            <td className="py-2 px-2.5 text-right font-mono font-bold text-gray-900">{formatMoney(it.total_cost || (it.qty * it.unit_cost))}</td>
                            <td className="py-2 px-2.5 text-right font-mono text-emerald-700">{formatMoney(it.retail_price)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Financial Settlement Breakdown */}
                  <div className="pt-3 border-t border-gray-200 grid grid-cols-2 gap-4 text-[11px]">
                    <div className="text-[10px] text-gray-600 space-y-1">
                      <div>Stock Keeper: <strong>Sumi (Cashier / Verified)</strong></div>
                      <div>Authorized Signature: ___________________________</div>
                      <div className="italic text-gray-500">Goods inspected, verified against supplier invoice, and entered into active inventory.</div>
                    </div>
                    <div className="space-y-1 font-mono text-right">
                      <div className="flex justify-between text-gray-600">
                        <span>Transport & Freight:</span>
                        <span>{formatMoney(selectedPurchase.transport || 1200)}</span>
                      </div>
                      <div className="flex justify-between font-bold text-sm text-gray-900 border-t pt-1">
                        <span>Total Landed Bill:</span>
                        <span>{formatMoney(selectedPurchase.total)}</span>
                      </div>
                      <div className="flex justify-between text-emerald-700 font-semibold">
                        <span>Paid Amount:</span>
                        <span>{formatMoney(selectedPurchase.paid)}</span>
                      </div>
                      <div className="flex justify-between font-bold text-sm text-rose-700 border-t pt-1">
                        <span>Outstanding Vendor Due:</span>
                        <span>{formatMoney(selectedPurchase.balance || selectedPurchase.due || 0)}</span>
                      </div>
                    </div>
                  </div>
                </div>

                {/* Footer Action Buttons */}
                <div className="pt-2 flex items-center justify-between gap-2 border-t border-[var(--border)]">
                  <button
                    type="button"
                    onClick={() => {
                      setShowPurchaseDetailModal(false);
                      setCatalogSubTab('barcodes');
                      setCurrentScreen('products');
                    }}
                    className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs text-[var(--foreground)] flex items-center gap-1.5"
                  >
                    <Icon name="barcode" size={14} />
                    <span>Print Barcodes for this Batch</span>
                  </button>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setShowPurchaseDetailModal(false)}
                      className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs text-[var(--foreground)]"
                    >
                      Close
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        window.print();
                        addToast(`Printing GRN voucher ${selectedPurchase.grn_no || selectedPurchase.purchase_no}...`, "success");
                      }}
                      className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                      <Icon name="printer" size={14} />
                      <span>Print GRN Voucher</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}

          '''

content = content[:start_idx] + proper_purchase_modals + content[end_idx:]

with open('scripts/parts/render_all_modals.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_all_modals.py patched with proper purchase modals!")
