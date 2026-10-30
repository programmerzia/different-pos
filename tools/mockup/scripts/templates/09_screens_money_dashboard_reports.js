      // Money & Shifts State
      const [showShiftCloseModal, setShowShiftCloseModal] = useState(false);
      const [showAccountTransferModal, setShowAccountTransferModal] = useState(false);
      const [transferFromAcc, setTransferFromAcc] = useState('Cash Drawer R1');
      const [transferToAcc, setTransferToAcc] = useState('Main Safe Box');
      const [transferAccAmount, setTransferAccAmount] = useState('30000');

      const [showNewExpenseModal, setShowNewExpenseModal] = useState(false);
      const [newExpHead, setNewExpHead] = useState('Staff Tiffin & Refreshment');
      const [newExpAmount, setNewExpAmount] = useState('450');
      const [newExpAccount, setNewExpAccount] = useState('Cash Drawer R1');
      const [newExpNote, setNewExpNote] = useState('');

      // Denomination counts state for Z-Report shift close
      const [denomCounts, setDenomCounts] = useState({
        1000: 50, 500: 40, 200: 45, 100: 75, 50: 30, 20: 25, 10: 20, 5: 10, 2: 0, 1: 0
      });

      // Reports Hub State
      const [selectedReportId, setSelectedReportId] = useState('daily_sales');
      const [reportDateRange, setReportDateRange] = useState('This Month');
      const [reportBranchFilter, setReportBranchFilter] = useState('all');
      const [showScheduleReportModal, setShowScheduleReportModal] = useState(false);

      const REPORTS_LIST = [
        { id: 'daily_sales', name: 'Daily Sales & Register Summary', cat: 'Sales', desc: 'Breakdown of sales, taxes, discounts and tenders by day' },
        { id: 'product_sales', name: 'Product Sales Velocity & Margin', cat: 'Sales', desc: 'Units sold, COGS, gross margin % and revenue per SKU' },
        { id: 'due_aging', name: 'Customer Due Aging Analysis', cat: 'Customers', desc: 'Outstanding balances bucketed into 0-30, 31-60, 61-90 and 90+ days' },
        { id: 'top_customers', name: 'Top Customer Lifetime Value (LTV)', cat: 'Customers', desc: 'Highest revenue generating members and purchase frequency' },
        { id: 'stock_valuation', name: 'FIFO Stock Valuation Ledger', cat: 'Inventory', desc: 'Stock quantities valued at current batch cost and retail price' },
        { id: 'low_stock', name: 'Low Stock & Purchase Reorder Alert', cat: 'Inventory', desc: 'Items below threshold calculated against 30-day run rate' },
        { id: 'dead_stock', name: 'Dead Stock Report (>90 Days Zero Sale)', cat: 'Inventory', desc: 'Unmoved capital with markdown recommendations' },
        { id: 'branch_transfer', name: 'Inter-Branch Transfer Discrepancy', cat: 'Inventory', desc: 'Dispatched vs received quantities with in-transit variance' },
        { id: 'vendor_payables', name: 'Vendor Payables & Bill Statement', cat: 'Purchases', desc: 'Unpaid vendor invoices, terms and upcoming payment dues' },
        { id: 'cash_flow', name: 'Day Book & Net Cash Flow Statement', cat: 'Money', desc: 'Cash in, cash out, expense heads, banking and closing drawer' },
        { id: 'pnl_statement', name: 'Profit & Loss (P&L) Statement', cat: 'Money', desc: 'Net revenue minus COGS minus operational expenses' },
        { id: 'vat_mushak', name: 'VAT Output Summary (Mushak 6.3)', cat: 'Tax & Compliance', desc: 'Auditable VAT breakdown for National Board of Revenue (NBR)' },
        { id: 'cashier_audit', name: 'Cashier Performance & Void Audit', cat: 'Audit', desc: 'Sales, voids, discounts and drawer variance per operator' },
        { id: 'messaging_roi', name: 'WhatsApp & SMS Due Campaign Report', cat: 'Messaging', desc: 'Delivered messages vs collected dues correlation' },
      ];
