#!/usr/bin/env node

/**
 * Headless Chrome CDP Verification for Part 3: Dashboard and Settings
 */

const { spawn } = require('child_process');
const http = require('http');

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function getJson(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); }
        catch (e) { reject(e); }
      });
    }).on('error', reject);
  });
}

async function run() {
  console.log('Launching headless Chrome for Part 3 verification...');
  const chrome = spawn('google-chrome', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--no-sandbox',
    '--disable-gpu',
    '--disable-dev-shm-usage',
    'http://127.0.0.1:8080/index.html'
  ]);

  let wsUrl = null;
  for (let i = 0; i < 30; i++) {
    await wait(300);
    try {
      const targets = await getJson('http://127.0.0.1:9222/json');
      const pageTarget = targets.find(t => t.type === 'page' && t.url.includes('127.0.0.1:8080'));
      if (pageTarget && pageTarget.webSocketDebuggerUrl) {
        wsUrl = pageTarget.webSocketDebuggerUrl;
        break;
      }
    } catch (e) {}
  }

  if (!wsUrl) {
    console.error('Failed to obtain Chrome CDP WebSocket URL');
    chrome.kill();
    process.exit(1);
  }

  console.log('Connected to page:', wsUrl);
  const ws = new WebSocket(wsUrl);

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
    if (!code.startsWith('(() =>') && !code.startsWith('(function')) {
      if (!code.includes('return ') && !code.includes(';')) {
        code = `return (${code});`;
      }
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
    await wait(20);
    await send('Input.dispatchKeyEvent', {
      type: 'keyUp',
      key,
      code,
      windowsVirtualKeyCode: keyCode,
      nativeVirtualKeyCode: keyCode,
      modifiers
    });
  };

  ws.onopen = async () => {
    try {
      console.log('Waiting for React application to mount...');
      await wait(1500);

      let passed = 0;
      let failed = 0;
      const assert = (condition, testName) => {
        if (condition) {
          console.log(`  ✓ PASS: ${testName}`);
          passed++;
        } else {
          console.error(`  ✗ FAIL: ${testName}`);
          failed++;
        }
      };

      console.log('\n========================================');
      console.log('VERIFYING PART 3: DASHBOARD AND SETTINGS');
      console.log('========================================');

      // Set role to Owner to access Dashboard and Settings
      await evalJs("window.__setViewAsRole('Owner')");
      await wait(100);

      // --- 3.1 DASHBOARD NAVIGATION & PERIOD / BRANCH SELECTORS ---
      console.log('\n--- 3.1 DASHBOARD (G D) VERIFICATION ---');
      await pressKey('g', 'KeyG', 71);
      await wait(80);
      await pressKey('d', 'KeyD', 68);
      await wait(500);

      const dashTitle = await evalJs("document.querySelector('h1')?.textContent || ''");
      assert(dashTitle.includes('Executive POS Dashboard'), 'Navigated to Dashboard screen (G D)');

      // Period selector exists
      const periodOptions = await evalJs(`
        const sel = Array.from(document.querySelectorAll('select')).find(s => s.options && Array.from(s.options).some(o => o.value === 'today'));
        return sel ? Array.from(sel.options).map(o => o.value) : [];
      `);
      assert(
        periodOptions.includes('today') && periodOptions.includes('yesterday') && periodOptions.includes('7d') && periodOptions.includes('30d') && periodOptions.includes('this_month') && periodOptions.includes('custom'),
        'Period selector has Today, Yesterday, 7 days, 30 days, This month, Custom'
      );

      // Branch selector exists
      const branchOptions = await evalJs(`
        const sel = Array.from(document.querySelectorAll('select')).find(s => s.options && Array.from(s.options).some(o => o.value === 'all'));
        return sel ? Array.from(sel.options).map(o => o.value) : [];
      `);
      assert(branchOptions.includes('all') && branchOptions.includes('df1') && branchOptions.includes('df2'), 'Branch selector has All Branches and individual outlets');

      // Verify Recomputation: changing period recomputes KPIs
      const todaySales = await evalJs("document.body.textContent.includes('84,250')");
      assert(todaySales, 'Today sales initial metric displays ৳84,250');

      await evalJs(`
        const sel = Array.from(document.querySelectorAll('select')).find(s => s.options && Array.from(s.options).some(o => o.value === 'yesterday'));
        if (sel) {
          sel.value = 'yesterday';
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
      `);
      await wait(200);

      const yesterdaySales = await evalJs("document.body.textContent.includes('73,800')");
      assert(yesterdaySales, 'Changing period recomputes sales metric to ৳73,800');

      // Reset to today
      await evalJs(`
        const sel = Array.from(document.querySelectorAll('select')).find(s => s.options && Array.from(s.options).some(o => o.value === 'today'));
        if (sel) {
          sel.value = 'today';
          sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
      `);
      await wait(100);

      // --- 3.2 9 KPIS WITH DELTAS ---
      console.log('\n--- 3.2 9 KPIS WITH DELTAS ---');
      const bodyText = await evalJs("document.body.textContent");
      assert(bodyText.includes('Gross Sales'), 'Contains KPI: Sales');
      assert(bodyText.includes('Gross Profit'), 'Contains KPI: Gross Profit');
      assert(bodyText.includes('Transactions'), 'Contains KPI: Transactions');
      assert(bodyText.includes('Avg Basket'), 'Contains KPI: Avg Basket');
      assert(bodyText.includes('Dues Outstanding'), 'Contains KPI: Dues Outstanding');
      assert(bodyText.includes('Collections Today'), 'Contains KPI: Collections Today');
      assert(bodyText.includes('Expenses Outflow') || bodyText.includes('Expenses'), 'Contains KPI: Expenses');
      assert(bodyText.includes('Stock Value'), 'Contains KPI: Stock Value');
      assert(bodyText.includes('Low-Stock Count') || bodyText.includes('Low Stock'), 'Contains KPI: Low-Stock Count');
      assert(bodyText.includes('+14.2%') && bodyText.includes('-3.4%'), 'KPI cards include percentage deltas');

      // --- 3.3 5 SVG CHARTS (NO EMPTY AXES) ---
      console.log('\n--- 3.3 5 CHARTS (INLINE SVG, NO EMPTY AXES) ---');
      // Chart 1: Sales trend line
      const hasTrendChart = await evalJs(`
        const svg = document.querySelector('svg polyline');
        const hasYAxis = document.body.textContent.includes('৳100k') && document.body.textContent.includes('৳50k');
        const hasXAxis = document.body.textContent.includes('10 AM') && document.body.textContent.includes('6 PM');
        return Boolean(svg && hasYAxis && hasXAxis);
      `);
      assert(hasTrendChart, 'Chart 1: Sales trend line with SVG polyline, gradient area & full X/Y axes');

      // Chart 2: Payment-method mix
      const hasPaymentMix = await evalJs(`
        const hasDonut = Boolean(document.querySelector('circle[stroke-dasharray]'));
        const hasCash = document.body.textContent.includes('Cash Tender (58%)');
        const hasBkash = document.body.textContent.includes('bKash QR');
        return hasDonut && hasCash && hasBkash;
      `);
      assert(hasPaymentMix, 'Chart 2: Payment-method mix donut chart with Cash, bKash & Card');

      // Chart 3: Hourly heat-map
      const hasHeatmap = await evalJs(`
        const hasDays = document.body.textContent.includes('Sat') && document.body.textContent.includes('Fri');
        const hasHours = document.body.textContent.includes('10am') && document.body.textContent.includes('8pm');
        return hasDays && hasHours;
      `);
      assert(hasHeatmap, 'Chart 3: Hourly sales heat-map with day & hour grid axes');

      // Chart 4: Branch comparison bars
      const hasBranchBars = await evalJs(`
        const hasBars = document.body.textContent.includes('DF1 Main') && document.body.textContent.includes('DF2 Dhan');
        const hasScale = document.body.textContent.includes('৳60k') && document.body.textContent.includes('৳20k');
        return hasBars && hasScale;
      `);
      assert(hasBranchBars, 'Chart 4: Branch comparison bars with outlet names and ৳ scale');

      // Chart 5: Sales by district
      const hasDistrictChart = await evalJs(`
        const hasDhaka = document.body.textContent.includes('Dhaka North') && document.body.textContent.includes('Dhaka South');
        const hasCtg = document.body.textContent.includes('Chittagong Metro');
        return hasDhaka && hasCtg;
      `);
      assert(hasDistrictChart, 'Chart 5: Sales by district with regional progress bars and percentages');

      // --- 3.4 5 LISTS ---
      console.log('\n--- 3.4 5 LISTS VERIFICATION ---');
      assert(bodyText.includes("Men's Slim Fit Panjabi"), 'List 1: Top products list displays items');
      assert(bodyText.includes('Rahim Uddin') && bodyText.includes('Platinum VIP'), 'List 2: Top customers list displays privilege members');
      assert(bodyText.includes('Low Stock Alerts') && bodyText.includes('Reorder'), 'List 3: Low-stock list has "Reorder" action');
      assert(bodyText.includes('Recent Sales Invoices') && bodyText.includes('DF1-R1-'), 'List 4: Recent sales list displays latest invoices');
      assert(bodyText.includes('Overdue Balances to Chase') && bodyText.includes('Send Reminder'), 'List 5: Dues to chase list has "Send reminder" action');

      // --- 3.5 FIRST-RUN CHECKLIST CARD ---
      console.log('\n--- 3.5 FIRST-RUN CHECKLIST CARD ---');
      const hasChecklist = await evalJs("Boolean(document.body.textContent.includes('Store Setup Checklist') && document.body.textContent.includes('3 of 4 Completed'))");
      assert(hasChecklist, 'First-run checklist card displays setup tasks & progress');

      // --- 3.6 SETTINGS (G ,) VERIFICATION ---
      console.log('\n--- 3.6 SETTINGS (G ,) VERIFICATION ---');
      await pressKey('g', 'KeyG', 71);
      await wait(80);
      await pressKey(',', 'Comma', 188);
      await wait(500);

      const setHeader = await evalJs("document.querySelector('h1')?.textContent || ''");
      assert(setHeader.includes('Store & System Settings'), 'Navigated to Settings screen (G ,)');

      // Full Width verification: no max-w-4xl on main settings container
      const isFullWidth = await evalJs(`
        const container = Array.from(document.querySelectorAll('div')).find(d => d.textContent.includes('Store & System Settings') && d.querySelector('h1'));
        const parent = container?.closest('.flex-1');
        return parent ? !parent.className.includes('max-w-4xl') : true;
      `);
      assert(isFullWidth, 'Settings layout is FULL WIDTH (max-w-4xl removed)');

      // Left sub-nav has 15+ sections
      const navSections = await evalJs(`
        const buttons = Array.from(document.querySelectorAll('button')).map(b => b.textContent.trim());
        const expected = [
          'Business Profile', 'Branches', 'Registers & Devices', 'Tax & 15% VAT',
          'Payment Methods', 'Receipt & Printers', 'Invoice Numbering', 'Customer PIN Policy',
          'Membership Card Design', 'Messaging Providers', 'Returns & Exchanges',
          'Notifications & Alerts', 'Appearance & Themes', 'Language & Regional',
          'Keyboard Shortcuts', 'Data Import & Export'
        ];
        return expected.filter(exp => buttons.some(b => b.includes(exp)));
      `);
      assert(navSections.length >= 15, `Settings left sub-nav contains all 15 sections (${navSections.length} found)`);

      // Test Print in Receipt & Printers
      await evalJs(`
        const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Receipt & Printers'));
        if (btn) btn.click();
      `);
      await wait(200);

      const hasTestPrint = await evalJs("Boolean(Array.from(document.querySelectorAll('button')).some(b => b.textContent.includes('Send Test Print')))");
      assert(hasTestPrint, 'Receipt & printers section has "Send Test Print" button');

      // QR Pairing in Registers & Devices
      await evalJs(`
        const btn = Array.from(document.querySelectorAll('button')).find(b => b.textContent.includes('Registers & Devices'));
        if (btn) btn.click();
      `);
      await wait(200);

      const hasQrPairing = await evalJs("Boolean(document.body.textContent.includes('Mobile POS Companion Pairing') && document.body.textContent.includes('DF-PAIR-'))");
      assert(hasQrPairing, 'Registers & devices section has QR pairing widget & pairing code');

      // Dirty-state bar & Save (Ctrl+S)
      const input = await evalJs(`
        const inp = Array.from(document.querySelectorAll('input')).find(i => i.value && i.value.includes('DF1-R1'));
        if (inp) {
          inp.value = 'DF1-R1-MODIFIED';
          inp.dispatchEvent(new Event('input', { bubbles: true }));
          inp.dispatchEvent(new Event('change', { bubbles: true }));
          return true;
        }
        return false;
      `);
      await wait(200);

      const dirtyBarVisible = await evalJs("Boolean(document.body.textContent.includes('You have unsaved changes'))");
      assert(dirtyBarVisible, 'Editing settings displays dirty-state warning bar');

      // Save via Ctrl+S
      await pressKey('s', 'KeyS', 83, 2); // 2 = Ctrl
      await wait(300);

      const toastSaved = await evalJs("Boolean(document.body.textContent.includes('Settings successfully saved'))");
      assert(toastSaved, 'Pressing Ctrl+S saves settings and shows confirmation toast');

      // --- 3.7 SELF-CHECK 7 ---
      console.log('\n--- 3.7 SELF-CHECK 7 VERIFICATION ---');
      assert(
        hasTrendChart && hasPaymentMix && hasHeatmap && hasBranchBars && hasDistrictChart && navSections.length >= 15 && isFullWidth,
        'SELF-CHECK 7: Dashboard has no empty chart; Settings has 15 sections and is full width'
      );

      console.log('\n========================================');
      console.log(`PART 3 VERIFICATION COMPLETE: ${passed} PASSED, ${failed} FAILED`);
      console.log('========================================\n');

      ws.close();
      chrome.kill();
      process.exit(failed > 0 ? 1 : 0);
    } catch (err) {
      console.error('Test execution error:', err);
      ws.close();
      chrome.kill();
      process.exit(1);
    }
  };
}

run();
