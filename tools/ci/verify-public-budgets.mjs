import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = new URL('../../', import.meta.url).pathname.replace(/^\/(.:\/)/, '$1');
const limits = {
  backendJs: 600 * 1024,
  backendCss: 32 * 1024,
  websiteStaticTotal: 800 * 1024,
  websiteLargestChunk: 260 * 1024,
};

function filesUnder(dir) {
  const out = [];
  for (const entry of readdirSync(dir, { withFileTypes: true })) {
    const path = join(dir, entry.name);
    if (entry.isDirectory()) out.push(...filesUnder(path));
    else if (entry.isFile()) out.push(path);
  }
  return out;
}

function assertWithin(name, value, limit) {
  console.log(name + '=' + value + ' limit=' + limit);
  if (value > limit) throw new Error(name + ' budget exceeded: ' + value + ' > ' + limit);
}

const backendAssets = filesUnder(join(root, 'backend', 'public', 'build', 'assets'));
const backendJs = Math.max(...backendAssets.filter(file => file.endsWith('.js')).map(file => statSync(file).size));
const backendCss = Math.max(...backendAssets.filter(file => file.endsWith('.css')).map(file => statSync(file).size));
assertWithin('backend_largest_js_bytes', backendJs, limits.backendJs);
assertWithin('backend_largest_css_bytes', backendCss, limits.backendCss);

const websiteStatic = filesUnder(join(root, 'website', '.next', 'static'));
const websiteTotal = websiteStatic.reduce((sum, file) => sum + statSync(file).size, 0);
const websiteLargest = Math.max(...websiteStatic.map(file => statSync(file).size));
assertWithin('website_static_total_bytes', websiteTotal, limits.websiteStaticTotal);
assertWithin('website_largest_chunk_bytes', websiteLargest, limits.websiteLargestChunk);

const forbidden = [
  'DB_PASSWORD=',
  'GOOGLE_GMAIL_CLIENT_SECRET=',
  'GOOGLE_DRIVE_CLIENT_SECRET=',
  'AWS_SECRET_ACCESS_KEY=',
  'mobisttech-ci',
];
const publicFiles = [...backendAssets, ...websiteStatic].filter(file => /\.(js|css|json|txt|html|map)$/i.test(file));
for (const file of publicFiles) {
  const content = readFileSync(file, 'utf8');
  for (const marker of forbidden) {
    if (content.includes(marker)) {
      throw new Error('Forbidden secret marker in public build: ' + relative(root, file) + ' -> ' + marker);
    }
  }
}

console.log('PUBLIC_BUDGETS=PASS');
