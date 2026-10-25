<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function wants_json_response(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    return str_contains($accept, 'application/json') || $requestedWith === 'xmlhttprequest';
}

function trim_value(mixed $value, int $maxLength = 0): string
{
    $text = trim((string) $value);

    if ($maxLength > 0 && strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength);
    }

    return $text;
}

function finish_request(?array $config, bool $wantsJson, string $status, string $message, int $httpStatus = 200, array $errors = []): never
{
    if ($wantsJson) {
        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    $location = contact_form_page_path($config);
    $query = http_build_query([
        'form_status' => $status,
        'form_message' => $message,
    ]);

    header('Location: ' . $location . '?' . $query, true, 303);
    exit;
}

function store_contact_submission(array $config, array $lead): bool
{
    $database = is_array($config['database'] ?? null) ? $config['database'] : [];

    if (!config_bool($database['enabled'] ?? false) || trim((string) ($database['name'] ?? '')) === '' || trim((string) ($database['username'] ?? '')) === '') {
        $database = [];
    }

    // Prefer the contact-form DB config, then fall back to the CMS database
    // because the dashboard mailbox reads from the CMS connection.
    $cmsConfigPath = project_root('config/cms.php');
    if ($database === [] && is_file($cmsConfigPath)) {
        $cmsConfig = require $cmsConfigPath;
        $database = is_array($cmsConfig) ? ($cmsConfig['database'] ?? []) : [];
    }

    $dbName = (string) ($database['name'] ?? '');
    $dbUser = (string) ($database['username'] ?? '');

    if ($dbName === '' || $dbUser === '') {
        error_log('[contact-submit][database] Dashboard mailbox DB is not configured.');
        return false;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $database['host'] ?? 'localhost',
        (int) ($database['port'] ?? 3306),
        $dbName
    );

    $pdo = new PDO(
        $dsn,
        $dbUser,
        (string) ($database['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_submissions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name VARCHAR(150) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        phone VARCHAR(50) DEFAULT NULL,
        company VARCHAR(150) DEFAULT NULL,
        service_interest VARCHAR(100) DEFAULT NULL,
        budget_range VARCHAR(80) DEFAULT NULL,
        message TEXT NULL,
        source_page VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        remote_address VARCHAR(45) DEFAULT NULL,
        forwarded_for VARCHAR(255) DEFAULT NULL,
        country_code CHAR(2) DEFAULT NULL,
        country_name VARCHAR(80) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'new',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_contact_submissions_email (email),
        KEY idx_contact_submissions_created_at (created_at),
        KEY idx_contact_submissions_service_interest (service_interest),
        KEY idx_contact_submissions_status (status, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $requiredColumns = [
        'full_name' => "ALTER TABLE contact_submissions ADD COLUMN full_name VARCHAR(150) NOT NULL DEFAULT '' AFTER id",
        'email' => "ALTER TABLE contact_submissions ADD COLUMN email VARCHAR(190) NOT NULL DEFAULT '' AFTER full_name",
        'phone' => "ALTER TABLE contact_submissions ADD COLUMN phone VARCHAR(50) DEFAULT NULL AFTER email",
        'company' => "ALTER TABLE contact_submissions ADD COLUMN company VARCHAR(150) DEFAULT NULL AFTER phone",
        'service_interest' => "ALTER TABLE contact_submissions ADD COLUMN service_interest VARCHAR(100) DEFAULT NULL AFTER company",
        'budget_range' => "ALTER TABLE contact_submissions ADD COLUMN budget_range VARCHAR(80) DEFAULT NULL AFTER service_interest",
        'message' => "ALTER TABLE contact_submissions ADD COLUMN message TEXT NULL AFTER budget_range",
        'source_page' => "ALTER TABLE contact_submissions ADD COLUMN source_page VARCHAR(255) DEFAULT NULL AFTER message",
        'ip_address' => "ALTER TABLE contact_submissions ADD COLUMN ip_address VARCHAR(45) DEFAULT NULL AFTER source_page",
        'remote_address' => "ALTER TABLE contact_submissions ADD COLUMN remote_address VARCHAR(45) DEFAULT NULL AFTER ip_address",
        'forwarded_for' => "ALTER TABLE contact_submissions ADD COLUMN forwarded_for VARCHAR(255) DEFAULT NULL AFTER remote_address",
        'country_code' => "ALTER TABLE contact_submissions ADD COLUMN country_code CHAR(2) DEFAULT NULL AFTER forwarded_for",
        'country_name' => "ALTER TABLE contact_submissions ADD COLUMN country_name VARCHAR(80) DEFAULT NULL AFTER country_code",
        'user_agent' => "ALTER TABLE contact_submissions ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER country_name",
        'status' => "ALTER TABLE contact_submissions ADD COLUMN status VARCHAR(32) NOT NULL DEFAULT 'new' AFTER user_agent",
        'created_at' => "ALTER TABLE contact_submissions ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status",
        'updated_at' => "ALTER TABLE contact_submissions ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];
    $availableColumns = array_flip($pdo->query('SHOW COLUMNS FROM contact_submissions')->fetchAll(PDO::FETCH_COLUMN));
    foreach ($requiredColumns as $column => $sql) {
        if (!isset($availableColumns[$column])) {
            $pdo->exec($sql);
        }
    }

    $availableColumns = array_flip($pdo->query('SHOW COLUMNS FROM contact_submissions')->fetchAll(PDO::FETCH_COLUMN));
    $values = [
        'full_name' => $lead['name'],
        'email' => $lead['email'],
        'phone' => $lead['phone'] !== '' ? $lead['phone'] : null,
        'company' => $lead['company'] !== '' ? $lead['company'] : null,
        'service_interest' => $lead['service_label'],
        'budget_range' => $lead['budget'] !== '' ? $lead['budget'] : null,
        'message' => $lead['message'],
        'source_page' => $lead['page_url'] !== '' ? $lead['page_url'] : null,
        'ip_address' => $lead['ip_address'] !== '' ? $lead['ip_address'] : null,
        'remote_address' => $lead['remote_address'] !== '' ? $lead['remote_address'] : null,
        'forwarded_for' => $lead['forwarded_for'] !== '' ? $lead['forwarded_for'] : null,
        'country_code' => $lead['country_code'] !== '' ? $lead['country_code'] : null,
        'country_name' => $lead['country_name'] !== '' ? $lead['country_name'] : null,
        'user_agent' => $lead['user_agent'] !== '' ? $lead['user_agent'] : null,
    ];
    $values = array_filter(
        $values,
        static fn (string $column): bool => isset($availableColumns[$column]),
        ARRAY_FILTER_USE_KEY
    );
    $columns = array_keys($values);
    $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
    $statement = $pdo->prepare(sprintf(
        'INSERT INTO contact_submissions (%s) VALUES (%s)',
        implode(', ', $columns),
        implode(', ', $placeholders)
    ));

    $statement->execute(array_combine($placeholders, array_values($values)));

    return true;
}

function mailer_header_text(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
}

function mailer_encode_header(string $value): string
{
    $value = mailer_header_text($value);

    if ($value === '') {
        return '';
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function mailer_format_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = mailer_header_text($name);

    return $name !== '' ? sprintf('%s <%s>', mailer_encode_header($name), $email) : sprintf('<%s>', $email);
}

function send_native_mail(array $message): void
{
    if (!function_exists('mail')) {
        throw new RuntimeException('PHP mail() is not available on this server.');
    }

    $toEmail = trim((string) ($message['to_email'] ?? ''));
    $fromEmail = trim((string) ($message['from_email'] ?? ''));

    if ($toEmail === '' || $fromEmail === '') {
        throw new RuntimeException('Native mail transport requires both to_email and from_email.');
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . mailer_format_address(
            $fromEmail,
            (string) ($message['from_name'] ?? 'A1 Technovation')
        ),
    ];

    if (!empty($message['reply_to_email'])) {
        $headers[] = 'Reply-To: ' . mailer_format_address(
            (string) $message['reply_to_email'],
            (string) ($message['reply_to_name'] ?? '')
        );
    }

    $success = mail(
        mailer_format_address($toEmail, (string) ($message['to_name'] ?? '')),
        mailer_encode_header((string) ($message['subject'] ?? 'Website enquiry')),
        (string) ($message['text'] ?? strip_tags((string) ($message['html'] ?? ''))),
        implode("\r\n", $headers)
    );

    if (!$success) {
        throw new RuntimeException('PHP mail() failed to hand off the message.');
    }
}

function build_contact_email_messages(array $config, array $lead): array
{
    $mailConfig = $config['mail'] ?? [];
    $fromEmail = (string) ($mailConfig['from_email'] ?? '');
    $fromName = (string) ($mailConfig['from_name'] ?? 'A1 Technovation');
    $notificationEmail = (string) ($mailConfig['notification_email'] ?? '');

    if ($fromEmail === '' || $notificationEmail === '') {
        throw new RuntimeException('Mail configuration is incomplete. Check config/contact-form.php.');
    }

    $sourcePath = trim((string) parse_url($lead['page_url'] ?: '', PHP_URL_PATH));
    $sourcePath = $sourcePath !== '' ? $sourcePath : '/';
    $sourceSlug = basename($sourcePath, '.html');
    $sourceSlug = $sourceSlug !== '' ? $sourceSlug : 'website';
    $sourceLabel = ucwords(str_replace(['-', '_'], ' ', $sourceSlug));
    $formTitle = $sourceLabel . ' Contact Form';
    $submittedAt = gmdate('Y-m-d\TH:i:sP');
    $detailRowStyle = 'padding:18px 20px;border:1px solid #d9e2ef;font-size:15px;line-height:1.7;vertical-align:top;';

    $emailLink = '<a href="mailto:' . rawurlencode($lead['email']) . '" style="color:#2563eb;text-decoration:underline;">' . htmlspecialchars($lead['email']) . '</a>';
    $websiteValue = $lead['website'] !== ''
        ? '<a href="' . htmlspecialchars($lead['website']) . '" style="color:#2563eb;text-decoration:underline;">' . htmlspecialchars($lead['website']) . '</a>'
        : 'Not provided';
    $sourcePageValue = $lead['page_url'] !== ''
        ? '<a href="' . htmlspecialchars($lead['page_url']) . '" style="color:#2563eb;text-decoration:underline;">' . htmlspecialchars($lead['page_url']) . '</a>'
        : 'Unknown';
    $countryValue = htmlspecialchars($lead['country_name'] ?: 'Unavailable') . ($lead['country_code'] !== '' ? ' (' . htmlspecialchars($lead['country_code']) . ')' : '');
    $messageValue = nl2br(htmlspecialchars($lead['message']));

    $rows = [
        ['Name', htmlspecialchars($lead['name'])],
        ['Email', $emailLink],
        ['Phone', htmlspecialchars($lead['phone'] ?: 'Not provided')],
        ['Company', htmlspecialchars($lead['company'] ?: 'Not provided')],
        ['WhatsApp', 'Not provided'],
        ['Website', $websiteValue],
        ['Service Needed', htmlspecialchars($lead['service_label'])],
        ['Monthly Budget', htmlspecialchars($lead['budget'] ?: 'Not specified')],
        ['Message', '<div style="white-space:normal;line-height:1.8;">' . $messageValue . '</div>'],
        ['Submitted', htmlspecialchars($submittedAt)],
        ['IP Address', htmlspecialchars($lead['ip_address'] ?: 'Unavailable')],
        ['Country', $countryValue],
        ['Remote Address', htmlspecialchars($lead['remote_address'] ?: 'Unavailable')],
        ['Forwarded For', htmlspecialchars($lead['forwarded_for'] ?: 'Unavailable')],
        ['Source Page', $sourcePageValue],
    ];

    $rowsHtml = '';
    foreach ($rows as [$label, $value]) {
        $isMessageRow = $label === 'Message';
        $rowsHtml .= '
            <tr>
                <td style="width:31%;' . $detailRowStyle . 'background:#f5f7fb;color:#1e2f4d;font-weight:700;">' . $label . '</td>
                <td style="' . $detailRowStyle . 'background:#ffffff;color:#162033;' . ($isMessageRow ? 'min-height:220px;' : '') . '">' . $value . '</td>
            </tr>';
    }

    $notificationHtml = '
        <!doctype html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>New Website Lead</title>
        </head>
        <body style="margin:0;padding:32px 16px;background:#eef2f7;font-family:Arial,Helvetica,sans-serif;color:#162033;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="max-width:920px;margin:0 auto;background:#ffffff;border:1px solid #d8e1ee;border-radius:18px;overflow:hidden;">
                <tr>
                    <td style="padding:0;">
                        <div style="background:#121a2b;padding:28px 36px;">
                            <div style="margin:0 0 10px;color:#9dc3ff;font-size:14px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;">New Website Lead</div>
                            <div style="margin:0;color:#ffffff;font-size:28px;line-height:1.25;font-weight:700;">' . htmlspecialchars($formTitle) . '</div>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:36px;">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 18px;border-collapse:collapse;">
                            <tr>
                                <td style="padding:0 0 10px;color:#51627f;font-size:13px;letter-spacing:0.08em;text-transform:uppercase;font-weight:700;">Lead Summary</td>
                            </tr>
                            <tr>
                                <td style="padding:0;color:#23324d;font-size:15px;line-height:1.7;">A new website enquiry has been submitted and is ready for follow-up. The full contact details, budget, message, and source metadata are listed below.</td>
                            </tr>
                        </table>
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">
                            ' . $rowsHtml . '
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>';
    $notificationText =
        "NEW WEBSITE LEAD\n" .
        "{$formTitle}\n\n" .
        "Name: {$lead['name']}\n" .
        "Email: {$lead['email']}\n" .
        "Phone: " . ($lead['phone'] ?: 'Not provided') . "\n" .
        "Company: " . ($lead['company'] ?: 'Not provided') . "\n" .
        "WhatsApp: Not provided\n" .
        "Website: " . ($lead['website'] ?: 'Not provided') . "\n" .
        "Service Needed: {$lead['service_label']}\n" .
        "Monthly Budget: " . ($lead['budget'] ?: 'Not specified') . "\n" .
        "Submitted: {$submittedAt}\n" .
        "IP Address: " . ($lead['ip_address'] ?: 'Unavailable') . "\n" .
        "Country: " . ($lead['country_name'] ?: 'Unavailable') . ($lead['country_code'] !== '' ? " ({$lead['country_code']})" : '') . "\n" .
        "Remote Address: " . ($lead['remote_address'] ?: 'Unavailable') . "\n" .
        "Forwarded For: " . ($lead['forwarded_for'] ?: 'Unavailable') . "\n" .
        "Source Page: " . ($lead['page_url'] ?: 'Unknown') . "\n\n" .
        "Message:\n{$lead['message']}";
    $messages = [[
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'to_email' => $notificationEmail,
        'to_name' => (string) ($mailConfig['notification_name'] ?? 'A1 Technovation'),
        'reply_to_email' => $lead['email'],
        'reply_to_name' => $lead['name'],
        'subject' => 'New website lead from ' . $lead['name'],
        'html' => $notificationHtml,
        'text' => $notificationText,
    ]];

    if (!config_bool($mailConfig['send_auto_reply'] ?? true)) {
        return $messages;
    }

    $autoReplyHtml = '
        <p>Hi ' . htmlspecialchars($lead['name']) . ',</p>
        <p>Thanks for reaching out to A1 Technovation. We have received your enquiry about <strong>' . htmlspecialchars($lead['service_label']) . '</strong>.</p>
        <p>Our team will review your message and get back to you within 24 hours.</p>
        <p>Best regards,<br>A1 Technovation</p>
    ';
    $autoReplyText =
        "Hi {$lead['name']},\n\n" .
        "Thanks for reaching out to A1 Technovation. We received your enquiry about {$lead['service_label']}.\n" .
        "Our team will get back to you within 24 hours.\n\n" .
        "Best regards,\nA1 Technovation";
    $messages[] = [
        'from_email' => $fromEmail,
        'from_name' => $fromName,
        'to_email' => $lead['email'],
        'to_name' => $lead['name'],
        'subject' => (string) ($mailConfig['auto_reply_subject'] ?? 'Thanks for contacting A1 Technovation'),
        'html' => $autoReplyHtml,
        'text' => $autoReplyText,
    ];

    return $messages;
}

function send_contact_emails(array $config, array $lead): void
{
    $mailConfig = $config['mail'] ?? [];
    $smtp = $config['smtp'] ?? [];
    $messages = build_contact_email_messages($config, $lead);
    $transport = strtolower(trim((string) ($mailConfig['transport'] ?? 'smtp')));
    $allowNativeFallback = config_bool($mailConfig['fallback_to_native_mail'] ?? true);
    $lastException = null;

    if ($transport !== 'native') {
        try {
            if (($smtp['host'] ?? '') === '') {
                throw new RuntimeException('SMTP host is missing.');
            }

            $mailer = new SmtpMailer($smtp);

            foreach ($messages as $message) {
                $mailer->send($message);
            }

            return;
        } catch (Throwable $exception) {
            $lastException = $exception;
            error_log('[contact-submit][smtp] ' . $exception->getMessage());

            if (!$allowNativeFallback && $transport === 'smtp') {
                throw $exception;
            }
        }
    }

    if ($transport === 'smtp' && !$allowNativeFallback && $lastException instanceof Throwable) {
        throw $lastException;
    }

    foreach ($messages as $message) {
        send_native_mail($message);
    }
}

$wantsJson = wants_json_response();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    finish_request(null, $wantsJson, 'error', 'Invalid request method.', 405);
}

try {
    $config = load_contact_form_config();
} catch (Throwable $exception) {
    error_log('[contact-submit] ' . $exception->getMessage());
    finish_request(null, $wantsJson, 'error', 'The contact form is not configured yet. Please complete the server setup.', 500);
}

$ipDetails = contact_form_request_ip_details();
$lead = [
    'name' => trim_value($_POST['name'] ?? '', 150),
    'email' => trim_value($_POST['email'] ?? '', 190),
    'phone' => trim_value($_POST['phone'] ?? '', 50),
    'company' => trim_value($_POST['company'] ?? '', 150),
    'website' => trim_value($_POST['website'] ?? '', 255),
    'service' => trim_value($_POST['service'] ?? '', 80),
    'budget' => trim_value($_POST['budget'] ?? '', 80),
    'message' => trim_value($_POST['message'] ?? '', 5000),
    'contact_reference_code' => trim_value($_POST['contact_reference_code'] ?? '', 255),
    'captcha_token' => trim_value($_POST['captcha_token'] ?? '', 80),
    'captcha_answer' => trim_value($_POST['captcha_answer'] ?? '', 20),
    'page_url' => trim_value($_POST['page_url'] ?? '', 255),
    'ip_address' => trim_value($ipDetails['ip_address'] ?? '', 45),
    'remote_address' => trim_value($ipDetails['remote_address'] ?? '', 45),
    'forwarded_for' => trim_value($ipDetails['forwarded_for'] ?? '', 255),
    'country_code' => trim_value($ipDetails['country_code'] ?? '', 2),
    'country_name' => trim_value($ipDetails['country_name'] ?? '', 80),
    'user_agent' => trim_value($_SERVER['HTTP_USER_AGENT'] ?? '', 255),
];
$lead['service_label'] = contact_form_service_label($lead['service']);

if ($lead['contact_reference_code'] !== '') {
    finish_request($config, $wantsJson, 'success', 'Thanks. Your message has been received.');
}

if ($lead['name'] === '' || strlen($lead['name']) < 2) {
    finish_request($config, $wantsJson, 'error', 'Please enter your full name.', 422, [
        ['field' => 'name', 'message' => 'Please enter your full name.'],
    ]);
}

if ($lead['email'] === '' || !filter_var($lead['email'], FILTER_VALIDATE_EMAIL)) {
    finish_request($config, $wantsJson, 'error', 'Please enter a valid email address.', 422, [
        ['field' => 'email', 'message' => 'Enter a valid email address.'],
    ]);
}

$allowedServices = ['seo', 'web', 'ppc', 'social', 'full', 'other'];
if ($lead['service'] === '' || !in_array($lead['service'], $allowedServices, true)) {
    finish_request($config, $wantsJson, 'error', 'Please select a service.', 422, [
        ['field' => 'service', 'message' => 'Please select a service.'],
    ]);
}

if ($lead['message'] === '' || strlen($lead['message']) < 10) {
    finish_request($config, $wantsJson, 'error', 'Please tell us a bit more about your project goals.', 422, [
        ['field' => 'message', 'message' => 'Message must be at least 10 characters.'],
    ]);
}

$antiSpamValidation = contact_form_validate_antispam($config, $lead);

if (!$antiSpamValidation['valid']) {
    finish_request(
        $config,
        $wantsJson,
        'error',
        $antiSpamValidation['message'] ?: 'Please review your details before sending.',
        422,
        $antiSpamValidation['errors'] ?? []
    );
}

$captchaValidation = contact_form_validate_captcha_answer($config, $lead['captcha_token'], $lead['captcha_answer']);

if (!$captchaValidation['valid']) {
    finish_request($config, $wantsJson, 'error', $captchaValidation['message'], 422, [
        ['field' => 'captcha_answer', 'message' => $captchaValidation['message']],
    ]);
}

$stored = false;
$mailed = false;
try {
    $stored = store_contact_submission($config, $lead);
} catch (Throwable $exception) {
    error_log('[contact-submit][database] ' . $exception->getMessage());
}

try {
    send_contact_emails($config, $lead);
    $mailed = true;
} catch (Throwable $exception) {
    error_log('[contact-submit][mail] ' . $exception->getMessage());
}

if (!$stored && !$mailed) {
    finish_request(
        $config,
        $wantsJson,
        'error',
        'We could not send your message right now. Please email info.a1technovation@gmail.com instead.',
        500
    );
}

$successMessage = $mailed
    ? 'Message sent successfully. Our team will get back to you within 24 hours.'
    : 'Thanks. Your message was saved successfully and our team will review it shortly.';

finish_request($config, $wantsJson, 'success', $successMessage);
