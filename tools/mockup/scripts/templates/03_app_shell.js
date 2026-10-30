    function App() {
      // Preferences & Settings
      const [lang, setLang] = useState('en'); // 'en' | 'bn'
      const [theme, setTheme] = useState('light'); // 'light' | 'dark'
      const [scheme, setScheme] = useState('teal'); // teal, indigo, violet, rose, ocean, graphite
      const [density, setDensity] = useState('comfortable'); // comfortable, compact
      const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
      
      // Screen Navigation: 'pos', 'sales', 'sale_detail', 'returns', 'due_collection', 'customers', 'customer_form', 'customer_profile', 'membership_cards', 'messaging', 'products', 'product_detail', 'product_form', 'stock', 'purchases', 'vendors', 'vendor_profile', 'money', 'dashboard', 'reports', 'users', 'settings', 'backup', 'audit_log', 'super_admin', 'states_demo', 'auth_login'
      const [currentScreen, setCurrentScreen] = useState('pos');
      const [branch, setBranch] = useState('Different Fashion - Main [DF1]');
      const [currentUser, setCurrentUser] = useState({ name: 'Sumi', role: 'Cashier', pin: '1234' });
      const [viewAsRole, setViewAsRole] = useState('Cashier'); // Super Admin, Owner, Manager, Cashier, Accountant, Stock Keeper
      const [isLocked, setIsLocked] = useState(false);
      const [lockPinInput, setLockPinInput] = useState('');

      // Connectivity & Sync: 0: Online, 1: Offline, 2: 3 unsynced, 3: Sync error
      const [syncStateIndex, setSyncStateIndex] = useState(0);
      const [isSyncing, setIsSyncing] = useState(false);
      const [showSyncCenter, setShowSyncCenter] = useState(false);

      // Top Bar Popovers
      const [showNotifications, setShowNotifications] = useState(false);
      const [notifTab, setNotifTab] = useState('all');
      const [showThemePopover, setShowThemePopover] = useState(false);
      const [showAvatarMenu, setShowAvatarMenu] = useState(false);
      const [showShortcutsModal, setShowShortcutsModal] = useState(false);
      const [showCommandPalette, setShowCommandPalette] = useState(false);
      const [cmdQuery, setCmdQuery] = useState('');

      // Notifications List
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
        { label: 'Online', icon: 'wifi', colorClass: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/30' },
        { label: 'Offline', icon: 'wifiOff', colorClass: 'text-amber-500 bg-amber-500/10 border-amber-500/30' },
        { label: '3 unsynced', icon: 'refresh', colorClass: 'text-amber-600 bg-amber-500/15 border-amber-500/40' },
        { label: 'Sync error', icon: 'alertTriangle', colorClass: 'text-rose-500 bg-rose-500/10 border-rose-500/30' }
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
