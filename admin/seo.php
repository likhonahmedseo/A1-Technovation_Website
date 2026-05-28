<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$seoSettings = cms_seo_settings();

// ── AJAX: PageSpeed Insights proxy ──────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'pagespeed') {
    header('Content-Type: application/json; charset=utf-8');
    $url      = trim((string) ($_POST['url'] ?? ''));
    $strategy = ($_POST['strategy'] ?? 'mobile') === 'desktop' ? 'desktop' : 'mobile';
    $apiKey   = trim((string) ($seoSettings['pagespeed_api_key'] ?? ''));

    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['error' => 'Please enter a valid URL.']);
        exit;
    }

    $apiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed'
        . '?url=' . urlencode($url)
        . '&strategy=' . $strategy
        . '&category=performance&category=accessibility&category=best-practices&category=seo';
    if ($apiKey !== '') {
        $apiUrl .= '&key=' . urlencode($apiKey);
    }

    $ctx    = stream_context_create(['http' => ['timeout' => 45, 'ignore_errors' => true, 'user_agent' => 'CMS-SEO-Tool/1.0']]);
    $raw    = @file_get_contents($apiUrl, false, $ctx);
    $status = $http_response_header[0] ?? '';

    if ($raw === false) {
        echo json_encode(['error' => 'Could not reach the PageSpeed API. Check server outbound access.']);
        exit;
    }

    // Pass response (including API errors) straight through
    echo $raw;
    exit;
}

// ── AJAX: Save single page meta ───────────────────────────────────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'save_meta') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        cms_require_csrf();
        $target          = trim((string) ($_POST['seo_target'] ?? ''));
        $metaTitle       = trim((string) ($_POST['meta_title'] ?? ''));
        $metaDescription = trim((string) ($_POST['meta_description'] ?? ''));
        $canonicalUrl    = trim((string) ($_POST['canonical_url'] ?? ''));
        if ($target === '') { throw new InvalidArgumentException('Missing target.'); }
        [$targetType, $targetId] = array_pad(explode(':', $target, 2), 2, '');
        if ($targetType === 'post') {
            $post = cms_get_post((int) $targetId);
            if (!is_array($post)) { throw new RuntimeException('Blog post not found.'); }
            cms_db()->prepare('UPDATE blog_posts SET meta_title=:t, meta_description=:d WHERE id=:id')
                ->execute(['t' => $metaTitle, 'd' => $metaDescription, 'id' => (int) $targetId]);
            cms_set_seo_page_override(cms_blog_post_url((string) $post['slug']), [
                'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'canonical_url' => $canonicalUrl,
            ]);
        } elseif ($targetType === 'page') {
            cms_db()->prepare('UPDATE cms_pages SET meta_title=:t, meta_description=:d, canonical_url=:c WHERE id=:id')
                ->execute(['t' => $metaTitle, 'd' => $metaDescription, 'c' => $canonicalUrl, 'id' => (int) $targetId]);
            $row = cms_db()->prepare('SELECT slug, page_type FROM cms_pages WHERE id=:id LIMIT 1');
            $row->execute(['id' => (int) $targetId]);
            $pageRow = $row->fetch();
            if (is_array($pageRow)) {
                cms_set_seo_page_override(cms_page_url((string) $pageRow['slug'], (string) $pageRow['page_type']), [
                    'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'canonical_url' => $canonicalUrl,
                ]);
            }
        } elseif ($targetType === 'static') {
            $path       = rawurldecode($targetId);
            $staticPage = cms_get_static_page_admin($path);
            cms_apply_static_page_seo($path, $metaTitle, $metaDescription, $canonicalUrl);
            cms_set_seo_page_override((string) $staticPage['url'], [
                'meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'canonical_url' => $canonicalUrl,
            ]);
        } else {
            throw new InvalidArgumentException('Unsupported target type.');
        }
        cms_log_activity('save_target_seo', 'seo');
        echo json_encode(['ok' => true]);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$pageTitle    = 'SEO Manager';
$activeNav    = 'seo';
$errorMessage = '';
$redirects    = cms_get_redirects();

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'save_seo') {
            $seoSettings = [
                'default_meta_title'       => trim((string) cms_post('default_meta_title')),
                'default_meta_description' => trim((string) cms_post('default_meta_description')),
                'og_default_image'         => trim((string) cms_post('og_default_image')),
                'google_analytics_id'      => trim((string) cms_post('google_analytics_id')),
                'google_search_console'    => trim((string) cms_post('google_search_console')),
                'bing_verification'        => trim((string) cms_post('bing_verification')),
                'twitter_card_type'        => trim((string) cms_post('twitter_card_type')),
                'twitter_site'             => trim((string) cms_post('twitter_site')),
                'canonical_base_url'       => rtrim(trim((string) cms_post('canonical_base_url')), '/'),
                'schema_org_json'          => trim((string) cms_post('schema_org_json')),
                'pagespeed_api_key'        => trim((string) cms_post('pagespeed_api_key')),
                'robots_txt'               => (string) cms_post('robots_txt'),
                'maintenance_mode'         => cms_post('maintenance_mode') ? '1' : '0',
                'maintenance_message'      => trim((string) cms_post('maintenance_message')),
            ];
            cms_save_seo_settings($seoSettings);
            if (cms_post('write_robots')) {
                cms_write_robots_txt($seoSettings['robots_txt']);
            }
            cms_log_activity('save_seo_settings', 'seo');
            cms_flash_set('success', 'SEO settings saved.');
            cms_redirect('admin/seo.php');
        }

        if ($action === 'generate_sitemap') {
            cms_write_sitemap();
            cms_log_activity('generate_sitemap', 'seo');
            cms_flash_set('success', 'sitemap.xml regenerated successfully.');
            cms_redirect('admin/seo.php');
        }

        if ($action === 'generate_llms') {
            cms_write_llms_txt();
            cms_log_activity('generate_llms_txt', 'seo');
            cms_flash_set('success', 'llms.txt regenerated successfully.');
            cms_redirect('admin/seo.php#llms');
        }

        if ($action === 'save_target_seo') {
            $target = trim((string) cms_post('seo_target'));
            $metaTitle = trim((string) cms_post('target_meta_title'));
            $metaDescription = trim((string) cms_post('target_meta_description'));
            $canonicalUrl = trim((string) cms_post('target_canonical_url'));

            if ($target === '') {
                throw new InvalidArgumentException('Choose a page or post to update.');
            }

            [$targetType, $targetId] = array_pad(explode(':', $target, 2), 2, '');
            if ($targetType === 'post') {
                $post = cms_get_post((int) $targetId);
                if (!is_array($post)) {
                    throw new RuntimeException('Selected blog post was not found.');
                }
                $statement = cms_db()->prepare('UPDATE blog_posts SET meta_title = :meta_title, meta_description = :meta_description WHERE id = :id');
                $statement->execute(['meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'id' => (int) $targetId]);
                cms_set_seo_page_override(cms_blog_post_url((string) $post['slug']), [
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'canonical_url' => $canonicalUrl,
                ]);
            } elseif ($targetType === 'page') {
                $statement = cms_db()->prepare('UPDATE cms_pages SET meta_title = :meta_title, meta_description = :meta_description, canonical_url = :canonical_url WHERE id = :id');
                $statement->execute(['meta_title' => $metaTitle, 'meta_description' => $metaDescription, 'canonical_url' => $canonicalUrl, 'id' => (int) $targetId]);
                $page = cms_db()->prepare('SELECT slug, page_type FROM cms_pages WHERE id = :id LIMIT 1');
                $page->execute(['id' => (int) $targetId]);
                $pageRow = $page->fetch();
                if (is_array($pageRow)) {
                    cms_set_seo_page_override(cms_page_url((string) $pageRow['slug'], (string) $pageRow['page_type']), [
                        'meta_title' => $metaTitle,
                        'meta_description' => $metaDescription,
                        'canonical_url' => $canonicalUrl,
                    ]);
                }
            } elseif ($targetType === 'static') {
                $path = rawurldecode($targetId);
                $staticPage = cms_get_static_page_admin($path);
                cms_apply_static_page_seo($path, $metaTitle, $metaDescription, $canonicalUrl);
                cms_set_seo_page_override((string) $staticPage['url'], [
                    'meta_title' => $metaTitle,
                    'meta_description' => $metaDescription,
                    'canonical_url' => $canonicalUrl,
                ]);
            } else {
                throw new InvalidArgumentException('Unsupported SEO target.');
            }

            cms_log_activity('save_target_seo', 'seo');
            cms_flash_set('success', 'Page SEO fields saved.');
            cms_redirect('admin/seo.php#page-seo');
        }

        if ($action === 'add_redirect') {
            $from = trim((string) cms_post('redirect_from'));
            $to   = trim((string) cms_post('redirect_to'));
            $code = (string) cms_post('redirect_code') === '302' ? '302' : '301';
            if ($from === '' || $to === '') {
                throw new InvalidArgumentException('From and To fields are required.');
            }
            $from      = '/' . ltrim($from, '/');
            $redirects[] = ['from' => $from, 'to' => $to, 'code' => $code, 'created_at' => date('Y-m-d H:i')];
            cms_save_redirects($redirects);
            if (cms_post('write_htaccess')) {
                cms_write_htaccess_redirects($redirects);
            }
            cms_log_activity('add_redirect', 'seo');
            cms_flash_set('success', "Redirect {$from} → {$to} added.");
            cms_redirect('admin/seo.php#redirects');
        }

        if ($action === 'delete_redirect') {
            $idx = (int) cms_post('redirect_index');
            array_splice($redirects, $idx, 1);
            cms_save_redirects($redirects);
            if (cms_post('write_htaccess')) {
                cms_write_htaccess_redirects($redirects);
            }
            cms_log_activity('delete_redirect', 'seo');
            cms_flash_set('success', 'Redirect deleted.');
            cms_redirect('admin/seo.php#redirects');
        }

        if ($action === 'write_redirects_htaccess') {
            cms_write_htaccess_redirects($redirects);
            cms_log_activity('write_redirects_htaccess', 'seo');
            cms_flash_set('success', '.htaccess redirect block updated.');
            cms_redirect('admin/seo.php#redirects');
        }

    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

// ── Sitemap stats ─────────────────────────────────────────────────────────────
$sitemapPath    = cms_root('sitemap.xml');
$sitemapExists  = file_exists($sitemapPath);
$sitemapUpdated = $sitemapExists ? date('M j, Y g:i A', (int) filemtime($sitemapPath)) : null;
$sitemapUrls    = $sitemapExists ? substr_count((string) file_get_contents($sitemapPath), '<url>') : 0;
$llmsPath       = cms_root('llms.txt');
$llmsExists     = file_exists($llmsPath);
$llmsUpdated    = $llmsExists ? date('M j, Y g:i A', (int) filemtime($llmsPath)) : null;
$llmsSize       = $llmsExists ? (int) filesize($llmsPath) : 0;

// ── Checklist data ────────────────────────────────────────────────────────────
$posts       = cms_get_published_posts(500);
$noMeta      = array_filter($posts, fn($p) => trim((string) ($p['meta_description'] ?? '')) === '');
$noImg       = array_filter($posts, fn($p) => trim((string) ($p['featured_image'] ?? '')) === '');
$titleShort  = array_filter($posts, fn($p) => mb_strlen(trim((string) ($p['meta_title'] ?? $p['title'] ?? ''))) < 30);
$titleLong   = array_filter($posts, fn($p) => mb_strlen(trim((string) ($p['meta_title'] ?? $p['title'] ?? ''))) > 60);
$descShort   = array_filter($posts, function ($p) {
    $description = trim((string) ($p['meta_description'] ?? ''));
    return $description !== '' && mb_strlen($description) < 120;
});
$descLong    = array_filter($posts, fn($p) => mb_strlen(trim((string) ($p['meta_description'] ?? ''))) > 160);
$pages       = cms_table_exists('cms_pages') ? cms_db()->query("SELECT * FROM cms_pages WHERE status='published'")->fetchAll() : [];
$pagesNoMeta = array_filter($pages, fn($p) => trim((string) ($p['meta_description'] ?? '')) === '');
$staticPages = cms_get_static_pages_admin();

// ── URL list for dropdowns ────────────────────────────────────────────────────
$urlOptions = [];
$seenUrls = [];
$addUrlOption = static function (string $label, string $url) use (&$urlOptions, &$seenUrls): void {
    $url = trim($url);
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
        return;
    }

    $key = strtolower(rtrim($url, '/'));
    if ($key === '') {
        $key = strtolower($url);
    }
    if (isset($seenUrls[$key])) {
        return;
    }

    $seenUrls[$key] = true;
    $urlOptions[] = [
        'label' => $label !== '' ? $label : $url,
        'url' => $url,
    ];
};
$labelFromUrl = static function (string $url): string {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $path = trim($path, '/');
    if ($path === '') {
        return 'Homepage';
    }

    return ucwords(str_replace(['-', '_', '/'], [' ', ' ', ' / '], preg_replace('/\.html$/', '', $path) ?? $path));
};

$addUrlOption('Homepage', cms_url(''));
$addUrlOption('Blog index', cms_blog_index_url());

foreach ($posts as $p) {
    $addUrlOption('Blog: ' . (string) $p['title'], cms_blog_post_url((string) $p['slug']));
}

foreach ($pages as $p) {
    $addUrlOption('CMS page: ' . (string) $p['title'], cms_page_url((string) $p['slug'], (string) $p['page_type']));
}

foreach ($staticPages as $staticPage) {
    $path = (string) ($staticPage['path'] ?? '');
    if (in_array($path, ['index.html', '404.html', 'sitemap.html'], true)) {
        continue;
    }
    $title = trim((string) ($staticPage['title'] ?? ''));
    $addUrlOption('Static page: ' . ($title !== '' ? $title : $labelFromUrl((string) ($staticPage['url'] ?? ''))), (string) ($staticPage['url'] ?? ''));
}

if ($sitemapExists) {
    $sitemapXml = (string) file_get_contents($sitemapPath);
    if (preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#i', $sitemapXml, $matches)) {
        foreach ($matches[1] as $loc) {
            $url = html_entity_decode(trim((string) $loc), ENT_QUOTES, 'UTF-8');
            $addUrlOption('Sitemap: ' . $labelFromUrl($url), $url);
        }
    }
}

usort($urlOptions, static function (array $a, array $b): int {
    if (($a['url'] ?? '') === cms_url('')) {
        return -1;
    }
    if (($b['url'] ?? '') === cms_url('')) {
        return 1;
    }

    return strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
});

$seoTargetOptions = [];
foreach ($staticPages as $staticPage) {
    $path = (string) ($staticPage['path'] ?? '');
    if ($path === '404.html') {
        continue;
    }
    $html = is_file(cms_root($path)) ? (string) file_get_contents(cms_root($path)) : '';
    $url = (string) ($staticPage['url'] ?? '');
    $override = cms_seo_override_for_url($url);
    $seoTargetOptions[] = [
        'value' => 'static:' . rawurlencode($path),
        'label' => 'Static page: ' . (string) ($staticPage['title'] ?? $path),
        'title' => (string) ($override['meta_title'] ?: cms_static_page_title($html, $path)),
        'description' => (string) ($override['meta_description'] ?: cms_static_page_meta_value($html, 'description')),
        'canonical' => (string) ($override['canonical_url'] ?: cms_static_page_canonical($html, $url)),
        'url' => $url,
    ];
}

foreach ($posts as $post) {
    $url = cms_blog_post_url((string) $post['slug']);
    $override = cms_seo_override_for_url($url);
    $seoTargetOptions[] = [
        'value' => 'post:' . (int) $post['id'],
        'label' => 'Blog post: ' . (string) $post['title'],
        'title' => (string) ($override['meta_title'] ?: $post['meta_title'] ?: $post['title']),
        'description' => (string) ($override['meta_description'] ?: $post['meta_description'] ?: $post['excerpt']),
        'canonical' => (string) ($override['canonical_url'] ?: $url),
        'url' => $url,
    ];
}

foreach ($pages as $page) {
    $url = cms_page_url((string) $page['slug'], (string) $page['page_type']);
    $override = cms_seo_override_for_url($url);
    $seoTargetOptions[] = [
        'value' => 'page:' . (int) $page['id'],
        'label' => 'CMS page: ' . (string) $page['title'],
        'title' => (string) ($override['meta_title'] ?: $page['meta_title'] ?: $page['title']),
        'description' => (string) ($override['meta_description'] ?: $page['meta_description'] ?: $page['excerpt']),
        'canonical' => (string) ($override['canonical_url'] ?: $page['canonical_url'] ?: $url),
        'url' => $url,
    ];
}

usort($seoTargetOptions, static fn (array $a, array $b): int => strcasecmp((string) $a['label'], (string) $b['label']));

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-magnifying-glass-chart"></i> SEO</span>
    <h1 class="admin-page-title">SEO Manager</h1>
    <p class="admin-page-copy">Meta tags, Open Graph, Twitter Cards, Schema.org, Rich Results validation, PageSpeed Insights, redirects, sitemap, and robots.txt — all from one place.</p>
  </div>
</section>

<?php if ($errorMessage !== ''): ?><div class="flash flash-error"><?= cms_e($errorMessage) ?></div><?php endif; ?>

<section class="content-grid">

  <!-- ══════════════════════════════════════════════════════════
       SEO Settings form
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card" style="grid-column:1 / -1">
    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="save_seo" />

      <!-- Default meta tags -->
      <h3 style="margin:0 0 16px"><i class="fas fa-tags"></i> Default meta tags</h3>
      <div class="form-grid two-col">
        <div class="field">
          <label for="seo_meta_title">Default meta title <span class="muted" style="font-weight:400;font-size:.8rem">(50–60 chars ideal)</span></label>
          <input id="seo_meta_title" type="text" name="default_meta_title" value="<?= cms_e((string) $seoSettings['default_meta_title']) ?>" placeholder="Site name — Tagline" maxlength="70" />
          <p class="helper-text">Used when a page or post has no custom meta title set.</p>
        </div>
        <div class="field">
          <label for="seo_og_image">Default OG image URL</label>
          <input id="seo_og_image" type="url" name="og_default_image" value="<?= cms_e((string) $seoSettings['og_default_image']) ?>" placeholder="https://..." />
          <p class="helper-text">Open Graph image for social sharing when no featured image is set.</p>
        </div>
      </div>
      <div class="field">
        <label for="seo_meta_desc">Default meta description <span class="muted" style="font-weight:400;font-size:.8rem">(120–160 chars ideal)</span></label>
        <textarea id="seo_meta_desc" name="default_meta_description" rows="2" maxlength="200"><?= cms_e((string) $seoSettings['default_meta_description']) ?></textarea>
      </div>
      <div class="field">
        <label for="seo_canonical">Canonical base URL</label>
        <input id="seo_canonical" type="url" name="canonical_base_url" value="<?= cms_e((string) $seoSettings['canonical_base_url']) ?>" placeholder="https://a1technovation.com" />
        <p class="helper-text">Used to build canonical link tags. Leave blank to auto-detect.</p>
      </div>

      <!-- Google integrations -->
      <h3 style="margin:20px 0 12px"><i class="fab fa-google"></i> Google integrations</h3>
      <div class="form-grid two-col">
        <div class="field">
          <label for="seo_ga_id">Google Analytics 4 ID</label>
          <input id="seo_ga_id" type="text" name="google_analytics_id" value="<?= cms_e((string) $seoSettings['google_analytics_id']) ?>" placeholder="G-XXXXXXXXXX" />
        </div>
        <div class="field">
          <label for="seo_gsc">Google Search Console verification</label>
          <input id="seo_gsc" type="text" name="google_search_console" value="<?= cms_e((string) $seoSettings['google_search_console']) ?>" placeholder="google-site-verification=XXXXX or the content value" />
          <p class="helper-text">Paste the full meta tag, the name=value pair, or just the content value from GSC.</p>
        </div>
      </div>
      <div class="field">
        <label for="seo_psi_key">PageSpeed Insights API key <span class="muted" style="font-weight:400;font-size:.8rem">(optional — increases rate limit)</span></label>
        <input id="seo_psi_key" type="text" name="pagespeed_api_key" value="<?= cms_e((string) $seoSettings['pagespeed_api_key']) ?>" placeholder="AIzaSy..." />
        <p class="helper-text">Get a free key at <a href="https://developers.google.com/speed/docs/insights/v5/get-started" target="_blank" rel="noopener">Google Cloud Console</a>. Works without a key at lower rate limits.</p>
      </div>

      <!-- Bing + Twitter/X -->
      <h3 style="margin:20px 0 12px"><i class="fab fa-twitter"></i> Twitter / X Card &amp; Bing</h3>
      <div class="form-grid two-col">
        <div class="field">
          <label for="seo_bing">Bing Webmaster verification</label>
          <input id="seo_bing" type="text" name="bing_verification" value="<?= cms_e((string) $seoSettings['bing_verification']) ?>" placeholder="msvalidate.01=XXXXX or the content value" />
          <p class="helper-text">Paste the full meta tag, the name=value pair, or just the content value from Bing Webmaster Tools.</p>
        </div>
        <div class="field">
          <label for="seo_twitter_site">Twitter / X @username</label>
          <input id="seo_twitter_site" type="text" name="twitter_site" value="<?= cms_e((string) $seoSettings['twitter_site']) ?>" placeholder="@a1technovation" />
        </div>
      </div>
      <div class="field" style="max-width:320px">
        <label for="seo_twitter_card">Twitter card type</label>
        <select id="seo_twitter_card" name="twitter_card_type">
          <?php foreach (['summary_large_image' => 'Summary Large Image', 'summary' => 'Summary', 'app' => 'App', 'player' => 'Player'] as $val => $label): ?>
            <option value="<?= cms_e($val) ?>" <?= ($seoSettings['twitter_card_type'] ?? 'summary_large_image') === $val ? 'selected' : '' ?>><?= cms_e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Schema.org JSON-LD -->
      <h3 style="margin:20px 0 4px"><i class="fas fa-code"></i> Global Schema.org / JSON-LD</h3>
      <p class="helper-text" style="margin:0 0 12px">Output in <code>&lt;head&gt;</code> on every page. Use for your Organization or WebSite markup. Per-page schema can be set inside the page editor.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
        <select id="schema-template-select" style="flex:1;min-width:180px;max-width:280px">
          <option value="">— Insert a template —</option>
          <option value="organization">Organization</option>
          <option value="localbusiness">LocalBusiness</option>
          <option value="website">WebSite (with SearchAction)</option>
          <option value="service">Service</option>
          <option value="article">Article</option>
          <option value="faqpage">FAQPage</option>
          <option value="person">Person</option>
          <option value="breadcrumb">BreadcrumbList</option>
        </select>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-schema-insert"><i class="fas fa-wand-magic-sparkles"></i> Insert template</button>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-schema-format"><i class="fas fa-align-left"></i> Format JSON</button>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-schema-validate"><i class="fas fa-check-circle"></i> Validate JSON</button>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-schema-clear" style="margin-left:auto"><i class="fas fa-trash"></i> Clear</button>
      </div>
      <div id="schema-validate-msg" style="display:none;padding:8px 12px;border-radius:6px;font-size:.83rem;margin-bottom:8px"></div>
      <div class="field" style="margin:0">
        <textarea id="seo_schema" name="schema_org_json" rows="10" style="font-family:monospace;font-size:.82rem;white-space:pre"><?= cms_e((string) $seoSettings['schema_org_json']) ?></textarea>
      </div>

      <!-- robots.txt -->
      <h3 style="margin:20px 0 12px"><i class="fas fa-robot"></i> robots.txt</h3>
      <div class="field">
        <label for="seo_robots">robots.txt content</label>
        <textarea id="seo_robots" name="robots_txt" rows="6" style="font-family:monospace"><?= cms_e((string) $seoSettings['robots_txt']) ?></textarea>
      </div>
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer">
        <input type="checkbox" name="write_robots" value="1" checked />
        Write robots.txt to disk when saving
      </label>

      <!-- Maintenance mode -->
      <h3 style="margin:20px 0 12px"><i class="fas fa-tools"></i> Maintenance mode</h3>
      <label style="display:flex;align-items:center;gap:8px;font-weight:400;cursor:pointer;margin-bottom:12px">
        <input type="checkbox" name="maintenance_mode" value="1" <?= ($seoSettings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?> />
        Enable maintenance mode <span class="status-badge status-draft" style="font-size:.72rem">visitors see the message below</span>
      </label>
      <div class="field">
        <label for="seo_maintenance_msg">Maintenance message</label>
        <textarea id="seo_maintenance_msg" name="maintenance_message" rows="2"><?= cms_e((string) $seoSettings['maintenance_message']) ?></textarea>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save SEO Settings</button>
    </form>
  </article>

  <!-- ══════════════════════════════════════════════════════════
       Page Meta Manager (sub-tabbed)
  ══════════════════════════════════════════════════════════ -->
  <?php
    $metaStatic = array_values(array_filter($seoTargetOptions, fn($t) => str_starts_with((string)$t['value'], 'static:')));
    $metaCms    = array_values(array_filter($seoTargetOptions, fn($t) => str_starts_with((string)$t['value'], 'page:')));
    $metaBlog   = array_values(array_filter($seoTargetOptions, fn($t) => str_starts_with((string)$t['value'], 'post:')));
  ?>
  <article class="admin-card" style="grid-column:1 / -1" id="page-seo">
    <div class="toolbar" style="margin-bottom:16px">
      <div>
        <h3 style="margin:0 0 4px"><i class="fas fa-pen-to-square"></i> Page Meta Manager</h3>
        <p class="helper-text" style="margin:0">Edit SEO titles and meta descriptions for every page and blog post. Changes save instantly without a page reload.</p>
      </div>
    </div>

    <div class="seo-subtab-bar">
      <button class="seo-subtab active" data-panel="seo-panel-static">
        <i class="fas fa-file-code"></i> Static Pages <span class="seo-subtab-count"><?= count($metaStatic) ?></span>
      </button>
      <button class="seo-subtab" data-panel="seo-panel-cms">
        <i class="fas fa-pager"></i> CMS Pages <span class="seo-subtab-count"><?= count($metaCms) ?></span>
      </button>
      <button class="seo-subtab" data-panel="seo-panel-blog">
        <i class="fas fa-newspaper"></i> Blog Posts <span class="seo-subtab-count"><?= count($metaBlog) ?></span>
      </button>
    </div>

    <?php
    $seoRowHtml = static function (array $t, string $namePrefix): string {
        $name         = cms_e(str_replace($namePrefix, '', (string)$t['label']));
        $url          = cms_e((string)$t['url']);
        $targetAttr   = cms_e((string)$t['value']);
        $title        = (string)$t['title'];
        $desc         = (string)$t['description'];
        $titleAttr    = cms_e($title);
        $descEsc      = cms_e($desc);
        $titleDisplay = $title !== '' ? cms_e($title) : '<em class="seo-not-set">Not set</em>';
        $descDisplay  = $desc  !== '' ? cms_e($desc)  : '<em class="seo-not-set">Not set</em>';
        return
          '<tr class="seo-meta-row" data-target="' . $targetAttr . '">'
          . '<td class="seo-page-name"><a href="' . $url . '" target="_blank" rel="noopener">' . $name . ' <i class="fas fa-arrow-up-right-from-square" style="font-size:.7rem;opacity:.5"></i></a></td>'
          . '<td class="seo-td-title">'
            . '<div class="seo-view-field seo-view-title">' . $titleDisplay . '</div>'
            . '<div class="seo-edit-field" style="display:none">'
              . '<input type="text" class="seo-title-input" value="' . $titleAttr . '" maxlength="190" placeholder="SEO title (50–60 chars)" />'
              . '<span class="seo-char-hint title-hint"></span>'
            . '</div>'
          . '</td>'
          . '<td class="seo-td-desc">'
            . '<div class="seo-view-field seo-view-desc">' . $descDisplay . '</div>'
            . '<div class="seo-edit-field" style="display:none">'
              . '<textarea class="seo-desc-input" maxlength="255" rows="3" placeholder="Meta description (120–160 chars)">' . $descEsc . '</textarea>'
              . '<span class="seo-char-hint desc-hint"></span>'
            . '</div>'
          . '</td>'
          . '<td class="seo-td-action">'
            . '<div class="seo-view-actions"><button type="button" class="btn btn-sm btn-secondary seo-edit-btn"><i class="fas fa-pencil"></i> Edit</button></div>'
            . '<div class="seo-edit-actions" style="display:none">'
              . '<button type="button" class="btn btn-sm btn-primary seo-save-btn"><i class="fas fa-floppy-disk"></i> Save</button> '
              . '<button type="button" class="btn btn-sm btn-ghost seo-cancel-btn">Cancel</button>'
            . '</div>'
            . '<span class="seo-row-msg"></span>'
          . '</td>'
          . '</tr>';
    };

    $renderPanel = static function (array $items, string $panelId, string $colLabel, string $namePrefix, bool $hidden) use ($seoRowHtml): void {
        $style = $hidden ? ' style="display:none"' : '';
        echo '<div class="seo-tab-panel" id="' . cms_e($panelId) . '"' . $style . '>';
        if (empty($items)) {
            echo '<p class="helper-text" style="padding:24px 0;text-align:center">No ' . cms_e(strtolower($colLabel)) . ' found.</p>';
        } else {
            echo '<div class="seo-meta-table-wrap"><table class="seo-meta-table">';
            echo '<thead><tr><th>' . cms_e($colLabel) . '</th><th>SEO Title <span class="muted">(50–60 chars)</span></th><th>Meta Description <span class="muted">(120–160 chars)</span></th><th style="width:130px"></th></tr></thead>';
            echo '<tbody>';
            foreach ($items as $t) {
                echo $seoRowHtml($t, $namePrefix);
            }
            echo '</tbody></table></div>';
        }
        echo '</div>';
    };

    $renderPanel($metaStatic, 'seo-panel-static', 'Static Pages', 'Static page: ', false);
    $renderPanel($metaCms,    'seo-panel-cms',    'CMS Pages',    'CMS page: ',    true);
    $renderPanel($metaBlog,   'seo-panel-blog',   'Blog Posts',   'Blog post: ',   true);
    ?>

  </article>

  <!-- ══════════════════════════════════════════════════════════
       Rich Results Validator
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card" style="grid-column:1 / -1" id="rich-results">
    <h3 style="margin:0 0 6px"><i class="fas fa-magnifying-glass"></i> Rich Results &amp; Schema Validator</h3>
    <p class="helper-text" style="margin:0 0 20px">Pick a page from the dropdown or type any URL, then open it in Google's testing tools.</p>

    <div class="form-grid" style="gap:12px">
      <div class="form-grid two-col" style="gap:12px">
        <div class="field" style="margin:0">
          <label for="rr-select">Pick a page</label>
          <select id="rr-select">
            <option value="">— select a page —</option>
            <?php foreach ($urlOptions as $opt): ?>
              <option value="<?= cms_e($opt['url']) ?>"><?= cms_e($opt['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin:0">
          <label for="rr-url">Or enter any URL</label>
          <input id="rr-url" type="url" placeholder="https://a1technovation.com/blog/my-post" />
        </div>
      </div>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button type="button" class="btn btn-primary" id="btn-rr-google">
          <i class="fab fa-google"></i> Google Rich Results Test
        </button>
        <button type="button" class="btn btn-secondary" id="btn-rr-schema">
          <i class="fas fa-code"></i> Schema Markup Validator
        </button>
        <button type="button" class="btn btn-secondary" id="btn-rr-preview">
          <i class="fas fa-share-nodes"></i> Open Graph Preview
        </button>
      </div>

      <p class="helper-text" style="margin:0;font-size:.8rem">
        <i class="fas fa-circle-info"></i>
        Each button opens the selected URL in the respective external tool in a new tab.
        The page must be publicly accessible for Google's tools to fetch it.
      </p>
    </div>
  </article>

  <!-- ══════════════════════════════════════════════════════════
       PageSpeed Insights
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card" style="grid-column:1 / -1" id="pagespeed">
    <h3 style="margin:0 0 6px"><i class="fas fa-gauge-high"></i> PageSpeed Insights</h3>
    <p class="helper-text" style="margin:0 0 20px">Run a Lighthouse audit via the PageSpeed Insights API and see scores inline. Results are pulled live — this may take 10–30 seconds.</p>

    <div class="form-grid" style="gap:12px">
      <div class="form-grid two-col" style="gap:12px">
        <div class="field" style="margin:0">
          <label for="psi-select">Pick a page</label>
          <select id="psi-select">
            <option value="">— select a page —</option>
            <?php foreach ($urlOptions as $opt): ?>
              <option value="<?= cms_e($opt['url']) ?>"><?= cms_e($opt['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field" style="margin:0">
          <label for="psi-url">Or enter any URL</label>
          <input id="psi-url" type="url" placeholder="https://a1technovation.com/" />
        </div>
      </div>

      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div style="display:flex;gap:0;border:1px solid var(--border);border-radius:6px;overflow:hidden">
          <button type="button" class="btn btn-secondary btn-sm psi-strategy-btn active" data-strategy="mobile" style="border:none;border-radius:0;border-right:1px solid var(--border)">
            <i class="fas fa-mobile-screen-button"></i> Mobile
          </button>
          <button type="button" class="btn btn-secondary btn-sm psi-strategy-btn" data-strategy="desktop" style="border:none;border-radius:0">
            <i class="fas fa-desktop"></i> Desktop
          </button>
        </div>
        <button type="button" class="btn btn-primary" id="btn-psi-run">
          <i class="fas fa-play"></i> Run PageSpeed Audit
        </button>
        <button type="button" class="btn btn-secondary btn-sm" id="btn-psi-open" style="margin-left:auto">
          <i class="fas fa-external-link"></i> Open on pagespeed.web.dev
        </button>
      </div>

      <!-- Loading state -->
      <div id="psi-loading" style="display:none;text-align:center;padding:32px 0;color:var(--text-muted)">
        <i class="fas fa-spinner fa-spin fa-2x" style="margin-bottom:12px"></i>
        <p style="margin:0">Running Lighthouse audit… this takes 10–30 seconds.</p>
      </div>

      <!-- Error state -->
      <div id="psi-error" style="display:none;padding:12px 16px;background:var(--danger-bg,#fee2e2);color:var(--danger,#dc2626);border-radius:8px;font-size:.88rem"></div>

      <!-- Results -->
      <div id="psi-results" style="display:none">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px" id="psi-scores"></div>
        <div id="psi-meta" style="font-size:.8rem;color:var(--text-muted);margin-bottom:8px"></div>
        <div id="psi-opportunities" style="display:none">
          <h4 style="margin:0 0 10px;font-size:.9rem"><i class="fas fa-lightbulb"></i> Top opportunities</h4>
          <ul id="psi-opp-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px;font-size:.83rem"></ul>
        </div>
      </div>
    </div>
  </article>

  <!-- ══════════════════════════════════════════════════════════
       Redirect Manager
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card" style="grid-column:1 / -1" id="redirects">
    <h3 style="margin:0 0 16px"><i class="fas fa-right-left"></i> Redirect Manager</h3>

    <?php if (count($redirects) > 0): ?>
    <div class="table-wrap" style="overflow-x:auto;margin-bottom:20px">
      <table class="admin-table" style="width:100%;min-width:560px">
        <thead><tr><th>From</th><th>To</th><th>Code</th><th>Added</th><th style="width:60px"></th></tr></thead>
        <tbody>
          <?php foreach ($redirects as $i => $r): ?>
          <tr>
            <td><code><?= cms_e((string) ($r['from'] ?? '')) ?></code></td>
            <td><code><?= cms_e((string) ($r['to'] ?? '')) ?></code></td>
            <td><span class="status-badge status-published"><?= cms_e((string) ($r['code'] ?? '301')) ?></span></td>
            <td class="muted" style="font-size:.8rem"><?= cms_e((string) ($r['created_at'] ?? '')) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('Delete this redirect?')">
                <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete_redirect" />
                <input type="hidden" name="redirect_index" value="<?= $i ?>" />
                <input type="hidden" name="write_htaccess" value="1" />
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <form method="post" style="margin-bottom:16px">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="write_redirects_htaccess" />
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-file-code"></i> Write all to .htaccess</button>
    </form>
    <?php else: ?>
      <p class="muted" style="margin-bottom:16px">No redirects configured yet.</p>
    <?php endif; ?>

    <form method="post" class="form-grid" style="border-top:1px solid var(--border);padding-top:16px">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="add_redirect" />
      <h4 style="margin:0 0 12px">Add new redirect</h4>
      <div class="form-grid two-col" style="gap:12px">
        <div class="field">
          <label for="r_from">From (path)</label>
          <input id="r_from" type="text" name="redirect_from" placeholder="/old-page" required />
        </div>
        <div class="field">
          <label for="r_to">To (URL or path)</label>
          <input id="r_to" type="text" name="redirect_to" placeholder="/new-page or https://..." required />
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
        <div class="field" style="margin:0;min-width:160px">
          <label for="r_code">HTTP code</label>
          <select id="r_code" name="redirect_code">
            <option value="301">301 — Permanent</option>
            <option value="302">302 — Temporary</option>
          </select>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-weight:400;cursor:pointer;margin-top:20px">
          <input type="checkbox" name="write_htaccess" value="1" checked />
          Write to .htaccess immediately
        </label>
      </div>
      <button type="submit" class="btn btn-primary" style="align-self:flex-start"><i class="fas fa-plus"></i> Add Redirect</button>
    </form>
  </article>

  <!-- ══════════════════════════════════════════════════════════
       Sitemap panel
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card seo-sitemap-card">
    <h3 style="margin:0 0 12px"><i class="fas fa-sitemap"></i> XML Sitemap</h3>
    <div class="stats-grid seo-sitemap-stats">
      <article class="stats-card">
        <span class="admin-kicker">Status</span>
        <strong style="font-size:1.15rem;background:none;-webkit-background-clip:unset;-webkit-text-fill-color:unset;color:<?= $sitemapExists ? 'var(--admin-success,#22c55e)' : 'var(--admin-warning,#f97316)' ?>">
          <?= $sitemapExists ? '✓ Generated' : '✗ Not found' ?>
        </strong>
        <p><?= $sitemapExists ? 'sitemap.xml exists on disk' : 'Click Regenerate to create it' ?></p>
      </article>
      <article class="stats-card">
        <span class="admin-kicker">URLs indexed</span>
        <strong><?= $sitemapUrls ?></strong>
        <p><?= $sitemapUpdated ? 'Last built ' . cms_e($sitemapUpdated) : 'Never generated' ?></p>
      </article>
    </div>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="generate_sitemap" />
      <button type="submit" class="btn btn-primary"><i class="fas fa-arrows-rotate"></i> Regenerate sitemap.xml</button>
      <?php if ($sitemapExists): ?>
        <a href="<?= cms_e(cms_url('sitemap.xml')) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i class="fas fa-external-link"></i> View sitemap</a>
      <?php endif; ?>
    </form>
    <p class="muted" style="margin-top:12px;font-size:.82rem">Submit in <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console</a> as <code><?= cms_e(cms_url('sitemap.xml')) ?></code>.</p>
  </article>

  <!-- ══════════════════════════════════════════════════════════
       llms.txt panel
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card seo-sitemap-card" id="llms">
    <h3 style="margin:0 0 12px"><i class="fas fa-brain"></i> llms.txt</h3>
    <div class="stats-grid seo-sitemap-stats">
      <article class="stats-card">
        <span class="admin-kicker">Status</span>
        <strong style="font-size:1.15rem;background:none;-webkit-background-clip:unset;-webkit-text-fill-color:unset;color:<?= $llmsExists ? 'var(--admin-success,#22c55e)' : 'var(--admin-warning,#f97316)' ?>">
          <?= $llmsExists ? 'Generated' : 'Not found' ?>
        </strong>
        <p><?= $llmsExists ? 'llms.txt exists on disk' : 'Click Generate to create it' ?></p>
      </article>
      <article class="stats-card">
        <span class="admin-kicker">File size</span>
        <strong><?= $llmsExists ? number_format($llmsSize / 1024, 1) . ' KB' : '0 KB' ?></strong>
        <p><?= $llmsUpdated ? 'Last built ' . cms_e($llmsUpdated) : 'Never generated' ?></p>
      </article>
    </div>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="generate_llms" />
      <button type="submit" class="btn btn-primary"><i class="fas fa-arrows-rotate"></i> Generate llms.txt</button>
      <?php if ($llmsExists): ?>
        <a href="<?= cms_e(cms_url('llms.txt')) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i class="fas fa-external-link"></i> View llms.txt</a>
      <?php endif; ?>
    </form>
    <p class="muted" style="margin-top:12px;font-size:.82rem">Builds an AI-friendly index from static pages, CMS pages, published blog posts, titles, meta descriptions, and canonical URLs.</p>
  </article>

  <!-- ══════════════════════════════════════════════════════════
       SEO checklist
  ══════════════════════════════════════════════════════════ -->
  <article class="admin-card seo-checklist-card">
    <h3 style="margin:0 0 16px"><i class="fas fa-clipboard-check"></i> On-page SEO checklist</h3>
    <ul class="info-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:8px">
      <li>
        <span class="status-badge <?= count($noMeta) === 0 ? 'status-published' : 'status-draft' ?>"><?= count($noMeta) === 0 ? '✓' : count($noMeta) ?></span>
        Blog posts missing meta description<?= count($noMeta) > 0 ? ' — <a href="' . cms_e(cms_url('admin/blogs.php')) . '">fix now</a>' : '' ?>
      </li>
      <li>
        <span class="status-badge <?= count($noImg) === 0 ? 'status-published' : 'status-draft' ?>"><?= count($noImg) === 0 ? '✓' : count($noImg) ?></span>
        Blog posts missing featured image
      </li>
      <li>
        <span class="status-badge <?= (count($titleShort) + count($titleLong)) === 0 ? 'status-published' : 'status-draft' ?>"><?= (count($titleShort) + count($titleLong)) === 0 ? '✓' : (count($titleShort) + count($titleLong)) ?></span>
        Posts with title outside 30–60 char range
        <?php if (count($titleShort) > 0): ?><span class="muted" style="font-size:.8rem">(<?= count($titleShort) ?> too short)</span><?php endif; ?>
        <?php if (count($titleLong) > 0): ?><span class="muted" style="font-size:.8rem">(<?= count($titleLong) ?> too long)</span><?php endif; ?>
      </li>
      <li>
        <span class="status-badge <?= (count($descShort) + count($descLong)) === 0 ? 'status-published' : 'status-draft' ?>"><?= (count($descShort) + count($descLong)) === 0 ? '✓' : (count($descShort) + count($descLong)) ?></span>
        Posts with description outside 120–160 char range
        <?php if (count($descShort) > 0): ?><span class="muted" style="font-size:.8rem">(<?= count($descShort) ?> too short)</span><?php endif; ?>
        <?php if (count($descLong) > 0): ?><span class="muted" style="font-size:.8rem">(<?= count($descLong) ?> too long)</span><?php endif; ?>
      </li>
      <li>
        <span class="status-badge <?= count($pagesNoMeta) === 0 ? 'status-published' : 'status-draft' ?>"><?= count($pagesNoMeta) === 0 ? '✓' : count($pagesNoMeta) ?></span>
        CMS pages missing meta description<?= count($pagesNoMeta) > 0 ? ' — <a href="' . cms_e(cms_url('admin/pages.php')) . '">fix now</a>' : '' ?>
      </li>
      <li><span class="status-badge <?= ($seoSettings['google_analytics_id'] ?? '') !== '' ? 'status-published' : 'status-draft' ?>"><?= ($seoSettings['google_analytics_id'] ?? '') !== '' ? '✓' : '!' ?></span> Google Analytics connected</li>
      <li><span class="status-badge <?= ($seoSettings['bing_verification'] ?? '') !== '' ? 'status-published' : 'status-draft' ?>"><?= ($seoSettings['bing_verification'] ?? '') !== '' ? '✓' : '!' ?></span> Bing Webmaster verified</li>
      <li><span class="status-badge <?= ($seoSettings['twitter_site'] ?? '') !== '' ? 'status-published' : 'status-draft' ?>"><?= ($seoSettings['twitter_site'] ?? '') !== '' ? '✓' : '!' ?></span> Twitter/X site handle set</li>
      <li><span class="status-badge <?= ($seoSettings['schema_org_json'] ?? '') !== '' ? 'status-published' : 'status-draft' ?>"><?= ($seoSettings['schema_org_json'] ?? '') !== '' ? '✓' : '!' ?></span> Schema.org JSON-LD configured</li>
      <li><span class="status-badge <?= $sitemapExists ? 'status-published' : 'status-draft' ?>"><?= $sitemapExists ? '✓' : '!' ?></span> sitemap.xml generated</li>
      <li><span class="status-badge <?= $llmsExists ? 'status-published' : 'status-draft' ?>"><?= $llmsExists ? '✓' : '!' ?></span> llms.txt generated</li>
    </ul>
  </article>

</section>

<!-- ── OG / Twitter Card Visual Preview ──────────────────────────────────── -->
<article class="admin-card" style="margin-bottom:32px" id="og-preview-tool">
  <h3 style="margin:0 0 6px"><i class="fas fa-share-nodes"></i> Open Graph & Twitter Card Preview</h3>
  <p class="helper-text" style="margin:0 0 20px">See exactly how your page looks when shared on Facebook/LinkedIn and Twitter/X before publishing.</p>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:28px">

    <!-- Input form -->
    <div>
      <div class="field" style="margin-bottom:12px">
        <label>Page Title</label>
        <input type="text" id="ogp-title" placeholder="Your page or post title" value="<?= cms_e((string) ($seoSettings['default_meta_title'] ?? 'A1 Technovation')) ?>">
      </div>
      <div class="field" style="margin-bottom:12px">
        <label>Description</label>
        <textarea id="ogp-desc" rows="2" placeholder="Meta description (155 chars)"><?= cms_e((string) ($seoSettings['default_meta_description'] ?? '')) ?></textarea>
      </div>
      <div class="field" style="margin-bottom:12px">
        <label>Image URL</label>
        <input type="url" id="ogp-image" placeholder="https://..." value="<?= cms_e((string) ($seoSettings['og_default_image'] ?? '')) ?>">
        <small>Recommended: 1200×630px for Facebook, 800×418px for Twitter.</small>
      </div>
      <div class="field" style="margin-bottom:12px">
        <label>Site Name</label>
        <input type="text" id="ogp-site" value="<?= cms_e((string) ($siteSettings['site_name'] ?? 'A1 Technovation')) ?>">
      </div>
      <div class="field">
        <label>URL</label>
        <input type="url" id="ogp-url" value="<?= cms_e(cms_url('')) ?>">
      </div>
    </div>

    <!-- Preview cards -->
    <div>
      <p style="font-weight:600;margin:0 0 12px;font-size:13px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Facebook / LinkedIn Preview</p>
      <div id="fb-preview" style="border-radius:8px;overflow:hidden;border:1px solid #3a4250;max-width:500px;background:#1c1e21;font-family:Arial,sans-serif">
        <div id="fb-img-wrap" style="background:#2a2d35;height:260px;display:flex;align-items:center;justify-content:center;overflow:hidden">
          <span style="color:#9ca3af;font-size:13px">No image</span>
        </div>
        <div style="padding:12px 16px">
          <p style="color:#8a8d91;font-size:12px;margin:0 0 2px;text-transform:uppercase" id="fb-url">a1technovation.com</p>
          <p style="color:#e4e6eb;font-size:16px;font-weight:700;margin:0 0 4px;line-height:1.3" id="fb-title">Title</p>
          <p style="color:#8a8d91;font-size:14px;margin:0;line-height:1.4" id="fb-desc">Description</p>
        </div>
      </div>

      <p style="font-weight:600;margin:20px 0 12px;font-size:13px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.06em">Twitter / X Preview</p>
      <div id="tw-preview" style="border-radius:16px;overflow:hidden;border:1px solid #2f3336;max-width:500px;background:#000;font-family:-apple-system,system-ui,sans-serif">
        <div id="tw-img-wrap" style="background:#1a1a1a;height:200px;display:flex;align-items:center;justify-content:center;overflow:hidden">
          <span style="color:#536471;font-size:13px">No image</span>
        </div>
        <div style="padding:10px 14px">
          <p style="color:#e7e9ea;font-size:15px;font-weight:700;margin:0 0 2px" id="tw-title">Title</p>
          <p style="color:#71767b;font-size:13px;margin:0 0 2px" id="tw-desc">Description</p>
          <p style="color:#71767b;font-size:13px;margin:0">🔗 <span id="tw-url">a1technovation.com</span></p>
        </div>
      </div>
    </div>
  </div>
</article>

<script>
(function () {
  function getHost(url) {
    try { return new URL(url).hostname; } catch(e) { return url; }
  }
  function setImg(wrapId, url) {
    const wrap = document.getElementById(wrapId);
    if (!wrap) return;
    if (url) {
      wrap.innerHTML = '<img src="' + url + '" style="width:100%;height:100%;object-fit:cover" onerror="this.parentNode.innerHTML=\'<span style=\\'color:#9ca3af;font-size:13px\\'>Image failed to load</span>\'">';
    } else {
      wrap.innerHTML = '<span style="color:#9ca3af;font-size:13px">No image</span>';
    }
  }
  function updatePreview() {
    const title = document.getElementById('ogp-title')?.value || '';
    const desc  = document.getElementById('ogp-desc')?.value || '';
    const img   = document.getElementById('ogp-image')?.value || '';
    const url   = document.getElementById('ogp-url')?.value || '';
    const host  = getHost(url);
    const shortTitle = title.length > 60 ? title.slice(0, 57) + '…' : title;
    const shortDesc  = desc.length > 155 ? desc.slice(0, 152) + '…' : desc;
    ['fb-title','tw-title'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = shortTitle; });
    ['fb-desc','tw-desc'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = shortDesc; });
    ['fb-url','tw-url'].forEach(id => { const el = document.getElementById(id); if (el) el.textContent = host; });
    setImg('fb-img-wrap', img);
    setImg('tw-img-wrap', img);
  }
  ['ogp-title','ogp-desc','ogp-image','ogp-url'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', updatePreview);
  });
  updatePreview();
})();
</script>

<style>
/* ── Page Meta Manager sub-tabs ──────────────────────────────────────────── */
.seo-subtab-bar {
  display: flex;
  gap: 4px;
  border-bottom: 2px solid var(--border);
  margin-bottom: 20px;
}
.seo-subtab {
  padding: 8px 16px;
  border: none;
  background: transparent;
  color: var(--text-muted);
  font: 600 13px/1.3 var(--font, Inter, system-ui, sans-serif);
  cursor: pointer;
  border-bottom: 2px solid transparent;
  margin-bottom: -2px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: color .15s;
}
.seo-subtab:hover { color: var(--text); }
.seo-subtab.active { color: var(--primary, #0b63f6); border-bottom-color: var(--primary, #0b63f6); }
.seo-subtab-count {
  background: var(--badge-bg, rgba(148,163,184,.18));
  color: var(--text-muted);
  border-radius: 999px;
  font-size: .7rem;
  font-weight: 700;
  padding: 1px 7px;
  min-width: 20px;
  text-align: center;
}
.seo-subtab.active .seo-subtab-count { background: var(--primary-light, #dbeafe); color: var(--primary, #0b63f6); }
.seo-meta-table-wrap { overflow-x: auto; }
.seo-meta-table {
  width: 100%;
  border-collapse: collapse;
  font-size: .85rem;
}
.seo-meta-table thead th {
  text-align: left;
  padding: 8px 10px;
  font-size: .75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .04em;
  color: var(--text-muted);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
}
.seo-meta-table tbody tr { border-bottom: 1px solid var(--border); }
.seo-meta-table tbody tr:last-child { border-bottom: none; }
.seo-meta-table tbody tr:hover { background: var(--row-hover, rgba(148,163,184,.06)); }
.seo-meta-table td { padding: 10px; vertical-align: top; }
.seo-page-name { width: 200px; min-width: 160px; }
.seo-page-name a { font-weight: 600; font-size: .83rem; color: var(--text); text-decoration: none; }
.seo-page-name a:hover { color: var(--primary); text-decoration: underline; }
.seo-td-title { width: 28%; }
.seo-td-desc { width: auto; }
.seo-td-action { width: 110px; white-space: nowrap; vertical-align: middle; }
.seo-meta-table input[type=text],
.seo-meta-table textarea {
  width: 100%;
  box-sizing: border-box;
  font-size: .82rem;
  padding: 6px 8px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--input-bg, transparent);
  color: var(--text);
  resize: vertical;
  line-height: 1.4;
}
.seo-meta-table input[type=text]:focus,
.seo-meta-table textarea:focus { outline: none; border-color: var(--primary, #0b63f6); }
.seo-char-hint { display: block; font-size: .7rem; margin-top: 3px; color: var(--text-muted); }
.seo-char-hint.ok   { color: #22c55e; }
.seo-char-hint.warn { color: #f97316; }
.seo-row-msg { display: inline-block; margin-top: 4px; font-size: .75rem; }
.seo-row-msg.ok   { color: #22c55e; }
.seo-row-msg.err  { color: #ef4444; }
.seo-view-field { font-size: .83rem; line-height: 1.45; color: var(--text); word-break: break-word; }
.seo-not-set { font-style: italic; color: var(--text-muted); font-size: .8rem; }
.seo-edit-actions { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.seo-view-actions, .seo-edit-actions { white-space: nowrap; }

.psi-score-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
  padding: 16px 8px;
  background: var(--card-bg, #1e2130);
  border: 1px solid var(--border);
  border-radius: 10px;
  text-align: center;
}
.psi-score-ring {
  width: 72px;
  height: 72px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.3rem;
  font-weight: 700;
  border: 5px solid currentColor;
  line-height: 1;
}
.psi-score-ring.green  { color: #22c55e; }
.psi-score-ring.orange { color: #f97316; }
.psi-score-ring.red    { color: #ef4444; }
.psi-score-label { font-size: .78rem; color: var(--text-muted); font-weight: 500; text-transform: uppercase; letter-spacing: .04em; }
.psi-strategy-btn.active { background: var(--primary); color: #fff; }
</style>

<script>
(function () {

  // ── Char counters ────────────────────────────────────────────────────────
  function addCounter(id, min, max) {
    const el = document.getElementById(id);
    if (!el) return;
    const span = document.createElement('span');
    span.style.cssText = 'font-size:.75rem;margin-left:8px';
    el.closest('.field').querySelector('label').appendChild(span);
    const update = () => {
      const n = el.value.length;
      span.textContent = n + ' chars';
      span.style.color = (n >= min && n <= max) ? '#22c55e' : '#f97316';
    };
    el.addEventListener('input', update);
    update();
  }
  addCounter('seo_meta_title', 30, 60);
  addCounter('seo_meta_desc', 120, 160);
  addCounter('target_meta_title', 30, 60);
  addCounter('target_meta_description', 120, 160);

  // ── Page SEO target picker ───────────────────────────────────────────────
  const seoTarget = document.getElementById('seo_target');
  if (seoTarget) {
    const titleField = document.getElementById('target_meta_title');
    const descField = document.getElementById('target_meta_description');
    const canonicalField = document.getElementById('target_canonical_url');
    const liveLink = document.getElementById('target_live_link');
    seoTarget.addEventListener('change', function () {
      const opt = seoTarget.options[seoTarget.selectedIndex];
      if (!opt || !opt.value) return;
      if (titleField) titleField.value = opt.dataset.title || '';
      if (descField) descField.value = opt.dataset.description || '';
      if (canonicalField) canonicalField.value = opt.dataset.canonical || opt.dataset.url || '';
      if (liveLink && opt.dataset.url) {
        liveLink.href = opt.dataset.url;
        liveLink.style.display = 'inline-flex';
      }
      [titleField, descField].forEach(function (field) {
        if (field) field.dispatchEvent(new Event('input'));
      });
    });
  }

  // ── Schema templates ─────────────────────────────────────────────────────
  const TEMPLATES = {
    organization: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "A1 Technovation",
  "url": "https://a1technovation.com",
  "logo": "https://a1technovation.com/assets/images/A1Technovation-Light-Background.png",
  "description": "Bangladesh's fastest-growing digital marketing agency specialising in SEO, PPC, and web design.",
  "email": "info.a1technovation@gmail.com",
  "telephone": "+880-1799-976295",
  "sameAs": [
    "https://www.facebook.com/a1technovation",
    "https://twitter.com/a1technovation",
    "https://www.linkedin.com/company/a1technovation"
  ]
}
<\/script>`,
    localbusiness: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "A1 Technovation",
  "url": "https://a1technovation.com",
  "image": "https://a1technovation.com/assets/images/A1Technovation-Light-Background.png",
  "telephone": "+880-1799-976295",
  "email": "info.a1technovation@gmail.com",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Your Street Address",
    "addressLocality": "Dhaka",
    "addressCountry": "BD"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": 23.8103,
    "longitude": 90.4125
  },
  "openingHoursSpecification": [{
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Sunday"],
    "opens": "09:00",
    "closes": "18:00"
  }]
}
<\/script>`,
    website: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "A1 Technovation",
  "url": "https://a1technovation.com",
  "potentialAction": {
    "@type": "SearchAction",
    "target": {
      "@type": "EntryPoint",
      "urlTemplate": "https://a1technovation.com/blog?q={search_term_string}"
    },
    "query-input": "required name=search_term_string"
  }
}
<\/script>`,
    service: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Service",
  "serviceType": "SEO Services",
  "name": "Search Engine Optimisation",
  "provider": {
    "@type": "Organization",
    "name": "A1 Technovation",
    "url": "https://a1technovation.com"
  },
  "areaServed": "Worldwide",
  "description": "Data-driven SEO strategies to improve organic rankings and drive qualified traffic.",
  "url": "https://a1technovation.com/pages/services-seo"
}
<\/script>`,
    article: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Article",
  "headline": "Article Headline Here",
  "description": "A short description of the article.",
  "image": "https://a1technovation.com/assets/images/og-default.jpg",
  "author": {
    "@type": "Person",
    "name": "Likhon Ahmed",
    "url": "https://a1technovation.com/pages/about"
  },
  "publisher": {
    "@type": "Organization",
    "name": "A1 Technovation",
    "logo": {
      "@type": "ImageObject",
      "url": "https://a1technovation.com/assets/images/A1Technovation-Light-Background.png"
    }
  },
  "datePublished": "2026-01-01",
  "dateModified": "2026-01-01"
}
<\/script>`,
    faqpage: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What is your main service?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We provide SEO, PPC, and web design services to help businesses grow online."
      }
    },
    {
      "@type": "Question",
      "name": "Where are you based?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "We are based in Dhaka, Bangladesh, and serve clients globally."
      }
    }
  ]
}
<\/script>`,
    person: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Likhon Ahmed",
  "jobTitle": "SEO Expert & Founder",
  "url": "https://a1technovation.com/pages/about",
  "image": "https://a1technovation.com/assets/images/team/likhon.jpg",
  "sameAs": [
    "https://www.linkedin.com/in/likhon-ahmed",
    "https://twitter.com/likhon"
  ],
  "worksFor": {
    "@type": "Organization",
    "name": "A1 Technovation"
  }
}
<\/script>`,
    breadcrumb: `<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "name": "Home",
      "item": "https://a1technovation.com"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "name": "Services",
      "item": "https://a1technovation.com/pages/services"
    }
  ]
}
<\/script>`
  };

  const schemaTA = document.getElementById('seo_schema');
  const validateMsg = document.getElementById('schema-validate-msg');

  document.getElementById('btn-schema-insert').addEventListener('click', () => {
    const key = document.getElementById('schema-template-select').value;
    if (!key) return alert('Please choose a template from the dropdown first.');
    if (schemaTA.value.trim() !== '' && !confirm('Replace the current schema content with the selected template?')) return;
    schemaTA.value = TEMPLATES[key];
    document.getElementById('schema-template-select').value = '';
  });

  document.getElementById('btn-schema-format').addEventListener('click', () => {
    const raw = schemaTA.value.trim();
    // Strip surrounding script tags if present
    const jsonStr = raw.replace(/^\s*<script[^>]*>/i, '').replace(/<\/script>\s*$/i, '').trim();
    try {
      const pretty = JSON.stringify(JSON.parse(jsonStr), null, 2);
      schemaTA.value = '<script type="application/ld+json">\n' + pretty + '\n<\/script>';
      showValidate('JSON formatted successfully.', 'ok');
    } catch (e) {
      showValidate('JSON parse error: ' + e.message, 'err');
    }
  });

  document.getElementById('btn-schema-validate').addEventListener('click', () => {
    const raw = schemaTA.value.trim();
    if (raw === '') { showValidate('Nothing to validate — textarea is empty.', 'warn'); return; }
    const jsonStr = raw.replace(/^\s*<script[^>]*>/i, '').replace(/<\/script>\s*$/i, '').trim();
    try {
      const obj = JSON.parse(jsonStr);
      const type = obj['@type'] || '(no @type)';
      const ctx  = obj['@context'] || '(no @context)';
      showValidate('✓ Valid JSON-LD — @type: ' + type + ', @context: ' + ctx, 'ok');
    } catch (e) {
      showValidate('✗ Invalid JSON: ' + e.message, 'err');
    }
  });

  document.getElementById('btn-schema-clear').addEventListener('click', () => {
    if (schemaTA.value.trim() !== '' && !confirm('Clear the schema markup?')) return;
    schemaTA.value = '';
    validateMsg.style.display = 'none';
  });

  function showValidate(msg, type) {
    validateMsg.style.display = '';
    validateMsg.textContent = msg;
    validateMsg.style.background = type === 'ok' ? 'var(--success-bg,#dcfce7)' : type === 'err' ? 'var(--danger-bg,#fee2e2)' : 'var(--warning-bg,#fef9c3)';
    validateMsg.style.color = type === 'ok' ? '#16a34a' : type === 'err' ? '#dc2626' : '#92400e';
  }

  // ── Rich Results validator ────────────────────────────────────────────────
  function rrUrl() {
    return document.getElementById('rr-select').value || document.getElementById('rr-url').value.trim();
  }

  document.getElementById('rr-select').addEventListener('change', function () {
    document.getElementById('rr-url').value = this.value;
  });
  document.getElementById('rr-url').addEventListener('input', function () {
    if (this.value.trim() !== '') document.getElementById('rr-select').value = '';
  });

  document.getElementById('btn-rr-google').addEventListener('click', () => {
    const u = rrUrl();
    if (!u) return alert('Select a page or enter a URL first.');
    window.open('https://search.google.com/test/rich-results?url=' + encodeURIComponent(u), '_blank');
  });

  document.getElementById('btn-rr-schema').addEventListener('click', () => {
    const u = rrUrl();
    if (!u) return alert('Select a page or enter a URL first.');
    window.open('https://validator.schema.org/#url=' + encodeURIComponent(u), '_blank');
  });

  document.getElementById('btn-rr-preview').addEventListener('click', () => {
    const u = rrUrl();
    if (!u) return alert('Select a page or enter a URL first.');
    window.open('https://www.opengraph.xyz/url/' + encodeURIComponent(u), '_blank');
  });

  // ── PageSpeed Insights ───────────────────────────────────────────────────
  let psiStrategy = 'mobile';

  document.querySelectorAll('.psi-strategy-btn').forEach(btn => {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.psi-strategy-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
      psiStrategy = this.dataset.strategy;
    });
  });

  document.getElementById('psi-select').addEventListener('change', function () {
    document.getElementById('psi-url').value = this.value;
  });
  document.getElementById('psi-url').addEventListener('input', function () {
    if (this.value.trim() !== '') document.getElementById('psi-select').value = '';
  });

  document.getElementById('btn-psi-open').addEventListener('click', () => {
    const u = psiUrl();
    if (!u) return alert('Select a page or enter a URL first.');
    window.open('https://pagespeed.web.dev/report?url=' + encodeURIComponent(u) + '&form_factor=' + psiStrategy, '_blank');
  });

  function psiUrl() {
    return document.getElementById('psi-select').value || document.getElementById('psi-url').value.trim();
  }

  document.getElementById('btn-psi-run').addEventListener('click', async () => {
    const url = psiUrl();
    if (!url) { alert('Select a page or enter a URL first.'); return; }

    const loading  = document.getElementById('psi-loading');
    const error    = document.getElementById('psi-error');
    const results  = document.getElementById('psi-results');
    const scoresEl = document.getElementById('psi-scores');
    const metaEl   = document.getElementById('psi-meta');
    const oppWrap  = document.getElementById('psi-opportunities');
    const oppList  = document.getElementById('psi-opp-list');

    loading.style.display = '';
    error.style.display   = 'none';
    results.style.display = 'none';

    try {
      const fd = new FormData();
      fd.append('url', url);
      fd.append('strategy', psiStrategy);

      const res  = await fetch('?ajax=pagespeed', { method: 'POST', body: fd });
      const data = await res.json();

      loading.style.display = 'none';

      if (data.error) { showPsiError(data.error.message || data.error); return; }

      const cats = data.lighthouseResult?.categories ?? {};
      const scoreMap = {
        performance:     { label: 'Performance',     key: 'performance'     },
        accessibility:   { label: 'Accessibility',   key: 'accessibility'   },
        'best-practices':{ label: 'Best Practices',  key: 'best-practices'  },
        seo:             { label: 'SEO',              key: 'seo'             },
      };

      scoresEl.innerHTML = '';
      Object.entries(scoreMap).forEach(([, cfg]) => {
        const cat = cats[cfg.key];
        if (!cat) return;
        const score = Math.round((cat.score ?? 0) * 100);
        const cls = score >= 90 ? 'green' : score >= 50 ? 'orange' : 'red';
        scoresEl.insertAdjacentHTML('beforeend', `
          <div class="psi-score-card">
            <div class="psi-score-ring ${cls}">${score}</div>
            <span class="psi-score-label">${cfg.label}</span>
          </div>`);
      });

      const audits   = data.lighthouseResult?.audits ?? {};
      const fetchTime = data.lighthouseResult?.fetchTime ?? '';
      metaEl.textContent = 'Strategy: ' + psiStrategy + (fetchTime ? ' · Tested at ' + new Date(fetchTime).toLocaleString() : '') + ' · URL: ' + url;

      // Top opportunities (score < 1 and displayValue present)
      const opps = Object.values(audits)
        .filter(a => a.score !== null && a.score < 1 && a.displayValue && a.details?.type === 'opportunity')
        .sort((a, b) => (a.score ?? 1) - (b.score ?? 1))
        .slice(0, 6);

      if (opps.length > 0) {
        oppList.innerHTML = opps.map(a =>
          `<li style="display:flex;gap:8px;align-items:flex-start">
            <span style="color:#f97316;flex-shrink:0">⚠</span>
            <span><strong>${escHtml(a.title)}</strong>${a.displayValue ? ' — ' + escHtml(a.displayValue) : ''}</span>
          </li>`
        ).join('');
        oppWrap.style.display = '';
      } else {
        oppWrap.style.display = 'none';
      }

      results.style.display = '';

    } catch (e) {
      loading.style.display = 'none';
      showPsiError('Request failed: ' + e.message);
    }
  });

  function showPsiError(msg) {
    const el = document.getElementById('psi-error');
    el.textContent = '✗ ' + msg;
    el.style.display = '';
  }

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  // ── Page Meta Manager: sub-tabs ──────────────────────────────────────────
  document.querySelectorAll('.seo-subtab').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.seo-subtab').forEach(b => b.classList.remove('active'));
      document.querySelectorAll('.seo-tab-panel').forEach(p => p.style.display = 'none');
      btn.classList.add('active');
      const panel = document.getElementById(btn.dataset.panel);
      if (panel) panel.style.display = '';
    });
  });

  // ── Page Meta Manager: char counter helper ───────────────────────────────
  function seoCharHint(input, hint, min, max) {
    const update = () => {
      const n = input.value.length;
      hint.textContent = n + ' / ' + max + ' chars';
      hint.className = 'seo-char-hint ' + (n === 0 ? '' : n >= min && n <= max ? 'ok' : 'warn');
    };
    input.addEventListener('input', update);
    update();
  }

  // ── Page Meta Manager: Edit / Cancel / Save per row ─────────────────────
  const seoToken = <?= json_encode(cms_csrf_token()) ?>;

  function seoEnterEdit(row) {
    row.querySelectorAll('.seo-view-field').forEach(el => el.style.display = 'none');
    row.querySelectorAll('.seo-edit-field').forEach(el => el.style.display = '');
    row.querySelector('.seo-view-actions').style.display = 'none';
    row.querySelector('.seo-edit-actions').style.display = '';
    const titleInput = row.querySelector('.seo-title-input');
    const descInput  = row.querySelector('.seo-desc-input');
    const titleHint  = row.querySelector('.title-hint');
    const descHint   = row.querySelector('.desc-hint');
    if (titleInput && titleHint) seoCharHint(titleInput, titleHint, 50, 60);
    if (descInput  && descHint)  seoCharHint(descInput, descHint, 120, 160);
    if (titleInput) titleInput.focus();
  }

  function seoExitEdit(row) {
    row.querySelectorAll('.seo-view-field').forEach(el => el.style.display = '');
    row.querySelectorAll('.seo-edit-field').forEach(el => el.style.display = 'none');
    row.querySelector('.seo-view-actions').style.display = '';
    row.querySelector('.seo-edit-actions').style.display = 'none';
    row.querySelector('.seo-row-msg').textContent = '';
  }

  document.querySelectorAll('.seo-edit-btn').forEach(btn => {
    btn.addEventListener('click', () => seoEnterEdit(btn.closest('.seo-meta-row')));
  });

  document.querySelectorAll('.seo-cancel-btn').forEach(btn => {
    btn.addEventListener('click', () => seoExitEdit(btn.closest('.seo-meta-row')));
  });

  document.querySelectorAll('.seo-save-btn').forEach(function (btn) {
    btn.addEventListener('click', async function () {
      const row    = btn.closest('.seo-meta-row');
      const target = row.dataset.target;
      const title  = row.querySelector('.seo-title-input').value.trim();
      const desc   = row.querySelector('.seo-desc-input').value.trim();
      const msg    = row.querySelector('.seo-row-msg');
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
      msg.textContent = '';
      try {
        const fd = new FormData();
        fd.append('_token', seoToken);
        fd.append('seo_target', target);
        fd.append('meta_title', title);
        fd.append('meta_description', desc);
        fd.append('canonical_url', '');
        const res  = await fetch('?ajax=save_meta', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
          // Update the view spans with the new values
          const viewTitle = row.querySelector('.seo-view-title');
          const viewDesc  = row.querySelector('.seo-view-desc');
          viewTitle.textContent = title || '';
          if (!title) viewTitle.innerHTML = '<em class="seo-not-set">Not set</em>';
          viewDesc.textContent = desc || '';
          if (!desc) viewDesc.innerHTML = '<em class="seo-not-set">Not set</em>';
          seoExitEdit(row);
          msg.textContent = '✓ Saved';
          msg.className = 'seo-row-msg ok';
          setTimeout(() => { msg.textContent = ''; msg.className = 'seo-row-msg'; }, 3000);
        } else {
          msg.textContent = data.error || 'Save failed';
          msg.className = 'seo-row-msg err';
        }
      } catch (e) {
        msg.textContent = 'Request failed';
        msg.className = 'seo-row-msg err';
      }
      btn.disabled = false;
      btn.innerHTML = '<i class="fas fa-floppy-disk"></i> Save';
    });
  });

})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
