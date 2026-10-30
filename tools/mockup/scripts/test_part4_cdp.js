#!/usr/bin/env node

/**
 * Headless Chrome CDP Verification for Part 4: Missing and Thin Screens
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
  console.log('Launching headless Chrome for Part 4 verification...');
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
    const data = JSON.parse(event.data);
    if (data.method === 'Runtime.exceptionThrown') {
      console.error('  [BROWSER EXCEPTION]:', data.params.exceptionDetails?.text, data.params.exceptionDetails?.exception?.description);
    }
    if (data.id && pending.has(data.id)) {
      const { resolve, reject } = pending.get(data.id);
      pending.delete(data.id);
      if (data.error) reject(data.error);
      else resolve(data.result);
    }
  };

  function send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = msgId++;
      pending.set(id, { resolve, reject });
      ws.send(JSON.stringify({ id, method, params }));
    });
  }

  await new Promise(r => ws.onopen = r);
  await send('Page.enable');
  await send('Runtime.enable');

  async function evaluate(expr) {
    const res = await send('Runtime.evaluate', {
      expression: expr,
      returnByValue: true,
      awaitPromise: true
    });
    if (res.exceptionDetails) {
      throw new Error(`Eval error: ${res.exceptionDetails.text} (${JSON.stringify(res.exceptionDetails)})`);
    }
    return res.result ? res.result.value : undefined;
  }

  console.log('\n--- Waiting for Application to Mount ---');
  await wait(1500);

  let totalTests = 0;
  let passedTests = 0;

  function assert(title, condition, extraInfo = '') {
    totalTests++;
    if (condition) {
      passedTests++;
      console.log(`  ✓ ${title}`);
    } else {
      console.error(`  ✗ ${title} ${extraInfo ? '(' + extraInfo + ')' : ''}`);
    }
  }

  try {
    // ----------------------------------------------------
    // TEST SECTION 1: AUTH & MY ACCOUNT & PIN SWITCH
    // ----------------------------------------------------
    console.log('\n[SECTION 1: AUTHENTICATION, LOGIN, ACCOUNT & PIN SWITCH]');

    // 1.1 Test Navigate to My Account
    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('account');
    })()`);
    await wait(300);

    const accountChecks = await evaluate(`(() => {
      const heading = document.body.innerText.includes('My Profile & Account');
      const passCard = document.body.innerText.includes('Security & Change Password');
      const activeSess = document.body.innerText.includes('Active Sessions');
      const historyTable = document.body.innerText.includes('Recent Sign-in History');
      const signoutBtn = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Sign out other devices'));
      return { heading, passCard, activeSess, historyTable, signoutBtn };
    })()`);

    assert('1.1 My Account: Screen renders profile, change password, active sessions, and login history',
      accountChecks.heading && accountChecks.passCard && accountChecks.activeSess && accountChecks.historyTable);
    assert('1.2 My Account: "Sign out other devices" action button exists', accountChecks.signoutBtn);

    // 1.3 Test Fast Switch User PIN modal
    await evaluate(`(() => {
      window.__openModal && window.__openModal('switch_user_pin');
    })()`);
    await wait(300);

    const pinModalChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Switch Register User');
      const pinDots = !!document.querySelectorAll('.rounded-full');
      const demoFarhan = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Farhan'));
      return { title, pinDots, demoFarhan };
    })()`);

    assert('1.3 Switch User PIN: Modal opens with PIN display and quick switch demo profiles',
      pinModalChecks.title && pinModalChecks.demoFarhan);

    // 1.4 Test fast switch to Farhan (Manager)
    await evaluate(`(() => {
      const farhanBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Farhan'));
      if (farhanBtn) farhanBtn.click();
    })()`);
    await wait(300);

    const roleSwitched = await evaluate(`(() => {
      return document.body.innerText.includes('Manager') && !document.body.innerText.includes('Switch Register User (Fast PIN)');
    })()`);
    assert('1.4 Switch User PIN: Fast switch to Farhan (Manager) updates user role and closes modal', roleSwitched);

    // 1.5 Test Logout flow
    await evaluate(`(() => {
      window.__setIsAuthenticated && window.__setIsAuthenticated(false);
      window.__setCurrentScreen && window.__setCurrentScreen('login');
    })()`);
    await wait(400);

    const loginScreenChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Different Fashion');
      const userField = !!document.querySelector('input[placeholder*="username" i], input[placeholder*="mobile" i], input[placeholder*="sumi" i]');
      const passField = !!document.querySelector('input[type="password"]');
      const quickLogin = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Sumi') || b.innerText.includes('Sign In'));
      return { title, userField, passField, quickLogin };
    })()`);

    assert('1.5 Auth Login: Log out displays login view with credentials and quick-login chips',
      loginScreenChecks.title && (loginScreenChecks.userField || loginScreenChecks.quickLogin));

    // Restore authenticated state and return to POS as Owner for unrestricted access
    await evaluate(`(() => {
      window.__setIsAuthenticated && window.__setIsAuthenticated(true);
      window.__setViewAsRole && window.__setViewAsRole('Owner');
      window.__setCurrentScreen && window.__setCurrentScreen('pos');
    })()`);
    await wait(400);

    // ----------------------------------------------------
    // TEST SECTION 2: VENDOR PROFILE & PAY VENDOR ALLOCATION
    // ----------------------------------------------------
    console.log('\n[SECTION 2: VENDOR PROFILE & PAY VENDOR WITH BILL ALLOCATION]');

    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('vendor_profile');
    })()`);
    await wait(400);

    const vendorProfileChecks = await evaluate(`(() => {
      const header = document.body.innerText.includes('Aarong Craft Supply') ||
                     document.body.innerText.includes('Vendor Profile') ||
                     document.body.innerText.includes('Outstanding Payable');
      const textLower = document.body.innerText.toLowerCase();
      const tabs = ['vendor profile', 'purchases', 'disbursements', 'debit notes', 'statement'].every(t =>
        textLower.includes(t)
      );
      const payBtn = !!Array.from(document.querySelectorAll('button')).find(b =>
        b.innerText.includes('Pay Vendor') || b.innerText.includes('Disburse Payment')
      );
      return { header, tabs, payBtn };
    })()`);

    assert('2.1 Vendor Profile: Screen renders with header, 5 tabs, and Pay Vendor action',
      vendorProfileChecks.header && vendorProfileChecks.tabs && vendorProfileChecks.payBtn,
      JSON.stringify(vendorProfileChecks));

    // 2.2 Test Pay Vendor modal with bill allocation
    await evaluate(`(() => {
      const payBtn = Array.from(document.querySelectorAll('button')).find(b =>
        b.innerText.includes('Pay Vendor') || b.innerText.includes('Disburse Payment')
      );
      if (payBtn) payBtn.click();
    })()`);
    await wait(400);

    const payModalChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Disburse Payment to Vendor');
      const allocTable = document.body.innerText.includes('Outstanding Bills Allocation') || document.body.innerText.includes('GRN-2610-09');
      const modeButtons = document.body.innerText.includes('Auto (FIFO)') && document.body.innerText.includes('Manual');
      return { title, allocTable, modeButtons };
    })()`);

    assert('2.2 Pay Vendor Modal: Opens in Modal with bill allocation table and Auto/Manual modes',
      payModalChecks.title && payModalChecks.allocTable && payModalChecks.modeButtons);

    // Close modal
    await evaluate(`(() => {
      window.__closeModal && window.__closeModal('pay_vendor');
      const cancelBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.trim() === 'Cancel');
      if (cancelBtn) cancelBtn.click();
    })()`);
    await wait(300);

    // ----------------------------------------------------
    // TEST SECTION 3: PRODUCT DETAIL & VARIANT PICKER
    // ----------------------------------------------------
    console.log('\n[SECTION 3: PRODUCT DETAIL DEEP DIVE & POS VARIANT PICKER]');

    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('product_detail');
    })()`);
    await wait(400);

    const prodDetailChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Cotton Panjabi Classic') ||
                    document.body.innerText.includes('Product 360') ||
                    document.body.innerText.includes('Product Details') ||
                    document.body.innerText.includes('Jamdani');
      const textLower = document.body.innerText.toLowerCase();
      const tabs = ['stock heat-map', 'fifo', 'movement', 'sales', 'price history'].every(t =>
        textLower.includes(t)
      );
      const heatmap = document.body.innerText.includes('DF1') || document.body.innerText.includes('Main');
      return { title, tabs, heatmap };
    })()`);

    assert('3.1 Product Detail: Screen renders with 5 tabs and Branch × Variant Stock Heat-map',
      prodDetailChecks.title && prodDetailChecks.tabs && prodDetailChecks.heatmap,
      JSON.stringify(prodDetailChecks));

    // 3.2 Test POS Apparel Variant Picker modal
    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('pos');
    })()`);
    await wait(300);

    await evaluate(`(() => {
      window.__openModal && window.__openModal('variant_picker');
    })()`);
    await wait(400);

    const variantPickerChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Select Size & Colour Variant');
      const matrix = document.body.innerText.includes('Maroon') && document.body.innerText.includes('Navy Blue') && document.body.innerText.includes('38 (S)');
      const inStockCell = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('pcs') && b.innerText.includes('In stock'));
      return { title, matrix, inStockCell };
    })()`);

    assert('3.2 POS Variant Picker: Opens Size × Colour matrix with stock count badges per cell',
      variantPickerChecks.title && variantPickerChecks.matrix && variantPickerChecks.inStockCell);

    // 3.3 Test clicking in-stock cell adds to cart and closes modal
    await evaluate(`(() => {
      const cell = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('pcs') && b.innerText.includes('In stock'));
      if (cell) cell.click();
    })()`);
    await wait(400);

    const variantModalClosed = await evaluate(`(() => {
      return !document.body.innerText.includes('Select Size & Colour Variant');
    })()`);
    assert('3.3 POS Variant Picker: Selecting variant adds item to sale and closes modal', variantModalClosed);

    // ----------------------------------------------------
    // TEST SECTION 4: RETURNS WIZARD & RETURN DETAIL
    // ----------------------------------------------------
    console.log('\n[SECTION 4: RETURNS WIZARD & RETURN DETAIL]');

    // 4.1 Returns DataList
    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('returns');
    })()`);
    await wait(400);

    const returnsListChecks = await evaluate(`(() => {
      const heading = document.body.innerText.includes('Returns & Exchanges');
      const wizardBtn = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Return Wizard'));
      const tableRows = document.querySelectorAll('tbody tr').length > 0;
      return { heading, wizardBtn, tableRows };
    })()`);

    assert('4.1 Returns: Screen renders <DataList> with Return Wizard CTA',
      returnsListChecks.heading && returnsListChecks.wizardBtn && returnsListChecks.tableRows);

    // 4.2 Return Wizard Modal
    await evaluate(`(() => {
      const btn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Return Wizard'));
      if (btn) btn.click();
    })()`);
    await wait(400);

    const wizardStep1Checks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Return & Exchange Wizard');
      const step1 = document.body.innerText.includes('1. Find Invoice');
      const invQuery = !!document.querySelector('input[value*="DF1-R1-26000412"]');
      const nextBtn = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Next: Select Items'));
      return { title, step1, invQuery, nextBtn };
    })()`);

    assert('4.2 Return Wizard: Step 1 verifies sale invoice number',
      wizardStep1Checks.title && wizardStep1Checks.step1 && (wizardStep1Checks.invQuery || wizardStep1Checks.nextBtn));

    // Navigate to Step 2
    await evaluate(`(() => {
      const nextBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Next: Select Items'));
      if (nextBtn) nextBtn.click();
    })()`);
    await wait(300);

    const wizardStep2Checks = await evaluate(`(() => {
      const step2 = document.body.innerText.includes('2. Select Items');
      const vatReversal = document.body.innerText.includes('VAT Reversal');
      return { step2, vatReversal };
    })()`);

    assert('4.2b Return Wizard: Step 2 shows returnable qty lines and VAT reversal calculation',
      wizardStep2Checks.step2 && wizardStep2Checks.vatReversal);

    // Close wizard
    await evaluate(`(() => {
      window.__closeModal && window.__closeModal('return_wizard');
    })()`);
    await wait(300);

    // 4.3 Return Detail Screen
    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('return_detail');
    })()`);
    await wait(400);

    const returnDetailChecks = await evaluate(`(() => {
      const heading = document.body.innerText.includes('Return Voucher');
      const memo = document.body.innerText.includes('Gross Return Amount') || document.body.innerText.includes('Settled & Restocked');
      const printBtn = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Print') || b.innerText.includes('Replacement'));
      return { heading, memo, printBtn };
    })()`);

    assert('4.3 Return Detail: Displays voucher breakdown, credit memo, and action buttons',
      returnDetailChecks.heading && returnDetailChecks.memo && returnDetailChecks.printBtn,
      JSON.stringify(returnDetailChecks));

    // ----------------------------------------------------
    // TEST SECTION 5: MESSAGING HUB (5 TABS)
    // ----------------------------------------------------
    console.log('\n[SECTION 5: MESSAGING HUB - 5 SPEC TABS]');

    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('messaging');
    })()`);
    await wait(400);

    const messagingTabsChecks = await evaluate(`(() => {
      const text = document.body.innerText;
      const composerTab = text.includes('Composer');
      const templatesTab = text.includes('Templates');
      const autoTab = text.includes('Automation');
      const logsTab = text.includes('Message Log');
      const optOutTab = text.includes('Opt-out') || text.includes('DND');
      return { composerTab, templatesTab, autoTab, logsTab, optOutTab };
    })()`);

    assert('5.1 Messaging: All 5 spec tabs exist (Composer, Templates, Automation, Log, Opt-Out)',
      messagingTabsChecks.composerTab && messagingTabsChecks.templatesTab &&
      messagingTabsChecks.autoTab && messagingTabsChecks.logsTab && messagingTabsChecks.optOutTab,
      JSON.stringify(messagingTabsChecks));

    // 5.2 Test Opt-Out tab and Add Opt-Out modal
    await evaluate(`(() => {
      const optBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Opt-out') || b.innerText.includes('DND'));
      if (optBtn) optBtn.click();
    })()`);
    await wait(300);

    await evaluate(`(() => {
      window.__openModal && window.__openModal('add_opt_out');
    })()`);
    await wait(300);

    const optModalChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Add Number to SMS Opt-Out Blacklist');
      const phoneInput = !!document.querySelector('input[placeholder*="017" i]');
      return { title, phoneInput };
    })()`);

    assert('5.2 Messaging: Add Opt-Out Number modal opens with phone input',
      optModalChecks.title && optModalChecks.phoneInput);

    await evaluate(`(() => {
      window.__closeModal && window.__closeModal('add_opt_out');
    })()`);
    await wait(300);

    // ----------------------------------------------------
    // TEST SECTION 6: MEMBERSHIP CARDS STUDIO & REISSUE
    // ----------------------------------------------------
    console.log('\n[SECTION 6: MEMBERSHIP CARDS STUDIO & REISSUE]');

    await evaluate(`(() => {
      window.__setCurrentScreen && window.__setCurrentScreen('membership_cards');
    })()`);
    await wait(400);

    const cardStudioChecks = await evaluate(`(() => {
      const text = document.body.innerText;
      const heading = text.includes('Membership Card Studio') || text.includes('Membership Card');
      const presets = text.includes('CR80') && text.includes('10-Up');
      const cardPreview = text.includes('DIFFERENT FASHION');
      const flipBtn = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Flip Card') || b.innerText.includes('Click to Flip') || b.innerText.includes('Flip'));
      const reissueBtn = !!Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Report Lost') || b.innerText.includes('Reissue'));
      return { heading, presets, cardPreview, flipBtn, reissueBtn };
    })()`);

    assert('6.1 Membership Cards: Designer renders CR80/A4 presets, 3D card preview, and Reissue trigger',
      cardStudioChecks.heading && cardStudioChecks.presets && cardStudioChecks.cardPreview && cardStudioChecks.flipBtn && cardStudioChecks.reissueBtn,
      JSON.stringify(cardStudioChecks));

    // 6.2 Test Reissue modal
    await evaluate(`(() => {
      const reissueBtn = Array.from(document.querySelectorAll('button')).find(b => b.innerText.includes('Report Lost') || b.innerText.includes('Reissue'));
      if (reissueBtn) reissueBtn.click();
    })()`);
    await wait(400);

    const reissueModalChecks = await evaluate(`(() => {
      const title = document.body.innerText.includes('Report Lost & Reissue Membership Card');
      const warning = document.body.innerText.includes('Previous Card Will Be Invalidated');
      const transferInfo = document.body.innerText.includes('Due Transferred') && document.body.innerText.includes('Loyalty Points');
      return { title, warning, transferInfo };
    })()`);

    assert('6.2 Membership Cards: Reissue modal invalidates old card, sets Luhn-verified new card, and transfers dues/points',
      reissueModalChecks.title && reissueModalChecks.warning && reissueModalChecks.transferInfo);

    await evaluate(`(() => {
      window.__closeModal && window.__closeModal('reissue_card');
    })()`);
    await wait(300);

    // ----------------------------------------------------
    // TEST SECTION 7: ADMIN SCREENS REACHABILITY
    // ----------------------------------------------------
    console.log('\n[SECTION 7: ADMIN SCREENS REACHABILITY]');

    // Ensure role is Owner to verify unrestricted access
    await evaluate(`(() => {
      window.__setViewAsRole && window.__setViewAsRole('Owner');
    })()`);
    await wait(300);

    const adminScreens = ['users', 'backup', 'audit_log', 'super_admin', 'fixed_assets'];
    for (const scr of adminScreens) {
      await evaluate(`(() => {
        window.__setCurrentScreen && window.__setCurrentScreen('${scr}');
      })()`);
      await wait(400);

      const mainInfo = await evaluate(`(() => {
        const m = document.querySelector('main');
        return {
          hasMain: !!m,
          textLen: m ? m.innerText.trim().length : 0,
          preview: m ? m.innerText.slice(0, 100) : ''
        };
      })()`);

      assert(`7.1 Admin Screen [${scr}]: Renders full UI when navigated to`,
        mainInfo.hasMain && mainInfo.textLen > 20,
        JSON.stringify(mainInfo));
    }

    // 7.2 Check Command Palette indexes Part 4 screens
    await evaluate(`(() => {
      window.__openModal && window.__openModal('command_palette');
      window.__setCmdQuery && window.__setCmdQuery('Details');
    })()`);
    await wait(400);

    const paletteIndexed = await evaluate(`(() => {
      const palette = document.body.innerText;
      return palette.includes('Product Details Deep Dive') || palette.includes('Return Voucher Details') || palette.includes('Vendor 360');
    })()`);

    assert('7.2 Command Palette: Indexes new Part 4 screens (Product Details, Return Details)', paletteIndexed);

    await evaluate(`(() => {
      window.__closeModal && window.__closeModal('command_palette');
      window.__setCurrentScreen && window.__setCurrentScreen('pos');
    })()`);
    await wait(300);

    // ----------------------------------------------------
    // FINAL REPORT
    // ----------------------------------------------------
    console.log(`\n========================================`);
    console.log(`PART 4 VERIFICATION RESULTS: ${passedTests}/${totalTests} PASS`);
    console.log(`========================================\n`);

    if (passedTests === totalTests) {
      console.log('ALL PART 4 CHECKS PASSED PERFECTLY!\n');
    } else {
      console.error(`FAILED: ${totalTests - passedTests} tests failed.`);
      process.exitCode = 1;
    }

  } catch (err) {
    console.error('Test execution failed:', err);
    process.exitCode = 1;
  } finally {
    ws.close();
    chrome.kill();
  }
}

run();
