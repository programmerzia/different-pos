import re

with open('scripts/parts/render_customers_messaging.py', 'r', encoding='utf-8') as f:
    content = f.read()

old_screen_6_pattern = re.compile(r'\{\/\* ================= SCREEN 6: CUSTOMER DIRECTORY.*?\{\/\* ================= SCREEN 7: CUSTOMER ADD', re.DOTALL)

new_screen_6 = '''{/* ================= SCREEN 6: CUSTOMER DIRECTORY (G C) ================= */}
              {currentScreen === 'customers' && (
                <DataList
                  id="customers"
                  title="Customers & Members"
                  subtitle="360° customer directory with loyalty tiers, card statuses, and due tracking"
                  data={MOCK_CUSTOMERS}
                  keyField="id"
                  searchPlaceholder="Search name, phone, or #1000 0017... (/)"
                  searchFields={['customer_no', 'name_en', 'name_bn', 'phone', 'district', 'city_upazila', 'group']}
                  columns={[
                    {
                      id: 'customer_no',
                      header: 'Customer #',
                      sortable: true,
                      className: 'font-mono font-semibold text-[var(--primary)]',
                      accessor: c => c.customer_no,
                      cell: c => (
                        <span className="font-mono font-semibold text-[var(--primary)] group-hover:underline">
                          {c.customer_no}
                        </span>
                      )
                    },
                    {
                      id: 'name',
                      header: 'Name (EN / BN)',
                      sortable: true,
                      accessor: c => c.name_en,
                      cell: c => (
                        <div>
                          <div className="font-semibold text-[var(--foreground)]">{c.name_en}</div>
                          <div className="text-[10px] text-[var(--muted-foreground)] font-bangla">{c.name_bn}</div>
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
                      id: 'location',
                      header: 'District & Upazila',
                      sortable: true,
                      accessor: c => c.district,
                      cell: c => (
                        <div>
                          <div className="text-[var(--foreground)] font-medium truncate max-w-[200px]">{c.street_address || (c.city_upazila + ', ' + c.district)}</div>
                          <div className="text-[10px] text-[var(--muted-foreground)] truncate">{c.city_upazila}, {c.district} · {c.postal_code || '1230'}</div>
                        </div>
                      )
                    },
                    {
                      id: 'group',
                      header: 'Tier / Group',
                      sortable: true,
                      accessor: c => c.group,
                      cell: c => (
                        <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                          (c.group || '').includes('VIP')
                            ? 'bg-[var(--accent)]/15 text-[var(--accent)]'
                            : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)]'
                        }`}>
                          {c.group}
                        </span>
                      )
                    },
                    {
                      id: 'balance',
                      header: 'Balance / Due',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: c => c.balance,
                      cell: c => (
                        <span className={`font-sans font-bold tabular-nums num ${c.balance < 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]'}`}>
                          {formatMoney(c.balance)}
                        </span>
                      )
                    },
                    {
                      id: 'credit_limit',
                      header: 'Credit Limit',
                      type: 'money',
                      align: 'right',
                      sortable: true,
                      accessor: c => c.credit_limit || 20000,
                      cell: c => (
                        <span className="font-sans font-semibold tabular-nums num text-[var(--muted-foreground)]">
                          {formatMoney(c.credit_limit || 20000)}
                        </span>
                      )
                    },
                    {
                      id: 'card_status',
                      header: 'Card Status',
                      sortable: true,
                      accessor: c => c.card_status,
                      cell: c => (
                        <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${
                          c.card_status === 'Active'
                            ? 'bg-[var(--success-soft)] text-[var(--success)]'
                            : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)]'
                        }`}>
                          {c.card_status}
                        </span>
                      )
                    },
                    {
                      id: 'pin_status',
                      header: 'PIN Status',
                      sortable: true,
                      accessor: c => c.pin_status,
                      cell: c => (
                        <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${
                          c.pin_status === 'Verified' || c.pin_status === 'Set'
                            ? 'bg-[var(--success-soft)] text-[var(--success)]'
                            : 'bg-[var(--warning-soft)] text-[var(--warning)]'
                        }`}>
                          {c.pin_status}
                        </span>
                      )
                    },
                    {
                      id: 'actions',
                      header: 'Actions',
                      align: 'right',
                      hideable: false,
                      cell: c => (
                        <div className="flex items-center justify-end gap-1" onClick={e => e.stopPropagation()}>
                          <button
                            type="button"
                            onClick={() => {
                              setCardCustomer(c);
                              setShowCustomerCardModal(true);
                            }}
                            className="p-1 rounded hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--primary)] cursor-pointer"
                            title="Print Customer Card"
                          >
                            <Icon name="idCard" size={14} />
                          </button>
                          <button
                            type="button"
                            onClick={() => {
                              setSelectedCustomer(c);
                              setCurrentScreen('customer_profile');
                            }}
                            className="p-1 rounded hover:bg-[var(--surface-sunken)] text-[var(--muted-foreground)] hover:text-[var(--foreground)] cursor-pointer"
                            title="View Customer Profile"
                          >
                            <Icon name="eye" size={14} />
                          </button>
                        </div>
                      )
                    }
                  ]}
                  filterChips={[
                    {
                      id: 'group',
                      label: 'Tier / Group',
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
                      id: 'balance_filter',
                      label: 'Account Balance Status',
                      options: [
                        { label: 'All Balances', value: 'all' },
                        { label: 'Has Overdue Balance (<৳0)', value: 'due' },
                        { label: 'Zero Balance (৳0)', value: 'zero' },
                        { label: 'Advance Store Credit (>৳0)', value: 'advance' }
                      ]
                    },
                    {
                      id: 'card_status',
                      label: 'Physical / Digital Card',
                      options: [
                        { label: 'All Card States', value: 'all' },
                        { label: 'Active Cards Only', value: 'Active' },
                        { label: 'Inactive / Lost', value: 'Inactive' }
                      ]
                    },
                    {
                      id: 'pin_status',
                      label: 'Security PIN Status',
                      options: [
                        { label: 'All PIN States', value: 'all' },
                        { label: 'Verified / Set', value: 'Verified' },
                        { label: 'Pending / Unset', value: 'Pending' }
                      ]
                    }
                  ]}
                  savedViews={[
                    { id: 'default', label: 'Default (All Customers)' },
                    { id: 'vip', label: 'VIP Wholesale Privilege', filters: { group: 'VIP Wholesale' } },
                    { id: 'overdue', label: 'Overdue Due Debtors', filters: { balance_filter: 'due' } }
                  ]}
                  bulkActions={[
                    {
                      label: 'Export Customers',
                      icon: 'fileText',
                      action: (rows) => {
                        exportToCSV('customers_directory_' + Date.now() + '.csv', [
                          { id: 'customer_no', header: 'Customer No' },
                          { id: 'name_en', header: 'Name EN' },
                          { id: 'name_bn', header: 'Name BN' },
                          { id: 'phone', header: 'Phone' },
                          { id: 'group', header: 'Group' },
                          { id: 'balance', header: 'Balance' },
                          { id: 'district', header: 'District' }
                        ], rows);
                        addToast('Exported ' + rows.length + ' customers to CSV', 'success');
                      }
                    },
                    {
                      label: 'Print Membership Cards',
                      icon: 'idCard',
                      action: (rows) => {
                        addToast('Queued ' + rows.length + ' membership cards for batch printing', 'info');
                      }
                    },
                    {
                      label: 'Send Bulk SMS',
                      icon: 'send',
                      action: (rows) => {
                        addToast('Prepared promotional SMS blast for ' + rows.length + ' customers', 'info');
                      }
                    }
                  ]}
                  customFilter={(c, filters) => {
                    if (filters.balance_filter && filters.balance_filter !== 'all') {
                      if (filters.balance_filter === 'due' && c.balance >= 0) return false;
                      if (filters.balance_filter === 'zero' && c.balance !== 0) return false;
                      if (filters.balance_filter === 'advance' && c.balance <= 0) return false;
                    }
                    if (filters.card_status && filters.card_status !== 'all' && c.card_status !== filters.card_status) return false;
                    if (filters.pin_status && filters.pin_status !== 'all') {
                      if (filters.pin_status === 'Verified' && c.pin_status !== 'Verified' && c.pin_status !== 'Set') return false;
                      if (filters.pin_status === 'Pending' && (c.pin_status === 'Verified' || c.pin_status === 'Set')) return false;
                    }
                    return true;
                  }}
                  headerActions={
                    <button
                      type="button"
                      onClick={() => setCurrentScreen('customer_form')}
                      className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                      <Icon name="plus" size={14} />
                      <span>Add Customer</span>
                    </button>
                  }
                  onRowClick={(c) => {
                    setSelectedCustomer(c);
                    setCustomerTab('overview');
                    setCurrentScreen('customer_profile');
                  }}
                  onRowContextMenu={(c, e) => {
                    openContextMenu(e, c.name_en, [
                      { label: 'View 360 Profile', action: () => { setSelectedCustomer(c); setCurrentScreen('customer_profile'); } },
                      { label: 'Collect Outstanding Due', action: () => { setDueCollectCustomer(c); setDueCollectAmount(Math.abs(c.balance).toString()); setShowDueCollectModal(true); } },
                      { label: 'Send WhatsApp Reminder', action: () => { setReminderCustomer(c); setShowReminderModal(true); } },
                      { label: 'View Membership Card', action: () => { setCardCustomer(c); setShowCustomerCardModal(true); } }
                    ]);
                  }}
                />
              )}

              {/* ================= SCREEN 7: CUSTOMER ADD'''

content = old_screen_6_pattern.sub(new_screen_6, content)

with open('scripts/parts/render_customers_messaging.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_customers_messaging.py patched successfully!")
