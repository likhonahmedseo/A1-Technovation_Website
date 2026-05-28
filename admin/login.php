<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

if (cms_setup_required()) {
    cms_redirect('admin/setup.php');
}

if (cms_is_logged_in()) {
    cms_redirect('admin/dashboard.php');
}

$errorMessage = '';
$flash = cms_flash_get();
$dbError = '';

try {
    cms_db();
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

if (cms_is_post()) {
    try {
        cms_require_csrf();

        if (cms_login((string) cms_post('email'), (string) cms_post('password'))) {
            cms_flash_set('success', 'Welcome back. You are now signed in.');
            cms_redirect('admin/dashboard.php');
        }

        $errorMessage = 'The email or password did not match any admin account.';
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Login | A1 Technovation CMS</title>
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('admin/assets/admin.css?v=20260513b')) ?>" />
</head>
<body>
  <div class="admin-auth">
    <section class="auth-card">
      <span class="auth-kicker"><i class="fas fa-shield-halved"></i> Secure Admin</span>
      <h1>Sign in to A1 CMS</h1>
      <p class="auth-copy">Manage blog posts, upload media, pages, and admin users for the A1 Technovation website from one secure dashboard.</p>

      <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
        <div class="flash flash-<?= cms_e((string) $flash['type']) ?>"><?= cms_e((string) $flash['message']) ?></div>
      <?php endif; ?>

      <?php if ($dbError !== ''): ?>
        <div class="flash flash-warning"><?= cms_e($dbError) ?></div>
      <?php endif; ?>

      <?php if ($errorMessage !== ''): ?>
        <div class="flash flash-error"><?= cms_e($errorMessage) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
        <div class="field">
          <label for="email">Admin email</label>
          <input id="email" type="email" name="email" value="<?= cms_e((string) cms_post('email')) ?>" required />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required />
        </div>
        <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-right-to-bracket"></i> Login to Dashboard</button>
      </form>

      <p class="helper-text" style="margin-top:20px">Need the first admin account instead? Use <a href="<?= cms_e(cms_url('admin/setup.php')) ?>" style="color:var(--admin-primary);font-weight:700">the setup page</a>.</p>
    </section>
  </div>
</body>
</html>
