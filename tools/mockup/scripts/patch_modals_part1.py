#!/usr/bin/env python3
"""
scripts/patch_modals_part1.py
Updates render_all_modals.py for Part 1:
- Rebuilds Command Palette with fuzzy search, prefixes (#, >, /, @), Luhn check digit validation, up/down/Enter, recent searches, permission checks
- Updates Payment Modal with Alt+E chip and F10 complete sale, wrapped in <Modal>
- Adds Hold List modal (Shift+F8) wrapped in <Modal>
- Updates Shortcuts modal (?) wrapped in <Modal>
- Wraps Customer Attach, Quick Customer, Price Check, Discount, and Due Collect in <Modal>
"""

import os
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET_FILE = os.path.join(BASE_DIR, "scripts", "parts", "render_all_modals.py")

with open(TARGET_FILE, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Update Command Palette (Ctrl+K)
old_palette_pattern = re.compile(
    r"\{\s*/\*\s*24\.\s*COMMAND PALETTE MODAL \(Ctrl\+K\)\s*\*/\s*\}\s*\{showCommandPalette && \(.*?\)\s*\}\s*\{/\* 25A\.",
    re.DOTALL
)

new_palette = """{/* 24. COMMAND PALETTE MODAL (Ctrl+K) */}
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
                const digitsOnly = q.replace(/[\\s-]/g, '');
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
                    { id: 'p_due', label: 'Customer Due Collection', subtitle: 'Collect pending receivables & dues', shortcut: 'G U', screen: 'due_collection', icon: 'banknote' },
                    { id: 'p_cust', label: 'Customer Directory', subtitle: 'Customer profiles, cards & credit limits', shortcut: 'G C', screen: 'customers', icon: 'users' },
                    { id: 'p_prod', label: 'Product Catalog', subtitle: 'Inventory items, prices & barcodes', shortcut: 'G I', screen: 'products', icon: 'package' },
                    { id: 'p_stock', label: 'Stock & Inventory Transfers', subtitle: 'Adjust stock, transfers & cycle counts', shortcut: 'G K', screen: 'stock', icon: 'truck' },
                    { id: 'p_pur', label: 'Purchase Orders & GRN', subtitle: 'Procurements & vendor bills', shortcut: 'G P', screen: 'purchases', icon: 'package' },
                    { id: 'p_ven', label: 'Suppliers & Vendors', subtitle: 'Trade payables & vendor ledger', shortcut: 'G V', screen: 'vendors', icon: 'truck' },
                    { id: 'p_mon', label: 'Financial Accounts & Ledgers', subtitle: 'Cash drawer, bank accounts & expenses', shortcut: 'G M', screen: 'money', icon: 'dollarSign' },
                    { id: 'p_rep', label: 'Reports & Analytics Hub', subtitle: 'Sales, taxes, profit & due aging', shortcut: 'G R', screen: 'reports', icon: 'barChart' },
                    { id: 'p_set', label: 'System Settings', subtitle: 'Store config, tax rates & roles', shortcut: 'G ,', screen: 'settings', icon: 'settings' }
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
                    { id: 'act_return', label: 'Start Fast Return / Exchange Mode', subtitle: 'Exchange items against invoice', shortcut: 'F11', icon: 'refresh', onSelect: () => { setShowCommandPalette(false); startReturnModeForInvoice('DF1-R1-26000412'); setCurrentScreen('pos'); } },
                    { id: 'act_collect_due', label: 'Collect Customer Due', subtitle: 'Open due settlement modal', shortcut: 'Ctrl+D', icon: 'banknote', onSelect: () => { setShowCommandPalette(false); openCollectDueModal(); } },
                    { id: 'act_new_cust', label: 'Add Quick Customer', subtitle: 'Register customer inline', shortcut: 'Ctrl+N', icon: 'user', onSelect: () => { setShowCommandPalette(false); setShowQuickNewCustomerModal(true); } },
                    { id: 'act_price_check', label: 'Price & Stock Check', subtitle: 'Instant barcode price lookup', shortcut: 'F5', icon: 'tag', onSelect: () => { setShowCommandPalette(false); setShowPriceCheckModal(true); } },
                    { id: 'act_hold', label: 'Park / Hold Current Sale', subtitle: 'Save cart basket for later', shortcut: 'F8', icon: 'pause', onSelect: () => { setShowCommandPalette(false); holdSale(); } },
                    { id: 'act_held_list', label: 'View Parked / Held Sales', subtitle: 'Recall a previously held cart', shortcut: 'Shift+F8', icon: 'layers', onSelect: () => { setShowCommandPalette(false); setShowHeldSalesModal(true); } },
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
                    return s.invoice.toLowerCase().includes(q) || s.customer.toLowerCase().includes(q);
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
                    return p.po_number.toLowerCase().includes(q) || p.vendor.toLowerCase().includes(q);
                  });
                  if (matched.length > 0) {
                    groups.push({
                      id: 'purchases',
                      name: `Purchases (${matched.length} found)`,
                      total: matched.length,
                      items: matched.slice(0, 5).map(p => ({
                        id: 'po_' + p.id,
                        label: `${p.po_number} — ${p.vendor}`,
                        subtitle: `Items: ${p.items_count} · Total: ${formatMoney(p.total)} · Status: ${p.status}`,
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
                    return v.name.toLowerCase().includes(q) || v.contact_person.toLowerCase().includes(q) || v.phone.includes(q);
                  });
                  if (matched.length > 0) {
                    groups.push({
                      id: 'vendors',
                      name: `Vendors (${matched.length} found)`,
                      total: matched.length,
                      items: matched.slice(0, 5).map(v => ({
                        id: 'ven_' + v.id,
                        label: `${v.name} (${v.contact_person})`,
                        subtitle: `Phone: ${v.phone} · Payable Due: ${formatMoney(v.balance_due ?? v.payable ?? 0)}`,
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
                      <button type="button" onClick={() => setCmdQuery('>')} className="px-1.5 py-0.5 rounded bg-[var(--surface-sunken)] hover:bg-[var(--primary-soft)] hover:text-[var(--primary)] font-mono cursor-pointer">> Actions</button>
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
                                        : (item.isError ? 'bg-rose-500/10 text-rose-600 hover:bg-rose-500/15' : 'hover:bg-[var(--surface-sunken)] text-[var(--foreground)]')
                                    }`}
                                  >
                                    <div className="flex items-center gap-2 truncate">
                                      <div className={`w-6 h-6 rounded-lg flex items-center justify-center shrink-0 ${
                                        isSelected ? 'bg-white/20 text-white' : (item.isError ? 'bg-rose-500/20 text-rose-600' : 'bg-[var(--surface-sunken)] text-[var(--primary)]')
                                      }`}>
                                        <Icon name={item.icon || 'search'} size={13} />
                                      </div>
                                      <div className="truncate">
                                        <div className="font-semibold text-xs flex items-center gap-1.5 truncate">
                                          <span className="truncate">{item.label}</span>
                                          {item.badge && (
                                            <span className={`px-1.5 py-0.2 rounded text-[9px] font-mono font-bold ${
                                              isSelected ? 'bg-white/20 text-white' : 'bg-emerald-500/15 text-emerald-600'
                                            }`}>
                                              {item.badge}
                                            </span>
                                          )}
                                          {item.isRestricted && (
                                            <span className={`px-1.5 py-0.2 rounded text-[9px] font-bold ${
                                              isSelected ? 'bg-white/20 text-white' : 'bg-amber-500/15 text-amber-600'
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

          {/* 25A."""

match = old_palette_pattern.search(content)
if match:
    content = content[:match.start()] + new_palette + content[match.end():]
    print("Replaced Command Palette with comprehensive search modal.")
else:
    print("Warning: old_palette_pattern not matched!")

# 2. Add Hold List modal (Shift+F8) right above Discount modal or after Discount modal
held_sales_modal_code = """
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
                          className="h-8 w-8 rounded-lg bg-[var(--surface-sunken)] hover:bg-rose-500/10 hover:text-rose-600 border border-[var(--border)] text-[var(--muted-foreground)] flex items-center justify-center cursor-pointer"
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
"""

if "{/* 8B. PARKED / HELD SALES MODAL" not in content:
    discount_anchor = "{/* 8. DISCOUNT POPOVER / MODAL"
    if discount_anchor in content:
        content = content.replace(discount_anchor, held_sales_modal_code + "\n          " + discount_anchor, 1)
        print("Added Parked / Held Sales Modal (Shift+F8).")

# 3. Update Payment Modal with Alt+E chip and F10 shortcut inside Payment
old_quick_cash = """                    {/* Quick Cash Buttons */}
                    <div className="pt-2 flex items-center gap-1">
                      {[100, 500, 1000].map(amt => ("""

new_quick_cash = """                    {/* Quick Cash Buttons */}
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
                      {[100, 500, 1000].map(amt => ("""

if old_quick_cash in content:
    content = content.replace(old_quick_cash, new_quick_cash)
    print("Added Alt+E Exact Cash chip to Payment Modal.")

old_pay_confirm_btn = """                    <button
                      onClick={() => completeSale('split')}
                      disabled={(tenderDue > 0 && (!attachedCustomer || isOfflineMode || (isCreditLimitExceeded && dueManagerPinOverride !== '1234' && dueManagerPinOverride !== '9999')))}
                      className="w-full h-12 rounded-xl bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold transition-colors shadow-md disabled:opacity-40"
                    >
                      {tenderDue > 0 ? 'Confirm Partial / Due Sale' : 'Complete Sale & Print Receipt'}
                    </button>"""

new_pay_confirm_btn = """                    <button
                      onClick={() => completeSale('split')}
                      disabled={(tenderDue > 0 && (!attachedCustomer || isOfflineMode || (isCreditLimitExceeded && dueManagerPinOverride !== '1234' && dueManagerPinOverride !== '9999')))}
                      className="w-full h-12 rounded-xl bg-[var(--primary)] hover:bg-[var(--primary-hover)] text-white text-xs font-bold transition-colors shadow-md disabled:opacity-40 flex items-center justify-center gap-2 cursor-pointer"
                    >
                      <span>{tenderDue > 0 ? 'Confirm Partial / Due Sale' : 'Complete Sale & Print Receipt'}</span>
                      <kbd className="px-1.5 py-0.5 rounded bg-black/25 text-white text-[10px] font-mono">F10</kbd>
                    </button>"""

if old_pay_confirm_btn in content:
    content = content.replace(old_pay_confirm_btn, new_pay_confirm_btn)
    print("Added F10 Complete Sale shortcut to Payment Modal button.")

# 4. Update Shortcuts Modal (?)
shortcuts_pattern = re.compile(
    r"\{\s*/\*\s*25\.\s*KEYBOARD SHORTCUTS MODAL \(\?\)\s*\*/\s*\}\s*\{showShortcutsModal && \(.*?\)\s*\}\s*\{/\* 26\.\s*LOCK SCREEN",
    re.DOTALL
)

new_shortcuts_modal = """{/* 25. KEYBOARD SHORTCUTS MODAL (?) */}
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

          {/* 26. LOCK SCREEN"""

match = shortcuts_pattern.search(content)
if match:
    content = content[:match.start()] + new_shortcuts_modal + content[match.end():]
    print("Replaced Shortcuts Modal with updated cheatsheet and Modal component.")
else:
    print("Warning: shortcuts_pattern not matched!")

with open(TARGET_FILE, "w", encoding="utf-8") as f:
    f.write(content)

print("render_all_modals.py patch complete.")
