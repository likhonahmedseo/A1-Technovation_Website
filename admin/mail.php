<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle = 'Mail Copies';
$activeNav = 'mail';
$errorMessage = '';
$selectedIds = [];

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');
        $selectedIds = array_values(array_filter(array_map('intval', (array) cms_post('selected_ids', []))));

        if ($action === 'export_selected') {
            if ($selectedIds === []) {
                throw new RuntimeException('Select at least one mail copy to export.');
            }
            $csv = cms_export_contact_submissions_csv($selectedIds);
            cms_log_activity('export_contact_mail_selected', 'mail', 0, count($selectedIds) . ' selected');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="mail-copies-selected-' . date('Y-m-d-His') . '.csv"');
            header('Content-Length: ' . strlen($csv));
            echo $csv;
            exit;
        }

        if ($action === 'mark_reviewed') {
            $count = cms_update_contact_submission_status($selectedIds, 'reviewed');
            cms_flash_set('success', "$count mail copy/copies marked as reviewed.");
            cms_redirect('admin/mail.php');
        }

        if ($action === 'archive_selected') {
            $count = cms_update_contact_submission_status($selectedIds, 'archived');
            cms_flash_set('success', "$count mail copy/copies archived.");
            cms_redirect('admin/mail.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

if ((string) cms_query('download') === 'csv') {
    $csv = cms_export_contact_submissions_csv();
    cms_log_activity('export_contact_mail_all', 'mail');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mail-copies-' . date('Y-m-d-His') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

$page = max(1, cms_query_int('page', 1));
$perPage = 50;
$total = cms_contact_submissions_count();
$mailCopies = cms_get_contact_submissions($perPage, ($page - 1) * $perPage);
$totalPages = max(1, (int) ceil($total / $perPage));
$statusCounts = [
    'new' => 0,
    'reviewed' => 0,
    'archived' => 0,
];

try {
    if (cms_ensure_contact_submissions_schema()) {
        $statusRows = cms_db()->query('SELECT status, COUNT(*) AS total FROM contact_submissions GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($statusRows as $statusRow) {
            $statusKey = (string) ($statusRow['status'] ?? '');
            if (array_key_exists($statusKey, $statusCounts)) {
                $statusCounts[$statusKey] = (int) $statusRow['total'];
            }
        }
    }
} catch (Throwable $exception) {
    // Keep the inbox usable even if metrics cannot be calculated.
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-envelope-open-text"></i> Mail Copies</span>
    <h1 class="admin-page-title">Website contact mail copies</h1>
    <p class="admin-page-copy">Every saved public contact form message in one dashboard inbox, with full and selected CSV export.</p>
  </div>
  <div class="card-actions">
    <a href="<?= cms_e(cms_url('admin/mail.php?download=csv')) ?>" class="admin-btn admin-btn-primary"><i class="fas fa-file-csv"></i> Export all CSV</a>
  </div>
</section>

<?php if ($errorMessage !== ''): ?><div class="flash flash-error"><?= cms_e($errorMessage) ?></div><?php endif; ?>

<section class="utility-metrics mail-metrics" aria-label="Mail copy summary">
  <article class="utility-metric">
    <i class="fas fa-inbox"></i>
    <span>Total Messages</span>
    <strong><?= number_format($total) ?></strong>
    <p>Saved contact form submissions.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-circle-dot"></i>
    <span>New</span>
    <strong><?= number_format($statusCounts['new']) ?></strong>
    <p>Waiting for first review.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-check-double"></i>
    <span>Reviewed</span>
    <strong><?= number_format($statusCounts['reviewed']) ?></strong>
    <p>Handled by the team.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-box-archive"></i>
    <span>Archived</span>
    <strong><?= number_format($statusCounts['archived']) ?></strong>
    <p>Stored for records.</p>
  </article>
</section>

<section class="table-card mail-inbox-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-envelope-open-text"></i> Contact inbox</h2>
      <p><?= number_format($total) ?> saved contact message<?= $total === 1 ? '' : 's' ?>. Select messages to export, review, or archive in bulk.</p>
    </div>
  </div>

  <?php if ($mailCopies === []): ?>
    <div class="empty-state">No public contact mail copies are stored yet. Confirm the contact form database env vars are configured.</div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <div class="mail-bulkbar">
        <div>
          <strong>Bulk actions</strong>
          <span>Select one or more messages from the table below.</span>
        </div>
        <div class="admin-action-row">
          <button type="submit" name="action" value="export_selected" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-download"></i> Export selected</button>
          <button type="submit" name="action" value="mark_reviewed" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-check"></i> Mark reviewed</button>
          <button type="submit" name="action" value="archive_selected" class="admin-btn admin-btn-danger admin-btn-sm" onclick="return confirm('Archive selected mail copies?')"><i class="fas fa-box-archive"></i> Archive selected</button>
        </div>
      </div>
      <div class="table-scroll">
        <table class="mail-table">
          <thead>
            <tr>
              <th><input type="checkbox" data-check-all="[name='selected_ids[]']" aria-label="Select all mail copies" /></th>
              <th>Contact</th>
              <th>Service</th>
              <th>Message</th>
              <th>Status</th>
              <th>Received</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mailCopies as $mail): ?>
              <?php
                $status = (string) ($mail['status'] ?? 'new');
                $statusClass = in_array($status, ['new', 'reviewed', 'archived'], true) ? $status : 'new';
                $sourcePage = trim((string) ($mail['source_page'] ?? ''));
                $budgetRange = trim((string) ($mail['budget_range'] ?? ''));
                $phone = trim((string) ($mail['phone'] ?? ''));
                $company = trim((string) ($mail['company'] ?? ''));
                $country = trim((string) ($mail['country_name'] ?? ''));
                $ipAddress = trim((string) ($mail['ip_address'] ?? ''));
              ?>
              <tr class="mail-row mail-row-<?= cms_e($statusClass) ?>">
                <td><input type="checkbox" name="selected_ids[]" value="<?= (int) $mail['id'] ?>" aria-label="Select mail from <?= cms_e((string) $mail['full_name']) ?>" /></td>
                <td>
                  <div class="mail-contact">
                    <strong><?= cms_e((string) $mail['full_name']) ?></strong>
                    <span class="mail-contact-line">
                      <a href="mailto:<?= cms_e((string) $mail['email']) ?>"><i class="fas fa-envelope"></i> <?= cms_e((string) $mail['email']) ?></a>
                      <?php if ($phone !== ''): ?><span><i class="fas fa-phone"></i> <?= cms_e($phone) ?></span><?php endif; ?>
                    </span>
                    <?php if ($company !== ''): ?><span class="mail-company"><i class="fas fa-building"></i> <?= cms_e($company) ?></span><?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="mail-service"><?= cms_e((string) ($mail['service_interest'] ?? 'General inquiry')) ?></div>
                  <?php if ($budgetRange !== ''): ?><span class="mail-budget"><?= cms_e($budgetRange) ?></span><?php endif; ?>
                </td>
                <td>
                  <div class="mail-message-preview"><?= cms_e(mb_strimwidth((string) $mail['message'], 0, 240, '...')) ?></div>
                  <?php if ($sourcePage !== ''): ?><span class="mail-source"><i class="fas fa-link"></i> <?= cms_e($sourcePage) ?></span><?php endif; ?>
                </td>
                <td>
                  <span class="mail-status mail-status-<?= cms_e($statusClass) ?>">
                    <i class="fas fa-circle"></i><?= cms_e($status) ?>
                  </span>
                </td>
                <td>
                  <div class="mail-received">
                    <strong><?= cms_e(cms_format_date((string) $mail['created_at'], 'M j, Y')) ?></strong>
                    <span><?= cms_e(cms_format_date((string) $mail['created_at'], 'g:i A')) ?></span>
                    <?php if ($country !== '' || $ipAddress !== ''): ?><span><?= cms_e(trim($country . ' ' . $ipAddress)) ?></span><?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </form>

    <?php if ($totalPages > 1): ?>
      <div class="admin-action-row mail-pagination">
        <?php if ($page > 1): ?><a class="admin-btn admin-btn-outline admin-btn-sm" href="<?= cms_e(cms_url('admin/mail.php?page=' . ($page - 1))) ?>"><i class="fas fa-arrow-left"></i> Previous</a><?php endif; ?>
        <span class="muted">Page <?= $page ?> of <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?><a class="admin-btn admin-btn-outline admin-btn-sm" href="<?= cms_e(cms_url('admin/mail.php?page=' . ($page + 1))) ?>">Next <i class="fas fa-arrow-right"></i></a><?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
