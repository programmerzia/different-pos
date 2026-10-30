import re

with open('scripts/parts/render_catalog_stock_purchases.py', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Replace Products subtab
old_prod_pattern = re.compile(r'\{\/\* SUB-TAB 1: PRODUCTS & SKUS \*\/}.*?\{\/\* SUB-TAB 2: CATEGORY', re.DOTALL)

new_prod = '''{/* SUB-TAB 1: PRODUCTS & SKUS */}
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
                                <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                  st === 'Out of Stock'
                                    ? 'bg-[var(--danger-soft)] text-[var(--danger)]'
                                    : st === 'Low Stock'
                                      ? 'bg-[var(--warning-soft)] text-[var(--warning)]'
                                      : 'bg-[var(--success-soft)] text-[var(--success)]'
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
                              addToast('Loaded ' + rows.length + ' products into bulk barcode generator', 'info');
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
                        onRowClick={p => openEditProductForm(p)}
                        onRowContextMenu={(p, e) => {
                          openContextMenu(e, p.name_en, [
                            { label: 'Edit Product', action: () => openEditProductForm(p) },
                            { label: 'Check Branch Stock', action: () => { setStockCheckQuery(p.sku); setShowStockCheckModal(true); } },
                            { label: 'Stock Adjustment (Damaged/Audit)', action: () => { setSelectedProduct(p); setShowStockAdjustmentModal(true); } },
                            { label: 'Transfer to Branch 2', action: () => { setSelectedProduct(p); setShowStockTransferModal(true); } },
                            { label: 'Print Barcode Labels', action: () => { setSelectedProduct(p); setShowBarcodeLabelModal(true); } }
                          ]);
                        }}
                      />
                    </div>
                  )}

                  {/* SUB-TAB 2: CATEGORY'''

content = old_prod_pattern.sub(new_prod, content)

# 2. Replace Stock Screen 13
old_stock_pattern = re.compile(r'\{\/\* ================= SCREEN 13: STOCK & TRANSFERS.*?\{\/\* ================= SCREEN 14: PURCHASES', re.DOTALL)

new_stock = '''{/* ================= SCREEN 13: STOCK & TRANSFERS (G K) ================= */}
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
                        addToast('Initiating transfer for ' + rows.length + ' products', 'info');
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
                      { label: 'Inter-Branch Transfer', action: () => { setSelectedProduct(p); setShowStockTransferModal(true); } },
                      { label: 'Stock Adjustment (Damaged/Audit)', action: () => { setSelectedProduct(p); setShowStockAdjustmentModal(true); } },
                      { label: 'Check Branch Stock', action: () => { setStockCheckQuery(p.sku); setShowStockCheckModal(true); } }
                    ]);
                  }}
                />
              )}

              {/* ================= SCREEN 14: PURCHASES'''

content = old_stock_pattern.sub(new_stock, content)

# 3. Replace Purchases Screen 14
old_purchases_pattern = re.compile(r'\{\/\* ================= SCREEN 14: PURCHASES & GRN.*?\{\/\* ================= SCREEN 15: VENDOR', re.DOTALL)

new_purchases = '''{/* ================= SCREEN 14: PURCHASES & GRN (G P) ================= */}
              {currentScreen === 'purchases' && (
                <div className="flex-1 flex flex-col overflow-hidden space-y-3">
                  {/* Summary Metric Cards */}
                  <div className="p-4 pb-0 grid grid-cols-2 md:grid-cols-4 gap-3 shrink-0">
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs">
                      <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Total Inward Bills (MTD)</div>
                      <div className="text-base font-bold font-mono text-[var(--foreground)] mt-0.5">{formatMoney(purchasesList.reduce((acc, p) => acc + (p.total || 0), 0))}</div>
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
                        {
                          id: 'branch',
                          label: 'Branch',
                          options: [
                            { label: 'All Branches', value: 'all' },
                            { label: 'Main Branch [DF1]', value: 'Main' },
                            { label: 'Branch 2 [DF2]', value: 'Branch 2' }
                          ]
                        }
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
                        { id: 'b2', label: 'Branch 2 Deliveries', filters: { branch: 'Branch 2' } }
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
                            addToast('Opened batch vendor payment allocation for ' + rows.length + ' vouchers', 'info');
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

              {/* ================= SCREEN 15: VENDOR'''

content = old_purchases_pattern.sub(new_purchases, content)

# 4. Replace Vendors Screen 15
old_vendor_pattern = re.compile(r'\{\/\* ================= SCREEN 15: VENDOR DIRECTORY.*?\Z', re.DOTALL)

new_vendor = '''{/* ================= SCREEN 15: VENDOR DIRECTORY (G V) ================= */}
              {currentScreen === 'vendors' && (
                <DataList
                  id="vendors"
                  title="Vendor & Supplier Directory"
                  subtitle="Apparel mills, fabric manufacturers, packaging suppliers, and payables ledger"
                  data={MOCK_VENDORS}
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
                      onClick={() => addToast('Add Vendor modal opened', 'info')}
                      className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                      <Icon name="plus" size={14} />
                      <span>Add Vendor</span>
                    </button>
                  }
                  onRowClick={v => {
                    setSelectedVendor(v);
                    const due = (v.balance_due !== undefined ? v.balance_due : v.payable) || 0;
                    setPayVendorAmount(String(due));
                    setShowPayVendorModal(true);
                  }}
                  onRowContextMenu={(v, e) => {
                    openContextMenu(e, v.name, [
                      { label: 'Pay Vendor Bill', action: () => {
                        setSelectedVendor(v);
                        const due = (v.balance_due !== undefined ? v.balance_due : v.payable) || 0;
                        setPayVendorAmount(String(due));
                        setShowPayVendorModal(true);
                      }},
                      { label: 'View Purchase Ledger', action: () => { setCurrentScreen('purchases'); } },
                      { label: 'Copy Phone Number', action: () => {
                        navigator.clipboard ? navigator.clipboard.writeText(v.contact || v.phone || '') : null;
                        addToast('Vendor phone copied', 'info');
                      }}
                    ]);
                  }}
                />
              )}
"""
'''

content = old_vendor_pattern.sub(new_vendor, content)

with open('scripts/parts/render_catalog_stock_purchases.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_catalog_stock_purchases.py patched successfully!")
