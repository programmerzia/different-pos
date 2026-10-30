      // Active selection pointers
      const [selectedSale, setSelectedSale] = useState(MOCK_SALES[0]);
      const [salesSearchQuery, setSalesSearchQuery] = useState('');
      const [salesStatusFilter, setSalesStatusFilter] = useState('all');
      const [salesDateFilter, setSalesDateFilter] = useState('This Month');

      // Due Collection state
      const [showDueCollectModal, setShowDueCollectModal] = useState(false);
      const [dueCollectCustomer, setDueCollectCustomer] = useState(null);
      const [dueCollectAmount, setDueCollectAmount] = useState('');
      const [dueCollectMethod, setDueCollectMethod] = useState('Cash');

      const [showReminderModal, setShowReminderModal] = useState(false);
      const [reminderCustomer, setReminderCustomer] = useState(null);
      const [reminderChannel, setReminderChannel] = useState('whatsapp');

      // Returns wizard state
      const [showNewReturnModal, setShowNewReturnModal] = useState(false);
      const [returnWizardStep, setReturnWizardStep] = useState(1);
      const [returnSaleQuery, setReturnSaleQuery] = useState('');
      const [returnReason, setReturnReason] = useState('Defective / Wrong Size');
      const [returnRefundMethod, setReturnRefundMethod] = useState('Cash Refund');

      // Filtered Sales
      const filteredSales = useMemo(() => {
        return MOCK_SALES.filter(s => {
          const matchesQuery = !salesSearchQuery || s.invoice.toLowerCase().includes(salesSearchQuery.toLowerCase()) || s.customer.toLowerCase().includes(salesSearchQuery.toLowerCase());
          const matchesStatus = salesStatusFilter === 'all' || s.status.toLowerCase() === salesStatusFilter.toLowerCase();
          return matchesQuery && matchesStatus;
        });
      }, [salesSearchQuery, salesStatusFilter]);
