# scripts/parts/render_catalog_stock_purchases.py

RENDER_CATALOG_STOCK_PURCHASES_JS = """
              {/* ================= SCREEN 11: PRODUCT CATALOG & CATEGORIES & BARCODES (G I) ================= */}
              {currentScreen === 'products' && (
                <div className="flex-1 flex flex-col overflow-hidden p-4 space-y-3">
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                      <h1 className="text-lg font-bold text-[var(--foreground)]">Product Catalog & Inventory Hub</h1>
                      <div className="text-xs text-[var(--muted-foreground)]">Manage apparel catalog, category taxonomies, and bulk barcode label printing</div>
                    </div>
                    <div className="flex items-center gap-2">
                      {catalogSubTab === 'categories' ? (
                        <button 
                          onClick={() => setShowAddCategoryModal(true)}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                        >
                          <Icon name="plus" size={14} />
                          <span>Add New Category</span>
                        </button>
                      ) : catalogSubTab === 'barcodes' ? (
                        <button 
                          onClick={() => {
                            const area = document.getElementById('printable-barcode-batch-area');
                            if (area) {
                              area.classList.add('printable-area');
                              window.print();
                              area.classList.remove('printable-area');
                            } else {
                              window.print();
                            }
                            addToast("Sending barcode stickers to thermal printer...", "success");
                          }}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                        >
                          <Icon name="printer" size={14} />
                          <span>Print Selected Barcodes</span>
                        </button>
                      ) : (
                        <button 
                          onClick={() => {
                            openNewProductForm();
                          }}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                        >
                          <Icon name="plus" size={14} />
                          <span>Add Product</span>
                        </button>
                      )}
                    </div>
                  </div>

                  {/* Sub-Tabs: Products & SKUs · Categories · Bulk Barcodes */}
                  <div className="flex items-center gap-1 bg-[var(--surface-sunken)] p-1 rounded-xl border border-[var(--border)] text-xs font-semibold w-fit">
                    <button
                      onClick={() => setCatalogSubTab('products')}
                      className={`h-7 px-3 rounded-lg transition-colors flex items-center gap-1.5 ${
                        catalogSubTab === 'products' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                      }`}
                    >
                      <Icon name="tag" size={13} />
                      <span>Products & SKUs</span>
                    </button>
                    <button
                      onClick={() => setCatalogSubTab('categories')}
                      className={`h-7 px-3 rounded-lg transition-colors flex items-center gap-1.5 ${
                        catalogSubTab === 'categories' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                      }`}
                    >
                      <Icon name="layers" size={13} />
                      <span>Category Management</span>
                    </button>
                    <button
                      onClick={() => setCatalogSubTab('barcodes')}
                      className={`h-7 px-3 rounded-lg transition-colors flex items-center gap-1.5 ${
                        catalogSubTab === 'barcodes' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                      }`}
                    >
                      <Icon name="barcode" size={13} />
                      <span>Bulk Barcodes Generator</span>
                    </button>
                  </div>

                  {/* SUB-TAB 1: PRODUCTS & SKUS */}
                  {catalogSubTab === 'products' && (
                    <div className="flex-1 overflow-hidden flex flex-col">
                      <DataList
                        id="products"
                        title="Apparel Products & SKUs"
                        subtitle="Master item catalog with variant barcodes, retail prices, and multi-branch stock levels"
                        data={INITIAL_PRODUCTS}
                        keyField="id"
                        searchPlaceholder="Search product name, SKU, or barcode... (/)"
                        searchFields={['sku', 'barcode', 'name_en', 'name_bn', 'category']}
                        columns={[
                          {
                            id: 'image',
                            header: 'Item',
                            sortable: false,
                            width: '52px',
                            className: 'w-[52px] text-center',
                            cell: p => (
                              <div className="w-9 h-9 rounded-md border border-[var(--border)] overflow-hidden bg-[var(--surface-sunken)] flex items-center justify-center shrink-0 shadow-2xs group-hover:border-[var(--primary)] transition-colors">
                                {p.image ? (
                                  <img
                                    src={p.image}
                                    alt={p.name_en}
                                    className="w-full h-full object-cover object-top"
                                    loading="lazy"
                                    onError={(e) => {
                                      e.currentTarget.style.display = 'none';
                                      if (e.currentTarget.nextSibling) e.currentTarget.nextSibling.style.display = 'flex';
                                    }}
                                  />
                                ) : null}
                                <div className={`w-full h-full flex items-center justify-center text-[var(--muted-foreground)] ${p.image ? 'hidden' : ''}`}>
                                  <Icon name={p.category === 'saree' ? 'saree' : p.category === 'panjabi' ? 'panjabi' : p.category === 'shirt' ? 'shirt' : 'tag'} size={15} />
                                </div>
                              </div>
                            )
                          },
                          {
                            id: 'sku',
                            header: 'SKU',
                            sortable: true,
                            className: 'font-mono font-semibold text-[var(--primary)]',
                            accessor: p => p.sku,
                            cell: p => (
                              <span className="font-mono font-semibold text-[var(--primary)] group-hover:underline">
                                {p.sku}
                              </span>
                            )
                          },
                          {
                            id: 'barcode',
                            header: 'Barcode',
                            sortable: true,
                            className: 'font-mono text-[var(--muted-foreground)]',
                            accessor: p => p.barcode
                          },
                          {
                            id: 'name_en',
                            header: 'Product Name',
                            sortable: true,
                            accessor: p => p.name_en,
                            cell: p => (
                              <div>
                                <div className="font-semibold text-[var(--foreground)]">{p.name_en}</div>
                                <div className="text-[10px] text-[var(--muted-foreground)] font-bangla">{p.name_bn}</div>
                              </div>
                            )
                          },
                          {
                            id: 'category',
                            header: 'Category',
                            sortable: true,
                            className: 'capitalize text-[var(--muted-foreground)]',
                            accessor: p => p.category
                          },
                          {
                            id: 'cost',
                            header: 'Cost Price',
                            type: 'money',
                            align: 'right',
                            sortable: true,
                            accessor: p => p.cost,
                            cell: p => (
                              <span className="font-sans font-medium tabular-nums num text-[var(--muted-foreground)]">
                                {formatMoney(p.cost)}
                              </span>
                            )
                          },
                          {
                            id: 'price',
                            header: 'Retail Price',
                            type: 'money',
                            align: 'right',
                            sortable: true,
                            accessor: p => p.price,
                            cell: p => (
                              <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                                {formatMoney(p.price)}
                              </span>
                            )
                          },
                          {
                            id: 'stock_main',
                            header: 'Stock (Main)',
                            type: 'qty',
                            align: 'center',
                            sortable: true,
                            accessor: p => p.stock_main,
                            cell: p => (
                              <span className={`font-mono font-bold num ${p.stock_main <= 3 ? 'text-[var(--danger)]' : 'text-[var(--foreground)]'}`}>
                                {p.stock_main}
                              </span>
                            )
                          },
                          {
                            id: 'stock_b2',
                            header: 'Stock (B2)',
                            type: 'qty',
                            align: 'center',
                            sortable: true,
                            accessor: p => p.stock_b2,
                            cell: p => (
                              <span className="font-mono text-[var(--muted-foreground)] num">
                                {p.stock_b2}
                              </span>
                            )
                          },
                          {
                            id: 'stock_total',
                            header: 'Total Stock',
                            type: 'qty',
                            align: 'center',
                            sortable: true,
                            accessor: p => p.stock_main + p.stock_b2,
                            cell: p => (
                              <span className="font-mono font-bold text-[var(--primary)] num">
                                {p.stock_main + p.stock_b2}
                              </span>
                            )
                          },
                          {
                            id: 'status',
                            header: 'Status',
                            sortable: true,
                            align: 'center',
                            accessor: p => (p.stock_main <= 0 ? 'Out of Stock' : p.stock_main <= 5 ? 'Low Stock' : 'In Stock'),
                            cell: p => {
                              const st = p.stock_main <= 0 ? 'Out of Stock' : p.stock_main <= 5 ? 'Low Stock' : 'In Stock';
                              return (
                                <span className={`px-2 py-0.5 rounded-md border text-[10px] font-semibold ${
                                  st === 'Out of Stock'
                                    ? 'bg-[var(--danger-soft)] text-[var(--danger)] border-[var(--danger)]/30'
                                    : st === 'Low Stock'
                                      ? 'bg-[var(--warning-soft)] text-[var(--warning)] border-[var(--warning)]/30'
                                      : 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30'
                                }`}>
                                  {st}
                                </span>
                              );
                            }
                          },
                          {
                            id: 'actions',
                            header: 'Actions',
                            align: 'right',
                            hideable: false,
                            cell: p => (
                              <div className="flex items-center justify-end gap-1" onClick={e => e.stopPropagation()}>
                                <button
                                  type="button"
                                  onClick={() => {
                                    setSelectedProduct(p);
                                    setShowBarcodeLabelModal(true);
                                  }}
                                  className="p-1 rounded hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--primary)] cursor-pointer"
                                  title="Print Barcode Labels"
                                >
                                  <Icon name="barcode" size={14} />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => openEditProductForm(p)}
                                  className="p-1 rounded hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--foreground)] cursor-pointer"
                                  title="Edit Product"
                                >
                                  <Icon name="edit" size={14} />
                                </button>
                              </div>
                            )
                          }
                        ]}
                        filterChips={[
                          {
                            id: 'category',
                            label: 'Category',
                            options: [
                              { label: 'All Categories', value: 'all' },
                              ...CATEGORIES.filter(c => c.id !== 'all').map(c => ({ label: c.name_en, value: c.id }))
                            ]
                          }
                        ]}
                        moreFilters={[
                          {
                            id: 'stock_level',
                            label: 'Stock Availability Status',
                            options: [
                              { label: 'All Stock Levels', value: 'all' },
                              { label: 'In Stock (>5)', value: 'in_stock' },
                              { label: 'Low Stock (1–5)', value: 'low' },
                              { label: 'Out of Stock (0)', value: 'out' }
                            ]
                          }
                        ]}
                        savedViews={[
                          { id: 'default', label: 'Default (All Products)' },
                          { id: 'low_stock', label: 'Low Stock & Reorder Needed', filters: { stock_level: 'low' } },
                          { id: 'panjabi', label: 'Festive Panjabi Collection', filters: { category: 'panjabi' } }
                        ]}
                        bulkActions={[
                          {
                            label: 'Print Selected Barcodes',
                            icon: 'barcode',
                            action: (rows) => {
                              setSelectedBarcodeProducts(rows.map(p => ({ ...p, print_qty: 4 })));
                              setCatalogSubTab('barcodes');
                              setSelectedBarcodeProducts(rows.map(p => ({ ...p, print_qty: 4 }))); setShowBulkBarcodeModal(true);
                            }
                          },
                          {
                            label: 'Export Product Catalog',
                            icon: 'fileText',
                            action: (rows) => {
                              exportToCSV('products_catalog_' + Date.now() + '.csv', [
                                { id: 'sku', header: 'SKU' },
                                { id: 'barcode', header: 'Barcode' },
                                { id: 'name_en', header: 'Product Name' },
                                { id: 'category', header: 'Category' },
                                { id: 'cost', header: 'Cost' },
                                { id: 'price', header: 'Retail Price' },
                                { id: 'stock_main', header: 'Main Stock' },
                                { id: 'stock_b2', header: 'Branch 2 Stock' }
                              ], rows);
                              addToast('Exported ' + rows.length + ' products to CSV', 'success');
                            }
                          }
                        ]}
                        customFilter={(p, filters) => {
                          if (filters.category && filters.category !== 'all' && p.category !== filters.category) return false;
                          if (filters.stock_level && filters.stock_level !== 'all') {
                            if (filters.stock_level === 'in_stock' && p.stock_main <= 5) return false;
                            if (filters.stock_level === 'low' && (p.stock_main <= 0 || p.stock_main > 5)) return false;
                            if (filters.stock_level === 'out' && p.stock_main > 0) return false;
                          }
                          return true;
                        }}
                        onRowClick={p => {
                          setSelectedProduct(p);
                          setCurrentScreen('product_detail');
                        }}
                        onRowContextMenu={(p, e) => {
                          openContextMenu(e, p.name_en, [
                            { label: 'View Product Details (Deep Dive)', action: () => { setSelectedProduct(p); setCurrentScreen('product_detail'); } },
                            { label: 'Edit Product', action: () => openEditProductForm(p) },
                            { label: 'Check Branch Stock', action: () => { setStockCheckQuery(p.sku); setShowStockCheckModal(true); } },
                            { label: 'Stock Adjustment (Damaged/Audit)', action: () => { setSelectedProduct(p); setShowStockAdjustmentModal(true); } },
                            ...(hasMultipleBranches ? [{ label: 'Transfer to Branch 2', action: () => { setSelectedProduct(p); setShowStockTransferModal(true); } }] : []),
                            { label: 'Print Barcode Labels', action: () => { setSelectedProduct(p); setShowBarcodeLabelModal(true); } }
                          ]);
                        }}
                      />
                    </div>
                  )}

                  {/* SUB-TAB 2: CATEGORY & BRAND MANAGEMENT */}
                  {catalogSubTab === 'categories' && (
                    <div className="flex-1 overflow-y-auto space-y-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {CATEGORIES.filter(c => c.id !== 'all').map((cat) => {
                          const count = RAW_DATA.PRODUCTS.filter(p => p.category === cat.id).length;
                          return (
                            <div key={cat.id} className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] shadow-xs transition-all flex flex-col justify-between">
                              <div className="flex items-start justify-between">
                                <div>
                                  <div className="font-bold text-sm text-[var(--foreground)]">{cat.name_en}</div>
                                  <div className="text-xs text-[var(--muted-foreground)] font-bangla">{cat.name_bn}</div>
                                  <div className="text-[10px] font-mono text-[var(--primary)] mt-1">Slug: {cat.id}</div>
                                </div>
                                <span className="px-2 py-0.5 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]">
                                  {count} SKUs
                                </span>
                              </div>
                              <div className="mt-4 pt-3 border-t border-[var(--border)] flex items-center justify-between text-xs">
                                <span className="text-[10px] text-[var(--muted-foreground)]">Status: Active</span>
                                <button
                                  onClick={() => openEditCategoryModal(cat)}
                                  className="text-xs text-[var(--primary)] hover:underline font-semibold"
                                >
                                  Edit Category
                                </button>
                              </div>
                            </div>
                          );
                        })}
                      </div>

                      {/* Brand Directory */}
                      <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3">
                        <div className="flex justify-between items-center">
                          <h3 className="font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">Registered Brands</h3>
                          <button onClick={() => setShowAddBrandModal(true)} className="text-xs text-[var(--primary)] font-semibold hover:underline">+ Add Brand</button>
                        </div>
                        <div className="flex flex-wrap gap-2 text-xs">
                          {brandsList.map((brand, bIdx) => (
                            <span key={bIdx} className="px-3 py-1 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-medium text-[var(--foreground)]">
                              {brand.name || brand}
                            </span>
                          ))}
                        </div>
                      </div>
                    </div>
                  )}

                  {/* SUB-TAB 3: BULK BARCODE GENERATOR */}
                  {catalogSubTab === 'barcodes' && (
                    <div className="flex-1 flex flex-col lg:flex-row gap-4 overflow-hidden">
                      {/* Left: Product Selection for Bulk Print */}
                      <div className="flex-1 rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 flex flex-col justify-between overflow-hidden">
                        <div className="flex justify-between items-center pb-2 border-b border-[var(--border)]">
                          <span className="font-bold text-xs">Select Products to Print Barcode Stickers</span>
                          <span className="text-[10px] text-[var(--muted-foreground)]">{selectedBarcodeProducts.length} products selected</span>
                        </div>
                        <div className="flex-1 overflow-y-auto divide-y divide-[var(--border)] py-1">
                          {RAW_DATA.PRODUCTS.slice(0, 15).map(prod => (
                            <div key={prod.id} className="py-2 flex items-center justify-between text-xs hover:bg-[var(--surface-sunken)] px-1 rounded">
                              <div className="flex items-center gap-2">
                                <input
                                  type="checkbox"
                                  defaultChecked
                                  className="rounded text-[var(--primary)]"
                                />
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">{prod.name_en}</div>
                                  <div className="text-[10px] font-mono text-[var(--muted-foreground)]">SKU: {prod.sku} · Barcode: {prod.barcode}</div>
                                </div>
                              </div>
                              <div className="flex items-center gap-2">
                                <span className="num font-bold">{formatMoney(prod.price)}</span>
                                <div className="flex items-center gap-1 text-[10px]">
                                  <span className="text-[var(--muted-foreground)]">Qty:</span>
                                  <input type="number" defaultValue="24" className="w-12 h-6 px-1 rounded bg-[var(--surface-sunken)] border text-center font-mono font-bold" />
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* Right: Barcode Sticker Preview (Printable Area) */}
                      <div className="w-full lg:w-96 rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 flex flex-col justify-between shrink-0">
                        <div>
                          <div className="flex justify-between items-center mb-3">
                            <span className="font-bold text-xs">Sticker Sheet Live Preview</span>
                            <select
                              value={barcodeSheetFormat}
                              onChange={(e) => setBarcodeSheetFormat(e.target.value)}
                              className="h-7 px-2 rounded bg-[var(--surface-sunken)] border text-xs font-semibold"
                            >
                              <option value="50x25_thermal">50×25mm Thermal Roll</option>
                              <option value="38x25_thermal">38×25mm Double Roll</option>
                              <option value="a4_65">A4 Sheet (65 Stickers)</option>
                            </select>
                          </div>

                          {/* Isolated Printable Barcode Container */}
                          <div id="printable-barcode-batch-area" className="p-3 bg-white text-black rounded-lg border border-[var(--border)] space-y-3">
                            {[0, 1, 2].map((i) => {
                              const p = RAW_DATA.PRODUCTS[i] || RAW_DATA.PRODUCTS[0];
                              return (
                                <div key={i} className="p-2 border border-[var(--border)] rounded text-center space-y-0.5 bg-white">
                                  <div className="text-[9px] font-extrabold uppercase tracking-widest">DIFFERENT FASHION</div>
                                  <div className="text-[11px] font-bold truncate">{p.name_en}</div>
                                  <div className="h-7 w-36 mx-auto flex items-center justify-between py-0.5">
                                    {[2,1,3,1,2,3,1,2,1,3,2,1,2,3].map((w, idx) => (
                                      <span key={idx} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                                    ))}
                                  </div>
                                  <div className="font-mono text-[9px] font-semibold">{p.barcode}</div>
                                  <div className="font-extrabold text-xs num">{formatMoney(p.price)} <span className="text-[8px] font-normal">(VAT Incl)</span></div>
                                </div>
                              );
                            })}
                          </div>
                        </div>

                        <div className="pt-3 border-t border-[var(--border)] text-center">
                          <span className="text-[10px] text-[var(--muted-foreground)]">Thermal ESC/POS Compatible · Code128 Auto</span>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* ================= SCREEN 12: PRODUCT SPECIFICATIONS & FORM (HIGH FIDELITY SELECT FIELDS) ================= */}
              {currentScreen === 'product_form' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4 max-w-4xl mx-auto w-full">
                  <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                    <div className="flex items-center gap-3">
                      <button onClick={() => setCurrentScreen('products')} className="p-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:bg-[var(--surface-sunken)]">
                        <Icon name="arrowLeft" size={16} />
                      </button>
                      <div>
                        <h1 className="text-lg font-bold text-[var(--foreground)]">{selectedProduct ? 'Edit Product Specifications' : 'New Apparel SKU Registration'}</h1>
                        <div className="text-xs text-[var(--muted-foreground)]">Define classification dropdowns, pricing tiers, size & color variants, and branch stock</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button onClick={() => setCurrentScreen('products')} className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-medium">
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          const errors = {};
                          const cleanNameEn = (prodFormNameEn || '').trim();
                          const cleanSku = (prodFormSku || '').trim();
                          const cost = Number(prodFormCost);
                          const price = Number(prodFormPrice);

                          if (!cleanNameEn || cleanNameEn.length < 3) {
                            errors.name = "Product name (English) is required (at least 3 characters)";
                          }
                          if (!cleanSku) {
                            errors.sku = "SKU identifier is required";
                          }
                          if (isNaN(cost) || cost <= 0) {
                            errors.cost = "Cost price must be greater than ৳0";
                          }
                          if (isNaN(price) || price <= 0) {
                            errors.price = "Retail price must be greater than ৳0";
                          } else if (cost > 0 && price < cost) {
                            errors.price = `Retail price (MRP) cannot be less than cost price (৳${cost})`;
                          }

                          if (Object.keys(errors).length > 0) {
                            setProdFormErrors(errors);
                            setProdFormTouched({ name: true, sku: true, cost: true, price: true });
                            addToast("Please correct validation errors before saving product", "danger");
                            return;
                          }

                          const productObj = {
                            id: selectedProduct?.id || ('prod_' + Date.now()),
                            name_en: cleanNameEn,
                            name_bn: prodFormNameBn.trim() || cleanNameEn,
                            category: prodFormCategory,
                            brand: prodFormBrand,
                            vendor: prodFormVendor,
                            unit: prodFormUnit,
                            sku: cleanSku,
                            barcode: prodFormBarcode || ('894' + Math.floor(100000000 + Math.random() * 900000000)),
                            image: prodFormImage || (selectedProduct?.image || ''),
                            cost: cost,
                            cost_price: cost,
                            price: price,
                            wholesale_price: Number(prodFormWholesale) || Math.round(cost * 1.2),
                            stock_main: Number(prodFormStockMain) || 0,
                            stock_b2: Number(prodFormStockB2) || 0,
                            total_stock: (Number(prodFormStockMain) || 0) + (Number(prodFormStockB2) || 0),
                            sell_as_bundle: prodFormBundle,
                            variants: buildProdFormVariants().map(({ key, ...v }) => v),
                            has_variants: buildProdFormVariants().length > 0
                          };

                          if (selectedProduct) {
                            const idx = INITIAL_PRODUCTS.findIndex(p => p.id === selectedProduct.id);
                            if (idx !== -1) INITIAL_PRODUCTS[idx] = productObj;
                            const rIdx = RAW_DATA.PRODUCTS.findIndex(p => p.id === selectedProduct.id);
                            if (rIdx !== -1) RAW_DATA.PRODUCTS[rIdx] = productObj;
                            addToast(`Product "${productObj.name_en}" updated successfully!`, "success");
                          } else {
                            INITIAL_PRODUCTS.unshift(productObj);
                            RAW_DATA.PRODUCTS.unshift(productObj);
                            addToast(`New product "${productObj.name_en}" (${productObj.sku}) added to catalog!`, "success");
                          }

                          setSelectedProduct(productObj);
                          setCurrentScreen('products');
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold shadow-xs flex items-center gap-1.5 cursor-pointer"
                      >
                        <Icon name="check" size={14} />
                        <span>Save Product</span>
                      </button>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Panel 1: General & Classification (Select Fields) */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                      <div className="font-bold text-xs text-[var(--foreground)] border-b border-[var(--border)] pb-2 flex items-center gap-1.5">
                        <Icon name="tag" size={14} className="text-[var(--primary)]" />
                        <span>Classification & Identity</span>
                      </div>

                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Product Name (English) *</label>
                        <input
                          type="text"
                          value={prodFormNameEn}
                          onChange={(e) => {
                            setProdFormNameEn(e.target.value);
                            if (prodFormErrors.name) setProdFormErrors(prev => ({ ...prev, name: null }));
                          }}
                          placeholder="e.g. Premium Cotton Panjabi Slim Fit"
                          className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] font-semibold ${
                            prodFormErrors.name ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                          }`}
                        />
                        {prodFormErrors.name && (
                          <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                            <Icon name="alertCircle" size={11} />
                            <span>{prodFormErrors.name}</span>
                          </div>
                        )}
                      </div>

                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Product Name (বাংলা)</label>
                        <input
                          type="text"
                          value={prodFormNameBn}
                          onChange={(e) => setProdFormNameBn(e.target.value)}
                          placeholder="e.g. প্রিমিয়াম সুতি পাঞ্জাবি"
                          className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-bangla text-[var(--foreground)]"
                        />
                      </div>

                      {/* Category & Brand Dropdowns */}
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Category *</label>
                          <select
                            value={prodFormCategory}
                            onChange={(e) => setProdFormCategory(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            {CATEGORIES.filter(c => c.id !== 'all').map(c => (
                              <option key={c.id} value={c.id}>{c.name_en}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Brand / Label *</label>
                          <select
                            value={prodFormBrand}
                            onChange={(e) => setProdFormBrand(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            <option>Different Fashion (In-house)</option>
                            <option>Cotton Club</option>
                            <option>Richman</option>
                            <option>Lubnan</option>
                            <option>Aarong</option>
                            <option>Apex Footwear</option>
                            <option>Other Supplier Brand</option>
                          </select>
                        </div>
                      </div>

                      {/* Vendor & Unit Dropdowns */}
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Primary Supplier / Vendor</label>
                          <select
                            value={prodFormVendor}
                            onChange={(e) => setProdFormVendor(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            {MOCK_VENDORS.map(v => (
                              <option key={v.id} value={v.name}>{v.name}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Unit of Measure</label>
                          <select
                            value={prodFormUnit}
                            onChange={(e) => setProdFormUnit(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            <option>Pieces (Pcs)</option>
                            <option>Pair (জোড়া)</option>
                            <option>Set (সেট)</option>
                            <option>Meter (মিটার)</option>
                            <option>Dozen (ডজন)</option>
                          </select>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">SKU Identifier *</label>
                          <input
                            type="text"
                            value={prodFormSku}
                            onChange={(e) => {
                              setProdFormSku(e.target.value);
                              if (prodFormErrors.sku) setProdFormErrors(prev => ({ ...prev, sku: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold text-[var(--foreground)] ${
                              prodFormErrors.sku ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {prodFormErrors.sku && (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{prodFormErrors.sku}</span>
                            </div>
                          )}
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Barcode (EAN-13)</label>
                          <div className="flex gap-1.5 mt-1">
                            <input
                              type="text"
                              value={prodFormBarcode}
                              onChange={(e) => setProdFormBarcode(e.target.value)}
                              className="flex-1 h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                            />
                            <button
                              type="button"
                              onClick={() => setProdFormBarcode('8941' + Math.floor(10000000 + Math.random() * 90000000))}
                              className="px-2 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[10px] font-semibold hover:bg-[var(--border)]"
                              title="Generate random barcode"
                            >
                              Gen
                            </button>
                          </div>
                        </div>
                      </div>

                      {/* Product Visual Asset / Photo */}
                      <div className="pt-2 border-t border-[var(--border)]">
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)] flex items-center justify-between">
                          <span>Apparel Catalog Image URL</span>
                          <span className="text-[10px] text-[var(--primary)] font-normal">HD Product Photography</span>
                        </label>
                        <div className="flex items-center gap-2 mt-1">
                          <div className="w-12 h-12 rounded-lg border border-[var(--border)] overflow-hidden bg-[var(--surface-sunken)] shrink-0 flex items-center justify-center">
                            {prodFormImage ? (
                              <img
                                src={prodFormImage}
                                alt="Preview"
                                className="w-full h-full object-cover object-top"
                                onError={(e) => {
                                  e.currentTarget.style.display = 'none';
                                }}
                              />
                            ) : (
                              <Icon name="tag" size={18} className="text-[var(--muted-foreground)]" />
                            )}
                          </div>
                          <div className="flex-1 space-y-1">
                            <input
                              type="text"
                              value={prodFormImage}
                              onChange={(e) => setProdFormImage(e.target.value)}
                              placeholder="https://images.unsplash.com/..."
                              className="w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                            />
                            <div className="flex items-center gap-1.5 text-[10px]">
                              <span className="text-[var(--muted-foreground)]">Presets:</span>
                              <button
                                type="button"
                                onClick={() => setProdFormImage('https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=600&q=80')}
                                className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]"
                              >
                                Panjabi
                              </button>
                              <button
                                type="button"
                                onClick={() => setProdFormImage('https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80')}
                                className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]"
                              >
                                Saree
                              </button>
                              <button
                                type="button"
                                onClick={() => setProdFormImage('https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?auto=format&fit=crop&w=600&q=80')}
                                className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--foreground)]"
                              >
                                Shirt
                              </button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Panel 2: Pricing, VAT & Size Variants */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                      <div className="font-bold text-xs text-[var(--foreground)] border-b border-[var(--border)] pb-2 flex items-center justify-between">
                        <div className="flex items-center gap-1.5">
                          <Icon name="dollarSign" size={14} className="text-[var(--success)]" />
                          <span>Pricing, VAT & Variant Matrix</span>
                        </div>
                        {prodFormCost > 0 && prodFormPrice > 0 && (
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold num ${
                            prodFormPrice >= prodFormCost ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--danger-soft)] text-[var(--danger)]'
                          }`}>
                            {prodFormPrice >= prodFormCost ? `+${Math.round(((prodFormPrice - prodFormCost) / prodFormCost) * 100)}% Margin` : 'Negative Margin'}
                          </span>
                        )}
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Cost Price (BDT) *</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormCost}
                            onChange={(e) => {
                              setProdFormCost(Number(e.target.value) || 0);
                              if (prodFormErrors.cost) setProdFormErrors(prev => ({ ...prev, cost: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs num font-bold text-right text-[var(--foreground)] ${
                              prodFormErrors.cost ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {prodFormErrors.cost && (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{prodFormErrors.cost}</span>
                            </div>
                          )}
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Retail MRP (BDT) *</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormPrice}
                            onChange={(e) => {
                              setProdFormPrice(Number(e.target.value) || 0);
                              if (prodFormErrors.price) setProdFormErrors(prev => ({ ...prev, price: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs num font-bold text-[var(--primary)] text-right ${
                              prodFormErrors.price ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {prodFormErrors.price && (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{prodFormErrors.price}</span>
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Wholesale Price (BDT)</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormWholesale}
                            onChange={(e) => setProdFormWholesale(Number(e.target.value) || 0)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-semibold text-right text-[var(--foreground)]"
                          />
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">VAT Tax Category</label>
                          <select
                            value={prodFormVatRate}
                            onChange={(e) => setProdFormVatRate(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            <option>15% Standard (NBR Mushak 6.3)</option>
                            <option>5% Reduced Apparel Tax</option>
                            <option>0% VAT Exempt</option>
                          </select>
                        </div>
                      </div>

                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Applicable Sizes</label>
                        <div className="flex flex-wrap gap-1.5 mt-1">
                          {[...['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'], ...prodFormSizes.filter(x => !['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL'].includes(x))].map(opt => (
                            <button
                              key={opt}
                              type="button"
                              aria-pressed={prodFormSizes.includes(opt)}
                              onClick={() => toggleProdFormOption(setProdFormSizes, opt)}
                              className={`px-2.5 py-1 min-h-8 rounded-md text-xs font-mono font-bold border transition-colors cursor-pointer ${
                                prodFormSizes.includes(opt)
                                  ? 'bg-[var(--primary)] text-[var(--primary-foreground)] border-[var(--primary)]'
                                  : 'bg-[var(--surface-sunken)] border-[var(--border)] hover:border-[var(--primary)]'
                              }`}
                            >
                              {opt}
                            </button>
                          ))}
                        </div>
                        <div className="flex gap-1.5 mt-1.5">
                          <input
                            type="text"
                            value={prodFormCustomSize}
                            onChange={(e) => setProdFormCustomSize(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addProdFormCustomOption(setProdFormSizes, prodFormCustomSize, setProdFormCustomSize); } }}
                            placeholder="Add size"
                            className="flex-1 h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                          <button
                            type="button"
                            onClick={() => addProdFormCustomOption(setProdFormSizes, prodFormCustomSize, setProdFormCustomSize)}
                            className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold cursor-pointer"
                          >
                            Add
                          </button>
                        </div>
                      </div>

                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Applicable Colors</label>
                        <div className="flex flex-wrap gap-1.5 mt-1">
                          {[...['White', 'Navy Blue', 'Black', 'Maroon', 'Olive Green', 'Pastel Pink', 'Royal Blue'], ...prodFormColors.filter(x => !['White', 'Navy Blue', 'Black', 'Maroon', 'Olive Green', 'Pastel Pink', 'Royal Blue'].includes(x))].map(opt => (
                            <button
                              key={opt}
                              type="button"
                              aria-pressed={prodFormColors.includes(opt)}
                              onClick={() => toggleProdFormOption(setProdFormColors, opt)}
                              className={`px-2.5 py-1 min-h-8 rounded-md text-xs font-bold border transition-colors cursor-pointer ${
                                prodFormColors.includes(opt)
                                  ? 'bg-[var(--primary)] text-[var(--primary-foreground)] border-[var(--primary)]'
                                  : 'bg-[var(--surface-sunken)] border-[var(--border)] hover:border-[var(--primary)]'
                              }`}
                            >
                              {opt}
                            </button>
                          ))}
                        </div>
                        <div className="flex gap-1.5 mt-1.5">
                          <input
                            type="text"
                            value={prodFormCustomColor}
                            onChange={(e) => setProdFormCustomColor(e.target.value)}
                            onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addProdFormCustomOption(setProdFormColors, prodFormCustomColor, setProdFormCustomColor); } }}
                            placeholder="Add colour"
                            className="flex-1 h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                          <button
                            type="button"
                            onClick={() => addProdFormCustomOption(setProdFormColors, prodFormCustomColor, setProdFormCustomColor)}
                            className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold cursor-pointer"
                          >
                            Add
                          </button>
                        </div>
                      </div>

                      {/* Generated Variant Grid */}
                      <div className="pt-1 border-t border-[var(--border)] space-y-2">
                        <div className="flex items-center justify-between">
                          <span className="text-[11px] font-semibold text-[var(--muted-foreground)]">Variants ({buildProdFormVariants().length})</span>
                          <label className="flex items-center gap-1.5 text-xs font-semibold text-[var(--foreground)] cursor-pointer">
                            <input type="checkbox" checked={prodFormBundle} onChange={(e) => setProdFormBundle(e.target.checked)} />
                            <span>Sell as bundle</span>
                          </label>
                        </div>
                        {buildProdFormVariants().length > 0 ? (
                          <div className="overflow-x-auto border border-[var(--border)] rounded-lg">
                            <table className="w-full text-xs">
                              <thead className="bg-[var(--surface-sunken)] text-[10px] text-[var(--muted-foreground)] uppercase">
                                <tr>
                                  <th className="p-1.5 text-left">Size</th>
                                  <th className="p-1.5 text-left">Colour</th>
                                  <th className="p-1.5 text-left">SKU</th>
                                  <th className="p-1.5 text-left">Barcode</th>
                                  <th className="p-1.5 text-right">Stock</th>
                                  <th className="p-1.5 text-right">Price</th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-[var(--border)]">
                                {buildProdFormVariants().map(row => (
                                  <tr key={row.key}>
                                    <td className="p-1.5 font-mono font-bold">{row.size || '-'}</td>
                                    <td className="p-1.5">{row.color || '-'}</td>
                                    <td className="p-1"><input type="text" value={row.sku} onChange={(e) => setProdFormVarEdit(row.key, 'sku', e.target.value)} aria-label={`SKU ${row.size} ${row.color}`} className="w-32 h-7 px-1.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-mono" /></td>
                                    <td className="p-1"><input type="text" value={row.barcode} onChange={(e) => setProdFormVarEdit(row.key, 'barcode', e.target.value)} aria-label={`Barcode ${row.size} ${row.color}`} className="w-24 h-7 px-1.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-mono" /></td>
                                    <td className="p-1"><input type="number" min="0" value={row.stock_main} onChange={(e) => setProdFormVarEdit(row.key, 'stock_main', Number(e.target.value) || 0)} aria-label={`Opening stock ${row.size} ${row.color}`} className="w-14 h-7 px-1.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-right" /></td>
                                    <td className="p-1"><input type="number" min="0" value={row.price} onChange={(e) => setProdFormVarEdit(row.key, 'price', Number(e.target.value) || 0)} aria-label={`Price ${row.size} ${row.color}`} className="w-16 h-7 px-1.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-right" /></td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>
                        ) : (
                          <div className="text-[11px] text-[var(--muted-foreground)]">Pick sizes and colours to generate variants.</div>
                        )}
                        {prodFormBundle && (
                          <div className="text-[11px] text-[var(--muted-foreground)] flex items-center gap-1.5">
                            <Icon name="info" size={13} />
                            <span>One bill line per selected size; the picker lets the cashier choose several sizes at once.</span>
                          </div>
                        )}
                      </div>

                      {/* Branch Initial Stock Allocation */}
                      <div className="grid grid-cols-2 gap-2 pt-1 border-t border-[var(--border)]">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Main Branch Stock</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormStockMain}
                            onChange={(e) => setProdFormStockMain(Number(e.target.value) || 0)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-right text-[var(--foreground)]"
                          />
                        </div>
                        {hasMultipleBranches && <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Branch 2 Stock</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormStockB2}
                            onChange={(e) => setProdFormStockB2(Number(e.target.value) || 0)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-right text-[var(--foreground)]"
                          />
                        </div>}
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* ================= SCREEN 13: STOCK & TRANSFERS (G K) ================= */}
              {currentScreen === 'stock' && (
                <DataList
                  id="stock"
                  title="Stock Management & Transfers"
                  subtitle="Branch stock levels, adjustments, inter-branch gate passes, and stocktakes"
                  data={INITIAL_PRODUCTS}
                  keyField="id"
                  searchPlaceholder="Search product SKU, name, barcode... (/)"
                  searchFields={['sku', 'name_en', 'name_bn', 'barcode', 'category']}
                  columns={[
                    {
                      id: 'image',
                      header: 'Item',
                      sortable: false,
                      width: '52px',
                      className: 'w-[52px] text-center',
                      cell: p => (
                        <div className="w-9 h-9 rounded-md border border-[var(--border)] overflow-hidden bg-[var(--surface-sunken)] flex items-center justify-center shrink-0 shadow-2xs group-hover:border-[var(--primary)] transition-colors">
                          {p.image ? (
                            <img
                              src={p.image}
                              alt={p.name_en}
                              className="w-full h-full object-cover object-top"
                              loading="lazy"
                              onError={(e) => {
                                e.currentTarget.style.display = 'none';
                                if (e.currentTarget.nextSibling) e.currentTarget.nextSibling.style.display = 'flex';
                              }}
                            />
                          ) : null}
                          <div className={`w-full h-full flex items-center justify-center text-[var(--muted-foreground)] ${p.image ? 'hidden' : ''}`}>
                            <Icon name={p.category === 'saree' ? 'saree' : p.category === 'panjabi' ? 'panjabi' : p.category === 'shirt' ? 'shirt' : 'tag'} size={15} />
                          </div>
                        </div>
                      )
                    },
                    {
                      id: 'sku',
                      header: 'SKU',
                      sortable: true,
                      className: 'font-mono font-medium text-[var(--primary)]',
                      accessor: p => p.sku
                    },
                    {
                      id: 'name_en',
                      header: 'Product Name',
                      sortable: true,
                      className: 'font-semibold text-[var(--foreground)]',
                      accessor: p => p.name_en
                    },
                    {
                      id: 'stock_main',
                      header: 'Main Branch Stock',
                      type: 'qty',
                      align: 'center',
                      sortable: true,
                      accessor: p => p.stock_main,
                      cell: p => (
                        <span className={`font-mono font-bold num ${p.stock_main <= 3 ? 'text-[var(--danger)]' : 'text-[var(--foreground)]'}`}>
                          {p.stock_main}
                        </span>
                      )
                    },
                    {
                      id: 'stock_b2',
                      header: 'Branch 2 Stock',
                      type: 'qty',
                      align: 'center',
                      sortable: true,
                      accessor: p => p.stock_b2,
                      cell: p => (
                        <span className="font-mono text-[var(--muted-foreground)] num">
                          {p.stock_b2}
                        </span>
                      )
                    },
                    {
                      id: 'stock_total',
                      header: 'Total Combined',
                      type: 'qty',
                      align: 'center',
                      sortable: true,
                      accessor: p => p.stock_main + p.stock_b2,
                      cell: p => (
                        <span className="font-mono font-bold text-[var(--primary)] num">
                          {p.stock_main + p.stock_b2}
                        </span>
                      )
                    },
                    {
                      id: 'cost',
                      header: 'Unit Cost',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: p => p.cost,
                      cell: p => (
                        <span className="font-sans font-medium tabular-nums num text-[var(--muted-foreground)]">
                          {formatMoney(p.cost)}
                        </span>
                      )
                    },
                    {
                      id: 'stock_value',
                      header: 'Stock Value (Cost)',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: p => (p.stock_main + p.stock_b2) * p.cost,
                      cell: p => (
                        <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                          {formatMoney((p.stock_main + p.stock_b2) * p.cost)}
                        </span>
                      )
                    },
                    {
                      id: 'actions',
                      header: 'Actions',
                      align: 'right',
                      hideable: false,
                      cell: p => (
                        <div className="flex items-center justify-end gap-1" onClick={e => e.stopPropagation()}>
                          <button
                            type="button"
                            onClick={() => { setSelectedProduct(p); setShowStockTransferModal(true); }}
                            className="h-6 px-2.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[10px] font-semibold hover:border-[var(--primary)] cursor-pointer"
                          >
                            Transfer
                          </button>
                          <button
                            type="button"
                            onClick={() => { setSelectedProduct(p); setShowStockAdjustmentModal(true); }}
                            className="h-6 px-2.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[10px] font-semibold hover:border-[var(--warning)] cursor-pointer"
                          >
                            Adjust
                          </button>
                        </div>
                      )
                    }
                  ]}
                  filterChips={[
                    {
                      id: 'category',
                      label: 'Category',
                      options: [
                        { label: 'All Categories', value: 'all' },
                        { label: 'Panjabi', value: 'panjabi' },
                        { label: 'Saree', value: 'saree' },
                        { label: 'Shirt', value: 'shirt' },
                        { label: 'Polo', value: 'polo' }
                      ]
                    }
                  ]}
                  moreFilters={[
                    {
                      id: 'reorder',
                      label: 'Stock Health Alert',
                      options: [
                        { label: 'All Inventory', value: 'all' },
                        { label: 'Low Stock (<10 combined)', value: 'low' },
                        { label: 'Sufficient Stock (≥10)', value: 'ok' }
                      ]
                    }
                  ]}
                  savedViews={[
                    { id: 'default', label: 'Default (All Stock)' },
                    { id: 'low_stock', label: 'Low Stock Reorder Alert', filters: { reorder: 'low' } },
                    { id: 'panjabi', label: 'Festive Panjabi Stock', filters: { category: 'panjabi' } }
                  ]}
                  bulkActions={[
                    {
                      label: 'Branch Transfer Batch',
                      icon: 'truck',
                      action: (rows) => {
                        setShowStockTransferModal(true);
                        handleOpenBatchTransfer(rows);
                      }
                    },
                    {
                      label: 'Export Valuation',
                      icon: 'fileText',
                      action: (rows) => {
                        exportToCSV('stock_valuation_' + Date.now() + '.csv', [
                          { id: 'sku', header: 'SKU' },
                          { id: 'name_en', header: 'Product' },
                          { id: 'stock_main', header: 'Main Stock' },
                          { id: 'stock_b2', header: 'Branch 2 Stock' },
                          { id: 'stock_total', header: 'Total Qty', accessor: p => p.stock_main + p.stock_b2 },
                          { id: 'cost', header: 'Cost' },
                          { id: 'stock_value', header: 'Total Value', accessor: p => (p.stock_main + p.stock_b2) * p.cost }
                        ], rows);
                        addToast('Exported valuation ledger to CSV', 'success');
                      }
                    }
                  ]}
                  customFilter={(p, filters) => {
                    if (filters.category && filters.category !== 'all' && p.category !== filters.category) return false;
                    if (filters.reorder && filters.reorder !== 'all') {
                      const total = p.stock_main + p.stock_b2;
                      if (filters.reorder === 'low' && total >= 10) return false;
                      if (filters.reorder === 'ok' && total < 10) return false;
                    }
                    return true;
                  }}
                  headerActions={
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setShowStockAdjustmentModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 cursor-pointer"
                      >
                        <Icon name="edit" size={14} />
                        <span>Stock Adjustment</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowStockTransferModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium flex items-center gap-1.5 cursor-pointer"
                      >
                        <Icon name="truck" size={14} />
                        <span>Branch Transfer</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowStocktakeModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                      >
                        <Icon name="check" size={14} />
                        <span>Stocktake Count</span>
                      </button>
                    </div>
                  }
                  onRowClick={p => {
                    setSelectedProduct(p);
                    setShowStockTransferModal(true);
                  }}
                  onRowContextMenu={(p, e) => {
                    openContextMenu(e, p.name_en, [
                      ...(hasMultipleBranches ? [{ label: 'Inter-Branch Transfer', action: () => { setSelectedProduct(p); setShowStockTransferModal(true); } }] : []),
                      { label: 'Stock Adjustment (Damaged/Audit)', action: () => { setSelectedProduct(p); setShowStockAdjustmentModal(true); } },
                      { label: 'Check Branch Stock', action: () => { setStockCheckQuery(p.sku); setShowStockCheckModal(true); } }
                    ]);
                  }}
                />
              )}

              {/* ================= SCREEN 14: PURCHASES & GRN (G P) ================= */}
              {currentScreen === 'purchases' && (
                <div className="flex-1 flex flex-col overflow-hidden space-y-3">
                  {/* Summary Metric Cards */}
                  <div className="p-4 pb-0 grid grid-cols-2 md:grid-cols-4 gap-3 shrink-0">
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs">
                      <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Total Inward Bills (MTD)</div>
                      <div className="text-base font-bold num text-[var(--foreground)] mt-0.5">{formatMoney(purchasesList.reduce((acc, p) => acc + (p.total || 0), 0))}</div>
                      <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">{purchasesList.length} purchase vouchers</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs">
                      <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Items Received in Stock</div>
                      <div className="text-base font-bold font-mono text-[var(--primary)] mt-0.5">
                        {purchasesList.reduce((acc, p) => acc + (p.items_count || 0), 0)} pcs
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">Across Main & Branch 2</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs">
                      <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Total Amount Paid</div>
                      <div className="text-base font-bold font-mono text-[var(--success)] mt-0.5">
                        {formatMoney(purchasesList.reduce((acc, p) => acc + (p.paid || 0), 0))}
                      </div>
                      <div className="text-[10px] text-[var(--success)] mt-0.5">Settled via Cash / Bank</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs">
                      <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Outstanding Vendor Payables</div>
                      <div className="text-base font-bold font-mono text-[var(--danger)] mt-0.5">
                        {formatMoney(purchasesList.reduce((acc, p) => acc + (p.balance || p.due || 0), 0))}
                      </div>
                      <div className="text-[10px] text-[var(--danger)] mt-0.5 font-semibold">10 active vendor suppliers</div>
                    </div>
                  </div>

                  {/* Purchases DataList */}
                  <div className="flex-1 overflow-hidden flex flex-col">
                    <DataList
                      id="purchases"
                      title="Vendor Purchases & GRN"
                      subtitle="Receive products into stock, track batch variants, landed costs, and vendor payables"
                      data={purchasesList}
                      keyField="id"
                      dateField="date"
                      searchPlaceholder="Search GRN #, vendor supplier, bill ref... (/)"
                      searchFields={['grn_no', 'purchase_no', 'vendor', 'bill_no', 'branch']}
                      columns={[
                        {
                          id: 'grn_no',
                          header: 'GRN #',
                          sortable: true,
                          className: 'font-mono font-bold text-[var(--primary)]',
                          accessor: p => p.grn_no || p.purchase_no,
                          cell: p => (
                            <span className="font-mono font-bold text-[var(--primary)] group-hover:underline">
                              {p.grn_no || p.purchase_no}
                            </span>
                          )
                        },
                        {
                          id: 'date',
                          header: 'Date',
                          sortable: true,
                          className: 'text-[var(--muted-foreground)]',
                          accessor: p => p.date
                        },
                        {
                          id: 'vendor',
                          header: 'Vendor Supplier',
                          sortable: true,
                          className: 'font-semibold text-[var(--foreground)]',
                          accessor: p => p.vendor
                        },
                        {
                          id: 'branch',
                          header: 'Receiving Branch',
                          sortable: true,
                          className: 'text-[var(--muted-foreground)]',
                          accessor: p => p.branch || 'Main Branch [DF1]'
                        },
                        {
                          id: 'bill_no',
                          header: 'Bill Ref',
                          sortable: true,
                          className: 'font-mono text-[var(--muted-foreground)]',
                          accessor: p => p.bill_no
                        },
                        {
                          id: 'items_count',
                          header: 'Quantity',
                          type: 'qty',
                          align: 'right',
                          sortable: true,
                          accessor: p => p.items_count,
                          cell: p => (
                            <span className="px-2 py-0.5 rounded bg-[var(--surface-sunken)] font-mono font-semibold text-[var(--foreground)] border border-[var(--border)]">
                              {p.items ? p.items.length : 2} items · {p.items_count} pcs
                            </span>
                          )
                        },
                        {
                          id: 'total',
                          header: 'Total Bill',
                          type: 'money',
                          align: 'right',
                          sortable: true,
                          accessor: p => p.total,
                          cell: p => (
                            <span className="font-sans font-bold tabular-nums num text-[var(--foreground)]">
                              {formatMoney(p.total)}
                            </span>
                          )
                        },
                        {
                          id: 'paid',
                          header: 'Paid',
                          type: 'money',
                          align: 'right',
                          sortable: true,
                          accessor: p => p.paid,
                          cell: p => (
                            <span className="font-sans font-semibold tabular-nums num text-[var(--success)]">
                              {formatMoney(p.paid)}
                            </span>
                          )
                        },
                        {
                          id: 'due',
                          header: 'Balance Due',
                          type: 'money',
                          align: 'right',
                          sortable: true,
                          accessor: p => p.balance || p.due || 0,
                          cell: p => {
                            const due = p.balance || p.due || 0;
                            return (
                              <span className="font-sans font-semibold tabular-nums num">
                                {due > 0 ? (
                                  <span className="text-[var(--danger)]">{formatMoney(due)}</span>
                                ) : (
                                  <span className="text-[var(--success)]">Paid (৳0)</span>
                                )}
                              </span>
                            );
                          }
                        },
                        {
                          id: 'actions',
                          header: 'Actions',
                          align: 'right',
                          hideable: false,
                          cell: p => (
                            <div className="flex items-center justify-end" onClick={e => e.stopPropagation()}>
                              <button
                                type="button"
                                onClick={() => { setSelectedPurchase(p); setShowPurchaseDetailModal(true); }}
                                className="h-6 px-2.5 rounded bg-[var(--primary-soft)] hover:bg-[var(--primary)] text-[var(--primary)] hover:text-white font-semibold text-[11px] transition-all cursor-pointer"
                              >
                                View Items
                              </button>
                            </div>
                          )
                        }
                      ]}
                      filterChips={[
                        ...(hasMultipleBranches ? [{
                          id: 'branch',
                          label: 'Branch',
                          options: [
                            { label: 'All Branches', value: 'all' },
                            { label: 'Main Branch [DF1]', value: 'Main' },
                            { label: 'Branch 2 [DF2]', value: 'Branch 2' }
                          ]
                        }] : [])
                      ]}
                      moreFilters={[
                        {
                          id: 'due_status',
                          label: 'Payment Due Status',
                          options: [
                            { label: 'All Vouchers', value: 'all' },
                            { label: 'Outstanding Balance Due (>৳0)', value: 'due' },
                            { label: 'Fully Paid (৳0 Due)', value: 'paid' }
                          ]
                        },
                        {
                          id: 'vendor',
                          label: 'Vendor Supplier',
                          options: [
                            { label: 'All Vendors', value: 'all' },
                            { label: 'Heritage Loom Ltd', value: 'Heritage' },
                            { label: 'Noman Terry Towel Mills', value: 'Noman' },
                            { label: 'Beximco Textiles Ltd', value: 'Beximco' },
                            { label: 'Amber Denim Mills', value: 'Amber' }
                          ]
                        }
                      ]}
                      savedViews={[
                        { id: 'default', label: 'Default (All Purchases)' },
                        { id: 'unpaid', label: 'Unpaid Vendor Bills', filters: { due_status: 'due' } },
                        ...(hasMultipleBranches ? [{ id: 'b2', label: 'Branch 2 Deliveries', filters: { branch: 'Branch 2' } }] : [])
                      ]}
                      bulkActions={[
                        {
                          label: 'Export Inward Bills',
                          icon: 'fileText',
                          action: (rows) => {
                            exportToCSV('vendor_purchases_' + Date.now() + '.csv', [
                              { id: 'grn_no', header: 'GRN No' },
                              { id: 'date', header: 'Date' },
                              { id: 'vendor', header: 'Vendor' },
                              { id: 'bill_no', header: 'Bill Ref' },
                              { id: 'total', header: 'Total Bill' },
                              { id: 'paid', header: 'Paid' },
                              { id: 'due', header: 'Due' }
                            ], rows);
                            addToast('Exported ' + rows.length + ' purchase vouchers', 'success');
                          }
                        },
                        {
                          label: 'Batch Pay Bills',
                          icon: 'banknote',
                          action: (rows) => {
                            if (rows.length > 0) { setSelectedVendor(vendorsList.find(v => v.name.includes(rows[0].vendor)) || vendorsList[0]); setPayVendorAmount(String(rows[0].amount_due || 25000)); setShowPayVendorModal(true); }
                          }
                        }
                      ]}
                      customFilter={(p, filters) => {
                        if (filters.branch && filters.branch !== 'all') {
                          const br = p.branch || 'Main Branch';
                          if (!br.toLowerCase().includes(filters.branch.toLowerCase())) return false;
                        }
                        if (filters.due_status && filters.due_status !== 'all') {
                          const due = p.balance || p.due || 0;
                          if (filters.due_status === 'due' && due <= 0) return false;
                          if (filters.due_status === 'paid' && due > 0) return false;
                        }
                        if (filters.vendor && filters.vendor !== 'all') {
                          if (!p.vendor.toLowerCase().includes(filters.vendor.toLowerCase())) return false;
                        }
                        return true;
                      }}
                      headerActions={
                        <button
                          type="button"
                          onClick={() => setShowNewPurchaseModal(true)}
                          className="h-8 px-3.5 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-[var(--primary-foreground)] text-xs font-bold flex items-center gap-1.5 shadow-xs transition-all cursor-pointer"
                        >
                          <Icon name="plus" size={14} />
                          <span>+ New Purchase & Receive Stock (GRN)</span>
                        </button>
                      }
                      onRowClick={p => {
                        setSelectedPurchase(p);
                        setShowPurchaseDetailModal(true);
                      }}
                      onRowContextMenu={(p, e) => {
                        openContextMenu(e, p.grn_no || p.purchase_no, [
                          { label: 'View Purchase & GRN Items', action: () => { setSelectedPurchase(p); setShowPurchaseDetailModal(true); } },
                          { label: 'Settle Vendor Bill', action: () => {
                            const v = MOCK_VENDORS.find(ven => ven.name === p.vendor);
                            if (v) { setSelectedVendor(v); setPayVendorAmount(String(p.balance || p.due || 0)); setShowPayVendorModal(true); }
                          }},
                          { label: 'Reprint GRN Slip', action: () => addToast('Reprinting GRN slip...', 'info') }
                        ]);
                      }}
                    />
                  </div>
                </div>
              )}

              {/* ================= SCREEN 15: VENDOR DIRECTORY (G V) ================= */}
              {currentScreen === 'vendors' && (
                <DataList
                  id="vendors"
                  title="Vendor & Supplier Directory"
                  subtitle="Apparel mills, fabric manufacturers, packaging suppliers, and payables ledger"
                  data={vendorsList || MOCK_VENDORS}
                  keyField="id"
                  searchPlaceholder="Search vendor supplier name, contact, hub location... (/)"
                  searchFields={['name', 'contact', 'location', 'address', 'phone']}
                  columns={[
                    {
                      id: 'name',
                      header: 'Vendor Name',
                      sortable: true,
                      className: 'font-semibold text-[var(--foreground)]',
                      accessor: v => v.name
                    },
                    {
                      id: 'contact_person',
                      header: 'Contact Person / Hub',
                      sortable: true,
                      className: 'text-[var(--muted-foreground)]',
                      accessor: v => v.location || v.address || 'Dhaka'
                    },
                    {
                      id: 'phone',
                      header: 'Phone',
                      sortable: true,
                      className: 'font-mono text-[var(--muted-foreground)]',
                      accessor: v => v.contact || v.phone
                    },
                    {
                      id: 'last_order',
                      header: 'Hub Location / Order',
                      sortable: true,
                      className: 'text-[var(--muted-foreground)]',
                      accessor: v => v.last_order || 'Recent'
                    },
                    {
                      id: 'bills_count',
                      header: 'Open Bills',
                      type: 'qty',
                      align: 'center',
                      sortable: true,
                      accessor: v => v.bills_count || 3
                    },
                    {
                      id: 'payable',
                      header: 'Payable Balance',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: v => (v.balance_due !== undefined ? v.balance_due : v.payable) || 0,
                      cell: v => {
                        const due = (v.balance_due !== undefined ? v.balance_due : v.payable) || 0;
                        return (
                          <span className="font-sans font-bold tabular-nums num text-[var(--danger)]">
                            {formatMoney(due)}
                          </span>
                        );
                      }
                    },
                    {
                      id: 'actions',
                      header: 'Actions',
                      align: 'right',
                      hideable: false,
                      cell: v => {
                        const due = (v.balance_due !== undefined ? v.balance_due : v.payable) || 0;
                        return (
                          <div className="flex items-center justify-end" onClick={e => e.stopPropagation()}>
                            <button
                              type="button"
                              onClick={() => {
                                setSelectedVendor(v);
                                setPayVendorAmount(String(due));
                                setShowPayVendorModal(true);
                              }}
                              className="h-6 px-2.5 rounded bg-[var(--primary-soft)] hover:bg-[var(--primary)] hover:text-white text-[var(--primary)] font-semibold text-[11px] transition-colors cursor-pointer"
                            >
                              Pay Vendor
                            </button>
                          </div>
                        );
                      }
                    }
                  ]}
                  filterChips={[
                    {
                      id: 'hub',
                      label: 'Hub Location',
                      options: [
                        { label: 'All Hubs', value: 'all' },
                        { label: 'Dhaka', value: 'Dhaka' },
                        { label: 'Narayanganj', value: 'Narayanganj' },
                        { label: 'Chittagong', value: 'Chittagong' },
                        { label: 'Pabna', value: 'Pabna' }
                      ]
                    }
                  ]}
                  moreFilters={[
                    {
                      id: 'payable_status',
                      label: 'Payable Balance Status',
                      options: [
                        { label: 'All Suppliers', value: 'all' },
                        { label: 'Has Payable Balance (>৳0)', value: 'due' },
                        { label: 'Cleared / Zero Balance', value: 'cleared' }
                      ]
                    }
                  ]}
                  savedViews={[
                    { id: 'default', label: 'Default (All Vendors)' },
                    { id: 'high_payables', label: 'Overdue Payables (>৳50k)', filters: { payable_status: 'due' } },
                    { id: 'dhaka', label: 'Dhaka Textile Suppliers', filters: { hub: 'Dhaka' } }
                  ]}
                  bulkActions={[
                    {
                      label: 'Export Vendor Ledger',
                      icon: 'fileText',
                      action: (rows) => {
                        exportToCSV('vendor_ledger_' + Date.now() + '.csv', [
                          { id: 'name', header: 'Vendor' },
                          { id: 'contact', header: 'Phone' },
                          { id: 'location', header: 'Location' },
                          { id: 'payable', header: 'Payable Balance' }
                        ], rows);
                        addToast('Exported ' + rows.length + ' vendor ledgers', 'success');
                      }
                    },
                    {
                      label: 'Supplier Aging Summary',
                      icon: 'barChart',
                      action: (rows) => {
                        addToast('Generated supplier aging summary for ' + rows.length + ' vendors', 'info');
                      }
                    }
                  ]}
                  customFilter={(v, filters) => {
                    if (filters.hub && filters.hub !== 'all') {
                      const loc = (v.location || v.address || '').toLowerCase();
                      if (!loc.includes(filters.hub.toLowerCase())) return false;
                    }
                    if (filters.payable_status && filters.payable_status !== 'all') {
                      const due = (v.balance_due !== undefined ? v.balance_due : v.payable) || 0;
                      if (filters.payable_status === 'due' && due <= 0) return false;
                      if (filters.payable_status === 'cleared' && due > 0) return false;
                    }
                    return true;
                  }}
                  headerActions={
                    <button
                      type="button"
                      onClick={() => setShowAddVendorModal(true)}
                      className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                      <Icon name="plus" size={14} />
                      <span>Add Vendor</span>
                    </button>
                  }
                  onRowClick={v => {
                    setSelectedVendor(v);
                    setCurrentScreen('vendor_profile');
                  }}
                  onRowContextMenu={(v, e) => {
                    openContextMenu(e, v.name, [
                      { label: 'View 360 Vendor Profile', action: () => {
                        setSelectedVendor(v);
                        setCurrentScreen('vendor_profile');
                      }},
                      { label: 'Pay Vendor Bill (Allocation)', action: () => {
                        setSelectedVendor(v);
                        const due = (v.balance_due !== undefined ? v.balance_due : v.payable) || 0;
                        setPayVendorAmount(String(due));
                        setShowPayVendorModal(true);
                      }},
                      { label: 'View Purchases (GRN)', action: () => { setCurrentScreen('purchases'); } },
                      { label: 'Copy Phone Number', action: () => {
                        navigator.clipboard ? navigator.clipboard.writeText(v.contact || v.phone || '') : null;
                        addToast('Vendor phone copied', 'info');
                      }}
                    ]);
                  }}
                />
              )}

              {/* ================= SCREEN: PRODUCT 360 DEEP DIVE (product_detail) ================= */}
              {currentScreen === 'product_detail' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  {/* Top Bar with Back Navigation & Action Buttons */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[var(--border)] gap-2">
                    <div className="flex items-center gap-2.5">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('products')}
                        className="h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                      >
                        <Icon name="arrowLeft" size={14} />
                        <span>Back to Products</span>
                      </button>
                      <div className="h-4 w-px bg-[var(--border)]" />
                      <div>
                        <h1 className="text-base font-bold text-[var(--foreground)] flex items-center gap-2">
                          <span>{selectedProduct?.name_en || 'Product 360 Profile'}</span>
                          <span className="font-bangla text-xs font-normal text-[var(--muted-foreground)]">({selectedProduct?.name_bn || ''})</span>
                        </h1>
                        <div className="text-[11px] text-[var(--muted-foreground)] font-mono">
                          SKU: <strong className="text-[var(--primary)]">{selectedProduct?.sku}</strong> · Barcode: {selectedProduct?.barcode} · Category: {selectedProduct?.category}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setShowBarcodeLabelModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--foreground)] cursor-pointer"
                      >
                        <Icon name="barcode" size={14} />
                        <span>Print Labels</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowStockAdjustmentModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--foreground)] cursor-pointer"
                      >
                        <Icon name="sliders" size={14} />
                        <span>Adjust Stock</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowStockTransferModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--foreground)] cursor-pointer"
                      >
                        <Icon name="truck" size={14} />
                        <span>Transfer</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => openEditProductForm(selectedProduct)}
                        className="h-8 px-3.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer hover:bg-[var(--primary-hover)]"
                      >
                        <Icon name="edit" size={14} />
                        <span>Edit Product</span>
                      </button>
                    </div>
                  </div>

                  {/* Product Visual Identity Hero Card */}
                  <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex flex-col md:flex-row items-start md:items-center gap-4 shadow-2xs">
                    <div className="relative w-28 h-28 sm:w-32 sm:h-32 rounded-lg border border-[var(--border)] overflow-hidden bg-[var(--surface-sunken)] shrink-0 shadow-xs group">
                      {selectedProduct?.image ? (
                        <img
                          src={selectedProduct.image}
                          alt={selectedProduct.name_en}
                          className="w-full h-full object-cover object-top transition-transform duration-300 group-hover:scale-105"
                          onError={(e) => {
                            e.currentTarget.style.display = 'none';
                            if (e.currentTarget.nextSibling) e.currentTarget.nextSibling.style.display = 'flex';
                          }}
                        />
                      ) : null}
                      <div className={`w-full h-full flex flex-col items-center justify-center text-[var(--muted-foreground)] ${selectedProduct?.image ? 'hidden' : ''}`}>
                        <Icon name={selectedProduct?.category === 'saree' ? 'saree' : selectedProduct?.category === 'panjabi' ? 'panjabi' : selectedProduct?.category === 'shirt' ? 'shirt' : 'tag'} size={32} />
                        <span className="text-[10px] mt-1 font-mono uppercase">{selectedProduct?.category || 'apparel'}</span>
                      </div>
                      <div className="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded bg-black/60 backdrop-blur-xs text-[9px] font-mono font-bold text-white uppercase tracking-wider">
                        {selectedProduct?.category || 'Apparel'}
                      </div>
                    </div>

                    <div className="flex-1 min-w-0 space-y-1.5">
                      <div className="flex flex-wrap items-center gap-2">
                        <span className="px-2 py-0.5 rounded-md bg-[var(--primary-soft)] text-[var(--primary)] font-bold text-xs uppercase tracking-wider border border-[var(--primary)]/20">
                          {selectedProduct?.brand || 'Different Fashion Signature'}
                        </span>
                        <span className="text-xs font-mono px-2 py-0.5 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)]">
                          SKU: <strong className="text-[var(--foreground)]">{selectedProduct?.sku}</strong>
                        </span>
                        <span className="text-xs font-mono px-2 py-0.5 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)]">
                          Barcode: <strong className="text-[var(--foreground)]">{selectedProduct?.barcode}</strong>
                        </span>
                        <span className="px-2 py-0.5 rounded-md bg-[var(--success-soft)] text-[var(--success)] font-semibold text-xs border border-[var(--success)]/20">
                          Active In-Store & Online
                        </span>
                      </div>

                      <h2 className="text-xl font-bold text-[var(--foreground)] tracking-tight">
                        {selectedProduct?.name_en}
                      </h2>
                      <div className="text-sm font-bangla text-[var(--muted-foreground)]">
                        {selectedProduct?.name_bn}
                      </div>

                      <div className="flex flex-wrap items-center gap-4 pt-1 text-xs text-[var(--muted-foreground)]">
                        <div>Vendor: <strong className="text-[var(--foreground)]">{selectedProduct?.vendor || 'Dhaka Textile Consortium'}</strong></div>
                        <div>•</div>
                        <div>Fabric / Material: <strong className="text-[var(--foreground)]">100% Pure Combed Cotton</strong></div>
                        <div>•</div>
                        <div>Unit: <strong className="text-[var(--foreground)]">{selectedProduct?.unit || 'Pieces (Pcs)'}</strong></div>
                        <div>•</div>
                        <div>NBR VAT: <strong className="text-[var(--foreground)]">15% Mushak 6.3 Standard</strong></div>
                      </div>
                    </div>
                  </div>

                  {/* Top Product Summary KPI Banner */}
                  <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Retail Selling Price</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--foreground)] mt-1">
                        {formatMoney(selectedProduct?.price || 1450)}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Standard VAT inclusive</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Cost Price (FIFO Avg)</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--muted-foreground)] mt-1">
                        {formatMoney(selectedProduct?.cost_price || Math.round((selectedProduct?.price || 1450) * 0.65))}
                      </div>
                      <div className="text-[10px] text-[var(--success)] font-semibold">35.0% Gross Margin</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Main Branch [DF1]</div>
                      <div className="font-mono font-bold text-lg text-[var(--primary)] mt-1">
                        {(selectedProduct?.stock_main !== undefined ? selectedProduct.stock_main : selectedProduct?.stock) || 24} pcs
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Uttara Showroom Counter</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Dhanmondi [DF2]</div>
                      <div className="font-mono font-bold text-lg text-[var(--foreground)] mt-1">
                        {selectedProduct?.stock_b2 || 12} pcs
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Road 27 Showroom</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Total Inventory Value</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--foreground)] mt-1">
                        {formatMoney((((selectedProduct?.stock_main !== undefined ? selectedProduct.stock_main : selectedProduct?.stock) || 24) + (selectedProduct?.stock_b2 || 12) + 8) * (selectedProduct?.cost_price || Math.round((selectedProduct?.price || 1450) * 0.65)))}
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">{((selectedProduct?.stock_main !== undefined ? selectedProduct.stock_main : selectedProduct?.stock) || 24) + (selectedProduct?.stock_b2 || 12) + 8} total pcs</div>
                    </div>
                  </div>

                  {/* Sub Tabs Bar */}
                  <div className="flex items-center gap-1 bg-[var(--surface-sunken)] p-1 rounded-xl border border-[var(--border)] text-xs font-semibold w-fit">
                    {[
                      { id: 'stock_heatmap', label: 'Stock Heat-map per Branch × Variant', icon: 'grid' },
                      { id: 'fifo_batches', label: 'FIFO Cost Batches', icon: 'layers' },
                      { id: 'movement_ledger', label: 'Movement Ledger', icon: 'refresh' },
                      { id: 'sales_purchases', label: 'Sales & Purchases History', icon: 'fileText' },
                      { id: 'price_history', label: 'Price History Chart', icon: 'barChart' }
                    ].map(tab => (
                      <button
                        key={tab.id}
                        type="button"
                        onClick={() => setProductDetailTab(tab.id)}
                        className={`h-7 px-3 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer ${
                          productDetailTab === tab.id ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        <Icon name={tab.icon} size={13} />
                        <span>{tab.label}</span>
                      </button>
                    ))}
                  </div>

                  {/* TAB 1: STOCK HEAT-MAP PER BRANCH X VARIANT */}
                  {productDetailTab === 'stock_heatmap' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div className="flex items-center justify-between">
                        <div>
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Multi-Branch Variant Matrix & Stock Availability</h2>
                          <div className="text-[11px] text-[var(--muted-foreground)]">Live on-hand balance per branch store and size variant (Green: In Stock, Yellow: Low Stock, Red: Out of Stock)</div>
                        </div>
                        <div className="flex items-center gap-3 text-[10px] font-semibold">
                          <span className="flex items-center gap-1"><span className="w-2.5 h-2.5 rounded bg-[var(--success-soft)] border border-[var(--success)] inline-block"></span> Adequate (&ge;5)</span>
                          <span className="flex items-center gap-1"><span className="w-2.5 h-2.5 rounded bg-[var(--warning-soft)] border border-[var(--warning)] inline-block"></span> Low Stock (1-4)</span>
                          <span className="flex items-center gap-1"><span className="w-2.5 h-2.5 rounded bg-[var(--danger-soft)] border border-[var(--danger)] inline-block"></span> Out of Stock (0)</span>
                        </div>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs border-collapse">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3 border-r border-[var(--border)]">Store Branch / Location</th>
                              <th className="py-2.5 px-3 text-center border-r border-[var(--border)]">Size 38 (S)</th>
                              <th className="py-2.5 px-3 text-center border-r border-[var(--border)]">Size 40 (M)</th>
                              <th className="py-2.5 px-3 text-center border-r border-[var(--border)]">Size 42 (L)</th>
                              <th className="py-2.5 px-3 text-center border-r border-[var(--border)]">Size 44 (XL)</th>
                              <th className="py-2.5 px-3 text-center border-r border-[var(--border)]">Size 46 (XXL)</th>
                              <th className="py-2.5 px-3 text-right font-bold bg-[var(--surface)]">Branch Total</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { branch: 'Different Fashion - Main [DF1] (Uttara)', s: 6, m: 8, l: 5, xl: 3, xxl: 2, total: 24 },
                              { branch: 'Different Fashion - Dhanmondi [DF2]', s: 3, m: 4, l: 3, xl: 2, xxl: 0, total: 12 },
                              { branch: 'Different Fashion - Uttara [DF3] (Sector 11)', s: 2, m: 3, l: 2, xl: 1, xxl: 0, total: 8 }
                            ].map((row, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-semibold text-[var(--foreground)] border-r border-[var(--border)]">{row.branch}</td>
                                {[row.s, row.m, row.l, row.xl, row.xxl].map((val, cellIdx) => (
                                  <td key={cellIdx} className="py-2.5 px-3 text-center border-r border-[var(--border)]">
                                    <span className={`inline-flex items-center justify-center min-w-[32px] h-6 px-1.5 rounded font-mono font-bold text-xs ${
                                      val >= 5 ? 'bg-[var(--success-soft)] text-[var(--success)]' : val > 0 ? 'bg-[var(--warning-soft)] text-[var(--warning)]' : 'bg-[var(--danger-soft)] text-[var(--danger)]'
                                    }`}>
                                      {val}
                                    </span>
                                  </td>
                                ))}
                                <td className="py-2.5 px-3 text-right font-mono font-bold text-[var(--primary)] bg-[var(--surface)]/70">
                                  {row.total} pcs
                                </td>
                              </tr>
                            ))}
                          </tbody>
                          <tfoot className="bg-[var(--surface-sunken)] border-t-2 border-[var(--border)] font-bold">
                            <tr>
                              <td className="py-2.5 px-3 text-[var(--foreground)] border-r border-[var(--border)]">Total Matrix Availability</td>
                              <td className="py-2.5 px-3 text-center font-mono border-r border-[var(--border)]">11 pcs</td>
                              <td className="py-2.5 px-3 text-center font-mono border-r border-[var(--border)]">15 pcs</td>
                              <td className="py-2.5 px-3 text-center font-mono border-r border-[var(--border)]">10 pcs</td>
                              <td className="py-2.5 px-3 text-center font-mono border-r border-[var(--border)]">6 pcs</td>
                              <td className="py-2.5 px-3 text-center font-mono border-r border-[var(--border)]">2 pcs</td>
                              <td className="py-2.5 px-3 text-right font-mono text-[var(--primary)] text-sm">44 pcs</td>
                            </tr>
                          </tfoot>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 2: FIFO BATCHES */}
                  {productDetailTab === 'fifo_batches' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div>
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Active FIFO Valuation Batches</h2>
                        <div className="text-[11px] text-[var(--muted-foreground)]">First-In First-Out cost allocation table used for precise COGS and net profit calculations</div>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Batch Number</th>
                              <th className="py-2.5 px-3">Received Date</th>
                              <th className="py-2.5 px-3">Supplier & GRN</th>
                              <th className="py-2.5 px-3 text-right">Unit Cost</th>
                              <th className="py-2.5 px-3 text-center">Initial Qty</th>
                              <th className="py-2.5 px-3 text-center">Remaining</th>
                              <th className="py-2.5 px-3 text-right">Batch Valuation</th>
                              <th className="py-2.5 px-3 text-center">Status</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { batch: 'BATCH-2026-10-A', date: '14 Oct 2026', grn: 'GRN-260192 (Heritage Loom)', cost: 850, init: 50, rem: 24, status: 'Active (Current)' },
                              { batch: 'BATCH-2026-09-C', date: '22 Sep 2026', grn: 'GRN-260145 (Heritage Loom)', cost: 820, init: 40, rem: 15, status: 'Active (Next)' },
                              { batch: 'BATCH-2026-08-F', date: '05 Aug 2026', grn: 'GRN-260089 (Fashion Silk Mills)', cost: 790, init: 60, rem: 5, status: 'Active' },
                              { batch: 'BATCH-2026-07-B', date: '12 Jul 2026', grn: 'GRN-260041 (Heritage Loom)', cost: 780, init: 50, rem: 0, status: 'Fully Sold (FIFO 0)' }
                            ].map((b, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-mono font-bold text-[var(--foreground)]">{b.batch}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{b.date}</td>
                                <td className="py-2.5 px-3 font-medium text-[var(--primary)]">{b.grn}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--foreground)]">{formatMoney(b.cost)}</td>
                                <td className="py-2.5 px-3 text-center font-mono">{b.init} pcs</td>
                                <td className="py-2.5 px-3 text-center font-mono font-bold text-[var(--foreground)]">{b.rem} pcs</td>
                                <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--foreground)]">{formatMoney(b.rem * b.cost)}</td>
                                <td className="py-2.5 px-3 text-center">
                                  <span className={`px-2 py-0.5 rounded-md border text-[10px] font-semibold ${
                                    b.rem > 0 ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30' : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)] border-[var(--border)]'
                                  }`}>
                                    {b.status}
                                  </span>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 3: MOVEMENT LEDGER */}
                  {productDetailTab === 'movement_ledger' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div>
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Item Stock Movement Ledger</h2>
                        <div className="text-[11px] text-[var(--muted-foreground)]">Comprehensive audit trail of all transactions affecting this product's inventory balance</div>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Date & Time</th>
                              <th className="py-2.5 px-3">Movement Type</th>
                              <th className="py-2.5 px-3">Reference / Order #</th>
                              <th className="py-2.5 px-3">Branch / Location</th>
                              <th className="py-2.5 px-3 text-right">Quantity</th>
                              <th className="py-2.5 px-3 text-right">Balance After</th>
                              <th className="py-2.5 px-3">Staff Operator</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { date: 'Today, 11:42 AM', type: 'Customer Sale', ref: 'DF1-R1-26000412', branch: 'Main [DF1]', delta: -2, bal: 44, staff: 'Sumi (Cashier)' },
                              { date: 'Today, 10:15 AM', type: 'Customer Sale', ref: 'DF1-R1-26000411', branch: 'Main [DF1]', delta: -1, bal: 46, staff: 'Sumi (Cashier)' },
                              { date: 'Yesterday, 04:30 PM', type: 'Branch Transfer Out', ref: 'TR-8921 → DF2', branch: 'Main [DF1]', delta: -5, bal: 47, staff: 'Rafiq (Stock)' },
                              { date: '27 Oct 2026, 02:10 PM', type: 'Customer Return', ref: 'RET-260012', branch: 'Main [DF1]', delta: +1, bal: 52, staff: 'Sumi (Cashier)' },
                              { date: '24 Oct 2026, 11:00 AM', type: 'Purchase GRN', ref: 'GRN-260192', branch: 'Main [DF1]', delta: +25, bal: 51, staff: 'Rafiq (Stock)' },
                              { date: '20 Oct 2026, 09:30 AM', type: 'Stocktake Audit', ref: 'ADJ-102 (Defect write-off)', branch: 'Main [DF1]', delta: -1, bal: 26, staff: 'Farhan (Manager)' }
                            ].map((m, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)] font-mono">{m.date}</td>
                                <td className="py-2.5 px-3">
                                  <span className={`px-2 py-0.5 rounded-md border text-[10px] font-semibold ${
                                    m.delta > 0 ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30' : 'bg-[var(--danger-soft)] text-[var(--danger)] border-[var(--danger)]/30'
                                  }`}>
                                    {m.type}
                                  </span>
                                </td>
                                <td className="py-2.5 px-3 font-mono text-[var(--primary)] font-semibold">{m.ref}</td>
                                <td className="py-2.5 px-3 text-[var(--foreground)]">{m.branch}</td>
                                <td className={`py-2.5 px-3 text-right font-mono font-bold ${m.delta > 0 ? 'text-[var(--success)]' : 'text-[var(--danger)]'}`}>
                                  {m.delta > 0 ? `+${m.delta}` : m.delta} pcs
                                </td>
                                <td className="py-2.5 px-3 text-right font-mono font-bold text-[var(--foreground)]">{m.bal} pcs</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{m.staff}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 4: SALES & PURCHASES HISTORY */}
                  {productDetailTab === 'sales_purchases' && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                      {/* Left: Recent Customer Sales */}
                      <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                        <div className="flex items-center justify-between">
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Recent Invoiced Sales</h2>
                          <span className="text-[11px] text-[var(--muted-foreground)]">Last 5 customer bills</span>
                        </div>
                        <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                          <table className="w-full text-left text-xs">
                            <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                              <tr>
                                <th className="py-2 px-2.5">Invoice #</th>
                                <th className="py-2 px-2.5">Customer</th>
                                <th className="py-2 px-2.5 text-center">Qty</th>
                                <th className="py-2 px-2.5 text-right">Total</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--border)]">
                              {[
                                { inv: 'DF1-R1-26000412', cust: 'Rahim Uddin (1000 0017)', qty: 2, total: 2900 },
                                { inv: 'DF1-R1-26000409', cust: 'Anika Tabassum (1000 0249)', qty: 1, total: 1450 },
                                { inv: 'DF1-R1-26000402', cust: 'Walk-in Customer', qty: 1, total: 1450 },
                                { inv: 'DF1-R1-26000395', cust: 'Kamal Hossain (1000 0301)', qty: 3, total: 4350 },
                                { inv: 'DF1-R1-26000388', cust: 'Farzana Yasmin (1000 0412)', qty: 1, total: 1450 }
                              ].map((s, idx) => (
                                <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                  <td className="py-2 px-2.5 font-mono text-[var(--primary)] font-semibold">{s.inv}</td>
                                  <td className="py-2 px-2.5 text-[var(--foreground)] truncate max-w-[140px]">{s.cust}</td>
                                  <td className="py-2 px-2.5 text-center font-mono">{s.qty}</td>
                                  <td className="py-2 px-2.5 text-right font-sans font-bold tabular-nums num">{formatMoney(s.total)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>

                      {/* Right: Supplier GRN Purchases */}
                      <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                        <div className="flex items-center justify-between">
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Purchase Inward Batches (GRN)</h2>
                          <span className="text-[11px] text-[var(--muted-foreground)]">Factory delivery orders</span>
                        </div>
                        <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                          <table className="w-full text-left text-xs">
                            <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                              <tr>
                                <th className="py-2 px-2.5">GRN #</th>
                                <th className="py-2 px-2.5">Supplier</th>
                                <th className="py-2 px-2.5 text-center">Received</th>
                                <th className="py-2 px-2.5 text-right">Cost Total</th>
                              </tr>
                            </thead>
                            <tbody className="divide-y divide-[var(--border)]">
                              {[
                                { grn: 'GRN-260192', vendor: 'Heritage Loom Ltd', qty: 25, total: 21250 },
                                { grn: 'GRN-260145', vendor: 'Heritage Loom Ltd', qty: 40, total: 32800 },
                                { grn: 'GRN-260089', vendor: 'Fashion Silk Mills', qty: 60, total: 47400 }
                              ].map((p, idx) => (
                                <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                  <td className="py-2 px-2.5 font-mono text-[var(--primary)] font-semibold">{p.grn}</td>
                                  <td className="py-2 px-2.5 text-[var(--foreground)] truncate max-w-[140px]">{p.vendor}</td>
                                  <td className="py-2 px-2.5 text-center font-mono">{p.qty} pcs</td>
                                  <td className="py-2 px-2.5 text-right font-sans font-bold tabular-nums num">{formatMoney(p.total)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* TAB 5: PRICE HISTORY CHART */}
                  {productDetailTab === 'price_history' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div>
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Price Revisions & Cost Evolution Chart</h2>
                        <div className="text-[11px] text-[var(--muted-foreground)]">Tracking retail selling price vs FIFO supplier cost trends over time</div>
                      </div>

                      <div className="h-48 w-full bg-[var(--surface-sunken)] rounded-xl p-3 border border-[var(--border)] flex flex-col justify-between">
                        <div className="flex items-center justify-between text-xs">
                          <span className="font-semibold text-[var(--muted-foreground)]">৳ Retail Price vs Cost Price (Past 6 Months)</span>
                          <div className="flex items-center gap-4 text-[10px]">
                            <span className="flex items-center gap-1.5"><span className="w-3 h-0.5 bg-[var(--primary)] inline-block"></span> Retail Price (৳)</span>
                            <span className="flex items-center gap-1.5"><span className="w-3 h-0.5 bg-[var(--warning)] inline-block"></span> Cost Price (৳)</span>
                          </div>
                        </div>

                        {/* Inline SVG Chart */}
                        <svg className="w-full h-32" viewBox="0 0 600 120" preserveAspectRatio="none">
                          <line x1="40" y1="20" x2="580" y2="20" stroke="var(--border)" strokeDasharray="3 3" />
                          <line x1="40" y1="60" x2="580" y2="60" stroke="var(--border)" strokeDasharray="3 3" />
                          <line x1="40" y1="100" x2="580" y2="100" stroke="var(--border)" />
                          <text x="35" y="24" fill="var(--muted-foreground)" fontSize="9" textAnchor="end">৳1,600</text>
                          <text x="35" y="64" fill="var(--muted-foreground)" fontSize="9" textAnchor="end">৳1,200</text>
                          <text x="35" y="104" fill="var(--muted-foreground)" fontSize="9" textAnchor="end">৳800</text>

                          {/* Retail Price Line (৳1,350 -> ৳1,450) */}
                          <polyline
                            fill="none"
                            stroke="var(--primary)"
                            strokeWidth="2.5"
                            points="60,50 160,50 260,45 360,45 460,38 560,38"
                          />
                          {/* Cost Price Line (৳780 -> ৳850) */}
                          <polyline
                            fill="none"
                            stroke="var(--warning)"
                            strokeWidth="2"
                            strokeDasharray="4 2"
                            points="60,105 160,102 260,98 360,98 460,92 560,92"
                          />

                          {/* Dots */}
                          <circle cx="560" cy="38" r="3.5" fill="var(--primary)" />
                          <circle cx="560" cy="92" r="3.5" fill="var(--warning)" />
                        </svg>

                        <div className="flex justify-between text-[10px] text-[var(--muted-foreground)] px-8 font-mono">
                          <span>May 2026</span>
                          <span>Jun 2026</span>
                          <span>Jul 2026</span>
                          <span>Aug 2026</span>
                          <span>Sep 2026</span>
                          <span>Oct 2026 (Current)</span>
                        </div>
                      </div>

                      {/* Revision Table */}
                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2 px-3">Effective Date</th>
                              <th className="py-2 px-3">Old Selling Price</th>
                              <th className="py-2 px-3">New Selling Price</th>
                              <th className="py-2 px-3">Revised Cost</th>
                              <th className="py-2 px-3">Approved By</th>
                              <th className="py-2 px-3">Reason for Revision</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            <tr className="hover:bg-[var(--surface-sunken)]/50">
                              <td className="py-2 px-3 text-[var(--muted-foreground)] font-mono">14 Oct 2026</td>
                              <td className="py-2 px-3 font-sans tabular-nums num text-[var(--muted-foreground)] line-through">৳ 1,350</td>
                              <td className="py-2 px-3 font-sans font-bold tabular-nums num text-[var(--primary)]">৳ 1,450</td>
                              <td className="py-2 px-3 font-sans tabular-nums num text-[var(--foreground)]">৳ 850</td>
                              <td className="py-2 px-3 font-medium">Farhan (Manager)</td>
                              <td className="py-2 px-3 text-[var(--muted-foreground)]">Yarn cost inflation & new festive weave margin adjustment</td>
                            </tr>
                            <tr className="hover:bg-[var(--surface-sunken)]/50">
                              <td className="py-2 px-3 text-[var(--muted-foreground)] font-mono">10 Jul 2026</td>
                              <td className="py-2 px-3 font-sans tabular-nums num text-[var(--muted-foreground)] line-through">৳ 1,280</td>
                              <td className="py-2 px-3 font-sans font-bold tabular-nums num text-[var(--primary)]">৳ 1,350</td>
                              <td className="py-2 px-3 font-sans tabular-nums num text-[var(--foreground)]">৳ 780</td>
                              <td className="py-2 px-3 font-medium">Tanvir (Owner)</td>
                              <td className="py-2 px-3 text-[var(--muted-foreground)]">Pre-Eid seasonal tariff update</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                </div>
              )}

              {/* ================= SCREEN: VENDOR 360 PROFILE (vendor_profile) ================= */}
              {currentScreen === 'vendor_profile' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  {/* Top Bar with Back Navigation & Action Buttons */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[var(--border)] gap-2">
                    <div className="flex items-center gap-2.5">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('vendors')}
                        className="h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition-colors cursor-pointer"
                      >
                        <Icon name="arrowLeft" size={14} />
                        <span>Back to Vendors</span>
                      </button>
                      <div className="h-4 w-px bg-[var(--border)]" />
                      <div>
                        <h1 className="text-base font-bold text-[var(--foreground)] flex items-center gap-2">
                          <span>{selectedVendor?.name || 'Vendor Profile'}</span>
                          <span className="font-bangla text-xs font-normal text-[var(--muted-foreground)]">({selectedVendor?.name_bn || 'টেক্সটাইল সরবরাহকারী'})</span>
                        </h1>
                        <div className="text-[11px] text-[var(--muted-foreground)]">
                          Vendor Code: <strong className="font-mono text-[var(--primary)]">{selectedVendor?.code || 'V-1001'}</strong> · Hub: {selectedVendor?.location || 'Dhaka'} · Contact: {selectedVendor?.contact || selectedVendor?.phone || '01711-223344'}
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => {
                          const due = (selectedVendor?.balance_due !== undefined ? selectedVendor.balance_due : selectedVendor?.payable) || 45200;
                          setPayVendorAmount(String(due));
                          setShowPayVendorModal(true);
                        }}
                        className="h-8 px-3.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer hover:bg-[var(--primary-hover)]"
                      >
                        <Icon name="dollarSign" size={14} />
                        <span>Pay Vendor (Bill Allocation)</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowNewPurchaseModal(true)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--foreground)] cursor-pointer"
                      >
                        <Icon name="plus" size={14} />
                        <span>New Purchase Order (GRN)</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          addToast("Vendor statement PDF generated", "success");
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 text-[var(--foreground)] cursor-pointer"
                      >
                        <Icon name="printer" size={14} />
                        <span>Export Statement</span>
                      </button>
                    </div>
                  </div>

                  {/* Vendor Financial Summary KPI Cards */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Total Purchases YTD</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--foreground)] mt-1">
                        ৳ 3,85,000
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">12 Delivered Shipments</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Disbursed Payments</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--success)] mt-1">
                        ৳ 3,39,800
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Via Bank & Cheque</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Outstanding Payable</div>
                      <div className="font-sans font-bold text-lg tabular-nums num text-[var(--danger)] mt-1">
                        {formatMoney((selectedVendor?.balance_due !== undefined ? selectedVendor.balance_due : selectedVendor?.payable) || 45200)}
                      </div>
                      <div className="text-[10px] text-[var(--danger)] font-medium">3 Unpaid / Partial Bills</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider">Payment Credit Terms</div>
                      <div className="font-semibold text-sm text-[var(--foreground)] mt-1">
                        30 Days Net
                      </div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Lead Time: 3 Business Days</div>
                    </div>
                  </div>

                  {/* Sub-Navigation Tabs */}
                  <div className="flex items-center gap-1 bg-[var(--surface-sunken)] p-1 rounded-xl border border-[var(--border)] text-xs font-semibold w-fit">
                    {[
                      { id: 'info', label: 'Vendor Profile & Bank Info', icon: 'user' },
                      { id: 'purchases', label: 'Purchases & Bills (GRN)', icon: 'truck' },
                      { id: 'payments', label: 'Payment Disbursements', icon: 'dollarSign' },
                      { id: 'returns', label: 'Debit Notes & Returns', icon: 'refresh' },
                      { id: 'statement', label: 'Statement Ledger', icon: 'fileText' }
                    ].map(tab => (
                      <button
                        key={tab.id}
                        type="button"
                        onClick={() => setVendorProfileTab(tab.id)}
                        className={`h-7 px-3 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer ${
                          vendorProfileTab === tab.id ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        <Icon name={tab.icon} size={13} />
                        <span>{tab.label}</span>
                      </button>
                    ))}
                  </div>

                  {/* TAB 1: INFO & BANK DETAILS */}
                  {vendorProfileTab === 'info' && (
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                      {/* Left: Supplier Commercial Profile */}
                      <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Supplier Registration Details</h2>
                        <div className="space-y-2 text-xs divide-y divide-[var(--border)]">
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Legal Trade Name:</span>
                            <span className="font-semibold text-[var(--foreground)]">{selectedVendor?.name}</span>
                          </div>
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Key Contact Person:</span>
                            <span className="font-medium text-[var(--foreground)]">{selectedVendor?.contact_person || 'Md. Jahangir Alam (Managing Director)'}</span>
                          </div>
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Mobile / Phone:</span>
                            <span className="font-mono font-medium text-[var(--foreground)]">{selectedVendor?.contact || selectedVendor?.phone || '01711-889900'}</span>
                          </div>
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Email Address:</span>
                            <span className="font-mono text-[var(--primary)]">{selectedVendor?.email || 'orders@aarongtextile.com.bd'}</span>
                          </div>
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Business BIN Number:</span>
                            <span className="font-mono font-semibold text-[var(--foreground)]">{selectedVendor?.bin || '001928374-0101'}</span>
                          </div>
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Trade License:</span>
                            <span className="font-mono text-[var(--foreground)]">TRAD/DSCC/092817/2023</span>
                          </div>
                          <div className="flex justify-between py-1.5">
                            <span className="text-[var(--muted-foreground)]">Factory / Warehouse:</span>
                            <span className="text-right text-[var(--foreground)]">{selectedVendor?.location || 'Baburhat, Narsingdi & Tejgaon I/A, Dhaka'}</span>
                          </div>
                        </div>
                      </div>

                      {/* Right: Bank Disbursement Accounts */}
                      <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Settlement Bank Account & Terms</h2>
                        <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-2 text-xs">
                          <div className="flex items-center gap-2 text-[var(--primary)] font-bold text-sm">
                            <Icon name="bank" size={16} />
                            <span>City Bank Ltd (Current Account)</span>
                          </div>
                          <div className="space-y-1 font-mono text-[11px] pt-1">
                            <div className="flex justify-between"><span className="text-[var(--muted-foreground)] font-sans">Account Name:</span><span className="font-bold">{selectedVendor?.name}</span></div>
                            <div className="flex justify-between"><span className="text-[var(--muted-foreground)] font-sans">Account Number:</span><span className="font-bold">1102948192001</span></div>
                            <div className="flex justify-between"><span className="text-[var(--muted-foreground)] font-sans">Routing Number:</span><span>085261492</span></div>
                            <div className="flex justify-between"><span className="text-[var(--muted-foreground)] font-sans">Branch:</span><span>Tejgaon Industrial Area</span></div>
                          </div>
                        </div>

                        <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-2 text-xs">
                          <div className="flex items-center gap-2 text-[var(--success)] font-bold text-sm">
                            <Icon name="smartphone" size={16} />
                            <span>bKash Merchant Gateway</span>
                          </div>
                          <div className="space-y-1 font-mono text-[11px] pt-1">
                            <div className="flex justify-between"><span className="text-[var(--muted-foreground)] font-sans">Merchant A/C:</span><span className="font-bold">01811-223344</span></div>
                            <div className="flex justify-between"><span className="text-[var(--muted-foreground)] font-sans">Counter Ref:</span><span>DF-PURCHASE</span></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* TAB 2: PURCHASES / BILLS */}
                  {vendorProfileTab === 'purchases' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div className="flex items-center justify-between">
                        <div>
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Purchase Inward Bills & GRN Ledger</h2>
                          <div className="text-[11px] text-[var(--muted-foreground)]">Inventory bills received from {selectedVendor?.name}</div>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowNewPurchaseModal(true)}
                          className="h-7 px-2.5 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1 cursor-pointer"
                        >
                          <Icon name="plus" size={13} />
                          <span>New Purchase</span>
                        </button>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">GRN #</th>
                              <th className="py-2.5 px-3">Date</th>
                              <th className="py-2.5 px-3">Store Branch</th>
                              <th className="py-2.5 px-3 text-right">Bill Total</th>
                              <th className="py-2.5 px-3 text-right">Paid Amount</th>
                              <th className="py-2.5 px-3 text-right">Balance Due</th>
                              <th className="py-2.5 px-3 text-center">Status</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { grn: 'GRN-260192', date: '26 Oct 2026', branch: 'Main [DF1]', total: 125000, paid: 95000, due: 30000, status: 'Partial Due' },
                              { grn: 'GRN-260164', date: '18 Oct 2026', branch: 'Dhanmondi [DF2]', total: 85000, paid: 69800, due: 15200, status: 'Partial Due' },
                              { grn: 'GRN-260132', date: '02 Oct 2026', branch: 'Main [DF1]', total: 175000, paid: 175000, due: 0, status: 'Fully Paid' }
                            ].map((p, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-mono font-bold text-[var(--primary)]">{p.grn}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{p.date}</td>
                                <td className="py-2.5 px-3 text-[var(--foreground)]">{p.branch}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--foreground)]">{formatMoney(p.total)}</td>
                                <td className="py-2.5 px-3 text-right font-sans tabular-nums num text-[var(--success)]">{formatMoney(p.paid)}</td>
                                <td className={`py-2.5 px-3 text-right font-sans font-bold tabular-nums num ${p.due > 0 ? 'text-[var(--danger)]' : 'text-[var(--muted-foreground)]'}`}>
                                  {formatMoney(p.due)}
                                </td>
                                <td className="py-2.5 px-3 text-center">
                                  <span className={`px-2 py-0.5 rounded-md border text-[10px] font-semibold ${
                                    p.status === 'Fully Paid' ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]/30' : 'bg-[var(--warning-soft)] text-[var(--warning)] border-[var(--warning)]/30'
                                  }`}>
                                    {p.status}
                                  </span>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 3: PAYMENTS DISBURSED */}
                  {vendorProfileTab === 'payments' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div>
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Supplier Disbursements & Wire Receipts</h2>
                        <div className="text-[11px] text-[var(--muted-foreground)]">Payment history with bill allocation breakdown</div>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Voucher #</th>
                              <th className="py-2.5 px-3">Date</th>
                              <th className="py-2.5 px-3">Payment Method</th>
                              <th className="py-2.5 px-3 text-right">Amount Paid</th>
                              <th className="py-2.5 px-3">Allocated to Bills</th>
                              <th className="py-2.5 px-3">Bank Reference #</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { v: 'PV-2026-084', date: '20 Oct 2026', method: 'Bank Transfer (City Bank)', amt: 65000, bills: 'GRN-260164', ref: 'TXN-CB-9182049' },
                              { v: 'PV-2026-071', date: '05 Oct 2026', method: 'Cheque #928410', amt: 85000, bills: 'GRN-260132', ref: 'CHQ-928410' },
                              { v: 'PV-2026-052', date: '15 Sep 2026', method: 'bKash Merchant', amt: 90000, bills: 'GRN-260132', ref: 'BK-TRX-00192' }
                            ].map((p, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-mono font-bold text-[var(--foreground)]">{p.v}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{p.date}</td>
                                <td className="py-2.5 px-3 font-medium text-[var(--foreground)]">{p.method}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--success)]">{formatMoney(p.amt)}</td>
                                <td className="py-2.5 px-3 font-mono text-[var(--primary)]">{p.bills}</td>
                                <td className="py-2.5 px-3 font-mono text-[var(--muted-foreground)]">{p.ref}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 4: RETURNS / DEBIT NOTES */}
                  {vendorProfileTab === 'returns' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div>
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Purchase Debit Notes & Fabric Defect Returns</h2>
                        <div className="text-[11px] text-[var(--muted-foreground)]">Claims issued against defective goods returned to supplier</div>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Debit Note #</th>
                              <th className="py-2.5 px-3">Date</th>
                              <th className="py-2.5 px-3">Claim Reason</th>
                              <th className="py-2.5 px-3 text-right">Claim Amount</th>
                              <th className="py-2.5 px-3 text-center">Settlement Status</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { dn: 'DN-2026-012', date: '15 Oct 2026', reason: 'Shade variation in fabric batch #92', amt: 14200, status: 'Adjusted in Bill GRN-260192' },
                              { dn: 'DN-2026-008', date: '28 Sep 2026', reason: 'Defective button stitching & dye bleed', amt: 8500, status: 'Credit Note Issued' }
                            ].map((d, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-mono font-bold text-[var(--warning)]">{d.dn}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{d.date}</td>
                                <td className="py-2.5 px-3 text-[var(--foreground)]">{d.reason}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--danger)]">−{formatMoney(d.amt)}</td>
                                <td className="py-2.5 px-3 text-center">
                                  <span className="px-2 py-0.5 rounded-md border border-[var(--success)]/30 text-[10px] font-semibold bg-[var(--success-soft)] text-[var(--success)]">
                                    {d.status}
                                  </span>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 5: RUNNING STATEMENT LEDGER */}
                  {vendorProfileTab === 'statement' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div className="flex items-center justify-between">
                        <div>
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Chronological Statement of Accounts</h2>
                          <div className="text-[11px] text-[var(--muted-foreground)]">Running payable balance audit ledger for {selectedVendor?.name}</div>
                        </div>
                        <button
                          type="button"
                          onClick={() => {
                            exportToCSV('vendor_statement_' + Date.now() + '.csv', [
                              { id: 'date', header: 'Date' },
                              { id: 'type', header: 'Type' },
                              { id: 'ref', header: 'Ref #' },
                              { id: 'debit', header: 'Debit (Paid)' },
                              { id: 'credit', header: 'Credit (Billed)' },
                              { id: 'balance', header: 'Balance Due' }
                            ], [
                              { date: '26 Oct 2026', type: 'Purchase GRN', ref: 'GRN-260192', debit: 0, credit: 125000, balance: 45200 },
                              { date: '20 Oct 2026', type: 'Payment Disbursed', ref: 'PV-2026-084', debit: 65000, credit: 0, balance: -79800 },
                              { date: '18 Oct 2026', type: 'Purchase GRN', ref: 'GRN-260164', debit: 0, credit: 85000, balance: 144800 },
                              { date: '15 Oct 2026', type: 'Debit Note Return', ref: 'DN-2026-012', debit: 14200, credit: 0, balance: 59800 }
                            ]);
                            addToast("Statement exported to CSV", "success");
                          }}
                          className="h-7 px-3 rounded bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 cursor-pointer"
                        >
                          <Icon name="fileText" size={13} />
                          <span>Export Statement CSV</span>
                        </button>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Date</th>
                              <th className="py-2.5 px-3">Transaction Description</th>
                              <th className="py-2.5 px-3">Reference #</th>
                              <th className="py-2.5 px-3 text-right">Debit (Paid ৳)</th>
                              <th className="py-2.5 px-3 text-right">Credit (Billed ৳)</th>
                              <th className="py-2.5 px-3 text-right">Running Balance</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {[
                              { date: '26 Oct 2026', desc: 'Fabric shipment received at Uttara Main', ref: 'GRN-260192', debit: 0, credit: 125000, bal: 45200 },
                              { date: '20 Oct 2026', desc: 'Wire transfer payment disbursed', ref: 'PV-2026-084', debit: 65000, credit: 0, bal: -79800 },
                              { date: '18 Oct 2026', desc: 'Winter collection goods received', ref: 'GRN-260164', debit: 0, credit: 85000, bal: 144800 },
                              { date: '15 Oct 2026', desc: 'Dye shade discrepancy adjustment', ref: 'DN-2026-012', debit: 14200, credit: 0, bal: 59800 }
                            ].map((row, idx) => (
                              <tr key={idx} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)] font-mono">{row.date}</td>
                                <td className="py-2.5 px-3 text-[var(--foreground)]">{row.desc}</td>
                                <td className="py-2.5 px-3 font-mono text-[var(--primary)] font-semibold">{row.ref}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-medium tabular-nums num text-[var(--success)]">{row.debit > 0 ? formatMoney(row.debit) : '—'}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-medium tabular-nums num text-[var(--danger)]">{row.credit > 0 ? formatMoney(row.credit) : '—'}</td>
                                <td className="py-2.5 px-3 text-right font-sans font-bold tabular-nums num text-[var(--foreground)]">{formatMoney(row.bal)}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}
                </div>
              )}
"""
