<?php
declare(strict_types=1);

function cms_admin_count(): int
{
    if (!cms_table_exists('admin_users')) {
        return 0;
    }

    return (int) cms_db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
}

function cms_setup_required(): bool
{
    return cms_admin_count() === 0;
}

function cms_validate_admin_name(string $fullName): string
{
    $fullName = trim($fullName);

    if ($fullName === '') {
        throw new RuntimeException('Admin full name is required.');
    }

    return $fullName;
}

function cms_validate_admin_email(string $email): string
{
    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Enter a valid admin email address.');
    }

    return $email;
}

function cms_validate_admin_password(string $password): string
{
    if (strlen($password) < 8) {
        throw new RuntimeException('Admin password must be at least 8 characters long.');
    }

    return $password;
}

function cms_admin_exists_by_email(string $email, ?int $ignoreId = null): bool
{
    $email = cms_validate_admin_email($email);

    if (!cms_table_exists('admin_users')) {
        return false;
    }

    $sql = 'SELECT id FROM admin_users WHERE email = :email';
    $params = ['email' => $email];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $sql .= ' LIMIT 1';

    $statement = cms_db()->prepare($sql);
    $statement->execute($params);

    return (bool) $statement->fetchColumn();
}

function cms_create_admin(string $fullName, string $email, string $password): int
{
    $fullName = cms_validate_admin_name($fullName);
    $email    = cms_validate_admin_email($email);
    $password = cms_validate_admin_password($password);

    if (cms_admin_exists_by_email($email)) {
        throw new RuntimeException('An admin account with this email already exists. Please sign in instead.');
    }

    // First admin ever (setup) is auto-verified. All subsequent admins need email verification.
    $isFirstAdmin = cms_admin_count() === 0;
    $token        = $isFirstAdmin ? null : bin2hex(random_bytes(32));
    $isVerified   = $isFirstAdmin ? 1 : 0;

    $statement = cms_db()->prepare(
        'INSERT INTO admin_users (full_name, email, password_hash, is_verified, verification_token, verification_sent_at)
         VALUES (:full_name, :email, :password_hash, :is_verified, :token, :sent_at)'
    );
    $statement->execute([
        'full_name'    => $fullName,
        'email'        => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_verified'  => $isVerified,
        'token'        => $token,
        'sent_at'      => $token !== null ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : null,
    ]);

    $newId = (int) cms_db()->lastInsertId();

    if ($token !== null) {
        try {
            cms_send_verification_email($email, $fullName, $token);
        } catch (Throwable $e) {
            // Log but don't block account creation — admin can resend later
            error_log('CMS: verification email failed for ' . $email . ': ' . $e->getMessage());
        }
    }

    return $newId;
}

function cms_send_verification_email(string $email, string $fullName, string $token): void
{
    require_once cms_root('php/SmtpMailer.php');

    // config_bool() lives in php/bootstrap.php — define it here if not already loaded
    if (!function_exists('config_bool')) {
        function config_bool(mixed $value): bool
        {
            if (is_bool($value)) {
                return $value;
            }
            if (is_string($value)) {
                return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
            }
            return (bool) $value;
        }
    }

    $smtpConfig  = (array) cms_config('smtp', []);
    $mailConfig  = (array) cms_config('mail', []);
    $verifyUrl   = rtrim((string) cms_config('app.base_url', ''), '/') . '/admin/verify-email.php?token=' . urlencode($token);
    $siteName    = (string) cms_config('app.name', 'CMS');
    $fromEmail   = (string) ($mailConfig['from_email'] ?? $smtpConfig['username'] ?? '');
    $fromName    = (string) ($mailConfig['from_name'] ?? $siteName);

    if ($smtpConfig === [] || ($smtpConfig['username'] ?? '') === '' || ($smtpConfig['password'] ?? '') === '') {
        throw new RuntimeException('SMTP is not configured. Add smtp credentials to config/cms.php.');
    }

    $html = <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
    <body style="margin:0;padding:0;background:#f4f6f9;font-family:Inter,Arial,sans-serif">
      <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0">
        <tr><td align="center">
          <table width="520" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 16px rgba(0,0,0,.06)">
            <tr>
              <td style="background:linear-gradient(135deg,#2563eb,#1e40af);padding:32px 40px;text-align:center">
                <p style="color:rgba(255,255,255,.75);font-size:13px;margin:0 0 4px;letter-spacing:.08em;text-transform:uppercase">$siteName</p>
                <h1 style="color:#fff;font-size:22px;margin:0;font-weight:700">Verify your email address</h1>
              </td>
            </tr>
            <tr>
              <td style="padding:36px 40px">
                <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 20px">Hi <strong>$fullName</strong>,</p>
                <p style="color:#374151;font-size:15px;line-height:1.6;margin:0 0 28px">
                  A new admin account was created for you on <strong>$siteName</strong>.
                  Click the button below to verify your email address and activate your account.
                  This link expires in <strong>24 hours</strong>.
                </p>
                <p style="text-align:center;margin:0 0 28px">
                  <a href="$verifyUrl"
                     style="display:inline-block;background:#2563eb;color:#fff;font-size:15px;font-weight:600;padding:14px 32px;border-radius:8px;text-decoration:none">
                    Verify Email &amp; Activate Account
                  </a>
                </p>
                <p style="color:#6b7280;font-size:13px;line-height:1.6;margin:0 0 8px">
                  If the button does not work, copy and paste this URL into your browser:
                </p>
                <p style="background:#f3f4f6;border-radius:6px;padding:10px 14px;font-size:12px;font-family:monospace;color:#374151;word-break:break-all;margin:0 0 24px">
                  $verifyUrl
                </p>
                <p style="color:#9ca3af;font-size:12px;margin:0">
                  If you did not expect this email, you can safely ignore it. No account will be activated without verification.
                </p>
              </td>
            </tr>
            <tr>
              <td style="background:#f9fafb;padding:20px 40px;border-top:1px solid #e5e7eb;text-align:center">
                <p style="color:#9ca3af;font-size:12px;margin:0">Sent by $siteName · Do not reply to this email</p>
              </td>
            </tr>
          </table>
        </td></tr>
      </table>
    </body>
    </html>
    HTML;

    $text = "Hi $fullName,\n\nVerify your CMS account by visiting:\n$verifyUrl\n\nThis link expires in 24 hours.\n\n$siteName";

    $mailer = new SmtpMailer($smtpConfig);
    $mailer->send([
        'from_email' => $fromEmail,
        'from_name'  => $fromName,
        'to_email'   => $email,
        'to_name'    => $fullName,
        'subject'    => 'Verify your ' . $siteName . ' account',
        'html'       => $html,
        'text'       => $text,
    ]);
}

function cms_resend_verification_email(int $adminId): void
{
    $admin = cms_get_admin_user_full($adminId);
    if (!$admin) {
        throw new RuntimeException('Admin user not found.');
    }
    if ((int) ($admin['is_verified'] ?? 0) === 1) {
        throw new RuntimeException('This account is already verified.');
    }

    $token = bin2hex(random_bytes(32));
    $stmt  = cms_db()->prepare(
        'UPDATE admin_users SET verification_token = :token, verification_sent_at = NOW() WHERE id = :id'
    );
    $stmt->execute(['token' => $token, 'id' => $adminId]);

    cms_send_verification_email((string) $admin['email'], (string) $admin['full_name'], $token);
}

function cms_verify_email_token(string $token): bool
{
    if (strlen($token) < 32 || !cms_table_exists('admin_users')) {
        return false;
    }

    if (!cms_column_exists('admin_users', 'verification_token')) {
        return false;
    }

    // Token expires after 24 hours
    $stmt = cms_db()->prepare(
        'SELECT id FROM admin_users
         WHERE verification_token = :token
           AND is_verified = 0
           AND verification_sent_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         LIMIT 1'
    );
    $stmt->execute(['token' => $token]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return false;
    }

    $update = cms_db()->prepare(
        'UPDATE admin_users SET is_verified = 1, verification_token = NULL WHERE id = :id'
    );
    $update->execute(['id' => (int) $row['id']]);

    return true;
}

function cms_get_admin_user_full(int $id): ?array
{
    if ($id <= 0 || !cms_table_exists('admin_users')) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function cms_get_admin_user(int $id): ?array
{
    if ($id <= 0 || !cms_table_exists('admin_users')) {
        return null;
    }

    $statement = cms_db()->prepare(
        'SELECT id, full_name, email, role, is_verified, last_login_at, created_at, updated_at FROM admin_users WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $admin = $statement->fetch();

    return is_array($admin) ? $admin : null;
}

function cms_get_admin_users(): array
{
    if (!cms_table_exists('admin_users')) {
        return [];
    }

    $statement = cms_db()->query(
        'SELECT id, full_name, email, role, is_verified, last_login_at, created_at, updated_at FROM admin_users ORDER BY full_name ASC, email ASC'
    );

    return $statement->fetchAll() ?: [];
}

function cms_update_admin_name(int $id, string $fullName): void
{
    $admin = cms_get_admin_user($id);

    if (!$admin) {
        throw new RuntimeException('The selected admin user could not be found.');
    }

    $statement = cms_db()->prepare('UPDATE admin_users SET full_name = :full_name WHERE id = :id');
    $statement->execute([
        'id' => $id,
        'full_name' => cms_validate_admin_name($fullName),
    ]);
}

function cms_update_admin_password(int $id, string $password): void
{
    $admin = cms_get_admin_user($id);

    if (!$admin) {
        throw new RuntimeException('The selected admin user could not be found.');
    }

    $statement = cms_db()->prepare('UPDATE admin_users SET password_hash = :password_hash WHERE id = :id');
    $statement->execute([
        'id' => $id,
        'password_hash' => password_hash(cms_validate_admin_password($password), PASSWORD_DEFAULT),
    ]);
}

function cms_current_admin(): ?array
{
    $adminId = $_SESSION['cms_admin_id'] ?? null;

    if (!is_int($adminId) && !ctype_digit((string) $adminId)) {
        return null;
    }

    if (!cms_table_exists('admin_users')) {
        return null;
    }

    $statement = cms_db()->prepare(
        'SELECT id, full_name, email, role, is_verified, last_login_at, created_at, updated_at FROM admin_users WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => (int) $adminId]);
    $admin = $statement->fetch();

    return is_array($admin) ? $admin : null;
}

function cms_is_logged_in(): bool
{
    return cms_current_admin() !== null;
}

function cms_login(string $email, string $password): bool
{
    if (!cms_table_exists('admin_users')) {
        throw new RuntimeException('The CMS database tables have not been installed yet.');
    }

    $statement = cms_db()->prepare('SELECT id, full_name, email, password_hash, role, is_verified, last_login_at FROM admin_users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => strtolower(trim($email))]);
    $admin = $statement->fetch();

    if (!is_array($admin) || !password_verify($password, (string) $admin['password_hash'])) {
        return false;
    }

    // Block login for unverified accounts (column may not exist on old installs)
    if (array_key_exists('is_verified', $admin) && (int) $admin['is_verified'] === 0) {
        throw new RuntimeException('Your account is not yet verified. Please check your email for the verification link, or ask an admin to resend it.');
    }

    session_regenerate_id(true);
    $_SESSION['cms_admin_id'] = (int) $admin['id'];
    $_SESSION['cms_last_active_at'] = time();

    $updateStatement = cms_db()->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = :id');
    $updateStatement->execute(['id' => (int) $admin['id']]);

    return true;
}

function cms_logout(): void
{
    unset($_SESSION['cms_admin_id'], $_SESSION['cms_last_active_at']);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function cms_require_login(): void
{
    if (!cms_is_logged_in()) {
        cms_flash_set('error', 'Please sign in to access the CMS.');
        cms_redirect('admin/login.php');
    }
}
