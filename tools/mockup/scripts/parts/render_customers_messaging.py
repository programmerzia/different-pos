# scripts/parts/render_customers_messaging.py

RENDER_CUSTOMERS_MESSAGING_JS = """
              {/* ================= SCREEN 6: CUSTOMER DIRECTORY (G C) ================= */}
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

              {/* ================= SCREEN 7: CUSTOMER ADD / EDIT FORM ================= */}
              {currentScreen === 'customer_form' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4 max-w-4xl mx-auto w-full">
                  
                  {/* Top Bar Navigation & Save */}
                  <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                    <div className="flex items-center gap-3">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('customers')}
                        className="p-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:bg-[var(--surface-sunken)] transition-colors"
                      >
                        <Icon name="arrowLeft" size={16} />
                      </button>
                      <div>
                        <h1 className="text-lg font-bold text-[var(--foreground)]">New Customer Registration — নতুন গ্রাহক নিবন্ধন</h1>
                        <div className="text-xs text-[var(--muted-foreground)]">Assign automatic 8-digit membership card number with Luhn check digit (#1000 00XX)</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setCurrentScreen('customers')}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          // Comprehensive Form Validation
                          const errors = {};
                          const cleanName = (custFormNameEn || '').trim();
                          const cleanPhone = (custFormPhone || '').replace(/[^0-9]/g, '');

                          if (!cleanName || cleanName.length < 3) {
                            errors.name = "Full name is required (at least 3 characters)";
                          }

                          if (!cleanPhone) {
                            errors.phone = "Mobile number is required";
                          } else if (!/^01[3-9]\\d{8}$/.test(cleanPhone)) {
                            errors.phone = "Invalid Bangladesh mobile number (must be 11 digits starting with 013-019)";
                          }

                          if (!custFormStreet || !custFormStreet.trim()) {
                            errors.street = "Street address or holding number is required";
                          }

                          if (custFormNid && custFormNid.trim()) {
                            const nidLen = custFormNid.trim().replace(/[^0-9]/g, '').length;
                            if (![10, 13, 17].includes(nidLen)) {
                              errors.nid = "NID must be 10, 13, or 17 digits";
                            }
                          }

                          if (Number(custFormCreditLimit) < 0) {
                            errors.credit = "Credit limit cannot be negative";
                          }

                          setCustFormErrors(errors);
                          setCustFormTouched({ name: true, phone: true, street: true, nid: true });

                          if (Object.keys(errors).length > 0) {
                            addToast("Please correct the highlighted form errors before saving", "danger");
                            return;
                          }

                          // Success: create and append customer
                          const nextNo = "1000 00" + Math.floor(40 + Math.random() * 50);
                          const newCust = {
                            id: 'c_' + Date.now(),
                            customer_no: nextNo,
                            name_en: cleanName,
                            name_bn: custFormNameBn.trim() || cleanName,
                            phone: cleanPhone,
                            alt_phone: custFormAltPhone || '',
                            nid: custFormNid || '',
                            email: custFormEmail || '',
                            group: custFormGroup,
                            group_discount: custFormGroup.includes('VIP') ? 10 : custFormGroup.includes('Corporate') ? 5 : 0,
                            balance: 0,
                            credit_limit: Number(custFormCreditLimit) || 10000,
                            credit_used: 0,
                            card_status: 'Active',
                            pin_status: 'Set',
                            division: custFormDivision,
                            district: custFormDistrict,
                            city_upazila: custFormUpazila,
                            postal_code: custFormPostCode,
                            street_address: custFormStreet,
                            landmark: custFormLandmark,
                            member_since: 'Today',
                            last_purchase: 'None',
                            total_spent: 0,
                            visits: 0,
                            open_invoices: []
                          };

                          MOCK_CUSTOMERS.unshift(newCust);
                          setSelectedCustomer(newCust);
                          addToast(`Customer "${newCust.name_en}" registered with Member #${newCust.customer_no}!`, "success");
                          setCustomerTab('overview');
                          setCurrentScreen('customer_profile');
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold shadow-xs transition-all flex items-center gap-1.5 cursor-pointer"
                      >
                        <Icon name="checkCircle" size={14} />
                        <span>Save Customer</span>
                      </button>
                    </div>
                  </div>

                  {/* Form Container */}
                  <div className="space-y-4">
                    
                    {/* SECTION 1: PERSONAL IDENTITY */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs space-y-3">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2">
                        <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">
                          <Icon name="user" size={15} className="text-[var(--primary)]" />
                          <span>1. Personal Identity & Contact Information (ব্যক্তিগত পরিচিতি)</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">* Required fields</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Full Name (English) *</label>
                          <input
                            type="text"
                            placeholder="e.g. Asif Mahmud"
                            value={custFormNameEn}
                            onChange={(e) => {
                              setCustFormNameEn(e.target.value);
                              if (custFormErrors.name) setCustFormErrors(prev => ({ ...prev, name: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              custFormErrors.name ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.name && (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.name}</span>
                            </div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Full Name (বাংলা)</label>
                          <input
                            type="text"
                            placeholder="e.g. আসিফ মাহমুদ"
                            value={custFormNameBn}
                            onChange={(e) => setCustFormNameBn(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Primary Mobile Number (WhatsApp / SMS) *</label>
                          <input
                            type="text"
                            placeholder="01711-234567"
                            value={custFormPhone}
                            onChange={(e) => {
                              setCustFormPhone(e.target.value);
                              if (custFormErrors.phone) setCustFormErrors(prev => ({ ...prev, phone: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-semibold text-[var(--foreground)] ${
                              custFormErrors.phone ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.phone ? (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.phone}</span>
                            </div>
                          ) : (
                            <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">Format: 11 digits starting with 013, 014, 015, 016, 017, 018, 019</div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Secondary / Alternative Phone</label>
                          <input
                            type="text"
                            placeholder="01822-XXXXXX (Optional)"
                            value={custFormAltPhone}
                            onChange={(e) => setCustFormAltPhone(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                          />
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">National ID (NID) / Smart Card</label>
                          <input
                            type="text"
                            placeholder="10, 13, or 17 digit NID number"
                            value={custFormNid}
                            onChange={(e) => {
                              setCustFormNid(e.target.value);
                              if (custFormErrors.nid) setCustFormErrors(prev => ({ ...prev, nid: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono text-[var(--foreground)] ${
                              custFormErrors.nid ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.nid && (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.nid}</span>
                            </div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Email Address</label>
                          <input
                            type="email"
                            placeholder="customer@example.com (Optional)"
                            value={custFormEmail}
                            onChange={(e) => setCustFormEmail(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                        </div>
                      </div>
                    </div>

                    {/* SECTION 2: GEOGRAPHICAL LOCATION & ADDRESS */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs space-y-3">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2">
                        <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">
                          <Icon name="mapPin" size={15} className="text-[var(--primary)]" />
                          <span>2. Customer Address & Delivery Details (ঠিকানা ও ডেলিভারি বিবরণ)</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Hierarchical Division &rarr; District &rarr; Upazila</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-4 gap-3 text-xs">
                        {/* Division */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">Division *</label>
                          <select
                            value={custFormDivision}
                            onChange={(e) => {
                              const div = e.target.value;
                              setCustFormDivision(div);
                              if (div === 'Dhaka') { setCustFormDistrict('Dhaka'); setCustFormUpazila('Uttara'); }
                              else if (div === 'Rajshahi') { setCustFormDistrict('Joypurhat'); setCustFormUpazila('Kalai'); }
                              else if (div === 'Chittagong') { setCustFormDistrict('Chittagong'); setCustFormUpazila('Kotwali'); }
                              else if (div === 'Sylhet') { setCustFormDistrict('Sylhet'); setCustFormUpazila('Sylhet Sadar'); }
                              else if (div === 'Khulna') { setCustFormDistrict('Khulna'); setCustFormUpazila('Khulna Sadar'); }
                              else if (div === 'Barisal') { setCustFormDistrict('Barisal'); setCustFormUpazila('Barisal Sadar'); }
                              else if (div === 'Rangpur') { setCustFormDistrict('Rangpur'); setCustFormUpazila('Rangpur Sadar'); }
                              else if (div === 'Mymensingh') { setCustFormDistrict('Mymensingh'); setCustFormUpazila('Mymensingh Sadar'); }
                            }}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            {['Dhaka', 'Chittagong', 'Rajshahi', 'Khulna', 'Sylhet', 'Barisal', 'Rangpur', 'Mymensingh'].map(d => (
                              <option key={d} value={d}>{d}</option>
                            ))}
                          </select>
                        </div>

                        {/* District */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">District *</label>
                          <select
                            value={custFormDistrict}
                            onChange={(e) => {
                              const dist = e.target.value;
                              setCustFormDistrict(dist);
                              if (dist === 'Joypurhat') setCustFormUpazila('Kalai');
                              else if (dist === 'Dhaka') setCustFormUpazila('Uttara');
                              else setCustFormUpazila(dist + ' Sadar');
                            }}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            {custFormDivision === 'Rajshahi' ? (
                              ['Joypurhat', 'Bogura', 'Rajshahi', 'Naogaon', 'Pabna', 'Sirajganj'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : custFormDivision === 'Chittagong' ? (
                              ["Chittagong", "Cox's Bazar", "Comilla", "Feni", "Noakhali"].map(d => <option key={d} value={d}>{d}</option>)
                            ) : custFormDivision === 'Sylhet' ? (
                              ['Sylhet', 'Moulvibazar', 'Habiganj', 'Sunamganj'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : custFormDivision === 'Khulna' ? (
                              ['Khulna', 'Jessore', 'Kushtia', 'Satkhira'].map(d => <option key={d} value={d}>{d}</option>)
                            ) : (
                              ['Dhaka', 'Gazipur', 'Narayanganj', 'Tangail', 'Narsingdi', 'Manikganj'].map(d => <option key={d} value={d}>{d}</option>)
                            )}
                          </select>
                        </div>

                        {/* Upazila / Thana */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">Upazila / Thana *</label>
                          <select
                            value={custFormUpazila}
                            onChange={(e) => setCustFormUpazila(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            {custFormDistrict === 'Joypurhat' ? (
                              ['Kalai', 'Khetlal', 'Joypurhat Sadar', 'Panchbibi', 'Akkelpur'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : custFormDistrict === 'Bogura' ? (
                              ['Bogura Sadar', 'Shajahanpur', 'Sherpur', 'Shibganj'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : custFormDistrict === 'Dhaka' ? (
                              ['Uttara', 'Dhanmondi', 'Gulshan', 'Mirpur', 'Banani', 'Motijheel', 'Mohammadpur'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : custFormDistrict === 'Gazipur' ? (
                              ['Gazipur Sadar', 'Tongi', 'Kaliakair', 'Sreepur'].map(u => <option key={u} value={u}>{u}</option>)
                            ) : (
                              [custFormDistrict + ' Sadar', 'Area 1', 'Area 2'].map(u => <option key={u} value={u}>{u}</option>)
                            )}
                          </select>
                        </div>

                        {/* Post Code */}
                        <div>
                          <label className="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase">Postal Code</label>
                          <input
                            type="text"
                            value={custFormPostCode}
                            onChange={(e) => setCustFormPostCode(e.target.value)}
                            placeholder="e.g. 1230"
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                          />
                        </div>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Street Address / Village / Holding No *</label>
                          <input
                            type="text"
                            placeholder="e.g. Holding 42, Road 11, Sector 3"
                            value={custFormStreet}
                            onChange={(e) => {
                              setCustFormStreet(e.target.value);
                              if (custFormErrors.street) setCustFormErrors(prev => ({ ...prev, street: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] ${
                              custFormErrors.street ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                            }`}
                          />
                          {custFormErrors.street && (
                            <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{custFormErrors.street}</span>
                            </div>
                          )}
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Delivery Landmark & Directions</label>
                          <input
                            type="text"
                            placeholder="e.g. Near Rajlakshmi Complex, Opposite to Mosque"
                            value={custFormLandmark}
                            onChange={(e) => setCustFormLandmark(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                          />
                        </div>
                      </div>
                    </div>

                    {/* SECTION 3: MEMBERSHIP & CREDIT SETTINGS */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs space-y-3">
                      <div className="flex items-center justify-between border-b border-[var(--border)] pb-2">
                        <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)] uppercase tracking-wider">
                          <Icon name="creditCard" size={15} className="text-[var(--primary)]" />
                          <span>3. Credit Limit, Loyalty Tier & Security (ক্রেডিট ও মেম্বারশিপ)</span>
                        </div>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Credit sales require verified customer attachment</span>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Customer Group / Tier *</label>
                          <select
                            value={custFormGroup}
                            onChange={(e) => {
                              const grp = e.target.value;
                              setCustFormGroup(grp);
                              if (grp === 'VIP Wholesale') { setCustFormCreditLimit(50000); setCustFormSpecialDisc(10); }
                              else if (grp === 'Corporate Client') { setCustFormCreditLimit(100000); setCustFormSpecialDisc(5); }
                              else { setCustFormCreditLimit(10000); setCustFormSpecialDisc(0); }
                            }}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                          >
                            <option value="Retail Member">Retail Member (Standard)</option>
                            <option value="VIP Wholesale">VIP Wholesale (−10% Auto Discount)</option>
                            <option value="Corporate Client">Corporate Client (−5% Auto Discount)</option>
                            <option value="Staff Account">Staff Account (Wholesale At-Cost)</option>
                          </select>
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Allowed Credit Limit (BDT) *</label>
                          <input
                            type="number"
                            min="0"
                            value={custFormCreditLimit}
                            onChange={(e) => setCustFormCreditLimit(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)] text-right"
                          />
                        </div>

                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Privilege Group Discount (%)</label>
                          <input
                            type="number"
                            min="0"
                            max="100"
                            value={custFormSpecialDisc}
                            onChange={(e) => setCustFormSpecialDisc(e.target.value)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--primary)] text-right"
                          />
                        </div>
                      </div>
                    </div>

                  </div>
                </div>
              )}{/* ================= SCREEN 8: CUSTOMER 360 PROFILE ================= */}
              {currentScreen === 'customer_profile' && selectedCustomer && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  {/* Customer Banner Header */}
                  <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                      <button onClick={() => setCurrentScreen('customers')} className="p-1.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                        <Icon name="arrowLeft" size={16} />
                      </button>
                      <div className="w-12 h-12 rounded-xl bg-[var(--primary)] text-[var(--primary-foreground)] flex items-center justify-center font-bold text-lg">
                        {selectedCustomer.name_en[0]}
                      </div>
                      <div>
                        <div className="flex items-center gap-2">
                          <h1 className="text-lg font-bold text-[var(--foreground)]">{selectedCustomer.name_en}</h1>
                          <span className="text-xs text-[var(--muted-foreground)] font-bangla">({selectedCustomer.name_bn})</span>
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--primary-soft)] text-[var(--primary)]">
                            {selectedCustomer.group}
                          </span>
                        </div>
                        <div className="text-xs text-[var(--muted-foreground)] flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                          <span>Card: <strong className="font-mono">{selectedCustomer.customer_no}</strong></span>
                          <span>·</span>
                          <span>Phone: <strong className="font-mono">{selectedCustomer.phone}</strong></span>
                          <span>·</span>
                          <span>Address: <strong className="text-[var(--foreground)]">{selectedCustomer.street_address ? `${selectedCustomer.street_address}, ` : ''}{selectedCustomer.city_upazila}, {selectedCustomer.district}</strong></span>
                        </div>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        onClick={() => {
                          setDueCollectCustomer(selectedCustomer);
                          setDueCollectAmount(Math.abs(selectedCustomer.balance).toString());
                          setShowDueCollectModal(true);
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold shadow-xs"
                      >
                        Collect Due
                      </button>
                      <button
                        onClick={() => {
                          setReminderCustomer(selectedCustomer);
                          setShowReminderModal(true);
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--success-soft)] border border-[var(--success)]/30 text-[var(--success)] text-xs font-semibold flex items-center gap-1.5"
                      >
                        <Icon name="send" size={14} />
                        <span>WhatsApp</span>
                      </button>
                      <button
                        onClick={() => {
                          setCardCustomer(selectedCustomer);
                          setShowCustomerCardModal(true);
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold flex items-center gap-1.5 hover:border-[var(--primary)]"
                        title="View & Print Customer Card"
                      >
                        <Icon name="idCard" size={14} />
                        <span>View Card</span>
                      </button>
                    </div>
                  </div>

                  {/* 4 KPI Summary Cards */}
                  <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[11px] text-[var(--muted-foreground)]">Lifetime Spend (LTV)</div>
                      <div className="text-base font-bold num text-[var(--foreground)] mt-1">{formatMoney(48250)}</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[11px] text-[var(--muted-foreground)]">Total Store Visits</div>
                      <div className="text-base font-bold font-mono text-[var(--foreground)] mt-1">14 visits</div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[11px] text-[var(--muted-foreground)]">Outstanding Due</div>
                      <div className={`text-base font-bold font-mono mt-1 ${selectedCustomer.balance < 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]'}`}>
                        {formatMoney(selectedCustomer.balance)}
                      </div>
                    </div>
                    <div className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)]">
                      <div className="text-[11px] text-[var(--muted-foreground)]">Card & PIN Security</div>
                      <div className="text-xs font-semibold text-[var(--success)] mt-1">Active · PIN Verified</div>
                    </div>
                  </div>

                  {/* Tabs Bar */}
                  <div className="border-b border-[var(--border)] flex gap-4 text-xs font-medium text-[var(--muted-foreground)]">
                    {['overview', 'sales', 'items', 'ledger', 'messages'].map(tab => (
                      <button
                        key={tab}
                        onClick={() => setCustomerTab(tab)}
                        className={`pb-2 capitalize transition-colors ${customerTab === tab ? 'text-[var(--primary)] border-b-2 border-[var(--primary)] font-bold' : 'hover:text-[var(--foreground)]'}`}
                      >
                        {tab}
                      </button>
                    ))}
                  </div>

                  {/* Tab Content */}
                  <div className="space-y-4">
                    {customerTab === 'overview' && (
                      <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {/* CARD 1: CUSTOMER ADDRESS & DELIVERY DETAILS */}
                          <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                            <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                              <div className="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                                <Icon name="mapPin" size={15} className="text-[var(--primary)]" />
                                <span>Customer Address & Delivery Details (ঠিকানা ও ডেলিভারি বিবরণ)</span>
                              </div>
                              <span className="px-2 py-0.5 rounded-full bg-[var(--success-soft)] text-[var(--success)] font-bold text-[10px]">
                                ✓ Verified Address
                              </span>
                            </div>

                            <div className="space-y-2 text-xs">
                              <div className="grid grid-cols-3 gap-2">
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Division</span>
                                  <div className="font-semibold text-[var(--foreground)]">{selectedCustomer.division || 'Dhaka'}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">District</span>
                                  <div className="font-semibold text-[var(--foreground)]">{selectedCustomer.district || 'Dhaka'}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Upazila / Thana</span>
                                  <div className="font-semibold text-[var(--foreground)]">{selectedCustomer.city_upazila || 'Uttara'}</div>
                                </div>
                              </div>

                              <div className="pt-2 border-t border-[var(--border)]">
                                <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Street Address / Holding No</span>
                                <div className="font-semibold text-[var(--foreground)] mt-0.5 p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                                  {selectedCustomer.street_address || 'Holding 42, Road 11, Sector 3'}
                                </div>
                              </div>

                              <div>
                                <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Delivery Landmark & Directions</span>
                                <div className="text-[var(--foreground)] mt-0.5 italic text-[11px]">
                                  {selectedCustomer.landmark || 'Near Rajlakshmi Complex, Opposite to Mosque'}
                                </div>
                              </div>
                            </div>
                          </div>

                          {/* CARD 2: CUSTOMER IDENTITY & CREDIT GOVERNANCE */}
                          <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                            <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                              <div className="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                                <Icon name="shieldCheck" size={15} className="text-[var(--success)]" />
                                <span>Identity, Membership & Credit Limits</span>
                              </div>
                              <span className="font-mono text-[11px] font-bold px-2 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">
                                #{selectedCustomer.customer_no}
                              </span>
                            </div>

                            <div className="space-y-2.5 text-xs">
                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">National ID (NID)</span>
                                  <div className="font-mono font-semibold text-[var(--foreground)]">{selectedCustomer.nid || '19922692018000412'}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Membership Group</span>
                                  <div className="font-semibold text-[var(--primary)]">{selectedCustomer.group}</div>
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Primary WhatsApp</span>
                                  <div className="font-mono font-bold text-[var(--foreground)]">{selectedCustomer.phone}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Privilege Discount</span>
                                  <div className="font-bold text-[var(--success)]">{selectedCustomer.group_discount ? `−${selectedCustomer.group_discount}% Auto` : 'None (Standard)'}</div>
                                </div>
                              </div>

                              {/* Credit Limit Meter */}
                              <div className="pt-2 border-t border-[var(--border)] space-y-1.5">
                                <div className="flex items-center justify-between text-[11px]">
                                  <span className="text-[var(--muted-foreground)] font-medium">Credit Facility Utilization:</span>
                                  <span className="font-mono font-bold text-[var(--foreground)]">
                                    {formatMoney(Math.abs(selectedCustomer.balance < 0 ? selectedCustomer.balance : 0))} / {formatMoney(selectedCustomer.credit_limit || 10000)}
                                  </span>
                                </div>
                                <div className="w-full h-2 rounded-full bg-[var(--surface-sunken)] border border-[var(--border)] overflow-hidden">
                                  {(() => {
                                    const used = Math.abs(selectedCustomer.balance < 0 ? selectedCustomer.balance : 0);
                                    const limit = selectedCustomer.credit_limit || 10000;
                                    const pct = Math.min(100, Math.round((used / limit) * 100));
                                    return (
                                      <div
                                        style={{ width: `${pct}%` }}
                                        className={`h-full transition-all ${pct > 80 ? 'bg-[var(--danger)]' : pct > 50 ? 'bg-[var(--warning)]' : 'bg-[var(--success)]'}`}
                                      />
                                    );
                                  })()}
                                </div>
                                <div className="flex justify-between text-[10px] text-[var(--muted-foreground)]">
                                  <span>Available Credit: <strong>{formatMoney(Math.max(0, (selectedCustomer.credit_limit || 10000) - Math.abs(selectedCustomer.balance < 0 ? selectedCustomer.balance : 0)))}</strong></span>
                                  <span>Security PIN: <strong className="text-[var(--success)]">Active (4-Digit)</strong></span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* CARD 3: RECENT PURCHASES & ACTIVITY */}
                        <div className="rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 shadow-xs">
                          <div className="font-bold text-xs mb-3 text-[var(--foreground)] flex items-center justify-between">
                            <span>Recent Purchase Transactions & Vouchers</span>
                            <span className="text-[11px] text-[var(--muted-foreground)] font-normal">{selectedCustomer.open_invoices?.length || 1} open invoices</span>
                          </div>
                          <div className="text-xs space-y-2">
                            <div className="p-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex justify-between items-center">
                              <div>
                                <div className="font-mono font-bold text-[var(--primary)] flex items-center gap-2">
                                  <span>DF1-R1-26000413</span>
                                  <span className="px-1.5 py-0.2 rounded text-[10px] bg-[var(--success-soft)] text-[var(--success)] font-bold">Settled</span>
                                </div>
                                <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">2x Cotton Panjabi M, 1x Classic Oxford Shirt L · Counter R1 (Sumi)</div>
                              </div>
                              <span className="num font-bold text-sm text-[var(--foreground)]">{formatMoney(4690)}</span>
                            </div>
                            {selectedCustomer.open_invoices?.map((inv, idx) => (
                              <div key={idx} className="p-3 rounded-lg bg-[var(--warning-soft)] border border-[var(--warning)]/30 flex justify-between items-center">
                                <div>
                                  <div className="font-mono font-bold text-[var(--warning)] flex items-center gap-2">
                                    <span>{inv.invoice}</span>
                                    <span className="px-1.5 py-0.2 rounded text-[10px] bg-[var(--warning-soft)] text-[var(--warning)] font-bold">Due Pending: {formatMoney(inv.due)}</span>
                                  </div>
                                  <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">{inv.date} · Paid {formatMoney(inv.paid)} of {formatMoney(inv.total)}</div>
                                </div>
                                <button
                                  type="button"
                                  onClick={() => {
                                    setDueCollectCustomer(selectedCustomer);
                                    setDueCollectAmount(inv.due.toString());
                                    setShowDueCollectModal(true);
                                  }}
                                  className="h-7 px-2.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold cursor-pointer"
                                >
                                  Pay Due
                                </button>
                              </div>
                            ))}
                          </div>
                        </div>
                      </div>
                    )}

                    {customerTab !== 'overview' && (
                      <div className="p-6 rounded-xl bg-[var(--surface)] border border-[var(--border)] text-center text-xs text-[var(--muted-foreground)]">
                        Viewing detailed records for {customerTab.toUpperCase()} tab of {selectedCustomer.name_en}.
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* ================= SCREEN 9: BULK MEMBERSHIP CARD PRODUCTION CENTER ================= */}
              {currentScreen === 'membership_cards' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  {/* Studio Header */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[var(--border)] gap-2">
                    <div>
                      <h1 className="text-lg font-bold text-[var(--foreground)]">Membership Card Studio & Production</h1>
                      <div className="text-xs text-[var(--muted-foreground)]">Print designer with front/back preview, ISO CR80 PVC cards, 10-Up A4 sheets, and Lost Card reissue</div>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => {
                          setReissueTargetCustomer(reissueTargetCustomer || MOCK_CUSTOMERS[0]);
                          setReissueOldCardNumber(reissueTargetCustomer?.customer_no || '1000 0017');
                          setShowReissueCardModal(true);
                        }}
                        className="h-8 px-3 rounded-lg bg-[var(--warning-soft)] hover:bg-[var(--warning)] hover:text-white text-[var(--warning)] text-xs font-bold flex items-center gap-1.5 transition-colors cursor-pointer"
                      >
                        <Icon name="refresh" size={13} />
                        <span>Report Lost → Reissue</span>
                      </button>

                      <div className="flex bg-[var(--surface-sunken)] p-0.5 rounded-lg border border-[var(--border)] text-xs">
                        <button
                          type="button"
                          onClick={() => setCardLayout('cr80')}
                          className={`px-3 py-1 rounded font-medium cursor-pointer ${cardLayout === 'cr80' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)]'}`}
                        >
                          CR80 PVC
                        </button>
                        <button
                          type="button"
                          onClick={() => setCardLayout('a4_10')}
                          className={`px-3 py-1 rounded font-medium cursor-pointer ${cardLayout === 'a4_10' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)]'}`}
                        >
                          A4 (10-Up)
                        </button>
                      </div>

                      <button
                        type="button"
                        onClick={() => {
                          const area = document.getElementById('printable-card-area');
                          if (area) {
                            area.classList.add('printable-area');
                            window.print();
                            area.classList.remove('printable-area');
                          } else {
                            window.print();
                          }
                          addToast("Sending cards to card printer...", "info");
                        }}
                        className="h-8 px-3.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer hover:bg-[var(--primary-hover)]"
                      >
                        <Icon name="printer" size={14} />
                        <span>Print Batch</span>
                      </button>
                    </div>
                  </div>

                  {/* Customer Selector & Flip Switcher Strip */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] gap-2 shadow-xs">
                    <div className="flex items-center gap-3">
                      <span className="text-xs font-semibold text-[var(--foreground)]">Select Member:</span>
                      <select
                        value={reissueTargetCustomer?.id || MOCK_CUSTOMERS[0].id}
                        onChange={(e) => {
                          const found = MOCK_CUSTOMERS.find(c => c.id === e.target.value);
                          if (found) {
                            setReissueTargetCustomer(found);
                            setReissueOldCardNumber(found.customer_no);
                          }
                        }}
                        className="h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                      >
                        {MOCK_CUSTOMERS.map(c => (
                          <option key={c.id} value={c.id}>{c.name_en} ({c.customer_no}) · {c.group}</option>
                        ))}
                      </select>
                    </div>

                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setCardPreviewSide(prev => prev === 'front' ? 'back' : 'front')}
                        className="h-8 px-3.5 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-bold text-[var(--primary)] flex items-center gap-1.5 cursor-pointer shadow-xs"
                      >
                        <Icon name="refresh" size={13} />
                        <span>Flip Card (Showing: {cardPreviewSide.toUpperCase()})</span>
                      </button>
                    </div>
                  </div>

                  {/* Interactive Card Designer Canvas & Live Front/Back Preview */}
                  <div id="printable-card-area" className="w-full space-y-4">
                    {cardLayout === 'cr80' && (
                      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                        {/* 3D Physical Card Simulation Container */}
                        <div className="p-6 rounded-2xl bg-[var(--surface)] border border-[var(--border)] flex flex-col items-center justify-center space-y-4 shadow-xs">
                          <div className="text-xs font-bold text-[var(--muted-foreground)] uppercase tracking-wider flex items-center justify-between w-full">
                            <span>Interactive Physical CR80 Simulation</span>
                            <span className="font-mono text-[10px]">ISO/IEC 7810 ID-1 (85.6 × 54mm)</span>
                          </div>

                          {/* THE CARD */}
                          {cardPreviewSide === 'front' ? (
                            /* FRONT SIDE PREVIEW */
                            <div
                              style={{
                                background: 'linear-gradient(135deg, #042f2e 0%, #0d9488 50%, #0f766e 100%)',
                                WebkitPrintColorAdjust: 'exact',
                                printColorAdjust: 'exact',
                                color: '#ffffff'
                              }}
                              className="w-[340px] h-[214px] rounded-2xl p-5 flex flex-col justify-between shadow-2xl relative overflow-hidden border border-[var(--primary)]/40 select-none animate-in fade-in duration-200"
                            >
                              {/* Metallic Sheen Effect */}
                              <div className="absolute -top-12 -right-12 w-40 h-40 bg-white/10 rounded-full blur-xl pointer-events-none" />

                              <div className="flex items-start justify-between relative z-10">
                                <div>
                                  <div className="text-xs font-black tracking-widest text-[var(--primary-soft)] uppercase">DIFFERENT FASHION</div>
                                  <div className="text-[9px] text-[var(--primary-soft)] tracking-wider font-semibold">EXCLUSIVE PRIVILEGE CLUB</div>
                                </div>
                                <div className="flex items-center gap-2">
                                  {/* Smart Chip */}
                                  <div className="w-8 h-6 rounded bg-gradient-to-br from-[var(--warning)] to-[var(--warning)] border border-[var(--warning)]/50 flex items-center justify-center text-[8px] font-mono font-bold text-black shadow-inner">
                                    CHIP
                                  </div>
                                  {/* NFC Contactless Wave */}
                                  <div className="text-[var(--primary-soft)] text-sm font-bold tracking-tighter">
                                    ))))
                                  </div>
                                </div>
                              </div>

                              <div className="space-y-1 relative z-10">
                                <div className="font-mono text-xl tracking-widest text-white font-extrabold drop-shadow">
                                  {reissueTargetCustomer?.customer_no || '1000 0017'}
                                </div>
                                <div className="flex justify-between items-end pt-1">
                                  <div>
                                    <div className="text-[8px] text-[var(--primary-soft)] uppercase tracking-wider font-semibold">Member Cardholder</div>
                                    <div className="text-xs font-bold tracking-wide text-white">{reissueTargetCustomer?.name_en || 'Rahim Uddin'}</div>
                                    <div className="text-[10px] text-[var(--primary-soft)] font-bangla">{reissueTargetCustomer?.name_bn || 'রহিম উদ্দিন'}</div>
                                  </div>
                                  <div className="text-right">
                                    <div className="text-[8px] text-[var(--primary-soft)] uppercase tracking-wider font-semibold">Club Tier</div>
                                    <div className="text-xs font-black text-[var(--warning)] uppercase tracking-wider">
                                      {(reissueTargetCustomer?.group || '').includes('VIP') ? 'PLATINUM VIP' : 'GOLD PRIVILEGE'}
                                    </div>
                                    <div className="text-[9px] text-[var(--primary-soft)] font-mono">VALID: 10/29</div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          ) : (
                            /* BACK SIDE PREVIEW */
                            <div
                              style={{
                                background: '#1e293b',
                                WebkitPrintColorAdjust: 'exact',
                                printColorAdjust: 'exact',
                                color: '#ffffff'
                              }}
                              className="w-[340px] h-[214px] rounded-2xl flex flex-col justify-between shadow-2xl relative overflow-hidden border border-[var(--border)] select-none animate-in fade-in duration-200"
                            >
                              {/* Magnetic Stripe */}
                              <div className="w-full h-10 bg-black mt-4 border-y border-black" />

                              {/* Signature & Barcode Area */}
                              <div className="px-5 py-2 space-y-2">
                                <div className="flex items-center gap-3">
                                  <div className="flex-1 h-7 bg-white text-black px-2 flex items-center justify-between text-[10px] font-mono italic border border-[var(--border)] rounded">
                                    <span className="text-[8px] text-[var(--muted-foreground)] font-sans not-italic">AUTHORIZED SIGNATURE</span>
                                    <span>{reissueTargetCustomer?.name_en || 'Cardholder'}</span>
                                  </div>
                                  <div className="w-8 h-7 bg-white text-black flex items-center justify-center font-mono font-bold text-xs rounded border border-[var(--border)]">
                                    849
                                  </div>
                                </div>

                                <div className="flex items-center justify-between pt-1">
                                  {/* Code 128 Barcode */}
                                  <div className="bg-white p-1.5 rounded border border-[var(--border)] text-center">
                                    <div className="font-mono text-base tracking-widest text-black font-bold h-6 flex items-center justify-center">
                                      ||| | |||| | |||||| || |
                                    </div>
                                    <div className="font-mono text-[8px] text-black font-semibold">
                                      MC{(reissueTargetCustomer?.customer_no || '1000 0017').replace(/[ ]+/g, '')}01
                                    </div>
                                  </div>

                                  {/* QR Code */}
                                  <div className="w-12 h-12 bg-white text-black p-1 rounded border border-[var(--border)] flex flex-col items-center justify-center">
                                    <Icon name="grid" size={24} className="text-black" />
                                    <span className="text-[6px] font-mono">SCAN</span>
                                  </div>
                                </div>

                                <div className="text-[7px] text-[var(--muted-foreground)] leading-tight pt-1">
                                  This membership card remains property of Different Fashion. Settle dues within 30 days. Found cards please return to Uttara Showroom. Helpline: 01711-223344.
                                </div>
                              </div>
                            </div>
                          )}

                          <button
                            type="button"
                            onClick={() => setCardPreviewSide(prev => prev === 'front' ? 'back' : 'front')}
                            className="text-xs text-[var(--primary)] font-semibold flex items-center gap-1 hover:underline"
                          >
                            <Icon name="refresh" size={12} />
                            <span>Click to Flip (Currently {cardPreviewSide.toUpperCase()})</span>
                          </button>
                        </div>

                        {/* Bulk Print Queue Card */}
                        <div className="p-4 rounded-2xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                          <div className="flex items-center justify-between">
                            <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Bulk Print Queue (Pending Batch)</h2>
                            <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[var(--primary-soft)] text-[var(--primary)]">
                              5 Cards Queued
                            </span>
                          </div>

                          <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                            <table className="w-full text-left text-xs">
                              <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                                <tr>
                                  <th className="py-2 px-2.5">Member #</th>
                                  <th className="py-2 px-2.5">Customer Name</th>
                                  <th className="py-2 px-2.5">Tier</th>
                                  <th className="py-2 px-2.5 text-center">Print Status</th>
                                </tr>
                              </thead>
                              <tbody className="divide-y divide-[var(--border)]">
                                {MOCK_CUSTOMERS.slice(0, 5).map((c, idx) => (
                                  <tr key={c.id} className="hover:bg-[var(--surface-sunken)]/50">
                                    <td className="py-2 px-2.5 font-mono font-bold text-[var(--primary)]">{c.customer_no}</td>
                                    <td className="py-2 px-2.5 font-medium text-[var(--foreground)] truncate max-w-[120px]">{c.name_en}</td>
                                    <td className="py-2 px-2.5">
                                      <span className="px-1.5 py-0.2 rounded text-[10px] font-bold bg-[var(--surface-sunken)]">
                                        {(c.group || '').includes('VIP') ? 'VIP' : 'GOLD'}
                                      </span>
                                    </td>
                                    <td className="py-2 px-2.5 text-center">
                                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--warning-soft)] text-[var(--warning)]">
                                        Ready to Print
                                      </span>
                                    </td>
                                  </tr>
                                ))}
                              </tbody>
                            </table>
                          </div>

                          <div className="pt-2 flex gap-2">
                            <button
                              type="button"
                              onClick={() => addToast("Printed 5 queued PVC cards", "success")}
                              className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center justify-center gap-1 shadow-xs cursor-pointer"
                            >
                              <Icon name="printer" size={13} />
                              <span>Print All Queued Cards</span>
                            </button>
                            <button
                              type="button"
                              onClick={() => addToast("Queue cleared", "info")}
                              className="px-3 h-8 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-xs font-medium text-[var(--muted-foreground)] cursor-pointer"
                            >
                              Clear
                            </button>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* A4 Sheet 10-Up Layout */}
                    {cardLayout === 'a4_10' && (
                      <div className="p-6 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4">
                        <div className="text-xs font-semibold text-[var(--muted-foreground)] flex items-center justify-between">
                          <span>A4 Card Sheet Grid (2 columns × 5 rows with crop marks for die-cutting)</span>
                          <span className="font-mono text-[10px]">Print at 100% scale (No fit to page)</span>
                        </div>
                        <div className="grid grid-cols-2 gap-4 max-w-2xl mx-auto p-4 bg-white text-black border border-dashed border-[var(--input)] shadow-sm">
                          {RAW_DATA.CUSTOMERS.slice(0, 10).map((cust, n) => (
                            <div key={cust.id} className="p-3 border border-[var(--border)] rounded-lg flex flex-col justify-between h-28 text-xs relative bg-white">
                              <div className="flex justify-between items-start">
                                <div>
                                  <div className="font-extrabold text-[11px] text-[var(--primary-hover)] tracking-wider">DIFFERENT FASHION</div>
                                  <div className="text-[9px] text-[var(--muted-foreground)]">Privilege Card #{n + 1}</div>
                                </div>
                                <span className="text-[9px] font-mono font-bold bg-[var(--surface-sunken)] px-1 rounded">{cust.group.includes('VIP') ? 'VIP' : 'MEMBER'}</span>
                              </div>
                              <div className="font-mono text-sm tracking-wider font-bold text-[var(--foreground)]">{cust.customer_no}</div>
                              <div className="flex justify-between items-end border-t border-[var(--border)] pt-1 text-[10px]">
                                <span className="font-bold">{cust.name_en}</span>
                                <span className="font-mono text-[var(--muted-foreground)]">MC{cust.customer_no.replace(/[ ]+/g, '')}01</span>
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* ================= SCREEN 10: WHATSAPP & SMS MESSAGING (G W) ================= */}
              {currentScreen === 'messaging' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                  {/* Top Bar */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-[var(--border)] gap-2">
                    <div>
                      <h1 className="text-lg font-bold text-[var(--foreground)]">Customer Messaging & Notification Engine</h1>
                      <div className="text-xs text-[var(--muted-foreground)]">WhatsApp Business API, Bulk SMS gateway, automated event triggers, and audit delivery log</div>
                    </div>

                    <div className="flex items-center gap-2">
                      <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-[var(--success)]/15 text-[var(--success)] flex items-center gap-1.5">
                        <span className="w-2 h-2 rounded-full bg-[var(--success)] animate-pulse"></span>
                        <span>WhatsApp Business Connected</span>
                      </span>
                      <span className="text-xs font-semibold px-2.5 py-1 rounded-full bg-[var(--primary-soft)] text-[var(--primary)] flex items-center gap-1.5">
                        <span>SMS Gateway: 4,250 Credits</span>
                      </span>
                    </div>
                  </div>

                  {/* 5-Tab Navigation Bar (Exact Spec: Composer, Templates library, Automation rules, Message log, Opt-out list) */}
                  <div className="flex items-center gap-1 bg-[var(--surface-sunken)] p-1 rounded-xl border border-[var(--border)] text-xs font-semibold w-fit">
                    {[
                      { id: 'composer', label: 'Composer & Dispatch', icon: 'send' },
                      { id: 'templates', label: 'Templates Library', icon: 'fileText' },
                      { id: 'automation', label: 'Automation Rules', icon: 'sliders' },
                      { id: 'log', label: 'Message Log (<DataList>)', icon: 'database' },
                      { id: 'opt_out', label: 'Opt-out / DND List', icon: 'slash' }
                    ].map(tab => (
                      <button
                        key={tab.id}
                        type="button"
                        onClick={() => setMessagingTab(tab.id)}
                        className={`h-7 px-3 rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer ${
                          messagingTab === tab.id ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        <Icon name={tab.icon} size={13} />
                        <span>{tab.label}</span>
                      </button>
                    ))}
                  </div>

                  {/* TAB 1: COMPOSER */}
                  {messagingTab === 'composer' && (
                    <div className="flex-1 flex flex-col lg:flex-row gap-4 items-start">
                      {/* Left: Dispatch Form */}
                      <div className="flex-1 w-full rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 space-y-4 shadow-xs">
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Dispatch Campaign Composer</h2>

                        <div className="space-y-3">
                          {/* Delivery Channel */}
                          <div>
                            <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Delivery Channel</label>
                            <div className="grid grid-cols-2 gap-2 mt-1">
                              <button
                                type="button"
                                onClick={() => setMsgChannel('whatsapp')}
                                className={`h-8 rounded-lg border text-xs font-semibold flex items-center justify-center gap-1.5 cursor-pointer ${
                                  msgChannel === 'whatsapp' ? 'bg-[var(--success)]/15 border-[var(--success)] text-[var(--success)] font-bold' : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)]'
                                }`}
                              >
                                <Icon name="smartphone" size={14} />
                                <span>WhatsApp Business API</span>
                              </button>
                              <button
                                type="button"
                                onClick={() => setMsgChannel('sms')}
                                className={`h-8 rounded-lg border text-xs font-semibold flex items-center justify-center gap-1.5 cursor-pointer ${
                                  msgChannel === 'sms' ? 'bg-[var(--primary-soft)] border-[var(--primary)] text-[var(--primary)] font-bold' : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)]'
                                }`}
                              >
                                <Icon name="send" size={14} />
                                <span>Masking Bulk SMS</span>
                              </button>
                            </div>
                          </div>

                          {/* Recipient Target */}
                          <div>
                            <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Recipient Target</label>
                            <select
                              value={msgRecipientFilter}
                              onChange={(e) => setMsgRecipientFilter(e.target.value)}
                              className="w-full mt-1 h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                            >
                              <option value="all_due">Bulk: All Customers with Outstanding Due (14 Customers · ৳42,800 Total)</option>
                              <option value="single">Single: {selectedCustomer ? `${selectedCustomer.name_en} (${selectedCustomer.phone})` : 'Rahim Uddin (01711-223344)'}</option>
                              <option value="vip">VIP Wholesale Members (5 Customers)</option>
                              <option value="all">All Registered Customers (30 Customers)</option>
                            </select>
                          </div>

                          {/* Pre-approved Template */}
                          <div>
                            <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Pre-approved Template</label>
                            <select
                              value={msgTemplate}
                              onChange={(e) => {
                                setMsgTemplate(e.target.value);
                                setMsgCustomText(MESSAGE_TEMPLATES[e.target.value] || '');
                              }}
                              className="w-full mt-1 h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                            >
                              <option value="due_soft">Gentle Due Reminder (বিনম্র বকেয়া তাগাদা)</option>
                              <option value="due_firm">Urgent Overdue Notice (জরুরি বকেয়া নোটিশ)</option>
                              <option value="festive_eid">Festive Eid Mubarak Discount Promo (ঈদ মোবারক অফার)</option>
                              <option value="birthday_wish">Birthday Privilege Gift Offer (জন্মদিনের উপহার)</option>
                              <option value="digital_receipt">Digital Sale Receipt & Link (বিক্রয় রশিদ)</option>
                            </select>
                          </div>

                          {/* Dynamic Variable Chips */}
                          <div>
                            <div className="text-[10px] text-[var(--muted-foreground)] mb-1">Click to insert dynamic variable tag:</div>
                            <div className="flex flex-wrap gap-1.5">
                              {['{name}', '{due}', '{last_invoice}', '{shop_phone}'].map(tag => (
                                <button
                                  key={tag}
                                  type="button"
                                  onClick={() => setMsgCustomText(prev => prev + ' ' + tag)}
                                  className="px-2 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-[10px] font-mono text-[var(--foreground)] cursor-pointer"
                                >
                                  + {tag}
                                </button>
                              ))}
                            </div>
                          </div>

                          {/* Editable Message Body Textarea */}
                          <div>
                            <div className="flex justify-between items-center text-[11px] text-[var(--muted-foreground)]">
                              <label className="font-semibold text-[var(--foreground)]">Editable Message Body</label>
                              <span className="font-mono">{msgCustomText.length} chars · {Math.ceil((msgCustomText.length || 1) / 160)} SMS Part(s)</span>
                            </div>
                            <textarea
                              rows={4}
                              value={msgCustomText}
                              onChange={(e) => setMsgCustomText(e.target.value)}
                              placeholder="Type message text here..."
                              className="w-full mt-1 p-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-mono leading-relaxed focus:border-[var(--primary)]"
                            />
                          </div>
                        </div>

                        <div className="pt-3 border-t border-[var(--border)] flex items-center justify-between">
                          <span className="text-xs text-[var(--muted-foreground)]">Estimated Cost: <strong className="text-[var(--foreground)]">৳ 0.35 / msg</strong></span>
                          <button
                            type="button"
                            onClick={() => {
                              const newLog = {
                                id: 'ml_' + Date.now(),
                                date: 'Today',
                                time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                                recipient: msgRecipientFilter === 'single' ? (selectedCustomer?.name_en || 'Customer') : 'Bulk Campaign (' + msgRecipientFilter + ')',
                                phone: msgRecipientFilter === 'single' ? (selectedCustomer?.phone || '01711...') : '14 Recipients',
                                channel: msgChannel === 'whatsapp' ? 'WhatsApp' : 'SMS',
                                snippet: msgCustomText.slice(0, 40) + '...',
                                status: 'Delivered',
                                cost: 0.35
                              };
                              setMessageLogs(prev => [newLog, ...prev]);
                              addToast(`Successfully dispatched ${msgChannel.toUpperCase()} message(s)!`, "success");
                            }}
                            className="h-9 px-5 rounded-lg bg-[var(--success)] hover:opacity-90 text-white text-xs font-bold shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
                          >
                            <Icon name="send" size={14} />
                            <span>{msgRecipientFilter === 'all_due' ? 'Send to 14 Customers' : 'Send Message Now'}</span>
                          </button>
                        </div>
                      </div>

                      {/* Right: Phone Mock Simulator */}
                      <div className="w-full lg:w-80 rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 flex flex-col items-center justify-center shrink-0 shadow-xs">
                        <div className="text-xs font-semibold text-[var(--muted-foreground)] mb-3">Live Recipient Phone Simulator</div>
                        <div className="w-64 h-[420px] rounded-3xl bg-[#0B141A] border-4 border-[var(--border)] p-2.5 flex flex-col justify-between shadow-2xl text-white overflow-hidden">
                          <div className="bg-[#1F2C34] -mx-2.5 -mt-2.5 p-2.5 flex items-center gap-2 border-b border-[#2A3942]">
                            <div className="w-7 h-7 rounded-full bg-[var(--primary)] text-white font-bold flex items-center justify-center text-xs">
                              DF
                            </div>
                            <div className="truncate flex-1">
                              <div className="text-[11px] font-bold text-[#E9EDEF] truncate">Different Fashion</div>
                              <div className="text-[9px] text-[#8696A0]">Official Business Account</div>
                            </div>
                          </div>

                          <div className="flex-1 py-3 px-1 flex flex-col justify-end bg-[#0B141A] space-y-1">
                            <div className="self-end max-w-[95%] p-2.5 rounded-lg rounded-tr-none bg-[#005C4B] text-[#E9EDEF] text-[11px] shadow leading-snug">
                              <div>
                                {msgCustomText
                                  .replace('{name}', selectedCustomer?.name_en || 'Rahim Uddin')
                                  .replace('{due}', formatMoney(Math.abs(selectedCustomer?.balance || 1200)))
                                  .replace('{last_invoice}', 'DF1-R1-26000412')
                                  .replace('{shop_phone}', '01711-223344')}
                              </div>
                              <div className="flex justify-end items-center gap-1 text-[9px] text-[#8696A0] mt-1">
                                <span>10:42 AM</span>
                                <span className="text-[#53BDEB] font-bold">✓✓</span>
                              </div>
                            </div>
                          </div>

                          <div className="bg-[#1F2C34] -mx-2.5 -mb-2.5 p-2 flex items-center gap-2 border-t border-[#2A3942]">
                            <div className="flex-1 h-7 rounded-full bg-[#2A3942] px-3 flex items-center text-[10px] text-[#8696A0]">
                              Type a reply...
                            </div>
                            <div className="w-6 h-6 rounded-full bg-[#00A884] flex items-center justify-center text-white">
                              <Icon name="send" size={11} />
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* TAB 2: TEMPLATES LIBRARY */}
                  {messagingTab === 'templates' && (
                    <div className="space-y-4">
                      <div className="flex items-center justify-between">
                        <div>
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Approved Campaign & Notification Templates</h2>
                          <div className="text-[11px] text-[var(--muted-foreground)]">Pre-configured message formats for automated workflows and customer outreach</div>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {[
                          { title: 'Gentle Due Payment Reminder', cat: 'Finance & Due', tag: 'due_soft', bn: 'বিনম্র বকেয়া তাগাদা', text: 'Dear Valued Customer, gentle reminder: your outstanding due balance at Different Fashion is overdue. Please settle at your convenience. Thank you!' },
                          { title: 'Urgent Overdue Notice', cat: 'Finance & Due', tag: 'due_firm', bn: 'জরুরি বকেয়া নোটিশ', text: 'URGENT: Your account balance of {due} has exceeded the credit term of 30 days. Kindly clear the balance immediately or call 01711-223344.' },
                          { title: 'Festive Eid Mubarak Privilege Promo', cat: 'Marketing', tag: 'festive_eid', bn: 'ঈদ মোবারক অফার', text: 'Eid Mubarak from Different Fashion! Enjoy our exclusive new festive Panjabi & Saree collection with special 15% VIP member privilege discount.' },
                          { title: 'VIP Birthday Celebration Gift', cat: 'Loyalty', tag: 'birthday_wish', bn: 'জন্মদিনের শুভেচ্ছা', text: 'Happy Birthday from Different Fashion! Enjoy a special ৳500 celebration shopping coupon on your next visit this week.' },
                          { title: 'Digital Invoice Receipt & Link', cat: 'Operational', tag: 'digital_receipt', bn: 'বিক্রয় রশিদ', text: 'Thank you for shopping at Different Fashion! View your digital invoice {last_invoice} and loyalty points at df.retailpos.bd/r/{last_invoice}' },
                          { title: 'Security PIN Setup Prompt', cat: 'Security', tag: 'pin_set', bn: 'নিরাপত্তা পিন সেট', text: 'Different Fashion Security Notice: Set your 4-digit customer credit PIN to protect your loyalty balance from unauthorized usage.' }
                        ].map((tpl, idx) => (
                          <div key={idx} className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex flex-col justify-between space-y-3 shadow-xs">
                            <div>
                              <div className="flex items-center justify-between">
                                <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-[var(--surface-sunken)] text-[var(--muted-foreground)]">{tpl.cat}</span>
                                <span className="text-[10px] font-mono text-[var(--primary)] font-semibold">{tpl.tag}</span>
                              </div>
                              <h3 className="font-bold text-xs text-[var(--foreground)] mt-2">{tpl.title}</h3>
                              <div className="text-[11px] text-[var(--muted-foreground)] font-bangla">{tpl.bn}</div>
                              <div className="mt-2 p-2 rounded-lg bg-[var(--surface-sunken)] text-[11px] font-mono text-[var(--foreground)] border border-[var(--border)] leading-relaxed line-clamp-3">
                                {tpl.text}
                              </div>
                            </div>
                            <div className="pt-2 border-t border-[var(--border)] flex gap-2">
                              <button
                                type="button"
                                onClick={() => {
                                  setMsgTemplate(tpl.tag);
                                  setMsgCustomText(tpl.text);
                                  setMessagingTab('composer');
                                  addToast("Loaded template into composer", "info");
                                }}
                                className="flex-1 h-7 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center justify-center gap-1 cursor-pointer"
                              >
                                <Icon name="send" size={12} />
                                <span>Use in Composer</span>
                              </button>
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}

                  {/* TAB 3: AUTOMATION RULES */}
                  {messagingTab === 'automation' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div>
                        <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Automated Event Triggers & Notification Rules</h2>
                        <div className="text-[11px] text-[var(--muted-foreground)]">Configure background system triggers to dispatch SMS & WhatsApp messages automatically</div>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Rule Name</th>
                              <th className="py-2.5 px-3">Trigger Event</th>
                              <th className="py-2.5 px-3">Channel</th>
                              <th className="py-2.5 px-3">Dispatch Timing</th>
                              <th className="py-2.5 px-3 text-center">Status</th>
                              <th className="py-2.5 px-3 text-right">Toggle Action</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {automationRules.map(rule => (
                              <tr key={rule.id} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-semibold text-[var(--foreground)]">{rule.name}</td>
                                <td className="py-2.5 px-3 font-mono text-[var(--primary)]">{rule.event}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{rule.channel}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{rule.delay}</td>
                                <td className="py-2.5 px-3 text-center">
                                  <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                    rule.status ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)]'
                                  }`}>
                                    {rule.status ? 'ACTIVE' : 'PAUSED'}
                                  </span>
                                </td>
                                <td className="py-2.5 px-3 text-right">
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setAutomationRules(prev => prev.map(r => r.id === rule.id ? { ...r, status: !r.status } : r));
                                      addToast(`Rule "${rule.name}" ${!rule.status ? 'activated' : 'paused'}`, "info");
                                    }}
                                    className={`px-3 py-1 rounded text-xs font-semibold cursor-pointer ${
                                      rule.status ? 'bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--muted-foreground)] hover:text-[var(--danger)]' : 'bg-[var(--primary)] text-[var(--primary-foreground)]'
                                    }`}
                                  >
                                    {rule.status ? 'Pause' : 'Activate'}
                                  </button>
                                </td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  )}

                  {/* TAB 4: MESSAGE LOG (DataList!) */}
                  {messagingTab === 'log' && (
                    <div className="flex-1 overflow-hidden flex flex-col">
                      <DataList
                        id="message_log"
                        title="Audit Message Dispatch Log"
                        subtitle="Complete delivery log of WhatsApp Business API & SMS notifications"
                        data={messageLogs}
                        keyField="id"
                        searchPlaceholder="Search recipient, phone, or message text... (/)"
                        searchFields={['recipient', 'phone', 'snippet', 'channel', 'status']}
                        columns={[
                          {
                            id: 'time',
                            header: 'Date & Time',
                            sortable: true,
                            className: 'font-mono text-[var(--muted-foreground)]',
                            accessor: m => m.date + ' ' + m.time,
                            cell: m => m.date + ' · ' + m.time
                          },
                          {
                            id: 'recipient',
                            header: 'Recipient',
                            sortable: true,
                            className: 'font-semibold',
                            accessor: m => m.recipient
                          },
                          {
                            id: 'phone',
                            header: 'Phone Number',
                            sortable: true,
                            className: 'font-mono text-[var(--muted-foreground)]',
                            accessor: m => m.phone
                          },
                          {
                            id: 'channel',
                            header: 'Channel',
                            sortable: true,
                            accessor: m => m.channel,
                            cell: m => (
                              <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                m.channel === 'WhatsApp' ? 'bg-[var(--success)]/15 text-[var(--success)]' : 'bg-[var(--primary-soft)] text-[var(--primary)]'
                              }`}>
                                {m.channel}
                              </span>
                            )
                          },
                          {
                            id: 'snippet',
                            header: 'Message Body Snippet',
                            className: 'truncate max-w-xs text-[var(--foreground)]',
                            accessor: m => m.snippet
                          },
                          {
                            id: 'cost',
                            header: 'Cost',
                            type: 'money',
                            align: 'right',
                            sortable: true,
                            accessor: m => m.cost,
                            cell: m => (
                              <span className="font-sans font-semibold tabular-nums num text-[var(--foreground)]">
                                {formatMoney(m.cost)}
                              </span>
                            )
                          },
                          {
                            id: 'status',
                            header: 'Status',
                            sortable: true,
                            align: 'center',
                            accessor: m => m.status,
                            cell: m => (
                              <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${
                                m.status === 'Delivered' ? 'bg-[var(--success-soft)] text-[var(--success)]' : 'bg-[var(--danger-soft)] text-[var(--danger)]'
                              }`}>
                                {m.status}
                              </span>
                            )
                          },
                          {
                            id: 'actions',
                            header: 'Actions',
                            align: 'right',
                            hideable: false,
                            cell: m => (
                              <button
                                type="button"
                                onClick={() => addToast(`Resent ${m.channel} message to ${m.recipient}`, "success")}
                                className="h-6 px-2 rounded bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[10px] font-semibold text-[var(--primary)] cursor-pointer"
                              >
                                Resend
                              </button>
                            )
                          }
                        ]}
                        filterChips={[
                          {
                            id: 'channel',
                            label: 'Channel',
                            options: [
                              { label: 'All Channels', value: 'all' },
                              { label: 'WhatsApp', value: 'WhatsApp' },
                              { label: 'SMS Gateway', value: 'SMS' }
                            ]
                          }
                        ]}
                        moreFilters={[
                          {
                            id: 'status',
                            label: 'Delivery Status',
                            options: [
                              { label: 'All Statuses', value: 'all' },
                              { label: 'Delivered', value: 'Delivered' },
                              { label: 'Failed', value: 'Failed' }
                            ]
                          }
                        ]}
                        savedViews={[
                          { id: 'default', label: 'Default (All Messages)' },
                          { id: 'whatsapp', label: 'WhatsApp Dispatches', filters: { channel: 'WhatsApp' } },
                          { id: 'sms', label: 'SMS Gateway Dispatches', filters: { channel: 'SMS' } }
                        ]}
                        bulkActions={[
                          {
                            label: 'Export Message Log',
                            icon: 'fileText',
                            action: (rows) => {
                              exportToCSV('messages_log_' + Date.now() + '.csv', [
                                { id: 'time', header: 'Timestamp' },
                                { id: 'recipient', header: 'Recipient' },
                                { id: 'phone', header: 'Phone' },
                                { id: 'channel', header: 'Channel' },
                                { id: 'status', header: 'Status' }
                              ], rows);
                              addToast('Exported ' + rows.length + ' message logs', 'success');
                            }
                          }
                        ]}
                        customFilter={(m, filters) => {
                          if (filters.channel && filters.channel !== 'all' && m.channel !== filters.channel) return false;
                          if (filters.status && filters.status !== 'all' && m.status !== filters.status) return false;
                          return true;
                        }}
                      />
                    </div>
                  )}

                  {/* TAB 5: OPT-OUT / DND LIST */}
                  {messagingTab === 'opt_out' && (
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-4 shadow-xs">
                      <div className="flex items-center justify-between">
                        <div>
                          <h2 className="text-xs font-bold text-[var(--foreground)] uppercase tracking-wider">Do-Not-Disturb (DND) / Unsubscribe Blacklist</h2>
                          <div className="text-[11px] text-[var(--muted-foreground)]">Customers who have requested exclusion from automated promotional SMS & WhatsApp messaging</div>
                        </div>
                        <button
                          type="button"
                          onClick={() => setShowAddOptOutModal(true)}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs cursor-pointer"
                        >
                          <Icon name="plus" size={14} />
                          <span>Add Number to Opt-out</span>
                        </button>
                      </div>

                      <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                        <table className="w-full text-left text-xs">
                          <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                            <tr>
                              <th className="py-2.5 px-3">Phone Number</th>
                              <th className="py-2.5 px-3">Customer Name</th>
                              <th className="py-2.5 px-3">Date Added</th>
                              <th className="py-2.5 px-3">Opt-out Reason</th>
                              <th className="py-2.5 px-3">Excluded Channels</th>
                              <th className="py-2.5 px-3 text-right">Actions</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-[var(--border)]">
                            {optOutList.map(item => (
                              <tr key={item.id} className="hover:bg-[var(--surface-sunken)]/50">
                                <td className="py-2.5 px-3 font-mono font-bold text-[var(--danger)]">{item.phone}</td>
                                <td className="py-2.5 px-3 font-semibold text-[var(--foreground)]">{item.name}</td>
                                <td className="py-2.5 px-3 text-[var(--muted-foreground)]">{item.date}</td>
                                <td className="py-2.5 px-3 text-[var(--foreground)]">{item.reason}</td>
                                <td className="py-2.5 px-3">
                                  <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--surface-sunken)] border border-[var(--border)]">
                                    {item.channel}
                                  </span>
                                </td>
                                <td className="py-2.5 px-3 text-right">
                                  <button
                                    type="button"
                                    onClick={() => {
                                      setOptOutList(prev => prev.filter(o => o.id !== item.id));
                                      addToast(`Removed ${item.phone} from opt-out list`, "success");
                                    }}
                                    className="h-6 px-2 rounded bg-[var(--surface-sunken)] hover:bg-[var(--surface-raised)] border border-[var(--border)] text-[10px] font-semibold text-[var(--danger)] cursor-pointer"
                                  >
                                    Remove
                                  </button>
                                </td>
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
