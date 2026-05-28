import fs from 'node:fs/promises';
import path from 'node:path';
import sharp from 'sharp';

const ROOT = process.cwd();
const IMAGES_DIR = path.join(ROOT, 'assets', 'images');
const TEXT_DIRS = [
  path.join(ROOT, 'css'),
  path.join(ROOT, 'js'),
  path.join(ROOT, 'pages'),
];
const TEXT_FILES = [
  path.join(ROOT, 'index.html'),
  path.join(ROOT, '404.html'),
  path.join(ROOT, '.htaccess'),
  path.join(ROOT, 'robots.txt'),
  path.join(ROOT, 'sitemap.xml'),
];

const TARGET_BYTES = 150 * 1024;
const QUALITY_STEPS = [84, 78, 72, 66, 60, 54, 48, 42, 36];
const SCALE_STEPS = [1, 0.9, 0.8, 0.7, 0.6, 0.5, 0.4, 0.33, 0.25];
const RASTER_EXTS = new Set(['.jpg', '.jpeg', '.png', '.webp']);
const TEXT_EXTS = new Set(['.html', '.css', '.js', '.xml', '.txt', '.php']);
const KEEP_FORMAT_NAMES = new Set([
  'favicon.png',
  'favicon.jpg',
  'a1technovation-dark-background.png',
  'a1technovation-light-background.png',
]);

function toPosix(filePath) {
  return filePath.split(path.sep).join('/');
}

function encodeAssetPath(assetPath) {
  return assetPath
    .split('/')
    .map((segment) => encodeURIComponent(segment))
    .join('/');
}

async function listFiles(dir) {
  const entries = await fs.readdir(dir, { withFileTypes: true });
  const files = [];

  for (const entry of entries) {
    const fullPath = path.join(dir, entry.name);
    if (entry.isDirectory()) {
      files.push(...await listFiles(fullPath));
      continue;
    }
    files.push(fullPath);
  }

  return files;
}

function getTargetExt(fileName) {
  return KEEP_FORMAT_NAMES.has(fileName.toLowerCase()) ? path.extname(fileName).toLowerCase() : '.webp';
}

async function buildCandidate(inputBuffer, metadata, targetExt, width, quality) {
  let pipeline = sharp(inputBuffer, { failOn: 'none' }).rotate();

  if (metadata.width && width && width < metadata.width) {
    pipeline = pipeline.resize({
      width,
      withoutEnlargement: true,
      fit: 'inside',
    });
  }

  if (targetExt === '.png') {
    return pipeline
      .png({
        compressionLevel: 9,
        effort: 10,
        palette: true,
      })
      .toBuffer();
  }

  if (targetExt === '.jpg' || targetExt === '.jpeg') {
    return pipeline
      .jpeg({
        quality,
        mozjpeg: true,
      })
      .toBuffer();
  }

  return pipeline
    .webp({
      quality,
      effort: 6,
      smartSubsample: true,
    })
    .toBuffer();
}

async function optimizeImage(filePath) {
  const originalStats = await fs.stat(filePath);
  const originalBuffer = await fs.readFile(filePath);
  const relativePath = toPosix(path.relative(ROOT, filePath));
  const fileName = path.basename(filePath);
  const currentExt = path.extname(filePath).toLowerCase();
  const targetExt = getTargetExt(fileName);
  const outputPath = path.join(path.dirname(filePath), `${path.parse(filePath).name}${targetExt}`);
  const tempOutputPath = outputPath === filePath ? `${outputPath}.tmp` : outputPath;
  const metadata = await sharp(originalBuffer, { failOn: 'none' }).metadata();

  if (originalStats.size <= TARGET_BYTES && currentExt === targetExt) {
    return {
      action: 'unchanged',
      source: relativePath,
      output: relativePath,
      originalBytes: originalStats.size,
      finalBytes: originalStats.size,
      width: metadata.width ?? null,
      quality: null,
    };
  }

  if (!metadata.width || !metadata.height) {
    return {
      action: 'skipped',
      source: relativePath,
      reason: 'unsupported-image-metadata',
    };
  }

  let bestBuffer = null;
  let bestSize = Number.POSITIVE_INFINITY;
  let bestWidth = metadata.width;
  let bestQuality = QUALITY_STEPS[QUALITY_STEPS.length - 1];

  for (const scale of SCALE_STEPS) {
    const candidateWidth = Math.max(1, Math.floor(metadata.width * scale));

    for (const quality of QUALITY_STEPS) {
      const buffer = await buildCandidate(originalBuffer, metadata, targetExt, candidateWidth, quality);

      if (buffer.length < bestSize) {
        bestBuffer = buffer;
        bestSize = buffer.length;
        bestWidth = candidateWidth;
        bestQuality = quality;
      }

      if (buffer.length <= TARGET_BYTES) {
        await fs.writeFile(tempOutputPath, buffer);

        if (outputPath === filePath) {
          await fs.unlink(outputPath);
          await fs.rename(tempOutputPath, outputPath);
        } else {
          await fs.unlink(filePath);
        }

        return {
          action: outputPath === filePath ? 'optimized' : 'converted',
          source: relativePath,
          output: toPosix(path.relative(ROOT, outputPath)),
          originalBytes: originalStats.size,
          finalBytes: buffer.length,
          width: candidateWidth,
          quality,
        };
      }
    }
  }

  if (!bestBuffer) {
    return {
      action: 'skipped',
      source: relativePath,
      reason: 'no-output-generated',
    };
  }

  await fs.writeFile(tempOutputPath, bestBuffer);

  if (outputPath === filePath) {
    await fs.unlink(outputPath);
    await fs.rename(tempOutputPath, outputPath);
  } else {
    await fs.unlink(filePath);
  }

  return {
    action: outputPath === filePath ? 'optimized-over-limit' : 'converted-over-limit',
    source: relativePath,
    output: toPosix(path.relative(ROOT, outputPath)),
    originalBytes: originalStats.size,
    finalBytes: bestBuffer.length,
    width: bestWidth,
    quality: bestQuality,
  };
}

function applyReplacements(content, replacements) {
  let nextContent = content;

  for (const { from, to } of replacements) {
    nextContent = nextContent.split(from).join(to);
  }

  return nextContent;
}

async function updateReferences(moves) {
  const textFiles = [...TEXT_FILES];

  for (const dir of TEXT_DIRS) {
    const files = await listFiles(dir);
    textFiles.push(...files.filter((file) => TEXT_EXTS.has(path.extname(file).toLowerCase())));
  }

  const replacements = [];

  for (const { source, output } of moves) {
    if (!output || source === output) {
      continue;
    }

    const encodedSource = encodeAssetPath(source);
    const encodedOutput = encodeAssetPath(output);

    replacements.push({ from: source, to: output });
    replacements.push({ from: encodedSource, to: encodedOutput });
    replacements.push({
      from: `https://a1technovation.com/${source}`,
      to: `https://a1technovation.com/${output}`,
    });
    replacements.push({
      from: `https://a1technovation.com/${encodedSource}`,
      to: `https://a1technovation.com/${encodedOutput}`,
    });
  }

  for (const file of textFiles) {
    let content;
    try {
      content = await fs.readFile(file, 'utf8');
    } catch {
      continue;
    }

    const nextContent = applyReplacements(content, replacements);
    if (nextContent !== content) {
      await fs.writeFile(file, nextContent);
    }
  }
}

async function main() {
  const files = (await listFiles(IMAGES_DIR))
    .filter((file) => RASTER_EXTS.has(path.extname(file).toLowerCase()))
    .sort();

  const results = [];

  for (const file of files) {
    results.push(await optimizeImage(file));
  }

  const moves = results.filter((result) => result.output);
  await updateReferences(moves);

  const oversized = results.filter(
    (result) => typeof result.finalBytes === 'number' && result.finalBytes > TARGET_BYTES,
  );

  const summary = {
    processed: results.length,
    converted: results.filter((result) => result.action === 'converted').length,
    optimized: results.filter((result) => result.action === 'optimized').length,
    unchanged: results.filter((result) => result.action === 'unchanged').length,
    oversized: oversized.length,
    results,
  };
  const reportDir = path.join(ROOT, 'output');
  const reportPath = path.join(reportDir, 'image-optimization-report.json');

  await fs.mkdir(reportDir, { recursive: true });
  await fs.writeFile(reportPath, JSON.stringify(summary, null, 2));

  console.log(`Processed ${summary.processed} images`);
  console.log(`Converted: ${summary.converted}`);
  console.log(`Optimized in place: ${summary.optimized}`);
  console.log(`Unchanged: ${summary.unchanged}`);
  console.log(`Oversized: ${summary.oversized}`);
  console.log(`Report: ${toPosix(path.relative(ROOT, reportPath))}`);

  process.exit(summary.oversized > 0 ? 1 : 0);
}

main().catch((error) => {
  console.error(error);
  process.exit(1);
});
