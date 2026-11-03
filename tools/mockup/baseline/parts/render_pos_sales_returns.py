# scripts/parts/render_pos_sales_returns.py

RENDER_POS_SALES_RETURNS_JS = """
              {/* ================= SCREEN 1: POS REGISTER & FAST RETURN/EXCHANGE ================= */}
              {currentScreen === 'pos' && (
                <div className="flex-1 flex flex-col lg:flex-row overflow-hidden">
                  
                  {/* Left Column: Categories + Product Catalog Grid */}
                  <div className="flex-1 flex flex-col border-r border-[var(--border)] overflow-hidden">
                    
                    {/* Top Bar: Barcode Input & Category Pills */}
                    <div className="p-2 border-b border-[var(--border)] bg-[var(--surface)] space-y-2 shrink-0">
                      <div className="flex items-center gap-2">
                        <div className="relative flex-1">
                          <input
                            ref={posScanInputRef}
                            type="text"
                            value={posSearchQuery}
                            onChange={(e) => setPosSearchQuery(e.target.value)}
                            onKeyDown={(e) => {
                              if (e.key === 'Enter') {
                                const q = posSearchQuery.trim();
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
                                } else if (filteredCatalog.length > 0) {
                                  addToCart(filteredCatalog[0]);
                                  setPosSearchQuery('');
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
                            <kbd className="text-[10px]">F2</kbd>
                          </div>
                        </div>

                        {/* Price Check Button */}
                        <button
                          onClick={() => setShowPriceCheckModal(true)}
                          className="h-9 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1 text-[var(--muted-foreground)]"
                          title="Price & Stock Check (F5)"
                        >
                          <Icon name="tag" size={14} />
                          <span className="hidden sm:inline">Price Check</span>
                          <kbd className="text-[10px]">F5</kbd>
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
                          className={`h-9 px-3 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors ${
                            posMode === 'return' 
                              ? 'bg-[var(--warning)] text-white shadow-xs' 
                              : 'bg-[var(--warning-soft)] border border-[var(--warning)]/30 text-[var(--warning)] hover:bg-[var(--warning-soft)]'
                          }`}
                          title="Return & Exchange Mode (F11)"
                        >
                          <Icon name="refresh" size={14} />
                          <span>{posMode === 'return' ? 'Return Mode Active' : 'Return (F11)'}</span>
                        </button>
                      </div>

                      {/* Categories horizontal scroll */}
                      <div className="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs select-none no-scrollbar">
                        {CATEGORIES.map(cat => (
                          <button
                            key={cat.id}
                            onClick={() => setPosCategory(cat.id)}
                            className={`h-7 px-3 rounded-full whitespace-nowrap font-medium transition-colors ${
                              posCategory === cat.id
                                ? 'bg-[var(--primary)] text-[var(--primary-foreground)] shadow-xs'
                                : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                            }`}
                          >
                            {lang === 'bn' ? cat.name_bn : cat.name_en}
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* Products Grid - with auto-rows-max and content-start to prevent Kanban stretching */}
                    <div className="flex-1 overflow-y-auto p-3 grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-2.5 auto-rows-max content-start items-start">
                      {filteredCatalog.filter(p => posCategory === 'all' || p.category === posCategory).map((product) => (
                        <div
                          key={product.id}
                          onClick={() => {
                            const isParentWithVariants = product.has_variants || ['panjabi', 'shirt', 'saree'].includes(product.category);
                            if (isParentWithVariants) {
                              setVariantPickerProduct(product);
                              setShowVariantPickerModal(true);
                            } else {
                              addToCart(product);
                            }
                          }}
                          onContextMenu={(e) => {
                            openContextMenu(e, product.name_en, [
                              { label: 'Product Details (Deep Dive)', action: () => { setSelectedProduct(product); setCurrentScreen('product_detail'); } },
                              { label: 'Select Variants (Size × Colour Grid)', action: () => { setVariantPickerProduct(product); setShowVariantPickerModal(true); } },
                              { label: posMode === 'return' ? 'Add as Exchange Item' : 'Add to Cart', shortcut: 'Enter', action: () => addToCart(product) },
                              { label: 'Check Branch Stock', action: () => { setStockCheckQuery(product.sku); setShowStockCheckModal(true); } },
                              { label: 'Print Barcode Label', action: () => { setSelectedProduct(product); setShowBarcodeLabelModal(true); } }
                            ]);
                          }}
                          className="p-2.5 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] hover:shadow-md cursor-pointer transition-all flex flex-col justify-between group relative select-none h-[122px]"
                        >
                          <div>
                            {/* Product Header with Thumbnail Image */}
                            <div className="flex items-center gap-2 mb-1">
                              <div className="w-9 h-9 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-center shrink-0 overflow-hidden group-hover:border-[var(--primary)] transition-colors">
                                <div className="w-full h-full flex flex-col items-center justify-center bg-[var(--primary-soft)]/20 text-[var(--primary)] font-bold text-[10px]">
                                  <Icon name={product.category === 'saree' ? 'heart' : product.category === 'panjabi' ? 'user' : 'tag'} size={15} />
                                </div>
                              </div>
                              <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between gap-1">
                                  <span className="text-[10px] font-mono font-medium px-1 py-0.2 rounded bg-[var(--surface-sunken)] text-[var(--muted-foreground)] truncate max-w-[65px]">
                                    {product.sku}
                                  </span>
                                  <span className={`text-[9px] font-bold px-1.5 py-0.2 rounded shrink-0 ${
                                    product.stock_main <= 0 
                                      ? 'bg-[var(--danger-soft)] text-[var(--danger)]' 
                                      : product.stock_main <= 5 
                                        ? 'bg-[var(--warning-soft)] text-[var(--warning)]' 
                                        : 'bg-[var(--success-soft)] text-[var(--success)]'
                                  }`}>
                                    {product.stock_main} left
                                  </span>
                                </div>
                                <div className="font-semibold text-xs text-[var(--foreground)] group-hover:text-[var(--primary)] line-clamp-1 leading-tight mt-0.5 truncate">
                                  {lang === 'bn' ? product.name_bn : product.name_en}
                                </div>
                              </div>
                            </div>
                          </div>

                          <div className="mt-1 pt-1.5 border-t border-[var(--border)] flex items-center justify-between">
                            {['panjabi', 'shirt', 'saree'].includes(product.category) ? (
                              <span className="text-[10px] text-[var(--primary)] font-semibold flex items-center gap-1">
                                <Icon name="grid" size={10} />
                                <span>Variants</span>
                              </span>
                            ) : (
                              <span className="text-[10px] text-[var(--muted-foreground)] font-mono">{product.barcode}</span>
                            )}
                            <span className="font-bold text-xs text-[var(--primary)] num">
                              {formatMoney(product.price)}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>

                    {/* POS Function Bar (Exact Hotkeys from Spec) */}
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
                      </button>
                      <button 
                        onClick={() => {
                          if (cart.length > 0) {
                            setDiscountTarget('line');
                            setDiscountTab('percent');
                            setDiscountValue('');
                            setShowManagerPinInline(false);
                            setShowDiscountModal(true);
                          } else {
                            addToast("Add item first", "warning");
                          }
                        }} 
                        className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium"
                      >
                        <Icon name="percent" size={12} />
                        <span>Line Disc</span>
                        <kbd className="text-[9px]">F6</kbd>
                      </button>
                      <button 
                        onClick={() => {
                          setDiscountTarget('order');
                          setDiscountTab('percent');
                          setDiscountValue('');
                          setShowManagerPinInline(false);
                          setShowDiscountModal(true);
                        }} 
                        className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium"
                      >
                        <Icon name="tag" size={12} />
                        <span>Order Disc</span>
                        <kbd className="text-[9px]">⇧F6</kbd>
                      </button>
                      <button 
                        onClick={() => openCollectDueModal()} 
                        className="px-2 py-1 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] flex items-center gap-1 font-medium text-[var(--warning)]"
                      >
                        <Icon name="banknote" size={12} />
                        <span>Collect Due</span>
                        <kbd className="text-[9px]">Ctrl+D</kbd>
                      </button>
                      <button 
                        onClick={() => {
                          if (posMode === 'sale') {
                            startReturnModeForInvoice('DF1-R1-26000412');
                          } else {
                            setPosMode('sale');
                          }
                        }} 
                        className="px-2 py-1 rounded bg-[var(--warning-soft)] border border-[var(--warning)]/30 text-[var(--warning)] hover:bg-[var(--warning-soft)] flex items-center gap-1 font-medium"
                      >
                        <Icon name="refresh" size={12} />
                        <span>Return / Exch</span>
                        <kbd className="text-[9px]">F11</kbd>
                      </button>
                      <button onClick={clearCart} className="px-2 py-1 rounded bg-[var(--danger-soft)] text-[var(--danger)] hover:bg-[var(--danger)] hover:text-white flex items-center gap-1 font-medium">
                        <Icon name="trash" size={12} />
                        <span>Clear</span>
                      </button>
                    </div>
                  </div>

                  {/* Right Column: Customer Card, Cart / Return Table, Totals, Pay Buttons */}
                  <div className="w-full lg:w-[420px] xl:w-[470px] bg-[var(--surface)] flex flex-col shrink-0 overflow-hidden">
                    
                    {/* RETURN MODE AMBER HEADER (Target <15s Return) */}
                    {posMode === 'return' && (
                      <div className="p-2.5 bg-[var(--warning-soft)] border-b border-[var(--warning)]/30 space-y-2">
                        <div className="flex items-center justify-between">
                          <div className="flex items-center gap-2">
                            <span className="w-2.5 h-2.5 rounded-full bg-[var(--warning)] animate-pulse"></span>
                            <div>
                              <div className="font-extrabold text-xs text-[var(--warning)] flex items-center gap-1.5 font-mono">
                                <span>RETURN / EXCHANGE — {returnSourceSale?.invoice || 'NO-RECEIPT'}</span>
                              </div>
                              <span className="text-[10px] font-bold text-[var(--success)] bg-[var(--success-soft)] px-1.5 py-0.2 rounded">
                                {returnPolicyStatus}
                              </span>
                            </div>
                          </div>

                          <button
                            onClick={() => { setPosMode('sale'); addToast("Exited return mode", "info"); }}
                            className="h-6 px-2 rounded bg-[var(--warning)] text-white font-semibold text-[10px] flex items-center gap-1 hover:opacity-90 shadow-xs"
                          >
                            <span>Exit Return (Esc)</span>
                          </button>
                        </div>

                        {/* Quick Invoice Selector Chips */}
                        <div className="flex items-center gap-1.5 pt-1 border-t border-[var(--warning)]/20 text-[10px]">
                          <span className="text-[var(--warning)] font-semibold shrink-0">Invoices:</span>
                          <div className="flex items-center gap-1 overflow-x-auto no-scrollbar">
                            {['DF1-R1-26000412', 'DF1-R1-26000411', 'DF1-R1-26000410'].map(inv => (
                              <button
                                key={inv}
                                onClick={() => startReturnModeForInvoice(inv)}
                                className={`px-2 py-0.5 rounded font-mono font-medium transition-colors ${
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
                    )}

                    {/* Attached Customer Chip */}
                    <div className="p-2.5 border-b border-[var(--border)] bg-[var(--surface-sunken)] flex items-center justify-between">
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
                          className="flex items-center gap-2 cursor-pointer hover:opacity-80 transition-opacity flex-1 min-w-0"
                        >
                          <div className="w-8 h-8 rounded-full bg-[var(--primary-soft)] text-[var(--primary)] flex items-center justify-center font-bold text-xs shrink-0">
                            {attachedCustomer.name_en[0]}
                          </div>
                          <div className="truncate flex-1">
                            <div className="flex items-center gap-1.5 font-bold text-xs">
                              <span className="truncate">{attachedCustomer.name_en}</span>
                              <span className="text-[10px] font-mono px-1 rounded bg-[var(--surface)] border border-[var(--border)]">{attachedCustomer.customer_no}</span>
                            </div>
                            <div className="text-[10px] text-[var(--muted-foreground)] flex items-center gap-1.5 mt-0.5 truncate">
                              <span>{attachedCustomer.phone}</span>
                              <span>·</span>
                              <span className={attachedCustomer.balance < 0 ? 'text-[var(--danger)] font-semibold' : 'text-[var(--success)] font-semibold'}>
                                Due: {formatMoney(Math.abs(attachedCustomer.balance))}
                              </span>
                              {customerGroupDiscountPercent > 0 && !isGroupDiscountRemoved && (
                                <span className="px-1 rounded bg-[var(--primary-soft)] text-[var(--primary)] font-bold">
                                  {attachedCustomer.group} −{customerGroupDiscountPercent}%
                                </span>
                              )}
                            </div>
                          </div>
                        </div>
                      ) : (
                        <button 
                          onClick={() => setShowCustomerAttachModal(true)}
                          className="w-full h-8 rounded-lg border border-dashed border-[var(--border)] hover:border-[var(--primary)] text-xs text-[var(--muted-foreground)] flex items-center justify-center gap-1.5"
                        >
                          <Icon name="user" size={14} />
                          <span>Attach Customer / Member (F4)</span>
                        </button>
                      )}

                      {attachedCustomer && (
                        <div className="flex items-center gap-1 ml-2 shrink-0">
                          <button
                            onClick={() => openCollectDueModal(attachedCustomer)}
                            className="h-7 px-2 rounded bg-[var(--warning)]/10 border border-[var(--warning)]/30 text-[var(--warning)] hover:bg-[var(--warning)]/20 text-[10px] font-bold flex items-center gap-1"
                            title="Collect Due (Ctrl+D)"
                          >
                            <Icon name="banknote" size={12} />
                            <span>Collect</span>
                          </button>
                          <button
                            onClick={() => { setPinDigits(''); setPinPadState('input'); setShowCustomerPinPadModal(true); }}
                            className="h-7 px-2 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-[10px] font-medium"
                            title="Verify PIN"
                          >
                            PIN
                          </button>
                          <button
                            onClick={() => setAttachedCustomer(null)}
                            className="p-1 rounded hover:bg-[var(--surface)] text-[var(--muted-foreground)]"
                            title="Detach"
                          >
                            <Icon name="x" size={14} />
                          </button>
                        </div>
                      )}
                    </div>

                    {/* ================= REGISTER ITEMS LIST ================= */}
                    <div className="flex-1 overflow-y-auto divide-y divide-[var(--border)]">
                      {/* RETURN MODE ACTIVE: SHOW ORIGINAL INVOICE ITEMS WITH CHECKBOXES & REASONS */}
                      {posMode === 'return' ? (
                        <div className="p-2 space-y-3">
                          <div className="flex justify-between items-center text-xs font-bold text-[var(--warning)] pb-1 border-b border-[var(--border)]">
                            <span>Select Items to Return:</span>
                            <span className="text-[10px] text-[var(--muted-foreground)] font-normal">Space toggles item</span>
                          </div>

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
                              className={`p-2.5 rounded-xl border transition-all cursor-pointer space-y-2 ${
                                line.selected 
                                  ? 'bg-[var(--warning)]/10 border-[var(--warning)]/40 shadow-xs' 
                                  : 'bg-[var(--surface-sunken)] border-[var(--border)] opacity-60'
                              }`}
                            >
                              <div className="flex items-start justify-between gap-2">
                                <div className="flex items-center gap-2">
                                  <input
                                    type="checkbox"
                                    checked={line.selected}
                                    onChange={() => {}}
                                    className="rounded text-[var(--warning)]"
                                  />
                                  <div>
                                    <div className="font-semibold text-xs text-[var(--foreground)]">{line.product?.name_en}</div>
                                    <div className="text-[10px] text-[var(--muted-foreground)]">
                                      Sold: {line.original_qty} · Prev returned: {line.returned_qty} · Returnable: <strong className="text-[var(--foreground)]">{line.returnable_qty}</strong>
                                    </div>
                                  </div>
                                </div>

                                <div className="text-right">
                                  <div className="font-bold text-xs num">{formatMoney(line.original_unit_price * line.return_qty)}</div>
                                  <div className="text-[9px] text-[var(--muted-foreground)]">@ {formatMoney(line.original_unit_price)}</div>
                                </div>
                              </div>

                              {/* Stepper & Reason Chips */}
                              {line.selected && (
                                <div className="pt-2 border-t border-[var(--warning)]/20 space-y-2" onClick={(e) => e.stopPropagation()}>
                                  <div className="flex items-center justify-between text-xs">
                                    <span className="text-[10px] font-semibold text-[var(--muted-foreground)]">Return Qty:</span>
                                    <div className="flex items-center gap-1">
                                      <button
                                        onClick={() => setReturnLines(prev => {
                                          const next = [...prev];
                                          next[idx].return_qty = Math.max(1, next[idx].return_qty - 1);
                                          return next;
                                        })}
                                        className="w-6 h-6 rounded bg-[var(--surface)] border flex items-center justify-center font-bold"
                                      >−</button>
                                      <span className="w-6 text-center font-mono font-bold">{line.return_qty}</span>
                                      <button
                                        onClick={() => setReturnLines(prev => {
                                          const next = [...prev];
                                          next[idx].return_qty = Math.min(next[idx].returnable_qty, next[idx].return_qty + 1);
                                          return next;
                                        })}
                                        className="w-6 h-6 rounded bg-[var(--surface)] border flex items-center justify-center font-bold"
                                      >+</button>
                                    </div>
                                  </div>

                                  {/* Quick chips for reason */}
                                  <div>
                                    <div className="text-[10px] font-semibold text-[var(--muted-foreground)] mb-1">Reason:</div>
                                    <div className="flex flex-wrap gap-1">
                                      {['Size issue', 'Colour', 'Defect', 'Changed mind', 'Other'].map(r => (
                                        <button
                                          key={r}
                                          onClick={() => setReturnLines(prev => {
                                            const next = [...prev];
                                            next[idx].reason = r;
                                            next[idx].restock = r === 'Defect' ? 'Damaged' : 'Sellable';
                                            return next;
                                          })}
                                          className={`px-2 py-0.5 rounded text-[10px] font-medium transition-colors ${
                                            line.reason === r 
                                              ? 'bg-[var(--warning)] text-white font-bold' 
                                              : 'bg-[var(--surface)] border border-[var(--border)] text-[var(--muted-foreground)]'
                                          }`}
                                        >
                                          {r}
                                        </button>
                                      ))}
                                    </div>
                                  </div>

                                  <div className="text-[10px] flex justify-between text-[var(--muted-foreground)] pt-0.5">
                                    <span>Inventory Restock:</span>
                                    <span className={line.restock === 'Damaged' ? 'text-[var(--danger)] font-bold' : 'text-[var(--success)] font-bold'}>
                                      {line.restock === 'Damaged' ? 'Damaged (Write-off)' : 'Sellable (Restock to Store)'}
                                    </span>
                                  </div>
                                </div>
                              )}
                            </div>
                          ))}

                          {/* Scanned Exchange Items (New) */}
                          <div className="pt-3 border-t border-[var(--border)] space-y-2">
                            <div className="flex justify-between items-center text-xs font-bold text-[var(--primary)]">
                              <span>New Exchange Items:</span>
                              <span className="text-[10px] text-[var(--muted-foreground)]">Scan to add exchange products</span>
                            </div>

                            {exchangeCart.length === 0 ? (
                              <div className="p-3 rounded-lg border border-dashed border-[var(--border)] text-center text-[11px] text-[var(--muted-foreground)]">
                                Scan or click any product from catalogue to exchange in this bill.
                              </div>
                            ) : (
                              exchangeCart.map((exItem, exIdx) => (
                                <div key={exIdx} className="p-2 rounded-lg bg-[var(--surface-sunken)] flex items-center justify-between text-xs">
                                  <div className="truncate flex-1">
                                    <div className="font-semibold text-[var(--foreground)] truncate">{exItem.name_en}</div>
                                    <div className="text-[10px] text-[var(--muted-foreground)]">{exItem.qty} × {formatMoney(exItem.price)}</div>
                                  </div>
                                  <div className="font-bold num">{formatMoney(exItem.price * exItem.qty)}</div>
                                </div>
                              ))
                            )}
                          </div>
                        </div>
                      ) : (
                        /* NORMAL SALE MODE: CART ITEMS WITH FULL DISCOUNT VISIBILITY */
                        cart.length === 0 ? (
                          <div className="h-full flex flex-col items-center justify-center p-6 text-center text-[var(--muted-foreground)]">
                            <div className="w-12 h-12 rounded-full bg-[var(--surface-sunken)] flex items-center justify-center mb-2">
                              <Icon name="shoppingCart" size={24} />
                            </div>
                            <div className="font-semibold text-xs text-[var(--foreground)]">Cart is empty</div>
                            <p className="text-[11px] mt-1 max-w-[200px]">Scan a barcode or click products on the left to start billing.</p>
                          </div>
                        ) : (
                          cart.map((item, idx) => (
                            <div
                              key={idx}
                              onContextMenu={(e) => {
                                openContextMenu(e, item.product.name_en, [
                                  { label: 'Apply Line Discount (F6)', shortcut: 'F6', action: () => { setSelectedCartLineIndex(idx); setDiscountTarget('line'); setShowDiscountModal(true); } },
                                  { label: 'Change Quantity (+)', action: () => updateCartQty(idx, 1) },
                                  { label: 'Change Quantity (-)', action: () => updateCartQty(idx, -1) },
                                  { label: 'Remove Item', shortcut: 'Del', action: () => removeCartItem(idx), destructive: true }
                                ]);
                              }}
                              className={`p-2.5 flex items-center justify-between gap-2 hover:bg-[var(--surface-sunken)] transition-colors select-none ${selectedCartLineIndex === idx ? 'bg-[var(--primary-soft)]/10' : ''}`}
                              onClick={() => setSelectedCartLineIndex(idx)}
                            >
                              <div className="flex-1 min-w-0">
                                <div className="font-semibold text-xs text-[var(--foreground)] truncate">
                                  {lang === 'bn' ? item.product.name_bn : item.product.name_en}
                                </div>
                                <div className="flex items-center gap-2 text-[10px] text-[var(--muted-foreground)] mt-0.5">
                                  {/* PRICE WITH VISIBLE STRIKE-THROUGH IF DISCOUNTED */}
                                  {item.line_discount > 0 ? (
                                    <div className="flex items-center gap-1.5 cursor-pointer hover:underline" onClick={() => { setSelectedCartLineIndex(idx); setDiscountTarget('line'); setShowDiscountModal(true); }}>
                                      <span className="line-through opacity-60 num">{formatMoney(item.original_price)}</span>
                                      <span className="font-bold text-[var(--foreground)] num">{formatMoney(item.original_price - item.line_discount)}</span>
                                      <span className="px-1 py-0.2 rounded bg-[var(--danger-soft)] text-[var(--danger)] font-bold text-[9px]">
                                        −{item.discount_percent ? `${item.discount_percent}%` : formatMoney(item.line_discount)}
                                      </span>
                                    </div>
                                  ) : (
                                    <span 
                                      className="num cursor-pointer hover:text-[var(--primary)] hover:underline"
                                      onClick={() => { setSelectedCartLineIndex(idx); setDiscountTarget('line'); setShowDiscountModal(true); }}
                                      title="Click to apply Line Discount (F6)"
                                    >
                                      {formatMoney(item.original_price)}
                                    </span>
                                  )}
                                  <span>·</span>
                                  {/* Direct Line Discount Button */}
                                  <button
                                    onClick={(e) => {
                                      e.stopPropagation();
                                      setSelectedCartLineIndex(idx);
                                      setDiscountTarget('line');
                                      setShowDiscountModal(true);
                                    }}
                                    className="px-1.5 py-0.5 rounded text-[10px] bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] border border-[var(--border)] hover:border-[var(--primary)] text-[var(--primary)] font-bold flex items-center gap-1 transition-colors"
                                    title="Line Discount (F6)"
                                  >
                                    <Icon name="percent" size={10} />
                                    <span>{item.line_discount > 0 ? 'Edit %' : '% Disc'}</span>
                                  </button>
                                  <span>·</span>
                                  <span>Tag: {item.salesman}</span>
                                </div>
                              </div>

                              {/* Qty Stepper with direct numeric edit (F3) */}
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
                              </div>

                              {/* Line Total */}
                              <div className="text-right w-20 shrink-0">
                                <div className="font-bold text-xs text-[var(--foreground)] num">
                                  {formatMoney((item.original_price - (item.line_discount || 0)) * item.qty)}
                                </div>
                              </div>

                              {/* Remove button */}
                              <button
                                onClick={(e) => { e.stopPropagation(); removeCartItem(idx); }}
                                className="p-1 rounded text-[var(--muted-foreground)] hover:text-[var(--danger)]"
                                title="Remove item"
                              >
                                <Icon name="x" size={14} />
                              </button>
                            </div>
                          ))
                        )
                      )}
                    </div>

                    {/* ================= TOTALS & SUMMARY ================= */}
                    {posMode === 'return' ? (
                      /* RETURN / EXCHANGE TOTALS FOOTER */
                      <div className="p-3 border-t border-[var(--border)] bg-[var(--warning-soft)] space-y-2 text-xs">
                        <div className="flex justify-between text-[var(--danger)] font-medium">
                          <span>Return Total (VAT Reversal Included):</span>
                          <span className="font-bold num">−{formatMoney(returnTotalGross)}</span>
                        </div>
                        {exchangeTotalGross > 0 && (
                          <div className="flex justify-between text-[var(--foreground)] font-medium">
                            <span>New Exchange Total (with 15% VAT):</span>
                            <span className="font-bold num">+{formatMoney(exchangeTotalGross)}</span>
                          </div>
                        )}
                        <div className="pt-2 border-t border-[var(--warning)]/30 flex items-baseline justify-between font-bold text-sm">
                          <span>{netExchangeDiff >= 0 ? 'Customer Pays Net:' : 'Store Refund to Customer:'}</span>
                          <span className={`text-xl num ${netExchangeDiff >= 0 ? 'text-[var(--primary)]' : 'text-[var(--warning)]'}`}>
                            {formatMoney(Math.abs(netExchangeDiff))}
                          </span>
                        </div>

                        {/* Refund Tender Method Dropdown */}
                        <div className="pt-1 flex items-center justify-between text-xs">
                          <span className="font-semibold text-[var(--muted-foreground)]">Refund Method:</span>
                          <select
                            value={returnRefundMethod}
                            onChange={(e) => setReturnRefundMethod(e.target.value)}
                            className="h-7 px-2 rounded bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold"
                          >
                            <option>Cash</option>
                            <option>Original method (bKash)</option>
                            <option>Adjust Customer Due</option>
                            <option>Store Credit</option>
                          </select>
                        </div>

                        <button
                          onClick={completeReturnExchange}
                          className="w-full h-12 rounded-xl bg-[var(--warning)] hover:bg-[var(--warning)] text-white font-bold text-xs shadow-md flex items-center justify-center gap-2 mt-1"
                        >
                          <Icon name="check" size={16} />
                          <span>Complete Return / Exchange (F10)</span>
                        </button>
                      </div>
                    ) : (
                      /* NORMAL SALE TOTALS: SUB -> LINE DISC -> GROUP DISC -> ORDER DISC -> VAT 15% -> TOTAL */
                      <div className="p-3 border-t border-[var(--border)] bg-[var(--surface-sunken)] space-y-1.5 shrink-0 text-xs">
                        <div className="flex justify-between text-[var(--muted-foreground)]">
                          <span>Subtotal ({cart.reduce((a, b) => a + b.qty, 0)} items):</span>
                          <span className="num">{formatMoney(subtotal)}</span>
                        </div>

                        {/* Line Discounts Row */}
                        {totalLineDiscounts > 0 && (
                          <div className="flex justify-between text-[var(--danger)]">
                            <span>Line Item Discounts:</span>
                            <span className="num">−{formatMoney(totalLineDiscounts)}</span>
                          </div>
                        )}

                        {/* Group Discount Row (Automatic) */}
                        {groupDiscountAmount > 0 && (
                          <div className="flex items-center justify-between text-[var(--primary)]">
                            <span className="flex items-center gap-1.5">
                              <span>Group Discount ({attachedCustomer?.group} {customerGroupDiscountPercent}%):</span>
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
                                className="w-4 h-4 rounded-full bg-[var(--primary-soft)] hover:bg-[var(--primary)] hover:text-white flex items-center justify-center text-[10px]"
                                title="Remove Group Discount"
                              >×</button>
                            </span>
                            <span className="num">−{formatMoney(groupDiscountAmount)}</span>
                          </div>
                        )}

                        {/* Order Discount Row with Dedicated Add / Edit button */}
                        <div className="flex items-center justify-between py-1 px-2 rounded-lg bg-[var(--surface)] border border-dashed border-[var(--border)]">
                          <span className="font-semibold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                            <Icon name="tag" size={12} className="text-[var(--primary)]" />
                            <span>Order Discount:</span>
                          </span>
                          {orderDiscountAmount > 0 ? (
                            <div className="flex items-center gap-1.5">
                              <span className="font-bold text-[var(--danger)] num">
                                −{formatMoney(orderDiscountAmount)} {orderDiscountType === 'percent' ? `(${orderDiscountPercent}%)` : ''}
                              </span>
                              <button
                                onClick={() => { setOrderDiscountAmount(0); setOrderDiscountPercent(0); }}
                                className="text-[10px] text-[var(--danger)] hover:underline font-bold"
                                title="Remove order discount"
                              >
                                × Remove
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
                              className="h-6 px-2.5 rounded bg-[var(--primary-soft)] hover:bg-[var(--primary)] hover:text-white text-[var(--primary)] font-bold text-[11px] flex items-center gap-1 transition-colors"
                            >
                              <Icon name="percent" size={11} />
                              <span>+ Add Order Disc (⇧F6)</span>
                            </button>
                          )}
                        </div>

                        {/* VAT 15% Row (Calculated strictly after all discounts) */}
                        <div className="flex justify-between text-[var(--muted-foreground)]">
                          <span>VAT (15% Standard after discount):</span>
                          <span className="num">{formatMoney(vatAmount)}</span>
                        </div>

                        {/* Grand Total Row */}
                        <div className="pt-2 border-t border-[var(--border)] flex items-baseline justify-between">
                          <span className="font-bold text-sm text-[var(--foreground)]">Total Payable:</span>
                          <span className="font-extrabold text-2xl xl:text-3xl text-[var(--primary)] num tracking-tight">
                            {formatMoney(totalPayable)}
                          </span>
                        </div>
                      </div>
                    )}

                    {/* Pay Buttons (Sale Mode) */}
                    {posMode === 'sale' && (
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
                            <div className="text-base font-bold num">{formatMoney(totalPayable)}</div>
                            <div className="text-[10px] opacity-80">{cart.reduce((s, i) => s + i.qty, 0)} items</div>
                          </div>
                        </button>
                      </div>
                    )}

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
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                          s.status === 'Paid'
                            ? 'bg-[var(--success-soft)] text-[var(--success)]'
                            : s.status === 'Partial'
                              ? 'bg-[var(--warning-soft)] text-[var(--warning)]'
                              : 'bg-[var(--danger-soft)] text-[var(--danger)]'
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
                          <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                            selectedSale.status === 'Paid' ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--warning-soft)] text-[var(--warning)]'
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
                    <div className="lg:col-span-2 rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 space-y-3">
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
                      <div className="rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 space-y-2 text-xs">
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

                      <div className="rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 space-y-2 text-xs">
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
                      <div key={i} className={`p-3.5 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs ${card.color}`}>
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
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)]">
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
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--warning-soft)] text-[var(--warning)]">
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
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Customer / Member</div>
                      <div className="font-bold text-xs text-[var(--foreground)] mt-1 truncate">
                        {selectedReturnRecord?.customer || 'Rahim Uddin (1000 0017)'}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Uttara Retail Store</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Gross Return Amount</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--danger)] mt-1">
                        −{formatMoney(selectedReturnRecord?.returned_total || 2900)}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Includes 15% VAT Reversal</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Refund / Credit Method</div>
                      <div className="font-bold text-xs text-[var(--primary)] mt-1">
                        {selectedReturnRecord?.refund_method || 'Store Credit Note'}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)] font-mono">Ref: CN-260012</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Restock Condition</div>
                      <div className="font-semibold text-xs text-[var(--success)] mt-1 flex items-center gap-1">
                        <span className="w-2 h-2 rounded-full bg-[var(--success)]"></span>
                        <span>Sellable Condition</span>
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Restocked to Branch 1 shelf</div>
                    </div>
                  </div>

                  {/* Return Lines Table */}
                  <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                    <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Returned Item Lines & Restock Inspection</h2>
                    <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
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
                              <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--success-soft)] text-[var(--success)]">
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
