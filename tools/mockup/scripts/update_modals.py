# scripts/update_modals.py
import re

with open('scripts/parts/render_all_modals.py', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Update Payment Modal:
# We look for "{showPaymentModal && (" up to "{/* 5. 80MM THERMAL RECEIPT MODAL"
old_payment_regex = r"\{/\* 4\. PAYMENT MODAL \(F10\) \*/\}[\s\S]*?(?=\{/\* 5\. 80MM THERMAL RECEIPT MODAL)"

new_payment_code = """{/* 4. PAYMENT MODAL (F10) */}
          {showPaymentModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowPaymentModal(false)}></div>
              <div className="relative z-50 w-full max-w-2xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl overflow-hidden flex flex-col">
                <div className="p-3.5 border-b border-[var(--border)] bg-[var(--surface-sunken)] flex items-center justify-between">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="creditCard" size={16} />
                    <span>Payment Tender & Due Settlement (F10)</span>
                  </div>
                  <button onClick={() => setShowPaymentModal(false)}><Icon name="x" size={16} /></button>
                </div>

                <div className="p-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Left: Quick Presets & Tender Inputs */}
                  <div className="space-y-2 text-xs">
                    {/* Quick Payment Presets */}
                    <div className="p-2.5 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-1.5">
                      <div className="flex items-center justify-between">
                        <span className="text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider">Quick Presets</span>
                        <span className="text-[10px] text-[var(--muted-foreground)]">Payable: <strong className="font-mono text-[var(--foreground)]">{formatMoney(totalPayable)}</strong></span>
                      </div>
                      <div className="grid grid-cols-3 gap-1.5">
                        <button
                          type="button"
                          onClick={() => setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 })}
                          className={`py-1.5 px-2 rounded-lg text-xs font-bold transition-all border ${
                            totalTendered >= totalPayable && totalTendered > 0
                              ? 'bg-emerald-600 text-white border-emerald-600 shadow-xs'
                              : 'bg-[var(--surface)] border-[var(--border)] hover:border-emerald-500 text-[var(--foreground)]'
                          }`}
                        >
                          Full Pay
                        </button>
                        <button
                          type="button"
                          onClick={() => setTenderTally({ cash: Math.round(totalPayable / 2), card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 })}
                          className={`py-1.5 px-2 rounded-lg text-xs font-bold transition-all border ${
                            totalTendered === Math.round(totalPayable / 2) && totalTendered > 0
                              ? 'bg-amber-600 text-white border-amber-600 shadow-xs'
                              : 'bg-[var(--surface)] border-[var(--border)] hover:border-amber-500 text-[var(--foreground)]'
                          }`}
                        >
                          50% Partial
                        </button>
                        <button
                          type="button"
                          onClick={() => setTenderTally({ cash: 0, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 })}
                          className={`py-1.5 px-2 rounded-lg text-xs font-bold transition-all border ${
                            totalTendered === 0
                              ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
                              : 'bg-[var(--surface)] border-[var(--border)] hover:border-rose-500 text-[var(--foreground)]'
                          }`}
                        >
                          Full Due (৳0)
                        </button>
                      </div>
                    </div>

                    <div className="font-bold text-xs text-[var(--muted-foreground)] pt-1">Tender Breakdown</div>
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

                    {/* Quick Cash Increments */}
                    <div className="pt-1.5 flex items-center gap-1">
                      {[100, 500, 1000].map(amt => (
                        <button
                          key={amt}
                          type="button"
                          onClick={() => setTenderTally(prev => ({ ...prev, cash: (Number(prev.cash) || 0) + amt }))}
                          className="flex-1 py-1 rounded bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-[10px] font-semibold hover:border-[var(--primary)] text-[var(--foreground)]"
                        >
                          +{amt}
                        </button>
                      ))}
                      <button
                        type="button"
                        onClick={() => setTenderTally({ cash: 0, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 })}
                        className="px-2.5 py-1 rounded bg-rose-500/10 text-rose-600 hover:bg-rose-500/20 font-mono text-[10px] font-semibold"
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
                        <span className="font-mono font-bold text-[var(--foreground)] text-sm">{formatMoney(totalPayable)}</span>
                      </div>
                      <div className="flex justify-between text-[var(--muted-foreground)]">
                        <span>Total Tendered:</span>
                        <span className="font-mono font-bold text-emerald-600 text-sm">{formatMoney(totalTendered)}</span>
                      </div>

                      {/* DUE / PARTIAL PAYMENT NOTICE */}
                      {tenderDue > 0 ? (
                        <div className="p-3 rounded-xl bg-amber-500/15 border-2 border-amber-500/40 text-amber-900 dark:text-amber-200 space-y-2">
                          <div className="flex items-center justify-between font-bold text-xs">
                            <span className="flex items-center gap-1.5">
                              <Icon name="alertTriangle" size={15} className="text-amber-600" />
                              <span>Paid: {formatMoney(totalTendered)}</span>
                            </span>
                            <span className="text-sm font-mono font-black text-amber-700 dark:text-amber-400">Due: {formatMoney(tenderDue)}</span>
                          </div>
                          {totalTendered === 0 && (
                            <div className="text-[11px] font-bold text-amber-800 dark:text-amber-300 bg-amber-500/20 px-2 py-0.5 rounded inline-block">
                              Full Credit Sale (৳0 paid — সম্পূর্ণ বাকিতে বিক্রয়)
                            </div>
                          )}

                          {/* Customer Required for Due Sale */}
                          {!attachedCustomer ? (
                            <div className="p-2.5 rounded-lg bg-rose-500/15 border border-rose-500/30 text-rose-700 dark:text-rose-300 space-y-1.5">
                              <div className="flex items-center gap-1.5 text-xs font-bold">
                                <Icon name="alertCircle" size={14} />
                                <span>Customer Required for Due Sale</span>
                              </div>
                              <p className="text-[11px] text-[var(--muted-foreground)]">Cannot record due against Walk-in Customer. Attach a registered customer or register new.</p>
                              <button
                                type="button"
                                onClick={() => { setShowPaymentModal(false); setShowCustomerAttachModal(true); }}
                                className="w-full py-1.5 px-3 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-xs"
                              >
                                <Icon name="userPlus" size={14} />
                                <span>Attach Customer to Sell on Due (F4)</span>
                              </button>
                            </div>
                          ) : (
                            /* Customer Credit Limit Bar */
                            <div className="pt-2 border-t border-amber-500/30 space-y-1.5">
                              <div className="flex items-center justify-between text-[11px]">
                                <span className="font-semibold text-[var(--foreground)]">{attachedCustomer.name_en} ({attachedCustomer.customer_no})</span>
                                <span className="font-mono text-[10px] text-[var(--muted-foreground)]">Limit: {formatMoney(attachedCustomer.credit_limit || 20000)}</span>
                              </div>
                              <div className="flex justify-between text-[10px] font-mono">
                                <span>Credit Utilization:</span>
                                <span>Used {formatMoney((attachedCustomer.credit_used || Math.abs(attachedCustomer.balance || 0)) + tenderDue)} of {formatMoney(attachedCustomer.credit_limit || 20000)}</span>
                              </div>
                              <div className="h-2 w-full bg-black/10 dark:bg-white/10 rounded-full overflow-hidden">
                                <div
                                  style={{ width: `${Math.min(100, Math.round((((attachedCustomer.credit_used || Math.abs(attachedCustomer.balance || 0)) + tenderDue) / (attachedCustomer.credit_limit || 20000)) * 100))}%` }}
                                  className={`h-full transition-all ${isCreditLimitExceeded ? 'bg-rose-500' : 'bg-amber-500'}`}
                                ></div>
                              </div>

                              {isCreditLimitExceeded && (
                                <div className="p-2 rounded-lg bg-rose-500/15 border border-rose-500/30 text-[10px] text-rose-600 font-bold flex items-center justify-between mt-1">
                                  <span>Credit Limit Exceeded! Manager PIN:</span>
                                  <input
                                    type="password"
                                    placeholder="PIN (1234)"
                                    value={dueManagerPinOverride}
                                    onChange={(e) => setDueManagerPinOverride(e.target.value)}
                                    className="w-20 h-6 px-1 rounded bg-[var(--surface)] border border-rose-400 text-center font-mono text-xs"
                                  />
                                </div>
                              )}

                              <div className="grid grid-cols-2 gap-1.5 pt-1 text-[10px]">
                                <div>
                                  <span className="text-[var(--muted-foreground)] font-semibold">Promise Date:</span>
                                  <input type="date" value={duePromiseDate} onChange={(e) => setDuePromiseDate(e.target.value)} className="w-full h-6 px-1 rounded bg-[var(--surface)] border border-[var(--border)] text-[10px]" />
                                </div>
                                <div>
                                  <span className="text-[var(--muted-foreground)] font-semibold">Customer PIN:</span>
                                  <input type="password" placeholder="••••" value={dueCustomerPin} onChange={(e) => setDueCustomerPin(e.target.value)} className="w-full h-6 px-1 rounded bg-[var(--surface)] border border-[var(--border)] text-[10px] font-mono" />
                                </div>
                              </div>
                            </div>
                          )}

                          {isOfflineMode && (
                            <div className="text-[11px] text-rose-600 font-bold pt-1 flex items-center gap-1">
                              <Icon name="wifiOff" size={13} />
                              <span>Due sales disabled in offline mode (Needs internet).</span>
                            </div>
                          )}
                        </div>
                      ) : (
                        <div className="p-3 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 space-y-1">
                          <div className="flex justify-between items-center font-bold">
                            <span>Change to Return:</span>
                            <span className="font-mono text-base text-emerald-600 dark:text-emerald-400">{formatMoney(tenderChange)}</span>
                          </div>
                          <div className="text-[10px] text-emerald-700 dark:text-emerald-400">Full payment received · No outstanding balance</div>
                        </div>
                      )}
                    </div>

                    <button
                      type="button"
                      onClick={() => completeSale('split')}
                      disabled={(tenderDue > 0 && (!attachedCustomer || isOfflineMode || (isCreditLimitExceeded && dueManagerPinOverride !== '1234' && dueManagerPinOverride !== '9999')))}
                      className="w-full h-12 rounded-xl bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold transition-all shadow-md disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
                    >
                      <Icon name="checkCircle" size={16} />
                      <span>
                        {tenderDue > 0
                          ? `Confirm Sale with ${formatMoney(tenderDue)} Due (বাকি বিক্রয় সম্পন্ন করুন)`
                          : `Complete Payment (${formatMoney(totalPayable)})`}
                      </span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}
"""

content = re.sub(old_payment_regex, new_payment_code, content)

# 2. Update Discount Modal:
old_discount_regex = r"\{/\* 8\. DISCOUNT POPOVER / MODAL \(F6 Line Disc, Shift\+F6 Order Disc\) \*/\}[\s\S]*?(?=\{/\* 9\. COLLECT DUE MODAL)"

new_discount_code = """{/* 8. DISCOUNT POPOVER / MODAL (F6 Line Disc, Shift+F6 Order Disc) */}
          {showDiscountModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowDiscountModal(false)}></div>
              <div className="relative z-50 w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-4 space-y-3 text-xs animate-in zoom-in-95 duration-100">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <div className="flex items-center gap-2 text-sm">
                    <Icon name="tag" size={15} className="text-[var(--primary)]" />
                    <span>{discountTarget === 'line' ? 'Line Item Discount (F6)' : 'Whole Order Discount (Shift+F6)'}</span>
                  </div>
                  <button onClick={() => setShowDiscountModal(false)}><Icon name="x" size={14} /></button>
                </div>

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
                      Reason for Discount {((discountTab === 'percent' && Number(discountValue) > 10) || showManagerPinInline) && <span className="text-rose-500 font-bold">* Required (&gt;10%)</span>}
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
                  <div className="p-2.5 rounded-xl bg-amber-500/15 border border-amber-500/30 text-amber-800 dark:text-amber-300 space-y-1.5 animate-in fade-in duration-200">
                    <div className="font-bold text-[11px] flex items-center justify-between">
                      <span>Manager PIN required (&gt;{currentRoleLimit}%)</span>
                      <span className="text-[10px] font-normal opacity-75">Demo PIN: 1234</span>
                    </div>
                    <input
                      type="password"
                      placeholder="Enter Manager PIN (1234 or 9999)"
                      value={discountManagerPin}
                      onChange={(e) => setDiscountManagerPin(e.target.value)}
                      className="w-full h-7 px-2 rounded bg-[var(--surface)] border border-amber-500 font-mono text-center text-xs"
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
            </div>
          )}
"""

content = re.sub(old_discount_regex, new_discount_code, content)

# 3. Update WhatsApp Reminder Modal:
old_reminder_regex = r"\{/\* 13\. WHATSAPP REMINDER MODAL \*/\}[\s\S]*?(?=\{/\* 14\. STOCK ADJUSTMENT MODAL)"

new_reminder_code = """{/* 13. WHATSAPP REMINDER MODAL */}
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
                      <div className="text-[10px] text-emerald-100 flex items-center gap-1">
                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-300 inline-block"></span>
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
                  <div className="self-end w-full bg-[#DCF8C6] dark:bg-[#005C4B] rounded-2xl rounded-tr-none p-3 shadow-md border border-emerald-200/50 dark:border-emerald-800/50">
                    <textarea
                      rows={4}
                      value={editableMsgText !== '' ? editableMsgText : `প্রিয় ${reminderCustomer.name_en}, ডিফারেন্ট ফ্যাশনে আপনার পূর্বের বকেয়া ${formatMoney(Math.abs(reminderCustomer.balance))} টাকা পরিশোধের জন্য বিনীত অনুরোধ জানানো হচ্ছে। ধন্যবাদ, ডিফারেন্ট ফ্যাশন (01711-223344)।`}
                      onChange={(e) => setEditableMsgText(e.target.value)}
                      placeholder="Type WhatsApp reminder message..."
                      className="w-full bg-transparent border-0 resize-none text-xs text-gray-900 dark:text-gray-100 font-sans focus:outline-hidden leading-relaxed"
                    />
                    <div className="flex items-center justify-end gap-1 mt-1 text-[10px] text-gray-500 dark:text-emerald-200">
                      <span>11:45 AM</span>
                      <span className="text-emerald-600 dark:text-emerald-300 font-bold">✓✓</span>
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
                    className="flex-1 h-9 px-4 rounded-lg bg-[#25D366] hover:bg-[#1EBE5D] text-slate-900 font-bold flex items-center justify-center gap-2 shadow-xs transition-all cursor-pointer"
                  >
                    <Icon name="send" size={15} />
                    <span>{msgTargetType === 'bulk' ? 'Send to All 14 Customers (বকেয়া তাগাদা পাঠান)' : 'Send WhatsApp Reminder'}</span>
                  </button>
                </div>
              </div>
            </div>
          )}
"""

content = re.sub(old_reminder_regex, new_reminder_code, content)

# 4. Update Inter-Branch Stock Transfer Modal:
old_transfer_regex = r"\{/\* 15\. INTER-BRANCH TRANSFER MODAL \*/\}[\s\S]*?(?=\{/\* 16\. STOCKTAKE COUNT MODAL)"

new_transfer_code = """{/* 15. INTER-BRANCH TRANSFER MODAL */}
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
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">From Branch</label>
                    <select
                      value={transferFrom}
                      onChange={(e) => setTransferFrom(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                    >
                      <option>Different Fashion - Main [DF1]</option>
                      <option>Different Fashion - 2 [DF2]</option>
                    </select>
                  </div>
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">To Branch</label>
                    <select
                      value={transferTo}
                      onChange={(e) => setTransferTo(e.target.value)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                    >
                      <option>Different Fashion - 2 [DF2]</option>
                      <option>Different Fashion - Main [DF1]</option>
                    </select>
                  </div>
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Transfer Quantity (pcs)</label>
                  <input
                    type="number"
                    value={transferQty}
                    onChange={(e) => setTransferQty(Number(e.target.value) || 1)}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono font-bold text-xs text-[var(--foreground)]"
                  />
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
                  <button type="button" onClick={() => setShowStockTransferModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]">Cancel</button>
                  <button
                    type="button"
                    onClick={() => {
                      addToast(`Gate Pass #TR-9921 created: ${transferQty} pcs from ${transferFrom.split('[')[1]?.replace(']','') || 'DF1'} to ${transferTo.split('[')[1]?.replace(']','') || 'DF2'}`, "success");
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
"""

content = re.sub(old_transfer_regex, new_transfer_code, content)

# 5. Append New Modals (Customer Card Modal, Add Category Modal, New Tenant Modal, Bulk Barcode Modal)
# right before "{/* 26. CONTEXT MENU OVERLAY */}"
new_extra_modals = """
          {/* 25A. CUSTOMER MEMBERSHIP CARD MODAL (CR80 Standard with Isolated Print) */}
          {showCustomerCardModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowCustomerCardModal(false)}></div>
              <div className="relative z-50 w-full max-w-2xl bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl overflow-hidden flex flex-col text-xs">
                {/* Header */}
                <div className="p-3.5 border-b border-[var(--border)] bg-[var(--surface-sunken)] flex items-center justify-between">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="creditCard" size={16} className="text-[var(--primary)]" />
                    <span>Customer Membership Card — CR80 Standard</span>
                  </div>
                  <button onClick={() => setShowCustomerCardModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">
                    <Icon name="x" size={16} />
                  </button>
                </div>

                {/* Printable Isolated Card Body */}
                <div className="p-6 bg-[var(--surface-sunken)] flex flex-col items-center justify-center">
                  <div id="printable-card-area" className="printable-area w-full max-w-lg space-y-4">
                    {(() => {
                      const c = cardCustomer || selectedCustomer || attachedCustomer || MOCK_CUSTOMERS[0];
                      return (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {/* FRONT OF CARD */}
                          <div className="w-full aspect-[85.6/54] rounded-2xl p-4 flex flex-col justify-between text-white shadow-xl relative overflow-hidden bg-gradient-to-br from-teal-900 via-emerald-800 to-slate-950 border border-teal-500/30">
                            {/* Watermark / Pattern */}
                            <div className="absolute -right-6 -bottom-6 w-32 h-32 rounded-full border border-teal-400/10 pointer-events-none"></div>
                            <div className="absolute right-6 -top-6 w-24 h-24 rounded-full border border-teal-400/10 pointer-events-none"></div>

                            {/* Card Top */}
                            <div className="flex items-start justify-between relative z-10">
                              <div>
                                <div className="font-extrabold text-xs tracking-widest uppercase">DIFFERENT FASHION</div>
                                <div className="text-[9px] text-teal-200 tracking-wider font-semibold">PREMIUM PRIVILEGE CLUB</div>
                              </div>
                              <div className="px-2 py-0.5 rounded-full bg-amber-400 text-slate-950 font-black text-[9px] tracking-wider uppercase shadow-xs">
                                {c.group || 'VIP GOLD'}
                              </div>
                            </div>

                            {/* Chip & Contactless */}
                            <div className="flex items-center gap-3 relative z-10 my-1">
                              <div className="w-8 h-6 rounded bg-gradient-to-br from-amber-300 to-yellow-600 border border-yellow-200/50 flex items-center justify-center shadow-inner">
                                <div className="w-5 h-3.5 border border-yellow-800/40 rounded-xs"></div>
                              </div>
                              <Icon name="wifi" size={14} className="text-teal-200 rotate-90" />
                            </div>

                            {/* Card Bottom Details */}
                            <div className="relative z-10 space-y-1">
                              <div className="font-mono text-sm tracking-widest text-teal-100 font-bold drop-shadow-xs">
                                {c.customer_no || '1000 0017'}
                              </div>
                              <div className="flex justify-between items-end">
                                <div>
                                  <div className="font-bold text-xs text-white truncate max-w-[140px]">{c.name_en}</div>
                                  <div className="text-[9px] text-teal-300">{c.name_bn || 'সম্মানিত গ্রাহক'}</div>
                                </div>
                                <div className="text-right">
                                  <div className="text-[8px] text-teal-300 font-mono uppercase">EXP: 04/29</div>
                                  <div className="text-[9px] font-mono text-white font-semibold">BIN 002847192</div>
                                </div>
                              </div>
                            </div>
                          </div>

                          {/* BACK OF CARD */}
                          <div className="w-full aspect-[85.6/54] rounded-2xl flex flex-col justify-between text-slate-800 shadow-xl relative overflow-hidden bg-gradient-to-b from-slate-100 to-slate-200 border border-slate-300">
                            {/* Magnetic Stripe */}
                            <div className="w-full h-8 bg-slate-900 mt-3"></div>

                            {/* Signature Panel */}
                            <div className="px-4 py-1">
                              <div className="flex items-center justify-between text-[8px] text-slate-500 font-semibold mb-0.5">
                                <span>AUTHORIZED SIGNATURE</span>
                                <span>NOT TRANSFERABLE</span>
                              </div>
                              <div className="h-6 w-full bg-white border border-slate-300 rounded flex items-center px-2 font-serif italic text-xs text-slate-700">
                                {c.name_en}
                              </div>
                            </div>

                            {/* Barcode & Terms */}
                            <div className="px-4 pb-3 flex items-center justify-between">
                              <div className="text-[7.5px] text-slate-500 max-w-[130px] leading-tight">
                                This card is property of Different Fashion. Valid at all branches in Bangladesh. Helpline: +880 1711-223344
                              </div>
                              <div className="text-center">
                                <div className="h-6 w-24 flex items-center justify-between mx-auto">
                                  {[2,1,3,1,1,2,1,3,2,1,2,1,3,1].map((w, i) => (
                                    <span key={i} style={{ width: `${w}px` }} className="h-full bg-black"></span>
                                  ))}
                                </div>
                                <span className="font-mono text-[7px] text-slate-700 block">MC{c.customer_no?.replace(/\s+/g, '') || '10000017'}01</span>
                              </div>
                            </div>
                          </div>
                        </div>
                      );
                    })()}
                  </div>
                </div>

                {/* Modal Footer Controls */}
                <div className="p-3.5 border-t border-[var(--border)] bg-[var(--surface)] flex items-center justify-between gap-3">
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
                      <span>Print Customer Card (শুধু কার্ড প্রিন্ট করুন)</span>
                    </button>
                  </div>
                </div>
              </div>
            </div>
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
                  <button onClick={() => setShowAddCategoryModal(false)}><Icon name="x" size={14} /></button>
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
                    }}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                  />
                </div>
                <div>
                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Category Name (Bengali) *</label>
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
                  <button onClick={() => setShowAddCategoryModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold">Cancel</button>
                  <button
                    onClick={() => {
                      if (!newCategoryNameEn.trim()) {
                        addToast("Please enter category name", "warning");
                        return;
                      }
                      const cat = {
                        id: newCategorySlug || 'cat_' + Date.now(),
                        name_en: newCategoryNameEn,
                        name_bn: newCategoryNameBn || newCategoryNameEn,
                        sku_count: 0
                      };
                      setCategoriesList(prev => [...prev, cat]);
                      CATEGORIES.push(cat);
                      addToast(`Category "${newCategoryNameEn}" added successfully!`, "success");
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
                  <button onClick={() => setShowNewTenantModal(false)}><Icon name="x" size={14} /></button>
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
                      setNewTenantDomain(e.target.value.toLowerCase().replace(/[^a-z0-9]/g, '') + '.retailpos.bd');
                    }}
                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold text-xs"
                  />
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
                      onChange={(e) => setNewTenantPhone(e.target.value)}
                      placeholder="01711-XXXXXX"
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-xs"
                    />
                  </div>
                  <div>
                    <label className="text-[10px] font-semibold text-[var(--muted-foreground)]">Initial Branch Quota</label>
                    <input
                      type="number"
                      value={newTenantBranches}
                      onChange={(e) => setNewTenantBranches(Number(e.target.value) || 1)}
                      className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono text-xs"
                    />
                  </div>
                </div>
                <div className="pt-2 flex gap-2">
                  <button onClick={() => setShowNewTenantModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-semibold">Cancel</button>
                  <button
                    onClick={() => {
                      if (!newTenantName.trim()) {
                        addToast("Please provide tenant shop name", "warning");
                        return;
                      }
                      const tenant = {
                        id: 'T-' + (100 + saasTenants.length + 1),
                        name: newTenantName,
                        code: newTenantName.slice(0, 3).toUpperCase(),
                        domain: newTenantDomain || 'shop.retailpos.bd',
                        plan: newTenantPlan,
                        branches: newTenantBranches,
                        registers: newTenantBranches * 2,
                        status: 'Active',
                        mrr: newTenantPlan.includes('12,000') ? 12000 : newTenantPlan.includes('4,500') ? 4500 : 2500,
                        joined: 'Today'
                      };
                      setSaasTenants(prev => [tenant, ...prev]);
                      addToast(`Tenant "${newTenantName}" onboarded to SaaS Platform!`, "success");
                      setNewTenantName('');
                      setNewTenantDomain('');
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
"""

target_marker = "{/* 26. CONTEXT MENU OVERLAY */}"
if target_marker in content:
    content = content.replace(target_marker, new_extra_modals + "\n          " + target_marker)
else:
    # fallback
    content = content.replace("{/* 25. KEYBOARD SHORTCUTS MODAL", new_extra_modals + "\n          {/* 25. KEYBOARD SHORTCUTS MODAL")

with open('scripts/parts/render_all_modals.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_all_modals.py updated successfully!")
