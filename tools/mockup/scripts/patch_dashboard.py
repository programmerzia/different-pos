#!/usr/bin/env python3
import sys

with open('scripts/parts/render_money_dashboard_reports.py', 'r', encoding='utf-8') as f:
    content = f.read()

# Locate SCREEN 18: DASHBOARD (G D)
start_tag = "{/* ================= SCREEN 18: DASHBOARD (G D) ================= */}"
end_tag = "{/* ================= SCREEN 19: REPORTS DIRECTORY (G R) ================= */}"

idx_start = content.find(start_tag)
idx_end = content.find(end_tag)

if idx_start == -1 or idx_end == -1:
    print(f"Error: tags not found. start={idx_start}, end={idx_end}")
    sys.exit(1)

new_dashboard = '''{/* ================= SCREEN 18: DASHBOARD (G D) ================= */}
              {currentScreen === 'dashboard' && (() => {
                // Recompute metrics based on period and branch
                const periodMultipliers = {
                  today: { sales: 84250, profit: 31170, tx: 63, basket: 1337, dues: 281900, collections: 18500, expenses: 6250, stock: 1845200, lowStock: 8, deltaSales: '+14.2%', deltaProfit: '+8.5%', deltaTx: '+12.0%', deltaBasket: '+2.1%', deltaDues: '-3.4%', deltaCollections: '+22.0%', deltaExpenses: '-5.1%', deltaStock: '+1.5%', deltaLow: '+2' },
                  yesterday: { sales: 73800, profit: 27300, tx: 55, basket: 1341, dues: 295000, collections: 15200, expenses: 4800, stock: 1852000, lowStock: 7, deltaSales: '-2.1%', deltaProfit: '-1.5%', deltaTx: '-3.0%', deltaBasket: '+0.9%', deltaDues: '+1.2%', deltaCollections: '+5.0%', deltaExpenses: '+8.2%', deltaStock: '+0.5%', deltaLow: '-1' },
                  '7d': { sales: 588400, profit: 217700, tx: 412, basket: 1428, dues: 281900, collections: 124000, expenses: 42500, stock: 1845200, lowStock: 8, deltaSales: '+18.4%', deltaProfit: '+14.2%', deltaTx: '+15.5%', deltaBasket: '+2.5%', deltaDues: '-6.2%', deltaCollections: '+14.8%', deltaExpenses: '+3.2%', deltaStock: '+2.1%', deltaLow: '+3' },
                  '30d': { sales: 2450000, profit: 906500, tx: 1840, basket: 1331, dues: 281900, collections: 485000, expenses: 186000, stock: 1845200, lowStock: 8, deltaSales: '+22.5%', deltaProfit: '+19.8%', deltaTx: '+21.0%', deltaBasket: '+1.2%', deltaDues: '-11.5%', deltaCollections: '+19.0%', deltaExpenses: '-2.4%', deltaStock: '+4.0%', deltaLow: '0' },
                  this_month: { sales: 2180000, profit: 806600, tx: 1620, basket: 1345, dues: 281900, collections: 420000, expenses: 162000, stock: 1845200, lowStock: 8, deltaSales: '+20.1%', deltaProfit: '+17.4%', deltaTx: '+18.2%', deltaBasket: '+1.6%', deltaDues: '-9.8%', deltaCollections: '+16.5%', deltaExpenses: '-1.8%', deltaStock: '+3.2%', deltaLow: '+1' },
                  custom: { sales: 1250000, profit: 462500, tx: 940, basket: 1330, dues: 281900, collections: 245000, expenses: 88000, stock: 1845200, lowStock: 8, deltaSales: '+15.0%', deltaProfit: '+12.0%', deltaTx: '+14.0%', deltaBasket: '+0.8%', deltaDues: '-5.0%', deltaCollections: '+11.0%', deltaExpenses: '+1.2%', deltaStock: '+2.0%', deltaLow: '+1' }
                };

                const branchFactors = { all: 1.0, df1: 0.65, df2: 0.25, df3: 0.10 };
                const m = periodMultipliers[dashPeriod] || periodMultipliers.today;
                const bf = branchFactors[dashBranch] || 1.0;

                const curSales = Math.round(m.sales * bf);
                const curProfit = Math.round(m.profit * bf);
                const curTx = Math.max(1, Math.round(m.tx * bf));
                const curBasket = Math.round(curSales / curTx);
                const curDues = Math.round(m.dues * bf);
                const curCollections = Math.round(m.collections * bf);
                const curExpenses = Math.round(m.expenses * bf);
                const curStock = Math.round(m.stock * bf);
                const curLowStock = Math.max(1, Math.round(m.lowStock * bf));

                // Helper for Right-Click Context Menu on every widget
                const handleWidgetMenu = (e, widgetName, exportRows = []) => {
                  e.preventDefault();
                  openContextMenu(e, widgetName, [
                    { label: 'Refresh Widget', icon: 'refresh', action: () => addToast('Refreshed ' + widgetName, 'info') },
                    { label: 'Period: Today', action: () => { setDashPeriod('today'); addToast('Period set to Today', 'info'); } },
                    { label: 'Period: 7 Days', action: () => { setDashPeriod('7d'); addToast('Period set to 7 Days', 'info'); } },
                    { label: 'Period: 30 Days', action: () => { setDashPeriod('30d'); addToast('Period set to 30 Days', 'info'); } },
                    { label: 'Period: This Month', action: () => { setDashPeriod('this_month'); addToast('Period set to This Month', 'info'); } },
                    { label: 'Open BI Report', icon: 'fileText', action: () => navigateToScreen('reports') },
                    { label: 'Export Data (CSV)', icon: 'download', action: () => {
                      if (exportRows.length > 0) {
                        exportToCSV(widgetName.toLowerCase().replace(/[^a-z0-9]/g, '_') + '_data.csv', Object.keys(exportRows[0]).map(k => ({ id: k, header: k })), exportRows);
                      } else {
                        addToast('Exported ' + widgetName + ' metrics', 'success');
                      }
                    }}
                  ]);
                };

                return (
                  <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4">
                    {/* Header Bar: Period Selector + Branch Selector */}
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-[var(--border)] shrink-0">
                      <div>
                        <div className="flex items-center gap-2">
                          <h1 className="text-lg font-bold text-[var(--foreground)]">Executive POS Dashboard</h1>
                          <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[var(--primary-soft)] text-[var(--primary)] border border-[var(--primary)]">
                            Live BI
                          </span>
                        </div>
                        <div className="text-xs text-[var(--muted-foreground)] mt-0.5">
                          Multi-branch sales velocity, margin analytics, payment split, and stock alerts
                        </div>
                      </div>

                      <div className="flex flex-wrap items-center gap-2">
                        {/* Period Selector */}
                        <div className="flex items-center gap-1">
                          <span className="text-[11px] font-medium text-[var(--muted-foreground)]">Period:</span>
                          <select
                            value={dashPeriod}
                            onChange={(e) => setDashPeriod(e.target.value)}
                            className="h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] cursor-pointer"
                          >
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                            <option value="this_month">This Month</option>
                            <option value="custom">Custom Range</option>
                          </select>
                        </div>

                        {dashPeriod === 'custom' && (
                          <div className="flex items-center gap-1">
                            <input
                              type="date"
                              value={dashCustomStart}
                              onChange={(e) => setDashCustomStart(e.target.value)}
                              className="h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                            />
                            <span className="text-[10px] text-[var(--muted-foreground)]">to</span>
                            <input
                              type="date"
                              value={dashCustomEnd}
                              onChange={(e) => setDashCustomEnd(e.target.value)}
                              className="h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs text-[var(--foreground)]"
                            />
                          </div>
                        )}

                        {/* Branch Selector */}
                        <div className="flex items-center gap-1">
                          <span className="text-[11px] font-medium text-[var(--muted-foreground)]">Branch:</span>
                          <select
                            value={dashBranch}
                            onChange={(e) => setDashBranch(e.target.value)}
                            className="h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)] cursor-pointer"
                          >
                            <option value="all">All Branches (Consolidated)</option>
                            <option value="df1">Main Branch [DF1]</option>
                            <option value="df2">Dhanmondi [DF2]</option>
                            <option value="df3">Uttara [DF3]</option>
                          </select>
                        </div>

                        <button
                          type="button"
                          onClick={() => addToast('Dashboard data refreshed', 'info')}
                          className="h-8 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-xs font-medium text-[var(--foreground)] flex items-center gap-1.5 cursor-pointer"
                          title="Refresh All Widgets"
                        >
                          <Icon name="refresh" size={13} />
                          <span className="hidden sm:inline">Refresh</span>
                        </button>
                      </div>
                    </div>

                    {/* First-Run Checklist Card */}
                    {!dashChecklistDismissed && (
                      <div className="p-3.5 rounded-xl bg-[var(--surface)] border border-[var(--primary)] shadow-xs relative overflow-hidden shrink-0">
                        <div className="flex items-start justify-between gap-3">
                          <div className="flex items-center gap-2">
                            <div className="w-7 h-7 rounded-lg bg-[var(--primary-soft)] text-[var(--primary)] flex items-center justify-center shrink-0">
                              <Icon name="checkCircle" size={15} />
                            </div>
                            <div>
                              <div className="text-xs font-bold text-[var(--foreground)] flex items-center gap-2">
                                <span>Store Setup Checklist</span>
                                <span className="px-1.5 py-0.2 rounded text-[10px] font-mono font-bold bg-[var(--primary-soft)] text-[var(--primary)]">
                                  3 of 4 Completed (75%)
                                </span>
                              </div>
                              <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                                Complete these initial setup steps to unlock all features across POS, branches, and ESC/POS hardware
                              </div>
                            </div>
                          </div>

                          <button
                            type="button"
                            onClick={() => setDashChecklistDismissed(true)}
                            className="text-xs text-[var(--muted-foreground)] hover:text-[var(--foreground)] cursor-pointer p-1"
                            title="Dismiss Checklist"
                          >
                            ✕
                          </button>
                        </div>

                        {/* Progress Bar */}
                        <div className="w-full h-1.5 bg-[var(--surface-sunken)] rounded-full mt-3 overflow-hidden">
                          <div className="h-full bg-[var(--primary)] rounded-full w-3/4 transition-all duration-300"></div>
                        </div>

                        {/* Checklist Items */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 mt-3 text-xs">
                          <div className="flex items-center justify-between p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                            <div className="flex items-center gap-2">
                              <span className="w-4 h-4 rounded-full bg-[var(--success-soft)] text-[var(--success)] font-bold text-[10px] flex items-center justify-center">✓</span>
                              <span className="font-medium text-[var(--foreground)]">Add Store Branches</span>
                            </div>
                            <button
                              type="button"
                              onClick={() => { navigateToScreen('settings'); setSettingsTab('branches'); }}
                              className="text-[10px] text-[var(--primary)] hover:underline cursor-pointer"
                            >
                              View
                            </button>
                          </div>

                          <div className="flex items-center justify-between p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                            <div className="flex items-center gap-2">
                              <span className="w-4 h-4 rounded-full bg-[var(--success-soft)] text-[var(--success)] font-bold text-[10px] flex items-center justify-center">✓</span>
                              <span className="font-medium text-[var(--foreground)]">Add Catalog Products</span>
                            </div>
                            <button
                              type="button"
                              onClick={() => navigateToScreen('products')}
                              className="text-[10px] text-[var(--primary)] hover:underline cursor-pointer"
                            >
                              View
                            </button>
                          </div>

                          <div className="flex items-center justify-between p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                            <div className="flex items-center gap-2">
                              <span className="w-4 h-4 rounded-full bg-[var(--success-soft)] text-[var(--success)] font-bold text-[10px] flex items-center justify-center">✓</span>
                              <span className="font-medium text-[var(--foreground)]">Add Staff Users</span>
                            </div>
                            <button
                              type="button"
                              onClick={() => navigateToScreen('users')}
                              className="text-[10px] text-[var(--primary)] hover:underline cursor-pointer"
                            >
                              View
                            </button>
                          </div>

                          <div className="flex items-center justify-between p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--warning)]">
                            <div className="flex items-center gap-2">
                              <span className="w-4 h-4 rounded-full bg-[var(--warning-soft)] text-[var(--warning)] font-bold text-[10px] flex items-center justify-center">⚙</span>
                              <span className="font-medium text-[var(--foreground)]">Connect Thermal Printer</span>
                            </div>
                            <button
                              type="button"
                              onClick={() => { navigateToScreen('settings'); setSettingsTab('receipt_printers'); }}
                              className="h-5 px-2 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-[10px] font-bold cursor-pointer"
                            >
                              Connect
                            </button>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* 9 KPIs with deltas */}
                    <div className="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 2xl:grid-cols-9 gap-3">
                      {[
                        { title: 'Gross Sales', val: formatMoney(curSales), delta: m.deltaSales, pos: true, sub: curTx + ' transactions', name: 'Gross Sales' },
                        { title: 'Gross Profit', val: formatMoney(curProfit), delta: m.deltaProfit, pos: true, sub: '37% avg margin', name: 'Gross Profit' },
                        { title: 'Transactions', val: '' + curTx, delta: m.deltaTx, pos: true, sub: 'Invoices billed', name: 'Transactions' },
                        { title: 'Avg Basket', val: formatMoney(curBasket), delta: m.deltaBasket, pos: true, sub: 'Per transaction', name: 'Avg Basket' },
                        { title: 'Dues Outstanding', val: formatMoney(curDues), delta: m.deltaDues, pos: false, sub: '14 active debtors', name: 'Dues Outstanding' },
                        { title: 'Collections Today', val: formatMoney(curCollections), delta: m.deltaCollections, pos: true, sub: 'Settled dues', name: 'Collections Today' },
                        { title: 'Expenses Outflow', val: formatMoney(curExpenses), delta: m.deltaExpenses, pos: false, sub: 'Daily disbursements', name: 'Expenses' },
                        { title: 'FIFO Stock Value', val: formatMoney(curStock), delta: m.deltaStock, pos: true, sub: '42 SKUs on hand', name: 'Stock Value' },
                        { title: 'Low-Stock Count', val: curLowStock + ' SKUs', delta: m.deltaLow, pos: false, sub: 'Below threshold', name: 'Low Stock' },
                      ].map((kpi, idx) => (
                        <div
                          key={idx}
                          onContextMenu={(e) => handleWidgetMenu(e, kpi.name, [{ Metric: kpi.title, Value: kpi.val, Delta: kpi.delta }])}
                          className="p-3 rounded-xl bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] transition-all shadow-xs flex flex-col justify-between cursor-default group"
                        >
                          <div>
                            <div className="flex items-center justify-between">
                              <span className="text-[11px] font-semibold text-[var(--muted-foreground)] truncate">{kpi.title}</span>
                              <span className="text-[9px] text-[var(--muted-foreground)] opacity-0 group-hover:opacity-100 transition-opacity">⋮</span>
                            </div>
                            <div className="text-base font-bold font-mono text-[var(--foreground)] mt-1 truncate num">
                              {kpi.val}
                            </div>
                          </div>
                          <div className="mt-2 pt-1 border-t border-[var(--border)] flex items-center justify-between text-[10px]">
                            <span className={kpi.pos ? 'text-[var(--success)] font-semibold' : 'text-[var(--danger)] font-semibold'}>
                              {kpi.delta}
                            </span>
                            <span className="text-[var(--muted-foreground)] truncate text-[9px]">{kpi.sub}</span>
                          </div>
                        </div>
                      ))}
                    </div>

                    {/* Charts Section: 5 SVG Charts (No empty axes!) */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
                      {/* Chart 1: Sales Trend Line (Inline SVG with Area Gradient & Full Axes) */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Sales Trend Line')}
                        className="lg:col-span-2 p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Revenue Velocity & Sales Trend</div>
                            <div className="text-[11px] text-[var(--muted-foreground)]">Intraday hourly progression vs yesterday's run rate</div>
                          </div>
                          <div className="flex items-center gap-2 text-[10px] font-mono">
                            <span className="flex items-center gap-1">
                              <span className="w-2.5 h-2.5 rounded-full bg-[var(--primary)] inline-block"></span>
                              <span>Today ({formatMoney(curSales)})</span>
                            </span>
                            <span className="flex items-center gap-1 text-[var(--muted-foreground)]">
                              <span className="w-2.5 h-0.5 bg-[var(--border)] inline-block"></span>
                              <span>Target (৳1,00,000)</span>
                            </span>
                          </div>
                        </div>

                        {/* Inline SVG Chart with Area, Gridlines, Y-ticks and X-ticks */}
                        <div className="w-full h-56 pt-2">
                          <svg viewBox="0 0 650 200" className="w-full h-full overflow-visible select-none">
                            <defs>
                              <linearGradient id="trendGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stopColor="var(--primary)" stopOpacity="0.3" />
                                <stop offset="100%" stopColor="var(--primary)" stopOpacity="0.0" />
                              </linearGradient>
                            </defs>

                            {/* Horizontal Gridlines & Y-Axis Labels */}
                            <line x1="50" y1="20" x2="630" y2="20" stroke="var(--border)" strokeDasharray="3 3" />
                            <text x="42" y="24" textAnchor="end" className="text-[10px] fill-[var(--muted-foreground)] font-mono">৳100k</text>

                            <line x1="50" y1="60" x2="630" y2="60" stroke="var(--border)" strokeDasharray="3 3" />
                            <text x="42" y="64" textAnchor="end" className="text-[10px] fill-[var(--muted-foreground)] font-mono">৳75k</text>

                            <line x1="50" y1="100" x2="630" y2="100" stroke="var(--border)" strokeDasharray="3 3" />
                            <text x="42" y="104" textAnchor="end" className="text-[10px] fill-[var(--muted-foreground)] font-mono">৳50k</text>

                            <line x1="50" y1="140" x2="630" y2="140" stroke="var(--border)" strokeDasharray="3 3" />
                            <text x="42" y="144" textAnchor="end" className="text-[10px] fill-[var(--muted-foreground)] font-mono">৳25k</text>

                            <line x1="50" y1="180" x2="630" y2="180" stroke="var(--border)" />
                            <text x="42" y="184" textAnchor="end" className="text-[10px] fill-[var(--muted-foreground)] font-mono">৳0</text>

                            {/* X-Axis Tick Labels */}
                            <text x="50" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">10 AM</text>
                            <text x="146" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">12 PM</text>
                            <text x="242" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">2 PM</text>
                            <text x="338" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">4 PM</text>
                            <text x="434" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">6 PM</text>
                            <text x="530" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">8 PM</text>
                            <text x="626" y="196" textAnchor="middle" className="text-[10px] fill-[var(--muted-foreground)] font-mono">10 PM</text>

                            {/* Shaded Area */}
                            <polygon
                              points="50,180 50,165 98,150 146,128 194,115 242,130 290,122 338,98 386,72 434,45 482,55 530,78 578,112 626,140 626,180"
                              fill="url(#trendGradient)"
                            />

                            {/* Trend Line */}
                            <polyline
                              points="50,165 98,150 146,128 194,115 242,130 290,122 338,98 386,72 434,45 482,55 530,78 578,112 626,140"
                              fill="none"
                              stroke="var(--primary)"
                              strokeWidth="2.5"
                              strokeLinecap="round"
                              strokeLinejoin="round"
                            />

                            {/* Target Benchmark Line */}
                            <line x1="50" y1="40" x2="626" y2="40" stroke="var(--muted-foreground)" strokeWidth="1" strokeDasharray="4 4" opacity="0.6" />

                            {/* Circular Data Nodes */}
                            {[
                              { cx: 50, cy: 165 },
                              { cx: 146, cy: 128 },
                              { cx: 242, cy: 130 },
                              { cx: 338, cy: 98 },
                              { cx: 434, cy: 45 },
                              { cx: 530, cy: 78 },
                              { cx: 626, cy: 140 }
                            ].map((dot, dIdx) => (
                              <circle
                                key={dIdx}
                                cx={dot.cx}
                                cy={dot.cy}
                                r="4"
                                fill="var(--surface)"
                                stroke="var(--primary)"
                                strokeWidth="2"
                                className="cursor-pointer hover:r-6 transition-all"
                              />
                            ))}
                          </svg>
                        </div>
                      </div>

                      {/* Chart 2: Payment-Method Mix (Donut Chart SVG + Legend) */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Payment Method Mix')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Payment Method Mix</div>
                            <div className="text-[11px] text-[var(--muted-foreground)]">Cash vs bKash vs Card split</div>
                          </div>
                          <span className="text-[10px] font-mono text-[var(--muted-foreground)]">100% Total</span>
                        </div>

                        {/* Inline Donut SVG */}
                        <div className="flex items-center justify-center py-2 relative">
                          <svg viewBox="0 0 160 160" className="w-36 h-36 transform -rotate-90">
                            {/* Background circle */}
                            <circle cx="80" cy="80" r="60" fill="transparent" stroke="var(--surface-sunken)" strokeWidth="18" />

                            {/* Cash: 58% (377 * 0.58 = 218.66) */}
                            <circle
                              cx="80"
                              cy="80"
                              r="60"
                              fill="transparent"
                              stroke="var(--success)"
                              strokeWidth="18"
                              strokeDasharray="218.66 377"
                              strokeDashoffset="0"
                            />

                            {/* bKash: 26% (377 * 0.26 = 98.02) */}
                            <circle
                              cx="80"
                              cy="80"
                              r="60"
                              fill="transparent"
                              stroke="var(--primary)"
                              strokeWidth="18"
                              strokeDasharray="98.02 377"
                              strokeDashoffset="-218.66"
                            />

                            {/* Card: 16% (377 * 0.16 = 60.32) */}
                            <circle
                              cx="80"
                              cy="80"
                              r="60"
                              fill="transparent"
                              stroke="var(--info)"
                              strokeWidth="18"
                              strokeDasharray="60.32 377"
                              strokeDashoffset="-316.68"
                            />
                          </svg>

                          {/* Center Text */}
                          <div className="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                            <span className="text-[10px] text-[var(--muted-foreground)] font-medium">Tendered</span>
                            <span className="text-xs font-bold font-mono text-[var(--foreground)] num">{formatMoney(curSales)}</span>
                          </div>
                        </div>

                        {/* Payment Breakdown Legend */}
                        <div className="space-y-1.5 pt-2 border-t border-[var(--border)] text-xs">
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-1.5">
                              <span className="w-2.5 h-2.5 rounded-full bg-[var(--success)] inline-block"></span>
                              <span className="font-medium text-[var(--foreground)]">Cash Tender (58%)</span>
                            </div>
                            <span className="font-mono text-[var(--muted-foreground)] num">{formatMoney(curSales * 0.58)}</span>
                          </div>
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-1.5">
                              <span className="w-2.5 h-2.5 rounded-full bg-[var(--primary)] inline-block"></span>
                              <span className="font-medium text-[var(--foreground)]">bKash QR / Merchant (26%)</span>
                            </div>
                            <span className="font-mono text-[var(--muted-foreground)] num">{formatMoney(curSales * 0.26)}</span>
                          </div>
                          <div className="flex items-center justify-between">
                            <div className="flex items-center gap-1.5">
                              <span className="w-2.5 h-2.5 rounded-full bg-[var(--info)] inline-block"></span>
                              <span className="font-medium text-[var(--foreground)]">Debit / Credit Card (16%)</span>
                            </div>
                            <span className="font-mono text-[var(--muted-foreground)] num">{formatMoney(curSales * 0.16)}</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Chart Row 2: Hourly Heat-Map + Branch Comparison + District Split */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
                      {/* Chart 3: Hourly Heat-Map */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Hourly Heat-Map')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Hourly Sales Heat-Map</div>
                            <div className="text-[11px] text-[var(--muted-foreground)]">Traffic density across operating hours</div>
                          </div>
                          <div className="flex items-center gap-1 text-[10px] text-[var(--muted-foreground)]">
                            <span>Low</span>
                            <span className="w-2 h-2 rounded bg-[var(--primary)] opacity-20"></span>
                            <span className="w-2 h-2 rounded bg-[var(--primary)] opacity-50"></span>
                            <span className="w-2 h-2 rounded bg-[var(--primary)] opacity-90"></span>
                            <span>High</span>
                          </div>
                        </div>

                        {/* Heatmap Grid with Days on Y and Hours on X */}
                        <div className="space-y-1 pt-1">
                          {/* X-axis hour headers */}
                          <div className="grid grid-cols-8 gap-1 text-[9px] font-mono text-[var(--muted-foreground)] text-center">
                            <span>Day</span>
                            <span>10am</span>
                            <span>12pm</span>
                            <span>2pm</span>
                            <span>4pm</span>
                            <span>6pm</span>
                            <span>8pm</span>
                            <span>10pm</span>
                          </div>

                          {/* Day Rows */}
                          {[
                            { day: 'Sat', opacities: [0.2, 0.4, 0.5, 0.8, 0.95, 0.85, 0.3] },
                            { day: 'Sun', opacities: [0.3, 0.5, 0.4, 0.7, 0.85, 0.75, 0.2] },
                            { day: 'Mon', opacities: [0.1, 0.3, 0.4, 0.6, 0.7, 0.65, 0.2] },
                            { day: 'Tue', opacities: [0.2, 0.4, 0.5, 0.65, 0.75, 0.6, 0.2] },
                            { day: 'Wed', opacities: [0.2, 0.3, 0.4, 0.7, 0.8, 0.7, 0.3] },
                            { day: 'Thu', opacities: [0.4, 0.6, 0.6, 0.85, 0.95, 0.9, 0.4] },
                            { day: 'Fri', opacities: [0.5, 0.7, 0.8, 0.95, 1.0, 0.95, 0.5] }
                          ].map((row, rIdx) => (
                            <div key={rIdx} className="grid grid-cols-8 gap-1 items-center text-[10px]">
                              <span className="font-mono text-[var(--muted-foreground)] text-[10px] text-left">{row.day}</span>
                              {row.opacities.map((op, oIdx) => (
                                <div
                                  key={oIdx}
                                  style={{ opacity: op }}
                                  className="h-5 rounded bg-[var(--primary)] hover:ring-1 hover:ring-[var(--foreground)] transition-all cursor-pointer"
                                  title={row.day + ' slot: ~' + Math.round(op * 15) + ' sales'}
                                />
                              ))}
                            </div>
                          ))}
                        </div>
                      </div>

                      {/* Chart 4: Branch Comparison Bars (SVG) */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Branch Comparison Bars')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Branch Performance Comparison</div>
                            <div className="text-[11px] text-[var(--muted-foreground)]">Sales vs Target vs Gross Profit</div>
                          </div>
                          <span className="text-[10px] font-mono text-[var(--muted-foreground)]">3 Outlets</span>
                        </div>

                        {/* Inline Bar Chart SVG */}
                        <div className="w-full h-44 pt-1">
                          <svg viewBox="0 0 320 160" className="w-full h-full select-none">
                            {/* Gridlines */}
                            <line x1="45" y1="20" x2="310" y2="20" stroke="var(--border)" strokeDasharray="2 2" />
                            <text x="38" y="24" textAnchor="end" className="text-[9px] fill-[var(--muted-foreground)] font-mono">৳60k</text>

                            <line x1="45" y1="65" x2="310" y2="65" stroke="var(--border)" strokeDasharray="2 2" />
                            <text x="38" y="69" textAnchor="end" className="text-[9px] fill-[var(--muted-foreground)] font-mono">৳40k</text>

                            <line x1="45" y1="110" x2="310" y2="110" stroke="var(--border)" strokeDasharray="2 2" />
                            <text x="38" y="114" textAnchor="end" className="text-[9px] fill-[var(--muted-foreground)] font-mono">৳20k</text>

                            <line x1="45" y1="140" x2="310" y2="140" stroke="var(--border)" />
                            <text x="38" y="144" textAnchor="end" className="text-[9px] fill-[var(--muted-foreground)] font-mono">৳0</text>

                            {/* DF1 Main: Sales(115px), Target(125px), Profit(45px) */}
                            <rect x="65" y="35" width="14" height="105" rx="2" fill="var(--primary)" />
                            <rect x="82" y="25" width="14" height="115" rx="2" fill="var(--border)" />
                            <rect x="99" y="98" width="14" height="42" rx="2" fill="var(--success)" />
                            <text x="90" y="154" textAnchor="middle" className="text-[9px] fill-[var(--foreground)] font-bold">DF1 Main</text>

                            {/* DF2 Dhanmondi */}
                            <rect x="150" y="85" width="14" height="55" rx="2" fill="var(--primary)" />
                            <rect x="167" y="75" width="14" height="65" rx="2" fill="var(--border)" />
                            <rect x="184" y="118" width="14" height="22" rx="2" fill="var(--success)" />
                            <text x="175" y="154" textAnchor="middle" className="text-[9px] fill-[var(--foreground)] font-bold">DF2 Dhan</text>

                            {/* DF3 Uttara */}
                            <rect x="235" y="115" width="14" height="25" rx="2" fill="var(--primary)" />
                            <rect x="252" y="105" width="14" height="35" rx="2" fill="var(--border)" />
                            <rect x="269" y="130" width="14" height="10" rx="2" fill="var(--success)" />
                            <text x="260" y="154" textAnchor="middle" className="text-[9px] fill-[var(--foreground)] font-bold">DF3 Uttara</text>
                          </svg>
                        </div>

                        {/* Legend */}
                        <div className="flex items-center justify-center gap-3 pt-2 border-t border-[var(--border)] text-[10px]">
                          <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded bg-[var(--primary)]"></span>
                            <span>Sales</span>
                          </span>
                          <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded bg-[var(--border)]"></span>
                            <span>Target</span>
                          </span>
                          <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded bg-[var(--success)]"></span>
                            <span>Gross Profit</span>
                          </span>
                        </div>
                      </div>

                      {/* Chart 5: Sales by District */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Sales by District')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between mb-2">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Sales Distribution by District</div>
                            <div className="text-[11px] text-[var(--muted-foreground)]">Regional revenue share across Bangladesh</div>
                          </div>
                          <span className="text-[10px] font-mono text-[var(--muted-foreground)]">5 Regions</span>
                        </div>

                        <div className="space-y-2.5 pt-1 text-xs">
                          {[
                            { district: 'Dhaka North (Uttara, Gulshan)', pct: 48, amount: curSales * 0.48 },
                            { district: 'Dhaka South (Dhanmondi, Mirpur)', pct: 28, amount: curSales * 0.28 },
                            { district: 'Chittagong Metro', pct: 12, amount: curSales * 0.12 },
                            { district: 'Sylhet Metro', pct: 7, amount: curSales * 0.07 },
                            { district: 'Rajshahi Metro', pct: 5, amount: curSales * 0.05 }
                          ].map((dst, idx) => (
                            <div key={idx} className="space-y-1">
                              <div className="flex items-center justify-between text-[11px]">
                                <span className="font-semibold text-[var(--foreground)]">{dst.district}</span>
                                <span className="font-mono text-[var(--muted-foreground)] num">
                                  {formatMoney(dst.amount)} ({dst.pct}%)
                                </span>
                              </div>
                              <div className="w-full h-2 rounded-full bg-[var(--surface-sunken)] overflow-hidden">
                                <div
                                  style={{ width: `${dst.pct}%` }}
                                  className="h-full rounded-full bg-[var(--primary)]"
                                />
                              </div>
                            </div>
                          ))}
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] flex items-center justify-between text-[10px] text-[var(--muted-foreground)]">
                          <span>Source: Registered Delivery & Walk-in Invoices</span>
                          <span className="font-semibold text-[var(--primary)] cursor-pointer" onClick={() => navigateToScreen('reports')}>
                            District Report →
                          </span>
                        </div>
                      </div>
                    </div>

                    {/* Lists Section: 5 Lists Required by Spec */}
                    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                      {/* List 1: Top Products */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Top Products')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Top Selling Products</div>
                            <div className="text-[10px] text-[var(--muted-foreground)]">Ranked by total revenue</div>
                          </div>
                          <span className="text-[10px] font-mono text-[var(--primary)] font-bold">Top 5</span>
                        </div>

                        <div className="divide-y divide-[var(--border)] text-xs">
                          {[
                            { rank: 1, name: "Men's Slim Fit Panjabi", sku: 'DF-PAN-01', sold: 42, rev: 60900 },
                            { rank: 2, name: 'Jamdani Handloom Saree', sku: 'DF-SAR-07', sold: 14, rev: 63000 },
                            { rank: 3, name: 'Cotton Formal Shirt', sku: 'DF-SHT-03', sold: 28, rev: 46200 },
                            { rank: 4, name: 'Casual Chino Pant', sku: 'DF-PNT-05', sold: 22, rev: 36300 },
                            { rank: 5, name: 'Embroidered Lawn 3-Piece', sku: 'DF-L3P-09', sold: 11, rev: 38500 }
                          ].map(prod => (
                            <div key={prod.sku} className="py-2 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded px-1 transition-colors">
                              <div className="flex items-center gap-2">
                                <span className="w-5 h-5 rounded-full bg-[var(--surface-sunken)] font-mono font-bold text-[10px] text-[var(--muted-foreground)] flex items-center justify-center">
                                  {prod.rank}
                                </span>
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">{prod.name}</div>
                                  <div className="text-[10px] font-mono text-[var(--primary)]">{prod.sku} · {prod.sold} sold</div>
                                </div>
                              </div>
                              <div className="text-right font-mono font-bold text-[var(--foreground)] num">
                                {formatMoney(prod.rev)}
                              </div>
                            </div>
                          ))}
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] text-right">
                          <button
                            type="button"
                            onClick={() => navigateToScreen('products')}
                            className="text-[11px] font-semibold text-[var(--primary)] hover:underline cursor-pointer"
                          >
                            All Products Catalog →
                          </button>
                        </div>
                      </div>

                      {/* List 2: Top Customers */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Top Customers')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Top Privilege Customers</div>
                            <div className="text-[10px] text-[var(--muted-foreground)]">Highest lifetime value (LTV)</div>
                          </div>
                          <span className="text-[10px] font-mono text-[var(--primary)] font-bold">Top 5</span>
                        </div>

                        <div className="divide-y divide-[var(--border)] text-xs">
                          {[
                            { rank: 1, name: 'Rahim Uddin', no: '1000 0017', tier: 'Platinum VIP', bills: 18, spend: 142500 },
                            { rank: 2, name: 'Anika Tabassum', no: '1000 0249', tier: 'Gold Privilege', bills: 14, spend: 98400 },
                            { rank: 3, name: 'Farzana Akter', no: '1000 0025', tier: 'Gold Privilege', bills: 11, spend: 76200 },
                            { rank: 4, name: 'Tanvir Ahmed', no: '1000 0033', tier: 'Silver Member', bills: 9, spend: 54300 },
                            { rank: 5, name: 'Sadia Islam', no: '1000 0041', tier: 'Silver Member', bills: 8, spend: 49800 }
                          ].map(c => (
                            <div key={c.no} className="py-2 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded px-1 transition-colors">
                              <div className="flex items-center gap-2">
                                <span className="w-5 h-5 rounded-full bg-[var(--surface-sunken)] font-mono font-bold text-[10px] text-[var(--muted-foreground)] flex items-center justify-center">
                                  {c.rank}
                                </span>
                                <div>
                                  <div className="font-semibold text-[var(--foreground)]">{c.name}</div>
                                  <div className="text-[10px] font-mono text-[var(--muted-foreground)]">{c.no} · {c.tier}</div>
                                </div>
                              </div>
                              <div className="text-right">
                                <div className="font-mono font-bold text-[var(--foreground)] num">{formatMoney(c.spend)}</div>
                                <div className="text-[9px] text-[var(--muted-foreground)]">{c.bills} visits</div>
                              </div>
                            </div>
                          ))}
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] text-right">
                          <button
                            type="button"
                            onClick={() => navigateToScreen('customers')}
                            className="text-[11px] font-semibold text-[var(--primary)] hover:underline cursor-pointer"
                          >
                            All Members List →
                          </button>
                        </div>
                      </div>

                      {/* List 3: Low-Stock with "Reorder" */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Low-Stock Alerts')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Low Stock Alerts</div>
                            <div className="text-[10px] text-[var(--warning)] font-semibold">Immediate reorder recommended</div>
                          </div>
                          <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-[var(--warning-soft)] text-[var(--warning)]">
                            {curLowStock} Critical
                          </span>
                        </div>

                        <div className="divide-y divide-[var(--border)] text-xs">
                          {[
                            { sku: 'DF-PAN-02', name: 'Silk Blend Festive Panjabi', stock: 2, min: 10, cost: 1850 },
                            { sku: 'DF-SAR-11', name: 'Katan Banarasi Bridal Saree', stock: 1, min: 5, cost: 6500 },
                            { sku: 'DF-POL-04', name: 'Classic Pique Polo Shirt', stock: 3, min: 15, cost: 550 },
                            { sku: 'DF-L3P-08', name: 'Georgette Party 3-Piece', stock: 2, min: 8, cost: 2400 },
                            { sku: 'DF-ACC-01', name: 'Handcrafted Leather Belt', stock: 4, min: 12, cost: 420 }
                          ].map(item => (
                            <div key={item.sku} className="py-2 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded px-1 transition-colors">
                              <div>
                                <div className="font-semibold text-[var(--foreground)]">{item.name}</div>
                                <div className="text-[10px] font-mono text-[var(--danger)]">
                                  {item.stock} in stock (Min {item.min}) · Unit: {formatMoney(item.cost)}
                                </div>
                              </div>
                              <button
                                type="button"
                                onClick={() => {
                                  addToast('Created Purchase Order draft for ' + item.name, 'success');
                                  navigateToScreen('purchases');
                                }}
                                className="h-6 px-2.5 rounded bg-[var(--primary)] text-[var(--primary-foreground)] text-[10px] font-bold flex items-center gap-1 shadow-xs hover:bg-[var(--primary-hover)] cursor-pointer"
                              >
                                <Icon name="shoppingBag" size={11} />
                                <span>Reorder</span>
                              </button>
                            </div>
                          ))}
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] text-right">
                          <button
                            type="button"
                            onClick={() => navigateToScreen('stock')}
                            className="text-[11px] font-semibold text-[var(--primary)] hover:underline cursor-pointer"
                          >
                            Stock Management Ledger →
                          </button>
                        </div>
                      </div>

                      {/* List 4: Recent Sales */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Recent Sales')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between"
                      >
                        <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Recent Sales Invoices</div>
                            <div className="text-[10px] text-[var(--muted-foreground)]">Latest register checkouts</div>
                          </div>
                          <span className="text-[10px] font-mono text-[var(--muted-foreground)]">Live</span>
                        </div>

                        <div className="divide-y divide-[var(--border)] text-xs">
                          {MOCK_SALES.slice(0, 5).map(sale => (
                            <div key={sale.id} className="py-2 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded px-1 transition-colors">
                              <div>
                                <div
                                  onClick={() => { setSelectedSale(sale); navigateToScreen('sale_detail'); }}
                                  className="font-mono font-bold text-[var(--primary)] hover:underline cursor-pointer"
                                >
                                  {sale.invoice}
                                </div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">
                                  {sale.customer || 'Walk-in'} · {sale.payment_method} · {sale.date}
                                </div>
                              </div>
                              <div className="text-right">
                                <div className="font-mono font-bold text-[var(--foreground)] num">{formatMoney(sale.total)}</div>
                                <span className="px-1.5 py-0.2 rounded text-[9px] font-bold bg-[var(--success-soft)] text-[var(--success)]">
                                  {sale.status}
                                </span>
                              </div>
                            </div>
                          ))}
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] text-right">
                          <button
                            type="button"
                            onClick={() => navigateToScreen('sales')}
                            className="text-[11px] font-semibold text-[var(--primary)] hover:underline cursor-pointer"
                          >
                            All Sales Transactions →
                          </button>
                        </div>
                      </div>

                      {/* List 5: Dues to Chase with "Send reminder" */}
                      <div
                        onContextMenu={(e) => handleWidgetMenu(e, 'Dues to Chase')}
                        className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] shadow-xs flex flex-col justify-between md:col-span-2 xl:col-span-2"
                      >
                        <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                          <div>
                            <div className="text-xs font-bold text-[var(--foreground)]">Overdue Balances to Chase</div>
                            <div className="text-[10px] text-[var(--danger)] font-medium">Customer dues requiring follow-up</div>
                          </div>
                          <span className="text-[10px] font-mono font-bold text-[var(--danger)]">{formatMoney(curDues)} Total</span>
                        </div>

                        <div className="divide-y divide-[var(--border)] text-xs">
                          {[
                            { name: 'Mohammad Hossain', no: '1000 0058', phone: '01811-334455', due: 38500, days: 42 },
                            { name: 'Shirin Sultana', no: '1000 0165', phone: '01712-445566', due: 28400, days: 35 },
                            { name: 'Ziaul Karim', no: '1000 0173', phone: '01913-556677', due: 21500, days: 28 },
                            { name: 'Delwar Hossain', no: '1000 0231', phone: '01614-667788', due: 18200, days: 24 },
                            { name: 'Rahim Uddin', no: '1000 0017', phone: '01711-223344', due: 12500, days: 16 }
                          ].map(debtor => (
                            <div key={debtor.no} className="py-2 flex items-center justify-between hover:bg-[var(--surface-sunken)] rounded px-1 transition-colors">
                              <div>
                                <div className="font-semibold text-[var(--foreground)] flex items-center gap-2">
                                  <span>{debtor.name}</span>
                                  <span className="text-[10px] font-mono text-[var(--muted-foreground)]">({debtor.no})</span>
                                </div>
                                <div className="text-[10px] text-[var(--muted-foreground)]">
                                  Phone: {debtor.phone} · <span className="text-[var(--danger)] font-medium">{debtor.days} days overdue</span>
                                </div>
                              </div>
                              <div className="flex items-center gap-3">
                                <div className="font-mono font-bold text-[var(--danger)] text-right num">
                                  {formatMoney(debtor.due)}
                                </div>
                                <button
                                  type="button"
                                  onClick={() => {
                                    addToast('Due reminder SMS dispatched to ' + debtor.name + ' (' + debtor.phone + ')', 'success');
                                  }}
                                  className="h-6 px-2.5 rounded bg-[var(--primary-soft)] border border-[var(--primary)] text-[var(--primary)] text-[10px] font-bold flex items-center gap-1 hover:bg-[var(--primary)] hover:text-[var(--primary-foreground)] transition-colors cursor-pointer"
                                >
                                  <Icon name="messageSquare" size={11} />
                                  <span>Send Reminder</span>
                                </button>
                              </div>
                            </div>
                          ))}
                        </div>

                        <div className="pt-2 border-t border-[var(--border)] text-right">
                          <button
                            type="button"
                            onClick={() => navigateToScreen('due_collection')}
                            className="text-[11px] font-semibold text-[var(--primary)] hover:underline cursor-pointer"
                          >
                            Full Due Aging Ledger →
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })()}
'''

content = content[:idx_start] + new_dashboard + "\n\n              " + content[idx_end:]

with open('scripts/parts/render_money_dashboard_reports.py', 'w', encoding='utf-8') as f:
    f.write(content)

print("Dashboard patched successfully!")
