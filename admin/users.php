<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle = 'Users';
$activeNav = 'users';
$errorMessage = '';
$currentAdmin = cms_current_admin();
$postAction = (string) cms_post('action');

if (cms_is_post()) {
    try {
        cms_require_csrf();

        $action = $postAction;

        if ($action === 'create_admin') {
            cms_require_role('super_admin');
            $newId = cms_create_admin(
                (string) cms_post('full_name'),
                (string) cms_post('email'),
                (string) cms_post('password')
            );
            $role = (string) cms_post('role', 'editor');
            if (is_int($newId) && $newId > 0) {
                cms_update_admin_role($newId, $role);
            }
            cms_log_activity('create_user', 'user', is_int($newId) ? $newId : 0, (string) cms_post('full_name'));
            cms_flash_set('success', 'New admin user created successfully.');
            cms_redirect('admin/users.php');
        }

        if ($action === 'update_name') {
            $adminId = (int) cms_post('admin_id');
            cms_update_admin_name($adminId, (string) cms_post('full_name'));
            if (cms_current_admin_can('super_admin')) {
                cms_update_admin_role($adminId, (string) cms_post('role', 'editor'));
            }
            cms_log_activity('update_user', 'user', $adminId, (string) cms_post('full_name'));
            cms_flash_set('success', 'Admin user updated successfully.');
            cms_redirect('admin/users.php?edit=' . $adminId);
        }

        if ($action === 'update_password') {
            $adminId = (int) cms_post('admin_id');
            $password = (string) cms_post('password');
            $confirmPassword = (string) cms_post('password_confirm');

            if ($password !== $confirmPassword) {
                throw new RuntimeException('Password confirmation did not match.');
            }

            cms_update_admin_password($adminId, $password);
            cms_flash_set('success', 'Admin password updated successfully.');
            cms_redirect('admin/users.php?edit=' . $adminId);
        }

        if ($action === 'resend_verification') {
            cms_require_role('super_admin');
            cms_resend_verification_email((int) cms_post('admin_id'));
            cms_flash_set('success', 'Verification email resent successfully.');
            cms_redirect('admin/users.php');
        }

        throw new RuntimeException('Unknown user management action.');
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}

$adminUsers = cms_get_admin_users();
$selectedAdminId = $postAction !== '' && in_array($postAction, ['update_name', 'update_password'], true)
    ? max(0, (int) cms_post('admin_id'))
    : max(0, (int) cms_query('edit', (string) ($currentAdmin['id'] ?? 0)));

if ($selectedAdminId <= 0 && $adminUsers !== []) {
    $selectedAdminId = (int) ($adminUsers[0]['id'] ?? 0);
}

$selectedAdmin = $selectedAdminId > 0 ? cms_get_admin_user($selectedAdminId) : null;

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-users-gear"></i> Admin Access</span>
    <h1 class="admin-page-title">Manage admin users</h1>
    <p class="admin-page-copy">Create new admin logins for your team. New users receive a Gmail verification email and must verify their address before they can sign in.</p>
  </div>
  <div class="admin-user-chip"><i class="fas fa-user-shield"></i> <?= count($adminUsers) ?> Admin<?= count($adminUsers) === 1 ? '' : 's' ?></div>
</section>

<?php if ($errorMessage !== ''): ?>
  <div class="flash flash-error"><?= cms_e($errorMessage) ?></div>
<?php endif; ?>

<section class="content-grid users-layout">
  <article class="admin-card user-create-card">
    <div class="toolbar">
      <div>
        <h3>Add a new admin user</h3>
        <p class="muted">Each admin signs in with a unique email address and password.</p>
      </div>
    </div>

    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="create_admin" />
      <div class="form-grid two-col">
        <div class="field">
          <label for="new_full_name">Full name</label>
          <input id="new_full_name" type="text" name="full_name" value="<?= cms_e($postAction === 'create_admin' ? (string) cms_post('full_name') : '') ?>" required />
        </div>
        <div class="field">
          <label for="new_email">Email address</label>
          <input id="new_email" type="email" name="email" value="<?= cms_e($postAction === 'create_admin' ? (string) cms_post('email') : '') ?>" required />
        </div>
      </div>
      <div class="form-grid two-col">
        <div class="field">
          <label for="new_password">Password</label>
          <input id="new_password" type="password" name="password" minlength="8" required />
          <p class="helper-text">Use at least 8 characters for each admin password.</p>
        </div>
        <div class="field">
          <label for="new_role">Role</label>
          <select id="new_role" name="role">
            <?php foreach (cms_roles() as $roleKey => $roleLabel): ?>
              <option value="<?= cms_e($roleKey) ?>" <?= $roleKey === 'editor' ? 'selected' : '' ?>><?= cms_e($roleLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="helper-text">Super Admin has full access. Editor can manage content. Author can write. Viewer is read-only.</p>
        </div>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Add Admin User</button>
    </form>
  </article>

  <article class="admin-card user-list-card">
    <div class="toolbar">
      <div>
        <h3>Current admin users</h3>
        <p class="muted">Choose a user to update their name or reset their password.</p>
      </div>
    </div>

    <?php if ($adminUsers === []): ?>
      <div class="empty-state">No admin users found yet.</div>
    <?php else: ?>
      <div class="user-directory">
        <?php foreach ($adminUsers as $adminUser): ?>
          <?php $isCurrentAdmin = (int) ($currentAdmin['id'] ?? 0) === (int) $adminUser['id']; ?>
          <?php $roles = cms_roles(); ?>
          <?php $isVerified = (int) ($adminUser['is_verified'] ?? 1) === 1; ?>
          <article class="user-directory-item<?= $isCurrentAdmin ? ' is-current' : '' ?>">
            <div class="user-avatar" aria-hidden="true">
              <?= cms_e(strtoupper(substr((string) $adminUser['full_name'], 0, 1) ?: 'A')) ?>
            </div>
            <div class="user-directory-main">
              <div class="user-directory-title">
                <strong><?= cms_e((string) $adminUser['full_name']) ?></strong>
                <span><?= $isCurrentAdmin ? 'Signed in now' : 'Admin user' ?></span>
              </div>
              <a href="mailto:<?= cms_e((string) $adminUser['email']) ?>" class="user-directory-email"><?= cms_e((string) $adminUser['email']) ?></a>
              <div class="user-directory-badges">
                <span class="status-badge status-published"><?= cms_e($roles[(string) ($adminUser['role'] ?? 'super_admin')] ?? 'Super Admin') ?></span>
                <?php if ($isVerified): ?>
                  <span class="status-badge status-published"><i class="fas fa-circle-check"></i> Verified</span>
                <?php else: ?>
                  <span class="status-badge status-draft"><i class="fas fa-clock"></i> Pending</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="user-directory-meta">
              <span>Last login</span>
              <strong><?= cms_e(cms_format_date((string) ($adminUser['last_login_at'] ?? ''), 'M j, Y g:i A') ?: 'Never') ?></strong>
            </div>
            <div class="user-directory-actions">
              <a href="<?= cms_e(cms_url('admin/users.php?edit=' . (int) $adminUser['id'])) ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-pen"></i> Edit
              </a>
              <?php if (!$isVerified && cms_current_admin_can('super_admin')): ?>
                <form method="post">
                  <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="resend_verification" />
                  <input type="hidden" name="admin_id" value="<?= (int) $adminUser['id'] ?>" />
                  <button type="submit" class="btn btn-secondary btn-sm" title="Resend verification email">
                    <i class="fas fa-envelope"></i> Resend
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</section>

<?php if ($selectedAdmin): ?>
  <section class="split-grid" style="margin-top:24px">
    <article class="admin-card">
      <div class="toolbar">
        <div>
          <h3>Update name</h3>
          <p class="muted">Change how this admin appears across the dashboard.</p>
        </div>
      </div>

      <form method="post" class="form-grid">
        <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
        <input type="hidden" name="action" value="update_name" />
        <input type="hidden" name="admin_id" value="<?= (int) $selectedAdmin['id'] ?>" />
        <div class="field">
          <label for="edit_full_name">Full name</label>
          <input
            id="edit_full_name"
            type="text"
            name="full_name"
            value="<?= cms_e((string) ($postAction === 'update_name' && (int) cms_post('admin_id') === (int) $selectedAdmin['id'] ? cms_post('full_name') : $selectedAdmin['full_name'])) ?>"
            required
          />
        </div>
        <div class="field">
          <label>Email address</label>
          <input type="text" value="<?= cms_e((string) $selectedAdmin['email']) ?>" readonly />
        </div>
        <?php if (cms_current_admin_can('super_admin')): ?>
        <div class="field">
          <label for="edit_role">Role</label>
          <select id="edit_role" name="role">
            <?php foreach (cms_roles() as $roleKey => $roleLabel): ?>
              <option value="<?= cms_e($roleKey) ?>" <?= ($selectedAdmin['role'] ?? 'super_admin') === $roleKey ? 'selected' : '' ?>><?= cms_e($roleLabel) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="fas fa-id-card"></i> Save User</button>
      </form>
    </article>

    <article class="admin-card">
      <div class="toolbar">
        <div>
          <h3>Reset password</h3>
          <p class="muted">Set a new sign-in password for this admin user.</p>
        </div>
      </div>

      <form method="post" class="form-grid">
        <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
        <input type="hidden" name="action" value="update_password" />
        <input type="hidden" name="admin_id" value="<?= (int) $selectedAdmin['id'] ?>" />
        <div class="field">
          <label for="edit_password">New password</label>
          <input id="edit_password" type="password" name="password" minlength="8" required />
        </div>
        <div class="field">
          <label for="edit_password_confirm">Confirm new password</label>
          <input id="edit_password_confirm" type="password" name="password_confirm" minlength="8" required />
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Update Password</button>
      </form>
    </article>
  </section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
