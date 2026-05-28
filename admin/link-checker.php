<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle = 'Broken Link Checker';
$activeNav = 'link-checker';
$results   = [];
$pageReport = [];
$summary   = [
    'url_limit' => 0,
    'links_checked' => 0,
    'content_scanned' => 0,
    'internal_links' => 0,
    'external_links' => 0,
    'broken_links' => 0,
    'affected_items' => 0,
    'clean_links' => 0,
    'limit_reached' => false,
    'unique_urls_checked' => 0,
];
$scanning  = false;
$scanError = '';
$scanLimit = 0;
$insertedUrlsRaw = '';
$insertedOnly = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scan') {
    cms_require_csrf();
    $rawLimit = (int) ($_POST['scan_limit'] ?? 0);
    $limit = $rawLimit <= 0 ? 0 : max(5, min(500, $rawLimit));
    $scanLimit = $limit;
    $insertedUrlsRaw = trim((string) ($_POST['inserted_urls'] ?? ''));
    $insertedUrls = cms_normalize_link_checker_urls($insertedUrlsRaw, cms_url(''));
    $insertedOnly = isset($_POST['inserted_only']) && $insertedUrls !== [];
    try {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        $audit    = cms_run_broken_link_audit($limit, $insertedUrls, !$insertedOnly);
        $summary  = array_merge($summary, (array) ($audit['summary'] ?? []));
        $results  = (array) ($audit['results'] ?? []);
        $pageReport = (array) ($audit['page_report'] ?? []);
        $scanning = true;
        $total    = (int) ($summary['broken_links'] ?? 0);
        cms_log_activity('broken_link_scan', 'seo', 0, "Scan found {$total} broken link(s)");
    } catch (Throwable $e) {
        $scanError = $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-link-slash"></i> SEO Health</span>
    <h1 class="admin-page-title">Broken Link Checker</h1>
    <p class="admin-page-copy">Audit all URLs from static pages, published CMS pages, and blog posts. Catch broken internal paths and unreliable external links before they hurt SEO or user trust.</p>
  </div>
</section>

<section class="utility-grid link-checker-grid">
  <div class="admin-card link-scan-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-magnifying-glass"></i> Run Link Audit</h2>
        <p>Choose a quick URL cap or scan everything. The scanner audits static pages, CMS pages, blog posts, and retries with a lightweight GET request when HEAD is blocked.</p>
      </div>
      <span class="utility-status is-on"><i class="fas fa-gauge-high"></i> All ready</span>
    </div>
    <form method="post" class="admin-form">
      <?= cms_csrf_field() ?>
      <input type="hidden" name="action" value="scan">
      <div class="link-scan-presets" aria-label="Audit size presets">
        <button type="button" class="link-preset<?= $scanLimit === 0 ? ' active' : '' ?>" data-link-limit="0">
          <strong>All</strong>
          <span>URLs</span>
        </button>
        <?php foreach ([50, 100, 250, 500] as $preset): ?>
          <button type="button" class="link-preset<?= $scanLimit === $preset ? ' active' : '' ?>" data-link-limit="<?= $preset ?>">
            <strong><?= $preset ?></strong>
            <span>URLs</span>
          </button>
        <?php endforeach; ?>
      </div>
      <div class="admin-form-row">
        <label for="scan_limit">Audit URL limit</label>
        <input id="scan_limit" type="range" name="scan_limit" value="<?= (int) $scanLimit ?>" min="0" max="500" step="5" data-link-limit-input>
        <div class="link-limit-row">
          <input type="number" value="<?= (int) $scanLimit ?>" min="0" max="500" step="5" data-link-limit-number aria-label="Audit URL limit number">
          <span><?= $scanLimit === 0 ? 'All URLs checked in this run' : 'URLs checked in this run' ?></span>
        </div>
        <small>Use 50-100 for quick checks. Use 0 or the All preset to scan every discovered URL together.</small>
      </div>
      <div class="admin-form-row">
        <label for="inserted_urls">Insert URLs to check</label>
        <textarea id="inserted_urls" name="inserted_urls" rows="5" placeholder="https://example.com/page&#10;/pages/contact&#10;example.com/pricing"><?= cms_e($insertedUrlsRaw) ?></textarea>
        <small>Add one URL per line. Full URLs, root-relative paths, and domains without https:// are accepted.</small>
      </div>
      <label class="checkbox-field link-inserted-only">
        <input type="checkbox" name="inserted_only" value="1" <?= $insertedOnly ? 'checked' : '' ?>>
        <span>
          <strong>Only check inserted URLs</strong>
          <small>Skip the full site audit when you only need to test the pasted list.</small>
        </span>
      </label>
      <div class="admin-form-actions">
        <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-magnifying-glass-chart"></i> Start Audit</button>
      </div>
    </form>
  </div>
  <aside class="admin-card link-workflow-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-shield-heart"></i> Fix Priority</h2>
        <p>Use the result labels to decide what to repair first.</p>
      </div>
    </div>
    <div class="link-priority-list">
      <div><i class="fas fa-circle-xmark"></i><strong>Internal 404s</strong><span>Fix first. They directly damage journeys and crawl paths.</span></div>
      <div><i class="fas fa-triangle-exclamation"></i><strong>Unreachable</strong><span>Retest once, then update or remove the link.</span></div>
      <div><i class="fas fa-up-right-from-square"></i><strong>External 4xx/5xx</strong><span>Replace with a working source or remove the reference.</span></div>
    </div>
  </aside>
</section>

<?php if ($scanError !== ''): ?>
  <div class="admin-flash admin-flash-error"><?= cms_e($scanError) ?></div>
<?php endif; ?>

<?php if ($scanning): ?>
  <?php
  $totalBroken = (int) ($summary['broken_links'] ?? 0);
  ?>
  <section class="utility-metrics link-audit-metrics" aria-label="Link audit summary">
    <article class="utility-metric">
      <i class="fas fa-link"></i>
      <span>URLs Checked</span>
      <strong><?= number_format((int) $summary['links_checked']) ?></strong>
      <p><?= number_format((int) ($summary['unique_urls_checked'] ?? $summary['links_checked'])) ?> unique URL<?= (int) ($summary['unique_urls_checked'] ?? $summary['links_checked']) === 1 ? '' : 's' ?> requested.</p>
    </article>
    <article class="utility-metric">
      <i class="fas fa-circle-xmark"></i>
      <span>Broken Links</span>
      <strong><?= number_format($totalBroken) ?></strong>
      <p><?= number_format((int) $summary['affected_items']) ?> affected content item<?= (int) $summary['affected_items'] === 1 ? '' : 's' ?>.</p>
    </article>
    <article class="utility-metric">
      <i class="fas fa-house"></i>
      <span>Internal</span>
      <strong><?= number_format((int) $summary['internal_links']) ?></strong>
      <p>Links pointing inside the site.</p>
    </article>
    <article class="utility-metric">
      <i class="fas fa-globe"></i>
      <span>External</span>
      <strong><?= number_format((int) $summary['external_links']) ?></strong>
      <p><?= (int) $summary['url_limit'] <= 0 ? 'Limit: All discovered URLs.' : 'Limit: ' . number_format((int) $summary['url_limit']) . ' URLs' . ($summary['limit_reached'] ? ' reached' : '') . '.' ?></p>
    </article>
  </section>

  <div class="link-scan-complete <?= $totalBroken === 0 ? 'is-clean' : 'has-issues' ?>">
    <i class="fas <?= $totalBroken === 0 ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
    <div>
      <strong><?= $totalBroken === 0 ? 'Audit complete. No broken links found.' : 'Audit complete. Broken links need review.' ?></strong>
      <span><?= number_format((int) $summary['content_scanned']) ?> content item<?= (int) $summary['content_scanned'] === 1 ? '' : 's' ?> scanned, <?= number_format((int) $summary['clean_links']) ?> clean URL<?= (int) $summary['clean_links'] === 1 ? '' : 's' ?> confirmed.</span>
    </div>
  </div>

  <?php if ($pageReport !== []): ?>
  <div class="admin-card link-page-report-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-diagram-project"></i> Per-Page Link Report</h2>
        <p>Internal and external links found on each scanned page, including HTTP status for every checked URL.</p>
      </div>
    </div>
    <div class="link-page-report-list">
      <?php foreach ($pageReport as $page): ?>
        <?php
          $pageLinks = (array) ($page['links'] ?? []);
          $internalPageLinks = array_values(array_filter($pageLinks, fn($link) => ($link['type'] ?? '') === 'internal'));
          $externalPageLinks = array_values(array_filter($pageLinks, fn($link) => ($link['type'] ?? '') === 'external'));
          $pageBroken = (int) ($page['broken_count'] ?? 0);
        ?>
        <details class="link-page-report-item">
          <summary>
            <span class="link-page-title">
              <span class="admin-badge"><?= cms_e((string) ($page['type'] ?? 'page')) ?></span>
              <strong><?= cms_e((string) ($page['title'] ?? 'Untitled')) ?></strong>
            </span>
            <span class="link-page-counts">
              <b><?= number_format((int) ($page['internal_count'] ?? 0)) ?> internal</b>
              <b><?= number_format((int) ($page['external_count'] ?? 0)) ?> external</b>
              <b class="<?= $pageBroken > 0 ? 'is-bad' : 'is-good' ?>"><?= number_format($pageBroken) ?> broken</b>
            </span>
          </summary>
          <div class="link-page-report-body">
            <a href="<?= cms_e((string) ($page['url'] ?? '#')) ?>" target="_blank" rel="noopener" class="link-page-url">
              <i class="fas fa-up-right-from-square"></i> <?= cms_e((string) ($page['url'] ?? '')) ?>
            </a>
            <div class="link-page-columns">
              <?php foreach ([['Internal Links', $internalPageLinks, 'internal'], ['External Links', $externalPageLinks, 'external']] as $group): ?>
                <div class="link-page-link-group">
                  <h3>
                    <i class="fas <?= $group[2] === 'internal' ? 'fa-house' : 'fa-globe' ?>"></i>
                    <?= cms_e($group[0]) ?>
                    <span><?= count($group[1]) ?></span>
                  </h3>
                  <?php if ($group[1] === []): ?>
                    <p class="link-empty-note">No <?= cms_e($group[2]) ?> links found on this page.</p>
                  <?php else: ?>
                    <div class="link-page-url-list">
                    <?php foreach ($group[1] as $link): ?>
                      <?php
                        $status = (int) ($link['status'] ?? 0);
                        $statusLabel = $status === 0 ? 'Unreachable' : (string) $status;
                        $ok = (bool) ($link['ok'] ?? false);
                      ?>
                      <div class="link-page-url-row">
                        <a href="<?= cms_e((string) ($link['url'] ?? '')) ?>" target="_blank" rel="noopener"><?= cms_e((string) ($link['url'] ?? '')) ?></a>
                        <span class="health-pill <?= $ok ? 'is-good' : 'is-bad' ?>"><?= cms_e($statusLabel) ?></span>
                      </div>
                    <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </details>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($results !== []): ?>
  <div class="admin-card link-results-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-link-slash"></i> Broken Links Found</h2>
        <p>Open the affected page, update the bad URL, then run the audit again.</p>
      </div>
    </div>
    <?php foreach ($results as $item): ?>
    <div class="link-result-item">
      <div class="link-result-title">
        <strong>
          <span class="admin-badge"><?= cms_e($item['type']) ?></span>
          <?= cms_e($item['title']) ?>
        </strong>
        <a href="<?= cms_e($item['url']) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-outline admin-btn-sm">
          <i class="fas fa-external-link"></i> View Page
        </a>
      </div>
      <div class="link-broken-list">
        <?php foreach ($item['broken'] as $link): ?>
          <?php
            $status = (int) ($link['status'] ?? 0);
            $linkType = (string) ($link['type'] ?? 'external');
            $label = $status === 0 ? 'Unreachable' : (string) $status;
            $isBad = $status === 0 || $status >= 500 || ($linkType === 'internal' && $status >= 400);
          ?>
          <div class="link-broken-row">
            <div>
              <a href="<?= cms_e((string) $link['url']) ?>" target="_blank" rel="noopener"><?= cms_e((string) $link['url']) ?></a>
              <span class="link-kind <?= $linkType === 'internal' ? 'is-internal' : 'is-external' ?>"><?= cms_e($linkType) ?></span>
            </div>
            <span class="health-pill <?= $isBad ? 'is-bad' : 'is-warn' ?>"><?= cms_e($label) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>

<script>
(function () {
  var range = document.querySelector('[data-link-limit-input]');
  var number = document.querySelector('[data-link-limit-number]');
  var presets = document.querySelectorAll('[data-link-limit]');
  if (!range || !number) return;

  function sync(value) {
    var parsed = parseInt(value, 10);
    var next = parsed <= 0 || isNaN(parsed) ? 0 : Math.max(5, Math.min(500, parsed));
    range.value = String(next);
    number.value = String(next);
    presets.forEach(function (button) {
      button.classList.toggle('active', button.getAttribute('data-link-limit') === String(next));
    });
    var label = number.nextElementSibling;
    if (label) {
      label.textContent = next === 0 ? 'All URLs checked in this run' : 'URLs checked in this run';
    }
  }

  range.addEventListener('input', function () { sync(range.value); });
  number.addEventListener('input', function () { sync(number.value); });
  presets.forEach(function (button) {
    button.addEventListener('click', function () { sync(button.getAttribute('data-link-limit')); });
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
