      // Customer & Membership State
      const [selectedCustomer, setSelectedCustomer] = useState(MOCK_CUSTOMERS[0]);
      const [customerTab, setCustomerTab] = useState('overview'); // overview, sales, items, payments, statement, returns, cards, messages, activity
      const [customerSearch, setCustomerSearch] = useState('');
      const [customerGroupFilter, setCustomerGroupFilter] = useState('all');
      const [showImportCustomersModal, setShowImportCustomersModal] = useState(false);
      const [showMergeDuplicatesModal, setShowMergeDuplicatesModal] = useState(false);

      // Membership card layout: 'cr80' | 'a4_10'
      const [cardLayout, setCardLayout] = useState('cr80');

      // Messaging State
      const [msgChannel, setMsgChannel] = useState('whatsapp'); // whatsapp | sms
      const [msgRecipientFilter, setMsgRecipientFilter] = useState('all_due'); // all_due | single | vip
      const [msgTemplate, setMsgTemplate] = useState('due_soft');
      const [msgCustomText, setMsgCustomText] = useState('Dear {name}, your outstanding balance at Different Fashion is {due}. Please pay at your earliest convenience. Thank you! [DF1-Kalai]');

      // Message templates library
      const MESSAGE_TEMPLATES = {
        due_soft: 'Dear {name}, gentle reminder: your outstanding due at Different Fashion is {due}. Thank you for shopping with us! Phone: 01700-112233',
        due_firm: 'Urgent: Dear {name}, your due balance of {due} for invoice {last_invoice} is overdue. Please settle by Saturday. Different Fashion',
        payment_thanks: 'Dear {name}, thank you! We have received your payment of {due}. Your current balance is ৳ 0.00. Different Fashion',
        receipt_link: 'Dear {name}, thank you for your purchase today! View your e-receipt here: https://df.pos.bd/r/{last_invoice} - Different Fashion',
        birthday_wish: 'Happy Birthday, {name}! 🎂 Enjoy an exclusive 10% discount on your next visit to Different Fashion this week! Promo: EID10',
        pin_set: 'Dear {name}, please set your 4-digit secret PIN at our cash counter on your next visit to secure your membership points and credit purchases.'
      };

      // Filtered Customers
      const filteredCustomers = useMemo(() => {
        return MOCK_CUSTOMERS.filter(c => {
          const q = customerSearch.toLowerCase();
          const matchesQuery = !q || c.customer_no.toLowerCase().includes(q) || c.name_en.toLowerCase().includes(q) || c.name_bn.includes(q) || c.phone.includes(q);
          const matchesGroup = customerGroupFilter === 'all' || c.group.toLowerCase() === customerGroupFilter.toLowerCase();
          return matchesQuery && matchesGroup;
        });
      }, [customerSearch, customerGroupFilter]);
