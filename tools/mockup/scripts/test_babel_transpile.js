const fs = require('fs');
const https = require('https');

function fetchWithRedirect(url, cb) {
  https.get(url, (res) => {
    if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
      let redirectUrl = res.headers.location;
      if (!redirectUrl.startsWith('http')) {
        redirectUrl = 'https://unpkg.com' + redirectUrl;
      }
      return fetchWithRedirect(redirectUrl, cb);
    }
    let data = '';
    res.on('data', chunk => data += chunk);
    res.on('end', () => cb(data));
  }).on('error', err => console.error("Fetch error:", err));
}

console.log("Fetching Babel standalone...");
fetchWithRedirect('https://unpkg.com/@babel/standalone/babel.min.js', (babelSource) => {
  console.log("Babel loaded (size:", babelSource.length, "bytes).");
  
  const vm = require('vm');
  const context = { window: {}, console: console };
  vm.createContext(context);
  vm.runInContext(babelSource, context);
  const Babel = context.Babel || context.window.Babel;

  if (!Babel) {
    console.error("Failed to initialize Babel!");
    process.exit(1);
  }

  console.log("Reading index.html...");
  const html = fs.readFileSync('index.html', 'utf-8');
  const scriptMatch = html.match(/<script type="text\/babel">([\s\S]*?)<\/script>/);

  if (!scriptMatch) {
    console.error("No <script type='text/babel'> found in index.html!");
    process.exit(1);
  }

  const code = scriptMatch[1];
  console.log("Found JSX code length:", code.length, "chars. Transpiling...");

  try {
    const result = Babel.transform(code, {
      presets: ['react'],
      plugins: []
    });
    console.log("Transpilation SUCCESS! Generated JS length:", result.code.length, "bytes");
    console.log("ALL JSX SYNTAX VALID AND CLEAN!");
    process.exit(0);
  } catch (err) {
    console.error("Transpilation ERROR:", err.message);
    if (err.loc) {
      console.error(`Error at line ${err.loc.line}, column ${err.loc.column}`);
      const lines = code.split('\n');
      const start = Math.max(0, err.loc.line - 5);
      const end = Math.min(lines.length, err.loc.line + 5);
      for (let i = start; i < end; i++) {
        console.error(`${i + 1}: ${lines[i]}`);
      }
    }
    process.exit(1);
  }
});
