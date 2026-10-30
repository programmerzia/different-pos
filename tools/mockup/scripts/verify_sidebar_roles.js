#!/usr/bin/env node
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
        try { resolve(JSON.parse(data)); } catch (e) { reject(e); }
      });
    }).on('error', reject);
  });
}

async function run() {
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
    await wait(1500);

    // Test Cashier
    await evalJs("window.__setViewAsRole('Cashier')");
    await wait(200);
    const cashierButtons = await evalJs("Array.from(document.querySelectorAll('aside button')).map(b => b.textContent.replace(/\\s+/g, ' ').trim()).filter(t => t && !t.includes('Shortcuts'))");
    console.log('Cashier buttons:', cashierButtons);

    // Test Stock Keeper
    await evalJs("window.__setViewAsRole('Stock Keeper')");
    await wait(200);
    const stockButtons = await evalJs("Array.from(document.querySelectorAll('aside button')).map(b => b.textContent.replace(/\\s+/g, ' ').trim()).filter(t => t && !t.includes('Shortcuts'))");
    console.log('Stock Keeper buttons:', stockButtons);

    // Test Accountant
    await evalJs("window.__setViewAsRole('Accountant')");
    await wait(200);
    const accButtons = await evalJs("Array.from(document.querySelectorAll('aside button')).map(b => b.textContent.replace(/\\s+/g, ' ').trim()).filter(t => t && !t.includes('Shortcuts'))");
    console.log('Accountant buttons:', accButtons);

    ws.close();
    chrome.kill();
    process.exit(0);
  };
}

run();
