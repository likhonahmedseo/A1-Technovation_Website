<?php
declare(strict_types=1);

function project_root(string $path = ''): string
{
    $root = dirname(__DIR__);

    if ($path === '') {
        return $root;
    }

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    return $root . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
}

function load_contact_form_config(): array
{
    $configPath = project_root('config/contact-form.php');

    if (!is_file($configPath)) {
        throw new RuntimeException('Missing config/contact-form.php. Copy config/contact-form.example.php and fill in your SMTP and database credentials.');
    }

    $config = require $configPath;

    if (!is_array($config)) {
        throw new RuntimeException('The contact form config file must return an array.');
    }

    require_once project_root('php/SmtpMailer.php');

    date_default_timezone_set($config['app']['timezone'] ?? 'Asia/Dhaka');
    ensure_contact_form_session($config);

    return $config;
}

function ensure_contact_form_session(array $config): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_name((string) ($config['app']['session_name'] ?? 'a1tech_contact'));
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 0');

    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

function contact_form_page_path(?array $config = null): string
{
    $path = (string) (($config['app']['contact_page'] ?? '/pages/contact'));
    $path = trim($path);

    if ($path === '') {
        return '/pages/contact';
    }

    if (preg_match('#^https?://#i', $path) === 1) {
        return $path;
    }

    if (str_starts_with($path, '/')) {
        return $path;
    }

    $baseUrl = trim((string) ($config['app']['base_url'] ?? ''));

    if ($baseUrl !== '') {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    return '/' . ltrim($path, '/');
}

function contact_form_service_label(string $value): string
{
    $labels = [
        'seo' => 'SEO Services',
        'web' => 'Web Design & Development',
        'ppc' => 'PPC Advertising',
        'social' => 'Social Media Marketing',
        'full' => 'Full Digital Marketing Package',
        'other' => 'Other / Not Sure Yet',
    ];

    return $labels[$value] ?? ucwords(str_replace(['-', '_'], ' ', $value));
}

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

function contact_form_captcha_config(?array $config = null): array
{
    $source = $config ?? load_contact_form_config();
    $captcha = is_array($source['captcha'] ?? null) ? $source['captcha'] : [];

    return [
        'enabled' => config_bool($captcha['enabled'] ?? true),
        'ttl_seconds' => max(120, (int) ($captcha['ttl_seconds'] ?? 900)),
        'min_number' => max(1, (int) ($captcha['min_number'] ?? 2)),
        'max_number' => max(3, (int) ($captcha['max_number'] ?? 12)),
    ];
}

function contact_form_antispam_config(?array $config = null): array
{
    $source = $config ?? load_contact_form_config();
    $antiSpam = is_array($source['anti_spam'] ?? null) ? $source['anti_spam'] : [];

    $list = static function (mixed $value): array {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $value
        )));
    };

    return [
        'enabled' => config_bool($antiSpam['enabled'] ?? true),
        'require_same_site' => config_bool($antiSpam['require_same_site'] ?? true),
        'minimum_submit_seconds' => max(0, (int) ($antiSpam['minimum_submit_seconds'] ?? 4)),
        'max_links_in_message' => max(0, (int) ($antiSpam['max_links_in_message'] ?? 2)),
        'check_email_dns' => config_bool($antiSpam['check_email_dns'] ?? true),
        'check_website_dns' => config_bool($antiSpam['check_website_dns'] ?? true),
        'rate_limit_window_seconds' => max(60, (int) ($antiSpam['rate_limit_window_seconds'] ?? 900)),
        'max_submissions_per_ip' => max(1, (int) ($antiSpam['max_submissions_per_ip'] ?? 3)),
        'max_submissions_per_email' => max(1, (int) ($antiSpam['max_submissions_per_email'] ?? 2)),
        'blocked_email_domains' => $list($antiSpam['blocked_email_domains'] ?? []),
        'blocked_website_domains' => $list($antiSpam['blocked_website_domains'] ?? []),
        'blocked_message_terms' => $list($antiSpam['blocked_message_terms'] ?? []),
    ];
}

function contact_form_host_from_url(string $url): string
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    if ($host === '') {
        return '';
    }

    if (str_starts_with($host, 'www.')) {
        $host = substr($host, 4);
    }

    return rtrim($host, '.');
}

function contact_form_is_same_site_request(array $config): bool
{
    $antiSpam = contact_form_antispam_config($config);

    if (!$antiSpam['enabled'] || !$antiSpam['require_same_site']) {
        return true;
    }

    $allowedHost = contact_form_host_from_url((string) ($config['app']['base_url'] ?? ''));
    $serverHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $serverHost = preg_replace('/:\d+$/', '', $serverHost) ?? $serverHost;

    if ($allowedHost === '' && $serverHost !== '') {
        $allowedHost = $serverHost;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        $value = (string) ($_SERVER[$header] ?? '');

        if ($value === '') {
            continue;
        }

        $host = contact_form_host_from_url($value);

        if ($host !== '' && $allowedHost !== '' && hash_equals($allowedHost, $host)) {
            return true;
        }
    }

    return false;
}

function contact_form_domain_is_blocked(string $domain, array $blockedDomains): bool
{
    $domain = strtolower(trim($domain));

    foreach ($blockedDomains as $blockedDomain) {
        if ($domain === $blockedDomain || str_ends_with($domain, '.' . $blockedDomain)) {
            return true;
        }
    }

    return false;
}

function contact_form_domain_has_dns(string $domain, bool $mailDomain = false): bool
{
    if ($domain === '' || !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        return false;
    }

    if ($mailDomain && function_exists('checkdnsrr') && checkdnsrr($domain, 'MX')) {
        return true;
    }

    if (function_exists('checkdnsrr')) {
        return checkdnsrr($domain, 'A') || checkdnsrr($domain, 'AAAA');
    }

    return gethostbyname($domain) !== $domain;
}

function contact_form_is_public_host(string $host): bool
{
    if ($host === '' || in_array($host, ['localhost', 'local'], true)) {
        return false;
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $host) === 1;
}

function contact_form_rate_limit_path(): string
{
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'a1_contact_rate_limits.json';
}

function contact_form_country_name(string $countryCode): string
{
    $countryCode = strtoupper(trim($countryCode));
    $countries = [
        'AE' => 'United Arab Emirates',
        'AU' => 'Australia',
        'BD' => 'Bangladesh',
        'CA' => 'Canada',
        'DE' => 'Germany',
        'ES' => 'Spain',
        'FR' => 'France',
        'GB' => 'United Kingdom',
        'IN' => 'India',
        'IT' => 'Italy',
        'JP' => 'Japan',
        'MY' => 'Malaysia',
        'NL' => 'Netherlands',
        'PK' => 'Pakistan',
        'QA' => 'Qatar',
        'SA' => 'Saudi Arabia',
        'SG' => 'Singapore',
        'TH' => 'Thailand',
        'US' => 'United States',
    ];

    return $countries[$countryCode] ?? $countryCode;
}

function contact_form_first_public_ip(string $value): string
{
    foreach (explode(',', $value) as $candidate) {
        $candidate = trim($candidate);

        if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $candidate;
        }
    }

    return '';
}

function contact_form_request_ip_details(): array
{
    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
    $realIp = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
    $connectingIp = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    $clientIp = contact_form_first_public_ip($connectingIp)
        ?: contact_form_first_public_ip($realIp)
        ?: contact_form_first_public_ip($forwardedFor)
        ?: $remoteAddress;

    $countryCode = '';
    foreach ([
        'HTTP_CF_IPCOUNTRY',
        'HTTP_CLOUDFRONT_VIEWER_COUNTRY',
        'HTTP_X_APPENGINE_COUNTRY',
        'HTTP_X_VERCEL_IP_COUNTRY',
    ] as $header) {
        $value = strtoupper(trim((string) ($_SERVER[$header] ?? '')));

        if (preg_match('/^[A-Z]{2}$/', $value)) {
            $countryCode = $value;
            break;
        }
    }

    return [
        'ip_address' => trim($clientIp),
        'remote_address' => $remoteAddress,
        'forwarded_for' => trim_value_for_contact_form($forwardedFor, 255),
        'country_code' => $countryCode,
        'country_name' => $countryCode !== '' ? contact_form_country_name($countryCode) : '',
    ];
}

function trim_value_for_contact_form(mixed $value, int $maxLength = 0): string
{
    $text = trim((string) $value);

    if ($maxLength > 0 && strlen($text) > $maxLength) {
        return substr($text, 0, $maxLength);
    }

    return $text;
}

function contact_form_check_rate_limit(array $config, string $ipAddress, string $email): array
{
    $antiSpam = contact_form_antispam_config($config);

    if (!$antiSpam['enabled']) {
        return ['valid' => true, 'message' => ''];
    }

    $now = time();
    $window = $antiSpam['rate_limit_window_seconds'];
    $emailKey = strtolower(trim($email));
    $ipKey = trim($ipAddress) !== '' ? trim($ipAddress) : 'unknown';
    $path = contact_form_rate_limit_path();
    $data = ['ip' => [], 'email' => []];

    $handle = @fopen($path, 'c+');

    if ($handle === false) {
        return ['valid' => true, 'message' => ''];
    }

    try {
        if (flock($handle, LOCK_EX)) {
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            if (is_array($decoded)) {
                $data = array_replace_recursive($data, $decoded);
            }

            foreach (['ip', 'email'] as $bucket) {
                foreach (($data[$bucket] ?? []) as $key => $timestamps) {
                    if (!is_array($timestamps)) {
                        unset($data[$bucket][$key]);
                        continue;
                    }

                    $data[$bucket][$key] = array_values(array_filter(
                        array_map('intval', $timestamps),
                        static fn (int $timestamp): bool => $timestamp > $now - $window
                    ));

                    if ($data[$bucket][$key] === []) {
                        unset($data[$bucket][$key]);
                    }
                }
            }

            $ipCount = count($data['ip'][$ipKey] ?? []);
            $emailCount = $emailKey !== '' ? count($data['email'][$emailKey] ?? []) : 0;

            if ($ipCount >= $antiSpam['max_submissions_per_ip'] || $emailCount >= $antiSpam['max_submissions_per_email']) {
                flock($handle, LOCK_UN);
                return [
                    'valid' => false,
                    'message' => 'Too many messages were sent recently. Please wait and try again later.',
                ];
            }

            $data['ip'][$ipKey][] = $now;

            if ($emailKey !== '') {
                $data['email'][$emailKey][] = $now;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_UNESCAPED_SLASHES));
            fflush($handle);
            flock($handle, LOCK_UN);
        }
    } finally {
        fclose($handle);
    }

    return ['valid' => true, 'message' => ''];
}

function contact_form_captcha_session_key(): string
{
    return 'contact_form_captcha_challenges';
}

function contact_form_cleanup_captcha_challenges(?array $config = null): void
{
    $captcha = contact_form_captcha_config($config);
    $bucketKey = contact_form_captcha_session_key();
    $challenges = $_SESSION[$bucketKey] ?? [];

    if (!is_array($challenges)) {
        $_SESSION[$bucketKey] = [];
        return;
    }

    $now = time();

    foreach ($challenges as $token => $challenge) {
        $expiresAt = (int) ($challenge['expires_at'] ?? 0);

        if ($expiresAt <= $now) {
            unset($challenges[$token]);
        }
    }

    if (count($challenges) > 12) {
        $challenges = array_slice($challenges, -12, null, true);
    }

    $_SESSION[$bucketKey] = $challenges;
}

function contact_form_create_captcha_challenge(?array $config = null): array
{
    $captcha = contact_form_captcha_config($config);

    if (!$captcha['enabled']) {
        return [
            'enabled' => false,
        ];
    }

    contact_form_cleanup_captcha_challenges($config);

    $min = min($captcha['min_number'], $captcha['max_number']);
    $max = max($captcha['min_number'], $captcha['max_number']);
    $left = random_int($min, $max);
    $right = random_int($min, $max);
    $operator = random_int(0, 1) === 0 ? '+' : '-';

    if ($operator === '-' && $right > $left) {
        [$left, $right] = [$right, $left];
    }

    $answer = $operator === '+' ? $left + $right : $left - $right;
    $token = bin2hex(random_bytes(16));
    $expiresAt = time() + $captcha['ttl_seconds'];
    $bucketKey = contact_form_captcha_session_key();

    if (!isset($_SESSION[$bucketKey]) || !is_array($_SESSION[$bucketKey])) {
        $_SESSION[$bucketKey] = [];
    }

    $_SESSION[$bucketKey][$token] = [
        'answer' => (string) $answer,
        'issued_at' => time(),
        'expires_at' => $expiresAt,
    ];

    return [
        'enabled' => true,
        'token' => $token,
        'prompt' => sprintf('What is %d %s %d?', $left, $operator, $right),
        'expires_at' => $expiresAt,
    ];
}

function contact_form_validate_captcha_answer(?array $config, string $token, string $answer): array
{
    $captcha = contact_form_captcha_config($config);

    if (!$captcha['enabled']) {
        return [
            'valid' => true,
            'message' => '',
        ];
    }

    contact_form_cleanup_captcha_challenges($config);
    $bucketKey = contact_form_captcha_session_key();
    $challenges = $_SESSION[$bucketKey] ?? [];

    if ($token === '' || !isset($challenges[$token]) || !is_array($challenges[$token])) {
        return [
            'valid' => false,
            'message' => 'The security check expired. Please refresh it and try again.',
        ];
    }

    $expected = trim((string) ($challenges[$token]['answer'] ?? ''));
    unset($_SESSION[$bucketKey][$token]);

    if ($answer === '' || !preg_match('/^-?\d+$/', $answer)) {
        return [
            'valid' => false,
            'message' => 'Please solve the security check before sending your message.',
        ];
    }

    if (!hash_equals($expected, $answer)) {
        return [
            'valid' => false,
            'message' => 'The security check answer is not correct. Please try again.',
        ];
    }

    return [
        'valid' => true,
        'message' => '',
    ];
}

function contact_form_validate_antispam(array $config, array $lead): array
{
    $antiSpam = contact_form_antispam_config($config);

    if (!$antiSpam['enabled']) {
        return ['valid' => true, 'message' => '', 'errors' => []];
    }

    $errors = [];
    $message = strtolower($lead['message'] ?? '');

    if (!contact_form_is_same_site_request($config)) {
        return [
            'valid' => false,
            'message' => 'For security, please send your message from the contact page.',
            'errors' => [],
        ];
    }

    $bucketKey = contact_form_captcha_session_key();
    $challenge = $_SESSION[$bucketKey][$lead['captcha_token'] ?? ''] ?? null;
    $issuedAt = is_array($challenge) ? (int) ($challenge['issued_at'] ?? 0) : 0;

    if ($antiSpam['minimum_submit_seconds'] > 0 && $issuedAt > 0 && time() - $issuedAt < $antiSpam['minimum_submit_seconds']) {
        return [
            'valid' => false,
            'message' => 'Please take a moment to complete the form before sending.',
            'errors' => [],
        ];
    }

    $emailParts = explode('@', strtolower((string) ($lead['email'] ?? '')), 2);
    $emailDomain = $emailParts[1] ?? '';

    if ($emailDomain === '' || contact_form_domain_is_blocked($emailDomain, $antiSpam['blocked_email_domains'])) {
        $errors[] = ['field' => 'email', 'message' => 'Please use a real business or personal email address.'];
    } elseif ($antiSpam['check_email_dns'] && !contact_form_domain_has_dns($emailDomain, true)) {
        $errors[] = ['field' => 'email', 'message' => 'This email domain does not appear to receive email.'];
    }

    $website = trim((string) ($lead['website'] ?? ''));

    if ($website !== '') {
        $websiteHost = contact_form_host_from_url($website);

        if (!filter_var($website, FILTER_VALIDATE_URL) || !in_array(parse_url($website, PHP_URL_SCHEME), ['http', 'https'], true)) {
            $errors[] = ['field' => 'website', 'message' => 'Enter a complete website URL starting with https://'];
        } elseif (!contact_form_is_public_host($websiteHost)) {
            $errors[] = ['field' => 'website', 'message' => 'Please enter a public website domain.'];
        } elseif (contact_form_domain_is_blocked($websiteHost, $antiSpam['blocked_website_domains'])) {
            $errors[] = ['field' => 'website', 'message' => 'Please enter your real business website.'];
        } elseif ($antiSpam['check_website_dns'] && !contact_form_domain_has_dns($websiteHost, false)) {
            $errors[] = ['field' => 'website', 'message' => 'This website domain could not be verified.'];
        }
    }

    $linkCount = preg_match_all('/https?:\/\/|www\./i', (string) ($lead['message'] ?? ''), $matches);

    if ($linkCount > $antiSpam['max_links_in_message']) {
        $errors[] = ['field' => 'message', 'message' => 'Please remove extra links from your message.'];
    }

    foreach ($antiSpam['blocked_message_terms'] as $term) {
        if ($term !== '' && str_contains($message, $term)) {
            $errors[] = ['field' => 'message', 'message' => 'Please send a genuine project enquiry.'];
            break;
        }
    }

    if ($errors !== []) {
        return [
            'valid' => false,
            'message' => 'Please review the highlighted fields before sending.',
            'errors' => $errors,
        ];
    }

    $rateLimit = contact_form_check_rate_limit(
        $config,
        (string) ($lead['ip_address'] ?? ''),
        (string) ($lead['email'] ?? '')
    );

    if (!$rateLimit['valid']) {
        return [
            'valid' => false,
            'message' => $rateLimit['message'],
            'errors' => [],
        ];
    }

    return ['valid' => true, 'message' => '', 'errors' => []];
}
