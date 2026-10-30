#!/usr/bin/env python3
"""
scripts/clean_duplicate_states.py
Removes duplicate useState declarations in app_state.py and fixes regex escapes in render_all_modals.py
"""

import os
import re

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
PARTS_DIR = os.path.join(BASE_DIR, "scripts", "parts")

# 1. Clean app_state.py
app_state_path = os.path.join(PARTS_DIR, "app_state.py")
with open(app_state_path, "r", encoding="utf-8") as f:
    text = f.read()

# Lines to remove from app_state.py because they are now getters/setters:
duplicates_to_remove = [
    "const [showSyncCenter, setShowSyncCenter] = useState(false);",
    "const [showTodaySummaryPopover, setShowTodaySummaryPopover] = useState(false);",
    "const [showDaySummaryPrintModal, setShowDaySummaryPrintModal] = useState(false);",
    "const [showShortcutsModal, setShowShortcutsModal] = useState(false);",
    "const [showCommandPalette, setShowCommandPalette] = useState(false);",
    "const [showVariantPickerModal, setShowVariantPickerModal] = useState(false);",
    "const [showDueCollectModal, setShowDueCollectModal] = useState(false);",
    "const [showMoneyReceiptModal, setShowMoneyReceiptModal] = useState(false);",
    "const [showReturnReceiptModal, setShowReturnReceiptModal] = useState(false);",
    "const [showAccountTransferModal, setShowAccountTransferModal] = useState(false);",
    "const [showAddStaffModal, setShowAddStaffModal] = useState(false);",
    "const [showBarcodeLabelModal, setShowBarcodeLabelModal] = useState(false);",
    "const [showNewExpenseModal, setShowNewExpenseModal] = useState(false);",
    "const [showNewPurchaseModal, setShowNewPurchaseModal] = useState(false);",
    "const [showPayVendorModal, setShowPayVendorModal] = useState(false);",
    "const [showReminderModal, setShowReminderModal] = useState(false);",
    "const [showResetPinModal, setShowResetPinModal] = useState(false);",
    "const [showStockAdjustmentModal, setShowStockAdjustmentModal] = useState(false);",
    "const [showStockCheckModal, setShowStockCheckModal] = useState(false);",
    "const [showStockTransferModal, setShowStockTransferModal] = useState(false);",
    "const [showStocktakeModal, setShowStocktakeModal] = useState(false);",
    "const [showPurchaseDetailModal, setShowPurchaseDetailModal] = useState(false);",
    "const [showCustomerCardModal, setShowCustomerCardModal] = useState(false);",
    "const [showAddCategoryModal, setShowAddCategoryModal] = useState(false);",
    "const [showBulkBarcodeModal, setShowBulkBarcodeModal] = useState(false);",
    "const [showNewTenantModal, setShowNewTenantModal] = useState(false);"
]

for dup in duplicates_to_remove:
    # Remove only occurrences that are standalone useState calls
    text = re.sub(r'^\s*' + re.escape(dup) + r'\s*$', '', text, flags=re.MULTILINE)

with open(app_state_path, "w", encoding="utf-8") as f:
    f.write(text)
print("Cleaned duplicate modal useState declarations from app_state.py.")

# 2. Fix escape in render_all_modals.py
render_modals_path = os.path.join(PARTS_DIR, "render_all_modals.py")
with open(render_modals_path, "r", encoding="utf-8") as f:
    modals_text = f.read()

modals_text = modals_text.replace("const digitsOnly = q.replace(/[\\s-]/g, '');", "const digitsOnly = q.replace(/\\\\s|-/g, '');")
modals_text = modals_text.replace("const isEightDigits = /^\\d{8}$/.test(digitsOnly);", "const isEightDigits = /^\\\\d{8}$/.test(digitsOnly);")

with open(render_modals_path, "w", encoding="utf-8") as f:
    f.write(modals_text)
print("Fixed regex escape in render_all_modals.py.")
