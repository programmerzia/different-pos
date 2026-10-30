#!/usr/bin/env python3
import sys

with open('scripts/parts/render_admin_and_states.py', 'r', encoding='utf-8') as f:
    content = f.read()

# Fix raw Tailwind palette classes in users and roles matrix
content = content.replace('bg-emerald-500/15 text-emerald-600', 'bg-[var(--success-soft)] text-[var(--success)]')
content = content.replace('bg-emerald-500/20 text-emerald-600', 'bg-[var(--success-soft)] text-[var(--success)]')

start_tag = "{/* ================= SCREEN 21: BACK-OFFICE SETTINGS (G ,) ================= */}"
end_tag = "{/* ================= SCREEN 22: BACKUP & DISASTER RECOVERY ================= */}"

idx_start = content.find(start_tag)
idx_end = content.find(end_tag)

if idx_start == -1 or idx_end == -1:
    print(f"Error: tags not found. start={idx_start}, end={idx_end}")
    sys.exit(1)

new_settings = '''{/* ================= SCREEN 21: BACK-OFFICE SETTINGS (G ,) ================= */}
              {currentScreen === 'settings' && (() => {
                const sections = [
                  { id: 'business_profile', label: 'Business Profile', icon: 'store', desc: 'Shop identity, legal BIN/TIN & address' },
                  { id: 'branches', label: 'Branches', icon: 'building', desc: 'Manage multi-branch retail outlets' },
                  { id: 'registers_devices', label: 'Registers & Devices', icon: 'smartphone', desc: 'Terminal config & Mobile QR pairing' },
                  { id: 'tax_vat', label: 'Tax & 15% VAT', icon: 'percent', desc: 'NBR compliance & Mushak 6.3 rates' },
                  { id: 'payment_methods', label: 'Payment Methods', icon: 'creditCard', desc: 'Cash, bKash, Nagad & POS terminals' },
                  { id: 'receipt_printers', label: 'Receipt & Printers', icon: 'printer', desc: 'Thermal ESC/POS & Live Test Print' },
                  { id: 'invoice_numbering', label: 'Invoice Numbering', icon: 'hash', desc: 'Sequence format, prefixes & reset rules' },
                  { id: 'customer_pin_policy', label: 'Customer PIN Policy', icon: 'lock', desc: 'Security PIN for due & redemption' },
                  { id: 'membership_cards', label: 'Membership Card Design', icon: 'tag', desc: 'CR80 standard card & barcode layout' },
                  { id: 'messaging_providers', label: 'Messaging Providers', icon: 'messageSquare', desc: 'SSL Wireless, Greenweb & WhatsApp' },
                  { id: 'returns_policy', label: 'Returns & Exchanges', icon: 'refresh', desc: '7-day policy, rules & vouchers' },
                  { id: 'notifications', label: 'Notifications & Alerts', icon: 'bell', desc: 'Low-stock triggers & EOD summaries' },
                  { id: 'appearance', label: 'Appearance & Themes', icon: 'palette', desc: 'Color schemes, dark mode & density' },
                  { id: 'language', label: 'Language & Regional', icon: 'globe', desc: 'English, Bengali receipts & ৳ currency' },
                  { id: 'keyboard_shortcuts', label: 'Keyboard Shortcuts', icon: 'keyboard', desc: 'POS key map & cheatsheet reference' },
                  { id: 'data_import_export', label: 'Data Import & Export', icon: 'download', desc: 'Catalog CSV import & SQLite backups' },
                ];

                const currentTab = settingsTab || 'business_profile';

                const updateDraft = (key, val) => {
                  setSettingsDraft(prev => ({ ...prev, [key]: val }));
                  setSettingsIsDirty(true);
                };

                const saveSettings = () => {
                  setShopSettings(settingsDraft);
                  setSettingsIsDirty(false);
                  addToast('Settings successfully saved!', 'success');
                };

                const discardSettings = () => {
                  setSettingsDraft(shopSettings);
                  setSettingsIsDirty(false);
                  addToast('Unsaved changes discarded', 'info');
                };

                return (
                  <div className="flex-1 flex flex-col overflow-hidden w-full h-full bg-[var(--background)]">
                    {/* Header Bar */}
                    <div className="p-3.5 border-b border-[var(--border)] bg-[var(--surface)] flex items-center justify-between shrink-0 select-none">
                      <div>
                        <div className="flex items-center gap-2">
                          <h1 className="text-base font-bold text-[var(--foreground)]">Store & System Settings</h1>
                          <span className="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-[var(--primary-soft)] text-[var(--primary)] border border-[var(--primary)]">
                            Enterprise POS v3.4
                          </span>
                        </div>
                        <div className="text-xs text-[var(--muted-foreground)] mt-0.5">
                          Manage store profile, multi-branch routing, hardware interfaces, NBR VAT, and security policies
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={saveSettings}
                          className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold shadow-xs hover:bg-[var(--primary-hover)] flex items-center gap-1.5 transition-colors cursor-pointer"
                        >
                          <Icon name="check" size={14} />
                          <span>Save Changes (Ctrl+S)</span>
                        </button>
                      </div>
                    </div>

                    {/* Dirty State Floating Warning Bar */}
                    {settingsIsDirty && (
                      <div className="px-4 py-2 bg-[var(--warning-soft)] border-b border-[var(--warning)] flex items-center justify-between text-xs shrink-0 animate-in slide-in-from-top duration-150">
                        <div className="flex items-center gap-2 text-[var(--foreground)] font-semibold">
                          <span className="w-2 h-2 rounded-full bg-[var(--warning)] animate-pulse"></span>
                          <span>You have unsaved changes in settings.</span>
                        </div>
                        <div className="flex items-center gap-2">
                          <button
                            type="button"
                            onClick={discardSettings}
                            className="h-6 px-2.5 rounded bg-[var(--surface)] border border-[var(--border)] text-xs font-medium text-[var(--muted-foreground)] hover:text-[var(--foreground)] cursor-pointer"
                          >
                            Discard
                          </button>
                          <button
                            type="button"
                            onClick={saveSettings}
                            className="h-6 px-3 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold shadow-xs cursor-pointer"
                          >
                            Save Changes
                          </button>
                        </div>
                      </div>
                    )}

                    {/* Full-Width Workspace Layout: Left Sub-Nav + Right Content Area */}
                    <div className="flex-1 flex overflow-hidden w-full">
                      {/* Left Sub-Nav with all 16 Sections */}
                      <div className="w-64 border-r border-[var(--border)] bg-[var(--surface-sunken)] p-2 overflow-y-auto space-y-0.5 shrink-0 select-none">
                        <div className="text-[10px] font-bold text-[var(--muted-foreground)] px-2.5 py-1 uppercase tracking-wider">
                          Configuration Menu
                        </div>
                        {sections.map(sec => {
                          const isActive = currentTab === sec.id;
                          return (
                            <button
                              key={sec.id}
                              type="button"
                              onClick={() => setSettingsTab(sec.id)}
                              className={`w-full text-left px-2.5 py-2 rounded-lg flex items-center gap-2.5 transition-all cursor-pointer ${
                                isActive
                                  ? 'bg-[var(--primary)] text-[var(--primary-foreground)] font-bold shadow-xs'
                                  : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)] hover:bg-[var(--surface)]'
                              }`}
                            >
                              <Icon name={sec.icon} size={15} className={isActive ? 'text-[var(--primary-foreground)]' : 'text-[var(--muted-foreground)]'} />
                              <div className="flex-1 truncate">
                                <div className="text-xs truncate">{sec.label}</div>
                                <div className={`text-[10px] truncate ${isActive ? 'text-[var(--primary-foreground)] opacity-80' : 'text-[var(--muted-foreground)]'}`}>
                                  {sec.desc}
                                </div>
                              </div>
                            </button>
                          );
                        })}
                      </div>

                      {/* Right Content Panel with Real Fields */}
                      <div className="flex-1 overflow-y-auto p-6 space-y-6 bg-[var(--surface)]">
                        {/* 1. Business Profile */}
                        {currentTab === 'business_profile' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Business & Company Profile</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Commercial registration details printed on tax invoices and receipts</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Registered Trading Name</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.shopName || ''}
                                    onChange={(e) => updateDraft('shopName', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-bold text-[var(--foreground)] focus:border-[var(--primary)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Trade License Number</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.tradeLicense || ''}
                                    onChange={(e) => updateDraft('tradeLicense', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)] focus:border-[var(--primary)]"
                                  />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Business Identification Number (BIN / NBR)</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.bin || ''}
                                    onChange={(e) => updateDraft('bin', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)] focus:border-[var(--primary)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Taxpayer Identification Number (TIN)</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.tin || ''}
                                    onChange={(e) => updateDraft('tin', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)] focus:border-[var(--primary)]"
                                  />
                                </div>
                              </div>

                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Physical Headquarter Address</label>
                                <input
                                  type="text"
                                  value={settingsDraft.address || ''}
                                  onChange={(e) => updateDraft('address', e.target.value)}
                                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                                />
                              </div>

                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Customer Helpline Phone</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.phone || ''}
                                    onChange={(e) => updateDraft('phone', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)] focus:border-[var(--primary)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Support Email Address</label>
                                  <input
                                    type="email"
                                    value={settingsDraft.email || ''}
                                    onChange={(e) => updateDraft('email', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)] focus:border-[var(--primary)]"
                                  />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-3 pt-2 border-t border-[var(--border)]">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Base Currency</label>
                                  <input
                                    type="text"
                                    disabled
                                    value="Bangladeshi Taka (৳ BDT)"
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-medium text-[var(--muted-foreground)] opacity-70"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Timezone</label>
                                  <input
                                    type="text"
                                    disabled
                                    value="Asia/Dhaka (GMT+6)"
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-medium text-[var(--muted-foreground)] opacity-70"
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 2. Branches */}
                        {currentTab === 'branches' && (
                          <div className="space-y-4 max-w-3xl">
                            <div className="flex items-center justify-between">
                              <div>
                                <h2 className="text-base font-bold text-[var(--foreground)]">Retail Branches & Locations</h2>
                                <p className="text-xs text-[var(--muted-foreground)]">Manage physical showroom outlets and inter-branch inventory allocation</p>
                              </div>
                              <button
                                type="button"
                                onClick={() => addToast('New branch provisioning dialog opened', 'info')}
                                className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                              >
                                <Icon name="plus" size={13} />
                                <span>Add Branch</span>
                              </button>
                            </div>

                            <div className="space-y-2.5">
                              {(settingsDraft.branches || []).map(b => (
                                <div key={b.id} className="p-3.5 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] flex items-center justify-between text-xs">
                                  <div className="flex items-center gap-3">
                                    <div className="w-8 h-8 rounded-lg bg-[var(--primary-soft)] text-[var(--primary)] font-mono font-bold flex items-center justify-center text-xs">
                                      {b.code}
                                    </div>
                                    <div>
                                      <div className="font-bold text-[var(--foreground)]">{b.name}</div>
                                      <div className="text-[11px] text-[var(--muted-foreground)]">{b.address} · Phone: {b.phone}</div>
                                    </div>
                                  </div>
                                  <div className="flex items-center gap-3">
                                    <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--success-soft)] text-[var(--success)]">
                                      Active
                                    </span>
                                    <button
                                      type="button"
                                      onClick={() => addToast(`Editing settings for ${b.name}`, 'info')}
                                      className="h-7 px-2.5 rounded bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-[11px] font-medium text-[var(--foreground)] cursor-pointer"
                                    >
                                      Edit
                                    </button>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* 3. Registers & Devices (QR Pairing) */}
                        {currentTab === 'registers_devices' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Counter Registers & Device Pairing</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Terminal identification, barcode scanner interfaces, and Mobile POS companion pairing</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-4 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Terminal Register ID</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.registerId || 'DF1-R1'}
                                    onChange={(e) => updateDraft('registerId', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--primary)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Counter Description</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.registerName || 'Main Counter Register 1'}
                                    onChange={(e) => updateDraft('registerName', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Barcode Scanner Protocol</label>
                                  <select
                                    value={settingsDraft.barcodeScannerType || 'Honeywell Orbit 7120 (USB HID / Virtual COM)'}
                                    onChange={(e) => updateDraft('barcodeScannerType', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                                  >
                                    <option>Honeywell Orbit 7120 (USB HID / Virtual COM)</option>
                                    <option>Zebra Symbol DS2208 (USB Keystroke)</option>
                                    <option>Generic Bluetooth 2D Ring Scanner</option>
                                  </select>
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Customer VFD Pole Display</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.customerDisplayPort || 'COM3 (9600 baud, 8-N-1)'}
                                    onChange={(e) => updateDraft('customerDisplayPort', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              {/* Mobile Companion QR Pairing Widget */}
                              <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--primary)] flex flex-col sm:flex-row items-center gap-4">
                                <div className="p-2 rounded-lg bg-white border border-[var(--border)] shrink-0">
                                  {/* Simulated QR Code SVG */}
                                  <svg viewBox="0 0 100 100" className="w-24 h-24">
                                    <rect x="0" y="0" width="30" height="30" fill="#000" />
                                    <rect x="5" y="5" width="20" height="20" fill="#fff" />
                                    <rect x="10" y="10" width="10" height="10" fill="#000" />

                                    <rect x="70" y="0" width="30" height="30" fill="#000" />
                                    <rect x="75" y="5" width="20" height="20" fill="#fff" />
                                    <rect x="80" y="10" width="10" height="10" fill="#000" />

                                    <rect x="0" y="70" width="30" height="30" fill="#000" />
                                    <rect x="5" y="75" width="20" height="20" fill="#fff" />
                                    <rect x="10" y="80" width="10" height="10" fill="#000" />

                                    <rect x="40" y="10" width="15" height="10" fill="#000" />
                                    <rect x="40" y="30" width="20" height="10" fill="#000" />
                                    <rect x="40" y="60" width="10" height="20" fill="#000" />
                                    <rect x="60" y="45" width="25" height="15" fill="#000" />
                                    <rect x="70" y="75" width="15" height="15" fill="#000" />
                                  </svg>
                                </div>

                                <div className="space-y-1 text-center sm:text-left">
                                  <div className="text-xs font-bold text-[var(--foreground)]">Instant Mobile POS Companion Pairing</div>
                                  <p className="text-[11px] text-[var(--muted-foreground)]">
                                    Scan this QR code with the Different Fashion Android / iOS companion app to link handheld barcode scanner or wireless queue buster.
                                  </p>
                                  <div className="text-[10px] font-mono text-[var(--primary)] pt-1">
                                    Pairing Code: <span className="font-bold">{settingsDraft.pairingToken || 'DF-PAIR-98234-X7'}</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 4. Tax/VAT */}
                        {currentTab === 'tax_vat' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Value Added Tax (VAT) & NBR Rules</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Statutory 15% VAT calculation and Mushak 6.3 legal compliance requirements</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Standard Retail VAT Rate (%)</label>
                                  <input
                                    type="number"
                                    value={settingsDraft.vatRate || 15}
                                    onChange={(e) => updateDraft('vatRate', Number(e.target.value))}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Mushak Invoice Challan Prefix</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.mushakChallanPrefix || 'M-6.3-'}
                                    onChange={(e) => updateDraft('mushakChallanPrefix', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Retail Prices Include 15% VAT (MRP Gross)</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">When enabled, price tags on apparel garments already include the 15% VAT</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.taxInclusivePricing !== false}
                                  onChange={(e) => updateDraft('taxInclusivePricing', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)] focus:ring-[var(--primary)]"
                                />
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Mushak 6.3 Legal Compliance Header</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Print official government tax invoice declaration at the top of receipts</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.mushakInvoiceEnabled !== false}
                                  onChange={(e) => updateDraft('mushakInvoiceEnabled', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)] focus:ring-[var(--primary)]"
                                />
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 5. Payment Methods */}
                        {currentTab === 'payment_methods' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Payment Gateways & Customer Credit</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Configure Cash tenders, bKash Merchant QR, Nagad wallets, Card POS, and Due allowances</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">bKash Merchant Wallet #</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.bkashMerchant || '01811223344'}
                                    onChange={(e) => updateDraft('bkashMerchant', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Nagad Merchant Wallet #</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.nagadMerchant || '01711223344'}
                                    onChange={(e) => updateDraft('nagadMerchant', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Primary POS Card Terminal</label>
                                <input
                                  type="text"
                                  value={settingsDraft.cardPosTerminal || 'City Bank POS - Terminal #928341'}
                                  onChange={(e) => updateDraft('cardPosTerminal', e.target.value)}
                                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-medium text-[var(--foreground)]"
                                />
                              </div>

                              <div className="pt-2 border-t border-[var(--border)] space-y-3">
                                <div className="text-xs font-bold text-[var(--foreground)]">Customer Due & Credit Terms</div>
                                <div className="grid grid-cols-2 gap-3">
                                  <div>
                                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Default Credit Limit (৳)</label>
                                    <input
                                      type="number"
                                      value={settingsDraft.defaultCreditLimit || 15000}
                                      onChange={(e) => updateDraft('defaultCreditLimit', Number(e.target.value))}
                                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                    />
                                  </div>
                                  <div>
                                    <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Max Overdue Days Limit</label>
                                    <input
                                      type="number"
                                      value={settingsDraft.dueDaysLimit || 30}
                                      onChange={(e) => updateDraft('dueDaysLimit', Number(e.target.value))}
                                      className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                    />
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 6. Receipt & Printers (Test Print) */}
                        {currentTab === 'receipt_printers' && (
                          <div className="space-y-4 max-w-3xl">
                            <div className="flex items-center justify-between">
                              <div>
                                <h2 className="text-base font-bold text-[var(--foreground)]">Thermal Receipt & Hardware Printers</h2>
                                <p className="text-xs text-[var(--muted-foreground)]">ESC/POS printer routing, paper roll width, header text, and test print validation</p>
                              </div>
                              <button
                                type="button"
                                onClick={() => {
                                  addToast('Test print sent to ESC/POS thermal printer (192.168.1.150:9100)', 'success');
                                }}
                                className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold flex items-center gap-1.5 shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer"
                              >
                                <Icon name="printer" size={14} />
                                <span>Send Test Print</span>
                              </button>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-3 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Printer Connection</label>
                                  <select
                                    value={settingsDraft.printerType || 'esc_pos_network'}
                                    onChange={(e) => updateDraft('printerType', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                                  >
                                    <option value="esc_pos_network">ESC/POS Ethernet / LAN</option>
                                    <option value="usb_direct">Direct USB Thermal</option>
                                    <option value="system_spooler">Windows / CUPS Spooler</option>
                                  </select>
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Printer IP Address</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.printerIp || '192.168.1.150'}
                                    onChange={(e) => updateDraft('printerIp', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Network Port</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.printerPort || '9100'}
                                    onChange={(e) => updateDraft('printerPort', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Thermal Receipt Header Text</label>
                                <textarea
                                  rows={2}
                                  value={settingsDraft.headerText || ''}
                                  onChange={(e) => updateDraft('headerText', e.target.value)}
                                  className="mt-1 w-full p-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                />
                              </div>

                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Receipt Footer & 7-Day Exchange Policy</label>
                                <textarea
                                  rows={2}
                                  value={settingsDraft.footerText || ''}
                                  onChange={(e) => updateDraft('footerText', e.target.value)}
                                  className="mt-1 w-full p-2 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                />
                              </div>

                              <div className="grid grid-cols-2 gap-3 pt-2 border-t border-[var(--border)]">
                                <div className="flex items-center justify-between p-2 rounded bg-[var(--surface)] border border-[var(--border)]">
                                  <span className="font-semibold text-[var(--foreground)]">Print Invoice Barcode</span>
                                  <input
                                    type="checkbox"
                                    checked={settingsDraft.showBarcodeOnReceipt !== false}
                                    onChange={(e) => updateDraft('showBarcodeOnReceipt', e.target.checked)}
                                    className="rounded text-[var(--primary)]"
                                  />
                                </div>
                                <div className="flex items-center justify-between p-2 rounded bg-[var(--surface)] border border-[var(--border)]">
                                  <span className="font-semibold text-[var(--foreground)]">Auto-cut Paper Roll</span>
                                  <input
                                    type="checkbox"
                                    checked={settingsDraft.autoCutReceipt !== false}
                                    onChange={(e) => updateDraft('autoCutReceipt', e.target.checked)}
                                    className="rounded text-[var(--primary)]"
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 7. Invoice Numbering */}
                        {currentTab === 'invoice_numbering' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Invoice Numbering & Sequencing</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Prefix conventions, sequence digits, and fiscal reset rules</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Branch / Register Prefix</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.invoicePrefix || 'DF1-R1-'}
                                    onChange={(e) => updateDraft('invoicePrefix', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Sequence Padding (Digits)</label>
                                  <input
                                    type="number"
                                    value={settingsDraft.invoiceSequencePadding || 8}
                                    onChange={(e) => updateDraft('invoiceSequencePadding', Number(e.target.value))}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div className="p-3 rounded-lg bg-[var(--surface)] border border-[var(--primary)]">
                                <div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Live Invoice Format Preview</div>
                                <div className="text-sm font-mono font-bold text-[var(--primary)] mt-1">
                                  {settingsDraft.invoicePrefix || 'DF1-R1-'}26000413
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 8. Customer PIN Policy */}
                        {currentTab === 'customer_pin_policy' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Customer Security PIN Policy</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Prevent unauthorized customer due additions and fraudulent loyalty reward claims</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Require 4-Digit PIN for Due Sales</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Cashier cannot add due to customer account without customer entering their secret PIN</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.requirePinForDueCollection !== false}
                                  onChange={(e) => updateDraft('requirePinForDueCollection', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)]"
                                />
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Require PIN for Loyalty Points Redemption</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Verify customer ownership before burning accumulated reward points</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.requirePinForPointsRedemption !== false}
                                  onChange={(e) => updateDraft('requirePinForPointsRedemption', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)]"
                                />
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">SMS OTP Override when PIN Forgotten</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Allow customer to receive a 6-digit one-time code on registered mobile</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.allowOtpBypass !== false}
                                  onChange={(e) => updateDraft('allowOtpBypass', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)]"
                                />
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 9. Membership Card Design */}
                        {currentTab === 'membership_cards' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Privilege Membership Card Layout</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Standard CR80 dimensions, barcode encoding, and visual card styling</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-4 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Card Standard Dimension</label>
                                  <input
                                    type="text"
                                    disabled
                                    value="CR80 (85.60 mm × 53.98 mm, ISO/IEC 7810 ID-1)"
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--muted-foreground)] opacity-70"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Barcode Encoding</label>
                                  <select
                                    value={settingsDraft.cardBarcodeStandard || 'Code 128'}
                                    onChange={(e) => updateDraft('cardBarcodeStandard', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                                  >
                                    <option>Code 128</option>
                                    <option>EAN-13</option>
                                    <option>QR Code Dynamic</option>
                                  </select>
                                </div>
                              </div>

                              {/* Card Preview */}
                              <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] flex justify-center">
                                <div className="w-72 h-44 rounded-xl bg-gradient-to-r from-teal-700 to-teal-900 text-white p-4 flex flex-col justify-between shadow-xl">
                                  <div className="flex justify-between items-start">
                                    <div>
                                      <div className="text-xs font-bold tracking-wider">DIFFERENT FASHION</div>
                                      <div className="text-[9px] opacity-80">PRIVILEGE CLUB</div>
                                    </div>
                                    <span className="text-[9px] font-mono px-1.5 py-0.5 rounded bg-white/20">PLATINUM</span>
                                  </div>
                                  <div>
                                    <div className="text-xs font-bold">RAHIM UDDIN</div>
                                    <div className="text-[11px] font-mono tracking-widest mt-0.5">1000 0017</div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 10. Messaging Providers */}
                        {currentTab === 'messaging_providers' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">SMS & WhatsApp Messaging Gateways</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">API tokens for Bangladesh telecom operator delivery and WhatsApp Cloud API</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">SMS Gateway Provider</label>
                                  <select
                                    value={settingsDraft.smsProvider || 'ssl_wireless'}
                                    onChange={(e) => updateDraft('smsProvider', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                                  >
                                    <option value="ssl_wireless">SSL Wireless (Bangladesh)</option>
                                    <option value="greenweb">Greenweb SMS Gateway</option>
                                    <option value="twilio">Twilio Cloud API</option>
                                  </select>
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Approved Masking / Sender ID</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.smsSenderId || 'DIFF-FASHION'}
                                    onChange={(e) => updateDraft('smsSenderId', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">API Secret Key</label>
                                <input
                                  type="password"
                                  value={settingsDraft.smsApiKey || 'ssl_live_9a8f7b6c5d4e3f2a1b'}
                                  onChange={(e) => updateDraft('smsApiKey', e.target.value)}
                                  className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                />
                              </div>

                              <button
                                type="button"
                                onClick={() => addToast('Dispatched test SMS to store administrator', 'success')}
                                className="h-8 px-3 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-semibold text-[var(--foreground)] flex items-center gap-1.5 cursor-pointer"
                              >
                                <Icon name="messageSquare" size={13} />
                                <span>Send Test SMS</span>
                              </button>
                            </div>
                          </div>
                        )}

                        {/* 11. Returns Policy */}
                        {currentTab === 'returns_policy' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Merchandise Return & Exchange Policy</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Rules governing allowable exchange windows, credit notes, and condition verifications</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Exchange Window (Days)</label>
                                  <input
                                    type="number"
                                    value={settingsDraft.returnWindowDays || 7}
                                    onChange={(e) => updateDraft('returnWindowDays', Number(e.target.value))}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Restocking Surcharge (%)</label>
                                  <input
                                    type="number"
                                    value={settingsDraft.restockFeePercentage || 0}
                                    onChange={(e) => updateDraft('restockFeePercentage', Number(e.target.value))}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Mandatory Invoice / Cash Memo Verification</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Return cannot proceed without validating original sale record in database</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.requireOriginalInvoice !== false}
                                  onChange={(e) => updateDraft('requireOriginalInvoice', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)]"
                                />
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Issue Store Credit Voucher (No Cash Refunds)</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Default policy: Return balances given as store credit note valid across branches</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.issueCreditNoteOnly !== false}
                                  onChange={(e) => updateDraft('issueCreditNoteOnly', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)]"
                                />
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 12. Notifications */}
                        {currentTab === 'notifications' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">System Alerts & EOD Notifications</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Automated alerts for stock thresholds and daily revenue reports</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Low-Stock Alert Trigger (Units)</label>
                                <input
                                  type="number"
                                  value={settingsDraft.lowStockAlertThreshold || 5}
                                  onChange={(e) => updateDraft('lowStockAlertThreshold', Number(e.target.value))}
                                  className="mt-1 w-48 h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                                />
                              </div>

                              <div className="grid grid-cols-2 gap-3 pt-2 border-t border-[var(--border)]">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">EOD Closing Report Recipient Email</label>
                                  <input
                                    type="email"
                                    value={settingsDraft.emailAddress || 'admin@differentfashion.com.bd'}
                                    onChange={(e) => updateDraft('emailAddress', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                                  />
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">EOD SMS Summary Phone #</label>
                                  <input
                                    type="text"
                                    value={settingsDraft.eodSmsRecipient || '01711000000'}
                                    onChange={(e) => updateDraft('eodSmsRecipient', e.target.value)}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-mono text-[var(--foreground)]"
                                  />
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 13. Appearance */}
                        {currentTab === 'appearance' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Visual Theme & Display Density</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">Customize color palette, dark mode contrast, and button padding</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-4 text-xs">
                              <div>
                                <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Color Theme</label>
                                <div className="grid grid-cols-3 gap-3 mt-1.5">
                                  {[
                                    { id: 'teal', label: 'Classic Teal (Default)' },
                                    { id: 'indigo', label: 'Indigo Modern' },
                                    { id: 'violet', label: 'Royal Violet' },
                                    { id: 'rose', label: 'Festive Rose' },
                                    { id: 'ocean', label: 'Ocean Blue' },
                                    { id: 'graphite', label: 'Graphite Neutral' }
                                  ].map(s => (
                                    <button
                                      key={s.id}
                                      type="button"
                                      onClick={() => {
                                        setScheme(s.id);
                                        updateDraft('colorScheme', s.id);
                                      }}
                                      className={`p-2.5 rounded-lg border text-left cursor-pointer transition-all ${
                                        scheme === s.id ? 'border-[var(--primary)] bg-[var(--primary-soft)] font-bold text-[var(--primary)]' : 'border-[var(--border)] bg-[var(--surface)] text-[var(--foreground)]'
                                      }`}
                                    >
                                      {s.label}
                                    </button>
                                  ))}
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-3 pt-2 border-t border-[var(--border)]">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Interface Mode</label>
                                  <div className="flex gap-2 mt-1.5">
                                    <button
                                      type="button"
                                      onClick={() => { setTheme('light'); updateDraft('theme', 'light'); }}
                                      className={`flex-1 h-8 rounded-lg border font-medium cursor-pointer ${theme === 'light' ? 'border-[var(--primary)] bg-[var(--primary-soft)] text-[var(--primary)] font-bold' : 'border-[var(--border)] bg-[var(--surface)] text-[var(--foreground)]'}`}
                                    >
                                      Light Mode
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => { setTheme('dark'); updateDraft('theme', 'dark'); }}
                                      className={`flex-1 h-8 rounded-lg border font-medium cursor-pointer ${theme === 'dark' ? 'border-[var(--primary)] bg-[var(--primary-soft)] text-[var(--primary)] font-bold' : 'border-[var(--border)] bg-[var(--surface)] text-[var(--foreground)]'}`}
                                    >
                                      Dark Mode
                                    </button>
                                  </div>
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">UI Density</label>
                                  <div className="flex gap-2 mt-1.5">
                                    <button
                                      type="button"
                                      onClick={() => { setDensity('comfortable'); updateDraft('density', 'comfortable'); }}
                                      className={`flex-1 h-8 rounded-lg border font-medium cursor-pointer ${density === 'comfortable' ? 'border-[var(--primary)] bg-[var(--primary-soft)] text-[var(--primary)] font-bold' : 'border-[var(--border)] bg-[var(--surface)] text-[var(--foreground)]'}`}
                                    >
                                      Comfortable
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => { setDensity('compact'); updateDraft('density', 'compact'); }}
                                      className={`flex-1 h-8 rounded-lg border font-medium cursor-pointer ${density === 'compact' ? 'border-[var(--primary)] bg-[var(--primary-soft)] text-[var(--primary)] font-bold' : 'border-[var(--border)] bg-[var(--surface)] text-[var(--foreground)]'}`}
                                    >
                                      Compact
                                    </button>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 14. Language */}
                        {currentTab === 'language' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Language & Regional Formatting</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">English & Bengali bilingual receipts, currency symbol rules, and font rendering</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-3.5 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Primary POS Display Language</label>
                                  <select
                                    value={lang}
                                    onChange={(e) => { setLang(e.target.value); updateDraft('defaultLanguage', e.target.value); }}
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                                  >
                                    <option value="en">English (Official)</option>
                                    <option value="bn">বাংলা (Bengali)</option>
                                  </select>
                                </div>
                                <div>
                                  <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Currency Glyph Display</label>
                                  <input
                                    type="text"
                                    disabled
                                    value="৳ (Bangladeshi Taka / Tabular Nums)"
                                    className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] text-xs font-bold text-[var(--foreground)] opacity-70"
                                  />
                                </div>
                              </div>

                              <div className="flex items-center justify-between p-3 rounded-lg bg-[var(--surface)] border border-[var(--border)]">
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">Bilingual Bengali Thermal Receipts</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Print product names in both English and Bengali on customer cash memos</div>
                                </div>
                                <input
                                  type="checkbox"
                                  checked={settingsDraft.enableBengaliReceipt !== false}
                                  onChange={(e) => updateDraft('enableBengaliReceipt', e.target.checked)}
                                  className="w-4 h-4 rounded text-[var(--primary)]"
                                />
                              </div>
                            </div>
                          </div>
                        )}

                        {/* 15. Keyboard Shortcuts */}
                        {currentTab === 'keyboard_shortcuts' && (
                          <div className="space-y-4 max-w-3xl">
                            <div className="flex items-center justify-between">
                              <div>
                                <h2 className="text-base font-bold text-[var(--foreground)]">POS & Navigation Keyboard Key Map</h2>
                                <p className="text-xs text-[var(--muted-foreground)]">Zero-conflict POS function keys, G-navigation chords, and palette shortcuts</p>
                              </div>
                              <button
                                type="button"
                                onClick={() => openModal('shortcuts')}
                                className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer"
                              >
                                <Icon name="keyboard" size={13} />
                                <span>Open Cheatsheet (?)</span>
                              </button>
                            </div>

                            <div className="rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] overflow-hidden shadow-xs text-xs">
                              <table className="w-full text-left">
                                <thead className="bg-[var(--surface)] border-b border-[var(--border)] text-[var(--muted-foreground)] font-semibold">
                                  <tr>
                                    <th className="py-2 px-3">Shortcut Key</th>
                                    <th className="py-2 px-3">Action Description</th>
                                    <th className="py-2 px-3">Active Context</th>
                                  </tr>
                                </thead>
                                <tbody className="divide-y divide-[var(--border)]">
                                  {[
                                    { key: 'F1', desc: 'Focus POS Billing Register', ctx: 'Global' },
                                    { key: 'F2', desc: 'Focus Barcode Scan Box', ctx: 'POS' },
                                    { key: 'F3', desc: 'Edit Quantity of Selected Line', ctx: 'POS Cart' },
                                    { key: 'F4', desc: 'Attach Customer Modal', ctx: 'POS' },
                                    { key: 'F5', desc: 'Price & Stock Check Modal', ctx: 'POS' },
                                    { key: 'F6', desc: 'Line Discount Modal', ctx: 'POS Cart' },
                                    { key: 'Shift+F6', desc: 'Order Discount Modal', ctx: 'POS' },
                                    { key: 'F8', desc: 'Park / Hold Current Sale', ctx: 'POS' },
                                    { key: 'Shift+F8', desc: 'Parked Sales Modal', ctx: 'POS' },
                                    { key: 'F9', desc: 'Open Payment Modal (PAY)', ctx: 'POS' },
                                    { key: 'F10', desc: 'Complete Sale', ctx: 'Inside Payment' },
                                    { key: 'F11', desc: 'Return / Exchange Mode', ctx: 'POS' },
                                    { key: 'Ctrl+D', desc: 'Collect Customer Due', ctx: 'Global' },
                                    { key: 'Ctrl+N', desc: 'Quick New Customer', ctx: 'Global' },
                                    { key: 'Ctrl+L', desc: 'Lock Screen', ctx: 'Global' },
                                    { key: 'Ctrl+K', desc: 'Command Palette', ctx: 'Global' },
                                    { key: 'Ctrl+S', desc: 'Save Changes in Settings', ctx: 'Settings' },
                                    { key: '?', desc: 'Shortcut Overlay', ctx: 'Global' },
                                  ].map((row, idx) => (
                                    <tr key={idx} className="hover:bg-[var(--surface)] transition-colors">
                                      <td className="py-2 px-3 font-mono font-bold text-[var(--primary)]">{row.key}</td>
                                      <td className="py-2 px-3 font-medium text-[var(--foreground)]">{row.desc}</td>
                                      <td className="py-2 px-3 text-[var(--muted-foreground)]">{row.ctx}</td>
                                    </tr>
                                  ))}
                                </tbody>
                              </table>
                            </div>
                          </div>
                        )}

                        {/* 16. Data Import & Export */}
                        {currentTab === 'data_import_export' && (
                          <div className="space-y-4 max-w-3xl">
                            <div>
                              <h2 className="text-base font-bold text-[var(--foreground)]">Data Import, Export & Cloud Migration</h2>
                              <p className="text-xs text-[var(--muted-foreground)]">CSV bulk catalog imports, full database snapshots, and disaster archives</p>
                            </div>

                            <div className="p-4 rounded-xl bg-[var(--surface-sunken)] border border-[var(--border)] space-y-4 text-xs">
                              <div className="grid grid-cols-2 gap-3">
                                <div className="p-3.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex flex-col justify-between space-y-2">
                                  <div>
                                    <div className="font-bold text-[var(--foreground)]">Product Catalog Import</div>
                                    <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">Bulk upload apparel SKUs, barcodes, variants, and wholesale cost</div>
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => addToast('Opening CSV Product Import wizard', 'info')}
                                    className="h-7 px-3 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold self-start cursor-pointer"
                                  >
                                    Import Products CSV
                                  </button>
                                </div>

                                <div className="p-3.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex flex-col justify-between space-y-2">
                                  <div>
                                    <div className="font-bold text-[var(--foreground)]">Customer Database Import</div>
                                    <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">Import existing club members with Luhn-validated customer numbers</div>
                                  </div>
                                  <button
                                    type="button"
                                    onClick={() => addToast('Opening Customer Import wizard', 'info')}
                                    className="h-7 px-3 rounded bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] self-start cursor-pointer"
                                  >
                                    Import Customers CSV
                                  </button>
                                </div>
                              </div>

                              <div className="p-3.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] flex items-center justify-between">
                                <div>
                                  <div className="font-bold text-[var(--foreground)]">Complete SQLite Database Export</div>
                                  <div className="text-[11px] text-[var(--muted-foreground)]">Download offline portable backup containing all sales, ledger entries, and audit logs</div>
                                </div>
                                <button
                                  type="button"
                                  onClick={() => {
                                    handleRunBackup();
                                    addToast('Generating SQLite DB snapshot...', 'info');
                                  }}
                                  className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold shadow-xs cursor-pointer"
                                >
                                  Export Database (JSON)
                                </button>
                              </div>
                            </div>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })()}
'''

content = content[:idx_start] + new_settings + "\n\n              " + content[idx_end:]

with open('scripts/parts/render_admin_and_states.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("Settings patched successfully!")
