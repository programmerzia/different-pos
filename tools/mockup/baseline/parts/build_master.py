#!/usr/bin/env python3
import json
import os
import shutil
import sys

# Add scripts/parts to path
sys.path.insert(0, os.path.abspath(os.path.dirname(__file__)))

from styles_and_head import HEAD_HTML
from icons_and_helpers import ICONS_AND_HELPERS_JS
from app_state import APP_STATE_JS
from render_shell import RENDER_SHELL_TOP_JS
from render_pos_sales_returns import RENDER_POS_SALES_RETURNS_JS
from render_customers_messaging import RENDER_CUSTOMERS_MESSAGING_JS
from render_catalog_stock_purchases import RENDER_CATALOG_STOCK_PURCHASES_JS
from render_money_dashboard_reports import RENDER_MONEY_DASHBOARD_REPORTS_JS
from render_admin_and_states import RENDER_ADMIN_AND_STATES_JS
from render_all_modals import RENDER_ALL_MODALS_JS

print("Starting master build of Retail POS Mockup...")

# Load comprehensive datasets
with open('scripts/mock_data.json', 'r', encoding='utf-8') as f:
    mock_data = json.load(f)

json_str = json.dumps(mock_data, ensure_ascii=False)

data_declaration = f"""
    // Embedded Mock Datasets (42 Products, 32 Customers, 65 Sales, 10 Vendors, 26 Purchases, Accounts, Expenses, Assets, Users, Audit)
    const RAW_DATA = {json_str};
    const CATEGORIES = RAW_DATA.CATEGORIES;
    const INITIAL_PRODUCTS = RAW_DATA.PRODUCTS;
    const MOCK_CUSTOMERS = RAW_DATA.CUSTOMERS;
    const MOCK_SALES = RAW_DATA.SALES;
    const MOCK_VENDORS = RAW_DATA.VENDORS;
    const MOCK_PURCHASES = RAW_DATA.PURCHASES;
    const MOCK_ACCOUNTS = RAW_DATA.ACCOUNTS;
    const MOCK_EXPENSES = RAW_DATA.EXPENSES;
    const MOCK_FIXED_ASSETS = RAW_DATA.FIXED_ASSETS;
    const MOCK_USERS = RAW_DATA.USERS;
    const MOCK_AUDIT_LOGS = RAW_DATA.AUDIT_LOGS;
    const AUDIT_LOGS = RAW_DATA.AUDIT_LOGS;
    const MOCK_RETURNS = RAW_DATA.RETURNS || [];
    const RETURNS = RAW_DATA.RETURNS || [];

    const MESSAGE_TEMPLATES = {{
      due_reminder: "Dear Valued Customer, gentle reminder: your outstanding balance at Different Fashion is overdue. Please settle at your earliest convenience. Thank you!",
      festive_eid: "Eid Mubarak from Different Fashion! Enjoy our exclusive new festive Panjabi & Saree collection with special privilege discounts.",
      new_arrival: "New Exclusive Collection has arrived at Different Fashion! Visit our Uttara or Branch 2 showroom today.",
      card_welcome: "Welcome to the Different Fashion Privilege Club! Present your digital card on your next purchase to earn loyalty rewards."
    }};
"""

# Assemble full HTML
assembled_html = (
    HEAD_HTML +
    ICONS_AND_HELPERS_JS +
    data_declaration +
    APP_STATE_JS +
    RENDER_SHELL_TOP_JS +
    RENDER_POS_SALES_RETURNS_JS +
    RENDER_CUSTOMERS_MESSAGING_JS +
    RENDER_CATALOG_STOCK_PURCHASES_JS +
    RENDER_MONEY_DASHBOARD_REPORTS_JS +
    RENDER_ADMIN_AND_STATES_JS +
    RENDER_ALL_MODALS_JS
)

output_path = 'index.html'
docs_path = '../../docs/mockup/index.html'

with open(output_path, 'w', encoding='utf-8') as f:
    f.write(assembled_html)

os.makedirs('../../docs/mockup', exist_ok=True)
shutil.copyfile(output_path, docs_path)

file_size = os.path.getsize(output_path)
line_count = len(assembled_html.splitlines())

print(f"Master build SUCCESS! Written {line_count:,} lines ({file_size:,} bytes) to {output_path} and {docs_path}")
