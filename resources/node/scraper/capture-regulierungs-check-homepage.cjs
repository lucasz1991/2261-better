#!/usr/bin/env node

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const projectRoot = path.resolve(__dirname, '../../..');

function loadEnvFile(filePath) {
  if (!fs.existsSync(filePath)) {
    return {};
  }

  const env = {};
  const lines = fs.readFileSync(filePath, 'utf8').split(/\r?\n/);

  for (const line of lines) {
    const trimmedLine = line.trim();

    if (!trimmedLine || trimmedLine.startsWith('#')) {
      continue;
    }

    const match = trimmedLine.match(/^([A-Za-z_][A-Za-z0-9_]*)=(.*)$/);

    if (!match) {
      continue;
    }

    let value = match[2].trim();

    if (
      (value.startsWith('"') && value.endsWith('"'))
      || (value.startsWith("'") && value.endsWith("'"))
    ) {
      value = value.slice(1, -1);
    }

    env[match[1]] = value;
  }

  return env;
}

function parseArgs(rawArgs) {
  const args = {
    positional: [],
  };

  for (const rawArg of rawArgs) {
    if (rawArg === '--help' || rawArg === '-h') {
      args.help = true;
      continue;
    }

    if (rawArg === '--headful') {
      args.headful = true;
      continue;
    }

    if (rawArg === '--no-full-page') {
      args.fullPage = false;
      continue;
    }

    const match = rawArg.match(/^--([^=]+)=(.*)$/);

    if (match) {
      args[match[1]] = match[2];
      continue;
    }

    args.positional.push(rawArg);
  }

  return args;
}

function printUsage() {
  process.stdout.write([
    'Usage:',
    '  npm run capture:homepage',
    '  node resources/node/scraper/capture-regulierungs-check-homepage.cjs [url] [outputPath]',
    '',
    'Options:',
    '  --url=http://127.0.0.1:9000',
    '  --output=storage/app/public/screenshots/regulierungs-check/homepage.png',
    '  --viewport=1440x1000',
    '  --executable=C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    '  --headful',
    '  --no-full-page',
    '',
  ].join('\n'));
}

function ensureDirectory(directoryPath) {
  fs.mkdirSync(directoryPath, { recursive: true });
  return directoryPath;
}

function resolveBrowserProfilePath() {
  const profileRoot = ensureDirectory(path.join(projectRoot, 'storage/app/temp/puppeteer-profiles'));
  const profileName = `profile-${process.pid}-${buildTimestamp()}`;

  return ensureDirectory(path.join(profileRoot, profileName));
}

function normalizeUrl(value) {
  const url = String(value || '').trim();

  if (!url) {
    return 'http://127.0.0.1:9000';
  }

  if (/^https?:\/\//i.test(url)) {
    return url;
  }

  return `http://${url}`;
}

function buildTimestamp() {
  return new Date().toISOString().replace(/[:.]/g, '-');
}

function resolveOutputPath(rawOutputPath) {
  const defaultFileName = `homepage-${buildTimestamp()}.png`;
  const fallbackPath = path.join(
    projectRoot,
    'storage/app/public/screenshots/regulierungs-check',
    defaultFileName,
  );

  if (!rawOutputPath) {
    return fallbackPath;
  }

  const resolvedPath = path.resolve(projectRoot, rawOutputPath);
  const extension = path.extname(resolvedPath).toLowerCase();

  if (extension === '.png') {
    return resolvedPath;
  }

  return path.join(resolvedPath, defaultFileName);
}

function parseViewport(value) {
  const match = String(value || '').match(/^(\d{3,5})x(\d{3,5})$/i);

  if (!match) {
    return {
      width: 1440,
      height: 1000,
    };
  }

  return {
    width: Math.max(320, Math.min(Number(match[1]), 7680)),
    height: Math.max(320, Math.min(Number(match[2]), 4320)),
  };
}

function resolveHeadlessMode(args) {
  if (args.headful) {
    return false;
  }

  const configuredValue = String(
    args.headless
    ?? process.env.REGULIERUNGS_CHECK_HEADLESS
    ?? process.env.PUPPETEER_HEADLESS
    ?? 'true',
  ).trim().toLowerCase();

  if (['0', 'false', 'no', 'off'].includes(configuredValue)) {
    return false;
  }

  return 'new';
}

function resolveBrowserExecutable(args) {
  const configuredPath = String(
    args.executable
    || args.executablePath
    || process.env.PUPPETEER_EXECUTABLE_PATH
    || process.env.CHROME_PATH
    || '',
  ).trim();

  const candidates = [];

  if (configuredPath) {
    candidates.push(configuredPath);
  }

  if (process.platform === 'win32') {
    const programFiles = [
      process.env.ProgramFiles,
      process.env['ProgramFiles(x86)'],
      'C:\\Program Files',
      'C:\\Program Files (x86)',
    ].filter(Boolean);

    for (const directory of programFiles) {
      candidates.push(path.join(directory, 'Google/Chrome/Application/chrome.exe'));
      candidates.push(path.join(directory, 'Microsoft/Edge/Application/msedge.exe'));
    }
  }

  if (process.platform === 'darwin') {
    candidates.push('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome');
    candidates.push('/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge');
  }

  candidates.push('/usr/bin/google-chrome');
  candidates.push('/usr/bin/chromium-browser');
  candidates.push('/usr/bin/chromium');
  candidates.push('/usr/bin/microsoft-edge');

  return candidates.find((candidate) => candidate && fs.existsSync(candidate)) || null;
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

function progressLog(stage, payload = {}) {
  process.stderr.write(`[REGULIERUNGS_CHECK_SCREENSHOT] ${JSON.stringify({
    stage,
    ...payload,
  })}\n`);
}

async function main() {
  const args = parseArgs(process.argv.slice(2));

  if (args.help) {
    printUsage();
    return;
  }

  const envFileValues = loadEnvFile(path.join(projectRoot, '.env'));
  const targetUrl = normalizeUrl(
    args.url
    || args.positional[0]
    || process.env.REGULIERUNGS_CHECK_URL
    || process.env.APP_URL
    || envFileValues.APP_URL
    || 'http://127.0.0.1:9000',
  );
  const screenshotPath = resolveOutputPath(
    args.output
    || args.positional[1]
    || process.env.REGULIERUNGS_CHECK_SCREENSHOT_PATH,
  );
  const viewport = parseViewport(args.viewport || process.env.REGULIERUNGS_CHECK_VIEWPORT);
  const fullPage = args.fullPage !== false;
  const navigationTimeoutMs = Math.max(
    5000,
    Number(args.timeout || process.env.REGULIERUNGS_CHECK_TIMEOUT_MS || 60000),
  );
  const settleDelayMs = Math.max(
    0,
    Number(args.settleMs || process.env.REGULIERUNGS_CHECK_SETTLE_MS || 750),
  );

  ensureDirectory(path.dirname(screenshotPath));

  let browser = null;

  try {
    const browserExecutable = resolveBrowserExecutable(args);

    progressLog('launch-browser', {
      headless: resolveHeadlessMode(args) !== false,
      viewport,
      executablePath: browserExecutable || 'puppeteer-managed',
    });

    const launchOptions = {
      headless: resolveHeadlessMode(args),
      acceptInsecureCerts: true,
      defaultViewport: viewport,
      userDataDir: resolveBrowserProfilePath(),
      pipe: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check',
        '--disable-background-networking',
        '--disable-component-update',
        '--disable-extensions',
        '--lang=de-DE,de;q=0.9',
      ],
    };

    if (browserExecutable) {
      launchOptions.executablePath = browserExecutable;
    }

    browser = await puppeteer.launch(launchOptions);

    const page = await browser.newPage();
    page.setDefaultTimeout(navigationTimeoutMs);
    page.setDefaultNavigationTimeout(navigationTimeoutMs);

    progressLog('open-homepage', {
      url: targetUrl,
    });

    await page.goto(targetUrl, {
      waitUntil: 'domcontentloaded',
      timeout: navigationTimeoutMs,
    });

    try {
      await page.waitForNetworkIdle({
        idleTime: 750,
        timeout: Math.min(15000, navigationTimeoutMs),
      });
    } catch (error) {
      progressLog('network-idle-timeout', {
        message: error.message,
      });
    }

    if (settleDelayMs > 0) {
      await sleep(settleDelayMs);
    }

    const title = await page.title();

    progressLog('capture-screenshot', {
      path: screenshotPath,
      fullPage,
    });

    await page.screenshot({
      path: screenshotPath,
      fullPage,
    });

    const result = {
      ok: true,
      capturedAt: new Date().toISOString(),
      url: targetUrl,
      finalUrl: page.url(),
      title,
      screenshotPath: path.relative(projectRoot, screenshotPath).replace(/\\/g, '/'),
      absoluteScreenshotPath: screenshotPath,
      viewport,
      fullPage,
    };

    process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
  } catch (error) {
    const result = {
      ok: false,
      capturedAt: new Date().toISOString(),
      url: targetUrl,
      screenshotPath: path.relative(projectRoot, screenshotPath).replace(/\\/g, '/'),
      absoluteScreenshotPath: screenshotPath,
      error: error.message,
    };

    process.stdout.write(`${JSON.stringify(result, null, 2)}\n`);
    process.exitCode = 1;
  } finally {
    if (browser) {
      await browser.close();
    }
  }
}

main();
