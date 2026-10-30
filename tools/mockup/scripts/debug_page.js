// scripts/debug_page.js
const { spawn } = require('child_process');
const http = require('http');

async function wait(ms) { return new Promise(r => setTimeout(r, ms)); }

async function run() {
  const chrome = spawn('google-chrome-stable', [
    '--headless=new',
    '--remote-debugging-port=9222',
    '--window-size=1440,900',
    '--user-data-dir=/tmp/pos-debug-' + Date.now(),
    '--no-sandbox',
    '--disable-gpu',
    'http://127.0.0.1:8080/'
  ]);

  await wait(1000);
  const targets = await new Promise((resolve) => {
    http.get('http://127.0.0.1:9222/json', (res) => {
      let buf = '';
      res.on('data', c => buf += c);
      res.on('end', () => resolve(JSON.parse(buf)));
    });
  });
  const page = targets.find(t => t.type === 'page' && t.url.includes('8080'));
  console.log('Target:', page?.url);

  const ws = new WebSocket(page.webSocketDebuggerUrl);
  await new Promise(r => ws.onopen = r);

  ws.send(JSON.stringify({ id: 1, method: 'Runtime.enable' }));

  ws.onmessage = (event) => {
    const data = JSON.parse(event.data);
    if (data.method === 'Runtime.consoleAPICalled') {
      console.log('CONSOLE:', data.params.type, data.params.args.map(a => a.value || a.description).join(' '));
    }
    if (data.method === 'Runtime.exceptionThrown') {
      console.error('EXCEPTION:', data.params.exceptionDetails.text, data.params.exceptionDetails.exception?.description);
    }
    if (data.id === 2) {
      console.log('Body Text snippet:', (data.result.result?.value || '').slice(0, 300));
    }
    if (data.id === 3) {
      console.log('Scan input exists:', data.result.result?.value);
      ws.close();
      chrome.kill();
      process.exit(0);
    }
  };

  await wait(1500);
  ws.send(JSON.stringify({ id: 2, method: 'Runtime.evaluate', params: { expression: 'document.body.innerText' } }));
  ws.send(JSON.stringify({ id: 3, method: 'Runtime.evaluate', params: { expression: '!!document.getElementById("pos-barcode-input")' } }));
}

run();
