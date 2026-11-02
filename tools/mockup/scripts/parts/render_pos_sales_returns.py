# scripts/parts/render_pos_sales_returns.py

RENDER_POS_SALES_RETURNS_JS = """
              {/* ================= SCREEN 1: POS REGISTER & FAST RETURN/EXCHANGE ================= */}
              {currentScreen === 'pos' && (
                <div className="flex-1 flex flex-col lg:flex-row overflow-hidden bg-[var(--background)]">
                  
                  {/* ================= LEFT COLUMN: PRIMARY TRANSACTION & BILLING STAGE ================= */}
                  <div className="flex-1 flex flex-col border-r border-[var(--border)] overflow-hidden bg-[var(--surface)]">
                    
                    {/* Top Bar: Barcode Input & Category Filters & Action Bar */}
                    <div className="p-2 border-b border-[var(--border)] bg-[var(--surface)] space-y-2 shrink-0">
                      <div className="flex items-center gap-2">
                        {/* Barcode / SKU / Invoice Input */}
                        <div className="relative flex-1">
                          <input
                            ref={posScanInputRef}
                            type="text"
                            value={posSearchQuery}
                            onChange={(e) => setPosSearchQuery(e.target.value)}
                            onKeyDown={(e) => {
                              if (e.key === 'Enter') {
                                const q = posSearchQuery.trim();
                                if (!q) return;
                                // Auto-detect invoice no (starts with DF1- or matches an invoice)
                                if (q.toUpperCase().startsWith('DF1-') || q.toUpperCase().startsWith('INV-')) {
                                  startReturnModeForInvoice(q);
                                  setPosSearchQuery('');
                                  return;
                                }

                                const matched = INITIAL_PRODUCTS.find(p => p.barcode === q || p.sku.toLowerCase() === q.toLowerCase());
                                if (matched) {
                                  addToCart(matched);
                                  setPosSearchQuery('');
                                  addToast(`Scanned: ${matched.name_en}`, "success");
                                } else if (filteredCatalog.length > 0) {
                                  addToCart(filteredCatalog[0]);
                                  setPosSearchQuery('');
                                  addToast(`Added: ${filteredCatalog[0].name_en}`, "success");
                                } else {
                                  addToast(`No product found for "${q}"`, "warning");
                                }
                              }
                            }}
                            id="pos-barcode-input"
                            placeholder={posMode === 'return' ? 'Scan exchange item barcode or product name...' : 'Scan barcode, SKU, or receipt barcode/invoice DF1-... (F2)'}
                            className={`w-full h-9 pl-8 pr-16 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              posMode === 'return' ? 'border-[var(--warning)] focus:border-[var(--warning)]' : 'border-[var(--border)] focus:border-[var(--primary)]'
                            }`}
                          />
                          <Icon name="search" size={14} className="absolute left-2.5 top-2.5 text-[var(--muted-foreground)]" />
                          <div className="absolute right-2 top-2 flex items-center gap-1">
                            <kbd className="text-[10px] font-mono px-1 py-0.2 rounded bg-[var(--surface)] border border-[var(--border)] text-[var(--muted-foreground)]">F2</kbd>
                          </div>
                        </div>

                        {/* Price Check Button (F5) */}
                        <button
                          onClick={() => setShowPriceCheckModal(true)}
                          className="h-9 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                          title="Price & Stock Check (F5)"
                        >
                          <Icon name="tag" size={14} />
                          <span className="hidden sm:inline">Price Check</span>
                          <kbd className="text-[10px] font-mono">F5</kbd>
                        </button>

                        {/* Fast Return Toggle (F11) */}
                        <button
                          onClick={() => {
                            if (posMode === 'sale') {
                              startReturnModeForInvoice('DF1-R1-26000412');
                            } else {
                              setPosMode('sale');
                              addToast("Exited return mode", "info");
                            }
                          }}
                          className={`h-9 px-3 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer ${
                            posMode === 'return' 
                              ? 'bg-[var(--warning)] text-white shadow-xs' 
                              : 'bg-[var(--warning-soft)] border border-[var(--warning)]/30 text-[var(--warning)] hover:bg-[var(--warning-soft)]'
                          }`}
                          title="Return & Exchange Mode (F11)"
                        >
                          <Icon name="refresh" size={14} />
                          <span>{posMode === 'return' ? 'Return Mode Active' : 'Return (F11)'}</span>
                        </button>

                        {/* Browse Catalog Toggle */}
                        <button
                          onClick={() => setShowPosCatalogBrowse(prev => !prev)}
                          className={`h-9 px-3 rounded-lg border text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer ${
                            showPosCatalogBrowse
                              ? 'bg-[var(--primary)] text-[var(--primary-foreground)] border-[var(--primary)] shadow-xs'
                              : 'bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)] hover:border-[var(--primary)]'
                          }`}
                          title="Toggle Visual Apparel Catalog Grid"
                        >
                          <Icon name={showPosCatalogBrowse ? "shoppingCart" : "grid"} size={14} />
                          <span className="hidden md:inline">{showPosCatalogBrowse ? `View Bill (${cart.reduce((s, i) => s + i.qty, 0)})` : 'Browse Catalog'}</span>
                        </button>
                      </div>

                      {/* Categories horizontal scroll */}
                      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs select-none no-scrollbar">
                        {CATEGORIES.map(cat => (
                          <button
                            key={cat.id}
                            onClick={() => {
                              setPosCategory(cat.id);
                              if (cat.id !== 'all') setShowPosCatalogBrowse(true);
                            }}
                            className={`h-7 px-2.5 rounded-md border whitespace-nowrap text-xs font-medium transition-colors cursor-pointer ${
                              posCategory === cat.id
                                ? 'bg-[var(--primary)] text-[var(--primary-foreground)] border-[var(--primary)] shadow-xs'
                                : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)] hover:border-[var(--border-strong)]'
                            }`}
                          >
                            {lang === 'bn' ? cat.name_bn : cat.name_en}
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* ================= LEFT COLUMN BODY ================= */}
                    <div className="flex-1 flex flex-col overflow-hidden relative">

                      {/* 1. RETURN & EXCHANGE MODE ACTIVE */}
                      {posMode === 'return' ? (
                        <div className="flex-1 flex flex-col overflow-y-auto p-3 space-y-4">
                          {/* Amber Header */}
                          <div className="p-3 bg-[var(--warning-soft)] border border-[var(--warning)]/30 rounded-xl space-y-2">
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                              <div className="flex items-center gap-2">
                                <span className="w-2.5 h-2.5 rounded-full bg-[var(--warning)] animate-pulse"></span>
                                <div>
                                  <div className="font-extrabold text-xs text-[var(--warning)] flex items-center gap-1.5 font-mono">
                                    <span>RETURN / EXCHANGE — {returnSourceSale?.invoice || 'NO-RECEIPT'}</span>
                                  </div>
                                  <span className="text-[10px] font-bold text-[var(--success)] bg-[var(--success-soft)] px-1.5 py-0.2 rounded border border-[var(--success)]/30">
                                    {returnPolicyStatus}
                                  </span>
                                </div>
                              </div>

                              <button
                                onClick={() => { setPosMode('sale'); addToast("Exited return mode", "info"); }}
                                className="h-6 px-2.5 rounded bg-[var(--warning)] text-white font-semibold text-[10px] flex items-center gap-1 hover:opacity-90 shadow-xs cursor-pointer self-start sm:self-auto"
                              >
                                <span>Exit Return (Esc)</span>
                              </button>
                            </div>

                            {/* Quick Invoice Selector Chips */}
                            <div className="flex items-center gap-1.5 pt-1.5 border-t border-[var(--warning)]/20 text-[10px]">
                              <span className="text-[var(--warning)] font-semibold shrink-0">Sample Invoices:</span>
                              <div className="flex items-center gap-1 overflow-x-auto no-scrollbar">
                                {['DF1-R1-26000412', 'DF1-R1-26000411', 'DF1-R1-26000410'].map(inv => (
                                  <button
                                    key={inv}
                                    onClick={() => startReturnModeForInvoice(inv)}
                                    className={`px-2 py-0.5 rounded font-mono font-medium transition-colors cursor-pointer ${
                                      returnSourceSale?.invoice === inv 
                                        ? 'bg-[var(--warning)] text-white font-bold' 
                                        : 'bg-[var(--surface)] border border-[var(--warning)]/40 text-[var(--foreground)] hover:bg-[var(--warning)]/20'
                                    }`}
                                  >
                                    {inv.slice(-8)}
                                  </button>
                                ))}
                              </div>
                            </div>
                          </div>

                          {/* Table of Items to Return */}
                          <div className="rounded-xl border border-[var(--border)] overflow-hidden shadow-xs bg-[var(--surface)]">
                            <div className="px-3 py-2 bg-[var(--surface-sunken)] border-b border-[var(--border)] flex items-center justify-between text-xs">
                              <span className="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                <Icon name="refresh" size={13} className="text-[var(--warning)]" />
                                <span>Original Invoice Items (Select Items to Return)</span>
                              </span>
                              <span className="text-[10px] text-[var(--muted-foreground)]">Click card/row or checkbox</span>
                            </div>

                            <div className="divide-y divide-[var(--border)]">
                              {returnLines.map((line, idx) => (
                                <div
                                  key={line.id}
                                  onClick={() => {
                                    setReturnLines(prev => {
                                      const next = [...prev];
                                      next[idx] = { ...next[idx], selected: !next[idx].selected };
                                      return next;
                                    });
                                  }}
                                  className={`p-3 transition-all cursor-pointer space-y-2 ${
                                    line.selected 
                                      ? 'bg-[var(--warning)]/10' 
                                      : 'hover:bg-[var(--surface-sunken)]/60 opacity-70'
                                  }`}
                                >
                                  <div className="flex items-start justify-between gap-3">
                                    <div className="flex items-center gap-3">
                                      <input
                                        type="checkbox"
                                        checked={line.selected}
                                        onChange={() => {}}
                                        className="rounded text-[var(--warning)] cursor-pointer"
                                      />
                                      <div>
                                        <div className="font-semibold text-xs text-[var(--foreground)]">{line.product?.name_en}</div>
                                        <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">
                                          Sold: {line.original_qty} · Prev returned: {line.returned_qty} · Returnable: <strong className="text-[var(--foreground)]">{line.returnable_qty} pcs</strong>
                                        </div>
                                      </div>
                                    </div>

                                    <div className="text-right">
                                      <div className="font-bold text-xs num text-[var(--danger)]">−{formatMoney(line.original_unit_price * line.return_qty)}</div>
                                      <div className="text-[9px] text-[var(--muted-foreground)]">@ {formatMoney(line.original_unit_price)}</div>
                                    </div>
                                  </div>

                                  {/* Stepper & Reason Chips if Selected */}
                                  {line.selected && (
                                    <div className="pt-2 border-t border-[var(--warning)]/20 flex flex-wrap items-center justify-between gap-2" onClick={(e) => e.stopPropagation()}>
                                      <div className="flex items-center gap-2">
                                        <span className="text-[10px] font-semibold text-[var(--muted-foreground)]">Return Qty:</span>
                                        <div className="flex items-center gap-1">
                                          <button
                                            onClick={() => setReturnLines(prev => {
                                              const next = [...prev];
                                              next[idx].return_qty = Math.max(1, next[idx].return_qty - 1);
                                              return next;
                                            })}
                                            className="w-6 h-6 rounded-md bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] active:scale-95 flex items-center justify-center font-bold text-xs cursor-pointer"
                                          >−</button>
                                          <span className="w-6 text-center font-mono font-bold text-xs">{line.return_qty}</span>
                                          <button
                                            onClick={() => setReturnLines(prev => {
                                              const next = [...prev];
                                              next[idx].return_qty = Math.min(next[idx].returnable_qty, next[idx].return_qty + 1);
                                              return next;
                                            })}
                                            className="w-6 h-6 rounded-md bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] active:scale-95 flex items-center justify-center font-bold text-xs cursor-pointer"
                                          >+</button>
                                        </div>
                                      </div>

                                      {/* Reason Chips */}
                                      <div className="flex items-center gap-1">
                                        <span className="text-[10px] font-semibold text-[var(--muted-foreground)] mr-1">Reason:</span>
                                        {['Size issue', 'Colour', 'Defect', 'Changed mind'].map(r => (
                                          <button
                                            key={r}
                                            onClick={() => setReturnLines(prev => {
                                              const next = [...prev];
                                              next[idx].reason = r;
                                              next[idx].restock = r === 'Defect' ? 'Damaged' : 'Sellable';
                                              return next;
                                            })}
                                            className={`px-2 py-0.5 rounded-md text-[10px] font-medium transition-colors cursor-pointer ${
                                              line.reason === r 
                                                ? 'bg-[var(--warning)] text-white font-bold' 
                                                : 'bg-[var(--surface)] border border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                                            }`}
                                          >
                                            {r}
                                          </button>
                                        ))}
                                      </div>

                                      {/* Restock Tag */}
                                      <span className={`text-[10px] px-2 py-0.5 rounded-md border font-semibold ${
                                        line.restock === 'Damaged' ? 'bg-[var(--danger-soft)] text-[var(--danger)] border-[var(--danger)]/30' : 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30'
                                      }`}>
                                        {line.restock === 'Damaged' ? 'Damaged (Write-off)' : 'Sellable (Restock to Shelf)'}
                                      </span>
                                    </div>
                                  )}
                                </div>
                              ))}
                            </div>
                          </div>

                          {/* Scanned Exchange Items (New) */}
                          <div className="rounded-xl border border-[var(--border)] overflow-hidden shadow-xs bg-[var(--surface)]">
                            <div className="px-3 py-2 bg-[var(--surface-sunken)] border-b border-[var(--border)] flex items-center justify-between text-xs">
                              <span className="font-bold text-[var(--primary)] flex items-center gap-1.5">
                                <Icon name="shoppingBag" size={13} />
                                <span>Replacement Exchange Items ({exchangeCart.length} added)</span>
                              </span>
                              <span className="text-[10px] text-[var(--muted-foreground)]">Scan barcode or click items to add replacement</span>
                            </div>

                            {exchangeCart.length === 0 ? (
                              <div className="p-4 text-center text-xs text-[var(--muted-foreground)]">
                                Scan or browse products to add replacement garments to this exchange bill.
                              </div>
                            ) : (
                              <div className="divide-y divide-[var(--border)]">
                                {exchangeCart.map((exItem, exIdx) => (
                                  <div key={exIdx} className="p-2.5 flex items-center justify-between text-xs">
                                    <div className="truncate flex-1">
                                      <div className="font-semibold text-[var(--foreground)] truncate">{exItem.name_en}</div>
                                      <div className="text-[10px] text-[var(--muted-foreground)]">{exItem.qty} × {formatMoney(exItem.price)}</div>
                                    </div>
                                    <div className="font-bold num text-[var(--primary)]">{formatMoney(exItem.price * exItem.qty)}</div>
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        </div>
                      ) : showPosCatalogBrowse || (posSearchQuery && filteredCatalog.length > 0 && !cart.some(c => c.product.barcode === posSearchQuery)) ? (
                        /* 2. CATALOG BROWSE OR SEARCH RESULTS GRID */
                        <div className="flex-1 flex flex-col overflow-hidden">
                          {/* Search Feedback Header with Return to Bill Button */}
                          <div className="px-3 py-2 bg-[var(--surface-sunken)] border-b border-[var(--border)] flex items-center justify-between text-xs shrink-0">
                            <div className="flex items-center gap-2">
                              <span className="font-semibold text-[var(--foreground)]">
                                {posSearchQuery ? `Search: "${posSearchQuery}"` : posCategory !== 'all' ? `Category: ${CATEGORIES.find(c => c.id === posCategory)?.name_en || posCategory}` : 'Apparel Catalog'}
                              </span>
                              <span className="text-[10px] font-mono px-2 py-0.5 rounded-md bg-[var(--surface)] border border-[var(--border)] text-[var(--muted-foreground)]">
                                {filteredCatalog.filter(p => posCategory === 'all' || p.category === posCategory).length} products
                              </span>
                            </div>

                            <button
                              onClick={() => {
                                setPosSearchQuery('');
                                setPosCategory('all');
                                setShowPosCatalogBrowse(false);
                              }}
                              className="h-7 px-3 rounded-md bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] flex items-center gap-1.5 transition-colors cursor-pointer"
                            >
                              <Icon name="receipt" size={13} className="text-[var(--primary)]" />
                              <span>View Scanned Bill ({cart.reduce((s, i) => s + i.qty, 0)} items)</span>
                            </button>
                          </div>

                          {/* Filtered Products Grid with Garment Photos */}
                          <div className="flex-1 overflow-y-auto p-3 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 auto-rows-max content-start items-start">
                            {filteredCatalog.filter(p => posCategory === 'all' || p.category === posCategory).map((product) => (
                              <div
                                key={product.id}
                                onClick={() => {
                                  if (product.variants?.length > 0) {
                                    openVariantPicker(product);
                                  } else {
                                    addToCart(product);
                                    addToast(`Added: ${product.name_en}`, "success");
                                    setShowPosCatalogBrowse(false);
                                    setPosSearchQuery('');
                                    setTimeout(focusScanInput, 50);
                                  }
                                }}
                                onContextMenu={(e) => {
                                  openContextMenu(e, product.name_en, [
                                    { label: 'Product Details', action: () => { setSelectedProduct(product); setCurrentScreen('product_detail'); } },
                                    { label: 'Select Variants (Size × Colour)', action: () => openVariantPicker(product) },
                                    { label: posMode === 'return' ? 'Add as Exchange Item' : 'Add to Bill', shortcut: 'Enter', action: () => addToCart(product) },
                                    { label: 'Check Branch Stock', action: () => { setStockCheckQuery(product.sku); setShowStockCheckModal(true); } }
                                  ]);
                                }}
                                className="p-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] hover:shadow-xs cursor-pointer transition-all flex flex-col justify-between group relative select-none h-[136px]"
                              >
                                <div className="flex items-start gap-2">
                                  {/* Thumbnail */}
                                  <div className="w-10 h-10 rounded-md border border-[var(--border)] overflow-hidden bg-[var(--surface-sunken)] shrink-0 flex items-center justify-center">
                                    {product.image ? (
                                      <img
                                        src={product.image}
                                        alt={product.name_en}
                                        className="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-200"
                                        onError={(e) => {
                                          e.currentTarget.style.display = 'none';
                                          if (e.currentTarget.nextSibling) e.currentTarget.nextSibling.style.display = 'flex';
                                        }}
                                      />
                                    ) : null}
                                    <div className={`w-full h-full flex items-center justify-center text-[var(--muted-foreground)] ${product.image ? 'hidden' : ''}`}>
                                      <Icon name={product.category === 'saree' ? 'saree' : product.category === 'panjabi' ? 'panjabi' : product.category === 'shirt' ? 'shirt' : 'tag'} size={16} />
                                    </div>
                                  </div>

                                  <div className="flex-1 min-w-0">
                                    <div className="font-semibold text-xs text-[var(--foreground)] line-clamp-1 group-hover:text-[var(--primary)] transition-colors">
                                      {lang === 'bn' ? product.name_bn : product.name_en}
                                    </div>
                                    <div className="text-[10px] text-[var(--muted-foreground)] font-mono">{product.sku}</div>
                                  </div>
                                </div>

                                <div className="mt-2 pt-1.5 border-t border-[var(--border)] flex items-end justify-between text-xs">
                                  <div>
                                    <div className="font-bold text-sm text-[var(--foreground)] num">{formatMoney(product.price)}</div>
                                    <div className="text-[10px] text-[var(--muted-foreground)]">Main: <strong className={product.stock_main <= 5 ? 'text-[var(--danger)]' : 'text-[var(--foreground)]'}>{product.stock_main} pcs</strong></div>
                                  </div>
                                  <span className="px-2 py-0.5 rounded-md bg-[var(--primary-soft)] text-[var(--primary)] font-bold text-[10px] group-hover:bg-[var(--primary)] group-hover:text-white transition-colors">
                                    + Add
                                  </span>
                                </div>
                              </div>
                            ))}
                          </div>
                        </div>
                      ) : cart.length > 0 ? (
                        /* 3. SCANNED PRODUCTS BILLING LEDGER TABLE (PRIMARY EXPERIENCE!) */
                        <div className="flex-1 flex flex-col overflow-hidden">
                          {/* Ledger Header Bar */}
                          <div className="px-3 py-2 bg-[var(--surface-sunken)]/60 border-b border-[var(--border)] flex items-center justify-between text-xs shrink-0">
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                <Icon name="receipt" size={14} className="text-[var(--primary)]" />
                                <span>Active Billing Ledger</span>
                              </span>
                              <span className="text-[10px] font-mono px-2 py-0.5 rounded-md bg-[var(--surface)] border border-[var(--border)] text-[var(--muted-foreground)]">
                                {cart.reduce((s, i) => s + i.qty, 0)} pieces · {cart.length} SKU{cart.length > 1 ? 's' : ''}
                              </span>
                            </div>

                            <div className="flex items-center gap-1.5">
                              <button
                                onClick={holdSale}
                                className="h-6 px-2 rounded-md bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[10px] font-semibold text-[var(--muted-foreground)] hover:text-[var(--foreground)] flex items-center gap-1 transition-colors cursor-pointer"
                                title="Hold / Park Current Bill (F8)"
                              >
                                <Icon name="pause" size={11} />
                                <span>Hold Bill</span>
                                <kbd className="text-[9px] font-mono">F8</kbd>
                              </button>
                              <button
                                onClick={() => {
                                  if (confirm("Are you sure you want to clear the active cart?")) {
                                    setCart([]);
                                    setSelectedCartLineIndex(0);
                                    addToast("Cart cleared", "info");
                                  }
                                }}
                                className="h-6 px-2 rounded-md bg-[var(--danger-soft)] hover:bg-[var(--danger)] hover:text-white border border-[var(--danger)]/30 text-[10px] font-semibold text-[var(--danger)] flex items-center gap-1 transition-colors cursor-pointer"
                                title="Clear All Items in Bill"
                              >
                                <Icon name="trash" size={11} />
                                <span>Clear Cart</span>
                              </button>
                            </div>
                          </div>

                          {/* Data Table of Scanned Line Items */}
                          <div className="flex-1 overflow-y-auto">
                            <table className="w-full text-left text-xs border-collapse">
                              <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[11px] font-semibold text-[var(--muted-foreground)] sticky top-0 z-10 select-none shadow-2xs">
                                <tr>
                                  <th className="py-2.5 px-3 w-8 text-center font-mono">#</th>
                                  <th className="py-2.5 px-2 w-12 text-center">Item</th>
                                  <th className="py-2.5 px-3">Description & Garment SKU</th>
                                  <th className="py-2.5 px-3 text-right">Unit MRP</th>
                                  <th className="py-2.5 px-3 text-center w-28">Quantity</th>
                                  <th className="py-2.5 px-3 text-right">Discount (F6)</th>
                                  <th className="py-2.5 px-3 text-right">VAT (15%)</th>
                                  <th className="py-2.5 px-3 text-right">Line Total (৳)</th>
                                  <th className="py-2.5 px-2 w-8 text-center"></th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-[var(--border)]">
                                {cart.map((item, idx) => {
                                  const lineUnitPrice = item.original_price - item.line_discount;
                                  const lineTotal = lineUnitPrice * item.qty;
                                  const lineVat = Math.round(lineTotal * 0.15);
                                  return (
                                    <tr
                                      key={idx}
                                      onClick={() => setSelectedCartLineIndex(idx)}
                                      onContextMenu={(e) => {
                                        openContextMenu(e, item.product.name_en, [
                                          { label: 'Apply Line Discount (F6)', shortcut: 'F6', action: () => { setSelectedCartLineIndex(idx); setDiscountTarget('line'); setShowDiscountModal(true); } },
                                          { label: 'Increase Quantity (+)', action: () => updateCartQty(idx, 1) },
                                          { label: 'Decrease Quantity (-)', action: () => updateCartQty(idx, -1) },
                                          { label: 'Remove Item', shortcut: 'Del', action: () => removeCartItem(idx), destructive: true }
                                        ]);
                                      }}
                                      className={`group transition-colors select-none ${
                                        selectedCartLineIndex === idx 
                                          ? 'bg-[var(--primary-soft)]/15 hover:bg-[var(--primary-soft)]/20' 
                                          : 'hover:bg-[var(--surface-sunken)]/60'
                                      }`}
                                    >
                                      {/* Index */}
                                      <td className="py-2.5 px-3 text-center font-mono text-[11px] text-[var(--muted-foreground)]">
                                        {idx + 1}
                                      </td>

                                      {/* Thumbnail */}
                                      <td className="py-2.5 px-2 text-center">
                                        <div className="w-9 h-9 rounded-md border border-[var(--border)] overflow-hidden bg-[var(--surface-sunken)] mx-auto flex items-center justify-center shrink-0">
                                          {item.product?.image ? (
                                            <img
                                              src={item.product.image}
                                              alt={item.product.name_en}
                                              className="w-full h-full object-cover object-top"
                                              onError={(e) => {
                                                e.currentTarget.style.display = 'none';
                                                if (e.currentTarget.nextSibling) e.currentTarget.nextSibling.style.display = 'flex';
                                              }}
                                            />
                                          ) : null}
                                          <div className={`w-full h-full flex items-center justify-center text-[var(--muted-foreground)] ${item.product?.image ? 'hidden' : ''}`}>
                                            <Icon name={item.product?.category === 'saree' ? 'saree' : item.product?.category === 'panjabi' ? 'panjabi' : item.product?.category === 'shirt' ? 'shirt' : 'tag'} size={15} />
                                          </div>
                                        </div>
                                      </td>

                                      {/* Description & SKU */}
                                      <td className="py-2.5 px-3">
                                        <div className="font-semibold text-xs text-[var(--foreground)] leading-tight">
                                          {lang === 'bn' ? item.product.name_bn : item.product.name_en}
                                        </div>
                                        <div className="text-[10px] text-[var(--muted-foreground)] flex items-center gap-1.5 mt-0.5">
                                          <span className="font-mono text-[var(--primary)] font-semibold">{item.variant?.sku || item.product.sku}</span>
                                          {item.variant && (
                                            <>
                                              <span>·</span>
                                              <span className="font-semibold text-[var(--foreground)]">{item.variant.size} / {item.variant.color}</span>
                                            </>
                                          )}
                                          <span>·</span>
                                          <span className="capitalize">{item.product.category}</span>
                                          {item.salesman && (
                                            <>
                                              <span>·</span>
                                              <span>Tag: <strong className="text-[var(--foreground)]">{item.salesman}</strong></span>
                                            </>
                                          )}
                                        </div>
                                      </td>

                                      {/* Unit Price */}
                                      <td className="py-2.5 px-3 text-right font-sans tabular-nums num text-xs">
                                        {item.line_discount > 0 ? (
                                          <div>
                                            <div className="line-through text-[10px] text-[var(--muted-foreground)] opacity-70">
                                              {formatMoney(item.original_price)}
                                            </div>
                                            <div className="font-bold text-[var(--foreground)]">
                                              {formatMoney(lineUnitPrice)}
                                            </div>
                                          </div>
                                        ) : (
                                          <div className="font-medium text-[var(--foreground)]">
                                            {formatMoney(item.original_price)}
                                          </div>
                                        )}
                                      </td>

                                      {/* Quantity Stepper */}
                                      <td className="py-2.5 px-3">
                                        <div className="flex items-center justify-center gap-1">
                                          <button
                                            type="button"
                                            onClick={(e) => { e.stopPropagation(); updateCartQty(idx, -1); }}
                                            className="w-6 h-6 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] flex items-center justify-center text-[var(--foreground)] active:scale-95 transition-all cursor-pointer"
                                            title="Decrease quantity"
                                          >
                                            <Icon name="minus" size={11} />
                                          </button>
                                          <input
                                            type="number"
                                            min="1"
                                            value={item.qty}
                                            onClick={(e) => e.stopPropagation()}
                                            onChange={(e) => {
                                              const val = parseInt(e.target.value) || 1;
                                              setCart(prev => {
                                                const next = [...prev];
                                                next[idx] = { ...next[idx], qty: Math.max(1, val) };
                                                return next;
                                              });
                                            }}
                                            className="w-10 h-6 text-center font-mono font-bold text-xs bg-[var(--surface)] border border-[var(--border)] rounded focus:border-[var(--primary)] focus:outline-none"
                                          />
                                          <button
                                            type="button"
                                            onClick={(e) => { e.stopPropagation(); updateCartQty(idx, 1); }}
                                            className="w-6 h-6 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] flex items-center justify-center text-[var(--foreground)] active:scale-95 transition-all cursor-pointer"
                                            title="Increase quantity"
                                          >
                                            <Icon name="plus" size={11} />
                                          </button>
                                        </div>
                                      </td>

                                      {/* Line Discount */}
                                      <td className="py-2.5 px-3 text-right">
                                        {item.line_discount > 0 ? (
                                          <button
                                            onClick={(e) => {
                                              e.stopPropagation();
                                              setSelectedCartLineIndex(idx);
                                              setDiscountTarget('line');
                                              setShowDiscountModal(true);
                                            }}
                                            className="px-2 py-0.5 rounded-md bg-[var(--danger-soft)] hover:bg-[var(--danger)] hover:text-white border border-[var(--danger)]/30 text-[var(--danger)] text-[10px] font-bold inline-flex items-center gap-1 transition-colors cursor-pointer"
                                            title="Click to edit line discount (F6)"
                                          >
                                            <span>−{item.discount_percent ? `${item.discount_percent}%` : formatMoney(item.line_discount)}</span>
                                            <Icon name="edit" size={10} />
                                          </button>
                                        ) : (
                                          <button
                                            onClick={(e) => {
                                              e.stopPropagation();
                                              setSelectedCartLineIndex(idx);
                                              setDiscountTarget('line');
                                              setShowDiscountModal(true);
                                            }}
                                            className="px-2 py-0.5 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] hover:border-[var(--primary)] text-[var(--muted-foreground)] hover:text-[var(--primary)] text-[10px] font-medium inline-flex items-center gap-1 transition-colors cursor-pointer"
                                            title="Apply Line Discount (F6)"
                                          >
                                            <Icon name="percent" size={10} />
                                            <span>Disc</span>
                                          </button>
                                        )}
                                      </td>

                                      {/* VAT 15% */}
                                      <td className="py-2.5 px-3 text-right font-sans tabular-nums num text-[11px] text-[var(--muted-foreground)]">
                                        {formatMoney(lineVat)}
                                      </td>

                                      {/* Line Total */}
                                      <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-xs text-[var(--foreground)]">
                                        {formatMoney(lineTotal)}
                                      </td>

                                      {/* Remove Action */}
                                      <td className="py-2.5 px-2 text-center">
                                        <button
                                          type="button"
                                          onClick={(e) => { e.stopPropagation(); removeCartItem(idx); }}
                                          className="p-1 rounded-md text-[var(--muted-foreground)] hover:text-[var(--danger)] hover:bg-[var(--danger-soft)] transition-colors cursor-pointer"
                                          title="Remove item from bill"
                                        >
                                          <Icon name="x" size={13} />
                                        </button>
                                      </td>
                                    </tr>
                                  );
                                })}
                              </tbody>
                            </table>
                          </div>

                          {/* Billing Table Bottom Status Bar */}
                          <div className="p-2.5 bg-[var(--surface-sunken)] border-t border-[var(--border)] flex flex-wrap items-center justify-between gap-2 text-xs shrink-0">
                            <div className="flex items-center gap-2 text-[11px] text-[var(--muted-foreground)]">
                              <span className="font-medium text-[var(--foreground)]">{cart.reduce((s, i) => s + i.qty, 0)} pieces in current bill</span>
                              <span>·</span>
                              <span>VAT 15% Mushak 6.3 automatically calculated</span>
                            </div>

                            <div className="flex items-center gap-1 text-[10px] text-[var(--muted-foreground)] font-mono">
                              <span>Shortcuts:</span>
                              <kbd className="px-1.5 py-0.5 rounded bg-[var(--surface)] border border-[var(--border)]">F6</kbd>
                              <span>Line Disc</span>
                              <kbd className="px-1.5 py-0.5 rounded bg-[var(--surface)] border border-[var(--border)]">⇧F6</kbd>
                              <span>Bill Disc</span>
                              <kbd className="px-1.5 py-0.5 rounded bg-[var(--surface)] border border-[var(--border)]">F9</kbd>
                              <span>Pay</span>
                            </div>
                          </div>
                        </div>
                      ) : (
                        /* 4. IDLE CASHIER SCAN COCKPIT (Barcode-First Retail Workflow) */
                        <div className="flex-1 overflow-y-auto p-4 flex flex-col justify-between space-y-3">
                          {/* 1. Dedicated Hardware Barcode Scan Terminal Target */}
                          <div 
                            onClick={() => {
                              const inp = document.getElementById('pos-barcode-input');
                              if (inp) inp.focus();
                            }}
                            className="p-6 rounded-xl bg-radial-[at_top] from-[var(--surface-raised)] to-[var(--surface)] border-2 border-dashed border-[var(--border)] hover:border-[var(--primary)]/70 transition-all cursor-pointer flex flex-col items-center justify-center text-center relative group shadow-2xs"
                          >
                            <div className="absolute top-3 right-3 flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-[10px] font-mono text-[var(--muted-foreground)]">
                              <span className="w-2 h-2 rounded-full bg-[var(--success)] animate-pulse"></span>
                              <span>LASER SCANNER READY</span>
                            </div>

                            <div className="w-14 h-14 rounded-xl bg-[var(--primary-soft)]/20 border border-[var(--primary)]/30 flex items-center justify-center text-[var(--primary)] mb-2 group-hover:scale-105 group-hover:bg-[var(--primary-soft)]/30 transition-all shadow-inner">
                              <Icon name="barcode" size={30} />
                            </div>

                            <div className="font-bold text-sm text-[var(--foreground)]">
                              Ready to Scan Garment Hangtag Barcode
                            </div>
                            <div className="text-xs text-[var(--muted-foreground)] max-w-md mt-0.5">
                              Point handheld laser scanner at apparel label or press <kbd className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[11px] font-mono font-bold text-[var(--foreground)]">F2</kbd> or <kbd className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[11px] font-mono font-bold text-[var(--foreground)]">/</kbd> to type SKU / model
                            </div>

                            {/* Quick Simulate Scan Buttons for Demo */}
                            <div className="mt-3 flex flex-wrap items-center justify-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                              <span className="text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider font-semibold mr-1">Demo Scans:</span>
                              {INITIAL_PRODUCTS.slice(0, 4).map(sampleP => (
                                <button
                                  key={sampleP.id}
                                  onClick={() => {
                                    addToCart(sampleP);
                                    addToast(`Scanned: ${sampleP.name_en} (${sampleP.sku})`, "success");
                                  }}
                                  className="h-6 px-2.5 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)]/30 border border-[var(--border)] hover:border-[var(--primary)] text-[10px] font-medium text-[var(--foreground)] flex items-center gap-1.5 transition-colors cursor-pointer"
                                >
                                  <Icon name="barcode" size={11} className="text-[var(--primary)]" />
                                  <span>{sampleP.name_en.split(' ')[0]} {sampleP.name_en.split(' ')[1] || ''}</span>
                                  <span className="font-mono text-[9px] text-[var(--muted-foreground)]">{sampleP.sku}</span>
                                </button>
                              ))}
                            </div>
                          </div>

                          {/* 2. Fast Cashier Action Hotkeys Grid */}
                          <div className="space-y-1.5">
                            <div className="flex items-center justify-between text-xs font-bold text-[var(--muted-foreground)] uppercase tracking-wider px-1">
                              <span>Cashier Rapid Operations</span>
                              <span className="text-[10px] lowercase font-normal">click or press shortcut</span>
                            </div>
                            <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
                              <button
                                onClick={() => {
                                  const inp = document.getElementById('pos-barcode-input');
                                  if (inp) { inp.focus(); inp.select(); }
                                }}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-[var(--primary-soft)]/20 text-[var(--primary)] group-hover:scale-105 transition-transform">
                                    <Icon name="search" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">F2</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Scan / Search SKU</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Focus barcode input</div>
                              </button>

                              <button
                                onClick={() => setShowPriceCheckModal(true)}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-blue-500/10 text-blue-600 group-hover:scale-105 transition-transform">
                                    <Icon name="tag" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">F5</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Price & Stock Check</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Multi-branch stock query</div>
                              </button>

                              <button
                                onClick={() => {
                                  if (posMode === 'sale') {
                                    startReturnModeForInvoice('DF1-R1-26000412');
                                  } else {
                                    setPosMode('sale');
                                  }
                                }}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--warning)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-[var(--warning-soft)] text-[var(--warning)] group-hover:scale-105 transition-transform">
                                    <Icon name="refresh" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">F11</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Return / Exchange</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Invoice lookup & return</div>
                              </button>

                              <button
                                onClick={() => setShowCustomerAttachModal(true)}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-emerald-500/10 text-emerald-600 group-hover:scale-105 transition-transform">
                                    <Icon name="user" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">F4</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Attach Customer</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">VIP phone & loyalty</div>
                              </button>

                              <button
                                onClick={() => openCollectDueModal()}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--warning)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-amber-500/10 text-amber-600 group-hover:scale-105 transition-transform">
                                    <Icon name="banknote" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">Ctrl+D</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Collect Due Payment</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Bakir khata collection</div>
                              </button>

                              <button
                                onClick={holdSale}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-purple-500/10 text-purple-600 group-hover:scale-105 transition-transform">
                                    <Icon name="pause" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">F8</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Hold / Park Cart</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Suspend up to 5 bills</div>
                              </button>

                              <button
                                onClick={() => setShowDrawerCountModal(true)}
                                className="p-2.5 rounded-lg bg-[var(--surface)] hover:bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-cyan-500/10 text-cyan-600 group-hover:scale-105 transition-transform">
                                    <Icon name="idCard" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">F1</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--foreground)] mt-1.5">Cash Drawer Audit</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Till denominations & shift</div>
                              </button>

                              <button
                                onClick={() => setShowPosCatalogBrowse(true)}
                                className="p-2.5 rounded-lg bg-[var(--primary-soft)]/20 hover:bg-[var(--primary-soft)]/40 border border-[var(--primary)]/30 hover:border-[var(--primary)] text-left transition-all group cursor-pointer shadow-2xs"
                              >
                                <div className="flex items-center justify-between">
                                  <span className="p-1.5 rounded-md bg-[var(--primary)] text-[var(--primary-foreground)] group-hover:scale-105 transition-transform">
                                    <Icon name="grid" size={15} />
                                  </span>
                                  <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface)] border border-[var(--border)]">Browse</kbd>
                                </div>
                                <div className="font-bold text-xs text-[var(--primary)] mt-1.5">Browse Catalog Grid</div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">Visual garment selector</div>
                              </button>
                            </div>
                          </div>

                          {/* 3. Catalog Browse Switcher Hint */}
                          <div className="p-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between gap-3">
                            <div className="flex items-center gap-2">
                              <Icon name="layers" size={16} className="text-[var(--primary)]" />
                              <div className="text-xs text-[var(--muted-foreground)]">
                                Want visual product selection? Click any category above (<span className="text-[var(--foreground)] font-medium">Panjabi, Saree, Kurti...</span>) or open the complete catalog grid.
                              </div>
                            </div>
                            <button
                              onClick={() => setShowPosCatalogBrowse(true)}
                              className="h-7 px-3 rounded-md bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] hover:text-[var(--primary)] shrink-0 transition-colors cursor-pointer"
                            >
                              Browse Products ({INITIAL_PRODUCTS.length})
                            </button>
                          </div>
                        </div>
                      )}

                    </div>
                  </div>

                  {/* ================= RIGHT COLUMN: CUSTOMER & SETTLEMENT COCKPIT ================= */}
                  <div className="w-full lg:w-[380px] xl:w-[430px] bg-[var(--surface)] flex flex-col shrink-0 h-full overflow-hidden border-l border-[var(--border)] lg:border-l">

                    {/* Scrollable Upper Cockpit: Customer, Breakdown & Tender Presets */}
                    <div className="flex-1 overflow-y-auto divide-y divide-[var(--border)]">

                      {/* SECTION 1: CUSTOMER & LOYALTY CARD */}
                    <div className="p-3 bg-[var(--surface)] space-y-2.5">
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider flex items-center gap-1.5">
                          <Icon name="user" size={13} className="text-[var(--primary)]" />
                          <span>Customer & Loyalty Tier</span>
                        </span>
                        {attachedCustomer ? (
                          <button
                            onClick={() => setAttachedCustomer(null)}
                            className="text-[10px] font-medium text-[var(--muted-foreground)] hover:text-[var(--danger)] flex items-center gap-1 cursor-pointer"
                            title="Detach Customer"
                          >
                            <span>Detach</span>
                            <Icon name="x" size={11} />
                          </button>
                        ) : null}
                      </div>

                      {attachedCustomer ? (
                        <div 
                          onClick={() => setShowCustomerAttachModal(true)}
                          onContextMenu={(e) => {
                            openContextMenu(e, attachedCustomer.name_en, [
                              { label: 'Collect Due (Ctrl+D)', shortcut: 'Ctrl+D', action: () => openCollectDueModal(attachedCustomer) },
                              { label: 'Verify PIN Pad', shortcut: 'PIN', action: () => { setPinDigits(''); setPinPadState('input'); setShowCustomerPinPadModal(true); } },
                              { label: 'View 360 Profile', action: () => { setSelectedCustomer(attachedCustomer); setCurrentScreen('customer_profile'); } },
                              { label: 'Detach Customer', action: () => setAttachedCustomer(null), destructive: true }
                            ]);
                          }}
                          className="p-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] transition-all cursor-pointer space-y-2"
                        >
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2.5 min-w-0">
                              <div className="w-8 h-8 rounded-md bg-[var(--primary)] text-[var(--primary-foreground)] flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
                                {attachedCustomer.name_en[0]}
                              </div>
                              <div className="truncate">
                                <div className="font-bold text-xs text-[var(--foreground)] truncate">{attachedCustomer.name_en}</div>
                                {attachedCustomer.shop_name && <div className="text-[10px] font-semibold text-[var(--foreground)] truncate">{attachedCustomer.shop_name}</div>}
                                <div className="text-[10px] text-[var(--muted-foreground)] font-mono">{attachedCustomer.phone}</div>
                              </div>
                            </div>
                            <span className="px-2 py-0.5 rounded-md border border-[var(--accent)]/30 text-[10px] font-semibold bg-[var(--accent)]/10 text-[var(--accent)] shrink-0">
                              {attachedCustomer.group || 'VIP'}
                            </span>
                          </div>

                          <div className="pt-2 border-t border-[var(--border)] flex items-center justify-between text-[11px]">
                            <div className="flex items-center gap-1">
                              <span className="text-[var(--muted-foreground)]">Balance:</span>
                              <span className={`font-bold font-mono ${attachedCustomer.balance < 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]'}`}>
                                {attachedCustomer.balance < 0 ? `Due ${formatMoney(Math.abs(attachedCustomer.balance))}` : formatMoney(attachedCustomer.balance)}
                              </span>
                            </div>
                            <div className="flex items-center gap-1">
                              <span className="text-[var(--muted-foreground)]">Points:</span>
                              <span className="font-bold font-mono text-[var(--primary)]">420 pts</span>
                            </div>
                          </div>

                          <div className="flex items-center gap-1.5 pt-1">
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); openCollectDueModal(attachedCustomer); }}
                              className="flex-1 h-6 rounded bg-[var(--warning-soft)] hover:bg-[var(--warning)] hover:text-white border border-[var(--warning)]/30 text-[var(--warning)] text-[10px] font-bold flex items-center justify-center gap-1 transition-colors cursor-pointer"
                            >
                              <Icon name="banknote" size={11} />
                              <span>Collect Due</span>
                            </button>
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setPinDigits(''); setPinPadState('input'); setShowCustomerPinPadModal(true); }}
                              className="px-2 h-6 rounded bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[10px] font-medium text-[var(--foreground)] cursor-pointer"
                            >
                              PIN
                            </button>
                            <button
                              type="button"
                              onClick={(e) => { e.stopPropagation(); setSelectedCustomer(attachedCustomer); setCurrentScreen('customer_profile'); }}
                              className="px-2 h-6 rounded bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[10px] font-medium text-[var(--foreground)] cursor-pointer"
                            >
                              360°
                            </button>
                          </div>
                        </div>
                      ) : (
                        <button 
                          onClick={() => setShowCustomerAttachModal(true)}
                          className="w-full h-11 rounded-lg border-2 border-[var(--warning)] bg-[var(--warning-soft)] hover:brightness-95 text-xs font-bold text-[var(--warning-text)] flex items-center justify-between px-3 transition-colors cursor-pointer"
                        >
                          <div className="flex items-center gap-2">
                            <span className="relative flex h-2 w-2">
                              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-[var(--warning)] opacity-75"></span>
                              <span className="relative inline-flex rounded-full h-2 w-2 bg-[var(--warning)]"></span>
                            </span>
                            <Icon name="user" size={15} />
                            <span>Walk-in Customer · Attach customer (F4)</span>
                          </div>
                          <kbd className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-[var(--surface)] border border-[var(--warning)]">F4</kbd>
                        </button>
                      )}
                    </div>

                    {/* SECTION 2: PARKED / HELD BILLS DRAWER (IF ANY) */}
                    {heldSales.length > 0 && (
                      <div className="p-2.5 bg-purple-500/10 border-b border-purple-500/20 flex items-center justify-between text-xs">
                        <div className="flex items-center gap-1.5 text-purple-700 dark:text-purple-300 font-semibold">
                          <Icon name="pause" size={13} />
                          <span>{heldSales.length} Parked Bill(s) Suspended</span>
                        </div>
                        <button
                          onClick={() => setShowHeldSalesModal(true)}
                          className="h-6 px-2.5 rounded-md bg-purple-600 text-white text-[10px] font-bold hover:bg-purple-700 transition-colors cursor-pointer"
                        >
                          Recall Bills
                        </button>
                      </div>
                    )}

                    {/* SECTION 3: COMPREHENSIVE FINANCIAL SUMMARY */}
                    {posMode === 'return' ? (
                      /* Return / Exchange Summary */
                      <div className="p-3 bg-[var(--warning-soft)]/40 space-y-2 text-xs">
                        <div className="flex items-center justify-between font-bold text-xs text-[var(--warning)] pb-1 border-b border-[var(--warning)]/20">
                          <span>Return Voucher Computation</span>
                          <span className="font-mono text-[10px]">{returnSourceSale?.invoice || 'RET-VOUCHER'}</span>
                        </div>
                        <div className="flex justify-between text-[var(--danger)] font-medium">
                          <span>Return Total (15% VAT Reversal):</span>
                          <span className="font-bold num">−{formatMoney(returnTotalGross)}</span>
                        </div>
                        {exchangeTotalGross > 0 && (
                          <div className="flex justify-between text-[var(--primary)] font-medium">
                            <span>New Exchange Total (with VAT):</span>
                            <span className="font-bold num">+{formatMoney(exchangeTotalGross)}</span>
                          </div>
                        )}
                        <div className="pt-2 border-t border-[var(--warning)]/30 flex items-baseline justify-between font-bold text-sm">
                          <span>{netExchangeDiff >= 0 ? 'Customer Pays Net:' : 'Store Refund to Customer:'}</span>
                          <span className={`text-2xl num ${netExchangeDiff >= 0 ? 'text-[var(--primary)]' : 'text-[var(--warning)]'}`}>
                            {formatMoney(Math.abs(netExchangeDiff))}
                          </span>
                        </div>

                        {/* Refund Tender Method Dropdown */}
                        <div className="pt-1 flex items-center justify-between text-xs">
                          <span className="font-semibold text-[var(--muted-foreground)]">Refund Method:</span>
                          <select
                            value={returnRefundMethod}
                            onChange={(e) => setReturnRefundMethod(e.target.value)}
                            className="h-7 px-2 rounded-md bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold cursor-pointer"
                          >
                            <option>Cash</option>
                            <option>Original method (bKash)</option>
                            <option>Adjust Customer Due</option>
                            <option>Store Credit Note</option>
                          </select>
                        </div>

                        <button
                          onClick={completeReturnExchange}
                          className="w-full h-11 rounded-lg bg-[var(--warning)] hover:bg-[var(--warning)]/90 text-white font-bold text-xs shadow-sm flex items-center justify-center gap-2 mt-2 active:translate-y-0.5 transition-all cursor-pointer"
                        >
                          <Icon name="check" size={16} />
                          <span>Complete Return / Exchange (F10)</span>
                        </button>
                      </div>
                    ) : (
                      /* Normal Sale Financial Ledger */
                      <div className="p-3 bg-[var(--surface)] space-y-2 text-xs">
                        <div className="flex items-center justify-between text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider pb-1 border-b border-[var(--border)]">
                          <span>Payment Breakdown</span>
                          <span>NBR Mushak 6.3</span>
                        </div>

                        {/* Subtotal */}
                        <div className="flex justify-between text-[var(--muted-foreground)]">
                          <span>Gross Subtotal ({cart.reduce((a, b) => a + b.qty, 0)} items):</span>
                          <span className="num font-semibold text-[var(--foreground)]">{formatMoney(subtotal)}</span>
                        </div>

                        {/* Line Item Discounts */}
                        {totalLineDiscounts > 0 && (
                          <div className="flex justify-between text-[var(--danger)]">
                            <span>Line Item Discounts:</span>
                            <span className="num font-semibold">−{formatMoney(totalLineDiscounts)}</span>
                          </div>
                        )}

                        {/* Group Discount (VIP Privilege) */}
                        {groupDiscountAmount > 0 && (
                          <div className="flex items-center justify-between text-[var(--primary)]">
                            <span className="flex items-center gap-1">
                              <span>VIP Privilege ({customerGroupDiscountPercent}%):</span>
                              <button
                                onClick={() => {
                                  if (viewAsRole === 'Cashier') {
                                    setShowManagerPinInline(true);
                                    addToast("Manager PIN required to remove group discount", "warning");
                                  } else {
                                    setIsGroupDiscountRemoved(true);
                                    addToast("Group discount removed", "info");
                                  }
                                }}
                                className="w-3.5 h-3.5 rounded bg-[var(--primary-soft)] hover:bg-[var(--primary)] hover:text-white flex items-center justify-center text-[9px]"
                                title="Remove Group Discount"
                              >×</button>
                            </span>
                            <span className="num font-semibold">−{formatMoney(groupDiscountAmount)}</span>
                          </div>
                        )}

                        {/* Order Discount Row with Dedicated Trigger */}
                        <div className="flex items-center justify-between py-1 px-2 rounded-md bg-[var(--surface-sunken)] border border-dashed border-[var(--border)]">
                          <span className="font-semibold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                            <Icon name="tag" size={12} className="text-[var(--primary)]" />
                            <span>Bill Discount:</span>
                          </span>
                          {orderDiscountAmount > 0 ? (
                            <div className="flex items-center gap-1.5">
                              <span className="font-bold text-[var(--danger)] num">
                                −{formatMoney(orderDiscountAmount)} {orderDiscountType === 'percent' ? `(${orderDiscountPercent}%)` : ''}
                              </span>
                              <button
                                onClick={() => { setOrderDiscountAmount(0); setOrderDiscountPercent(0); }}
                                className="text-[10px] text-[var(--danger)] hover:underline font-bold cursor-pointer"
                                title="Remove order discount"
                              >
                                ×
                              </button>
                            </div>
                          ) : (
                            <button
                              onClick={() => {
                                setDiscountTarget('order');
                                setDiscountTab('percent');
                                setDiscountValue('');
                                setShowDiscountModal(true);
                              }}
                              className="h-5 px-2 rounded bg-[var(--primary-soft)] hover:bg-[var(--primary)] hover:text-white text-[var(--primary)] font-bold text-[10px] flex items-center gap-1 transition-colors cursor-pointer"
                            >
                              <Icon name="percent" size={10} />
                              <span>+ Bill Disc (⇧F6)</span>
                            </button>
                          )}
                        </div>

                        {/* Standard 15% VAT (Mushak 6.3) */}
                        <div className="flex justify-between text-[var(--muted-foreground)]">
                          <span>Standard 15% VAT (Mushak 6.3):</span>
                          <span className="num font-semibold text-[var(--foreground)]">{formatMoney(vatAmount)}</span>
                        </div>

                        {/* Prominent Grand Total Display */}
                        <div className="pt-2 border-t border-[var(--border)] flex items-baseline justify-between">
                          <span className="font-bold text-xs uppercase tracking-wider text-[var(--foreground)]">Total Payable:</span>
                          <span className="font-extrabold text-2xl xl:text-3xl text-[var(--primary)] num tracking-tight">
                            {formatMoney(totalPayable)}
                          </span>
                        </div>
                      </div>
                    )}

                    {/* SECTION 4: TENDER & PAYMENT PAD (SALE MODE) */}
                    {posMode === 'sale' && (
                      <div className="p-3 bg-[var(--surface)] space-y-3 shrink-0">
                        {/* Quick-Tender Presets Row */}
                        <div>
                          <div className="text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider mb-1.5">
                            Instant Tender
                          </div>
                          <div className="grid grid-cols-3 gap-1.5">
                            <button
                              type="button"
                              onClick={() => {
                                if (cart.length > 0) {
                                  setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                                  setShowPaymentModal(true);
                                }
                              }}
                              disabled={cart.length === 0}
                              className="h-8 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-semibold text-[var(--foreground)] flex items-center justify-center gap-1.5 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                              title="Quick Pay with Cash (Alt+C)"
                            >
                              <Icon name="banknote" size={13} className="text-[var(--success)]" />
                              <span>Cash</span>
                              <kbd className="text-[9px] px-1 py-0.2 rounded bg-[var(--surface)] text-[var(--muted-foreground)] font-mono border border-[var(--border)]">Alt+C</kbd>
                            </button>
                            <button
                              type="button"
                              onClick={() => {
                                if (cart.length > 0) {
                                  setTenderTally({ cash: 0, card: 0, bkash: totalPayable, nagad: 0, rocket: 0, bank: 0 });
                                  setShowPaymentModal(true);
                                }
                              }}
                              disabled={cart.length === 0}
                              className="h-8 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] hover:border-[#E2136E] text-xs font-semibold text-[var(--foreground)] flex items-center justify-center gap-1.5 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                              title="Quick Pay with bKash (Alt+B)"
                            >
                              <span className="w-2 h-2 rounded-full bg-[#E2136E]"></span>
                              <span>bKash</span>
                              <kbd className="text-[9px] px-1 py-0.2 rounded bg-[var(--surface)] text-[var(--muted-foreground)] font-mono border border-[var(--border)]">Alt+B</kbd>
                            </button>
                            <button
                              type="button"
                              onClick={() => {
                                if (cart.length > 0) {
                                  setTenderTally({ cash: 0, card: totalPayable, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                                  setShowPaymentModal(true);
                                }
                              }}
                              disabled={cart.length === 0}
                              className="h-8 rounded-md bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-semibold text-[var(--foreground)] flex items-center justify-center gap-1.5 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
                              title="Quick Pay with Card (Alt+K)"
                            >
                              <Icon name="creditCard" size={13} className="text-[var(--primary)]" />
                              <span>Card</span>
                              <kbd className="text-[9px] px-1 py-0.2 rounded bg-[var(--surface)] text-[var(--muted-foreground)] font-mono border border-[var(--border)]">Alt+K</kbd>
                            </button>
                          </div>
                        </div>

                        {/* Cash Change Return Calculator */}
                        <div className="p-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] space-y-1.5">
                          <div className="flex items-center justify-between text-[10px] text-[var(--muted-foreground)] font-semibold uppercase">
                            <span>Cash Received Preset</span>
                            <span>Change Calculator</span>
                          </div>
                          <div className="grid grid-cols-4 gap-1">
                            {[
                              { label: 'Exact', amt: totalPayable },
                              { label: '৳1,000', amt: 1000 },
                              { label: '৳2,000', amt: 2000 },
                              { label: '৳5,000', amt: 5000 }
                            ].map((preset, pIdx) => (
                              <button
                                key={pIdx}
                                type="button"
                                onClick={() => {
                                  setTenderTally({ cash: preset.amt, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                                }}
                                className="h-6 rounded bg-[var(--surface)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[10px] font-mono font-medium text-[var(--foreground)] transition-colors cursor-pointer"
                              >
                                {preset.label}
                              </button>
                            ))}
                          </div>
                          {tenderTally.cash > totalPayable && totalPayable > 0 && (
                            <div className="flex items-center justify-between text-xs font-bold pt-1 border-t border-[var(--border)] text-[var(--success)]">
                              <span>Change to Return:</span>
                              <span className="font-mono text-sm">{formatMoney(tenderTally.cash - totalPayable)}</span>
                            </div>
                          )}
                        </div>
                      </div>
                    )}

                    </div>
                    {/* End Scrollable Upper Cockpit */}

                    {/* ================= PINNED BOTTOM SETTLEMENT DOCK (ALWAYS AT SCREEN BOTTOM) ================= */}
                    <div className="p-3 bg-[var(--surface)] border-t border-[var(--border)] shrink-0 shadow-[0_-4px_20px_rgba(0,0,0,0.06)] space-y-2 mt-auto">
                      {/* Sub-bar: Cart items summary & clear action */}
                      <div className="flex items-center justify-between text-[11px] px-0.5">
                        <div className="flex items-center gap-1.5 text-[var(--muted-foreground)]">
                          <span>Cart Summary:</span>
                          <span className="font-bold font-mono text-[var(--foreground)]">{cart.reduce((s, i) => s + i.qty, 0)} pcs</span>
                          {vatAmount > 0 && (
                            <span className="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">· Incl. 15% VAT</span>
                          )}
                        </div>

                        {cart.length > 0 && (
                          <button
                            type="button"
                            onClick={() => {
                              if (confirm("Are you sure you want to clear the active cart?")) {
                                clearCart();
                                addToast("Cart cleared", "info");
                              }
                            }}
                            className="text-[11px] font-semibold text-[var(--muted-foreground)] hover:text-[var(--danger)] flex items-center gap-1 transition-colors cursor-pointer"
                            title="Clear active transaction (Ctrl+D)"
                          >
                            <Icon name="trash" size={11} />
                            <span>Clear (Ctrl+D)</span>
                          </button>
                        )}
                      </div>

                      {/* Primary Settlement Button (F9) */}
                      {posMode === 'return' ? (
                        /* Return / Exchange Trigger Button */
                        <button
                          type="button"
                          onClick={completeReturnExchange}
                          disabled={returnTotalGross === 0 && exchangeTotalGross === 0}
                          className="w-full h-14 rounded-xl bg-gradient-to-r from-amber-600 via-amber-600 to-yellow-600 hover:from-amber-500 hover:to-yellow-500 active:scale-[0.99] text-white font-bold text-sm shadow-md shadow-amber-900/20 flex items-center justify-between px-4 transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                          <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-lg bg-white/20 flex items-center justify-center text-white shrink-0 shadow-inner">
                              <Icon name="refresh" size={18} />
                            </div>
                            <div className="text-left leading-tight">
                              <div className="flex items-center gap-1.5">
                                <span className="font-black text-sm tracking-wide uppercase">COMPLETE EXCHANGE</span>
                                <kbd className="px-1.5 py-0.2 rounded bg-black/30 text-white text-[9px] font-mono font-bold border border-white/20">F10</kbd>
                              </div>
                              <div className="text-[10px] text-amber-100 font-medium">Issue return voucher & update inventory</div>
                            </div>
                          </div>
                          <div className="text-right leading-tight">
                            <div className="text-lg font-black font-mono tracking-tight">
                              {formatMoney(Math.abs(netExchangeDiff))}
                            </div>
                            <div className="text-[10px] text-amber-100/90 font-medium">
                              {netExchangeDiff >= 0 ? 'Pay Due' : 'Store Refund'}
                            </div>
                          </div>
                        </button>
                      ) : (
                        /* Standard Sale Settle / Pay Button */
                        <button
                          type="button"
                          onClick={() => {
                            if (cart.length > 0) {
                              setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                              setShowPaymentModal(true);
                            }
                          }}
                          disabled={cart.length === 0}
                          className={`w-full h-14 rounded-xl transition-all duration-150 flex items-center justify-between px-4 cursor-pointer active:scale-[0.99] ${
                            cart.length > 0
                              ? 'bg-gradient-to-r from-emerald-600 via-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white shadow-lg shadow-emerald-900/25 hover:shadow-xl'
                              : 'bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)] opacity-50 cursor-not-allowed'
                          }`}
                          title="Open Payment & Due Settlement (F9)"
                        >
                          <div className="flex items-center gap-3">
                            <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${
                              cart.length > 0 ? 'bg-white/20 text-white shadow-inner' : 'bg-[var(--surface)] text-[var(--muted-foreground)]'
                            }`}>
                              <Icon name="creditCard" size={20} />
                            </div>
                            <div className="text-left leading-tight">
                              <div className="flex items-center gap-2">
                                <span className="font-extrabold text-sm tracking-wider uppercase">
                                  {cart.length > 0 ? 'SETTLE / PAY' : 'EMPTY CART'}
                                </span>
                                <kbd className={`px-1.5 py-0.5 rounded text-[10px] font-mono font-bold border ${
                                  cart.length > 0 ? 'bg-black/25 text-white border-white/25 shadow-2xs' : 'bg-[var(--surface)] border-[var(--border)] text-[var(--muted-foreground)]'
                                }`}>
                                  F9
                                </kbd>
                              </div>
                              <div className={`text-[11px] font-medium ${cart.length > 0 ? 'text-emerald-100/90' : 'text-[var(--muted-foreground)]'}`}>
                                {cart.length > 0 ? 'Cash · bKash · Card or Due Sale' : 'Scan garments to begin transaction'}
                              </div>
                            </div>
                          </div>

                          <div className="text-right leading-tight">
                            <div className={`text-xl xl:text-2xl font-black font-mono tracking-tight ${
                              cart.length > 0 ? 'text-white drop-shadow-xs' : 'text-[var(--muted-foreground)]'
                            }`}>
                              {formatMoney(totalPayable)}
                            </div>
                            <div className={`text-[10px] font-semibold uppercase tracking-wider ${cart.length > 0 ? 'text-emerald-100/90' : 'text-[var(--muted-foreground)]'}`}>
                              {cart.reduce((s, i) => s + i.qty, 0)} {cart.reduce((s, i) => s + i.qty, 0) === 1 ? 'item' : 'items'} · Charge
                            </div>
                          </div>
                        </button>
                      )}
                    </div>

                  </div>

                </div>
              )}

              {/* ================= SCREEN 2: SALES HISTORY (G S) ================= */}
              {currentScreen === 'sales' && (
                <DataList
                  id="sales"
                  title="Sales & Invoices"
                  subtitle="View completed sales, filter by cashier/courier/returns, reprint receipts or initiate returns"
                  data={MOCK_SALES}
                  keyField="id"
                  dateField="date"
                  searchPlaceholder="Search invoice #, customer name/no, cashier... (/)"
                  searchFields={[
                    (s) => s.invoice,
                    (s) => s.customer,
                    (s) => s.customer_no,
                    (s) => s.cashier,
                    (s) => s.payment_method,
                    (s) => s.sync_status,
                    (s) => s.courier
                  ]}
                  columns={[
                    {
                      id: 'invoice',
                      header: 'Invoice No',
                      sortable: true,
                      className: 'font-mono font-bold text-[var(--primary)]',
                      accessor: (s) => s.invoice,
                      cell: (s) => (
                        <span className="font-mono font-bold text-[var(--primary)] group-hover:underline">
                          {s.invoice}
                        </span>
                      )
                    },
                    {
                      id: 'date',
                      header: 'Date & Time',
                      sortable: true,
                      className: 'text-[var(--muted-foreground)]',
                      accessor: (s) => (s.date || 'Today') + ' ' + s.time,
                      cell: (s) => (
                        <span className="text-[var(--muted-foreground)]">
                          {s.date || 'Today'} · {s.time}
                        </span>
                      )
                    },
                    {
                      id: 'customer',
                      header: 'Customer',
                      sortable: true,
                      accessor: (s) => formatSaleCustomer(s).name,
                      cell: (s) => {
                        const cust = formatSaleCustomer(s);
                        return (
                          <div>
                            <div className="font-semibold text-[var(--foreground)]">{cust.name}</div>
                            {cust.no && <div className="text-[10px] font-mono text-[var(--primary)]">{cust.no}</div>}
                          </div>
                        );
                      }
                    },
                    {
                      id: 'items_count',
                      header: 'Items',
                      type: 'qty',
                      align: 'center',
                      sortable: true,
                      accessor: (s) => s.items_count || (s.items ? s.items.length : 1)
                    },
                    {
                      id: 'discount',
                      header: 'Discount',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: (s) => (s.discount !== undefined ? s.discount : (s.items_count > 2 ? 250 : 0))
                    },
                    {
                      id: 'vat',
                      header: 'VAT',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: (s) => (s.vat !== undefined ? s.vat : Math.round(s.total * 0.05))
                    },
                    {
                      id: 'branch',
                      header: 'Branch',
                      sortable: true,
                      accessor: (s) => s.branch || (s.invoice && s.invoice.startsWith('DF2') ? 'Branch 2 [DF2]' : 'Main Branch [DF1]')
                    },
                    {
                      id: 'register',
                      header: 'Register',
                      sortable: true,
                      accessor: (s) => s.register || (s.invoice && s.invoice.includes('R2') ? 'Register 2 [R2]' : 'Register 1 [R1]')
                    },
                    {
                      id: 'total',
                      header: 'Total',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: (s) => s.total,
                      cell: (s) => (
                        <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                          {formatMoney(s.total)}
                        </span>
                      )
                    },
                    {
                      id: 'paid',
                      header: 'Paid',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: (s) => s.paid,
                      cell: (s) => (
                        <span className="font-sans font-semibold tabular-nums num text-[var(--success)]">
                          {formatMoney(s.paid)}
                        </span>
                      )
                    },
                    {
                      id: 'due',
                      header: 'Due',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: (s) => s.due,
                      cell: (s) => (
                        <span className="font-sans font-semibold tabular-nums num text-[var(--danger)]">
                          {s.due > 0 ? formatMoney(s.due) : '—'}
                        </span>
                      )
                    },
                    {
                      id: 'status',
                      header: 'Status',
                      sortable: true,
                      align: 'center',
                      accessor: (s) => s.status,
                      cell: (s) => (
                        <span className={`px-2 py-0.5 rounded-md border text-[10px] font-semibold ${
                          s.status === 'Paid'
                            ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30'
                            : s.status === 'Partial'
                              ? 'bg-[var(--warning-soft)] text-[var(--warning)] border-[var(--warning)]/30'
                              : 'bg-[var(--danger-soft)] text-[var(--danger)] border-[var(--danger)]/30'
                        }`}>
                          {s.status}
                        </span>
                      )
                    },
                    {
                      id: 'cashier',
                      header: 'Cashier',
                      sortable: true,
                      accessor: (s) => s.cashier
                    },
                    {
                      id: 'sync_status',
                      header: 'Sync Status',
                      sortable: true,
                      accessor: (s) => s.sync_status || 'Synced',
                      cell: (s) => (
                        <span className={`text-[10px] font-medium flex items-center gap-1 ${
                          (s.sync_status || '').includes('Flagged')
                            ? 'text-[var(--danger)] font-bold'
                            : 'text-[var(--muted-foreground)]'
                        }`}>
                          {s.sync_status || 'Synced'}
                        </span>
                      )
                    },
                    {
                      id: 'actions',
                      header: 'Actions',
                      align: 'right',
                      hideable: false,
                      cell: (s) => (
                        <div className="flex items-center justify-end gap-1" onClick={(e) => e.stopPropagation()}>
                          <button
                            type="button"
                            onClick={() => {
                              startReturnModeForInvoice(s.invoice);
                              setCurrentScreen('pos');
                            }}
                            className="p-1 rounded hover:bg-[var(--warning-soft)] text-[var(--warning)]"
                            title="Return Items from this Sale"
                          >
                            <Icon name="refresh" size={14} />
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setLastCompletedSale(s);
                              setShowReceiptModal(true);
                            }}
                            className="p-1 rounded hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]"
                            title="Reprint 80mm Receipt"
                          >
                            <Icon name="printer" size={14} />
                          </button>
                        </div>
                      )
                    }
                  ]}
                  filterChips={[
                    {
                      id: 'status',
                      label: 'Status',
                      options: [
                        { label: 'All Statuses', value: 'all' },
                        { label: 'Paid', value: 'Paid' },
                        { label: 'Partial', value: 'Partial' },
                        { label: 'Due', value: 'Due' },
                        { label: 'Void', value: 'Void' }
                      ]
                    }
                  ]}
                  moreFilters={[
                    {
                      id: 'cashier',
                      label: 'Cashier',
                      options: [
                        { label: 'All Cashiers', value: 'all' },
                        { label: 'Rana (Register 1)', value: 'Rana' },
                        { label: 'Kabir (Register 2)', value: 'Kabir' },
                        { label: 'Salma (Uttara)', value: 'Salma' },
                        { label: 'Sumi (Branch 2)', value: 'Sumi' }
                      ]
                    },
                    {
                      id: 'payment_method',
                      label: 'Payment Method',
                      options: [
                        { label: 'All Methods', value: 'all' },
                        { label: 'Cash Only', value: 'Cash' },
                        { label: 'bKash / MFS', value: 'bKash' },
                        { label: 'Visa / MasterCard', value: 'Card' },
                        { label: 'Customer Due Account', value: 'Due' },
                        { label: 'Split Payment', value: 'Split' }
                      ]
                    },
                    {
                      id: 'sync_flag',
                      label: 'Cloud Sync Flag',
                      options: [
                        { label: 'All Sync States', value: 'all' },
                        { label: 'Synced ✓', value: 'Synced' },
                        { label: 'Queued (Pending)', value: 'Queued' },
                        { label: 'Flagged Mismatch ⚠', value: 'Flagged' }
                      ]
                    },
                    {
                      id: 'has_return',
                      label: 'Has Return / Exchange',
                      options: [
                        { label: 'All Invoices', value: 'all' },
                        { label: 'Has Returns / Exchanges', value: 'yes' },
                        { label: 'No Returns', value: 'no' }
                      ]
                    },
                    {
                      id: 'courier',
                      label: 'Courier Dispatch Delivery',
                      options: [
                        { label: 'All Delivery Channels', value: 'all' },
                        { label: 'In-Store POS Sale', value: 'instore' },
                        { label: 'Pathao Parcel', value: 'Pathao Parcel' },
                        { label: 'Steadfast Courier', value: 'Steadfast Courier' },
                        { label: 'RedX Delivery', value: 'RedX' }
                      ]
                    }
                  ]}
                  savedViews={[
                    { id: 'default', label: 'Default (All Sales)' },
                    { id: 'due_unpaid', label: 'Unpaid & Due Invoices', filters: { status: 'Due' } },
                    { id: 'flagged_courier', label: 'Flagged Sync & Courier', filters: { sync_flag: 'Flagged' } }
                  ]}
                  bulkActions={[
                    {
                      label: 'Export Selected',
                      icon: 'fileText',
                      action: (rows) => {
                        exportToCSV('selected_sales_' + Date.now() + '.csv', [
                          { id: 'invoice', header: 'Invoice' },
                          { id: 'date', header: 'Date' },
                          { id: 'customer', header: 'Customer', accessor: s => formatSaleCustomer(s).name },
                          { id: 'total', header: 'Total' },
                          { id: 'paid', header: 'Paid' },
                          { id: 'due', header: 'Due' },
                          { id: 'status', header: 'Status' }
                        ], rows);
                        addToast('Exported ' + rows.length + ' sales to CSV', 'success');
                      }
                    },
                    {
                      label: 'Reprint Receipts Batch',
                      icon: 'printer',
                      action: (rows) => {
                        addToast('Sending ' + rows.length + ' receipts to 80mm thermal printer...', 'info');
                      }
                    },
                    {
                      label: 'Sync Cloud Now',
                      icon: 'refresh',
                      action: (rows) => {
                        addToast('Synced ' + rows.length + ' sales with cloud server!', 'success');
                      }
                    }
                  ]}
                  customFilter={(s, filters) => {
                    if (filters.cashier && filters.cashier !== 'all' && s.cashier !== filters.cashier) return false;
                    if (filters.payment_method && filters.payment_method !== 'all' && (s.payment_method || '').toLowerCase() !== filters.payment_method.toLowerCase()) return false;
                    if (filters.sync_flag && filters.sync_flag !== 'all') {
                      if (!(s.sync_status || '').toLowerCase().includes(filters.sync_flag.toLowerCase())) return false;
                    }
                    if (filters.has_return && filters.has_return !== 'all') {
                      const hasRet = (typeof MOCK_RETURNS !== 'undefined' ? MOCK_RETURNS : []).some(r => r.original_invoice === s.invoice || r.invoice === s.invoice);
                      if (filters.has_return === 'yes' && !hasRet) return false;
                      if (filters.has_return === 'no' && hasRet) return false;
                    }
                    if (filters.courier && filters.courier !== 'all') {
                      if (filters.courier === 'instore') {
                        if (s.courier) return false;
                      } else {
                        if (!s.courier || !s.courier.toLowerCase().includes(filters.courier.toLowerCase())) return false;
                      }
                    }
                    return true;
                  }}
                  onRowClick={(s) => {
                    setSelectedSale(s);
                    setCurrentScreen('sale_detail');
                  }}
                  onRowContextMenu={(s, e) => {
                    openContextMenu(e, s.invoice, [
                      { label: 'View Sale Details', action: () => { setSelectedSale(s); setCurrentScreen('sale_detail'); } },
                      { label: 'Return / Exchange (F11)', shortcut: 'F11', action: () => { startReturnModeForInvoice(s.invoice); setCurrentScreen('pos'); } },
                      { label: 'Collect Due (Ctrl+D)', shortcut: 'Ctrl+D', action: () => openCollectDueModal() },
                      { label: 'Reprint 80mm Receipt', action: () => { setLastCompletedSale(s); setShowReceiptModal(true); } },
                      { label: 'Reprint A4 Invoice', action: () => { setLastCompletedSale(s); setShowA4InvoiceModal(true); } },
                      { label: 'Copy Invoice Number', action: () => { navigator.clipboard ? navigator.clipboard.writeText(s.invoice) : null; addToast('Invoice # copied', 'info'); } }
                    ]);
                  }}
                />
              )}

              {/* ================= SCREEN 3: SALE DETAIL VIEW ================= */}
              {currentScreen === 'sale_detail' && selectedSale && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                    <div className="flex items-center gap-3">
                      <button
                        onClick={() => setCurrentScreen('sales')}
                        className="p-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:bg-[var(--surface-sunken)]"
                        title="Back to Sales List"
                      >
                        <Icon name="arrowLeft" size={16} />
                      </button>
                      <div>
                        <div className="flex items-center gap-2">
                          <h1 className="text-lg font-bold font-mono text-[var(--foreground)]">{selectedSale.invoice}</h1>
                          <span className={`px-2 py-0.5 rounded-md border text-xs font-semibold ${
                            selectedSale.status === 'Paid' ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30' : 'bg-[var(--warning-soft)] text-[var(--warning)] border-[var(--warning)]/30'
                          }`}>
                            {selectedSale.status}
                          </span>
                        </div>
                        <div className="text-xs text-[var(--muted-foreground)]">Created {selectedSale.date || 'Today'} at {selectedSale.time} by {selectedSale.cashier}</div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => {
                          startReturnModeForInvoice(selectedSale.invoice);
                          setCurrentScreen('pos');
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--warning)] text-white hover:opacity-90 text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                      >
                        <Icon name="refresh" size={14} />
                        <span>Return Items (F11)</span>
                      </button>
                      <button
                        onClick={() => { setLastCompletedSale(selectedSale); setShowReceiptModal(true); }}
                        className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5"
                      >
                        <Icon name="printer" size={14} />
                        <span>Print 80mm Receipt</span>
                      </button>
                      <button
                        onClick={() => { setLastCompletedSale(selectedSale); setShowA4InvoiceModal(true); }}
                        className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5"
                      >
                        <Icon name="fileText" size={14} />
                        <span>A4 Tax Invoice</span>
                      </button>
                    </div>
                  </div>

                  {/* Two column detail */}
                  <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
                    {/* Left 2 cols: Items list with per-line discounts */}
                    <div className="lg:col-span-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] p-4 space-y-3">
                      <div className="font-bold text-sm">Purchased Items & Discounts</div>
                      <table className="w-full text-left text-xs">
                        <thead className="border-b border-[var(--border)] text-[var(--muted-foreground)]">
                          <tr>
                            <th className="py-2">Item Description</th>
                            <th className="py-2 text-center">Qty</th>
                            <th className="py-2 text-right">Unit Price</th>
                            <th className="py-2 text-right">Line Discount</th>
                            <th className="py-2 text-right">Line Total</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border)]">
                          {(selectedSale.items || [
                            { product: INITIAL_PRODUCTS[0], qty: 2, original_price: 1450, line_discount: 145 },
                            { product: INITIAL_PRODUCTS[3], qty: 1, original_price: 1890, line_discount: 0 }
                          ]).map((it, idx) => (
                            <tr key={idx}>
                              <td className="py-2.5 font-medium">{it.product?.name_en || 'Cotton Panjabi M'}</td>
                              <td className="py-2.5 text-center font-mono">{it.qty}</td>
                              <td className="py-2.5 text-right num">{formatMoney(it.original_price || 1450)}</td>
                              <td className="py-2.5 text-right num text-[var(--danger)]">
                                {it.line_discount > 0 ? `−${formatMoney(it.line_discount)}` : '—'}
                              </td>
                              <td className="py-2.5 text-right num font-bold">{formatMoney(((it.original_price || 1450) * it.qty) - (it.line_discount || 0))}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>

                    {/* Right 1 col: Financial & Customer & Due */}
                    <div className="space-y-4">
                      <div className="rounded-lg bg-[var(--surface)] border border-[var(--border)] p-4 space-y-2 text-xs">
                        <div className="font-bold text-sm mb-2">Payment Breakdown</div>
                        <div className="flex justify-between text-[var(--muted-foreground)]">
                          <span>Subtotal:</span>
                          <span className="num">{formatMoney(selectedSale.total * 0.87)}</span>
                        </div>
                        <div className="flex justify-between text-[var(--danger)]">
                          <span>Total Discounts:</span>
                          <span className="num">−{formatMoney(selectedSale.totalDiscount || 145)}</span>
                        </div>
                        <div className="flex justify-between text-[var(--muted-foreground)]">
                          <span>VAT (15%):</span>
                          <span className="num">{formatMoney(selectedSale.total * 0.13)}</span>
                        </div>
                        <div className="pt-2 border-t border-[var(--border)] flex justify-between font-bold text-sm">
                          <span>Grand Total:</span>
                          <span className="text-[var(--primary)] num">{formatMoney(selectedSale.total)}</span>
                        </div>
                        <div className="flex justify-between text-[var(--success)] font-medium pt-1">
                          <span>Paid ({selectedSale.paymentMethod || 'Cash'}):</span>
                          <span className="num">{formatMoney(selectedSale.paid)}</span>
                        </div>
                        {selectedSale.due > 0 && (
                          <div className="flex justify-between text-[var(--danger)] font-bold pt-1 border-t border-[var(--border)]">
                            <span>Outstanding Due:</span>
                            <span className="num">{formatMoney(selectedSale.due)}</span>
                          </div>
                        )}
                      </div>

                      <div className="rounded-lg bg-[var(--surface)] border border-[var(--border)] p-4 space-y-2 text-xs">
                        <div className="font-bold text-sm mb-2">Customer Info</div>
                        <div className="flex justify-between">
                          <span className="text-[var(--muted-foreground)]">Customer:</span>
                          <span className="font-semibold">{selectedSale.customer}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-[var(--muted-foreground)]">Customer #:</span>
                          <span className="font-mono">{selectedSale.customer_no || 'Walk-in'}</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* ================= SCREEN 4: DUE COLLECTION LEDGER (G U) ================= */}
              {currentScreen === 'due_collection' && (
                <div className="flex-1 flex flex-col overflow-hidden space-y-3">
                  {/* Aging KPI Cards */}
                  <div className="p-4 pb-0 grid grid-cols-2 lg:grid-cols-4 gap-3 shrink-0">
                    {[
                      { label: '0–30 Days (Current)', amount: 142500, count: 18, color: 'border-l-4 border-[var(--success)]' },
                      { label: '31–60 Days (Aging)', amount: 68400, count: 7, color: 'border-l-4 border-[var(--warning)]' },
                      { label: '61–90 Days (Overdue)', amount: 42100, count: 4, color: 'border-l-4 border-[var(--danger)]' },
                      { label: '90+ Days (Critical)', amount: 28900, count: 3, color: 'border-l-4 border-[var(--danger)]' }
                    ].map((card, i) => (
                      <div key={i} className={`p-3.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] shadow-xs ${card.color}`}>
                        <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">{card.label}</div>
                        <div className="text-lg font-bold text-[var(--foreground)] mt-1 num">{formatMoney(card.amount)}</div>
                        <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">{card.count} debtors</div>
                      </div>
                    ))}
                  </div>

                  {/* Due Debtors DataList */}
                  <div className="flex-1 overflow-hidden flex flex-col">
                    <DataList
                      id="due_collection"
                      title="Due Collection Ledger"
                      subtitle="Active debtors directory with credit limit meter, overdue aging, and settlement ledger"
                      data={MOCK_CUSTOMERS.filter(c => c.balance < 0)}
                      keyField="id"
                      searchPlaceholder="Search customer no (#1000...), name, mobile phone... (/)"
                      searchFields={['customer_no', 'name_en', 'name_bn', 'phone', 'district']}
                      columns={[
                        {
                          id: 'customer_no',
                          header: 'Customer No',
                          sortable: true,
                          className: 'font-mono font-medium text-[var(--primary)]',
                          accessor: c => c.customer_no
                        },
                        {
                          id: 'name',
                          header: 'Name (EN / BN)',
                          sortable: true,
                          accessor: c => c.name_en,
                          cell: c => (
                            <div>
                              <span className="font-semibold text-[var(--foreground)]">{c.name_en}</span>{' '}
                              <span className="font-bangla font-normal text-[var(--muted-foreground)]">({c.name_bn})</span>
                            </div>
                          )
                        },
                        {
                          id: 'phone',
                          header: 'Mobile Phone',
                          sortable: true,
                          className: 'font-mono text-[var(--muted-foreground)]',
                          accessor: c => c.phone
                        },
                        {
                          id: 'credit_limit_meter',
                          header: 'Credit Limit Meter',
                          sortable: false,
                          className: 'w-48',
                          cell: c => {
                            const dueAmt = Math.abs(c.balance);
                            const limit = c.credit_limit || 20000;
                            const pct = Math.min(100, Math.round((dueAmt / limit) * 100));
                            return (
                              <div className="space-y-1">
                                <div className="flex justify-between text-[10px] text-[var(--muted-foreground)] font-mono">
                                  <span>Used {formatMoney(dueAmt)}</span>
                                  <span>Limit {formatMoney(limit)}</span>
                                </div>
                                <div className="h-1.5 w-full bg-[var(--surface-sunken)] rounded-full overflow-hidden">
                                  <div
                                    style={{ width: `${pct}%` }}
                                    className={`h-full ${pct > 80 ? 'bg-[var(--danger)]' : pct > 50 ? 'bg-[var(--warning)]' : 'bg-[var(--success)]'}`}
                                  ></div>
                                </div>
                              </div>
                            );
                          }
                        },
                        {
                          id: 'balance',
                          header: 'Outstanding Due',
                          type: 'money',
                          align: 'right',
                          sortable: true,
                          accessor: c => Math.abs(c.balance),
                          cell: c => (
                            <span className="font-sans font-bold tabular-nums num text-[var(--danger)]">
                              {formatMoney(Math.abs(c.balance))}
                            </span>
                          )
                        },
                        {
                          id: 'last_purchase',
                          header: 'Last Purchase',
                          sortable: true,
                          className: 'text-[var(--muted-foreground)]',
                          accessor: c => c.last_purchase
                        },
                        {
                          id: 'actions',
                          header: 'Actions',
                          align: 'right',
                          hideable: false,
                          cell: c => (
                            <div className="flex items-center justify-end gap-1.5" onClick={e => e.stopPropagation()}>
                              <button
                                type="button"
                                onClick={() => openCollectDueModal(c)}
                                className="h-7 px-2.5 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold hover:bg-[var(--primary-hover)] cursor-pointer"
                              >
                                Collect (Ctrl+D)
                              </button>
                              <button
                                type="button"
                                onClick={() => {
                                  setReminderCustomer(c);
                                  setShowReminderModal(true);
                                }}
                                className="h-7 px-2 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-medium hover:border-[var(--primary)] cursor-pointer"
                                title="Send WhatsApp Reminder"
                              >
                                <Icon name="send" size={12} />
                              </button>
                            </div>
                          )
                        }
                      ]}
                      filterChips={[
                        {
                          id: 'group',
                          label: 'Customer Group',
                          options: [
                            { label: 'All Groups', value: 'all' },
                            { label: 'Retail', value: 'Retail' },
                            { label: 'VIP Wholesale', value: 'VIP Wholesale' },
                            { label: 'Staff', value: 'Staff' }
                          ]
                        }
                      ]}
                      moreFilters={[
                        {
                          id: 'aging',
                          label: 'Aging Bracket',
                          options: [
                            { label: 'All Aging Brackets', value: 'all' },
                            { label: '0–30 Days (Current)', value: '0-30' },
                            { label: '31–60 Days (Aging)', value: '31-60' },
                            { label: '61–90 Days (Overdue)', value: '61-90' },
                            { label: '90+ Days (Critical)', value: '90+' }
                          ]
                        },
                        {
                          id: 'credit_status',
                          label: 'Credit Utilization',
                          options: [
                            { label: 'All Levels', value: 'all' },
                            { label: 'Near Limit (>75%)', value: 'near_limit' },
                            { label: 'Exceeded Limit (>100%)', value: 'over_limit' }
                          ]
                        }
                      ]}
                      savedViews={[
                        { id: 'default', label: 'Default (All Debtors)' },
                        { id: 'critical', label: 'Critical Overdue (90+ Days)', filters: { aging: '90+' } },
                        { id: 'vip', label: 'VIP Wholesale Overdue', filters: { group: 'VIP Wholesale' } }
                      ]}
                      bulkActions={[
                        {
                          label: 'Send Bulk Reminder',
                          icon: 'send',
                          action: (rows) => {
                            addToast('Triggered WhatsApp balance reminder to ' + rows.length + ' debtors', 'success');
                          }
                        },
                        {
                          label: 'Export Debtors',
                          icon: 'fileText',
                          action: (rows) => {
                            exportToCSV('debtors_ledger_' + Date.now() + '.csv', [
                              { id: 'customer_no', header: 'Customer No' },
                              { id: 'name_en', header: 'Name' },
                              { id: 'phone', header: 'Phone' },
                              { id: 'balance', header: 'Due Amount', accessor: c => Math.abs(c.balance) }
                            ], rows);
                          }
                        }
                      ]}
                      headerActions={
                        <button
                          type="button"
                          onClick={() => openCollectDueModal()}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                          <Icon name="banknote" size={14} />
                          <span>Collect Due (Ctrl+D)</span>
                        </button>
                      }
                      onRowClick={(c) => openCollectDueModal(c)}
                      onRowContextMenu={(c, e) => {
                        openContextMenu(e, c.name_en, [
                          { label: 'Collect Outstanding Due (Ctrl+D)', shortcut: 'Ctrl+D', action: () => openCollectDueModal(c) },
                          { label: 'View Customer 360 Profile', action: () => { setSelectedCustomer(c); setCurrentScreen('customer_profile'); } },
                          { label: 'Send WhatsApp Reminder', action: () => { setReminderCustomer(c); setShowReminderModal(true); } }
                        ]);
                      }}
                    />
                  </div>
                </div>
              )}

              {/* ================= SCREEN 5: RETURNS HISTORY & DETAIL (G T) ================= */}
              {currentScreen === 'returns' && (
                <DataList
                  id="returns"
                  title="Returns & Exchanges Ledger"
                  subtitle="Audit log of customer returns, size exchanges, defect write-offs, and refund methods"
                  data={MOCK_RETURNS || []}
                  keyField="id"
                  dateField="date"
                  searchPlaceholder="Search return #, original invoice, customer... (/)"
                  searchFields={['return_no', 'invoice', 'original_invoice', 'customer', 'cashier', 'reason', 'refund_method']}
                  columns={[
                    {
                      id: 'return_no',
                      header: 'Return #',
                      sortable: true,
                      className: 'font-mono font-bold text-[var(--warning)]',
                      accessor: r => r.return_no
                    },
                    {
                      id: 'invoice',
                      header: 'Original Invoice',
                      sortable: true,
                      className: 'font-mono text-[var(--primary)]',
                      accessor: r => r.invoice || r.original_invoice
                    },
                    {
                      id: 'date',
                      header: 'Date & Time',
                      sortable: true,
                      className: 'text-[var(--muted-foreground)]',
                      accessor: r => r.date + ' ' + r.time,
                      cell: r => r.date + ' · ' + r.time
                    },
                    {
                      id: 'customer',
                      header: 'Customer',
                      sortable: true,
                      className: 'font-medium',
                      accessor: r => r.customer
                    },
                    {
                      id: 'items_returned',
                      header: 'Returned Items',
                      sortable: true,
                      className: 'truncate max-w-xs',
                      accessor: r => r.items_returned
                    },
                    {
                      id: 'returned_total',
                      header: 'Return Amount',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: r => r.returned_total,
                      cell: r => (
                        <span className="font-sans font-bold tabular-nums num text-[var(--danger)]">
                          −{formatMoney(r.returned_total)}
                        </span>
                      )
                    },
                    {
                      id: 'net_refund',
                      header: 'Exchange Net',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: r => r.net_refund || r.net_diff || 0,
                      cell: r => (
                        <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                          {r.net_refund ? 'Refund ' + formatMoney(r.net_refund) : (r.net_diff ? formatMoney(r.net_diff) : '৳ 0.00')}
                        </span>
                      )
                    },
                    {
                      id: 'refund_method',
                      header: 'Refund Method',
                      sortable: true,
                      accessor: r => r.refund_method,
                      cell: r => (
                        <span className="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)]">
                          {r.refund_method}
                        </span>
                      )
                    },
                    {
                      id: 'reason',
                      header: 'Reason',
                      sortable: true,
                      accessor: r => r.reason,
                      cell: r => (
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                          r.reason === 'Defect'
                            ? 'bg-[var(--danger-soft)] text-[var(--danger)]'
                            : 'bg-[var(--warning-soft)] text-[var(--warning)]'
                        }`}>
                          {r.reason}
                        </span>
                      )
                    },
                    {
                      id: 'cashier',
                      header: 'Cashier',
                      sortable: true,
                      className: 'text-[var(--muted-foreground)]',
                      accessor: r => r.cashier
                    }
                  ]}
                  filterChips={[
                    {
                      id: 'reason',
                      label: 'Return Reason',
                      options: [
                        { label: 'All Reasons', value: 'all' },
                        { label: 'Size Issue', value: 'Size issue' },
                        { label: 'Defect', value: 'Defect' },
                        { label: 'Changed Mind', value: 'Changed mind' },
                        { label: 'Colour', value: 'Colour' }
                      ]
                    }
                  ]}
                  moreFilters={[
                    {
                      id: 'refund_method',
                      label: 'Refund Method',
                      options: [
                        { label: 'All Methods', value: 'all' },
                        { label: 'Cash Refund', value: 'Cash' },
                        { label: 'bKash MFS', value: 'bKash' },
                        { label: 'Card Refund', value: 'Card' },
                        { label: 'Due Adjustment', value: 'Due Adjustment' },
                        { label: 'Store Credit Voucher', value: 'Store Credit' }
                      ]
                    },
                    {
                      id: 'cashier',
                      label: 'Processed By',
                      options: [
                        { label: 'All Staff', value: 'all' },
                        { label: 'Sumi', value: 'Sumi' },
                        { label: 'Kabir', value: 'Kabir' },
                        { label: 'Rana', value: 'Rana' }
                      ]
                    }
                  ]}
                  savedViews={[
                    { id: 'default', label: 'Default (All Returns)' },
                    { id: 'defect', label: 'Defective Items (Write-off)', filters: { reason: 'Defect' } },
                    { id: 'cash_refunds', label: 'Cash Refunds Settled', filters: { refund_method: 'Cash' } }
                  ]}
                  bulkActions={[
                    {
                      label: 'Export Returns',
                      icon: 'fileText',
                      action: (rows) => {
                        exportToCSV('returns_log_' + Date.now() + '.csv', [
                          { id: 'return_no', header: 'Return No' },
                          { id: 'invoice', header: 'Original Invoice' },
                          { id: 'date', header: 'Date' },
                          { id: 'returned_total', header: 'Return Amount' },
                          { id: 'refund_method', header: 'Method' },
                          { id: 'reason', header: 'Reason' }
                        ], rows);
                        addToast('Exported ' + rows.length + ' return vouchers', 'success');
                      }
                    },
                    {
                      label: 'Print Return Slips',
                      icon: 'printer',
                      action: (rows) => {
                        addToast('Printed ' + rows.length + ' return receipts', 'info');
                      }
                    }
                  ]}
                  headerActions={
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => {
                          setReturnWizardStep(1);
                          setShowReturnWizardModal(true);
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer hover:bg-[var(--primary-hover)]"
                      >
                        <Icon name="plus" size={14} />
                        <span>Return Wizard</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          startReturnModeForInvoice('DF1-R1-26000412');
                          setCurrentScreen('pos');
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--warning)] hover:opacity-90 text-white text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                      >
                        <Icon name="refresh" size={14} />
                        <span>POS Return (F11)</span>
                      </button>
                    </div>
                  }
                  onRowClick={(r) => {
                    setSelectedReturnRecord(r);
                    setCurrentScreen('return_detail');
                  }}
                  onRowContextMenu={(r, e) => {
                    openContextMenu(e, r.return_no, [
                      { label: 'View Return Details', action: () => { setSelectedReturnRecord(r); setCurrentScreen('return_detail'); } },
                      { label: 'View Original Sale Invoice', action: () => {
                        const matched = MOCK_SALES.find(s => s.invoice === (r.invoice || r.original_invoice));
                        if (matched) { setSelectedSale(matched); setCurrentScreen('sale_detail'); }
                        else addToast('Invoice not found', 'warning');
                      }},
                      { label: 'Reprint Return Slip', action: () => addToast('Reprinting return slip...', 'info') },
                      { label: 'Print Defect Ticket', action: () => addToast('Printing warehouse inspection slip...', 'info') }
                    ]);
                  }}
                />
              )}

              {/* ================= SCREEN: RETURN & EXCHANGE DETAIL (return_detail) ================= */}
              {currentScreen === 'return_detail' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  {/* Top Bar with Back Navigation */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[var(--border)] gap-2">
                    <div className="flex items-center gap-2.5">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('returns')}
                        className="h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                      >
                        <Icon name="arrowLeft" size={14} />
                        <span>Back to Returns</span>
                      </button>
                      <div className="h-4 w-px bg-[var(--border)]" />
                      <div>
                        <h1 className="text-base font-bold text-[var(--foreground)] flex items-center gap-2">
                          <span>Return Voucher: {selectedReturnRecord?.return_no || 'RET-260012'}</span>
                          <span className="px-2 py-0.5 rounded-md border border-[var(--warning)]/30 text-[10px] font-semibold bg-[var(--warning-soft)] text-[var(--warning)]">
                            Settled & Restocked
                          </span>
                        </h1>
                        <div className="text-[11px] text-[var(--muted-foreground)]">
                          Original Invoice: <button
                            type="button"
                            onClick={() => {
                              const matched = MOCK_SALES.find(s => s.invoice === (selectedReturnRecord?.invoice || selectedReturnRecord?.original_invoice));
                              if (matched) { setSelectedSale(matched); setCurrentScreen('sale_detail'); }
                              else addToast('Original sale invoice not found', 'warning');
                            }}
                            className="font-mono font-bold text-[var(--primary)] hover:underline"
                          >
                            {selectedReturnRecord?.invoice || selectedReturnRecord?.original_invoice || 'DF1-R1-26000412'}
                          </button> · Date: {selectedReturnRecord?.date || 'Today'} · Processed by: {selectedReturnRecord?.cashier || 'Sumi'}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => addToast("Re-printing return credit note...", "info")}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--foreground)] cursor-pointer"
                      >
                        <Icon name="printer" size={14} />
                        <span>Print Return Slip</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          startReturnModeForInvoice(selectedReturnRecord?.invoice || 'DF1-R1-26000412');
                          setCurrentScreen('pos');
                        }}
                        className="h-8 px-3.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer hover:bg-[var(--primary-hover)]"
                      >
                        <Icon name="refresh" size={14} />
                        <span>Start Replacement Sale</span>
                      </button>
                    </div>
                  </div>

                  {/* Summary Metric Cards */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div className="p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Customer / Member</div>
                      <div className="font-bold text-xs text-[var(--foreground)] mt-1 truncate">
                        {selectedReturnRecord?.customer || 'Rahim Uddin (1000 0017)'}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Uttara Retail Store</div>
                    </div>
                    <div className="p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Gross Return Amount</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--danger)] mt-1">
                        −{formatMoney(selectedReturnRecord?.returned_total || 2900)}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Includes 15% VAT Reversal</div>
                    </div>
                    <div className="p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Refund / Credit Method</div>
                      <div className="font-bold text-xs text-[var(--primary)] mt-1">
                        {selectedReturnRecord?.refund_method || 'Store Credit Note'}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)] font-mono">Ref: CN-260012</div>
                    </div>
                    <div className="p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Restock Condition</div>
                      <div className="font-semibold text-xs text-[var(--success)] mt-1 flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full bg-[var(--success)]"></span>
                        <span>Sellable Condition</span>
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Restocked to Branch 1 shelf</div>
                    </div>
                  </div>

                  {/* Return Lines Table */}
                  <div className="p-4 rounded-lg bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                    <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Returned Item Lines & Restock Inspection</h2>
                    <div className="overflow-x-auto border border-[var(--border)] rounded-lg">
                      <table className="w-full text-left text-xs">
                        <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                          <tr>
                            <th className="py-2.5 px-3">Item Description</th>
                            <th className="py-2.5 px-3">SKU</th>
                            <th className="py-2.5 px-3 text-center">Returned Qty</th>
                            <th className="py-2.5 px-3 text-right">Unit Price</th>
                            <th className="py-2.5 px-3 text-right">Line Disc</th>
                            <th className="py-2.5 px-3">Return Reason</th>
                            <th className="py-2.5 px-3 text-center">Restock Disposition</th>
                            <th className="py-2.5 px-3 text-right">Net Credit (৳)</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border)]">
                          <tr className="hover:bg-[var(--surface-sunken)]/50">
                            <td className="py-2.5 px-3 font-semibold text-[var(--foreground)]">
                              {selectedReturnRecord?.items_returned || 'Semi-fitted Cotton Panjabi (Size 42)'}
                            </td>
                            <td className="py-2.5 px-3 font-mono text-[var(--primary)] font-semibold">DF-PAN-01</td>
                            <td className="py-2.5 px-3 text-center font-mono font-bold">1 pc</td>
                            <td className="py-2.5 px-3 text-right font-sans tabular-nums num">৳ 1,450</td>
                            <td className="py-2.5 px-3 text-right font-sans tabular-nums num text-[var(--muted-foreground)]">৳ 0</td>
                            <td className="py-2.5 px-3 font-medium text-[var(--warning)]">{selectedReturnRecord?.reason || 'Wrong size / Fit issue'}</td>
                            <td className="py-2.5 px-3 text-center">
                              <span className="px-2 py-0.5 rounded-md border border-[var(--success)]/30 text-[10px] font-semibold bg-[var(--success-soft)] text-[var(--success)]">
                                Sellable (Returned to Shelf)
                              </span>
                            </td>
                            <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--danger)]">
                              −৳ 1,450
                            </td>
                          </tr>
                        </tbody>
                        <tfoot className="bg-[var(--surface-sunken)] border-t-2 border-[var(--border)] font-bold">
                          <tr>
                            <td colSpan={7} className="py-2.5 px-3 text-right text-[var(--muted-foreground)]">Net Refund Total:</td>
                            <td className="py-2.5 px-3 text-right font-sans font-bold text-sm text-[var(--danger)]">
                              −{formatMoney(selectedReturnRecord?.returned_total || 1450)}
                            </td>
                          </tr>
                        </tfoot>
                      </table>
                    </div>
                  </div>
                </div>
              )}
"""
