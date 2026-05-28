<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

if (!cms_setup_required()) {
    cms_redirect(cms_is_logged_in() ? 'admin/dashboard.php' : 'admin/login.php');
}

$errorMessage = '';
$successMessage = '';
$dbError = '';

try {
    cms_db();
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

if (cms_is_post() && $dbError === '') {
    try {
        cms_require_csrf();

        if (!cms_table_exists('admin_users')) {
            cms_run_schema();
        }

        if (cms_setup_required()) {
            cms_create_admin(
                (string) cms_post('full_name'),
                (string) cms_post('email'),
                (string) cms_post('password')
            );
        } elseif (cms_admin_exists_by_email((string) cms_post('email'))) {
            cms_flash_set('success', 'The CMS is already installed. Please sign in with your admin account.');
            cms_redirect('admin/login.php');
        }

        if (!cms_login((string) cms_post('email'), (string) cms_post('password'))) {
            throw new RuntimeException('The admin account exists, but the password did not match. Try signing in from the login page.');
        }

        cms_flash_set('success', 'The CMS is ready and the admin account has been created.');
        cms_redirect('admin/dashboard.php');
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
  <title>Setup | A1 Technovation CMS</title>
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('admin/assets/admin.css?v=20260513')) ?>" />
</head>
<body>
  <div class="admin-auth">
    <section class="auth-card">
      <span class="auth-kicker"><i class="fas fa-wand-magic-sparkles"></i> First-Time Setup</span>
      <h1>Finish the CMS install</h1>
      <p class="auth-copy">This step creates the MySQL tables, sets up the first admin user, and prepares the public blog pages for publishing.</p>

      <?php if ($dbError !== ''): ?>
        <div class="flash flash-warning"><?= cms_e($dbError) ?></div>
        <p class="helper-text">Update <code>config/cms.php</code> with the real MySQL host, database, username, and password, then refresh this page.</p>
      <?php endif; ?>

      <?php if ($errorMessage !== ''): ?>
        <div class="flash flash-error"><?= cms_e($errorMessage) ?></div>
      <?php endif; ?>

      <?php if ($successMessage !== ''): ?>
        <div class="flash flash-success"><?= cms_e($successMessage) ?></div>
      <?php endif; ?>

      <form method="post" class="form-grid">
        <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
        <div class="field">
          <label for="full_name">Admin name</label>
          <input id="full_name" type="text" name="full_name" value="<?= cms_e((string) cms_post('full_name')) ?>" required />
        </div>
        <div class="field">
          <label for="email">Admin email</label>
          <input id="email" type="email" name="email" value="<?= cms_e((string) cms_post('email')) ?>" required />
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" required />
        </div>
        <button type="submit" class="btn btn-primary btn-block" <?= $dbError !== '' ? 'disabled' : '' ?>><i class="fas fa-bolt"></i> Create Admin and Install CMS</button>
      </form>
    </section>
  </div>
</body>
</html>
