<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Token auth ────────────────────────────────────────────────────────────────
function api_bearer_token(): string
{
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if (str_starts_with($auth, 'Bearer ')) {
        return substr($auth, 7);
    }
    return trim((string) ($_GET['token'] ?? ''));
}

function api_error(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

function api_json(mixed $data): never
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ── Route ─────────────────────────────────────────────────────────────────────
$resource = strtolower(trim((string) ($_GET['resource'] ?? '')));
$id       = (int) ($_GET['id'] ?? 0);
$method   = strtoupper($_SERVER['REQUEST_METHOD']);

if ($resource === 'newsletter_subscribe') {
    if ($method !== 'POST') {
        api_error(405, 'Use POST for newsletter subscribe.');
    }
    $body  = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) {
        $body = $_POST;
    }
    $email = trim((string) ($body['email'] ?? ''));
    $name  = trim((string) ($body['name'] ?? ''));
    $src   = trim((string) ($body['source'] ?? 'website'));
    $result = cms_newsletter_subscribe($email, $name, $src);
    http_response_code($result['ok'] ? 200 : 400);
    unset($result['token']);
    api_json($result);
}

$rawToken = api_bearer_token();
if ($rawToken === '') {
    api_error(401, 'Missing API token. Pass as Authorization: Bearer <token> header.');
}

$tokenRow = cms_api_verify_token($rawToken);
if ($tokenRow === null) {
    api_error(401, 'Invalid or expired token.');
}

$needsWrite  = in_array($method, ['POST', 'PUT', 'PATCH'], true);
$needsDelete = $method === 'DELETE';

if ($needsWrite && !cms_api_has_scope($tokenRow, 'write')) {
    api_error(403, 'Token does not have write scope.');
}
if ($needsDelete && !cms_api_has_scope($tokenRow, 'delete')) {
    api_error(403, 'Token does not have delete scope.');
}

// ── Resources ─────────────────────────────────────────────────────────────────

if ($resource === 'posts') {
    if ($method === 'GET') {
        if ($id > 0) {
            $post = cms_get_post($id);
            if (!is_array($post) || (string) ($post['status'] ?? '') !== 'published') {
                api_error(404, 'Post not found.');
            }
            unset($post['content_html']); // strip heavy field unless requested
            if (isset($_GET['full'])) {
                $post = cms_get_post($id);
            }
            api_json($post);
        }
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        if (!cms_table_exists('blog_posts')) {
            api_json(['data' => [], 'total' => 0, 'page' => $page, 'limit' => $limit]);
        }
        $posts = cms_db()->query("SELECT id, title, slug, status, category, tags, author_name, featured_image, excerpt, published_at, created_at FROM blog_posts WHERE status='published' ORDER BY published_at DESC LIMIT {$limit} OFFSET {$offset}")->fetchAll();
        $total = (int) cms_db()->query("SELECT COUNT(*) FROM blog_posts WHERE status='published'")->fetchColumn();
        api_json(['data' => $posts, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }
    api_error(405, 'Method not allowed on posts (read-only via API).');
}

if ($resource === 'pages') {
    if ($method === 'GET') {
        if ($id > 0) {
            $p = cms_get_page($id);
            if (!is_array($p) || $p['status'] !== 'published') {
                api_error(404, 'Page not found.');
            }
            api_json($p);
        }
        $pages = cms_table_exists('cms_pages')
            ? cms_db()->query("SELECT id, title, slug, page_type, status, excerpt, meta_title, meta_description, published_at FROM cms_pages WHERE status='published' ORDER BY sort_order, title")->fetchAll()
            : [];
        api_json(['data' => $pages, 'total' => count($pages)]);
    }
    api_error(405, 'Method not allowed.');
}

if ($resource === 'media') {
    if ($method === 'GET') {
        $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
        $offset = (max(1, (int) ($_GET['page'] ?? 1)) - 1) * $limit;
        $media  = cms_table_exists('media_files')
            ? cms_db()->query("SELECT id, title, alt_text, file_path, mime_type, file_size, created_at FROM media_files ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}")->fetchAll()
            : [];
        $total = (int) (cms_table_exists('media_files') ? cms_db()->query('SELECT COUNT(*) FROM media_files')->fetchColumn() : 0);
        api_json(['data' => $media, 'total' => $total]);
    }
    api_error(405, 'Method not allowed.');
}

if ($resource === 'categories') {
    api_json(['data' => cms_get_all_categories()]);
}

if ($resource === 'tags') {
    api_json(['data' => cms_get_all_tags()]);
}

if ($resource === 'settings') {
    $public = cms_seo_settings();
    $site   = cms_site_settings();
    api_json([
        'site_name'          => $site['site_name'] ?? 'A1 Technovation',
        'canonical_base_url' => $public['canonical_base_url'] ?? cms_url(''),
        'twitter_site'       => $public['twitter_site'] ?? '',
        'og_default_image'   => $public['og_default_image'] ?? '',
    ]);
}

api_error(404, "Unknown resource '{$resource}'. Available: posts, pages, media, categories, tags, settings, newsletter_subscribe.");
