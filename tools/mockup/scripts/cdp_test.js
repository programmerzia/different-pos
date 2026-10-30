// scripts/cdp_test.js - Comprehensive CDP Verification Suite
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
  console.log('Launching headless Chrome with 1440x900 viewport...');
  const chrome = spawn('google-chrome-stable', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--window-size=1440,900',
    '--user-data-dir=/tmp/pos-test-profile-' + Date.now(),
    '--no-sandbox',
    '--disable-gpu',
    'http://127.0.0.1:8080/'
  ]);

  const cleanup = () => {
    try { chrome.kill(); } catch (e) {}
  };
  process.on('exit', cleanup);
  process.on('SIGINT', cleanup);

  console.log('Waiting for Chrome DevTools...');
  const page = await getPageTarget();
  if (!page) {
    console.error('Target page not found!');
    cleanup();
    process.exit(1);
  }
  console.log('Connected to target:', page.title, page.url);

  const ws = new WebSocket(page.webSocketDebuggerUrl);

  let msgId = 1;
  const pending = new Map();

  function send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = msgId++;
      pending.set(id, { resolve, reject });
      ws.send(JSON.stringify({ id, method, params }));
    });
  }

  const errors = [];
  ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    if (data.id && pending.has(data.id)) {
      const { resolve, reject } = pending.get(data.id);
      pending.delete(data.id);
      if (data.error) reject(data.error);
      else resolve(data.result);
    } else if (data.method === 'Runtime.consoleAPICalled') {
      const text = data.params.args.map(a => a.value || a.description).join(' ');
      if (data.params.type === 'error') {
        console.error('[BROWSER CONSOLE ERROR]', text);
        errors.push(text);
      }
    } else if (data.method === 'Runtime.exceptionThrown') {
      const err = `${data.params.exceptionDetails.text} ${data.params.exceptionDetails.exception?.description}`;
      console.error('[BROWSER EXCEPTION]', err);
      errors.push(err);
    }
  };

  ws.onopen = async () => {
    console.log('WebSocket connected to CDP');
    await send('Runtime.enable');
    await send('Page.enable');
    await send('DOM.enable');

    console.log('Waiting for React initial render...');
    await wait(2000);

    // ================= TEST 1: PRINT ISOLATION & COLOR FIDELITY CSS =================
    console.log('\n--- TEST 1: Print CSS Color Adjust & Isolation Verification ---');
    const printCssCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const styles = Array.from(document.querySelectorAll('style')).map(s => s.innerText).join('\\n');
          const hasColorAdjust = styles.includes('print-color-adjust: exact') || styles.includes('-webkit-print-color-adjust: exact');
          const hasPrintableArea = styles.includes('.printable-area');
          const hasNoPrintRule = styles.includes('.no-print');
          const hasReceiptRule = styles.includes('.printable-receipt');
          return {
            hasColorAdjust,
            hasPrintableArea,
            hasNoPrintRule,
            hasReceiptRule
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Print CSS Rules Verification:', printCssCheck.result.value);
    if (!printCssCheck.result.value.hasColorAdjust) {
      throw new Error('Print CSS missing exact color adjust rule!');
    }

    // ================= TEST 2: CUSTOMERS DIRECTORY & CUSTOMER 360 PROFILE (NO GPS) =================
    console.log('\n--- TEST 2: Customers Directory & Customer 360 Profile (Zero GPS) ---');
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const btn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Customers'));
          if (btn) btn.click();
        })()
      `
    });
    await wait(600);

    // Click first customer row to open Customer 360 Profile
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const firstRow = document.querySelector('tbody tr');
          if (firstRow) firstRow.click();
        })()
      `
    });
    await wait(600);

    const profileCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const heading = document.querySelector('h1')?.innerText || '';
          const hasGpsCoordinates = document.body.innerText.includes('GPS SATELLITE COORDINATES');
          const hasGpsChip = document.body.innerText.includes('Verified GPS') || document.body.innerText.includes('GPS:');
          const hasIdentityCard = document.body.innerText.includes('Identity, Membership & Credit Limits');
          const hasCreditMeter = document.body.innerText.includes('Credit Facility Utilization');
          return {
            customerHeading: heading,
            hasGpsCoordinates,
            hasGpsChip,
            hasIdentityCard,
            hasCreditMeter
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Customer 360 Profile Verification:', profileCheck.result.value);
    if (profileCheck.result.value.hasGpsCoordinates || profileCheck.result.value.hasGpsChip) {
      throw new Error('Customer 360 Profile still contains GPS coordinates or chip!');
    }

    // ================= TEST 3: CUSTOMER REGISTRATION FORM (NO GPS & SHORTENED SAVE BUTTON) =================
    console.log('\n--- TEST 3: Customer Registration Form (Zero GPS & Clean Save Button) ---');
    // Navigate back to Customers screen
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const backBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Customers'));
          if (backBtn) backBtn.click();
        })()
      `
    });
    await wait(500);

    // Click "Add Customer" button
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const addBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Add Customer'));
          if (addBtn) addBtn.click();
        })()
      `
    });
    await wait(600);

    const formButtonAndGpsCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const hasGpsButton = Array.from(document.querySelectorAll('button')).some(b => b.innerText.includes('Detect GPS Location'));
          const saveBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save Customer'));
          const saveBtnText = saveBtn ? saveBtn.innerText.trim() : '';
          const hasExcessiveText = saveBtnText.includes('গ্রাহক সংরক্ষণ করুন') || saveBtnText.includes('Issue Card');
          return {
            hasGpsButton,
            saveBtnFound: !!saveBtn,
            saveBtnText,
            hasExcessiveText
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Customer Form Structure Verification:', formButtonAndGpsCheck.result.value);
    if (formButtonAndGpsCheck.result.value.hasGpsButton) {
      throw new Error('Customer form still contains Detect GPS Location button!');
    }
    if (formButtonAndGpsCheck.result.value.hasExcessiveText) {
      throw new Error('Save button text is still excessively long: ' + formButtonAndGpsCheck.result.value.saveBtnText);
    }

    // Test Empty Form Validation
    console.log('Testing empty customer form submission...');
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const saveBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save Customer'));
          if (saveBtn) saveBtn.click();
        })()
      `
    });
    await wait(400);

    const emptyValidation = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const errors = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          return { errorCount: errors.length, errors };
        })()
      `,
      returnByValue: true
    });
    console.log('Empty Customer Form Validation Result:', emptyValidation.result.value);
    if (emptyValidation.result.value.errorCount === 0) {
      throw new Error('Customer form failed to trigger validation on empty submission!');
    }

    // ================= TEST 4: MEMBERSHIP CARDS SCREEN & PRINT COLOR FIDELITY =================
    console.log('\n--- TEST 4: Membership Cards Screen & Print Isolation ---');
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const cardsBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Membership Cards'));
          if (cardsBtn) cardsBtn.click();
        })()
      `
    });
    await wait(700);

    const cardScreenCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const printableCardArea = document.getElementById('printable-card-area');
          const noPrintElements = Array.from(document.querySelectorAll('.no-print'));
          const cards = printableCardArea ? Array.from(printableCardArea.querySelectorAll('.rounded-2xl')) : [];
          const firstCard = cards[0];
          const bgStyle = firstCard ? (firstCard.getAttribute('style') || '') : '';
          const hasLuxuryGradient = bgStyle.includes('linear-gradient');
          const hasExactPrint = bgStyle.includes('print-color-adjust: exact') || bgStyle.includes('WebkitPrintColorAdjust') || bgStyle.includes('printColorAdjust');
          return {
            printableCardAreaPresent: !!printableCardArea,
            noPrintElementsCount: noPrintElements.length,
            cardCount: cards.length,
            hasLuxuryGradient,
            hasExactPrint,
            bgStylePreview: bgStyle.slice(0, 80)
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Membership Cards Screen Verification:', cardScreenCheck.result.value);
    if (!cardScreenCheck.result.value.printableCardAreaPresent) {
      throw new Error('Printable card area (#printable-card-area) not found on Membership Cards screen!');
    }

    // ================= TEST 5: QUICK NEW CUSTOMER MODAL (MODAL 2) VALIDATION =================
    console.log('\n--- TEST 5: Quick New Customer Modal (Modal 2) Validation ---');
    // Open POS screen
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const posBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('POS Register') || b.innerText.includes('F1'));
          if (posBtn) posBtn.click();
        })()
      `
    });
    await wait(500);

    // Open attach customer modal (F4)
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const attachBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Attach Customer') || b.innerText.includes('F4'));
          if (attachBtn) attachBtn.click();
        })()
      `
    });
    await wait(400);

    // Click "New (Ctrl+N)" in Attach Customer Modal
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const newBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('New (Ctrl+N)'));
          if (newBtn) newBtn.click();
        })()
      `
    });
    await wait(500);

    // Click "Register & Attach Customer" with empty fields to trigger validation
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const regBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save & Attach to Sale'));
          if (regBtn) regBtn.click();
        })()
      `
    });
    await wait(400);

    const quickCustValidationCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const roseInputs = document.querySelectorAll('.border-rose-500');
          const errorTexts = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          const hasNameError = errorTexts.some(e => e.includes('name is required'));
          const hasPhoneError = errorTexts.some(e => e.includes('mobile number'));
          return {
            roseInputsCount: roseInputs.length,
            hasNameError,
            hasPhoneError,
            errorTexts
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Quick New Customer Modal Validation Result:', quickCustValidationCheck.result.value);
    if (!quickCustValidationCheck.result.value.hasNameError || !quickCustValidationCheck.result.value.hasPhoneError) {
      throw new Error('Quick Customer modal failed to display name and phone validation errors!');
    }

    // Close quick customer modal
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const cancelBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText === 'Cancel');
          if (cancelBtn) cancelBtn.click();
        })()
      `
    });
    await wait(400);

    // ================= TEST 6: PAY VENDOR MODAL (MODAL 18) VALIDATION =================
    console.log('\n--- TEST 6: Pay Vendor Modal (Modal 18) Validation ---');
    // Switch to Owner role via avatar to unlock all modules
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const avatarBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Cashier') || b.innerText.includes('Sumi') || b.innerText.includes('Manager') || b.innerText.includes('Owner'));
          if (avatarBtn) avatarBtn.click();
        })()
      `
    });
    await wait(300);
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const roleSelect = Array.from(document.querySelectorAll('select')).find(s => Array.from(s.options).some(o => o.value === 'Owner'));
          if (roleSelect) {
            roleSelect.value = 'Owner';
            roleSelect.dispatchEvent(new Event('change', { bubbles: true }));
          }
          const backdrop = document.querySelector('.fixed.inset-0.z-40');
          if (backdrop) backdrop.click();
        })()
      `
    });
    await wait(500);

    // Navigate to Vendor Directory screen
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const vBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Vendor Directory'));
          if (vBtn) vBtn.click();
        })()
      `
    });
    await wait(600);

    // Click "Pay Vendor" on first vendor
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const payBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Pay Vendor'));
          if (payBtn) payBtn.click();
        })()
      `
    });
    await wait(500);

    // Set invalid payment amount (0)
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const setReactVal = (input, val) => {
            Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(input, val);
            input.dispatchEvent(new Event('input', { bubbles: true }));
          };
          const numInput = document.querySelector('input[type="number"]');
          if (numInput) setReactVal(numInput, '0');

          const submitBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Confirm Payment'));
          if (submitBtn) submitBtn.click();
        })()
      `
    });
    await wait(400);

    const payVendorValidationCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const roseInputs = document.querySelectorAll('.border-rose-500');
          const errorTexts = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          const hasZeroError = errorTexts.some(e => e.includes('greater than ৳0'));
          return {
            hasRoseInput: roseInputs.length > 0,
            hasZeroError,
            errorTexts
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Pay Vendor Validation Result:', payVendorValidationCheck.result.value);
    if (!payVendorValidationCheck.result.value.hasZeroError) {
      throw new Error('Pay vendor modal failed to reject zero payment amount!');
    }

    // Close pay vendor modal
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const cancelBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText === 'Cancel');
          if (cancelBtn) cancelBtn.click();
        })()
      `
    });
    await wait(400);

    // ================= TEST 7: INTER-BRANCH STOCK TRANSFER MODAL (MODAL 15) VALIDATION =================
    console.log('\n--- TEST 7: Inter-Branch Stock Transfer (Modal 15) Validation ---');
    // Navigate to Stock & Transfer screen
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const stockBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Stock & Transfer'));
          if (stockBtn) stockBtn.click();
        })()
      `
    });
    await wait(600);

    // Click "Branch Transfer" button
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const xferBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Branch Transfer'));
          if (xferBtn) xferBtn.click();
        })()
      `
    });
    await wait(500);

    // Select identical origin and destination branches and click Generate Gate Pass
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const selects = Array.from(document.querySelectorAll('select')).filter(s => Array.from(s.options).some(o => o.innerText.includes('DF1') || o.innerText.includes('DF2')));
          if (selects.length >= 2) {
            selects[0].value = selects[0].options[0].value;
            selects[0].dispatchEvent(new Event('change', { bubbles: true }));
            selects[1].value = selects[0].options[0].value; // Make identical
            selects[1].dispatchEvent(new Event('change', { bubbles: true }));
          }
          const execBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Generate Gate Pass'));
          if (execBtn) execBtn.click();
        })()
      `
    });
    await wait(400);

    const transferValidationCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const errorTexts = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          const hasBranchError = errorTexts.some(e => e.includes('identical') || e.includes('Origin and Destination'));
          return {
            hasBranchError,
            errorTexts
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Inter-Branch Transfer Validation Result:', transferValidationCheck.result.value);
    if (!transferValidationCheck.result.value.hasBranchError) {
      throw new Error('Transfer modal failed to validate identical origin and destination branches!');
    }

    // Close transfer modal
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const cancelBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText === 'Cancel');
          if (cancelBtn) cancelBtn.click();
        })()
      `
    });
    await wait(400);

    // ================= TEST 8: ADD CATEGORY MODAL (MODAL 25B) VALIDATION =================
    console.log('\n--- TEST 8: Add Category Modal (Modal 25B) Validation ---');
    // Navigate to Products & SKUs screen
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const prodBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Products & SKUs'));
          if (prodBtn) prodBtn.click();
        })()
      `
    });
    await wait(600);

    // Click "Categories" sub-tab
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const catTab = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Category Management'));
          if (catTab) catTab.click();
        })()
      `
    });
    await wait(500);

    // Click "Add New Category"
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const addCatBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Add New Category'));
          if (addCatBtn) addCatBtn.click();
        })()
      `
    });
    await wait(500);

    // Submit empty category form
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const saveBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save Category'));
          if (saveBtn) saveBtn.click();
        })()
      `
    });
    await wait(400);

    const categoryValidationCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const roseInputs = document.querySelectorAll('.border-rose-500');
          const errorTexts = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          const hasCatError = errorTexts.some(e => e.includes('Category name is required'));
          return {
            hasRoseInput: roseInputs.length > 0,
            hasCatError,
            errorTexts
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Add Category Validation Result:', categoryValidationCheck.result.value);
    if (!categoryValidationCheck.result.value.hasCatError) {
      throw new Error('Add Category modal failed to validate empty category name!');
    }

    // Close add category modal
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const cancelBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText === 'Cancel');
          if (cancelBtn) cancelBtn.click();
        })()
      `
    });
    await wait(400);

    // ================= TEST 9: RESET STAFF PIN (MODAL 23) VALIDATION =================
    console.log('\n--- TEST 9: Reset Staff PIN (Modal 23) Validation ---');
    // Navigate to Users & Roles
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const usersBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Users & Roles'));
          if (usersBtn) usersBtn.click();
        })()
      `
    });
    await wait(600);

    // Click "Reset PIN" on first staff member
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const resetBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Reset PIN'));
          if (resetBtn) resetBtn.click();
        })()
      `
    });
    await wait(500);

    // Enter 2 digits only and click "Update PIN"
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const setReactVal = (input, val) => {
            Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(input, val);
            input.dispatchEvent(new Event('input', { bubbles: true }));
          };
          const pinInput = document.querySelector('input[type="password"]');
          if (pinInput) setReactVal(pinInput, '12');

          const updateBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Update PIN'));
          if (updateBtn) updateBtn.click();
        })()
      `
    });
    await wait(400);

    const pinValidationCheck = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const errorTexts = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          const hasPinError = errorTexts.some(e => e.includes('4 numeric digits'));
          return {
            hasPinError,
            errorTexts
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Reset PIN Validation Result:', pinValidationCheck.result.value);
    if (!pinValidationCheck.result.value.hasPinError) {
      throw new Error('Reset PIN modal failed to reject non-4-digit PIN!');
    }

    // Close reset PIN modal
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const cancelBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText === 'Cancel');
          if (cancelBtn) cancelBtn.click();
        })()
      `
    });
    await wait(400);

    // ================= TEST 10: PRODUCT CATALOG PRICE < COST VALIDATION =================
    console.log('\n--- TEST 10: Product Form Retail Price < Cost Price Validation ---');
    // Navigate back to Products & SKUs screen
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const prodBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Products & SKUs'));
          if (prodBtn) prodBtn.click();
        })()
      `
    });
    await wait(600);

    // Click "Products & SKUs" subtab to ensure add product is visible
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const prodSubTab = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Products & SKUs') && b.className.includes('h-7'));
          if (prodSubTab) prodSubTab.click();
        })()
      `
    });
    await wait(400);

    // Click "+ Add Product"
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const addProdBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Add Product'));
          if (addProdBtn) addProdBtn.click();
        })()
      `
    });
    await wait(600);

    // Set invalid price < cost
    await send('Runtime.evaluate', {
      expression: `
        (() => {
          const setReactVal = (input, val) => {
            Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(input, val);
            input.dispatchEvent(new Event('input', { bubbles: true }));
          };
          const inputs = document.querySelectorAll('input');
          // Cost = 1200, Price = 800 (invalid)
          setReactVal(inputs[0], 'Test Silk Kurti');
          setReactVal(inputs[2], 'DF-KUR-9901');
          setReactVal(inputs[4], '1200'); // Cost
          setReactVal(inputs[5], '800'); // Retail MRP < Cost

          const saveBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Save Product'));
          if (saveBtn) saveBtn.click();
        })()
      `
    });
    await wait(400);

    const priceValidation = await send('Runtime.evaluate', {
      expression: `
        (() => {
          const errors = Array.from(document.querySelectorAll('.text-rose-500')).map(e => e.innerText.trim()).filter(Boolean);
          return {
            hasPriceError: errors.some(e => e.includes('cannot be less than cost price')),
            errors
          };
        })()
      `,
      returnByValue: true
    });
    console.log('Product Price < Cost Validation Result:', priceValidation.result.value);
    if (!priceValidation.result.value.hasPriceError) {
      throw new Error('Product form failed to reject retail price < cost price!');
    }

    console.log('\n================ ALL 10 COMPREHENSIVE SUITE TESTS PASSED ================');
    console.log('Total Browser Console Errors:', errors.length);

    cleanup();
    ws.close();
    process.exit(errors.length > 0 ? 1 : 0);
  };
}

run().catch(err => {
  console.error('CDP Error:', err);
  process.exit(1);
});
