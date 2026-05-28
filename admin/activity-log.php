<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle  = 'Activity Log';
$activeNav  = 'activity-log';
$errorMessage = '';

// CSV export
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    cms_require_role('super_admin');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity-log-' . date('Y-m-d') . '.csv"');
    echo cms_export_activity_log_csv();
    exit;
}

if (cms_is_post()) {
    try {
        cms_require_csrf();
        cms_require_role('super_admin');
        if ((string) cms_post('action') === 'clear_log') {
            cms_clear_activity_log();
            cms_flash_set('success', 'Activity log cleared.');
            cms_redirect('admin/activity-log.php');
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

$page    = max(1, (int) cms_query('p', '1'));
$perPage = 50;
$offset  = ($page - 1) * $perPage;
$total   = cms_activity_log_count();
$pages   = max(1, (int) ceil($total / $perPage));
$log     = cms_get_activity_log($perPage, $offset);

$actionLabels = [
    'login'              => ['Login',              'fa-right-to-bracket', 'tag-blue'],
    'save_post'          => ['Saved Post',          'fa-newspaper',        'tag-green'],
    'delete_post'        => ['Deleted Post',        'fa-trash',            'tag-red'],
    'bulk_delete'        => ['Bulk Delete',         'fa-trash-can',        'tag-red'],
    'bulk_publish'       => ['Bulk Publish',        'fa-upload',           'tag-green'],
    'bulk_draft'         => ['Bulk Draft',          'fa-file',             'tag-yellow'],
    'save_page'          => ['Saved Page',          'fa-laptop-code',      'tag-green'],
    'delete_page'        => ['Deleted Page',        'fa-trash',            'tag-red'],
    'create_form'        => ['Created Form',        'fa-clipboard-list',   'tag-blue'],
    'update_form'        => ['Updated Form',        'fa-clipboard-list',   'tag-green'],
    'delete_form'        => ['Deleted Form',        'fa-clipboard-list',   'tag-red'],
    'create_custom_post' => ['Created Entry',       'fa-plus',             'tag-blue'],
    'update_custom_post' => ['Updated Entry',       'fa-pen',              'tag-green'],
    'delete_custom_post' => ['Deleted Entry',       'fa-trash',            'tag-red'],
];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-clock-rotate-left"></i> Audit Trail</span>
    <h1 class="admin-page-title">Activity log</h1>
    <p class="admin-page-copy">Every content save, delete, and login recorded with admin name, timestamp, and IP address.</p>
  </div>
  <div style="display:flex;gap:10px;align-items:center">
    <a href="?action=export_csv" class="admin-btn admin-btn-outline"><i class="fas fa-download"></i> Export CSV</a>
  <?php if (cms_current_admin_can('super_admin')): ?>
  <form method="post" onsubmit="return confirm('Clear the entire activity log?')">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="clear_log" />
    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Clear Log</button>
  </form>
  <?php endif; ?>
  </div>
</section>

<?php if ($errorMessage !== ''): ?>
  <div class="flash flash-error"><?= cms_e($errorMessage) ?></div>
<?php endif; ?>

<section class="table-card">
  <div class="toolbar">
    <div>
      <h3>Recent actions <span class="admin-kicker" style="font-size:.75rem"><?= number_format($total) ?> total</span></h3>
    </div>
  </div>

  <?php if ($log === []): ?>
    <div class="empty-state">No activity recorded yet. Actions will appear here as admins use the CMS.</div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Action</th>
            <th>Admin</th>
            <th>Entity</th>
            <th>IP Address</th>
            <th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($log as $entry): ?>
            <?php
            $meta  = $actionLabels[(string) $entry['action']] ?? [ucwords(str_replace('_', ' ', (string) $entry['action'])), 'fa-circle', 'tag-blue'];
            $label = $meta[0];
            $icon  = $meta[1];
            $tagClass = $meta[2];
            ?>
            <tr>
              <td>
                <span class="status-badge <?= cms_e($tagClass) ?>">
                  <i class="fas <?= cms_e($icon) ?>"></i> <?= cms_e($label) ?>
                </span>
              </td>
              <td><strong><?= cms_e((string) $entry['admin_name']) ?></strong></td>
              <td>
                <?php if ($entry['entity_label'] !== ''): ?>
                  <span class="muted"><?= cms_e((string) $entry['entity_type']) ?></span>
                  <?= cms_e((string) $entry['entity_label']) ?>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td><code><?= cms_e((string) $entry['ip_address']) ?></code></td>
              <td><?= cms_e(cms_format_date((string) $entry['created_at'], 'M j, Y g:i A')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="pagination" style="padding:16px 20px;display:flex;gap:8px;align-items:center">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?p=<?= $i ?>" class="btn <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?> btn-sm"><?= $i ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
