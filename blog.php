<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/functions.php';
require_once __DIR__ . '/php/cms/public-layout.php';

if (cms_begin_public_page_cache()) {
    return;
}

$posts = [];
$dbError = '';

try {
    $posts = cms_get_published_posts(24);
} catch (Throwable $exception) {
    $dbError = $exception->getMessage();
}

$manualPosts = cms_static_blog_sources();

$existingSlugs = [];
foreach ($posts as $existingPost) {
    if (is_array($existingPost) && isset($existingPost['slug'])) {
        $existingSlugs[(string) $existingPost['slug']] = true;
    }
}

foreach (array_reverse($manualPosts) as $manualPost) {
    if (!isset($existingSlugs[(string) $manualPost['slug']])) {
        array_unshift($posts, $manualPost);
    }
}

$featuredPost = $posts[0] ?? null;
$latestPosts = array_slice($posts, $featuredPost ? 1 : 0);
$blogCanonical = cms_blog_index_url();
$seoOverride = cms_seo_override_for_url($blogCanonical);
$blogTitle = (string) ($seoOverride['meta_title'] ?: 'A1 Technovation Blog');
$blogDescription = (string) ($seoOverride['meta_description'] ?: 'Read A1 Technovation blog posts on SEO, PPC, social media, web design, and growth strategy.');
$blogCanonical = (string) ($seoOverride['canonical_url'] ?: $blogCanonical);

$featuredPost = is_array($featuredPost) ? $featuredPost : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= cms_e($blogDescription) ?>" />
  <meta property="og:title" content="<?= cms_e($blogTitle) ?>" />
  <meta property="og:description" content="<?= cms_e($blogDescription) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= cms_e($blogCanonical) ?>" />
  <meta property="og:image" content="<?= cms_e(cms_url('assets/images/A1Technovation-Light-Background.png')) ?>" />
  <title><?= cms_e($blogTitle) ?></title>
  <link rel="canonical" href="<?= cms_e($blogCanonical) ?>" />
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/style.css?v=20260513')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/responsive.css?v=20260513')) ?>" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('css/cms-public.css?v=20260504')) ?>" />
</head>
<body>
<?php cms_public_nav('blog'); ?>

<section class="page-hero">
  <div class="hero-bg"><div class="hero-grid"></div><div class="hero-glow-1"></div><div class="hero-glow-2"></div></div>
  <div class="container"><div class="page-hero-inner">
    <div class="breadcrumb"><a href="<?= cms_e(cms_url('')) ?>">Home</a><i class="fas fa-chevron-right"></i><span>Blog</span></div>
    <span class="label label-light label-dot">Insights &amp; Guides</span>
    <h1>Marketing Intelligence <span class="text-grad-hero">You Can Act On</span></h1>
    <p>Tactical guides, industry analysis, and growth playbooks from our digital marketing team to help businesses grow smarter.</p>
  </div></div>
</section>

<section class="section">
  <div class="container">
    <?php if ($dbError !== ''): ?>
      <div class="cms-flash cms-flash-warning"><?= cms_e($dbError) ?></div>
    <?php endif; ?>

    <?php if ($featuredPost): ?>
      <?php
        $featuredTitle = (string) ($featuredPost['title'] ?? '');
        $featuredCategory = trim((string) ($featuredPost['category'] ?? '')) ?: 'Insights';
        $featuredPublishedAt = cms_format_date((string) ($featuredPost['published_at'] ?? ''));
        $featuredExcerpt = (string) ($featuredPost['excerpt'] ?? '');
        $featuredAuthorName = (string) ($featuredPost['author_name'] ?? 'A1 Technovation');
        $featuredAuthorImage = cms_media_url((string) (($featuredPost['author_image'] ?? '') ?: cms_default_author_image_url()));
        $featuredSlug = (string) ($featuredPost['slug'] ?? '');
      ?>
      <div class="section-header reveal">
        <span class="label label-primary label-dot">Featured Article</span>
      </div>
      <div class="featured-panel reveal">
        <div class="featured-panel-media">
          <img src="<?= cms_e(cms_media_url((string) (cms_resolve_post_featured_image((array) $featuredPost) ?: 'assets/images/online-marketing.webp'))) ?>" alt="<?= cms_e($featuredTitle) ?>" loading="lazy" />
        </div>
        <div class="featured-panel-copy">
          <div class="blog-meta"><span class="b-cat"><?= cms_e($featuredCategory) ?></span><span class="b-dot">●</span><span class="b-date"><?= cms_e($featuredPublishedAt) ?></span></div>
          <h2 style="font-size:1.875rem"><?= cms_e($featuredTitle) ?></h2>
          <p style="font-size:1rem;line-height:1.75;margin:0"><?= cms_e($featuredExcerpt) ?></p>
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
            <div class="b-author"><img src="<?= cms_e($featuredAuthorImage) ?>" alt="<?= cms_e($featuredAuthorName) ?>" class="b-author-img" /><?= cms_e($featuredAuthorName) ?></div>
            <span class="b-read"><i class="fas fa-clock"></i> Live from CMS</span>
          </div>
          <a href="<?= cms_e(cms_blog_post_url($featuredSlug)) ?>" class="btn btn-primary">Read Article <i class="fas fa-arrow-right"></i></a>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section-gray" style="padding-top:0">
  <div class="container">
    <div class="section-header reveal" style="margin-bottom:48px">
      <span class="label label-primary label-dot">Latest Articles</span>
      <h2>Published from the <span class="text-grad">New CMS</span></h2>
    </div>

    <?php if ($latestPosts === [] && !$featuredPost): ?>
      <div class="cms-empty-state">No posts have been published yet. Sign in to <a href="<?= cms_e(cms_url('admin/login.php')) ?>" style="color:#0f6fec;font-weight:700">the CMS admin</a> and publish the first article.</div>
    <?php else: ?>
      <div class="blog-grid">
        <?php foreach ($latestPosts as $index => $post): ?>
          <?php
            $postTitle = (string) ($post['title'] ?? '');
            $postCategory = trim((string) ($post['category'] ?? '')) ?: 'Insights';
            $postPublishedAt = cms_format_date((string) ($post['published_at'] ?? ''));
            $postExcerpt = (string) ($post['excerpt'] ?? '');
            $postAuthorName = (string) ($post['author_name'] ?? 'A1 Technovation');
            $postAuthorImage = cms_media_url((string) (($post['author_image'] ?? '') ?: cms_default_author_image_url()));
            $postSlug = (string) ($post['slug'] ?? '');
          ?>
          <div class="blog-card reveal d<?= (($index % 3) + 1) ?>">
            <div class="blog-img-box"><img src="<?= cms_e(cms_media_url((string) (cms_resolve_post_featured_image((array) $post) ?: 'assets/images/SEO.webp'))) ?>" alt="<?= cms_e($postTitle) ?>" class="blog-img" loading="lazy" /></div>
            <div class="blog-body">
              <div class="blog-meta"><span class="b-cat"><?= cms_e($postCategory) ?></span><span class="b-dot">●</span><span class="b-date"><?= cms_e($postPublishedAt) ?></span></div>
              <h4><?= cms_e($postTitle) ?></h4>
              <p><?= cms_e($postExcerpt) ?></p>
              <div class="blog-footer"><div class="b-author"><img src="<?= cms_e($postAuthorImage) ?>" alt="<?= cms_e($postAuthorName) ?>" class="b-author-img" /><?= cms_e($postAuthorName) ?></div><span class="b-read"><i class="fas fa-arrow-right"></i> Read now</span></div>
              <a href="<?= cms_e(cms_blog_post_url($postSlug)) ?>" class="btn btn-secondary btn-sm" style="margin-top:18px">Open Post</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php cms_public_footer(); ?>
</body>
</html>
