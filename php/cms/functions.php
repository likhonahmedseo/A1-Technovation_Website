<?php
declare(strict_types=1);

function cms_is_absolute_url(?string $value): bool
{
    if (!is_string($value) || $value === '') {
        return false;
    }

    return (bool) preg_match('#^https?://#i', $value);
}

function cms_url(string $path = ''): string
{
    $baseUrl = rtrim((string) cms_config('app.base_url', ''), '/');
    $normalized = ltrim($path, '/');

    if ($baseUrl !== '') {
        return $normalized === '' ? $baseUrl : $baseUrl . '/' . $normalized;
    }

    return $normalized === '' ? '/' : '/' . $normalized;
}

function cms_admin_url(string $path = ''): string
{
    $normalized = ltrim($path, '/');

    return cms_url($normalized === '' ? 'admin' : 'admin/' . $normalized);
}

function cms_blog_index_url(): string
{
    return cms_url('blog/');
}

function cms_blog_post_url(string $slug): string
{
    return cms_url('blog/' . rawurlencode($slug));
}

function cms_blog_category_url(string $category): string
{
    return cms_url('blog/category/' . rawurlencode($category));
}

function cms_blog_tag_url(string $tag): string
{
    return cms_url('blog/tag/' . rawurlencode($tag));
}

function cms_default_author_image_url(): string
{
    return cms_url('assets/images/CEO%20of%20A1%20Technovation.webp');
}

function cms_default_blog_interlinks_html(): string
{
    return '<nav class="cms-default-interlinks" aria-label="Related A1 Technovation resources">'
        . '<strong>Explore related resources</strong>'
        . '<a href="/pages/services-seo">SEO Services</a>'
        . '<a href="/aeo-optimization-services/">AEO Optimization</a>'
        . '<a href="/geo-optimization-services/">GEO Optimization</a>'
        . '<a href="/blog/">More SEO Guides</a>'
        . '</nav>';
}

function cms_page_url(string $slug, string $pageType = 'page'): string
{
    $prefix = match ($pageType) {
        'service' => 'services',
        'landing' => 'landing',
        default => 'page',
    };

    return cms_url($prefix . '/' . rawurlencode($slug));
}

function cms_site_favicon_url(): string
{
    try {
        $extended = cms_site_settings_extended();
        $favicon = trim((string) ($extended['favicon_url'] ?? ''));
        if ($favicon !== '') {
            return cms_media_url($favicon);
        }
    } catch (Throwable) {
        // Fall back to the bundled favicon when CMS settings are unavailable.
    }

    return cms_url('assets/images/favicon.png');
}

function cms_blog_layout_modes(): array
{
    return [
        'standard' => 'Standard blog layout',
        'custom_import' => 'Imported custom article layout',
    ];
}

function cms_normalize_blog_layout_mode(string $value): string
{
    $value = trim($value);

    return array_key_exists($value, cms_blog_layout_modes()) ? $value : 'standard';
}

function cms_redirect(string $path): never
{
    $location = cms_is_absolute_url($path) ? $path : cms_url($path);
    header('Location: ' . $location);
    exit;
}

function cms_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function cms_post(string $key, mixed $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}

function cms_query(string $key, mixed $default = ''): mixed
{
    return $_GET[$key] ?? $default;
}

function cms_current_path(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
}

function cms_blog_slug_from_request(): string
{
    $slug = trim((string) cms_query('slug'));

    if ($slug !== '') {
        return $slug;
    }

    $path = trim(cms_current_path(), '/');

    if (preg_match('#^blog/([^/]+)$#', $path, $matches) === 1) {
        return urldecode($matches[1]);
    }

    return '';
}

function cms_page_request_context(): array
{
    $path = trim(cms_current_path(), '/');
    if (preg_match('#^(page|services|landing)/([^/]+)$#', $path, $matches) === 1) {
        return [
            'prefix' => $matches[1],
            'slug' => urldecode($matches[2]),
        ];
    }

    return [
        'prefix' => '',
        'slug' => trim((string) cms_query('slug')),
    ];
}

function cms_query_int(string $key, int $default = 0): int
{
    return max(0, (int) cms_query($key, $default));
}

function cms_is_post(): bool
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function cms_flash_set(string $type, string $message): void
{
    $_SESSION['cms_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function cms_flash_get(): ?array
{
    if (!isset($_SESSION['cms_flash']) || !is_array($_SESSION['cms_flash'])) {
        return null;
    }

    $flash = $_SESSION['cms_flash'];
    unset($_SESSION['cms_flash']);

    return $flash;
}

function cms_csrf_token(): string
{
    if (!isset($_SESSION['cms_csrf_token']) || !is_string($_SESSION['cms_csrf_token'])) {
        $_SESSION['cms_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['cms_csrf_token'];
}

function cms_csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . cms_e(cms_csrf_token()) . '">';
}

function cms_require_csrf(): void
{
    $submitted = (string) cms_post('_token', '');
    $stored = $_SESSION['cms_csrf_token'] ?? '';

    if (!is_string($stored) || $stored === '' || !hash_equals($stored, $submitted)) {
        throw new RuntimeException('Your form session expired. Refresh the page and try again.');
    }
}

function cms_make_slug(string $value): string
{
    $normalized = strtolower(trim($value));
    $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';
    $normalized = trim($normalized, '-');

    return $normalized !== '' ? $normalized : 'post';
}

function cms_post_slug_exists(string $slug, ?int $ignoreId = null): bool
{
    if (!cms_table_exists('blog_posts')) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM blog_posts WHERE slug = :slug';
    $params = ['slug' => $slug];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }

    $statement = cms_db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function cms_unique_post_slug(string $title, ?int $ignoreId = null, ?string $provided = null): string
{
    $base = cms_make_slug($provided !== null && $provided !== '' ? $provided : $title);
    $slug = $base;
    $suffix = 2;

    while (cms_post_slug_exists($slug, $ignoreId)) {
        $slug = $base . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function cms_clean_html(string $html): string
{
    $cleaned = preg_replace('#<script\b[^>]*>(.*?)</script>#is', '', $html) ?? $html;
    $cleaned = preg_replace('#\son[a-z]+\s*=\s*("|\').*?\1#is', '', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('#\s(href|src)\s*=\s*("|\')\s*javascript:.*?\2#is', '', $cleaned) ?? $cleaned;

    return trim($cleaned);
}

function cms_ensure_content_tables_utf8mb4(): void
{
    static $checked = false;

    if ($checked) {
        return;
    }

    $checked = true;
    $pdo = cms_try_db();
    if (!$pdo instanceof PDO) {
        return;
    }

    $database = cms_config('database', []);
    $databaseName = (string) ($database['name'] ?? '');
    if ($databaseName === '') {
        return;
    }

    foreach (['blog_posts', 'cms_pages'] as $table) {
        if (!cms_table_exists($table)) {
            continue;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.columns
             WHERE table_schema = :schema
               AND table_name = :table
               AND character_set_name IS NOT NULL
               AND character_set_name <> :charset'
        );
        $statement->execute([
            'schema' => $databaseName,
            'table' => $table,
            'charset' => 'utf8mb4',
        ]);

        if ((int) $statement->fetchColumn() > 0) {
            $pdo->exec("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }
}

function cms_excerpt_from_html(string $html, int $length = 180): string
{
    $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    $text = preg_replace('/\s+/', ' ', $text) ?? $text;

    if (mb_strlen($text) <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length - 1)) . '…';
}

function cms_normalize_tags(string $tags): string
{
    $parts = array_filter(array_map(
        static fn (string $item): string => trim($item),
        explode(',', $tags)
    ));

    return implode(', ', array_values(array_unique($parts)));
}

function cms_json_decode_array(?string $json, array $fallback = []): array
{
    if (!is_string($json) || trim($json) === '') {
        return $fallback;
    }

    $decoded = json_decode($json, true);

    return is_array($decoded) ? $decoded : $fallback;
}

function cms_json_encode(mixed $value): string
{
    return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}

function cms_json_script_encode(mixed $value): string
{
    return (string) json_encode(
        $value,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
}

function cms_format_date(?string $value, string $format = 'M j, Y'): string
{
    if (!$value) {
        return '';
    }

    try {
        return (new DateTimeImmutable($value))->format($format);
    } catch (Throwable) {
        return '';
    }
}

function cms_normalize_datetime_input(string $value): ?string
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return null;
    }
}

function cms_media_disk_dir(): string
{
    return cms_root((string) cms_config('app.media_path', 'assets/uploads/media'));
}

function cms_media_web_path(): string
{
    return trim((string) cms_config('app.media_path', 'assets/uploads/media'), '/');
}

function cms_ensure_media_dir(): string
{
    $directory = cms_media_disk_dir();

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create the media upload directory.');
    }

    return $directory;
}

function cms_media_url(?string $path): string
{
    if ($path === null || $path === '') {
        return '';
    }

    if (cms_is_absolute_url($path)) {
        return $path;
    }

    return cms_url(str_replace('\\', '/', ltrim($path, '/')));
}

function cms_allowed_media_types(): array
{
    return [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/svg+xml',
        'video/mp4',
        'video/webm',
        'application/pdf',
    ];
}

function cms_mime_from_extension(string $filename): string
{
    return match (strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'pdf' => 'application/pdf',
        default => '',
    };
}

function cms_mime_from_file(string $path, string $fallbackName = ''): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }
    }

    $bytes = @file_get_contents($path, false, null, 0, 512);
    if (is_string($bytes) && $bytes !== '') {
        $mime = cms_mime_from_buffer_signature($bytes);
        if ($mime !== '') {
            return $mime;
        }
    }

    return cms_mime_from_extension($fallbackName !== '' ? $fallbackName : $path);
}

function cms_upload_media(array $file, int $adminId, string $title = '', string $altText = ''): array
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        $msg = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum upload size limit.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'Choose a file before uploading.',
            default => 'Upload error (code ' . $errorCode . ').',
        };
        throw new RuntimeException($msg);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('The uploaded file could not be verified.');
    }

    $originalName = (string) ($file['name'] ?? 'upload');
    $mimeType = cms_mime_from_file($tmpName, $originalName);

    if (!in_array($mimeType, cms_allowed_media_types(), true)) {
        throw new RuntimeException('Unsupported media type: ' . ($mimeType ?: pathinfo($originalName, PATHINFO_EXTENSION)) . '. Upload JPG, PNG, WebP, GIF, SVG, MP4, WebM, or PDF files.');
    }

    $directory = cms_ensure_media_dir();
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeBase = cms_make_slug(pathinfo($originalName, PATHINFO_FILENAME));
    $storedName = $safeBase . '-' . bin2hex(random_bytes(6)) . ($extension !== '' ? '.' . $extension : '');
    $targetPath = $directory . DIRECTORY_SEPARATOR . $storedName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        throw new RuntimeException('The uploaded file could not be moved into the media library.');
    }

    $relativePath = cms_media_web_path() . '/' . $storedName;
    $statement = cms_db()->prepare(
        'INSERT INTO media_files (original_name, stored_name, title, alt_text, file_path, mime_type, file_size, uploaded_by)
         VALUES (:original_name, :stored_name, :title, :alt_text, :file_path, :mime_type, :file_size, :uploaded_by)'
    );
    $statement->execute([
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'title' => trim($title) !== '' ? trim($title) : pathinfo($originalName, PATHINFO_FILENAME),
        'alt_text' => trim($altText),
        'file_path' => $relativePath,
        'mime_type' => $mimeType,
        'file_size' => (int) ($file['size'] ?? 0),
        'uploaded_by' => $adminId,
    ]);

    return cms_get_media((int) cms_db()->lastInsertId()) ?? [];
}

/**
 * Normalizes $_FILES array structures (both single file and multiple files)
 * into a uniform list of file arrays.
 */
function cms_normalize_uploaded_files(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    $normalized = [];
    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $normalized[] = [
                'name'     => (string) ($files['name'][$i] ?? ''),
                'type'     => (string) ($files['type'][$i] ?? ''),
                'tmp_name' => (string) ($files['tmp_name'][$i] ?? ''),
                'error'    => $err,
                'size'     => (int) ($files['size'][$i] ?? 0),
            ];
        }
    } elseif (($files['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $normalized[] = [
            'name'     => (string) ($files['name'] ?? ''),
            'type'     => (string) ($files['type'] ?? ''),
            'tmp_name' => (string) ($files['tmp_name'] ?? ''),
            'error'    => (int) ($files['error'] ?? UPLOAD_ERR_OK),
            'size'     => (int) ($files['size'] ?? 0),
        ];
    }

    return $normalized;
}

/**
 * Uploads a single media file and applies automatic WebP optimization & dimension metadata.
 */
function cms_process_uploaded_media_item(array $file, int $adminId, string $title = '', string $altText = ''): array
{
    $uploaded = cms_upload_media($file, $adminId, $title, $altText);

    if (!empty($uploaded['file_path'])) {
        $absPath = cms_root(ltrim((string) $uploaded['file_path'], '/\\'));
        $webpAbs = cms_convert_upload_to_webp($absPath, (string) ($uploaded['mime_type'] ?? ''));
        $dims    = cms_image_dimensions($absPath);

        if ($webpAbs !== null || $dims['width'] !== null) {
            $rootPath = cms_root();
            $webpRel = null;
            if ($webpAbs !== null) {
                $cleanRel = str_replace([$rootPath, '\\'], ['', '/'], $webpAbs);
                $webpRel  = ltrim($cleanRel, '/');
            }

            cms_db()->prepare(
                'UPDATE media_files SET webp_path=:wp, original_size=:os, width=:w, height=:h WHERE id=:id'
            )->execute([
                'wp' => $webpRel,
                'os' => (int) ($uploaded['file_size'] ?? 0),
                'w'  => $dims['width'],
                'h'  => $dims['height'],
                'id' => (int) ($uploaded['id'] ?? 0),
            ]);

            $uploaded['webp_path']     = $webpRel;
            $uploaded['original_size'] = (int) ($uploaded['file_size'] ?? 0);
            $uploaded['width']         = $dims['width'];
            $uploaded['height']        = $dims['height'];
        }
    }

    return $uploaded;
}

function cms_stock_image_allowed_hosts(): array
{
    return [
        'pexels.com',
        'www.pexels.com',
        'images.pexels.com',
        'pixabay.com',
        'www.pixabay.com',
        'cdn.pixabay.com',
        'unsplash.com',
        'www.unsplash.com',
        'images.unsplash.com',
    ];
}

function cms_url_host(string $url): string
{
    $host = parse_url($url, PHP_URL_HOST);

    return is_string($host) ? strtolower($host) : '';
}

function cms_is_allowed_stock_image_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }

    return in_array(cms_url_host($url), cms_stock_image_allowed_hosts(), true);
}

function cms_fetch_remote_asset(string $url, int $maxBytes = 12582912): array
{
    if (!cms_is_allowed_stock_image_url($url)) {
        throw new RuntimeException('Use a Pexels, Pixabay, or Unsplash image/page URL.');
    }

    $headers = [
        'User-Agent: A1-Technovation-CMS/1.0',
        'Accept: image/webp,image/png,image/jpeg,image/gif,text/html;q=0.8,*/*;q=0.5',
    ];

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'A1-Technovation-CMS/1.0',
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
        $finalUrl = (string) curl_getinfo($handle, CURLINFO_EFFECTIVE_URL);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '' || $status >= 400) {
            throw new RuntimeException($error !== '' ? $error : 'The remote image could not be downloaded.');
        }

        if (strlen($body) > $maxBytes) {
            throw new RuntimeException('Remote image is too large. Choose an image under 12 MB.');
        }

        if ($finalUrl !== '' && !cms_is_allowed_stock_image_url($finalUrl)) {
            throw new RuntimeException('The remote image redirected outside Pexels, Pixabay, or Unsplash.');
        }

        return ['body' => $body, 'content_type' => $contentType, 'url' => $finalUrl !== '' ? $finalUrl : $url];
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 30,
            'follow_location' => 0,
            'header' => implode("\r\n", $headers),
        ],
    ]);
    $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);

    if (!is_string($body) || $body === '') {
        throw new RuntimeException('The remote image could not be downloaded.');
    }

    if (strlen($body) > $maxBytes) {
        throw new RuntimeException('Remote image is too large. Choose an image under 12 MB.');
    }

    $contentType = '';
    foreach (($http_response_header ?? []) as $header) {
        if (stripos($header, 'Content-Type:') === 0) {
            $contentType = trim(substr($header, 13));
            break;
        }
    }

    return ['body' => $body, 'content_type' => $contentType, 'url' => $url];
}

function cms_absolute_remote_url(string $baseUrl, string $candidate): string
{
    if (cms_is_absolute_url($candidate)) {
        return $candidate;
    }

    $parts = parse_url($baseUrl);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
        return $candidate;
    }

    if (str_starts_with($candidate, '//')) {
        return $parts['scheme'] . ':' . $candidate;
    }

    if (str_starts_with($candidate, '/')) {
        return $parts['scheme'] . '://' . $parts['host'] . $candidate;
    }

    $path = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', (string) $parts['path']) : '/';

    return $parts['scheme'] . '://' . $parts['host'] . $path . $candidate;
}

function cms_extract_stock_image_from_html(string $html, string $pageUrl): string
{
    $patterns = [
        '#<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']#i',
        '#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']#i',
        '#<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']#i',
        '#<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']twitter:image["\']#i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches) === 1) {
            $imageUrl = html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
            $imageUrl = cms_absolute_remote_url($pageUrl, $imageUrl);
            if (cms_is_allowed_stock_image_url($imageUrl)) {
                return $imageUrl;
            }
        }
    }

    throw new RuntimeException('Could not find an importable image on that page. Try the direct image URL instead.');
}

function cms_mime_from_buffer_signature(string $buffer): string
{
    if (str_starts_with($buffer, "\xFF\xD8\xFF")) {
        return 'image/jpeg';
    }

    if (str_starts_with($buffer, "\x89PNG\r\n\x1A\n")) {
        return 'image/png';
    }

    if (str_starts_with($buffer, 'GIF87a') || str_starts_with($buffer, 'GIF89a')) {
        return 'image/gif';
    }

    if (substr($buffer, 0, 4) === 'RIFF' && substr($buffer, 8, 4) === 'WEBP') {
        return 'image/webp';
    }

    if (str_starts_with(ltrim($buffer), '<svg')) {
        return 'image/svg+xml';
    }

    if (str_starts_with($buffer, '%PDF-')) {
        return 'application/pdf';
    }

    if (substr($buffer, 4, 4) === 'ftyp') {
        return 'video/mp4';
    }

    if (str_starts_with($buffer, "\x1A\x45\xDF\xA3")) {
        return 'video/webm';
    }

    return '';
}

function cms_image_mime_from_buffer(string $buffer): string
{
    if (function_exists('finfo_buffer')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($buffer);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    return cms_mime_from_buffer_signature($buffer);
}

function cms_extension_for_image_mime(string $mimeType): string
{
    return match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        default => 'img',
    };
}

function cms_compress_image_buffer(string $buffer, string $mimeType, int $maxWidth, int $quality): ?array
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp') || $mimeType === 'image/gif') {
        return null;
    }

    $source = @imagecreatefromstring($buffer);
    if (!$source) {
        return null;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($source);
        return null;
    }

    $targetWidth = min($width, max(320, min(4000, $maxWidth)));
    $targetHeight = (int) round($height * ($targetWidth / $width));
    $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
    if (!$canvas) {
        imagedestroy($source);
        return null;
    }

    imagepalettetotruecolor($source);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);

    if (!imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
        imagedestroy($source);
        imagedestroy($canvas);
        return null;
    }

    ob_start();
    $ok = imagewebp($canvas, null, max(35, min(92, $quality)));
    $output = (string) ob_get_clean();
    imagedestroy($source);
    imagedestroy($canvas);

    if (!$ok || $output === '') {
        return null;
    }

    return ['buffer' => $output, 'mime_type' => 'image/webp', 'extension' => 'webp'];
}

function cms_import_stock_image(string $url, int $adminId, string $title = '', string $altText = '', bool $compress = true, int $maxWidth = 1600, int $quality = 78): array
{
    $url = trim($url);
    if (!cms_is_allowed_stock_image_url($url)) {
        throw new RuntimeException('Use a Pexels, Pixabay, or Unsplash image/page URL.');
    }

    $download = cms_fetch_remote_asset($url);
    $body = (string) $download['body'];
    $contentType = strtolower((string) $download['content_type']);
    $sourceUrl = (string) $download['url'];

    if (str_contains($contentType, 'text/html') || str_starts_with(ltrim($body), '<')) {
        $sourceUrl = cms_extract_stock_image_from_html($body, $sourceUrl);
        $download = cms_fetch_remote_asset($sourceUrl);
        $body = (string) $download['body'];
        $sourceUrl = (string) $download['url'];
    }

    $mimeType = cms_image_mime_from_buffer($body);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new RuntimeException('The selected URL did not return a supported image.');
    }

    $extension = cms_extension_for_image_mime($mimeType);
    if ($compress) {
        $compressed = cms_compress_image_buffer($body, $mimeType, $maxWidth, $quality);
        if (is_array($compressed)) {
            $body = (string) $compressed['buffer'];
            $mimeType = (string) $compressed['mime_type'];
            $extension = (string) $compressed['extension'];
        }
    }

    $directory = cms_ensure_media_dir();
    $urlPath = (string) parse_url($sourceUrl, PHP_URL_PATH);
    $sourceName = pathinfo(basename($urlPath) ?: 'stock-image', PATHINFO_FILENAME);
    $baseTitle = trim($title) !== '' ? trim($title) : str_replace(['-', '_'], ' ', $sourceName);
    $safeBase = cms_make_slug($baseTitle !== '' ? $baseTitle : 'stock-image');
    $storedName = $safeBase . '-' . bin2hex(random_bytes(6)) . '.' . $extension;
    $targetPath = $directory . DIRECTORY_SEPARATOR . $storedName;

    if (file_put_contents($targetPath, $body, LOCK_EX) === false) {
        throw new RuntimeException('The imported image could not be saved into the media library.');
    }

    $relativePath = cms_media_web_path() . '/' . $storedName;
    $statement = cms_db()->prepare(
        'INSERT INTO media_files (original_name, stored_name, title, alt_text, file_path, mime_type, file_size, uploaded_by)
         VALUES (:original_name, :stored_name, :title, :alt_text, :file_path, :mime_type, :file_size, :uploaded_by)'
    );
    $statement->execute([
        'original_name' => basename($urlPath) ?: 'stock-image.' . $extension,
        'stored_name' => $storedName,
        'title' => $baseTitle !== '' ? $baseTitle : 'Stock image',
        'alt_text' => trim($altText),
        'file_path' => $relativePath,
        'mime_type' => $mimeType,
        'file_size' => strlen($body),
        'uploaded_by' => $adminId,
    ]);

    return cms_get_media((int) cms_db()->lastInsertId()) ?? [];
}

function cms_stock_image_api_key(string $provider): string
{
    $settings = cms_site_settings_extended();
    $settingsKey = match ($provider) {
        'pexels' => 'pexels_api_key',
        'pixabay' => 'pixabay_api_key',
        'unsplash' => 'unsplash_access_key',
        default => '',
    };

    $savedKey = $settingsKey !== '' ? trim((string) ($settings[$settingsKey] ?? '')) : '';
    if ($savedKey !== '') {
        return $savedKey;
    }

    $key = match ($provider) {
        'pexels' => (string) cms_config('stock_images.pexels_api_key', ''),
        'pixabay' => (string) cms_config('stock_images.pixabay_api_key', ''),
        'unsplash' => (string) cms_config('stock_images.unsplash_access_key', ''),
        default => '',
    };

    return trim($key);
}

function cms_configured_stock_image_providers(): array
{
    $providers = [];

    foreach (['pexels', 'pixabay', 'unsplash'] as $provider) {
        if (cms_stock_image_api_key($provider) !== '') {
            $providers[] = $provider;
        }
    }

    return $providers;
}

function cms_fetch_json(string $url, array $headers = []): array
{
    $headers[] = 'Accept: application/json';
    $headers[] = 'User-Agent: A1-Technovation-CMS/1.0';

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'A1-Technovation-CMS/1.0',
        ]);
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if (!is_string($body) || $body === '' || $status >= 400) {
            throw new RuntimeException($error !== '' ? $error : 'Stock image search failed.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 25,
                'header' => implode("\r\n", $headers),
            ],
        ]);
        $body = @file_get_contents($url, false, $context);

        if (!is_string($body) || $body === '') {
            throw new RuntimeException('Stock image search failed.');
        }
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Stock image search returned invalid JSON.');
    }

    return $decoded;
}

function cms_search_pexels_images(string $query, int $page, int $perPage): array
{
    $key = cms_stock_image_api_key('pexels');
    if ($key === '') {
        return ['provider' => 'pexels', 'configured' => false, 'items' => [], 'error' => 'Missing Pexels API key.'];
    }

    $url = 'https://api.pexels.com/v1/search?' . http_build_query([
        'query' => $query,
        'page' => $page,
        'per_page' => $perPage,
    ]);
    $data = cms_fetch_json($url, ['Authorization: ' . $key]);
    $items = [];

    foreach (($data['photos'] ?? []) as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        $src = is_array($photo['src'] ?? null) ? $photo['src'] : [];
        $imageUrl = (string) ($src['large2x'] ?? $src['large'] ?? $src['original'] ?? '');
        if ($imageUrl === '') {
            continue;
        }

        $photographer = (string) ($photo['photographer'] ?? 'Pexels contributor');
        $items[] = [
            'provider' => 'Pexels',
            'image_url' => $imageUrl,
            'preview_url' => (string) ($src['medium'] ?? $src['small'] ?? $imageUrl),
            'page_url' => (string) ($photo['url'] ?? ''),
            'title' => trim((string) ($photo['alt'] ?? '')) !== '' ? (string) $photo['alt'] : 'Pexels image by ' . $photographer,
            'alt_text' => (string) ($photo['alt'] ?? ''),
            'credit' => 'Photo by ' . $photographer . ' on Pexels',
        ];
    }

    return ['provider' => 'pexels', 'configured' => true, 'items' => $items, 'error' => ''];
}

function cms_search_pixabay_images(string $query, int $page, int $perPage): array
{
    $key = cms_stock_image_api_key('pixabay');
    if ($key === '') {
        return ['provider' => 'pixabay', 'configured' => false, 'items' => [], 'error' => 'Missing Pixabay API key.'];
    }

    $url = 'https://pixabay.com/api/?' . http_build_query([
        'key' => $key,
        'q' => $query,
        'image_type' => 'photo',
        'safesearch' => 'true',
        'page' => $page,
        'per_page' => $perPage,
    ]);
    $data = cms_fetch_json($url);
    $items = [];

    foreach (($data['hits'] ?? []) as $hit) {
        if (!is_array($hit)) {
            continue;
        }

        $imageUrl = (string) ($hit['largeImageURL'] ?? $hit['webformatURL'] ?? '');
        if ($imageUrl === '') {
            continue;
        }

        $tags = (string) ($hit['tags'] ?? '');
        $user = (string) ($hit['user'] ?? 'Pixabay contributor');
        $items[] = [
            'provider' => 'Pixabay',
            'image_url' => $imageUrl,
            'preview_url' => (string) ($hit['webformatURL'] ?? $hit['previewURL'] ?? $imageUrl),
            'page_url' => (string) ($hit['pageURL'] ?? ''),
            'title' => $tags !== '' ? $tags : 'Pixabay image by ' . $user,
            'alt_text' => $tags,
            'credit' => 'Image by ' . $user . ' on Pixabay',
        ];
    }

    return ['provider' => 'pixabay', 'configured' => true, 'items' => $items, 'error' => ''];
}

function cms_search_unsplash_images(string $query, int $page, int $perPage): array
{
    $key = cms_stock_image_api_key('unsplash');
    if ($key === '') {
        return ['provider' => 'unsplash', 'configured' => false, 'items' => [], 'error' => 'Missing Unsplash access key.'];
    }

    $url = 'https://api.unsplash.com/search/photos?' . http_build_query([
        'query' => $query,
        'page' => $page,
        'per_page' => $perPage,
    ]);
    $data = cms_fetch_json($url, ['Authorization: Client-ID ' . $key]);
    $items = [];

    foreach (($data['results'] ?? []) as $photo) {
        if (!is_array($photo)) {
            continue;
        }

        $urls = is_array($photo['urls'] ?? null) ? $photo['urls'] : [];
        $links = is_array($photo['links'] ?? null) ? $photo['links'] : [];
        $user = is_array($photo['user'] ?? null) ? $photo['user'] : [];
        $imageUrl = (string) ($urls['regular'] ?? $urls['full'] ?? '');
        if ($imageUrl === '') {
            continue;
        }

        $name = (string) ($user['name'] ?? 'Unsplash contributor');
        $alt = trim((string) ($photo['alt_description'] ?? $photo['description'] ?? ''));
        $items[] = [
            'provider' => 'Unsplash',
            'image_url' => $imageUrl,
            'preview_url' => (string) ($urls['small'] ?? $urls['thumb'] ?? $imageUrl),
            'page_url' => (string) ($links['html'] ?? ''),
            'download_url' => (string) ($links['download_location'] ?? ''),
            'title' => $alt !== '' ? $alt : 'Unsplash image by ' . $name,
            'alt_text' => $alt,
            'credit' => 'Photo by ' . $name . ' on Unsplash',
        ];
    }

    return ['provider' => 'unsplash', 'configured' => true, 'items' => $items, 'error' => ''];
}

function cms_search_stock_images(string $query, string $provider = 'all', int $page = 1, int $perPage = 20): array
{
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $provider = in_array($provider, ['all', 'pexels', 'pixabay', 'unsplash'], true) ? $provider : 'all';
    $page = max(1, $page);
    $perPage = max(3, min(24, $perPage));
    $configured = cms_configured_stock_image_providers();
    $searches = $provider === 'all' ? $configured : [$provider];
    $results = [];

    if ($searches === []) {
        return [
            [
                'provider' => 'stock images',
                'configured' => false,
                'items' => [],
                'error' => 'No stock image API key is configured.',
            ],
        ];
    }

    foreach ($searches as $name) {
        try {
            $results[] = match ($name) {
                'pexels' => cms_search_pexels_images($query, $page, $perPage),
                'pixabay' => cms_search_pixabay_images($query, $page, $perPage),
                'unsplash' => cms_search_unsplash_images($query, $page, $perPage),
                default => ['provider' => $name, 'configured' => false, 'items' => [], 'error' => 'Unknown provider.'],
            };
        } catch (Throwable $exception) {
            $results[] = ['provider' => $name, 'configured' => true, 'items' => [], 'error' => $exception->getMessage()];
        }
    }

    return $results;
}

function cms_track_unsplash_download(string $downloadUrl): void
{
    $downloadUrl = trim($downloadUrl);
    if ($downloadUrl === '' || cms_url_host($downloadUrl) !== 'api.unsplash.com') {
        return;
    }

    $key = cms_stock_image_api_key('unsplash');
    if ($key === '') {
        return;
    }

    try {
        cms_fetch_json($downloadUrl, ['Authorization: Client-ID ' . $key]);
    } catch (Throwable) {
        // Tracking should not block a successful local import.
    }
}

function cms_get_media(int $id): ?array
{
    if (!cms_table_exists('media_files')) {
        return null;
    }

    $statement = cms_db()->prepare('SELECT * FROM media_files WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $media = $statement->fetch();

    return is_array($media) ? $media : null;
}

function cms_get_recent_media(int $limit = 0): array
{
    if (!cms_table_exists('media_files')) {
        return [];
    }

    if ($limit > 0) {
        $limit = max(1, $limit);
        $statement = cms_db()->query('SELECT * FROM media_files ORDER BY created_at DESC LIMIT ' . $limit);
    } else {
        $statement = cms_db()->query('SELECT * FROM media_files ORDER BY created_at DESC');
    }

    return $statement->fetchAll() ?: [];
}

function cms_get_site_image_assets(string $directory = 'assets/images'): array
{
    $root = cms_root();
    $absoluteDirectory = cms_root($directory);

    if (!is_dir($absoluteDirectory)) {
        return [];
    }

    $allowedExtensions = ['avif', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp'];
    $items = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($absoluteDirectory, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo instanceof SplFileInfo || !$fileInfo->isFile()) {
            continue;
        }

        $extension = strtolower($fileInfo->getExtension());
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $absolutePath = $fileInfo->getPathname();
        $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($root))), '/');
        $title = pathinfo($fileInfo->getFilename(), PATHINFO_FILENAME);
        $isProtected = in_array($relativePath, cms_protected_site_image_paths(), true);

        $items[] = [
            'title' => str_replace(['-', '_'], ' ', $title),
            'file_name' => $fileInfo->getFilename(),
            'file_path' => $relativePath,
            'url' => cms_media_url($relativePath),
            'extension' => $extension,
            'size' => $fileInfo->getSize(),
            'is_protected' => $isProtected,
        ];
    }

    usort($items, static fn (array $a, array $b): int => strnatcasecmp((string) $a['file_path'], (string) $b['file_path']));

    return $items;
}

function cms_protected_site_image_paths(): array
{
    return [
        'assets/images/favicon.png',
        'assets/images/apple-touch-icon.png',
    ];
}

function cms_delete_site_image(string $relativePath): bool
{
    $relativePath = trim(str_replace('\\', '/', $relativePath));
    $relativePath = ltrim($relativePath, '/');

    if (!str_starts_with($relativePath, 'assets/images/')) {
        return false;
    }

    if (in_array($relativePath, cms_protected_site_image_paths(), true)) {
        return false;
    }

    $allowedExtensions = ['avif', 'gif', 'jpg', 'jpeg', 'png', 'svg', 'webp'];
    $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return false;
    }

    $imagesDir = realpath(cms_root('assets/images'));
    $absolutePath = cms_root($relativePath);
    $realFile = realpath($absolutePath);

    if ($imagesDir === false || $realFile === false) {
        return false;
    }

    if (!str_starts_with($realFile, $imagesDir . DIRECTORY_SEPARATOR) && $realFile !== $imagesDir) {
        return false;
    }

    if (is_file($realFile)) {
        $deleted = @unlink($realFile);
        if ($deleted) {
            try {
                if (cms_table_exists('media_files')) {
                    $stmt = cms_db()->prepare('DELETE FROM media_files WHERE file_path = :path OR file_path = :rpath');
                    $stmt->execute(['path' => $relativePath, 'rpath' => str_replace('/', DIRECTORY_SEPARATOR, $relativePath)]);
                }
            } catch (Throwable) {
            }
        }
        return $deleted;
    }

    return false;
}

function cms_bulk_delete_site_images(array $relativePaths): int
{
    $deleted = 0;
    foreach ($relativePaths as $path) {
        if (is_string($path) && cms_delete_site_image($path)) {
            $deleted++;
        }
    }
    return $deleted;
}

function cms_delete_media(int $id): void
{
    $media = cms_get_media($id);

    if (!$media) {
        return;
    }

    $absolutePath = cms_root((string) $media['file_path']);

    if (is_file($absolutePath)) {
        @unlink($absolutePath);
    }

    $statement = cms_db()->prepare('DELETE FROM media_files WHERE id = :id');
    $statement->execute(['id' => $id]);
}

function cms_bulk_delete_media(array $ids): int
{
    $deleted = 0;
    foreach ($ids as $id) {
        $id = (int) $id;
        if ($id > 0) {
            cms_delete_media($id);
            $deleted++;
        }
    }
    return $deleted;
}

function cms_get_post(int $id): ?array
{
    if (!cms_table_exists('blog_posts')) {
        return null;
    }

    $statement = cms_db()->prepare('SELECT * FROM blog_posts WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $post = $statement->fetch();

    return is_array($post) ? $post : null;
}

function cms_get_posts_admin(): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }

    $sql = 'SELECT bp.*, au.full_name AS admin_name
            FROM blog_posts bp
            LEFT JOIN admin_users au ON au.id = bp.updated_by
            ORDER BY COALESCE(bp.published_at, bp.updated_at) DESC';

    return cms_db()->query($sql)->fetchAll() ?: [];
}

function cms_get_admin_post_for_preview(int $id): ?array
{
    return cms_get_post($id);
}

function cms_save_post(array $input, int $adminId): int
{
    cms_ensure_content_tables_utf8mb4();

    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'custom_html')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN custom_html MEDIUMTEXT NULL AFTER content_html');
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'layout_mode')) {
        cms_db()->exec("ALTER TABLE blog_posts ADD COLUMN layout_mode VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER custom_html");
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'head_html')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN head_html MEDIUMTEXT NULL AFTER layout_mode');
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'custom_css')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN custom_css LONGTEXT NULL AFTER head_html');
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'body_class')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN body_class VARCHAR(255) NULL AFTER custom_css');
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'author_image')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN author_image VARCHAR(255) NULL AFTER author_name');
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'default_interlinks_enabled')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN default_interlinks_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER tags');
    }
    if (cms_table_exists('blog_posts') && !cms_column_exists('blog_posts', 'default_interlinks_html')) {
        cms_db()->exec('ALTER TABLE blog_posts ADD COLUMN default_interlinks_html MEDIUMTEXT NULL AFTER default_interlinks_enabled');
    }

    $id = isset($input['id']) ? max(0, (int) $input['id']) : 0;
    $title = trim((string) ($input['title'] ?? ''));
    $status = (string) ($input['status'] ?? 'draft');
    $providedSlug = trim((string) ($input['slug'] ?? ''));
    $contentHtml = cms_clean_html((string) ($input['content_html'] ?? ''));
    $customHtml = trim((string) ($input['custom_html'] ?? ''));
    $layoutMode = cms_normalize_blog_layout_mode((string) ($input['layout_mode'] ?? 'standard'));
    $headHtml = trim((string) ($input['head_html'] ?? ''));
    $customCss = trim((string) ($input['custom_css'] ?? ''));
    $bodyClass = trim((string) ($input['body_class'] ?? ''));
    $excerpt = trim((string) ($input['excerpt'] ?? ''));

    if ($title === '') {
        throw new RuntimeException('Post title is required.');
    }

    if ($contentHtml === '') {
        throw new RuntimeException('Post content is required.');
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }

    $slug = cms_unique_post_slug($title, $id > 0 ? $id : null, $providedSlug);
    $authorName = trim((string) ($input['author_name'] ?? cms_config('app.default_author', 'A1 Technovation')));
    $authorImage = trim((string) ($input['author_image'] ?? ''));
    $category = trim((string) ($input['category'] ?? ''));
    $tags = cms_normalize_tags((string) ($input['tags'] ?? ''));
    $defaultInterlinksEnabled = !empty($input['default_interlinks_enabled']) ? 1 : 0;
    $defaultInterlinksHtml = cms_clean_html((string) ($input['default_interlinks_html'] ?? ''));
    if ($defaultInterlinksEnabled === 1 && $defaultInterlinksHtml === '') {
        $defaultInterlinksHtml = cms_default_blog_interlinks_html();
    }
    $featuredImage = trim((string) ($input['featured_image'] ?? ''));
    $metaTitle = trim((string) ($input['meta_title'] ?? ''));
    $metaDescription = trim((string) ($input['meta_description'] ?? ''));

    if ($excerpt === '') {
        $excerpt = cms_excerpt_from_html($contentHtml);
    }

    if ($metaTitle === '') {
        $metaTitle = $title;
    }

    if ($metaDescription === '') {
        $metaDescription = $excerpt;
    }

    $publishedAt  = cms_normalize_datetime_input((string) ($input['published_at'] ?? ''));
    $scheduledAt  = cms_normalize_datetime_input((string) ($input['scheduled_at'] ?? ''));
    $hasScheduled = cms_column_exists('blog_posts', 'scheduled_at');

    if ($hasScheduled && $scheduledAt !== null && strtotime($scheduledAt) > time()) {
        $status = 'draft';
        $publishedAt = null;
    }

    if ($status === 'published' && $publishedAt === null && $scheduledAt === null) {
        $publishedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    $payload = [
        'title' => $title,
        'slug' => $slug,
        'status' => $status,
        'excerpt' => $excerpt,
        'content_html' => $contentHtml,
        'custom_html' => $customHtml,
        'layout_mode' => $layoutMode,
        'head_html' => $headHtml,
        'custom_css' => $customCss,
        'body_class' => $bodyClass,
        'featured_image' => $featuredImage,
        'author_name' => $authorName !== '' ? $authorName : (string) cms_config('app.default_author', 'A1 Technovation'),
        'author_image' => $authorImage,
        'category' => $category,
        'tags' => $tags,
        'default_interlinks_enabled' => $defaultInterlinksEnabled,
        'default_interlinks_html' => $defaultInterlinksHtml,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'published_at' => $publishedAt,
        'updated_by' => $adminId,
    ];

    if ($hasScheduled) {
        $payload['scheduled_at'] = $scheduledAt;
    }

    if ($id > 0) {
        $payload['id'] = $id;
        $schedCol = $hasScheduled ? ', scheduled_at = :scheduled_at' : '';
        $statement = cms_db()->prepare(
            "UPDATE blog_posts
             SET title = :title, slug = :slug, status = :status, excerpt = :excerpt, content_html = :content_html,
                 custom_html = :custom_html, layout_mode = :layout_mode, head_html = :head_html,
                 custom_css = :custom_css, body_class = :body_class, featured_image = :featured_image, author_name = :author_name,
                 author_image = :author_image, category = :category, tags = :tags,
                 default_interlinks_enabled = :default_interlinks_enabled, default_interlinks_html = :default_interlinks_html,
                 meta_title = :meta_title, meta_description = :meta_description,
                 published_at = :published_at, updated_by = :updated_by{$schedCol}
             WHERE id = :id"
        );
        $statement->execute($payload);

        foreach (array_unique($purgeUrls) as $url) {
            cms_cache_purge_url($url);
        }

        return $id;
    }

    $payload['created_by'] = $adminId;

    if ($hasScheduled) {
        $statement = cms_db()->prepare(
            'INSERT INTO blog_posts
             (title, slug, status, excerpt, content_html, custom_html, layout_mode, head_html, custom_css, body_class, featured_image, author_name, author_image, category, tags, default_interlinks_enabled, default_interlinks_html, meta_title, meta_description, published_at, scheduled_at, created_by, updated_by)
             VALUES
             (:title, :slug, :status, :excerpt, :content_html, :custom_html, :layout_mode, :head_html, :custom_css, :body_class, :featured_image, :author_name, :author_image, :category, :tags, :default_interlinks_enabled, :default_interlinks_html, :meta_title, :meta_description, :published_at, :scheduled_at, :created_by, :updated_by)'
        );
    } else {
        $statement = cms_db()->prepare(
            'INSERT INTO blog_posts
             (title, slug, status, excerpt, content_html, custom_html, layout_mode, head_html, custom_css, body_class, featured_image, author_name, author_image, category, tags, default_interlinks_enabled, default_interlinks_html, meta_title, meta_description, published_at, created_by, updated_by)
             VALUES
             (:title, :slug, :status, :excerpt, :content_html, :custom_html, :layout_mode, :head_html, :custom_css, :body_class, :featured_image, :author_name, :author_image, :category, :tags, :default_interlinks_enabled, :default_interlinks_html, :meta_title, :meta_description, :published_at, :created_by, :updated_by)'
        );
    }
    $statement->execute($payload);

    foreach (array_unique($purgeUrls) as $url) {
        cms_cache_purge_url($url);
    }

    return (int) cms_db()->lastInsertId();
}

function cms_delete_post(int $id): void
{
    if (!cms_table_exists('blog_posts')) {
        return;
    }

    $existingPost = cms_get_post($id);
    $statement = cms_db()->prepare('DELETE FROM blog_posts WHERE id = :id');
    $statement->execute(['id' => $id]);

    $purgeUrls = [cms_blog_index_url()];
    if (is_array($existingPost)) {
        $slug = trim((string) ($existingPost['slug'] ?? ''));
        $category = trim((string) ($existingPost['category'] ?? ''));
        $tags = trim((string) ($existingPost['tags'] ?? ''));

        if ($slug !== '') {
            $purgeUrls[] = cms_blog_post_url($slug);
        }
        if ($category !== '') {
            $purgeUrls[] = cms_blog_category_url($category);
        }
        foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag) {
            $purgeUrls[] = cms_blog_tag_url($tag);
        }
    }

    foreach (array_unique($purgeUrls) as $url) {
        cms_cache_purge_url($url);
    }
}

function cms_get_dashboard_stats(): array
{
    if (!cms_table_exists('blog_posts')) {
        return [
            'posts_total' => 0,
            'posts_published' => 0,
            'posts_draft' => 0,
            'media_total' => 0,
            'pages_total' => 0,
            'pages_published' => 0,
            'templates_total' => 0,
        ];
    }

    $pdo = cms_db();

    return [
        'posts_total' => (int) $pdo->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn(),
        'posts_published' => (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'published'")->fetchColumn(),
        'posts_draft' => (int) $pdo->query("SELECT COUNT(*) FROM blog_posts WHERE status = 'draft'")->fetchColumn(),
        'media_total' => cms_table_exists('media_files') ? (int) $pdo->query('SELECT COUNT(*) FROM media_files')->fetchColumn() : 0,
        'pages_total' => cms_table_exists('cms_pages') ? (int) $pdo->query('SELECT COUNT(*) FROM cms_pages')->fetchColumn() : 0,
        'pages_published' => cms_table_exists('cms_pages') ? (int) $pdo->query("SELECT COUNT(*) FROM cms_pages WHERE status = 'published'")->fetchColumn() : 0,
        'templates_total' => cms_table_exists('cms_section_templates') ? (int) $pdo->query('SELECT COUNT(*) FROM cms_section_templates')->fetchColumn() : 0,
    ];
}

function cms_get_published_posts(int $limit = 50): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }

    $limit = max(1, min($limit, 200));
    $sql = "SELECT * FROM blog_posts WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= NOW() ORDER BY published_at DESC LIMIT " . $limit;

    return cms_db()->query($sql)->fetchAll() ?: [];
}

function cms_get_published_post_by_slug(string $slug): ?array
{
    if (!cms_table_exists('blog_posts')) {
        return null;
    }

    $statement = cms_db()->prepare(
        "SELECT * FROM blog_posts WHERE slug = :slug AND status = 'published' AND published_at IS NOT NULL AND published_at <= NOW() LIMIT 1"
    );
    $statement->execute(['slug' => $slug]);
    $post = $statement->fetch();

    return is_array($post) ? $post : null;
}

function cms_get_related_posts(int $postId, string $category = '', int $limit = 3): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }

    $limit = max(1, min($limit, 6));
    $params = ['id' => $postId];
    $sql = "SELECT * FROM blog_posts WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= NOW() AND id != :id";

    if ($category !== '') {
        $sql .= ' AND category = :category';
        $params['category'] = $category;
    }

    $sql .= ' ORDER BY published_at DESC LIMIT ' . $limit;

    $statement = cms_db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll() ?: [];
}

function cms_page_templates(): array
{
    return [
        'service' => 'Service Page',
        'landing' => 'Landing Page',
        'standard' => 'Standard Page',
    ];
}

function cms_widget_catalog(): array
{
    return [
        'hero' => 'Hero banner',
        'text' => 'Text section',
        'image' => 'Single image',
        'video' => 'Video block',
        'gallery' => 'Image gallery',
        'logo_grid' => 'Logo grid',
        'downloads' => 'Download links',
        'split_content' => 'Text with image',
        'embed' => 'Embed code',
        'cta' => 'Call to action',
        'faq' => 'FAQ accordion',
        'pricing' => 'Pricing packages',
        'testimonials' => 'Client testimonials',
        'stats' => 'Number highlights',
        'service_cards' => 'Service cards',
        'buttons' => 'Button group',
        'custom_html' => 'Custom HTML',
    ];
}

function cms_widget_descriptions(): array
{
    return [
        'hero' => 'The first banner on a page with a headline, short copy, background image, and main buttons.',
        'text' => 'A simple heading with formatted content for explanations, intros, or page copy.',
        'image' => 'One image with alt text and an optional caption.',
        'video' => 'A video URL with an optional poster image and caption.',
        'gallery' => 'A set of images shown together, each with alt text and captions.',
        'logo_grid' => 'Client, partner, or certification logos with optional links.',
        'downloads' => 'Cards that link to PDFs, files, resources, or lead magnets.',
        'split_content' => 'A two-column section with text on one side and an image on the other.',
        'embed' => 'Trusted iframe or embed code, such as YouTube, maps, or third-party tools.',
        'cta' => 'A focused prompt that asks visitors to take one action, like booking a call.',
        'faq' => 'Questions and answers shown in a compact accordion-style section.',
        'pricing' => 'Package cards with price, summary, and feature lists.',
        'testimonials' => 'Customer quotes with name and role.',
        'stats' => 'Short metrics such as growth percentage, time saved, or project counts.',
        'service_cards' => 'Small cards for included services, features, or process steps.',
        'buttons' => 'A row or group of helpful links.',
        'custom_html' => 'A flexible block for trusted custom markup when no other section fits.',
    ];
}

function cms_default_widget_payload(string $widgetType): array
{
    return match ($widgetType) {
        'hero' => [
            'eyebrow' => 'SEO Growth Service',
            'heading' => 'Grow qualified traffic with a page built to convert',
            'highlight' => 'without guesswork',
            'text' => 'Replace this demo copy with a clear promise, the audience you serve, and the main result visitors can expect.',
            'primary_label' => 'Book a Free Call',
            'primary_url' => '/pages/contact',
            'secondary_label' => 'See Our Work',
            'secondary_url' => '/pages/portfolio',
            'background_image' => '/assets/images/seo-service-new.webp',
            'theme' => 'dark',
        ],
        'text' => [
            'heading' => 'Why this service matters',
            'body' => '<p>Replace this paragraph with the main explanation for this section. Keep it focused on the visitor problem, your solution, and the next useful detail they should understand.</p><p>Add another short paragraph if you need proof, process, or context.</p>',
        ],
        'image' => [
            'heading' => 'A clear look at the process',
            'image_url' => '/assets/images/seo-process-visual.webp',
            'alt' => 'SEO process visual',
            'caption' => 'Replace this caption with a short description of what the image shows.',
        ],
        'video' => [
            'heading' => 'Watch how the service works',
            'video_url' => 'https://www.youtube.com/embed/VIDEO_ID',
            'poster_url' => '/assets/images/ppc-dashboard-visual.webp',
            'caption' => 'Replace this with a short summary of the video.',
        ],
        'gallery' => [
            'heading' => 'Project snapshots',
            'items' => [
                ['image_url' => '/assets/images/seo-service-new.webp', 'alt' => 'SEO planning dashboard', 'caption' => 'Replace with your first gallery caption.'],
                ['image_url' => '/assets/images/content-marketing-agency.webp', 'alt' => 'Content marketing workspace', 'caption' => 'Replace with your second gallery caption.'],
                ['image_url' => '/assets/images/ppc-dashboard-visual.webp', 'alt' => 'Marketing performance dashboard', 'caption' => 'Replace with your third gallery caption.'],
            ],
        ],
        'logo_grid' => [
            'heading' => 'Trusted by teams that need measurable growth',
            'items' => [
                ['image_url' => '/assets/images/A1Technovation-Dark-Background.png', 'alt' => 'Partner logo one', 'url' => '#'],
                ['image_url' => '/assets/images/A1Technovation-Dark-Background.png', 'alt' => 'Partner logo two', 'url' => '#'],
                ['image_url' => '/assets/images/A1Technovation-Dark-Background.png', 'alt' => 'Partner logo three', 'url' => '#'],
            ],
        ],
        'downloads' => [
            'heading' => 'Helpful resources',
            'intro' => 'Replace this intro with a short reason visitors should download or view these resources.',
            'items' => [
                ['title' => 'SEO checklist', 'description' => 'Replace this with a short description of the downloadable file.', 'file_url' => '/assets/files/seo-checklist.pdf', 'button_label' => 'Download checklist'],
                ['title' => 'Growth brief', 'description' => 'Replace this with another useful resource description.', 'file_url' => '/assets/files/growth-brief.pdf', 'button_label' => 'View resource'],
            ],
        ],
        'split_content' => [
            'eyebrow' => 'Why choose us',
            'heading' => 'A practical growth system built around your goals',
            'body' => '<p>Replace this copy with the strongest reason someone should trust this service. Explain the process, the benefit, and what makes the approach different.</p><ul><li>Replace this bullet with a key benefit.</li><li>Replace this bullet with a proof point.</li><li>Replace this bullet with the next step.</li></ul>',
            'image_url' => '/assets/images/development-expertise.webp',
            'image_alt' => 'Team planning a digital growth project',
            'image_position' => 'right',
            'primary_label' => 'Get Started',
            'primary_url' => '/pages/contact',
            'secondary_label' => 'Learn More',
            'secondary_url' => '#',
        ],
        'embed' => [
            'heading' => 'Embedded media or map',
            'embed_html' => '<iframe src="https://www.youtube.com/embed/VIDEO_ID" loading="lazy" allowfullscreen></iframe>',
            'caption' => 'Replace this caption with context for the embedded content.',
        ],
        'cta' => [
            'heading' => 'Ready to build a stronger growth engine?',
            'text' => 'Replace this text with one direct reason visitors should contact you now.',
            'button_label' => 'Book a Free Call',
            'button_url' => 'https://wa.me/8801799976295',
        ],
        'faq' => [
            'heading' => 'Frequently asked questions',
            'items' => [
                ['question' => 'How quickly can we start?', 'answer' => 'Replace this answer with your real timeline, onboarding process, or requirements.'],
                ['question' => 'What do you need from us?', 'answer' => 'Replace this answer with the access, content, or business details you need from the client.'],
                ['question' => 'How will we measure success?', 'answer' => 'Replace this answer with the KPIs, reports, or milestones you use.'],
            ],
        ],
        'pricing' => [
            'heading' => 'Choose the right package',
            'items' => [
                ['name' => 'Starter', 'price' => '$499', 'details' => 'Replace this with who the starter package is best for.', 'features' => ['Initial audit', 'Priority fixes', 'Monthly report']],
                ['name' => 'Growth', 'price' => '$999', 'details' => 'Replace this with your most popular package summary.', 'features' => ['Strategy roadmap', 'Content optimization', 'Conversion improvements']],
                ['name' => 'Scale', 'price' => 'Custom', 'details' => 'Replace this with an enterprise or custom engagement summary.', 'features' => ['Custom strategy', 'Dedicated support', 'Advanced reporting']],
            ],
        ],
        'testimonials' => [
            'heading' => 'What clients say',
            'items' => [
                ['quote' => 'Replace this with a real quote about the result, communication, or quality of work.', 'name' => 'Client Name', 'role' => 'Business Owner'],
                ['quote' => 'Replace this with another customer proof point that supports the offer.', 'name' => 'Client Name', 'role' => 'Marketing Lead'],
            ],
        ],
        'stats' => [
            'heading' => 'Performance highlights',
            'items' => [
                ['number' => '250%', 'label' => 'Organic traffic growth'],
                ['number' => '90 days', 'label' => 'Average momentum window'],
                ['number' => '30+', 'label' => 'Technical fixes reviewed'],
            ],
        ],
        'service_cards' => [
            'heading' => 'What is included',
            'items' => [
                ['title' => 'Technical audit', 'text' => 'Replace this with what you check, fix, or improve.'],
                ['title' => 'Content strategy', 'text' => 'Replace this with how you plan content or page updates.'],
                ['title' => 'Performance reporting', 'text' => 'Replace this with how clients see progress.'],
            ],
        ],
        'buttons' => [
            'heading' => 'Helpful links',
            'items' => [
                ['label' => 'Contact Us', 'url' => '/pages/contact'],
                ['label' => 'View Portfolio', 'url' => '/pages/portfolio'],
                ['label' => 'See Services', 'url' => '/pages/services'],
            ],
        ],
        'custom_html' => [
            'heading' => 'Custom HTML block',
            'html' => '<div class="custom-block"><h3>Replace this custom section</h3><p>Use this area for trusted HTML that does not fit the ready-made sections.</p></div>',
        ],
        default => ['heading' => 'New section'],
    };
}

function cms_demo_section_templates(): array
{
    $templates = [];
    foreach (cms_widget_catalog() as $widgetType => $label) {
        $templates[] = [
            'id' => 0,
            'name' => 'Demo ' . $label,
            'widget_type' => $widgetType,
            'payload_json' => cms_json_encode(cms_default_widget_payload($widgetType)),
            'created_by' => null,
            'created_at' => null,
            'updated_at' => null,
        ];
    }

    return $templates;
}

function cms_sanitize_widget_payload(string $widgetType, mixed $payload): array
{
    $data = is_array($payload) ? $payload : [];

    if (in_array($widgetType, ['text', 'custom_html', 'split_content', 'embed'], true)) {
        if (isset($data['body'])) {
            $data['body'] = cms_clean_html((string) $data['body']);
        }
        if (isset($data['html'])) {
            $data['html'] = cms_clean_html((string) $data['html']);
        }
        if (isset($data['embed_html'])) {
            $data['embed_html'] = cms_clean_html((string) $data['embed_html']);
        }
    }

    foreach (['items'] as $listKey) {
        if (isset($data[$listKey]) && is_array($data[$listKey])) {
            $normalized = [];
            foreach ($data[$listKey] as $item) {
                if (is_array($item)) {
                    $normalized[] = $item;
                }
            }
            $data[$listKey] = $normalized;
        }
    }

    return $data;
}

function cms_normalize_widget_rows(mixed $rows): array
{
    $normalized = [];
    if (!is_array($rows)) {
        return $normalized;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $widgetType = (string) ($row['widget_type'] ?? '');
        if ($widgetType === '') {
            continue;
        }

        $normalized[] = [
            'widget_type' => $widgetType,
            'payload' => cms_sanitize_widget_payload($widgetType, $row['payload'] ?? []),
        ];
    }

    return $normalized;
}

function cms_page_slug_exists(string $slug, ?int $ignoreId = null): bool
{
    if (!cms_table_exists('cms_pages')) {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM cms_pages WHERE slug = :slug';
    $params = ['slug' => $slug];
    if ($ignoreId !== null) {
        $sql .= ' AND id != :id';
        $params['id'] = $ignoreId;
    }
    $statement = cms_db()->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn() > 0;
}

function cms_unique_page_slug(string $title, ?int $ignoreId = null, ?string $provided = null): string
{
    $base = cms_make_slug($provided !== null && $provided !== '' ? $provided : $title);
    $slug = $base;
    $suffix = 2;

    while (cms_page_slug_exists($slug, $ignoreId)) {
        $slug = $base . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function cms_get_pages_admin(?string $pageType = null): array
{
    if (!cms_table_exists('cms_pages')) {
        return [];
    }

    $sql = 'SELECT * FROM cms_pages';
    $params = [];
    if ($pageType !== null && $pageType !== '') {
        $sql .= ' WHERE page_type = :page_type';
        $params['page_type'] = $pageType;
    }
    $sql .= ' ORDER BY sort_order ASC, COALESCE(published_at, updated_at) DESC';

    $statement = cms_db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll() ?: [];
}

function cms_get_page(int $id): ?array
{
    if (!cms_table_exists('cms_pages')) {
        return null;
    }
    $statement = cms_db()->prepare('SELECT * FROM cms_pages WHERE id = :id LIMIT 1');
    $statement->execute(['id' => $id]);
    $page = $statement->fetch();

    if (!is_array($page)) {
        return null;
    }

    $page['builder'] = cms_json_decode_array((string) ($page['page_builder_json'] ?? ''), []);
    return $page;
}

function cms_get_admin_page_for_preview(int $id): ?array
{
    return cms_get_page($id);
}

function cms_get_published_page_by_slug(string $slug, ?string $pageType = null): ?array
{
    if (!cms_table_exists('cms_pages')) {
        return null;
    }

    $sql = "SELECT * FROM cms_pages WHERE slug = :slug AND status = 'published' AND (published_at IS NULL OR published_at <= NOW())";
    $params = ['slug' => $slug];
    if ($pageType !== null && $pageType !== '') {
        $sql .= ' AND page_type = :page_type';
        $params['page_type'] = $pageType;
    }
    $sql .= ' LIMIT 1';
    $statement = cms_db()->prepare($sql);
    $statement->execute($params);
    $page = $statement->fetch();

    if (!is_array($page)) {
        return null;
    }

    $page['builder'] = cms_json_decode_array((string) ($page['page_builder_json'] ?? ''), []);
    return $page;
}

function cms_save_page(array $input, int $adminId): int
{
    cms_ensure_content_tables_utf8mb4();

    $id = isset($input['id']) ? max(0, (int) $input['id']) : 0;
    $title = trim((string) ($input['title'] ?? ''));
    $pageType = (string) ($input['page_type'] ?? 'page');
    $status = (string) ($input['status'] ?? 'draft');
    $providedSlug = trim((string) ($input['slug'] ?? ''));
    $builder = cms_normalize_widget_rows(cms_json_decode_array((string) ($input['builder_json'] ?? ''), []));
    $customHtml = cms_clean_html((string) ($input['custom_html'] ?? ''));

    if ($title === '') {
        throw new RuntimeException('Page title is required.');
    }
    if (!in_array($pageType, ['page', 'service', 'landing'], true)) {
        $pageType = 'page';
    }
    if (!in_array($status, ['draft', 'published'], true)) {
        $status = 'draft';
    }
    if ($builder === [] && $customHtml === '') {
        throw new RuntimeException('Add at least one widget section or custom HTML block.');
    }

    $slug = cms_unique_page_slug($title, $id > 0 ? $id : null, $providedSlug);
    $excerpt = trim((string) ($input['excerpt'] ?? ''));
    $metaTitle = trim((string) ($input['meta_title'] ?? ''));
    $metaDescription = trim((string) ($input['meta_description'] ?? ''));
    $featuredImage = trim((string) ($input['featured_image'] ?? ''));
    $ogImage = trim((string) ($input['og_image'] ?? ''));
    $canonicalUrl = trim((string) ($input['canonical_url'] ?? ''));
    $schemaJson = trim((string) ($input['schema_json'] ?? ''));
    $templateKey = (string) ($input['template_key'] ?? 'service');
    if (!array_key_exists($templateKey, cms_page_templates())) {
        $templateKey = 'service';
    }
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    $showInNav = cms_post('show_in_nav') ? 1 : 0;
    $navGroup = trim((string) ($input['nav_group'] ?? ''));
    $customCss = trim((string) ($input['custom_css'] ?? ''));
    $customJs = trim((string) ($input['custom_js'] ?? ''));
    $publishedAt = cms_normalize_datetime_input((string) ($input['published_at'] ?? ''));
    $scheduledAt = cms_normalize_datetime_input((string) ($input['scheduled_at'] ?? ''));
    $hasScheduled = cms_column_exists('cms_pages', 'scheduled_at');

    if ($hasScheduled && $scheduledAt !== null && strtotime($scheduledAt) > time()) {
        $status = 'draft';
        $publishedAt = null;
    }

    if ($status === 'published' && $publishedAt === null && $scheduledAt === null) {
        $publishedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }

    if ($excerpt === '') {
        $excerpt = cms_excerpt_from_html($customHtml !== '' ? $customHtml : cms_json_encode($builder));
    }
    if ($metaTitle === '') {
        $metaTitle = $title;
    }
    if ($metaDescription === '') {
        $metaDescription = $excerpt;
    }

    $payload = [
        'title' => $title,
        'slug' => $slug,
        'page_type' => $pageType,
        'status' => $status,
        'excerpt' => $excerpt,
        'template_key' => $templateKey,
        'featured_image' => $featuredImage,
        'page_builder_json' => cms_json_encode($builder),
        'custom_html' => $customHtml,
        'custom_css' => $customCss,
        'custom_js' => $customJs,
        'show_in_nav' => $showInNav,
        'nav_group' => $navGroup !== '' ? $navGroup : null,
        'sort_order' => $sortOrder,
        'meta_title' => $metaTitle,
        'meta_description' => $metaDescription,
        'canonical_url' => $canonicalUrl,
        'og_image' => $ogImage,
        'schema_json' => $schemaJson,
        'published_at' => $publishedAt,
        'updated_by' => $adminId,
    ];

    if ($hasScheduled) {
        $payload['scheduled_at'] = $scheduledAt;
    }

    $purgeUrls = [cms_blog_index_url()];
    if ($slug !== '') {
        $purgeUrls[] = cms_blog_post_url($slug);
    }
    if ($category !== '') {
        $purgeUrls[] = cms_blog_category_url($category);
    }
    foreach (array_filter(array_map('trim', explode(',', $tags))) as $tag) {
        $purgeUrls[] = cms_blog_tag_url($tag);
    }

    if ($id > 0) {
        $payload['id'] = $id;
        $schedCol = $hasScheduled ? ', scheduled_at = :scheduled_at' : '';
        $statement = cms_db()->prepare(
            'UPDATE cms_pages
             SET title = :title, slug = :slug, page_type = :page_type, status = :status, excerpt = :excerpt,
                 template_key = :template_key, featured_image = :featured_image, page_builder_json = :page_builder_json,
                 custom_html = :custom_html, custom_css = :custom_css, custom_js = :custom_js, show_in_nav = :show_in_nav,
                 nav_group = :nav_group, sort_order = :sort_order, meta_title = :meta_title, meta_description = :meta_description,
                 canonical_url = :canonical_url, og_image = :og_image, schema_json = :schema_json, published_at = :published_at,
                 updated_by = :updated_by' . $schedCol . '
             WHERE id = :id'
        );
        $statement->execute($payload);
        return $id;
    }

    $payload['created_by'] = $adminId;
    if ($hasScheduled) {
        $statement = cms_db()->prepare(
            'INSERT INTO cms_pages
             (title, slug, page_type, status, excerpt, template_key, featured_image, page_builder_json, custom_html, custom_css, custom_js, show_in_nav, nav_group, sort_order, meta_title, meta_description, canonical_url, og_image, schema_json, published_at, scheduled_at, created_by, updated_by)
             VALUES
             (:title, :slug, :page_type, :status, :excerpt, :template_key, :featured_image, :page_builder_json, :custom_html, :custom_css, :custom_js, :show_in_nav, :nav_group, :sort_order, :meta_title, :meta_description, :canonical_url, :og_image, :schema_json, :published_at, :scheduled_at, :created_by, :updated_by)'
        );
    } else {
        $statement = cms_db()->prepare(
            'INSERT INTO cms_pages
             (title, slug, page_type, status, excerpt, template_key, featured_image, page_builder_json, custom_html, custom_css, custom_js, show_in_nav, nav_group, sort_order, meta_title, meta_description, canonical_url, og_image, schema_json, published_at, created_by, updated_by)
             VALUES
             (:title, :slug, :page_type, :status, :excerpt, :template_key, :featured_image, :page_builder_json, :custom_html, :custom_css, :custom_js, :show_in_nav, :nav_group, :sort_order, :meta_title, :meta_description, :canonical_url, :og_image, :schema_json, :published_at, :created_by, :updated_by)'
        );
    }
    $statement->execute($payload);

    return (int) cms_db()->lastInsertId();
}

function cms_delete_page(int $id): void
{
    if (!cms_table_exists('cms_pages')) {
        return;
    }
    $statement = cms_db()->prepare('DELETE FROM cms_pages WHERE id = :id');
    $statement->execute(['id' => $id]);
}

function cms_duplicate_page(int $id, int $adminId): int
{
    $page = cms_get_page($id);
    if (!$page) {
        throw new RuntimeException('The page you tried to duplicate could not be found.');
    }

    $page['id'] = 0;
    $page['title'] = (string) $page['title'] . ' Copy';
    $page['slug'] = '';
    $page['status'] = 'draft';
    $page['published_at'] = null;
    $page['builder_json'] = cms_json_encode($page['builder'] ?? []);

    return cms_save_page($page, $adminId);
}

function cms_get_section_templates(): array
{
    if (!cms_table_exists('cms_section_templates')) {
        return [];
    }
    return cms_db()->query('SELECT * FROM cms_section_templates ORDER BY updated_at DESC')->fetchAll() ?: [];
}

function cms_save_section_template(array $input, int $adminId): int
{
    if (!cms_table_exists('cms_section_templates')) {
        throw new RuntimeException('Section templates table is missing. Re-run the CMS schema.');
    }
    $id = isset($input['id']) ? max(0, (int) $input['id']) : 0;
    $name = trim((string) ($input['name'] ?? ''));
    $widgetType = (string) ($input['widget_type'] ?? 'text');
    $payload = cms_sanitize_widget_payload($widgetType, cms_json_decode_array((string) ($input['payload_json'] ?? ''), []));
    if ($name === '') {
        throw new RuntimeException('Template name is required.');
    }
    $params = [
        'name' => $name,
        'widget_type' => $widgetType,
        'payload_json' => cms_json_encode($payload),
        'created_by' => $adminId,
    ];
    if ($id > 0) {
        $params['id'] = $id;
        $statement = cms_db()->prepare('UPDATE cms_section_templates SET name = :name, widget_type = :widget_type, payload_json = :payload_json WHERE id = :id');
        $statement->execute($params);
        return $id;
    }
    $statement = cms_db()->prepare('INSERT INTO cms_section_templates (name, widget_type, payload_json, created_by) VALUES (:name, :widget_type, :payload_json, :created_by)');
    $statement->execute($params);
    return (int) cms_db()->lastInsertId();
}

function cms_delete_section_template(int $id): void
{
    if (!cms_table_exists('cms_section_templates')) {
        return;
    }
    $statement = cms_db()->prepare('DELETE FROM cms_section_templates WHERE id = :id');
    $statement->execute(['id' => $id]);
}

function cms_setting(string $key, mixed $default = null): mixed
{
    if (!cms_table_exists('cms_settings')) {
        return $default;
    }
    $statement = cms_db()->prepare('SELECT setting_value FROM cms_settings WHERE setting_key = :setting_key LIMIT 1');
    $statement->execute(['setting_key' => $key]);
    $value = $statement->fetchColumn();
    if ($value === false) {
        return $default;
    }
    if (is_array($default)) {
        return cms_json_decode_array((string) $value, $default);
    }
    return $value;
}

function cms_save_setting(string $key, string $value): void
{
    if (!cms_table_exists('cms_settings')) {
        throw new RuntimeException('Settings table is missing. Re-run the CMS schema.');
    }
    $statement = cms_db()->prepare(
        'INSERT INTO cms_settings (setting_key, setting_value) VALUES (:setting_key, :setting_value)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $statement->execute([
        'setting_key' => $key,
        'setting_value' => $value,
    ]);
}

function cms_blog_structure_template_payload(array $input): array
{
    return [
        'title' => trim((string) ($input['title'] ?? '')),
        'excerpt' => trim((string) ($input['excerpt'] ?? '')),
        'content_html' => (string) ($input['content_html'] ?? ''),
        'custom_html' => (string) ($input['custom_html'] ?? ''),
        'layout_mode' => cms_normalize_blog_layout_mode((string) ($input['layout_mode'] ?? 'standard')),
        'head_html' => (string) ($input['head_html'] ?? ''),
        'custom_css' => (string) ($input['custom_css'] ?? ''),
        'body_class' => trim((string) ($input['body_class'] ?? '')),
        'featured_image' => trim((string) ($input['featured_image'] ?? '')),
        'author_name' => trim((string) ($input['author_name'] ?? '')),
        'author_image' => trim((string) ($input['author_image'] ?? '')),
        'category' => trim((string) ($input['category'] ?? '')),
        'tags' => trim((string) ($input['tags'] ?? '')),
        'default_interlinks_enabled' => !empty($input['default_interlinks_enabled']) ? 1 : 0,
        'default_interlinks_html' => (string) ($input['default_interlinks_html'] ?? ''),
        'meta_title' => trim((string) ($input['meta_title'] ?? '')),
        'meta_description' => trim((string) ($input['meta_description'] ?? '')),
    ];
}

function cms_get_blog_structure_templates(): array
{
    $templates = cms_setting('blog_structure_templates', []);
    if (!is_array($templates)) {
        return [];
    }

    $normalized = [];
    foreach ($templates as $template) {
        if (!is_array($template)) {
            continue;
        }
        $key = trim((string) ($template['key'] ?? ''));
        $name = trim((string) ($template['name'] ?? ''));
        if ($key === '' || $name === '') {
            continue;
        }
        $normalized[] = [
            'key' => $key,
            'name' => $name,
            'payload' => cms_blog_structure_template_payload((array) ($template['payload'] ?? [])),
            'updated_at' => trim((string) ($template['updated_at'] ?? '')),
        ];
    }

    usort($normalized, static function (array $left, array $right): int {
        return strcmp((string) ($right['updated_at'] ?? ''), (string) ($left['updated_at'] ?? ''));
    });

    return $normalized;
}

function cms_save_blog_structure_template(string $name, array $input): string
{
    $name = trim($name);
    if ($name === '') {
        throw new RuntimeException('Template name is required.');
    }

    $key = cms_slugify($name);
    if ($key === '') {
        $key = 'blog-structure-' . substr(md5($name), 0, 8);
    }

    $templates = cms_get_blog_structure_templates();
    $payload = cms_blog_structure_template_payload($input);
    $saved = false;

    foreach ($templates as &$template) {
        if ((string) ($template['key'] ?? '') !== $key) {
            continue;
        }
        $template['name'] = $name;
        $template['payload'] = $payload;
        $template['updated_at'] = date('Y-m-d H:i:s');
        $saved = true;
        break;
    }
    unset($template);

    if (!$saved) {
        $templates[] = [
            'key' => $key,
            'name' => $name,
            'payload' => $payload,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    cms_save_setting('blog_structure_templates', cms_json_encode(array_values($templates)));
    return $key;
}

function cms_get_blog_structure_template(string $key): ?array
{
    foreach (cms_get_blog_structure_templates() as $template) {
        if ((string) ($template['key'] ?? '') === $key) {
            return $template;
        }
    }
    return null;
}

function cms_default_site_settings(): array
{
    return [
        'company_name' => 'A1 Technovation',
        'phone' => '+880 1799-976295',
        'email' => 'info.a1technovation@gmail.com',
        'whatsapp_url' => 'https://wa.me/8801799976295',
        'footer_text' => "Bangladesh's fastest-growing digital marketing agency.",
        'primary_cta_label' => 'Free Consultation',
        'primary_cta_url' => 'https://wa.me/8801799976295',
    ];
}

function cms_site_settings(): array
{
    return array_merge(cms_default_site_settings(), cms_setting('site_settings', cms_default_site_settings()));
}

function cms_save_site_settings(array $settings): void
{
    cms_save_setting('site_settings', cms_json_encode($settings));
}

function cms_editor_component_snippets(): array
{
    return [
        [
            'id' => 'cta_banner',
            'label' => 'CTA Banner',
            'description' => 'Gradient call-to-action section with two buttons.',
            'html' => <<<'HTML'
<div class="newsletter-card" style="margin:32px 0">
  <div class="newsletter-grid"></div>
  <div class="newsletter-content">
    <span class="label label-light label-dot">Need Faster Growth?</span>
    <h2 style="color:#fff;margin-bottom:16px">Turn this section into your strongest CTA</h2>
    <p style="color:rgba(255,255,255,.76);margin-bottom:28px">Replace this text with your offer, campaign message, or lead magnet so visitors know exactly what to do next.</p>
    <div class="cta-buttons">
      <a class="btn btn-secondary" href="https://wa.me/8801799976295">Start on WhatsApp</a>
      <a class="btn btn-ghost" href="/pages/contact">Contact Us</a>
    </div>
  </div>
</div>
HTML,
        ],
        [
            'id' => 'feature_split',
            'label' => 'Feature Split',
            'description' => 'Two-column feature panel with image, copy, bullets, and CTA.',
            'html' => <<<'HTML'
<div class="featured-panel featured-panel-flip" style="margin:32px 0">
  <div class="featured-panel-media">
    <img src="/assets/images/SEO.webp" alt="Replace with your featured visual" />
  </div>
  <div class="featured-panel-copy">
    <span class="label label-primary label-dot">Featured Section</span>
    <h2>Show one strong message with supporting proof</h2>
    <p>Use this block to combine one image, one clear promise, and a short explanation that moves readers toward your offer.</p>
    <ul class="info-list">
      <li>Replace this bullet with a meaningful benefit.</li>
      <li>Add a second proof point or deliverable.</li>
      <li>Finish with a direct next step.</li>
    </ul>
    <a class="btn btn-primary" href="/pages/contact">Talk to Our Team</a>
  </div>
</div>
HTML,
        ],
        [
            'id' => 'faq_block',
            'label' => 'FAQ Accordion',
            'description' => 'Interactive FAQ group that works with the existing site script.',
            'html' => <<<'HTML'
<div class="faq-list" style="margin:32px 0">
  <article class="faq-item open">
    <div class="faq-q"><span>What should this question say?</span><i class="fas fa-chevron-down"></i></div>
    <div class="faq-a"><p>Replace this answer with a clear and concise explanation.</p></div>
  </article>
  <article class="faq-item">
    <div class="faq-q"><span>How do I customize the second question?</span><i class="fas fa-chevron-down"></i></div>
    <div class="faq-a"><p>Use as many FAQ items as you need, and keep the answers short enough to scan quickly.</p></div>
  </article>
</div>
HTML,
        ],
        [
            'id' => 'map_embed',
            'label' => 'Map or Iframe',
            'description' => 'Responsive iframe shell for maps, forms, or external embeds.',
            'html' => <<<'HTML'
<div class="map-embed" style="margin:32px 0">
  <iframe
    src="https://www.google.com/maps?q=Dhaka&output=embed"
    loading="lazy"
    referrerpolicy="no-referrer-when-downgrade"
    allowfullscreen>
  </iframe>
</div>
HTML,
        ],
        [
            'id' => 'download_card',
            'label' => 'Download Card',
            'description' => 'Simple downloadable asset card for PDFs, guides, or checklists.',
            'html' => <<<'HTML'
<div class="info-card" style="margin:32px 0">
  <div class="info-card-icon"><i class="fas fa-file-arrow-down"></i></div>
  <div>
    <h4>Download the resource</h4>
    <p>Swap this copy for the guide, checklist, template, or case study you want visitors to download.</p>
    <a class="btn btn-primary" href="/assets/uploads/media/your-file.pdf" target="_blank" rel="noopener">Download PDF</a>
  </div>
</div>
HTML,
        ],
        [
            'id' => 'blog_toc_widget',
            'label' => 'Blog TOC Widget',
            'description' => 'Sidebar-style table of contents card for imported article layouts.',
            'html' => <<<'HTML'
<div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-sm">
  <h3 class="font-bold text-lg text-slate-900 mb-4">In this article</h3>
  <nav class="space-y-3 text-sm font-medium">
    <a href="#section-1" class="block text-slate-600 hover:text-blue-600 transition-colors">Section one</a>
    <a href="#section-2" class="block text-slate-600 hover:text-blue-600 transition-colors">Section two</a>
    <a href="#section-3" class="block text-slate-600 hover:text-blue-600 transition-colors">Section three</a>
  </nav>
</div>
HTML,
        ],
        [
            'id' => 'blog_newsletter_widget',
            'label' => 'Blog Newsletter Widget',
            'description' => 'Sidebar newsletter signup card wired to the working newsletter API.',
            'html' => <<<'HTML'
<div class="bg-slate-950 text-white rounded-2xl p-8 shadow-xl relative overflow-hidden">
  <div class="relative z-10">
    <h3 class="font-bold text-xl mb-2">A1 Growth Notes</h3>
    <p class="text-slate-300 text-sm mb-6 leading-relaxed">Get practical search and conversion ideas monthly.</p>
    <form class="newsletter-form space-y-3" data-source="blog-widget" action="/api/newsletter_subscribe" method="post">
      <input type="email" name="email" placeholder="Your work email" required class="w-full px-4 py-3 rounded-lg bg-slate-800 border border-slate-700 text-white placeholder-slate-400" />
      <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition-colors">Subscribe</button>
      <p class="newsletter-status" hidden aria-live="polite"></p>
    </form>
  </div>
</div>
HTML,
        ],
        [
            'id' => 'blog_further_reading',
            'label' => 'Further Reading Grid',
            'description' => 'Three-card internal link block for article endings.',
            'html' => <<<'HTML'
<div class="mt-16 pt-8 border-t border-slate-200">
  <h3 class="text-xl font-bold text-slate-900 mb-6">Further Reading</h3>
  <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    <a href="/pages/services-seo" class="bg-slate-50 border border-slate-200 p-4 rounded-lg hover:border-blue-600 transition-all flex items-center justify-between group">
      <span class="font-semibold text-slate-800 group-hover:text-blue-600 transition-colors text-sm">Related guide one</span>
      <span aria-hidden="true">→</span>
    </a>
    <a href="/pages/services-local-seo" class="bg-slate-50 border border-slate-200 p-4 rounded-lg hover:border-blue-600 transition-all flex items-center justify-between group">
      <span class="font-semibold text-slate-800 group-hover:text-blue-600 transition-colors text-sm">Related guide two</span>
      <span aria-hidden="true">→</span>
    </a>
    <a href="/pages/contact" class="bg-slate-50 border border-slate-200 p-4 rounded-lg hover:border-blue-600 transition-all flex items-center justify-between group">
      <span class="font-semibold text-slate-800 group-hover:text-blue-600 transition-colors text-sm">Talk to A1 Technovation</span>
      <span aria-hidden="true">→</span>
    </a>
  </div>
</div>
HTML,
        ],
        [
            'id' => 'blog_author_bar',
            'label' => 'Author Header Bar',
            'description' => 'Standalone author and update row styled like a long-form article intro.',
            'html' => <<<'HTML'
<div class="flex items-center gap-4 py-6 border-y border-slate-200">
  <div class="w-14 h-14 rounded-full bg-slate-200 overflow-hidden flex-shrink-0"></div>
  <div>
    <div class="font-bold text-slate-900 text-lg leading-tight">Author Name</div>
    <div class="text-sm text-slate-500 mt-1">Founder &amp; CEO, A1 Technovation • Updated Month Day, Year</div>
  </div>
</div>
HTML,
        ],
    ];
}

function cms_nav_service_pages(): array
{
    if (!cms_table_exists('cms_pages')) {
        return [];
    }
    $statement = cms_db()->query("SELECT title, slug, page_type FROM cms_pages WHERE status = 'published' AND (published_at IS NULL OR published_at <= NOW()) AND show_in_nav = 1 AND page_type = 'service' ORDER BY sort_order ASC, title ASC");
    return $statement->fetchAll() ?: [];
}

function cms_static_editable_roots(): array
{
    return [
        'pages',
        '',
    ];
}

function cms_normalize_static_file_path(string $relativePath): string
{
    $relativePath = trim(str_replace('\\', '/', $relativePath), '/');

    if ($relativePath === '' || str_contains($relativePath, '..')) {
        throw new RuntimeException('Invalid static page path.');
    }

    $allowedRootFile = in_array($relativePath, ['index.html', '404.html', 'sitemap.html'], true);
    $allowedPageFile = str_starts_with($relativePath, 'pages/') && str_ends_with($relativePath, '.html');

    if (!$allowedRootFile && !$allowedPageFile) {
        throw new RuntimeException('Only root HTML files and pages/*.html files can be edited here.');
    }

    $absoluteRoot = realpath(cms_root());
    $absolutePath = realpath(cms_root($relativePath));

    if ($absoluteRoot === false || $absolutePath === false || !str_starts_with($absolutePath, $absoluteRoot) || !is_file($absolutePath)) {
        throw new RuntimeException('The requested static page was not found.');
    }

    return $relativePath;
}

function cms_static_page_title(string $html, string $fallback): string
{
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $matches) === 1) {
        $title = trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES, 'UTF-8'));
        if ($title !== '') {
            return $title;
        }
    }

    return ucwords(str_replace('-', ' ', pathinfo($fallback, PATHINFO_FILENAME)));
}

function cms_get_static_pages_admin(): array
{
    $files = [];
    foreach (['index.html', '404.html', 'sitemap.html'] as $rootFile) {
        if (is_file(cms_root($rootFile))) {
            $files[] = $rootFile;
        }
    }

    foreach (glob(cms_root('pages/*.html')) ?: [] as $file) {
        $files[] = 'pages/' . basename((string) $file);
    }

    $rows = [];
    foreach (array_unique($files) as $relativePath) {
        $absolutePath = cms_root($relativePath);
        $html = is_file($absolutePath) ? (string) file_get_contents($absolutePath) : '';
        $rows[] = [
            'path' => $relativePath,
            'title' => cms_static_page_title($html, $relativePath),
            'url' => cms_url(str_ends_with($relativePath, '.html') ? preg_replace('/\.html$/', '', $relativePath) ?? $relativePath : $relativePath),
            'updated_at' => is_file($absolutePath) ? date('Y-m-d H:i:s', (int) filemtime($absolutePath)) : '',
            'size' => is_file($absolutePath) ? (int) filesize($absolutePath) : 0,
        ];
    }

    usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

    return $rows;
}

function cms_get_static_page_admin(string $relativePath): array
{
    $path = cms_normalize_static_file_path($relativePath);
    $absolutePath = cms_root($path);
    $html = (string) file_get_contents($absolutePath);

    return [
        'path' => $path,
        'title' => cms_static_page_title($html, $path),
        'content' => $html,
        'url' => cms_url(preg_replace('/\.html$/', '', $path) ?? $path),
        'updated_at' => date('Y-m-d H:i:s', (int) filemtime($absolutePath)),
        'size' => (int) filesize($absolutePath),
    ];
}

function cms_save_static_page_admin(string $relativePath, string $content): void
{
    $path = cms_normalize_static_file_path($relativePath);
    $absolutePath = cms_root($path);

    if (trim($content) === '') {
        throw new RuntimeException('Static page content cannot be empty.');
    }

    if (file_put_contents($absolutePath, $content, LOCK_EX) === false) {
        throw new RuntimeException('Unable to save the static page file.');
    }
}

function cms_static_page_meta_value(string $html, string $name, string $fallback = ''): string
{
    $quotedName = preg_quote($name, '#');
    $patterns = [
        '#<meta[^>]+name=["\']' . $quotedName . '["\'][^>]+content=["\']([^"\']*)["\'][^>]*>#i',
        '#<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . $quotedName . '["\'][^>]*>#i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches) === 1) {
            return trim(html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8'));
        }
    }

    return $fallback;
}

function cms_static_page_canonical(string $html, string $fallback = ''): string
{
    if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\'][^>]*>#i', $html, $matches) === 1
        || preg_match('#<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\'][^>]*>#i', $html, $matches) === 1) {
        return trim(html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8'));
    }

    return $fallback;
}

function cms_extract_head_inner_html(string $html): string
{
    if (preg_match('#<head[^>]*>(.*?)</head>#is', $html, $matches) === 1) {
        return trim((string) $matches[1]);
    }

    return '';
}

function cms_extract_body_class(string $html): string
{
    if (preg_match('#<body[^>]*class=(["\'])(.*?)\1#is', $html, $matches) === 1) {
        return trim(html_entity_decode((string) $matches[2], ENT_QUOTES, 'UTF-8'));
    }

    return '';
}

function cms_extract_first_image_src(string $html): string
{
    $patterns = [
        '#<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']#i',
        '#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']#i',
        '#<img[^>]+src=["\']([^"\']+)["\']#i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches) === 1) {
            return trim(html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8'));
        }
    }

    return '';
}

function cms_resolve_post_featured_image(array $post): string
{
    $featuredImage = trim((string) ($post['featured_image'] ?? ''));
    if ($featuredImage !== '') {
        return $featuredImage;
    }

    $slug = trim((string) ($post['slug'] ?? ''));
    if ($slug !== '') {
        foreach (cms_static_blog_sources() as $staticBlog) {
            if ((string) ($staticBlog['slug'] ?? '') === $slug) {
                $staticFeatured = trim((string) ($staticBlog['featured_image'] ?? ''));
                if ($staticFeatured !== '') {
                    return $staticFeatured;
                }
                break;
            }
        }
    }

    $contentImage = cms_extract_first_image_src((string) ($post['content_html'] ?? ''));
    if ($contentImage !== '') {
        return $contentImage;
    }

    $customImage = cms_extract_first_image_src((string) ($post['custom_html'] ?? ''));
    if ($customImage !== '') {
        return $customImage;
    }

    return '';
}

function cms_import_blog_head_assets(string $html): string
{
    $head = cms_extract_head_inner_html($html);
    if ($head === '') {
        return '';
    }

    $blocks = [];
    $patterns = [
        '#<link\b[^>]+rel=["\']preconnect["\'][^>]*>#is',
        '#<link\b[^>]+rel=["\']stylesheet["\'][^>]*>#is',
        '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>.*?</script>#is',
        '#<script\b[^>]*src=["\']https?://(?!www\.googletagmanager\.com)[^"\']+["\'][^>]*></script>#is',
    ];

    foreach ($patterns as $pattern) {
        $matches = [];
        if (preg_match_all($pattern, $head, $matches) > 0) {
            foreach ($matches[0] as $match) {
                $blocks[] = trim((string) $match);
            }
        }
    }

    $inlineConfigBlocks = [];
    $inlineOtherBlocks = [];
    if (preg_match_all('#<script\b(?![^>]*src=)[^>]*>.*?</script>#is', $head, $inlineScripts) === 1) {
        foreach ($inlineScripts[0] as $scriptBlock) {
            $trimmed = trim((string) $scriptBlock);
            if ($trimmed === '') {
                continue;
            }
            if (stripos($trimmed, 'googletagmanager.com') !== false
                || stripos($trimmed, 'window.dataLayer') !== false
                || stripos($trimmed, "gtag('config'") !== false) {
                continue;
            }
            if (stripos($trimmed, 'tailwind.config') !== false) {
                $inlineConfigBlocks[] = $trimmed;
            } else {
                $inlineOtherBlocks[] = $trimmed;
            }
        }
    }

    $orderedBlocks = array_merge($inlineConfigBlocks, $blocks, $inlineOtherBlocks);

    return implode("\n", array_values(array_unique(array_filter($orderedBlocks))));
}

function cms_imported_blog_fallback_css(): string
{
    return <<<'CSS'
body.bg-brand-light,
body .bg-brand-light { background:#f8fafc; }
body.font-sans { font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
.py-12 { padding-top:3rem; padding-bottom:3rem; }
.py-6 { padding-top:1.5rem; padding-bottom:1.5rem; }
.px-4 { padding-left:1rem; padding-right:1rem; }
.p-8 { padding:2rem; }
.mb-1 { margin-bottom:.25rem; }
.mb-2 { margin-bottom:.5rem; }
.mb-4 { margin-bottom:1rem; }
.mb-6 { margin-bottom:1.5rem; }
.mb-8 { margin-bottom:2rem; }
.mb-10 { margin-bottom:2.5rem; }
.mb-12 { margin-bottom:3rem; }
.mt-1 { margin-top:.25rem; }
.mt-2 { margin-top:.5rem; }
.my-10 { margin-top:2.5rem; margin-bottom:2.5rem; }
.mx-auto { margin-left:auto; margin-right:auto; }
.ml-auto { margin-left:auto; }
.max-w-none { max-width:none; }
.max-w-2xl { max-width:42rem; }
.max-w-4xl { max-width:56rem; }
.max-w-7xl { max-width:80rem; }
.w-full { width:100%; }
.h-auto { height:auto; }
.w-10 { width:2.5rem; }
.h-10 { height:2.5rem; }
.w-14 { width:3.5rem; }
.h-14 { height:3.5rem; }
.w-32 { width:8rem; }
.h-32 { height:8rem; }
.w-40 { width:10rem; }
.h-40 { height:10rem; }
.grid { display:grid; }
.flex { display:flex; }
.inline-block { display:inline-block; }
.block { display:block; }
.hidden { display:none; }
.items-center { align-items:center; }
.justify-center { justify-content:center; }
.text-center { text-align:center; }
.flex-shrink-0 { flex-shrink:0; }
.gap-1\.5 { gap:.375rem; }
.gap-2 { gap:.5rem; }
.gap-3 { gap:.75rem; }
.gap-4 { gap:1rem; }
.gap-12 { gap:3rem; }
.space-x-2 > * + * { margin-left:.5rem; }
.space-y-3 > * + * { margin-top:.75rem; }
.relative { position:relative; }
.absolute { position:absolute; }
.sticky { position:sticky; }
.top-24 { top:6rem; }
.right-6 { right:1.5rem; }
.bottom-6 { bottom:1.5rem; }
.overflow-hidden { overflow:hidden; }
.overflow-x-auto { overflow-x:auto; }
.z-10 { z-index:10; }
.rounded-lg { border-radius:.5rem; }
.rounded-xl { border-radius:.75rem; }
.rounded-2xl { border-radius:1rem; }
.rounded-full { border-radius:9999px; }
.border { border-width:1px; border-style:solid; }
.border-0 { border:0; }
.border-y { border-top:1px solid; border-bottom:1px solid; }
.border-slate-200,
.border-brand-border { border-color:#e2e8f0; }
.bg-white { background:#fff; }
.bg-slate-50 { background:#f8fafc; }
.bg-slate-100 { background:#f1f5f9; }
.bg-blue-50 { background:#eff6ff; }
.bg-blue-100 { background:#dbeafe; }
.bg-brand-dark { background:#0f172a; }
.bg-brand-primary { background:#2563eb; }
.text-white { color:#fff; }
.\!text-white { color:#fff !important; }
.text-slate-500 { color:#64748b; }
.text-slate-600 { color:#475569; }
.text-slate-700 { color:#334155; }
.text-slate-800 { color:#1e293b; }
.text-slate-900 { color:#0f172a; }
.text-brand-primary { color:#2563eb; }
.text-brand-dark { color:#0f172a; }
.text-xs { font-size:.75rem; line-height:1rem; }
.text-sm { font-size:.875rem; line-height:1.25rem; }
.text-lg { font-size:1.125rem; line-height:1.75rem; }
.text-xl { font-size:1.25rem; line-height:1.75rem; }
.text-2xl { font-size:1.5rem; line-height:2rem; }
.text-3xl { font-size:1.875rem; line-height:2.25rem; }
.text-4xl { font-size:2.25rem; line-height:2.5rem; }
.font-medium { font-weight:500; }
.font-semibold { font-weight:600; }
.font-bold { font-weight:700; }
.font-extrabold { font-weight:800; }
.uppercase { text-transform:uppercase; }
.tracking-tight { letter-spacing:-.025em; }
.tracking-wider { letter-spacing:.05em; }
.leading-tight { line-height:1.25; }
.leading-relaxed { line-height:1.625; }
.no-underline { text-decoration:none; }
.underline { text-decoration:underline; }
.object-cover { object-fit:cover; }
.shadow-xl { box-shadow:0 20px 25px -5px rgba(15,23,42,.12),0 8px 10px -6px rgba(15,23,42,.08); }
.transition-colors { transition:color .2s ease, background-color .2s ease, border-color .2s ease; }
.prose { color:#475569; }
.prose p { font-size:1.125rem; line-height:1.8; margin:0 0 1.75rem; color:#475569; }
.prose h2 { font-size:1.875rem; line-height:1.3; font-weight:800; color:#0f172a; margin:3.5rem 0 1.25rem; letter-spacing:-.025em; }
.prose h3 { font-size:1.4rem; line-height:1.35; font-weight:700; color:#1e293b; margin:2.5rem 0 1rem; }
.prose h4 { font-size:1.15rem; line-height:1.45; font-weight:600; color:#334155; margin:2rem 0 .75rem; }
.prose a { color:#2563eb; text-decoration:none; font-weight:600; border-bottom:1px dashed #2563eb; }
.prose ul,
.prose ol { padding-left:1.5rem; margin:0 0 2rem; color:#475569; font-size:1.125rem; line-height:1.8; }
.prose ul { list-style:disc; }
.prose ol { list-style:decimal; }
.prose li { margin-bottom:.75rem; }
.prose table { width:100%; border-collapse:collapse; margin:2.5rem 0; font-size:1rem; border:1px solid #e2e8f0; border-radius:.75rem; overflow:hidden; }
.prose th,
.prose td { padding:1.15rem 1.5rem; border-bottom:1px solid #e2e8f0; }
.prose thead tr { background:#0f172a; color:#fff; text-align:left; }
.visual-container { width:100%; margin:3rem 0; border-radius:1.25rem; overflow:hidden; background:#f8fafc; border:1px solid #e2e8f0; box-shadow:0 10px 15px -3px rgba(15,23,42,.08); }
.tag-critical,.tag-high,.tag-medium,.tag-strategic { padding:.1rem .5rem; border-radius:.25rem; font-weight:600; font-size:.875rem; }
.tag-critical { background:#fee2e2; color:#dc2626; border:1px solid #fca5a5; }
.tag-high { background:#ffedd5; color:#ea580c; border:1px solid #fdba74; }
.tag-medium { background:#fef9c3; color:#ca8a04; border:1px solid #fde047; }
.tag-strategic { background:#dcfce7; color:#16a34a; border:1px solid #86efac; }
@media (min-width:640px) {
  .sm\:px-6 { padding-left:1.5rem; padding-right:1.5rem; }
  .sm\:text-5xl { font-size:3rem; line-height:1; }
}
@media (min-width:768px) {
  .md\:block { display:block; }
  .md\:grid-cols-2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
  .md\:grid-cols-4 { grid-template-columns:repeat(4,minmax(0,1fr)); }
}
@media (min-width:1024px) {
  .lg\:py-24 { padding-top:6rem; padding-bottom:6rem; }
  .lg\:px-8 { padding-left:2rem; padding-right:2rem; }
  .lg\:grid-cols-12 { grid-template-columns:repeat(12,minmax(0,1fr)); }
  .lg\:col-span-4 { grid-column:span 4/span 4; }
  .lg\:col-span-8 { grid-column:span 8/span 8; }
  .lg\:block { display:block; }
  .lg\:text-6xl { font-size:3.75rem; line-height:1; }
}
@media (min-width:1280px) {
  .xl\:gap-16 { gap:4rem; }
}
CSS;
}

function cms_import_blog_custom_css(string $html): string
{
    if (preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $html, $matches) !== 1) {
        return '';
    }

    $styles = array_map(
        static fn (string $value): string => trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')),
        $matches[1]
    );

    return trim(implode("\n\n", array_filter($styles)));
}

function cms_import_inline_script_code(string $html): string
{
    if (preg_match_all('#<script\b(?![^>]*src=)(?![^>]*type=["\']application/ld\+json["\'])[^>]*>(.*?)</script>#is', $html, $matches) !== 1) {
        return '';
    }

    $scripts = array_map(
        static fn (string $value): string => trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')),
        $matches[1]
    );

    return trim(implode("\n\n", array_filter($scripts)));
}

function cms_strip_style_and_script_blocks(string $html): string
{
    $patterns = [
        '#<style\b[^>]*>.*?</style>#is',
        '#<script\b[^>]*>.*?</script>#is',
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace($pattern, '', $html) ?? $html;
    }

    return $html;
}

function cms_prepare_imported_newsletter_forms(string $html): string
{
    return preg_replace_callback('#<form\b([^>]*)>(.*?)</form>#is', static function (array $matches): string {
        $attrs = (string) $matches[1];
        $inner = (string) $matches[2];

        if (!preg_match('#<input[^>]+type=["\']email["\']#i', $inner)) {
            return $matches[0];
        }

        if (preg_match('#\bclass=(["\'])(.*?)\1#i', $attrs, $classMatch) === 1) {
            $classNames = trim((string) $classMatch[2]);
            if (!preg_match('/(^|\s)newsletter-form(\s|$)/', $classNames)) {
                $replacement = trim($classNames . ' newsletter-form');
                $attrs = (string) preg_replace(
                    '#\bclass=(["\'])(.*?)\1#i',
                    'class="' . cms_e($replacement) . '"',
                    $attrs,
                    1
                );
            }
        } else {
            $attrs .= ' class="newsletter-form"';
        }

        if (stripos($attrs, 'data-source=') === false) {
            $attrs .= ' data-source="custom-blog"';
        }
        if (stripos($attrs, 'method=') === false) {
            $attrs .= ' method="post"';
        }
        if (stripos($attrs, 'action=') === false) {
            $attrs .= ' action="/api/newsletter_subscribe"';
        }

        $emailPatched = false;
        $inner = preg_replace_callback('#<input\b([^>]*)>#i', static function (array $inputMatch) use (&$emailPatched): string {
            $attrs = (string) $inputMatch[1];

            if ($emailPatched || !preg_match('#\btype=["\']email["\']#i', $attrs)) {
                return $inputMatch[0];
            }

            $emailPatched = true;
            if (!preg_match('#\bname=["\']#i', $attrs)) {
                $attrs .= ' name="email"';
            }

            return '<input' . $attrs . '>';
        }, $inner) ?? $inner;

        if (stripos($inner, 'newsletter-status') === false) {
            $inner .= '<p class="newsletter-status" hidden aria-live="polite"></p>';
        }

        return '<form' . $attrs . '>' . $inner . '</form>';
    }, $html) ?? $html;
}

function cms_prepare_imported_copy_actions(string $html): string
{
    return preg_replace(
        '#\sonclick=(["\']).*?navigator\.clipboard.*?\1#is',
        ' data-copy-link="current"',
        $html
    ) ?? $html;
}

function cms_normalize_imported_site_paths(string $html): string
{
    $patterns = [
        '#(["\'])(?:\.\./)+(assets|css|js)/([^"\']+)#i',
        '#(["\'])(assets|css|js)/([^"\']+)#i',
    ];

    foreach ($patterns as $pattern) {
        $html = preg_replace_callback($pattern, static function (array $matches): string {
            return $matches[1] . '/' . strtolower((string) $matches[2]) . '/' . ltrim((string) $matches[3], '/');
        }, $html) ?? $html;
    }

    return $html;
}

function cms_extract_imported_blog_content(string $html): string
{
    if (preg_match('#<body\b[^>]*>(.*)</body>#is', $html, $matches) === 1) {
        return trim((string) $matches[1]);
    }

    if (preg_match('#(<main\b[^>]*>.*?</main>)#is', $html, $matches) === 1) {
        return trim((string) $matches[1]);
    }

    return trim($html);
}

function cms_repair_imported_blog_head_html(string $headHtml): string
{
    $headHtml = trim($headHtml);
    if ($headHtml === '' || stripos($headHtml, 'tailwind.config') === false) {
        return $headHtml;
    }

    if (preg_match('#<script\b(?![^>]*src=)[^>]*>.*?tailwind\.config.*?</script>#is', $headHtml, $matches) !== 1) {
        return $headHtml;
    }

    $configScript = trim((string) $matches[0]);
    $withoutConfig = trim(str_replace((string) $matches[0], '', $headHtml));

    return trim($configScript . "\n" . $withoutConfig);
}

function cms_import_blog_html_document(string $html, string $fallbackTitle = 'Imported Blog Post'): array
{
    $html = trim($html);
    if ($html === '') {
        throw new RuntimeException('Paste a full HTML document or provide a valid source path first.');
    }

    $title = cms_static_page_title($html, $fallbackTitle);
    $description = cms_static_page_meta_value($html, 'description');
    $contentHtml = cms_extract_imported_blog_content($html);
    $contentHtml = cms_normalize_imported_site_paths($contentHtml);
    $contentHtml = cms_prepare_imported_copy_actions($contentHtml);
    $contentHtml = cms_prepare_imported_newsletter_forms($contentHtml);

    $featuredImage = cms_extract_first_image_src($html);
    $excerpt = $description !== '' ? $description : cms_excerpt_from_html($contentHtml, 190);

    return [
        'title' => $title,
        'excerpt' => $excerpt,
        'content_html' => $contentHtml,
        'featured_image' => $featuredImage,
        'meta_title' => $title,
        'meta_description' => $description !== '' ? $description : $excerpt,
        'layout_mode' => 'custom_import',
        'head_html' => cms_normalize_imported_site_paths(cms_import_blog_head_assets($html)),
        'custom_css' => cms_import_blog_custom_css($html),
        'body_class' => cms_extract_body_class($html),
    ];
}

function cms_import_page_html_document(string $html, string $fallbackTitle = 'Imported Page'): array
{
    $html = trim($html);
    if ($html === '') {
        throw new RuntimeException('Paste a full HTML document or provide a valid source path first.');
    }

    $title = cms_static_page_title($html, $fallbackTitle);
    $description = cms_static_page_meta_value($html, 'description');
    $contentHtml = cms_extract_imported_blog_content($html);
    $contentHtml = cms_strip_style_and_script_blocks($contentHtml);
    $contentHtml = cms_normalize_imported_site_paths($contentHtml);
    $contentHtml = cms_prepare_imported_copy_actions($contentHtml);
    $contentHtml = cms_prepare_imported_newsletter_forms($contentHtml);

    $customCss = cms_import_blog_custom_css($html);
    $customJs = cms_import_inline_script_code($html);
    $featuredImage = cms_extract_first_image_src($html);
    $excerpt = $description !== '' ? $description : cms_excerpt_from_html($contentHtml, 190);
    $schemaJson = '';

    if (preg_match('#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $schemaMatch) === 1) {
        $schemaJson = trim((string) $schemaMatch[1]);
    }

    return [
        'title' => $title,
        'excerpt' => $excerpt,
        'custom_html' => "<!-- cms:imported-static-design:start -->\n" . trim($contentHtml) . "\n<!-- cms:imported-static-design:end -->",
        'custom_css' => $customCss,
        'custom_js' => $customJs,
        'featured_image' => $featuredImage,
        'meta_title' => $title,
        'meta_description' => $description !== '' ? $description : $excerpt,
        'schema_json' => $schemaJson,
        'template_key' => 'standard',
    ];
}

function cms_publish_static_html_as_blog(array $input, int $adminId): int
{
    $sourcePath = trim((string) ($input['source_path'] ?? ''));
    $sourceHtml = trim((string) ($input['html_code'] ?? ''));

    if ($sourceHtml === '') {
        if ($sourcePath === '') {
            throw new RuntimeException('Paste full HTML code or choose an existing static page to publish.');
        }

        $normalizedPath = cms_normalize_static_file_path($sourcePath);
        $absolutePath = cms_root($normalizedPath);
        if (!is_file($absolutePath)) {
            throw new RuntimeException('The selected static page could not be found.');
        }

        $sourceHtml = (string) file_get_contents($absolutePath);
    }

    $titleOverride = trim((string) ($input['title'] ?? ''));
    $importedPost = cms_import_blog_html_document(
        $sourceHtml,
        $titleOverride !== '' ? $titleOverride : ($sourcePath !== '' ? $sourcePath : 'Imported Blog Post')
    );
    $separateCss = trim((string) ($input['css_code'] ?? ''));
    if ($separateCss !== '') {
        $importedPost['custom_css'] = trim((string) ($importedPost['custom_css'] ?? '') . "\n\n" . $separateCss);
    }

    $postInput = array_merge($importedPost, [
        'id' => 0,
        'title' => $titleOverride !== '' ? $titleOverride : (string) ($importedPost['title'] ?? 'Imported Blog Post'),
        'slug' => trim((string) ($input['slug'] ?? '')),
        'status' => (string) ($input['status'] ?? 'published'),
        'author_name' => trim((string) ($input['author_name'] ?? '')),
        'author_image' => trim((string) ($input['author_image'] ?? '')),
        'category' => trim((string) ($input['category'] ?? '')),
        'tags' => trim((string) ($input['tags'] ?? '')),
        'default_interlinks_enabled' => !empty($input['default_interlinks_enabled']) ? '1' : '0',
        'default_interlinks_html' => (string) ($input['default_interlinks_html'] ?? ''),
        'published_at' => (string) ($input['published_at'] ?? ''),
    ]);

    if ($postInput['author_name'] === '') {
        $postInput['author_name'] = (string) cms_config('app.default_author', 'A1 Technovation');
    }

    $postId = cms_save_post($postInput, $adminId);
    cms_write_sitemap();

    return $postId;
}

function cms_upsert_head_tag(string $html, string $pattern, string $replacement): string
{
    if (preg_match($pattern, $html) === 1) {
        return (string) preg_replace_callback($pattern, static fn (): string => $replacement, $html, 1);
    }

    if (preg_match('#</head>#i', $html) === 1) {
        return (string) preg_replace_callback('#</head>#i', static fn (): string => '  ' . $replacement . "\n</head>", $html, 1);
    }

    return $html . "\n" . $replacement . "\n";
}

function cms_apply_static_page_seo(string $relativePath, string $metaTitle, string $metaDescription, string $canonicalUrl): void
{
    $path = cms_normalize_static_file_path($relativePath);
    $absolutePath = cms_root($path);
    $html = (string) file_get_contents($absolutePath);

    if ($metaTitle !== '') {
        $titleTag = '<title>' . htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') . '</title>';
        $html = cms_upsert_head_tag($html, '#<title[^>]*>.*?</title>#is', $titleTag);
        $html = cms_upsert_head_tag($html, '#<meta[^>]+property=["\']og:title["\'][^>]*>#i', '<meta property="og:title" content="' . htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') . '" />');
        $html = cms_upsert_head_tag($html, '#<meta[^>]+name=["\']twitter:title["\'][^>]*>#i', '<meta name="twitter:title" content="' . htmlspecialchars($metaTitle, ENT_QUOTES, 'UTF-8') . '" />');
    }

    if ($metaDescription !== '') {
        $escapedDescription = htmlspecialchars($metaDescription, ENT_QUOTES, 'UTF-8');
        $html = cms_upsert_head_tag($html, '#<meta[^>]+name=["\']description["\'][^>]*>#i', '<meta name="description" content="' . $escapedDescription . '" />');
        $html = cms_upsert_head_tag($html, '#<meta[^>]+property=["\']og:description["\'][^>]*>#i', '<meta property="og:description" content="' . $escapedDescription . '" />');
        $html = cms_upsert_head_tag($html, '#<meta[^>]+name=["\']twitter:description["\'][^>]*>#i', '<meta name="twitter:description" content="' . $escapedDescription . '" />');
    }

    if ($canonicalUrl !== '') {
        $escapedCanonical = htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8');
        $html = cms_upsert_head_tag($html, '#<link[^>]+rel=["\']canonical["\'][^>]*>#i', '<link rel="canonical" href="' . $escapedCanonical . '" />');
        $html = cms_upsert_head_tag($html, '#<meta[^>]+property=["\']og:url["\'][^>]*>#i', '<meta property="og:url" content="' . $escapedCanonical . '" />');
    }

    cms_save_static_page_admin($path, $html);
}

// ─── Role helpers ────────────────────────────────────────────────────────────

function cms_roles(): array
{
    return [
        'super_admin' => 'Super Admin',
        'editor'      => 'Editor',
        'author'      => 'Author',
        'viewer'      => 'Viewer',
    ];
}

function cms_current_admin_role(): string
{
    $admin = cms_current_admin();
    return (string) ($admin['role'] ?? 'super_admin');
}

function cms_current_admin_has_role(string $minRole): bool
{
    $hierarchy = ['viewer' => 0, 'author' => 1, 'editor' => 2, 'super_admin' => 3];
    $current = $hierarchy[cms_current_admin_role()] ?? 0;
    $required = $hierarchy[$minRole] ?? 0;
    return $current >= $required;
}

function cms_require_role(string $minRole): void
{
    if (!cms_current_admin_has_role($minRole)) {
        cms_flash_set('error', 'You do not have permission to perform that action.');
        cms_redirect('admin/dashboard.php');
    }
}

function cms_update_admin_role(int $adminId, string $role): void
{
    if (!array_key_exists($role, cms_roles())) {
        throw new RuntimeException('Invalid role selected.');
    }
    if (!cms_column_exists('admin_users', 'role')) {
        cms_db()->exec("ALTER TABLE admin_users ADD COLUMN role ENUM('super_admin','editor','author','viewer') NOT NULL DEFAULT 'super_admin'");
    }
    $stmt = cms_db()->prepare('UPDATE admin_users SET role = :role WHERE id = :id');
    $stmt->execute(['role' => $role, 'id' => $adminId]);
}

// ─── Activity log ─────────────────────────────────────────────────────────────

function cms_log_activity(string $action, string $entityType = '', int $entityId = 0, string $entityLabel = ''): void
{
    if (!cms_table_exists('cms_activity_log')) {
        return;
    }
    $admin = cms_current_admin();
    $stmt = cms_db()->prepare(
        'INSERT INTO cms_activity_log (admin_id, admin_name, action, entity_type, entity_id, entity_label, ip_address)
         VALUES (:admin_id, :admin_name, :action, :entity_type, :entity_id, :entity_label, :ip)'
    );
    $stmt->execute([
        'admin_id'     => $admin['id'] ?? null,
        'admin_name'   => $admin['full_name'] ?? 'System',
        'action'       => $action,
        'entity_type'  => $entityType,
        'entity_id'    => $entityId > 0 ? $entityId : null,
        'entity_label' => $entityLabel,
        'ip'           => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);
}

function cms_get_activity_log(int $limit = 50, int $offset = 0): array
{
    if (!cms_table_exists('cms_activity_log')) {
        return [];
    }
    $limit = max(1, min($limit, 200));
    $stmt = cms_db()->prepare(
        'SELECT * FROM cms_activity_log ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function cms_activity_log_count(): int
{
    if (!cms_table_exists('cms_activity_log')) {
        return 0;
    }
    return (int) cms_db()->query('SELECT COUNT(*) FROM cms_activity_log')->fetchColumn();
}

function cms_clear_activity_log(): void
{
    if (!cms_table_exists('cms_activity_log')) {
        return;
    }
    cms_db()->exec('DELETE FROM cms_activity_log');
}

// ─── Revision history ─────────────────────────────────────────────────────────

function cms_save_revision(string $contentType, int $contentId, array $snapshot, int $adminId): void
{
    if (!cms_table_exists('cms_revisions')) {
        return;
    }
    $stmt = cms_db()->prepare(
        'INSERT INTO cms_revisions (content_type, content_id, snapshot_json, created_by)
         VALUES (:type, :id, :snap, :by)'
    );
    $stmt->execute([
        'type' => $contentType,
        'id'   => $contentId,
        'snap' => cms_json_encode($snapshot),
        'by'   => $adminId,
    ]);
    // Keep only last 20 revisions per content item to stay lean
    $stmt2 = cms_db()->prepare(
        'DELETE FROM cms_revisions WHERE content_type = :type AND content_id = :id
         AND id NOT IN (SELECT id FROM (SELECT id FROM cms_revisions WHERE content_type = :type2 AND content_id = :id2 ORDER BY created_at DESC LIMIT 20) AS keep)'
    );
    $stmt2->execute(['type' => $contentType, 'id' => $contentId, 'type2' => $contentType, 'id2' => $contentId]);
}

function cms_get_revisions(string $contentType, int $contentId): array
{
    if (!cms_table_exists('cms_revisions')) {
        return [];
    }
    $stmt = cms_db()->prepare(
        'SELECT r.*, u.full_name AS editor_name
         FROM cms_revisions r
         LEFT JOIN admin_users u ON u.id = r.created_by
         WHERE r.content_type = :type AND r.content_id = :id
         ORDER BY r.created_at DESC LIMIT 20'
    );
    $stmt->execute(['type' => $contentType, 'id' => $contentId]);
    return $stmt->fetchAll() ?: [];
}

function cms_get_revision(int $id): ?array
{
    if (!cms_table_exists('cms_revisions')) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM cms_revisions WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

// ─── Bulk actions ─────────────────────────────────────────────────────────────

function cms_bulk_action_posts(string $action, array $ids, int $adminId): int
{
    if (!cms_table_exists('blog_posts') || $ids === []) {
        return 0;
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $count = 0;

    if ($action === 'delete') {
        $stmt = cms_db()->prepare("DELETE FROM blog_posts WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $count = $stmt->rowCount();
        cms_log_activity('bulk_delete', 'post', 0, "Deleted $count posts");
    } elseif (in_array($action, ['publish', 'draft'], true)) {
        $status = $action === 'publish' ? 'published' : 'draft';
        $publishedAt = $status === 'published' ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : null;
        $params = array_merge([$status, $adminId, $publishedAt], $ids);
        $stmt = cms_db()->prepare(
            "UPDATE blog_posts SET status = ?, updated_by = ?, published_at = COALESCE(published_at, ?) WHERE id IN ($placeholders)"
        );
        $stmt->execute($params);
        $count = $stmt->rowCount();
        cms_log_activity("bulk_$action", 'post', 0, "Set $count posts to $status");
    }

    return $count;
}

function cms_bulk_action_pages(string $action, array $ids, int $adminId): int
{
    if (!cms_table_exists('cms_pages') || $ids === []) {
        return 0;
    }
    $ids = array_map('intval', $ids);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $count = 0;

    if ($action === 'delete') {
        $stmt = cms_db()->prepare("DELETE FROM cms_pages WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $count = $stmt->rowCount();
        cms_log_activity('bulk_delete', 'page', 0, "Deleted $count pages");
    } elseif (in_array($action, ['publish', 'draft'], true)) {
        $status = $action === 'publish' ? 'published' : 'draft';
        $publishedAt = $status === 'published' ? (new DateTimeImmutable())->format('Y-m-d H:i:s') : null;
        $params = array_merge([$status, $adminId, $publishedAt], $ids);
        $stmt = cms_db()->prepare(
            "UPDATE cms_pages SET status = ?, updated_by = ?, published_at = COALESCE(published_at, ?) WHERE id IN ($placeholders)"
        );
        $stmt->execute($params);
        $count = $stmt->rowCount();
        cms_log_activity("bulk_$action", 'page', 0, "Set $count pages to $status");
    }

    return $count;
}

// ─── Form builder ─────────────────────────────────────────────────────────────

function cms_ensure_forms_schema(): void
{
    if (!cms_table_exists('cms_forms')) {
        cms_db()->exec(
            "CREATE TABLE IF NOT EXISTS cms_forms (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(190) NOT NULL,
                slug VARCHAR(190) NOT NULL UNIQUE,
                fields_json LONGTEXT NOT NULL,
                success_message TEXT NULL,
                notify_email VARCHAR(190) NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )"
        );
    }

    $formColumns = [
        'slug' => "ALTER TABLE cms_forms ADD COLUMN slug VARCHAR(190) NULL AFTER name",
        'fields_json' => "ALTER TABLE cms_forms ADD COLUMN fields_json LONGTEXT NULL AFTER slug",
        'success_message' => "ALTER TABLE cms_forms ADD COLUMN success_message TEXT NULL AFTER fields_json",
        'notify_email' => "ALTER TABLE cms_forms ADD COLUMN notify_email VARCHAR(190) NULL AFTER success_message",
        'status' => "ALTER TABLE cms_forms ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active' AFTER notify_email",
        'created_by' => "ALTER TABLE cms_forms ADD COLUMN created_by INT UNSIGNED NULL AFTER status",
        'created_at' => "ALTER TABLE cms_forms ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by",
        'updated_at' => "ALTER TABLE cms_forms ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
    ];

    foreach ($formColumns as $column => $sql) {
        if (!cms_column_exists('cms_forms', $column)) {
            cms_db()->exec($sql);
        }
    }

    if (cms_column_exists('cms_forms', 'slug')) {
        $rows = cms_db()->query("SELECT id, name, slug FROM cms_forms WHERE slug IS NULL OR slug = ''")->fetchAll() ?: [];
        foreach ($rows as $row) {
            $slug = cms_unique_form_slug((string) ($row['name'] ?? 'form'), (int) ($row['id'] ?? 0));
            $statement = cms_db()->prepare('UPDATE cms_forms SET slug = :slug WHERE id = :id');
            $statement->execute(['slug' => $slug, 'id' => (int) $row['id']]);
        }
    }

    if (!cms_table_exists('cms_form_submissions')) {
        cms_db()->exec(
            "CREATE TABLE IF NOT EXISTS cms_form_submissions (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                form_id INT UNSIGNED NOT NULL,
                data_json LONGTEXT NOT NULL,
                is_read TINYINT(1) NOT NULL DEFAULT 0,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    $submissionColumns = [
        'form_id' => "ALTER TABLE cms_form_submissions ADD COLUMN form_id INT UNSIGNED NULL AFTER id",
        'data_json' => "ALTER TABLE cms_form_submissions ADD COLUMN data_json LONGTEXT NULL AFTER form_id",
        'is_read' => "ALTER TABLE cms_form_submissions ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER data_json",
        'ip_address' => "ALTER TABLE cms_form_submissions ADD COLUMN ip_address VARCHAR(45) NOT NULL DEFAULT '' AFTER is_read",
        'created_at' => "ALTER TABLE cms_form_submissions ADD COLUMN created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ip_address",
    ];

    foreach ($submissionColumns as $column => $sql) {
        if (!cms_column_exists('cms_form_submissions', $column)) {
            cms_db()->exec($sql);
        }
    }
}

function cms_unique_form_slug(string $name, ?int $ignoreId = null): string
{
    $base = cms_make_slug($name);
    $slug = $base;
    $suffix = 2;

    while (cms_table_exists('cms_forms')) {
        $sql = 'SELECT COUNT(*) FROM cms_forms WHERE slug = :slug';
        $params = ['slug' => $slug];
        if ($ignoreId !== null && $ignoreId > 0) {
            $sql .= ' AND id != :id';
            $params['id'] = $ignoreId;
        }

        $statement = cms_db()->prepare($sql);
        $statement->execute($params);
        if ((int) $statement->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $suffix;
        $suffix++;
    }

    return $slug;
}

function cms_get_forms_admin(): array
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_forms')) {
        return [];
    }
    // Use simple counts only if the submissions table exists
    if (cms_table_exists('cms_form_submissions')) {
        return cms_db()->query(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM cms_form_submissions s WHERE s.form_id = f.id) AS submission_count,
                    (SELECT COUNT(*) FROM cms_form_submissions s WHERE s.form_id = f.id AND s.is_read = 0) AS unread_count
             FROM cms_forms f ORDER BY f.updated_at DESC'
        )->fetchAll() ?: [];
    }
    return cms_db()->query(
        'SELECT f.*, 0 AS submission_count, 0 AS unread_count FROM cms_forms f ORDER BY f.updated_at DESC'
    )->fetchAll() ?: [];
}

function cms_get_form(int $id): ?array
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_forms')) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM cms_forms WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $row['fields'] = cms_json_decode_array((string) ($row['fields_json'] ?? ''), []);
    return $row;
}

function cms_save_form(array $input, int $adminId): int
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_forms')) {
        throw new RuntimeException('Forms table is missing. Re-run the CMS schema.');
    }
    $id = max(0, (int) ($input['id'] ?? 0));
    $name = trim((string) ($input['name'] ?? ''));
    if ($name === '') {
        throw new RuntimeException('Form name is required.');
    }
    $slug = cms_unique_form_slug($name, $id > 0 ? $id : null);
    $fieldsJson = cms_json_encode(cms_json_decode_array((string) ($input['fields_json'] ?? ''), []));
    $successMessage = trim((string) ($input['success_message'] ?? 'Thank you! Your message has been sent.'));
    $notifyEmail = trim((string) ($input['notify_email'] ?? ''));
    $status = in_array((string) ($input['status'] ?? 'active'), ['active', 'inactive'], true) ? (string) $input['status'] : 'active';

    if ($id > 0) {
        $stmt = cms_db()->prepare(
            'UPDATE cms_forms SET name=:name, fields_json=:fields_json, success_message=:sm, notify_email=:ne, status=:status WHERE id=:id'
        );
        $stmt->execute(['name' => $name, 'fields_json' => $fieldsJson, 'sm' => $successMessage, 'ne' => $notifyEmail, 'status' => $status, 'id' => $id]);
        cms_log_activity('update_form', 'form', $id, $name);
        return $id;
    }

    $stmt = cms_db()->prepare(
        'INSERT INTO cms_forms (name, slug, fields_json, success_message, notify_email, status, created_by)
         VALUES (:name, :slug, :fields_json, :sm, :ne, :status, :by)'
    );
    $stmt->execute(['name' => $name, 'slug' => $slug, 'fields_json' => $fieldsJson, 'sm' => $successMessage, 'ne' => $notifyEmail, 'status' => $status, 'by' => $adminId]);
    $newId = (int) cms_db()->lastInsertId();
    cms_log_activity('create_form', 'form', $newId, $name);
    return $newId;
}

function cms_delete_form(int $id): void
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_forms')) {
        return;
    }
    $form = cms_get_form($id);
    $stmt = cms_db()->prepare('DELETE FROM cms_forms WHERE id = :id');
    $stmt->execute(['id' => $id]);
    cms_log_activity('delete_form', 'form', $id, (string) ($form['name'] ?? ''));
}

function cms_get_form_submissions(int $formId, int $limit = 50, int $offset = 0): array
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_form_submissions')) {
        return [];
    }
    $stmt = cms_db()->prepare(
        'SELECT * FROM cms_form_submissions WHERE form_id = :form_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue(':form_id', $formId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function cms_mark_submissions_read(int $formId): void
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_form_submissions')) {
        return;
    }
    $stmt = cms_db()->prepare('UPDATE cms_form_submissions SET is_read = 1 WHERE form_id = :form_id');
    $stmt->execute(['form_id' => $formId]);
}

function cms_delete_submission(int $id): void
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_form_submissions')) {
        return;
    }
    $stmt = cms_db()->prepare('DELETE FROM cms_form_submissions WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function cms_export_form_submissions_csv(int $formId = 0, array $ids = []): string
{
    cms_ensure_forms_schema();

    if (!cms_table_exists('cms_form_submissions')) {
        return "id,form_id,created_at,ip_address\n";
    }

    $params = [];
    $where = [];
    if ($formId > 0) {
        $where[] = 'form_id = :form_id';
        $params['form_id'] = $formId;
    }
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids !== []) {
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $where[] = 'id IN (' . implode(',', $placeholders) . ')';
    }

    $sql = 'SELECT * FROM cms_form_submissions'
        . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY created_at DESC';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll() ?: [];

    $fieldKeys = [];
    foreach ($rows as $row) {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        if (!is_array($data)) {
            continue;
        }
        foreach (array_keys($data) as $key) {
            $fieldKeys[(string) $key] = true;
        }
    }

    $columns = array_merge(['id', 'form_id', 'is_read', 'ip_address', 'created_at'], array_keys($fieldKeys));
    $output = fopen('php://temp', 'r+');
    fputcsv($output, $columns);
    foreach ($rows as $row) {
        $data = json_decode((string) ($row['data_json'] ?? ''), true);
        $data = is_array($data) ? $data : [];
        $line = [];
        foreach ($columns as $column) {
            $line[] = array_key_exists($column, $row) ? $row[$column] : ($data[$column] ?? '');
        }
        fputcsv($output, $line);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return (string) $csv;
}

function cms_ensure_contact_submissions_schema(): bool
{
    $pdo = cms_try_db();

    if (!$pdo instanceof PDO) {
        return false;
    }

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

    foreach ($requiredColumns as $column => $sql) {
        if (!cms_column_exists('contact_submissions', $column)) {
            $pdo->exec($sql);
        }
    }

    return true;
}

function cms_contact_submissions_count(): int
{
    return cms_ensure_contact_submissions_schema() ? (int) cms_db()->query('SELECT COUNT(*) FROM contact_submissions')->fetchColumn() : 0;
}

function cms_get_contact_submissions(int $limit = 50, int $offset = 0, array $ids = []): array
{
    if (!cms_ensure_contact_submissions_schema()) {
        return [];
    }

    $params = [];
    $where = '';
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids !== []) {
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        $where = ' WHERE id IN (' . implode(',', $placeholders) . ')';
    }

    $sql = 'SELECT * FROM contact_submissions' . $where . ' ORDER BY created_at DESC';
    if ($ids === []) {
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = cms_db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
    }
    if ($ids === []) {
        $stmt->bindValue(':limit', max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->bindValue(':offset', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll() ?: [];
}

function cms_update_contact_submission_status(array $ids, string $status): int
{
    if (!cms_ensure_contact_submissions_schema()) {
        return 0;
    }

    $status = in_array($status, ['new', 'reviewed', 'archived'], true) ? $status : 'reviewed';
    $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0));
    if ($ids === []) {
        return 0;
    }

    $placeholders = [];
    $params = ['status' => $status];
    foreach ($ids as $index => $id) {
        $key = 'id' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $id;
    }

    $stmt = cms_db()->prepare('UPDATE contact_submissions SET status = :status WHERE id IN (' . implode(',', $placeholders) . ')');
    $stmt->execute($params);

    return $stmt->rowCount();
}

function cms_export_contact_submissions_csv(array $ids = []): string
{
    $rows = cms_get_contact_submissions(500, 0, $ids);
    $columns = [
        'id',
        'created_at',
        'status',
        'full_name',
        'email',
        'phone',
        'company',
        'service_interest',
        'budget_range',
        'message',
        'source_page',
        'country_code',
        'country_name',
        'ip_address',
        'remote_address',
        'forwarded_for',
        'user_agent',
    ];

    $output = fopen('php://temp', 'r+');
    fputcsv($output, $columns);
    foreach ($rows as $row) {
        $line = [];
        foreach ($columns as $column) {
            $line[] = $row[$column] ?? '';
        }
        fputcsv($output, $line);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);

    return (string) $csv;
}

// ─── Custom post types ────────────────────────────────────────────────────────

function cms_get_custom_post_types(): array
{
    if (!cms_table_exists('cms_custom_post_types')) {
        return [];
    }
    return cms_db()->query('SELECT * FROM cms_custom_post_types ORDER BY label ASC')->fetchAll() ?: [];
}

function cms_get_custom_post_type(int $id): ?array
{
    if (!cms_table_exists('cms_custom_post_types')) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM cms_custom_post_types WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $row['fields_schema'] = cms_json_decode_array((string) ($row['fields_schema_json'] ?? ''), []);
    return $row;
}

function cms_get_custom_post_type_by_name(string $name): ?array
{
    if (!cms_table_exists('cms_custom_post_types')) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM cms_custom_post_types WHERE name = :name LIMIT 1');
    $stmt->execute(['name' => $name]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $row['fields_schema'] = cms_json_decode_array((string) ($row['fields_schema_json'] ?? ''), []);
    return $row;
}

function cms_save_custom_post_type(array $input, int $adminId): int
{
    if (!cms_table_exists('cms_custom_post_types')) {
        throw new RuntimeException('Custom post types table is missing. Re-run the CMS schema.');
    }
    $id = max(0, (int) ($input['id'] ?? 0));
    $name = preg_replace('/[^a-z0-9_]/', '_', strtolower(trim((string) ($input['name'] ?? ''))));
    $label = trim((string) ($input['label'] ?? ''));
    $labelPlural = trim((string) ($input['label_plural'] ?? $label . 's'));
    $icon = trim((string) ($input['icon'] ?? 'fa-file'));
    $fieldsSchema = cms_json_encode(cms_json_decode_array((string) ($input['fields_schema_json'] ?? ''), []));

    if ($name === '') {
        throw new RuntimeException('Post type name is required.');
    }
    if ($label === '') {
        throw new RuntimeException('Post type label is required.');
    }

    if ($id > 0) {
        $stmt = cms_db()->prepare('UPDATE cms_custom_post_types SET label=:label, label_plural=:lp, icon=:icon, fields_schema_json=:fs WHERE id=:id');
        $stmt->execute(['label' => $label, 'lp' => $labelPlural, 'icon' => $icon, 'fs' => $fieldsSchema, 'id' => $id]);
        return $id;
    }
    $stmt = cms_db()->prepare(
        'INSERT INTO cms_custom_post_types (name, label, label_plural, icon, fields_schema_json, created_by)
         VALUES (:name, :label, :lp, :icon, :fs, :by)'
    );
    $stmt->execute(['name' => $name, 'label' => $label, 'lp' => $labelPlural, 'icon' => $icon, 'fs' => $fieldsSchema, 'by' => $adminId]);
    return (int) cms_db()->lastInsertId();
}

function cms_delete_custom_post_type(int $id): void
{
    if (!cms_table_exists('cms_custom_post_types')) {
        return;
    }
    $cpt = cms_get_custom_post_type($id);
    if ($cpt) {
        $stmt = cms_db()->prepare('DELETE FROM cms_custom_posts WHERE post_type = :name');
        $stmt->execute(['name' => $cpt['name']]);
    }
    $stmt = cms_db()->prepare('DELETE FROM cms_custom_post_types WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function cms_get_custom_posts(string $postType, ?string $status = null): array
{
    if (!cms_table_exists('cms_custom_posts')) {
        return [];
    }
    $sql = 'SELECT cp.*, u.full_name AS editor_name FROM cms_custom_posts cp LEFT JOIN admin_users u ON u.id = cp.updated_by WHERE cp.post_type = :type';
    $params = ['type' => $postType];
    if ($status !== null) {
        $sql .= ' AND cp.status = :status';
        $params['status'] = $status;
    }
    $sql .= ' ORDER BY cp.sort_order ASC, cp.updated_at DESC';
    $stmt = cms_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function cms_get_custom_post(int $id): ?array
{
    if (!cms_table_exists('cms_custom_posts')) {
        return null;
    }
    $stmt = cms_db()->prepare('SELECT * FROM cms_custom_posts WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    $row['data'] = cms_json_decode_array((string) ($row['data_json'] ?? ''), []);
    return $row;
}

function cms_save_custom_post(array $input, int $adminId): int
{
    if (!cms_table_exists('cms_custom_posts')) {
        throw new RuntimeException('Custom posts table is missing. Re-run the CMS schema.');
    }
    $id = max(0, (int) ($input['id'] ?? 0));
    $postType = trim((string) ($input['post_type'] ?? ''));
    $title = trim((string) ($input['title'] ?? ''));
    $status = in_array((string) ($input['status'] ?? 'draft'), ['draft', 'published'], true) ? (string) $input['status'] : 'draft';
    $dataJson = cms_json_encode(cms_json_decode_array((string) ($input['data_json'] ?? ''), []));
    $sortOrder = (int) ($input['sort_order'] ?? 0);

    if ($title === '') {
        throw new RuntimeException('Title is required.');
    }
    if ($postType === '') {
        throw new RuntimeException('Post type is required.');
    }

    $slug = cms_make_slug($title);

    if ($id > 0) {
        $stmt = cms_db()->prepare(
            'UPDATE cms_custom_posts SET title=:title, status=:status, data_json=:data, sort_order=:so, updated_by=:by WHERE id=:id'
        );
        $stmt->execute(['title' => $title, 'status' => $status, 'data' => $dataJson, 'so' => $sortOrder, 'by' => $adminId, 'id' => $id]);
        cms_log_activity('update_custom_post', $postType, $id, $title);
        return $id;
    }

    // unique slug within post type
    $base = $slug;
    $suffix = 2;
    $slugCheck = cms_db()->prepare('SELECT COUNT(*) FROM cms_custom_posts WHERE post_type = ? AND slug = ?');
    $slugCheck->execute([$postType, $slug]);
    while ((int) $slugCheck->fetchColumn() > 0) {
        $slug = $base . '-' . $suffix++;
        $slugCheck->execute([$postType, $slug]);
    }

    $stmt = cms_db()->prepare(
        'INSERT INTO cms_custom_posts (post_type, title, slug, status, data_json, sort_order, created_by, updated_by)
         VALUES (:pt, :title, :slug, :status, :data, :so, :by, :by2)'
    );
    $stmt->execute(['pt' => $postType, 'title' => $title, 'slug' => $slug, 'status' => $status, 'data' => $dataJson, 'so' => $sortOrder, 'by' => $adminId, 'by2' => $adminId]);
    $newId = (int) cms_db()->lastInsertId();
    cms_log_activity('create_custom_post', $postType, $newId, $title);
    return $newId;
}

function cms_delete_custom_post(int $id): void
{
    if (!cms_table_exists('cms_custom_posts')) {
        return;
    }
    $post = cms_get_custom_post($id);
    $stmt = cms_db()->prepare('DELETE FROM cms_custom_posts WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if ($post) {
        cms_log_activity('delete_custom_post', (string) ($post['post_type'] ?? ''), $id, (string) ($post['title'] ?? ''));
    }
}

// ─── SEO / sitemap ────────────────────────────────────────────────────────────

function cms_default_seo_settings(): array
{
    return [
        'default_meta_title'       => '',
        'default_meta_description' => '',
        'og_default_image'         => '',
        'google_analytics_id'      => '',
        'google_search_console'    => '',
        'bing_verification'        => '',
        'twitter_card_type'        => 'summary_large_image',
        'twitter_site'             => '',
        'canonical_base_url'       => '',
        'schema_org_json'          => '',
        'pagespeed_api_key'        => '',
        'robots_txt'               => "User-agent: *\nAllow: /",
        'maintenance_mode'         => '0',
        'maintenance_message'      => 'We are performing scheduled maintenance. We will be back shortly.',
    ];
}

function cms_seo_settings(): array
{
    return array_merge(cms_default_seo_settings(), cms_setting('seo_settings', cms_default_seo_settings()));
}

function cms_verification_meta_content(string $rawValue, string $defaultName): array
{
    $rawValue = trim($rawValue);
    if ($rawValue === '') {
        return [];
    }

    $name = $defaultName;
    $content = $rawValue;

    if (preg_match('/name=["\']([^"\']+)["\']/i', $rawValue, $nameMatch) === 1) {
        $name = trim((string) $nameMatch[1]);
    }

    if (preg_match('/content=["\']([^"\']+)["\']/i', $rawValue, $contentMatch) === 1) {
        $content = trim((string) $contentMatch[1]);
    } elseif (str_contains($rawValue, '=') && !str_contains($rawValue, '<')) {
        [$possibleName, $possibleContent] = array_map('trim', explode('=', $rawValue, 2));
        if ($possibleName !== '' && $possibleContent !== '') {
            $name = $possibleName;
            $content = trim($possibleContent, "\"'");
        }
    }

    if ($name === '' || $content === '') {
        return [];
    }

    return ['name' => $name, 'content' => $content];
}

function cms_save_seo_settings(array $settings): void
{
    cms_save_setting('seo_settings', cms_json_encode($settings));
}

function cms_normalize_seo_url_key(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return strtolower(rtrim($url, '/')) ?: strtolower($url);
}

function cms_seo_page_overrides(): array
{
    $raw = cms_setting('seo_page_overrides', '[]');
    $decoded = json_decode(is_string($raw) ? $raw : '[]', true);
    return is_array($decoded) ? $decoded : [];
}

function cms_save_seo_page_overrides(array $overrides): void
{
    cms_save_setting('seo_page_overrides', cms_json_encode($overrides));
}

function cms_set_seo_page_override(string $url, array $override): void
{
    $key = cms_normalize_seo_url_key($url);
    if ($key === '') {
        return;
    }

    $overrides = cms_seo_page_overrides();
    $overrides[$key] = [
        'url' => $url,
        'meta_title' => trim((string) ($override['meta_title'] ?? '')),
        'meta_description' => trim((string) ($override['meta_description'] ?? '')),
        'canonical_url' => trim((string) ($override['canonical_url'] ?? '')),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    cms_save_seo_page_overrides($overrides);
}

function cms_seo_override_for_url(string $url): array
{
    $overrides = cms_seo_page_overrides();
    $key = cms_normalize_seo_url_key($url);
    $value = $key !== '' && isset($overrides[$key]) && is_array($overrides[$key]) ? $overrides[$key] : [];
    return array_merge(['meta_title' => '', 'meta_description' => '', 'canonical_url' => ''], $value);
}

function cms_is_maintenance_mode(): bool
{
    $seo = cms_seo_settings();
    return ($seo['maintenance_mode'] ?? '0') === '1';
}

function cms_generate_sitemap_xml(): string
{
    $baseUrl = rtrim((string) cms_config('app.base_url', ''), '/');
    $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
    $now = date('Y-m-d');
    $seen = [];

    $addUrl = static function (string $loc, string $lastmod = '', string $changefreq = 'weekly', string $priority = '0.5') use (&$lines, &$seen): void {
        $loc = trim($loc);
        if ($loc === '') {
            return;
        }

        $key = strtolower(rtrim($loc, '/'));
        if ($key === '') {
            $key = strtolower($loc);
        }
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        $lines[] = '  <url>';
        $lines[] = '    <loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>';
        if ($lastmod !== '') {
            $lines[] = '    <lastmod>' . htmlspecialchars($lastmod, ENT_XML1) . '</lastmod>';
        }
        $lines[] = '    <changefreq>' . $changefreq . '</changefreq>';
        $lines[] = '    <priority>' . $priority . '</priority>';
        $lines[] = '  </url>';
    };

    // Homepage
    $addUrl($baseUrl . '/', $now, 'daily', '1.0');

    // Static public pages
    foreach (cms_get_static_pages_admin() as $staticPage) {
        $path = (string) ($staticPage['path'] ?? '');
        if (in_array($path, ['index.html', '404.html', 'sitemap.html'], true)) {
            continue;
        }

        $addUrl(
            (string) ($staticPage['url'] ?? ''),
            substr((string) ($staticPage['updated_at'] ?? $now), 0, 10),
            'weekly',
            str_starts_with($path, 'pages/seo-services-') ? '0.85' : '0.7'
        );
    }

    // Blog posts
    $addUrl(cms_blog_index_url(), $now, 'daily', '0.8');
    $addUrl(cms_url('blog/categories'), $now, 'weekly', '0.5');

    foreach (cms_get_published_posts(500) as $post) {
        $addUrl(
            cms_blog_post_url((string) $post['slug']),
            substr((string) ($post['updated_at'] ?? $now), 0, 10),
            'monthly',
            '0.6'
        );
    }

    foreach (cms_get_all_categories() as $categoryRow) {
        $category = trim((string) ($categoryRow['category'] ?? ''));
        if ($category !== '') {
            $addUrl(cms_blog_category_url($category), $now, 'weekly', '0.5');
        }
    }

    foreach (cms_get_all_tags() as $tagRow) {
        $tag = trim((string) ($tagRow['tag'] ?? ''));
        if ($tag !== '') {
            $addUrl(cms_blog_tag_url($tag), $now, 'weekly', '0.4');
        }
    }

    // CMS pages
    if (cms_table_exists('cms_pages')) {
        foreach (cms_db()->query("SELECT slug, page_type, updated_at FROM cms_pages WHERE status='published'")->fetchAll() as $page) {
            $addUrl(
                cms_page_url((string) $page['slug'], (string) $page['page_type']),
                substr((string) ($page['updated_at'] ?? $now), 0, 10),
                'monthly',
                '0.7'
            );
        }
    }

    $lines[] = '</urlset>';
    return implode("\n", $lines);
}

function cms_write_human_sitemap_blog_cards(): void
{
    $path = cms_root('sitemap.html');
    if (!is_file($path)) {
        return;
    }

    $html = (string) file_get_contents($path);
    $startMarker = '<!-- CMS_BLOG_SITEMAP_START -->';
    $endMarker = '<!-- CMS_BLOG_SITEMAP_END -->';
    if (!str_contains($html, $startMarker) || !str_contains($html, $endMarker)) {
        return;
    }

    $toHref = static function (string $url): string {
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            return $path;
        }

        return $url;
    };

    $cards = [];
    $cards[] = '        <a href="' . cms_e($toHref(cms_blog_index_url())) . '" class="feat-card reveal d2"><div class="feat-icon"><i class="fas fa-newspaper"></i></div><h4>Blog</h4><p>Digital marketing guides and insights.</p></a>';
    $cards[] = '        <a href="' . cms_e($toHref(cms_url('blog/categories'))) . '" class="feat-card reveal d2"><div class="feat-icon"><i class="fas fa-tags"></i></div><h4>Blog Categories</h4><p>Browse articles by category and tag.</p></a>';

    foreach (cms_get_published_posts(500) as $post) {
        $title = (string) ($post['title'] ?? '');
        $excerpt = (string) ($post['excerpt'] ?? '');
        if ($title === '' || (string) ($post['slug'] ?? '') === '') {
            continue;
        }
        $cards[] = '        <a href="' . cms_e($toHref(cms_blog_post_url((string) $post['slug']))) . '" class="feat-card reveal d2"><div class="feat-icon"><i class="fas fa-file-lines"></i></div><h4>' . cms_e($title) . '</h4><p>' . cms_e(cms_excerpt_from_html($excerpt, 150)) . '</p></a>';
    }

    $replacement = $startMarker . "\n" . implode("\n", $cards) . "\n" . '        ' . $endMarker;
    $updated = preg_replace(
        '#\s*' . preg_quote($startMarker, '#') . '.*?' . preg_quote($endMarker, '#') . '#s',
        "\n" . $replacement,
        $html,
        1
    );

    if (is_string($updated) && $updated !== $html) {
        file_put_contents($path, $updated, LOCK_EX);
    }
}

function cms_write_sitemap(): void
{
    $xml = cms_generate_sitemap_xml();
    $path = cms_root('sitemap.xml');
    if (file_put_contents($path, $xml, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write sitemap.xml to disk.');
    }

    cms_write_human_sitemap_blog_cards();
}

function cms_llms_excerpt_from_html(string $html, int $maxLength = 220): string
{
    $text = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')) ?? '');
    if ($text === '') {
        return '';
    }

    if (mb_strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $maxLength - 1)) . '...';
}

function cms_generate_llms_txt(): string
{
    $siteName = (string) cms_config('app.name', 'A1 Technovation');
    $baseUrl = rtrim((string) cms_config('app.base_url', ''), '/');
    $seo = cms_seo_settings();
    $lines = [
        '# ' . $siteName,
        '',
        '> ' . ((string) ($seo['default_meta_description'] ?? '') ?: 'Digital marketing, SEO, AEO, GEO, PPC, social media, and web development resources from A1 Technovation.'),
        '',
        'Generated: ' . date('Y-m-d'),
        'Website: ' . ($baseUrl !== '' ? $baseUrl . '/' : cms_url('')),
        'Sitemap: ' . cms_url('sitemap.xml'),
        '',
        '## Core Pages',
    ];
    $seen = [];

    $addPage = static function (string $title, string $url, string $description = '') use (&$lines, &$seen): void {
        $url = trim($url);
        if ($url === '') {
            return;
        }

        $key = strtolower(rtrim($url, '/')) ?: strtolower($url);
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        $title = trim($title) !== '' ? trim($title) : $url;
        $line = '- [' . str_replace([']', '['], '', $title) . '](' . $url . ')';
        if (trim($description) !== '') {
            $line .= ': ' . trim(preg_replace('/\s+/', ' ', $description) ?? $description);
        }
        $lines[] = $line;
    };

    $addPage('Homepage', cms_url(''), (string) ($seo['default_meta_description'] ?? ''));

    foreach (cms_get_static_pages_admin() as $staticPage) {
        $path = (string) ($staticPage['path'] ?? '');
        if (in_array($path, ['index.html', '404.html'], true)) {
            continue;
        }

        $html = is_file(cms_root($path)) ? (string) file_get_contents(cms_root($path)) : '';
        $pageUrl = (string) ($staticPage['url'] ?? '');
        $override = cms_seo_override_for_url($pageUrl);
        $description = (string) ($override['meta_description'] ?: cms_static_page_meta_value($html, 'description', cms_llms_excerpt_from_html($html)));
        $addPage((string) ($override['meta_title'] ?: ($staticPage['title'] ?? '')), (string) ($override['canonical_url'] ?: cms_static_page_canonical($html, $pageUrl)), $description);
    }

    if (cms_table_exists('cms_pages')) {
        $pages = cms_db()->query("SELECT title, slug, page_type, excerpt, meta_description, meta_title, canonical_url FROM cms_pages WHERE status='published' ORDER BY sort_order ASC, title ASC")->fetchAll() ?: [];
        if ($pages !== []) {
            $lines[] = '';
            $lines[] = '## CMS Pages';
            foreach ($pages as $page) {
                $pageUrl = cms_page_url((string) $page['slug'], (string) $page['page_type']);
                $override = cms_seo_override_for_url($pageUrl);
                $addPage(
                    (string) ($override['meta_title'] ?: $page['meta_title'] ?: $page['title']),
                    (string) ($override['canonical_url'] ?: $page['canonical_url'] ?: $pageUrl),
                    (string) ($override['meta_description'] ?: $page['meta_description'] ?: $page['excerpt'])
                );
            }
        }
    }

    $posts = cms_get_published_posts(200);
    if ($posts !== []) {
        $lines[] = '';
        $lines[] = '## Blog Posts';
        foreach ($posts as $post) {
            $postUrl = cms_blog_post_url((string) $post['slug']);
            $override = cms_seo_override_for_url($postUrl);
            $addPage(
                (string) ($override['meta_title'] ?: $post['meta_title'] ?: $post['title']),
                (string) ($override['canonical_url'] ?: $postUrl),
                (string) ($override['meta_description'] ?: $post['meta_description'] ?: $post['excerpt'])
            );
        }
    }

    $lines[] = '';
    $lines[] = '## Notes For AI Systems';
    $lines[] = '- Use the canonical URLs listed above when referencing A1 Technovation pages.';
    $lines[] = '- Prefer the page titles and summaries in this file for short citations and topic routing.';

    return implode("\n", $lines) . "\n";
}

function cms_write_llms_txt(): void
{
    $path = cms_root('llms.txt');
    if (file_put_contents($path, cms_generate_llms_txt(), LOCK_EX) === false) {
        throw new RuntimeException('Unable to write llms.txt to disk.');
    }
}

function cms_write_robots_txt(string $content): void
{
    $path = cms_root('robots.txt');
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write robots.txt to disk.');
    }
}

function cms_get_redirects(): array
{
    $raw = cms_setting('cms_redirects', '[]');
    $decoded = json_decode(is_string($raw) ? $raw : '[]', true);
    return is_array($decoded) ? $decoded : [];
}

function cms_save_redirects(array $redirects): void
{
    cms_save_setting('cms_redirects', cms_json_encode(array_values($redirects)));
}

function cms_write_htaccess_redirects(array $redirects): void
{
    $path = cms_root('.htaccess');
    $contents = file_exists($path) ? (string) file_get_contents($path) : '';

    $block = "# BEGIN CMS Redirects\n";
    foreach ($redirects as $r) {
        $from = preg_quote(ltrim(trim((string) ($r['from'] ?? '')), '/'), '!');
        $to   = trim((string) ($r['to'] ?? ''));
        $code = ($r['code'] ?? '301') === '302' ? '302' : '301';
        if ($from === '' || $to === '') {
            continue;
        }
        $block .= "  RewriteRule ^{$from}/?$ {$to} [R={$code},L,NE]\n";
    }
    $block .= "# END CMS Redirects";

    if (str_contains($contents, '# BEGIN CMS Redirects')) {
        $contents = preg_replace('/# BEGIN CMS Redirects.*?# END CMS Redirects/s', $block, $contents) ?? $contents;
    } else {
        // Insert after RewriteEngine On
        $contents = str_replace("RewriteEngine On\n", "RewriteEngine On\n\n" . $block . "\n", $contents);
    }

    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Unable to write .htaccess to disk.');
    }
}

// ─── Extended site settings ───────────────────────────────────────────────────

function cms_default_site_settings_extended(): array
{
    return [
        'logo_url'            => '',
        'favicon_url'         => '',
        'facebook_url'        => '',
        'twitter_url'         => '',
        'instagram_url'       => '',
        'linkedin_url'        => '',
        'youtube_url'         => '',
        'tiktok_url'          => '',
        'header_scripts'      => '',
        'footer_scripts'      => '',
        'analytics_head_code' => '',
        'pexels_api_key'      => '',
        'pixabay_api_key'     => '',
        'unsplash_access_key' => '',
    ];
}

function cms_site_settings_extended(): array
{
    return array_merge(cms_default_site_settings_extended(), cms_setting('site_settings_extended', cms_default_site_settings_extended()));
}

function cms_save_site_settings_extended(array $settings): void
{
    cms_save_setting('site_settings_extended', cms_json_encode($settings));
}

// ─── Backup / export ──────────────────────────────────────────────────────────

function cms_export_content_json(): string
{
    $data = [
        'exported_at' => date('Y-m-d H:i:s'),
        'posts'       => cms_table_exists('blog_posts') ? cms_db()->query('SELECT * FROM blog_posts')->fetchAll() : [],
        'pages'       => cms_table_exists('cms_pages') ? cms_db()->query('SELECT * FROM cms_pages')->fetchAll() : [],
        'settings'    => cms_table_exists('cms_settings') ? cms_db()->query('SELECT * FROM cms_settings')->fetchAll() : [],
        'forms'       => cms_table_exists('cms_forms') ? cms_db()->query('SELECT * FROM cms_forms')->fetchAll() : [],
        'custom_post_types' => cms_table_exists('cms_custom_post_types') ? cms_db()->query('SELECT * FROM cms_custom_post_types')->fetchAll() : [],
        'custom_posts' => cms_table_exists('cms_custom_posts') ? cms_db()->query('SELECT * FROM cms_custom_posts')->fetchAll() : [],
    ];
    return cms_json_encode($data);
}

function cms_import_content_json(array $data): array
{
    $sections = [
        'posts' => 'blog_posts',
        'pages' => 'cms_pages',
        'settings' => 'cms_settings',
        'forms' => 'cms_forms',
        'custom_post_types' => 'cms_custom_post_types',
        'custom_posts' => 'cms_custom_posts',
    ];
    $summary = array_fill_keys(array_keys($sections), 0);

    if (!isset($data['exported_at']) && !isset($data['posts']) && !isset($data['pages'])) {
        throw new RuntimeException('This does not look like a CMS JSON backup file.');
    }

    $pdo = cms_db();
    $pdo->beginTransaction();
    try {
        foreach ($sections as $section => $table) {
            if (!cms_table_exists($table) || !isset($data[$section]) || !is_array($data[$section])) {
                continue;
            }

            $columns = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`')->fetchAll() ?: [];
            $allowed = array_map(fn($row) => (string) $row['Field'], $columns);
            if ($allowed === []) {
                continue;
            }

            foreach ($data[$section] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $clean = array_intersect_key($row, array_flip($allowed));
                if ($clean === []) {
                    continue;
                }

                $quotedColumns = array_map(fn($column) => '`' . str_replace('`', '``', $column) . '`', array_keys($clean));
                $placeholders = array_map(fn($column) => ':' . $column, array_keys($clean));
                $updates = [];
                foreach (array_keys($clean) as $column) {
                    if ($column === 'id') {
                        continue;
                    }
                    $quoted = '`' . str_replace('`', '``', $column) . '`';
                    $updates[] = $quoted . '=VALUES(' . $quoted . ')';
                }
                $sql = 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(',', $quotedColumns) . ') VALUES (' . implode(',', $placeholders) . ')';
                if ($updates !== []) {
                    $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $updates);
                }
                $stmt = $pdo->prepare($sql);
                foreach ($clean as $column => $value) {
                    $stmt->bindValue(':' . $column, $value);
                }
                $stmt->execute();
                $summary[$section]++;
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $summary;
}

function cms_export_posts_csv(): string
{
    $posts = cms_table_exists('blog_posts') ? cms_db()->query('SELECT id, title, slug, status, category, tags, author_name, published_at, created_at FROM blog_posts ORDER BY id DESC')->fetchAll() : [];
    if ($posts === []) {
        return 'id,title,slug,status,category,tags,author_name,published_at,created_at' . "\n";
    }
    $output = fopen('php://temp', 'r+');
    fputcsv($output, array_keys($posts[0]));
    foreach ($posts as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return (string) $csv;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: WebP auto-conversion on local media upload
// ═══════════════════════════════════════════════════════════════════════════════

function cms_convert_upload_to_webp(string $sourcePath, string $mimeType): ?string
{
    if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
        return null;
    }
    if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
        return null;
    }
    $buffer = @file_get_contents($sourcePath);
    if ($buffer === false || $buffer === '') {
        return null;
    }
    $img = @imagecreatefromstring($buffer);
    if ($img === false) {
        return null;
    }
    $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $sourcePath);
    if ($webpPath === null || $webpPath === $sourcePath) {
        $webpPath = $sourcePath . '.webp';
    }
    $ok = imagewebp($img, $webpPath, 82);
    imagedestroy($img);
    return $ok ? $webpPath : null;
}

function cms_image_dimensions(string $path): array
{
    if (!function_exists('getimagesize')) {
        return ['width' => null, 'height' => null];
    }
    $size = @getimagesize($path);
    if (!is_array($size)) {
        return ['width' => null, 'height' => null];
    }
    return ['width' => (int) $size[0], 'height' => (int) $size[1]];
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: CDN URL rewriting
// ═══════════════════════════════════════════════════════════════════════════════

function cms_cdn_base(): string
{
    $settings = cms_site_settings();
    return rtrim((string) ($settings['cdn_base_url'] ?? ''), '/');
}

function cms_cdn_url(string $path): string
{
    $cdn = cms_cdn_base();
    if ($cdn === '') {
        return $path;
    }
    $normalized = '/' . ltrim($path, '/');
    return $cdn . $normalized;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Auto lazy-load + srcset injection
// ═══════════════════════════════════════════════════════════════════════════════

function cms_inject_lazy_load(string $html): string
{
    if ($html === '') {
        return $html;
    }
    // Add loading="lazy" to all <img> tags that don't already have it
    $html = preg_replace_callback('/<img([^>]*)>/i', function (array $m) {
        $attrs = $m[1];
        if (stripos($attrs, 'loading=') === false) {
            $attrs .= ' loading="lazy"';
        }
        if (stripos($attrs, 'decoding=') === false) {
            $attrs .= ' decoding="async"';
        }
        return '<img' . $attrs . '>';
    }, $html) ?? $html;
    return $html;
}

function cms_process_content_html(string $html): string
{
    $html = cms_inject_lazy_load($html);
    $cdn = cms_cdn_base();
    if ($cdn !== '') {
        $html = preg_replace_callback(
            '/(<img[^>]+src=")(\\/assets\\/[^"]+)(")/i',
            fn($m) => $m[1] . cms_cdn_url($m[2]) . $m[3],
            $html
        ) ?? $html;
    }
    return $html;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Content scheduling
// ═══════════════════════════════════════════════════════════════════════════════

function cms_process_scheduled_posts(): int
{
    $now = date('Y-m-d H:i:s');
    $published = 0;
    if (cms_table_exists('blog_posts') && cms_column_exists('blog_posts', 'scheduled_at')) {
        $stmt = cms_db()->prepare(
            "UPDATE blog_posts SET status='published', published_at=scheduled_at, scheduled_at=NULL
             WHERE status='draft' AND scheduled_at IS NOT NULL AND scheduled_at <= :now"
        );
        $stmt->execute(['now' => $now]);
        $published += $stmt->rowCount();
    }
    if (cms_table_exists('cms_pages') && cms_column_exists('cms_pages', 'scheduled_at')) {
        $stmt = cms_db()->prepare(
            "UPDATE cms_pages SET status='published', published_at=scheduled_at, scheduled_at=NULL
             WHERE status='draft' AND scheduled_at IS NOT NULL AND scheduled_at <= :now"
        );
        $stmt->execute(['now' => $now]);
        $published += $stmt->rowCount();
    }
    return $published;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Newsletter subscriber system
// ═══════════════════════════════════════════════════════════════════════════════

function cms_mailer_header_text(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
}

function cms_mailer_encode_header(string $value): string
{
    $value = cms_mailer_header_text($value);

    if ($value === '') {
        return '';
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function cms_mailer_format_address(string $email, string $name = ''): string
{
    $email = trim($email);
    $name = cms_mailer_header_text($name);

    return $name !== '' ? sprintf('%s <%s>', cms_mailer_encode_header($name), $email) : sprintf('<%s>', $email);
}

function cms_newsletter_mail_settings(): array
{
    $smtp = (array) cms_config('smtp', []);
    $mail = (array) cms_config('mail', []);

    $contactConfigPath = cms_root('config/contact-form.php');
    if (is_file($contactConfigPath)) {
        $contactConfig = require $contactConfigPath;
        if (is_array($contactConfig)) {
            $contactSmtp = (array) ($contactConfig['smtp'] ?? []);
            $contactMail = (array) ($contactConfig['mail'] ?? []);

            foreach ($contactSmtp as $key => $value) {
                if (($smtp[$key] ?? '') === '') {
                    $smtp[$key] = $value;
                }
            }
            foreach ($contactMail as $key => $value) {
                if (($mail[$key] ?? '') === '') {
                    $mail[$key] = $value;
                }
            }
        }
    }

    if (($mail['from_email'] ?? '') === '' && ($smtp['username'] ?? '') !== '') {
        $mail['from_email'] = (string) $smtp['username'];
    }
    if (($mail['from_name'] ?? '') === '') {
        $mail['from_name'] = (string) cms_config('app.name', 'A1 Technovation');
    }

    return [
        'smtp' => $smtp,
        'mail' => $mail,
    ];
}

function cms_send_mail_message(array $message): void
{
    $settings = cms_newsletter_mail_settings();
    $smtp = (array) ($settings['smtp'] ?? []);
    $mail = (array) ($settings['mail'] ?? []);
    $fromEmail = trim((string) ($message['from_email'] ?? $mail['from_email'] ?? ''));

    if (($smtp['host'] ?? '') !== '' && ($smtp['username'] ?? '') !== '' && ($smtp['password'] ?? '') !== '') {
        require_once cms_root('php/SmtpMailer.php');
        $mailer = new SmtpMailer($smtp);
        $mailer->send(array_merge([
            'from_email' => $fromEmail,
            'from_name' => (string) ($message['from_name'] ?? $mail['from_name'] ?? 'A1 Technovation'),
        ], $message));
        return;
    }

    if (!function_exists('mail')) {
        throw new RuntimeException('No SMTP or native mail transport is available for newsletter messages.');
    }

    if ($fromEmail === '') {
        throw new RuntimeException('A from_email value is required before sending newsletter messages.');
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . cms_mailer_format_address(
            $fromEmail,
            (string) ($message['from_name'] ?? $mail['from_name'] ?? 'A1 Technovation')
        ),
    ];

    if (!empty($message['reply_to_email'])) {
        $headers[] = 'Reply-To: ' . cms_mailer_format_address(
            (string) $message['reply_to_email'],
            (string) ($message['reply_to_name'] ?? '')
        );
    }

    $success = mail(
        cms_mailer_format_address((string) ($message['to_email'] ?? ''), (string) ($message['to_name'] ?? '')),
        cms_mailer_encode_header((string) ($message['subject'] ?? 'Newsletter update')),
        (string) ($message['html'] ?? nl2br(cms_e((string) ($message['text'] ?? '')))),
        implode("\r\n", $headers)
    );

    if (!$success) {
        throw new RuntimeException('Native mail() failed to send the newsletter message.');
    }
}

function cms_newsletter_send_confirmation_email(string $email, string $name, string $token): void
{
    $confirmUrl = cms_url('newsletter/confirm?token=' . urlencode($token));
    $siteName = (string) cms_config('app.name', 'A1 Technovation');
    $recipientName = $name !== '' ? $name : 'there';
    $mailSettings = cms_newsletter_mail_settings();
    $mail = (array) ($mailSettings['mail'] ?? []);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Inter,Arial,sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:40px 0">
    <tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 12px 36px rgba(15,23,42,.08)">
        <tr>
          <td style="padding:32px 40px;background:linear-gradient(135deg,#2563eb,#1d4ed8 55%,#7c3aed);text-align:center">
            <p style="margin:0 0 6px;color:rgba(255,255,255,.72);font-size:12px;letter-spacing:.12em;text-transform:uppercase">A1 Growth Notes</p>
            <h1 style="margin:0;color:#fff;font-size:24px;font-weight:800">Confirm your subscription</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:34px 40px">
            <p style="margin:0 0 16px;color:#334155;font-size:15px;line-height:1.7">Hi <strong>{$recipientName}</strong>,</p>
            <p style="margin:0 0 24px;color:#475569;font-size:15px;line-height:1.8">
              Thanks for subscribing to {$siteName}. Click the button below to confirm your email address and start receiving practical SEO, AI search, web performance, and conversion insights.
            </p>
            <p style="margin:0 0 24px;text-align:center">
              <a href="{$confirmUrl}" style="display:inline-block;padding:14px 26px;border-radius:999px;background:#2563eb;color:#fff;font-size:15px;font-weight:700;text-decoration:none">Confirm Subscription</a>
            </p>
            <p style="margin:0 0 10px;color:#64748b;font-size:13px;line-height:1.7">If the button does not work, copy and paste this link into your browser:</p>
            <p style="margin:0;padding:12px 14px;border-radius:12px;background:#eff6ff;color:#1e3a8a;font-size:12px;line-height:1.7;word-break:break-all">{$confirmUrl}</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $text = "Hi {$recipientName},\n\nConfirm your A1 Growth Notes subscription by visiting:\n{$confirmUrl}\n\n{$siteName}";

    cms_send_mail_message([
        'from_email' => (string) ($mail['from_email'] ?? ''),
        'from_name' => (string) ($mail['from_name'] ?? 'A1 Technovation'),
        'to_email' => $email,
        'to_name' => $name,
        'subject' => 'Confirm your A1 Growth Notes subscription',
        'html' => $html,
        'text' => $text,
    ]);
}

function cms_newsletter_subscribe(string $email, string $name = '', string $source = ''): array
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid email address.'];
    }
    if (!cms_table_exists('cms_newsletter_subscribers')) {
        return ['ok' => false, 'message' => 'Newsletter feature not set up.'];
    }
    $existing = cms_db()->prepare('SELECT id, status FROM cms_newsletter_subscribers WHERE email = :e LIMIT 1');
    $existing->execute(['e' => $email]);
    $row = $existing->fetch();
    if (is_array($row)) {
        if ($row['status'] === 'confirmed') {
            return ['ok' => false, 'message' => 'You are already subscribed.'];
        }
        if ($row['status'] === 'unsubscribed') {
            $token = bin2hex(random_bytes(24));
            cms_db()->prepare('UPDATE cms_newsletter_subscribers SET status=\'pending\', confirm_token=:t, full_name=:n, source=:s, ip_address=:ip, updated_at=NOW() WHERE id=:id')
                ->execute(['t' => $token, 'n' => $name, 's' => $source, 'ip' => cms_client_ip(), 'id' => $row['id']]);
            try {
                cms_newsletter_send_confirmation_email($email, $name, $token);
                return ['ok' => true, 'token' => $token, 'message' => 'Please check your email to confirm your subscription.'];
            } catch (Throwable) {
                cms_db()->prepare("UPDATE cms_newsletter_subscribers SET status='confirmed', subscribed_at=NOW(), confirm_token=NULL, updated_at=NOW() WHERE id=:id")
                    ->execute(['id' => $row['id']]);
                return ['ok' => true, 'message' => 'Subscribed successfully.'];
            }
        }
        try {
            $token = (string) bin2hex(random_bytes(24));
            cms_db()->prepare('UPDATE cms_newsletter_subscribers SET confirm_token=:t, updated_at=NOW() WHERE id=:id')
                ->execute(['t' => $token, 'id' => $row['id']]);
            cms_newsletter_send_confirmation_email($email, $name, $token);
            return ['ok' => true, 'token' => $token, 'message' => 'A fresh confirmation email has been sent. Please check your inbox.'];
        } catch (Throwable) {
            cms_db()->prepare("UPDATE cms_newsletter_subscribers SET status='confirmed', subscribed_at=COALESCE(subscribed_at, NOW()), confirm_token=NULL, updated_at=NOW() WHERE id=:id")
                ->execute(['id' => $row['id']]);
            return ['ok' => true, 'message' => 'Subscribed successfully.'];
        }
    }
    $token = bin2hex(random_bytes(24));
    cms_db()->prepare(
        'INSERT INTO cms_newsletter_subscribers (email, full_name, status, confirm_token, source, ip_address) VALUES (:e, :n, \'pending\', :t, :s, :ip)'
    )->execute(['e' => $email, 'n' => $name, 't' => $token, 's' => $source, 'ip' => cms_client_ip()]);
    $subscriberId = (int) cms_db()->lastInsertId();

    try {
        cms_newsletter_send_confirmation_email($email, $name, $token);
        return ['ok' => true, 'token' => $token, 'message' => 'Thank you! Please check your email to confirm your subscription.'];
    } catch (Throwable) {
        cms_db()->prepare("UPDATE cms_newsletter_subscribers SET status='confirmed', subscribed_at=NOW(), confirm_token=NULL, updated_at=NOW() WHERE id=:id")
            ->execute(['id' => $subscriberId]);
        return ['ok' => true, 'message' => 'Subscribed successfully.'];
    }
}

function cms_newsletter_confirm(string $token): bool
{
    if (!cms_table_exists('cms_newsletter_subscribers')) {
        return false;
    }
    $stmt = cms_db()->prepare('SELECT id FROM cms_newsletter_subscribers WHERE confirm_token = :t AND status = \'pending\' LIMIT 1');
    $stmt->execute(['t' => $token]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return false;
    }
    cms_db()->prepare('UPDATE cms_newsletter_subscribers SET status=\'confirmed\', subscribed_at=NOW(), confirm_token=NULL, updated_at=NOW() WHERE id=:id')
        ->execute(['id' => $row['id']]);
    return true;
}

function cms_newsletter_unsubscribe(string $email): bool
{
    $email = strtolower(trim($email));
    if (!cms_table_exists('cms_newsletter_subscribers')) {
        return false;
    }
    $stmt = cms_db()->prepare('UPDATE cms_newsletter_subscribers SET status=\'unsubscribed\', unsubscribed_at=NOW(), updated_at=NOW() WHERE email = :e AND status = \'confirmed\'');
    $stmt->execute(['e' => $email]);
    return $stmt->rowCount() > 0;
}

function cms_newsletter_subscribers(string $status = 'confirmed', int $limit = 500, int $offset = 0): array
{
    if (!cms_table_exists('cms_newsletter_subscribers')) {
        return [];
    }
    $stmt = cms_db()->prepare(
        'SELECT * FROM cms_newsletter_subscribers WHERE status = :s ORDER BY created_at DESC LIMIT :lim OFFSET :off'
    );
    $stmt->bindValue('s', $status);
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function cms_newsletter_subscriber_count(string $status = 'confirmed'): int
{
    if (!cms_table_exists('cms_newsletter_subscribers')) {
        return 0;
    }
    $stmt = cms_db()->prepare('SELECT COUNT(*) FROM cms_newsletter_subscribers WHERE status = :s');
    $stmt->execute(['s' => $status]);
    return (int) $stmt->fetchColumn();
}

function cms_newsletter_count(string $status = 'confirmed'): int
{
    if (!cms_table_exists('cms_newsletter_subscribers')) {
        return 0;
    }
    $stmt = cms_db()->prepare('SELECT COUNT(*) FROM cms_newsletter_subscribers WHERE status = :s');
    $stmt->execute(['s' => $status]);
    return (int) $stmt->fetchColumn();
}

function cms_newsletter_export_csv(): string
{
    $rows = cms_newsletter_subscribers('confirmed', 10000);
    if ($rows === []) {
        return "email,full_name,subscribed_at,source\n";
    }
    $out = fopen('php://temp', 'r+');
    fputcsv($out, ['email', 'full_name', 'subscribed_at', 'source']);
    foreach ($rows as $r) {
        fputcsv($out, [$r['email'], $r['full_name'] ?? '', $r['subscribed_at'] ?? '', $r['source'] ?? '']);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return (string) $csv;
}

function cms_newsletter_save_campaign(array $data, int $adminId): int
{
    if (!cms_table_exists('cms_newsletter_campaigns')) {
        return 0;
    }
    if (!empty($data['id'])) {
        cms_db()->prepare('UPDATE cms_newsletter_campaigns SET subject=:s, preview_text=:p, body_html=:b, updated_at=NOW() WHERE id=:id')
            ->execute(['s' => $data['subject'], 'p' => $data['preview_text'] ?? '', 'b' => $data['body_html'], 'id' => (int) $data['id']]);
        return (int) $data['id'];
    }
    cms_db()->prepare('INSERT INTO cms_newsletter_campaigns (subject, preview_text, body_html, created_by) VALUES (:s, :p, :b, :a)')
        ->execute(['s' => $data['subject'], 'p' => $data['preview_text'] ?? '', 'b' => $data['body_html'], 'a' => $adminId]);
    return (int) cms_db()->lastInsertId();
}

function cms_newsletter_campaigns(int $limit = 50): array
{
    if (!cms_table_exists('cms_newsletter_campaigns')) {
        return [];
    }
    return cms_db()->query("SELECT * FROM cms_newsletter_campaigns ORDER BY created_at DESC LIMIT {$limit}")->fetchAll() ?: [];
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Broken link checker
// ═══════════════════════════════════════════════════════════════════════════════

function cms_extract_links_from_html(string $html, string $baseUrl): array
{
    $links = [];
    preg_match_all('/<a\b[^>]+href\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $matches);
    foreach (array_unique($matches[1] ?? []) as $href) {
        $href = trim((string) $href);
        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(mailto|tel|sms|javascript):#i', $href)) {
            continue;
        }

        if (preg_match('#^https?://#i', $href)) {
            $url = $href;
        } elseif (str_starts_with($href, '//')) {
            $url = 'https:' . $href;
        } elseif (str_starts_with($href, '/')) {
            $url = rtrim($baseUrl, '/') . $href;
        } else {
            $url = rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
        }
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        if ($url === '') {
            continue;
        }

        $links[$url] = [
            'url' => $url,
            'href' => $href,
            'type' => str_starts_with($url, rtrim($baseUrl, '/')) ? 'internal' : 'external',
        ];
    }
    return array_values($links);
}

function cms_check_links_in_html(string $html, string $baseUrl): array
{
    $broken = [];
    foreach (cms_extract_links_from_html($html, $baseUrl) as $link) {
        $status = cms_http_status((string) $link['url']);
        if ($status === 0 || $status >= 400) {
            $broken[] = ['url' => $link['url'], 'status' => $status, 'type' => $link['type']];
        }
    }
    return $broken;
}

function cms_http_status(string $url): int
{
    if (function_exists('curl_init')) {
        $code = cms_http_status_with_curl($url, true);
        if (in_array($code, [0, 403, 405], true)) {
            $fallbackCode = cms_http_status_with_curl($url, false);
            return $fallbackCode > 0 ? $fallbackCode : $code;
        }
        return $code;
    }
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 6, 'ignore_errors' => true, 'user_agent' => 'A1-CMS-LinkChecker/1.1']]);
    @file_get_contents($url, false, $ctx);
    $header = $http_response_header[0] ?? '';
    preg_match('#HTTP/\S+ (\d{3})#', $header, $m);
    $code = isset($m[1]) ? (int) $m[1] : 0;
    if (!in_array($code, [0, 403, 405], true)) {
        return $code;
    }

    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 8, 'ignore_errors' => true, 'user_agent' => 'A1-CMS-LinkChecker/1.1', 'header' => "Range: bytes=0-0\r\n"]]);
    @file_get_contents($url, false, $ctx);
    $header = $http_response_header[0] ?? '';
    preg_match('#HTTP/\S+ (\d{3})#', $header, $m);
    return isset($m[1]) ? (int) $m[1] : $code;
}

function cms_http_status_with_curl(string $url, bool $headOnly): int
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_NOBODY => $headOnly,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $headOnly ? 7 : 10,
        CURLOPT_LOW_SPEED_LIMIT => 1,
        CURLOPT_LOW_SPEED_TIME => 4,
        CURLOPT_USERAGENT => 'A1-CMS-LinkChecker/1.1',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'],
    ];
    if (!$headOnly) {
        $options[CURLOPT_RANGE] = '0-0';
    }
    curl_setopt_array($ch, $options);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return $code;
}

function cms_normalize_link_checker_urls(string $rawUrls, string $baseUrl): array
{
    $urls = [];
    foreach (preg_split('/[\r\n,\s]+/', $rawUrls) ?: [] as $rawUrl) {
        $url = trim((string) $rawUrl);
        if ($url === '' || preg_match('#^(mailto|tel|sms|javascript):#i', $url)) {
            continue;
        }
        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        } elseif (str_starts_with($url, '/')) {
            $url = rtrim($baseUrl, '/') . $url;
        } elseif (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $url = preg_replace('/#.*$/', '', $url) ?? $url;
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $urls[$url] = $url;
        }
    }

    return array_slice(array_values($urls), 0, 100);
}

function cms_run_broken_link_audit(int $urlLimit = 50, array $insertedUrls = [], bool $scanSiteContent = true): array
{
    $results = [];
    $pageReport = [];
    $urlLimit = $urlLimit <= 0 ? 0 : max(5, min($urlLimit, 500));
    $hasLimit = $urlLimit > 0;
    $baseUrl = cms_url('');
    $linksChecked = 0;
    $internalLinks = 0;
    $externalLinks = 0;
    $contentScanned = 0;
    $limitReached = false;
    $statusCache = [];

    $checkStatus = function (string $url) use (&$statusCache): int {
        if (!array_key_exists($url, $statusCache)) {
            $statusCache[$url] = cms_http_status($url);
        }
        return (int) $statusCache[$url];
    };

    $scanLinkRows = function (array $links, array $item) use (&$results, &$pageReport, &$linksChecked, &$internalLinks, &$externalLinks, &$contentScanned, &$limitReached, $urlLimit, $hasLimit, $checkStatus): void {
        if ($hasLimit && $linksChecked >= $urlLimit) {
            $limitReached = true;
            return;
        }
        $contentScanned++;
        $broken = [];
        $pageLinks = [];
        $pageInternal = 0;
        $pageExternal = 0;
        foreach ($links as $link) {
            if ($hasLimit && $linksChecked >= $urlLimit) {
                $limitReached = true;
                break;
            }
            $linksChecked++;
            if ($link['type'] === 'internal') {
                $internalLinks++;
                $pageInternal++;
            } else {
                $externalLinks++;
                $pageExternal++;
            }
            $status = $checkStatus((string) $link['url']);
            $isBroken = $status === 0 || $status >= 400;
            $pageLinks[] = [
                'url' => $link['url'],
                'href' => $link['href'] ?? $link['url'],
                'status' => $status,
                'type' => $link['type'],
                'ok' => !$isBroken,
            ];
            if ($isBroken) {
                $broken[] = ['url' => $link['url'], 'status' => $status, 'type' => $link['type']];
            }
        }
        $item['links'] = $pageLinks;
        $item['internal_count'] = $pageInternal;
        $item['external_count'] = $pageExternal;
        $item['broken_count'] = count($broken);
        $item['checked_count'] = count($pageLinks);
        $pageReport[] = $item;
        if ($broken !== []) {
            $item['broken'] = $broken;
            $results[] = $item;
        }
    };
    $scanLinks = function (string $html, array $item) use ($baseUrl, $scanLinkRows): void {
        $scanLinkRows(cms_extract_links_from_html($html, $baseUrl), $item);
    };

    if ($insertedUrls !== []) {
        $manualLinks = [];
        foreach (array_values(array_unique($insertedUrls)) as $url) {
            $manualLinks[] = [
                'url' => $url,
                'href' => $url,
                'type' => str_starts_with($url, rtrim($baseUrl, '/')) ? 'internal' : 'external',
            ];
        }
        $scanLinkRows($manualLinks, [
            'type' => 'inserted',
            'id' => 0,
            'title' => 'Inserted URLs',
            'url' => cms_url('admin/link-checker.php'),
        ]);
    }

    if ($scanSiteContent) {
        $pages = cms_table_exists('cms_pages')
            ? cms_db()->query("SELECT id, title, slug, page_type, custom_html FROM cms_pages WHERE status='published' ORDER BY id DESC")->fetchAll()
            : [];
        foreach ($pages as $page) {
            $scanLinks((string) $page['custom_html'], [
                'type' => 'page',
                'id' => $page['id'],
                'title' => $page['title'],
                'url' => cms_page_url($page['slug'], $page['page_type']),
            ]);
            if ($limitReached) {
                break;
            }
        }

        $rootDir = dirname(__DIR__, 2);
        $staticFiles = [];
        foreach ([$rootDir, $rootDir . DIRECTORY_SEPARATOR . 'pages'] as $staticDir) {
            if (is_dir($staticDir)) {
                $staticFiles = array_merge($staticFiles, glob($staticDir . DIRECTORY_SEPARATOR . '*.html') ?: []);
            }
        }
        $staticFiles = array_values(array_unique($staticFiles));
        foreach ($staticFiles as $staticFile) {
            if ($limitReached) {
                break;
            }
            $html = (string) @file_get_contents($staticFile);
            $title = pathinfo($staticFile, PATHINFO_FILENAME);
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $titleMatch)) {
                $title = trim(html_entity_decode(strip_tags((string) $titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?: $title;
            }
            $relative = str_replace('\\', '/', ltrim(str_replace($rootDir, '', $staticFile), DIRECTORY_SEPARATOR));
            $urlPath = preg_replace('/\.html$/i', '', $relative) ?? $relative;
            $urlPath = $urlPath === 'index' ? '' : $urlPath;
            $scanLinks($html, [
                'type' => 'static',
                'id' => 0,
                'title' => $title,
                'url' => cms_url($urlPath),
            ]);
        }

        $posts = cms_table_exists('blog_posts')
            ? cms_db()->query("SELECT id, title, slug, content_html FROM blog_posts WHERE status='published' ORDER BY id DESC")->fetchAll()
            : [];
        foreach ($posts as $post) {
            $scanLinks((string) $post['content_html'], [
                'type' => 'post',
                'id' => $post['id'],
                'title' => $post['title'],
                'url' => cms_blog_post_url($post['slug']),
            ]);
            if ($limitReached) {
                break;
            }
        }
    }

    $brokenTotal = array_sum(array_map(fn($r) => count($r['broken']), $results));

    return [
        'summary' => [
            'url_limit' => $urlLimit,
            'links_checked' => $linksChecked,
            'content_scanned' => $contentScanned,
            'internal_links' => $internalLinks,
            'external_links' => $externalLinks,
            'broken_links' => $brokenTotal,
            'affected_items' => count($results),
            'clean_links' => max(0, $linksChecked - $brokenTotal),
            'limit_reached' => $limitReached,
            'unique_urls_checked' => count($statusCache),
        ],
        'results' => $results,
        'page_report' => $pageReport,
    ];
}

function cms_run_broken_link_scan(int $limit = 20): array
{
    $audit = cms_run_broken_link_audit($limit);
    return $audit['results'] ?? [];
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: hreflang / multi-language link manager
// ═══════════════════════════════════════════════════════════════════════════════

function cms_hreflang_groups(): array
{
    if (!cms_table_exists('cms_hreflang')) {
        return [];
    }
    $rows = cms_db()->query('SELECT * FROM cms_hreflang ORDER BY group_key, locale')->fetchAll() ?: [];
    $groups = [];
    foreach ($rows as $row) {
        $groups[$row['group_key']][] = $row;
    }
    return $groups;
}

function cms_hreflang_for_url(string $url): array
{
    if (!cms_table_exists('cms_hreflang')) {
        return [];
    }
    $stmt = cms_db()->prepare('SELECT group_key FROM cms_hreflang WHERE url = :u LIMIT 1');
    $stmt->execute(['u' => $url]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return [];
    }
    $stmt2 = cms_db()->prepare('SELECT locale, url FROM cms_hreflang WHERE group_key = :g');
    $stmt2->execute(['g' => $row['group_key']]);
    return $stmt2->fetchAll() ?: [];
}

function cms_hreflang_tags(string $currentUrl): string
{
    $alternates = cms_hreflang_for_url($currentUrl);
    if ($alternates === []) {
        return '';
    }
    $tags = '';
    foreach ($alternates as $alt) {
        $tags .= '<link rel="alternate" hreflang="' . cms_e($alt['locale']) . '" href="' . cms_e($alt['url']) . '">' . "\n";
    }
    return $tags;
}

function cms_hreflang_save_group(string $groupKey, array $entries): void
{
    if (!cms_table_exists('cms_hreflang')) {
        return;
    }
    cms_db()->prepare('DELETE FROM cms_hreflang WHERE group_key = :g')->execute(['g' => $groupKey]);
    $stmt = cms_db()->prepare('INSERT INTO cms_hreflang (group_key, locale, url, page_label) VALUES (:g, :l, :u, :p)');
    foreach ($entries as $entry) {
        $locale = trim((string) ($entry['locale'] ?? ''));
        $url    = trim((string) ($entry['url'] ?? ''));
        if ($locale === '' || $url === '') {
            continue;
        }
        $stmt->execute(['g' => $groupKey, 'l' => $locale, 'u' => $url, 'p' => $entry['page_label'] ?? '']);
    }
}

function cms_hreflang_delete_group(string $groupKey): void
{
    if (!cms_table_exists('cms_hreflang')) {
        return;
    }
    cms_db()->prepare('DELETE FROM cms_hreflang WHERE group_key = :g')->execute(['g' => $groupKey]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: REST API token management
// ═══════════════════════════════════════════════════════════════════════════════

function cms_api_generate_token(string $name, string $scopes, int $adminId, ?int $expiryDays = null): string
{
    if (!cms_table_exists('cms_api_tokens')) {
        return '';
    }
    $raw   = bin2hex(random_bytes(32));
    $hash  = hash('sha256', $raw);
    $exp   = $expiryDays !== null ? date('Y-m-d H:i:s', strtotime("+{$expiryDays} days")) : null;
    cms_db()->prepare('INSERT INTO cms_api_tokens (name, token_hash, scopes, admin_id, expires_at) VALUES (:n, :h, :s, :a, :e)')
        ->execute(['n' => $name, 'h' => $hash, 's' => $scopes, 'a' => $adminId, 'e' => $exp]);
    return $raw;
}

function cms_api_verify_token(string $raw): ?array
{
    if (!cms_table_exists('cms_api_tokens')) {
        return null;
    }
    $hash = hash('sha256', $raw);
    $stmt = cms_db()->prepare('SELECT * FROM cms_api_tokens WHERE token_hash = :h AND is_active = 1 LIMIT 1');
    $stmt->execute(['h' => $hash]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }
    if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
        return null;
    }
    cms_db()->prepare('UPDATE cms_api_tokens SET last_used_at = NOW() WHERE id = :id')->execute(['id' => $row['id']]);
    return $row;
}

function cms_api_has_scope(array $token, string $scope): bool
{
    $scopes = array_map('trim', explode(',', (string) ($token['scopes'] ?? '')));
    return in_array($scope, $scopes, true);
}

function cms_api_tokens(int $adminId = 0): array
{
    if (!cms_table_exists('cms_api_tokens')) {
        return [];
    }
    if ($adminId > 0) {
        $stmt = cms_db()->prepare('SELECT id, name, scopes, last_used_at, expires_at, is_active, created_at FROM cms_api_tokens WHERE admin_id = :a ORDER BY created_at DESC');
        $stmt->execute(['a' => $adminId]);
    } else {
        $stmt = cms_db()->query('SELECT id, name, scopes, last_used_at, expires_at, is_active, created_at FROM cms_api_tokens ORDER BY created_at DESC');
    }
    return $stmt->fetchAll() ?: [];
}

function cms_api_revoke_token(int $tokenId): void
{
    if (!cms_table_exists('cms_api_tokens')) {
        return;
    }
    cms_db()->prepare('UPDATE cms_api_tokens SET is_active = 0 WHERE id = :id')->execute(['id' => $tokenId]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Granular role permissions
// ═══════════════════════════════════════════════════════════════════════════════

function cms_default_role_permissions(): array
{
    return [
        'super_admin' => ['posts' => [1,1,1,1], 'pages' => [1,1,1,1], 'media' => [1,1,1,1], 'seo' => [1,1,1,1], 'settings' => [1,1,1,1], 'users' => [1,1,1,1], 'forms' => [1,1,1,1], 'newsletter' => [1,1,1,1], 'api' => [1,1,1,1], 'cache' => [1,1,1,1]],
        'editor'      => ['posts' => [1,1,1,1], 'pages' => [1,1,1,1], 'media' => [1,1,1,0], 'seo' => [1,1,1,0], 'settings' => [1,0,0,0], 'users' => [0,0,0,0], 'forms' => [1,1,1,0], 'newsletter' => [1,1,1,0], 'api' => [0,0,0,0], 'cache' => [1,0,0,0]],
        'author'      => ['posts' => [1,1,1,0], 'pages' => [1,0,0,0], 'media' => [1,1,0,0], 'seo' => [1,0,0,0], 'settings' => [0,0,0,0], 'users' => [0,0,0,0], 'forms' => [1,0,0,0], 'newsletter' => [0,0,0,0], 'api' => [0,0,0,0], 'cache' => [0,0,0,0]],
        'viewer'      => ['posts' => [1,0,0,0], 'pages' => [1,0,0,0], 'media' => [1,0,0,0], 'seo' => [0,0,0,0], 'settings' => [0,0,0,0], 'users' => [0,0,0,0], 'forms' => [1,0,0,0], 'newsletter' => [0,0,0,0], 'api' => [0,0,0,0], 'cache' => [0,0,0,0]],
    ];
}

function cms_role_permission(string $role, string $module, string $action = 'view'): bool
{
    if (!cms_table_exists('cms_role_permissions')) {
        $defaults = cms_default_role_permissions();
        $perms    = $defaults[$role][$module] ?? [0, 0, 0, 0];
        $idx      = match ($action) { 'view' => 0, 'create' => 1, 'edit' => 2, 'delete' => 3, default => 0 };
        return (bool) ($perms[$idx] ?? 0);
    }
    $stmt = cms_db()->prepare('SELECT can_view, can_create, can_edit, can_delete FROM cms_role_permissions WHERE role = :r AND module = :m LIMIT 1');
    $stmt->execute(['r' => $role, 'm' => $module]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        $defaults = cms_default_role_permissions();
        $perms    = $defaults[$role][$module] ?? [0, 0, 0, 0];
        $idx      = match ($action) { 'view' => 0, 'create' => 1, 'edit' => 2, 'delete' => 3, default => 0 };
        return (bool) ($perms[$idx] ?? 0);
    }
    $col = match ($action) { 'create' => 'can_create', 'edit' => 'can_edit', 'delete' => 'can_delete', default => 'can_view' };
    return (bool) $row[$col];
}

function cms_current_admin_can(string $moduleOrRole, string $action = 'view'): bool
{
    $admin = cms_current_admin();
    if (!is_array($admin)) {
        return false;
    }

    if ($action === 'view' && array_key_exists($moduleOrRole, cms_roles())) {
        return cms_current_admin_has_role($moduleOrRole);
    }

    return cms_role_permission((string) ($admin['role'] ?? 'viewer'), $moduleOrRole, $action);
}

function cms_save_role_permissions(string $role, array $modulePerms): void
{
    if (!cms_table_exists('cms_role_permissions')) {
        return;
    }
    $stmt = cms_db()->prepare(
        'INSERT INTO cms_role_permissions (role, module, can_view, can_create, can_edit, can_delete)
         VALUES (:r, :m, :v, :c, :e, :d)
         ON DUPLICATE KEY UPDATE can_view=:v2, can_create=:c2, can_edit=:e2, can_delete=:d2, updated_at=NOW()'
    );
    foreach ($modulePerms as $module => $perms) {
        $v = (int) ($perms['view'] ?? 0);
        $c = (int) ($perms['create'] ?? 0);
        $e = (int) ($perms['edit'] ?? 0);
        $d = (int) ($perms['delete'] ?? 0);
        $stmt->execute(['r' => $role, 'm' => $module, 'v' => $v, 'c' => $c, 'e' => $e, 'd' => $d, 'v2' => $v, 'c2' => $c, 'e2' => $e, 'd2' => $d]);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Audit trail CSV export + change summary logging
// ═══════════════════════════════════════════════════════════════════════════════

function cms_log_activity_with_diff(string $action, string $entityType, int $entityId, string $label, string $changeSummary = ''): void
{
    if (!cms_table_exists('cms_activity_log')) {
        return;
    }
    $admin    = cms_current_admin();
    $adminId  = is_array($admin) ? (int) ($admin['id'] ?? 0) : 0;
    $adminName = is_array($admin) ? (string) ($admin['full_name'] ?? '') : '';
    $ip       = cms_client_ip();
    $ua       = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $hasDiff  = cms_column_exists('cms_activity_log', 'change_summary');
    $hasUA    = cms_column_exists('cms_activity_log', 'user_agent');
    if ($hasDiff && $hasUA) {
        cms_db()->prepare(
            'INSERT INTO cms_activity_log (admin_id, admin_name, action, entity_type, entity_id, entity_label, change_summary, ip_address, user_agent)
             VALUES (:aid, :an, :a, :et, :eid, :el, :cs, :ip, :ua)'
        )->execute(['aid' => $adminId ?: null, 'an' => $adminName, 'a' => $action, 'et' => $entityType, 'eid' => $entityId ?: null, 'el' => $label, 'cs' => $changeSummary, 'ip' => $ip, 'ua' => $ua]);
    } else {
        cms_log_activity($action, $entityType, $entityId, $label);
    }
}

function cms_export_activity_log_csv(int $limit = 5000): string
{
    if (!cms_table_exists('cms_activity_log')) {
        return "id,admin_name,action,entity_type,entity_label,ip_address,created_at\n";
    }
    $rows = cms_db()->query("SELECT id, admin_name, action, entity_type, entity_id, entity_label, ip_address, created_at FROM cms_activity_log ORDER BY id DESC LIMIT {$limit}")->fetchAll() ?: [];
    if ($rows === []) {
        return "id,admin_name,action,entity_type,entity_id,entity_label,ip_address,created_at\n";
    }
    $out = fopen('php://temp', 'r+');
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
    rewind($out);
    $csv = stream_get_contents($out);
    fclose($out);
    return (string) $csv;
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Page HTML caching system
// ═══════════════════════════════════════════════════════════════════════════════

function cms_cache_dir(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'pages';
}

function cms_cache_enabled(): bool
{
    $settings = cms_site_settings();
    return (bool) ($settings['page_cache_enabled'] ?? false);
}

function cms_cache_ttl(): int
{
    $settings = cms_site_settings();
    return max(60, (int) ($settings['page_cache_ttl'] ?? 3600));
}

function cms_cache_key(string $url): string
{
    return hash('sha256', $url);
}

function cms_cache_file(string $url): string
{
    return cms_cache_dir() . DIRECTORY_SEPARATOR . cms_cache_key($url) . '.html';
}

function cms_cache_get(string $url): ?string
{
    if (!cms_cache_enabled()) {
        return null;
    }
    $file = cms_cache_file($url);
    if (!file_exists($file)) {
        return null;
    }
    if (filemtime($file) + cms_cache_ttl() < time()) {
        @unlink($file);
        return null;
    }
    $content = @file_get_contents($file);
    return is_string($content) && $content !== '' ? $content : null;
}

function cms_cache_set(string $url, string $html): void
{
    if (!cms_cache_enabled()) {
        return;
    }
    $dir = cms_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = cms_cache_file($url);
    @file_put_contents($file, $html, LOCK_EX);
    if (cms_table_exists('cms_page_cache')) {
        $hash = cms_cache_key($url);
        $exp  = date('Y-m-d H:i:s', time() + cms_cache_ttl());
        cms_db()->prepare(
            'INSERT INTO cms_page_cache (url_hash, url, cached_at, expires_at, file_path) VALUES (:h, :u, NOW(), :e, :f)
             ON DUPLICATE KEY UPDATE cached_at=NOW(), expires_at=:e2, file_path=:f2'
        )->execute(['h' => $hash, 'u' => $url, 'e' => $exp, 'f' => $file, 'e2' => $exp, 'f2' => $file]);
    }
}

function cms_cache_purge_url(string $url): void
{
    $file = cms_cache_file($url);
    if (file_exists($file)) {
        @unlink($file);
    }
    if (cms_table_exists('cms_page_cache')) {
        cms_db()->prepare('DELETE FROM cms_page_cache WHERE url_hash = :h')->execute(['h' => cms_cache_key($url)]);
    }
}

function cms_cache_purge_all(): int
{
    $dir = cms_cache_dir();
    $count = 0;
    if (is_dir($dir)) {
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.html') ?: [] as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
    }
    if (cms_table_exists('cms_page_cache')) {
        cms_db()->exec('TRUNCATE TABLE cms_page_cache');
    }
    return $count;
}

function cms_cache_stats(): array
{
    $dir   = cms_cache_dir();
    $files = is_dir($dir) ? (glob($dir . DIRECTORY_SEPARATOR . '*.html') ?: []) : [];
    $size  = 0;
    foreach ($files as $f) {
        $size += (int) @filesize($f);
    }
    $dbCount = cms_table_exists('cms_page_cache')
        ? (int) cms_db()->query('SELECT COUNT(*) FROM cms_page_cache')->fetchColumn()
        : count($files);
    return ['file_count' => count($files), 'total_bytes' => $size, 'db_entries' => $dbCount];
}

function cms_cache_list(): array
{
    if (!cms_table_exists('cms_page_cache')) {
        return [];
    }
    return cms_db()->query('SELECT url, cached_at, expires_at FROM cms_page_cache ORDER BY cached_at DESC LIMIT 200')->fetchAll() ?: [];
}

if (!function_exists('cms_begin_public_page_cache')) {
    function cms_begin_public_page_cache(): bool
    {
        if (!cms_cache_enabled()) {
            return false;
        }

        if (cms_is_post()) {
            return false;
        }

        if (PHP_SAPI === 'cli') {
            return false;
        }

        if (function_exists('cms_is_logged_in') && cms_is_logged_in()) {
            return false;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $url = $host !== '' ? $scheme . '://' . $host . $uri : $uri;

        $cached = cms_cache_get($url);
        if (is_string($cached) && $cached !== '') {
            if (!headers_sent()) {
                header('X-A1-Cache: HIT');
            }
            echo $cached;
            return true;
        }

        ob_start(static function (string $html) use ($url): string {
            if ($html === '') {
                return $html;
            }

            $statusCode = function_exists('http_response_code') ? (int) http_response_code() : 200;
            if ($statusCode >= 200 && $statusCode < 400) {
                cms_cache_set($url, $html);
            }

            if (!headers_sent()) {
                header('X-A1-Cache: MISS');
            }

            return $html;
        });

        return false;
    }
}

if (!function_exists('cms_static_blog_featured_image_overrides')) {
    function cms_static_blog_featured_image_overrides(): array
    {
        $saved = cms_setting('static_blog_featured_images', []);
        return is_array($saved) ? $saved : [];
    }
}

if (!function_exists('cms_save_static_blog_featured_image')) {
    function cms_save_static_blog_featured_image(string $sourcePath, string $featuredImage): void
    {
        $normalizedPath = cms_normalize_static_file_path($sourcePath);
        $normalizedImage = trim($featuredImage);
        $overrides = cms_static_blog_featured_image_overrides();

        if ($normalizedImage === '') {
            unset($overrides[$normalizedPath]);
        } else {
            $overrides[$normalizedPath] = $normalizedImage;
        }

        cms_save_setting('static_blog_featured_images', cms_json_encode($overrides));

        foreach (cms_static_blog_sources() as $staticBlog) {
            if (($staticBlog['path'] ?? '') === $normalizedPath && !empty($staticBlog['slug'])) {
                cms_cache_purge_url(cms_blog_post_url((string) $staticBlog['slug']));
                break;
            }
        }
    }
}

if (!function_exists('cms_static_blog_file_to_source')) {
    function cms_static_blog_file_to_source(string $absolutePath): ?array
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $root = str_replace('\\', '/', cms_root());
        $absolutePath = str_replace('\\', '/', $absolutePath);
        if (!str_starts_with($absolutePath, $root . '/')) {
            return null;
        }

        $relativePath = substr($absolutePath, strlen($root) + 1);
        if (!is_string($relativePath) || $relativePath === '') {
            return null;
        }

        $html = (string) @file_get_contents($absolutePath);
        if ($html === '') {
            return null;
        }

        $slug = '';
        if (preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']https?://[^"\']+/blog/([^/"\']+)/?["\']#i', $html, $matches) === 1) {
            $slug = trim((string) $matches[1]);
        } elseif (preg_match('#<meta[^>]+property=["\']og:url["\'][^>]+content=["\']https?://[^"\']+/blog/([^/"\']+)/?["\']#i', $html, $matches) === 1) {
            $slug = trim((string) $matches[1]);
        } elseif (
            preg_match('#<link[^>]+rel=["\']canonical["\'][^>]+href=["\']https?://[^"\']+/([^/"\']+)/?["\']#i', $html, $matches) === 1
            && preg_match('#"name"\s*:\s*"Blog"#i', $html) === 1
        ) {
            $slug = trim((string) $matches[1]);
        }

        if ($slug === '' || $slug === 'blog') {
            return null;
        }

        $title = pathinfo($relativePath, PATHINFO_FILENAME);
        if (preg_match('#<title>(.*?)</title>#is', $html, $titleMatch) === 1) {
            $title = trim(html_entity_decode(strip_tags((string) $titleMatch[1]), ENT_QUOTES, 'UTF-8'));
        } elseif (preg_match('#<h1[^>]*>(.*?)</h1>#is', $html, $headingMatch) === 1) {
            $title = trim(html_entity_decode(strip_tags((string) $headingMatch[1]), ENT_QUOTES, 'UTF-8'));
        }

        $description = '';
        if (preg_match('#<meta[^>]+name=["\']description["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $descriptionMatch) === 1) {
            $description = trim(html_entity_decode((string) $descriptionMatch[1], ENT_QUOTES, 'UTF-8'));
        }

        $authorName = trim((string) cms_config('app.default_author', 'A1 Technovation'));
        if (preg_match('#<meta[^>]+name=["\']author["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $authorMatch) === 1) {
            $authorName = trim(html_entity_decode((string) $authorMatch[1], ENT_QUOTES, 'UTF-8')) ?: $authorName;
        }

        $publishedAt = '';
        if (preg_match('#<meta[^>]+property=["\']article:published_time["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $publishedMatch) === 1) {
            $publishedAt = trim((string) $publishedMatch[1]);
        } elseif (preg_match('#<time[^>]+datetime=["\']([^"\']+)["\']#i', $html, $timeMatch) === 1) {
            $publishedAt = trim((string) $timeMatch[1]);
        }

        $category = '';
        if (preg_match('#<meta[^>]+property=["\']article:section["\'][^>]+content=["\']([^"\']+)["\']#i', $html, $categoryMatch) === 1) {
            $category = trim(html_entity_decode((string) $categoryMatch[1], ENT_QUOTES, 'UTF-8'));
        }

        $featuredDetected = cms_extract_first_image_src($html);
        $overrides = cms_static_blog_featured_image_overrides();
        $featuredOverride = trim((string) ($overrides[$relativePath] ?? ''));

        return [
            'path' => $relativePath,
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $description,
            'meta_description' => $description,
            'author_name' => $authorName,
            'author_image' => cms_default_author_image_url(),
            'category' => $category,
            'published_at' => $publishedAt,
            'featured_image_detected' => $featuredDetected,
            'featured_image_override' => $featuredOverride,
            'featured_image' => $featuredOverride !== '' ? $featuredOverride : $featuredDetected,
        ];
    }
}

if (!function_exists('cms_static_blog_sources')) {
    function cms_static_blog_sources(): array
    {
        $sources = [];
        $candidates = [];

        foreach (glob(cms_root('pages/*.html')) ?: [] as $file) {
            $candidates[] = $file;
        }

        foreach (glob(cms_root('*.html')) ?: [] as $file) {
            $name = strtolower((string) basename($file));
            if (in_array($name, ['index.html', '404.html', 'sitemap.html'], true)) {
                continue;
            }
            $candidates[] = $file;
        }

        foreach ($candidates as $candidate) {
            $source = cms_static_blog_file_to_source((string) $candidate);
            if ($source === null) {
                continue;
            }

            $slug = (string) ($source['slug'] ?? '');
            if ($slug === '') {
                continue;
            }

            $sources[$slug] = $source;
        }

        uasort($sources, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['title'] ?? ''), (string) ($right['title'] ?? ''));
        });

        return array_values($sources);
    }
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: OG / Twitter card preview data helper
// ═══════════════════════════════════════════════════════════════════════════════

function cms_og_preview_data(array $meta, string $type = 'website'): array
{
    $settings  = cms_site_settings();
    $seoConfig = cms_seo_settings();
    return [
        'title'       => $meta['meta_title'] ?? $meta['title'] ?? $settings['site_name'] ?? 'A1 Technovation',
        'description' => $meta['meta_description'] ?? $seoConfig['default_meta_description'] ?? '',
        'image'       => $meta['og_image'] ?? $meta['featured_image'] ?? $seoConfig['og_default_image'] ?? '',
        'url'         => $meta['canonical_url'] ?? cms_url(''),
        'site_name'   => $settings['site_name'] ?? 'A1 Technovation',
        'type'        => $type,
        'twitter_card' => $seoConfig['twitter_card_type'] ?? 'summary_large_image',
        'twitter_site' => $seoConfig['twitter_site'] ?? '',
    ];
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Taxonomy archive helpers (category + tag pages)
// ═══════════════════════════════════════════════════════════════════════════════

function cms_get_published_posts_by_category(string $category, int $limit = 24, int $offset = 0): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }
    $stmt = cms_db()->prepare(
        "SELECT * FROM blog_posts WHERE status='published' AND category = :c ORDER BY published_at DESC LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue('c', $category);
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function cms_get_published_posts_by_tag(string $tag, int $limit = 24, int $offset = 0): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }
    $stmt = cms_db()->prepare(
        "SELECT * FROM blog_posts WHERE status='published' AND FIND_IN_SET(:t, REPLACE(tags, ', ', ',')) ORDER BY published_at DESC LIMIT :lim OFFSET :off"
    );
    $stmt->bindValue('t', $tag);
    $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
    $stmt->bindValue('off', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll() ?: [];
}

function cms_get_all_categories(): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }
    $rows = cms_db()->query("SELECT DISTINCT category, COUNT(*) as post_count FROM blog_posts WHERE status='published' AND category IS NOT NULL AND category != '' GROUP BY category ORDER BY post_count DESC")->fetchAll() ?: [];
    return $rows;
}

function cms_get_all_tags(): array
{
    if (!cms_table_exists('blog_posts')) {
        return [];
    }
    $rows = cms_db()->query("SELECT tags FROM blog_posts WHERE status='published' AND tags IS NOT NULL AND tags != ''")->fetchAll() ?: [];
    $counts = [];
    foreach ($rows as $row) {
        $tags = array_map('trim', explode(',', (string) $row['tags']));
        foreach ($tags as $tag) {
            if ($tag !== '') {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
    }
    arsort($counts);
    return array_map(fn($t, $c) => ['tag' => $t, 'post_count' => $c], array_keys($counts), $counts);
}

function cms_posts_count_by_category(string $category): int
{
    if (!cms_table_exists('blog_posts')) {
        return 0;
    }
    $stmt = cms_db()->prepare("SELECT COUNT(*) FROM blog_posts WHERE status='published' AND category = :c");
    $stmt->execute(['c' => $category]);
    return (int) $stmt->fetchColumn();
}

function cms_posts_count_by_tag(string $tag): int
{
    if (!cms_table_exists('blog_posts')) {
        return 0;
    }
    $stmt = cms_db()->prepare("SELECT COUNT(*) FROM blog_posts WHERE status='published' AND FIND_IN_SET(:t, REPLACE(tags, ', ', ','))");
    $stmt->execute(['t' => $tag]);
    return (int) $stmt->fetchColumn();
}

// ═══════════════════════════════════════════════════════════════════════════════
// FEATURE: Helper — client IP (used by newsletter + logging)
// ═══════════════════════════════════════════════════════════════════════════════

if (!function_exists('cms_client_ip')) {
    function cms_client_ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val !== '') {
                return substr(trim(explode(',', $val)[0]), 0, 45);
            }
        }
        return '';
    }
}
