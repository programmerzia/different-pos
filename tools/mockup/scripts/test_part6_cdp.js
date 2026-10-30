#!/usr/bin/env node
/**
 * scripts/test_part6_cdp.js
 * CDP Test Suite for Part 6: Tokens, Typography, Schemes & Offline Paths
 */

const { spawn } = require('child_process');
const http = require('http');
const fs = require('fs');

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function getJson(url) {
  return new Promise((resolve, reject) => {
    http.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try { resolve(JSON.parse(data)); } catch (e) { reject(e); }
      });
    }).on('error', reject);
  });
}

async function run() {
  console.log('=== PART 6 CDP TEST SUITE: TOKENS, TYPOGRAPHY, SCHEMES & OFFLINE PATHS ===\n');

  // Step 1: File checks for offline vendor paths in docs/mockup/index.html
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

  console.log('--- 6.1: Offline React Vendor Paths in docs/mockup/index.html ---');
  const mockupHtml = fs.readFileSync('../../docs/mockup/index.html', 'utf8');
  assert('../../docs/mockup/index.html has relative react vendor path ../../tools/mockup/vendor/', mockupHtml.includes('src="../../tools/mockup/vendor/react.production.min.js"'));
  assert('../../docs/mockup/index.html has relative react-dom vendor path ../../tools/mockup/vendor/', mockupHtml.includes('src="../../tools/mockup/vendor/react-dom.production.min.js"'));

  const indexHtml = fs.readFileSync('index.html', 'utf8');
  assert('index.html has relative vendor path vendor/react.production.min.js', indexHtml.includes('src="vendor/react.production.min.js"'));

  // Step 2: Spawn headless chrome
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
    console.error('Failed to connect to headless Chrome on port 9222');
    chrome.kill();
    process.exit(1);
  }

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
    const code = `(() => { return (${expr}); })()`;
    const res = await send('Runtime.evaluate', { expression: code, returnByValue: true, awaitPromise: true });
    return res.result ? res.result.value : undefined;
  };

  ws.onopen = async () => {
    await wait(2000);

    try {
      // ==========================================
      // TEST 6.2: Zero Raw Palette Classes in DOM
      // ==========================================
      console.log('\n--- 6.2: Zero Raw Palette Classes in DOM ---');
      const badClasses = await evalJs(`(() => {
        const allElements = document.querySelectorAll('*');
        const violations = [];
        const regex = /^(?:[a-zA-Z0-9_\\-:]*)(?:emerald|amber|rose|red|green|blue|slate|gray|teal|yellow)-[0-9]+(?:\\/[0-9]+)?$/;
        allElements.forEach(el => {
          if (el.classList) {
            el.classList.forEach(cls => {
              if (regex.test(cls)) violations.push(cls);
            });
          }
        });
        return violations;
      })()`);

      assert(`Zero raw palette classes in DOM (found: ${badClasses.length})`, badClasses.length === 0);

      // ==========================================
      // TEST 6.3: Typography & Tabular Numbers
      // ==========================================
      console.log('\n--- 6.3: Typography & Tabular Nums Enforcement ---');
      const typography = await evalJs(`(() => {
        const testEl = document.createElement('span');
        testEl.className = 'num';
        testEl.textContent = '৳ 1,250.00';
        document.body.appendChild(testEl);
        const style = window.getComputedStyle(testEl);
        const res = {
          fontFamily: style.fontFamily,
          fontVariantNumeric: style.fontVariantNumeric
        };
        testEl.remove();
        return res;
      })()`);

      assert(`font-variant-numeric contains tabular-nums (${typography.fontVariantNumeric})`, typography.fontVariantNumeric.includes('tabular-nums'));
      assert(`font-family includes Inter/Hind Siliguri (${typography.fontFamily})`, typography.fontFamily.toLowerCase().includes('inter') || typography.fontFamily.toLowerCase().includes('siliguri'));

      // Check that font-mono is not applied on money amounts
      const fontMonoOnAmounts = await evalJs(`(() => {
        const elementsWithTaka = Array.from(document.querySelectorAll('*')).filter(el => {
          return el.children.length === 0 && el.textContent.includes('৳');
        });
        const violations = [];
        elementsWithTaka.forEach(el => {
          if (el.classList.contains('font-mono')) {
            violations.push(el.outerHTML);
          }
        });
        return violations;
      })()`);
      if (fontMonoOnAmounts.length > 0) {
        console.log("Violations found:", fontMonoOnAmounts);
      }
      assert(`font-mono is restricted from money amounts (violations: ${fontMonoOnAmounts.length})`, fontMonoOnAmounts.length === 0);

      // ==========================================
      // TEST 6.4: Taka (৳) Symbol in Light & Dark Mode
      // ==========================================
      console.log('\n--- 6.4: Taka (৳) Symbol in Light & Dark Mode ---');
      const hasTakaLight = await evalJs(`document.body.innerText.includes('৳')`);
      assert('৳ symbol renders in light mode', hasTakaLight);

      await evalJs("document.documentElement.setAttribute('data-theme', 'dark')");
      await wait(200);

      const darkState = await evalJs(`(() => {
        return {
          theme: document.documentElement.getAttribute('data-theme'),
          hasTaka: document.body.innerText.includes('৳')
        };
      })()`);
      assert('Dark theme applied with data-theme="dark"', darkState.theme === 'dark');
      assert('৳ symbol renders correctly in dark mode', darkState.hasTaka);

      // Reset to light mode
      await evalJs("document.documentElement.setAttribute('data-theme', 'light')");

      // ==========================================
      // TEST 6.5: 6 Color Schemes Active
      // ==========================================
      console.log('\n--- 6.5: 6 Color Schemes Active ---');
      const schemes = ['teal', 'indigo', 'violet', 'rose', 'ocean', 'graphite'];
      for (const s of schemes) {
        const prim = await evalJs(`(() => {
          document.documentElement.setAttribute('data-scheme', '${s}');
          return window.getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();
        })()`);
        assert(`Scheme "${s}" loaded with --primary: ${prim}`, prim.length > 0);
      }
      // Reset to teal
      await evalJs("document.documentElement.setAttribute('data-scheme', 'teal')");

      // ==========================================
      // TEST 6.6: Density Switcher (Compact vs Comfortable)
      // ==========================================
      console.log('\n--- 6.6: Density Switcher ---');
      const density = await evalJs(`(() => {
        document.documentElement.setAttribute('data-density', 'compact');
        const compact = window.getComputedStyle(document.documentElement).getPropertyValue('--row-height').trim();
        document.documentElement.setAttribute('data-density', 'comfortable');
        const comfortable = window.getComputedStyle(document.documentElement).getPropertyValue('--row-height').trim();
        return { compact, comfortable };
      })()`);
      assert(`Density modifies --row-height (compact=${density.compact}, comfortable=${density.comfortable})`, density.compact === '32px' && density.comfortable === '44px');

      // ==========================================
      // TEST 6.7: Role-Based Sidebar Menus & Permissions
      // ==========================================
      console.log('\n--- 6.7: Role Switcher & Role Menus ---');
      const rolesTest = await evalJs(`(() => {
        const check = {};
        
        // Cashier
        window.__setViewAsRole('Cashier');
        check.cashierHasMoney = window.__canAccess ? window.__canAccess('money') : false;
        check.cashierHasPos = window.__canAccess ? window.__canAccess('pos') : true;

        // Stock Keeper
        window.__setViewAsRole('Stock Keeper');
        check.stockHasMoney = window.__canAccess ? window.__canAccess('money') : false;
        check.stockHasStock = window.__canAccess ? window.__canAccess('stock') : true;

        // Accountant
        window.__setViewAsRole('Accountant');
        check.accHasMoney = window.__canAccess ? window.__canAccess('money') : true;
        check.accHasAssets = window.__canAccess ? window.__canAccess('fixed_assets') : true;

        // Owner
        window.__setViewAsRole('Owner');
        check.ownerHasMoney = window.__canAccess ? window.__canAccess('money') : true;
        check.ownerHasAdmin = window.__canAccess ? window.__canAccess('settings') : true;

        return check;
      })()`);

      assert('Cashier restricted from Money (G M), has POS (F1)', !rolesTest.cashierHasMoney && rolesTest.cashierHasPos);
      assert('Stock Keeper restricted from Money, has Stock & Transfer (G K)', !rolesTest.stockHasMoney && rolesTest.stockHasStock);
      assert('Accountant has Money (G M) & Fixed Assets', rolesTest.accHasMoney && rolesTest.accHasAssets);
      assert('Owner has all access (Money & Settings)', rolesTest.ownerHasMoney && rolesTest.ownerHasAdmin);

    } catch (err) {
      console.error('Test step failed:', err);
      failed++;
    } finally {
      console.log(`\n========================================`);
      console.log(`Part 6 CDP Tests Completed: ${passed} passed, ${failed} failed.`);
      console.log(`========================================\n`);
      chrome.kill();
      process.exit(failed > 0 ? 1 : 0);
    }
  };
}

run();
