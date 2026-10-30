      // Admin, Staff & Roles State
      const [selectedStaff, setSelectedStaff] = useState(MOCK_USERS[0]);
      const [staffRoleFilter, setStaffRoleFilter] = useState('all');
      const [showAddStaffModal, setShowAddStaffModal] = useState(false);
      const [newStaffName, setNewStaffName] = useState('');
      const [newStaffRole, setNewStaffRole] = useState('Cashier');
      const [newStaffPin, setNewStaffPin] = useState('');
      const [newStaffBranch, setNewStaffBranch] = useState('Different Fashion - Main [DF1]');
      const [showResetPinModal, setShowResetPinModal] = useState(false);
      const [resetPinTarget, setResetPinTarget] = useState(null);
      const [newResetPinValue, setNewResetPinValue] = useState('');

      // Settings State
      const [settingsTab, setSettingsTab] = useState('general'); // general | vat | receipts | devices | gateways
      const [shopSettings, setShopSettings] = useState({
        shopName: 'DIFFERENT FASHION',
        branchName: 'Different Fashion - Main',
        branchCode: 'DF1',
        tradeLicense: 'TRAD/DNCC/092841/2024',
        bin: '002847192-0101',
        vatRate: 15,
        vatInclusive: false,
        address: 'Holding 42, Level 2, Sector 3, Uttara, Dhaka-1230',
        phone: '01711-223344',
        email: 'info@differentfashion.bd',
        currency: 'BDT',
        thermalPaper: '80mm',
        headerText: 'DIFFERENT FASHION\nPremium Menswear & Womenswear\nBranch: Uttara Sector 3, Dhaka',
        footerText: 'Thank you for shopping with us!\nExchange within 7 days with original receipt & intact barcode tags.\nNo cash refunds for discounted items.\nCustomer Care: 01711-223344',
        printerIp: '192.168.1.180',
        printerPort: '9100',
        drawerCommand: '27,112,0,50,250',
        bkashMerchant: '01700998877',
        nagadMerchant: '01800112233'
      });

      // Backup & Restore State
      const [backupStatus, setBackupStatus] = useState('idle'); // idle | backing_up | success | error
      const [backupProgress, setBackupProgress] = useState(0);
      const [backupHistory, setBackupHistory] = useState([
        { id: 'b1', filename: 'different_fashion_auto_2026-10-29_1400.sqlite.gz', size: '12.4 MB', time: 'Today, 2:00 PM', type: 'Cloud Sync (GCS)', status: 'Success' },
        { id: 'b2', filename: 'different_fashion_manual_2026-10-28_2130.sqlite.gz', size: '12.1 MB', time: 'Yesterday, 9:30 PM', type: 'Local USB Drive', status: 'Success' },
        { id: 'b3', filename: 'different_fashion_auto_2026-10-28_1400.sqlite.gz', size: '12.0 MB', time: 'Yesterday, 2:00 PM', type: 'Cloud Sync (GCS)', status: 'Success' },
        { id: 'b4', filename: 'different_fashion_auto_2026-10-27_1400.sqlite.gz', size: '11.8 MB', time: '27 Oct 2026, 2:00 PM', type: 'Cloud Sync (GCS)', status: 'Success' }
      ]);

      const handleRunBackup = () => {
        setBackupStatus('backing_up');
        setBackupProgress(10);
        const intv = setInterval(() => {
          setBackupProgress(p => {
            if (p >= 90) {
              clearInterval(intv);
              setTimeout(() => {
                setBackupStatus('success');
                setBackupProgress(100);
                setBackupHistory(prev => [
                  { id: 'b_' + Date.now(), filename: `different_fashion_manual_${new Date().toISOString().slice(0, 10)}.sqlite.gz`, size: '12.5 MB', time: 'Just now', type: 'Manual Trigger', status: 'Success' },
                  ...prev
                ]);
                addToast("Database snapshot (SQLite) archived successfully!", "success");
              }, 400);
              return 90;
            }
            return p + 25;
          });
        }, 200);
      };

      // Audit Log State
      const [auditActionFilter, setAuditActionFilter] = useState('all');
      const [auditSearchQuery, setAuditSearchQuery] = useState('');
      const filteredAuditLogs = useMemo(() => {
        return MOCK_AUDIT_LOGS.filter(log => {
          const q = auditSearchQuery.toLowerCase();
          const matchesQuery = !q || log.action.toLowerCase().includes(q) || log.user.toLowerCase().includes(q) || log.details.toLowerCase().includes(q);
          const matchesAction = auditActionFilter === 'all' || log.action === auditActionFilter;
          return matchesQuery && matchesAction;
        });
      }, [auditSearchQuery, auditActionFilter]);

      // Super Admin Platform State
      const [superAdminTab, setSuperAdminTab] = useState('tenants'); // tenants | quotas | logs | apis
