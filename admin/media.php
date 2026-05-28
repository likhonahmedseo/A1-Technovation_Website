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
            $adminId   = (int) ($currentAdmin['id'] ?? 0);
            $uploaded  = cms_upload_media(
                $_FILES['media_file'] ?? [],
                $adminId,
                (string) cms_post('title'),
                (string) cms_post('alt_text')
            );
            // Auto-convert JPEG/PNG to WebP and store webp_path + dimensions
            if (!empty($uploaded['file_path'])) {
                $absPath  = dirname(__DIR__) . DIRECTORY_SEPARATOR . ltrim($uploaded['file_path'], '/\\');
                $webpAbs  = cms_convert_upload_to_webp($absPath, (string) ($uploaded['mime_type'] ?? ''));
                $dims     = cms_image_dimensions($absPath);
                if ($webpAbs !== null || $dims['width'] !== null) {
                    $webpRel = $webpAbs !== null ? str_replace(DIRECTORY_SEPARATOR, '/', ltrim(str_replace(dirname(__DIR__), '', $webpAbs), '/\\')) : null;
                    cms_db()->prepare(
                        'UPDATE media_files SET webp_path=:wp, original_size=:os, width=:w, height=:h WHERE id=:id'
                    )->execute([
                        'wp' => $webpRel, 'os' => (int) ($uploaded['file_size'] ?? 0),
                        'w' => $dims['width'], 'h' => $dims['height'], 'id' => (int) ($uploaded['id'] ?? 0),
                    ]);
                }
            }
            cms_flash_set('success', 'Media uploaded and optimised successfully.');
            cms_redirect('admin/media.php');
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
        <h3>Upload new media</h3>
        <p class="muted">Supported: JPG, PNG, WebP, GIF, SVG, MP4, WebM, and PDF.</p>
      </div>
    </div>

    <form method="post" enctype="multipart/form-data" class="form-grid">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="upload_media" />
      <div class="field">
        <label for="media_file">Select file</label>
        <input id="media_file" type="file" name="media_file" required />
      </div>
      <div class="form-grid two-col">
        <div class="field">
          <label for="title">Title</label>
          <input id="title" type="text" name="title" placeholder="Homepage hero, SEO infographic..." />
        </div>
        <div class="field">
          <label for="alt_text">Alt text</label>
          <input id="alt_text" type="text" name="alt_text" placeholder="Describe the image for accessibility" />
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-cloud-arrow-up"></i> Upload to Library</button>
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

<section class="table-card" style="margin-top:20px">
  <div class="toolbar">
    <div>
      <h3>Site image directory <span id="siteImageCount" style="font-weight:400;color:var(--admin-muted);font-size:.88rem"></span></h3>
      <p class="muted">All reusable images from <code>assets/images</code>. Copy any URL and paste it into page sections, hero backgrounds, galleries, image blocks, or custom HTML.</p>
    </div>
  </div>

  <?php if ($siteImages === []): ?>
    <div class="empty-state">No images found in <code>assets/images</code>.</div>
  <?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:20px;align-items:center">
      <div style="flex:1;min-width:220px;position:relative">
        <i class="fas fa-magnifying-glass" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--admin-muted);pointer-events:none;font-size:.85rem"></i>
        <input id="siteImageSearch" type="search" placeholder="Search images by filename..."
               style="width:100%;padding:10px 12px 10px 36px;border:1.5px solid var(--admin-border);border-radius:10px;font-size:.9rem;background:#fff" />
      </div>
      <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e(cms_url('assets/images/')) ?>"><i class="fas fa-copy"></i> Copy Folder URL</button>
    </div>

    <div class="media-grid" id="siteImageGrid">
      <?php foreach ($siteImages as $image): ?>
        <?php $imageUrl = (string) $image['url']; ?>
        <article class="media-card"
                 data-title="<?= cms_e(strtolower((string) $image['title'])) ?>"
                 data-name="<?= cms_e(strtolower((string) $image['file_name'])) ?>"
                 data-path="<?= cms_e(strtolower((string) $image['file_path'])) ?>">
          <div class="media-preview">
            <img src="<?= cms_e($imageUrl) ?>" alt="<?= cms_e((string) $image['title']) ?>" loading="lazy" />
          </div>
          <div class="media-body">
            <strong style="font-size:.88rem;line-height:1.3;display:block;margin-bottom:2px"><?= cms_e((string) $image['file_name']) ?></strong>
            <span class="helper-text" style="font-size:.78rem"><?= cms_e(strtoupper((string) $image['extension'])) ?> · <?= number_format((float) $image['size'] / 1024, 1) ?> KB</span>
            <input class="media-url" type="text" readonly value="<?= cms_e($imageUrl) ?>" />
            <div class="inline-actions" style="flex-wrap:wrap">
              <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e($imageUrl) ?>"><i class="fas fa-copy"></i> Copy URL</button>
              <a href="<?= cms_e($imageUrl) ?>" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="fas fa-eye"></i> View</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>

    <p id="siteImageNoResults" style="display:none;text-align:center;padding:32px;color:var(--admin-muted)">No images match your search.</p>
  <?php endif; ?>
</section>

<section class="table-card" style="margin-top:20px">
  <div class="toolbar">
    <div>
      <h3>Library <span id="mediaCount" style="font-weight:400;color:var(--admin-muted);font-size:.88rem"></span></h3>
      <p class="muted">All uploads — newest first. Search or filter by type.</p>
    </div>
  </div>

  <?php if ($mediaItems === []): ?>
    <div class="empty-state">No media uploaded yet. Use the upload form above to add your first file.</div>
  <?php else: ?>
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
          <div class="media-preview">
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
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
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
(function () {
  const grid = document.getElementById('siteImageGrid');
  if (!grid) return;

  const cards = Array.from(grid.querySelectorAll('.media-card'));
  const search = document.getElementById('siteImageSearch');
  const noRes = document.getElementById('siteImageNoResults');
  const count = document.getElementById('siteImageCount');

  function update() {
    const q = search ? search.value.trim().toLowerCase() : '';
    let visible = 0;

    cards.forEach(card => {
      const show = !q || card.dataset.title.includes(q) || card.dataset.name.includes(q) || card.dataset.path.includes(q);
      card.style.display = show ? '' : 'none';
      if (show) visible++;
    });

    if (noRes) noRes.style.display = visible === 0 ? '' : 'none';
    if (count) count.textContent = '(' + visible + ' of <?= count($siteImages) ?>)';
  }

  if (search) search.addEventListener('input', update);
  update();
})();

(function () {
  const grid    = document.getElementById('mediaGrid');
  if (!grid) return;

  const cards   = Array.from(grid.querySelectorAll('.media-card'));
  const search  = document.getElementById('mediaSearch');
  const filters = document.querySelectorAll('.media-filter-btn');
  const noRes   = document.getElementById('mediaNoResults');
  const count   = document.getElementById('mediaCount');

  let activeFilter = 'all';

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
    if (count) count.textContent = '(' + visible + ' of <?= count($mediaItems) ?>)';
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
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
