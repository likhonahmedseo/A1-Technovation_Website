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
        if ($selectedIds === [] && cms_post('single_id')) {
            $singleId = (int) cms_post('single_id');
            if ($singleId > 0) {
                $selectedIds = [$singleId];
            }
        }

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
              <th style="text-align:right">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($mailCopies as $mail): ?>
              <?php
                $mailId = (int) $mail['id'];
                $status = (string) ($mail['status'] ?? 'new');
                $statusClass = in_array($status, ['new', 'reviewed', 'archived'], true) ? $status : 'new';
                $sourcePage = trim((string) ($mail['source_page'] ?? ''));
                $budgetRange = trim((string) ($mail['budget_range'] ?? ''));
                $phone = trim((string) ($mail['phone'] ?? ''));
                $company = trim((string) ($mail['company'] ?? ''));
                $country = trim((string) ($mail['country_name'] ?? ''));
                $ipAddress = trim((string) ($mail['ip_address'] ?? ''));
                $rawMessage = (string) ($mail['message'] ?? '');
                $isLongMessage = mb_strlen($rawMessage) > 160;
                $previewMessage = $isLongMessage ? mb_strimwidth($rawMessage, 0, 160, '...') : $rawMessage;
              ?>
              <tr class="mail-row mail-row-<?= cms_e($statusClass) ?>" id="mail-row-<?= $mailId ?>">
                <td><input type="checkbox" name="selected_ids[]" value="<?= $mailId ?>" aria-label="Select mail from <?= cms_e((string) $mail['full_name']) ?>" /></td>
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
                  <div class="mail-message-container">
                    <div class="mail-message-preview" id="mail-preview-<?= $mailId ?>">
                      <?= nl2br(cms_e($previewMessage)) ?>
                    </div>
                    <?php if ($isLongMessage): ?>
                      <div class="mail-message-preview mail-message-full" id="mail-full-<?= $mailId ?>" style="display:none">
                        <?= nl2br(cms_e($rawMessage)) ?>
                      </div>
                      <div class="mail-message-actions">
                        <button type="button" class="mail-inline-toggle-btn" data-target="<?= $mailId ?>" aria-expanded="false">
                          <i class="fas fa-chevron-down"></i> Show full text
                        </button>
                        <button type="button" class="mail-open-modal-link" data-mail-id="<?= $mailId ?>" title="View formatted email details">
                          <i class="fas fa-up-right-from-square"></i> Full email
                        </button>
                      </div>
                    <?php else: ?>
                      <div class="mail-message-actions">
                        <button type="button" class="mail-open-modal-link" data-mail-id="<?= $mailId ?>" title="View formatted email details">
                          <i class="fas fa-up-right-from-square"></i> Full email
                        </button>
                      </div>
                    <?php endif; ?>
                    <?php if ($sourcePage !== ''): ?>
                      <span class="mail-source"><i class="fas fa-link"></i> <?= cms_e($sourcePage) ?></span>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <span class="mail-status mail-status-<?= cms_e($statusClass) ?>" id="status-badge-<?= $mailId ?>">
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
                <td style="text-align:right">
                  <button type="button" class="admin-btn admin-btn-outline admin-btn-sm mail-open-modal-btn" data-mail-id="<?= $mailId ?>" title="Open full email details">
                    <i class="fas fa-eye"></i> View email
                  </button>
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

<!-- Full Email View Modal Dialog -->
<div id="mailDetailModal" class="mail-modal-overlay" style="display:none" role="dialog" aria-modal="true" aria-labelledby="modalSenderName">
  <div class="mail-modal-dialog">
    <div class="mail-modal-header">
      <div class="mail-modal-title-group">
        <div class="mail-modal-icon">
          <i class="fas fa-envelope-open-text"></i>
        </div>
        <div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 id="modalSenderName" class="mail-modal-title">Sender Name</h3>
            <span id="modalStatusBadge" class="mail-status mail-status-new">New</span>
          </div>
          <span id="modalReceivedTime" class="mail-modal-subtitle">Oct 9, 2026 at 6:23 PM</span>
        </div>
      </div>
      <button type="button" class="mail-modal-close" id="closeMailModalBtn" aria-label="Close dialog">&times;</button>
    </div>

    <div class="mail-modal-body">
      <!-- Contact Info Cards -->
      <div class="mail-modal-meta-grid">
        <div class="mail-meta-item">
          <span class="mail-meta-label"><i class="fas fa-envelope"></i> Email</span>
          <a id="modalEmailLink" href="#" class="mail-meta-value mail-email-link"></a>
        </div>
        <div class="mail-meta-item">
          <span class="mail-meta-label"><i class="fas fa-phone"></i> Phone</span>
          <span id="modalPhone" class="mail-meta-value">—</span>
        </div>
        <div class="mail-meta-item">
          <span class="mail-meta-label"><i class="fas fa-building"></i> Company</span>
          <span id="modalCompany" class="mail-meta-value">—</span>
        </div>
        <div class="mail-meta-item">
          <span class="mail-meta-label"><i class="fas fa-briefcase"></i> Service Requested</span>
          <span id="modalService" class="mail-meta-value">—</span>
        </div>
        <div class="mail-meta-item">
          <span class="mail-meta-label"><i class="fas fa-dollar-sign"></i> Budget</span>
          <span id="modalBudget" class="mail-meta-value">—</span>
        </div>
        <div class="mail-meta-item">
          <span class="mail-meta-label"><i class="fas fa-globe"></i> Location / IP</span>
          <span id="modalLocation" class="mail-meta-value">—</span>
        </div>
      </div>

      <div id="modalSourcePageRow" class="mail-meta-source-row" style="display:none">
        <span class="mail-meta-label"><i class="fas fa-link"></i> Submitted From Page:</span>
        <a id="modalSourcePageLink" href="#" target="_blank" rel="noopener" class="mail-source-url" style="color:var(--admin-primary);word-break:break-all"></a>
      </div>

      <!-- Full Message Section -->
      <div class="mail-modal-message-box">
        <div class="mail-message-box-header">
          <strong><i class="fas fa-message"></i> Complete Email Message</strong>
          <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" id="copyMessageBtn" title="Copy full message text">
            <i class="fas fa-copy"></i> Copy text
          </button>
        </div>
        <div id="modalFullMessage" class="mail-message-full-content"></div>
      </div>
    </div>

    <div class="mail-modal-footer">
      <div class="mail-footer-left">
        <form method="post" id="modalStatusForm" style="display:inline-flex;gap:8px">
          <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
          <input type="hidden" name="single_id" id="modalSingleId" value="" />
          <button type="submit" name="action" value="mark_reviewed" id="modalMarkReviewedBtn" class="admin-btn admin-btn-outline admin-btn-sm">
            <i class="fas fa-check"></i> Mark reviewed
          </button>
          <button type="submit" name="action" value="archive_selected" id="modalArchiveBtn" class="admin-btn admin-btn-outline admin-btn-sm" style="color:var(--admin-danger);border-color:rgba(220,38,38,0.2)" onclick="return confirm('Archive this message?')">
            <i class="fas fa-box-archive"></i> Archive
          </button>
        </form>
      </div>
      <div class="mail-footer-right" style="display:inline-flex;gap:8px">
        <a id="modalReplyBtn" href="#" class="admin-btn admin-btn-primary admin-btn-sm">
          <i class="fas fa-reply"></i> Reply via Email
        </a>
        <button type="button" class="admin-btn admin-btn-ghost admin-btn-sm" id="closeMailModalBtn2">Close</button>
      </div>
    </div>
  </div>
</div>

<script id="mailCopiesData" type="application/json">
<?= json_encode(array_map(static function (array $m): array {
    return [
        'id'               => (int) $m['id'],
        'full_name'        => (string) ($m['full_name'] ?? 'Anonymous'),
        'email'            => (string) ($m['email'] ?? ''),
        'phone'            => (string) ($m['phone'] ?? ''),
        'company'          => (string) ($m['company'] ?? ''),
        'service_interest' => (string) ($m['service_interest'] ?? 'General inquiry'),
        'budget_range'     => (string) ($m['budget_range'] ?? ''),
        'message'          => (string) ($m['message'] ?? ''),
        'source_page'      => (string) ($m['source_page'] ?? ''),
        'status'           => (string) ($m['status'] ?? 'new'),
        'created_at'       => (string) ($m['created_at'] ?? ''),
        'formatted_date'   => cms_format_date((string) $m['created_at'], 'M j, Y \a\t g:i A'),
        'country_name'     => (string) ($m['country_name'] ?? ''),
        'ip_address'       => (string) ($m['ip_address'] ?? ''),
    ];
}, $mailCopies), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>

<script>
(function () {
  // Parse message data
  const mailMap = {};
  try {
    const dataEl = document.getElementById('mailCopiesData');
    if (dataEl) {
      const items = JSON.parse(dataEl.textContent);
      items.forEach(item => { mailMap[item.id] = item; });
    }
  } catch (err) {
    console.error('Failed to parse mail copies data:', err);
  }

  // 1. Inline expand/collapse toggles
  document.querySelectorAll('.mail-inline-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const targetId = this.getAttribute('data-target');
      const previewEl = document.getElementById('mail-preview-' + targetId);
      const fullEl    = document.getElementById('mail-full-' + targetId);
      if (!previewEl || !fullEl) return;

      const isExpanded = fullEl.style.display !== 'none';
      if (isExpanded) {
        fullEl.style.display = 'none';
        previewEl.style.display = '';
        this.innerHTML = '<i class="fas fa-chevron-down"></i> Show full text';
        this.setAttribute('aria-expanded', 'false');
      } else {
        fullEl.style.display = '';
        previewEl.style.display = 'none';
        this.innerHTML = '<i class="fas fa-chevron-up"></i> Show less';
        this.setAttribute('aria-expanded', 'true');
      }
    });
  });

  // 2. Full Email Modal logic
  const modal       = document.getElementById('mailDetailModal');
  const closeBtn1   = document.getElementById('closeMailModalBtn');
  const closeBtn2   = document.getElementById('closeMailModalBtn2');
  const copyBtn     = document.getElementById('copyMessageBtn');
  let currentMail   = null;

  function openModal(id) {
    const mail = mailMap[id];
    if (!mail || !modal) return;
    currentMail = mail;

    document.getElementById('modalSenderName').textContent = mail.full_name || 'Anonymous';
    
    const badge = document.getElementById('modalStatusBadge');
    if (badge) {
      badge.className = 'mail-status mail-status-' + (mail.status || 'new');
      badge.innerHTML = '<i class="fas fa-circle"></i> ' + (mail.status || 'new');
    }

    document.getElementById('modalReceivedTime').textContent = mail.formatted_date ? 'Received ' + mail.formatted_date : '';
    
    const emailLink = document.getElementById('modalEmailLink');
    if (emailLink) {
      emailLink.textContent = mail.email || '—';
      emailLink.href = mail.email ? 'mailto:' + encodeURIComponent(mail.email) : '#';
    }

    document.getElementById('modalPhone').textContent    = mail.phone || '—';
    document.getElementById('modalCompany').textContent  = mail.company || '—';
    document.getElementById('modalService').textContent  = mail.service_interest || 'General inquiry';
    document.getElementById('modalBudget').textContent   = mail.budget_range || '—';

    const locationParts = [mail.country_name, mail.ip_address].filter(Boolean);
    document.getElementById('modalLocation').textContent = locationParts.length > 0 ? locationParts.join(' • ') : '—';

    const sourceRow  = document.getElementById('modalSourcePageRow');
    const sourceLink = document.getElementById('modalSourcePageLink');
    if (sourceRow && sourceLink) {
      if (mail.source_page) {
        sourceRow.style.display = 'flex';
        sourceLink.textContent = mail.source_page;
        sourceLink.href = mail.source_page;
      } else {
        sourceRow.style.display = 'none';
      }
    }

    // Full Message Body
    const msgBox = document.getElementById('modalFullMessage');
    if (msgBox) {
      msgBox.textContent = mail.message || '(Empty message)';
    }

    // Modal Actions
    const singleIdInput = document.getElementById('modalSingleId');
    if (singleIdInput) singleIdInput.value = mail.id;

    const replyBtn = document.getElementById('modalReplyBtn');
    if (replyBtn) {
      if (mail.email) {
        const subject = encodeURIComponent('Re: Inquiry regarding ' + (mail.service_interest || 'A1 Technovation'));
        replyBtn.href = 'mailto:' + encodeURIComponent(mail.email) + '?subject=' + subject;
        replyBtn.style.display = 'inline-flex';
      } else {
        replyBtn.style.display = 'none';
      }
    }

    // Reset copy button
    if (copyBtn) {
      copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy text';
    }

    modal.style.display = 'flex';
    requestAnimationFrame(() => modal.classList.add('is-active'));
    document.body.style.overflow = 'hidden';
  }

  function closeModal() {
    if (!modal) return;
    modal.classList.remove('is-active');
    setTimeout(() => {
      modal.style.display = 'none';
      document.body.style.overflow = '';
    }, 200);
  }

  if (closeBtn1) closeBtn1.addEventListener('click', closeModal);
  if (closeBtn2) closeBtn2.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && modal.classList.contains('is-active')) {
      closeModal();
    }
  });

  // Attach open listeners
  document.querySelectorAll('.mail-open-modal-link, .mail-open-modal-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      const id = this.getAttribute('data-mail-id');
      if (id) openModal(id);
    });
  });

  // Copy full message
  if (copyBtn) {
    copyBtn.addEventListener('click', () => {
      if (!currentMail || !currentMail.message) return;
      navigator.clipboard.writeText(currentMail.message).then(() => {
        copyBtn.innerHTML = '<i class="fas fa-check" style="color:var(--admin-success)"></i> Copied!';
        setTimeout(() => {
          copyBtn.innerHTML = '<i class="fas fa-copy"></i> Copy text';
        }, 2200);
      }).catch(err => {
        console.error('Failed to copy message:', err);
      });
    });
  }
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
