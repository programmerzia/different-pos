      // --- POS Register State ---
      const [posCategory, setPosCategory] = useState('all');
      const [posSearchQuery, setPosSearchQuery] = useState('');
      const [posSelectedTileIndex, setPosSelectedTileIndex] = useState(0);
      const posScanInputRef = useRef(null);

      // Cart
      const [cart, setCart] = useState([
        { product: INITIAL_PRODUCTS[0], qty: 2, original_price: 1450, unit_price: 1400, line_discount: 100, salesman: 'Sumi', note: 'Gift wrap requested' },
        { product: INITIAL_PRODUCTS[3], qty: 1, original_price: 1890, unit_price: 1890, line_discount: 0, salesman: 'Sumi', note: '' },
      ]);
      const [selectedCartLineIndex, setSelectedCartLineIndex] = useState(0);
      const [attachedCustomer, setAttachedCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [heldSales, setHeldSales] = useState([]);
      const [orderDiscount, setOrderDiscount] = useState(0);

      // Modals
      const [showCustomerAttachModal, setShowCustomerAttachModal] = useState(false);
      const [customerSearchQuery, setCustomerSearchQuery] = useState('');
      const [showCustomerPinPadModal, setShowCustomerPinPadModal] = useState(false);
      const [pinPadState, setPinPadState] = useState('input'); // input, success, wrong, locked, offline, no_pin
      const [pinDigits, setPinDigits] = useState('');
      const [pinAttemptsLeft, setPinAttemptsLeft] = useState(2);

      const [showPaymentModal, setShowPaymentModal] = useState(false);
      const [tenderTally, setTenderTally] = useState({ cash: 4690, card: 0, bkash: 0, nagad: 0, rocket: 0, bank: 0, due: 0 });
      const [showReceiptModal, setShowReceiptModal] = useState(false);
      const [showA4InvoiceModal, setShowA4InvoiceModal] = useState(false);
      const [lastCompletedSale, setLastCompletedSale] = useState(null);

      const [showDiscountModal, setShowDiscountModal] = useState(false);
      const [discountType, setDiscountType] = useState('order'); // line | order
      const [discountUnit, setDiscountUnit] = useState('bdt'); // bdt | percent
      const [discountValue, setDiscountValue] = useState('');
      const [discountReason, setDiscountReason] = useState('Seasonal Eid promo');
      const [discountManagerPin, setDiscountManagerPin] = useState('');

      const [showCashInOutModal, setShowCashInOutModal] = useState(false);
      const [cashInOutType, setCashInOutType] = useState('in'); // in | out
      const [cashInOutAmount, setCashInOutAmount] = useState('');
      const [cashInOutReason, setCashInOutReason] = useState('');

      const [showPriceCheckModal, setShowPriceCheckModal] = useState(false);
      const [priceCheckQuery, setPriceCheckQuery] = useState('');

      const [showReturnExchangeModal, setShowReturnExchangeModal] = useState(false);
      const [returnInvoiceQuery, setReturnInvoiceQuery] = useState('DF1-R1-26000412');
      const [exchangeStep, setExchangeStep] = useState(1);

      const [showHeldSalesModal, setShowHeldSalesModal] = useState(false);
      const [showQuickNewCustomerModal, setShowQuickNewCustomerModal] = useState(false);
      const [quickCustName, setQuickCustName] = useState('');
      const [quickCustPhone, setQuickCustPhone] = useState('');
      const [quickCustDistrict, setQuickCustDistrict] = useState('Joypurhat');

      const [showVariantPickerModal, setShowVariantPickerModal] = useState(false);
      const [variantPickerProduct, setVariantPickerProduct] = useState(null);

      // Cart calculations
      const subtotal = useMemo(() => cart.reduce((acc, item) => acc + (item.original_price * item.qty), 0), [cart]);
      const lineDiscounts = useMemo(() => cart.reduce((acc, item) => acc + (item.line_discount), 0), [cart]);
      const totalDiscount = lineDiscounts + orderDiscount;
      const vatAmount = Math.round((subtotal - totalDiscount) * 0.15); // Standard 15% VAT
      const grandTotal = Math.max(0, (subtotal - totalDiscount) + vatAmount);

      const focusScanInput = useCallback(() => {
        if (currentScreen === 'pos' && !showPaymentModal && !showCustomerAttachModal && !showCustomerPinPadModal && !showReceiptModal && !showShortcutsModal && !showCommandPalette && !isLocked) {
          setTimeout(() => {
            posScanInputRef.current?.focus();
          }, 50);
        }
      }, [currentScreen, showPaymentModal, showCustomerAttachModal, showCustomerPinPadModal, showReceiptModal, showShortcutsModal, showCommandPalette, isLocked]);

      useEffect(() => {
        focusScanInput();
      }, [focusScanInput]);

      // Filtered POS Products
      const filteredProducts = useMemo(() => {
        return INITIAL_PRODUCTS.filter(p => {
          const matchesCat = posCategory === 'all' || p.category === posCategory;
          const q = posSearchQuery.toLowerCase();
          const matchesQuery = !q || p.name_en.toLowerCase().includes(q) || p.name_bn.includes(q) || p.sku.toLowerCase().includes(q) || p.barcode.includes(q);
          return matchesCat && matchesQuery;
        });
      }, [posCategory, posSearchQuery]);

      // Add to Cart
      const addToCart = (product, variant = null) => {
        if (product.stock_main <= 0) {
          addToast(`${product.name_en} is out of stock!`, 'danger');
          return;
        }
        setCart(prev => {
          const skuToFind = variant ? variant.sku : product.sku;
          const idx = prev.findIndex(item => item.product.sku === skuToFind || (item.variant && item.variant.sku === skuToFind));
          if (idx >= 0) {
            const updated = [...prev];
            updated[idx] = { ...updated[idx], qty: updated[idx].qty + 1 };
            setSelectedCartLineIndex(idx);
            return updated;
          } else {
            const newLine = {
              product,
              variant: variant || (product.variants && product.variants[0]),
              qty: 1,
              original_price: variant ? variant.price : product.price,
              unit_price: variant ? variant.price : product.price,
              line_discount: 0,
              salesman: currentUser.name,
              note: ''
            };
            setSelectedCartLineIndex(prev.length);
            return [...prev, newLine];
          }
        });
        addToast(`Added ${product.name_en} to cart`, 'info');
        focusScanInput();
      };

      // Complete payment flow
      const completePayment = () => {
        const saleId = 'DF1-R1-' + Math.floor(26000000 + Math.random() * 9999);
        const newSale = {
          id: saleId,
          invoice: saleId,
          time: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          customer: attachedCustomer ? (lang === 'bn' ? attachedCustomer.name_bn : attachedCustomer.name_en) : 'Walk-in',
          customer_no: attachedCustomer?.customer_no || 'Walk-in',
          items: [...cart],
          total: grandTotal,
          subtotal: subtotal,
          vat: vatAmount,
          discount: totalDiscount,
          paid: (tenderTally.cash + tenderTally.card + tenderTally.bkash + tenderTally.nagad + tenderTally.rocket + tenderTally.bank),
          change: Math.max(0, (tenderTally.cash + tenderTally.card + tenderTally.bkash + tenderTally.nagad + tenderTally.rocket + tenderTally.bank) - grandTotal),
          due: tenderTally.due,
          tenderTally: { ...tenderTally },
          cashier: currentUser.name,
          date: new Date().toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
        };
        setLastCompletedSale(newSale);
        setShowPaymentModal(false);
        setShowReceiptModal(true);
        setCart([]);
        addToast(`Sale ${saleId} completed!`, 'success');
      };
