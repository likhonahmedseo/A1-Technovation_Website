<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('super_admin');

$pageTitle = 'API Tokens';
$activeNav = 'api-tokens';
$flash     = [];
$newToken  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'generate') {
        $name   = trim((string) ($_POST['token_name'] ?? ''));
        $scopes = trim((string) ($_POST['scopes'] ?? 'read'));
        $expiry = (int) ($_POST['expiry_days'] ?? 0) ?: null;
        if ($name !== '') {
            $admin    = cms_current_admin();
            $adminId  = (int) ($admin['id'] ?? 0);
            $newToken = cms_api_generate_token($name, $scopes, $adminId, $expiry);
            cms_log_activity('api_token_created', 'api_token', 0, $name);
            $flash = ['type' => 'success', 'message' => 'Token generated. Copy it now — it will not be shown again.'];
        }
    }

    if ($action === 'revoke') {
        $tokenId = (int) ($_POST['token_id'] ?? 0);
        if ($tokenId > 0) {
            cms_api_revoke_token($tokenId);
            cms_log_activity('api_token_revoked', 'api_token', $tokenId, 'Token #' . $tokenId);
            $flash = ['type' => 'success', 'message' => 'Token revoked.'];
        }
    }
}

$tokens = cms_api_tokens();
$activeTokens = count(array_filter($tokens, fn($t) => (int) ($t['is_active'] ?? 0) === 1));
$revokedTokens = count($tokens) - $activeTokens;
$usedTokens = count(array_filter($tokens, fn($t) => !empty($t['last_used_at'])));
$neverUsedTokens = $activeTokens - count(array_filter($tokens, fn($t) => (int) ($t['is_active'] ?? 0) === 1 && !empty($t['last_used_at'])));

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-key"></i> Developer</span>
    <h1 class="admin-page-title">API Tokens</h1>
    <p class="admin-page-copy">Generate scoped bearer tokens for integrations, automations, and external dashboards. Tokens are hashed, so copy the full token immediately after creation.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<?php if ($newToken): ?>
<div class="admin-card api-token-reveal">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-triangle-exclamation"></i> Your New Token</h2>
      <p>This is the only time the full token is shown. Store it in your password manager or integration settings now.</p>
    </div>
    <button type="button" class="admin-btn admin-btn-primary admin-btn-sm" data-copy-token="<?= cms_e($newToken) ?>">
      <i class="fas fa-copy"></i> Copy Token
    </button>
  </div>
  <div>
    <code class="api-token-code"><?= cms_e($newToken) ?></code>
    <p class="admin-help-text" style="margin:12px 0 0">Use as: <code>Authorization: Bearer &lt;token&gt;</code></p>
  </div>
</div>
<?php endif; ?>

<section class="utility-metrics api-metrics">
  <article class="utility-metric">
    <i class="fas fa-key"></i>
    <span>Active Tokens</span>
    <strong><?= $activeTokens ?></strong>
    <p>Can currently access protected API routes.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-clock"></i>
    <span>Used Tokens</span>
    <strong><?= $usedTokens ?></strong>
    <p>Have connected at least once.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-ban"></i>
    <span>Revoked</span>
    <strong><?= $revokedTokens ?></strong>
    <p>Disabled tokens kept for audit history.</p>
  </article>
  <article class="utility-metric">
    <i class="fas fa-plug-circle-xmark"></i>
    <span>Never Used</span>
    <strong><?= max(0, $neverUsedTokens) ?></strong>
    <p>Active tokens that have not connected yet.</p>
  </article>
</section>

<div class="utility-grid api-dashboard">

<!-- Generate form -->
<div class="admin-card api-generate-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-plus-circle"></i> Generate Token</h2>
      <p>Create a token for automation, integrations, or external dashboards.</p>
    </div>
    <span class="utility-status is-on"><i class="fas fa-lock"></i> Hashed</span>
  </div>
  <form method="post" class="admin-form">
    <?= cms_csrf_field() ?>
    <input type="hidden" name="action" value="generate">
    <div class="api-form-grid">
      <div class="admin-form-row">
        <label for="token_name">Token Name</label>
        <input id="token_name" type="text" name="token_name" required placeholder="e.g. Zapier Integration">
      </div>
      <div class="admin-form-row">
        <label for="expiry_days">Expires in days</label>
        <input id="expiry_days" type="number" name="expiry_days" min="0" value="0" placeholder="0 = never">
        <small>Leave 0 for no expiry.</small>
      </div>
    </div>
    <div class="admin-form-row">
      <label for="scopes">Access scope</label>
      <select id="scopes" name="scopes" data-api-scope-select>
        <option value="read">read - GET only</option>
        <option value="read,write">read,write - GET + POST/PUT</option>
        <option value="read,write,delete">read,write,delete - Full access</option>
      </select>
    </div>
    <div class="api-scope-cards" aria-label="API scope guide">
      <button type="button" class="api-scope-card active" data-api-scope="read">
        <i class="fas fa-eye"></i>
        <strong>read</strong>
        <span>View public posts, pages, media, tags, categories, and settings.</span>
      </button>
      <button type="button" class="api-scope-card" data-api-scope="read,write">
        <i class="fas fa-pen-to-square"></i>
        <strong>write</strong>
        <span>Reserved for create/update API workflows.</span>
      </button>
      <button type="button" class="api-scope-card is-danger" data-api-scope="read,write,delete">
        <i class="fas fa-triangle-exclamation"></i>
        <strong>delete</strong>
        <span>Highest-risk permission. Use only for trusted internal tools.</span>
      </button>
    </div>
    <div class="admin-form-actions">
      <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-plus"></i> Generate Token</button>
    </div>
  </form>
  <div class="utility-callout api-security-callout">
    <strong><i class="fas fa-shield-halved"></i> Security tip</strong>
    <span>Prefer short expiry windows and the smallest scope needed. Revoke unused tokens after integrations are tested.</span>
  </div>
</div>

<!-- Tokens list -->
<div class="admin-card api-inventory-card">
  <div class="utility-card-head">
    <div>
      <h2><i class="fas fa-list-check"></i> API Token Inventory</h2>
      <p>Monitor usage, expiry, and revoke access without deleting audit history.</p>
    </div>
    <span class="utility-status <?= $activeTokens > 0 ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $activeTokens ?> Active</span>
  </div>
  <?php if ($tokens === []): ?>
    <div class="empty-state">No tokens yet. Generate your first read-only token for a low-risk integration test.</div>
  <?php else: ?>
    <div class="api-token-list">
      <?php foreach ($tokens as $t):
        $isActive = (bool) $t['is_active'];
        $scopes = array_filter(array_map('trim', explode(',', (string) $t['scopes'])));
        $expiryText = $t['expires_at'] ? date('d M Y', strtotime($t['expires_at'])) : 'Never';
        $lastUsedText = $t['last_used_at'] ? date('d M Y', strtotime($t['last_used_at'])) : 'Never';
      ?>
        <article class="api-token-item <?= $isActive ? '' : 'is-revoked' ?>">
          <div class="api-token-main">
            <div>
              <strong><?= cms_e($t['name']) ?></strong>
              <span>Created <?= cms_e(date('d M Y', strtotime($t['created_at']))) ?></span>
            </div>
            <span class="utility-status <?= $isActive ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $isActive ? 'Active' : 'Revoked' ?></span>
          </div>
          <div class="api-token-meta">
            <div><span>Scopes</span><strong><?php foreach ($scopes as $scope): ?><code><?= cms_e($scope) ?></code><?php endforeach; ?></strong></div>
            <div><span>Last Used</span><strong><?= cms_e($lastUsedText) ?></strong></div>
            <div><span>Expires</span><strong><?= cms_e($expiryText) ?></strong></div>
          </div>
          <?php if ($isActive): ?>
            <form method="post" onsubmit="return confirm('Revoke this token? Connected integrations using this token will stop working.')">
              <?= cms_csrf_field() ?>
              <input type="hidden" name="action" value="revoke">
              <input type="hidden" name="token_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" class="admin-btn admin-btn-danger admin-btn-sm"><i class="fas fa-ban"></i> Revoke Token</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="api-endpoint-panel">
    <div>
      <span>API Base URL</span>
      <code><?= cms_e(cms_url('api.php')) ?></code>
    </div>
    <div>
      <span>Example Request</span>
      <code>GET <?= cms_e(cms_url('api.php')) ?>?resource=posts</code>
    </div>
    <div>
      <span>Header</span>
      <code>Authorization: Bearer &lt;token&gt;</code>
    </div>
  </div>
</div>

</div>

<script>
const apiScopeSelect = document.querySelector('[data-api-scope-select]');
const apiScopeCards = document.querySelectorAll('[data-api-scope]');
function syncApiScopeCards(value) {
  apiScopeCards.forEach((card) => {
    card.classList.toggle('active', card.getAttribute('data-api-scope') === value);
  });
}
if (apiScopeSelect) {
  apiScopeSelect.addEventListener('change', () => syncApiScopeCards(apiScopeSelect.value));
  apiScopeCards.forEach((card) => {
    card.addEventListener('click', () => {
      apiScopeSelect.value = card.getAttribute('data-api-scope') || 'read';
      syncApiScopeCards(apiScopeSelect.value);
    });
  });
}

document.querySelectorAll('[data-copy-token]').forEach((button) => {
  button.addEventListener('click', async () => {
    const token = button.getAttribute('data-copy-token') || '';
    try {
      await navigator.clipboard.writeText(token);
      const original = button.innerHTML;
      button.innerHTML = '<i class="fas fa-check"></i> Copied';
      setTimeout(() => { button.innerHTML = original; }, 1800);
    } catch (error) {
      window.prompt('Copy this token:', token);
    }
  });
});
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
