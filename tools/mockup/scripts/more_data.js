// Supplementary mock data: 65 Sales, 26 Purchases, Accounts, Expenses, Fixed Assets, Users, Audit Logs, Reports

const SALES = [];
const paymentMethods = ['Cash', 'bKash', 'Card', 'Nagad', 'Rocket', 'Due'];
const cashiers = ['Sumi', 'Kabir', 'Rana'];

for (let i = 1; i <= 65; i++) {
  const invNum = 26000413 - 65 + i;
  const isToday = i >= 55;
  const isYesterday = i >= 45 && i < 55;
  const dayOffset = 65 - i;
  
  let dateStr = '29 Oct 2026';
  let timeStr = `${Math.floor(9 + (i % 11))}:${String((i * 7) % 60).padStart(2, '0')} ${i % 2 === 0 ? 'AM' : 'PM'}`;
  if (isYesterday) {
    dateStr = '28 Oct 2026';
  } else if (!isToday) {
    const d = Math.max(1, 29 - Math.floor(dayOffset / 2));
    dateStr = `${String(d).padStart(2, '0')} Oct 2026`;
  }

  // Pick customer
  const custIndex = (i * 3) % 32;
  const isWalkIn = (i % 5 === 0);
  const customerName = isWalkIn ? 'Walk-in Customer' : `Customer #${10000017 + (custIndex * 8)}`;
  const custNo = isWalkIn ? 'Walk-in' : `1000 ${String(17 + (custIndex * 8)).padStart(4, '0')}`;

  const itemCount = 1 + (i % 4);
  const total = 950 + (i * 240) % 8500;
  const isVoid = (i === 12 || i === 38);
  const isDue = (i % 8 === 0 && !isVoid);
  const isPartial = (i % 6 === 0 && !isVoid && !isDue);
  
  let status = 'Paid';
  let paid = total;
  let due = 0;

  if (isVoid) {
    status = 'Void';
    paid = 0;
    due = 0;
  } else if (isDue) {
    status = 'Due';
    paid = 0;
    due = total;
  } else if (isPartial) {
    status = 'Partial';
    paid = Math.floor(total * 0.4);
    due = total - paid;
  }

  let syncStatus = 'Synced';
  if (i === 64) syncStatus = 'Flagged: Price mismatch';
  if (i === 52) syncStatus = 'Flagged: Oversold';
  if (i === 65) syncStatus = 'Queued';

  SALES.push({
    id: `s${i}`,
    invoice: `DF1-R1-${invNum}`,
    date: dateStr,
    time: timeStr,
    customer: customerName,
    customer_no: custNo,
    items_count: itemCount,
    total,
    paid,
    due,
    status,
    cashier: cashiers[i % 3],
    payment_method: paymentMethods[i % 6],
    sync_status: syncStatus,
    courier: i % 4 === 0 ? 'Steadfast Courier' : (i % 7 === 0 ? 'Pathao Parcel' : null),
    tracking_no: i % 4 === 0 ? `STF-26${9000 + i}` : (i % 7 === 0 ? 'PTH-88' + i : null)
  });
}
SALES.reverse(); // Newest first

// 26 Purchases from Vendors
const PURCHASES = [];
for (let i = 1; i <= 26; i++) {
  const pNum = `ST-2600${String(i).padStart(2, '0')}`;
  const vendorIdx = (i - 1) % 10;
  const vNames = [
    'Aarong Craft Suppliers Ltd', 'Noman Fabrics & Weaving', 'Beximco Textiles Distributor',
    'Square Fashion Knitwear', 'Apex Footwear Distribution', 'Tangail Weavers Cooperative',
    'Joypurhat Cotton Mills Agency', 'Bogura Denim Wash & Stitch', 'Islampur Cloth Merchants', 'Pakiza Dyeing & Printing'
  ];
  const d = Math.max(1, 29 - Math.floor((26 - i) * 1.1));
  const dateStr = `${String(d).padStart(2, '0')} Oct 2026`;
  const total = 18000 + (i * 3500) % 65000;
  const transport = 500 + (i * 150) % 2000;
  const paid = i % 3 === 0 ? Math.floor(total * 0.5) : (i % 5 === 0 ? 0 : total);
  const status = paid === total ? 'Received & Paid' : (paid > 0 ? 'Partial Paid' : 'Due Balance');

  PURCHASES.push({
    id: `pur-${i}`,
    purchase_no: pNum,
    bill_no: `INV-V${vendorIdx + 1}-${1000 + i}`,
    date: dateStr,
    branch: i % 3 === 0 ? 'Branch 2' : 'Main Branch',
    vendor: vNames[vendorIdx],
    total,
    transport,
    paid,
    due: total - paid,
    status,
    batches_count: 2 + (i % 3)
  });
}
PURCHASES.reverse();

// 5 Money Accounts
const ACCOUNTS = [
  { id: 'acc-1', name: 'Cash Drawer R1', type: 'Cash Drawer', balance: 89250, branch: 'Main', last_tx: '10:42 AM' },
  { id: 'acc-2', name: 'Main Safe Box', type: 'Cash Vault', balance: 345000, branch: 'Main', last_tx: 'Yesterday' },
  { id: 'acc-3', name: 'City Bank Current A/C #1209', type: 'Bank', balance: 812000, branch: 'Corporate', last_tx: '27 Oct' },
  { id: 'acc-4', name: 'bKash Merchant #01700-112233', type: 'MFS', balance: 64500, branch: 'Main', last_tx: '10:38 AM' },
  { id: 'acc-5', name: 'Nagad Merchant #01800-445566', type: 'MFS', balance: 42000, branch: 'Main', last_tx: '09:45 AM' },
];

// 14 Expenses Entries
const EXPENSES = [
  { id: 'exp-1', date: '29 Oct 2026', head: 'Staff Tiffin & Refreshment', amount: 450, account: 'Cash Drawer R1', note: 'Morning tea and snacks for 4 staff', by: 'Sumi' },
  { id: 'exp-2', date: '29 Oct 2026', head: 'Cleaning & Sanitation Supplies', amount: 320, account: 'Cash Drawer R1', note: 'Floor cleaner and tissue rolls', by: 'Sumi' },
  { id: 'exp-3', date: '28 Oct 2026', head: 'Courier Delivery Advance', amount: 1200, account: 'Cash Drawer R1', note: 'Steadfast delivery dispatch 6 parcels', by: 'Kabir' },
  { id: 'exp-4', date: '28 Oct 2026', head: 'Generator Diesel Fuel', amount: 2500, account: 'Main Safe Box', note: '20L diesel for backup generator', by: 'Rana' },
  { id: 'exp-5', date: '27 Oct 2026', head: 'Packaging Bags Printing', amount: 8500, account: 'City Bank Current', note: '2,000 branded shopping poly/paper bags', by: 'Owner' },
  { id: 'exp-6', date: '25 Oct 2026', head: 'Electricity Bill (NESCO)', amount: 14200, account: 'City Bank Current', note: 'Main branch commercial electricity', by: 'Owner' },
  { id: 'exp-7', date: '24 Oct 2026', head: 'Internet Fiber Bill', amount: 2000, account: 'bKash Merchant', note: 'Dot Internet 50Mbps duplex line', by: 'Manager' },
  { id: 'exp-8', date: '22 Oct 2026', head: 'Shop Rent Advance', amount: 45000, account: 'City Bank Current', note: 'Branch 2 monthly shop rent', by: 'Owner' },
  { id: 'exp-9', date: '20 Oct 2026', head: 'Staff Salary Partial Draw', amount: 6000, account: 'Main Safe Box', note: 'Mid-month advance to Cashier Sumi', by: 'Owner' },
  { id: 'exp-10', date: '18 Oct 2026', head: 'Thermal Receipt Rolls (50 pcs)', amount: 2400, account: 'Cash Drawer R1', note: '80mm BPA-free thermal rolls', by: 'Kabir' },
  { id: 'exp-11', date: '15 Oct 2026', head: 'Hanger & Display Rack Fittings', amount: 3800, account: 'Main Safe Box', note: 'Wooden suit hangers for panjabi section', by: 'Rana' },
  { id: 'exp-12', date: '12 Oct 2026', head: 'Municipal Trade License Fee', amount: 7500, account: 'City Bank Current', note: 'Yearly renewal pourashava', by: 'Owner' },
  { id: 'exp-13', date: '10 Oct 2026', head: 'Facebook Advertisement Campaign', amount: 5000, account: 'City Bank Current', note: 'Puja festival clothing collection boost', by: 'Manager' },
  { id: 'exp-14', date: '05 Oct 2026', head: 'AC Servicing & Gas Refill', amount: 4200, account: 'Main Safe Box', note: '2 Gree 2-ton split AC annual servicing', by: 'Rana' },
];

// 6 Fixed Assets
const FIXED_ASSETS = [
  { id: 'fa-1', name: 'Dell Optiplex 7090 Touch POS Terminal', date: '15 Jan 2025', cost: 65000, branch: 'Main', status: 'Active In-Use', note: 'Core register machine R1' },
  { id: 'fa-2', name: 'Epson TM-T82III Thermal Receipt Printer (80mm)', date: '15 Jan 2025', cost: 16500, branch: 'Main', status: 'Active In-Use', note: 'High-speed auto-cutter' },
  { id: 'fa-3', name: 'Honeywell Voyager 1400g 2D Barcode Scanner', date: '15 Jan 2025', cost: 8500, branch: 'Main', status: 'Active In-Use', note: 'USB stand scanner' },
  { id: 'fa-4', name: 'Hamko 2.5 kVA Pure Sine Wave Inverter + 2 Batteries', date: '10 Feb 2025', cost: 72000, branch: 'Main', status: 'Active In-Use', note: '8-hour backup for POS & lights' },
  { id: 'fa-5', name: 'Gree 2.0 Ton Inverter Split Air Conditioner', date: '20 Mar 2025', cost: 88000, branch: 'Main', status: 'Active In-Use', note: 'Shop floor cooling' },
  { id: 'fa-6', name: 'Heavy Duty 5-Bill 8-Coin Steel Cash Drawer', date: '15 Jan 2025', cost: 5500, branch: 'Main', status: 'Active In-Use', note: 'RJ11 trigger connected to printer' },
];

// 6 Staff Users with Roles & PINs
const USERS = [
  { id: 'u1', name: 'Sumi', username: 'sumi.pos', role: 'Cashier', pin: '1234', branch: 'Main', phone: '01711-001122', status: 'Active', discount_cap: '5%' },
  { id: 'u2', name: 'Rana', username: 'rana.mgr', role: 'Manager', pin: '4321', branch: 'Main', phone: '01819-334455', status: 'Active', discount_cap: '15%' },
  { id: 'u3', name: 'Kamal Hossain', username: 'kamal.owner', role: 'Owner', pin: '9999', branch: 'All Branches', phone: '01700-998877', status: 'Active', discount_cap: '100%' },
  { id: 'u4', name: 'Kabir', username: 'kabir.pos', role: 'Cashier', pin: '2233', branch: 'Branch 2', phone: '01912-667788', status: 'Active', discount_cap: '5%' },
  { id: 'u5', name: 'Anisur Rahman', username: 'anis.acc', role: 'Accountant', pin: '5566', branch: 'Main', phone: '01611-889900', status: 'Active', discount_cap: '0%' },
  { id: 'u6', name: 'Zahirul Islam', username: 'zahir.stock', role: 'Stock Keeper', pin: '7788', branch: 'Main', phone: '01722-113344', status: 'Active', discount_cap: '0%' },
];

// 20 Audit Log Entries
const AUDIT_LOGS = [
  { id: 'aud-1', time: '10:41 AM', user: 'Sumi (Cashier)', action: 'Sale DF1-R1-26000413 completed', module: 'POS', diff: 'Tender: Cash ৳ 4,690.00' },
  { id: 'aud-2', time: '10:35 AM', user: 'Rana (Manager)', action: 'Price override approved', module: 'POS', diff: 'PAN-COT-M: ৳1450 → ৳1400 (−৳50)' },
  { id: 'aud-3', time: '10:14 AM', user: 'Sumi (Cashier)', action: 'Sale DF1-R1-26000412 completed', module: 'POS', diff: 'Synced with price mismatch flag' },
  { id: 'aud-4', time: '09:48 AM', user: 'Rana (Manager)', action: 'Customer PIN reset', module: 'Customers', diff: 'Customer #1000 0033 unlocked' },
  { id: 'aud-5', time: '09:02 AM', user: 'Sumi (Cashier)', action: 'Register Shift Opened', module: 'Shifts', diff: 'Opening Float: ৳ 5,000.00' },
  { id: 'aud-6', time: '08:55 AM', user: 'Sumi (Cashier)', action: 'Login success', module: 'Auth', diff: 'IP 192.168.1.104 (POS Terminal 1)' },
  { id: 'aud-7', time: 'Yesterday', user: 'Kamal (Owner)', action: 'Purchase ST-260026 received', module: 'Purchases', diff: 'Stock +120 pcs, 8 batches created' },
  { id: 'aud-8', time: 'Yesterday', user: 'Rana (Manager)', action: 'Sale DF1-R1-26000408 voided', module: 'Sales', diff: 'Reason: Customer bought wrong size' },
  { id: 'aud-9', time: 'Yesterday', user: 'Zahir (Stock)', action: 'Branch Transfer DF1 → DF2', module: 'Stock', diff: '15 Panjabis dispatched (In-Transit)' },
  { id: 'aud-10', time: 'Yesterday', user: 'Anis (Accountant)', action: 'Expense entry #14 added', module: 'Money', diff: '৳ 4,200 for AC Servicing' },
];

module.exports = { SALES, PURCHASES, ACCOUNTS, EXPENSES, FIXED_ASSETS, USERS, AUDIT_LOGS };
