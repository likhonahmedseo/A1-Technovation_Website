<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle = 'hreflang Manager';
$activeNav = 'hreflang';
$flash     = [];

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'save_group') {
        $groupKey = trim((string) ($_POST['group_key'] ?? ''));
        if ($groupKey === '') {
            $groupKey = 'group-' . bin2hex(random_bytes(4));
        }
        $locales = (array) ($_POST['locale'] ?? []);
        $urls    = (array) ($_POST['url'] ?? []);
        $labels  = (array) ($_POST['label'] ?? []);
        $entries = [];
        foreach ($locales as $i => $locale) {
            $locale = trim((string) $locale);
            $url    = trim((string) ($urls[$i] ?? ''));
            if ($locale !== '' && $url !== '') {
                $entries[] = ['locale' => $locale, 'url' => $url, 'page_label' => $labels[$i] ?? ''];
            }
        }
        if ($entries !== []) {
            cms_hreflang_save_group($groupKey, $entries);
            cms_log_activity('hreflang_save', 'hreflang', 0, $groupKey);
            $flash = ['type' => 'success', 'message' => 'Group "' . htmlspecialchars($groupKey) . '" saved.'];
        }
    }

    if ($action === 'delete_group') {
        $groupKey = trim((string) ($_POST['group_key'] ?? ''));
        if ($groupKey !== '') {
            cms_hreflang_delete_group($groupKey);
            $flash = ['type' => 'success', 'message' => 'Group deleted.'];
        }
    }
}

$groups = cms_hreflang_groups();
$editGroup = isset($_GET['edit']) ? trim($_GET['edit']) : null;
$editData  = ($editGroup !== null && isset($groups[$editGroup])) ? $groups[$editGroup] : [];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-language"></i> Internationalisation</span>
    <h1 class="admin-page-title">hreflang Manager</h1>
    <p class="admin-page-copy">Link translated versions of your pages so Google serves the right language to the right audience.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<section class="utility-metrics">
  <article class="utility-metric">
    <i class="fas fa-layer-group"></i>
    <span>Language Groups</span>
    <strong><?= count($groups) ?></strong>
    <p>Page families with alternate URLs.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-language"></i>
    <span>Total Alternates</span>
    <strong><?= array_sum(array_map('count', $groups)) ?></strong>
    <p>Locale URLs available for search engines.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-globe"></i>
    <span>Default Locale</span>
    <strong>en</strong>
    <p>Add x-default when one URL should serve global users.</p>
  </article>
</section>

<div class="utility-grid">

<!-- Form -->
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-pen-nib"></i> <?= $editGroup ? 'Edit Group: ' . cms_e($editGroup) : 'New Language Group' ?></h2>
      <p>Create one group per translated page family.</p>
    </div>
  </div>
  <form method="post" class="admin-form" id="hreflang-form">
    <?= cms_csrf_field() ?>
    <input type="hidden" name="action" value="save_group">
    <div class="admin-form-row">
      <label>Group Key <small>(internal name, e.g. "home", "seo-services")</small></label>
      <input type="text" name="group_key" value="<?= cms_e($editGroup ?? '') ?>" placeholder="e.g. seo-services-page" required>
    </div>
    <div id="hreflang-rows">
      <?php
      $rows = $editData ?: [
          ['locale' => 'en', 'url' => '', 'page_label' => ''],
          ['locale' => 'ar', 'url' => '', 'page_label' => ''],
      ];
      foreach ($rows as $i => $row): ?>
      <div class="hreflang-row utility-row">
        <input class="admin-input" type="text" name="locale[]" value="<?= cms_e($row['locale']) ?>" placeholder="en" style="font-family:monospace">
        <input class="admin-input" type="url" name="url[]" value="<?= cms_e($row['url']) ?>" placeholder="https://a1technovation.com/...">
        <input class="admin-input" type="text" name="label[]" value="<?= cms_e($row['page_label'] ?? '') ?>" placeholder="Label">
        <button type="button" onclick="this.closest('.hreflang-row').remove()" class="admin-btn admin-btn-danger admin-btn-sm"><i class="fas fa-times"></i></button>
      </div>
      <?php endforeach; ?>
    </div>
    <button type="button" id="add-row" class="admin-btn admin-btn-outline admin-btn-sm" style="margin-bottom:16px"><i class="fas fa-plus"></i> Add Locale</button>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Save Group</button>
      <?php if ($editGroup): ?>
        <a href="?" class="admin-btn admin-btn-outline">Cancel</a>
      <?php endif; ?>
    </div>
  </form>
  <div class="utility-callout" style="margin-top:20px">
    <strong><i class="fas fa-circle-info"></i> Common locales</strong>
    <code>en</code> · <code>en-US</code> · <code>en-GB</code> · <code>en-AU</code> · <code>en-CA</code> · <code>ar</code> · <code>fr</code> · <code>de</code> · <code>x-default</code>
    <span>These tags are output in your page <code>&lt;head&gt;</code> via <code>cms_hreflang_tags($currentUrl)</code>.</span>
  </div>
</div>

<!-- Groups list -->
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-list-check"></i> All Language Groups</h2>
      <p>Quickly edit, audit, or remove alternate URL sets.</p>
    </div>
  </div>
  <?php if ($groups === []): ?>
    <div class="empty-state">No groups yet. Create one to link translated pages.</div>
  <?php else: ?>
  <table class="admin-table">
    <thead><tr><th>Group</th><th>Locales</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($groups as $key => $entries): ?>
      <tr>
        <td><strong><?= cms_e($key) ?></strong></td>
        <td>
          <?php foreach ($entries as $e): ?>
            <span class="locale-pill" style="margin:2px">
              <?= cms_e($e['locale']) ?>
            </span>
          <?php endforeach; ?>
        </td>
        <td style="white-space:nowrap">
          <a href="?edit=<?= urlencode($key) ?>" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-pen"></i></a>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this group?')">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="delete_group">
            <input type="hidden" name="group_key" value="<?= cms_e($key) ?>">
            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

</div>

<script>
document.getElementById('add-row').addEventListener('click', function () {
  const container = document.getElementById('hreflang-rows');
  const div = document.createElement('div');
  div.className = 'hreflang-row utility-row';
  div.innerHTML = `
    <input class="admin-input" type="text" name="locale[]" placeholder="en-US" style="font-family:monospace">
    <input class="admin-input" type="url" name="url[]" placeholder="https://...">
    <input class="admin-input" type="text" name="label[]" placeholder="Label">
    <button type="button" onclick="this.closest('.hreflang-row').remove()" class="admin-btn admin-btn-danger admin-btn-sm"><i class="fas fa-times"></i></button>
  `;
  container.appendChild(div);
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
