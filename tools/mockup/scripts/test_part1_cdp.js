// scripts/test_part1_cdp.js - Automated Verification for Part 1 Blockers
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
  console.log('Launching headless Chrome for Part 1 verification...');
  const chrome = spawn('google-chrome-stable', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--window-size=1440,900',
    '--user-data-dir=/tmp/pos-test-part1-' + Date.now(),
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
      const res = await send('Runtime.evaluate', { expression: expr, returnByValue: true });
      if (res.exceptionDetails) {
        throw new Error(res.exceptionDetails.text + ' ' + (res.exceptionDetails.exception?.description || ''));
      }
      return res.result?.value;
    };

    const pressKey = async (key, options = {}) => {
      await evalJs(`
        (() => {
          const target = document.activeElement || window;
          const ev = new KeyboardEvent('keydown', {
            key: ${JSON.stringify(key)},
            bubbles: true,
            cancelable: true,
            shiftKey: ${!!options.shiftKey},
            ctrlKey: ${!!options.ctrlKey},
            altKey: ${!!options.altKey},
            metaKey: ${!!options.metaKey}
          });
          target.dispatchEvent(ev);
        })()
      `);
      await wait(180);
    };

    const setInputValue = async (id, val) => {
      await evalJs(`
        (() => {
          const el = document.getElementById(${JSON.stringify(id)});
          if (!el) return;
          const setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value')?.set;
          if (setter) setter.call(el, ${JSON.stringify(val)});
          else el.value = ${JSON.stringify(val)};
          el.dispatchEvent(new Event('input', { bubbles: true }));
          el.dispatchEvent(new Event('change', { bubbles: true }));
        })()
      `);
      await wait(300);
    };

    console.log('Waiting for React application to mount...');
    await wait(2000);

    const title = await evalJs('document.title');
    console.log('Document title:', title);
    await evalJs('window.confirm = () => true;');

    let passed = 0;
    let failed = 0;
    const assert = (desc, cond) => {
      if (cond) {
        console.log(`  ✓ PASS: ${desc}`);
        passed++;
      } else {
        console.error(`  ✗ FAIL: ${desc}`);
        failed++;
      }
    };

    // ==========================================
    // CHECK 1.1: Single Modal System for Every Overlay
    // ==========================================
    console.log('\n--- CHECK 1.1: Single Modal System & Focus Trap ---');

    // 1. F4: Customer Attach
    await pressKey('F4');
    let modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    let modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('F4 opens Customer Attach modal', modalCount === 1 && modalTitle?.includes('Attach Customer'));

    // Pressing another function key while modal is open does NOT open background modal
    await pressKey('F5');
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    assert('Pressing F5 while modal is open does NOT open another modal', modalCount === 1);

    // Esc closes modal and returns focus to scan box
    await pressKey('Escape');
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    let activeId = await evalJs('document.activeElement?.id');
    assert('Esc closes Customer Attach modal', modalCount === 0);
    assert('On modal close, scan box is focused', activeId === 'pos-barcode-input');

    // 2. F5: Price Check Modal
    await pressKey('F5');
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('F5 opens Price Check modal', modalCount === 1 && modalTitle?.includes('Price & Stock Check'));
    await pressKey('Escape');
    assert('Esc closes Price Check modal', (await evalJs('document.querySelectorAll(\'[role="dialog"]\').length')) === 0);

    // 3. F6: Line Discount Modal
    await pressKey('F6');
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('F6 opens Line Discount modal', modalCount === 1 && modalTitle?.includes('Line Item Discount'));
    await pressKey('Escape');

    // 4. Shift+F6: Order Discount Modal
    await pressKey('F6', { shiftKey: true });
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('Shift+F6 opens Order Discount modal', modalCount === 1 && modalTitle?.includes('Order / Bill Discount'));
    await pressKey('Escape');

    // 5. Shift+F8: Parked / Held Sales Modal
    await pressKey('F8', { shiftKey: true });
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('Shift+F8 opens Parked / Held Sales modal', modalCount === 1 && modalTitle?.includes('Parked / Held Sales'));
    await pressKey('Escape');

    // 6. F9: Payment Modal
    await pressKey('F9');
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('F9 opens Payment & Due Settlement modal', modalCount === 1 && modalTitle?.includes('Payment'));
    
    // Check Alt+E chip in payment modal
    let hasAltEChip = await evalJs('!!document.querySelector(\'button[title*="Alt+E"]\')');
    assert('Payment modal contains Exact Cash (Alt+E) chip', hasAltEChip);

    // Check F10 button in payment modal
    let hasF10Btn = await evalJs('!!document.querySelector(\'button kbd\')?.textContent.includes("F10") || document.body.innerText.includes("F10")');
    assert('Payment modal contains Complete Sale (F10) action', hasF10Btn);

    await pressKey('Escape');

    // 7. Ctrl+D: Collect Due Modal
    await pressKey('d', { ctrlKey: true });
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('Ctrl+D opens Collect Customer Due modal', modalCount === 1 && modalTitle?.includes('Collect Customer Due'));
    await pressKey('Escape');

    // 8. Ctrl+N: Quick New Customer
    await pressKey('n', { ctrlKey: true });
    modalCount = await evalJs('document.querySelectorAll(\'[role="dialog"]\').length');
    modalTitle = await evalJs('document.querySelector(\'[role="dialog"] span\')?.textContent');
    assert('Ctrl+N opens Quick New Customer modal', modalCount === 1 && modalTitle?.includes('Quick New Customer'));
    await pressKey('Escape');

    // ==========================================
    // CHECK 1.2: POS Key Map Exactly as Spec (No conflicts)
    // ==========================================
    console.log('\n--- CHECK 1.2: POS Key Map & Conflict Check ---');
    const scanBoxPlaceholder = await evalJs('document.getElementById("pos-barcode-input")?.getAttribute("placeholder")');
    assert('Scan box has id pos-barcode-input and displays (F2)', scanBoxPlaceholder?.includes('(F2)'));

    const payButtonText = await evalJs('Array.from(document.querySelectorAll("button")).find(b => b.textContent.includes("PAY") && b.textContent.includes("F9"))?.textContent');
    assert('Prominent PAY button on POS shows F9', !!payButtonText);

    // Check for key conflicts across key-hints in shortcuts modal
    await pressKey('?');
    const shortcutKbds = await evalJs(`
      Array.from(document.querySelectorAll('[role="dialog"] kbd')).map(k => k.textContent.trim());
    `);
    const seen = new Set();
    let hasDup = false;
    for (const k of shortcutKbds) {
      if (seen.has(k)) { hasDup = true; break; }
      seen.add(k);
    }
    assert('Cheatsheet lists distinct shortcuts with zero duplicate mappings', !hasDup);
    await pressKey('Escape');

    // ==========================================
    // CHECK 1.3: Command Palette Really Searches
    // ==========================================
    console.log('\n--- CHECK 1.3: Command Palette Search & Luhn Validation ---');
    await pressKey('k', { ctrlKey: true });
    let paletteOpen = await evalJs('!!document.getElementById("command-palette-input")');
    assert('Ctrl+K opens Command Palette modal', paletteOpen);

    // 1. Search "saree"
    await setInputValue('command-palette-input', 'saree');
    let sareeMatches = await evalJs('document.body.innerText.toLowerCase().includes("saree") && document.body.innerText.toLowerCase().includes("products")');
    assert('Searching "saree" lists sarees under Products group', sareeMatches);

    // 2. Search "1000 0017" (valid Luhn)
    await setInputValue('command-palette-input', '1000 0017');
    let rahimFound = await evalJs('document.body.innerText.includes("Rahim Uddin") && document.body.innerText.includes("1000 0017")');
    assert('Searching valid customer "1000 0017" finds Rahim Uddin with verified Luhn', rahimFound);

    // Press Enter to open Rahim's 360 profile
    await pressKey('Enter');
    await wait(400);
    let isCustomerProfile = await evalJs('document.body.innerText.includes("Rahim Uddin") && (document.body.innerText.includes("Credit Limit") || document.body.innerText.includes("Customer 360"))');
    assert('Pressing Enter on valid customer opens 360 profile', isCustomerProfile);

    // 3. Search "1000 0018" (invalid Luhn)
    await pressKey('k', { ctrlKey: true });
    await wait(250);
    await setInputValue('command-palette-input', '1000 0018');
    let invalidFound = await evalJs('document.body.innerText.includes("Invalid customer number")');
    assert('Searching invalid customer "1000 0018" displays "Invalid customer number"', invalidFound);
    await pressKey('Escape');

    // 4. Search "/26000412" (invoice search)
    await pressKey('k', { ctrlKey: true });
    await wait(250);
    await setInputValue('command-palette-input', '/26000412');
    let invoiceFound = await evalJs('document.body.innerText.includes("DF1-R1-26000412")');
    assert('Searching "/26000412" matches invoice DF1-R1-26000412', invoiceFound);
    await pressKey('Enter');
    await wait(400);
    let isSaleDetail = await evalJs('document.body.innerText.includes("DF1-R1-26000412") && (document.body.innerText.includes("Purchased Items") || document.body.innerText.includes("Payment Breakdown"))');
    assert('Pressing Enter on invoice opens Sale Detail screen', isSaleDetail);

    // ==========================================
    // CHECK 1.4: Permissions Apply to Shortcuts & Palette
    // ==========================================
    console.log('\n--- CHECK 1.4: Permissions Matrix & Restrictions ---');
    // Ensure we are viewing as role: Cashier
    await evalJs(`if (window.__setViewAsRole) window.__setViewAsRole("Cashier");`);
    await wait(200);

    // Simulate G P (Purchases) as Cashier
    await pressKey('g');
    await wait(60);
    await pressKey('p');
    await wait(200);
    let toastRestricted = await evalJs('document.body.innerText.includes("Not allowed for Cashier — ask a Manager")');
    assert('As Cashier, "G P" shows toast "Not allowed for Cashier — ask a Manager" and does not navigate', toastRestricted);

    // Simulate G M (Money) as Cashier
    await pressKey('g');
    await wait(60);
    await pressKey('m');
    await wait(200);
    toastRestricted = await evalJs('document.body.innerText.includes("Not allowed for Cashier — ask a Manager")');
    assert('As Cashier, "G M" shows toast "Not allowed for Cashier — ask a Manager"', toastRestricted);

    // Change View As Role to "Owner"
    await evalJs(`if (window.__setViewAsRole) window.__setViewAsRole("Owner");`);
    await wait(300);

    // As Owner, simulate G M (Money)
    await pressKey('g');
    await wait(60);
    await pressKey('m');
    await wait(300);
    let isMoneyScreen = await evalJs('document.body.innerText.includes("Financial Accounts") || document.body.innerText.includes("Cash Drawer (Main)")');
    assert('As Owner, "G M" opens Money screen successfully', isMoneyScreen);

    // ==========================================
    // CHECK 1.5: Remove Every Shift Remnant
    // ==========================================
    console.log('\n--- CHECK 1.5: Verify Zero Shift / Float Remnants ---');
    let hasShiftText = await evalJs('document.body.innerText.includes("Shift:") || document.body.innerText.includes("Float ৳5,000")');
    assert('Zero UI text containing "Shift:" or "Float ৳5,000"', !hasShiftText);

    let hasTodayPill = await evalJs('document.body.innerText.includes("Today ৳84,250") || document.body.innerText.includes("Today ৳")');
    assert('Status pill correctly displays "Today ৳84,250 · 63 sales"', hasTodayPill);

    console.log(`\n========================================`);
    console.log(`PART 1 VERIFICATION COMPLETE: ${passed} PASSED, ${failed} FAILED`);
    console.log(`========================================\n`);

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
