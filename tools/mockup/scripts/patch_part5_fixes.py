#!/usr/bin/env python3
"""
scripts/patch_part5_fixes.py
Upgrades showStockTransferModal and showBulkBarcodeModal to <Modal>
Exposes helper setters on window
Ensures button onClick handlers
"""

print("Applying Part 5 modal upgrades...")

# ==============================================================
# 1. Update app_state.py
# ==============================================================
with open('scripts/parts/app_state.py', 'r', encoding='utf-8') as f:
    app_state = f.read()

# Fix regex escapes to prevent SyntaxWarnings
app_state = app_state.replace("replace(/\\s+/g, '')", "replace(/\\\\s+/g, '')")
app_state = app_state.replace("replace(/\\s+/g, '-')", "replace(/\\\\s+/g, '-')")

window_exports = """
      window.__setCatalogSubTab = setCatalogSubTab;
      window.__setImportStep = setImportStep;
      window.__setBrandForm = setBrandForm;
      window.__setVendorForm = setVendorForm;
      window.__setBranchFormData = setBranchFormData;
      window.__handleSaveBrand = handleSaveBrand;
      window.__handleSaveVendor = handleSaveVendor;
      window.__handleSaveBranch = handleSaveBranch;
      window.__handleSaveStocktakeAudit = handleSaveStocktakeAudit;
      window.__handleExecuteCsvImport = handleExecuteCsvImport;
"""

if "window.__handleSaveBrand = handleSaveBrand;" not in app_state:
    target = "window.__closeModal = closeModal;"
    app_state = app_state.replace(target, target + window_exports)
    print("  ✓ Exposed Part 5 helpers on window")

with open('scripts/parts/app_state.py', 'w', encoding='utf-8') as f:
    f.write(app_state)


# ==============================================================
# 2. Upgrade showStockTransferModal in render_all_modals.py
# ==============================================================
with open('scripts/parts/render_all_modals.py', 'r', encoding='utf-8') as f:
    modals = f.read()

# Replace raw transfer div with <Modal>
old_transfer = """{/* 15. INTER-BRANCH TRANSFER MODAL */}
          {showStockTransferModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowStockTransferModal(false)}></div>
              <div className="relative z-50 w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5">
                    <Icon name="arrowLeftRight" size={15} className="text-[var(--primary)]" />
                    <span>Inter-Branch Stock Transfer</span>
                  </div>
                  <button onClick={() => setShowStockTransferModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Item to Transfer</label>
                  <div className="mt-1 p-2 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs text-[var(--foreground)]">
                    {selectedProduct?.name_en || 'Semi-fitted Panjabi (DF-PAN-01)'}
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">From Branch *</label>
                    <select
                      value={transferFrom}
                      onChange={(e) => {
                        setTransferFrom(e.target.value);
                        if (transferErrors.branch) setTransferErrors(prev => ({ ...prev, branch: null }));
                      }}
                      className={`mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] font-semibold ${
                        transferErrors.branch ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                      }`}
                    >
                      <option>Different Fashion - Main [DF1]</option>
                      <option>Different Fashion - 2 [DF2]</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">To Branch *</label>
                    <select
                      value={transferTo}
                      onChange={(e) => {
                        setTransferTo(e.target.value);
                        if (transferErrors.branch) setTransferErrors(prev => ({ ...prev, branch: null }));
                      }}
                      className={`mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] font-semibold ${
                        transferErrors.branch ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                      }`}
                    >
                      <option>Different Fashion - 2 [DF2]</option>
                      <option>Different Fashion - Main [DF1]</option>
                    </select>
                  </div>
                </div>
                {transferErrors.branch && (
                  <div className="text-[10px] text-rose-500 font-semibold flex items-center gap-1">
                    <Icon name="alertCircle" size={11} />
                    <span>{transferErrors.branch}</span>
                  </div>
                )}
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Transfer Quantity (pcs) *</label>
                  <input
                    type="number"
                    value={transferQty}
                    onChange={(e) => {
                      setTransferQty(Number(e.target.value) || 0);
                      if (transferErrors.qty) setTransferErrors(prev => ({ ...prev, qty: null }));
                    }}
                    placeholder="e.g. 5"
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold ${
                      transferErrors.qty ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                    }`}
                  />
                  {transferErrors.qty && (
                    <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{transferErrors.qty}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Vehicle / Dispatch Note</label>
                  <input
                    type="text"
                    value={transferNote}
                    onChange={(e) => setTransferNote(e.target.value)}
                    placeholder="e.g. Courier RedX / Pickup Van DHA-METRO-11-2091"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                  />
                </div>
                <div className="pt-2 flex gap-2">
                  <button type="button" onClick={() => setShowStockTransferModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold">Cancel</button>
                  <button
                    type="button"
                    onClick={() => {
                      if (transferFrom === transferTo) {
                        setTransferErrors({ branch: "Origin and Destination branches cannot be identical" });
                        addToast("Origin and Destination branches cannot be identical", "danger");
                        return;
                      }
                      if (Number(transferQty) <= 0) {
                        setTransferErrors({ qty: "Transfer quantity must be at least 1 piece" });
                        addToast("Transfer quantity must be at least 1 piece", "danger");
                        return;
                      }
                      setTransferErrors({});
                      addToast(`Gate Pass #TR-9921 created: ${transferQty} pcs dispatched from ${transferFrom.split('[')[1]?.replace(']','') || 'DF1'} to ${transferTo.split('[')[1]?.replace(']','') || 'DF2'}`, "success");
                      setShowStockTransferModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold shadow-xs cursor-pointer"
                  >
                    Generate Gate Pass
                  </button>
                </div>
              </div>
            </div>
          )}"""

new_transfer = """{/* 15. INTER-BRANCH STOCK TRANSFER MODAL */}
          <Modal
            isOpen={showStockTransferModal}
            onClose={() => setShowStockTransferModal(false)}
            title="Inter-Branch Stock Transfer (Gate Pass)"
            subtitle="Issue authorized transfer challan and dispatch inventory between outlets"
            icon="arrowLeftRight"
            size="md"
          >
            <div className="space-y-3.5 text-xs">
              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Transfer Products</label>
                <div className="mt-1 p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-[var(--foreground)]">
                  {transferBatchProducts && transferBatchProducts.length > 1 ? (
                    <span>Batch Transfer: <strong>{transferBatchProducts.length} selected products</strong></span>
                  ) : (
                    <span>{selectedProduct?.name_en || 'Semi-fitted Panjabi Classic'} (<span className="font-mono">{selectedProduct?.sku || 'DF-PAN-01'}</span>)</span>
                  )}
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Source Branch (From) *</label>
                  <select
                    value={transferFrom}
                    onChange={(e) => setTransferFrom(e.target.value)}
                    className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                  >
                    <option>Different Fashion - Main [DF1]</option>
                    <option>Different Fashion - 2 [DF2]</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Destination Branch (To) *</label>
                  <select
                    value={transferTo}
                    onChange={(e) => setTransferTo(e.target.value)}
                    className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                  >
                    <option>Different Fashion - 2 [DF2]</option>
                    <option>Different Fashion - Main [DF1]</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Transfer Quantity (pcs) *</label>
                  <input
                    type="number"
                    min={1}
                    value={transferQty}
                    onChange={(e) => setTransferQty(Number(e.target.value) || 1)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Gate Pass Challan #</label>
                  <input
                    type="text"
                    readOnly
                    value="GP-2026-104"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--muted-foreground)]"
                  />
                </div>
              </div>

              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Transport / Vehicle Memo</label>
                <input
                  type="text"
                  value={transferMemo}
                  onChange={(e) => setTransferMemo(e.target.value)}
                  placeholder="e.g. Pickup Van DHA-METRO-11-2091, Driver: Kalam"
                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                />
              </div>

              <div className="pt-2 flex gap-2 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowStockTransferModal(false)}
                  className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold hover:bg-[var(--border)]"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={() => {
                    const count = transferBatchProducts && transferBatchProducts.length > 1 ? transferBatchProducts.length : 1;
                    addToast(`Gate Pass GP-2026-104 issued: ${count} product line(s) dispatched to ${transferTo}!`, "success");
                    setShowStockTransferModal(false);
                  }}
                  className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold hover:bg-[var(--primary-hover)] shadow-xs"
                >
                  Generate Gate Pass & Dispatch
                </button>
              </div>
            </div>
          </Modal>"""

if old_transfer in modals:
    modals = modals.replace(old_transfer, new_transfer)
    print("  ✓ Upgraded showStockTransferModal to <Modal>")

# Also remove duplicate/old showBulkBarcodeModal if present
old_barcode_div = """          {/* 25D. QUICK BULK BARCODE MODAL */}
          {showBulkBarcodeModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowBulkBarcodeModal(false)}></div>
              <div className="relative z-50 w-full max-w-2xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-5 space-y-4 text-xs max-h-[90vh] overflow-y-auto">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-2 text-sm">
                    <Icon name="barcode" size={16} className="text-[var(--primary)]" />
                    <span>Bulk Barcode Label Generator</span>
                  </div>
                  <button onClick={() => setShowBulkBarcodeModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div className="flex items-center justify-between bg-[var(--surface-sunken)] p-2 rounded-lg">
                  <span className="font-semibold text-[var(--muted-foreground)]">Format:</span>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => setBarcodeSheetFormat('thermal_50x25')}
                      className={`px-3 py-1 rounded text-xs font-semibold ${barcodeSheetFormat === 'thermal_50x25' ? 'bg-[var(--primary)] text-white' : 'bg-[var(--surface)] text-[var(--foreground)]'}`}
                    >
                      Thermal Roll (50×25mm)
                    </button>
                    <button
                      type="button"
                      onClick={() => setBarcodeSheetFormat('a4_40')}
                      className={`px-3 py-1 rounded text-xs font-semibold ${barcodeSheetFormat === 'a4_40' ? 'bg-[var(--primary)] text-white' : 'bg-[var(--surface)] text-[var(--foreground)]'}`}
                    >
                      A4 Sheet (40-Up)
                    </button>
                  </div>
                </div>
                {/* Printable container */}
                <div className="printable-area p-4 bg-white text-black rounded-xl border border-gray-300">
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {selectedBarcodeProducts.map(p => (
                      <div key={p.id} className="p-2 border border-gray-300 rounded text-center space-y-0.5">
                        <div className="text-[8px] font-bold tracking-widest uppercase">DIFFERENT FASHION</div>
                        <div className="text-[10px] font-bold truncate">{p.name_en}</div>
                        <div className="h-5 w-24 mx-auto flex items-center justify-between">
                          {[2,1,2,1,1,2,1,2,1,1,2,1].map((w, i) => (
                            <span key={i} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                          ))}
                        </div>
                        <div className="font-mono text-[8px]">{p.barcode || 'DF-1002'}</div>
                        <div className="font-bold text-[10px] font-mono">{formatMoney(p.price)}</div>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="flex justify-between items-center pt-2 border-t border-[var(--border)]">
                  <div className="text-muted-foreground">{selectedBarcodeProducts.length} unique labels</div>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => setShowBulkBarcodeModal(false)}
                      className="px-4 py-1.5 rounded-lg border border-[var(--border)] hover:bg-[var(--surface-sunken)]"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        window.print();
                        addToast("Labels dispatched to thermal printer", "success");
                        setShowBulkBarcodeModal(false);
                      }}
                      className="px-4 py-1.5 rounded-lg bg-[var(--primary)] text-white font-bold"
                    >
                      Print Batch (ESC/POS)
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}"""

if old_barcode_div in modals:
    modals = modals.replace(old_barcode_div, "")
    print("  ✓ Removed duplicate old barcode div in render_all_modals.py")

with open('scripts/parts/render_all_modals.py', 'w', encoding='utf-8') as f:
    f.write(modals)

print("Part 5 fixes applied!")
