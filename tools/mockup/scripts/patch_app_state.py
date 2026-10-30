#!/usr/bin/env python3
"""
scripts/patch_app_state.py
Applies Part 1 updates to app_state.py:
1. Unified modal state (activeModal & secondaryModal) and compatibility getters/setters for all modals
2. canAccess(screenId, role) and navigateToScreen(screenId)
3. focusSelectedLineQty()
4. Exact keydown listener:
   - Suppress function keys while activeModal !== null
   - Payment modal: F10 completes sale, Alt+E exact cash
   - POS hotkeys: F1-F6, Shift+F6, F8, Shift+F8, F9, F11, Ctrl+D, Ctrl+N, Ctrl+L, Ctrl+K, Ctrl+B, ?
   - G sequences: G D, G S, G T, G U, G C, G W, G I, G K, G P, G V, G M, G R, G , routed through navigateToScreen
"""

import os
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
TARGET_FILE = os.path.join(BASE_DIR, "scripts", "parts", "app_state.py")

with open(TARGET_FILE, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Replace the scattered modal useState declarations with unified activeModal system
old_modals_block = """      // Modals
      const [showCustomerAttachModal, setShowCustomerAttachModal] = useState(false);
      const [customerSearchQuery, setCustomerSearchQuery] = useState('');
      const [showCustomerPinPadModal, setShowCustomerPinPadModal] = useState(false);
      const [pinPadState, setPinPadState] = useState('input'); // input, success, wrong, locked, offline, no_pin
      const [pinDigits, setPinDigits] = useState('');
      const [pinAttemptsLeft, setPinAttemptsLeft] = useState(2);

      // Tender & Payment
      const [showPaymentModal, setShowPaymentModal] = useState(false);
      const [tenderTally, setTenderTally] = useState({ cash: 4690, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0 });
      const [dueCustomerPin, setDueCustomerPin] = useState('');
      const [duePromiseDate, setDuePromiseDate] = useState('2026-11-05');
      const [dueNote, setDueNote] = useState('');
      const [dueManagerPinOverride, setDueManagerPinOverride] = useState('');
      const [showReceiptModal, setShowReceiptModal] = useState(false);
      const [showA4InvoiceModal, setShowA4InvoiceModal] = useState(false);
      const [lastCompletedSale, setLastCompletedSale] = useState(null);

      // Discount Popover / Modal (F6 line, Shift+F6 order)
      const [showDiscountModal, setShowDiscountModal] = useState(false);
      const [discountTarget, setDiscountTarget] = useState('line'); // 'line' | 'order'
      const [discountTab, setDiscountTab] = useState('percent'); // 'percent' | 'amount' | 'set_price'
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

      // Price Check Modal (F7)
      const [showPriceCheckModal, setShowPriceCheckModal] = useState(false);
      const [priceCheckQuery, setPriceCheckQuery] = useState('');

      const [showHeldSalesModal, setShowHeldSalesModal] = useState(false);
      const [showQuickNewCustomerModal, setShowQuickNewCustomerModal] = useState(false);"""

new_modals_block = """      // ================= UNIFIED MODAL & OVERLAY SYSTEM =================
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

      const showStockCheckModal = activeModal === 'stock_check';
      const setShowStockCheckModal = (v) => v ? openModal('stock_check') : closeModal('stock_check');

      // Modals State Payloads
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
      const [priceCheckQuery, setPriceCheckQuery] = useState('');"""

if old_modals_block in content:
    content = content.replace(old_modals_block, new_modals_block)
    print("Replaced modals block with unified activeModal system.")
else:
    print("Error: old_modals_block not found!")

# 2. Add canAccess(screenId) and navigateToScreen(screenId) right near screen state
screen_nav_anchor = "const [currentScreen, setCurrentScreen] = useState('pos');"
permissions_code = """const [currentScreen, setCurrentScreen] = useState('pos');

      // Master Permissions Check & Navigation
      const canAccess = (screenId, role = viewAsRole) => {
        if (!screenId) return true;
        if (role === 'Super Admin' || role === 'Owner') return true;
        if (role === 'Manager') {
          return screenId !== 'super_admin';
        }
        if (role === 'Cashier') {
          // Cashier is strictly restricted from:
          // purchases (G P), money (G M), dashboard (G D), reports (G R), settings (G ,), stock (G K), vendors (G V), fixed_assets, users, backup, audit_log, super_admin
          const cashierAllowed = [
            'pos', 'sales', 'sale_detail', 'returns', 'due_collection', 
            'customers', 'customer_profile', 'customer_form', 'membership_cards', 
            'products', 'product_detail', 'messaging', 'states_demo'
          ];
          return cashierAllowed.includes(screenId);
        }
        if (role === 'Stock Keeper') {
          const stockAllowed = ['stock', 'products', 'product_detail', 'product_form', 'purchases', 'vendors', 'vendor_profile'];
          return stockAllowed.includes(screenId);
        }
        if (role === 'Accountant') {
          const accAllowed = ['dashboard', 'money', 'reports', 'sales', 'sale_detail', 'returns', 'due_collection', 'purchases', 'vendors', 'vendor_profile', 'fixed_assets', 'customers', 'customer_profile'];
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
      };"""

if screen_nav_anchor in content and "const canAccess =" not in content:
    content = content.replace(screen_nav_anchor, permissions_code, 1)
    print("Added canAccess and navigateToScreen functions.")

# 3. Add focusSelectedLineQty helper
focus_scan_anchor = """      const focusScanInput = () => {
        if (posScanInputRef.current) {
          posScanInputRef.current.focus();
        }
      };"""

new_focus_helpers = """      const focusScanInput = () => {
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
      };"""

if focus_scan_anchor in content:
    content = content.replace(focus_scan_anchor, new_focus_helpers)
    print("Added focusSelectedLineQty and updated focusScanInput.")

# 4. Replace Global Keyboard Handler with strict Part 1 spec
old_keydown_start = "      // Global Keyboard Handling (F6 line disc, Shift+F6 order disc, Ctrl+D due, F11 return)\n      useEffect(() => {"
# Find the whole useEffect for keydown
keydown_pattern = re.compile(
    r"      // Global Keyboard Handling.*?window\.addEventListener\('keydown', handleKeyDown\);\s*return \(\) => window\.removeEventListener\('keydown', handleKeyDown\);\s*\}, \[.*?\]\);",
    re.DOTALL
)

new_keydown = """      // ================= GLOBAL KEYBOARD SHORTCUTS ENGINE =================
      // Strict rule: No key conflicts. Function keys while a modal is open must not open background modals.
      useEffect(() => {
        let gKeySequence = false;
        let gKeyTimeout = null;

        const handleKeyDown = (e) => {
          const activeEl = document.activeElement;
          const isTextInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable);

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

          // Shortcuts overlay '?'
          if (e.key === '?' && !isTextInput) {
            e.preventDefault();
            openModal('shortcuts');
            return;
          }

          // 'G' Navigation Sequences (strictly checked against permissions!)
          if (!isTextInput && !e.ctrlKey && !e.metaKey && !e.altKey) {
            if (e.key === 'g' || e.key === 'G') {
              gKeySequence = true;
              clearTimeout(gKeyTimeout);
              gKeyTimeout = setTimeout(() => { gKeySequence = false; }, 1000);
              return;
            }

            if (gKeySequence) {
              gKeySequence = false;
              clearTimeout(gKeyTimeout);
              const key = e.key.toLowerCase();
              if (key === 'd') { navigateToScreen('dashboard'); return; }
              if (key === 's') { navigateToScreen('sales'); return; }
              if (key === 't') { navigateToScreen('returns'); return; }
              if (key === 'u') { navigateToScreen('due_collection'); return; }
              if (key === 'c') { navigateToScreen('customers'); return; }
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
      }, [cart, totalPayable, tenderTally, heldSales, attachedCustomer, contextMenu.visible, activeModal, secondaryModal, isLocked, posMode, returnLines, exchangeCart, selectedCartLineIndex, viewAsRole]);"""

match = keydown_pattern.search(content)
if match:
    content = content[:match.start()] + new_keydown + content[match.end():]
    print("Replaced global keydown handler with exact spec key map.")
else:
    print("Error: keydown_pattern not matched in app_state.py!")

with open(TARGET_FILE, "w", encoding="utf-8") as f:
    f.write(content)

print("app_state.py patch complete.")
