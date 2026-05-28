<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/public-layout.php';

$email   = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$done    = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email !== '') {
    $done    = cms_newsletter_unsubscribe($email);
    $message = $done ? 'You have been unsubscribed.' : 'Email not found or already unsubscribed.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Unsubscribe | A1 Technovation</title>
  <meta name="robots" content="noindex">
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/style.css?v=20260513')) ?>" />
</head>
<body>
<?php cms_public_nav(''); ?>
<section style="min-height:60vh;display:flex;align-items:center;justify-content:center;padding:80px 20px;text-align:center">
  <div style="max-width:480px">
    <?php if ($done): ?>
      <div style="font-size:64px;margin-bottom:24px">👋</div>
      <h1 style="color:#fff;font-size:32px;margin:0 0 16px">Unsubscribed</h1>
      <p style="color:#9ca3af;font-size:18px;margin:0 0 32px"><?= cms_e($message) ?> You won't receive any more emails from us.</p>
      <a href="<?= cms_e(cms_url('')) ?>" class="btn btn-outline">Go Home</a>
    <?php elseif ($message !== ''): ?>
      <h1 style="color:#fff;font-size:28px;margin:0 0 16px">Hmm, something went wrong</h1>
      <p style="color:#9ca3af;margin:0 0 24px"><?= cms_e($message) ?></p>
      <a href="<?= cms_e(cms_url('')) ?>" class="btn btn-outline">Go Home</a>
    <?php else: ?>
      <h1 style="color:#fff;font-size:32px;margin:0 0 16px">Unsubscribe</h1>
      <p style="color:#9ca3af;font-size:18px;margin:0 0 32px">Enter your email to unsubscribe from our newsletter.</p>
      <form method="post" style="display:flex;flex-direction:column;gap:12px;max-width:360px;margin:0 auto">
        <input type="email" name="email" value="<?= cms_e($email) ?>" placeholder="your@email.com" required
               style="padding:12px 16px;border-radius:8px;border:1px solid #3a4250;background:#1c1e21;color:#fff;font-size:15px">
        <button type="submit" class="btn btn-danger">Unsubscribe</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php cms_public_footer(); ?>
</body>
</html>
