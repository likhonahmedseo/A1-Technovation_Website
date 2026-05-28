<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/functions.php';
require_once __DIR__ . '/php/cms/public-layout.php';

if (cms_begin_public_page_cache()) {
    return;
}

$previewId = cms_is_logged_in() ? cms_query_int('preview_post') : 0;
$slug = cms_blog_slug_from_request();
$post = null;
$relatedPosts = [];
$dbError = '';
$staticFallbackPosts = [];
foreach (cms_static_blog_sources() as $staticBlog) {
    $staticFallbackPosts[(string) $staticBlog['slug']] = __DIR__ . '/' . ltrim((string) $staticBlog['path'], '/');
}

try {
    if ($previewId > 0) {
        $post = cms_get_admin_post_for_preview($previewId);
    } elseif ($slug !== '') {
        $post = cms_get_published_post_by_slug($slug);
    }

    if (is_array($post)) {
        $relatedPosts = cms_get_related_posts((int) $post['id'], (string) ($post['category'] ?? ''), 3);
    }
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

if (!is_array($post) && isset($staticFallbackPosts[$slug]) && is_file($staticFallbackPosts[$slug])) {
    http_response_code(200);
    readfile($staticFallbackPosts[$slug]);
    exit;
}

if (!is_array($post)) {
    http_response_code(404);
}

$canonicalUrl = is_array($post)
    ? cms_blog_post_url((string) $post['slug'])
    : cms_blog_index_url();
$seoOverride = cms_seo_override_for_url($canonicalUrl);
$title = is_array($post) ? (string) ($seoOverride['meta_title'] ?: $post['meta_title'] ?: $post['title']) : 'Blog Post Not Found';
$description = is_array($post) ? (string) ($seoOverride['meta_description'] ?: $post['meta_description'] ?: $post['excerpt']) : 'The requested blog post could not be found.';
$canonicalUrl = (string) ($seoOverride['canonical_url'] ?: $canonicalUrl);
$seoSettings = cms_seo_settings();
$resolvedFeaturedImage = is_array($post) ? cms_resolve_post_featured_image($post) : '';
$ogImage = is_array($post)
    ? cms_media_url((string) ($resolvedFeaturedImage ?: ($seoSettings['og_default_image'] ?: 'assets/images/A1Technovation-Light-Background.png')))
    : cms_url('assets/images/A1Technovation-Light-Background.png');
$googleVerification = cms_verification_meta_content((string) ($seoSettings['google_search_console'] ?? ''), 'google-site-verification');
$bingVerification = cms_verification_meta_content((string) ($seoSettings['bing_verification'] ?? ''), 'msvalidate.01');
$postContentHtml = is_array($post)
    ? cms_process_content_html((string) ($post['content_html'] ?? ''))
    : '';
$postCustomHtml = is_array($post)
    ? cms_process_content_html(trim((string) ($post['custom_html'] ?? '')))
    : '';
$postAuthorImage = is_array($post)
    ? cms_media_url((string) (($post['author_image'] ?? '') ?: cms_default_author_image_url()))
    : cms_default_author_image_url();
$postInterlinksHtml = is_array($post) && !empty($post['default_interlinks_enabled'])
    ? cms_process_content_html((string) (($post['default_interlinks_html'] ?? '') ?: cms_default_blog_interlinks_html()))
    : '';
$layoutMode = is_array($post)
    ? cms_normalize_blog_layout_mode((string) ($post['layout_mode'] ?? 'standard'))
    : 'standard';
$customHeadHtml = is_array($post) ? cms_repair_imported_blog_head_html((string) ($post['head_html'] ?? '')) : '';
$customCss = is_array($post) ? trim((string) ($post['custom_css'] ?? '')) : '';
$customBodyClass = is_array($post) ? trim((string) ($post['body_class'] ?? '')) : '';
$isCustomImportedLayout = is_array($post) && $layoutMode === 'custom_import';
$customImportHasOwnShell = $isCustomImportedLayout
    && (preg_match('#<(?:nav|header)\b#i', $postContentHtml) === 1 || preg_match('#<footer\b#i', $postContentHtml) === 1);
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
  <meta property="og:type" content="article" />
  <meta property="og:url" content="<?= cms_e($canonicalUrl) ?>" />
  <meta property="og:image" content="<?= cms_e($ogImage) ?>" />
  <?php $twitterCard = $seoSettings['twitter_card_type'] ?? 'summary_large_image'; ?>
  <meta name="twitter:card" content="<?= cms_e($twitterCard) ?>" />
  <meta name="twitter:title" content="<?= cms_e($title) ?>" />
  <meta name="twitter:description" content="<?= cms_e($description) ?>" />
  <meta name="twitter:image" content="<?= cms_e($ogImage) ?>" />
  <?php if (($seoSettings['twitter_site'] ?? '') !== ''): ?>
  <meta name="twitter:site" content="<?= cms_e((string) $seoSettings['twitter_site']) ?>" />
  <?php endif; ?>
  <title><?= cms_e($title) ?></title>
  <link rel="canonical" href="<?= cms_e($canonicalUrl) ?>" />
  <?php if (($seoSettings['schema_org_json'] ?? '') !== ''): ?>
  <?= str_contains((string) $seoSettings['schema_org_json'], '<script') ? $seoSettings['schema_org_json'] : '<script type="application/ld+json">' . "\n" . $seoSettings['schema_org_json'] . "\n</script>" ?>
  <?php endif; ?>
  <?php if (($seoSettings['google_analytics_id'] ?? '') !== ''): ?>
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?= cms_e((string) $seoSettings['google_analytics_id']) ?>"></script>
  <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= cms_e((string) $seoSettings['google_analytics_id']) ?>');</script>
  <?php endif; ?>
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <?php if ($customHeadHtml !== ''): ?>
  <?= $customHeadHtml . "\n" ?>
  <?php endif; ?>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/style.css?v=20260513')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/responsive.css?v=20260513')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/cms-pages.css?v=20260504')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/cms-public.css?v=20260504')) ?>" />
  <?php if ($isCustomImportedLayout): ?>
  <style>
<?= cms_imported_blog_fallback_css() . "\n" ?>
    .imported-blog-scroll-top {
      position: fixed;
      right: 24px;
      bottom: 24px;
      width: 52px;
      height: 52px;
      border: 0;
      border-radius: 999px;
      background: linear-gradient(135deg, #2563eb, #7c3aed);
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 18px 36px rgba(37,99,235,.24);
      cursor: pointer;
      opacity: 0;
      visibility: hidden;
      transform: translateY(16px);
      transition: opacity .22s ease, transform .22s ease, visibility .22s ease;
      z-index: 2000;
    }
    .imported-blog-scroll-top.is-visible {
      opacity: 1;
      visibility: visible;
      transform: translateY(0);
    }
    .newsletter-status {
      display: block;
      margin-top: 12px;
      font-size: .875rem;
      line-height: 1.5;
    }
    .newsletter-status.success { color: #16a34a; }
    .newsletter-status.error { color: #dc2626; }
    .cms-imported-post-extras {
      max-width: 980px;
      margin: 32px auto;
      padding: 0 20px;
    }
    .imported-blog-content {
      background: #f8fafc;
      color: #475569;
      font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }
    .imported-blog-content > p,
    .imported-blog-content > h1,
    .imported-blog-content > h2,
    .imported-blog-content > h3,
    .imported-blog-content > h4,
    .imported-blog-content > ul,
    .imported-blog-content > ol,
    .imported-blog-content > table,
    .imported-blog-content > blockquote {
      max-width: 900px;
      margin-left: auto;
      margin-right: auto;
      padding-left: 20px;
      padding-right: 20px;
    }
    .imported-blog-content > p {
      font-size: 1.125rem;
      line-height: 1.8;
      margin-bottom: 1.75rem;
    }
    .imported-blog-content > h1 {
      font-size: clamp(2.25rem, 5vw, 4rem);
      line-height: 1.05;
      font-weight: 800;
      color: #0f172a;
      margin-top: 4rem;
      margin-bottom: 2rem;
    }
    .imported-blog-content > h2 {
      font-size: 1.875rem;
      line-height: 1.3;
      font-weight: 800;
      color: #0f172a;
      margin-top: 3.5rem;
      margin-bottom: 1.25rem;
    }
  </style>
  <?php endif; ?>
  <style>
    .cms-author-line {
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    .cms-author-line img {
      width: 36px;
      height: 36px;
      border-radius: 999px;
      object-fit: cover;
      border: 2px solid rgba(255,255,255,.4);
    }
    .cms-default-interlinks {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      gap: 10px;
      margin: 32px 0 0;
      padding: 18px;
      border: 1px solid rgba(37,99,235,.18);
      border-radius: 12px;
      background: #f8fafc;
    }
    .cms-default-interlinks strong {
      flex-basis: 100%;
      color: #0f172a;
      font-size: .95rem;
    }
    .cms-default-interlinks a {
      display: inline-flex;
      align-items: center;
      min-height: 34px;
      padding: 7px 12px;
      border-radius: 999px;
      background: #fff;
      color: #0f6fec;
      font-size: .875rem;
      font-weight: 700;
      text-decoration: none;
      box-shadow: inset 0 0 0 1px rgba(15,111,236,.18);
    }
  </style>
  <?php if ($customCss !== ''): ?>
  <style>
<?= $customCss . "\n" ?>
  </style>
  <?php endif; ?>
</head>
<body<?= $customBodyClass !== '' ? ' class="' . cms_e($customBodyClass) . '"' : '' ?>>
<?php if ($isCustomImportedLayout): ?>
  <?php if (!$customImportHasOwnShell): ?>
    <?php cms_public_nav('blog'); ?>
  <?php endif; ?>
  <div class="imported-blog-content"><?= $postContentHtml ?></div>
  <?php if ($postCustomHtml !== ''): ?>
    <?= $postCustomHtml ?>
  <?php endif; ?>
  <?php if ($postInterlinksHtml !== ''): ?>
    <div class="cms-imported-post-extras"><?= $postInterlinksHtml ?></div>
  <?php endif; ?>
  <?php if (!$customImportHasOwnShell): ?>
    <?php cms_public_footer(); ?>
  <?php endif; ?>
  <button class="imported-blog-scroll-top" id="importedBlogScrollTop" aria-label="Back to top"><i class="fas fa-arrow-up"></i></button>
  <script>
    (function () {
      var scrollButton = document.getElementById('importedBlogScrollTop');
      var toggleScrollButton = function () {
        if (!scrollButton) return;
        scrollButton.classList.toggle('is-visible', window.scrollY > 420);
      };

      if (scrollButton) {
        window.addEventListener('scroll', toggleScrollButton, { passive: true });
        toggleScrollButton();
        scrollButton.addEventListener('click', function () {
          window.scrollTo({ top: 0, behavior: 'smooth' });
        });
      }

      document.querySelectorAll('[data-copy-link]').forEach(function (button) {
        button.addEventListener('click', async function () {
          try {
            if (navigator.clipboard && navigator.clipboard.writeText) {
              await navigator.clipboard.writeText(window.location.href);
            }
            button.setAttribute('data-copy-state', 'copied');
          } catch (error) {
            window.prompt('Copy this link:', window.location.href);
          }
        });
      });

      document.querySelectorAll('.newsletter-form').forEach(function (form) {
        form.addEventListener('submit', async function (event) {
          event.preventDefault();
          var emailInput = form.querySelector('input[type="email"]');
          var nameInput = form.querySelector('input[name="name"]');
          var submitButton = form.querySelector('[type="submit"]');
          var status = form.querySelector('.newsletter-status');
          var email = emailInput ? emailInput.value.trim() : '';

          if (!email) return;

          var setStatus = function (type, message) {
            if (!status) return;
            status.hidden = false;
            status.className = 'newsletter-status ' + type;
            status.textContent = message;
          };

          var originalHtml = submitButton ? submitButton.innerHTML : '';
          if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
          }

          try {
            var response = await fetch(form.getAttribute('action') || '/api/newsletter_subscribe', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                email: email,
                name: nameInput ? nameInput.value.trim() : '',
                source: form.getAttribute('data-source') || window.location.pathname || 'custom-blog',
              }),
            });
            var result = await response.json().catch(function () { return {}; });
            var message = result.message || (response.ok ? 'Subscribed successfully.' : 'Subscription failed. Please try again.');
            setStatus(response.ok ? 'success' : 'error', message);
            if (response.ok) form.reset();
          } catch (error) {
            setStatus('error', 'Subscription is temporarily unavailable. Please try again later.');
          } finally {
            if (submitButton) {
              submitButton.disabled = false;
              submitButton.innerHTML = originalHtml;
            }
          }
        });
      });
    })();
  </script>
<?php else: ?>
<?php cms_public_nav('blog'); ?>

<?php if (!is_array($post)): ?>
  <section class="section" style="padding-top:140px">
    <div class="container">
      <?php if ($dbError !== ''): ?>
        <div class="cms-flash cms-flash-warning"><?= cms_e($dbError) ?></div>
      <?php endif; ?>
      <div class="cms-empty-state">
        <h2 style="margin-bottom:12px">Post not found</h2>
        <p class="muted">This article is missing or has not been published yet.</p>
        <a href="<?= cms_e(cms_blog_index_url()) ?>" class="btn btn-primary" style="margin-top:16px">Back to Blog</a>
      </div>
    </div>
  </section>
<?php else: ?>
  <section class="page-hero">
    <div class="hero-bg"><div class="hero-grid"></div><div class="hero-glow-1"></div><div class="hero-glow-2"></div></div>
    <div class="container"><div class="page-hero-inner">
      <div class="breadcrumb"><a href="<?= cms_e(cms_url('')) ?>">Home</a><i class="fas fa-chevron-right"></i><a href="<?= cms_e(cms_blog_index_url()) ?>">Blog</a><i class="fas fa-chevron-right"></i><span><?= cms_e((string) $post['title']) ?></span></div>
      <span class="label label-light label-dot"><?= cms_e((string) ($post['category'] ?: 'Insights')) ?></span>
      <h1><?= cms_e((string) $post['title']) ?></h1>
      <p><?= cms_e((string) $post['excerpt']) ?></p>
      <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;color:#dbe7ff">
        <span class="cms-author-line"><img src="<?= cms_e($postAuthorImage) ?>" alt="<?= cms_e((string) $post['author_name']) ?>" /><i class="fas fa-user"></i> <?= cms_e((string) $post['author_name']) ?></span>
        <span><i class="fas fa-calendar"></i> <?= cms_e(cms_format_date((string) $post['published_at'], 'F j, Y')) ?></span>
      </div>
    </div></div>
  </section>

  <section class="section">
    <div class="container">
      <?php if ($dbError !== ''): ?>
        <div class="cms-flash cms-flash-warning"><?= cms_e($dbError) ?></div>
      <?php endif; ?>

      <?php if ($resolvedFeaturedImage !== ''): ?>
        <div class="featured-panel" style="margin-bottom:36px">
          <div class="featured-panel-media" style="width:100%">
            <img src="<?= cms_e(cms_media_url($resolvedFeaturedImage)) ?>" alt="<?= cms_e((string) $post['title']) ?>" />
          </div>
        </div>
      <?php endif; ?>

      <article class="cms-article-content">
        <?= $postContentHtml ?>
        <?php if ($postCustomHtml !== ''): ?>
          <?= $postCustomHtml ?>
        <?php endif; ?>
        <?php if ($postInterlinksHtml !== ''): ?>
          <?= $postInterlinksHtml ?>
        <?php endif; ?>
      </article>
    </div>
  </section>

  <?php if ($relatedPosts !== []): ?>
    <section class="section section-gray" style="padding-top:0">
      <div class="container">
        <div class="section-header reveal" style="margin-bottom:48px">
          <span class="label label-primary label-dot">More Articles</span>
          <h2>Keep Reading</h2>
        </div>
        <div class="blog-grid">
          <?php foreach ($relatedPosts as $index => $related): ?>
            <div class="blog-card reveal d<?= (($index % 3) + 1) ?>">
              <div class="blog-img-box"><img src="<?= cms_e(cms_media_url((string) (cms_resolve_post_featured_image((array) $related) ?: 'assets/images/SEO.webp'))) ?>" alt="<?= cms_e((string) $related['title']) ?>" class="blog-img" loading="lazy" /></div>
              <div class="blog-body">
                <div class="blog-meta"><span class="b-cat"><?= cms_e((string) ($related['category'] ?: 'Insights')) ?></span><span class="b-dot">●</span><span class="b-date"><?= cms_e(cms_format_date((string) $related['published_at'])) ?></span></div>
                <h4><?= cms_e((string) $related['title']) ?></h4>
                <p><?= cms_e((string) $related['excerpt']) ?></p>
                <a href="<?= cms_e(cms_blog_post_url((string) $related['slug'])) ?>" class="btn btn-secondary btn-sm" style="margin-top:18px">Open Post</a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>
<?php endif; ?>

<?php cms_public_footer(); ?>
<?php endif; ?>
</body>
</html>
