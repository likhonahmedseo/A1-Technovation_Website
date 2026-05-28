<?php
declare(strict_types=1);

function cms_root(string $path = ''): string
{
    $root = dirname(__DIR__, 2);

    if ($path === '') {
        return $root;
    }

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    return $root . DIRECTORY_SEPARATOR . ltrim($normalized, DIRECTORY_SEPARATOR);
}

function cms_load_raw_config(): array
{
    static $config;

    if (is_array($config)) {
        return $config;
    }

    $configPath = cms_root('config/cms.php');

    if (!is_file($configPath)) {
        throw new RuntimeException('Missing CMS config file. Create config/cms.php from config/cms.example.php.');
    }

    $loaded = require $configPath;

    if (!is_array($loaded)) {
        throw new RuntimeException('The CMS config file must return an array.');
    }

    $config = $loaded;

    return $config;
}

function cms_config(?string $key = null, mixed $default = null): mixed
{
    $config = cms_load_raw_config();

    if ($key === null || $key === '') {
        return $config;
    }

    $segments = explode('.', $key);
    $value = $config;

    foreach ($segments as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

date_default_timezone_set((string) cms_config('app.timezone', 'UTC'));

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    session_name((string) cms_config('app.session_name', 'a1tech_cms'));
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 0');
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Process scheduled posts/pages on every admin request (lightweight check)
if (!defined('CMS_SKIP_SCHEDULER') && isset($_SERVER['SCRIPT_FILENAME'])) {
    $scriptPath = str_replace(['/', '\\'], '/', (string) $_SERVER['SCRIPT_FILENAME']);
    if (str_contains($scriptPath, '/admin/')) {
        // Run at most once per minute — use a flat file lock
        $lockFile = sys_get_temp_dir() . '/a1cms_scheduler_' . md5(cms_root()) . '.lock';
        $now = time();
        if (!file_exists($lockFile) || ($now - (int) file_get_contents($lockFile)) >= 60) {
            file_put_contents($lockFile, (string) $now);
            try { cms_process_scheduled_posts(); } catch (Throwable) {}
        }
    }
}
