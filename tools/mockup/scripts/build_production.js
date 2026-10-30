const fs = require('fs');
const https = require('https');
const vm = require('vm');

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

console.log("Loading Babel standalone...");
const babelCache = 'vendor/babel.min.js';

function proceedWithBabel(babelSource) {
  console.log("Babel loaded (size:", babelSource.length, "bytes). Initializing VM context...");
  const context = { window: {}, console: console };
  vm.createContext(context);
  vm.runInContext(babelSource, context);
  const Babel = context.Babel || context.window.Babel;

  if (!Babel) {
    console.error("Failed to initialize Babel!");
    process.exit(1);
  }

  console.log("Reading raw index.html...");
  const html = fs.readFileSync('index.html', 'utf-8');
  const scriptMatch = html.match(/<script type="text\/babel">([\s\S]*?)<\/script>/);

  if (!scriptMatch) {
    console.error("No <script type='text/babel'> found in index.html!");
    process.exit(1);
  }

  const jsxCode = scriptMatch[1];
  console.log("Transpiling", jsxCode.length, "chars of JSX to vanilla JS...");

  try {
    const result = Babel.transform(jsxCode, {
      presets: [['react', { runtime: 'classic' }]],
      plugins: []
    });

    console.log("Transpilation SUCCESS! JS size:", result.code.length, "bytes");

    // Replace the Babel CDN script tag with a direct local + CDN fallback
    let compiledHtml = html.replace(
      '<script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>',
      '<!-- Pre-transpiled to standard vanilla JavaScript (No Babel runtime required!) -->'
    );

    // Replace React imports with local + CDN fallback
    compiledHtml = compiledHtml.replace(
      `<script src="https://unpkg.com/react@18.2.0/umd/react.production.min.js"></script>`,
      `<script src="vendor/react.production.min.js"></script>
  <script>if (!window.React) document.write('<script src="https://unpkg.com/react@18.2.0/umd/react.production.min.js"><\\/script>')</script>`
    );

    compiledHtml = compiledHtml.replace(
      `<script src="https://unpkg.com/react-dom@18.2.0/umd/react-dom.production.min.js"></script>`,
      `<script src="vendor/react-dom.production.min.js"></script>
  <script>if (!window.ReactDOM) document.write('<script src="https://unpkg.com/react-dom@18.2.0/umd/react-dom.production.min.js"><\\/script>')</script>`
    );

    // Replace <script type="text/babel"> with standard <script>
    compiledHtml = compiledHtml.replace(
      /<script type="text\/babel">[\s\S]*?<\/script>/,
      '<script>\n' + result.code + '\n</script>'
    );

    fs.writeFileSync('index.html', compiledHtml);

    // Fix offline React vendor paths for docs/mockup/index.html (relative to root)
    const mockupHtml = compiledHtml
      .replace('src="vendor/react.production.min.js"', 'src="../../tools/mockup/vendor/react.production.min.js"')
      .replace('src="vendor/react-dom.production.min.js"', 'src="../../tools/mockup/vendor/react-dom.production.min.js"');
    fs.writeFileSync('../../docs/mockup/index.html', mockupHtml);

    console.log("Master production build SUCCESS! Saved to index.html and docs/mockup/index.html");
    process.exit(0);
  } catch (err) {
    console.error("Transpilation ERROR:", err);
    process.exit(1);
  }
}

if (fs.existsSync(babelCache)) {
  console.log("Using cached babel.min.js from", babelCache);
  proceedWithBabel(fs.readFileSync(babelCache, 'utf8'));
} else {
  console.log("Downloading babel.min.js...");
  fetchWithRedirect('https://unpkg.com/@babel/standalone/babel.min.js', (source) => {
    fs.writeFileSync(babelCache, source);
    proceedWithBabel(source);
  });
}
