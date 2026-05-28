<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle = 'Revisions';
$activeNav = 'revisions';
$flash     = [];

// ── POST: restore revision ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_require_csrf();
    $revId       = (int) ($_POST['revision_id'] ?? 0);
    $contentType = (string) ($_POST['content_type'] ?? '');
    $contentId   = (int) ($_POST['content_id'] ?? 0);

    if ($revId > 0 && in_array($contentType, ['post', 'page'], true) && $contentId > 0) {
        $rev = cms_get_revision($revId);
        if (is_array($rev)) {
            $snapshot = json_decode((string) $rev['snapshot_json'], true);
            if (is_array($snapshot)) {
                $admin = cms_current_admin();
                $adminId = (int) ($admin['id'] ?? 0);
                if ($contentType === 'post') {
                    $post = cms_get_post($contentId);
                    if (is_array($post)) {
                        cms_save_revision('post', $contentId, $post, $adminId);
                    }
                    cms_db()->prepare(
                        'UPDATE blog_posts SET title=:t, content_html=:c, excerpt=:e, meta_title=:mt, meta_description=:md, updated_by=:ub WHERE id=:id'
                    )->execute([
                        't' => $snapshot['title'] ?? '', 'c' => $snapshot['content_html'] ?? '',
                        'e' => $snapshot['excerpt'] ?? '', 'mt' => $snapshot['meta_title'] ?? '',
                        'md' => $snapshot['meta_description'] ?? '', 'ub' => $adminId, 'id' => $contentId,
                    ]);
                } else {
                    $page = cms_get_page($contentId);
                    if (is_array($page)) {
                        cms_save_revision('page', $contentId, $page, $adminId);
                    }
                    cms_db()->prepare(
                        'UPDATE cms_pages SET title=:t, page_builder_json=:pb, custom_html=:ch, meta_title=:mt, meta_description=:md, updated_by=:ub WHERE id=:id'
                    )->execute([
                        't' => $snapshot['title'] ?? '', 'pb' => $snapshot['page_builder_json'] ?? '[]',
                        'ch' => $snapshot['custom_html'] ?? '', 'mt' => $snapshot['meta_title'] ?? '',
                        'md' => $snapshot['meta_description'] ?? '', 'ub' => $adminId, 'id' => $contentId,
                    ]);
                }
                cms_log_activity('revision_restore', $contentType, $contentId, 'Restored revision #' . $revId);
                $flash = ['type' => 'success', 'message' => 'Revision #' . $revId . ' restored successfully.'];
            }
        }
    }
}

// ── View: list revisions for a content item ──────────────────────────────────
$viewType = in_array($_GET['type'] ?? '', ['post', 'page'], true) ? $_GET['type'] : '';
$viewId   = (int) ($_GET['id'] ?? 0);
$compareA = (int) ($_GET['a'] ?? 0);
$compareB = (int) ($_GET['b'] ?? 0);
$revisions = ($viewType !== '' && $viewId > 0) ? cms_get_revisions($viewType, $viewId) : [];

// Recent revisions across all content for the overview
$recentRevisions = [];
if (cms_table_exists('cms_revisions')) {
    $recentRevisions = cms_db()->query(
        'SELECT r.*, u.full_name AS editor_name FROM cms_revisions r
         LEFT JOIN admin_users u ON u.id = r.created_by
         ORDER BY r.created_at DESC LIMIT 50'
    )->fetchAll() ?: [];
}
$revisionCount = count($recentRevisions);
$postRevisionCount = count(array_filter($recentRevisions, fn($r) => ($r['content_type'] ?? '') === 'post'));
$pageRevisionCount = count(array_filter($recentRevisions, fn($r) => ($r['content_type'] ?? '') === 'page'));

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-code-compare"></i> History</span>
    <h1 class="admin-page-title">Content Revisions</h1>
    <p class="admin-page-copy">Browse and restore previous versions of posts and pages.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<section class="utility-metrics">
  <article class="utility-metric">
    <i class="fas fa-clock-rotate-left"></i>
    <span>Recent Saves</span>
    <strong><?= $revisionCount ?></strong>
    <p>Latest revision snapshots available here.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-newspaper"></i>
    <span>Post Revisions</span>
    <strong><?= $postRevisionCount ?></strong>
    <p>Blog content snapshots in the recent list.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-file-lines"></i>
    <span>Page Revisions</span>
    <strong><?= $pageRevisionCount ?></strong>
    <p>CMS page snapshots in the recent list.</p>
  </article>
</section>

<?php if ($viewType !== '' && $viewId > 0): ?>
<!-- Revision list for specific content -->
<div class="admin-card" style="margin-bottom:24px">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-code-compare"></i> Revisions for <?= cms_e(ucfirst($viewType)) ?> #<?= (int) $viewId ?></h2>
      <p>Preview a snapshot before restoring. The current version is saved before restore.</p>
    </div>
    <a href="?" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-arrow-left"></i> All Revisions</a>
  </div>
  <?php if ($revisions === []): ?>
    <div class="empty-state">No revisions found.</div>
  <?php else: ?>
  <table class="admin-table">
    <thead><tr><th>#</th><th>Title (snapshot)</th><th>Editor</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($revisions as $rev):
      $snap = json_decode((string) $rev['snapshot_json'], true);
      $snapTitle = is_array($snap) ? ($snap['title'] ?? '—') : '—';
    ?>
      <tr>
        <td><?= (int) $rev['id'] ?></td>
        <td><?= cms_e($snapTitle) ?></td>
        <td><?= cms_e($rev['editor_name'] ?? 'Unknown') ?></td>
        <td><?= cms_e(date('d M Y H:i', strtotime($rev['created_at']))) ?></td>
        <td>
          <button type="button" class="admin-btn admin-btn-outline admin-btn-sm"
            onclick="toggleSnapshot(<?= (int) $rev['id'] ?>)">
            <i class="fas fa-eye"></i> Preview
          </button>
          <form method="post" style="display:inline" onsubmit="return confirm('Restore this revision? Current version will be saved first.')">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="revision_id" value="<?= (int) $rev['id'] ?>">
            <input type="hidden" name="content_type" value="<?= cms_e($viewType) ?>">
            <input type="hidden" name="content_id" value="<?= (int) $viewId ?>">
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-rotate-left"></i> Restore</button>
          </form>
        </td>
      </tr>
      <tr id="snap-<?= (int) $rev['id'] ?>" style="display:none">
        <td colspan="5">
          <div class="revision-preview">
            <strong>Snapshot fields:</strong>
            <?php if (is_array($snap)): ?>
              <ul style="margin:8px 0 0;padding-left:20px">
                <?php foreach (['title','meta_title','meta_description','status','published_at'] as $field):
                  if (!isset($snap[$field])) continue; ?>
                  <li><strong><?= cms_e($field) ?>:</strong> <?= cms_e((string) $snap[$field]) ?></li>
                <?php endforeach; ?>
                <?php if (!empty($snap['content_html'])): ?>
                  <li><strong>content_html:</strong> <?= number_format(strlen($snap['content_html'])) ?> chars</li>
                <?php endif; ?>
              </ul>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php else: ?>
<!-- Overview: recent revisions -->
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-timeline"></i> Recent Revisions</h2>
      <p>Every edit creates a rollback point for safer publishing.</p>
    </div>
  </div>
  <?php if ($recentRevisions === []): ?>
    <div class="empty-state">No revisions recorded yet. Revisions are saved automatically when you edit posts or pages.</div>
  <?php else: ?>
  <table class="admin-table">
    <thead><tr><th>Type</th><th>ID</th><th>Editor</th><th>Saved At</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recentRevisions as $rev): ?>
      <tr>
        <td><span class="admin-badge"><?= cms_e($rev['content_type']) ?></span></td>
        <td>#<?= (int) $rev['content_id'] ?></td>
        <td><?= cms_e($rev['editor_name'] ?? 'Unknown') ?></td>
        <td><?= cms_e(date('d M Y H:i', strtotime($rev['created_at']))) ?></td>
        <td>
          <a href="?type=<?= urlencode($rev['content_type']) ?>&id=<?= (int) $rev['content_id'] ?>"
             class="admin-btn admin-btn-outline admin-btn-sm">
            <i class="fas fa-list"></i> View Revisions
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function toggleSnapshot(id) {
  const row = document.getElementById('snap-' + id);
  if (row) row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
