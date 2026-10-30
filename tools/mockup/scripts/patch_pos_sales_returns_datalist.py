import re

with open('scripts/parts/render_pos_sales_returns.py', 'r', encoding='utf-8') as f:
    content = f.read()

# Replacement 1: SCREEN 2 (Sales)
old_screen_2_pattern = re.compile(r'\{\/\* ================= SCREEN 2: SALES HISTORY.*?\{\/\* ================= SCREEN 3: SALE DETAIL VIEW', re.DOTALL)

new_screen_2 = '''{/* ================= SCREEN 2: SALES HISTORY (G S) ================= */}
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

              {/* ================= SCREEN 3: SALE DETAIL VIEW'''

content = old_screen_2_pattern.sub(new_screen_2, content)

# Replacement 2: SCREEN 4 (Due Collection)
old_screen_4_pattern = re.compile(r'\{\/\* ================= SCREEN 4: DUE COLLECTION LEDGER.*?\{\/\* ================= SCREEN 5: RETURNS HISTORY', re.DOTALL)

new_screen_4 = '''{/* ================= SCREEN 4: DUE COLLECTION LEDGER (G U) ================= */}
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
                        <div className="text-lg font-bold text-[var(--foreground)] mt-1 font-mono num">{formatMoney(card.amount)}</div>
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

              {/* ================= SCREEN 5: RETURNS HISTORY'''

content = old_screen_4_pattern.sub(new_screen_4, content)

# Replacement 3: SCREEN 5 (Returns)
old_screen_5_pattern = re.compile(r'\{\/\* ================= SCREEN 5: RETURNS HISTORY.*?\Z', re.DOTALL)

new_screen_5 = '''{/* ================= SCREEN 5: RETURNS HISTORY & DETAIL (G T) ================= */}
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
                    <button
                      type="button"
                      onClick={() => {
                        startReturnModeForInvoice('DF1-R1-26000412');
                        setCurrentScreen('pos');
                      }}
                      className="h-8 px-3 rounded-lg bg-[var(--warning)] hover:bg-amber-600 text-white text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                      <Icon name="refresh" size={14} />
                      <span>Start Return in POS (F11)</span>
                    </button>
                  }
                  onRowClick={(r) => {
                    startReturnModeForInvoice(r.invoice || r.original_invoice);
                    setCurrentScreen('pos');
                  }}
                  onRowContextMenu={(r, e) => {
                    openContextMenu(e, r.return_no, [
                      { label: 'View Original Invoice Details', action: () => {
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
"""
'''

content = old_screen_5_pattern.sub(new_screen_5, content)

with open('scripts/parts/render_pos_sales_returns.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_pos_sales_returns.py patched successfully!")
