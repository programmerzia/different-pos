# scripts/update_quick_customer_modal.py

with open('scripts/parts/render_all_modals.py', 'r', encoding='utf-8') as f:
    content = f.read()

start_marker = "{/* 2. QUICK NEW CUSTOMER MODAL (Ctrl+N) */}"
end_marker = "{/* 3. CUSTOMER PIN PAD MODAL */}"

start_idx = content.find(start_marker)
end_idx = content.find(end_marker)

if start_idx == -1 or end_idx == -1:
    print(f"Error finding markers: start={start_idx}, end={end_idx}")
    exit(1)

upgraded_quick_modal = '''{/* 2. QUICK NEW CUSTOMER MODAL (Ctrl+N) */}
          {showQuickNewCustomerModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="fixed inset-0 z-40" onClick={() => setShowQuickNewCustomerModal(false)}></div>
              <div className="relative z-50 w-full max-w-md bg-[var(--surface-raised)] border border-[var(--border)] rounded-2xl shadow-2xl p-5 space-y-3.5 text-xs animate-in zoom-in-95 duration-100">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                  <div className="flex items-center gap-2 font-bold text-sm text-[var(--foreground)]">
                    <Icon name="userPlus" size={16} className="text-[var(--primary)]" />
                    <span>Quick New Customer (Ctrl+N) — দ্রুত গ্রাহক নিবন্ধন</span>
                  </div>
                  <button onClick={() => setShowQuickNewCustomerModal(false)} className="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">
                    <Icon name="x" size={16} />
                  </button>
                </div>

                <div className="space-y-2.5">
                  <div>
                    <label className="font-semibold text-[11px] text-[var(--muted-foreground)]">Full Name *</label>
                    <input
                      type="text"
                      autoFocus
                      value={quickCustName}
                      onChange={(e) => setQuickCustName(e.target.value)}
                      placeholder="e.g. Asif Mahmud (আসিফ মাহমুদ)"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)] font-semibold"
                    />
                  </div>

                  <div>
                    <label className="font-semibold text-[11px] text-[var(--muted-foreground)]">Mobile Number (013-019) *</label>
                    <input
                      type="text"
                      value={quickCustPhone}
                      onChange={(e) => setQuickCustPhone(e.target.value)}
                      placeholder="01711-234567"
                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                    />
                    <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">Must be 11 digits starting with 013, 014, 015, 016, 017, 018, or 019</div>
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
                    onClick={() => setShowQuickNewCustomerModal(false)}
                    className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                  >
                    Cancel
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      const cleanName = (quickCustName || '').trim();
                      const cleanPhone = (quickCustPhone || '').replace(/[^0-9]/g, '');

                      if (!cleanName || cleanName.length < 3) {
                        addToast("Customer name is required (minimum 3 characters)", "warning");
                        return;
                      }

                      if (!cleanPhone || !/^01[3-9]\\d{8}$/.test(cleanPhone)) {
                        addToast("Invalid mobile number! Must be 11 digits starting with 013-019", "danger");
                        return;
                      }

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
              </div>
            </div>
          )}

          '''

content = content[:start_idx] + upgraded_quick_modal + content[end_idx:]

with open('scripts/parts/render_all_modals.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("render_all_modals.py updated with validated Quick Customer Modal!")
