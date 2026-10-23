const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const http = require('http');

const CHROME_PATH = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const DEBUG_PORT = 9222;
const BASE_URL = 'http://127.0.0.1:8088';
const OUTPUT_DIR = path.join(__dirname, '..', 'playstore-assets');

if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

async function loginAndGetCookie() {
  const payload = JSON.stringify({
    email: 'reviewer@jodrod.app',
    password: 'Reviewer2026!'
  });

  return new Promise((resolve, reject) => {
    const req = http.request(`${BASE_URL}/api/auth.php?action=login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload)
      }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        const cookies = res.headers['set-cookie'] || [];
        for (const c of cookies) {
          const match = c.match(/PHPSESSID=([^;]+)/);
          if (match) {
            return resolve(match[1]);
          }
        }
        reject(new Error('Failed to obtain PHPSESSID: ' + data));
      });
    });
    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

function launchChrome() {
  const tmpDir = path.join('/tmp', 'chrome_playstore_' + Date.now());
  const args = [
    '--headless=new',
    `--remote-debugging-port=${DEBUG_PORT}`,
    `--user-data-dir=${tmpDir}`,
    '--disable-gpu',
    '--no-sandbox',
    '--hide-scrollbars',
    'about:blank'
  ];
  const proc = spawn(CHROME_PATH, args, { stdio: 'ignore' });
  return { proc, tmpDir };
}

function wait(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function getPageWsUrl() {
  for (let i = 0; i < 40; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${DEBUG_PORT}/json/list`);
      if (res.ok) {
        const list = await res.json();
        const page = list.find(item => item.type === 'page' && item.webSocketDebuggerUrl);
        if (page) {
          return page.webSocketDebuggerUrl;
        }
      }
    } catch {
      // retry
    }
    await wait(250);
  }
  throw new Error('Chrome page target not found');
}

class CDPClient {
  constructor(ws) {
    this.ws = ws;
    this.id = 1;
    this.callbacks = new Map();
    ws.onmessage = (event) => {
      const data = JSON.parse(event.data);
      if (data.id && this.callbacks.has(data.id)) {
        const { resolve, reject } = this.callbacks.get(data.id);
        this.callbacks.delete(data.id);
        if (data.error) reject(new Error(data.error.message));
        else resolve(data.result);
      }
    };
  }

  send(method, params = {}) {
    return new Promise((resolve, reject) => {
      const id = this.id++;
      this.callbacks.set(id, { resolve, reject });
      this.ws.send(JSON.stringify({ id, method, params }));
    });
  }
}

async function captureScreen(client, { route, width, height, isMobile, filename, waitMs = 3500, script = null }) {
  console.log(`Capturing: ${filename} (${width}x${height})...`);
  await client.send('Emulation.setDeviceMetricsOverride', {
    width,
    height,
    deviceScaleFactor: 1,
    mobile: isMobile
  });

  await client.send('Page.navigate', { url: `${BASE_URL}/${route}` });
  await wait(waitMs);

  if (script) {
    await client.send('Runtime.evaluate', { expression: script });
    await wait(800);
  }

  const { data } = await client.send('Page.captureScreenshot', { format: 'png' });
  const filePath = path.join(OUTPUT_DIR, filename);
  fs.writeFileSync(filePath, Buffer.from(data, 'base64'));
  console.log(`✓ Saved: ${filePath}`);
}

async function main() {
  console.log('1. Authenticating test user with backend...');
  const sessionId = await loginAndGetCookie();
  console.log(`   Session cookie: ${sessionId}`);

  console.log('2. Launching headless Chrome...');
  const { proc } = launchChrome();

  try {
    const wsUrl = await getPageWsUrl();
    console.log(`   Connected to Page CDP WebSocket`);
    const ws = new WebSocket(wsUrl);
    await new Promise((res, rej) => {
      ws.onopen = res;
      ws.onerror = rej;
    });

    const client = new CDPClient(ws);
    await client.send('Page.enable');
    await client.send('Network.enable');

    // Inject session cookie
    await client.send('Network.setCookie', {
      name: 'PHPSESSID',
      value: sessionId,
      domain: '127.0.0.1',
      path: '/'
    });

    // 1. Phone Screenshot 1: Interactive Map (1080 x 1920 - Exact 9:16)
    await captureScreen(client, {
      route: '#map',
      width: 1080,
      height: 1920,
      isMobile: true,
      filename: 'screenshot-1-map.png',
      waitMs: 4000,
      script: `
        if (window.jodrodMap) {
          window.jodrodMap.invalidateSize();
        }
      `
    });

    // 2. Phone Screenshot 2: My Parking Lots (1080 x 1920 - Exact 9:16)
    await captureScreen(client, {
      route: '#my-spaces',
      width: 1080,
      height: 1920,
      isMobile: true,
      filename: 'screenshot-2-parking-lots.png',
      waitMs: 3500,
      script: `
        const link = document.getElementById('nav-my-spaces');
        if (link) link.click();
      `
    });

    // 3. Phone Screenshot 3: 3D Vehicles (1080 x 1920 - Exact 9:16)
    await captureScreen(client, {
      route: '#vehicles',
      width: 1080,
      height: 1920,
      isMobile: true,
      filename: 'screenshot-3-vehicles.png',
      waitMs: 4000,
      script: `
        const link = document.getElementById('nav-vehicles');
        if (link) link.click();
      `
    });

    // 4. Phone Screenshot 4: Profile & Points (1080 x 1920 - Exact 9:16)
    await captureScreen(client, {
      route: '#profile',
      width: 1080,
      height: 1920,
      isMobile: true,
      filename: 'screenshot-4-profile.png',
      waitMs: 3500,
      script: `
        const link = document.getElementById('nav-profile');
        if (link) link.click();
      `
    });

    // 5. Feature Graphic for Play Store: Exactly 1024 x 500 px
    await captureScreen(client, {
      route: '#map',
      width: 1024,
      height: 500,
      isMobile: false,
      filename: 'feature-graphic.png',
      waitMs: 4000,
      script: `
        if (window.jodrodMap) {
          window.jodrodMap.invalidateSize();
        }
      `
    });

    // Copy 512x512 App Icon to output directory
    const srcIcon = path.join(__dirname, '..', 'mobile', 'assets', 'playstore-icon-512.png');
    const dstIcon = path.join(OUTPUT_DIR, 'app-icon-512.png');
    if (fs.existsSync(srcIcon)) {
      fs.copyFileSync(srcIcon, dstIcon);
      console.log(`✓ Copied: ${dstIcon}`);
    }

    console.log('\n======================================================');
    console.log('🎉 All Play Store visual assets generated successfully!');
    console.log(`Directory: ${OUTPUT_DIR}`);
    console.log('======================================================');
  } finally {
    proc.kill();
  }
}

main().catch(err => {
  console.error('Error generating assets:', err);
  process.exit(1);
});
