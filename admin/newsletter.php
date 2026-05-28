<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle = 'Newsletter';
$activeNav = 'newsletter';
$flash     = [];

// ── AJAX: export CSV ────────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
    echo cms_newsletter_export_csv();
    exit;
}

// ── POST: delete subscriber ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    cms_require_csrf();
    $action = (string) $_POST['action'];

    if ($action === 'delete_subscriber') {
        $id = (int) ($_POST['subscriber_id'] ?? 0);
        if ($id > 0 && cms_table_exists('cms_newsletter_subscribers')) {
            cms_db()->prepare('DELETE FROM cms_newsletter_subscribers WHERE id = :id')->execute(['id' => $id]);
            $flash = ['type' => 'success', 'message' => 'Subscriber removed.'];
        }
    }

    if ($action === 'confirm_subscriber') {
        $id = (int) ($_POST['subscriber_id'] ?? 0);
        if ($id > 0 && cms_table_exists('cms_newsletter_subscribers')) {
            $stmt = cms_db()->prepare(
                "UPDATE cms_newsletter_subscribers
                 SET status='confirmed', subscribed_at=NOW(), confirm_token=NULL, updated_at=NOW()
                 WHERE id = :id AND status='pending'"
            );
            $stmt->execute(['id' => $id]);
            $flash = $stmt->rowCount() > 0
                ? ['type' => 'success', 'message' => 'Pending subscriber confirmed.']
                : ['type' => 'warning', 'message' => 'Subscriber was already handled or could not be confirmed.'];
        }
    }

    if ($action === 'save_campaign') {
        $admin = cms_current_admin();
        $cid   = cms_newsletter_save_campaign([
            'id'           => (int) ($_POST['campaign_id'] ?? 0) ?: null,
            'subject'      => trim((string) ($_POST['subject'] ?? '')),
            'preview_text' => trim((string) ($_POST['preview_text'] ?? '')),
            'body_html'    => trim((string) ($_POST['body_html'] ?? '')),
        ], (int) ($admin['id'] ?? 0));
        $flash = ['type' => 'success', 'message' => 'Campaign saved (ID ' . $cid . ').'];
    }

    if ($action === 'delete_campaign') {
        $id = (int) ($_POST['campaign_id'] ?? 0);
        if ($id > 0 && cms_table_exists('cms_newsletter_campaigns')) {
            cms_db()->prepare("DELETE FROM cms_newsletter_campaigns WHERE id = :id AND status='draft'")->execute(['id' => $id]);
            $flash = ['type' => 'success', 'message' => 'Draft campaign deleted.'];
        }
    }
}

$tab         = in_array($_GET['tab'] ?? '', ['campaigns', 'pending', 'unsubscribed'], true) ? $_GET['tab'] : 'subscribers';
$confirmed   = cms_newsletter_subscribers('confirmed', 200);
$pending     = cms_newsletter_subscribers('pending', 100);
$unsubs      = cms_newsletter_subscribers('unsubscribed', 100);
$campaigns   = cms_newsletter_campaigns();
$draftCampaigns = count(array_filter($campaigns, fn($campaign) => ($campaign['status'] ?? '') === 'draft'));
$sentCampaigns = count(array_filter($campaigns, fn($campaign) => ($campaign['status'] ?? '') === 'sent'));
$tabMeta = [
    'subscribers' => ['label' => 'Subscribers', 'icon' => 'fas fa-circle-check', 'count' => count($confirmed)],
    'campaigns' => ['label' => 'Campaigns', 'icon' => 'fas fa-envelope-open-text', 'count' => count($campaigns)],
    'pending' => ['label' => 'Pending', 'icon' => 'fas fa-clock', 'count' => count($pending)],
    'unsubscribed' => ['label' => 'Unsubscribed', 'icon' => 'fas fa-user-slash', 'count' => count($unsubs)],
];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-paper-plane"></i> Newsletter</span>
    <h1 class="admin-page-title">Newsletter Management</h1>
    <p class="admin-page-copy">Manage subscribers, draft campaigns, and export your list.</p>
  </div>
  <div class="admin-action-row">
    <a href="?action=export_csv" class="admin-btn admin-btn-outline"><i class="fas fa-download"></i> Export CSV</a>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<section class="utility-metrics newsletter-metrics">
  <article class="utility-metric">
    <i class="fas fa-circle-check"></i>
    <span>Confirmed</span>
    <strong><?= count($confirmed) ?></strong>
    <p>Subscribers ready for campaign sends.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-clock"></i>
    <span>Pending</span>
    <strong><?= count($pending) ?></strong>
    <p>Waiting for email confirmation.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-paper-plane"></i>
    <span>Campaigns</span>
    <strong><?= count($campaigns) ?></strong>
    <p><?= $draftCampaigns ?> draft<?= $draftCampaigns === 1 ? '' : 's' ?>, <?= $sentCampaigns ?> sent.</p>
  </article>
</section>

<nav class="newsletter-tabs" aria-label="Newsletter sections">
  <?php foreach ($tabMeta as $tabKey => $item): ?>
    <a href="?tab=<?= cms_e($tabKey) ?>" class="newsletter-tab<?= $tab === $tabKey ? ' active' : '' ?>">
      <i class="<?= cms_e($item['icon']) ?>"></i>
      <span><?= cms_e($item['label']) ?></span>
      <b><?= (int) $item['count'] ?></b>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'subscribers'): ?>
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-users"></i> Confirmed Subscribers</h2>
      <p>Contacts who completed confirmation and can receive newsletter campaigns.</p>
    </div>
  </div>
  <table class="admin-table">
    <thead><tr><th>Email</th><th>Name</th><th>Subscribed</th><th>Source</th><th></th></tr></thead>
    <tbody>
    <?php if ($confirmed === []): ?>
      <tr><td colspan="5"><div class="empty-state">No confirmed subscribers yet. New subscribers appear here after confirmation.</div></td></tr>
    <?php else: foreach ($confirmed as $s): ?>
      <tr>
        <td><strong><?= cms_e($s['email']) ?></strong></td>
        <td><?= cms_e(($s['full_name'] ?? '') !== '' ? $s['full_name'] : '—') ?></td>
        <td><?= cms_e($s['subscribed_at'] ? date('d M Y', strtotime($s['subscribed_at'])) : '') ?></td>
        <td><span class="newsletter-source"><?= cms_e(($s['source'] ?? '') !== '' ? $s['source'] : 'unknown') ?></span></td>
        <td>
          <form method="post" onsubmit="return confirm('Remove this subscriber?')">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="delete_subscriber">
            <input type="hidden" name="subscriber_id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm"><i class="fas fa-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'campaigns'): ?>
<div class="utility-grid" style="margin-bottom:28px">
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-pen-nib"></i> New Campaign</h2>
      <p>Draft newsletter copy with merge tags before sending from your campaign workflow.</p>
    </div>
  </div>
  <form method="post" class="admin-form">
    <?= cms_csrf_field() ?>
    <input type="hidden" name="action" value="save_campaign">
    <input type="hidden" name="campaign_id" value="0">
    <div class="admin-form-row">
      <label>Subject</label>
      <input type="text" name="subject" required placeholder="Your email subject line">
    </div>
    <div class="admin-form-row">
      <label>Preview Text <small>(shown in inbox preview)</small></label>
      <input type="text" name="preview_text" placeholder="Short preview shown in Gmail, Outlook...">
    </div>
    <div class="admin-form-row">
      <label>Body HTML</label>
      <textarea name="body_html" rows="12" placeholder="<p>Hi {{name}},</p><p>...</p>"></textarea>
      <small>Use <code>{{name}}</code> for subscriber name, <code>{{email}}</code> for email, <code>{{unsubscribe_url}}</code> for the unsubscribe link.</small>
    </div>
    <div class="newsletter-token-row">
      <span>{{name}}</span>
      <span>{{email}}</span>
      <span>{{unsubscribe_url}}</span>
    </div>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Save Draft</button>
    </div>
  </form>
</div>

<aside class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-lightbulb"></i> Draft Tips</h2>
      <p>Keep subject lines short, preview text specific, and every campaign useful enough to earn the next open.</p>
    </div>
  </div>
  <div class="utility-callout">
    <strong><i class="fas fa-shield-heart"></i> Before sending</strong>
    <span>Confirm unsubscribe links, test the email on mobile, and export the list before a major campaign.</span>
  </div>
</aside>
</div>

<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-list-check"></i> Campaigns</h2>
      <p>Drafts and sent campaign records.</p>
    </div>
  </div>
  <table class="admin-table">
    <thead><tr><th>Subject</th><th>Status</th><th>Sent</th><th>Count</th><th></th></tr></thead>
    <tbody>
    <?php if ($campaigns === []): ?>
      <tr><td colspan="5"><div class="empty-state">No campaigns yet. Save your first draft above.</div></td></tr>
    <?php else: foreach ($campaigns as $c): ?>
      <tr>
        <td><strong><?= cms_e($c['subject']) ?></strong><p><?= cms_e($c['preview_text'] ?? '') ?></p></td>
        <td><span class="status-badge status-<?= $c['status'] === 'sent' ? 'published' : 'draft' ?>"><?= cms_e($c['status']) ?></span></td>
        <td><?= cms_e($c['sent_at'] ? date('d M Y', strtotime($c['sent_at'])) : '—') ?></td>
        <td><?= (int) $c['sent_count'] ?></td>
        <td>
          <?php if ($c['status'] === 'draft'): ?>
          <form method="post" onsubmit="return confirm('Delete this draft?')" style="display:inline">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="delete_campaign">
            <input type="hidden" name="campaign_id" value="<?= (int) $c['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm"><i class="fas fa-trash"></i></button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'pending'): ?>
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-clock"></i> Pending Confirmation</h2>
      <p>People who submitted the form but have not confirmed their email yet.</p>
    </div>
  </div>
  <table class="admin-table">
    <thead><tr><th>Email</th><th>Name</th><th>Signed Up</th><th>Source</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if ($pending === []): ?>
      <tr><td colspan="5"><div class="empty-state">No pending subscribers.</div></td></tr>
    <?php else: foreach ($pending as $s): ?>
      <tr>
        <td><strong><?= cms_e($s['email']) ?></strong></td>
        <td><?= cms_e(($s['full_name'] ?? '') !== '' ? $s['full_name'] : '—') ?></td>
        <td><?= cms_e(date('d M Y', strtotime($s['created_at']))) ?></td>
        <td><span class="newsletter-source"><?= cms_e(($s['source'] ?? '') !== '' ? $s['source'] : 'unknown') ?></span></td>
        <td>
          <div style="display:flex;gap:8px;justify-content:flex-end;align-items:center">
          <form method="post" onsubmit="return confirm('Confirm this subscriber?')" style="display:inline">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="confirm_subscriber">
            <input type="hidden" name="subscriber_id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm" title="Confirm subscriber" aria-label="Confirm subscriber">
              <i class="fas fa-check"></i>
            </button>
          </form>
          <form method="post" onsubmit="return confirm('Remove?')" style="display:inline">
            <?= cms_csrf_field() ?>
            <input type="hidden" name="action" value="delete_subscriber">
            <input type="hidden" name="subscriber_id" value="<?= (int) $s['id'] ?>">
            <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm" title="Delete subscriber" aria-label="Delete subscriber"><i class="fas fa-trash"></i></button>
          </form>
          </div>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'unsubscribed'): ?>
<div class="admin-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-user-slash"></i> Unsubscribed</h2>
      <p>Contacts who opted out and should not receive future sends.</p>
    </div>
  </div>
  <table class="admin-table">
    <thead><tr><th>Email</th><th>Name</th><th>Unsubscribed</th></tr></thead>
    <tbody>
    <?php if ($unsubs === []): ?>
      <tr><td colspan="3"><div class="empty-state">No unsubscribed contacts.</div></td></tr>
    <?php else: foreach ($unsubs as $s): ?>
      <tr>
        <td><strong><?= cms_e($s['email']) ?></strong></td>
        <td><?= cms_e(($s['full_name'] ?? '') !== '' ? $s['full_name'] : '—') ?></td>
        <td><?= cms_e($s['unsubscribed_at'] ? date('d M Y', strtotime($s['unsubscribed_at'])) : '') ?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
