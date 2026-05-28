<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/public-layout.php';
require_once __DIR__ . '/php/cms/page-renderer.php';

$context = cms_page_request_context();
$pageType = match ($context['prefix']) {
    'services' => 'service',
    'landing' => 'landing',
    default => 'page',
};

$previewId = cms_is_logged_in() ? cms_query_int('preview_page') : 0;
$page = $previewId > 0
    ? cms_get_admin_page_for_preview($previewId)
    : cms_get_published_page_by_slug((string) $context['slug'], $pageType);

if (!is_array($page)) {
    http_response_code(404);
}

$title = is_array($page) ? (string) ($page['meta_title'] ?: $page['title']) : 'Page Not Found';
$description = is_array($page) ? (string) ($page['meta_description'] ?: $page['excerpt']) : 'The requested page could not be found.';
$canonical = is_array($page) && (string) ($page['canonical_url'] ?? '') !== ''
    ? (string) $page['canonical_url']
    : (is_array($page) ? cms_page_url((string) $page['slug'], (string) $page['page_type']) : cms_url(''));
$seoOverride = cms_seo_override_for_url(is_array($page) ? cms_page_url((string) $page['slug'], (string) $page['page_type']) : $canonical);
$title = (string) ($seoOverride['meta_title'] ?: $title);
$description = (string) ($seoOverride['meta_description'] ?: $description);
$canonical = (string) ($seoOverride['canonical_url'] ?: $canonical);
$seoSettings = cms_seo_settings();
$ogImage = is_array($page) ? (string) ($page['og_image'] ?: $page['featured_image'] ?: ($seoSettings['og_default_image'] ?? '')) : '';
$googleVerification = cms_verification_meta_content((string) ($seoSettings['google_search_console'] ?? ''), 'google-site-verification');
$bingVerification = cms_verification_meta_content((string) ($seoSettings['bing_verification'] ?? ''), 'msvalidate.01');
$customHtml = is_array($page) ? trim((string) ($page['custom_html'] ?? '')) : '';
$customHtmlIsImportedStaticDesign = str_contains($customHtml, '<!-- cms:imported-static-design:start -->')
    && str_contains($customHtml, '<!-- cms:imported-static-design:end -->');
if ($customHtmlIsImportedStaticDesign) {
    $customHtml = str_replace(
        ['<!-- cms:imported-static-design:start -->', '<!-- cms:imported-static-design:end -->'],
        '',
        $customHtml
    );
    $customHtml = trim($customHtml);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= cms_e($description) ?>" />
  <?php if ($googleVerification !== []): ?>
  <meta name="<?= cms_e((string) $googleVerification['name']) ?>" content="<?= cms_e((string) $googleVerification['content']) ?>" />
  <?php endif; ?>
  <?php if ($bingVerification !== []): ?>
  <meta name="<?= cms_e((string) $bingVerification['name']) ?>" content="<?= cms_e((string) $bingVerification['content']) ?>" />
  <?php endif; ?>
  <meta property="og:title" content="<?= cms_e($title) ?>" />
  <meta property="og:description" content="<?= cms_e($description) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= cms_e($canonical) ?>" />
  <?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= cms_e(cms_media_url($ogImage)) ?>" /><?php endif; ?>
  <?php $twitterCard = $seoSettings['twitter_card_type'] ?? 'summary_large_image'; ?>
  <meta name="twitter:card" content="<?= cms_e($twitterCard) ?>" />
  <meta name="twitter:title" content="<?= cms_e($title) ?>" />
  <meta name="twitter:description" content="<?= cms_e($description) ?>" />
  <?php if ($ogImage !== ''): ?><meta name="twitter:image" content="<?= cms_e(cms_media_url($ogImage)) ?>" /><?php endif; ?>
  <?php if (($seoSettings['twitter_site'] ?? '') !== ''): ?>
  <meta name="twitter:site" content="<?= cms_e((string) $seoSettings['twitter_site']) ?>" />
  <?php endif; ?>
  <title><?= cms_e($title) ?></title>
  <link rel="canonical" href="<?= cms_e($canonical) ?>" />
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/style.css?v=20260513')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/responsive.css?v=20260513')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/cms-pages.css?v=20260504')) ?>" />
  <?php if (($seoSettings['schema_org_json'] ?? '') !== ''): ?>
  <?= str_contains((string) $seoSettings['schema_org_json'], '<script') ? $seoSettings['schema_org_json'] : '<script type="application/ld+json">' . "\n" . $seoSettings['schema_org_json'] . "\n</script>" ?>
  <?php endif; ?>
  <?php if (is_array($page) && trim((string) ($page['schema_json'] ?? '')) !== ''): ?><script type="application/ld+json"><?= (string) $page['schema_json'] ?></script><?php endif; ?>
  <?php if (is_array($page) && trim((string) ($page['custom_css'] ?? '')) !== ''): ?><style><?= (string) $page['custom_css'] ?></style><?php endif; ?>
  <?php if (($seoSettings['google_analytics_id'] ?? '') !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= cms_e((string) $seoSettings['google_analytics_id']) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= cms_e((string) $seoSettings['google_analytics_id']) ?>');</script>
  <?php endif; ?>
</head>
<body>
<?php cms_public_nav('services'); ?>
<?php if (!is_array($page)): ?>
  <section class="section" style="padding-top:140px"><div class="container"><div class="cms-empty-state"><h2>Page not found</h2><p>The page you requested does not exist or is not published yet.</p></div></div></section>
<?php else: ?>
  <?= cms_render_widget_list((array) ($page['builder'] ?? [])) ?>
  <?php if ($customHtml !== ''): ?>
    <?php if ($customHtmlIsImportedStaticDesign): ?>
      <?= $customHtml ?>
    <?php else: ?>
      <section class="section"><div class="container"><?= $customHtml ?></div></section>
    <?php endif; ?>
  <?php endif; ?>
  <?php if (trim((string) ($page['custom_js'] ?? '')) !== ''): ?><script><?= (string) $page['custom_js'] ?></script><?php endif; ?>
<?php endif; ?>
<?php cms_public_footer(); ?>
</body>
</html>
