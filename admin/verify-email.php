<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

$token   = trim((string) cms_query('token'));
$success = false;
$error   = '';

if ($token === '') {
    $error = 'No verification token provided. Please use the link from your email.';
} else {
    try {
        if (cms_verify_email_token($token)) {
            $success = true;
            cms_log_activity('email_verified', 'user');
        } else {
            $error = 'This verification link is invalid or has expired (links expire after 24 hours). Please ask a Super Admin to resend the verification email.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$siteName = (string) cms_config('app.name', 'CMS');
$loginUrl = cms_url('admin/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Email Verification | <?= htmlspecialchars($siteName, ENT_QUOTES) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f0f4ff;
      font-family: Inter, system-ui, sans-serif;
    }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 4px 32px rgba(37,99,235,.10);
      padding: 48px 44px;
      max-width: 460px;
      width: 100%;
      text-align: center;
    }
    .icon-wrap {
      width: 72px;
      height: 72px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 28px;
      margin-bottom: 24px;
    }
    .icon-success { background: #dcfce7; color: #16a34a; }
    .icon-error   { background: #fee2e2; color: #dc2626; }
    h1 { font-size: 1.4rem; font-weight: 700; color: #111827; margin: 0 0 12px; }
    p  { font-size: .95rem; color: #6b7280; line-height: 1.65; margin: 0 0 28px; }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: #2563eb;
      color: #fff;
      font-size: .9rem;
      font-weight: 600;
      padding: 12px 28px;
      border-radius: 8px;
      text-decoration: none;
      transition: background .15s;
    }
    .btn:hover { background: #1d4ed8; }
    .brand {
      font-size: .75rem;
      color: #d1d5db;
      margin-top: 32px;
      letter-spacing: .05em;
      text-transform: uppercase;
    }
  </style>
</head>
<body>
  <div class="card">
    <?php if ($success): ?>
      <div class="icon-wrap icon-success"><i class="fas fa-circle-check"></i></div>
      <h1>Email verified!</h1>
      <p>Your account has been activated. You can now sign in to the <?= htmlspecialchars($siteName, ENT_QUOTES) ?> admin panel.</p>
      <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES) ?>" class="btn">
        <i class="fas fa-right-to-bracket"></i> Sign in now
      </a>
    <?php else: ?>
      <div class="icon-wrap icon-error"><i class="fas fa-circle-xmark"></i></div>
      <h1>Verification failed</h1>
      <p><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
      <a href="<?= htmlspecialchars($loginUrl, ENT_QUOTES) ?>" class="btn" style="background:#6b7280">
        <i class="fas fa-arrow-left"></i> Back to login
      </a>
    <?php endif; ?>
    <p class="brand"><?= htmlspecialchars($siteName, ENT_QUOTES) ?></p>
  </div>
</body>
</html>
