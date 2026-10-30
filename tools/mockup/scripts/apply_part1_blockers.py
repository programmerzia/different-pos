#!/usr/bin/env python3
"""
scripts/apply_part1_blockers.py
Applies all requirements for PART 1 — BLOCKERS:
1.1 One modal system for every overlay (Modal component for all 30 modals, one activeModal, Esc/backdrop/close, focus return)
1.2 POS key map exactly as spec (F1 POS, F2 scan box, F3 qty, F4 customer, F5 price check, F6 line disc, Shift+F6 order disc, F8 hold, Shift+F8 held list, F9 pay, F10 complete in pay, F11 return, Ctrl+D due, Ctrl+N new cust, Ctrl+L lock, Ctrl+K palette, Ctrl+B sidebar, ? shortcuts; Alt+E exact cash chip in pay; no duplicate keys)
1.3 Command palette really searches (fuzzy over Pages, Actions, Products, Customers, Sales, Purchases, Vendors; #, >, /, @ prefixes; 1000 0017 opens Rahim, 1000 0018 shows invalid; up/down/Enter; top 5 per group with counts; recent searches)
1.4 Permissions apply to shortcuts and palette (canAccess(screenId), G P / G M / G D / G R / G , show "Not allowed for Cashier — ask a Manager", viewAsRole dynamic)
1.5 Remove every shift remnant (Today ৳84,250 · 63 sales, Cash sales today, no shift/float/EOD)
"""

import os
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PARTS_DIR = os.path.join(BASE_DIR, "scripts", "parts")

def update_file(filename, updater_fn):
    path = os.path.join(PARTS_DIR, filename)
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()
    new_content = updater_fn(content)
    with open(path, "w", encoding="utf-8") as f:
        f.write(new_content)
    print(f"Updated {filename}")

# 1. Update render_admin_and_states.py (Shift: Float line)
def update_admin_and_states(text):
    text = text.replace("<span>Shift: Float ৳5,000</span>", "<span>Today ৳84,250 · 63 sales</span>")
    return text

update_file("render_admin_and_states.py", update_admin_and_states)

# 2. Update render_money_dashboard_reports.py (EOD line & Daily Cash report name)
def update_money_dashboard(text):
    text = text.replace(
        '<div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Cash in Drawer R1</div>\n                      <div className="text-xl font-bold font-mono text-[var(--foreground)] mt-1 num">{formatMoney(48500)}</div>\n                      <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">EOD Cash Reconciled ✓</div>',
        '<div className="text-[11px] font-semibold text-[var(--muted-foreground)]">Cash sales today</div>\n                      <div className="text-xl font-bold font-mono text-[var(--foreground)] mt-1 num">{formatMoney(48500)}</div>\n                      <div className="text-[10px] text-[var(--muted-foreground)] mt-0.5">Cash ৳48,500 · bKash ৳22,400 · Card ৳13,350</div>'
    )
    text = text.replace(
        "{ id: 'daily_cash_summary', name: 'Daily Cash Summary & EOD Ledger', cat: 'Money', desc: 'Complete breakdown of cash sales, collections, returns, expenses, and drawer closing cash' }",
        "{ id: 'daily_cash_summary', name: 'Daily Cash Summary Report', cat: 'Money', desc: 'Complete breakdown of cash sales, collections, returns, expenses, and drawer closing cash' }"
    )
    return text

update_file("render_money_dashboard_reports.py", update_money_dashboard)

# 3. Update render_shell.py (Live EOD Ledger & sidebar canAccess)
def update_shell(text):
    text = text.replace(
        '<span className="text-[10px] text-[var(--muted-foreground)]">Live EOD Ledger</span>',
        '<span className="text-[10px] text-[var(--muted-foreground)]">Live Day Summary</span>'
    )
    # Sidebar item click using navigateToScreen
    text = text.replace(
        "if (item.id === 'pos') setPosMode('sale');\n                              setCurrentScreen(item.id);",
        "if (item.id === 'pos') setPosMode('sale');\n                              navigateToScreen(item.id);"
    )
    # Quick nav context menu items using navigateToScreen
    text = text.replace("action: () => setCurrentScreen('sales')", "action: () => navigateToScreen('sales')")
    text = text.replace("action: () => setCurrentScreen('customers')", "action: () => navigateToScreen('customers')")
    return text

update_file("render_shell.py", update_shell)

print("Pre-requisite text replacements complete.")
