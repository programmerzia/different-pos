# scripts/parts/app_state.py

APP_STATE_JS = """
    function App() {
      // Preferences & Settings
      const [lang, setLang] = useState('en'); // 'en' | 'bn'
      const [theme, setTheme] = useState('light'); // 'light' | 'dark'
      const [scheme, setScheme] = useState('teal'); // teal, indigo, violet, rose, ocean, graphite
      const [density, setDensity] = useState('comfortable'); // comfortable, compact
      const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
      
      // Screen Navigation
      // 'pos', 'sales', 'sale_detail', 'returns', 'due_collection', 'customers', 'customer_form', 'customer_profile', 'customer_statement', 'membership_cards', 'messaging', 'products', 'product_detail', 'product_form', 'stock', 'purchases', 'vendors', 'vendor_profile', 'money', 'fixed_assets', 'dashboard', 'reports', 'users', 'settings', 'backup', 'audit_log', 'super_admin', 'states_demo'
      const [currentScreen, setCurrentScreen] = useState('pos');

      // Master Permissions Check & Navigation
      const canAccess = (screenId, role = viewAsRole) => {
        if (!screenId) return true;
        if (screenId === 'account' || screenId === 'login') return true;
        if (role === 'Super Admin' || role === 'Owner') return true;
        if (role === 'Manager') {
          return screenId !== 'super_admin';
        }
        if (role === 'Cashier') {
          // Cashier is strictly restricted from:
          // purchases (G P), money (G M), dashboard (G D), reports (G R), settings (G ,), stock (G K), vendors (G V), fixed_assets, users, backup, audit_log, super_admin
          const cashierAllowed = [
            'pos', 'sales', 'sale_detail', 'returns', 'return_detail', 'due_collection', 
            'customers', 'customer_profile', 'customer_form', 'customer_statement', 'membership_cards', 
            'products', 'product_detail', 'messaging', 'account', 'states_demo'
          ];
          return cashierAllowed.includes(screenId);
        }
        if (role === 'Stock Keeper') {
          const stockAllowed = ['stock', 'products', 'product_detail', 'product_form', 'purchases', 'vendors', 'vendor_profile', 'account'];
          return stockAllowed.includes(screenId);
        }
        if (role === 'Accountant') {
          const accAllowed = ['dashboard', 'money', 'reports', 'sales', 'sale_detail', 'returns', 'return_detail', 'due_collection', 'purchases', 'vendors', 'vendor_profile', 'fixed_assets', 'customers', 'customer_profile', 'customer_statement', 'account'];
          return accAllowed.includes(screenId);
        }
        return true;
      };

      const navigateToScreen = (screenId) => {
        if (!canAccess(screenId)) {
          addToast(`Not allowed for ${viewAsRole} — ask a Manager`, "warning");
          return false;
        }
        setCurrentScreen(screenId);
        return true;
      };
      const [branch, setBranch] = useState('Different Fashion - Main [DF1]');
      const [currentUser, setCurrentUser] = useState({ name: 'Tanvir Ahmed', role: 'Owner', pin: '9999' });
      const [viewAsRole, setViewAsRole] = useState('Owner'); // Super Admin, Owner, Manager, Cashier, Accountant, Stock Keeper
      window.__setViewAsRole = setViewAsRole;
      const [isAuthenticated, setIsAuthenticated] = useState(true);
      window.__setIsAuthenticated = setIsAuthenticated;
      window.__setCurrentScreen = setCurrentScreen;
      window.__navigateToScreen = navigateToScreen;
      const [isLocked, setIsLocked] = useState(false);
      const [lockPinInput, setLockPinInput] = useState('');

      // Connectivity & Sync: 0: Online, 1: Offline, 2: 3 unsynced, 3: Sync error
      const [syncStateIndex, setSyncStateIndex] = useState(0);
      const [isSyncing, setIsSyncing] = useState(false);

      // Top Bar: Today Summary Popover (NO SHIFTS)

      const todaySummary = {
        gross_sales: 84250,
        sales_count: 63,
        returns_total: 3450,
        returns_count: 2,
        discounts_total: 4800,
        net_sales: 80800,
        cash_total: 48500,
        bkash_total: 22400,
        card_total: 13350,
        dues_created: 5200,
        dues_collected: 8600
      };

      // Top Bar Popovers
      const [showNotifications, setShowNotifications] = useState(false);
      const [notifTab, setNotifTab] = useState('all');
      const [showThemePopover, setShowThemePopover] = useState(false);
      const [showAvatarMenu, setShowAvatarMenu] = useState(false);

      const [cmdQuery, setCmdQuery] = useState('');
      window.__setCmdQuery = setCmdQuery;
      const [paletteSelectedIndex, setPaletteSelectedIndex] = useState(0);

      // Notifications List (All shift mentions removed)
      const [notifications, setNotifications] = useState([
        { id: 'n1', type: 'alert', title: 'Low stock: Panjabi Cotton M (3 left)', time: '12m ago', unread: true },
        { id: 'n2', type: 'alert', title: 'Sale DF1-R1-26000412 synced with price mismatch', time: '34m ago', unread: true },
        { id: 'n3', type: 'alert', title: 'Wrong PIN 3× for customer #1000 0017', time: '1h ago', unread: true },
        { id: 'n4', type: 'export', title: 'Export ready: Sales Oct 2026.xlsx', time: '2h ago', unread: true },
        { id: 'n5', type: 'transfer', title: 'Transfer incoming from Branch 2 (15 items)', time: '3h ago', unread: false },
        { id: 'n6', type: 'message', title: 'Due reminder delivered to Rahim Uddin via WhatsApp', time: '4h ago', unread: false }
      ]);

      // Clock
      const [currentTime, setCurrentTime] = useState(new Date());
      useEffect(() => {
        const timer = setInterval(() => setCurrentTime(new Date()), 1000);
        return () => clearInterval(timer);
      }, []);

      // Toasts
      const [toasts, setToasts] = useState([]);
      const addToast = (message, type = 'info') => {
        const id = Date.now() + Math.random();
        setToasts(prev => [...prev.slice(-3), { id, message, type }]);
        setTimeout(() => setToasts(prev => prev.filter(t => t.id !== id)), 4000);
      };

      // Desktop Right-Click Context Menu
      const [contextMenu, setContextMenu] = useState({ visible: false, x: 0, y: 0, title: '', items: [] });
      const openContextMenu = (e, title, items) => {
        e.preventDefault();
        e.stopPropagation();
        setContextMenu({
          visible: true,
          x: Math.min(e.clientX, window.innerWidth - 240),
          y: Math.min(e.clientY, window.innerHeight - 300),
          title,
          items
        });
      };

      useEffect(() => {
        const handleClick = () => { if (contextMenu.visible) setContextMenu({ visible: false, x: 0, y: 0, items: [] }); };
        window.addEventListener('click', handleClick);
        return () => window.removeEventListener('click', handleClick);
      }, [contextMenu.visible]);

      // Apply Attributes to <html>
      useEffect(() => {
        document.documentElement.setAttribute('data-theme', theme);
        document.documentElement.setAttribute('data-scheme', scheme);
        document.documentElement.setAttribute('data-density', density);
      }, [theme, scheme, density]);

      // Sync status definitions
      const syncStatuses = [
        { label: 'Online', icon: 'wifi', colorClass: 'text-[var(--success)] bg-[var(--success-soft)] border-[var(--success)]/30' },
        { label: 'Offline', icon: 'wifiOff', colorClass: 'text-[var(--warning)] bg-[var(--warning-soft)] border-[var(--warning)]/30' },
        { label: '3 unsynced', icon: 'refresh', colorClass: 'text-[var(--warning)] bg-[var(--warning-soft)] border-[var(--warning)]/40' },
        { label: 'Sync error', icon: 'alertTriangle', colorClass: 'text-[var(--danger)] bg-[var(--danger-soft)] border-[var(--danger)]/30' }
      ];
      const currentSyncStatus = syncStatuses[syncStateIndex];
      const isOfflineMode = syncStateIndex === 1 || syncStateIndex === 2;

      const handleSyncRetry = () => {
        setIsSyncing(true);
        setTimeout(() => {
          setIsSyncing(false);
          setSyncStateIndex(0);
          addToast("Synced 3 sales to server successfully!", "success");
        }, 1200);
      };

      // --- POS Register State ---
      const [posCategory, setPosCategory] = useState('all');
      const [posSearchQuery, setPosSearchQuery] = useState('');
      const [showPosCatalogBrowse, setShowPosCatalogBrowse] = useState(false);
      const [posSelectedTileIndex, setPosSelectedTileIndex] = useState(0);
      const posScanInputRef = useRef(null);

      // --- Product Catalog Filter State ---
      const [productSearch, setProductSearch] = useState('');
      const [productCatFilter, setProductCatFilter] = useState('all');

      // Filtered catalog for POS and Products screen
      const filteredCatalog = useMemo(() => {
        return INITIAL_PRODUCTS.filter(p => {
          if (currentScreen === 'products') {
            const matchCat = productCatFilter === 'all' || p.category === productCatFilter;
            const q = productSearch.toLowerCase().trim();
            const matchSearch = !q || 
              p.name_en.toLowerCase().includes(q) || 
              p.name_bn.includes(q) || 
              p.sku.toLowerCase().includes(q) || 
              p.barcode.includes(q);
            return matchCat && matchSearch;
          }
          const q = posSearchQuery.toLowerCase().trim();
          if (!q) return true;
          return p.name_en.toLowerCase().includes(q) || 
            p.name_bn.includes(q) || 
            p.sku.toLowerCase().includes(q) || 
            p.barcode.includes(q);
        });
      }, [currentScreen, productSearch, productCatFilter, posSearchQuery]);

      // Cart
      const [cart, setCart] = useState([
        { product: INITIAL_PRODUCTS[0], qty: 2, original_price: 1450, unit_price: 1450, line_discount: 145, discount_type: 'percent', discount_percent: 10, salesman: 'Sumi', note: '' },
        { product: INITIAL_PRODUCTS[3], qty: 1, original_price: 1890, unit_price: 1890, line_discount: 0, discount_type: 'none', discount_percent: 0, salesman: 'Sumi', note: '' },
      ]);
      const [selectedCartLineIndex, setSelectedCartLineIndex] = useState(0);
      const [attachedCustomer, setAttachedCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [heldSales, setHeldSales] = useState([]);
      const [orderDiscount, setOrderDiscount] = useState(0);
      const [orderDiscountType, setOrderDiscountType] = useState('amount'); // 'amount' | 'percent'
      const [orderDiscountPercent, setOrderDiscountPercent] = useState(0);
      const [isGroupDiscountRemoved, setIsGroupDiscountRemoved] = useState(false);

      // ================= UNIFIED MODAL & OVERLAY SYSTEM =================
      // Strict rule: Only ONE modal may be open at a time (except secondaryModal for Manager PIN)
      const [activeModal, setActiveModal] = useState(null);
      const [secondaryModal, setSecondaryModal] = useState(null);

      const openModal = (name) => {
        setSecondaryModal(null);
        setActiveModal(name);
      };

      const closeModal = (name = null) => {
        if (secondaryModal) {
          setSecondaryModal(null);
          return;
        }
        if (!name || activeModal === name) {
          setActiveModal(null);
          setTimeout(() => {
            const scanInput = document.getElementById('pos-barcode-input');
            if (scanInput) scanInput.focus();
          }, 50);
        }
      };
      window.__openModal = openModal;
      window.__closeModal = closeModal;

      // Compatibility Getters & Setters for all overlays
      const showCustomerAttachModal = activeModal === 'customer_attach';
      const setShowCustomerAttachModal = (v) => v ? openModal('customer_attach') : closeModal('customer_attach');

      const showQuickNewCustomerModal = activeModal === 'quick_new_customer';
      const setShowQuickNewCustomerModal = (v) => v ? openModal('quick_new_customer') : closeModal('quick_new_customer');

      const showCustomerPinPadModal = activeModal === 'customer_pin_pad';
      const setShowCustomerPinPadModal = (v) => v ? openModal('customer_pin_pad') : closeModal('customer_pin_pad');

      const showPaymentModal = activeModal === 'payment';
      const setShowPaymentModal = (v) => v ? openModal('payment') : closeModal('payment');

      const showReceiptModal = activeModal === 'receipt';
      const setShowReceiptModal = (v) => v ? openModal('receipt') : closeModal('receipt');

      const showA4InvoiceModal = activeModal === 'a4_invoice';
      const setShowA4InvoiceModal = (v) => v ? openModal('a4_invoice') : closeModal('a4_invoice');

      const showPriceCheckModal = activeModal === 'price_check';
      const setShowPriceCheckModal = (v) => v ? openModal('price_check') : closeModal('price_check');

      const showDiscountModal = activeModal === 'discount';
      const setShowDiscountModal = (v) => v ? openModal('discount') : closeModal('discount');

      const showHeldSalesModal = activeModal === 'held_sales';
      const setShowHeldSalesModal = (v) => v ? openModal('held_sales') : closeModal('held_sales');

      const showDueCollectModal = activeModal === 'due_collect';
      const setShowDueCollectModal = (v) => v ? openModal('due_collect') : closeModal('due_collect');

      const showMoneyReceiptModal = activeModal === 'money_receipt';
      const setShowMoneyReceiptModal = (v) => v ? openModal('money_receipt') : closeModal('money_receipt');

      const showDaySummaryPrintModal = activeModal === 'day_summary_print';
      const setShowDaySummaryPrintModal = (v) => v ? openModal('day_summary_print') : closeModal('day_summary_print');

      const showReturnReceiptModal = activeModal === 'return_receipt';
      const setShowReturnReceiptModal = (v) => v ? openModal('return_receipt') : closeModal('return_receipt');

      const showReminderModal = activeModal === 'reminder';
      const setShowReminderModal = (v) => v ? openModal('reminder') : closeModal('reminder');

      const showStockAdjustmentModal = activeModal === 'stock_adjustment';
      const setShowStockAdjustmentModal = (v) => v ? openModal('stock_adjustment') : closeModal('stock_adjustment');

      const showStockTransferModal = activeModal === 'stock_transfer';
      const setShowStockTransferModal = (v) => v ? openModal('stock_transfer') : closeModal('stock_transfer');

      const showStocktakeModal = activeModal === 'stocktake';
      const setShowStocktakeModal = (v) => v ? openModal('stocktake') : closeModal('stocktake');

      const showBarcodeLabelModal = activeModal === 'barcode_label';
      const setShowBarcodeLabelModal = (v) => v ? openModal('barcode_label') : closeModal('barcode_label');

      const showPayVendorModal = activeModal === 'pay_vendor';
      const setShowPayVendorModal = (v) => v ? openModal('pay_vendor') : closeModal('pay_vendor');

      const showNewPurchaseModal = activeModal === 'new_purchase';
      const setShowNewPurchaseModal = (v) => v ? openModal('new_purchase') : closeModal('new_purchase');

      const showPurchaseDetailModal = activeModal === 'purchase_detail';
      const setShowPurchaseDetailModal = (v) => v ? openModal('purchase_detail') : closeModal('purchase_detail');

      const showAccountTransferModal = activeModal === 'account_transfer';
      const setShowAccountTransferModal = (v) => v ? openModal('account_transfer') : closeModal('account_transfer');

      const showNewExpenseModal = activeModal === 'new_expense';
      const setShowNewExpenseModal = (v) => v ? openModal('new_expense') : closeModal('new_expense');

      const showAddStaffModal = activeModal === 'add_staff';
      const setShowAddStaffModal = (v) => v ? openModal('add_staff') : closeModal('add_staff');

      const showResetPinModal = activeModal === 'reset_pin';
      const setShowResetPinModal = (v) => v ? openModal('reset_pin') : closeModal('reset_pin');

      const showCommandPalette = activeModal === 'command_palette';
      const setShowCommandPalette = (v) => v ? openModal('command_palette') : closeModal('command_palette');

      const showCustomerCardModal = activeModal === 'customer_card';
      const setShowCustomerCardModal = (v) => v ? openModal('customer_card') : closeModal('customer_card');

      const showAddCategoryModal = activeModal === 'add_category';
      const setShowAddCategoryModal = (v) => v ? openModal('add_category') : closeModal('add_category');

      const showNewTenantModal = activeModal === 'new_tenant';
      const setShowNewTenantModal = (v) => v ? openModal('new_tenant') : closeModal('new_tenant');

      const showBulkBarcodeModal = activeModal === 'bulk_barcode';
      const setShowBulkBarcodeModal = (v) => v ? openModal('bulk_barcode') : closeModal('bulk_barcode');

      const showShortcutsModal = activeModal === 'shortcuts';
      const setShowShortcutsModal = (v) => v ? openModal('shortcuts') : closeModal('shortcuts');

      const showVariantPickerModal = activeModal === 'variant_picker';
      const setShowVariantPickerModal = (v) => v ? openModal('variant_picker') : closeModal('variant_picker');

      const showSyncCenter = activeModal === 'sync_center';
      const setShowSyncCenter = (v) => v ? openModal('sync_center') : closeModal('sync_center');

      const showTodaySummaryPopover = activeModal === 'today_summary';
      const setShowTodaySummaryPopover = (v) => v ? openModal('today_summary') : closeModal('today_summary');

      const showStockCheckModal = activeModal === 'stock_check';
      const setShowStockCheckModal = (v) => v ? openModal('stock_check') : closeModal('stock_check');

      const showSwitchUserPinModal = activeModal === 'switch_user_pin';
      const setShowSwitchUserPinModal = (v) => v ? openModal('switch_user_pin') : closeModal('switch_user_pin');

      const showReturnWizardModal = activeModal === 'return_wizard';
      const setShowReturnWizardModal = (v) => v ? openModal('return_wizard') : closeModal('return_wizard');

      const showReissueCardModal = activeModal === 'reissue_card';
      const setShowReissueCardModal = (v) => v ? openModal('reissue_card') : closeModal('reissue_card');

      const showForgotPasswordModal = activeModal === 'forgot_password';
      const setShowForgotPasswordModal = (v) => v ? openModal('forgot_password') : closeModal('forgot_password');

      const showBranchPickerModal = activeModal === 'branch_picker';
      const setShowBranchPickerModal = (v) => v ? openModal('branch_picker') : closeModal('branch_picker');

      const showAddBrandModal = activeModal === 'add_brand';
      const setShowAddBrandModal = (v) => v ? openModal('add_brand') : closeModal('add_brand');

      const showAddVendorModal = activeModal === 'add_vendor';
      const setShowAddVendorModal = (v) => v ? openModal('add_vendor') : closeModal('add_vendor');

      const showImportWizardModal = activeModal === 'import_wizard';
      const setShowImportWizardModal = (v) => v ? openModal('import_wizard') : closeModal('import_wizard');

      const showBranchModal = activeModal === 'branch_modal';
      const setShowBranchModal = (v) => v ? openModal('branch_modal') : closeModal('branch_modal');

      const showEditCategoryModal = activeModal === 'edit_category';
      const setShowEditCategoryModal = (v) => v ? openModal('edit_category') : closeModal('edit_category');

      // Modals State Payloads
      
      // --- Part 5: Brand, Vendor, CSV Import & Operational States ---
      const [brandsList, setBrandsList] = useState([
        { id: 'b1', name: 'Different Fashion (In-house)', origin: 'Bangladesh', desc: 'Premium in-house ethnic wear & casual apparel', count: 18 },
        { id: 'b2', name: 'Aarong Handlooms', origin: 'Bangladesh', desc: 'Traditional silk, cotton and jamdani textiles', count: 12 },
        { id: 'b3', name: 'Yellow Casuals', origin: 'Beximco BD', desc: 'Trendy youth wear, polo and casual denim', count: 9 },
        { id: 'b4', name: 'Richman Premium', origin: 'Bangladesh', desc: 'Formal menswear, suits and panjabi sets', count: 7 },
        { id: 'b5', name: 'Cats Eye Lifestyle', origin: 'Bangladesh', desc: 'Executive formal & casual shirts', count: 5 }
      ]);
      const [brandForm, setBrandForm] = useState({ name: '', origin: 'Bangladesh', desc: '' });
      const handleSaveBrand = (e) => {
        if (e && e.preventDefault) e.preventDefault();
        if (!brandForm.name.trim()) {
          addToast("Brand name is required", "warning");
          return;
        }
        const newBrand = {
          id: 'b_' + Date.now(),
          name: brandForm.name.trim(),
          origin: brandForm.origin.trim() || 'Bangladesh',
          desc: brandForm.desc.trim(),
          count: 0
        };
        setBrandsList(prev => [...prev, newBrand]);
        setBrandForm({ name: '', origin: 'Bangladesh', desc: '' });
        closeModal('add_brand');
        addToast(`Brand "${newBrand.name}" registered successfully!`, "success");
      };

      const [vendorsList, setVendorsList] = useState(MOCK_VENDORS);
      const [vendorForm, setVendorForm] = useState({
        name_en: '',
        name_bn: '',
        contact: '',
        phone: '',
        email: '',
        hub: 'Dhaka',
        balance: 0
      });
      const handleSaveVendor = (e) => {
        if (e && e.preventDefault) e.preventDefault();
        if (!vendorForm.name_en.trim()) {
          addToast("Vendor company name is required", "warning");
          return;
        }
        if (!vendorForm.phone.trim()) {
          addToast("Vendor phone number is required", "warning");
          return;
        }
        const newVendor = {
          id: 'VND-' + (vendorsList.length + 1).toString().padStart(3, '0'),
          name: vendorForm.name_en.trim(),
          name_bn: vendorForm.name_bn.trim() || vendorForm.name_en.trim(),
          contact: vendorForm.contact.trim() || 'Proprietor',
          phone: vendorForm.phone.trim(),
          email: vendorForm.email.trim() || `${vendorForm.name_en.toLowerCase().replace(/\\s+/g, '')}@supplier.bd`,
          hub: vendorForm.hub,
          payable: Number(vendorForm.balance) || 0,
          orders_count: 0,
          last_delivery: 'Just now'
        };
        setVendorsList(prev => [newVendor, ...prev]);
        setVendorForm({ name_en: '', name_bn: '', contact: '', phone: '', email: '', hub: 'Dhaka', balance: 0 });
        closeModal('add_vendor');
        addToast(`Vendor "${newVendor.name}" registered successfully!`, "success");
      };

      // CSV Data Import Wizard State
      const [importEntityType, setImportEntityType] = useState('products'); // 'products' | 'customers'
      const [importStep, setImportStep] = useState(1); // 1: upload, 2: mapping, 3: dryrun, 4: done
      const [importFileName, setImportFileName] = useState('apparel_sample_batch_oct.csv');
      const [importFileRowsCount, setImportFileRowsCount] = useState(6);
      const [importDryRunPreview, setImportDryRunPreview] = useState([
        { id: 1, name: 'Festive Silk Jamdani Kurti', sku: 'DF-KUR-01', barcode: '8942001', category: 'Kurti', price: 2200, cost: 1400, stock: 25, status: 'valid', error: null },
        { id: 2, name: 'Linen Slim Casual Shirt', sku: 'DF-SHI-09', barcode: '8942002', category: 'Shirt', price: 1650, cost: 950, stock: 15, status: 'valid', error: null },
        { id: 3, name: 'Embroidered Silk Scarf', sku: 'DF-PAN-01', barcode: '8941001', category: 'Accessories', price: 850, cost: 400, stock: 40, status: 'error', error: 'Duplicate SKU (DF-PAN-01 already in catalog)' },
        { id: 4, name: 'Traditional Dhupian Silk Saree', sku: 'DF-SAR-11', barcode: '8942005', category: 'Saree', price: 6800, cost: 4200, stock: 8, status: 'valid', error: null },
        { id: 5, name: 'Premium Cotton Polo Navy', sku: 'DF-POL-14', barcode: '', category: 'Polo', price: 'invalid_price', cost: 650, stock: 18, status: 'error', error: 'MRP price must be a valid number' },
        { id: 6, name: 'Katan Silk Dupatta', sku: 'DF-ACC-03', barcode: '8942006', category: 'Accessories', price: 1250, cost: 700, stock: 30, status: 'valid', error: null }
      ]);

      const handleLoadSampleCsv = (type) => {
        setImportEntityType(type);
        setImportFileName(type === 'products' ? 'apparel_products_oct2026.csv' : 'retail_members_oct2026.csv');
        if (type === 'products') {
          setImportFileRowsCount(6);
          setImportDryRunPreview([
            { id: 1, name: 'Festive Silk Jamdani Kurti', sku: 'DF-KUR-01', barcode: '8942001', category: 'Kurti', price: 2200, cost: 1400, stock: 25, status: 'valid', error: null },
            { id: 2, name: 'Linen Slim Casual Shirt', sku: 'DF-SHI-09', barcode: '8942002', category: 'Shirt', price: 1650, cost: 950, stock: 15, status: 'valid', error: null },
            { id: 3, name: 'Embroidered Silk Scarf', sku: 'DF-PAN-01', barcode: '8941001', category: 'Accessories', price: 850, cost: 400, stock: 40, status: 'error', error: 'Duplicate SKU (DF-PAN-01 already in catalog)' },
            { id: 4, name: 'Traditional Dhupian Silk Saree', sku: 'DF-SAR-11', barcode: '8942005', category: 'Saree', price: 6800, cost: 4200, stock: 8, status: 'valid', error: null },
            { id: 5, name: 'Premium Cotton Polo Navy', sku: 'DF-POL-14', barcode: '', category: 'Polo', price: 'invalid_price', cost: 650, stock: 18, status: 'error', error: 'MRP price must be a valid number' },
            { id: 6, name: 'Katan Silk Dupatta', sku: 'DF-ACC-03', barcode: '8942006', category: 'Accessories', price: 1250, cost: 700, stock: 30, status: 'valid', error: null }
          ]);
        } else {
          setImportFileRowsCount(4);
          setImportDryRunPreview([
            { id: 1, name: 'Kazi Farhan', phone: '01711998877', customer_no: '1000 0388', balance: 0, status: 'valid', error: null },
            { id: 2, name: 'Shahana Parvin', phone: '01819554433', customer_no: '1000 0389', balance: 0, status: 'valid', error: null },
            { id: 3, name: 'Invalid Contact Customer', phone: '12345', customer_no: '1000 0390', balance: 0, status: 'error', error: 'Invalid 11-digit Bangladesh phone number' },
            { id: 4, name: 'Mahmudul Hasan', phone: '01912443322', customer_no: '1000 0391', balance: 0, status: 'valid', error: null }
          ]);
        }
      };

      const handleExecuteCsvImport = () => {
        const validRows = importDryRunPreview.filter(r => r.status === 'valid');
        if (importEntityType === 'products') {
          validRows.forEach(r => {
            INITIAL_PRODUCTS.unshift({
              id: 'PRD-' + (INITIAL_PRODUCTS.length + 10),
              name_en: r.name,
              name_bn: r.name,
              sku: r.sku,
              barcode: r.barcode || '894' + Math.floor(100000 + Math.random() * 900000),
              category: r.category.toLowerCase(),
              price: Number(r.price) || 1200,
              cost_price: Number(r.cost) || 750,
              stock_main: Number(r.stock) || 10,
              stock_branch2: 5,
              sizes: ['M', 'L', 'XL'],
              colors: ['Standard'],
              vat_category: 'standard'
            });
          });
          addToast(`Successfully imported ${validRows.length} products into catalog!`, "success");
        } else {
          validRows.forEach(r => {
            MOCK_CUSTOMERS.unshift({
              id: 'c_' + (MOCK_CUSTOMERS.length + 10),
              name_en: r.name,
              name_bn: r.name,
              phone: r.phone,
              customer_no: r.customer_no,
              due_balance: 0,
              credit_limit: 15000,
              loyalty_points: 50,
              tier: 'Standard',
              card_barcode: 'MC' + r.customer_no.replace(/\\s+/g, '')
            });
          });
          addToast(`Successfully imported ${validRows.length} customers into directory!`, "success");
        }
        setImportStep(4);
      };

      // Branch Provisioning State
      const [branchModalMode, setBranchModalMode] = useState('add');
      const [branchFormData, setBranchFormData] = useState({
        id: '',
        name: '',
        code: '',
        address: '',
        phone: '',
        registers: 2,
        bin: '002938172-0103'
      });
      const openBranchModal = (mode, branchObj = null) => {
        setBranchModalMode(mode);
        if (mode === 'edit' && branchObj) {
          setBranchFormData({
            id: branchObj.id,
            name: branchObj.name,
            code: branchObj.code || (branchObj.name.includes('Main') ? 'DF1' : 'DF2'),
            address: branchObj.address || 'House 14, Road 11, Sector 4, Uttara, Dhaka',
            phone: branchObj.phone || '01711-223344',
            registers: branchObj.registers || 2,
            bin: branchObj.bin || '002938172-0101'
          });
        } else {
          setBranchFormData({
            id: 'DF' + ((settingsDraft.branches?.length || 2) + 1),
            name: 'Different Fashion - Dhanmondi [DF' + ((settingsDraft.branches?.length || 2) + 1) + ']',
            code: 'DF' + ((settingsDraft.branches?.length || 2) + 1),
            address: 'Plot 28, Road 7/A, Dhanmondi, Dhaka',
            phone: '01711-889900',
            registers: 2,
            bin: '002938172-0103'
          });
        }
        openModal('branch_modal');
      };
      const handleSaveBranch = (e) => {
        if (e && e.preventDefault) e.preventDefault();
        if (!branchFormData.name.trim()) {
          addToast("Branch name is required", "warning");
          return;
        }
        const currentBranches = settingsDraft.branches || [
          { id: 'DF1', name: 'Different Fashion - Main [DF1]', address: 'Uttara Sector 4, Dhaka', registers: 2 },
          { id: 'DF2', name: 'Different Fashion - 2 [DF2]', address: 'Mirpur 10 Circle, Dhaka', registers: 2 }
        ];
        let updatedBranches;
        if (branchModalMode === 'edit') {
          updatedBranches = currentBranches.map(b => b.id === branchFormData.id ? { ...b, ...branchFormData } : b);
        } else {
          updatedBranches = [...currentBranches, { active: true, ...branchFormData, id: branchFormData.code || 'DF' + (currentBranches.length + 1) }];
        }
        setSettingsDraft(prev => ({ ...(prev || {}), branches: updatedBranches }));
        if (typeof setSettingsIsDirty === 'function') setSettingsIsDirty(true);
        closeModal('branch_modal');
        addToast(`Branch "${branchFormData.name}" ${branchModalMode === 'edit' ? 'updated' : 'provisioned'} successfully!`, "success");
      };

      // Category Edit State
      const [editCategoryTarget, setEditCategoryTarget] = useState(null);
      const [categoryForm, setCategoryForm] = useState({ name_en: '', name_bn: '', slug: '', parent: 'None', vat: 15 });
      const openEditCategoryModal = (cat) => {
        setEditCategoryTarget(cat);
        setCategoryForm({
          name_en: cat.name_en,
          name_bn: cat.name_bn,
          slug: cat.slug || cat.id,
          parent: cat.parent || 'None',
          vat: cat.vat || 15
        });
        openModal('edit_category');
      };
      const handleSaveCategory = (e) => {
        if (e && e.preventDefault) e.preventDefault();
        if (!categoryForm.name_en.trim()) {
          addToast("Category name is required", "warning");
          return;
        }
        if (editCategoryTarget) {
          setCategoriesList(prev => prev.map(c => c.id === editCategoryTarget.id ? { ...c, ...categoryForm } : c));
          addToast(`Category "${categoryForm.name_en}" updated successfully!`, "success");
        } else {
          const newCat = {
            id: categoryForm.slug || 'cat_' + Date.now(),
            name_en: categoryForm.name_en.trim(),
            name_bn: categoryForm.name_bn.trim() || categoryForm.name_en.trim(),
            slug: categoryForm.slug || categoryForm.name_en.toLowerCase().replace(/\\s+/g, '-'),
            item_count: 0
          };
          setCategoriesList(prev => [...prev, newCat]);
          addToast(`Category "${newCat.name_en}" created successfully!`, "success");
        }
        closeModal('edit_category');
      };

      // Stock Transfer & Batch Transfer State
      const [transferBatchProducts, setTransferBatchProducts] = useState([]);
      const [transferMemo, setTransferMemo] = useState('Challan / Vehicle: Pickup Van DHA-METRO-11-2091');
      const handleOpenBatchTransfer = (products) => {
        setTransferBatchProducts(products);
        if (products.length > 0) setSelectedProduct(products[0]);
        setTransferQty(products.length > 1 ? 5 : 2);
        openModal('stock_transfer');
      };

      // Physical Stocktake Audit State
      const [stocktakeAuditScope, setStocktakeAuditScope] = useState('all');
      const [stocktakePin, setStocktakePin] = useState('');
      const [stocktakeRows, setStocktakeRows] = useState([
        { id: 'PRD-01', name: 'Semi-fitted Panjabi Classic', sku: 'DF-PAN-01', system_qty: 32, counted_qty: 30, discrepancy: -2, reason: 'Damaged in display' },
        { id: 'PRD-02', name: 'Premium Silk Jamdani Saree', sku: 'DF-SAR-02', system_qty: 14, counted_qty: 14, discrepancy: 0, reason: 'Matched' },
        { id: 'PRD-03', name: 'Cotton Casual Shirt Slim Fit', sku: 'DF-SHI-03', system_qty: 45, counted_qty: 46, discrepancy: +1, reason: 'Supplier extra pack' },
        { id: 'PRD-04', name: 'Embroidered Silk Panjabi', sku: 'DF-PAN-04', system_qty: 18, counted_qty: 17, discrepancy: -1, reason: 'Theft / Shrinkage' },
        { id: 'PRD-05', name: 'Cotton Polo Shirt Navy', sku: 'DF-POL-05', system_qty: 28, counted_qty: 28, discrepancy: 0, reason: 'Matched' }
      ]);
      const handleSaveStocktakeAudit = () => {
        if (stocktakePin !== '1234') {
          addToast("Manager approval PIN required (enter 1234)", "danger");
          return;
        }
        const varianceCount = stocktakeRows.filter(r => r.discrepancy !== 0).length;
        addToast(`Stocktake audit saved! Reconciled ${stocktakeRows.length} items (${varianceCount} variances logged to audit ledger)`, "success");
        setStocktakePin('');
        closeModal('stocktake');
      };

      // Barcode Printing State
      const [barcodeLabelPreset, setBarcodeLabelPreset] = useState('thermal_50x30');
      const [barcodeCopiesPerItem, setBarcodeCopiesPerItem] = useState(4);
      const [barcodeIncludeBangla, setBarcodeIncludeBangla] = useState(true);
      const [barcodeIncludeMrp, setBarcodeIncludeMrp] = useState(true);
      const handlePrintBarcodeBatch = () => {
        const totalStickers = (selectedBarcodeProducts.length || 1) * barcodeCopiesPerItem;
        addToast(`Printed ${totalStickers} barcode stickers to Zebra ZD220 thermal printer!`, "success");
        closeModal('bulk_barcode');
      };

      const [customerSearchQuery, setCustomerSearchQuery] = useState('');
      const [pinPadState, setPinPadState] = useState('input');
      const [pinDigits, setPinDigits] = useState('');
      const [pinAttemptsLeft, setPinAttemptsLeft] = useState(2);

      // Tender & Payment State
      const [tenderTally, setTenderTally] = useState({ cash: 4690, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
      const [dueCustomerPin, setDueCustomerPin] = useState('');
      const [duePromiseDate, setDuePromiseDate] = useState('2026-11-05');
      const [dueNote, setDueNote] = useState('');
      const [dueManagerPinOverride, setDueManagerPinOverride] = useState('');
      const [lastCompletedSale, setLastCompletedSale] = useState(null);

      // Discount State
      const [discountTarget, setDiscountTarget] = useState('line');
      const [discountTab, setDiscountTab] = useState('percent');
      const [discountValue, setDiscountValue] = useState('');
      const [discountReason, setDiscountReason] = useState('Seasonal Eid promo');
      const [discountManagerPin, setDiscountManagerPin] = useState('');
      const [showManagerPinInline, setShowManagerPinInline] = useState(false);

      // Role discount limits: Cashier 10%, Manager 25%, Owner 100%
      const ROLE_DISCOUNT_LIMITS = {
        'Cashier': 10,
        'Stock Keeper': 0,
        'Accountant': 10,
        'Manager': 25,
        'Owner': 100,
        'Super Admin': 100
      };
      const currentRoleLimit = ROLE_DISCOUNT_LIMITS[viewAsRole] || 10;

      // Price Check State
      const [priceCheckQuery, setPriceCheckQuery] = useState('');
      const [quickCustName, setQuickCustName] = useState('');
      const [quickCustPhone, setQuickCustPhone] = useState('');
      const [quickCustDistrict, setQuickCustDistrict] = useState('Joypurhat');
      const [quickCustGroup, setQuickCustGroup] = useState('Retail');
      const [quickCustAddress, setQuickCustAddress] = useState('');
      const [quickCustShop, setQuickCustShop] = useState('');

      // --- Full Customer Registration Form States & Validation ---
      const [custFormNameEn, setCustFormNameEn] = useState('');
      const [custFormNameBn, setCustFormNameBn] = useState('');
      const [custFormShop, setCustFormShop] = useState('');
      const [custFormPhone, setCustFormPhone] = useState('');
      const [custFormAltPhone, setCustFormAltPhone] = useState('');
      const [custFormNid, setCustFormNid] = useState('');
      const [custFormEmail, setCustFormEmail] = useState('');
      const [custFormGroup, setCustFormGroup] = useState('Retail Member');
      const [custFormCreditLimit, setCustFormCreditLimit] = useState(10000);
      const [custFormSpecialDisc, setCustFormSpecialDisc] = useState(0);

      // Customer Address Details (GPS coordinates removed)
      const [custFormDivision, setCustFormDivision] = useState('Dhaka');
      const [custFormDistrict, setCustFormDistrict] = useState('Dhaka');
      const [custFormUpazila, setCustFormUpazila] = useState('Uttara');
      const [custFormPostCode, setCustFormPostCode] = useState('1230');
      const [custFormStreet, setCustFormStreet] = useState('');
      const [custFormLandmark, setCustFormLandmark] = useState('');

      // Validation Errors & Touched States
      const [custFormErrors, setCustFormErrors] = useState({});
      const [custFormTouched, setCustFormTouched] = useState({});

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
      const [prodFormSizes, setProdFormSizes] = useState([]);
      const [prodFormColors, setProdFormColors] = useState([]);
      const [prodFormVarEdits, setProdFormVarEdits] = useState({});
      const [prodFormBundle, setProdFormBundle] = useState(false);
      const [prodFormCustomSize, setProdFormCustomSize] = useState('');
      const [prodFormCustomColor, setProdFormCustomColor] = useState('');

      const toggleProdFormOption = (setter, value) => {
        setter(prev => prev.includes(value) ? prev.filter(v => v !== value) : [...prev, value]);
      };

      const addProdFormCustomOption = (setter, value, clear) => {
        const v = value.trim();
        if (!v) return;
        setter(prev => prev.includes(v) ? prev : [...prev, v]);
        clear('');
      };

      const buildProdFormVariants = () => {
        if (!prodFormSizes.length && !prodFormColors.length) return [];
        const sizes = prodFormSizes.length ? prodFormSizes : [''];
        const colors = prodFormColors.length ? prodFormColors : [''];
        const base = (prodFormSku || 'SKU').trim();
        const rows = [];
        sizes.forEach(size => colors.forEach(color => {
          const key = `${size}|${color}`;
          const edit = prodFormVarEdits[key] || {};
          const suffix = [size.toUpperCase(), color.replace(/[^A-Za-z]/g, '').slice(0, 2).toUpperCase()].filter(Boolean).join('-');
          rows.push({
            key,
            size,
            color,
            sku: edit.sku != null ? edit.sku : `${base}-${suffix}`,
            barcode: edit.barcode != null ? edit.barcode : '',
            stock_main: edit.stock_main != null ? edit.stock_main : 0,
            stock_b2: 0,
            price: edit.price != null ? edit.price : (Number(prodFormPrice) || 0)
          });
        }));
        return rows;
      };

      const setProdFormVarEdit = (key, field, value) => {
        setProdFormVarEdits(prev => ({ ...prev, [key]: { ...(prev[key] || {}), [field]: value } }));
      };
      const [prodFormImage, setProdFormImage] = useState('');
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

      // Pay Vendor State & Validation (Modal 18)
      const [payVendorError, setPayVendorError] = useState(null);

      // Account Transfer State & Validation (Modal 20)
      const [transferAccErrors, setTransferAccErrors] = useState({});

      // Reset PIN State & Validation (Modal 23)
      const [resetPinValue, setResetPinValue] = useState('');
      const [resetPinError, setResetPinError] = useState(null);

      // Add Category State & Validation (Modal 25B)
      const [categoryErrors, setCategoryErrors] = useState({});

      // SaaS Tenant State & Validation (Modal 25C)
      const [tenantErrors, setTenantErrors] = useState({});

      // Quick Customer State & Validation (Modal 2)
      const [quickCustErrors, setQuickCustErrors] = useState({});

      // Due Collect State & Validation (Modal 9)
      const [dueCollectError, setDueCollectError] = useState(null);

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
        setProdFormSizes([]);
        setProdFormColors([]);
        setProdFormVarEdits({});
        setProdFormBundle(false);
        setProdFormImage('https://images.unsplash.com/photo-1596755094514-f87e34085b2c?auto=format&fit=crop&w=600&q=80');
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
        const pv = p.variants || [];
        setProdFormSizes([...new Set(pv.map(v => v.size).filter(Boolean))]);
        setProdFormColors([...new Set(pv.map(v => v.color).filter(Boolean))]);
        setProdFormVarEdits(Object.fromEntries(pv.map(v => [`${v.size || ''}|${v.color || ''}`, { sku: v.sku, barcode: v.barcode, stock_main: v.stock_main, price: v.price }])));
        setProdFormBundle(!!p.sell_as_bundle);
        setProdFormImage(p.image || '');
        setProdFormErrors({});
        setProdFormTouched({});
        setCurrentScreen('product_form');
      };

      const [variantPickerProduct, setVariantPickerProduct] = useState(INITIAL_PRODUCTS[0]);

      // --- Collect Due Modal (Ctrl+D) ---

      const [dueCollectCustomer, setDueCollectCustomer] = useState(null);
      const [dueCollectAmount, setDueCollectAmount] = useState('');
      const [dueCollectMethod, setDueCollectMethod] = useState('Cash');
      const [dueCollectInvoices, setDueCollectInvoices] = useState([]);

      const [lastMoneyReceipt, setLastMoneyReceipt] = useState(null);

      // --- RETURN & EXCHANGE FAST MODE (F11 / Scan Receipt) ---
      const [posMode, setPosMode] = useState('sale'); // 'sale' | 'return'
      const [returnSourceSale, setReturnSourceSale] = useState(null);
      const [returnLines, setReturnLines] = useState([]);
      const [exchangeCart, setExchangeCart] = useState([]);
      const [returnRefundMethod, setReturnRefundMethod] = useState('Cash');
      const [returnPolicyStatus, setReturnPolicyStatus] = useState('Within 7-day window ✓');
      const [returnUnknownCustomerManagerPin, setReturnUnknownCustomerManagerPin] = useState('');

      const [lastCompletedReturn, setLastCompletedReturn] = useState(null);
      const [returnSearchFilter, setReturnSearchFilter] = useState('');
      const [returnReasonFilter, setReturnReasonFilter] = useState('all');

      // --- Back-Office Entity Selection & Navigation State ---
      const [selectedCustomer, setSelectedCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [selectedProduct, setSelectedProduct] = useState(INITIAL_PRODUCTS[0]);
      const [selectedSale, setSelectedSale] = useState(MOCK_SALES[0]);
      const [selectedVendor, setSelectedVendor] = useState(MOCK_VENDORS[0]);
      const [selectedReportId, setSelectedReportId] = useState('daily_cash');
      const [reminderCustomer, setReminderCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [resetPinTarget, setResetPinTarget] = useState(null);

      // --- Modals for Back-Office Operations ---

      // --- Filter, Search & Tab States ---
      const [salesSearchQuery, setSalesSearchQuery] = useState('');
      const [salesDateFilter, setSalesDateFilter] = useState('all');
      const [salesStatusFilter, setSalesStatusFilter] = useState('all');

      const [customerSearch, setCustomerSearch] = useState('');
      const [customerGroupFilter, setCustomerGroupFilter] = useState('all');
      const [customerTab, setCustomerTab] = useState('overview');

      const [cardLayout, setCardLayout] = useState('cr80');
      const [labelPreset, setLabelPreset] = useState('standard');

      const [msgChannel, setMsgChannel] = useState('whatsapp');
      const [msgTemplate, setMsgTemplate] = useState('due_reminder');
      const [msgCustomText, setMsgCustomText] = useState('');
      const [msgRecipientFilter, setMsgRecipientFilter] = useState('all_due');

      const [settingsTab, setSettingsTab] = useState('business_profile');
      const [reportDateRange, setReportDateRange] = useState('today');

      const [auditSearchQuery, setAuditSearchQuery] = useState('');
      const [auditActionFilter, setAuditActionFilter] = useState('all');

      // --- Modal Form Field States ---
      const [adjustQty, setAdjustQty] = useState('');
      const [adjustReason, setAdjustReason] = useState('Damaged in shop');
      const [stockCheckQuery, setStockCheckQuery] = useState('');

      const [payVendorAccount, setPayVendorAccount] = useState('Cash Drawer (Main)');
      const [payVendorAmount, setPayVendorAmount] = useState('');

      const [newExpHead, setNewExpHead] = useState('Shop Rent & Service Charge');
      const [newExpAmount, setNewExpAmount] = useState('');
      const [newExpAccount, setNewExpAccount] = useState('Cash Drawer (Main)');

      // --- Vendor Purchase & Goods Received Note (GRN) States ---
      const [purchasesList, setPurchasesList] = useState(MOCK_PURCHASES);
      const [selectedPurchase, setSelectedPurchase] = useState(null);

      const [newPurVendor, setNewPurVendor] = useState('Heritage Loom Ltd');
      const [newPurBillNo, setNewPurBillNo] = useState('INV-HL-9924');
      const [newPurBranch, setNewPurBranch] = useState('Different Fashion - Main [DF1]');
      const [newPurDate, setNewPurDate] = useState('29 Oct 2026');
      const [newPurTransport, setNewPurTransport] = useState(1200);
      const [newPurLabour, setNewPurLabour] = useState(500);
      const [newPurPaid, setNewPurPaid] = useState(35000);
      const [newPurItems, setNewPurItems] = useState([
        { id: 'pi-1', product_id: 'prod-01', name_en: 'Semi-fitted Cotton Panjabi', sku: 'DF-PAN-01', size: 'L', color: 'White', qty: 25, unit_cost: 850, retail_price: 1450, total_cost: 21250 },
        { id: 'pi-2', product_id: 'prod-03', name_en: 'Pure Cotton Formal Shirt', sku: 'DF-SHT-03', size: 'XL', color: 'Sky Blue', qty: 15, unit_cost: 950, retail_price: 1650, total_cost: 14250 },
        { id: 'pi-3', product_id: 'prod-07', name_en: 'Festive Jamdani Saree', sku: 'DF-SAR-07', size: 'Free Size', color: 'Maroon Gold', qty: 6, unit_cost: 2800, retail_price: 4500, total_cost: 16800 }
      ]);
      const [purItemProductId, setPurItemProductId] = useState('prod-01');
      const [purItemSize, setPurItemSize] = useState('L');
      const [purItemColor, setPurItemColor] = useState('White');
      const [purItemQty, setPurItemQty] = useState(10);
      const [purItemCost, setPurItemCost] = useState(850);
      const [purItemRetail, setPurItemRetail] = useState(1450);

       const [transferFromAcc, setTransferFromAcc] = useState('Cash Drawer (Main)');
      const [transferToAcc, setTransferToAcc] = useState('City Bank Current A/C');
      const [transferAccAmount, setTransferAccAmount] = useState('');

      // --- Branch Stock Transfer States ---
      const [transferFrom, setTransferFrom] = useState('Different Fashion - Main [DF1]');
      const [transferTo, setTransferTo] = useState('Different Fashion - 2 [DF2]');
      const [transferQty, setTransferQty] = useState(5);
      const [transferNote, setTransferNote] = useState('Weekend stock replenishment');

      // --- Customer-Specific Card Modal State ---

      const [cardCustomer, setCardCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [bulkCardSegment, setBulkCardSegment] = useState('all_vip');
      const [selectedCardCustomerIds, setSelectedCardCustomerIds] = useState(MOCK_CUSTOMERS.slice(0, 10).map(c => c.id));

      // --- Category & Brand Management State ---
      const [catalogSubTab, setCatalogSubTab] = useState('products'); // 'products' | 'categories' | 'brands' | 'bulk_barcodes'
      const [categoriesList, setCategoriesList] = useState(CATEGORIES);

      const [newCategoryNameEn, setNewCategoryNameEn] = useState('');
      const [newCategoryNameBn, setNewCategoryNameBn] = useState('');
      const [newCategorySlug, setNewCategorySlug] = useState('');

      // --- Bulk Barcode Printing State ---

      const [barcodeSheetFormat, setBarcodeSheetFormat] = useState('thermal_50x25'); // 'thermal_50x25' | 'a4_24' | 'a4_40'
      const [selectedBarcodeProducts, setSelectedBarcodeProducts] = useState(
        INITIAL_PRODUCTS.slice(0, 8).map(p => ({ ...p, print_qty: 4 }))
      );

      // --- SaaS Super Admin Platform Multi-Tenant State ---
      const [saasTenants, setSaasTenants] = useState([
        { id: 'T-101', name: 'Different Fashion', code: 'DF', domain: 'different.retailpos.bd', plan: 'Enterprise (৳12,000/mo)', branches: 2, registers: 4, status: 'Active', mrr: 12000, joined: '15 Jan 2024' },
        { id: 'T-102', name: 'Lubnan Lifestyle Ltd', code: 'LBN', domain: 'lubnan.retailpos.bd', plan: 'Enterprise (৳12,000/mo)', branches: 5, registers: 12, status: 'Active', mrr: 12000, joined: '02 Mar 2024' },
        { id: 'T-103', name: 'Gentle Park Casuals', code: 'GP', domain: 'gentlepark.retailpos.bd', plan: 'Pro (৳4,500/mo)', branches: 3, registers: 6, status: 'Active', mrr: 4500, joined: '10 Jun 2024' },
        { id: 'T-104', name: 'Artisan Outfit & Panjabi', code: 'ART', domain: 'artisan.retailpos.bd', plan: 'Starter (৳2,500/mo)', branches: 1, registers: 2, status: 'Active', mrr: 2500, joined: '22 Aug 2024' },
        { id: 'T-105', name: 'Richman Showroom Uttara', code: 'RCH', domain: 'richman.retailpos.bd', plan: 'Pro (৳4,500/mo)', branches: 1, registers: 2, status: 'Trial (5 days left)', mrr: 4500, joined: '18 Oct 2026' }
      ]);

      const [newTenantName, setNewTenantName] = useState('');
      const [newTenantDomain, setNewTenantDomain] = useState('');
      const [newTenantPlan, setNewTenantPlan] = useState('Pro (৳4,500/mo)');
      const [newTenantPhone, setNewTenantPhone] = useState('');
      const [newTenantBranches, setNewTenantBranches] = useState(2);

      // --- WhatsApp Editable Messaging State ---
      const [editableMsgText, setEditableMsgText] = useState('');
      const [msgTargetType, setMsgTargetType] = useState('single'); // 'single' | 'bulk'
      const [bulkMsgSegment, setBulkMsgSegment] = useState('all_due');

      // --- Fast Return / Exchange Enhanced State ---
      const [returnInvoiceInput, setReturnInvoiceInput] = useState('DF1-R1-26000412');
      const [isExchangingForItems, setIsExchangingForItems] = useState(true);
      const [adjustAgainstDue, setAdjustAgainstDue] = useState(false);
      const [discountCustomReason, setDiscountCustomReason] = useState('');

      // --- Backup & Settings State ---
      const [backupStatus, setBackupStatus] = useState('idle');
      const [backupProgress, setBackupProgress] = useState(0);
      const [backupHistory, setBackupHistory] = useState([
        { id: 'b_1', filename: 'different_fashion_20261029_0200.sqlite', size: '14.2 MB', time: 'Today, 02:00 AM', type: 'Daily Cloud Sync', status: 'Success' },
        { id: 'b_2', filename: 'different_fashion_20261028_0200.sqlite', size: '14.0 MB', time: 'Yesterday, 02:00 AM', type: 'Daily Cloud Sync', status: 'Success' },
        { id: 'b_3', filename: 'different_fashion_20261027_0200.sqlite', size: '13.8 MB', time: '27 Oct 2026, 02:00 AM', type: 'Daily Cloud Sync', status: 'Success' },
        { id: 'b_4', filename: 'different_fashion_manual_20261026.sqlite', size: '13.5 MB', time: '26 Oct 2026, 05:40 PM', type: 'Manual Export', status: 'Success' }
      ]);

      const handleRunBackup = () => {
        setBackupStatus('backing_up');
        setBackupProgress(10);
        setTimeout(() => setBackupProgress(45), 300);
        setTimeout(() => setBackupProgress(80), 600);
        setTimeout(() => {
          setBackupProgress(100);
          setBackupStatus('idle');
          addToast("SQLite DB snapshot & encrypted archive generated!", "success");
        }, 1000);
      };

      // ================= PART 4: AUTH & ACCOUNT STATES =================
      const [loginUsername, setLoginUsername] = useState('sumi@differentfashion.bd');
      const [loginPassword, setLoginPassword] = useState('123456');
      const [loginShowPassword, setLoginShowPassword] = useState(false);
      const [loginRememberDevice, setLoginRememberDevice] = useState(true);
      const [loginAttempts, setLoginAttempts] = useState(0);
      const [loginIsLockedOut, setLoginIsLockedOut] = useState(false);
      const [loginStep, setLoginStep] = useState('credentials'); // 'credentials' | 'branch_picker' | 'forgot_password'
      const [forgotContact, setForgotContact] = useState('');
      const [forgotOtp, setForgotOtp] = useState('');
      const [forgotNewPass, setForgotNewPass] = useState('');
      const [forgotStep, setForgotStep] = useState('input'); // 'input' | 'otp' | 'success'
      const [switchUserPinDigits, setSwitchUserPinDigits] = useState('');

      const [activeSessions, setActiveSessions] = useState([
        { id: 'sess_1', device: 'Counter 1 POS (This Terminal)', location: 'Uttara Showroom', ip: '192.168.1.101', lastActive: 'Active now', current: true },
        { id: 'sess_2', device: 'iPad POS Mobile Companion', location: 'Dhanmondi Branch', ip: '192.168.2.14', lastActive: '18 minutes ago', current: false },
        { id: 'sess_3', device: 'Manager Laptop (Chrome/macOS)', location: 'Gulshan HQ', ip: '103.145.11.2', lastActive: '1 hour ago', current: false }
      ]);

      const [loginHistory, setLoginHistory] = useState([
        { id: 'lh_1', timestamp: 'Today, 09:15 AM', user: 'Sumi (Cashier)', terminal: 'Main Counter [DF1-R1]', ip: '192.168.1.101', status: 'Success' },
        { id: 'lh_2', timestamp: 'Yesterday, 09:20 AM', user: 'Sumi (Cashier)', terminal: 'Main Counter [DF1-R1]', ip: '192.168.1.101', status: 'Success' },
        { id: 'lh_3', timestamp: '27 Oct 2026, 02:40 PM', user: 'Farhan (Manager)', terminal: 'Office Tablet [DF1-M1]', ip: '192.168.1.105', status: 'Success' },
        { id: 'lh_4', timestamp: '26 Oct 2026, 11:10 AM', user: 'Unknown', terminal: 'Web Browser', ip: '103.204.1.9', status: 'Failed PIN' }
      ]);

      const [accountCurrentPass, setAccountCurrentPass] = useState('');
      const [accountNewPass, setAccountNewPass] = useState('');
      const [accountConfirmPass, setAccountConfirmPass] = useState('');

      const handleLoginSubmit = (e) => {
        if (e && e.preventDefault) e.preventDefault();
        if (loginIsLockedOut) {
          addToast("Terminal is locked out! 5 failed attempts exceeded. Contact Administrator.", "danger");
          return;
        }
        if (!loginUsername.trim()) {
          addToast("Enter username or mobile number!", "warning");
          return;
        }
        if (!loginPassword || loginPassword.length < 4) {
          const nextAttempts = loginAttempts + 1;
          setLoginAttempts(nextAttempts);
          if (nextAttempts >= 5) {
            setLoginIsLockedOut(true);
            addToast("Terminal locked out: 5 failed login attempts exceeded!", "danger");
          } else {
            addToast(`Invalid credentials! Attempts remaining: ${5 - nextAttempts}`, "danger");
          }
          return;
        }
        setLoginAttempts(0);
        if (activeBranches.length === 1) {
          handleBranchSelect(activeBranches[0].name);
          return;
        }
        setLoginStep('branch_picker');
        addToast("Credentials verified. Please select register branch.", "info");
      };

      const handleBranchSelect = (selectedBranch) => {
        setBranch(selectedBranch);
        setIsAuthenticated(true);
        setLoginStep('credentials');
        setCurrentScreen('pos');
        addToast(`Terminal opened at ${selectedBranch}`, "success");
      };

      const handleLogout = () => {
        setIsAuthenticated(false);
        setCurrentScreen('login');
        setLoginStep('credentials');
        addToast("Logged out of register terminal", "info");
      };

      const handlePinSwitch = (enteredPin) => {
        const pinMap = {
          '1234': { name: 'Sumi', role: 'Cashier' },
          '2345': { name: 'Rafiq', role: 'Stock Keeper' },
          '3456': { name: 'Nusrat', role: 'Accountant' },
          '5678': { name: 'Farhan', role: 'Manager' },
          '9999': { name: 'Tanvir Ahmed', role: 'Owner' }
        };
        const target = pinMap[enteredPin];
        if (target) {
          setCurrentUser(prev => ({ ...prev, name: target.name, role: target.role, pin: enteredPin }));
          setViewAsRole(target.role);
          setSwitchUserPinDigits('');
          closeModal();
          addToast(`Fast switched to ${target.name} (${target.role})`, "success");
          return true;
        } else {
          addToast("Invalid user PIN code!", "danger");
          return false;
        }
      };

      // ================= PART 4: VENDOR PROFILE & ALLOCATION STATES =================
      const [vendorProfileTab, setVendorProfileTab] = useState('info'); // 'info' | 'purchases' | 'payments' | 'returns' | 'statement'
      const [payVendorAllocationMode, setPayVendorAllocationMode] = useState('auto'); // 'auto' | 'manual'
      const [payVendorAllocations, setPayVendorAllocations] = useState({});
      const [payVendorMethod, setPayVendorMethod] = useState('Bank Transfer');
      const [payVendorReference, setPayVendorReference] = useState('');
      const [payVendorNote, setPayVendorNote] = useState('');

      // ================= PART 4: PRODUCT DETAIL & VARIANT PICKER STATES =================
      const [productDetailTab, setProductDetailTab] = useState('stock_heatmap'); // 'stock_heatmap' | 'fifo_batches' | 'movement_ledger' | 'sales_purchases' | 'price_history'

      // ================= PART 4: RETURNS WIZARD & DETAIL STATES =================
      const [selectedReturnRecord, setSelectedReturnRecord] = useState(MOCK_RETURNS[0] || null);
      const [returnWizardStep, setReturnWizardStep] = useState(1); // 1: find invoice, 2: select items, 3: refund method, 4: receipt
      const [returnWizardInvoiceQuery, setReturnWizardInvoiceQuery] = useState('DF1-R1-26000412');

      // ================= PART 4: MESSAGING TABS & LOG STATES =================
      const [messagingTab, setMessagingTab] = useState('composer'); // 'composer' | 'templates' | 'automation' | 'log' | 'opt_out'
      const [automationRules, setAutomationRules] = useState([
        { id: 'auto_1', name: 'Digital Receipt on Sale Complete', event: 'Sale Completed', channel: 'WhatsApp / SMS', status: true, delay: 'Instant' },
        { id: 'auto_2', name: 'Gentle Due Reminder at 15 Days Overdue', event: 'Due Age > 15 Days', channel: 'SMS', status: true, delay: 'Daily 10:00 AM' },
        { id: 'auto_3', name: 'Urgent Due Notice at 30 Days Overdue', event: 'Due Age > 30 Days', channel: 'WhatsApp', status: true, delay: 'Daily 11:30 AM' },
        { id: 'auto_4', name: 'VIP Customer Birthday Privilege Offer', event: 'Customer Birthday', channel: 'SMS', status: true, delay: '9:00 AM on Birthday' },
        { id: 'auto_5', name: 'High Value Return Alert to Manager (>৳5,000)', event: 'Return > ৳5,000', channel: 'WhatsApp', status: false, delay: 'Instant' }
      ]);
      const [messageLogs, setMessageLogs] = useState([
        { id: 'ml_1', date: '29 Oct 2026', time: '11:42 AM', recipient: 'Rahim Uddin', phone: '01711-000017', channel: 'WhatsApp', snippet: 'Gentle reminder: your outstanding balance of ৳1,200 is overdue...', status: 'Delivered', cost: 0.45 },
        { id: 'ml_2', date: '29 Oct 2026', time: '10:15 AM', recipient: 'Anika Tabassum', phone: '01711-000249', channel: 'SMS', snippet: 'Thank you for shopping at Different Fashion! Invoice DF1-R1-26000412...', status: 'Delivered', cost: 0.35 },
        { id: 'ml_3', date: '28 Oct 2026', time: '04:30 PM', recipient: 'Kamal Hossain', phone: '01811-000301', channel: 'SMS', snippet: 'Eid Mubarak! Enjoy 15% VIP discount on all new festive Panjabi...', status: 'Delivered', cost: 0.35 },
        { id: 'ml_4', date: '28 Oct 2026', time: '01:10 PM', recipient: 'Farzana Yasmin', phone: '01911-000412', channel: 'WhatsApp', snippet: 'Your store credit note RET-260012 of ৳1,450 is now active...', status: 'Delivered', cost: 0.45 },
        { id: 'ml_5', date: '27 Oct 2026', time: '09:05 AM', recipient: 'Tariqul Islam', phone: '01511-000523', channel: 'SMS', snippet: 'Gentle reminder: outstanding balance overdue...', status: 'Failed', cost: 0.00 }
      ]);
      const [optOutList, setOptOutList] = useState([
        { id: 'opt_1', phone: '01719-998877', name: 'Arif Chowdhury', date: '12 Oct 2026', reason: 'Customer requested via SMS STOP', channel: 'All Channels' },
        { id: 'opt_2', phone: '01822-445566', name: 'Shahana Begum', date: '04 Sep 2026', reason: 'Phone handed to minor', channel: 'SMS Only' }
      ]);
      const showAddOptOutModal = activeModal === 'add_opt_out';
      const setShowAddOptOutModal = (v) => v ? openModal('add_opt_out') : closeModal('add_opt_out');
      const [newOptOutPhone, setNewOptOutPhone] = useState('');
      const [newOptOutName, setNewOptOutName] = useState('');
      const [newOptOutReason, setNewOptOutReason] = useState('Customer preference');

      // ================= PART 4: MEMBERSHIP CARDS PREVIEW & REISSUE STATES =================
      const [cardViewTab, setCardViewTab] = useState('single'); // 'single' | 'gallery' | 'a4_10' | 'cr80_pvc'
      const [cardPreviewSide, setCardPreviewSide] = useState('front'); // 'front' | 'back'
      const [reissueTargetCustomer, setReissueTargetCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [reissueReason, setReissueReason] = useState('Lost Card');
      const [reissueOldCardNumber, setReissueOldCardNumber] = useState(MOCK_CUSTOMERS[0]?.customer_no || '1000 0017');
      const [reissueNewCardNumber, setReissueNewCardNumber] = useState('1000 0380');
      const [cardTierFilter, setCardTierFilter] = useState('all'); // 'all' | 'vip' | 'gold' | 'corporate'
      const [cardMemberSearch, setCardMemberSearch] = useState('');
      const [isCardNumberRevealed, setIsCardNumberRevealed] = useState(false);
      const [selectedBatchCardIds, setSelectedBatchCardIds] = useState(MOCK_CUSTOMERS.slice(0, 10).map(c => c.id));
      const [cardPrintSide, setCardPrintSide] = useState('both'); // 'both' | 'front' | 'back'
      const [cardPrintJob, setCardPrintJob] = useState(null);
      const [stmtQuery, setStmtQuery] = useState('');
      const [stmtCustomer, setStmtCustomer] = useState(null);
      const [stmtPeriod, setStmtPeriod] = useState('month');
      const [stmtFrom, setStmtFrom] = useState('2026-10-01');
      const [stmtTo, setStmtTo] = useState('2026-10-29');
      const [cardExportOpen, setCardExportOpen] = useState(false);
      const [cardCropMarks, setCardCropMarks] = useState(true);
      const startCardPrint = (customers, bleed = false) => {
        if (!customers || !customers.length) return;
        setCardPrintJob({ customers, bleed, cropMarks: cardCropMarks });
      };
      window.__startCardPrint = startCardPrint;
      useEffect(() => {
        if (!cardPrintJob) return;
        const t = setTimeout(() => {
          runPrintJob(
            cardPrintJob.bleed ? 'bleed' : 'cards',
            cardPrintJob.bleed ? 'size: 91.6mm 60mm; margin: 0' : 'size: 85.6mm 54mm; margin: 0',
            () => setCardPrintJob(null)
          );
        }, 120);
        return () => clearTimeout(t);
      }, [cardPrintJob]);
      const [cardA4SheetIndex, setCardA4SheetIndex] = useState(0);
      const [cardA4SheetSide, setCardA4SheetSide] = useState('front');
      const [showPrintSpecsModal, setShowPrintSpecsModal] = useState(false);
      const [cardScanTestInput, setCardScanTestInput] = useState('');
      const [cardScanTestResult, setCardScanTestResult] = useState(null);

      // Dashboard Filter & Computation States
      const [dashPeriod, setDashPeriod] = useState('today'); // 'today' | 'yesterday' | '7d' | '30d' | 'this_month' | 'custom'
      const [dashBranch, setDashBranch] = useState('all'); // 'all' | 'df1' | 'df2' | 'df3'
      const [dashCustomStart, setDashCustomStart] = useState('2026-10-01');
      const [dashCustomEnd, setDashCustomEnd] = useState('2026-10-29');
      const [dashChecklistDismissed, setDashChecklistDismissed] = useState(false);

      // Full 15-Section Store & System Settings
      const [shopSettings, setShopSettings] = useState({
        // 1. Business Profile
        shopName: 'Different Fashion',
        tradeLicense: 'TRAD/DSCC/019284/2024',
        bin: '002847192-0101',
        tin: '592817349120',
        address: 'Holding 42, Level 2, Sector 3, Uttara, Dhaka-1230',
        phone: '01711-223344, 01822-334455',
        email: 'info@differentfashion.com.bd',
        currency: 'BDT (৳)',
        timezone: 'Asia/Dhaka (GMT+6)',

        // 2. Branches
        branches: [
          { id: 'df1', code: 'DF1', name: 'Different Fashion - Main [DF1]', address: 'Sector 3, Uttara, Dhaka', phone: '01711-223344', active: true },
          { id: 'df2', code: 'DF2', name: 'Different Fashion - Dhanmondi [DF2]', address: 'Road 27, Dhanmondi, Dhaka', phone: '01822-334455', active: true },
          { id: 'df3', code: 'DF3', name: 'Different Fashion - Uttara [DF3]', address: 'Sector 11, Uttara, Dhaka', phone: '01933-445566', active: true }
        ],

        // 3. Registers & Devices
        registerId: 'DF1-R1',
        registerName: 'Main Counter Register 1',
        barcodeScannerType: 'Honeywell Orbit 7120 (USB HID / Virtual COM)',
        customerDisplayEnabled: true,
        customerDisplayPort: 'COM3 (9600 baud, 8-N-1)',
        pairingToken: 'DF-PAIR-98234-X7',

        // 4. Tax/VAT
        vatRate: 15,
        vatRegistrationName: 'Different Fashion Ltd',
        taxInclusivePricing: true,
        mushakInvoiceEnabled: true,
        mushakChallanPrefix: 'M-6.3-',

        // 5. Payment Methods
        enableCash: true,
        enableBkash: true,
        bkashMerchant: '01811223344',
        bkashApiEnabled: true,
        enableNagad: true,
        nagadMerchant: '01711223344',
        enableCard: true,
        cardPosTerminal: 'City Bank POS - Terminal #928341',
        enableDueCredit: true,
        defaultCreditLimit: 15000,
        dueDaysLimit: 30,

        // 6. Receipt & Printers
        printerType: 'esc_pos_network',
        printerIp: '192.168.1.150',
        printerPort: '9100',
        printerPaperWidth: '80mm',
        headerText: 'DIFFERENT FASHION\\nExclusive Clothing & Lifestyle\\nUttara & Dhanmondi, Dhaka',
        footerText: 'পণ্য পরিবর্তনের ক্ষেত্রে ৭ দিনের মধ্যে মূল ক্যাশ মেমো সহ যোগাযোগ করুন।\\nThank you for shopping at Different Fashion!',
        showBarcodeOnReceipt: true,
        autoCutReceipt: true,
        drawerCommand: '27,112,0,25,250',

        // 7. Invoice Numbering
        invoicePrefix: 'DF1-R1-',
        invoiceSequencePadding: 8,
        invoiceResetPeriod: 'yearly',
        currentSequenceNumber: 26000413,

        // 8. Customer PIN Policy
        requirePinForDueCollection: true,
        requirePinForPointsRedemption: true,
        pinLength: 4,
        allowOtpBypass: true,
        lockoutAfterAttempts: 3,

        // 9. Membership Card Design
        cardDimension: 'cr80',
        cardBarcodeStandard: 'Code 128',
        cardShowQr: true,
        cardBgColor: '#0f766e',
        cardTextColor: '#ffffff',
        cardShowTierBadge: true,

        // 10. Messaging Providers
        smsProvider: 'ssl_wireless',
        smsSenderId: 'DIFF-FASHION',
        smsApiKey: 'ssl_live_9a8f7b6c5d4e3f2a1b',
        sms: {
          provider: 'ssl_wireless',
          senderId: 'DIFF-FASHION',
          apiKey: 'ssl_live_9a8f7b6c5d4e3f2a1b',
          apiSecret: '',
          baseUrl: '',
          templates: {
            sale_completed: { enabled: true, text: 'Thank you {customer}! Invoice {invoice} total ৳{total}, paid ৳{paid}, due ৳{due}. — {shop}' },
            payment_received: { enabled: false, text: 'Dear {customer}, we received your payment of ৳{amount}. Remaining due ৳{due}. — {shop}' },
            due_reminder: { enabled: false, text: 'Dear {customer}, your outstanding balance at {shop} is ৳{due}. Please settle at your earliest convenience.' }
          }
        },
        whatsappEnabled: true,
        whatsappBusinessNumber: '+8801711223344',

        // 11. Returns Policy
        returnWindowDays: 7,
        requireOriginalInvoice: true,
        allowCashRefund: false,
        issueCreditNoteOnly: true,
        restockFeePercentage: 0,

        // 12. Notifications
        lowStockAlertThreshold: 5,
        emailDailySummary: true,
        emailAddress: 'admin@differentfashion.com.bd',
        smsOnEodClose: true,
        eodSmsRecipient: '01711000000',

        // 13. Appearance
        theme: 'light',
        colorScheme: 'teal',
        density: 'comfortable',

        // 14. Language
        defaultLanguage: 'en',
        enableBengaliReceipt: true,
        currencySymbol: '৳',
        bengaliNumerals: false,

        // 15. Keyboard Shortcuts
        enablePosShortcuts: true,
        enableGlobalNavShortcuts: true,

        // 16. Data Import & Export
        autoBackupDaily: true,
        backupLocation: 'Local SQLite + Cloud Archive'
      });

      // Settings Draft & Dirty State Tracker
      const activeBranches = (shopSettings.branches || []).filter(b => b.active);
      const hasMultipleBranches = activeBranches.length > 1;
      const [settingsDraft, setSettingsDraft] = useState(shopSettings);
      const [settingsIsDirty, setSettingsIsDirty] = useState(false);

      // --- Memoized Filtered Lists ---
      const filteredSales = useMemo(() => {
        return MOCK_SALES.filter(s => {
          const q = salesSearchQuery.toLowerCase().trim();
          const matchSearch = !q || s.invoice.toLowerCase().includes(q) || s.customer.toLowerCase().includes(q) || (s.customer_no && s.customer_no.includes(q));
          const matchStatus = salesStatusFilter === 'all' || s.status.toLowerCase() === salesStatusFilter.toLowerCase();
          return matchSearch && matchStatus;
        });
      }, [salesSearchQuery, salesStatusFilter]);

      const filteredCustomers = useMemo(() => {
        return MOCK_CUSTOMERS.filter(c => {
          const q = customerSearch.toLowerCase().trim();
          const matchSearch = !q || c.name_en.toLowerCase().includes(q) || (c.name_bn && c.name_bn.includes(q)) || (c.phone && c.phone.includes(q)) || (c.customer_no && c.customer_no.includes(q)) || (c.shop_name && c.shop_name.toLowerCase().includes(q));
          const matchGroup = customerGroupFilter === 'all' || (c.group && c.group.toLowerCase().includes(customerGroupFilter.toLowerCase()));
          return matchSearch && matchGroup;
        });
      }, [customerSearch, customerGroupFilter]);

      const filteredAuditLogs = useMemo(() => {
        return AUDIT_LOGS.filter(l => {
          const q = auditSearchQuery.toLowerCase().trim();
          const matchSearch = !q || (l.user && l.user.toLowerCase().includes(q)) || (l.details && l.details.toLowerCase().includes(q)) || (l.action && l.action.toLowerCase().includes(q));
          const matchAction = auditActionFilter === 'all' || (l.action && l.action.toLowerCase().includes(auditActionFilter.toLowerCase()));
          return matchSearch && matchAction;
        });
      }, [auditSearchQuery, auditActionFilter]);

      // Customer Group Discount Calculation
      const customerGroupDiscountPercent = useMemo(() => {
        if (!attachedCustomer || isGroupDiscountRemoved) return 0;
        return attachedCustomer.group_discount || (attachedCustomer.group.includes('VIP') ? 10 : 0);
      }, [attachedCustomer, isGroupDiscountRemoved]);

      // Cart calculations: Subtotal -> Line Discounts -> Group Discount -> Order Discount -> VAT 15% -> Total
      const subtotal = useMemo(() => cart.reduce((acc, item) => acc + (item.original_price * item.qty), 0), [cart]);
      const totalLineDiscounts = useMemo(() => cart.reduce((acc, item) => acc + ((item.line_discount || 0) * item.qty), 0), [cart]);
      
      const subtotalAfterLine = Math.max(0, subtotal - totalLineDiscounts);
      const groupDiscountAmount = customerGroupDiscountPercent > 0 ? Math.round(subtotalAfterLine * (customerGroupDiscountPercent / 100)) : 0;
      
      const subtotalAfterGroup = Math.max(0, subtotalAfterLine - groupDiscountAmount);
      const orderDiscountAmount = orderDiscountType === 'percent' 
        ? Math.round(subtotalAfterGroup * (orderDiscountPercent / 100))
        : Math.min(orderDiscount, subtotalAfterGroup);

      const totalAllDiscounts = totalLineDiscounts + groupDiscountAmount + orderDiscountAmount;
      const taxableSubtotal = Math.max(0, subtotal - totalAllDiscounts);
      const vatAmount = Math.round(taxableSubtotal * 0.15); // Standard 15% VAT strictly after discounts
      const totalPayable = taxableSubtotal + vatAmount;

      // Tender Calculation
      const totalTendered = useMemo(() => {
        return (Number(tenderTally.cash) || 0) +
               (Number(tenderTally.card) || 0) +
               (Number(tenderTally.bkash) || 0) +
               (Number(tenderTally.nagad) || 0) +
               (Number(tenderTally.rocket) || 0) +
               (Number(tenderTally.bank) || 0);
      }, [tenderTally]);

      const tenderChange = Math.max(0, totalTendered - totalPayable);
      const tenderDue = Math.max(0, totalPayable - totalTendered);
      const isCreditLimitExceeded = useMemo(() => {
        if (!attachedCustomer || tenderDue <= 0) return false;
        const used = attachedCustomer.credit_used || Math.abs(attachedCustomer.balance || 0);
        const limit = attachedCustomer.credit_limit || 20000;
        return (used + tenderDue) > limit;
      }, [attachedCustomer, tenderDue]);

      // Return Calculations
      const returnSubtotal = useMemo(() => {
        return returnLines.filter(l => l.selected).reduce((acc, l) => {
          const itemNet = (l.original_unit_price * l.return_qty) - ((l.line_discount || 0) * l.return_qty);
          return acc + itemNet;
        }, 0);
      }, [returnLines]);

      const returnVatReversed = Math.round(returnSubtotal * 0.15);
      const returnTotalGross = returnSubtotal + returnVatReversed;

      const exchangeSubtotal = useMemo(() => {
        return exchangeCart.reduce((acc, item) => acc + (item.price * item.qty), 0);
      }, [exchangeCart]);
      const exchangeVat = Math.round(exchangeSubtotal * 0.15);
      const exchangeTotalGross = exchangeSubtotal + exchangeVat;

      const netExchangeDiff = exchangeTotalGross - returnTotalGross;

      // Add to Cart helper
      const addToCart = (product, variant = null, qty = 1) => {
        if (posMode === 'return') {
          // If in return mode, scanned items are exchange items!
          setExchangeCart(prev => {
            const idx = prev.findIndex(p => p.id === product.id);
            if (idx >= 0) {
              const next = [...prev];
              next[idx] = { ...next[idx], qty: next[idx].qty + qty };
              return next;
            }
            return [...prev, { ...product, qty }];
          });
          addToast(`Added exchange item: ${product.name_en}`, 'info');
          return;
        }

        setCart(prev => {
          const existingIndex = prev.findIndex(item => item.product.id === product.id && (!variant || item.variant?.sku === variant?.sku));
          if (existingIndex >= 0) {
            const next = [...prev];
            next[existingIndex] = { ...next[existingIndex], qty: next[existingIndex].qty + qty };
            return next;
          }
          return [...prev, {
            product,
            variant,
            qty,
            original_price: variant?.price ?? product.price,
            unit_price: variant?.price ?? product.price,
            line_discount: 0,
            discount_type: 'none',
            discount_percent: 0,
            salesman: currentUser.name,
            note: ''
          }];
        });
        addToast(`Added "${product.name_en}" to cart`, 'info');
      };

      const [variantQtys, setVariantQtys] = useState({});

      const openVariantPicker = (product) => {
        setVariantQtys({});
        setVariantPickerProduct(product);
        setShowVariantPickerModal(true);
      };

      const confirmVariantPicker = () => {
        const lines = (variantPickerProduct?.variants || []).filter(v => (variantQtys[v.sku] || 0) > 0);
        if (!lines.length) {
          addToast('Set a quantity for at least one size', 'warning');
          return;
        }
        lines.forEach(v => addToCart(variantPickerProduct, v, variantQtys[v.sku]));
        setShowVariantPickerModal(false);
        setVariantQtys({});
        setShowPosCatalogBrowse(false);
        setPosSearchQuery('');
        setTimeout(focusScanInput, 50);
      };

      const updateCartQty = (index, delta) => {
        setCart(prev => {
          const next = [...prev];
          const newQty = next[index].qty + delta;
          if (newQty <= 0) {
            return next.filter((_, i) => i !== index);
          }
          next[index] = { ...next[index], qty: newQty };
          return next;
        });
      };

      const removeCartItem = (index) => {
        setCart(prev => prev.filter((_, i) => i !== index));
        addToast("Item removed from cart", "warning");
      };

      const clearCart = () => {
        if (cart.length === 0) return;
        setCart([]);
        setOrderDiscount(0);
        setOrderDiscountPercent(0);
        setIsGroupDiscountRemoved(false);
        addToast("Cart cleared", "warning");
      };

      const holdSale = () => {
        if (cart.length === 0) return;
        const newHold = {
          id: 'hold_' + Date.now(),
          timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          customer: attachedCustomer,
          cart: [...cart],
          orderDiscount,
          orderDiscountPercent
        };
        setHeldSales(prev => [newHold, ...prev]);
        setCart([]);
        setOrderDiscount(0);
        setOrderDiscountPercent(0);
        addToast(`Sale held (#${heldSales.length + 1})`, "info");
      };

      const recallSale = (heldSale) => {
        setCart(heldSale.cart);
        setAttachedCustomer(heldSale.customer);
        setOrderDiscount(heldSale.orderDiscount);
        setOrderDiscountPercent(heldSale.orderDiscountPercent || 0);
        setHeldSales(prev => prev.filter(h => h.id !== heldSale.id));
        setShowHeldSalesModal(false);
        addToast("Held sale restored to register", "success");
      };

      // Complete Sale Handler
      const completeSale = (tenderType = 'exact_cash') => {
        if (cart.length === 0) {
          addToast("Cart is empty!", "danger");
          return;
        }

        const paid = tenderType === 'exact_cash' ? totalPayable : totalTendered;
        const due = Math.max(0, totalPayable - paid);

        if (due > 0 && !attachedCustomer) {
          addToast("Customer must be attached to create due / credit sale!", "danger");
          setShowCustomerAttachModal(true);
          return;
        }

        if (due > 0 && isOfflineMode) {
          addToast("Due sales are disabled while offline (needs internet)!", "danger");
          return;
        }

        const saleRecord = {
          id: 's_' + Date.now(),
          invoice: `DF1-R1-26${Math.floor(100000 + Math.random() * 900000)}`,
          date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          cashier: currentUser.name,
          customer: attachedCustomer ? attachedCustomer.name_en : 'Walk-in Customer',
          customer_no: attachedCustomer ? attachedCustomer.customer_no : 'Walk-in',
          customer_phone: attachedCustomer ? attachedCustomer.phone : 'N/A',
          items: [...cart],
          subtotal,
          totalDiscount: totalAllDiscounts,
          line_discount_total: totalLineDiscounts,
          group_discount_total: groupDiscountAmount,
          order_discount_total: orderDiscountAmount,
          vatAmount,
          total: totalPayable,
          paid: Math.min(paid, totalPayable),
          due: due,
          previous_due: attachedCustomer ? Math.abs(attachedCustomer.balance || 0) : 0,
          total_due_balance: (attachedCustomer ? Math.abs(attachedCustomer.balance || 0) : 0) + due,
          tendered: paid,
          change: tenderChange,
          paymentMethod: due > 0 ? (paid === 0 ? 'Full Due Credit' : 'Partial Due') : (tenderType === 'exact_cash' ? 'Cash' : 'Split Tender'),
          status: due === 0 ? 'Paid' : (paid > 0 ? 'Partial' : 'Due'),
          sync_status: isOfflineMode ? 'Queued (Offline)' : 'Synced'
        };

        // Update customer balance if due created
        if (due > 0 && attachedCustomer) {
          attachedCustomer.balance = (attachedCustomer.balance || 0) - due;
          attachedCustomer.credit_used = (attachedCustomer.credit_used || 0) + due;
          attachedCustomer.open_invoices = [
            ...(attachedCustomer.open_invoices || []),
            { invoice: saleRecord.invoice, date: saleRecord.date, total: totalPayable, paid: paid, due: due, allocated: due }
          ];
        }

        MOCK_SALES.unshift(saleRecord);
        setLastCompletedSale(saleRecord);
        const saleSms = shopSettings.sms && shopSettings.sms.templates && shopSettings.sms.templates.sale_completed;
        if (attachedCustomer && saleSms && saleSms.enabled) {
          const smsText = renderSmsTemplate(saleSms.text, {
            customer: attachedCustomer.name_en,
            invoice: saleRecord.invoice,
            total: saleRecord.total,
            paid: saleRecord.paid,
            due: saleRecord.due,
            shop: shopSettings.shopName
          });
          setMessageLogs(prev => [{
            id: 'ml_' + Date.now(),
            date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
            time: saleRecord.time,
            recipient: attachedCustomer.name_en,
            phone: attachedCustomer.phone,
            channel: 'SMS',
            snippet: smsText,
            status: 'Delivered',
            cost: 0.35
          }, ...prev]);
          addToast(`SMS sent to ${attachedCustomer.phone}`, "success");
        }
        setShowPaymentModal(false);
        setShowReceiptModal(true);
        setCart([]);
        setOrderDiscount(0);
        setOrderDiscountPercent(0);
        setIsGroupDiscountRemoved(false);
        addToast(`Sale complete: ${saleRecord.invoice}${due > 0 ? ` (Due: ${formatMoney(due)})` : ''}`, "success");
      };

      // Start Return Mode for an invoice
      const startReturnModeForInvoice = (invoiceNo) => {
        const found = MOCK_SALES.find(s => s.invoice.toLowerCase() === invoiceNo.toLowerCase().trim()) || MOCK_SALES[1];
        setReturnSourceSale(found);
        setPosMode('return');
        setExchangeCart([]);

        const lines = (found.items || [
          { product: INITIAL_PRODUCTS[0], qty: 2, original_price: 1450, unit_price: 1450, line_discount: 0, returned_qty: 0, returnable_qty: 2 },
          { product: INITIAL_PRODUCTS[4], qty: 1, original_price: 1350, unit_price: 1350, line_discount: 0, returned_qty: 0, returnable_qty: 1 },
          { product: INITIAL_PRODUCTS[3], qty: 1, original_price: 1890, unit_price: 1890, line_discount: 0, returned_qty: 0, returnable_qty: 1 }
        ]).map((it, idx) => ({
          id: 'ret_l_' + idx,
          product: it.product,
          original_qty: it.qty,
          returned_qty: it.returned_qty || 0,
          returnable_qty: it.returnable_qty || it.qty,
          return_qty: it.returnable_qty || it.qty,
          original_unit_price: it.original_price,
          line_discount: it.line_discount || 0,
          selected: true,
          reason: 'Size issue',
          restock: 'Sellable'
        }));

        setReturnLines(lines);

        // Find customer if attached
        if (found.customer_no && found.customer_no !== 'Walk-in') {
          const cust = MOCK_CUSTOMERS.find(c => c.customer_no === found.customer_no);
          if (cust) {
            setAttachedCustomer(cust);
            if (cust.balance < 0) {
              setReturnRefundMethod('Adjust Customer Due');
            } else {
              setReturnRefundMethod('Cash');
            }
          }
        }

        setReturnPolicyStatus('Within 7-day window ✓');
        addToast(`Loaded return items for ${found.invoice}`, "info");
      };

      // Complete Return & Exchange
      const completeReturnExchange = () => {
        const selectedReturns = returnLines.filter(l => l.selected && l.return_qty > 0);
        if (selectedReturns.length === 0 && exchangeCart.length === 0) {
          addToast("Select at least one item to return or add exchange item!", "warning");
          return;
        }

        const returnRec = {
          id: 'ret_' + Date.now(),
          return_no: `RET-26${Math.floor(1000 + Math.random() * 9000)}`,
          original_invoice: returnSourceSale ? returnSourceSale.invoice : 'NO-RECEIPT',
          date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }),
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          customer: attachedCustomer ? attachedCustomer.name_en : (returnSourceSale?.customer || 'Walk-in Customer'),
          customer_no: attachedCustomer ? attachedCustomer.customer_no : (returnSourceSale?.customer_no || 'Walk-in'),
          returned_items: selectedReturns,
          exchange_items: [...exchangeCart],
          return_total: returnTotalGross,
          exchange_total: exchangeTotalGross,
          net_diff: netExchangeDiff,
          refund_method: returnRefundMethod,
          cashier: currentUser.name
        };

        // Adjust customer due if refund adjusted
        if (attachedCustomer && returnRefundMethod === 'Adjust Customer Due' && netExchangeDiff < 0) {
          const credit = Math.abs(netExchangeDiff);
          attachedCustomer.balance = (attachedCustomer.balance || 0) + credit;
          attachedCustomer.credit_used = Math.max(0, (attachedCustomer.credit_used || 0) - credit);
        }

        MOCK_RETURNS.unshift(returnRec);
        setLastCompletedReturn(returnRec);
        setShowReturnReceiptModal(true);
        setPosMode('sale');
        setReturnLines([]);
        setExchangeCart([]);
        setReturnSourceSale(null);
        addToast(`Return processed: ${returnRec.return_no}`, "success");
      };

      const focusScanInput = () => {
        const el = document.getElementById('pos-barcode-input');
        if (el) {
          el.focus();
        } else if (posScanInputRef.current) {
          posScanInputRef.current.focus();
        }
      };

      const focusSelectedLineQty = () => {
        const input = document.getElementById(`cart-line-qty-${selectedCartLineIndex}`);
        if (input) {
          input.focus();
          input.select();
        } else if (cart.length > 0) {
          setSelectedCartLineIndex(0);
          setTimeout(() => {
            const el = document.getElementById('cart-line-qty-0');
            if (el) { el.focus(); el.select(); }
          }, 40);
        }
      };

      // Open Collect Due Modal helper (Ctrl+D)
      const openCollectDueModal = (customer = null) => {
        const target = customer || attachedCustomer || MOCK_CUSTOMERS.find(c => c.balance < 0) || MOCK_CUSTOMERS[0];
        setDueCollectCustomer(target);
        const dueAmt = Math.abs(target.balance || 0);
        setDueCollectAmount(dueAmt.toString());
        setDueCollectInvoices(target.open_invoices && target.open_invoices.length > 0 ? target.open_invoices : [
          { invoice: 'DF1-R1-26000411', date: '25 Oct 2026', total: 5450, paid: 4250, due: dueAmt, allocated: dueAmt }
        ]);
        setShowDueCollectModal(true);
      };

      // Execute Collect Due payment
      const handleExecuteCollectDue = () => {
        const amt = Number(dueCollectAmount) || 0;
        const maxDue = Math.abs(dueCollectCustomer?.balance || 0);
        if (!dueCollectCustomer || amt <= 0) {
          setDueCollectError("Please enter a valid collection amount greater than ৳0");
          addToast("Enter valid collection amount", "warning");
          return;
        }
        if (amt > maxDue) {
          setDueCollectError(`Amount cannot exceed total due balance (${formatMoney(maxDue)})`);
          addToast("Amount exceeds total due balance", "warning");
          return;
        }
        setDueCollectError(null);

        const receiptRecord = {
          receipt_no: `MR-26${Math.floor(1000 + Math.random() * 9000)}`,
          date: new Date().toLocaleDateString('en-GB'),
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          customer: dueCollectCustomer.name_en,
          customer_no: dueCollectCustomer.customer_no,
          phone: dueCollectCustomer.phone,
          previous_due: Math.abs(dueCollectCustomer.balance || 0),
          amount_paid: amt,
          new_due: Math.max(0, Math.abs(dueCollectCustomer.balance || 0) - amt),
          method: dueCollectMethod,
          cashier: currentUser.name
        };

        dueCollectCustomer.balance = (dueCollectCustomer.balance || 0) + amt;
        dueCollectCustomer.credit_used = Math.max(0, (dueCollectCustomer.credit_used || 0) - amt);

        setLastMoneyReceipt(receiptRecord);
        setShowDueCollectModal(false);
        setShowMoneyReceiptModal(true);
        addToast(`Collected ${formatMoney(amt)} from ${dueCollectCustomer.name_en}`, "success");
      };

      // ================= GLOBAL KEYBOARD SHORTCUTS ENGINE =================
      // Strict rule: No key conflicts. Function keys while a modal is open must not open background modals.
      useEffect(() => {
        let gKeySequence = false;
        let gKeyTimeout = null;

        const handleKeyDown = (e) => {
          const activeEl = document.activeElement;
          const isTextInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable) && (activeEl.id !== 'pos-barcode-input' || (activeEl.value && activeEl.value.length > 0));

          // If terminal is locked, only allow unlock interactions
          if (isLocked) {
            return;
          }

          // Esc handling: closes top-most overlay only
          if (e.key === 'Escape') {
            if (contextMenu.visible) { setContextMenu({ visible: false, x: 0, y: 0, items: [] }); return; }
            if (secondaryModal) { setSecondaryModal(null); return; }
            if (activeModal) {
              if (activeModal === 'payment' && (tenderTally.cash > 0 || tenderTally.card > 0 || tenderTally.bkash > 0)) {
                if (window.confirm("Discard current tendered payment?")) {
                  closeModal('payment');
                }
                return;
              }
              if (activeModal === 'customer_pin_pad' && pinDigits.length > 0) {
                if (window.confirm("Discard customer PIN entry?")) {
                  closeModal('customer_pin_pad');
                }
                return;
              }
              closeModal();
              return;
            }
            if (posMode === 'return') { setPosMode('sale'); addToast("Exited return mode", "info"); return; }
            focusScanInput();
            return;
          }

          // IF A MODAL IS CURRENTLY OPEN:
          if (activeModal !== null) {
            // Inside Payment Modal (activeModal === 'payment')
            if (activeModal === 'payment') {
              if (e.key === 'F10') {
                e.preventDefault();
                completeSale('split');
                return;
              }
              if (e.altKey && (e.key === 'e' || e.key === 'E')) {
                e.preventDefault();
                setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                addToast("Exact cash applied (Alt+E)", "info");
                return;
              }
            }
            // Strict Spec Rule: Pressing a function key while a modal is open must NOT open another modal behind it!
            if (e.key.startsWith('F') || (e.ctrlKey && (e.key === 'd' || e.key === 'n' || e.key === 'k' || e.key === 'b' || e.key === 'l'))) {
              e.preventDefault();
              return;
            }
            return;
          }

          // Command Palette (Ctrl+K)
          if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            openModal('command_palette');
            return;
          }

          // Toggle Sidebar (Ctrl+B)
          if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            setSidebarCollapsed(prev => !prev);
            return;
          }

          // Lock Terminal (Ctrl+L)
          if ((e.ctrlKey || e.metaKey) && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            setIsLocked(true);
            return;
          }

          // Collect Due (Ctrl+D)
          if ((e.ctrlKey || e.metaKey) && (e.key === 'd' || e.key === 'D')) {
            e.preventDefault();
            openCollectDueModal();
            return;
          }

          // Quick New Customer (Ctrl+N)
          if ((e.ctrlKey || e.metaKey) && (e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            openModal('quick_new_customer');
            return;
          }

          // Order Discount (Shift+F6)
          if (e.shiftKey && e.key === 'F6') {
            e.preventDefault();
            setDiscountTarget('order');
            setDiscountTab('percent');
            setDiscountValue('');
            setShowManagerPinInline(false);
            openModal('discount');
            return;
          }

          // Line Discount (F6)
          if (e.key === 'F6' && !e.shiftKey) {
            e.preventDefault();
            if (cart.length > 0) {
              setDiscountTarget('line');
              setDiscountTab('percent');
              setDiscountValue('');
              setShowManagerPinInline(false);
              openModal('discount');
            } else {
              addToast("Add an item first to apply line discount", "warning");
            }
            return;
          }

          // Parked / Held Sales List (Shift+F8)
          if (e.shiftKey && e.key === 'F8') {
            e.preventDefault();
            openModal('held_sales');
            return;
          }

          // Hold Cart (F8)
          if (e.key === 'F8' && !e.shiftKey) {
            e.preventDefault();
            if (cart.length > 0) {
              holdSale();
            } else if (heldSales.length > 0) {
              openModal('held_sales');
            } else {
              addToast("Cart is empty - nothing to hold", "info");
            }
            return;
          }

          // Return / Exchange Mode (F11)
          if (e.key === 'F11') {
            e.preventDefault();
            if (posMode === 'sale') {
              startReturnModeForInvoice('DF1-R1-26000412');
            } else {
              setPosMode('sale');
              addToast("Returned to Sale mode", "info");
            }
            return;
          }

          // POS Switch (F1)
          if (e.key === 'F1') {
            e.preventDefault();
            setCurrentScreen('pos');
            setPosMode('sale');
            focusScanInput();
            return;
          }

          // Focus Scan Box (F2)
          if (e.key === 'F2') {
            e.preventDefault();
            focusScanInput();
            return;
          }

          // Quantity of Selected Cart Line (F3)
          if (e.key === 'F3') {
            e.preventDefault();
            focusSelectedLineQty();
            return;
          }

          // Attach Customer (F4)
          if (e.key === 'F4') {
            e.preventDefault();
            openModal('customer_attach');
            return;
          }

          // Price & Stock Check (F5)
          if (e.key === 'F5') {
            e.preventDefault();
            openModal('price_check');
            return;
          }

          // Quick Tender Alt+C (Cash), Alt+B (bKash), Alt+K (Card)
          if (e.altKey && !e.ctrlKey && !e.metaKey) {
            const k = e.key.toLowerCase();
            if (k === 'c') {
              e.preventDefault();
              if (cart.length > 0) {
                setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                openModal('payment');
              } else {
                addToast("Cart is empty!", "danger");
              }
              return;
            }
            if (k === 'b') {
              e.preventDefault();
              if (cart.length > 0) {
                setTenderTally({ cash: 0, card: 0, bkash: totalPayable, nagad: 0, rocket: 0, bank: 0 });
                openModal('payment');
              } else {
                addToast("Cart is empty!", "danger");
              }
              return;
            }
            if (k === 'k') {
              e.preventDefault();
              if (cart.length > 0) {
                setTenderTally({ cash: 0, card: totalPayable, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
                openModal('payment');
              } else {
                addToast("Cart is empty!", "danger");
              }
              return;
            }
          }

          // PAY (F9)
          if (e.key === 'F9') {
            e.preventDefault();
            if (cart.length > 0) {
              setTenderTally({ cash: totalPayable, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
              openModal('payment');
            } else {
              addToast("Cart is empty!", "danger");
            }
            return;
          }

          // Ctrl+S / Cmd+S in Settings to save
          if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            if (currentScreen === 'settings') {
              e.preventDefault();
              setShopSettings(settingsDraft);
              setSettingsIsDirty(false);
              addToast("Settings successfully saved!", "success");
              return;
            }
          }

          if (e.key === '/' && !isTextInput && currentScreen === 'customer_statement') {
            e.preventDefault();
            const box = document.getElementById('stmt-search');
            if (box) box.focus();
            return;
          }

          // Shortcuts overlay '?'
          if (e.key === '?' && !isTextInput) {
            e.preventDefault();
            openModal('shortcuts');
            return;
          }

          // 'G' Navigation Sequences (strictly checked against permissions!)
          if (!isTextInput && !e.ctrlKey && !e.metaKey && !e.altKey) {
            if (e.key === 'g' || e.key === 'G') {
              e.preventDefault();
              gKeySequence = true;
              clearTimeout(gKeyTimeout);
              gKeyTimeout = setTimeout(() => { gKeySequence = false; }, 1000);
              return;
            }

            if (gKeySequence) {
              e.preventDefault();
              gKeySequence = false;
              clearTimeout(gKeyTimeout);
              const key = e.key.toLowerCase();
              if (key === 'd') { navigateToScreen('dashboard'); return; }
              if (key === 's') { navigateToScreen('sales'); return; }
              if (key === 't') { navigateToScreen('returns'); return; }
              if (key === 'u') { navigateToScreen('due_collection'); return; }
              if (key === 'c') { navigateToScreen('customers'); return; }
              if (key === 'a') { navigateToScreen('customer_statement'); return; }
              if (key === 'w') { navigateToScreen('messaging'); return; }
              if (key === 'i') { navigateToScreen('products'); return; }
              if (key === 'k') { navigateToScreen('stock'); return; }
              if (key === 'p') { navigateToScreen('purchases'); return; }
              if (key === 'v') { navigateToScreen('vendors'); return; }
              if (key === 'm') { navigateToScreen('money'); return; }
              if (key === 'r') { navigateToScreen('reports'); return; }
              if (key === ',') { navigateToScreen('settings'); return; }
            }
          }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
      }, [cart, totalPayable, tenderTally, heldSales, attachedCustomer, contextMenu.visible, activeModal, secondaryModal, isLocked, posMode, returnLines, exchangeCart, selectedCartLineIndex, viewAsRole, currentScreen, settingsDraft]);

      // Global window exports for CDP testing and automated suites
      window.__setCatalogSubTab = setCatalogSubTab;
      window.__setSettingsTab = setSettingsTab;
      window.__setImportStep = setImportStep;
      window.__setBrandForm = setBrandForm;
      window.__setVendorForm = setVendorForm;
      window.__setBranchFormData = setBranchFormData;
      window.__handleSaveBrand = handleSaveBrand;
      window.__handleSaveVendor = handleSaveVendor;
      window.__handleSaveBranch = handleSaveBranch;
      window.__handleSaveStocktakeAudit = handleSaveStocktakeAudit;
      window.__handleExecuteCsvImport = handleExecuteCsvImport;
      window.__brandsList = brandsList;
      window.__vendorsList = vendorsList;
"""
