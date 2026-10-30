      // Catalog & Stock State
      const [selectedProduct, setSelectedProduct] = useState(INITIAL_PRODUCTS[0]);
      const [productSearch, setProductSearch] = useState('');
      const [productCatFilter, setProductCatFilter] = useState('all');

      // Product Form tab: 'general' | 'pricing' | 'variants' | 'inventory' | 'status'
      const [prodFormTab, setProdFormTab] = useState('general');

      // Stock Check modal
      const [showStockCheckModal, setShowStockCheckModal] = useState(false);
      const [stockCheckQuery, setStockCheckQuery] = useState('');

      // Stock Adjustment modal
      const [showStockAdjustmentModal, setShowStockAdjustmentModal] = useState(false);
      const [adjustReason, setAdjustReason] = useState('Damaged');
      const [adjustQty, setAdjustQty] = useState('');
      const [adjustManagerPin, setAdjustManagerPin] = useState('');

      // Branch Transfer modal
      const [showStockTransferModal, setShowStockTransferModal] = useState(false);
      const [transferFrom, setTransferFrom] = useState('Different Fashion - Main [DF1]');
      const [transferTo, setTransferTo] = useState('Different Fashion - 2 [DF2]');
      const [transferQty, setTransferQty] = useState('10');

      // Stocktake modal
      const [showStocktakeModal, setShowStocktakeModal] = useState(false);
      const [stocktakeType, setStocktakeType] = useState('Category: Panjabi');
      const [blindCount, setBlindCount] = useState(false);

      // Barcode Label Designer modal
      const [showBarcodeLabelModal, setShowBarcodeLabelModal] = useState(false);
      const [labelPreset, setLabelPreset] = useState('50x30mm Single');
      const [labelQty, setLabelQty] = useState('24');

      // Purchases & Vendors State
      const [selectedVendor, setSelectedVendor] = useState(MOCK_VENDORS[0]);
      const [selectedPurchase, setSelectedPurchase] = useState(MOCK_PURCHASES[0]);
      const [showNewPurchaseModal, setShowNewPurchaseModal] = useState(false);
      const [newPurVendor, setNewPurVendor] = useState('Aarong Craft Suppliers Ltd');
      const [newPurBillNo, setNewPurBillNo] = useState('INV-V1-9988');
      const [newPurTotal, setNewPurTotal] = useState('34500');
      const [newPurTransport, setNewPurTransport] = useState('800');
      const [newPurPaid, setNewPurPaid] = useState('20000');

      const [showPayVendorModal, setShowPayVendorModal] = useState(false);
      const [payVendorAmount, setPayVendorAmount] = useState('25000');
      const [payVendorAccount, setPayVendorAccount] = useState('City Bank Current A/C #1209');

      // Filtered Products
      const filteredCatalog = useMemo(() => {
        return INITIAL_PRODUCTS.filter(p => {
          const q = productSearch.toLowerCase();
          const matchesQuery = !q || p.name_en.toLowerCase().includes(q) || p.name_bn.includes(q) || p.sku.toLowerCase().includes(q) || p.barcode.includes(q);
          const matchesCat = productCatFilter === 'all' || p.category === productCatFilter;
          return matchesQuery && matchesCat;
        });
      }, [productSearch, productCatFilter]);
