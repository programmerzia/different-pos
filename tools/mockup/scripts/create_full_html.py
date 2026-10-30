#!/usr/bin/env python3
import json
import os

print("Writing create_full_html.py...")

with open('scripts/mock_data.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

# Data JSON strings
products_json = json.dumps(data['PRODUCTS'], ensure_ascii=False)
categories_json = json.dumps(data['CATEGORIES'], ensure_ascii=False)
customers_json = json.dumps(data['CUSTOMERS'], ensure_ascii=False)
vendors_json = json.dumps(data['VENDORS'], ensure_ascii=False)
sales_json = json.dumps(data['SALES'], ensure_ascii=False)
purchases_json = json.dumps(data['PURCHASES'], ensure_ascii=False)
accounts_json = json.dumps(data['ACCOUNTS'], ensure_ascii=False)
expenses_json = json.dumps(data['EXPENSES'], ensure_ascii=False)
assets_json = json.dumps(data['FIXED_ASSETS'], ensure_ascii=False)
users_json = json.dumps(data['USERS'], ensure_ascii=False)
audit_json = json.dumps(data['AUDIT_LOGS'], ensure_ascii=False)

print("JSON prepared. Ready to assemble HTML.")
