<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('super_admin');

$pageTitle = 'Role Permissions';
$activeNav = 'permissions';
$flash     = [];

$modules = ['posts', 'pages', 'media', 'seo', 'settings', 'users', 'forms', 'newsletter', 'api', 'cache'];
$roles   = ['super_admin', 'editor', 'author', 'viewer'];
$actions = ['view', 'create', 'edit', 'delete'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cms_require_csrf();
    $role = (string) ($_POST['role'] ?? '');
    if (in_array($role, $roles, true)) {
        $modulePerms = [];
        foreach ($modules as $mod) {
            $modulePerms[$mod] = [
                'view'   => isset($_POST[$mod . '_view']) ? 1 : 0,
                'create' => isset($_POST[$mod . '_create']) ? 1 : 0,
                'edit'   => isset($_POST[$mod . '_edit']) ? 1 : 0,
                'delete' => isset($_POST[$mod . '_delete']) ? 1 : 0,
            ];
        }
        cms_save_role_permissions($role, $modulePerms);
        cms_log_activity('permissions_updated', 'role', 0, "Updated permissions for role: {$role}");
        $flash = ['type' => 'success', 'message' => 'Permissions for "' . $role . '" saved.'];
    }
}

$activeRole = in_array($_GET['role'] ?? '', $roles, true) ? $_GET['role'] : 'editor';

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-shield-halved"></i> Access Control</span>
    <h1 class="admin-page-title">Role Permissions</h1>
    <p class="admin-page-copy">Define exactly what each role can view, create, edit, or delete per module.</p>
  </div>
</section>

<?php if ($flash): ?>
  <div class="admin-flash admin-flash-<?= cms_e($flash['type']) ?>"><?= cms_e($flash['message']) ?></div>
<?php endif; ?>

<!-- Role tabs -->
<div class="admin-tabs" style="margin-bottom:24px">
  <?php foreach ($roles as $role): ?>
    <a href="?role=<?= urlencode($role) ?>" class="admin-tab<?= $activeRole === $role ? ' active' : '' ?>"><?= cms_e(str_replace('_', ' ', ucwords($role, '_'))) ?></a>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <div class="admin-card-head">
    <h2>Permissions: <?= cms_e(str_replace('_', ' ', ucwords($activeRole, '_'))) ?></h2>
    <?php if ($activeRole === 'super_admin'): ?>
      <span style="color:var(--admin-muted);font-size:13px">Super Admin always has full access and cannot be restricted.</span>
    <?php endif; ?>
  </div>

  <form method="post" class="admin-form">
    <?= cms_csrf_field() ?>
    <input type="hidden" name="role" value="<?= cms_e($activeRole) ?>">

    <table class="admin-table">
      <thead>
        <tr>
          <th>Module</th>
          <?php foreach ($actions as $action): ?>
            <th style="text-align:center"><?= ucfirst($action) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($modules as $mod): ?>
        <tr>
          <td><strong><?= cms_e(ucfirst($mod)) ?></strong></td>
          <?php foreach ($actions as $action): ?>
            <td style="text-align:center">
              <?php
              $checked = cms_role_permission($activeRole, $mod, $action);
              $disabled = $activeRole === 'super_admin' ? 'disabled' : '';
              ?>
              <input type="checkbox"
                name="<?= cms_e($mod . '_' . $action) ?>"
                value="1"
                <?= $checked ? 'checked' : '' ?>
                <?= $disabled ?>
                <?= $activeRole === 'super_admin' ? 'checked' : '' ?>>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($activeRole !== 'super_admin'): ?>
    <div class="admin-form-actions" style="margin-top:20px">
      <button type="submit" class="admin-btn admin-btn-primary"><i class="fas fa-save"></i> Save Permissions</button>
    </div>
    <?php endif; ?>
  </form>
</div>

<div class="admin-card" style="margin-top:24px">
  <div class="admin-card-head"><h2>Default Role Access Summary</h2></div>
  <table class="admin-table">
    <thead><tr><th>Role</th><th>Description</th></tr></thead>
    <tbody>
      <tr><td><strong>super_admin</strong></td><td>Full access to everything, including users and system settings.</td></tr>
      <tr><td><strong>editor</strong></td><td>Full content control (posts, pages, media, SEO, forms, newsletter). Cannot manage users or API.</td></tr>
      <tr><td><strong>author</strong></td><td>Create and edit own posts. Read-only on pages and media.</td></tr>
      <tr><td><strong>viewer</strong></td><td>Read-only access to posts, pages, forms, and media.</td></tr>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
