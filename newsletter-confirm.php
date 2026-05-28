<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/public-layout.php';

$token   = trim((string) ($_GET['token'] ?? ''));
$success = false;
if ($token !== '') {
    $success = cms_newsletter_confirm($token);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $success ? 'Subscription Confirmed' : 'Invalid Link' ?> | A1 Technovation</title>
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
    <?php if ($success): ?>
      <div style="font-size:64px;margin-bottom:24px">🎉</div>
      <h1 style="color:#fff;font-size:32px;margin:0 0 16px">You're subscribed!</h1>
      <p style="color:#9ca3af;font-size:18px;margin:0 0 32px">Thank you for confirming your email. You'll receive our latest updates and insights.</p>
      <a href="<?= cms_e(cms_url('blog')) ?>" class="btn btn-primary">Read the Blog</a>
    <?php else: ?>
      <div style="font-size:64px;margin-bottom:24px">🔗</div>
      <h1 style="color:#fff;font-size:32px;margin:0 0 16px">Invalid or expired link</h1>
      <p style="color:#9ca3af;font-size:18px;margin:0 0 32px">This confirmation link is invalid or has already been used. Please sign up again.</p>
      <a href="<?= cms_e(cms_url('')) ?>" class="btn btn-outline">Go Home</a>
    <?php endif; ?>
  </div>
</section>
<?php cms_public_footer(); ?>
</body>
</html>
