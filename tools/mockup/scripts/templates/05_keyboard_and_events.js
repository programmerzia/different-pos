      // Global Keyboard Handling
      useEffect(() => {
        const handleKeyDown = (e) => {
          const activeEl = document.activeElement;
          const isTextInput = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable);

          // Esc handling
          if (e.key === 'Escape') {
            if (contextMenu.visible) { setContextMenu({ visible: false, x: 0, y: 0, items: [] }); return; }
            if (showCommandPalette) { setShowCommandPalette(false); return; }
            if (showShortcutsModal) { setShowShortcutsModal(false); return; }
            if (showCustomerPinPadModal) { setShowCustomerPinPadModal(false); return; }
            if (showCustomerAttachModal) { setShowCustomerAttachModal(false); return; }
            if (showPaymentModal) { setShowPaymentModal(false); return; }
            if (showReceiptModal) { setShowReceiptModal(false); return; }
            if (showA4InvoiceModal) { setShowA4InvoiceModal(false); return; }
            if (showPriceCheckModal) { setShowPriceCheckModal(false); return; }
            if (showReturnExchangeModal) { setShowReturnExchangeModal(false); return; }
            if (showHeldSalesModal) { setShowHeldSalesModal(false); return; }
            if (showQuickNewCustomerModal) { setShowQuickNewCustomerModal(false); return; }
            if (showDiscountModal) { setShowDiscountModal(false); return; }
            if (showCashInOutModal) { setShowCashInOutModal(false); return; }
            if (showVariantPickerModal) { setShowVariantPickerModal(false); return; }
            if (showThemePopover) { setShowThemePopover(false); return; }
            if (showNotifications) { setShowNotifications(false); return; }
            if (showAvatarMenu) { setShowAvatarMenu(false); return; }
            if (showSyncCenter) { setShowSyncCenter(false); return; }
            focusScanInput();
            return;
          }

          // Ctrl+K: Command Palette
          if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            setShowCommandPalette(prev => !prev);
            return;
          }

          // Ctrl+B: Toggle Sidebar
          if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            setSidebarCollapsed(prev => !prev);
            return;
          }

          // Ctrl+L: Lock Terminal
          if ((e.ctrlKey || e.metaKey) && (e.key === 'l' || e.key === 'L')) {
            e.preventDefault();
            setIsLocked(true);
            return;
          }

          // Ctrl+N: Quick New Customer
          if ((e.ctrlKey || e.metaKey) && (e.key === 'n' || e.key === 'N')) {
            e.preventDefault();
            setShowQuickNewCustomerModal(true);
            return;
          }

          // Shift+F10: Context Menu Parity
          if (e.shiftKey && e.key === 'F10') {
            e.preventDefault();
            const rect = activeEl?.getBoundingClientRect?.() || { left: window.innerWidth / 2, top: window.innerHeight / 2 };
            openContextMenu({ clientX: rect.left + 50, clientY: rect.top + 20, preventDefault: () => {}, stopPropagation: () => {} }, 'Action Menu', [
              { label: 'POS Register', shortcut: 'F1', action: () => setCurrentScreen('pos') },
              { label: 'Search / Scan', shortcut: 'F2', action: focusScanInput },
              { label: 'Attach Customer', shortcut: 'F4', action: () => setShowCustomerAttachModal(true) },
              { label: 'Apply Discount', shortcut: 'F6', action: () => setShowDiscountModal(true) },
              { label: 'Hold Sale', shortcut: 'F8', action: () => addToast('Sale held', 'accent') },
              { type: 'separator' },
              { label: 'Lock Terminal', shortcut: 'Ctrl+L', action: () => setIsLocked(true), destructive: true }
            ]);
            return;
          }

          // '?' for Shortcuts Directory (when not typing in text field)
          if (e.key === '?' && !isTextInput) {
            e.preventDefault();
            setShowShortcutsModal(prev => !prev);
            return;
          }

          // 'G' then letter sequence navigation
          if (window.__lastKeyPressed === 'g' || window.__lastKeyPressed === 'G') {
            const keyLower = e.key.toLowerCase();
            if (['d', 's', 't', 'u', 'c', 'w', 'i', 'k', 'p', 'v', 'm', 'r', ','].includes(keyLower)) {
              e.preventDefault();
              window.__lastKeyPressed = null;
              if (keyLower === 'd') setCurrentScreen('dashboard');
              if (keyLower === 's') setCurrentScreen('sales');
              if (keyLower === 't') setCurrentScreen('returns');
              if (keyLower === 'u') setCurrentScreen('due_collection');
              if (keyLower === 'c') setCurrentScreen('customers');
              if (keyLower === 'w') setCurrentScreen('messaging');
              if (keyLower === 'i') setCurrentScreen('products');
              if (keyLower === 'k') setCurrentScreen('stock');
              if (keyLower === 'p') setCurrentScreen('purchases');
              if (keyLower === 'v') setCurrentScreen('vendors');
              if (keyLower === 'm') setCurrentScreen('money');
              if (keyLower === 'r') setCurrentScreen('reports');
              if (keyLower === ',') setCurrentScreen('settings');
              return;
            }
          }
          if ((e.key === 'g' || e.key === 'G') && !isTextInput) {
            window.__lastKeyPressed = 'g';
            setTimeout(() => { window.__lastKeyPressed = null; }, 800);
            return;
          }

          // F-keys
          if (e.key === 'F1') {
            e.preventDefault();
            setCurrentScreen('pos');
            focusScanInput();
            return;
          }
          if (e.key === 'F2') {
            e.preventDefault();
            setCurrentScreen('pos');
            posScanInputRef.current?.focus();
            posScanInputRef.current?.select();
            return;
          }
          if (e.key === 'F3' && currentScreen === 'pos') {
            e.preventDefault();
            if (cart.length > 0) {
              const newQty = prompt("Enter new quantity for selected line:", cart[selectedCartLineIndex]?.qty || 1);
              if (newQty && !isNaN(newQty) && Number(newQty) > 0) {
                setCart(prev => {
                  const updated = [...prev];
                  updated[selectedCartLineIndex].qty = Number(newQty);
                  return updated;
                });
              }
            }
            return;
          }
          if (e.key === 'F4') {
            e.preventDefault();
            setShowCustomerAttachModal(true);
            return;
          }
          if (e.key === 'F5') {
            e.preventDefault();
            setShowPriceCheckModal(true);
            return;
          }
          if (e.key === 'F6') {
            e.preventDefault();
            setShowDiscountModal(true);
            return;
          }
          if (e.key === 'F7') {
            e.preventDefault();
            setShowCashInOutModal(true);
            return;
          }
          if (e.key === 'F8') {
            e.preventDefault();
            if (e.shiftKey) {
              setShowHeldSalesModal(true);
            } else {
              if (cart.length === 0) {
                addToast("Cart is empty to hold", "warning");
              } else {
                setHeldSales(prev => [...prev, { id: 'H-' + Date.now().toString().slice(-4), time: new Date().toLocaleTimeString(), customer: attachedCustomer, cart, grandTotal }]);
                setCart([]);
                addToast("Sale held (F8)", "accent");
              }
            }
            return;
          }
          if (e.key === 'F9') {
            e.preventDefault();
            if (cart.length === 0) {
              addToast("Cannot checkout an empty cart", "warning");
            } else {
              setTenderTally({ cash: grandTotal, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0, due: 0 });
              setShowPaymentModal(true);
            }
            return;
          }
          if (e.key === 'F10') {
            e.preventDefault();
            if (showPaymentModal) {
              completePayment();
            }
            return;
          }
          if (e.key === 'F11') {
            e.preventDefault();
            setShowReturnExchangeModal(true);
            return;
          }

          // Delete key removes selected cart item if not typing
          if (e.key === 'Delete' && !isTextInput && currentScreen === 'pos') {
            e.preventDefault();
            if (cart.length > 0) {
              setCart(prev => prev.filter((_, idx) => idx !== selectedCartLineIndex));
              setSelectedCartLineIndex(prev => Math.max(0, prev - 1));
              addToast("Removed item from cart", "info");
            }
            return;
          }

          // Alt shortcuts
          if (e.altKey) {
            if (e.key >= '1' && e.key <= '9') {
              e.preventDefault();
              const catIndex = Number(e.key) - 1;
              if (CATEGORIES[catIndex]) {
                setPosCategory(CATEGORIES[catIndex].id);
                addToast(`Filter: ${CATEGORIES[catIndex].name_en}`, 'info');
              }
            } else if (e.key === 'b' || e.key === 'B') {
              e.preventDefault();
              setBranch(prev => prev.includes('Main') ? 'Different Fashion - 2 [DF2]' : 'Different Fashion - Main [DF1]');
              addToast(`Switched to: ${branch.includes('Main') ? 'Branch 2' : 'Main Branch'}`, 'info');
            } else if (e.key === 'l' || e.key === 'L') {
              e.preventDefault();
              setLang(prev => prev === 'en' ? 'bn' : 'en');
            } else if (e.key === 't' || e.key === 'T') {
              e.preventDefault();
              setShowThemePopover(prev => !prev);
            } else if (e.key === 'n' || e.key === 'N') {
              e.preventDefault();
              setShowNotifications(prev => !prev);
            } else if (e.key === 'y' || e.key === 'Y') {
              e.preventDefault();
              setShowSyncCenter(prev => !prev);
            }
          }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
      }, [cart, selectedCartLineIndex, grandTotal, showPaymentModal, currentScreen, attachedCustomer, branch, showCommandPalette, showShortcutsModal, showCustomerAttachModal, showCustomerPinPadModal, showReceiptModal, showA4InvoiceModal, showPriceCheckModal, showReturnExchangeModal, showHeldSalesModal, showQuickNewCustomerModal, showDiscountModal, showCashInOutModal, showVariantPickerModal, showThemePopover, showNotifications, showAvatarMenu, showSyncCenter, isLocked, contextMenu.visible, focusScanInput]);
