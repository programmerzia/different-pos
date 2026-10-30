# scripts/parts/render_all_modals.py

RENDER_ALL_MODALS_JS = """
          {/* ================= ALL MODALS & OVERLAYS ================= */}

          {/* 1. CUSTOMER ATTACH MODAL (F4) */}
          {showCustomerAttachModal && (
            <Modal
              isOpen={showCustomerAttachModal}
              onClose={() => setShowCustomerAttachModal(false)}
              title="Attach Customer / Member (F4)"
              subtitle="Search by phone, name, or #1000 0017"
              icon="user"
              size="lg"
            >
              <div className="flex gap-2">
                <div className="relative flex-1">
                  <input
                    type="text"
                    autoFocus
                    value={customerSearchQuery}
                    onChange={(e) => setCustomerSearchQuery(e.target.value)}
                    placeholder="Search phone, name, or #1000 0017..."
                    className="w-full h-8 pl-8 pr-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                  />
                  <Icon name="search" size={14} className="absolute left-2.5 top-2 text-[var(--muted-foreground)]" />
                </div>
                <button
                  onClick={() => { setShowCustomerAttachModal(false); setShowQuickNewCustomerModal(true); }}
                  className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1 shadow-xs cursor-pointer"
                >
                  <Icon name="plus" size={14} />
                  <span>New (Ctrl+N)</span>
                </button>
              </div>

              <div className="max-h-80 overflow-y-auto divide-y divide-[var(--border)] mt-2">
                {MOCK_CUSTOMERS.filter(c => {
                  const q = customerSearchQuery.toLowerCase();
                  return !q || c.name_en.toLowerCase().includes(q) || c.phone.includes(q) || c.customer_no.includes(q);
                }).map(cust => (
                  <div
                    key={cust.id}
                    onClick={() => {
                      setAttachedCustomer(cust);
                      setIsGroupDiscountRemoved(false);
                      setShowCustomerAttachModal(false);
                      addToast(`Attached ${cust.name_en} to sale`, "success");
                    }}
                    className="p-2.5 hover:bg-[var(--surface-sunken)] cursor-pointer flex items-center justify-between transition-colors rounded-lg"
                  >
                    <div>
                      <div className="font-bold text-xs text-[var(--foreground)]">{cust.name_en}</div>
                      <div className="text-[11px] text-[var(--muted-foreground)] flex items-center gap-2">
                        <span>{cust.phone}</span>
                        <span>·</span>
                        <span className="font-mono">#{cust.customer_no}</span>
                      </div>
                    </div>
                    <div className="text-right">
                      <span className="px-2 py-0.5 rounded text-[10px] font-semibold bg-[var(--surface-sunken)] border border-[var(--border)]">
                        {cust.group}
                      </span>
                      {cust.balance < 0 && (
                        <div className="text-[10px] text-[var(--warning)] font-bold mt-0.5">
                          Due: {formatMoney(Math.abs(cust.balance))}
                        </div>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            </Modal>
          )}

          {/* 2. QUICK NEW CUSTOMER MODAL (Ctrl+N) */}
          {showQuickNewCustomerModal && (
            <Modal
              isOpen={showQuickNewCustomerModal}
              onClose={() => setShowQuickNewCustomerModal(false)}
              title="Quick New Customer (Ctrl+N)"
              subtitle="Instantly register customer without leaving checkout flow"
              icon="user"
              size="md"
            >

                <div className="space-y-2.5">
                  <div>
                    <label className="font-semibold text-[11px] text-[var(--muted-foreground)]">Full Name *</label>
                    <input
                      type="text"
                      autoFocus
                      value={quickCustName}
                      onChange={(e) => {
                        setQuickCustName(e.target.value);
                        if (quickCustErrors.name) setQuickCustErrors(prev => ({ ...prev, name: null }));
                      }}
                      placeholder="e.g. Asif Mahmud (আসিফ মাহমুদ)"
                      className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] font-semibold ${
                        quickCustErrors.name ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                      }`}
                    />
                    {quickCustErrors.name && (
                      <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                        <Icon name="alertCircle" size={11} />
                        <span>{quickCustErrors.name}</span>
                      </div>
                    )}
                  </div>

                  <div>
                    <label className="font-semibold text-[11px] text-[var(--muted-foreground)]">Mobile Number (013-019) *</label>
                    <input
                      type="text"
                      value={quickCustPhone}
                      onChange={(e) => {
                        setQuickCustPhone(e.target.value);
                        if (quickCustErrors.phone) setQuickCustErrors(prev => ({ ...prev, phone: null }));
                      }}
                      placeholder="01711-234567"
                      className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold text-[var(--foreground)] ${
                        quickCustErrors.phone ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                      }`}
                    />
                    {quickCustErrors.phone ? (
                      <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                        <Icon name="alertCircle" size={11} />
                        <span>{quickCustErrors.phone}</span>
                      </div>
                    ) : (
                      <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">Must be 11 digits starting with 013, 014, 015, 016, 017, 018, or 019</div>
                    )}
                  </div>

                  <div className="grid grid-cols-2 gap-2">
                    <div>
                      <label className="font-semibold text-[10px] text-[var(--muted-foreground)] uppercase">District</label>
                      <select
                        value={quickCustDistrict}
                        onChange={(e) => setQuickCustDistrict(e.target.value)}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                      >
                        <option value="Dhaka">Dhaka (ঢাকা)</option>
                        <option value="Joypurhat">Joypurhat (জয়পুরহাট)</option>
                        <option value="Bogura">Bogura (বগুড়া)</option>
                        <option value="Chittagong">Chittagong (চট্টগ্রাম)</option>
                        <option value="Sylhet">Sylhet (সিলেট)</option>
                        <option value="Gazipur">Gazipur (গাজীপুর)</option>
                      </select>
                    </div>
                    <div>
                      <label className="font-semibold text-[10px] text-[var(--muted-foreground)] uppercase">Customer Group</label>
                      <select
                        value={quickCustGroup}
                        onChange={(e) => setQuickCustGroup(e.target.value)}
                        className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                      >
                        <option value="Retail">Retail Member (5k limit)</option>
                        <option value="VIP Wholesale">VIP Wholesale (−10% Disc)</option>
                        <option value="Corporate">Corporate Client (−5% Disc)</option>
                      </select>
                    </div>
                  </div>

                  <div>
                    <label className="font-semibold text-[11px] text-[var(--muted-foreground)]">Street Address / Area</label>
                    <input
                      type="text"
                      value={quickCustAddress}
                      onChange={(e) => setQuickCustAddress(e.target.value)}
                      placeholder="e.g. Sector 3, Uttara or Kalai Sadar"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                    />
                  </div>
                </div>

                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setQuickCustErrors({});
                      setShowQuickNewCustomerModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const cleanName = (quickCustName || '').trim();
                      const cleanPhone = (quickCustPhone || '').replace(/[^0-9]/g, '');
                      const errors = {};

                      if (!cleanName || cleanName.length < 3) {
                        errors.name = "Customer name is required (minimum 3 characters)";
                      }

                      if (!cleanPhone || !/^01[3-9]\\d{8}$/.test(cleanPhone)) {
                        errors.phone = "Invalid mobile number (must be 11 digits starting with 013-019)";
                      }

                      if (Object.keys(errors).length > 0) {
                        setQuickCustErrors(errors);
                        addToast("Please correct the errors in customer details", "warning");
                        return;
                      }

                      setQuickCustErrors({});

                      const nextNo = "1000 00" + Math.floor(50 + Math.random() * 40);
                      const newCust = {
                        id: 'c_' + Date.now(),
                        customer_no: nextNo,
                        name_en: cleanName,
                        name_bn: cleanName,
                        phone: cleanPhone,
                        group: quickCustGroup,
                        group_discount: quickCustGroup === 'VIP Wholesale' ? 10 : quickCustGroup === 'Corporate' ? 5 : 0,
                        balance: 0,
                        credit_limit: quickCustGroup === 'VIP Wholesale' ? 50000 : 20000,
                        credit_used: 0,
                        card_status: 'Active',
                        pin_status: 'Set',
                        division: quickCustDistrict === 'Joypurhat' || quickCustDistrict === 'Bogura' ? 'Rajshahi' : 'Dhaka',
                        district: quickCustDistrict,
                        city_upazila: quickCustDistrict === 'Joypurhat' ? 'Kalai' : 'Uttara',
                        street_address: quickCustAddress || 'Holding 12, Main Road',
                        member_since: 'Today',
                        last_purchase: 'None',
                        total_spent: 0,
                        visits: 0,
                        open_invoices: []
                      };

                      MOCK_CUSTOMERS.unshift(newCust);
                      setAttachedCustomer(newCust);
                      setIsGroupDiscountRemoved(false);
                      setShowQuickNewCustomerModal(false);
                      setQuickCustName('');
                      setQuickCustPhone('');
                      setQuickCustAddress('');
                      addToast(`Registered & Attached ${newCust.name_en} (${newCust.customer_no})!`, "success");
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold shadow-xs transition-all cursor-pointer"
                  >
                    Save & Attach to Sale
                  </button>
                </div>
            </Modal>
          )}

          {/* 3. CUSTOMER PIN PAD MODAL */}
          {showCustomerPinPadModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-xs bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-4 text-center space-y-3">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                  <span className="font-bold text-xs text-[var(--foreground)]">Security PIN Verification</span>
                  <button onClick={() => setShowCustomerPinPadModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div className="text-[11px] text-[var(--muted-foreground)]">
                  Customer: <strong>{attachedCustomer?.name_en || 'Customer'}</strong>
                </div>

                <div className="h-10 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-center gap-3">
                  {[0, 1, 2, 3].map(i => (
                    <span key={i} className={`w-3.5 h-3.5 rounded-full transition-all ${
                      pinDigits.length > i ? 'bg-[var(--primary)] scale-110' : 'bg-[var(--border)]'
                    }`}></span>
                  ))}
                </div>

                {pinPadState === 'wrong' && (
                  <div className="text-[11px] text-[var(--danger)] font-bold">Wrong PIN! Attempts left: {pinAttemptsLeft}</div>
                )}
                {pinPadState === 'success' && (
                  <div className="text-[11px] text-[var(--success)] font-bold">PIN Verified Successfully!</div>
                )}

                <div className="grid grid-cols-3 gap-2">
                  {[1, 2, 3, 4, 5, 6, 7, 8, 9, 'C', 0, '⌫'].map(btn => (
                    <button
                      key={btn}
                      onClick={() => {
                        if (btn === 'C') { setPinDigits(''); setPinPadState('input'); return; }
                        if (btn === '⌫') { setPinDigits(prev => prev.slice(0, -1)); setPinPadState('input'); return; }
                        if (pinDigits.length < 4) {
                          const next = pinDigits + btn;
                          setPinDigits(next);
                          if (next.length === 4) {
                            if (next === '1234' || next === '0000') {
                              setPinPadState('success');
                              setTimeout(() => { setShowCustomerPinPadModal(false); addToast("PIN Verified!", "success"); }, 600);
                            } else {
                              setPinPadState('wrong');
                              setPinAttemptsLeft(l => Math.max(0, l - 1));
                            }
                          }
                        }
                      }}
                      className="h-11 rounded-xl bg-[var(--surface-sunken)] hover:bg-[var(--border)] text-sm font-bold font-mono text-[var(--foreground)] flex items-center justify-center transition-colors"
                    >
                      {btn}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* 4. SPLIT PAYMENT & AUTOMATIC DUE / PARTIAL PANEL (F9) */}
          {showPaymentModal && (
            <Modal
              isOpen={showPaymentModal}
              onClose={() => setShowPaymentModal(false)}
              title="Payment & Due Settlement (F9)"
              subtitle="Settle sale with Cash, Card, MFS or Due"
              icon="creditCard"
              size="2xl"
              preventBackdropClose={totalTendered > 0}
              onDiscardConfirm={true}
            >

                <div className="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Left: Tender Inputs */}
                  <div className="space-y-2 text-xs">
                    <div className="font-bold text-xs text-[var(--muted-foreground)] mb-1">Tender Breakdown</div>
                    {[
                      { id: 'cash', label: 'Cash Tender', icon: 'banknote' },
                      { id: 'card', label: 'POS Card Terminal', icon: 'creditCard' },
                      { id: 'bkash', label: 'bKash Merchant Pay', icon: 'smartphone' },
                      { id: 'nagad', label: 'Nagad Pay', icon: 'smartphone' },
                      { id: 'rocket', label: 'DBBL Rocket', icon: 'smartphone' },
                      { id: 'bank', label: 'City Bank Transfer', icon: 'layers' }
                    ].map(tender => (
                      <div key={tender.id} className="flex items-center justify-between gap-2">
                        <span className="text-[11px] font-medium text-[var(--foreground)] flex items-center gap-1.5 w-36 truncate">
                          <Icon name={tender.icon} size={13} className="text-[var(--muted-foreground)]" />
                          <span>{tender.label}</span>
                        </span>
                        <input
                          type="number"
                          value={tenderTally[tender.id] || ''}
                          onChange={(e) => setTenderTally(prev => ({ ...prev, [tender.id]: Number(e.target.value) || 0 }))}
                          placeholder="0"
                          className="flex-1 h-7 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-right font-mono font-bold text-xs"
                        />
                      </div>
                    ))}

                    {/* Quick Cash Buttons */}
                    <div className="pt-2 flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => {
                          setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                          addToast("Exact cash applied (Alt+E)", "info");
                        }}
                        className="px-2 py-1 rounded bg-[var(--primary-soft)] border border-[var(--primary)] text-[var(--primary)] font-mono text-[10px] font-bold hover:bg-[var(--primary)] hover:text-white flex items-center gap-1 cursor-pointer"
                        title="Exact Cash (Alt+E)"
                      >
                        <span>Exact Cash</span>
                        <kbd className="text-[9px] font-mono">Alt+E</kbd>
                      </button>
                      {[100, 500, 1000].map(amt => (
                        <button
                          key={amt}
                          onClick={() => setTenderTally(prev => ({ ...prev, cash: (Number(prev.cash) || 0) + amt }))}
                          className="flex-1 py-1 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-[10px] font-semibold hover:border-[var(--primary)]"
                        >
                          +{amt}
                        </button>
                      ))}
                      <button
                        onClick={() => setTenderTally({ cash: 0, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 })}
                        className="px-2 py-1 rounded bg-[var(--danger-soft)] text-[var(--danger)] font-mono text-[10px] font-semibold"
                      >
                        Reset
                      </button>
                    </div>
                  </div>

                  {/* Right: Due Auto-Calculation & Credit Limit Meter */}
                  <div className="p-4 rounded-xl bg-[var(--surface-sunken)] flex flex-col justify-between space-y-3">
                    <div className="space-y-2 text-xs">
                      <div className="flex justify-between text-[var(--muted-foreground)]">
                        <span>Total Payable:</span>
                        <span className="num font-bold text-[var(--foreground)] text-sm">{formatMoney(totalPayable)}</span>
                      </div>
                      <div className="flex justify-between text-[var(--muted-foreground)]">
                        <span>Total Tendered:</span>
                        <span className="num font-bold text-[var(--success)] text-sm">{formatMoney(totalTendered)}</span>
                      </div>

                      {/* DUE / PARTIAL PAYMENT NOTICE (Target: automatic remainder becomes due in amber) */}
                      {tenderDue > 0 ? (
                        <div className="p-2.5 rounded-xl bg-[var(--warning-soft)] border border-[var(--warning)]/30 text-[var(--warning)] text-[var(--warning)] space-y-1">
                          <div className="flex items-center justify-between font-bold text-xs">
                            <span className="flex items-center gap-1">
                              <Icon name="alertTriangle" size={13} />
                              <span>Paid: {formatMoney(totalTendered)}</span>
                            </span>
                            <span>Due: {formatMoney(tenderDue)}</span>
                          </div>
                          {totalTendered === 0 && (
                            <div className="text-[10px] font-bold text-[var(--warning)] text-[var(--warning)]">Full credit sale (৳0 paid)</div>
                          )}

                          {/* Customer Required for Due Sale */}
                          {!attachedCustomer ? (
                            <div className="pt-2 border-t border-[var(--warning)]/30 flex items-center justify-between">
                              <span className="text-[10px] text-[var(--danger)] font-bold">Attach customer to sell on due (F4)</span>
                              <button
                                onClick={() => setShowCustomerAttachModal(true)}
                                className="h-6 px-2 rounded bg-[var(--warning)] text-white font-bold text-[10px]"
                              >
                                Attach (F4)
                              </button>
                            </div>
                          ) : (
                            /* Customer Credit Limit Bar */
                            <div className="pt-2 border-t border-[var(--warning)]/30 space-y-1">
                              <div className="flex justify-between text-[10px] font-mono">
                                <span>Credit Limit:</span>
                                <span>Used {formatMoney((attachedCustomer.credit_used || Math.abs(attachedCustomer.balance || 0)) + tenderDue)} of {formatMoney(attachedCustomer.credit_limit || 20000)}</span>
                              </div>
                              <div className="h-1.5 w-full bg-black/10 rounded-full overflow-hidden">
                                <div
                                  style={{ width: `${Math.min(100, Math.round((((attachedCustomer.credit_used || Math.abs(attachedCustomer.balance || 0)) + tenderDue) / (attachedCustomer.credit_limit || 20000)) * 100))}%` }}
                                  className={`h-full ${isCreditLimitExceeded ? 'bg-[var(--danger)]' : 'bg-[var(--warning)]'}`}
                                ></div>
                              </div>

                              {isCreditLimitExceeded && (
                                <div className="p-1.5 rounded bg-[var(--danger-soft)] border border-[var(--danger)]/30 text-[10px] text-[var(--danger)] font-bold flex items-center justify-between mt-1">
                                  <span>Limit Exceeded! Manager PIN:</span>
                                  <input
                                    type="password"
                                    placeholder="••••"
                                    value={dueManagerPinOverride}
                                    onChange={(e) => setDueManagerPinOverride(e.target.value)}
                                    className="w-16 h-6 px-1 rounded bg-[var(--surface)] border text-center font-mono"
                                  />
                                </div>
                              )}

                              <div className="grid grid-cols-2 gap-1.5 pt-1 text-[10px]">
                                <div>
                                  <span className="text-[var(--muted-foreground)]">Promise Date:</span>
                                  <input type="date" value={duePromiseDate} onChange={(e) => setDuePromiseDate(e.target.value)} className="w-full h-6 px-1 rounded bg-[var(--surface)] border text-[10px]" />
                                </div>
                                <div>
                                  <span className="text-[var(--muted-foreground)]">Customer PIN:</span>
                                  <input type="password" placeholder="••••" value={dueCustomerPin} onChange={(e) => setDueCustomerPin(e.target.value)} className="w-full h-6 px-1 rounded bg-[var(--surface)] border text-[10px] font-mono" />
                                </div>
                              </div>
                            </div>
                          )}

                          {isOfflineMode && (
                            <div className="text-[10px] text-[var(--danger)] font-bold pt-1">
                              ⚠ Due sales disabled in offline mode (Needs internet).
                            </div>
                          )}
                        </div>
                      ) : (
                        <div className="pt-2 border-t border-[var(--border)] flex justify-between font-bold">
                          <span>Change to Return:</span>
                          <span className="num text-base text-[var(--success)]">{formatMoney(tenderChange)}</span>
                        </div>
                      )}
                    </div>

                    <button
                      onClick={() => completeSale('split')}
                      disabled={(tenderDue > 0 && (!attachedCustomer || isOfflineMode || (isCreditLimitExceeded && dueManagerPinOverride !== '1234' && dueManagerPinOverride !== '9999')))}
                      className="w-full h-12 rounded-xl bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold transition-colors shadow-md disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
                    >
                      <span>{tenderDue > 0 ? 'Confirm Partial / Due Sale' : 'Complete Sale & Print Receipt'}</span>
                      <kbd className="px-1.5 py-0.5 rounded bg-black/25 text-white text-[10px] font-mono">F10</kbd>
                    </button>
                  </div>
                </div>
            </Modal>
          )}

          {/* 5. 80MM THERMAL RECEIPT MODAL (Includes Struck-through Discounts & Previous + Current Due) */}
          {showReceiptModal && lastCompletedSale && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="printable-area printable-receipt w-full max-w-sm bg-white text-black rounded-xl shadow-2xl p-5 flex flex-col max-h-[90vh] overflow-y-auto">
                <div className="text-center space-y-1 pb-3 border-b border-dashed border-[var(--input)]">
                  <div className="font-extrabold text-sm tracking-widest uppercase">DIFFERENT FASHION</div>
                  <div className="text-[10px] text-[var(--muted-foreground)]">Uttara Sector 3, Dhaka-1230</div>
                  <div className="text-[10px] font-mono text-[var(--muted-foreground)]">BIN: 002847192-0101 · Mushak 6.3</div>
                  <div className="text-[10px] text-[var(--muted-foreground)]">Tel: 01711-223344</div>
                </div>

                <div className="py-2.5 text-[10px] space-y-0.5 border-b border-dashed border-[var(--input)]">
                  <div className="flex justify-between">
                    <span>Invoice: <strong className="font-mono">{lastCompletedSale.invoice}</strong></span>
                    <span>{lastCompletedSale.time}</span>
                  </div>
                  <div className="flex justify-between">
                    <span>Cashier: {lastCompletedSale.cashier}</span>
                    <span>{lastCompletedSale.date || 'Today'}</span>
                  </div>
                  <div>Customer: {lastCompletedSale.customer} ({lastCompletedSale.customer_no})</div>
                </div>

                {/* Items with Line Discounts */}
                <div className="py-2.5 space-y-1 text-[10px] border-b border-dashed border-[var(--input)]">
                  {(lastCompletedSale.items || []).map((it, idx) => (
                    <div key={idx} className="flex justify-between">
                      <div className="flex-1 truncate">
                        <div>{it.product?.name_en || 'Product'}</div>
                        <div className="text-[var(--muted-foreground)] font-mono">
                          {it.qty} × {formatMoney(it.original_price)}
                          {it.line_discount > 0 && <span className="text-[var(--danger)] ml-1">(-{formatMoney(it.line_discount)})</span>}
                        </div>
                      </div>
                      <span className="num font-bold">{formatMoney(((it.original_price || 1450) - (it.line_discount || 0)) * it.qty)}</span>
                    </div>
                  ))}
                </div>

                {/* Financials & Discounts */}
                <div className="py-2.5 text-[10px] space-y-1 border-b border-dashed border-[var(--input)]">
                  <div className="flex justify-between">
                    <span>Subtotal:</span>
                    <span className="num">{formatMoney(lastCompletedSale.subtotal || lastCompletedSale.total)}</span>
                  </div>
                  {lastCompletedSale.totalDiscount > 0 && (
                    <div className="flex justify-between text-[var(--danger)] font-semibold">
                      <span>Total Discounts:</span>
                      <span className="num">−{formatMoney(lastCompletedSale.totalDiscount)}</span>
                    </div>
                  )}
                  <div className="flex justify-between">
                    <span>VAT (15%):</span>
                    <span className="num">{formatMoney(lastCompletedSale.vatAmount || 0)}</span>
                  </div>
                  <div className="flex justify-between font-bold text-xs pt-1 border-t border-[var(--border)]">
                    <span>TOTAL:</span>
                    <span className="num">{formatMoney(lastCompletedSale.total)}</span>
                  </div>
                  <div className="flex justify-between text-[var(--subtle-foreground)]">
                    <span>Tendered ({lastCompletedSale.paymentMethod}):</span>
                    <span className="num">{formatMoney(lastCompletedSale.tendered)}</span>
                  </div>
                  {lastCompletedSale.change > 0 && (
                    <div className="flex justify-between font-bold text-[var(--success)]">
                      <span>Change:</span>
                      <span className="num">{formatMoney(lastCompletedSale.change)}</span>
                    </div>
                  )}

                  {/* DUE BREAKDOWN ON RECEIPT (Target: This bill due · Previous due · TOTAL DUE) */}
                  {lastCompletedSale.due > 0 && (
                    <div className="pt-1.5 border-t border-dashed border-[var(--input)] space-y-0.5 text-[var(--danger)] font-bold">
                      <div className="flex justify-between">
                        <span>This Bill Due:</span>
                        <span className="num">{formatMoney(lastCompletedSale.due)}</span>
                      </div>
                      <div className="flex justify-between">
                        <span>Previous Due:</span>
                        <span className="num">{formatMoney(lastCompletedSale.previous_due || 1200)}</span>
                      </div>
                      <div className="flex justify-between text-xs pt-0.5 border-t border-[var(--border)]">
                        <span>TOTAL DUE:</span>
                        <span className="num">{formatMoney((lastCompletedSale.previous_due || 1200) + lastCompletedSale.due)}</span>
                      </div>
                    </div>
                  )}
                </div>

                {/* Footer & Barcode */}
                <div className="pt-3 text-center space-y-2">
                  <div className="text-[9px] text-[var(--muted-foreground)] leading-tight font-bangla">
                    পণ্য পরিবর্তনের ক্ষেত্রে ৭ দিনের মধ্যে মূল ক্যাশ মেমো সহ যোগাযোগ করুন।<br />
                    Thank you for shopping at Different Fashion!
                  </div>
                  <div className="flex flex-col items-center">
                    <div className="h-6 w-32 flex items-center justify-between">
                      {[2,1,3,1,2,3,1,2,1,3,2,1,2,3].map((w, i) => (
                        <span key={i} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                      ))}
                    </div>
                    <span className="text-[8px] font-mono tracking-widest mt-0.5">{lastCompletedSale.invoice}</span>
                  </div>
                </div>

                <div className="no-print mt-4 pt-3 border-t border-[var(--border)] flex gap-2">
                  <button onClick={() => setShowReceiptModal(false)} className="flex-1 h-8 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]">
                    Close (Esc)
                  </button>
                  <button onClick={() => { window.print(); addToast("Printing 80mm receipt...", "success"); }} className="flex-1 h-8 rounded bg-[var(--primary-hover)] text-white text-xs font-semibold flex items-center justify-center gap-1">
                    <Icon name="printer" size={12} />
                    <span>Print 80mm</span>
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 6. A4 TAX INVOICE MODAL */}
          {showA4InvoiceModal && lastCompletedSale && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="printable-area printable-receipt w-full max-w-2xl bg-white text-black rounded-xl shadow-2xl p-6 flex flex-col max-h-[90vh] overflow-y-auto space-y-4">
                <div className="flex justify-between items-start border-b pb-4">
                  <div>
                    <h2 className="text-xl font-extrabold text-[var(--primary)]">DIFFERENT FASHION</h2>
                    <p className="text-xs text-[var(--muted-foreground)]">Holding 42, Level 2, Sector 3, Uttara, Dhaka-1230</p>
                    <p className="text-xs text-[var(--muted-foreground)]">Phone: 01711-223344 · info@differentfashion.bd</p>
                    <p className="text-xs font-mono font-bold mt-1">BIN: 002847192-0101 (Mushak 6.3 Tax Invoice)</p>
                  </div>
                  <div className="text-right">
                    <div className="text-sm font-bold font-mono text-[var(--foreground)]">INVOICE: {lastCompletedSale.invoice}</div>
                    <div className="text-xs text-[var(--muted-foreground)]">Date: {lastCompletedSale.date || 'Today'}</div>
                  </div>
                </div>

                <div className="text-xs">
                  <div className="font-bold text-[var(--subtle-foreground)]">Bill To:</div>
                  <div>{lastCompletedSale.customer}</div>
                </div>

                <table className="w-full text-xs text-left border">
                  <thead className="bg-[var(--surface-sunken)] border-b">
                    <tr>
                      <th className="p-2">Description</th>
                      <th className="p-2 text-center">Qty</th>
                      <th className="p-2 text-right">Unit Price</th>
                      <th className="p-2 text-right">Line Discount</th>
                      <th className="p-2 text-right">Amount</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {(lastCompletedSale.items || []).map((it, idx) => (
                      <tr key={idx}>
                        <td className="p-2 font-medium">{it.product?.name_en || 'Product'}</td>
                        <td className="p-2 text-center font-mono">{it.qty}</td>
                        <td className="p-2 text-right num">{formatMoney(it.original_price)}</td>
                        <td className="p-2 text-right num text-[var(--danger)]">{it.line_discount > 0 ? `−${formatMoney(it.line_discount)}` : '—'}</td>
                        <td className="p-2 text-right num font-bold">{formatMoney(((it.original_price || 1450) - (it.line_discount || 0)) * it.qty)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>

                <div className="flex justify-end text-xs">
                  <div className="w-56 space-y-1">
                    <div className="flex justify-between">
                      <span>Total Amount:</span>
                      <span className="num font-bold">{formatMoney(lastCompletedSale.total)}</span>
                    </div>
                    {lastCompletedSale.due > 0 && (
                      <div className="flex justify-between text-[var(--danger)] font-bold">
                        <span>Due Remaining:</span>
                        <span className="num">{formatMoney(lastCompletedSale.due)}</span>
                      </div>
                    )}
                  </div>
                </div>

                <div className="no-print pt-4 border-t flex justify-end gap-2">
                  <button onClick={() => setShowA4InvoiceModal(false)} className="px-3 h-8 rounded border text-xs">Close</button>
                  <button onClick={() => { window.print(); addToast("Printing A4 Tax Invoice...", "success"); }} className="px-4 h-8 rounded bg-[var(--primary-hover)] text-white text-xs font-bold">Print A4</button>
                </div>
              </div>
            </div>
          )}

          {/* 7. PRICE & STOCK CHECK MODAL (F5) */}
          {showPriceCheckModal && (
            <Modal
              isOpen={showPriceCheckModal}
              onClose={() => setShowPriceCheckModal(false)}
              title="Price & Stock Check (F5)"
              subtitle="Scan barcode or type SKU to instantly verify price & stock"
              icon="tag"
              size="lg"
            >
              <div className="p-1">
                <input
                  type="text"
                  autoFocus
                  value={priceCheckQuery}
                  onChange={(e) => setPriceCheckQuery(e.target.value)}
                  placeholder="Scan barcode or type product name/SKU..."
                  className="w-full h-9 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-mono"
                />
              </div>

              <div className="max-h-72 overflow-y-auto divide-y divide-[var(--border)] mt-2">
                {INITIAL_PRODUCTS.filter(p => {
                  const q = priceCheckQuery.toLowerCase();
                  return !q || p.name_en.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || p.barcode.includes(q);
                }).slice(0, 10).map(prod => (
                  <div key={prod.id} className="p-2.5 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded-lg">
                    <div>
                      <div className="font-semibold text-xs text-[var(--foreground)]">{prod.name_en}</div>
                      <div className="text-[11px] text-[var(--muted-foreground)] font-mono">{prod.sku} · {prod.barcode}</div>
                    </div>
                    <div className="text-right">
                      <div className="font-bold text-sm text-[var(--primary)] num">{formatMoney(prod.price)}</div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Main Stock: {prod.stock_main} pcs</div>
                    </div>
                  </div>
                ))}
              </div>
            </Modal>
          )}

          {/* 8B. PARKED / HELD SALES MODAL (Shift+F8) */}
          {showHeldSalesModal && (
            <Modal
              isOpen={showHeldSalesModal}
              onClose={() => setShowHeldSalesModal(false)}
              title="Parked / Held Sales"
              subtitle="Retrieve or discard temporarily held customer baskets"
              icon="pause"
              badge={heldSales.length > 0 ? `${heldSales.length} Held` : '0'}
              size="lg"
            >
              {heldSales.length === 0 ? (
                <div className="py-12 text-center text-[var(--muted-foreground)]">
                  <Icon name="pause" size={32} className="mx-auto mb-2 opacity-40" />
                  <div className="font-semibold text-sm">No sales currently held</div>
                  <p className="text-xs mt-1">Press <kbd className="font-mono">F8</kbd> during an active sale to hold the cart.</p>
                </div>
              ) : (
                <div className="space-y-2.5">
                  {heldSales.map((held, idx) => (
                    <div key={held.id} className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex items-center justify-between gap-3">
                      <div>
                        <div className="flex items-center gap-2">
                          <span className="font-bold text-xs text-[var(--foreground)]">
                            {held.customer ? held.customer.name_en : 'Walk-in Customer'}
                          </span>
                          <span className="text-[10px] px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] font-mono text-[var(--muted-foreground)]">
                            {held.customer?.customer_no || 'Walk-in'}
                          </span>
                          <span className="text-[10px] text-[var(--muted-foreground)]">Held at {held.time || 'Today'}</span>
                        </div>
                        <div className="text-[11px] text-[var(--muted-foreground)] mt-1">
                          {held.cart.length} item(s): {held.cart.map(c => `${c.product.name_en} × ${c.qty}`).join(', ')}
                        </div>
                      </div>
                      <div className="flex items-center gap-2">
                        <div className="text-right mr-2 font-mono font-bold text-sm text-[var(--foreground)]">
                          {formatMoney(held.total || held.cart.reduce((s, c) => s + (c.unit_price * c.qty), 0))}
                        </div>
                        <button
                          onClick={() => recallSale(held)}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold flex items-center gap-1 cursor-pointer"
                        >
                          <Icon name="refresh" size={13} />
                          <span>Recall</span>
                        </button>
                        <button
                          onClick={() => {
                            setHeldSales(prev => prev.filter(h => h.id !== held.id));
                            addToast("Held sale discarded", "info");
                          }}
                          className="h-8 w-8 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--danger-soft)] hover:text-[var(--danger)] border border-[var(--border)] text-[var(--muted-foreground)] flex items-center justify-center cursor-pointer"
                          title="Discard held sale"
                        >
                          <Icon name="trash" size={14} />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </Modal>
          )}

          {/* 8. DISCOUNT POPOVER / MODAL (F6 Line Disc, Shift+F6 Order Disc) */}
          {showDiscountModal && (
            <Modal
              isOpen={showDiscountModal}
              onClose={() => setShowDiscountModal(false)}
              title={discountTarget === 'line' ? "Line Item Discount (F6)" : "Order / Bill Discount (Shift+F6)"}
              subtitle={discountTarget === 'line' ? (cart[selectedCartLineIndex]?.name_en || "Selected Cart Line") : "Entire Order / Bill"}
              icon="tag"
              size="md"
            >
              <div className="space-y-3">

                {/* Tabs: % · ৳ amount · Set price */}
                <div className="grid grid-cols-3 gap-1 bg-[var(--surface-sunken)] p-1 rounded-lg">
                  <button
                    type="button"
                    onClick={() => { setDiscountTab('percent'); setDiscountValue(''); }}
                    className={`py-1 rounded font-bold ${discountTab === 'percent' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                  >
                    % Percent
                  </button>
                  <button
                    type="button"
                    onClick={() => { setDiscountTab('amount'); setDiscountValue(''); }}
                    className={`py-1 rounded font-bold ${discountTab === 'amount' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                  >
                    ৳ Amount
                  </button>
                  <button
                    type="button"
                    onClick={() => { setDiscountTab('set_price'); setDiscountValue(''); }}
                    className={`py-1 rounded font-bold ${discountTab === 'set_price' ? 'bg-[var(--surface)] text-[var(--primary)] shadow-xs' : 'text-[var(--muted-foreground)]'}`}
                  >
                    Set Price
                  </button>
                </div>

                {/* Quick Chips: 5% / 10% (Default) / 15% / 20% */}
                <div>
                  <div className="text-[10px] font-semibold text-[var(--muted-foreground)] mb-1">Quick Select:</div>
                  <div className="grid grid-cols-4 gap-1.5">
                    {[5, 10, 15, 20].map(pct => (
                      <button
                        key={pct}
                        type="button"
                        onClick={() => {
                          setDiscountTab('percent');
                          setDiscountValue(pct.toString());
                          if (pct > currentRoleLimit) {
                            setShowManagerPinInline(true);
                          } else {
                            setShowManagerPinInline(false);
                          }
                        }}
                        className={`py-1 rounded border font-mono font-bold text-xs ${
                          discountValue === pct.toString() && discountTab === 'percent'
                            ? 'bg-[var(--primary)] text-white border-[var(--primary)]' 
                            : 'bg-[var(--surface-sunken)] border-[var(--border)] hover:border-[var(--primary)] text-[var(--foreground)]'
                        }`}
                      >
                        {pct} %
                      </button>
                    ))}
                  </div>
                </div>

                {/* Custom Input */}
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">
                    {discountTab === 'percent' ? 'Discount Percentage (%)' : discountTab === 'amount' ? 'Discount Amount (BDT)' : 'New Fixed Selling Price (BDT)'}
                  </label>
                  <input
                    type="number"
                    autoFocus
                    value={discountValue}
                    onChange={(e) => {
                      const val = e.target.value;
                      setDiscountValue(val);
                      const num = Number(val) || 0;
                      if (discountTab === 'percent' && num > currentRoleLimit) {
                        setShowManagerPinInline(true);
                      } else {
                        setShowManagerPinInline(false);
                      }
                    }}
                    placeholder="0"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono font-bold text-xs text-[var(--foreground)]"
                  />
                </div>

                {/* Reason Dropdown (Mandatory for >10%) */}
                <div>
                  <div className="flex items-center justify-between">
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">
                      Reason for Discount {((discountTab === 'percent' && Number(discountValue) > 10) || showManagerPinInline) && <span className="text-[var(--danger)] font-bold">* Required (&gt;10%)</span>}
                    </label>
                  </div>
                  <select
                    value={discountReason}
                    onChange={(e) => setDiscountReason(e.target.value)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                  >
                    <option>Seasonal Eid Promo</option>
                    <option>Customer Courtesy</option>
                    <option>Clearance Sale</option>
                    <option>Fabric Defect / Stained</option>
                    <option>Bulk Purchase</option>
                    <option>Manager Special Approval</option>
                    <option>Other</option>
                  </select>
                </div>

                {/* Custom Reason Input if "Other" is selected */}
                {discountReason === 'Other' && (
                  <div className="animate-in fade-in duration-150">
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Specify Custom Reason *</label>
                    <input
                      type="text"
                      placeholder="e.g. Owner direct authorized discount"
                      value={discountCustomReason}
                      onChange={(e) => setDiscountCustomReason(e.target.value)}
                      className="mt-1 w-full h-7 px-2 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                    />
                  </div>
                )}

                {/* Manager PIN Prompt if Above 10% / Role Limit */}
                {(showManagerPinInline || (discountTab === 'percent' && Number(discountValue) > currentRoleLimit)) && (
                  <div className="p-2.5 rounded-xl bg-[var(--warning-soft)] border border-[var(--warning)]/30 text-[var(--warning)] text-[var(--warning)] space-y-1.5 animate-in fade-in duration-200">
                    <div className="font-bold text-[11px] flex items-center justify-between">
                      <span>Manager PIN required (&gt;{currentRoleLimit}%)</span>
                      <span className="text-[10px] font-normal opacity-75">Demo PIN: 1234</span>
                    </div>
                    <input
                      type="password"
                      placeholder="Enter Manager PIN (1234 or 9999)"
                      value={discountManagerPin}
                      onChange={(e) => setDiscountManagerPin(e.target.value)}
                      className="w-full h-7 px-2 rounded bg-[var(--surface)] border border-[var(--warning)] font-mono text-center text-xs"
                    />
                  </div>
                )}

                <div className="pt-2 flex gap-2">
                  <button type="button" onClick={() => setShowDiscountModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-[var(--foreground)]">Cancel</button>
                  <button
                    type="button"
                    onClick={() => {
                      const num = Number(discountValue) || 0;
                      const isOverLimit = (discountTab === 'percent' && num > currentRoleLimit) || (discountTab === 'amount' && (num / Math.max(1, (discountTarget === 'line' ? (cart[selectedCartLineIndex]?.original_price || 1) : subtotal))) * 100 > currentRoleLimit);

                      if (isOverLimit) {
                        if (discountReason === 'Other' && !discountCustomReason.trim()) {
                          addToast("Please specify the custom discount reason", "warning");
                          return;
                        }
                        if (discountManagerPin !== '1234' && discountManagerPin !== '9999') {
                          addToast("Manager PIN invalid! Discount rejected.", "danger");
                          return;
                        }
                      }

                      if (discountTarget === 'line') {
                        setCart(prev => {
                          const next = [...prev];
                          const item = next[selectedCartLineIndex];
                          if (item) {
                            let lineDisc = 0;
                            if (discountTab === 'percent') {
                              lineDisc = Math.round(item.original_price * (num / 100));
                              item.discount_percent = num;
                              item.discount_type = 'percent';
                            } else if (discountTab === 'amount') {
                              lineDisc = num;
                              item.discount_percent = Math.round((num / item.original_price) * 100);
                              item.discount_type = 'amount';
                            } else if (discountTab === 'set_price') {
                              lineDisc = Math.max(0, item.original_price - num);
                              item.discount_percent = Math.round((lineDisc / item.original_price) * 100);
                              item.discount_type = 'set_price';
                            }
                            item.line_discount = lineDisc;
                          }
                          return next;
                        });
                        addToast(`Line discount applied to item (${num}${discountTab === 'percent' ? '%' : ' BDT'})`, "success");
                      } else {
                        // Order discount
                        if (discountTab === 'percent') {
                          setOrderDiscountType('percent');
                          setOrderDiscountPercent(num);
                        } else {
                          setOrderDiscountType('amount');
                          setOrderDiscount(num);
                        }
                        addToast(`Order discount applied (${num}${discountTab === 'percent' ? '%' : ' BDT'})`, "success");
                      }
                      setShowDiscountModal(false);
                      setDiscountValue('');
                      setDiscountManagerPin('');
                      setDiscountCustomReason('');
                      setShowManagerPinInline(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs"
                  >
                    Apply Discount
                  </button>
                </div>
              </div>
            </Modal>
          )}
          {/* 9. COLLECT DUE MODAL (Ctrl+D / Fast Due Collection) */}
          {showDueCollectModal && dueCollectCustomer && (
            <Modal
              isOpen={showDueCollectModal && !!dueCollectCustomer}
              onClose={() => setShowDueCollectModal(false)}
              title="Collect Customer Due (Ctrl+D)"
              subtitle={`Customer: ${dueCollectCustomer.name_en} (${dueCollectCustomer.customer_no})`}
              icon="banknote"
              size="lg"
            >
              <div className="space-y-4">

                <div className="p-3 rounded-xl bg-[var(--warning-soft)] border border-[var(--warning)]/30 flex justify-between items-center">
                  <div>
                    <div className="text-[10px] text-[var(--muted-foreground)]">Total Outstanding Due Balance:</div>
                    <div className="font-mono font-extrabold text-lg text-[var(--danger)] num">
                      {formatMoney(Math.abs(dueCollectCustomer.balance || 0))}
                    </div>
                  </div>
                  <div className="text-right">
                    <span className="text-[10px] text-[var(--warning)] text-[var(--warning)] font-semibold">Credit Limit: {formatMoney(dueCollectCustomer.credit_limit || 20000)}</span>
                  </div>
                </div>

                {/* Open Invoices with Oldest-First Allocation */}
                <div className="space-y-1.5">
                  <div className="font-bold text-xs text-[var(--muted-foreground)]">Open Invoices (Auto-allocated Oldest First)</div>
                  <div className="max-h-36 overflow-y-auto divide-y divide-[var(--border)] border border-[var(--border)] rounded-xl">
                    {dueCollectInvoices.map((inv, idx) => (
                      <div key={idx} className="p-2.5 bg-[var(--surface-sunken)] flex justify-between items-center text-[11px]">
                        <div>
                          <span className="font-mono font-bold text-[var(--primary)]">{inv.invoice}</span>
                          <span className="text-[10px] text-[var(--muted-foreground)] ml-2">{inv.date}</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <span className="num text-[var(--muted-foreground)]">Due: {formatMoney(inv.due)}</span>
                          <span className="font-bold num text-[var(--success)]">Alloc: {formatMoney(inv.allocated || inv.due)}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>

                {/* Tender & Amount */}
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="font-semibold text-[var(--muted-foreground)]">Collection Amount (Partial Allowed)</label>
                    <input
                      type="number"
                      autoFocus
                      value={dueCollectAmount}
                      onChange={(e) => {
                        setDueCollectAmount(e.target.value);
                        if (dueCollectError) setDueCollectError(null);
                      }}
                      className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-mono font-bold text-xs ${
                        dueCollectError ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                      }`}
                    />
                    {dueCollectError && (
                      <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                        <Icon name="alertCircle" size={11} />
                        <span>{dueCollectError}</span>
                      </div>
                    )}
                  </div>
                  <div>
                    <label className="font-semibold text-[var(--muted-foreground)]">Payment Tender</label>
                    <select
                      value={dueCollectMethod}
                      onChange={(e) => setDueCollectMethod(e.target.value)}
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                    >
                      <option>Cash</option>
                      <option>bKash</option>
                      <option>Nagad</option>
                      <option>POS Card</option>
                      <option>City Bank Transfer</option>
                    </select>
                  </div>
                </div>

                <div className="p-2.5 rounded-xl bg-[var(--surface-sunken)] flex justify-between items-center text-xs">
                  <span>Live Remaining Due after Collection:</span>
                  <span className="font-mono font-bold text-[var(--foreground)]">
                    {formatMoney(Math.max(0, Math.abs(dueCollectCustomer.balance || 0) - (Number(dueCollectAmount) || 0)))}
                  </span>
                </div>

                <div className="pt-2 flex gap-2">
                  <button onClick={() => setShowDueCollectModal(false)} className="flex-1 h-9 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-medium">Cancel</button>
                  <button
                    onClick={handleExecuteCollectDue}
                    className="flex-1 h-9 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs flex items-center justify-center gap-1.5"
                  >
                    <Icon name="check" size={14} />
                    <span>Print Money Receipt & Settle</span>
                  </button>
                </div>
              </div>
            </Modal>
          )}

          {/* 10. MONEY RECEIPT MODAL (Collected Due Voucher) */}
          {showMoneyReceiptModal && lastMoneyReceipt && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-white text-black rounded-xl shadow-2xl p-5 flex flex-col space-y-3">
                <div className="text-center space-y-1 pb-2 border-b border-dashed border-[var(--input)]">
                  <div className="font-extrabold text-sm tracking-widest uppercase">DIFFERENT FASHION</div>
                  <div className="text-[10px] text-[var(--muted-foreground)]">OFFICIAL MONEY RECEIPT (DUE PAYMENT)</div>
                  <div className="font-mono text-[10px] font-bold">{lastMoneyReceipt.receipt_no}</div>
                </div>

                <div className="text-xs space-y-1 py-1">
                  <div className="flex justify-between"><span>Received From:</span><strong>{lastMoneyReceipt.customer}</strong></div>
                  <div className="flex justify-between"><span>Customer #:</span><span className="font-mono">{lastMoneyReceipt.customer_no}</span></div>
                  <div className="flex justify-between"><span>Payment Date:</span><span>{lastMoneyReceipt.date} · {lastMoneyReceipt.time}</span></div>
                  <div className="flex justify-between"><span>Tender Method:</span><strong>{lastMoneyReceipt.method}</strong></div>
                </div>

                <div className="p-3 bg-[var(--surface-sunken)] rounded-lg space-y-1 text-xs">
                  <div className="flex justify-between"><span>Previous Due:</span><span className="num">{formatMoney(lastMoneyReceipt.previous_due)}</span></div>
                  <div className="flex justify-between font-bold text-[var(--success)]"><span>Amount Received:</span><span className="num">{formatMoney(lastMoneyReceipt.amount_paid)}</span></div>
                  <div className="flex justify-between font-bold border-t border-[var(--border)] pt-1"><span>Current Balance Due:</span><span className="num">{formatMoney(lastMoneyReceipt.new_due)}</span></div>
                </div>

                <div className="pt-2 flex gap-2">
                  <button onClick={() => setShowMoneyReceiptModal(false)} className="flex-1 h-8 rounded border text-xs font-semibold">Close</button>
                  <button
                    onClick={() => {
                      addToast(`WhatsApp payment thanks dispatched to ${lastMoneyReceipt.phone}!`, "success");
                      setShowMoneyReceiptModal(false);
                    }}
                    className="flex-1 h-8 rounded bg-[var(--success)] text-white text-xs font-bold flex items-center justify-center gap-1"
                  >
                    <Icon name="send" size={12} />
                    <span>Send WhatsApp Thanks</span>
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 11. TODAY DAY SUMMARY PRINT MODAL */}
          {showDaySummaryPrintModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-white text-black rounded-xl shadow-2xl p-5 flex flex-col space-y-3">
                <div className="text-center space-y-1 pb-2 border-b border-dashed border-[var(--input)]">
                  <div className="font-extrabold text-sm tracking-widest uppercase">DIFFERENT FASHION</div>
                  <div className="text-[10px] text-[var(--muted-foreground)]">DAILY SALES & CASH SUMMARY</div>
                  <div className="text-[10px] font-mono">{currentTime.toLocaleDateString('en-GB')} · 10:00 PM</div>
                </div>

                <div className="text-xs space-y-1.5 py-1 font-mono">
                  <div className="flex justify-between"><span>Gross Sales:</span><span>{formatMoney(todaySummary.gross_sales)}</span></div>
                  <div className="flex justify-between text-[var(--danger)]"><span>Returns:</span><span>−{formatMoney(todaySummary.returns_total)}</span></div>
                  <div className="flex justify-between text-[var(--danger)]"><span>Discounts Given:</span><span>−{formatMoney(todaySummary.discounts_total)}</span></div>
                  <div className="flex justify-between font-bold border-t pt-1"><span>NET SALES:</span><span>{formatMoney(todaySummary.net_sales)}</span></div>
                  <div className="pt-2 border-t text-[11px] text-[var(--muted-foreground)]">
                    <div className="flex justify-between"><span>Cash Tender:</span><span>{formatMoney(todaySummary.cash_total)}</span></div>
                    <div className="flex justify-between"><span>bKash Pay:</span><span>{formatMoney(todaySummary.bkash_total)}</span></div>
                    <div className="flex justify-between"><span>POS Cards:</span><span>{formatMoney(todaySummary.card_total)}</span></div>
                  </div>
                  <div className="pt-2 border-t text-[11px] text-[var(--muted-foreground)]">
                    <div className="flex justify-between"><span>New Dues Created:</span><span>+{formatMoney(todaySummary.dues_created)}</span></div>
                    <div className="flex justify-between"><span>Dues Collected:</span><span>{formatMoney(todaySummary.dues_collected)}</span></div>
                  </div>
                </div>

                <div className="pt-2 flex gap-2">
                  <button onClick={() => setShowDaySummaryPrintModal(false)} className="flex-1 h-8 rounded border text-xs font-semibold">Close</button>
                  <button onClick={() => { window.print(); addToast("Printed Day Summary Voucher", "success"); }} className="flex-1 h-8 rounded bg-[var(--primary-hover)] text-white text-xs font-bold">Print Voucher</button>
                </div>
              </div>
            </div>
          )}

          {/* 12. RETURN & EXCHANGE 80MM RECEIPT MODAL */}
          {/* 10. RETURN RECEIPT MODAL */}
          {showReturnReceiptModal && lastCompletedReturn && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="printable-area printable-receipt w-full max-w-sm bg-white text-black rounded-xl shadow-2xl p-5 flex flex-col space-y-3">
                <div className="text-center space-y-1 pb-2 border-b border-dashed border-[var(--input)]">
                  <div className="font-extrabold text-sm tracking-widest uppercase">DIFFERENT FASHION</div>
                  <div className="text-[10px] text-[var(--warning)] font-bold">RETURN & EXCHANGE RECEIPT</div>
                  <div className="font-mono text-[10px]">{lastCompletedReturn.return_no}</div>
                  <div className="text-[9px] text-[var(--muted-foreground)]">Ref: {lastCompletedReturn.original_invoice}</div>
                </div>

                <div className="text-xs space-y-1 py-1">
                  <div className="flex justify-between"><span>Customer:</span><strong>{lastCompletedReturn.customer}</strong></div>
                  <div className="flex justify-between"><span>Returned Items:</span><span className="num font-bold text-[var(--danger)]">−{formatMoney(lastCompletedReturn.return_total)}</span></div>
                  {lastCompletedReturn.exchange_total > 0 && (
                    <div className="flex justify-between"><span>Exchange Items:</span><span className="num font-bold">+{formatMoney(lastCompletedReturn.exchange_total)}</span></div>
                  )}
                  <div className="flex justify-between font-bold border-t border-[var(--border)] pt-1">
                    <span>{lastCompletedReturn.net_diff >= 0 ? 'Customer Paid:' : 'Refunded to Customer:'}</span>
                    <span className="num">{formatMoney(Math.abs(lastCompletedReturn.net_diff))}</span>
                  </div>
                  <div className="flex justify-between text-[11px] text-[var(--muted-foreground)]">
                    <span>Refund Method:</span>
                    <span>{lastCompletedReturn.refund_method}</span>
                  </div>
                </div>

                <div className="no-print pt-2 flex gap-2">
                  <button onClick={() => setShowReturnReceiptModal(false)} className="flex-1 h-8 rounded border text-xs font-semibold">Close</button>
                  <button onClick={() => { window.print(); addToast("Printed Return Voucher", "success"); }} className="flex-1 h-8 rounded bg-[var(--warning)] text-white text-xs font-bold">Print Receipt</button>
                </div>
              </div>
            </div>
          )}

          {/* 13. WHATSAPP REMINDER MODAL */}
          {showReminderModal && reminderCustomer && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowReminderModal(false)}></div>
              <div className="relative z-50 w-full max-w-md bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl overflow-hidden flex flex-col text-xs">
                {/* Authentic WhatsApp Green Top Bar */}
                <div className="bg-[#075E54] text-white p-3.5 flex items-center justify-between shadow-xs">
                  <div className="flex items-center gap-2.5">
                    <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-sm">
                      <Icon name="messageSquare" size={16} />
                    </div>
                    <div>
                      <div className="font-bold text-xs">{msgTargetType === 'bulk' ? 'Bulk WhatsApp: 14 Due Customers' : reminderCustomer.name_en}</div>
                      <div className="text-[10px] text-[var(--primary-soft)] flex items-center gap-1">
                        <span className="w-1.5 h-1.5 rounded-full bg-[var(--success-soft)] inline-block"></span>
                        <span>{msgTargetType === 'bulk' ? 'Total Overdue: ৳ 2,48,200.00' : `${reminderCustomer.phone} · Due: ${formatMoney(Math.abs(reminderCustomer.balance))}`}</span>
                      </div>
                    </div>
                  </div>
                  <button onClick={() => setShowReminderModal(false)} className="text-white/80 hover:text-white p-1">
                    <Icon name="x" size={16} />
                  </button>
                </div>

                {/* Dispatch Mode Selector */}
                <div className="p-2.5 bg-[var(--surface-sunken)] border-b border-[var(--border)] flex items-center justify-between gap-2">
                  <span className="text-[11px] font-semibold text-[var(--muted-foreground)]">Dispatch Target:</span>
                  <div className="flex bg-[var(--surface)] p-0.5 rounded-lg border border-[var(--border)] text-[11px]">
                    <button
                      type="button"
                      onClick={() => setMsgTargetType('single')}
                      className={`px-2.5 py-1 rounded-md font-semibold transition-all ${
                        msgTargetType === 'single' ? 'bg-[#075E54] text-white shadow-xs' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                      }`}
                    >
                      Single ({reminderCustomer.name_en})
                    </button>
                    <button
                      type="button"
                      onClick={() => setMsgTargetType('bulk')}
                      className={`px-2.5 py-1 rounded-md font-semibold transition-all ${
                        msgTargetType === 'bulk' ? 'bg-[#075E54] text-white shadow-xs' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                      }`}
                    >
                      Bulk All (14 Customers)
                    </button>
                  </div>
                </div>

                {/* Variable Insertion Tag Chips */}
                <div className="px-3 pt-2.5 pb-1 flex items-center gap-1.5 flex-wrap bg-[var(--surface-raised)]">
                  <span className="text-[10px] font-semibold text-[var(--muted-foreground)]">Insert Tags:</span>
                  {[
                    { tag: '{name}', label: 'Customer Name' },
                    { tag: '{due}', label: 'Due Amount' },
                    { tag: '{last_invoice}', label: 'Last Invoice' },
                    { tag: '{shop_phone}', label: 'Store Helpline' }
                  ].map(t => (
                    <button
                      key={t.tag}
                      type="button"
                      onClick={() => {
                        const current = editableMsgText || `প্রিয় ${reminderCustomer.name_en}, ডিফারেন্ট ফ্যাশনে আপনার পূর্বের বকেয়া ${formatMoney(Math.abs(reminderCustomer.balance))} টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। ধন্যবাদ, ডিফারেন্ট ফ্যাশন (01711-223344)।`;
                        setEditableMsgText(current + ' ' + t.tag);
                      }}
                      className="px-2 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[#075E54] text-[10px] font-mono text-[var(--foreground)]"
                    >
                      + {t.tag}
                    </button>
                  ))}
                </div>

                {/* WhatsApp Chat Background & Editable Message Bubble */}
                <div className="p-4 bg-[#E5DDD5] dark:bg-[#0B141A] min-h-[160px] flex flex-col justify-end">
                  <div className="self-end w-full bg-[#DCF8C6] dark:bg-[#005C4B] rounded-2xl rounded-tr-none p-3 shadow-md border border-[var(--success)]/30 border-[var(--success)]/30">
                    <textarea
                      rows={4}
                      value={editableMsgText !== '' ? editableMsgText : `প্রিয় ${reminderCustomer.name_en}, ডিফারেন্ট ফ্যাশনে আপনার পূর্বের বকেয়া ${formatMoney(Math.abs(reminderCustomer.balance))} টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। ধন্যবাদ, ডিফারেন্ট ফ্যাশন (01711-223344)।`}
                      onChange={(e) => setEditableMsgText(e.target.value)}
                      placeholder="Type WhatsApp reminder message..."
                      className="w-full bg-transparent border-0 resize-none text-xs text-[var(--foreground)] text-[var(--foreground)] font-sans focus:outline-hidden leading-relaxed"
                    />
                    <div className="flex items-center justify-end gap-1 mt-1 text-[10px] text-[var(--muted-foreground)] text-[var(--success)]">
                      <span>11:45 AM</span>
                      <span className="text-[var(--success)] text-[var(--success)] font-bold">✓✓</span>
                    </div>
                  </div>
                </div>

                {/* Footer Actions */}
                <div className="p-3 border-t border-[var(--border)] bg-[var(--surface-sunken)] flex items-center justify-between gap-2">
                  <button
                    type="button"
                    onClick={() => setShowReminderModal(false)}
                    className="h-9 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-[var(--foreground)] font-semibold"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      if (msgTargetType === 'bulk') {
                        addToast("Bulk WhatsApp queue dispatched to 14 customers (৳2,48,200 due)!", "success");
                      } else {
                        addToast(`WhatsApp reminder sent to ${reminderCustomer.name_en} (${reminderCustomer.phone})!`, "success");
                      }
                      setShowReminderModal(false);
                    }}
                    className="flex-1 h-9 px-4 rounded-lg bg-[#25D366] hover:bg-[#1EBE5D] text-[var(--foreground)] font-bold flex items-center justify-center gap-2 shadow-xs transition-all cursor-pointer"
                  >
                    <Icon name="send" size={15} />
                    <span>{msgTargetType === 'bulk' ? 'Send to All 14 Customers (বকেয়া তাগাদা পাঠান)' : 'Send WhatsApp Reminder'}</span>
                  </button>
                </div>
              </div>
            </div>
          )}
{/* 14. STOCK ADJUSTMENT MODAL */}
          {showStockAdjustmentModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowStockAdjustmentModal(false)}></div>
              <div className="relative z-50 w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs animate-in zoom-in-95 duration-100">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5">
                    <Icon name="package" size={15} className="text-[var(--primary)]" />
                    <span>Stock Adjustment (হিসাব সমন্বয়)</span>
                  </div>
                  <button onClick={() => setShowStockAdjustmentModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]"><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">Item: <strong className="text-[var(--foreground)]">{selectedProduct?.name_en || 'Product'}</strong></label>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">Adjustment Reason</label>
                  <select value={adjustReason} onChange={(e) => setAdjustReason(e.target.value)} className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold">
                    <option>Damaged / Stained in Store</option>
                    <option>Inventory Audit Discrepancy</option>
                    <option>Theft / Shrinkage</option>
                    <option>Sample Given to Influencer</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">Quantity (−/+) *</label>
                  <input
                    type="number"
                    value={adjustQty}
                    onChange={(e) => {
                      setAdjustQty(e.target.value);
                      if (adjustErrors.qty) setAdjustErrors(prev => ({ ...prev, qty: null }));
                    }}
                    placeholder="e.g. -2"
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold ${
                      adjustErrors.qty ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {adjustErrors.qty && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{adjustErrors.qty}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">Manager Approval PIN (1234) *</label>
                  <input
                    type="password"
                    maxLength={4}
                    value={adjustPin}
                    onChange={(e) => {
                      setAdjustPin(e.target.value);
                      if (adjustErrors.pin) setAdjustErrors(prev => ({ ...prev, pin: null }));
                    }}
                    placeholder="••••"
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono text-center tracking-widest ${
                      adjustErrors.pin ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {adjustErrors.pin && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{adjustErrors.pin}</span>
                    </div>
                  )}
                </div>
                <div className="pt-2 flex gap-2">
                  <button type="button" onClick={() => setShowStockAdjustmentModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold">Cancel</button>
                  <button
                    type="button"
                    onClick={() => {
                      const errors = {};
                      if (!adjustQty || Number(adjustQty) === 0) {
                        errors.qty = "Please specify a non-zero adjustment quantity";
                      }
                      if (adjustPin !== '1234') {
                        errors.pin = "Invalid Manager PIN (enter 1234)";
                      }
                      if (Object.keys(errors).length > 0) {
                        setAdjustErrors(errors);
                        return;
                      }
                      addToast(`Stock adjustment of ${adjustQty} pcs logged and audited for ${selectedProduct?.name_en || 'Product'}`, "success");
                      setShowStockAdjustmentModal(false);
                      setAdjustPin('');
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold shadow-xs cursor-pointer"
                  >
                    Apply Write-off
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 15. INTER-BRANCH TRANSFER MODAL */}
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
                        transferErrors.branch ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
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
                        transferErrors.branch ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                      }`}
                    >
                      <option>Different Fashion - 2 [DF2]</option>
                      <option>Different Fashion - Main [DF1]</option>
                    </select>
                  </div>
                </div>
                {transferErrors.branch && (
                  <div className="text-[10px] text-[var(--danger)] font-semibold flex items-center gap-1">
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
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-mono font-bold text-xs text-[var(--foreground)] ${
                      transferErrors.qty ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {transferErrors.qty && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{transferErrors.qty}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Gate Pass Dispatch Note</label>
                  <input
                    type="text"
                    value={transferNote}
                    onChange={(e) => setTransferNote(e.target.value)}
                    placeholder="e.g. Replenishment for weekend rush"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                  />
                </div>
                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setTransferErrors({});
                      setShowStockTransferModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                  >
                    Cancel
                  </button>
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
          )}
{/* 16. PHYSICAL INVENTORY STOCKTAKE MODAL */}
          <Modal
            isOpen={showStocktakeModal}
            onClose={() => setShowStocktakeModal(false)}
            title="Physical Inventory Stocktake & Reconciliation"
            subtitle="Audit on-hand physical counts against system recorded quantities and reconcile variances"
            icon="package"
            size="2xl"
          >
            <div className="space-y-3.5 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Audit Showroom Branch</label>
                  <select
                    value={branch}
                    onChange={(e) => setBranch(e.target.value)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                  >
                    <option>Different Fashion - Main [DF1]</option>
                    <option>Different Fashion - 2 [DF2]</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Count Category Scope</label>
                  <select
                    value={stocktakeAuditScope}
                    onChange={(e) => setStocktakeAuditScope(e.target.value)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                  >
                    <option value="all">Entire Store Inventory (All SKUs)</option>
                    <option value="panjabi">Festive Panjabi Collection</option>
                    <option value="saree">Jamdani & Silk Sarees</option>
                    <option value="shirt">Casual & Formal Shirts</option>
                  </select>
                </div>
              </div>

              {/* Count Reconciliation Table */}
              <div className="rounded-xl border border-[var(--border)] overflow-hidden max-h-64 overflow-y-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                    <tr>
                      <th className="py-2 px-3">SKU / Item</th>
                      <th className="py-2 px-2 text-center">System Qty</th>
                      <th className="py-2 px-2 text-center">Counted Qty</th>
                      <th className="py-2 px-2 text-center">Variance</th>
                      <th className="py-2 px-3">Audit Reason</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[var(--border)]">
                    {stocktakeRows.map((r, idx) => (
                      <tr key={r.id} className="hover:bg-[var(--surface-sunken)]">
                        <td className="py-2 px-3">
                          <div className="font-semibold text-[var(--foreground)]">{r.name}</div>
                          <div className="text-[10px] font-mono text-[var(--muted-foreground)]">{r.sku}</div>
                        </td>
                        <td className="py-2 px-2 text-center font-mono font-bold">{r.system_qty}</td>
                        <td className="py-2 px-2 text-center">
                          <input
                            type="number"
                            value={r.counted_qty}
                            onChange={(e) => {
                              const newCount = Number(e.target.value);
                              setStocktakeRows(prev => prev.map((item, i) => i === idx ? {
                                ...item,
                                counted_qty: newCount,
                                discrepancy: newCount - item.system_qty
                              } : item));
                            }}
                            className="w-16 h-7 text-center rounded bg-[var(--surface)] border border-[var(--border)] font-mono font-bold text-xs"
                          />
                        </td>
                        <td className="py-2 px-2 text-center font-mono font-bold">
                          {r.discrepancy === 0 ? (
                            <span className="text-[var(--success)]">0</span>
                          ) : r.discrepancy > 0 ? (
                            <span className="text-[var(--info)]">+{r.discrepancy}</span>
                          ) : (
                            <span className="text-[var(--danger)]">{r.discrepancy}</span>
                          )}
                        </td>
                        <td className="py-2 px-3">
                          <input
                            type="text"
                            value={r.reason}
                            onChange={(e) => {
                              const val = e.target.value;
                              setStocktakeRows(prev => prev.map((item, i) => i === idx ? { ...item, reason: val } : item));
                            }}
                            className="w-full h-7 px-2 rounded bg-[var(--surface)] border border-[var(--border)] text-[11px]"
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              <div className="p-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-semibold text-[var(--foreground)]">Manager Approval PIN (1234) *</span>
                  <span className="text-[10px] text-[var(--muted-foreground)]">Required to reconcile shrinkage variances</span>
                </div>
                <input
                  type="password"
                  maxLength={4}
                  value={stocktakePin}
                  onChange={(e) => setStocktakePin(e.target.value)}
                  placeholder="••••"
                  className="w-full h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-center tracking-widest"
                />
              </div>

              <div className="pt-2 flex gap-2 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => setShowStocktakeModal(false)}
                  className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={handleSaveStocktakeAudit}
                  className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold hover:bg-[var(--primary-hover)] shadow-xs"
                >
                  Reconcile & Update Inventory
                </button>
              </div>
            </div>
          </Modal>

          {/* 17. BARCODE LABEL DESIGNER MODAL */}
          {showBarcodeLabelModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <span>Barcode Sticker Print</span>
                  <button onClick={() => setShowBarcodeLabelModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Sticker Preset Size</label>
                  <select value={labelPreset} onChange={(e) => setLabelPreset(e.target.value)} className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                    <option>50x30mm Single Roll</option>
                    <option>38x25mm Double Column</option>
                    <option>A4 Sheet (65 Labels)</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Print Quantity</label>
                  <input type="number" defaultValue="24" className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono" />
                </div>
                <div className="printable-area p-3 rounded-lg bg-white text-black border border-[var(--border)] text-center space-y-1">
                  <div className="text-[9px] font-bold tracking-widest uppercase">DIFFERENT FASHION</div>
                  <div className="font-bold text-xs truncate">{selectedProduct?.name_en || 'Cotton Panjabi M'}</div>
                  <div className="h-6 w-36 mx-auto flex items-center justify-between">
                    {[2,1,3,1,2,3,1,2,1,3,2,1].map((w, i) => (
                      <span key={i} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                    ))}
                  </div>
                  <div className="font-mono text-[9px]">{selectedProduct?.barcode || '8941001'}</div>
                  <div className="font-bold text-xs num">{formatMoney(selectedProduct?.price || 1450)} (VAT Incl)</div>
                </div>
                <div className="no-print pt-2 flex gap-2">
                  <button onClick={() => setShowBarcodeLabelModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">Cancel</button>
                  <button onClick={() => { window.print(); addToast("Labels sent to barcode printer", "success"); setShowBarcodeLabelModal(false); }} className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold">Print Labels</button>
                </div>
              </div>
            </div>
          )}

          {/* 18. PAY VENDOR MODAL WITH BILL ALLOCATION */}
          {showPayVendorModal && selectedVendor && (
            <Modal
              isOpen={showPayVendorModal && !!selectedVendor}
              onClose={() => setShowPayVendorModal(false)}
              size="lg"
              title={`Disburse Payment to Vendor — ${selectedVendor.name}`}
            >
              <div className="space-y-4 text-xs">
                {/* Vendor Overview Banner */}
                <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between">
                  <div>
                    <div className="font-bold text-sm text-[var(--foreground)]">{selectedVendor.name}</div>
                    <div className="text-[11px] text-[var(--muted-foreground)]">
                      Code: <span className="font-mono">{selectedVendor.id}</span> · Contact: {selectedVendor.contact || 'Direct Supplier'} · {selectedVendor.location || 'Dhaka'}
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider font-semibold">Total Payable Due</div>
                    <div className="font-mono font-bold text-base text-[var(--danger)]">
                      {formatMoney(selectedVendor.payable !== undefined ? selectedVendor.payable : (selectedVendor.balance_due || 45000))}
                    </div>
                  </div>
                </div>

                {/* Amount & Allocation Mode */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Payment Amount (BDT) *</label>
                    <input
                      type="number"
                      autoFocus
                      value={payVendorAmount}
                      onChange={(e) => {
                        setPayVendorAmount(e.target.value);
                        if (payVendorError) setPayVendorError(null);
                      }}
                      placeholder="0.00"
                      className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-mono font-bold text-xs ${
                        payVendorError ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                      }`}
                    />
                    {payVendorError && (
                      <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                        <Icon name="alertCircle" size={11} />
                        <span>{payVendorError}</span>
                      </div>
                    )}
                  </div>

                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Allocation Mode</label>
                    <div className="mt-1 grid grid-cols-2 gap-1 h-8 p-0.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                      <button
                        type="button"
                        onClick={() => setPayVendorAllocationMode('auto')}
                        className={`rounded-md text-[11px] font-semibold transition-colors ${
                          payVendorAllocationMode === 'auto'
                            ? 'bg-[var(--surface)] text-[var(--foreground)] shadow-xs'
                            : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        Auto (FIFO)
                      </button>
                      <button
                        type="button"
                        onClick={() => setPayVendorAllocationMode('manual')}
                        className={`rounded-md text-[11px] font-semibold transition-colors ${
                          payVendorAllocationMode === 'manual'
                            ? 'bg-[var(--surface)] text-[var(--foreground)] shadow-xs'
                            : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'
                        }`}
                      >
                        Manual
                      </button>
                    </div>
                  </div>

                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Debit Account / Bank *</label>
                    <select
                      value={payVendorAccount}
                      onChange={(e) => setPayVendorAccount(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                    >
                      <option>City Bank Current A/C #1209</option>
                      <option>bKash Merchant Account</option>
                      <option>Cash Drawer R1</option>
                      <option>Main Safe Box</option>
                    </select>
                  </div>
                </div>

                {/* Open Bills Allocation Table */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-[var(--foreground)] text-xs">Outstanding Bills Allocation</span>
                    <span className="text-[11px] text-[var(--muted-foreground)]">
                      {payVendorAllocationMode === 'auto' ? 'Auto-allocated to oldest bills first' : 'Enter amount allocated per bill'}
                    </span>
                  </div>

                  <div className="border border-[var(--border)] rounded-xl overflow-hidden">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-[var(--surface-sunken)] text-[10px] text-[var(--muted-foreground)] uppercase border-b border-[var(--border)]">
                        <tr>
                          <th className="py-2 px-3">GRN / Bill No.</th>
                          <th className="py-2 px-2">Date</th>
                          <th className="py-2 px-2 text-right">Bill Total</th>
                          <th className="py-2 px-2 text-right">Balance Due</th>
                          <th className="py-2 px-3 text-right">This Allocation</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[var(--border)]">
                        {[
                          { bill: 'GRN-2610-09', date: '24 Oct 2026', total: 24500, due: 24500 },
                          { bill: 'GRN-2610-04', date: '19 Oct 2026', total: 18000, due: 13000 },
                          { bill: 'GRN-2609-28', date: '28 Sep 2026', total: 12000, due: 7500 }
                        ].map((b, idx) => {
                          const amt = Number(payVendorAmount) || 0;
                          let alloc = 0;
                          if (payVendorAllocationMode === 'auto') {
                            if (idx === 0) alloc = Math.min(amt, b.due);
                            else if (idx === 1) alloc = Math.min(Math.max(0, amt - 24500), b.due);
                            else if (idx === 2) alloc = Math.min(Math.max(0, amt - 37500), b.due);
                          } else {
                            alloc = payVendorAllocations[b.bill] || 0;
                          }

                          return (
                            <tr key={b.bill} className="hover:bg-[var(--surface-sunken)]/50">
                              <td className="py-2 px-3 font-mono font-semibold text-[var(--foreground)]">{b.bill}</td>
                              <td className="py-2 px-2 text-[var(--muted-foreground)]">{b.date}</td>
                              <td className="py-2 px-2 text-right num">{formatMoney(b.total)}</td>
                              <td className="py-2 px-2 text-right num font-bold text-[var(--danger)]">{formatMoney(b.due)}</td>
                              <td className="py-2 px-3 text-right">
                                {payVendorAllocationMode === 'auto' ? (
                                  <span className="num font-bold text-[var(--success)]">{formatMoney(alloc)}</span>
                                ) : (
                                  <input
                                    type="number"
                                    placeholder="0"
                                    value={payVendorAllocations[b.bill] || ''}
                                    onChange={(e) => {
                                      const val = Number(e.target.value) || 0;
                                      setPayVendorAllocations(prev => ({ ...prev, [b.bill]: val }));
                                    }}
                                    className="w-24 h-6 px-1.5 text-right font-mono font-bold rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-xs"
                                  />
                                )}
                              </td>
                            </tr>
                          );
                        })}
                      </tbody>
                    </table>
                  </div>
                </div>

                {/* Reference & Notes */}
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Payment Reference / Cheque No.</label>
                    <input
                      type="text"
                      value={payVendorReference}
                      onChange={(e) => setPayVendorReference(e.target.value)}
                      placeholder="e.g. CQ-992014 or TRX-88329"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-xs"
                    />
                  </div>
                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Payment Note / Memo</label>
                    <input
                      type="text"
                      value={payVendorNote}
                      onChange={(e) => setPayVendorNote(e.target.value)}
                      placeholder="e.g. Partial settlement for Jamdani batch"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs"
                    />
                  </div>
                </div>

                <div className="pt-2 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setPayVendorError(null);
                      setShowPayVendorModal(false);
                    }}
                    className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const amt = Number(payVendorAmount);
                      const vendorDue = (selectedVendor.payable !== undefined ? selectedVendor.payable : selectedVendor.balance_due) || 0;
                      if (!payVendorAmount || amt <= 0) {
                        setPayVendorError("Please enter a payment amount greater than ৳0");
                        addToast("Invalid payment amount", "warning");
                        return;
                      }
                      if (amt > vendorDue) {
                        setPayVendorError(`Amount cannot exceed payable due (${formatMoney(vendorDue)})`);
                        addToast("Payment amount exceeds balance due", "warning");
                        return;
                      }
                      setPayVendorError(null);
                      const newBal = Math.max(0, vendorDue - amt);
                      selectedVendor.balance_due = newBal;
                      selectedVendor.payable = newBal;
                      addToast(`Disbursed ${formatMoney(amt)} to ${selectedVendor.name} from ${payVendorAccount}`, "success");
                      setShowPayVendorModal(false);
                      setPayVendorAmount('');
                      setPayVendorReference('');
                      setPayVendorNote('');
                    }}
                    className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs"
                  >
                    Confirm & Disburse Payment
                  </button>
                </div>
              </div>
            </Modal>
          )}

          {/* 19. NEW PURCHASE ORDER & GOODS RECEIVED NOTE (GRN) MODAL WITH LINE ITEMS */}
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
                      <span className="text-[var(--muted-foreground)]">Target Retail MRP: <strong className="num text-[var(--foreground)]">৳{purItemRetail}</strong></span>
                      <span className="px-2 py-0.5 rounded bg-[var(--success-soft)] text-[var(--success)] text-[var(--success)] font-bold font-mono text-[10px]">
                        +{Math.round(((purItemRetail - purItemCost) / Math.max(1, purItemCost)) * 100)}% Margin
                      </span>
                      <span className="text-[var(--muted-foreground)] font-mono">
                        Line Subtotal: <strong className="text-[var(--primary)]">{formatMoney(purItemQty * purItemCost)}</strong>
                      </span>
                    </div>

                    <button
                      type="button"
                      onClick={() => {
                        if (Number(purItemQty) <= 0) {
                          addToast("Quantity must be at least 1 piece", "warning");
                          return;
                        }
                        if (Number(purItemCost) <= 0) {
                          addToast("Unit cost must be greater than ৳0", "warning");
                          return;
                        }
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
                              <td className="py-1.5 px-3 text-right num text-[var(--muted-foreground)]">{formatMoney(item.unit_cost)}</td>
                              <td className="py-1.5 px-3 text-right num font-bold text-[var(--foreground)]">{formatMoney(item.qty * item.unit_cost)}</td>
                              <td className="py-1.5 px-3 text-right num text-[var(--success)]">{formatMoney(item.retail_price)}</td>
                              <td className="py-1.5 px-2 text-center">
                                <button
                                  type="button"
                                  onClick={() => setNewPurItems(prev => prev.filter((_, i) => i !== idx))}
                                  className="text-[var(--danger)] hover:text-[var(--danger)] p-1 rounded hover:bg-[var(--danger-soft)]"
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
                            <span className="num font-bold text-[var(--foreground)]">{formatMoney(goodsTotal)}</span>
                          </div>
                          <div className="flex justify-between py-0.5 text-[var(--muted-foreground)]">
                            <span>Transport & Freight:</span>
                            <span className="num">+{formatMoney(transport)}</span>
                          </div>
                          <div className="flex justify-between py-0.5 text-[var(--muted-foreground)]">
                            <span>Labour / Unloading:</span>
                            <span className="num">+{formatMoney(labour)}</span>
                          </div>
                        </div>
                        <div className="pt-1 border-t border-[var(--border)] flex justify-between font-bold text-xs text-[var(--foreground)]">
                          <span>Grand Landed Cost:</span>
                          <span className="num text-sm text-[var(--primary)]">{formatMoney(grandTotal)}</span>
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
                              className="mt-1 w-full h-7 px-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--success)] text-right"
                            />
                          </div>
                        </div>

                        <div className="p-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex items-center justify-between">
                          <span className="text-[10px] font-semibold text-[var(--muted-foreground)]">Balance Due:</span>
                          <span className={`font-mono font-bold text-xs ${balanceDue > 0 ? 'text-[var(--danger)]' : 'text-[var(--success)]'}`}>
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
                      if (!newPurBillNo || !newPurBillNo.trim()) {
                        addToast("Vendor Bill / Challan # is required", "warning");
                        return;
                      }
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
            <Modal
              isOpen={showPurchaseDetailModal && !!selectedPurchase}
              onClose={() => setShowPurchaseDetailModal(false)}
              title={`Purchase & GRN Details — ${selectedPurchase.grn_no || selectedPurchase.purchase_no}`}
              subtitle={`Vendor: ${selectedPurchase.vendor_name || 'Vendor'} · ${selectedPurchase.date}`}
              icon="fileText"
              size="3xl"
              printable={true}
            >
              <div className="space-y-4">

                {/* Printable Isolated GRN Voucher Area */}
                <div className="printable-area p-5 bg-white text-[var(--foreground)] rounded-xl border border-[var(--border)] shadow-xs space-y-4">
                  {/* Top Header of Voucher */}
                  <div className="flex justify-between items-start border-b pb-3">
                    <div>
                      <div className="font-extrabold text-base tracking-wider uppercase text-[var(--primary-hover)]">DIFFERENT FASHION</div>
                      <div className="text-[10px] text-[var(--muted-foreground)]">Uttara Sector 3, Dhaka-1230 · Helpline: 01711-223344</div>
                      <div className="text-[10px] font-mono text-[var(--muted-foreground)]">BIN: 002847192-0101 · Mushak 6.3 Registered</div>
                      <div className="text-xs font-bold text-[var(--primary)] uppercase tracking-widest mt-1">GOODS RECEIVED NOTE (GRN)</div>
                    </div>
                    <div className="text-right space-y-0.5 text-[11px]">
                      <div>GRN No: <strong className="font-mono font-bold text-[var(--primary-hover)]">{selectedPurchase.grn_no || selectedPurchase.purchase_no}</strong></div>
                      <div>Date: <span className="font-mono">{selectedPurchase.date}</span></div>
                      <div>Receiving Branch: <strong>{selectedPurchase.branch || 'Main Branch [DF1]'}</strong></div>
                      <div>Vendor Bill Ref: <span className="font-mono font-semibold">{selectedPurchase.bill_no}</span></div>
                    </div>
                  </div>

                  {/* Vendor Details */}
                  <div className="p-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex justify-between items-center text-[11px]">
                    <div>
                      <div className="text-[10px] text-[var(--muted-foreground)] font-semibold uppercase">Supplier / Vendor</div>
                      <div className="font-bold text-sm text-[var(--foreground)]">{selectedPurchase.vendor}</div>
                    </div>
                    <div className="text-right">
                      <span className={`px-2 py-0.5 rounded text-[10px] font-bold uppercase ${
                        (selectedPurchase.balance || selectedPurchase.due || 0) <= 0 
                          ? 'bg-[var(--success-soft)] text-[var(--success)]' 
                          : 'bg-[var(--danger-soft)] text-[var(--danger)]'
                      }`}>
                        {selectedPurchase.status || 'Received & Recorded'}
                      </span>
                    </div>
                  </div>

                  {/* Items Table */}
                  <div>
                    <div className="font-bold text-xs text-[var(--foreground)] mb-1.5 flex items-center justify-between">
                      <span>Received Product Line Items</span>
                      <span className="text-[10px] font-normal text-[var(--muted-foreground)]">Total: {selectedPurchase.items_count || 35} pcs</span>
                    </div>

                    <table className="w-full text-left border-collapse text-xs">
                      <thead className="bg-[var(--surface-sunken)] text-[var(--subtle-foreground)] font-semibold text-[10px] border-b border-[var(--border)]">
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
                      <tbody className="divide-y divide-[var(--border)]">
                        {(selectedPurchase.items || [
                          { id: '1', name_en: 'Semi-fitted Cotton Panjabi', sku: 'DF-PAN-01', size: 'L', color: 'White', qty: 20, unit_cost: 850, retail_price: 1450, total_cost: 17000 },
                          { id: '2', name_en: 'Pure Cotton Formal Shirt', sku: 'DF-SHT-03', size: 'XL', color: 'Sky Blue', qty: 15, unit_cost: 950, retail_price: 1650, total_cost: 14250 }
                        ]).map((it, idx) => (
                          <tr key={it.id || idx}>
                            <td className="py-2 px-2.5 font-mono text-[var(--muted-foreground)]">{idx + 1}</td>
                            <td className="py-2 px-2.5 font-semibold text-[var(--foreground)]">
                              <div>{it.name_en}</div>
                              <div className="text-[10px] font-mono text-[var(--muted-foreground)]">{it.sku}</div>
                            </td>
                            <td className="py-2 px-2.5">
                              <span className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] border text-[10px] font-mono text-[var(--foreground)]">
                                {it.size || 'L'} / {it.color || 'Standard'}
                              </span>
                            </td>
                            <td className="py-2 px-2.5 text-right font-mono font-bold text-[var(--foreground)]">{it.qty} pcs</td>
                            <td className="py-2 px-2.5 text-right num text-[var(--subtle-foreground)]">{formatMoney(it.unit_cost)}</td>
                            <td className="py-2 px-2.5 text-right num font-bold text-[var(--foreground)]">{formatMoney(it.total_cost || (it.qty * it.unit_cost))}</td>
                            <td className="py-2 px-2.5 text-right num text-[var(--success)]">{formatMoney(it.retail_price)}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {/* Financial Settlement Breakdown */}
                  <div className="pt-3 border-t border-[var(--border)] grid grid-cols-2 gap-4 text-[11px]">
                    <div className="text-[10px] text-[var(--muted-foreground)] space-y-1">
                      <div>Stock Keeper: <strong>Sumi (Cashier / Verified)</strong></div>
                      <div>Authorized Signature: ___________________________</div>
                      <div className="italic text-[var(--muted-foreground)]">Goods inspected, verified against supplier invoice, and entered into active inventory.</div>
                    </div>
                    <div className="space-y-1 font-mono text-right">
                      <div className="flex justify-between text-[var(--muted-foreground)]">
                        <span>Transport & Freight:</span>
                        <span>{formatMoney(selectedPurchase.transport || 1200)}</span>
                      </div>
                      <div className="flex justify-between font-bold text-sm text-[var(--foreground)] border-t pt-1">
                        <span>Total Landed Bill:</span>
                        <span>{formatMoney(selectedPurchase.total)}</span>
                      </div>
                      <div className="flex justify-between text-[var(--success)] font-semibold">
                        <span>Paid Amount:</span>
                        <span>{formatMoney(selectedPurchase.paid)}</span>
                      </div>
                      <div className="flex justify-between font-bold text-sm text-[var(--danger)] border-t pt-1">
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
            </Modal>
          )}

          {/* 20. ACCOUNT TRANSFER MODAL */}
          {showAccountTransferModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5">
                    <Icon name="arrowLeftRight" size={15} className="text-[var(--primary)]" />
                    <span>Transfer Money Between Accounts</span>
                  </div>
                  <button onClick={() => setShowAccountTransferModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">From Account *</label>
                  <select
                    value={transferFromAcc}
                    onChange={(e) => {
                      setTransferFromAcc(e.target.value);
                      if (transferAccErrors.account) setTransferAccErrors(prev => ({ ...prev, account: null }));
                    }}
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-semibold text-xs ${
                      transferAccErrors.account ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  >
                    <option>Cash Drawer R1</option>
                    <option>Main Safe Box</option>
                    <option>City Bank Current A/C #1209</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">To Account *</label>
                  <select
                    value={transferToAcc}
                    onChange={(e) => {
                      setTransferToAcc(e.target.value);
                      if (transferAccErrors.account) setTransferAccErrors(prev => ({ ...prev, account: null }));
                    }}
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-semibold text-xs ${
                      transferAccErrors.account ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  >
                    <option>Main Safe Box</option>
                    <option>Petty Cash Box</option>
                    <option>City Bank Current A/C #1209</option>
                  </select>
                </div>
                {transferAccErrors.account && (
                  <div className="text-[10px] text-[var(--danger)] font-semibold flex items-center gap-1">
                    <Icon name="alertCircle" size={11} />
                    <span>{transferAccErrors.account}</span>
                  </div>
                )}
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Amount (BDT) *</label>
                  <input
                    type="number"
                    autoFocus
                    placeholder="0.00"
                    value={transferAccAmount}
                    onChange={(e) => {
                      setTransferAccAmount(e.target.value);
                      if (transferAccErrors.amount) setTransferAccErrors(prev => ({ ...prev, amount: null }));
                    }}
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-mono font-bold text-xs ${
                      transferAccErrors.amount ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {transferAccErrors.amount && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{transferAccErrors.amount}</span>
                    </div>
                  )}
                </div>
                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setTransferAccErrors({});
                      setShowAccountTransferModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const errors = {};
                      if (transferFromAcc === transferToAcc) {
                        errors.account = "Source and destination accounts cannot be identical";
                      }
                      if (!transferAccAmount || Number(transferAccAmount) <= 0) {
                        errors.amount = "Transfer amount must be greater than ৳0";
                      }
                      if (Object.keys(errors).length > 0) {
                        setTransferAccErrors(errors);
                        addToast("Please correct transfer details", "warning");
                        return;
                      }
                      setTransferAccErrors({});
                      addToast(`Transferred ${formatMoney(Number(transferAccAmount))} from ${transferFromAcc} to ${transferToAcc}`, "success");
                      setShowAccountTransferModal(false);
                      setTransferAccAmount('');
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs cursor-pointer"
                  >
                    Execute Transfer
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 21. NEW EXPENSE MODAL */}
          {showNewExpenseModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5">
                    <Icon name="receipt" size={15} className="text-[var(--primary)]" />
                    <span>Record Expense Voucher</span>
                  </div>
                  <button onClick={() => setShowNewExpenseModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Expense Head *</label>
                  <select value={newExpHead} onChange={(e) => setNewExpHead(e.target.value)} className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs">
                    <option>Staff Tiffin & Refreshment</option>
                    <option>Electricity & Utility Bill</option>
                    <option>Store Maintenance & Cleaning</option>
                    <option>Shopping Bag & Packaging</option>
                    <option>Courier Delivery Charges</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Amount (BDT) *</label>
                  <input
                    type="number"
                    autoFocus
                    value={newExpAmount}
                    onChange={(e) => {
                      setNewExpAmount(e.target.value);
                      if (newExpErrors.amount) setNewExpErrors(prev => ({ ...prev, amount: null }));
                    }}
                    placeholder="0.00"
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-mono font-bold text-xs ${
                      newExpErrors.amount ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {newExpErrors.amount && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{newExpErrors.amount}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Paid From Account *</label>
                  <select value={newExpAccount} onChange={(e) => setNewExpAccount(e.target.value)} className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs">
                    <option>Cash Drawer R1</option>
                    <option>Petty Cash Box</option>
                    <option>Main Safe Box</option>
                  </select>
                </div>
                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setNewExpErrors({});
                      setShowNewExpenseModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      if (!newExpAmount || Number(newExpAmount) <= 0) {
                        setNewExpErrors({ amount: "Please enter a valid expense amount greater than ৳0" });
                        addToast("Please enter a valid expense amount", "danger");
                        return;
                      }
                      setNewExpErrors({});
                      addToast(`Expense voucher ৳${newExpAmount} (${newExpHead}) recorded successfully`, "success");
                      setShowNewExpenseModal(false);
                      setNewExpAmount('');
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs cursor-pointer"
                  >
                    Save Voucher
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 22. ADD STAFF MODAL */}
          {showAddStaffModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowAddStaffModal(false)}></div>
              <div className="relative z-50 w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs animate-in zoom-in-95 duration-100">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5">
                    <Icon name="shield" size={15} className="text-[var(--primary)]" />
                    <span>Add Staff Member (কর্মী যোগ)</span>
                  </div>
                  <button onClick={() => setShowAddStaffModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]"><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">Full Name *</label>
                  <input
                    type="text"
                    value={staffName}
                    onChange={(e) => {
                      setStaffName(e.target.value);
                      if (staffErrors.name) setStaffErrors(prev => ({ ...prev, name: null }));
                    }}
                    placeholder="e.g. Mahfuzur Rahman"
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-semibold ${
                      staffErrors.name ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {staffErrors.name && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{staffErrors.name}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">System Role *</label>
                  <select
                    value={staffRole}
                    onChange={(e) => setStaffRole(e.target.value)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                  >
                    <option value="Cashier">Cashier (Max 10% Discount)</option>
                    <option value="Stock Keeper">Stock Keeper</option>
                    <option value="Accountant">Accountant</option>
                    <option value="Manager">Manager (Unlimited Discount)</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)] font-semibold">Initial 4-digit PIN *</label>
                  <input
                    type="password"
                    maxLength={4}
                    value={staffPin}
                    onChange={(e) => {
                      setStaffPin(e.target.value.replace(/[^0-9]/g, ''));
                      if (staffErrors.pin) setStaffErrors(prev => ({ ...prev, pin: null }));
                    }}
                    placeholder="••••"
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono text-center tracking-widest ${
                      staffErrors.pin ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {staffErrors.pin && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{staffErrors.pin}</span>
                    </div>
                  )}
                </div>
                <div className="pt-2 flex gap-2">
                  <button type="button" onClick={() => setShowAddStaffModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold">Cancel</button>
                  <button
                    type="button"
                    onClick={() => {
                      const errors = {};
                      if (!staffName || staffName.trim().length < 3) {
                        errors.name = "Staff name is required (minimum 3 characters)";
                      }
                      if (!staffPin || staffPin.length !== 4) {
                        errors.pin = "4-digit numeric PIN is required";
                      }
                      if (Object.keys(errors).length > 0) {
                        setStaffErrors(errors);
                        return;
                      }
                      addToast(`New staff member "${staffName}" created with role: ${staffRole}`, "success");
                      setShowAddStaffModal(false);
                      setStaffName('');
                      setStaffPin('');
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold shadow-xs cursor-pointer"
                  >
                    Save Staff
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 23. RESET PIN MODAL */}
          {showResetPinModal && resetPinTarget && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-xs bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5">
                    <Icon name="key" size={15} className="text-[var(--primary)]" />
                    <span>Reset PIN: {resetPinTarget.name}</span>
                  </div>
                  <button onClick={() => { setResetPinError(null); setResetPinValue(''); setShowResetPinModal(false); }}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Enter New 4-Digit PIN *</label>
                  <input
                    type="password"
                    maxLength={4}
                    autoFocus
                    placeholder="••••"
                    value={resetPinValue}
                    onChange={(e) => {
                      setResetPinValue(e.target.value.replace(/[^0-9]/g, ''));
                      if (resetPinError) setResetPinError(null);
                    }}
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-mono text-center font-bold text-sm tracking-widest ${
                      resetPinError ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {resetPinError && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{resetPinError}</span>
                    </div>
                  )}
                </div>
                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setResetPinError(null);
                      setResetPinValue('');
                      setShowResetPinModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      if (!resetPinValue || resetPinValue.length !== 4) {
                        setResetPinError("PIN must be exactly 4 numeric digits");
                        addToast("Invalid 4-digit PIN", "warning");
                        return;
                      }
                      setResetPinError(null);
                      addToast(`Security PIN updated for ${resetPinTarget.name}`, "success");
                      setShowResetPinModal(false);
                      setResetPinValue('');
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs cursor-pointer"
                  >
                    Update PIN
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 24. COMMAND PALETTE MODAL (Ctrl+K) */}
          {showCommandPalette && (
            <Modal
              isOpen={showCommandPalette}
              onClose={() => setShowCommandPalette(false)}
              size="xl"
              showClose={true}
              className="!p-0 overflow-hidden"
            >
              {(() => {
                const raw = cmdQuery.trim();
                let prefix = null;
                let q = raw.toLowerCase();

                if (q.startsWith('#')) { prefix = 'customers'; q = q.slice(1).trim(); }
                else if (q.startsWith('>')) { prefix = 'actions'; q = q.slice(1).trim(); }
                else if (q.startsWith('/')) { prefix = 'sales'; q = q.slice(1).trim(); }
                else if (q.startsWith('@')) { prefix = 'vendors'; q = q.slice(1).trim(); }

                // Check 8-digit customer number with Luhn check
                const digitsOnly = q.replace(/\\s|-/g, '');
                const isEightDigits = /^\\d{8}$/.test(digitsOnly);
                let luhnCustomer = null;
                let luhnError = false;

                if (isEightDigits) {
                  if (luhnCheck(digitsOnly)) {
                    luhnCustomer = MOCK_CUSTOMERS.find(c => (c.customer_no || '').replace(/\\D/g, '') === digitsOnly) || {
                      customer_no: `${digitsOnly.slice(0, 4)} ${digitsOnly.slice(4)}`,
                      name_en: 'Rahim Uddin',
                      phone: '01712-345678',
                      group: 'Wholesale Member',
                      balance: -1200
                    };
                  } else {
                    luhnError = true;
                  }
                }

                const groups = [];

                // 1. Pages / Navigation
                if (!prefix || prefix === 'pages') {
                  const pages = [
                    { id: 'p_pos', label: 'POS Register', subtitle: 'Checkout & Sales counter', shortcut: 'F1', screen: 'pos', icon: 'shoppingCart' },
                    { id: 'p_sales', label: 'Sales & Invoices', subtitle: 'View order history & reprint receipts', shortcut: 'G S', screen: 'sales', icon: 'fileText' },
                    { id: 'p_returns', label: 'Returns & Exchanges', subtitle: 'Process return items & store refunds', shortcut: 'G T', screen: 'returns', icon: 'refresh' },
                    { id: 'p_return_detail', label: 'Return Voucher Details', subtitle: 'Credit notes, VAT adjustments & restocking memo', shortcut: '', screen: 'return_detail', icon: 'fileText' },
                    { id: 'p_due', label: 'Customer Due Collection', subtitle: 'Collect pending receivables & dues', shortcut: 'G U', screen: 'due_collection', icon: 'banknote' },
                    { id: 'p_cust', label: 'Customer Directory', subtitle: 'Customer profiles, cards & credit limits', shortcut: 'G C', screen: 'customers', icon: 'users' },
                    { id: 'p_membership', label: 'Membership Cards Designer', subtitle: 'CR80 & A4 10-up card print studio & reissue queue', shortcut: '', screen: 'membership_cards', icon: 'creditCard' },
                    { id: 'p_messaging', label: 'SMS & WhatsApp Messaging', subtitle: 'Marketing composer, templates, automations & logs', shortcut: '', screen: 'messaging', icon: 'messageSquare' },
                    { id: 'p_prod', label: 'Product Catalog', subtitle: 'Inventory items, prices & barcodes', shortcut: 'G I', screen: 'products', icon: 'package' },
                    { id: 'p_product_detail', label: 'Product Details Deep Dive', subtitle: 'Branch × variant heat-map, FIFO batches & price history', shortcut: '', screen: 'product_detail', icon: 'package' },
                    { id: 'p_stock', label: 'Stock & Inventory Transfers', subtitle: 'Adjust stock, transfers & cycle counts', shortcut: 'G K', screen: 'stock', icon: 'truck' },
                    { id: 'p_pur', label: 'Purchase Orders & GRN', subtitle: 'Procurements & vendor bills', shortcut: 'G P', screen: 'purchases', icon: 'package' },
                    { id: 'p_ven', label: 'Suppliers & Vendors', subtitle: 'Trade payables & vendor ledger', shortcut: 'G V', screen: 'vendors', icon: 'truck' },
                    { id: 'p_vendor_profile', label: 'Vendor 360 Profile & Statements', subtitle: 'Disbursements, debit notes & running ledger', shortcut: '', screen: 'vendor_profile', icon: 'truck' },
                    { id: 'p_mon', label: 'Financial Accounts & Ledgers', subtitle: 'Cash drawer, bank accounts & expenses', shortcut: 'G M', screen: 'money', icon: 'dollarSign' },
                    { id: 'p_fixed_assets', label: 'Fixed Assets Register', subtitle: 'Store equipment, depreciation & asset valuation', shortcut: '', screen: 'fixed_assets', icon: 'layers' },
                    { id: 'p_rep', label: 'Reports & Analytics Hub', subtitle: 'Sales, taxes, profit & due aging', shortcut: 'G R', screen: 'reports', icon: 'barChart' },
                    { id: 'p_set', label: 'System Settings', subtitle: 'Store config, tax rates, invoice numbers & devices', shortcut: 'G ,', screen: 'settings', icon: 'settings' },
                    { id: 'p_users', label: 'Staff Users & Role Permissions', subtitle: 'Counter staff, managers & access control matrix', shortcut: '', screen: 'users', icon: 'users' },
                    { id: 'p_backup', label: 'Database Backup & Snapshots', subtitle: 'Daily SQLite snapshots & cloud sync backup', shortcut: '', screen: 'backup', icon: 'hardDrive' },
                    { id: 'p_audit', label: 'System Audit Logs', subtitle: 'Security events, discount edits & access trail', shortcut: '', screen: 'audit_log', icon: 'shield' },
                    { id: 'p_super', label: 'Super Admin & Multi-Store', subtitle: 'Tenant management & multi-outlet provisioning', shortcut: '', screen: 'super_admin', icon: 'server' },
                    { id: 'p_account', label: 'My Account & Profile', subtitle: 'User credentials, password, active sessions & login history', shortcut: '', screen: 'account', icon: 'user' }
                  ];
                  const matched = pages.filter(p => !q || p.label.toLowerCase().includes(q) || p.subtitle.toLowerCase().includes(q) || p.screen.includes(q));
                  if (matched.length > 0) {
                    groups.push({
                      id: 'pages',
                      name: 'Navigation & Screens',
                      total: matched.length,
                      items: matched.slice(0, 5).map(item => ({
                        ...item,
                        isRestricted: !canAccess(item.screen),
                        onSelect: () => {
                          setShowCommandPalette(false);
                          navigateToScreen(item.screen);
                        }
                      }))
                    });
                  }
                }

                // 2. Quick Actions
                if (!prefix || prefix === 'actions') {
                  const actions = [
                    { id: 'act_return_wiz', label: 'Sales Return & Exchange Wizard', subtitle: 'Process customer returns step-by-step', shortcut: 'F11', icon: 'refresh', onSelect: () => { setShowCommandPalette(false); setReturnWizardStep(1); setShowReturnWizardModal(true); } },
                    { id: 'act_return_pos', label: 'Start Fast Return / Exchange Mode in POS', subtitle: 'Exchange items against invoice', shortcut: 'Shift+F11', icon: 'refresh', onSelect: () => { setShowCommandPalette(false); startReturnModeForInvoice('DF1-R1-26000412'); setCurrentScreen('pos'); } },
                    { id: 'act_collect_due', label: 'Collect Customer Due', subtitle: 'Open due settlement modal', shortcut: 'Ctrl+D', icon: 'banknote', onSelect: () => { setShowCommandPalette(false); openCollectDueModal(); } },
                    { id: 'act_new_cust', label: 'Add Quick Customer', subtitle: 'Register customer inline', shortcut: 'Ctrl+N', icon: 'user', onSelect: () => { setShowCommandPalette(false); setShowQuickNewCustomerModal(true); } },
                    { id: 'act_price_check', label: 'Price & Stock Check', subtitle: 'Instant barcode price lookup', shortcut: 'F5', icon: 'tag', onSelect: () => { setShowCommandPalette(false); setShowPriceCheckModal(true); } },
                    { id: 'act_hold', label: 'Park / Hold Current Sale', subtitle: 'Save cart basket for later', shortcut: 'F8', icon: 'pause', onSelect: () => { setShowCommandPalette(false); holdSale(); } },
                    { id: 'act_held_list', label: 'View Parked / Held Sales', subtitle: 'Recall a previously held cart', shortcut: 'Shift+F8', icon: 'layers', onSelect: () => { setShowCommandPalette(false); setShowHeldSalesModal(true); } },
                    { id: 'act_switch_pin', label: 'Switch Register User (Fast PIN)', subtitle: 'Switch cashier or manager via PIN', shortcut: '', icon: 'users', onSelect: () => { setShowCommandPalette(false); setShowSwitchUserPinModal(true); } },
                    { id: 'act_reissue_card', label: 'Reissue Membership Card', subtitle: 'Report lost card and issue new Luhn-verified card', shortcut: '', icon: 'creditCard', onSelect: () => { setShowCommandPalette(false); setShowReissueCardModal(true); } },
                    { id: 'act_lock', label: 'Lock Register Terminal', subtitle: 'Secure screen with PIN pad', shortcut: 'Ctrl+L', icon: 'lock', onSelect: () => { setShowCommandPalette(false); setIsLocked(true); } },
                    { id: 'act_shortcuts', label: 'View Keyboard Shortcuts', subtitle: 'All POS & system hotkeys', shortcut: '?', icon: 'command', onSelect: () => { setShowCommandPalette(false); setShowShortcutsModal(true); } }
                  ];
                  const matched = actions.filter(a => !q || a.label.toLowerCase().includes(q) || a.subtitle.toLowerCase().includes(q));
                  if (matched.length > 0) {
                    groups.push({
                      id: 'actions',
                      name: 'Quick Actions',
                      total: matched.length,
                      items: matched.slice(0, 5)
                    });
                  }
                }

                // 3. Customers
                if (!prefix || prefix === 'customers') {
                  if (luhnError) {
                    groups.push({
                      id: 'customers_error',
                      name: 'Customers',
                      total: 1,
                      items: [{
                        id: 'cust_luhn_err',
                        label: 'Invalid customer number',
                        subtitle: `Check digit verification failed for "${raw}" — check number and re-enter`,
                        icon: 'alertTriangle',
                        isError: true,
                        onSelect: () => {
                          addToast("Invalid customer number: Check digit mismatch", "danger");
                        }
                      }]
                    });
                  } else if (luhnCustomer) {
                    groups.push({
                      id: 'customers_luhn',
                      name: 'Customers (1 found)',
                      total: 1,
                      items: [{
                        id: 'cust_luhn_' + luhnCustomer.customer_no,
                        label: `${luhnCustomer.name_en} (#${luhnCustomer.customer_no})`,
                        subtitle: `Phone: ${luhnCustomer.phone} · Group: ${luhnCustomer.group || 'Wholesale Member'} · Due: ${formatMoney(Math.abs(luhnCustomer.balance || 0))}`,
                        icon: 'user',
                        badge: 'Verified Luhn',
                        onSelect: () => {
                          setShowCommandPalette(false);
                          setSelectedCustomer(luhnCustomer);
                          navigateToScreen('customer_profile');
                        }
                      }]
                    });
                  } else {
                    const matched = MOCK_CUSTOMERS.filter(c => {
                      if (!q) return false;
                      const cNoClean = (c.customer_no || '').replace(/\\s/g, '').toLowerCase();
                      const cardClean = (c.card_barcode || '').toLowerCase();
                      return c.name_en.toLowerCase().includes(q) ||
                             (c.name_bn && c.name_bn.includes(q)) ||
                             c.phone.includes(q) ||
                             cNoClean.includes(q.replace(/\\s/g, '')) ||
                             cardClean.includes(q);
                    });
                    if (matched.length > 0) {
                      groups.push({
                        id: 'customers',
                        name: `Customers (${matched.length} found)`,
                        total: matched.length,
                        items: matched.slice(0, 5).map(c => ({
                          id: 'cust_' + c.id,
                          label: `${c.name_en} (#${c.customer_no})`,
                          subtitle: `Phone: ${c.phone} · Balance: ${formatMoney(Math.abs(c.balance || 0))} ${c.balance < 0 ? 'Due' : 'Credit'}`,
                          icon: 'user',
                          onSelect: () => {
                            setShowCommandPalette(false);
                            setSelectedCustomer(c);
                            navigateToScreen('customer_profile');
                          }
                        }))
                      });
                    }
                  }
                }

                // 4. Products
                if (!prefix || prefix === 'products') {
                  const matched = INITIAL_PRODUCTS.filter(p => {
                    if (!q) return false;
                    return p.name_en.toLowerCase().includes(q) ||
                           (p.name_bn && p.name_bn.includes(q)) ||
                           p.sku.toLowerCase().includes(q) ||
                           p.barcode.includes(q);
                  });
                  if (matched.length > 0) {
                    groups.push({
                      id: 'products',
                      name: `Products (${matched.length} found)`,
                      total: matched.length,
                      items: matched.slice(0, 5).map(p => ({
                        id: 'prod_' + p.id,
                        label: `${p.name_en} (${p.sku})`,
                        subtitle: `Barcode: ${p.barcode} · Price: ${formatMoney(p.price)} · Stock: ${p.stock_main} pcs`,
                        icon: 'package',
                        onSelect: () => {
                          setShowCommandPalette(false);
                          if (currentScreen === 'pos') {
                            addToCart(p);
                            addToast(`Added ${p.name_en} to cart`, "success");
                          } else {
                            setSelectedProduct(p);
                            navigateToScreen('product_detail');
                          }
                        }
                      }))
                    });
                  }
                }

                // 5. Sales & Invoices
                if (!prefix || prefix === 'sales') {
                  const matched = MOCK_SALES.filter(s => {
                    if (!q) return false;
                    const inv = (s.invoice || '').toLowerCase();
                    const cust = (s.customer || '').toLowerCase();
                    return inv.includes(q) || cust.includes(q);
                  });
                  if (matched.length > 0) {
                    groups.push({
                      id: 'sales',
                      name: `Sales & Invoices (${matched.length} found)`,
                      total: matched.length,
                      items: matched.slice(0, 5).map(s => ({
                        id: 'sale_' + s.id,
                        label: `${s.invoice} — ${s.customer}`,
                        subtitle: `Date: ${s.date} · Total: ${formatMoney(s.total)} · Status: ${s.status}`,
                        icon: 'fileText',
                        onSelect: () => {
                          setShowCommandPalette(false);
                          setSelectedSale(s);
                          navigateToScreen('sale_detail');
                        }
                      }))
                    });
                  }
                }

                // 6. Purchases
                if (!prefix || prefix === 'purchases') {
                  const matched = MOCK_PURCHASES.filter(p => {
                    if (!q) return false;
                    const pNo = (p.purchase_no || p.grn_no || p.bill_no || '').toLowerCase();
                    const ven = (p.vendor || '').toLowerCase();
                    return pNo.includes(q) || ven.includes(q);
                  });
                  if (matched.length > 0) {
                    groups.push({
                      id: 'purchases',
                      name: `Purchases (${matched.length} found)`,
                      total: matched.length,
                      items: matched.slice(0, 5).map(p => ({
                        id: 'po_' + p.id,
                        label: `${p.purchase_no || p.grn_no || 'PO'} — ${p.vendor}`,
                        subtitle: `Items: ${p.items_count || (p.items ? p.items.length : 0)} · Total: ${formatMoney(p.total)} · Status: ${p.status}`,
                        icon: 'package',
                        onSelect: () => {
                          setShowCommandPalette(false);
                          setSelectedPurchase(p);
                          openModal('purchase_detail');
                        }
                      }))
                    });
                  }
                }

                // 7. Vendors
                if (!prefix || prefix === 'vendors') {
                  const matched = MOCK_VENDORS.filter(v => {
                    if (!q) return false;
                    const vName = (v.name || '').toLowerCase();
                    const vContact = (v.contact || '').toLowerCase();
                    return vName.includes(q) || vContact.includes(q);
                  });
                  if (matched.length > 0) {
                    groups.push({
                      id: 'vendors',
                      name: `Vendors (${matched.length} found)`,
                      total: matched.length,
                      items: matched.slice(0, 5).map(v => ({
                        id: 'ven_' + v.id,
                        label: `${v.name} (${v.contact || 'Supplier'})`,
                        subtitle: `Location: ${v.location || 'Dhaka'} · Payable: ${formatMoney(v.payable || 0)}`,
                        icon: 'truck',
                        onSelect: () => {
                          setShowCommandPalette(false);
                          setSelectedVendor(v);
                          navigateToScreen('vendor_profile');
                        }
                      }))
                    });
                  }
                }

                const flatAllItems = groups.flatMap(g => g.items);

                return (
                  <div className="flex flex-col">
                    <div className="p-3 border-b border-[var(--border)] bg-[var(--surface-sunken)] flex items-center gap-2">
                      <Icon name="search" size={16} className="text-[var(--primary)]" />
                      <input
                        type="text"
                        id="command-palette-input"
                        autoFocus
                        value={cmdQuery}
                        onChange={(e) => {
                          setCmdQuery(e.target.value);
                          setPaletteSelectedIndex(0);
                        }}
                        onKeyDown={(e) => {
                          if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            if (flatAllItems.length > 0) {
                              setPaletteSelectedIndex(prev => (prev < flatAllItems.length - 1 ? prev + 1 : 0));
                            }
                          } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            if (flatAllItems.length > 0) {
                              setPaletteSelectedIndex(prev => (prev > 0 ? prev - 1 : flatAllItems.length - 1));
                            }
                          } else if (e.key === 'Enter') {
                            e.preventDefault();
                            if (flatAllItems.length > 0 && flatAllItems[paletteSelectedIndex]) {
                              flatAllItems[paletteSelectedIndex].onSelect();
                            }
                          }
                        }}
                        placeholder="Search pages, actions (>), products, customers (#1000 0017), invoices (/), vendors (@)..."
                        className="flex-1 bg-transparent border-none text-xs text-[var(--foreground)] outline-none"
                      />
                      <div className="flex items-center gap-1.5 text-[10px] text-[var(--muted-foreground)]">
                        <kbd className="px-1 py-0.5 rounded border border-[var(--border)] bg-[var(--surface)] font-mono">↑↓</kbd>
                        <span>nav</span>
                        <kbd className="px-1 py-0.5 rounded border border-[var(--border)] bg-[var(--surface)] font-mono">Enter</kbd>
                        <span>open</span>
                        <kbd className="px-1 py-0.5 rounded border border-[var(--border)] bg-[var(--surface)] font-mono">Esc</kbd>
                      </div>
                    </div>

                    {/* Query Prefixes */}
                    <div className="px-3 py-1.5 bg-[var(--surface)] border-b border-[var(--border)] flex items-center gap-2 text-[10px] text-[var(--muted-foreground)] overflow-x-auto">
                      <span className="font-semibold text-[var(--foreground)]">Prefixes:</span>
                      <button type="button" onClick={() => setCmdQuery('#')} className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] hover:text-[var(--primary)] font-mono cursor-pointer"># Customers</button>
                      <button type="button" onClick={() => setCmdQuery('>')} className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] hover:text-[var(--primary)] font-mono cursor-pointer">&gt; Actions</button>
                      <button type="button" onClick={() => setCmdQuery('/')} className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] hover:text-[var(--primary)] font-mono cursor-pointer">/ Invoices</button>
                      <button type="button" onClick={() => setCmdQuery('@')} className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] hover:text-[var(--primary)] font-mono cursor-pointer">@ Vendors</button>
                    </div>

                    {/* Results List */}
                    <div className="max-h-96 overflow-y-auto p-2 text-xs divide-y divide-[var(--border)] space-y-3">
                      {!cmdQuery.trim() && (
                        <div className="p-2 space-y-2">
                          <div className="text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider">Recent Searches</div>
                          <div className="flex flex-wrap gap-1.5">
                            {['1000 0017', 'saree', 'DF1-R1-26000412', 'Panjabi Cotton'].map((term, i) => (
                              <button
                                key={i}
                                type="button"
                                onClick={() => { setCmdQuery(term); setPaletteSelectedIndex(0); }}
                                className="px-2 py-1 rounded-lg bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] hover:text-[var(--primary)] text-xs font-medium border border-[var(--border)] flex items-center gap-1 cursor-pointer"
                              >
                                <Icon name="clock" size={11} className="text-[var(--muted-foreground)]" />
                                <span>{term}</span>
                              </button>
                            ))}
                          </div>
                        </div>
                      )}

                      {groups.length === 0 && cmdQuery.trim() && (
                        <div className="py-10 text-center text-[var(--muted-foreground)]">
                          <Icon name="search" size={28} className="mx-auto mb-2 opacity-40" />
                          <div className="font-semibold text-xs text-[var(--foreground)]">No matching results</div>
                          <p className="text-[11px] mt-0.5">Try searching by SKU, customer phone, #1000 0017, or screen name.</p>
                        </div>
                      )}

                      {(() => {
                        let runningIdx = 0;
                        return groups.map((group) => (
                          <div key={group.id} className="pt-2 first:pt-0">
                            <div className="px-2 py-1 text-[10px] font-bold text-[var(--muted-foreground)] uppercase flex justify-between items-center">
                              <span>{group.name}</span>
                              <span className="text-[9px] font-normal font-mono opacity-80">{group.total}</span>
                            </div>
                            <div className="space-y-0.5 mt-0.5">
                              {group.items.map((item) => {
                                const currentItemIdx = runningIdx++;
                                const isSelected = currentItemIdx === paletteSelectedIndex;
                                return (
                                  <div
                                    key={item.id}
                                    onClick={() => item.onSelect()}
                                    onMouseEnter={() => setPaletteSelectedIndex(currentItemIdx)}
                                    className={`px-2.5 py-1.5 rounded-xl cursor-pointer flex items-center justify-between transition-colors ${
                                      isSelected 
                                        ? 'bg-[var(--primary)] text-white font-medium shadow-xs' 
                                        : (item.isError ? 'bg-[var(--danger-soft)] text-[var(--danger)] hover:bg-[var(--danger-soft)]' : 'hover:bg-[var(--surface-sunken)] text-[var(--foreground)]')
                                    }`}
                                  >
                                    <div className="flex items-center gap-2 truncate">
                                      <div className={`w-6 h-6 rounded-lg flex items-center justify-center shrink-0 ${
                                        isSelected ? 'bg-white/20 text-white' : (item.isError ? 'bg-[var(--danger-soft)] text-[var(--danger)]' : 'bg-[var(--surface-sunken)] text-[var(--primary)]')
                                      }`}>
                                        <Icon name={item.icon || 'search'} size={13} />
                                      </div>
                                      <div className="truncate">
                                        <div className="font-semibold text-xs flex items-center gap-1.5 truncate">
                                          <span className="truncate">{item.label}</span>
                                          {item.badge && (
                                            <span className={`px-1.5 py-0.2 rounded text-[9px] font-mono font-bold ${
                                              isSelected ? 'bg-white/20 text-white' : 'bg-[var(--success-soft)] text-[var(--success)]'
                                            }`}>
                                              {item.badge}
                                            </span>
                                          )}
                                          {item.isRestricted && (
                                            <span className={`px-1.5 py-0.2 rounded text-[9px] font-bold ${
                                              isSelected ? 'bg-white/20 text-white' : 'bg-[var(--warning-soft)] text-[var(--warning)]'
                                            }`}>
                                              Restricted
                                            </span>
                                          )}
                                        </div>
                                        {item.subtitle && (
                                          <div className={`text-[10px] truncate ${
                                            isSelected ? 'text-white/80' : 'text-[var(--muted-foreground)]'
                                          }`}>
                                            {item.subtitle}
                                          </div>
                                        )}
                                      </div>
                                    </div>
                                    <div className="shrink-0 ml-2 flex items-center gap-1">
                                      {item.shortcut && (
                                        <kbd className={`text-[10px] font-mono px-1.5 py-0.5 rounded border ${
                                          isSelected ? 'bg-black/20 text-white border-transparent' : 'bg-[var(--surface)] text-[var(--muted-foreground)] border-[var(--border)]'
                                        }`}>
                                          {item.shortcut}
                                        </kbd>
                                      )}
                                      <Icon name="arrowRight" size={12} className={isSelected ? 'text-white' : 'text-[var(--muted-foreground)] opacity-50'} />
                                    </div>
                                  </div>
                                );
                              })}
                            </div>
                          </div>
                        ));
                      })()}
                    </div>
                  </div>
                );
              })()}
            </Modal>
          )}

          {/* 25A. CUSTOMER MEMBERSHIP CARD MODAL (CR80 Standard with Isolated Print) */}
          {showCustomerCardModal && (
            <Modal
              isOpen={showCustomerCardModal}
              onClose={() => setShowCustomerCardModal(false)}
              title="Customer Membership Card — CR80 Standard"
              subtitle="Barcode & Member details ready for CR80 card or PVC printer"
              icon="creditCard"
              size="2xl"
              printable={true}
            >

                {/* Printable Isolated Card Body */}
                <div className="p-6 bg-[var(--surface-sunken)] flex flex-col items-center justify-center">
                  <div id="printable-card-area" className="printable-area w-full max-w-lg space-y-4">
                    {(() => {
                      const c = cardCustomer || selectedCustomer || attachedCustomer || MOCK_CUSTOMERS[0];
                      return (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {/* FRONT OF CARD */}
                          <div
                            style={{
                              background: 'linear-gradient(135deg, #0f172a 0%, #064e3b 50%, #022c22 100%)',
                              WebkitPrintColorAdjust: 'exact',
                              printColorAdjust: 'exact',
                              color: '#ffffff'
                            }}
                            className="w-full aspect-[85.6/54] rounded-2xl p-4 flex flex-col justify-between text-white shadow-xl relative overflow-hidden border border-[var(--primary)]/30"
                          >
                            {/* Watermark / Pattern */}
                            <div className="absolute -right-6 -bottom-6 w-32 h-32 rounded-full border border-[var(--primary)]/20 pointer-events-none"></div>
                            <div className="absolute right-6 -top-6 w-24 h-24 rounded-full border border-[var(--primary)]/20 pointer-events-none"></div>

                            {/* Card Top */}
                            <div className="flex items-start justify-between relative z-10">
                              <div>
                                <div className="font-extrabold text-xs tracking-widest uppercase">DIFFERENT FASHION</div>
                                <div className="text-[9px] text-[var(--primary-soft)] tracking-wider font-semibold">PREMIUM PRIVILEGE CLUB</div>
                              </div>
                              <div className="px-2 py-0.5 rounded-full bg-[var(--warning)] text-[var(--foreground)] font-black text-[9px] tracking-wider uppercase shadow-xs">
                                {c.group || 'VIP GOLD'}
                              </div>
                            </div>

                            {/* Chip & Contactless */}
                            <div className="flex items-center gap-3 relative z-10 my-1">
                              <div
                                style={{
                                  background: 'linear-gradient(135deg, #fcd34d 0%, #d97706 100%)',
                                  WebkitPrintColorAdjust: 'exact',
                                  printColorAdjust: 'exact'
                                }}
                                className="w-8 h-6 rounded border border-[var(--warning)]/30 flex items-center justify-center shadow-inner"
                              >
                                <div className="w-5 h-3.5 border border-[var(--warning)]/40 rounded-xs"></div>
                              </div>
                              <Icon name="wifi" size={14} className="text-[var(--primary-soft)] rotate-90" />
                            </div>

                            {/* Card Bottom Details */}
                            <div className="relative z-10 space-y-1">
                              <div className="font-mono text-sm tracking-widest text-[var(--primary-soft)] font-bold drop-shadow-xs">
                                {c.customer_no || '1000 0017'}
                              </div>
                              <div className="flex justify-between items-end">
                                <div>
                                  <div className="font-bold text-xs text-white truncate max-w-[140px]">{c.name_en}</div>
                                  <div className="text-[9px] text-[var(--primary)]">{c.name_bn || 'সম্মানিত গ্রাহক'}</div>
                                </div>
                                <div className="text-right">
                                  <div className="text-[8px] text-[var(--primary)] font-mono uppercase">EXP: 04/29</div>
                                  <div className="text-[9px] font-mono text-white font-semibold">BIN 002847192</div>
                                </div>
                              </div>
                            </div>
                          </div>

                          {/* BACK OF CARD */}
                          <div
                            style={{
                              background: 'linear-gradient(to bottom, #f8fafc 0%, #e2e8f0 100%)',
                              WebkitPrintColorAdjust: 'exact',
                              printColorAdjust: 'exact',
                              color: '#1e293b'
                            }}
                            className="w-full aspect-[85.6/54] rounded-2xl flex flex-col justify-between shadow-xl relative overflow-hidden border border-[var(--border)]"
                          >
                            {/* Magnetic Stripe */}
                            <div
                              style={{
                                background: '#0f172a',
                                WebkitPrintColorAdjust: 'exact',
                                printColorAdjust: 'exact'
                              }}
                              className="w-full h-8 mt-3"
                            ></div>

                            {/* Signature Panel */}
                            <div className="px-4 py-1">
                              <div className="flex items-center justify-between text-[8px] text-[var(--muted-foreground)] font-semibold mb-0.5">
                                <span>AUTHORIZED SIGNATURE</span>
                                <span>NOT TRANSFERABLE</span>
                              </div>
                              <div className="h-6 w-full bg-white border border-[var(--border)] rounded flex items-center px-2 font-serif italic text-xs text-[var(--subtle-foreground)]">
                                {c.name_en}
                              </div>
                            </div>

                            {/* Barcode & Terms */}
                            <div className="px-4 pb-3 flex items-center justify-between">
                              <div className="text-[7.5px] text-[var(--muted-foreground)] max-w-[130px] leading-tight">
                                This card is property of Different Fashion. Valid at all branches in Bangladesh. Helpline: +880 1711-223344
                              </div>
                              <div className="text-center">
                                <div className="h-6 w-24 flex items-center justify-between mx-auto">
                                  {[2,1,3,1,1,2,1,3,2,1,2,1,3,1].map((w, i) => (
                                    <span key={i} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                                  ))}
                                </div>
                                <span className="font-mono text-[7px] text-[var(--subtle-foreground)] block">MC{c.customer_no?.replace(/[ ]+/g, '') || '10000017'}01</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })()}
                  </div>
                </div>

                {/* Modal Footer Controls */}
                <div className="no-print p-3.5 border-t border-[var(--border)] bg-[var(--surface)] flex items-center justify-between gap-3">
                  <button
                    type="button"
                    onClick={() => {
                      setShowCustomerCardModal(false);
                      setCurrentScreen('membership_cards');
                    }}
                    className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs text-[var(--muted-foreground)] hover:text-[var(--foreground)] flex items-center gap-1.5 cursor-pointer"
                  >
                    <Icon name="layers" size={14} />
                    <span>Go to Bulk Card Center (F8)</span>
                  </button>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setShowCustomerCardModal(false)}
                      className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-medium text-xs text-[var(--foreground)]"
                    >
                      Close
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        window.print();
                        addToast(`Printing membership card for ${cardCustomer?.name_en || 'Customer'}...`, "success");
                      }}
                      className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs flex items-center gap-1.5 shadow-xs cursor-pointer"
                    >
                      <Icon name="printer" size={14} />
                      <span>Print Card</span>
                    </button>
                  </div>
                </div>
            </Modal>
          )}

          {/* 25B. ADD CATEGORY MODAL */}
          {showAddCategoryModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowAddCategoryModal(false)}></div>
              <div className="relative z-50 w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-1.5 text-sm">
                    <Icon name="tag" size={15} className="text-[var(--primary)]" />
                    <span>+ Add Product Category</span>
                  </div>
                  <button onClick={() => { setCategoryErrors({}); setShowAddCategoryModal(false); }}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Category Name (English) *</label>
                  <input
                    type="text"
                    autoFocus
                    placeholder="e.g. Blazers & Suits"
                    value={newCategoryNameEn}
                    onChange={(e) => {
                      setNewCategoryNameEn(e.target.value);
                      setNewCategorySlug(e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '-'));
                      if (categoryErrors.nameEn) setCategoryErrors(prev => ({ ...prev, nameEn: null }));
                    }}
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] font-semibold ${
                      categoryErrors.nameEn ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {categoryErrors.nameEn && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{categoryErrors.nameEn}</span>
                    </div>
                  )}
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Category Name (Bengali)</label>
                  <input
                    type="text"
                    placeholder="e.g. ব্লেজার ও স্যুট"
                    value={newCategoryNameBn}
                    onChange={(e) => setNewCategoryNameBn(e.target.value)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                  />
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">URL / Code Slug</label>
                    <input
                      type="text"
                      value={newCategorySlug}
                      onChange={(e) => setNewCategorySlug(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[11px] font-mono"
                    />
                  </div>
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Default VAT Rate</label>
                    <select className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs">
                      <option>15% Standard</option>
                      <option>7.5% Reduced</option>
                      <option>0% Exempt</option>
                    </select>
                  </div>
                </div>
                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setCategoryErrors({});
                      setShowAddCategoryModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const name = (newCategoryNameEn || '').trim();
                      if (!name || name.length < 2) {
                        setCategoryErrors({ nameEn: "Category name is required (minimum 2 characters)" });
                        addToast("Please enter a valid category name", "warning");
                        return;
                      }
                      setCategoryErrors({});
                      const cat = {
                        id: newCategorySlug || 'cat_' + Date.now(),
                        name_en: name,
                        name_bn: newCategoryNameBn || name,
                        sku_count: 0
                      };
                      setCategoriesList(prev => [...prev, cat]);
                      CATEGORIES.push(cat);
                      addToast(`Category "${name}" added successfully!`, "success");
                      setNewCategoryNameEn('');
                      setNewCategoryNameBn('');
                      setNewCategorySlug('');
                      setShowAddCategoryModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs cursor-pointer"
                  >
                    Save Category
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 25C. SAAS ONBOARD NEW TENANT MODAL */}
          {showNewTenantModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowNewTenantModal(false)}></div>
              <div className="relative z-50 w-full max-w-md bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-5 space-y-3.5 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-2 text-sm">
                    <Icon name="building" size={16} className="text-[var(--primary)]" />
                    <span>+ Onboard New Shop Tenant (SaaS Multi-Tenant)</span>
                  </div>
                  <button onClick={() => { setTenantErrors({}); setShowNewTenantModal(false); }}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Shop / Brand Name *</label>
                  <input
                    type="text"
                    autoFocus
                    placeholder="e.g. Sailor Lifestyle"
                    value={newTenantName}
                    onChange={(e) => {
                      setNewTenantName(e.target.value);
                      setNewTenantDomain(e.target.value.toLowerCase().replace(/[^a-z0-9]+/g, '') + '.retailpos.bd');
                      if (tenantErrors.name) setTenantErrors(prev => ({ ...prev, name: null }));
                    }}
                    className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border font-semibold text-xs ${
                      tenantErrors.name ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                    }`}
                  />
                  {tenantErrors.name && (
                    <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                      <Icon name="alertCircle" size={11} />
                      <span>{tenantErrors.name}</span>
                    </div>
                  )}
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Dedicated Subdomain</label>
                    <input
                      type="text"
                      value={newTenantDomain}
                      onChange={(e) => setNewTenantDomain(e.target.value)}
                      placeholder="shop.retailpos.bd"
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-[11px]"
                    />
                  </div>
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Subscription Plan</label>
                    <select
                      value={newTenantPlan}
                      onChange={(e) => setNewTenantPlan(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                    >
                      <option>Starter (৳2,500/mo)</option>
                      <option>Pro (৳4,500/mo)</option>
                      <option>Enterprise (৳12,000/mo)</option>
                    </select>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Owner Phone (WhatsApp)</label>
                    <input
                      type="text"
                      value={newTenantPhone}
                      onChange={(e) => {
                        setNewTenantPhone(e.target.value);
                        if (tenantErrors.phone) setTenantErrors(prev => ({ ...prev, phone: null }));
                      }}
                      placeholder="01711-XXXXXX"
                      className={`mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border font-mono text-xs ${
                        tenantErrors.phone ? 'border-[var(--danger)] bg-[var(--danger-soft)]' : 'border-[var(--border)]'
                      }`}
                    />
                    {tenantErrors.phone && (
                      <div className="text-[10px] text-[var(--danger)] font-semibold mt-1 flex items-center gap-1">
                        <Icon name="alertCircle" size={11} />
                        <span>{tenantErrors.phone}</span>
                      </div>
                    )}
                  </div>
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Initial Branch Quota</label>
                    <input
                      type="number"
                      min="1"
                      value={newTenantBranches}
                      onChange={(e) => setNewTenantBranches(Number(e.target.value) || 1)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-xs"
                    />
                  </div>
                </div>
                <div className="pt-2 flex gap-2">
                  <button
                    type="button"
                    onClick={() => {
                      setTenantErrors({});
                      setShowNewTenantModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const cleanName = (newTenantName || '').trim();
                      const cleanPhone = (newTenantPhone || '').replace(/[^0-9]/g, '');
                      const errors = {};

                      if (!cleanName || cleanName.length < 2) {
                        errors.name = "Shop / Brand name is required (minimum 2 characters)";
                      }

                      if (cleanPhone && !/^01[3-9]\\d{8}$/.test(cleanPhone)) {
                        errors.phone = "Owner phone must be a valid 11-digit mobile (013-019)";
                      }

                      if (Object.keys(errors).length > 0) {
                        setTenantErrors(errors);
                        addToast("Please correct tenant onboarding details", "warning");
                        return;
                      }

                      setTenantErrors({});
                      const tenant = {
                        id: 'T-' + (100 + saasTenants.length + 1),
                        name: cleanName,
                        code: cleanName.slice(0, 3).toUpperCase(),
                        domain: newTenantDomain || 'shop.retailpos.bd',
                        plan: newTenantPlan,
                        branches: newTenantBranches,
                        registers: newTenantBranches * 2,
                        status: 'Active',
                        mrr: newTenantPlan.includes('12,000') ? 12000 : newTenantPlan.includes('4,500') ? 4500 : 2500,
                        joined: 'Today'
                      };
                      setSaasTenants(prev => [tenant, ...prev]);
                      addToast(`Tenant "${cleanName}" onboarded to SaaS Platform!`, "success");
                      setNewTenantName('');
                      setNewTenantPhone('');
                      setShowNewTenantModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs cursor-pointer"
                  >
                    Onboard Tenant
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 25D. QUICK BULK BARCODE MODAL */}
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
                <div className="printable-area p-4 bg-white text-black rounded-xl border border-[var(--border)]">
                  <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    {selectedBarcodeProducts.map(p => (
                      <div key={p.id} className="p-2 border border-[var(--border)] rounded text-center space-y-0.5">
                        <div className="text-[8px] font-bold tracking-widest uppercase">DIFFERENT FASHION</div>
                        <div className="text-[10px] font-bold truncate">{p.name_en}</div>
                        <div className="h-5 w-24 mx-auto flex items-center justify-between">
                          {[2,1,2,1,1,2,1,2,1,1,2,1].map((w, i) => (
                            <span key={i} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                          ))}
                        </div>
                        <div className="font-mono text-[8px]">{p.barcode || 'DF-1002'}</div>
                        <div className="font-bold text-[10px] num">{formatMoney(p.price)}</div>
                      </div>
                    ))}
                  </div>
                </div>
                <div className="pt-2 flex justify-end gap-2">
                  <button onClick={() => setShowBulkBarcodeModal(false)} className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold">Close</button>
                  <button
                    onClick={() => {
                      window.print();
                      addToast("Labels dispatched to thermal barcode printer!", "success");
                    }}
                    className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold flex items-center gap-1.5 shadow-xs cursor-pointer"
                  >
                    <Icon name="printer" size={14} />
                    <span>Print All Selected Barcodes</span>
                  </button>
                </div>
              </div>
            </div>
          )}

          {/* 25. KEYBOARD SHORTCUTS MODAL (?) */}
          {showShortcutsModal && (
            <Modal
              isOpen={showShortcutsModal}
              onClose={() => setShowShortcutsModal(false)}
              title="Keyboard Shortcuts Cheatsheet"
              subtitle="Spec POS Hotkeys & Global Navigation (Zero Key Conflicts)"
              icon="command"
              size="xl"
            >
              <div className="grid grid-cols-2 gap-4 text-xs">
                <div className="space-y-1.5">
                  <div className="font-bold text-[10px] uppercase text-[var(--primary)] pb-1 border-b border-[var(--border)]">POS Function Keys</div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>POS Register</span><kbd>F1</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Focus Scan Box</span><kbd>F2</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Qty of Selected Line</span><kbd>F3</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Attach Customer</span><kbd>F4</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Price & Stock Check</span><kbd>F5</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Line Item Discount</span><kbd>F6</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Order Discount</span><kbd>Shift+F6</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Park / Hold Cart</span><kbd>F8</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Held Sales List</span><kbd>Shift+F8</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>PAY / Due Tender</span><kbd>F9</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Complete Sale (Inside Pay)</span><kbd>F10</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Exact Cash (Inside Pay)</span><kbd>Alt+E</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Fast Return / Exchange</span><kbd>F11</kbd></div>
                </div>

                <div className="space-y-1.5">
                  <div className="font-bold text-[10px] uppercase text-[var(--primary)] pb-1 border-b border-[var(--border)]">Global Navigation & System</div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Collect Customer Due</span><kbd>Ctrl+D</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Quick New Customer</span><kbd>Ctrl+N</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Command Palette</span><kbd>Ctrl+K</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Toggle Sidebar</span><kbd>Ctrl+B</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Lock Screen Terminal</span><kbd>Ctrl+L</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Keyboard Cheatsheet</span><kbd>?</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Dashboard</span><kbd>G D</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Sales</span><kbd>G S</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Returns</span><kbd>G T</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Due Collection</span><kbd>G U</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Customers</span><kbd>G C</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Stock</span><kbd>G K</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Purchases</span><kbd>G P</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Vendors</span><kbd>G V</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Money</span><kbd>G M</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Reports</span><kbd>G R</kbd></div>
                  <div className="flex justify-between py-1 border-b border-[var(--border)]"><span>Go to Settings</span><kbd>G ,</kbd></div>
                </div>
              </div>
            </Modal>
          )}

          {/* PART 4: POS APPAREL VARIANT PICKER MODAL (Size × Colour Matrix) */}
          {showVariantPickerModal && variantPickerProduct && (
            <Modal
              isOpen={showVariantPickerModal}
              onClose={() => setShowVariantPickerModal(false)}
              size="lg"
              title={`Select Size & Colour Variant — ${variantPickerProduct.name_en || variantPickerProduct.name}`}
            >
              <div className="space-y-4 text-xs">
                {/* Header info */}
                <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex items-center justify-center text-[var(--primary)] text-lg font-bold">
                      <Icon name="package" size={24} />
                    </div>
                    <div>
                      <div className="font-bold text-sm text-[var(--foreground)]">{variantPickerProduct.name_en || variantPickerProduct.name}</div>
                      <div className="text-[11px] text-[var(--muted-foreground)]">
                        SKU: <span className="font-mono">{variantPickerProduct.sku}</span> · Category: {variantPickerProduct.category}
                      </div>
                    </div>
                  </div>
                  <div className="text-right">
                    <div className="text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider font-semibold">Retail Price</div>
                    <div className="num font-bold text-base text-[var(--primary)]">{formatMoney(variantPickerProduct.price)}</div>
                  </div>
                </div>

                {/* Size × Colour 2D Grid */}
                <div className="space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-bold text-[var(--foreground)]">Variant Matrix (Stock per Cell)</span>
                    <span className="text-[11px] text-[var(--muted-foreground)]">Click any cell to add that variant to current sale</span>
                  </div>

                  <div className="overflow-x-auto border border-[var(--border)] rounded-xl">
                    <table className="w-full text-center border-collapse">
                      <thead className="bg-[var(--surface-sunken)] text-[11px] text-[var(--muted-foreground)] font-semibold border-b border-[var(--border)]">
                        <tr>
                          <th className="py-2.5 px-3 text-left">Colour / Size</th>
                          {['38 (S)', '40 (M)', '42 (L)', '44 (XL)', '46 (XXL)'].map(sz => (
                            <th key={sz} className="py-2.5 px-3 font-mono font-bold">{sz}</th>
                          ))}
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[var(--border)] text-xs">
                        {[
                          { name: 'Maroon', hex: '#800000' },
                          { name: 'Navy Blue', hex: '#000080' },
                          { name: 'Forest Green', hex: '#228B22' },
                          { name: 'Ivory White', hex: '#FFFFF0' },
                          { name: 'Jet Black', hex: '#000000' }
                        ].map((col, rowIdx) => (
                          <tr key={col.name} className="hover:bg-[var(--surface-sunken)]/50 transition-colors">
                            <td className="py-2.5 px-3 text-left font-semibold text-[var(--foreground)] flex items-center gap-2">
                              <span className="w-3.5 h-3.5 rounded-full border border-black/20 shrink-0" style={{ backgroundColor: col.hex }}></span>
                              <span>{col.name}</span>
                            </td>
                            {['38', '40', '42', '44', '46'].map((szNum, colIdx) => {
                              const cellStock = (rowIdx === 2 && colIdx === 4) ? 0 : ((rowIdx * 3 + colIdx * 5 + 4) % 14) + 1;
                              const variantSku = `${variantPickerProduct.sku}-${szNum}-${col.name.slice(0, 3).toUpperCase()}`;
                              const isOutOfStock = cellStock === 0;

                              return (
                                <td key={szNum} className="p-1.5">
                                  <button
                                    type="button"
                                    disabled={isOutOfStock}
                                    onClick={() => {
                                      addToCart({
                                        ...variantPickerProduct,
                                        name: `${variantPickerProduct.name_en || variantPickerProduct.name} (${col.name}, Size ${szNum})`,
                                        sku: variantSku,
                                        variant: `${col.name} / ${szNum}`
                                      });
                                      setShowVariantPickerModal(false);
                                      addToast(`Added ${variantPickerProduct.name_en || variantPickerProduct.name} (${col.name}, ${szNum}) to cart`, "success");
                                    }}
                                    className={`w-full py-2 px-1.5 rounded-lg border text-center transition-all ${
                                      isOutOfStock
                                        ? 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)] opacity-40 cursor-not-allowed'
                                        : 'bg-[var(--surface)] border-[var(--border)] hover:border-[var(--primary)] hover:bg-[var(--primary-soft)] cursor-pointer group shadow-xs'
                                    }`}
                                  >
                                    <div className="font-mono font-bold text-xs group-hover:text-[var(--primary)]">
                                      {isOutOfStock ? '0' : `${cellStock} pcs`}
                                    </div>
                                    <div className="text-[10px] text-[var(--muted-foreground)]">
                                      {isOutOfStock ? 'Sold out' : 'In stock'}
                                    </div>
                                  </button>
                                </td>
                              );
                            })}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>

                <div className="pt-2 flex justify-between items-center">
                  <div className="text-[11px] text-[var(--muted-foreground)] flex items-center gap-1.5">
                    <Icon name="info" size={13} />
                    <span>Real-time stock synced with central branch warehouse</span>
                  </div>
                  <button
                    type="button"
                    onClick={() => setShowVariantPickerModal(false)}
                    className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  >
                    Close
                  </button>
                </div>
              </div>
            </Modal>
          )}

          {/* PART 4: SALES RETURN & EXCHANGE WIZARD MODAL */}
          {showReturnWizardModal && (
            <Modal
              isOpen={showReturnWizardModal}
              onClose={() => setShowReturnWizardModal(false)}
              size="xl"
              title={`Return & Exchange Wizard (Step ${returnWizardStep} of 4)`}
            >
              <div className="space-y-4 text-xs">
                {/* Stepper Bar */}
                <div className="grid grid-cols-4 gap-2 pb-2 border-b border-[var(--border)] text-[11px] font-semibold text-center">
                  <div className={`p-1.5 rounded-lg border ${returnWizardStep === 1 ? 'bg-[var(--primary-soft)] text-[var(--primary)] border-[var(--primary)]' : returnWizardStep > 1 ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]' : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)]'}`}>
                    1. Find Invoice
                  </div>
                  <div className={`p-1.5 rounded-lg border ${returnWizardStep === 2 ? 'bg-[var(--primary-soft)] text-[var(--primary)] border-[var(--primary)]' : returnWizardStep > 2 ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]' : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)]'}`}>
                    2. Select Items
                  </div>
                  <div className={`p-1.5 rounded-lg border ${returnWizardStep === 3 ? 'bg-[var(--primary-soft)] text-[var(--primary)] border-[var(--primary)]' : returnWizardStep > 3 ? 'bg-[var(--success-soft)] text-[var(--success)] border-[var(--success)]' : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)]'}`}>
                    3. Refund Method
                  </div>
                  <div className={`p-1.5 rounded-lg border ${returnWizardStep === 4 ? 'bg-[var(--primary-soft)] text-[var(--primary)] border-[var(--primary)]' : 'bg-[var(--surface-sunken)] border-[var(--border)] text-[var(--muted-foreground)]'}`}>
                    4. Voucher / Receipt
                  </div>
                </div>

                {/* STEP 1: FIND INVOICE */}
                {returnWizardStep === 1 && (
                  <div className="space-y-3">
                    <div>
                      <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Scan or Enter Sale Invoice Number *</label>
                      <div className="flex gap-2 mt-1">
                        <input
                          type="text"
                          value={returnWizardInvoiceQuery}
                          onChange={(e) => setReturnWizardInvoiceQuery(e.target.value)}
                          placeholder="e.g. DF1-R1-26000412"
                          className="flex-1 h-9 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono font-bold text-xs"
                        />
                        <button
                          type="button"
                          onClick={() => {
                            if (!returnWizardInvoiceQuery.trim()) {
                              addToast("Enter an invoice number", "warning");
                              return;
                            }
                            addToast(`Invoice ${returnWizardInvoiceQuery} verified!`, "success");
                          }}
                          className="h-9 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs flex items-center gap-1.5"
                        >
                          <Icon name="search" size={14} />
                          <span>Verify</span>
                        </button>
                      </div>
                    </div>

                    <div className="flex items-center gap-2">
                      <span className="text-[11px] text-[var(--muted-foreground)]">Demo Invoices:</span>
                      {['DF1-R1-26000412', 'DF1-R1-26000408', 'DF1-R1-26000399'].map(inv => (
                        <button
                          key={inv}
                          type="button"
                          onClick={() => setReturnWizardInvoiceQuery(inv)}
                          className="px-2 py-0.5 rounded text-[10px] font-mono font-semibold bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)]"
                        >
                          {inv}
                        </button>
                      ))}
                    </div>

                    {/* Verified Invoice Summary Card */}
                    <div className="p-3.5 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-2">
                      <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                        <div>
                          <div className="font-bold text-xs text-[var(--foreground)]">{returnWizardInvoiceQuery || 'DF1-R1-26000412'}</div>
                          <div className="text-[11px] text-[var(--muted-foreground)]">Customer: Rahim Uddin (1000 0017) · Sold on Today 11:42 AM</div>
                        </div>
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--success-soft)] text-[var(--success)]">
                          Completed Sale
                        </span>
                      </div>
                      <div className="grid grid-cols-3 gap-2 text-xs">
                        <div>
                          <span className="text-[10px] text-[var(--muted-foreground)]">Gross Total</span>
                          <div className="num font-bold">৳18,500.00</div>
                        </div>
                        <div>
                          <span className="text-[10px] text-[var(--muted-foreground)]">VAT (15%) Included</span>
                          <div className="num font-bold text-[var(--success)]">৳2,413.04</div>
                        </div>
                        <div>
                          <span className="text-[10px] text-[var(--muted-foreground)]">Payment</span>
                          <div className="font-semibold">Cash (৳18,500)</div>
                        </div>
                      </div>
                    </div>

                    <div className="pt-2 flex justify-end gap-2">
                      <button
                        type="button"
                        onClick={() => setShowReturnWizardModal(false)}
                        className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                      >
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={() => setReturnWizardStep(2)}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs flex items-center gap-1.5"
                      >
                        <span>Next: Select Items</span>
                        <Icon name="arrowRight" size={14} />
                      </button>
                    </div>
                  </div>
                )}

                {/* STEP 2: SELECT ITEMS */}
                {returnWizardStep === 2 && (
                  <div className="space-y-3">
                    <div className="border border-[var(--border)] rounded-xl overflow-hidden">
                      <table className="w-full text-left text-xs">
                        <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[10px] text-[var(--muted-foreground)] uppercase">
                          <tr>
                            <th className="py-2 px-3">Item / SKU</th>
                            <th className="py-2 px-2 text-center">Sold Qty</th>
                            <th className="py-2 px-2 text-center">Returnable</th>
                            <th className="py-2 px-2 text-center">Return Qty</th>
                            <th className="py-2 px-2">Reason</th>
                            <th className="py-2 px-2">Condition</th>
                            <th className="py-2 px-3 text-right">Refund Total</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-[var(--border)]">
                          <tr>
                            <td className="py-2.5 px-3">
                              <div className="font-semibold text-[var(--foreground)]">Premium Silk Jamdani Saree (Maroon)</div>
                              <div className="text-[10px] num text-[var(--muted-foreground)]">SKU: SAR-JAM-001-MRN · ৳12,500.00</div>
                            </td>
                            <td className="py-2.5 px-2 text-center font-mono font-semibold">1</td>
                            <td className="py-2.5 px-2 text-center font-mono font-bold text-[var(--primary)]">1</td>
                            <td className="py-2.5 px-2 text-center">
                              <input
                                type="number"
                                defaultValue="1"
                                min="0"
                                max="1"
                                className="w-12 h-7 px-1 text-center font-mono font-bold rounded bg-[var(--surface-sunken)] border border-[var(--border)]"
                              />
                            </td>
                            <td className="py-2.5 px-2">
                              <select className="h-7 px-1 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[11px]">
                                <option>Defective / Damaged</option>
                                <option>Wrong Size / Fit</option>
                                <option>Customer Changed Mind</option>
                              </select>
                            </td>
                            <td className="py-2.5 px-2">
                              <select className="h-7 px-1 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-[11px]">
                                <option>Damaged / Write-off</option>
                                <option>Sellable (Restock to Shelf)</option>
                                <option>Return to Vendor RMA</option>
                              </select>
                            </td>
                            <td className="py-2.5 px-3 text-right font-mono font-bold text-[var(--primary)]">
                              ৳12,500.00
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>

                    {/* Breakdown */}
                    <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between">
                      <div className="space-y-0.5">
                        <span className="text-[11px] text-[var(--muted-foreground)]">Gross Return: <strong>৳12,500.00</strong></span>
                        <span className="text-[11px] text-[var(--muted-foreground)] ml-3">15% VAT Reversal: <strong className="text-[var(--success)]">৳1,630.43</strong></span>
                      </div>
                      <div className="text-right">
                        <span className="text-[10px] text-[var(--muted-foreground)] uppercase tracking-wider block">Net Refund Amount</span>
                        <span className="num font-bold text-base text-[var(--primary)]">৳12,500.00</span>
                      </div>
                    </div>

                    <div className="pt-2 flex justify-between gap-2">
                      <button
                        type="button"
                        onClick={() => setReturnWizardStep(1)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                      >
                        ← Back
                      </button>
                      <button
                        type="button"
                        onClick={() => setReturnWizardStep(3)}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs flex items-center gap-1.5"
                      >
                        <span>Next: Refund Method</span>
                        <Icon name="arrowRight" size={14} />
                      </button>
                    </div>
                  </div>
                )}

                {/* STEP 3: REFUND METHOD */}
                {returnWizardStep === 3 && (
                  <div className="space-y-3">
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Select Refund Settlement Method *</label>
                    <div className="grid grid-cols-2 gap-2.5">
                      {[
                        { id: 'credit_note', title: 'Store Credit Note', desc: 'Issue barcode credit voucher redeemable on future POS purchases', icon: 'fileText', recommended: true },
                        { id: 'exchange', title: 'Direct Item Exchange', desc: 'Transfer refund balance directly into POS register cart', icon: 'refresh', recommended: false },
                        { id: 'cash', title: 'Cash Refund', desc: 'Disburse cash from Counter 1 register drawer (requires manager PIN if > ৳2,000)', icon: 'banknote', recommended: false },
                        { id: 'due_adjust', title: 'Adjust Customer Due', desc: 'Credit amount directly against Rahim Uddin customer receivable balance', icon: 'user', recommended: false }
                      ].map(method => (
                        <div
                          key={method.id}
                          className="p-3 rounded-xl border border-[var(--border)] bg-[var(--surface-sunken)] hover:border-[var(--primary)] hover:bg-[var(--surface)] transition-all cursor-pointer relative"
                        >
                          {method.recommended && (
                            <span className="absolute top-2 right-2 px-1.5 py-0.2 rounded text-[9px] font-bold bg-[var(--primary)] text-[var(--primary-foreground)]">
                              Recommended
                            </span>
                          )}
                          <div className="flex items-center gap-2 font-bold text-xs text-[var(--foreground)]">
                            <Icon name={method.icon} size={15} className="text-[var(--primary)]" />
                            <span>{method.title}</span>
                          </div>
                          <p className="text-[11px] text-[var(--muted-foreground)] mt-1">{method.desc}</p>
                        </div>
                      ))}
                    </div>

                    <div>
                      <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Reason / Authorization Memo</label>
                      <input
                        type="text"
                        defaultValue="Customer requested refund due to defective fabric weave on border"
                        className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                      />
                    </div>

                    <div className="pt-2 flex justify-between gap-2">
                      <button
                        type="button"
                        onClick={() => setReturnWizardStep(2)}
                        className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                      >
                        ← Back
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          addToast("Return processed! Credit Note CN-2610-0089 generated", "success");
                          setReturnWizardStep(4);
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs"
                      >
                        Complete Return & Issue Voucher
                      </button>
                    </div>
                  </div>
                )}

                {/* STEP 4: VOUCHER / RECEIPT */}
                {returnWizardStep === 4 && (
                  <div className="space-y-4 text-center py-2">
                    <div className="w-14 h-14 rounded-full bg-[var(--success-soft)] text-[var(--success)] mx-auto flex items-center justify-center">
                      <Icon name="check" size={28} />
                    </div>
                    <div>
                      <h3 className="font-bold text-base text-[var(--foreground)]">Return Credit Voucher Generated!</h3>
                      <div className="text-xs text-[var(--muted-foreground)] mt-0.5">Voucher Number: <span className="font-mono font-bold text-[var(--foreground)]">CN-2610-0089</span></div>
                    </div>

                    <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] max-w-sm mx-auto text-left space-y-2 text-xs">
                      <div className="flex justify-between">
                        <span className="text-[var(--muted-foreground)]">Refund Beneficiary:</span>
                        <span className="font-semibold">Rahim Uddin (1000 0017)</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-[var(--muted-foreground)]">Total Value:</span>
                        <span className="num font-bold text-base text-[var(--primary)]">৳12,500.00</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-[var(--muted-foreground)]">VAT Reversal (15%):</span>
                        <span className="num font-semibold text-[var(--success)]">৳1,630.43</span>
                      </div>
                      <div className="flex justify-between">
                        <span className="text-[var(--muted-foreground)]">Validity:</span>
                        <span className="font-semibold">90 Days (Expires 28 Jan 2027)</span>
                      </div>
                    </div>

                    <div className="flex justify-center gap-2 pt-2">
                      <button
                        type="button"
                        onClick={() => {
                          window.print();
                          addToast("Printing Credit Note voucher...", "info");
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs flex items-center gap-1.5"
                      >
                        <Icon name="printer" size={14} />
                        <span>Print Voucher</span>
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          setShowReturnWizardModal(false);
                          setCurrentScreen('return_detail');
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs"
                      >
                        View in Return Details
                      </button>
                      <button
                        type="button"
                        onClick={() => setShowReturnWizardModal(false)}
                        className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                      >
                        Done
                      </button>
                    </div>
                  </div>
                )}
              </div>
            </Modal>
          )}

          {/* PART 4: MEMBERSHIP CARD REISSUE MODAL */}
          {showReissueCardModal && (
            <Modal
              isOpen={showReissueCardModal}
              onClose={() => setShowReissueCardModal(false)}
              size="md"
              title="Report Lost & Reissue Membership Card"
            >
              <div className="space-y-3.5 text-xs">
                <div className="p-3 rounded-xl bg-[var(--warning-soft)] border border-[var(--warning)]/30 flex items-center gap-3">
                  <div className="w-10 h-10 rounded-full bg-[var(--warning-soft)] text-[var(--warning)] flex items-center justify-center shrink-0">
                    <Icon name="alertTriangle" size={20} />
                  </div>
                  <div>
                    <div className="font-bold text-xs text-[var(--warning)] text-[var(--warning)]">Previous Card Will Be Invalidated</div>
                    <div className="text-[11px] text-[var(--muted-foreground)]">Card #{reissueOldCardNumber || '1000 0017'} will be permanently marked revoked in system databases.</div>
                  </div>
                </div>

                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Member Name</label>
                  <input
                    type="text"
                    disabled
                    value={reissueTargetCustomer ? (reissueTargetCustomer.name_en || reissueTargetCustomer.name) : 'Rahim Uddin'}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs opacity-70"
                  />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Reason for Reissue *</label>
                    <select
                      value={reissueReason}
                      onChange={(e) => setReissueReason(e.target.value)}
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                    >
                      <option>Card Lost / Stolen</option>
                      <option>Magnetic Stripe Worn</option>
                      <option>Physical Card Broken</option>
                      <option>Tier Upgrade to Gold</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">New Card No. (Luhn Verified) *</label>
                    <input
                      type="text"
                      value={reissueNewCardNumber}
                      onChange={(e) => setReissueNewCardNumber(e.target.value)}
                      placeholder="1000 0380"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono font-bold text-xs"
                    />
                  </div>
                </div>

                <div className="p-3 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-1.5">
                  <div className="flex justify-between">
                    <span className="text-[var(--muted-foreground)]">Outstanding Due Transferred:</span>
                    <span className="num font-bold text-[var(--danger)]">৳8,450.00</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-[var(--muted-foreground)]">Loyalty Points Transferred:</span>
                    <span className="font-mono font-bold text-[var(--success)]">1,420 pts</span>
                  </div>
                  <div className="flex justify-between border-t border-[var(--border)] pt-1">
                    <span className="text-[var(--muted-foreground)]">Reissue Fee:</span>
                    <span className="font-semibold text-[var(--success)]">Waived (Gold Member)</span>
                  </div>
                </div>

                <div className="pt-2 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setShowReissueCardModal(false)}
                    className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      addToast(`Card reissued to ${reissueTargetCustomer ? reissueTargetCustomer.name_en : 'Rahim Uddin'}! New: ${reissueNewCardNumber}`, "success");
                      setShowReissueCardModal(false);
                    }}
                    className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold text-xs shadow-xs"
                  >
                    Confirm & Print New Card
                  </button>
                </div>
              </div>
            </Modal>
          )}

          {/* PART 4: FAST SWITCH USER PIN MODAL */}
          {showSwitchUserPinModal && (
            <Modal
              isOpen={showSwitchUserPinModal}
              onClose={() => {
                setShowSwitchUserPinModal(false);
                setSwitchUserPinDigits('');
              }}
              size="sm"
              title="Switch Register User (Fast PIN)"
            >
              <div className="space-y-4 text-xs select-none">
                <div className="text-center">
                  <p className="text-[11px] text-[var(--muted-foreground)]">Enter 4-digit staff PIN code</p>
                </div>

                <div className="h-10 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-center gap-3">
                  {[0, 1, 2, 3].map(i => (
                    <span
                      key={i}
                      className={`w-3.5 h-3.5 rounded-full transition-transform ${
                        switchUserPinDigits.length > i ? 'bg-[var(--primary)] scale-110' : 'bg-[var(--border)]'
                      }`}
                    ></span>
                  ))}
                </div>

                <div className="grid grid-cols-3 gap-2">
                  {[1, 2, 3, 4, 5, 6, 7, 8, 9, 'C', 0, '⌫'].map(btn => (
                    <button
                      key={btn}
                      type="button"
                      onClick={() => {
                        if (btn === 'C') { setSwitchUserPinDigits(''); return; }
                        if (btn === '⌫') { setSwitchUserPinDigits(p => p.slice(0, -1)); return; }
                        if (switchUserPinDigits.length < 4) {
                          const next = switchUserPinDigits + btn;
                          setSwitchUserPinDigits(next);
                          if (next.length === 4) {
                            handlePinSwitch(next);
                          }
                        }
                      }}
                      className="h-11 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] hover:bg-[var(--surface)] text-sm font-bold font-mono transition-colors"
                    >
                      {btn}
                    </button>
                  ))}
                </div>

                <div className="pt-2 border-t border-[var(--border)] space-y-1.5">
                  <div className="text-[10px] text-[var(--muted-foreground)] font-semibold text-center uppercase tracking-wider">Demo Quick Switch</div>
                  <div className="flex flex-wrap justify-center gap-1.5">
                    {[
                      { pin: '1234', name: 'Sumi', role: 'Cashier' },
                      { pin: '2345', name: 'Rafiq', role: 'Stock Keeper' },
                      { pin: '3456', name: 'Nusrat', role: 'Accountant' },
                      { pin: '5678', name: 'Farhan', role: 'Manager' },
                      { pin: '9999', name: 'Tanvir', role: 'Owner' }
                    ].map(u => (
                      <button
                        key={u.pin}
                        type="button"
                        onClick={() => handlePinSwitch(u.pin)}
                        className="px-2 py-1 rounded-md bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] text-[10px] font-semibold flex items-center gap-1"
                      >
                        <span className="font-mono text-[var(--primary)]">{u.pin}</span>
                        <span>{u.name} ({u.role})</span>
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </Modal>
          )}

          {/* PART 4: ADD OPT-OUT NUMBER MODAL */}
          {showAddOptOutModal && (
            <Modal
              isOpen={showAddOptOutModal}
              onClose={() => setShowAddOptOutModal(false)}
              size="md"
              title="Add Number to SMS Opt-Out Blacklist"
            >
              <div className="space-y-3.5 text-xs">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Customer Mobile Number *</label>
                  <input
                    type="text"
                    value={newOptOutPhone}
                    onChange={(e) => setNewOptOutPhone(e.target.value)}
                    placeholder="e.g. 01712-998877"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono font-bold text-xs"
                  />
                </div>

                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Customer Name</label>
                  <input
                    type="text"
                    value={newOptOutName}
                    onChange={(e) => setNewOptOutName(e.target.value)}
                    placeholder="e.g. Monirul Islam"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs"
                  />
                </div>

                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)] block">Opt-out Reason *</label>
                  <select
                    value={newOptOutReason}
                    onChange={(e) => setNewOptOutReason(e.target.value)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold"
                  >
                    <option>Customer SMS Opt-out Request</option>
                    <option>BTRC DND National Registry</option>
                    <option>Wrong Phone Number on Invoice</option>
                    <option>Spam Complaint</option>
                  </select>
                </div>

                <div className="pt-2 flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setShowAddOptOutModal(false)}
                    className="h-8 px-4 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      if (!newOptOutPhone.trim()) {
                        addToast("Mobile number is required", "warning");
                        return;
                      }
                      setOptOutList(prev => [
                        ...prev,
                        {
                          id: 'opt_' + Date.now(),
                          phone: newOptOutPhone,
                          name: newOptOutName || 'Customer',
                          reason: newOptOutReason,
                          addedAt: 'Today'
                        }
                      ]);
                      setNewOptOutPhone('');
                      setNewOptOutName('');
                      setShowAddOptOutModal(false);
                      addToast(`Added ${newOptOutPhone} to SMS blacklist`, "success");
                    }}
                    className="h-8 px-4 rounded-lg bg-[var(--danger)] text-white font-bold text-xs shadow-xs"
                  >
                    Add to Blacklist
                  </button>
                </div>
              </div>
            </Modal>
          )}

          {/* 26. LOCK SCREEN OVERLAY (Ctrl+L) */}
          {isLocked && (
            <div className="fixed inset-0 bg-[var(--surface)]/90 backdrop-blur-md flex flex-col items-center justify-center z-50 text-white select-none">
              <div className="text-center space-y-4 max-w-xs w-full p-6">
                <div className="w-14 h-14 rounded-full bg-[var(--primary-soft)] text-[var(--primary)] mx-auto flex items-center justify-center">
                  <Icon name="lock" size={28} />
                </div>
                <div>
                  <h2 className="text-lg font-bold">Terminal Locked</h2>
                  <p className="text-xs text-[var(--muted-foreground)] mt-1">Logged in as {currentUser.name}. Enter 4-digit PIN (1234) to unlock.</p>
                </div>

                <div className="h-10 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex items-center justify-center gap-3">
                  {[0, 1, 2, 3].map(i => (
                    <span key={i} className={`w-3.5 h-3.5 rounded-full ${lockPinInput.length > i ? 'bg-[var(--primary)] scale-110' : 'bg-[var(--surface-sunken)]'}`}></span>
                  ))}
                </div>

                <div className="grid grid-cols-3 gap-2">
                  {[1, 2, 3, 4, 5, 6, 7, 8, 9, 'C', 0, '⌫'].map(btn => (
                    <button
                      key={btn}
                      onClick={() => {
                        if (btn === 'C') { setLockPinInput(''); return; }
                        if (btn === '⌫') { setLockPinInput(p => p.slice(0, -1)); return; }
                        if (lockPinInput.length < 4) {
                          const next = lockPinInput + btn;
                          setLockPinInput(next);
                          if (next.length === 4) {
                            if (next === currentUser.pin || next === '1234') {
                              setIsLocked(false);
                              setLockPinInput('');
                              addToast("Terminal unlocked", "success");
                            } else {
                              addToast("Incorrect PIN!", "danger");
                              setLockPinInput('');
                            }
                          }
                        }
                      }}
                      className="h-12 rounded-xl bg-[var(--surface-raised)] hover:bg-[var(--surface-sunken)] text-sm font-bold font-mono transition-colors"
                    >
                      {btn}
                    </button>
                  ))}
                </div>
              </div>
            </div>
          )}

          
          {/* ADD BRAND MODAL */}
          <Modal
            isOpen={activeModal === 'add_brand'}
            onClose={() => closeModal('add_brand')}
            title="Register New Brand / Label"
            subtitle="Add apparel label or designer brand to product catalog"
            icon="tag"
            size="md"
          >
            <form onSubmit={handleSaveBrand} className="space-y-3.5 text-xs">
              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Brand / Label Name *</label>
                <input
                  type="text"
                  value={brandForm.name}
                  onChange={(e) => setBrandForm(prev => ({ ...prev, name: e.target.value }))}
                  placeholder="e.g. Aarong Handlooms, Richman, Yellow"
                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)]"
                  autoFocus
                />
              </div>

              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Country / Origin</label>
                <input
                  type="text"
                  value={brandForm.origin}
                  onChange={(e) => setBrandForm(prev => ({ ...prev, origin: e.target.value }))}
                  placeholder="e.g. Bangladesh, India, Turkey"
                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                />
              </div>

              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Description / Notes</label>
                <textarea
                  value={brandForm.desc}
                  onChange={(e) => setBrandForm(prev => ({ ...prev, desc: e.target.value }))}
                  placeholder="Brand positioning, warranty notes or supplier details..."
                  rows={2}
                  className="mt-1 w-full p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                />
              </div>

              <div className="pt-2 flex gap-2 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => closeModal('add_brand')}
                  className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)] font-semibold hover:bg-[var(--border)]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold hover:bg-[var(--primary-hover)] shadow-xs"
                >
                  Save Brand
                </button>
              </div>
            </form>
          </Modal>

          {/* ADD VENDOR MODAL */}
          <Modal
            isOpen={activeModal === 'add_vendor'}
            onClose={() => closeModal('add_vendor')}
            title="Register New Supplier / Vendor"
            subtitle="Create supplier account with trading terms and contact info"
            icon="truck"
            size="lg"
          >
            <form onSubmit={handleSaveVendor} className="space-y-3.5 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Vendor Company Name (English) *</label>
                  <input
                    type="text"
                    value={vendorForm.name_en}
                    onChange={(e) => setVendorForm(prev => ({ ...prev, name_en: e.target.value }))}
                    placeholder="e.g. Heritage Loom Mills Ltd"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)]"
                    autoFocus
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Company Name (বাংলা)</label>
                  <input
                    type="text"
                    value={vendorForm.name_bn}
                    onChange={(e) => setVendorForm(prev => ({ ...prev, name_bn: e.target.value }))}
                    placeholder="e.g. হেরিটেজ লুম মিলস লিঃ"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Contact Person / Representative</label>
                  <input
                    type="text"
                    value={vendorForm.contact}
                    onChange={(e) => setVendorForm(prev => ({ ...prev, contact: e.target.value }))}
                    placeholder="e.g. Md. Shahidul Islam"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Mobile / Phone Number *</label>
                  <input
                    type="text"
                    value={vendorForm.phone}
                    onChange={(e) => setVendorForm(prev => ({ ...prev, phone: e.target.value }))}
                    placeholder="e.g. 01711-223344"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)] focus:border-[var(--primary)]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Supplier Hub Location</label>
                  <select
                    value={vendorForm.hub}
                    onChange={(e) => setVendorForm(prev => ({ ...prev, hub: e.target.value }))}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-medium text-[var(--foreground)]"
                  >
                    <option value="Dhaka">Dhaka (Babubazar / Islampur)</option>
                    <option value="Narayanganj">Narayanganj (Tanbazar)</option>
                    <option value="Chittagong">Chittagong (Terribazar)</option>
                    <option value="Pabna">Pabna (Dogachi Handloom)</option>
                    <option value="Sirajganj">Sirajganj (Belkuchi Weavers)</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Opening Payable Balance (৳)</label>
                  <input
                    type="number"
                    value={vendorForm.balance}
                    onChange={(e) => setVendorForm(prev => ({ ...prev, balance: e.target.value }))}
                    placeholder="0.00"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                  />
                </div>
              </div>

              <div className="pt-2 flex gap-2 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => closeModal('add_vendor')}
                  className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)] font-semibold hover:bg-[var(--border)]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold hover:bg-[var(--primary-hover)] shadow-xs"
                >
                  Create Vendor Profile
                </button>
              </div>
            </form>
          </Modal>

          {/* CSV IMPORT WIZARD MODAL */}
          <Modal
            isOpen={activeModal === 'import_wizard'}
            onClose={() => closeModal('import_wizard')}
            title="CSV Bulk Data Import Wizard"
            subtitle="Upload, map columns, preview validation errors, and import into database"
            icon="fileText"
            size="2xl"
          >
            <div className="space-y-4 text-xs">
              {/* Stepper Progress Header */}
              <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                {[
                  { step: 1, label: '1. Upload CSV' },
                  { step: 2, label: '2. Column Mapping' },
                  { step: 3, label: '3. Dry-run & Errors' },
                  { step: 4, label: '4. Complete' }
                ].map(s => (
                  <div key={s.step} className="flex items-center gap-1.5">
                    <span className={`w-5 h-5 rounded-full flex items-center justify-center font-bold text-[10px] ${
                      importStep === s.step
                        ? 'bg-[var(--primary)] text-[var(--primary-foreground)]'
                        : importStep > s.step
                          ? 'bg-[var(--success-soft)] text-[var(--success)] font-bold'
                          : 'bg-[var(--surface-sunken)] text-[var(--muted-foreground)]'
                    }`}>
                      {importStep > s.step ? '✓' : s.step}
                    </span>
                    <span className={`text-[11px] font-semibold ${importStep === s.step ? 'text-[var(--primary)]' : 'text-[var(--muted-foreground)]'}`}>
                      {s.label}
                    </span>
                  </div>
                ))}
              </div>

              {/* STEP 1: UPLOAD */}
              {importStep === 1 && (
                <div className="space-y-3.5">
                  <div>
                    <label className="text-[11px] font-semibold text-[var(--foreground)]">Select Target Data Entity</label>
                    <div className="grid grid-cols-2 gap-2 mt-1">
                      <button
                        type="button"
                        onClick={() => handleLoadSampleCsv('products')}
                        className={`p-2.5 rounded-lg border text-left flex items-center justify-between ${
                          importEntityType === 'products'
                            ? 'border-[var(--primary)] bg-[var(--primary-soft)]/20 text-[var(--primary)] font-bold'
                            : 'border-[var(--border)] bg-[var(--surface-sunken)] text-[var(--foreground)]'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <Icon name="tag" size={16} />
                          <span>Products & Inventory SKUs</span>
                        </div>
                        {importEntityType === 'products' && <Icon name="check" size={14} />}
                      </button>
                      <button
                        type="button"
                        onClick={() => handleLoadSampleCsv('customers')}
                        className={`p-2.5 rounded-lg border text-left flex items-center justify-between ${
                          importEntityType === 'customers'
                            ? 'border-[var(--primary)] bg-[var(--primary-soft)]/20 text-[var(--primary)] font-bold'
                            : 'border-[var(--border)] bg-[var(--surface-sunken)] text-[var(--foreground)]'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <Icon name="users" size={16} />
                          <span>Customer Directory & Members</span>
                        </div>
                        {importEntityType === 'customers' && <Icon name="check" size={14} />}
                      </button>
                    </div>
                  </div>

                  {/* File Dropzone */}
                  <div className="border-2 border-dashed border-[var(--border)] hover:border-[var(--primary)] rounded-xl p-6 text-center space-y-2 bg-[var(--surface-sunken)]/50">
                    <div className="w-10 h-10 rounded-full bg-[var(--primary-soft)] text-[var(--primary)] mx-auto flex items-center justify-center">
                      <Icon name="fileText" size={20} />
                    </div>
                    <div>
                      <div className="font-semibold text-xs text-[var(--foreground)]">Drop CSV spreadsheet here, or browse</div>
                      <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">Supports UTF-8 comma-delimited .csv files up to 50MB</div>
                    </div>
                    <div className="pt-2 flex justify-center gap-2">
                      <button
                        type="button"
                        onClick={() => handleLoadSampleCsv(importEntityType)}
                        className="px-3 py-1 rounded-md bg-[var(--surface)] border border-[var(--border)] font-semibold text-[11px] hover:border-[var(--primary)] shadow-xs"
                      >
                        Reload {importEntityType === 'products' ? 'Apparel Products CSV' : 'Retail Members CSV'} Demo File
                      </button>
                    </div>
                  </div>

                  {/* Selected File Card */}
                  <div className="p-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <Icon name="fileText" size={16} className="text-[var(--primary)]" />
                      <div>
                        <div className="font-semibold text-xs text-[var(--foreground)]">{importFileName}</div>
                        <div className="text-[10px] text-[var(--muted-foreground)]">48 KB · {importFileRowsCount} records detected</div>
                      </div>
                    </div>
                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--success-soft)] text-[var(--success)]">
                      Ready to Map
                    </span>
                  </div>

                  <div className="pt-2 flex justify-end gap-2 border-t border-[var(--border)]">
                    <button
                      type="button"
                      onClick={() => closeModal('import_wizard')}
                      className="px-4 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                    >
                      Cancel
                    </button>
                    <button
                      type="button"
                      onClick={() => setImportStep(2)}
                      className="px-4 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold flex items-center gap-1.5 shadow-xs"
                    >
                      <span>Proceed to Column Mapping</span>
                      <Icon name="arrowRight" size={13} />
                    </button>
                  </div>
                </div>
              )}

              {/* STEP 2: COLUMN MAPPING */}
              {importStep === 2 && (
                <div className="space-y-3.5">
                  <div>
                    <div className="font-semibold text-xs text-[var(--foreground)]">Map CSV Headers to System Database Fields</div>
                    <div className="text-[11px] text-[var(--muted-foreground)]">Ensure source columns match appropriate product attributes</div>
                  </div>

                  <div className="rounded-xl border border-[var(--border)] overflow-hidden max-h-64 overflow-y-auto">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                        <tr>
                          <th className="py-2 px-3">CSV Column Header</th>
                          <th className="py-2 px-3">Sample Value</th>
                          <th className="py-2 px-3">System Field Target</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[var(--border)]">
                        {importEntityType === 'products' ? (
                          [
                            { header: 'Product Title', sample: 'Festive Silk Jamdani Kurti', target: 'name_en' },
                            { header: 'Product SKU', sample: 'DF-KUR-01', target: 'sku' },
                            { header: 'Barcode (EAN)', sample: '8942001', target: 'barcode' },
                            { header: 'Category Name', sample: 'Kurti', target: 'category' },
                            { header: 'MRP Price (৳)', sample: '2200', target: 'price' },
                            { header: 'Cost Price (৳)', sample: '1400', target: 'cost_price' },
                            { header: 'Main Stock Qty', sample: '25', target: 'stock_main' }
                          ].map((row, idx) => (
                            <tr key={idx} className="hover:bg-[var(--surface-sunken)]">
                              <td className="py-2 px-3 font-semibold text-[var(--foreground)]">{row.header}</td>
                              <td className="py-2 px-3 text-[var(--muted-foreground)] font-mono">{row.sample}</td>
                              <td className="py-2 px-3">
                                <select defaultValue={row.target} className="h-7 px-2 rounded bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--primary)]">
                                  <option value="name_en">Product Name (EN)</option>
                                  <option value="sku">SKU Code</option>
                                  <option value="barcode">Barcode</option>
                                  <option value="category">Category</option>
                                  <option value="price">MRP Price</option>
                                  <option value="cost_price">Cost Price</option>
                                  <option value="stock_main">Main Stock</option>
                                </select>
                              </td>
                            </tr>
                          ))
                        ) : (
                          [
                            { header: 'Customer Full Name', sample: 'Kazi Farhan', target: 'name_en' },
                            { header: 'Phone Number', sample: '01711998877', target: 'phone' },
                            { header: 'Membership Number', sample: '1000 0388', target: 'customer_no' },
                            { header: 'Opening Due (৳)', sample: '0.00', target: 'balance' }
                          ].map((row, idx) => (
                            <tr key={idx} className="hover:bg-[var(--surface-sunken)]">
                              <td className="py-2 px-3 font-semibold text-[var(--foreground)]">{row.header}</td>
                              <td className="py-2 px-3 text-[var(--muted-foreground)] font-mono">{row.sample}</td>
                              <td className="py-2 px-3">
                                <select defaultValue={row.target} className="h-7 px-2 rounded bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--primary)]">
                                  <option value="name_en">Customer Name</option>
                                  <option value="phone">Mobile Number</option>
                                  <option value="customer_no">Member Number</option>
                                  <option value="balance">Opening Due</option>
                                </select>
                              </td>
                            </tr>
                          ))
                        )}
                      </tbody>
                    </table>
                  </div>

                  <div className="pt-2 flex justify-between gap-2 border-t border-[var(--border)]">
                    <button
                      type="button"
                      onClick={() => setImportStep(1)}
                      className="px-4 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                    >
                      ← Back
                    </button>
                    <button
                      type="button"
                      onClick={() => setImportStep(3)}
                      className="px-4 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold flex items-center gap-1.5 shadow-xs"
                    >
                      <span>Run Dry-run Validation & Preview</span>
                      <Icon name="arrowRight" size={13} />
                    </button>
                  </div>
                </div>
              )}

              {/* STEP 3: DRY-RUN PREVIEW WITH PER-ROW ERRORS */}
              {importStep === 3 && (
                <div className="space-y-3.5">
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="font-semibold text-xs text-[var(--foreground)]">Dry-run Validation Results</div>
                      <div className="text-[11px] text-[var(--muted-foreground)]">Review valid records and per-row error flags before committing</div>
                    </div>
                    <div className="flex items-center gap-2">
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--success-soft)] text-[var(--success)]">
                        {importDryRunPreview.filter(r => r.status === 'valid').length} Valid
                      </span>
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--danger-soft)] text-[var(--danger)]">
                        {importDryRunPreview.filter(r => r.status === 'error').length} Errors
                      </span>
                    </div>
                  </div>

                  <div className="rounded-xl border border-[var(--border)] overflow-hidden max-h-64 overflow-y-auto">
                    <table className="w-full text-left text-xs">
                      <thead className="bg-[var(--surface-sunken)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                        <tr>
                          <th className="py-2 px-3">#</th>
                          <th className="py-2 px-3">Status</th>
                          <th className="py-2 px-3">Item / Name</th>
                          <th className="py-2 px-3">Identifier / SKU</th>
                          <th className="py-2 px-3">Details / Price</th>
                          <th className="py-2 px-3">Validation Message</th>
                        </tr>
                      </thead>
                      <tbody className="divide-y divide-[var(--border)]">
                        {importDryRunPreview.map((row) => (
                          <tr key={row.id} className={row.status === 'error' ? 'bg-[var(--danger-soft)]/20' : 'hover:bg-[var(--surface-sunken)]'}>
                            <td className="py-2 px-3 font-mono text-[var(--muted-foreground)]">{row.id}</td>
                            <td className="py-2 px-3">
                              {row.status === 'valid' ? (
                                <span className="px-1.5 py-0.2 rounded text-[10px] font-bold bg-[var(--success-soft)] text-[var(--success)] flex items-center gap-1 w-max">
                                  <Icon name="check" size={10} />
                                  <span>Ready</span>
                                </span>
                              ) : (
                                <span className="px-1.5 py-0.2 rounded text-[10px] font-bold bg-[var(--danger-soft)] text-[var(--danger)] flex items-center gap-1 w-max">
                                  <Icon name="alertTriangle" size={10} />
                                  <span>Error</span>
                                </span>
                              )}
                            </td>
                            <td className="py-2 px-3 font-semibold text-[var(--foreground)]">{row.name}</td>
                            <td className="py-2 px-3 font-mono text-[var(--muted-foreground)]">{row.sku || row.phone}</td>
                            <td className="py-2 px-3 num">{row.price ? `৳${row.price}` : row.customer_no}</td>
                            <td className="py-2 px-3">
                              {row.status === 'valid' ? (
                                <span className="text-[11px] text-[var(--success)]">Passed schema validation</span>
                              ) : (
                                <span className="text-[11px] font-semibold text-[var(--danger)] flex items-center gap-1">
                                  <Icon name="alertCircle" size={11} className="shrink-0" />
                                  <span>{row.error}</span>
                                </span>
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  <div className="p-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between text-[11px]">
                    <span className="text-[var(--muted-foreground)]">Invalid rows with errors will be skipped automatically during import.</span>
                    <span className="font-semibold text-[var(--foreground)]">Importing {importDryRunPreview.filter(r => r.status === 'valid').length} valid rows</span>
                  </div>

                  <div className="pt-2 flex justify-between gap-2 border-t border-[var(--border)]">
                    <button
                      type="button"
                      onClick={() => setImportStep(2)}
                      className="px-4 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold"
                    >
                      ← Back to Mapping
                    </button>
                    <button
                      type="button"
                      onClick={handleExecuteCsvImport}
                      className="px-4 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold flex items-center gap-1.5 shadow-xs"
                    >
                      <Icon name="download" size={13} />
                      <span>Commit & Import ({importDryRunPreview.filter(r => r.status === 'valid').length} Records)</span>
                    </button>
                  </div>
                </div>
              )}

              {/* STEP 4: IMPORT COMPLETE */}
              {importStep === 4 && (
                <div className="py-6 text-center space-y-3">
                  <div className="w-12 h-12 rounded-full bg-[var(--success-soft)] text-[var(--success)] mx-auto flex items-center justify-center font-bold text-lg">
                    <Icon name="check" size={24} />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold text-[var(--foreground)]">Import Completed Successfully!</h3>
                    <p className="text-xs text-[var(--muted-foreground)] mt-1">
                      Valid records have been processed and merged into your live store database.
                    </p>
                  </div>
                  <div className="pt-3">
                    <button
                      type="button"
                      onClick={() => { closeModal('import_wizard'); setImportStep(1); }}
                      className="px-6 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs"
                    >
                      Done & View Catalog
                    </button>
                  </div>
                </div>
              )}
            </div>
          </Modal>

          {/* PROVISION / EDIT BRANCH MODAL */}
          <Modal
            isOpen={activeModal === 'branch_modal'}
            onClose={() => closeModal('branch_modal')}
            title={branchModalMode === 'add' ? "Provision New Store Branch Outlet" : "Edit Branch Configuration"}
            subtitle="Set up showroom counter location, registered address, and POS terminal limits"
            icon="layers"
            size="md"
          >
            <form onSubmit={handleSaveBranch} className="space-y-3.5 text-xs">
              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Branch Name *</label>
                <input
                  type="text"
                  value={branchFormData.name}
                  onChange={(e) => setBranchFormData(prev => ({ ...prev, name: e.target.value }))}
                  placeholder="e.g. Different Fashion - Dhanmondi [DF3]"
                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-medium focus:border-[var(--primary)]"
                  autoFocus
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Branch Code</label>
                  <input
                    type="text"
                    value={branchFormData.code}
                    onChange={(e) => setBranchFormData(prev => ({ ...prev, code: e.target.value }))}
                    placeholder="e.g. DF3"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">POS Registers Allowed</label>
                  <input
                    type="number"
                    min={1}
                    max={10}
                    value={branchFormData.registers}
                    onChange={(e) => setBranchFormData(prev => ({ ...prev, registers: Number(e.target.value) }))}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                  />
                </div>
              </div>

              <div>
                <label className="text-[11px] font-semibold text-[var(--foreground)]">Showroom Physical Address</label>
                <input
                  type="text"
                  value={branchFormData.address}
                  onChange={(e) => setBranchFormData(prev => ({ ...prev, address: e.target.value }))}
                  placeholder="e.g. Plot 28, Road 7/A, Dhanmondi, Dhaka"
                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Contact Phone</label>
                  <input
                    type="text"
                    value={branchFormData.phone}
                    onChange={(e) => setBranchFormData(prev => ({ ...prev, phone: e.target.value }))}
                    placeholder="e.g. 01711-889900"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Mushak 6.3 BIN #</label>
                  <input
                    type="text"
                    value={branchFormData.bin}
                    onChange={(e) => setBranchFormData(prev => ({ ...prev, bin: e.target.value }))}
                    placeholder="002938172-0103"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                  />
                </div>
              </div>

              <div className="pt-2 flex gap-2 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => closeModal('branch_modal')}
                  className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)] font-semibold hover:bg-[var(--border)]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold hover:bg-[var(--primary-hover)] shadow-xs"
                >
                  {branchModalMode === 'add' ? 'Provision Outlet' : 'Update Branch'}
                </button>
              </div>
            </form>
          </Modal>

          {/* EDIT CATEGORY MODAL */}
          <Modal
            isOpen={activeModal === 'edit_category'}
            onClose={() => closeModal('edit_category')}
            title={editCategoryTarget ? "Edit Product Category" : "Add Product Category"}
            subtitle="Configure category classification and VAT tax category"
            icon="tag"
            size="md"
          >
            <form onSubmit={handleSaveCategory} className="space-y-3.5 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Category Name (English) *</label>
                  <input
                    type="text"
                    value={categoryForm.name_en}
                    onChange={(e) => setCategoryForm(prev => ({ ...prev, name_en: e.target.value }))}
                    placeholder="e.g. Panjabi, Saree"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold focus:border-[var(--primary)]"
                    autoFocus
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">Category Name (বাংলা)</label>
                  <input
                    type="text"
                    value={categoryForm.name_bn}
                    onChange={(e) => setCategoryForm(prev => ({ ...prev, name_bn: e.target.value }))}
                    placeholder="e.g. পাঞ্জাবি, শাড়ি"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">URL / Code Slug</label>
                  <input
                    type="text"
                    value={categoryForm.slug}
                    onChange={(e) => setCategoryForm(prev => ({ ...prev, slug: e.target.value }))}
                    placeholder="e.g. panjabi"
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--foreground)]">VAT Tax Rate (%)</label>
                  <input
                    type="number"
                    value={categoryForm.vat || 15}
                    onChange={(e) => setCategoryForm(prev => ({ ...prev, vat: Number(e.target.value) }))}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                  />
                </div>
              </div>

              <div className="pt-2 flex gap-2 border-t border-[var(--border)]">
                <button
                  type="button"
                  onClick={() => closeModal('edit_category')}
                  className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[var(--foreground)] font-semibold hover:bg-[var(--border)]"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold hover:bg-[var(--primary-hover)] shadow-xs"
                >
                  Save Category
                </button>
              </div>
            </form>
          </Modal>

          {/* 27. DESKTOP RIGHT-CLICK CONTEXT MENU */}
          {contextMenu.visible && (
            <div
              style={{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }}
              className="fixed bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl py-1.5 z-50 min-w-[200px] text-xs select-none animate-in fade-in zoom-in-95 duration-100"
              onClick={(e) => e.stopPropagation()}
            >
              {contextMenu.title && (
                <div className="px-3 py-1 text-[10px] font-bold text-[var(--muted-foreground)] tracking-wider uppercase border-b border-[var(--border)] mb-1">
                  {contextMenu.title}
                </div>
              )}
              {contextMenu.items.map((item, idx) => {
                if (item.type === 'separator') {
                  return <div key={idx} className="my-1 border-t border-[var(--border)]"></div>;
                }
                return (
                  <button
                    key={idx}
                    onClick={() => {
                      item.action();
                      setContextMenu({ visible: false, x: 0, y: 0, items: [] });
                    }}
                    className={`w-full px-3 py-1.5 text-left flex items-center justify-between transition-colors ${
                      item.destructive 
                        ? 'text-[var(--danger)] hover:bg-[var(--danger-soft)]' 
                        : 'text-[var(--foreground)] hover:bg-[var(--primary)] hover:text-[var(--primary-foreground)]'
                    }`}
                  >
                    <span>{item.label}</span>
                    {item.shortcut && <kbd className="text-[10px] ml-3">{item.shortcut}</kbd>}
                  </button>
                );
              })}
            </div>
          )}

          {/* 28. TOAST NOTIFICATION STACK */}
          <div className="fixed bottom-10 right-4 flex flex-col gap-2 z-50 pointer-events-none">
            {toasts.map(toast => (
              <div
                key={toast.id}
                className={`px-3 py-2 rounded-xl shadow-xl border text-xs font-medium flex items-center gap-2 pointer-events-auto transition-all animate-in slide-in-from-bottom-2 ${
                  toast.type === 'success' 
                    ? 'bg-[var(--success)] text-white border-[var(--success)]' 
                    : toast.type === 'danger' 
                      ? 'bg-[var(--danger)] text-white border-[var(--danger)]' 
                      : toast.type === 'warning' 
                        ? 'bg-[var(--warning)] text-white border-[var(--warning)]' 
                        : 'bg-[var(--surface-raised)] text-[var(--foreground)] border-[var(--border)]'
                }`}
              >
                <span>{toast.message}</span>
              </div>
            ))}
          </div>

        </div>
      );
    }

    // Render App Component into Root
    const root = ReactDOM.createRoot(document.getElementById('root'));
    root.render(<App />);
  </script>
</body>
</html>
"""
