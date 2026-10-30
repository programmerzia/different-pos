#!/usr/bin/env python3
import re

print("Applying catalog, customers, and modal validation upgrades...")

# ================= 1. UPDATE render_catalog_stock_purchases.py =================
with open('scripts/parts/render_catalog_stock_purchases.py', 'r', encoding='utf-8') as f:
    cat_content = f.read()

# Replace Add Product button handler
old_add_btn = """                        <button 
                          onClick={() => {
                            setSelectedProduct(null);
                            setCurrentScreen('product_form');
                          }}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                        >"""
new_add_btn = """                        <button 
                          onClick={() => {
                            openNewProductForm();
                          }}
                          className="h-8 px-3 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold flex items-center gap-1.5 shadow-xs"
                        >"""

if old_add_btn in cat_content:
    cat_content = cat_content.replace(old_add_btn, new_add_btn)

# Replace table click and context menu handlers
old_ctx = "{ label: 'Edit Product', action: () => { setSelectedProduct(p); setCurrentScreen('product_form'); } }"
new_ctx = "{ label: 'Edit Product', action: () => { openEditProductForm(p); } }"
if old_ctx in cat_content:
    cat_content = cat_content.replace(old_ctx, new_ctx)

old_row_click = "onClick={() => { setSelectedProduct(p); setCurrentScreen('product_form'); }}"
new_row_click = "onClick={() => { openEditProductForm(p); }}"
if old_row_click in cat_content:
    cat_content = cat_content.replace(old_row_click, new_row_click)

# Replace the entire product_form block
prod_form_pattern = re.compile(
    r"\{\/\* ================= SCREEN 12: PRODUCT SPECIFICATIONS & FORM.*?\{\/\* ================= SCREEN 13: STOCK & TRANSFERS",
    re.DOTALL
)

new_prod_form = """{/* ================= SCREEN 12: PRODUCT SPECIFICATIONS & FORM (HIGH FIDELITY SELECT FIELDS) ================= */}
              {currentScreen === 'product_form' && (
                <div className="flex-1 flex flex-col overflow-y-auto p-4 space-y-4 max-w-4xl mx-auto w-full">
                  <div className="flex items-center justify-between pb-3 border-b border-[var(--border)]">
                    <div className="flex items-center gap-3">
                      <button onClick={() => setCurrentScreen('products')} className="p-1.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:bg-[var(--surface-sunken)]">
                        <Icon name="arrowLeft" size={16} />
                      </button>
                      <div>
                        <h1 className="text-lg font-bold text-[var(--foreground)]">{selectedProduct ? 'Edit Product Specifications' : 'New Apparel SKU Registration'}</h1>
                        <div className="text-xs text-[var(--muted-foreground)]">Define classification dropdowns, pricing tiers, size & color variants, and branch stock</div>
                      </div>
                    </div>
                    <div className="flex items-center gap-2">
                      <button onClick={() => setCurrentScreen('products')} className="h-8 px-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-medium">
                        Cancel
                      </button>
                      <button
                        type="button"
                        onClick={() => {
                          const errors = {};
                          const cleanNameEn = (prodFormNameEn || '').trim();
                          const cleanSku = (prodFormSku || '').trim();
                          const cost = Number(prodFormCost);
                          const price = Number(prodFormPrice);

                          if (!cleanNameEn || cleanNameEn.length < 3) {
                            errors.name = "Product name (English) is required (at least 3 characters)";
                          }
                          if (!cleanSku) {
                            errors.sku = "SKU identifier is required";
                          }
                          if (isNaN(cost) || cost <= 0) {
                            errors.cost = "Cost price must be greater than ৳0";
                          }
                          if (isNaN(price) || price <= 0) {
                            errors.price = "Retail price must be greater than ৳0";
                          } else if (cost > 0 && price < cost) {
                            errors.price = `Retail price (MRP) cannot be less than cost price (৳${cost})`;
                          }

                          if (Object.keys(errors).length > 0) {
                            setProdFormErrors(errors);
                            setProdFormTouched({ name: true, sku: true, cost: true, price: true });
                            addToast("Please correct validation errors before saving product", "danger");
                            return;
                          }

                          const productObj = {
                            id: selectedProduct?.id || ('prod_' + Date.now()),
                            name_en: cleanNameEn,
                            name_bn: prodFormNameBn.trim() || cleanNameEn,
                            category: prodFormCategory,
                            brand: prodFormBrand,
                            vendor: prodFormVendor,
                            unit: prodFormUnit,
                            sku: cleanSku,
                            barcode: prodFormBarcode || ('894' + Math.floor(100000000 + Math.random() * 900000000)),
                            cost: cost,
                            cost_price: cost,
                            price: price,
                            wholesale_price: Number(prodFormWholesale) || Math.round(cost * 1.2),
                            stock_main: Number(prodFormStockMain) || 0,
                            stock_b2: Number(prodFormStockB2) || 0,
                            total_stock: (Number(prodFormStockMain) || 0) + (Number(prodFormStockB2) || 0)
                          };

                          if (selectedProduct) {
                            const idx = INITIAL_PRODUCTS.findIndex(p => p.id === selectedProduct.id);
                            if (idx !== -1) INITIAL_PRODUCTS[idx] = productObj;
                            const rIdx = RAW_DATA.PRODUCTS.findIndex(p => p.id === selectedProduct.id);
                            if (rIdx !== -1) RAW_DATA.PRODUCTS[rIdx] = productObj;
                            addToast(`Product "${productObj.name_en}" updated successfully!`, "success");
                          } else {
                            INITIAL_PRODUCTS.unshift(productObj);
                            RAW_DATA.PRODUCTS.unshift(productObj);
                            addToast(`New product "${productObj.name_en}" (${productObj.sku}) added to catalog!`, "success");
                          }

                          setSelectedProduct(productObj);
                          setCurrentScreen('products');
                        }}
                        className="h-8 px-4 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-semibold shadow-xs flex items-center gap-1.5 cursor-pointer"
                      >
                        <Icon name="check" size={14} />
                        <span>Save Product</span>
                      </button>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Panel 1: General & Classification (Select Fields) */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                      <div className="font-bold text-xs text-[var(--foreground)] border-b border-[var(--border)] pb-2 flex items-center gap-1.5">
                        <Icon name="tag" size={14} className="text-[var(--primary)]" />
                        <span>Classification & Identity</span>
                      </div>

                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Product Name (English) *</label>
                        <input
                          type="text"
                          value={prodFormNameEn}
                          onChange={(e) => {
                            setProdFormNameEn(e.target.value);
                            if (prodFormErrors.name) setProdFormErrors(prev => ({ ...prev, name: null }));
                          }}
                          placeholder="e.g. Premium Cotton Panjabi Slim Fit"
                          className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs text-[var(--foreground)] font-semibold ${
                            prodFormErrors.name ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                          }`}
                        />
                        {prodFormErrors.name && (
                          <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                            <Icon name="alertCircle" size={11} />
                            <span>{prodFormErrors.name}</span>
                          </div>
                        )}
                      </div>

                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Product Name (বাংলা)</label>
                        <input
                          type="text"
                          value={prodFormNameBn}
                          onChange={(e) => setProdFormNameBn(e.target.value)}
                          placeholder="e.g. প্রিমিয়াম সুতি পাঞ্জাবি"
                          className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-bangla text-[var(--foreground)]"
                        />
                      </div>

                      {/* Category & Brand Dropdowns */}
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Category *</label>
                          <select
                            value={prodFormCategory}
                            onChange={(e) => setProdFormCategory(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            {CATEGORIES.filter(c => c.id !== 'all').map(c => (
                              <option key={c.id} value={c.id}>{c.name_en}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Brand / Label *</label>
                          <select
                            value={prodFormBrand}
                            onChange={(e) => setProdFormBrand(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            <option>Different Fashion (In-house)</option>
                            <option>Cotton Club</option>
                            <option>Richman</option>
                            <option>Lubnan</option>
                            <option>Aarong</option>
                            <option>Apex Footwear</option>
                            <option>Other Supplier Brand</option>
                          </select>
                        </div>
                      </div>

                      {/* Vendor & Unit Dropdowns */}
                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Primary Supplier / Vendor</label>
                          <select
                            value={prodFormVendor}
                            onChange={(e) => setProdFormVendor(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            {MOCK_VENDORS.map(v => (
                              <option key={v.id} value={v.name}>{v.name}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Unit of Measure</label>
                          <select
                            value={prodFormUnit}
                            onChange={(e) => setProdFormUnit(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            <option>Pieces (Pcs)</option>
                            <option>Pair (জোড়া)</option>
                            <option>Set (সেট)</option>
                            <option>Meter (মিটার)</option>
                            <option>Dozen (ডজন)</option>
                          </select>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">SKU Identifier *</label>
                          <input
                            type="text"
                            value={prodFormSku}
                            onChange={(e) => {
                              setProdFormSku(e.target.value);
                              if (prodFormErrors.sku) setProdFormErrors(prev => ({ ...prev, sku: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold text-[var(--foreground)] ${
                              prodFormErrors.sku ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {prodFormErrors.sku && (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{prodFormErrors.sku}</span>
                            </div>
                          )}
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Barcode (EAN-13)</label>
                          <div className="flex gap-1.5 mt-1">
                            <input
                              type="text"
                              value={prodFormBarcode}
                              onChange={(e) => setProdFormBarcode(e.target.value)}
                              className="flex-1 h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-[var(--foreground)]"
                            />
                            <button
                              type="button"
                              onClick={() => setProdFormBarcode('8941' + Math.floor(10000000 + Math.random() * 90000000))}
                              className="px-2 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-[10px] font-semibold hover:bg-[var(--border)]"
                              title="Generate random barcode"
                            >
                              Gen
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>

                    {/* Panel 2: Pricing, VAT & Size Variants */}
                    <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                      <div className="font-bold text-xs text-[var(--foreground)] border-b border-[var(--border)] pb-2 flex items-center justify-between">
                        <div className="flex items-center gap-1.5">
                          <Icon name="dollarSign" size={14} className="text-emerald-600" />
                          <span>Pricing, VAT & Variant Matrix</span>
                        </div>
                        {prodFormCost > 0 && prodFormPrice > 0 && (
                          <span className={`px-2 py-0.5 rounded text-[10px] font-bold font-mono ${
                            prodFormPrice >= prodFormCost ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-400' : 'bg-rose-500/15 text-rose-700'
                          }`}>
                            {prodFormPrice >= prodFormCost ? `+${Math.round(((prodFormPrice - prodFormCost) / prodFormCost) * 100)}% Margin` : 'Negative Margin'}
                          </span>
                        )}
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Cost Price (BDT) *</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormCost}
                            onChange={(e) => {
                              setProdFormCost(Number(e.target.value) || 0);
                              if (prodFormErrors.cost) setProdFormErrors(prev => ({ ...prev, cost: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold text-right text-[var(--foreground)] ${
                              prodFormErrors.cost ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {prodFormErrors.cost && (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{prodFormErrors.cost}</span>
                            </div>
                          )}
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Retail MRP (BDT) *</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormPrice}
                            onChange={(e) => {
                              setProdFormPrice(Number(e.target.value) || 0);
                              if (prodFormErrors.price) setProdFormErrors(prev => ({ ...prev, price: null }));
                            }}
                            className={`mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border text-xs font-mono font-bold text-[var(--primary)] text-right ${
                              prodFormErrors.price ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                            }`}
                          />
                          {prodFormErrors.price && (
                            <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
                              <Icon name="alertCircle" size={11} />
                              <span>{prodFormErrors.price}</span>
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-2">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Wholesale Price (BDT)</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormWholesale}
                            onChange={(e) => setProdFormWholesale(Number(e.target.value) || 0)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-semibold text-right text-[var(--foreground)]"
                          />
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">VAT Tax Category</label>
                          <select
                            value={prodFormVatRate}
                            onChange={(e) => setProdFormVatRate(e.target.value)}
                            className="mt-1 w-full h-8 px-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]"
                          >
                            <option>15% Standard (NBR Mushak 6.3)</option>
                            <option>5% Reduced Apparel Tax</option>
                            <option>0% VAT Exempt</option>
                          </select>
                        </div>
                      </div>

                      {/* Clickable Size Variant Chips */}
                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Applicable Sizes</label>
                        <div className="flex flex-wrap gap-1.5 mt-1">
                          {['S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', '40'].map(sz => (
                            <button
                              key={sz}
                              type="button"
                              className="px-2.5 py-1 rounded-md text-xs font-mono font-bold bg-[var(--surface-sunken)] border border-[var(--border)] hover:border-[var(--primary)] focus:bg-[var(--primary)] focus:text-white transition-colors"
                            >
                              {sz}
                            </button>
                          ))}
                        </div>
                      </div>

                      {/* Color Variant Chips */}
                      <div>
                        <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Applicable Colors</label>
                        <div className="flex flex-wrap gap-1.5 mt-1">
                          {['White', 'Navy Blue', 'Black', 'Maroon', 'Olive Green', 'Pastel Pink', 'Royal Blue'].map(col => (
                            <span key={col} className="px-2.5 py-0.5 rounded-md text-xs font-medium bg-[var(--surface-sunken)] border border-[var(--border)]">
                              {col}
                            </span>
                          ))}
                        </div>
                      </div>

                      {/* Branch Initial Stock Allocation */}
                      <div className="grid grid-cols-2 gap-2 pt-1 border-t border-[var(--border)]">
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Main Branch Stock</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormStockMain}
                            onChange={(e) => setProdFormStockMain(Number(e.target.value) || 0)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-right text-[var(--foreground)]"
                          />
                        </div>
                        <div>
                          <label className="text-[11px] font-semibold text-[var(--muted-foreground)]">Branch 2 Stock</label>
                          <input
                            type="number"
                            min="0"
                            value={prodFormStockB2}
                            onChange={(e) => setProdFormStockB2(Number(e.target.value) || 0)}
                            className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] text-xs font-mono font-bold text-right text-[var(--foreground)]"
                          />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* ================= SCREEN 13: STOCK & TRANSFERS"""

cat_content = prod_form_pattern.sub(new_prod_form, cat_content)

with open('scripts/parts/render_catalog_stock_purchases.py', 'w', encoding='utf-8') as f:
    f.write(cat_content)

print("✓ render_catalog_stock_purchases.py updated with controlled product form & validation.")

# ================= 2. UPDATE render_customers_messaging.py =================
with open('scripts/parts/render_customers_messaging.py', 'r', encoding='utf-8') as f:
    cust_content = f.read()

# Update table column for Address
old_addr_col = """                              <td className="py-2.5 px-3 text-[var(--muted-foreground)]">
                                {c.district}, {c.city_upazila}
                              </td>"""
new_addr_col = """                              <td className="py-2.5 px-3">
                                <div className="text-[var(--foreground)] font-medium truncate max-w-[200px]">{c.street_address || `${c.city_upazila}, ${c.district}`}</div>
                                <div className="text-[10px] text-[var(--muted-foreground)] truncate">{c.city_upazila}, {c.district} · {c.postal_code || '1230'}</div>
                              </td>"""
if old_addr_col in cust_content:
    cust_content = cust_content.replace(old_addr_col, new_addr_col)

# Update customer_profile banner
old_profile_banner = """                        <div className="text-xs text-[var(--muted-foreground)] flex items-center gap-3 mt-0.5">
                          <span>Card: <strong className="font-mono">{selectedCustomer.customer_no}</strong></span>
                          <span>·</span>
                          <span>Phone: <strong className="font-mono">{selectedCustomer.phone}</strong></span>
                          <span>·</span>
                          <span>Location: {selectedCustomer.district}, {selectedCustomer.city_upazila}</span>
                        </div>"""

new_profile_banner = """                        <div className="text-xs text-[var(--muted-foreground)] flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5">
                          <span>Card: <strong className="font-mono">{selectedCustomer.customer_no}</strong></span>
                          <span>·</span>
                          <span>Phone: <strong className="font-mono">{selectedCustomer.phone}</strong></span>
                          <span>·</span>
                          <span>Address: <strong className="text-[var(--foreground)]">{selectedCustomer.street_address ? `${selectedCustomer.street_address}, ` : ''}{selectedCustomer.city_upazila}, {selectedCustomer.district}</strong></span>
                          <span>·</span>
                          <span className="px-1.5 py-0.2 rounded bg-teal-500/10 text-teal-700 dark:text-teal-300 font-mono text-[10px] font-bold">
                            📍 {selectedCustomer.geo_lat || '23.8712'}° N, {selectedCustomer.geo_lng || '90.3982'}° E (Verified GPS)
                          </span>
                        </div>"""

if old_profile_banner in cust_content:
    cust_content = cust_content.replace(old_profile_banner, new_profile_banner)

# Update customer_profile overview tab
old_tab_content_pattern = re.compile(
    r"\{\/\* Tab Content \*\/\}\s*<div className=\"rounded-xl bg-\[var\(--surface\)\] border border-\[var\(--border\)\] p-4\">.*?<\/div>\s*<\/div>\s*<\/div>\s*\)\}\s*\{\/\* ================= SCREEN 9: BULK MEMBERSHIP CARD",
    re.DOTALL
)

new_tab_content = """{/* Tab Content */}
                  <div className="space-y-4">
                    {customerTab === 'overview' && (
                      <div className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          {/* CARD 1: GEOGRAPHICAL LOCATION & DELIVERY ADDRESS */}
                          <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                            <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                              <div className="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                                <Icon name="mapPin" size={15} className="text-[var(--primary)]" />
                                <span>Geographical Location & Delivery Address (ভৌগোলিক ঠিকানা)</span>
                              </div>
                              <span className="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold text-[10px]">
                                ✓ Live Verified
                              </span>
                            </div>

                            <div className="space-y-2 text-xs">
                              <div className="grid grid-cols-3 gap-2">
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Division</span>
                                  <div className="font-semibold text-[var(--foreground)]">{selectedCustomer.division || 'Dhaka'}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">District</span>
                                  <div className="font-semibold text-[var(--foreground)]">{selectedCustomer.district || 'Dhaka'}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Upazila / Thana</span>
                                  <div className="font-semibold text-[var(--foreground)]">{selectedCustomer.city_upazila || 'Uttara'}</div>
                                </div>
                              </div>

                              <div className="pt-2 border-t border-[var(--border)]">
                                <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Street Address / Holding No</span>
                                <div className="font-semibold text-[var(--foreground)] mt-0.5 p-2 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                                  {selectedCustomer.street_address || 'Holding 42, Road 11, Sector 3'}
                                </div>
                              </div>

                              <div>
                                <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Delivery Landmark & Directions</span>
                                <div className="text-[var(--foreground)] mt-0.5 italic text-[11px]">
                                  {selectedCustomer.landmark || 'Near Rajlakshmi Complex, Opposite to Mosque'}
                                </div>
                              </div>

                              {/* GPS Coordinates & Interactive Map Pill */}
                              <div className="p-3 rounded-xl bg-teal-500/5 border border-teal-500/20 flex items-center justify-between">
                                <div className="flex items-center gap-2.5">
                                  <div className="w-8 h-8 rounded-lg bg-teal-500/20 text-teal-600 flex items-center justify-center font-bold">
                                    <Icon name="crosshair" size={16} />
                                  </div>
                                  <div>
                                    <div className="text-[10px] font-bold text-teal-700 dark:text-teal-300 uppercase tracking-wider">GPS Satellite Coordinates</div>
                                    <div className="font-mono text-xs font-bold text-[var(--foreground)]">
                                      Lat: {selectedCustomer.geo_lat || '23.8712'}° N · Lng: {selectedCustomer.geo_lng || '90.3982'}° E
                                    </div>
                                  </div>
                                </div>
                                <button
                                  type="button"
                                  onClick={() => addToast(`Opening Google Maps for coordinates (${selectedCustomer.geo_lat || '23.8712'}, ${selectedCustomer.geo_lng || '90.3982'})`, "info")}
                                  className="h-7 px-2.5 rounded-lg bg-[var(--surface)] border border-[var(--border)] hover:border-[var(--primary)] text-[11px] font-semibold text-[var(--primary)] flex items-center gap-1 shadow-xs cursor-pointer"
                                >
                                  <Icon name="externalLink" size={12} />
                                  <span>View Map</span>
                                </button>
                              </div>
                            </div>
                          </div>

                          {/* CARD 2: CUSTOMER IDENTITY & CREDIT GOVERNANCE */}
                          <div className="p-4 rounded-xl bg-[var(--surface)] border border-[var(--border)] space-y-3 shadow-xs">
                            <div className="flex items-center justify-between pb-2 border-b border-[var(--border)]">
                              <div className="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                                <Icon name="shieldCheck" size={15} className="text-emerald-600" />
                                <span>Identity, Membership & Credit Limits</span>
                              </div>
                              <span className="font-mono text-[11px] font-bold px-2 py-0.5 rounded bg-[var(--surface-sunken)] border border-[var(--border)]">
                                #{selectedCustomer.customer_no}
                              </span>
                            </div>

                            <div className="space-y-2.5 text-xs">
                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">National ID (NID)</span>
                                  <div className="font-mono font-semibold text-[var(--foreground)]">{selectedCustomer.nid || '19922692018000412'}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Membership Group</span>
                                  <div className="font-semibold text-[var(--primary)]">{selectedCustomer.group}</div>
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-2">
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Primary WhatsApp</span>
                                  <div className="font-mono font-bold text-[var(--foreground)]">{selectedCustomer.phone}</div>
                                </div>
                                <div>
                                  <span className="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">Privilege Discount</span>
                                  <div className="font-bold text-emerald-600">{selectedCustomer.group_discount ? `−${selectedCustomer.group_discount}% Auto` : 'None (Standard)'}</div>
                                </div>
                              </div>

                              {/* Credit Limit Meter */}
                              <div className="pt-2 border-t border-[var(--border)] space-y-1.5">
                                <div className="flex items-center justify-between text-[11px]">
                                  <span className="text-[var(--muted-foreground)] font-medium">Credit Facility Utilization:</span>
                                  <span className="font-mono font-bold text-[var(--foreground)]">
                                    {formatMoney(Math.abs(selectedCustomer.balance < 0 ? selectedCustomer.balance : 0))} / {formatMoney(selectedCustomer.credit_limit || 10000)}
                                  </span>
                                </div>
                                <div className="w-full h-2 rounded-full bg-[var(--surface-sunken)] border border-[var(--border)] overflow-hidden">
                                  {(() => {
                                    const used = Math.abs(selectedCustomer.balance < 0 ? selectedCustomer.balance : 0);
                                    const limit = selectedCustomer.credit_limit || 10000;
                                    const pct = Math.min(100, Math.round((used / limit) * 100));
                                    return (
                                      <div
                                        style={{ width: `${pct}%` }}
                                        className={`h-full transition-all ${pct > 80 ? 'bg-rose-500' : pct > 50 ? 'bg-amber-500' : 'bg-emerald-500'}`}
                                      />
                                    );
                                  })()}
                                </div>
                                <div className="flex justify-between text-[10px] text-[var(--muted-foreground)]">
                                  <span>Available Credit: <strong>{formatMoney(Math.max(0, (selectedCustomer.credit_limit || 10000) - Math.abs(selectedCustomer.balance < 0 ? selectedCustomer.balance : 0)))}</strong></span>
                                  <span>Security PIN: <strong className="text-emerald-600">Active (4-Digit)</strong></span>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        {/* CARD 3: RECENT PURCHASES & ACTIVITY */}
                        <div className="rounded-xl bg-[var(--surface)] border border-[var(--border)] p-4 shadow-xs">
                          <div className="font-bold text-xs mb-3 text-[var(--foreground)] flex items-center justify-between">
                            <span>Recent Purchase Transactions & Vouchers</span>
                            <span className="text-[11px] text-[var(--muted-foreground)] font-normal">{selectedCustomer.open_invoices?.length || 1} open invoices</span>
                          </div>
                          <div className="text-xs space-y-2">
                            <div className="p-3 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] flex justify-between items-center">
                              <div>
                                <div className="font-mono font-bold text-[var(--primary)] flex items-center gap-2">
                                  <span>DF1-R1-26000413</span>
                                  <span className="px-1.5 py-0.2 rounded text-[10px] bg-emerald-500/15 text-emerald-700 font-bold">Settled</span>
                                </div>
                                <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">2x Cotton Panjabi M, 1x Classic Oxford Shirt L · Counter R1 (Sumi)</div>
                              </div>
                              <span className="font-mono font-bold text-sm text-[var(--foreground)]">{formatMoney(4690)}</span>
                            </div>
                            {selectedCustomer.open_invoices?.map((inv, idx) => (
                              <div key={idx} className="p-3 rounded-lg bg-amber-500/5 border border-amber-500/20 flex justify-between items-center">
                                <div>
                                  <div className="font-mono font-bold text-[var(--warning)] flex items-center gap-2">
                                    <span>{inv.invoice}</span>
                                    <span className="px-1.5 py-0.2 rounded text-[10px] bg-amber-500/20 text-amber-700 font-bold">Due Pending: {formatMoney(inv.due)}</span>
                                  </div>
                                  <div className="text-[11px] text-[var(--muted-foreground)] mt-0.5">{inv.date} · Paid {formatMoney(inv.paid)} of {formatMoney(inv.total)}</div>
                                </div>
                                <button
                                  type="button"
                                  onClick={() => {
                                    setDueCollectCustomer(selectedCustomer);
                                    setDueCollectAmount(inv.due.toString());
                                    setShowDueCollectModal(true);
                                  }}
                                  className="h-7 px-2.5 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold cursor-pointer"
                                >
                                  Pay Due
                                </button>
                              </div>
                            ))}
                          </div>
                        </div>
                      </div>
                    )}

                    {customerTab !== 'overview' && (
                      <div className="p-6 rounded-xl bg-[var(--surface)] border border-[var(--border)] text-center text-xs text-[var(--muted-foreground)]">
                        Viewing detailed records for {customerTab.toUpperCase()} tab of {selectedCustomer.name_en}.
                      </div>
                    )}
                  </div>
                </div>
              )}

              {/* ================= SCREEN 9: BULK MEMBERSHIP CARD"""

cust_content = old_tab_content_pattern.sub(new_tab_content, cust_content)

with open('scripts/parts/render_customers_messaging.py', 'w', encoding='utf-8') as f:
    f.write(cust_content)

print("✓ render_customers_messaging.py updated with address in directory & full customer 360 profile overview.")

# ================= 3. UPDATE render_all_modals.py =================
with open('scripts/parts/render_all_modals.py', 'r', encoding='utf-8') as f:
    modal_content = f.read()

# Modal 14: Stock Adjustment validation
old_adj = """{/* 14. STOCK ADJUSTMENT MODAL */}
          {showStockAdjustmentModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <span>Stock Adjustment</span>
                  <button onClick={() => setShowStockAdjustmentModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Item: {selectedProduct?.name_en || 'Product'}</label>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Adjustment Reason</label>
                  <select value={adjustReason} onChange={(e) => setAdjustReason(e.target.value)} className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                    <option>Damaged / Stained in Store</option>
                    <option>Inventory Audit Discrepancy</option>
                    <option>Theft / Shrinkage</option>
                    <option>Sample Given to Influencer</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Quantity (−/+)</label>
                  <input type="number" value={adjustQty} onChange={(e) => setAdjustQty(e.target.value)} placeholder="e.g. -2" className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono font-bold" />
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Manager Approval PIN *</label>
                  <input type="password" placeholder="••••" className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono" />
                </div>
                <div className="pt-2 flex gap-2">
                  <button onClick={() => setShowStockAdjustmentModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">Cancel</button>
                  <button onClick={() => { addToast("Stock adjustment logged and audited", "success"); setShowStockAdjustmentModal(false); }} className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold">Apply Write-off</button>
                </div>
              </div>
            </div>
          )}"""

new_adj = """{/* 14. STOCK ADJUSTMENT MODAL */}
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
                      adjustErrors.qty ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                    }`}
                  />
                  {adjustErrors.qty && (
                    <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
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
                      adjustErrors.pin ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                    }`}
                  />
                  {adjustErrors.pin && (
                    <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
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
          )}"""

if old_adj in modal_content:
    modal_content = modal_content.replace(old_adj, new_adj)

# Modal 15: Stock Transfer validation
old_trans_btn = """                  <button
                    type="button"
                    onClick={() => {
                      addToast(`Gate Pass #TR-9921 created: ${transferQty} pcs from ${transferFrom.split('[')[1]?.replace(']','') || 'DF1'} to ${transferTo.split('[')[1]?.replace(']','') || 'DF2'}`, "success");
                      setShowStockTransferModal(false);
                    }}
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] text-xs font-bold shadow-xs cursor-pointer"
                  >
                    Generate Gate Pass
                  </button>"""

new_trans_btn = """                  <button
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
                  </button>"""

if old_trans_btn in modal_content:
    modal_content = modal_content.replace(old_trans_btn, new_trans_btn)

# Modal 21: Expense validation
old_exp_btn = """                  <button onClick={() => { addToast("Expense voucher recorded", "success"); setShowNewExpenseModal(false); }} className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold">Save Voucher</button>"""
new_exp_btn = """                  <button
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
                    className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold shadow-xs cursor-pointer"
                  >
                    Save Voucher
                  </button>"""

if old_exp_btn in modal_content:
    modal_content = modal_content.replace(old_exp_btn, new_exp_btn)

# Modal 22: Add Staff controlled & validated
old_staff = """{/* 22. ADD STAFF MODAL */}
          {showAddStaffModal && (
            <div className="fixed inset-0 bg-black/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
              <div className="w-full max-w-sm bg-[var(--surface-raised)] border border-[var(--border)] rounded-xl shadow-2xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between pb-2 border-b border-[var(--border)] font-bold">
                  <span>Add Staff Member</span>
                  <button onClick={() => setShowAddStaffModal(false)}><Icon name="x" size={14} /></button>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Full Name</label>
                  <input type="text" placeholder="e.g. Mahfuzur Rahman" className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]" />
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Role</label>
                  <select className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">
                    <option>Cashier</option>
                    <option>Stock Keeper</option>
                    <option>Accountant</option>
                    <option>Manager</option>
                  </select>
                </div>
                <div>
                  <label className="text-[11px] text-[var(--muted-foreground)]">Initial 4-digit PIN</label>
                  <input type="password" placeholder="••••" className="mt-1 w-full h-8 px-2.5 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)] font-mono" />
                </div>
                <div className="pt-2 flex gap-2">
                  <button onClick={() => setShowAddStaffModal(false)} className="flex-1 h-8 rounded-lg bg-[var(--surface-sunken)] border border-[var(--border)]">Cancel</button>
                  <button onClick={() => { addToast("New staff created successfully!", "success"); setShowAddStaffModal(false); }} className="flex-1 h-8 rounded-lg bg-[var(--primary)] text-[var(--primary-foreground)] font-semibold">Save Staff</button>
                </div>
              </div>
            </div>
          )}"""

new_staff = """{/* 22. ADD STAFF MODAL */}
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
                      staffErrors.name ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                    }`}
                  />
                  {staffErrors.name && (
                    <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
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
                      staffErrors.pin ? 'border-rose-500 bg-rose-500/5' : 'border-[var(--border)]'
                    }`}
                  />
                  {staffErrors.pin && (
                    <div className="text-[10px] text-rose-500 font-semibold mt-1 flex items-center gap-1">
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
          )}"""

if old_staff in modal_content:
    modal_content = modal_content.replace(old_staff, new_staff)

with open('scripts/parts/render_all_modals.py', 'w', encoding='utf-8') as f:
    f.write(modal_content)

print("✓ render_all_modals.py updated with validations for modals 14, 15, 21, and 22.")
