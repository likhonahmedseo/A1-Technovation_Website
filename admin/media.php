<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle = 'Media';
$activeNav = 'media';
$currentAdmin = cms_current_admin();
$errorMessage = '';

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'upload_media') {
            $adminId  = (int) ($currentAdmin['id'] ?? 0);
            $rawFiles = $_FILES['media_files'] ?? $_FILES['media_file'] ?? [];
            $files    = cms_normalize_uploaded_files($rawFiles);

            if ($files === []) {
                throw new RuntimeException('Please select at least one file to upload.');
            }

            $rawTitle     = trim((string) cms_post('title'));
            $rawAlt       = trim((string) cms_post('alt_text'));
            $totalCount   = count($files);
            $successCount = 0;
            $errors       = [];

            foreach ($files as $i => $file) {
                try {
                    $itemTitle = $rawTitle !== ''
                        ? ($totalCount > 1 ? "{$rawTitle} (" . ($i + 1) . ")" : $rawTitle)
                        : '';
                    $itemAlt   = $rawAlt !== ''
                        ? ($totalCount > 1 ? "{$rawAlt} (" . ($i + 1) . " of {$totalCount})" : $rawAlt)
                        : '';

                    cms_process_uploaded_media_item($file, $adminId, $itemTitle, $itemAlt);
                    $successCount++;
                } catch (Throwable $e) {
                    $fileName = !empty($file['name']) ? $file['name'] : ('File #' . ($i + 1));
                    $errors[] = $fileName . ': ' . $e->getMessage();
                }
            }

            if ($successCount > 0 && empty($errors)) {
                $msg = $successCount === 1
                    ? 'Media uploaded and optimised successfully.'
                    : "Successfully uploaded and optimised {$successCount} files.";
                cms_flash_set('success', $msg);
                cms_redirect('admin/media.php');
            } elseif ($successCount > 0 && !empty($errors)) {
                cms_flash_set('warning', "Uploaded {$successCount} file(s). Some failed: " . implode('; ', $errors));
                cms_redirect('admin/media.php');
            } else {
                throw new RuntimeException('Upload failed: ' . implode('; ', $errors));
            }
        }

        if ($action === 'import_stock_image') {
            $imported = cms_import_stock_image(
                (string) cms_post('stock_image_url'),
                (int) ($currentAdmin['id'] ?? 0),
                (string) cms_post('stock_title'),
                (string) cms_post('stock_alt_text'),
                (string) cms_post('stock_compress') === '1',
                (int) cms_post('stock_max_width', 1600),
                (int) cms_post('stock_quality', 78)
            );
            cms_track_unsplash_download((string) cms_post('stock_download_url'));
            $sizeKb = isset($imported['file_size']) ? number_format((float) $imported['file_size'] / 1024, 1) : '0.0';
            cms_flash_set('success', 'Stock image imported and saved to the media library (' . $sizeKb . ' KB).');
            cms_redirect('admin/media.php');
        }

        if ($action === 'delete_media') {
            cms_delete_media((int) cms_post('id'));
            cms_flash_set('success', 'Media item removed.');
            cms_redirect('admin/media.php');
        }

        if ($action === 'bulk_delete_media') {
            $rawIds = $_POST['media_ids'] ?? [];
            $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];
            $count = cms_bulk_delete_media($ids);
            if ($count > 0) {
                cms_flash_set('success', "Successfully deleted {$count} media " . ($count === 1 ? 'item' : 'items') . '.');
            } else {
                cms_flash_set('warning', 'No media items were deleted.');
            }
            cms_redirect('admin/media.php');
        }

        if ($action === 'delete_site_image') {
            $filePath = (string) cms_post('file_path');
            if (cms_delete_site_image($filePath)) {
                cms_flash_set('success', 'Image removed from site assets.');
            } else {
                cms_flash_set('error', 'Could not delete the selected image. It may be protected or already removed.');
            }
            cms_redirect('admin/media.php');
        }

        if ($action === 'bulk_delete_site_images') {
            $rawPaths = $_POST['file_paths'] ?? [];
            $paths = is_array($rawPaths) ? array_filter($rawPaths, 'is_string') : [];
            $count = cms_bulk_delete_site_images($paths);
            if ($count > 0) {
                cms_flash_set('success', "Successfully deleted {$count} site " . ($count === 1 ? 'image' : 'images') . '.');
            } else {
                cms_flash_set('warning', 'No images were deleted. Selected files may be protected or already removed.');
            }
            cms_redirect('admin/media.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

$mediaItems = cms_get_recent_media();
$siteImages = cms_get_site_image_assets();
$stockQuery = trim((string) cms_query('stock_q'));
$stockProvider = (string) cms_query('stock_provider', 'all');
$stockPage = max(1, (int) cms_query('stock_page', 1));
$stockResults = $stockQuery !== '' ? cms_search_stock_images($stockQuery, $stockProvider, $stockPage, 20) : [];
$configuredStockProviders = cms_configured_stock_image_providers();
$stockProviderLabels = ['pexels' => 'Pexels', 'pixabay' => 'Pixabay', 'unsplash' => 'Unsplash'];

// 5 most recently added images (from CMS media library first, supplemented by latest site image assets)
$recentLibraryImages = array_values(array_filter($mediaItems, static function (array $item): bool {
    return str_starts_with((string) ($item['mime_type'] ?? ''), 'image/');
}));

$recentAddedImages = [];
foreach (array_slice($recentLibraryImages, 0, 5) as $m) {
    $recentAddedImages[] = [
        'id'           => (int) ($m['id'] ?? 0),
        'title'        => (string) ($m['title'] ?: $m['original_name']),
        'file_name'    => (string) ($m['original_name'] ?? ''),
        'url'          => cms_media_url((string) $m['file_path']),
        'alt_text'     => (string) ($m['alt_text'] ?: $m['title']),
        'extension'    => strtoupper(pathinfo((string) $m['original_name'], PATHINFO_EXTENSION)),
        'size'         => (int) ($m['file_size'] ?? 0),
        'created_at'   => (string) ($m['created_at'] ?? ''),
        'is_deletable' => true,
    ];
}

if (count($recentAddedImages) < 5 && !empty($siteImages)) {
    $sortedSiteImages = $siteImages;
    usort($sortedSiteImages, static function (array $a, array $b): int {
        $mtimeA = isset($a['file_path']) ? @filemtime(cms_root((string) $a['file_path'])) : 0;
        $mtimeB = isset($b['file_path']) ? @filemtime(cms_root((string) $b['file_path'])) : 0;
        return $mtimeB <=> $mtimeA;
    });

    $existingUrls = array_column($recentAddedImages, 'url');
    foreach ($sortedSiteImages as $img) {
        if (count($recentAddedImages) >= 5) {
            break;
        }
        $imgUrl = (string) ($img['url'] ?? '');
        if (in_array($imgUrl, $existingUrls, true)) {
            continue;
        }
        $mtime = isset($img['file_path']) ? @filemtime(cms_root((string) $img['file_path'])) : 0;
        $recentAddedImages[] = [
            'id'           => 0,
            'title'        => (string) ($img['title'] ?? $img['file_name']),
            'file_name'    => (string) ($img['file_name'] ?? ''),
            'file_path'    => (string) ($img['file_path'] ?? ''),
            'url'          => $imgUrl,
            'alt_text'     => (string) ($img['title'] ?? ''),
            'extension'    => strtoupper((string) ($img['extension'] ?? 'JPG')),
            'size'         => (int) ($img['size'] ?? 0),
            'created_at'   => $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '',
            'is_deletable' => empty($img['is_protected']),
        ];
        $existingUrls[] = $imgUrl;
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-photo-film"></i> Media Library</span>
    <h1 class="admin-page-title">Upload once, reuse everywhere</h1>
    <p class="admin-page-copy">Store images, PDFs, and video files in one place, then paste their URLs into blog posts or featured image fields without touching the filesystem manually.</p>
  </div>
</section>

<?php if ($errorMessage !== ''): ?>
  <div class="flash flash-error"><?= cms_e($errorMessage) ?></div>
<?php endif; ?>

<section class="split-grid wide" style="display:grid;gap:20px">
  <article class="admin-card">
    <div class="toolbar">
      <div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
          <h3 style="margin:0">Upload new media</h3>
          <span class="dropzone-badge"><i class="fas fa-layer-group"></i> Multiple upload supported</span>
        </div>
        <p class="muted" style="margin-top:4px">Upload multiple images or files at once. JPEGs and PNGs are automatically converted to optimized WebP.</p>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="form-grid" id="mediaUploadForm">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="upload_media" />

      <div class="field">
        <label>Select images or files</label>
        <div class="media-upload-dropzone" id="mediaDropzone" tabindex="0" role="button" aria-label="Choose files or drag and drop">
          <input id="media_files" type="file" name="media_files[]" multiple required accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,video/mp4,video/webm,application/pdf" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0" />
          <div class="dropzone-icon">
            <i class="fas fa-cloud-arrow-up"></i>
          </div>
          <div class="dropzone-content">
            <p class="dropzone-prompt"><strong>Choose files</strong> or drag &amp; drop here</p>
            <p class="dropzone-hint">Select one or multiple images at once (hold <kbd>Ctrl</kbd> or <kbd>Shift</kbd> to select multiple). Supported: JPG, PNG, WebP, GIF, SVG, MP4, WebM, and PDF.</p>
          </div>
        </div>

        <div id="selectedFilesTray" class="selected-files-tray" style="display:none">
          <div class="selected-files-header">
            <div class="selected-summary">
              <i class="fas fa-images" style="color:var(--admin-primary)"></i>
              <strong id="selectedFilesCount">0 files selected</strong>
              <span id="selectedFilesSize" style="color:var(--admin-muted);font-size:.85rem">(0 KB)</span>
            </div>
            <button type="button" id="clearSelectionBtn" class="btn btn-ghost btn-sm" style="color:var(--admin-danger);padding:4px 8px;font-size:.82rem">
              <i class="fas fa-trash-can"></i> Clear all
            </button>
          </div>
          <div id="selectedFilesList" class="selected-files-list"></div>
        </div>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="title">Title <span class="field-optional">optional</span></label>
          <input id="title" type="text" name="title" placeholder="Homepage hero, SEO infographic..." />
          <p class="helper-text" style="font-size:.76rem;color:var(--admin-muted);margin:4px 0 0">Blank = uses original filenames. Filled = numbered automatically (e.g. Title (1), Title (2)).</p>
        </div>
        <div class="field">
          <label for="alt_text">Alt text <span class="field-optional">optional</span></label>
          <input id="alt_text" type="text" name="alt_text" placeholder="Describe images for accessibility" />
          <p class="helper-text" style="font-size:.76rem;color:var(--admin-muted);margin:4px 0 0">Used as accessibility text for uploaded images.</p>
        </div>
      </div>

      <button type="submit" id="uploadSubmitBtn" class="btn btn-primary" style="justify-content:center;padding:12px 18px">
        <i class="fas fa-cloud-arrow-up"></i> <span id="uploadBtnText">Upload to Library</span>
      </button>
    </form>
  </article>

  <article class="admin-card">
    <div class="toolbar">
      <div>
        <h3>Import stock image</h3>
        <p class="muted">Paste a Pexels image/page URL. The CMS downloads it, compresses it, and stores it locally.</p>
      </div>
    </div>

    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="import_stock_image" />
      <div class="field">
        <label for="stock_image_url">Image or page URL</label>
        <input id="stock_image_url" type="url" name="stock_image_url" placeholder="https://images.pexels.com/..." required />
        <p class="helper-text">Allowed sources include Pexels direct image/page URLs. Check the provider license before publishing.</p>
      </div>
      <div class="form-grid two-col">
        <div class="field">
          <label for="stock_title">Title</label>
          <input id="stock_title" type="text" name="stock_title" placeholder="Dubai SEO hero image" />
        </div>
        <div class="field">
          <label for="stock_alt_text">Alt text</label>
          <input id="stock_alt_text" type="text" name="stock_alt_text" placeholder="Describe the imported image" />
        </div>
      </div>
      <div class="form-grid two-col">
        <div class="field">
          <label for="stock_max_width">Max width</label>
          <input id="stock_max_width" type="number" name="stock_max_width" value="1600" min="320" max="4000" step="80" />
        </div>
        <div class="field">
          <label for="stock_quality">WebP quality</label>
          <input id="stock_quality" type="number" name="stock_quality" value="78" min="35" max="92" step="1" />
        </div>
      </div>
      <label class="checkbox-field" style="align-items:center">
        <input type="checkbox" name="stock_compress" value="1" checked />
        <span>Compress imported image to WebP when the server supports it</span>
      </label>
      <div class="inline-actions" style="flex-wrap:wrap">
        <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-arrow-down"></i> Import & Compress</button>
        <a href="https://www.pexels.com/search/" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Pexels</a>
      </div>
    </form>
  </article>

  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3>How to use this library</h3>
        <p class="muted">Each asset gets a stable public URL under <code>assets/uploads/media</code>.</p>
      </div>
    </div>
    <div class="form-grid">
      <div class="empty-state" style="text-align:left">
        <strong>Featured images</strong>
        <p class="muted">Copy an image URL and paste it into the featured image field on the blogs page.</p>
      </div>
      <div class="empty-state" style="text-align:left">
        <strong>Inside CKEditor</strong>
        <p class="muted">Paste the image or file URL into article content, or link to PDFs and media downloads inside your post body.</p>
      </div>
    </div>
  </article>
</section>

<section class="table-card" style="margin-top:20px">
  <div class="toolbar">
    <div>
      <h3>Search free stock images</h3>
      <p class="muted">Search configured stock image APIs, then import the selected image into this CMS with compression.</p>
    </div>
  </div>

  <form method="get" class="form-grid" style="margin-bottom:20px">
    <div class="form-grid two-col">
      <div class="field">
        <label for="stock_q">Search keyword</label>
        <input id="stock_q" type="search" name="stock_q" value="<?= cms_e($stockQuery) ?>" placeholder="SEO office, Dubai skyline, marketing team..." />
      </div>
      <div class="field">
        <label for="stock_provider">Provider</label>
        <select id="stock_provider" name="stock_provider">
          <?php $providerOptions = ['all' => 'All configured']; ?>
          <?php foreach ($configuredStockProviders as $providerKey) { $providerOptions[$providerKey] = $stockProviderLabels[$providerKey] ?? ucfirst($providerKey); } ?>
          <?php foreach ($providerOptions as $value => $label): ?>
            <option value="<?= cms_e($value) ?>" <?= $stockProvider === $value ? 'selected' : '' ?>><?= cms_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="inline-actions" style="flex-wrap:wrap">
      <button type="submit" class="btn btn-primary"><i class="fas fa-magnifying-glass"></i> Search Images</button>
      <a href="<?= cms_e(cms_url('admin/media.php')) ?>" class="btn btn-ghost btn-sm">Clear</a>
    </div>
  </form>

  <?php if ($stockQuery === ''): ?>
    <div class="empty-state" style="text-align:left">
      <strong>API keys required</strong>
      <p class="muted">Add your Pexels key in <a href="<?= cms_e(cms_url('admin/settings.php#stock-images')) ?>">Settings &gt; Stock image APIs</a>, or set the <code>PEXELS_API_KEY</code> environment variable.</p>
    </div>
  <?php else: ?>
    <?php foreach ($stockResults as $providerResult): ?>
      <?php
        $providerName = ucfirst((string) ($providerResult['provider'] ?? 'Provider'));
        $items = is_array($providerResult['items'] ?? null) ? $providerResult['items'] : [];
        $error = (string) ($providerResult['error'] ?? '');
      ?>
      <div style="margin-top:18px">
        <div class="toolbar" style="margin-bottom:12px">
          <div>
            <h4 style="margin:0"><?= cms_e($providerName) ?></h4>
            <?php if ($error !== ''): ?><p class="muted"><?= cms_e($error) ?></p><?php endif; ?>
          </div>
        </div>

        <?php if ($items === [] && $error === ''): ?>
          <div class="empty-state">No <?= cms_e($providerName) ?> images found for this search.</div>
        <?php elseif ($items !== []): ?>
          <div class="media-grid">
            <?php foreach ($items as $item): ?>
              <?php
                $imageUrl = (string) ($item['image_url'] ?? '');
                $previewUrl = (string) ($item['preview_url'] ?? $imageUrl);
                $title = (string) ($item['title'] ?? 'Stock image');
                $alt = (string) ($item['alt_text'] ?? $title);
                $pageUrl = (string) ($item['page_url'] ?? '');
                $credit = (string) ($item['credit'] ?? $providerName);
                $downloadUrl = (string) ($item['download_url'] ?? '');
              ?>
              <article class="media-card">
                <div class="media-preview">
                  <img src="<?= cms_e($previewUrl) ?>" alt="<?= cms_e($alt !== '' ? $alt : $title) ?>" loading="lazy" />
                </div>
                <div class="media-body">
                  <strong style="font-size:.88rem;line-height:1.3;display:block;margin-bottom:2px"><?= cms_e($title) ?></strong>
                  <span class="helper-text" style="font-size:.78rem"><?= cms_e($credit) ?></span>
                  <div class="inline-actions" style="flex-wrap:wrap;margin-top:10px">
                    <form method="post" style="display:inline">
                      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                      <input type="hidden" name="action" value="import_stock_image" />
                      <input type="hidden" name="stock_image_url" value="<?= cms_e($imageUrl) ?>" />
                      <input type="hidden" name="stock_title" value="<?= cms_e($title) ?>" />
                      <input type="hidden" name="stock_alt_text" value="<?= cms_e($alt) ?>" />
                      <input type="hidden" name="stock_max_width" value="1600" />
                      <input type="hidden" name="stock_quality" value="78" />
                      <input type="hidden" name="stock_compress" value="1" />
                      <input type="hidden" name="stock_download_url" value="<?= cms_e($downloadUrl) ?>" />
                      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-cloud-arrow-down"></i> Import</button>
                    </form>
                    <?php if ($pageUrl !== ''): ?>
                      <a href="<?= cms_e($pageUrl) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Source</a>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>

    <div class="inline-actions" style="justify-content:flex-end;margin-top:18px;flex-wrap:wrap">
      <?php if ($stockPage > 1): ?>
        <a class="btn btn-ghost btn-sm" href="<?= cms_e(cms_url('admin/media.php?' . http_build_query(['stock_q' => $stockQuery, 'stock_provider' => $stockProvider, 'stock_page' => $stockPage - 1]))) ?>"><i class="fas fa-arrow-left"></i> Previous</a>
      <?php endif; ?>
      <a class="btn btn-secondary btn-sm" href="<?= cms_e(cms_url('admin/media.php?' . http_build_query(['stock_q' => $stockQuery, 'stock_provider' => $stockProvider, 'stock_page' => $stockPage + 1]))) ?>">Next <i class="fas fa-arrow-right"></i></a>
    </div>
  <?php endif; ?>
</section>

<section class="table-card" style="margin-top:20px" id="recentImagesSection">
  <div class="toolbar">
    <div>
      <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <h3 style="margin:0"><i class="fas fa-clock-rotate-left" style="color:var(--admin-primary)"></i> Recently added images</h3>
        <span class="badge" style="background:#e9f2ff;color:var(--admin-primary);padding:3px 10px;border-radius:999px;font-size:.76rem;font-weight:600">
          Last <?= count($recentAddedImages) ?> added
        </span>
      </div>
      <p class="muted" style="margin-top:4px">The last 5 images added to your website. Quick-copy URLs or view full resolution.</p>
    </div>
  </div>

  <?php if ($recentAddedImages === []): ?>
    <div class="empty-state">No images added yet. Upload images above to see them here.</div>
  <?php else: ?>
    <div class="media-grid" style="grid-template-columns:repeat(auto-fill, minmax(210px, 1fr))">
      <?php foreach ($recentAddedImages as $image): ?>
        <?php
          $imageUrl = (string) $image['url'];
          $title    = (string) $image['title'];
          $ext      = (string) $image['extension'];
          $sizeKb   = number_format((float) $image['size'] / 1024, 1);
          $dateStr  = !empty($image['created_at']) ? cms_format_date($image['created_at'], 'M j, Y') : '';
        ?>
        <article class="media-card" style="display:flex;flex-direction:column">
          <div class="media-preview" style="position:relative">
            <img src="<?= cms_e($imageUrl) ?>" alt="<?= cms_e((string) ($image['alt_text'] ?: $title)) ?>" loading="lazy" />
            <span style="position:absolute;top:8px;right:8px;background:rgba(11,21,40,0.72);color:#fff;font-size:.7rem;font-weight:600;padding:2px 7px;border-radius:6px;backdrop-filter:blur(4px);letter-spacing:.3px">
              <?= cms_e($ext) ?>
            </span>
          </div>
          <div class="media-body" style="display:flex;flex-direction:column;flex:1">
            <strong style="font-size:.88rem;line-height:1.3;display:block;margin-bottom:2px" title="<?= cms_e($title) ?>">
              <?= cms_e($title) ?>
            </strong>
            <span class="helper-text" style="font-size:.78rem;margin-bottom:8px">
              <?= $sizeKb ?> KB <?= $dateStr !== '' ? '· ' . cms_e($dateStr) : '' ?>
            </span>
            <input class="media-url" type="text" readonly value="<?= cms_e($imageUrl) ?>" style="margin-top:auto;margin-bottom:10px" />
            <div class="inline-actions" style="flex-wrap:wrap">
              <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e($imageUrl) ?>"><i class="fas fa-copy"></i> Copy URL</button>
              <a href="<?= cms_e($imageUrl) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a>
              <?php if (!empty($image['is_deletable']) && !empty($image['id'])): ?>
                <form method="post" onsubmit="return confirm('Delete this media item permanently?');" style="display:inline">
                  <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="delete_media" />
                  <input type="hidden" name="id" value="<?= (int) $image['id'] ?>" />
                  <button type="submit" class="btn btn-danger btn-sm" title="Delete image"><i class="fas fa-trash"></i></button>
                </form>
              <?php elseif (!empty($image['is_deletable']) && !empty($image['file_path'])): ?>
                <form method="post" onsubmit="return confirm('Delete this image permanently from assets/images?');" style="display:inline">
                  <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="delete_site_image" />
                  <input type="hidden" name="file_path" value="<?= cms_e((string) $image['file_path']) ?>" />
                  <button type="submit" class="btn btn-danger btn-sm" title="Delete image"><i class="fas fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="table-card" style="margin-top:20px" id="siteImagesSection">
  <div class="toolbar" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
    <div>
      <h3>Site image directory <span id="siteImageCount" style="font-weight:400;color:var(--admin-muted);font-size:.88rem"></span></h3>
      <p class="muted">All reusable images from <code>assets/images</code>. Copy any URL and paste it into page sections, hero backgrounds, galleries, image blocks, or custom HTML.</p>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <button type="button" class="btn btn-secondary btn-sm" id="siteImageSelectToggleBtn">
        <i class="fas fa-check-square"></i> <span id="siteImageSelectToggleText">Select multiple</span>
      </button>
      <button type="button" class="btn btn-ghost btn-sm" data-copy-text="<?= cms_e(cms_url('assets/images/')) ?>"><i class="fas fa-copy"></i> Copy Folder URL</button>
    </div>
  </div>

  <?php if ($siteImages === []): ?>
    <div class="empty-state">No images found in <code>assets/images</code>.</div>
  <?php else: ?>
    <!-- Bulk Actions Sticky Bar for Site Images -->
    <form id="siteImageBulkForm" method="post" style="display:none;margin-bottom:16px">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="bulk_delete_site_images" />
      <div class="bulk-action-bar">
        <div class="bulk-action-info">
          <span class="bulk-counter-pill"><i class="fas fa-check-circle"></i> <strong id="siteImageSelectedCount">0</strong> selected</span>
          <button type="button" id="siteImageSelectAllBtn" class="btn btn-ghost btn-sm"><i class="fas fa-check-double"></i> Select visible (<span id="siteImageVisibleCount">0</span>)</button>
          <button type="button" id="siteImageDeselectAllBtn" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Deselect all</button>
        </div>
        <div class="bulk-action-buttons">
          <button type="button" id="siteImageBulkDeleteBtn" class="btn btn-danger btn-sm" disabled>
            <i class="fas fa-trash-can"></i> Delete selected (<span id="siteImageDeleteCountBadge">0</span>)
          </button>
          <button type="button" id="siteImageCancelSelectBtn" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
      </div>
      <div id="siteImageBulkHiddenInputs"></div>
    </form>

    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;align-items:center">
      <div style="flex:1;min-width:220px;position:relative">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--admin-muted);pointer-events:none;font-size:.85rem"></i>
        <input id="siteImageSearch" type="search" placeholder="Search images by filename..."
               style="width:100%;padding:10px 12px 10px 36px;border:1.5px solid var(--admin-border);border-radius:10px;font-size:.9rem;background:#fff" />
      </div>
    </div>

    <div class="media-grid" id="siteImageGrid">
      <?php foreach ($siteImages as $image): ?>
        <?php $imageUrl = (string) $image['url']; ?>
        <article class="media-card"
                 data-title="<?= cms_e(strtolower((string) $image['title'])) ?>"
                 data-name="<?= cms_e(strtolower((string) $image['file_name'])) ?>"
                 data-path="<?= cms_e(strtolower((string) $image['file_path'])) ?>">
          <div class="media-preview" style="position:relative">
            <label class="media-select-checkbox-wrap" title="<?= !empty($image['is_protected']) ? 'Protected system file' : 'Select this image' ?>">
              <input type="checkbox" class="site-image-check" value="<?= cms_e((string) $image['file_path']) ?>" <?= !empty($image['is_protected']) ? 'disabled' : '' ?> />
              <span class="media-custom-check"><i class="fas fa-check"></i></span>
            </label>
            <img src="<?= cms_e($imageUrl) ?>" alt="<?= cms_e((string) $image['title']) ?>" loading="lazy" />
            <?php if (!empty($image['is_protected'])): ?>
              <span class="media-protected-badge" title="Protected system file"><i class="fas fa-shield-halved"></i> Protected</span>
            <?php endif; ?>
          </div>
          <div class="media-body">
            <strong style="font-size:.88rem;line-height:1.3;display:block;margin-bottom:2px" title="<?= cms_e((string) $image['file_name']) ?>"><?= cms_e((string) $image['file_name']) ?></strong>
            <span class="helper-text" style="font-size:.78rem"><?= cms_e(strtoupper((string) $image['extension'])) ?> · <?= number_format((float) $image['size'] / 1024, 1) ?> KB</span>
            <input class="media-url" type="text" readonly value="<?= cms_e($imageUrl) ?>" />
            <div class="inline-actions" style="flex-wrap:wrap">
              <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e($imageUrl) ?>"><i class="fas fa-copy"></i> Copy URL</button>
              <a href="<?= cms_e($imageUrl) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a>
              <?php if (empty($image['is_protected'])): ?>
                <form method="post" onsubmit="return confirm('Permanently delete <?= cms_e(addslashes((string) $image['file_name'])) ?> from assets/images?');" style="display:inline">
                  <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="delete_site_image" />
                  <input type="hidden" name="file_path" value="<?= cms_e((string) $image['file_path']) ?>" />
                  <button type="submit" class="btn btn-danger btn-sm" title="Delete image"><i class="fas fa-trash"></i></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <p id="siteImageNoResults" style="display:none;text-align:center;padding:32px;color:var(--admin-muted)">No images match your search.</p>
  <?php endif; ?>
</section>

<section class="table-card" style="margin-top:20px" id="librarySection">
  <div class="toolbar" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px">
    <div>
      <h3>Library <span id="mediaCount" style="font-weight:400;color:var(--admin-muted);font-size:.88rem"></span></h3>
      <p class="muted">All uploads — newest first. Search or filter by type.</p>
    </div>
    <div>
      <button type="button" class="btn btn-secondary btn-sm" id="librarySelectToggleBtn">
        <i class="fas fa-check-square"></i> <span id="librarySelectToggleText">Select multiple</span>
      </button>
    </div>
  </div>

  <?php if ($mediaItems === []): ?>
    <div class="empty-state">No media uploaded yet. Use the upload form above to add your first file.</div>
  <?php else: ?>
    <!-- Bulk Actions Sticky Bar for Library -->
    <form id="libraryBulkForm" method="post" style="display:none;margin-bottom:16px">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="bulk_delete_media" />
      <div class="bulk-action-bar">
        <div class="bulk-action-info">
          <span class="bulk-counter-pill"><i class="fas fa-check-circle"></i> <strong id="librarySelectedCount">0</strong> selected</span>
          <button type="button" id="librarySelectAllBtn" class="btn btn-ghost btn-sm"><i class="fas fa-check-double"></i> Select visible (<span id="libraryVisibleCount">0</span>)</button>
          <button type="button" id="libraryDeselectAllBtn" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i> Deselect all</button>
        </div>
        <div class="bulk-action-buttons">
          <button type="button" id="libraryBulkDeleteBtn" class="btn btn-danger btn-sm" disabled>
            <i class="fas fa-trash-can"></i> Delete selected (<span id="libraryDeleteCountBadge">0</span>)
          </button>
          <button type="button" id="libraryCancelSelectBtn" class="btn btn-ghost btn-sm">Cancel</button>
        </div>
      </div>
      <div id="libraryBulkHiddenInputs"></div>
    </form>

    <!-- Toolbar: search + type filter -->
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;align-items:center">
      <div style="flex:1;min-width:200px;position:relative">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--admin-muted);pointer-events:none;font-size:.85rem"></i>
        <input id="mediaSearch" type="search" placeholder="Search by title or filename…"
               style="width:100%;padding:10px 12px 10px 36px;border:1.5px solid var(--admin-border);border-radius:10px;font-size:.9rem;background:#fff" />
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap" id="mediaFilters">
        <button class="btn btn-secondary btn-sm media-filter-btn active" data-filter="all">All (<?= count($mediaItems) ?>)</button>
        <?php
          $images = array_filter($mediaItems, fn($m) => str_starts_with((string)$m['mime_type'], 'image/'));
          $videos = array_filter($mediaItems, fn($m) => str_starts_with((string)$m['mime_type'], 'video/'));
          $docs   = array_filter($mediaItems, fn($m) => !str_starts_with((string)$m['mime_type'], 'image/') && !str_starts_with((string)$m['mime_type'], 'video/'));
        ?>
        <?php if ($images): ?><button class="btn btn-ghost btn-sm media-filter-btn" data-filter="image"><i class="fas fa-image"></i> Images (<?= count($images) ?>)</button><?php endif; ?>
        <?php if ($videos): ?><button class="btn btn-ghost btn-sm media-filter-btn" data-filter="video"><i class="fas fa-film"></i> Videos (<?= count($videos) ?>)</button><?php endif; ?>
        <?php if ($docs):   ?><button class="btn btn-ghost btn-sm media-filter-btn" data-filter="doc"><i class="fas fa-file"></i> Files (<?= count($docs) ?>)</button><?php endif; ?>
      </div>
    </div>

    <div class="media-grid" id="mediaGrid">
      <?php foreach ($mediaItems as $media): ?>
        <?php
          $mediaUrl  = cms_media_url((string) $media['file_path']);
          $mime      = (string) $media['mime_type'];
          $isImage   = str_starts_with($mime, 'image/');
          $isVideo   = str_starts_with($mime, 'video/');
          $typeClass = $isImage ? 'image' : ($isVideo ? 'video' : 'doc');
        ?>
        <article class="media-card" data-type="<?= $typeClass ?>"
                 data-title="<?= cms_e(strtolower((string) $media['title'])) ?>"
                 data-name="<?= cms_e(strtolower((string) $media['original_name'])) ?>">
          <div class="media-preview" style="position:relative">
            <label class="media-select-checkbox-wrap" title="Select this item">
              <input type="checkbox" class="library-item-check" value="<?= (int) $media['id'] ?>" />
              <span class="media-custom-check"><i class="fas fa-check"></i></span>
            </label>
            <?php if ($isImage): ?>
              <img src="<?= cms_e($mediaUrl) ?>" alt="<?= cms_e((string) ($media['alt_text'] ?: $media['title'])) ?>" loading="lazy" />
            <?php elseif ($isVideo): ?>
              <video src="<?= cms_e($mediaUrl) ?>" muted playsinline></video>
            <?php else: ?>
              <?php
                $ext = strtolower(pathinfo((string) $media['original_name'], PATHINFO_EXTENSION));
                $icon = match($ext) {
                  'pdf'  => 'fa-file-pdf',
                  'doc', 'docx' => 'fa-file-word',
                  'xls', 'xlsx' => 'fa-file-excel',
                  'zip', 'rar'  => 'fa-file-zipper',
                  default       => 'fa-file-lines',
                };
              ?>
              <i class="fas <?= $icon ?>" style="font-size:2.5rem;color:#83a3c7"></i>
            <?php endif; ?>
          </div>
          <div class="media-body">
            <strong style="font-size:.88rem;line-height:1.3;display:block;margin-bottom:2px"><?= cms_e((string) $media['title'] ?: $media['original_name']) ?></strong>
            <?php if ($media['title'] && $media['original_name'] !== $media['title']): ?>
              <span class="helper-text" style="font-size:.78rem"><?= cms_e((string) $media['original_name']) ?></span>
            <?php endif; ?>
            <input class="media-url" type="text" readonly value="<?= cms_e($mediaUrl) ?>" />
            <div class="inline-actions" style="flex-wrap:wrap">
              <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e($mediaUrl) ?>"><i class="fas fa-copy"></i> Copy URL</button>
              <a href="<?= cms_e($mediaUrl) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a>
              <form method="post" onsubmit="return confirm('Delete this media item permanently?');" style="display:inline">
                <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete_media" />
                <input type="hidden" name="id" value="<?= (int) $media['id'] ?>" />
                <button type="submit" class="btn btn-danger btn-sm" title="Delete media"><i class="fas fa-trash"></i></button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <p id="mediaNoResults" style="display:none;text-align:center;padding:32px;color:var(--admin-muted)">No items match your search.</p>
  <?php endif; ?>
</section>

<script>
// ── Site Images Directory (search + multi-select + bulk delete) ──
(function () {
  const section = document.getElementById('siteImagesSection');
  const grid    = document.getElementById('siteImageGrid');
  if (!grid || !section) return;

  const cards          = Array.from(grid.querySelectorAll('.media-card'));
  const search         = document.getElementById('siteImageSearch');
  const noRes          = document.getElementById('siteImageNoResults');
  const countEl        = document.getElementById('siteImageCount');
  const toggleBtn      = document.getElementById('siteImageSelectToggleBtn');
  const toggleText     = document.getElementById('siteImageSelectToggleText');
  const bulkForm       = document.getElementById('siteImageBulkForm');
  const selectedCountEl= document.getElementById('siteImageSelectedCount');
  const visibleCountEl = document.getElementById('siteImageVisibleCount');
  const deleteCountEl  = document.getElementById('siteImageDeleteCountBadge');
  const selectAllBtn   = document.getElementById('siteImageSelectAllBtn');
  const deselectAllBtn = document.getElementById('siteImageDeselectAllBtn');
  const bulkDeleteBtn  = document.getElementById('siteImageBulkDeleteBtn');
  const cancelBtn      = document.getElementById('siteImageCancelSelectBtn');
  const hiddenInputs   = document.getElementById('siteImageBulkHiddenInputs');

  let isSelectMode = false;
  let lastCheckedIndex = -1;

  function getVisibleCards() {
    return cards.filter(c => c.style.display !== 'none');
  }

  function updateCounts() {
    const visibleCards = getVisibleCards();
    const checkedBoxes = cards.map(c => c.querySelector('.site-image-check')).filter(cb => cb && cb.checked);
    const checkedCount = checkedBoxes.length;

    if (selectedCountEl) selectedCountEl.textContent = checkedCount;
    if (deleteCountEl) deleteCountEl.textContent = checkedCount;
    if (visibleCountEl) visibleCountEl.textContent = visibleCards.filter(c => {
      const cb = c.querySelector('.site-image-check');
      return cb && !cb.disabled;
    }).length;

    if (bulkDeleteBtn) bulkDeleteBtn.disabled = checkedCount === 0;

    cards.forEach(card => {
      const cb = card.querySelector('.site-image-check');
      card.classList.toggle('is-selected', !!(cb && cb.checked));
    });

    if (checkedCount > 0 && !isSelectMode) {
      setSelectMode(true);
    }
  }

  function setSelectMode(active) {
    isSelectMode = active;
    section.classList.toggle('media-select-mode', active);
    if (toggleBtn) {
      toggleBtn.classList.toggle('btn-select-mode-active', active);
      if (active) {
        toggleBtn.classList.remove('btn-secondary');
        toggleBtn.classList.add('btn-primary');
      } else {
        toggleBtn.classList.add('btn-secondary');
        toggleBtn.classList.remove('btn-primary');
      }
    }
    if (toggleText) toggleText.textContent = active ? 'Exit Selection' : 'Select multiple';
    if (bulkForm) bulkForm.style.display = active ? 'block' : 'none';

    if (!active) {
      cards.forEach(card => {
        const cb = card.querySelector('.site-image-check');
        if (cb) cb.checked = false;
        card.classList.remove('is-selected');
      });
      lastCheckedIndex = -1;
      updateCounts();
    }
  }

  function updateSearch() {
    const q = search ? search.value.trim().toLowerCase() : '';
    let visible = 0;

    cards.forEach(card => {
      const show = !q || card.dataset.title.includes(q) || card.dataset.name.includes(q) || card.dataset.path.includes(q);
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    if (noRes) noRes.style.display = visible === 0 ? '' : 'none';
    if (countEl) countEl.textContent = '(' + visible + ' of <?= count($siteImages) ?>)';
    updateCounts();
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      setSelectMode(!isSelectMode);
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      setSelectMode(false);
    });
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', () => {
      const visible = getVisibleCards();
      visible.forEach(card => {
        const cb = card.querySelector('.site-image-check');
        if (cb && !cb.disabled) cb.checked = true;
      });
      updateCounts();
    });
  }

  if (deselectAllBtn) {
    deselectAllBtn.addEventListener('click', () => {
      cards.forEach(card => {
        const cb = card.querySelector('.site-image-check');
        if (cb) cb.checked = false;
      });
      updateCounts();
    });
  }

  cards.forEach((card, idx) => {
    const cb = card.querySelector('.site-image-check');
    if (!cb) return;

    cb.addEventListener('click', (e) => {
      e.stopPropagation();
      if (e.shiftKey && lastCheckedIndex !== -1 && cb.checked) {
        const start = Math.min(lastCheckedIndex, idx);
        const end = Math.max(lastCheckedIndex, idx);
        for (let i = start; i <= end; i++) {
          const targetCb = cards[i]?.querySelector('.site-image-check');
          if (targetCb && !targetCb.disabled && cards[i].style.display !== 'none') {
            targetCb.checked = true;
          }
        }
      }
      lastCheckedIndex = idx;
      updateCounts();
    });

    card.addEventListener('click', (e) => {
      if (!isSelectMode) return;
      if (e.target.closest('button, a, input, form, label')) return;
      if (cb.disabled) return;
      cb.checked = !cb.checked;
      lastCheckedIndex = idx;
      updateCounts();
    });
  });

  if (bulkDeleteBtn && bulkForm && hiddenInputs) {
    bulkDeleteBtn.addEventListener('click', () => {
      const selected = cards
        .map(c => c.querySelector('.site-image-check'))
        .filter(cb => cb && cb.checked && !cb.disabled)
        .map(cb => cb.value);

      if (selected.length === 0) {
        alert('Please select at least one image to delete.');
        return;
      }

      const confirmMsg = 'Are you sure you want to permanently delete ' + selected.length + ' selected ' +
        (selected.length === 1 ? 'image' : 'images') + ' from assets/images?\n\n' +
        'This will permanently remove the files from your server and cannot be undone!';

      if (!confirm(confirmMsg)) return;

      hiddenInputs.innerHTML = '';
      selected.forEach(path => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'file_paths[]';
        input.value = path;
        hiddenInputs.appendChild(input);
      });

      bulkDeleteBtn.disabled = true;
      bulkDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting ' + selected.length + ' images...';
      bulkForm.submit();
    });
  }

  if (search) search.addEventListener('input', updateSearch);
  updateSearch();
})();

// ── Library (mediaGrid: filter + multi-select + bulk delete) ──
(function () {
  const section = document.getElementById('librarySection');
  const grid    = document.getElementById('mediaGrid');
  if (!grid || !section) return;

  const cards          = Array.from(grid.querySelectorAll('.media-card'));
  const search         = document.getElementById('mediaSearch');
  const filters        = document.querySelectorAll('.media-filter-btn');
  const noRes          = document.getElementById('mediaNoResults');
  const countEl        = document.getElementById('mediaCount');
  const toggleBtn      = document.getElementById('librarySelectToggleBtn');
  const toggleText     = document.getElementById('librarySelectToggleText');
  const bulkForm       = document.getElementById('libraryBulkForm');
  const selectedCountEl= document.getElementById('librarySelectedCount');
  const visibleCountEl = document.getElementById('libraryVisibleCount');
  const deleteCountEl  = document.getElementById('libraryDeleteCountBadge');
  const selectAllBtn   = document.getElementById('librarySelectAllBtn');
  const deselectAllBtn = document.getElementById('libraryDeselectAllBtn');
  const bulkDeleteBtn  = document.getElementById('libraryBulkDeleteBtn');
  const cancelBtn      = document.getElementById('libraryCancelSelectBtn');
  const hiddenInputs   = document.getElementById('libraryBulkHiddenInputs');

  let activeFilter = 'all';
  let isSelectMode = false;
  let lastCheckedIndex = -1;

  function getVisibleCards() {
    return cards.filter(c => c.style.display !== 'none');
  }

  function updateCounts() {
    const visibleCards = getVisibleCards();
    const checkedBoxes = cards.map(c => c.querySelector('.library-item-check')).filter(cb => cb && cb.checked);
    const checkedCount = checkedBoxes.length;

    if (selectedCountEl) selectedCountEl.textContent = checkedCount;
    if (deleteCountEl) deleteCountEl.textContent = checkedCount;
    if (visibleCountEl) visibleCountEl.textContent = visibleCards.length;

    if (bulkDeleteBtn) bulkDeleteBtn.disabled = checkedCount === 0;

    cards.forEach(card => {
      const cb = card.querySelector('.library-item-check');
      card.classList.toggle('is-selected', !!(cb && cb.checked));
    });

    if (checkedCount > 0 && !isSelectMode) {
      setSelectMode(true);
    }
  }

  function setSelectMode(active) {
    isSelectMode = active;
    section.classList.toggle('media-select-mode', active);
    if (toggleBtn) {
      toggleBtn.classList.toggle('btn-select-mode-active', active);
      if (active) {
        toggleBtn.classList.remove('btn-secondary');
        toggleBtn.classList.add('btn-primary');
      } else {
        toggleBtn.classList.add('btn-secondary');
        toggleBtn.classList.remove('btn-primary');
      }
    }
    if (toggleText) toggleText.textContent = active ? 'Exit Selection' : 'Select multiple';
    if (bulkForm) bulkForm.style.display = active ? 'block' : 'none';

    if (!active) {
      cards.forEach(card => {
        const cb = card.querySelector('.library-item-check');
        if (cb) cb.checked = false;
        card.classList.remove('is-selected');
      });
      lastCheckedIndex = -1;
      updateCounts();
    }
  }

  function update() {
    const q = search ? search.value.trim().toLowerCase() : '';
    let visible = 0;
    cards.forEach(card => {
      const typeOk = activeFilter === 'all' || card.dataset.type === activeFilter;
      const textOk = !q || card.dataset.title.includes(q) || card.dataset.name.includes(q);
      const show   = typeOk && textOk;
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (noRes) noRes.style.display = visible === 0 ? '' : 'none';
    if (countEl) countEl.textContent = '(' + visible + ' of <?= count($mediaItems) ?>)';
    updateCounts();
  }

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      setSelectMode(!isSelectMode);
    });
  }

  if (cancelBtn) {
    cancelBtn.addEventListener('click', () => {
      setSelectMode(false);
    });
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener('click', () => {
      const visible = getVisibleCards();
      visible.forEach(card => {
        const cb = card.querySelector('.library-item-check');
        if (cb && !cb.disabled) cb.checked = true;
      });
      updateCounts();
    });
  }

  if (deselectAllBtn) {
    deselectAllBtn.addEventListener('click', () => {
      cards.forEach(card => {
        const cb = card.querySelector('.library-item-check');
        if (cb) cb.checked = false;
      });
      updateCounts();
    });
  }

  cards.forEach((card, idx) => {
    const cb = card.querySelector('.library-item-check');
    if (!cb) return;

    cb.addEventListener('click', (e) => {
      e.stopPropagation();
      if (e.shiftKey && lastCheckedIndex !== -1 && cb.checked) {
        const start = Math.min(lastCheckedIndex, idx);
        const end = Math.max(lastCheckedIndex, idx);
        for (let i = start; i <= end; i++) {
          const targetCb = cards[i]?.querySelector('.library-item-check');
          if (targetCb && !targetCb.disabled && cards[i].style.display !== 'none') {
            targetCb.checked = true;
          }
        }
      }
      lastCheckedIndex = idx;
      updateCounts();
    });

    card.addEventListener('click', (e) => {
      if (!isSelectMode) return;
      if (e.target.closest('button, a, input, form, label')) return;
      cb.checked = !cb.checked;
      lastCheckedIndex = idx;
      updateCounts();
    });
  });

  if (bulkDeleteBtn && bulkForm && hiddenInputs) {
    bulkDeleteBtn.addEventListener('click', () => {
      const selected = cards
        .map(c => c.querySelector('.library-item-check'))
        .filter(cb => cb && cb.checked && !cb.disabled)
        .map(cb => parseInt(cb.value, 10))
        .filter(id => id > 0);

      if (selected.length === 0) {
        alert('Please select at least one media item to delete.');
        return;
      }

      const confirmMsg = 'Are you sure you want to permanently delete ' + selected.length + ' selected media ' +
        (selected.length === 1 ? 'item' : 'items') + '?\n\n' +
        'This will remove files from the server and cannot be undone!';

      if (!confirm(confirmMsg)) return;

      hiddenInputs.innerHTML = '';
      selected.forEach(id => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'media_ids[]';
        input.value = id;
        hiddenInputs.appendChild(input);
      });

      bulkDeleteBtn.disabled = true;
      bulkDeleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting ' + selected.length + ' items...';
      bulkForm.submit();
    });
  }

  if (search) search.addEventListener('input', update);

  filters.forEach(btn => {
    btn.addEventListener('click', () => {
      filters.forEach(b => { b.classList.remove('active'); b.classList.add('btn-ghost'); b.classList.remove('btn-secondary'); });
      btn.classList.add('active', 'btn-secondary');
      btn.classList.remove('btn-ghost');
      activeFilter = btn.dataset.filter;
      update();
    });
  });

  update();
})();

(function () {
  const form      = document.getElementById('mediaUploadForm');
  const dropzone  = document.getElementById('mediaDropzone');
  const fileInput = document.getElementById('media_files');
  const tray      = document.getElementById('selectedFilesTray');
  const list      = document.getElementById('selectedFilesList');
  const countEl   = document.getElementById('selectedFilesCount');
  const sizeEl    = document.getElementById('selectedFilesSize');
  const clearBtn  = document.getElementById('clearSelectionBtn');
  const submitBtn = document.getElementById('uploadSubmitBtn');
  const btnText   = document.getElementById('uploadBtnText');

  if (!form || !dropzone || !fileInput) return;

  let dt = new DataTransfer();

  function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
  }

  function getFileIcon(filename, type) {
    if (type.startsWith('image/')) return 'fa-image';
    if (type.startsWith('video/')) return 'fa-film';
    const ext = filename.split('.').pop().toLowerCase();
    if (ext === 'pdf') return 'fa-file-pdf';
    if (['doc', 'docx'].includes(ext)) return 'fa-file-word';
    return 'fa-file';
  }

  function syncInput() {
    fileInput.files = dt.files;
    renderFiles();
  }

  function renderFiles() {
    list.innerHTML = '';
    const files = Array.from(dt.files);

    if (files.length === 0) {
      tray.style.display = 'none';
      if (btnText) btnText.textContent = 'Upload to Library';
      fileInput.required = true;
      return;
    }

    fileInput.required = false;
    tray.style.display = 'block';

    let totalBytes = 0;
    files.forEach((file, index) => {
      totalBytes += file.size;

      const chip = document.createElement('div');
      chip.className = 'selected-file-chip';

      const thumb = document.createElement('div');
      thumb.className = 'chip-thumb';

      if (file.type.startsWith('image/')) {
        const img = document.createElement('img');
        const url = URL.createObjectURL(file);
        img.src = url;
        img.onload = () => URL.revokeObjectURL(url);
        thumb.appendChild(img);
      } else {
        const icon = document.createElement('i');
        icon.className = 'fas ' + getFileIcon(file.name, file.type);
        thumb.appendChild(icon);
      }

      const info = document.createElement('div');
      info.className = 'chip-info';

      const name = document.createElement('span');
      name.className = 'chip-name';
      name.title = file.name;
      name.textContent = file.name;

      const meta = document.createElement('span');
      meta.className = 'chip-meta';
      meta.textContent = formatBytes(file.size);

      info.appendChild(name);
      info.appendChild(meta);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'chip-remove';
      removeBtn.title = 'Remove ' + file.name;
      removeBtn.setAttribute('aria-label', 'Remove ' + file.name);
      removeBtn.innerHTML = '<i class="fas fa-times"></i>';
      removeBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        removeFileAt(index);
      });

      chip.appendChild(thumb);
      chip.appendChild(info);
      chip.appendChild(removeBtn);
      list.appendChild(chip);
    });

    if (countEl) countEl.textContent = files.length + (files.length === 1 ? ' file selected' : ' files selected');
    if (sizeEl) sizeEl.textContent = '(' + formatBytes(totalBytes) + ')';
    if (btnText) {
      btnText.textContent = files.length === 1
        ? 'Upload 1 File to Library'
        : 'Upload ' + files.length + ' Files to Library';
    }
  }

  function addFiles(newFiles) {
    for (let i = 0; i < newFiles.length; i++) {
      dt.items.add(newFiles[i]);
    }
    syncInput();
  }

  function removeFileAt(index) {
    const newDt = new DataTransfer();
    for (let i = 0; i < dt.files.length; i++) {
      if (i !== index) {
        newDt.items.add(dt.files[i]);
      }
    }
    dt = newDt;
    syncInput();
  }

  function clearAll() {
    dt = new DataTransfer();
    syncInput();
  }

  if (clearBtn) clearBtn.addEventListener('click', clearAll);

  dropzone.addEventListener('click', (e) => {
    if (e.target !== fileInput) {
      fileInput.click();
    }
  });

  dropzone.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      fileInput.click();
    }
  });

  fileInput.addEventListener('change', () => {
    if (fileInput.files && fileInput.files.length > 0) {
      addFiles(fileInput.files);
    }
  });

  ['dragenter', 'dragover'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.add('drag-active');
    });
  });

  ['dragleave', 'drop'].forEach(eventName => {
    dropzone.addEventListener(eventName, (e) => {
      e.preventDefault();
      e.stopPropagation();
      dropzone.classList.remove('drag-active');
    });
  });

  dropzone.addEventListener('drop', (e) => {
    const droppedFiles = e.dataTransfer ? e.dataTransfer.files : null;
    if (droppedFiles && droppedFiles.length > 0) {
      addFiles(droppedFiles);
    }
  });

  form.addEventListener('submit', (e) => {
    if (!fileInput.files || fileInput.files.length === 0) {
      e.preventDefault();
      alert('Please select at least one file to upload.');
      return;
    }
    if (submitBtn) {
      submitBtn.disabled = true;
      const count = fileInput.files.length;
      submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading &amp; Optimising (' + count + ' ' + (count === 1 ? 'file' : 'files') + ')...';
    }
  });
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
