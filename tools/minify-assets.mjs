import fs from 'node:fs/promises';
import path from 'node:path';

const ROOT = process.cwd();
const ASSETS = [
  ['css/style.css', 'css/style.min.css', 'css'],
  ['css/responsive.css', 'css/responsive.min.css', 'css'],
  ['css/cms-pages.css', 'css/cms-pages.min.css', 'css'],
  ['css/cms-public.css', 'css/cms-public.min.css', 'css'],
  ['js/main.js', 'js/main.min.js', 'js'],
];

function stripComments(input, { keepImportant = false } = {}) {
  let output = '';
  let quote = null;
  let templateDepth = 0;

  for (let i = 0; i < input.length; i += 1) {
    const char = input[i];
    const next = input[i + 1];

    if (quote) {
      output += char;
      if (char === '\\') {
        output += input[i + 1] ?? '';
        i += 1;
      } else if (char === quote) {
        quote = null;
      } else if (quote === '`' && char === '$' && next === '{') {
        templateDepth += 1;
      } else if (quote === '`' && char === '}' && templateDepth > 0) {
        templateDepth -= 1;
      }
      continue;
    }

    if (char === '"' || char === "'" || char === '`') {
      quote = char;
      output += char;
      continue;
    }

    if (char === '/' && next === '*') {
      const end = input.indexOf('*/', i + 2);
      const comment = input.slice(i, end === -1 ? input.length : end + 2);
      if (keepImportant && comment.startsWith('/*!')) {
        output += comment;
      }
      i = end === -1 ? input.length : end + 1;
      continue;
    }

    if (char === '/' && next === '/') {
      const end = input.indexOf('\n', i + 2);
      i = end === -1 ? input.length : end;
      output += '\n';
      continue;
    }

    output += char;
  }

  return output;
}

function minifyCss(input) {
  return stripComments(input)
    .replace(/\s+/g, ' ')
    .replace(/\s*([{}:;,>+~])\s*/g, '$1')
    .replace(/;}/g, '}')
    .replace(/\s*!important/g, '!important')
    .trim();
}

function minifyJs(input) {
  return stripComments(input, { keepImportant: true })
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean)
    .join('\n');
}

function formatBytes(bytes) {
  return `${(bytes / 1024).toFixed(1)} KiB`;
}

async function minifyAsset([source, target, type]) {
  const sourcePath = path.join(ROOT, source);
  const targetPath = path.join(ROOT, target);
  const input = await fs.readFile(sourcePath, 'utf8');
  const output = type === 'css' ? minifyCss(input) : minifyJs(input);

  await fs.writeFile(targetPath, `${output}\n`);

  const before = Buffer.byteLength(input);
  const after = Buffer.byteLength(output);
  const saved = before - after;

  return { source, target, before, after, saved };
}

const results = await Promise.all(ASSETS.map(minifyAsset));

for (const result of results) {
  console.log(
    `${result.target}: ${formatBytes(result.before)} -> ${formatBytes(result.after)} saved ${formatBytes(result.saved)}`,
  );
}
