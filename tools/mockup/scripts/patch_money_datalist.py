import re

with open('scripts/parts/render_money_dashboard_reports.py', 'r', encoding='utf-8') as f:
    content = f.read()

old_table_pattern = re.compile(r'\{\/\* Expense Vouchers Table \*\/}.*?\{\/\* ================= SCREEN 17: FIXED ASSETS', re.DOTALL)

new_datalist = '''{/* Expense Vouchers DataList */}
                  <div className="flex-1 overflow-hidden flex flex-col">
                    <DataList
                      id="expenses"
                      title="Expense Vouchers & Outflows"
                      subtitle="Daily operational disbursements, store utilities, shop rent, and refreshment vouchers"
                      data={MOCK_EXPENSES}
                      keyField="id"
                      dateField="date"
                      searchPlaceholder="Search voucher #, expense head, account, note... (/)"
                      searchFields={['voucher_no', 'head', 'account', 'note', 'date']}
                      columns={[
                        {
                          id: 'voucher_no',
                          header: 'Voucher #',
                          sortable: true,
                          className: 'font-mono font-medium text-[var(--primary)]',
                          accessor: exp => exp.voucher_no,
                          cell: exp => (
                            <span className="font-mono font-semibold text-[var(--primary)] group-hover:underline">
                              {exp.voucher_no}
                            </span>
                          )
                        },
                        {
                          id: 'head',
                          header: 'Expense Head',
                          sortable: true,
                          className: 'font-semibold text-[var(--foreground)]',
                          accessor: exp => exp.head
                        },
                        {
                          id: 'account',
                          header: 'Paid From Account',
                          sortable: true,
                          className: 'text-[var(--muted-foreground)]',
                          accessor: exp => exp.account
                        },
                        {
                          id: 'date',
                          header: 'Date',
                          sortable: true,
                          className: 'text-[var(--muted-foreground)]',
                          accessor: exp => exp.date
                        },
                        {
                          id: 'amount',
                          header: 'Amount',
                          type: 'money',
                          align: 'right',
                          sortable: true,
                          accessor: exp => exp.amount,
                          cell: exp => (
                            <span className="font-sans font-bold tabular-nums num text-[var(--danger)]">
                              −{formatMoney(exp.amount)}
                            </span>
                          )
                        },
                        {
                          id: 'note',
                          header: 'Note / Narration',
                          sortable: true,
                          className: 'text-[var(--muted-foreground)] text-[11px] truncate max-w-xs',
                          accessor: exp => exp.note
                        }
                      ]}
                      filterChips={[
                        {
                          id: 'account',
                          label: 'Paid From',
                          options: [
                            { label: 'All Accounts', value: 'all' },
                            { label: 'Cash in Till', value: 'Cash' },
                            { label: 'BRAC Bank', value: 'BRAC' },
                            { label: 'bKash Merchant', value: 'bKash' }
                          ]
                        }
                      ]}
                      moreFilters={[
                        {
                          id: 'head',
                          label: 'Expense Head Category',
                          options: [
                            { label: 'All Heads', value: 'all' },
                            { label: 'Store Rent & Service Charge', value: 'Rent' },
                            { label: 'Staff Tea & Refreshment', value: 'Refreshment' },
                            { label: 'Electricity & Utilities', value: 'Electricity' },
                            { label: 'Shopping Bags & Packaging', value: 'Packaging' },
                            { label: 'Courier Logistics & Freight', value: 'Courier' }
                          ]
                        }
                      ]}
                      savedViews={[
                        { id: 'default', label: 'Default (All Expenses)' },
                        { id: 'till_outflow', label: 'Cash Outflow from Till', filters: { account: 'Cash' } },
                        { id: 'rent_utilities', label: 'Store Rent & Utility Bills', filters: { head: 'Rent' } }
                      ]}
                      bulkActions={[
                        {
                          label: 'Export Expense Register',
                          icon: 'fileText',
                          action: (rows) => {
                            exportToCSV('expense_vouchers_' + Date.now() + '.csv', [
                              { id: 'voucher_no', header: 'Voucher No' },
                              { id: 'head', header: 'Head' },
                              { id: 'account', header: 'Account' },
                              { id: 'date', header: 'Date' },
                              { id: 'amount', header: 'Amount' },
                              { id: 'note', header: 'Note' }
                            ], rows);
                            addToast('Exported ' + rows.length + ' expense vouchers', 'success');
                          }
                        },
                        {
                          label: 'Print Vouchers Batch',
                          icon: 'printer',
                          action: (rows) => {
                            addToast('Sent ' + rows.length + ' vouchers to laser printer', 'info');
                          }
                        }
                      ]}
                      customFilter={(exp, filters) => {
                        if (filters.account && filters.account !== 'all') {
                          if (!exp.account.toLowerCase().includes(filters.account.toLowerCase())) return false;
                        }
                        if (filters.head && filters.head !== 'all') {
                          if (!exp.head.toLowerCase().includes(filters.head.toLowerCase())) return false;
                        }
                        return true;
                      }}
                      onRowClick={exp => {
                        addToast('Viewing voucher ' + exp.voucher_no + ' (' + formatMoney(exp.amount) + ')', 'info');
                      }}
                      onRowContextMenu={(exp, e) => {
                        openContextMenu(e, exp.voucher_no, [
                          { label: 'View Expense Voucher Details', action: () => addToast('Voucher #' + exp.voucher_no + ' details', 'info') },
                          { label: 'Reprint Debit Voucher', action: () => addToast('Reprinting debit voucher...', 'info') },
                          { label: 'Copy Voucher Number', action: () => {
                            navigator.clipboard ? navigator.clipboard.writeText(exp.voucher_no) : null;
                            addToast('Voucher # copied', 'info');
                          }}
                        ]);
                      }}
                    />
                  </div>
                </div>
              )}

              {/* ================= SCREEN 17: FIXED ASSETS'''

content = old_table_pattern.sub(new_datalist, content)

with open('scripts/parts/render_money_dashboard_reports.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_money_dashboard_reports.py patched successfully!")
