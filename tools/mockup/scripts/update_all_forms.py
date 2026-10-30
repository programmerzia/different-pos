#!/usr/bin/env python3
import re
import os

print("Starting form & validation upgrades...")

# ================= 1. UPDATE app_state.py =================
with open('scripts/parts/app_state.py', 'r', encoding='utf-8') as f:
    app_state = f.read()

target_anchor = "      const [custFormTouched, setCustFormTouched] = useState({});"
product_state_code = """      const [custFormTouched, setCustFormTouched] = useState({});

      // Product Form State & Validation
      const [prodFormNameEn, setProdFormNameEn] = useState('');
      const [prodFormNameBn, setProdFormNameBn] = useState('');
      const [prodFormCategory, setProdFormCategory] = useState('panjabi');
      const [prodFormBrand, setProdFormBrand] = useState('Different Fashion (In-house)');
      const [prodFormVendor, setProdFormVendor] = useState(MOCK_VENDORS[0]?.name || 'Fashion Silk Mills Ltd');
      const [prodFormUnit, setProdFormUnit] = useState('Pieces (Pcs)');
      const [prodFormSku, setProdFormSku] = useState('');
      const [prodFormBarcode, setProdFormBarcode] = useState('');
      const [prodFormCost, setProdFormCost] = useState(950);
      const [prodFormPrice, setProdFormPrice] = useState(1450);
      const [prodFormWholesale, setProdFormWholesale] = useState(1150);
      const [prodFormVatRate, setProdFormVatRate] = useState('15% Standard (NBR Mushak 6.3)');
      const [prodFormStockMain, setProdFormStockMain] = useState(25);
      const [prodFormStockB2, setProdFormStockB2] = useState(12);
      const [prodFormErrors, setProdFormErrors] = useState({});
      const [prodFormTouched, setProdFormTouched] = useState({});

      // Staff Form State & Validation (Modal 22)
      const [staffName, setStaffName] = useState('');
      const [staffRole, setStaffRole] = useState('Cashier');
      const [staffPin, setStaffPin] = useState('');
      const [staffErrors, setStaffErrors] = useState({});

      // Stock Adjustment State & Validation (Modal 14)
      const [adjustPin, setAdjustPin] = useState('');
      const [adjustErrors, setAdjustErrors] = useState({});

      // Stock Transfer State & Validation (Modal 15)
      const [transferErrors, setTransferErrors] = useState({});

      // Expense State & Validation (Modal 21)
      const [newExpErrors, setNewExpErrors] = useState({});

      const openNewProductForm = () => {
        setSelectedProduct(null);
        setProdFormNameEn('');
        setProdFormNameBn('');
        setProdFormCategory('panjabi');
        setProdFormBrand('Different Fashion (In-house)');
        setProdFormVendor(MOCK_VENDORS[0]?.name || 'Fashion Silk Mills Ltd');
        setProdFormUnit('Pieces (Pcs)');
        setProdFormSku('DF-SKU-' + (INITIAL_PRODUCTS.length + 101));
        setProdFormBarcode('8941' + Math.floor(10000000 + Math.random() * 90000000));
        setProdFormCost(950);
        setProdFormPrice(1450);
        setProdFormWholesale(1150);
        setProdFormVatRate('15% Standard (NBR Mushak 6.3)');
        setProdFormStockMain(25);
        setProdFormStockB2(12);
        setProdFormErrors({});
        setProdFormTouched({});
        setCurrentScreen('product_form');
      };

      const openEditProductForm = (p) => {
        setSelectedProduct(p);
        setProdFormNameEn(p.name_en || '');
        setProdFormNameBn(p.name_bn || '');
        setProdFormCategory(p.category || 'panjabi');
        setProdFormBrand(p.brand || 'Different Fashion (In-house)');
        setProdFormVendor(p.vendor || MOCK_VENDORS[0]?.name || 'Fashion Silk Mills Ltd');
        setProdFormUnit(p.unit || 'Pieces (Pcs)');
        setProdFormSku(p.sku || '');
        setProdFormBarcode(p.barcode || '');
        setProdFormCost(p.cost || p.cost_price || 950);
        setProdFormPrice(p.price || 1450);
        setProdFormWholesale(p.wholesale_price || Math.round((p.cost || p.cost_price || 950) * 1.2));
        setProdFormVatRate('15% Standard (NBR Mushak 6.3)');
        setProdFormStockMain(p.stock_main != null ? p.stock_main : 20);
        setProdFormStockB2(p.stock_b2 != null ? p.stock_b2 : 10);
        setProdFormErrors({});
        setProdFormTouched({});
        setCurrentScreen('product_form');
      };"""

if target_anchor in app_state and "prodFormNameEn" not in app_state:
    app_state = app_state.replace(target_anchor, product_state_code)
    with open('scripts/parts/app_state.py', 'w', encoding='utf-8') as f:
        f.write(app_state)
    print("✓ app_state.py updated with product, staff, expense & adjustment state and helper hooks.")
else:
    print("ℹ app_state.py already contains product state or anchor not found.")

print("Finished app_state updates.")
