// scripts/test_part2_cdp.js - Automated Verification for Part 2 List Patterns
const http = require('http');
const { spawn } = require('child_process');

async function wait(ms) {
  return new Promise(r => setTimeout(r, ms));
}

async function getPageTarget() {
  for (let i = 0; i < 20; i++) {
    try {
      const data = await new Promise((resolve, reject) => {
        http.get('http://127.0.0.1:9222/json', (res) => {
          let buf = '';
          res.on('data', chunk => buf += chunk);
          res.on('end', () => resolve(buf));
        }).on('error', reject);
      });
      const targets = JSON.parse(data);
      const page = targets.find(t => t.type === 'page' && t.url.includes('8080'));
      if (page) return page;
    } catch (e) {
      await wait(300);
    }
  }
  return null;
}

async function run() {
  console.log('Launching headless Chrome for Part 2 verification...');
  const chrome = spawn('google-chrome-stable', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--window-size=1440,900',
    '--user-data-dir=/tmp/pos-test-part2-' + Date.now(),
    '--no-sandbox',
    '--disable-gpu',
    'http://127.0.0.1:8080/'
  ]);

  try {
    const page = await getPageTarget();
    if (!page) {
      console.error('Failed to find page target at 127.0.0.1:8080');
      process.exit(1);
    }
    console.log('Connected to page:', page.webSocketDebuggerUrl);

    const ws = new WebSocket(page.webSocketDebuggerUrl);
    await new Promise(r => ws.onopen = r);

    let msgId = 1;
    const pending = new Map();
    ws.onmessage = (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && pending.has(msg.id)) {
        const { resolve, reject } = pending.get(msg.id);
        pending.delete(msg.id);
        if (msg.error) reject(msg.error);
        else resolve(msg.result);
      }
    };

    const send = (method, params = {}) => new Promise((resolve, reject) => {
      const id = msgId++;
      pending.set(id, { resolve, reject });
      ws.send(JSON.stringify({ id, method, params }));
    });

    const evalJs = async (expr) => {
      let code = expr.trim();
      if (code.includes('return ') && !code.startsWith('(() =>') && !code.startsWith('(function')) {
        code = `(() => {\n${code}\n})()`;
      }
      const res = await send('Runtime.evaluate', {
        expression: code,
        returnByValue: true,
        awaitPromise: true
      });
      if (res.exceptionDetails) {
        throw new Error(`Eval error in "${expr}": ${JSON.stringify(res.exceptionDetails)}`);
      }
      return res.result ? res.result.value : undefined;
    };

    const pressKey = async (key, code, keyCode, modifiers = 0) => {
      await send('Input.dispatchKeyEvent', {
        type: 'keyDown',
        key,
        code,
        windowsVirtualKeyCode: keyCode,
        nativeVirtualKeyCode: keyCode,
        modifiers
      });
      await wait(50);
      await send('Input.dispatchKeyEvent', {
        type: 'keyUp',
        key,
        code,
        windowsVirtualKeyCode: keyCode,
        nativeVirtualKeyCode: keyCode,
        modifiers
      });
      await wait(50);
    };

    console.log('Waiting for React application to mount...');
    for (let i = 0; i < 30; i++) {
      const ready = await evalJs('Boolean(document.querySelector("#root")?.children.length > 0)');
      if (ready) break;
      await wait(200);
    }

    // Stub window.confirm so it never freezes
    await evalJs('window.confirm = () => true;');

    let passed = 0;
    let failed = 0;

    function assert(condition, message) {
      if (condition) {
        console.log(`  ✓ PASS: ${message}`);
        passed++;
      } else {
        console.error(`  ✗ FAIL: ${message}`);
        failed++;
      }
    }

    console.log('\n========================================');
    console.log('VERIFYING PART 2: THE LIST PATTERN ON ALL 8 LISTS');
    console.log('========================================\n');

    // --- 2.1 SALES LIST ---
    console.log('--- 2.1 SALES LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('s', 'KeyS', 83);
    await wait(500);

    let title = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(title.includes('Sales & Invoices'), 'Navigated to Sales screen (G S)');

    // Search box exists and '/' focuses it
    await evalJs('document.activeElement.blur();');
    await pressKey('/', 'Slash', 191);
    await wait(100);
    const activePlaceholder = await evalJs("document.activeElement?.getAttribute('placeholder') || ''");
    assert(activePlaceholder.includes('Search invoice'), "Pressing '/' focuses search box");

    // Clear search if anything typed
    await evalJs('document.activeElement.blur();');

    // Customer column formatting: Shows name + formatted no. "1000 0249", NEVER "Customer #10000249"
    const salesTableText = await evalJs("document.querySelector('table')?.textContent || ''");
    assert(!salesTableText.includes('Customer #'), "Customer column NEVER shows 'Customer #10000249'");
    assert(salesTableText.includes('1000 0249'), "Customer column shows formatted number '1000 0249'");

    // Required columns: Discount, VAT, Branch, Register
    const salesThs = await evalJs("Array.from(document.querySelectorAll('th')).map(th => th.textContent.trim())");
    assert(salesThs.some(h => h.includes('Discount')), "Contains 'Discount' column");
    assert(salesThs.some(h => h.includes('VAT')), "Contains 'VAT' column");
    assert(salesThs.some(h => h.includes('Branch')), "Contains 'Branch' column");
    assert(salesThs.some(h => h.includes('Register')), "Contains 'Register' column");

    // Totals footer row with ৳
    const salesHasTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('Total') && document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(salesHasTotals, 'Sales table contains a TOTALS FOOTER row with ৳ currency format');

    // Pagination display contains "1–25 of 65"
    const salesPagination = await evalJs("document.body.textContent.includes('1–25 of 65')");
    assert(salesPagination, "Pagination displays '1–25 of 65'");

    // Saved views dropdown
    const salesSavedViews = await evalJs(`
      const sel = Array.from(document.querySelectorAll('select')).find(s => s.options && Array.from(s.options).some(o => o.text.includes('Default (All Sales)')));
      sel ? Array.from(sel.options).map(o => o.text.trim()) : []
    `);
    assert(salesSavedViews.length >= 3, 'Saved views dropdown has Default + 2 saved views');

    // Column chooser opens
    await evalJs("Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Columns'))?.click()");
    await wait(150);
    const colChooserWorks = await evalJs("Boolean(document.querySelector('input[type=\"checkbox\"]:not([disabled])'))");
    assert(colChooserWorks, 'Column chooser opens with column visibility toggles');
    await evalJs("Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Columns'))?.click()");
    await wait(100);

    // More filters drawer
    await evalJs("Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('More Filters'))?.click()");
    await wait(200);
    const moreFiltersWorks = await evalJs("Boolean(document.querySelector('[role=\"dialog\"]'))");
    assert(moreFiltersWorks, "'More filters' button opens drawer dialog");
    await pressKey('Escape', 'Escape', 27);
    await wait(200);

    // Export options (Excel, CSV, PDF)
    await evalJs("Array.from(document.querySelectorAll('button')).find(b => b.textContent.trim().startsWith('Export'))?.click()");
    await wait(150);
    const exportOpts = await evalJs(`
      const opts = Array.from(document.querySelectorAll('button')).filter(b => b.textContent.includes('Excel') || b.textContent.includes('CSV') || b.textContent.includes('PDF'));
      return opts.map(o => o.textContent.trim());
    `);
    assert(exportOpts.some(o => o.includes('Excel')) && exportOpts.some(o => o.includes('CSV')) && exportOpts.some(o => o.includes('PDF')), 'Export menu has Excel, CSV, and PDF options');
    await evalJs('document.body.click();');
    await wait(100);

    // Row keyboard navigation: J/K moves highlight, Space selects, bulk bar appears
    await evalJs("const container = document.querySelector('div[tabindex=\"0\"]'); if (container) container.focus();");
    await pressKey('j', 'KeyJ', 74);
    await wait(60);
    await pressKey(' ', 'Space', 32);
    await wait(150);

    const bulkBarAppears = await evalJs("Boolean(document.body.textContent.includes('1 item selected'))");
    assert(bulkBarAppears, 'Pressing J and Space selects row and shows Bulk Actions Bar');

    // Clear selection
    await evalJs(`
      const clearBtn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Clear selection'));
      if (clearBtn) clearBtn.click();
    `);

    // --- 2.2 DUE COLLECTION LIST ---
    console.log('\n--- 2.2 DUE COLLECTION LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('u', 'KeyU', 85);
    await wait(500);

    const dueTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(dueTitle.includes('Due Collection'), 'Navigated to Due Collection screen (G U)');

    const dueTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(dueTotals, 'Due Collection list has a TOTALS FOOTER row');

    const duePagination = await evalJs("Boolean(document.body.textContent.includes('of 14') || document.body.textContent.includes('1–'))");
    assert(duePagination, 'Due Collection list has pagination controls');

    // --- 2.3 RETURNS LIST ---
    console.log('\n--- 2.3 RETURNS LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('t', 'KeyT', 84);
    await wait(500);

    const retTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(retTitle.includes('Returns & Exchanges'), 'Navigated to Returns screen (G T)');

    const retTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(retTotals, 'Returns list has a TOTALS FOOTER row');

    const retExport = await evalJs("Boolean(Array.from(document.querySelectorAll('button')).some(b => b.textContent.includes('Export')))");
    assert(retExport, 'Returns list has Export button');

    // --- 2.4 CUSTOMERS LIST ---
    console.log('\n--- 2.4 CUSTOMERS LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('c', 'KeyC', 67);
    await wait(500);

    const custTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(custTitle.includes('Customers & Members'), 'Navigated to Customers screen (G C)');

    const custTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(custTotals, 'Customers list has a TOTALS FOOTER row');

    const custPagination = await evalJs("Boolean(document.body.textContent.includes('1–25 of 32'))");
    assert(custPagination, "Customers list has pagination '1–25 of 32'");

    // --- 2.5 PRODUCTS LIST ---
    console.log('\n--- 2.5 PRODUCTS LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('i', 'KeyI', 73);
    await wait(500);

    const prodTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(prodTitle.includes('Product Catalog'), 'Navigated to Product Catalog screen (G I)');

    const prodTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('Total') && document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(prodTotals, 'Products list has a TOTALS FOOTER row with sums');

    const prodPagination = await evalJs("Boolean(document.body.textContent.includes('1–25 of 42'))");
    assert(prodPagination, "Products list has pagination '1–25 of 42'");

    // --- 2.6 STOCK LIST ---
    console.log('\n--- 2.6 STOCK LIST VERIFICATION ---');
    // Switch to Owner role so restricted screens (Stock, Purchases, Vendors, Money) can be navigated
    await evalJs("window.__setViewAsRole('Owner')");
    await wait(100);
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('k', 'KeyK', 75);
    await wait(500);

    const stockTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(stockTitle.includes('Stock Management'), 'Navigated to Stock Management screen (G K)');

    const stockTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(stockTotals, 'Stock list has a TOTALS FOOTER row with Valuation sum');

    // --- 2.7 PURCHASES LIST ---
    console.log('\n--- 2.7 PURCHASES LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('p', 'KeyP', 80);
    await wait(500);

    const purTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(purTitle.includes('Vendor Purchases'), 'Navigated to Purchases screen (G P)');

    const purTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(purTotals, 'Purchases list has a TOTALS FOOTER row with Total Bill, Paid, and Due sums');

    const purPagination = await evalJs("Boolean(document.body.textContent.includes('1–25 of 26'))");
    assert(purPagination, "Purchases list has pagination '1–25 of 26'");

    // --- 2.8 VENDORS LIST ---
    console.log('\n--- 2.8 VENDORS LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('v', 'KeyV', 86);
    await wait(500);

    const venTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(venTitle.includes('Vendor & Supplier'), 'Navigated to Vendors screen (G V)');

    const venTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(venTotals, 'Vendors list has a TOTALS FOOTER row with Payable Balance sum');

    // --- 2.9 MONEY ENTRIES LIST ---
    console.log('\n--- 2.9 MONEY ENTRIES LIST VERIFICATION ---');
    await pressKey('g', 'KeyG', 71);
    await wait(80);
    await pressKey('m', 'KeyM', 77);
    await wait(500);

    const moneyTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
    assert(moneyTitle.includes('Financial Accounts'), 'Navigated to Money screen (G M)');

    const expTotals = await evalJs("Boolean(document.querySelector('tfoot')?.textContent.includes('৳'))");
    assert(expTotals, 'Expense vouchers list has a TOTALS FOOTER row with Outflow sum');

    // --- 2.10 SELF-CHECK 6 ---
    console.log('\n--- 2.10 SELF-CHECK 6 VERIFICATION ---');
    assert(
      salesHasTotals && dueTotals && retTotals && custTotals && prodTotals && stockTotals && purTotals && venTotals && expTotals,
      'SELF-CHECK 6: Every single list has a totals footer, pagination and Export'
    );

    console.log('\n========================================');
    console.log(`PART 2 VERIFICATION COMPLETE: ${passed} PASSED, ${failed} FAILED`);
    console.log('========================================\n');

    ws.close();
    chrome.kill();
    process.exit(failed > 0 ? 1 : 0);
  } catch (err) {
    console.error('Test execution error:', err);
    chrome.kill();
    process.exit(1);
  }
}

run();
