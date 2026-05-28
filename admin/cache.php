<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle = 'Page Cache';
$activeNav = 'cache';
$flash     = [];

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'toggle_cache') {
        $enabled = (int) ($_POST['page_cache_enabled'] ?? 0);
        $ttl     = max(60, (int) ($_POST['page_cache_ttl'] ?? 3600));
        $settings = cms_site_settings();
        $settings['page_cache_enabled'] = $enabled;
        $settings['page_cache_ttl']     = $ttl;
        cms_save_site_settings($settings);
        $flash = ['type' => 'success', 'message' => 'Cache settings saved.'];
    }

    if ($action === 'purge_all') {
        $count = cms_cache_purge_all();
        cms_log_activity('cache_purge_all', 'cache', 0, "Purged {$count} cached pages");
        $flash = ['type' => 'success', 'message' => "Purged {$count} cached page(s)."];
    }

    if ($action === 'purge_url') {
        $url = trim((string) ($_POST['url'] ?? ''));
        if ($url !== '') {
            cms_cache_purge_url($url);
            $flash = ['type' => 'success', 'message' => 'Cache cleared for: ' . htmlspecialchars($url)];
        }
    }

    if ($action === 'save_cdn') {
        $cdn = trim((string) ($_POST['cdn_base_url'] ?? ''));
        $settings = cms_site_settings();
        $settings['cdn_base_url'] = $cdn;
        cms_save_site_settings($settings);
        $flash = ['type' => 'success', 'message' => 'CDN settings saved.'];
    }
}

$siteSettings = cms_site_settings();
$cacheEnabled = (bool) ($siteSettings['page_cache_enabled'] ?? false);
$cacheTtl     = (int) ($siteSettings['page_cache_ttl'] ?? 3600);
$cdnBase      = (string) ($siteSettings['cdn_base_url'] ?? '');
$stats        = cms_cache_stats();
$cachedPages  = cms_cache_list();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-bolt"></i> Performance</span>
    <h1 class="admin-page-title">Page Cache & CDN</h1>
    <p class="admin-page-copy">Enable HTML page caching and CDN URL rewriting for maximum speed.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<section class="utility-metrics">
  <article class="utility-metric">
    <i class="fas fa-file-code"></i>
    <span>Cached Pages</span>
    <strong><?= (int) $stats['file_count'] ?></strong>
    <p>HTML snapshots currently indexed.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-hard-drive"></i>
    <span>Cache Size</span>
    <strong><?= number_format($stats['total_bytes'] / 1024, 1) ?> KB</strong>
    <p>Total disk space used by cached pages.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-toggle-<?= $cacheEnabled ? 'on' : 'off' ?>"></i>
    <span>Cache Status</span>
    <strong><?= $cacheEnabled ? 'Enabled' : 'Disabled' ?></strong>
    <p><?= $cacheEnabled ? 'Anonymous visitors can receive cached HTML.' : 'Pages are generated live until cache is enabled.' ?></p>
  </article>
</section>

<div class="utility-grid even" style="margin-bottom:28px">

<!-- Cache Settings -->
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-bolt"></i> Page Cache Settings</h2>
      <p>Control full-page HTML caching for public visitors.</p>
    </div>
    <span class="utility-status <?= $cacheEnabled ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $cacheEnabled ? 'On' : 'Off' ?></span>
  </div>
  <form method="post" class="admin-form">
    <?= cms_csrf_field() ?>
    <input type="hidden" name="action" value="toggle_cache">
    <div class="admin-form-row">
      <label class="cache-toggle-card">
        <input type="checkbox" name="page_cache_enabled" value="1" <?= $cacheEnabled ? 'checked' : '' ?>>
        <span class="cache-toggle-visual" aria-hidden="true"></span>
        <span class="cache-toggle-copy">
          <strong><?= $cacheEnabled ? 'HTML page cache is enabled' : 'Enable HTML page cache' ?></strong>
          <small>Caches full page HTML to disk, bypassing PHP/DB for anonymous visitors.</small>
        </span>
        <span class="cache-toggle-state"><?= $cacheEnabled ? 'On' : 'Off' ?></span>
      </label>
    </div>
    <div class="admin-form-row">
      <label>Cache TTL (seconds)</label>
      <input type="number" name="page_cache_ttl" value="<?= (int) $cacheTtl ?>" min="60" max="86400">
      <small>How long cached pages are served before re-generating. Default: 3600 (1 hour).</small>
    </div>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Save</button>
    </div>
  </form>
</div>

<!-- CDN Settings -->
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-cloud"></i> CDN Integration</h2>
      <p>Rewrite uploaded media URLs through your CDN domain.</p>
    </div>
    <span class="utility-status <?= $cdnBase !== '' ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $cdnBase !== '' ? 'Configured' : 'Not set' ?></span>
  </div>
  <form method="post" class="admin-form">
    <?= cms_csrf_field() ?>
    <input type="hidden" name="action" value="save_cdn">
    <div class="admin-form-row">
      <label>CDN Base URL</label>
      <input type="url" name="cdn_base_url" value="<?= cms_e($cdnBase) ?>" placeholder="https://cdn.yourdomain.com">
      <small>All <code>/assets/uploads/media/</code> URLs will be rewritten to use this CDN domain. Leave blank to disable.</small>
    </div>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Save</button>
    </div>
  </form>
</div>
</div>

<!-- Purge -->
<div class="admin-card" style="margin-bottom:28px">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-broom"></i> Purge Cache</h2>
      <p>Refresh every cached page or clear one URL after publishing an update.</p>
    </div>
  </div>
  <div class="admin-action-row">
    <form method="post" onsubmit="return confirm('Purge ALL cached pages?')">
      <?= cms_csrf_field() ?>
      <input type="hidden" name="action" value="purge_all">
      <button type="submit" class="admin-btn admin-btn-danger"><i class="fas fa-fire"></i> Purge All Cache</button>
    </form>
    <form method="post" class="admin-action-row" style="flex:1 1 420px">
      <?= cms_csrf_field() ?>
      <input type="hidden" name="action" value="purge_url">
      <input class="admin-input" type="url" name="url" placeholder="https://a1technovation.com/blog/my-post" style="flex:1 1 300px">
      <button type="submit" class="admin-btn admin-btn-outline"><i class="fas fa-broom"></i> Purge URL</button>
    </form>
  </div>
</div>

<!-- Cached pages list -->
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-layer-group"></i> Currently Cached Pages</h2>
      <p>Review cached URLs and clear individual entries when needed.</p>
    </div>
  </div>
  <?php if ($cachedPages === []): ?>
    <div class="empty-state">No pages in cache index. <?= $cacheEnabled ? 'Pages will be cached when first visited.' : 'Enable cache above to start.' ?></div>
  <?php else: ?>
  <table class="admin-table">
    <thead><tr><th>URL</th><th>Cached At</th><th>Expires</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cachedPages as $p): ?>
      <tr>
        <td><a href="<?= cms_e($p['url']) ?>" target="_blank" rel="noopener"><?= cms_e($p['url']) ?></a></td>
        <td><?= cms_e(date('d M Y H:i', strtotime($p['cached_at']))) ?></td>
        <td><?= cms_e($p['expires_at'] ? date('d M Y H:i', strtotime($p['expires_at'])) : '—') ?></td>
        <td>
          <form method="post">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="purge_url">
            <input type="hidden" name="url" value="<?= cms_e($p['url']) ?>">
            <button type="submit" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
