<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('super_admin');

$pageTitle = 'Backup & Export';
$activeNav = 'backup';
$flash = [];

// Handle download requests — these stream a file, so they must happen before any output.
$download = (string) cms_query('download');

if ($download === 'json') {
    cms_log_activity('export_json', 'backup');
    $json = cms_export_content_json();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="cms-backup-' . date('Y-m-d-His') . '.json"');
    header('Content-Length: ' . strlen($json));
    echo $json;
    exit;
}

if ($download === 'posts_csv') {
    cms_log_activity('export_csv', 'backup', 0, 'posts');
    $csv = cms_export_posts_csv();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="blog-posts-' . date('Y-m-d') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

if ($download === 'mail_csv') {
    cms_log_activity('export_csv', 'backup', 0, 'mail copies');
    $csv = cms_export_contact_submissions_csv();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mail-copies-' . date('Y-m-d-His') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

if ($download === 'form_submissions_csv') {
    cms_log_activity('export_csv', 'backup', 0, 'form submissions');
    $csv = cms_export_form_submissions_csv();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="cms-form-submissions-' . date('Y-m-d-His') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'import_json') {
    try {
        cms_require_csrf();
        if ((string) ($_POST['confirm_import'] ?? '') !== '1') {
            throw new RuntimeException('Confirm that you have a fresh backup before importing.');
        }
        $upload = $_FILES['backup_json'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Choose a valid CMS JSON backup file.');
        }
        if ((int) ($upload['size'] ?? 0) > 20 * 1024 * 1024) {
            throw new RuntimeException('Backup file is too large. Maximum size is 20 MB.');
        }
        $raw = file_get_contents((string) $upload['tmp_name']);
        if (!is_string($raw) || trim($raw) === '') {
            throw new RuntimeException('The uploaded backup file is empty.');
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            throw new RuntimeException('The uploaded file is not valid JSON.');
        }
        $imported = cms_import_content_json($payload);
        $totalImported = array_sum($imported);
        cms_log_activity('import_json', 'backup', 0, 'Imported ' . $totalImported . ' CMS row(s)');
        $flash = [
            'type' => 'success',
            'message' => 'Imported ' . number_format($totalImported) . ' row(s): '
                . implode(', ', array_map(fn($key, $count) => str_replace('_', ' ', $key) . ' ' . (int) $count, array_keys($imported), $imported)) . '.',
        ];
    } catch (Throwable $e) {
        $flash = ['type' => 'error', 'message' => $e->getMessage()];
    }
}

// Count rows for each table
function cms_table_count(string $table): int
{
    return cms_table_exists($table) ? (int) cms_db()->query("SELECT COUNT(*) FROM $table")->fetchColumn() : 0;
}

$counts = [
    'blog_posts'             => cms_table_count('blog_posts'),
    'cms_pages'              => cms_table_count('cms_pages'),
    'media_files'            => cms_table_count('media_files'),
    'cms_forms'              => cms_table_count('cms_forms'),
    'cms_form_submissions'   => cms_table_count('cms_form_submissions'),
    'contact_submissions'    => cms_table_count('contact_submissions'),
    'cms_custom_post_types'  => cms_table_count('cms_custom_post_types'),
    'cms_custom_posts'       => cms_table_count('cms_custom_posts'),
    'cms_settings'           => cms_table_count('cms_settings'),
];
$totalRows = array_sum($counts);
$contentRows = ($counts['blog_posts'] ?? 0) + ($counts['cms_pages'] ?? 0) + ($counts['cms_custom_posts'] ?? 0);
$leadRows = ($counts['contact_submissions'] ?? 0) + ($counts['cms_form_submissions'] ?? 0);
$exportCards = [
    [
        'title' => 'Full JSON backup',
        'label' => 'Recommended',
        'icon' => 'fas fa-file-code',
        'accent' => 'primary',
        'description' => 'Exports CMS-managed content, settings, forms, custom post types, and entries as one portable JSON file.',
        'url' => cms_url('admin/backup.php?download=json'),
        'button' => 'Download JSON backup',
        'meta' => number_format($totalRows) . ' database rows',
        'primary' => true,
    ],
    [
        'title' => 'Blog posts CSV',
        'label' => 'Editorial',
        'icon' => 'fas fa-file-csv',
        'accent' => 'blue',
        'description' => 'Exports post titles, slugs, statuses, categories, and dates for spreadsheet review.',
        'url' => cms_url('admin/backup.php?download=posts_csv'),
        'button' => 'Download posts CSV',
        'meta' => number_format($counts['blog_posts']) . ' posts',
        'primary' => false,
    ],
    [
        'title' => 'Mail copies CSV',
        'label' => 'Leads',
        'icon' => 'fas fa-envelope-open-text',
        'accent' => 'green',
        'description' => 'Exports public contact form mail copies with source, status, country, and IP metadata.',
        'url' => cms_url('admin/backup.php?download=mail_csv'),
        'button' => 'Download mail CSV',
        'meta' => number_format($counts['contact_submissions']) . ' messages',
        'primary' => false,
    ],
    [
        'title' => 'CMS form submissions CSV',
        'label' => 'Forms',
        'icon' => 'fas fa-clipboard-list',
        'accent' => 'amber',
        'description' => 'Exports form-builder submissions with dynamic fields flattened into spreadsheet columns.',
        'url' => cms_url('admin/backup.php?download=form_submissions_csv'),
        'button' => 'Download forms CSV',
        'meta' => number_format($counts['cms_form_submissions']) . ' submissions',
        'primary' => false,
    ],
];
$tableIcons = [
    'blog_posts' => 'fas fa-newspaper',
    'cms_pages' => 'fas fa-file-lines',
    'media_files' => 'fas fa-images',
    'cms_forms' => 'fas fa-rectangle-list',
    'cms_form_submissions' => 'fas fa-inbox',
    'contact_submissions' => 'fas fa-envelope',
    'cms_custom_post_types' => 'fas fa-shapes',
    'cms_custom_posts' => 'fas fa-layer-group',
    'cms_settings' => 'fas fa-sliders',
];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-database"></i> Data Management</span>
    <h1 class="admin-page-title">Backup &amp; Export</h1>
    <p class="admin-page-copy">Download portable CMS backups and focused CSV exports. Exports are read-only, so no live content is changed or deleted.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<section class="utility-metrics backup-metrics">
  <article class="utility-metric">
    <i class="fas fa-database"></i>
    <span>Total Export Rows</span>
    <strong><?= number_format($totalRows) ?></strong>
    <p>Rows available across CMS export tables.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-pen-nib"></i>
    <span>Content Items</span>
    <strong><?= number_format($contentRows) ?></strong>
    <p>Posts, pages, and custom post entries.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-address-book"></i>
    <span>Lead Records</span>
    <strong><?= number_format($leadRows) ?></strong>
    <p>Contact mail copies and form submissions.</p>
  </article>
</section>

<section class="utility-grid backup-dashboard">
  <div class="utility-stack">
    <article class="admin-card backup-hero-card">
      <div class="utility-card-head">
        <div>
          <h2><i class="fas fa-cloud-arrow-down"></i> Export Center</h2>
          <p>Use JSON when you need a CMS content backup. Use CSV when you need spreadsheet analysis or lead handoff.</p>
        </div>
        <span class="utility-status is-on"><i class="fas fa-circle"></i> Read-only</span>
      </div>
      <div class="backup-export-grid">
        <?php foreach ($exportCards as $card): ?>
          <article class="backup-export-card <?= $card['primary'] ? 'is-primary' : '' ?> backup-accent-<?= cms_e($card['accent']) ?>">
            <div class="backup-export-icon" aria-hidden="true"><i class="<?= cms_e($card['icon']) ?>"></i></div>
            <div class="backup-export-body">
              <span class="backup-export-label"><?= cms_e($card['label']) ?></span>
              <h3><?= cms_e($card['title']) ?></h3>
              <p><?= cms_e($card['description']) ?></p>
            </div>
            <div class="backup-export-footer">
              <small><?= cms_e($card['meta']) ?></small>
              <a href="<?= cms_e($card['url']) ?>" class="admin-btn <?= $card['primary'] ? 'admin-btn-primary' : 'admin-btn-outline' ?>">
                <i class="fas fa-download"></i> <?= cms_e($card['button']) ?>
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="admin-card">
      <div class="utility-card-head">
        <div>
          <h2><i class="fas fa-table-list"></i> Export Coverage</h2>
          <p>Current row counts by table so you can spot missing or unexpected data before downloading.</p>
        </div>
      </div>
      <div class="backup-table-grid">
        <?php foreach ($counts as $table => $count): ?>
          <div class="backup-table-item">
            <i class="<?= cms_e($tableIcons[$table] ?? 'fas fa-table') ?>"></i>
            <div>
              <strong><?= cms_e(ucwords(str_replace('_', ' ', str_replace('cms_', '', $table)))) ?></strong>
              <span><?= cms_e($table) ?></span>
            </div>
            <b><?= number_format($count) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="admin-card backup-import-card">
      <div class="utility-card-head">
        <div>
          <h2><i class="fas fa-file-import"></i> Import CMS JSON</h2>
          <p>Restore or merge a previously exported CMS JSON backup into this site.</p>
        </div>
        <span class="utility-status is-off"><i class="fas fa-circle"></i> Careful</span>
      </div>
      <form method="post" enctype="multipart/form-data" class="admin-form" onsubmit="return confirm('Import this JSON backup into the CMS database? Existing rows with matching IDs may be updated.')">
        <?= cms_csrf_field() ?>
        <input type="hidden" name="action" value="import_json">
        <div class="admin-form-row">
          <label for="backup_json">CMS JSON backup file</label>
          <input id="backup_json" type="file" name="backup_json" accept="application/json,.json" required>
          <small>Imports posts, pages, settings, forms, custom post types, and custom posts. Media files are not bundled in JSON backups.</small>
        </div>
        <label class="backup-import-confirm">
          <input type="checkbox" name="confirm_import" value="1" required>
          <span>I have downloaded a fresh backup before importing.</span>
        </label>
        <div class="admin-form-actions">
          <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-file-import"></i> Import JSON backup</button>
        </div>
      </form>
    </article>
  </div>

  <aside class="utility-stack">
    <article class="admin-card">
      <div class="utility-card-head">
        <div>
          <h2><i class="fas fa-shield-halved"></i> Backup Checklist</h2>
          <p>For a production-safe backup, collect all three layers below.</p>
        </div>
      </div>
      <div class="backup-checklist">
        <div><i class="fas fa-check"></i><span><strong>CMS JSON</strong> Content, settings, forms, and custom entries.</span></div>
        <div><i class="fas fa-check"></i><span><strong>Database SQL</strong> Schema, indexes, and everything outside CMS export.</span></div>
        <div><i class="fas fa-check"></i><span><strong>Media files</strong> Uploads from <code><?= cms_e(cms_media_web_path()) ?>/</code>.</span></div>
      </div>
    </article>

    <article class="admin-card">
      <div class="utility-card-head">
        <div>
          <h2><i class="fas fa-triangle-exclamation"></i> What JSON Does Not Include</h2>
          <p>Useful boundary notes before you rely on an export.</p>
        </div>
      </div>
      <div class="utility-callout">
        <strong><i class="fas fa-database"></i> Full database backups still matter</strong>
        <span>For schema-level backups, use phpMyAdmin Export or run <code>mysqldump -u USER -p DB_NAME &gt; backup.sql</code> on the server.</span>
      </div>
      <ul class="backup-note-list">
        <li>Uploaded media files are referenced, not bundled.</li>
        <li>Server configuration and environment variables are not exported.</li>
        <li>Use CSV exports for review, not full site restoration.</li>
      </ul>
    </article>
  </aside>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
