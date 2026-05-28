<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle    = 'Dashboard';
$activeNav    = 'dashboard';
$stats        = cms_get_dashboard_stats();
$recentPosts  = array_slice(cms_get_posts_admin(), 0, 5);
$recentMedia  = array_slice(cms_get_recent_media(), 0, 6);
$recentPages  = array_slice(cms_get_pages_admin(), 0, 5);
$recentLog    = cms_get_activity_log(8);
$formCount    = cms_table_exists('cms_forms') ? (int) cms_db()->query('SELECT COUNT(*) FROM cms_forms')->fetchColumn() : 0;
$unreadLeads  = cms_table_exists('cms_form_submissions') && cms_column_exists('cms_form_submissions', 'is_read') ? (int) cms_db()->query('SELECT COUNT(*) FROM cms_form_submissions WHERE is_read=0')->fetchColumn() : 0;
$mailCopyCount = cms_contact_submissions_count();
$recentMailCopies = cms_get_contact_submissions(5);
$cptCount     = cms_table_exists('cms_custom_post_types') ? (int) cms_db()->query('SELECT COUNT(*) FROM cms_custom_post_types')->fetchColumn() : 0;
$newsletterPending = function_exists('cms_newsletter_count') ? cms_newsletter_count('pending') : 0;
$newsletterConfirmed = function_exists('cms_newsletter_count') ? cms_newsletter_count('confirmed') : 0;
$apiTokens = function_exists('cms_api_tokens') ? cms_api_tokens() : [];
$activeApiTokens = count(array_filter($apiTokens, fn($t) => (int) ($t['is_active'] ?? 0) === 1));
$deleteApiTokens = count(array_filter($apiTokens, fn($t) => (int) ($t['is_active'] ?? 0) === 1 && str_contains((string) ($t['scopes'] ?? ''), 'delete')));
$cacheEnabled = function_exists('cms_cache_enabled') ? cms_cache_enabled() : false;
$cacheStats = function_exists('cms_cache_stats') ? cms_cache_stats() : ['file_count' => 0, 'db_entries' => 0, 'total_bytes' => 0];
$seoSettings = function_exists('cms_seo_settings') ? cms_seo_settings() : [];
$activityCount = function_exists('cms_activity_log_count') ? cms_activity_log_count() : count($recentLog);
$lastBackupAt = '';
if (cms_table_exists('cms_activity_log')) {
    $lastBackupAt = (string) (cms_db()->query("SELECT created_at FROM cms_activity_log WHERE action IN ('export_json','export_csv') ORDER BY created_at DESC LIMIT 1")->fetchColumn() ?: '');
}
$newMailCopies = cms_table_exists('contact_submissions') && cms_column_exists('contact_submissions', 'status')
    ? (int) cms_db()->query("SELECT COUNT(*) FROM contact_submissions WHERE status='new'")->fetchColumn()
    : 0;
$staticPageCount = is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pages')
    ? count(glob(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'pages' . DIRECTORY_SEPARATOR . '*.html') ?: [])
    : 0;

$readinessChecks = [
    ['label' => 'SEO defaults configured', 'ok' => trim((string) ($seoSettings['default_meta_title'] ?? '')) !== '' && trim((string) ($seoSettings['default_meta_description'] ?? '')) !== '', 'href' => 'admin/seo.php'],
    ['label' => 'Sitemap available', 'ok' => file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sitemap.xml'), 'href' => 'admin/seo.php'],
    ['label' => 'Robots.txt available', 'ok' => file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'robots.txt'), 'href' => 'admin/seo.php'],
    ['label' => 'Page cache enabled', 'ok' => $cacheEnabled, 'href' => 'admin/cache.php'],
    ['label' => 'Backup exported', 'ok' => $lastBackupAt !== '', 'href' => 'admin/backup.php'],
    ['label' => 'No unread form leads', 'ok' => $unreadLeads === 0, 'href' => 'admin/forms.php'],
    ['label' => 'No new mail copies', 'ok' => $newMailCopies === 0, 'href' => 'admin/mail.php'],
    ['label' => 'No delete API tokens', 'ok' => $deleteApiTokens === 0, 'href' => 'admin/api-tokens.php'],
];
$readyCount = count(array_filter($readinessChecks, fn($check) => (bool) $check['ok']));
$readinessScore = $readinessChecks === [] ? 100 : (int) round(($readyCount / count($readinessChecks)) * 100);
$readinessClass = $readinessScore >= 85 ? 'is-good' : ($readinessScore >= 60 ? 'is-warn' : 'is-bad');

$actionItems = [];
if ($unreadLeads > 0) {
    $actionItems[] = ['icon' => 'fas fa-clipboard-list', 'title' => $unreadLeads . ' unread form submission' . ($unreadLeads === 1 ? '' : 's'), 'copy' => 'Review new leads from CMS forms.', 'href' => 'admin/forms.php', 'button' => 'Review forms', 'tone' => 'warn'];
}
if ($newMailCopies > 0) {
    $actionItems[] = ['icon' => 'fas fa-envelope-open-text', 'title' => $newMailCopies . ' new mail cop' . ($newMailCopies === 1 ? 'y' : 'ies'), 'copy' => 'Contact messages are waiting for review.', 'href' => 'admin/mail.php', 'button' => 'Open inbox', 'tone' => 'warn'];
}
if ($newsletterPending > 0) {
    $actionItems[] = ['icon' => 'fas fa-paper-plane', 'title' => $newsletterPending . ' newsletter pending', 'copy' => 'Pending subscribers may need confirmation or cleanup.', 'href' => 'admin/newsletter.php', 'button' => 'Open newsletter', 'tone' => 'info'];
}
if (!$cacheEnabled) {
    $actionItems[] = ['icon' => 'fas fa-bolt', 'title' => 'Page cache is off', 'copy' => 'Enable cache/CDN before a high-traffic launch.', 'href' => 'admin/cache.php', 'button' => 'Configure cache', 'tone' => 'info'];
}
if ($lastBackupAt === '') {
    $actionItems[] = ['icon' => 'fas fa-database', 'title' => 'No backup export found', 'copy' => 'Download a JSON backup before production changes.', 'href' => 'admin/backup.php', 'button' => 'Download backup', 'tone' => 'bad'];
}
if ($deleteApiTokens > 0) {
    $actionItems[] = ['icon' => 'fas fa-key', 'title' => $deleteApiTokens . ' high-risk API token' . ($deleteApiTokens === 1 ? '' : 's'), 'copy' => 'Delete-scope tokens should be rare and short-lived.', 'href' => 'admin/api-tokens.php', 'button' => 'Review tokens', 'tone' => 'bad'];
}

$healthItems = [
    ['label' => 'SEO', 'value' => trim((string) ($seoSettings['default_meta_title'] ?? '')) !== '' ? 'Configured' : 'Needs setup', 'ok' => trim((string) ($seoSettings['default_meta_title'] ?? '')) !== '', 'href' => 'admin/seo.php'],
    ['label' => 'Links', 'value' => 'Audit ready', 'ok' => true, 'href' => 'admin/link-checker.php'],
    ['label' => 'Cache', 'value' => $cacheEnabled ? 'Enabled' : 'Disabled', 'ok' => $cacheEnabled, 'href' => 'admin/cache.php'],
    ['label' => 'Backups', 'value' => $lastBackupAt !== '' ? cms_format_date($lastBackupAt, 'M j g:i A') : 'Not exported', 'ok' => $lastBackupAt !== '', 'href' => 'admin/backup.php'],
    ['label' => 'Forms', 'value' => $unreadLeads > 0 ? $unreadLeads . ' unread' : 'Clear', 'ok' => $unreadLeads === 0, 'href' => 'admin/forms.php'],
    ['label' => 'API', 'value' => $activeApiTokens . ' active', 'ok' => $deleteApiTokens === 0, 'href' => 'admin/api-tokens.php'],
];

$quickActions = [
    ['label' => 'Create Blog', 'icon' => 'fas fa-pen-nib', 'href' => 'admin/blogs.php?action=new'],
    ['label' => 'Edit Homepage', 'icon' => 'fas fa-house', 'href' => 'admin/static-editor.php?file=index.html'],
    ['label' => 'Run Link Audit', 'icon' => 'fas fa-link-slash', 'href' => 'admin/link-checker.php'],
    ['label' => 'SEO Settings', 'icon' => 'fas fa-magnifying-glass-chart', 'href' => 'admin/seo.php'],
    ['label' => 'Download Backup', 'icon' => 'fas fa-cloud-arrow-down', 'href' => 'admin/backup.php'],
    ['label' => 'Clear Cache', 'icon' => 'fas fa-bolt', 'href' => 'admin/cache.php'],
    ['label' => 'View Site', 'icon' => 'fas fa-globe', 'href' => '', 'external' => true],
];

$rootDir = dirname(__DIR__);
$staticPages = function_exists('cms_get_static_pages_admin') ? cms_get_static_pages_admin() : [];
$allCmsPages = function_exists('cms_get_pages_admin') ? cms_get_pages_admin() : [];
$allPosts = function_exists('cms_get_posts_admin') ? cms_get_posts_admin() : [];
$redirects = function_exists('cms_get_redirects') ? cms_get_redirects() : [];
$sitemapPath = $rootDir . DIRECTORY_SEPARATOR . 'sitemap.xml';
$sitemapExists = is_file($sitemapPath);
$sitemapUpdated = $sitemapExists ? date('M j, Y g:i A', (int) filemtime($sitemapPath)) : '';
$sitemapUrls = $sitemapExists ? substr_count((string) file_get_contents($sitemapPath), '<url>') : 0;
$robotsExists = is_file($rootDir . DIRECTORY_SEPARATOR . 'robots.txt');
$notFoundExists = is_file($rootDir . DIRECTORY_SEPARATOR . '404.html');
$lastBackupAgeDays = $lastBackupAt !== '' ? max(0, (int) floor((time() - strtotime($lastBackupAt)) / 86400)) : null;
$staleCutoff = strtotime('-90 days');

$metaFromHtml = static function (string $html, string $name): string {
    $quoted = preg_quote($name, '#');
    if (preg_match('#<meta[^>]+(?:name|property)=["\']' . $quoted . '["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $m)) {
        return trim(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if (preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:name|property)=["\']' . $quoted . '["\']#i', $html, $m)) {
        return trim(html_entity_decode((string) $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    return '';
};
$titleFromHtml = static function (string $html, string $fallback): string {
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
        $title = trim(html_entity_decode(strip_tags((string) $m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($title !== '') {
            return $title;
        }
    }
    return $fallback;
};
$wordCount = static fn(string $html): int => str_word_count(trim(strip_tags($html)));
$issueRows = [];
$contentRows = [];
$speedRows = [];
$aiChecklistTotals = ['items' => 0, 'schema' => 0, 'faq' => 0, 'author' => 0, 'summary' => 0];
$addIssue = static function (array &$rows, string $severity, string $title, string $detail, string $href, string $type): void {
    $rows[] = compact('severity', 'title', 'detail', 'href', 'type');
};
$auditContent = function (array $item) use (&$issueRows, &$contentRows, &$speedRows, &$aiChecklistTotals, $addIssue, $wordCount, $staleCutoff): void {
    $html = (string) ($item['html'] ?? '');
    $title = trim((string) ($item['meta_title'] ?? $item['title'] ?? ''));
    $metaDescription = trim((string) ($item['meta_description'] ?? ''));
    $plain = trim(strip_tags($html));
    $words = $wordCount($html);
    $h1Count = preg_match_all('#<h1\b#i', $html);
    $imageCount = preg_match_all('#<img\b#i', $html);
    $imagesMissingAlt = preg_match_all('#<img\b(?:(?!\balt=)[^>])*>#i', $html);
    $linkCount = preg_match_all('#<a\b[^>]+href=#i', $html);
    $schemaCount = preg_match_all('#<script\b[^>]+application/ld\+json#i', $html);
    $hasFaq = stripos($html, 'FAQ') !== false || stripos($html, 'Question') !== false || stripos($html, 'faq') !== false;
    $hasAuthor = stripos($html, 'author') !== false || trim((string) ($item['author'] ?? '')) !== '';
    $hasSummary = preg_match('#<(?:p|div|section)[^>]*>\s*(?:[^<]{80,260})\s*</(?:p|div|section)>#i', $html) === 1;
    $updatedAt = strtotime((string) ($item['updated_at'] ?? '')) ?: 0;
    $editHref = (string) ($item['edit_href'] ?? '#');
    $displayTitle = (string) ($item['title'] ?? 'Untitled');
    $type = (string) ($item['type'] ?? 'content');

    $contentRows[] = [
        'title' => $displayTitle,
        'type' => $type,
        'words' => $words,
        'links' => $linkCount,
        'updated_at' => $updatedAt,
        'href' => $editHref,
    ];

    if ($title === '') {
        $addIssue($issueRows, 'bad', $displayTitle, 'Missing meta title.', $editHref, $type);
    } elseif (mb_strlen($title) > 65) {
        $addIssue($issueRows, 'warn', $displayTitle, 'Meta title is longer than 65 characters.', $editHref, $type);
    }
    if ($metaDescription === '') {
        $addIssue($issueRows, 'bad', $displayTitle, 'Missing meta description.', $editHref, $type);
    } elseif (mb_strlen($metaDescription) > 160) {
        $addIssue($issueRows, 'warn', $displayTitle, 'Meta description is longer than 160 characters.', $editHref, $type);
    }
    if ($h1Count !== 1) {
        $addIssue($issueRows, 'warn', $displayTitle, $h1Count === 0 ? 'Missing H1 heading.' : 'Multiple H1 headings found.', $editHref, $type);
    }
    if ($words > 0 && $words < 300) {
        $addIssue($issueRows, 'warn', $displayTitle, 'Thin content: fewer than 300 words.', $editHref, $type);
    }
    if ($imagesMissingAlt > 0) {
        $addIssue($issueRows, 'warn', $displayTitle, $imagesMissingAlt . ' image(s) missing alt text.', $editHref, $type);
    }
    if ($updatedAt > 0 && $updatedAt < $staleCutoff) {
        $addIssue($issueRows, 'info', $displayTitle, 'Not updated in 90+ days.', $editHref, $type);
    }

    if (($item['size'] ?? strlen($html)) > 220000 || $imageCount > 20 || ($imageCount > 4 && stripos($html, 'loading="lazy"') === false)) {
        $speedRows[] = [
            'title' => $displayTitle,
            'href' => $editHref,
            'size' => (int) ($item['size'] ?? strlen($html)),
            'images' => $imageCount,
            'note' => $imageCount > 4 && stripos($html, 'loading="lazy"') === false ? 'Missing lazy loading on image-heavy page.' : 'Heavy page footprint.',
        ];
    }

    $aiChecklistTotals['items']++;
    $aiChecklistTotals['schema'] += $schemaCount > 0 ? 1 : 0;
    $aiChecklistTotals['faq'] += $hasFaq ? 1 : 0;
    $aiChecklistTotals['author'] += $hasAuthor ? 1 : 0;
    $aiChecklistTotals['summary'] += $hasSummary ? 1 : 0;
};

foreach ($staticPages as $staticPage) {
    $path = (string) ($staticPage['path'] ?? '');
    $absolute = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    $html = is_file($absolute) ? (string) file_get_contents($absolute) : '';
    $auditContent([
        'type' => 'static',
        'title' => $titleFromHtml($html, (string) ($staticPage['title'] ?? $path)),
        'meta_title' => $titleFromHtml($html, ''),
        'meta_description' => $metaFromHtml($html, 'description'),
        'html' => $html,
        'updated_at' => $staticPage['updated_at'] ?? '',
        'size' => $staticPage['size'] ?? strlen($html),
        'edit_href' => cms_url('admin/static-editor.php?file=' . rawurlencode($path)),
    ]);
}
foreach ($allCmsPages as $page) {
    $auditContent([
        'type' => 'page',
        'title' => (string) ($page['title'] ?? ''),
        'meta_title' => (string) ($page['meta_title'] ?? ''),
        'meta_description' => (string) ($page['meta_description'] ?? ''),
        'html' => (string) ($page['custom_html'] ?? ''),
        'updated_at' => (string) ($page['updated_at'] ?? ''),
        'edit_href' => cms_url('admin/pages.php?edit=' . (int) ($page['id'] ?? 0)),
    ]);
}
foreach ($allPosts as $post) {
    $auditContent([
        'type' => 'post',
        'title' => (string) ($post['title'] ?? ''),
        'meta_title' => (string) ($post['meta_title'] ?? ''),
        'meta_description' => (string) ($post['meta_description'] ?? ''),
        'html' => (string) (($post['content_html'] ?? '') . "\n" . ($post['custom_html'] ?? '')),
        'updated_at' => (string) ($post['updated_at'] ?? $post['published_at'] ?? ''),
        'author' => (string) ($post['author_name'] ?? ''),
        'edit_href' => cms_url('admin/blogs.php?edit=' . (int) ($post['id'] ?? 0)),
    ]);
}

$severityWeight = ['bad' => 0, 'warn' => 1, 'info' => 2];
usort($issueRows, static fn(array $a, array $b): int => ($severityWeight[$a['severity']] ?? 9) <=> ($severityWeight[$b['severity']] ?? 9));
usort($contentRows, static fn(array $a, array $b): int => ($b['updated_at'] ?? 0) <=> ($a['updated_at'] ?? 0));
usort($speedRows, static fn(array $a, array $b): int => (($b['size'] ?? 0) + (($b['images'] ?? 0) * 5000)) <=> (($a['size'] ?? 0) + (($a['images'] ?? 0) * 5000)));
$staleContentCount = count(array_filter($contentRows, static fn(array $row): bool => (int) ($row['updated_at'] ?? 0) > 0 && (int) $row['updated_at'] < $staleCutoff));
$publishedSurface = $staticPageCount + (int) $stats['pages_total'] + (int) $stats['posts_published'];

$internalLinkIdeas = [];
$candidateTargets = array_slice(array_filter($contentRows, static fn(array $row): bool => (int) ($row['words'] ?? 0) >= 250), 0, 30);
foreach ($candidateTargets as $target) {
    $words = array_values(array_filter(array_unique(preg_split('/[^a-z0-9]+/i', strtolower((string) $target['title'])) ?: []), static fn(string $w): bool => strlen($w) >= 5 && !in_array($w, ['services', 'service', 'bangladesh', 'technovation'], true)));
    if ($words === []) {
        continue;
    }
    foreach ($candidateTargets as $source) {
        if ($source['href'] === $target['href']) {
            continue;
        }
        $score = count(array_intersect($words, preg_split('/[^a-z0-9]+/i', strtolower((string) $source['title'])) ?: []));
        if ($score > 0) {
            $internalLinkIdeas[] = ['from' => $source['title'], 'to' => $target['title'], 'href' => $source['href'], 'score' => $score];
            break;
        }
    }
    if (count($internalLinkIdeas) >= 6) {
        break;
    }
}

$leadServiceRows = [];
if (cms_table_exists('contact_submissions') && cms_column_exists('contact_submissions', 'service_interest')) {
    $leadServiceRows = cms_db()->query("SELECT COALESCE(NULLIF(service_interest,''),'Not specified') AS label, COUNT(*) AS total FROM contact_submissions GROUP BY label ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$leadSourceRows = [];
if (cms_table_exists('contact_submissions') && cms_column_exists('contact_submissions', 'source_page')) {
    $leadSourceRows = cms_db()->query("SELECT COALESCE(NULLIF(source_page,''),'Unknown') AS label, COUNT(*) AS total FROM contact_submissions GROUP BY label ORDER BY total DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$scheduledPosts = [];
if (cms_table_exists('blog_posts') && cms_column_exists('blog_posts', 'scheduled_at')) {
    $scheduledPosts = cms_db()->query("SELECT id, title, scheduled_at FROM blog_posts WHERE scheduled_at IS NOT NULL ORDER BY scheduled_at ASC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
$contentCalendarRows = $scheduledPosts;
foreach (array_slice(array_filter($allPosts, static fn(array $post): bool => ($post['status'] ?? '') === 'draft'), 0, max(0, 6 - count($contentCalendarRows))) as $draft) {
    $contentCalendarRows[] = ['id' => (int) ($draft['id'] ?? 0), 'title' => (string) ($draft['title'] ?? 'Untitled draft'), 'scheduled_at' => 'Draft'];
}

$gscReady = false;
$gscNote = 'Connect Search Console exports to show clicks, impressions, CTR, and losing pages here.';
$gscSummary = null;
$gscRows = [];
$gscError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'import_gsc_csv') {
    cms_require_csrf();
    $csvRaw = trim((string) ($_POST['gsc_csv'] ?? ''));
    if ($csvRaw === '' && isset($_FILES['gsc_file']) && is_uploaded_file((string) ($_FILES['gsc_file']['tmp_name'] ?? ''))) {
        $csvRaw = (string) file_get_contents((string) $_FILES['gsc_file']['tmp_name']);
    }
    if ($csvRaw === '') {
        $gscError = 'Paste or upload a Search Console CSV export first.';
    } else {
        $lines = preg_split('/\r\n|\n|\r/', $csvRaw) ?: [];
        $header = array_map(static fn($h) => strtolower(trim((string) $h)), str_getcsv((string) array_shift($lines)));
        $index = array_flip($header);
        $clicksKey = $index['clicks'] ?? null;
        $impressionsKey = $index['impressions'] ?? null;
        $ctrKey = $index['ctr'] ?? null;
        $positionKey = $index['position'] ?? ($index['avg. position'] ?? null);
        $pageKey = $index['page'] ?? ($index['pages'] ?? ($index['query'] ?? null));
        if ($clicksKey === null || $impressionsKey === null) {
            $gscError = 'CSV must include Clicks and Impressions columns.';
        } else {
            $totalClicks = 0;
            $totalImpressions = 0;
            $positionTotal = 0.0;
            $positionRows = 0;
            foreach ($lines as $line) {
                if (trim((string) $line) === '') {
                    continue;
                }
                $cols = str_getcsv((string) $line);
                $clicks = (int) str_replace([',', '%'], '', (string) ($cols[$clicksKey] ?? 0));
                $impressions = (int) str_replace([',', '%'], '', (string) ($cols[$impressionsKey] ?? 0));
                $ctr = $ctrKey !== null ? (float) str_replace(['%', ','], '', (string) ($cols[$ctrKey] ?? 0)) : ($impressions > 0 ? ($clicks / $impressions) * 100 : 0);
                $position = $positionKey !== null ? (float) str_replace(',', '', (string) ($cols[$positionKey] ?? 0)) : 0;
                $label = $pageKey !== null ? (string) ($cols[$pageKey] ?? 'Unknown') : 'Unknown';
                $totalClicks += $clicks;
                $totalImpressions += $impressions;
                if ($position > 0) {
                    $positionTotal += $position;
                    $positionRows++;
                }
                $gscRows[] = compact('label', 'clicks', 'impressions', 'ctr', 'position');
            }
            usort($gscRows, static fn(array $a, array $b): int => ((int) $b['clicks']) <=> ((int) $a['clicks']));
            $gscRows = array_slice($gscRows, 0, 5);
            $gscSummary = [
                'clicks' => $totalClicks,
                'impressions' => $totalImpressions,
                'ctr' => $totalImpressions > 0 ? round(($totalClicks / $totalImpressions) * 100, 2) : 0,
                'position' => $positionRows > 0 ? round($positionTotal / $positionRows, 1) : 0,
            ];
            $gscReady = true;
            $gscNote = 'Imported CSV summary for this dashboard view.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar dashboard-hero">
  <div>
    <span class="admin-kicker"><i class="fas fa-chart-line"></i> CMS Overview</span>
    <h1 class="admin-page-title">Everything in one place</h1>
    <p class="admin-page-copy">Track content production, jump into the editor, manage team access, and keep the media library organised from one control panel.</p>
  </div>
  <div class="dashboard-hero-side">
    <div class="admin-user-chip"><i class="fas fa-user-shield"></i> <?= cms_e($currentAdmin['full_name'] ?? 'Admin') ?></div>
    <div class="dashboard-command" data-dashboard-command-wrap>
      <i class="fas fa-magnifying-glass"></i>
      <input type="search" placeholder="Search tools, pages, actions..." data-dashboard-command aria-label="Search dashboard tools">
      <div class="dashboard-command-results" data-dashboard-command-results hidden>
        <?php foreach (array_merge($quickActions, [
            ['label' => 'Mail Copies', 'icon' => 'fas fa-envelope-open-text', 'href' => 'admin/mail.php'],
            ['label' => 'Newsletter', 'icon' => 'fas fa-paper-plane', 'href' => 'admin/newsletter.php'],
            ['label' => 'API Tokens', 'icon' => 'fas fa-key', 'href' => 'admin/api-tokens.php'],
            ['label' => 'SEO Settings', 'icon' => 'fas fa-magnifying-glass-chart', 'href' => 'admin/seo.php'],
        ]) as $command): ?>
          <a href="<?= cms_e(!empty($command['external']) ? cms_url('') : cms_url($command['href'])) ?>" data-dashboard-command-item <?= !empty($command['external']) ? 'target="_blank" rel="noopener"' : '' ?>>
            <i class="<?= cms_e($command['icon']) ?>"></i><span><?= cms_e($command['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="dashboard-control-grid">
  <article class="admin-card readiness-card <?= cms_e($readinessClass) ?>">
    <div class="readiness-score">
      <div class="readiness-ring" style="--score: <?= (int) $readinessScore ?>%">
        <strong><?= (int) $readinessScore ?></strong>
        <span>/100</span>
      </div>
      <div>
        <span class="admin-kicker"><i class="fas fa-shield-heart"></i> Production Readiness</span>
        <h2><?= $readinessScore >= 85 ? 'Ready with light checks' : ($readinessScore >= 60 ? 'Almost ready' : 'Needs attention') ?></h2>
        <p><?= $readyCount ?> of <?= count($readinessChecks) ?> launch checks are passing.</p>
      </div>
    </div>
    <div class="readiness-checks">
      <?php foreach ($readinessChecks as $check): ?>
        <a href="<?= cms_e(cms_url($check['href'])) ?>" class="readiness-check <?= $check['ok'] ? 'is-ok' : 'is-missing' ?>">
          <i class="fas <?= $check['ok'] ? 'fa-check' : 'fa-arrow-right' ?>"></i>
          <span><?= cms_e($check['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="admin-card action-center-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-list-check"></i> Action Center</h2>
        <p>Highest-impact items to review next.</p>
      </div>
      <span class="utility-status <?= $actionItems === [] ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= count($actionItems) ?> Open</span>
    </div>
    <?php if ($actionItems === []): ?>
      <div class="dashboard-empty-good">
        <i class="fas fa-circle-check"></i>
        <strong>No urgent dashboard actions.</strong>
        <span>Forms, mail copies, cache, backups, and API risk checks look calm.</span>
      </div>
    <?php else: ?>
      <div class="dashboard-action-list">
        <?php foreach (array_slice($actionItems, 0, 6) as $item): ?>
          <div class="dashboard-action-item is-<?= cms_e($item['tone']) ?>">
            <i class="<?= cms_e($item['icon']) ?>"></i>
            <div>
              <strong><?= cms_e($item['title']) ?></strong>
              <span><?= cms_e($item['copy']) ?></span>
            </div>
            <a href="<?= cms_e(cms_url($item['href'])) ?>" class="admin-btn admin-btn-outline admin-btn-sm"><?= cms_e($item['button']) ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</section>

<section class="quick-action-strip" aria-label="Quick actions">
  <?php foreach ($quickActions as $action): ?>
    <a href="<?= cms_e(!empty($action['external']) ? cms_url('') : cms_url($action['href'])) ?>" class="quick-action-button" <?= !empty($action['external']) ? 'target="_blank" rel="noopener"' : '' ?>>
      <i class="<?= cms_e($action['icon']) ?>"></i>
      <span><?= cms_e($action['label']) ?></span>
    </a>
  <?php endforeach; ?>
</section>

<section class="dashboard-health-grid">
  <?php foreach ($healthItems as $item): ?>
    <a href="<?= cms_e(cms_url($item['href'])) ?>" class="dashboard-health-item <?= $item['ok'] ? 'is-ok' : 'is-warn' ?>">
      <span><?= cms_e($item['label']) ?></span>
      <strong><?= cms_e($item['value']) ?></strong>
      <i class="fas <?= $item['ok'] ? 'fa-check-circle' : 'fa-circle-exclamation' ?>"></i>
    </a>
  <?php endforeach; ?>
</section>

<div class="dashboard-section-head">
  <div>
    <span>Growth Control</span>
    <h2>SEO, Links, Sitemap, Speed, and AI Search</h2>
  </div>
  <a href="<?= cms_e(cms_url('admin/seo.php')) ?>">Open SEO center <i class="fas fa-arrow-right"></i></a>
</div>

<section class="dashboard-feature-grid">
  <article class="admin-card dashboard-feature-card dashboard-issue-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-triangle-exclamation"></i> SEO Issue Queue</h2>
        <p>Prioritized title, meta, H1, thin-content, image-alt, and freshness issues.</p>
      </div>
      <span class="utility-status <?= $issueRows === [] ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= number_format(count($issueRows)) ?></span>
    </div>
    <?php if ($issueRows === []): ?>
      <div class="dashboard-empty-good"><i class="fas fa-circle-check"></i><strong>No SEO issues found.</strong><span>The scanned content looks clean.</span></div>
    <?php else: ?>
      <div class="dashboard-mini-list">
        <?php foreach (array_slice($issueRows, 0, 8) as $issue): ?>
          <a href="<?= cms_e((string) $issue['href']) ?>" class="dashboard-mini-row is-<?= cms_e((string) $issue['severity']) ?>">
            <i class="fas <?= $issue['severity'] === 'bad' ? 'fa-circle-xmark' : ($issue['severity'] === 'warn' ? 'fa-circle-exclamation' : 'fa-clock') ?>"></i>
            <span><strong><?= cms_e((string) $issue['title']) ?></strong><small><?= cms_e((string) $issue['detail']) ?></small></span>
            <b><?= cms_e((string) $issue['type']) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-sitemap"></i> Sitemap Health</h2>
        <p>Index coverage basics for crawl discovery.</p>
      </div>
      <span class="utility-status <?= $sitemapExists ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $sitemapExists ? 'Live' : 'Missing' ?></span>
    </div>
    <div class="dashboard-kpi-grid">
      <div><span>Sitemap URLs</span><strong><?= number_format($sitemapUrls) ?></strong></div>
      <div><span>Content Surface</span><strong><?= number_format($publishedSurface) ?></strong></div>
      <div><span>Last Generated</span><strong><?= cms_e($sitemapUpdated !== '' ? $sitemapUpdated : 'Never') ?></strong></div>
      <div><span>Robots.txt</span><strong><?= $robotsExists ? 'Found' : 'Missing' ?></strong></div>
    </div>
    <div class="dashboard-card-actions">
      <a href="<?= cms_e(cms_url('admin/seo.php')) ?>" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-arrows-rotate"></i> Regenerate</a>
      <?php if ($sitemapExists): ?><a href="<?= cms_e(cms_url('sitemap.xml')) ?>" target="_blank" rel="noopener" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-up-right-from-square"></i> View</a><?php endif; ?>
    </div>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-gauge-high"></i> Page Speed Watchlist</h2>
        <p>Flags large HTML pages, image-heavy layouts, and missing lazy loading.</p>
      </div>
      <span class="utility-status <?= $speedRows === [] ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= number_format(count($speedRows)) ?></span>
    </div>
    <?php if ($speedRows === []): ?>
      <div class="dashboard-empty-good"><i class="fas fa-circle-check"></i><strong>No heavy pages flagged.</strong><span>HTML size and image counts look reasonable.</span></div>
    <?php else: ?>
      <div class="dashboard-mini-list">
        <?php foreach (array_slice($speedRows, 0, 6) as $row): ?>
          <a href="<?= cms_e((string) $row['href']) ?>" class="dashboard-mini-row is-warn">
            <i class="fas fa-gauge"></i>
            <span><strong><?= cms_e((string) $row['title']) ?></strong><small><?= cms_e((string) $row['note']) ?></small></span>
            <b><?= number_format(((int) $row['size']) / 1024, 0) ?> KB</b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-link"></i> Internal Link Opportunities</h2>
        <p>Title-similarity suggestions for adding contextual internal links.</p>
      </div>
    </div>
    <?php if ($internalLinkIdeas === []): ?>
      <div class="dashboard-empty-good"><i class="fas fa-circle-check"></i><strong>No obvious opportunities.</strong><span>Add more published content to expand suggestions.</span></div>
    <?php else: ?>
      <div class="dashboard-mini-list">
        <?php foreach ($internalLinkIdeas as $idea): ?>
          <a href="<?= cms_e((string) $idea['href']) ?>" class="dashboard-mini-row is-info">
            <i class="fas fa-arrow-right-arrow-left"></i>
            <span><strong><?= cms_e((string) $idea['from']) ?></strong><small>Link to: <?= cms_e((string) $idea['to']) ?></small></span>
            <b><?= (int) $idea['score'] ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-route"></i> 404 & Redirect Monitor</h2>
        <p>Redirect inventory and 404 recovery readiness.</p>
      </div>
      <span class="utility-status <?= $notFoundExists ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $notFoundExists ? '404 Ready' : 'Needs 404' ?></span>
    </div>
    <div class="dashboard-kpi-grid">
      <div><span>Redirect Rules</span><strong><?= number_format(count($redirects)) ?></strong></div>
      <div><span>404 Page</span><strong><?= $notFoundExists ? 'Found' : 'Missing' ?></strong></div>
      <div><span>Broken Link Tool</span><strong>Ready</strong></div>
      <div><span>Log Source</span><strong>Server</strong></div>
    </div>
    <div class="dashboard-card-actions">
      <a href="<?= cms_e(cms_url('admin/seo.php#redirects')) ?>" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-route"></i> Manage Redirects</a>
      <a href="<?= cms_e(cms_url('admin/link-checker.php')) ?>" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-link-slash"></i> Link Audit</a>
    </div>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-robot"></i> AI Search / AEO Checklist</h2>
        <p>Coverage for answer-ready content, schema, FAQ signals, and authorship.</p>
      </div>
    </div>
    <div class="dashboard-progress-list">
      <?php foreach ([['Schema', 'schema'], ['FAQ Signals', 'faq'], ['Author Signals', 'author'], ['Answer Summary', 'summary']] as $check): ?>
        <?php $pct = $aiChecklistTotals['items'] > 0 ? (int) round(($aiChecklistTotals[$check[1]] / $aiChecklistTotals['items']) * 100) : 0; ?>
        <div class="dashboard-progress-row">
          <span><?= cms_e($check[0]) ?></span>
          <strong><?= $pct ?>%</strong>
          <div><i style="width:<?= $pct ?>%"></i></div>
        </div>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-chart-simple"></i> Search Console Import</h2>
        <p><?= cms_e($gscNote) ?></p>
      </div>
      <span class="utility-status <?= $gscReady ? 'is-on' : 'is-off' ?>"><i class="fas fa-circle"></i><?= $gscReady ? 'Connected' : 'Ready' ?></span>
    </div>
    <?php if ($gscError !== ''): ?><div class="admin-flash admin-flash-error"><?= cms_e($gscError) ?></div><?php endif; ?>
    <?php if ($gscSummary !== null): ?>
      <div class="dashboard-kpi-grid">
        <div><span>Clicks</span><strong><?= number_format((int) $gscSummary['clicks']) ?></strong></div>
        <div><span>Impressions</span><strong><?= number_format((int) $gscSummary['impressions']) ?></strong></div>
        <div><span>CTR</span><strong><?= number_format((float) $gscSummary['ctr'], 2) ?>%</strong></div>
        <div><span>Avg Position</span><strong><?= number_format((float) $gscSummary['position'], 1) ?></strong></div>
      </div>
      <div class="dashboard-mini-list dashboard-gsc-list">
        <?php foreach ($gscRows as $row): ?>
          <div class="dashboard-mini-row is-info">
            <i class="fas fa-chart-line"></i>
            <span><strong><?= cms_e(mb_strimwidth((string) $row['label'], 0, 72, '...')) ?></strong><small><?= number_format((int) $row['impressions']) ?> impressions, <?= number_format((float) $row['ctr'], 2) ?>% CTR</small></span>
            <b><?= number_format((int) $row['clicks']) ?></b>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="dashboard-gsc-form">
      <?= cms_csrf_field() ?>
      <input type="hidden" name="action" value="import_gsc_csv">
      <textarea name="gsc_csv" rows="4" placeholder="Paste Search Console CSV export here"></textarea>
      <div class="dashboard-card-actions">
        <input type="file" name="gsc_file" accept=".csv,text/csv">
        <button type="submit" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-upload"></i> Import CSV</button>
      </div>
    </form>
  </article>
</section>

<div class="dashboard-section-head">
  <div>
    <span>CMS Metrics</span>
    <h2>Publishing and Asset Inventory</h2>
  </div>
  <a href="<?= cms_e(cms_url('admin/activity-log.php')) ?>">View activity <i class="fas fa-arrow-right"></i></a>
</div>

<section class="stats-grid dashboard-stats-grid">
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-newspaper"></i></span>
    <span class="admin-kicker">Posts</span>
    <strong><?= (int) $stats['posts_total'] ?></strong>
    <p>Total blog entries in the CMS database.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-circle-check"></i></span>
    <span class="admin-kicker">Published</span>
    <strong><?= (int) $stats['posts_published'] ?></strong>
    <p>Articles already live on the public blog page.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-file-pen"></i></span>
    <span class="admin-kicker">Drafts</span>
    <strong><?= (int) $stats['posts_draft'] ?></strong>
    <p>Posts waiting for review, polish, or scheduling.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-photo-film"></i></span>
    <span class="admin-kicker">Media</span>
    <strong><?= (int) $stats['media_total'] ?></strong>
    <p>Uploaded images, files, and rich assets ready to reuse.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-file-lines"></i></span>
    <span class="admin-kicker">Pages</span>
    <strong><?= (int) $stats['pages_total'] ?></strong>
    <p>Dynamic pages and service templates in the builder.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-layer-group"></i></span>
    <span class="admin-kicker">Sections</span>
    <strong><?= (int) $stats['templates_total'] ?></strong>
    <p>Reusable widget templates saved for future pages.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-clipboard-list"></i></span>
    <span class="admin-kicker">Forms</span>
    <strong><?= $formCount ?></strong>
    <p><?= $unreadLeads > 0 ? "<strong style='color:var(--admin-primary)'>$unreadLeads unread</strong> lead submissions." : 'No unread form submissions.' ?></p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-envelope-open-text"></i></span>
    <span class="admin-kicker">Mail Copies</span>
    <strong><?= number_format($mailCopyCount) ?></strong>
    <p>Saved public contact form messages available for export.</p>
  </article>
  <article class="stats-card">
    <span class="stats-icon"><i class="fas fa-database"></i></span>
    <span class="admin-kicker">Custom Types</span>
    <strong><?= $cptCount ?></strong>
    <p>Custom content structures registered in the CMS.</p>
  </article>
</section>

<div class="dashboard-section-head">
  <div>
    <span>Operating Snapshot</span>
    <h2>Content, Leads, and Security</h2>
  </div>
</div>

<section class="dashboard-snapshot-grid">
  <article class="admin-card snapshot-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-file-lines"></i> Content Snapshot</h2>
        <p>Publishing surface across static pages, CMS pages, and posts.</p>
      </div>
    </div>
    <div class="snapshot-list">
      <div><span>Static HTML pages</span><strong><?= number_format($staticPageCount) ?></strong></div>
      <div><span>CMS pages</span><strong><?= number_format((int) $stats['pages_total']) ?></strong></div>
      <div><span>Published posts</span><strong><?= number_format((int) $stats['posts_published']) ?></strong></div>
      <div><span>Draft posts</span><strong><?= number_format((int) $stats['posts_draft']) ?></strong></div>
    </div>
  </article>

  <article class="admin-card snapshot-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-address-book"></i> Leads & Newsletter</h2>
        <p>Fresh lead and subscriber signals.</p>
      </div>
    </div>
    <div class="snapshot-list">
      <div><span>Unread form leads</span><strong><?= number_format($unreadLeads) ?></strong></div>
      <div><span>New mail copies</span><strong><?= number_format($newMailCopies) ?></strong></div>
      <div><span>Confirmed subscribers</span><strong><?= number_format($newsletterConfirmed) ?></strong></div>
      <div><span>Pending subscribers</span><strong><?= number_format($newsletterPending) ?></strong></div>
    </div>
  </article>

  <article class="admin-card snapshot-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-user-shield"></i> Security Snapshot</h2>
        <p>Admin-facing access and automation risk.</p>
      </div>
    </div>
    <div class="snapshot-list">
      <div><span>Active API tokens</span><strong><?= number_format($activeApiTokens) ?></strong></div>
      <div><span>Delete-scope tokens</span><strong><?= number_format($deleteApiTokens) ?></strong></div>
      <div><span>Activity log rows</span><strong><?= number_format($activityCount) ?></strong></div>
      <div><span>Cached pages</span><strong><?= number_format((int) ($cacheStats['db_entries'] ?? $cacheStats['file_count'] ?? 0)) ?></strong></div>
    </div>
  </article>
</section>

<section class="dashboard-feature-grid dashboard-ops-grid">
  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-chart-pie"></i> Content Performance Snapshot</h2>
        <p>Freshness and content depth indicators across all scanned assets.</p>
      </div>
    </div>
    <div class="dashboard-kpi-grid">
      <div><span>Scanned Items</span><strong><?= number_format(count($contentRows)) ?></strong></div>
      <div><span>Stale 90+ Days</span><strong><?= number_format($staleContentCount) ?></strong></div>
      <div><span>Avg Words</span><strong><?= count($contentRows) > 0 ? number_format((int) round(array_sum(array_column($contentRows, 'words')) / count($contentRows))) : '0' ?></strong></div>
      <div><span>Avg Links</span><strong><?= count($contentRows) > 0 ? number_format((int) round(array_sum(array_column($contentRows, 'links')) / count($contentRows))) : '0' ?></strong></div>
    </div>
    <div class="dashboard-mini-list">
      <?php foreach (array_slice($contentRows, 0, 4) as $row): ?>
        <a href="<?= cms_e((string) $row['href']) ?>" class="dashboard-mini-row is-info">
          <i class="fas fa-file-lines"></i>
          <span><strong><?= cms_e((string) $row['title']) ?></strong><small><?= number_format((int) $row['words']) ?> words, <?= number_format((int) $row['links']) ?> links</small></span>
          <b><?= cms_e((string) $row['type']) ?></b>
        </a>
      <?php endforeach; ?>
    </div>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-bullseye"></i> Lead & Form Insights</h2>
        <p>Top requested services and source pages from saved contact messages.</p>
      </div>
    </div>
    <div class="dashboard-two-col-list">
      <div>
        <h3>Services</h3>
        <?php foreach ($leadServiceRows ?: [['label' => 'No lead data yet', 'total' => 0]] as $row): ?>
          <div><span><?= cms_e((string) $row['label']) ?></span><strong><?= number_format((int) $row['total']) ?></strong></div>
        <?php endforeach; ?>
      </div>
      <div>
        <h3>Sources</h3>
        <?php foreach ($leadSourceRows ?: [['label' => 'No source data yet', 'total' => 0]] as $row): ?>
          <div><span><?= cms_e(mb_strimwidth((string) $row['label'], 0, 34, '...')) ?></span><strong><?= number_format((int) $row['total']) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-calendar-days"></i> Content Calendar</h2>
        <p>Scheduled posts and drafts waiting for publishing decisions.</p>
      </div>
      <a href="<?= cms_e(cms_url('admin/blogs.php?action=new')) ?>" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-plus"></i> Add</a>
    </div>
    <?php if ($contentCalendarRows === []): ?>
      <div class="dashboard-empty-good"><i class="fas fa-calendar-check"></i><strong>No scheduled or draft posts.</strong><span>The editorial queue is clear.</span></div>
    <?php else: ?>
      <div class="dashboard-mini-list">
        <?php foreach ($contentCalendarRows as $row): ?>
          <a href="<?= cms_e(cms_url('admin/blogs.php?edit=' . (int) ($row['id'] ?? 0))) ?>" class="dashboard-mini-row is-info">
            <i class="fas fa-calendar-day"></i>
            <span><strong><?= cms_e((string) $row['title']) ?></strong><small><?= cms_e(($row['scheduled_at'] ?? '') === 'Draft' ? 'Draft needs a publish date' : cms_format_date((string) $row['scheduled_at'], 'M j, Y g:i A')) ?></small></span>
            <b><?= ($row['scheduled_at'] ?? '') === 'Draft' ? 'Draft' : 'Scheduled' ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>

  <article class="admin-card dashboard-feature-card">
    <div class="utility-card-head">
      <div>
        <h2><i class="fas fa-lock"></i> Backup & Security Status</h2>
        <p>Operational safety checks for exports, cache, API tokens, and admin activity.</p>
      </div>
    </div>
    <div class="dashboard-kpi-grid">
      <div><span>Last Backup</span><strong><?= $lastBackupAt !== '' ? cms_e(cms_format_date($lastBackupAt, 'M j')) : 'Never' ?></strong></div>
      <div><span>Backup Age</span><strong><?= $lastBackupAgeDays === null ? 'N/A' : number_format($lastBackupAgeDays) . 'd' ?></strong></div>
      <div><span>Risk Tokens</span><strong><?= number_format($deleteApiTokens) ?></strong></div>
      <div><span>Cache</span><strong><?= $cacheEnabled ? 'On' : 'Off' ?></strong></div>
    </div>
    <div class="dashboard-card-actions">
      <a href="<?= cms_e(cms_url('admin/backup.php')) ?>" class="admin-btn admin-btn-primary admin-btn-sm"><i class="fas fa-cloud-arrow-down"></i> Backup</a>
      <a href="<?= cms_e(cms_url('admin/api-tokens.php')) ?>" class="admin-btn admin-btn-outline admin-btn-sm"><i class="fas fa-key"></i> Tokens</a>
    </div>
  </article>
</section>

<div class="dashboard-section-head">
  <div>
    <span>Recent Work</span>
    <h2>Latest Messages, Pages, Posts, and Media</h2>
  </div>
</div>

<section class="dashboard-grid">
  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3>Latest mail copies</h3>
        <p class="muted">Public contact form messages saved in the dashboard.</p>
      </div>
      <div class="card-actions">
        <a href="<?= cms_e(cms_url('admin/mail.php')) ?>" class="btn btn-primary btn-sm"><i class="fas fa-envelope-open-text"></i> Open Inbox</a>
        <a href="<?= cms_e(cms_url('admin/mail.php?download=csv')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-file-csv"></i> Export CSV</a>
      </div>
    </div>

    <?php if ($recentMailCopies === []): ?>
      <div class="empty-state">No public contact mail copies saved yet.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Contact</th><th>Service</th><th>Message</th><th>Received</th></tr></thead>
          <tbody>
            <?php foreach ($recentMailCopies as $mail): ?>
              <tr>
                <td><strong><?= cms_e((string) $mail['full_name']) ?></strong><p><?= cms_e((string) $mail['email']) ?></p></td>
                <td><?= cms_e((string) ($mail['service_interest'] ?? '')) ?></td>
                <td><?= cms_e(mb_strimwidth((string) $mail['message'], 0, 120, '...')) ?></td>
                <td><?= cms_e(cms_format_date((string) $mail['created_at'], 'M j g:i A')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3>Recent pages</h3>
        <p class="muted">Service pages, landing pages, and standard pages built in the new visual CMS.</p>
      </div>
      <div class="card-actions">
        <a href="<?= cms_e(cms_url('admin/pages.php')) ?>" class="btn btn-primary btn-sm"><i class="fas fa-laptop-code"></i> Manage Pages</a>
      </div>
    </div>

    <?php if ($recentPages === []): ?>
      <div class="empty-state">No dynamic pages yet. Build your first service page from the pages screen.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Type</th>
              <th>Status</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPages as $page): ?>
              <tr>
                <td><strong><?= cms_e((string) $page['title']) ?></strong><p><?= cms_e((string) $page['slug']) ?></p></td>
                <td><?= cms_e(ucfirst((string) $page['page_type'])) ?></td>
                <td><span class="status-badge status-<?= cms_e((string) $page['status']) ?>"><?= cms_e((string) $page['status']) ?></span></td>
                <td><?= cms_e(cms_format_date((string) ($page['updated_at'] ?? ''), 'M j, Y g:i A')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3>Recent blog posts</h3>
        <p class="muted">Jump back into your latest drafts and published entries.</p>
      </div>
      <div class="card-actions">
        <a href="<?= cms_e(cms_url('admin/blogs.php')) ?>" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i> Manage Posts</a>
      </div>
    </div>

    <?php if ($recentPosts === []): ?>
      <div class="empty-state">No posts yet. Create your first draft from the blogs screen.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Status</th>
              <th>Category</th>
              <th>Updated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recentPosts as $post): ?>
              <tr>
                <td>
                  <strong><?= cms_e((string) $post['title']) ?></strong>
                  <p><?= cms_e((string) $post['slug']) ?></p>
                </td>
                <td><span class="status-badge status-<?= cms_e((string) $post['status']) ?>"><?= cms_e((string) $post['status']) ?></span></td>
                <td><?= cms_e((string) ($post['category'] ?? 'Uncategorised')) ?></td>
                <td><?= cms_e(cms_format_date((string) ($post['updated_at'] ?? ''), 'M j, Y g:i A')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3>Latest media</h3>
        <p class="muted">Copy URLs from the library directly into featured images or article content.</p>
      </div>
      <div class="card-actions">
        <a href="<?= cms_e(cms_url('admin/media.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-photo-film"></i> Open Media</a>
      </div>
    </div>

    <?php if ($recentMedia === []): ?>
      <div class="empty-state">Upload the first featured image, PDF, or video into the CMS media library.</div>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($recentMedia as $media): ?>
          <?php $mediaUrl = cms_media_url((string) $media['file_path']); ?>
          <article class="media-card">
            <div class="media-preview">
              <?php if (str_starts_with((string) $media['mime_type'], 'image/')): ?>
                <img src="<?= cms_e($mediaUrl) ?>" alt="<?= cms_e((string) ($media['alt_text'] ?: $media['title'])) ?>" />
              <?php elseif (str_starts_with((string) $media['mime_type'], 'video/')): ?>
                <video src="<?= cms_e($mediaUrl) ?>" muted playsinline></video>
              <?php else: ?>
                <i class="fas fa-file-lines"></i>
              <?php endif; ?>
            </div>
            <div class="media-body">
              <strong><?= cms_e((string) $media['title']) ?></strong>
              <input class="media-url" type="text" readonly value="<?= cms_e($mediaUrl) ?>" />
              <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e($mediaUrl) ?>">Copy URL</button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </article>
</section>
<!-- ── Activity log widget ──────────────────────────────────────────────── -->
<?php if ($recentLog !== []): ?>
<article class="table-card" style="margin-top:24px">
  <div class="toolbar">
    <div>
      <h3>Recent activity</h3>
      <p class="muted">Latest actions by all admin users.</p>
    </div>
    <a href="<?= cms_e(cms_url('admin/activity-log.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-clock-rotate-left"></i> Full Log</a>
  </div>
  <div class="table-scroll">
    <table>
      <thead><tr><th>Action</th><th>Admin</th><th>Details</th><th>Time</th></tr></thead>
      <tbody>
        <?php foreach ($recentLog as $entry): ?>
          <tr>
            <td><strong><?= cms_e(ucwords(str_replace('_', ' ', (string) $entry['action']))) ?></strong></td>
            <td><?= cms_e((string) $entry['admin_name']) ?></td>
            <td class="muted"><?= cms_e((string) $entry['entity_label']) ?></td>
            <td><?= cms_e(cms_format_date((string) $entry['created_at'], 'M j g:i A')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</article>
<?php endif; ?>

<script>
(function () {
  var input = document.querySelector('[data-dashboard-command]');
  var results = document.querySelector('[data-dashboard-command-results]');
  if (!input || !results) return;
  var items = Array.prototype.slice.call(results.querySelectorAll('[data-dashboard-command-item]'));

  function update() {
    var query = input.value.trim().toLowerCase();
    var visible = 0;
    items.forEach(function (item) {
      var match = query === '' || item.textContent.toLowerCase().indexOf(query) !== -1;
      item.hidden = !match;
      if (match) visible++;
    });
    results.hidden = document.activeElement !== input || visible === 0;
  }

  input.addEventListener('input', update);
  input.addEventListener('focus', update);
  document.addEventListener('click', function (event) {
    if (!event.target.closest('[data-dashboard-command-wrap]')) {
      results.hidden = true;
    }
  });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
