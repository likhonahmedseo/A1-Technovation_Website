<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/functions.php';
require_once __DIR__ . '/php/cms/public-layout.php';

if (cms_begin_public_page_cache()) {
    return;
}

$categories = cms_get_all_categories();
$tags       = cms_get_all_tags();
$canonical  = cms_url('blog/categories');
$pageTitle  = 'Blog Categories & Tags | A1 Technovation';
$desc       = 'Explore all blog categories and tags on A1 Technovation. Find posts on SEO, PPC, social media, and digital marketing.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= cms_e($desc) ?>" />
  <meta property="og:title" content="<?= cms_e($pageTitle) ?>" />
  <meta property="og:description" content="<?= cms_e($desc) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:url" content="<?= cms_e($canonical) ?>" />
  <title><?= cms_e($pageTitle) ?></title>
  <link rel="canonical" href="<?= cms_e($canonical) ?>" />
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
    <div class="breadcrumb">
      <a href="<?= cms_e(cms_url('')) ?>">Home</a>
      <i class="fas fa-chevron-right"></i>
      <a href="<?= cms_e(cms_blog_index_url()) ?>">Blog</a>
      <i class="fas fa-chevron-right"></i>
      <span>Categories</span>
    </div>
    <span class="label label-light label-dot">All Topics</span>
    <h1 class="page-hero-title">Categories & Tags</h1>
    <p class="page-hero-desc">Browse by topic to find exactly what you're looking for.</p>
  </div></div>
</section>

<section class="section-pad">
  <div class="container">

    <h2 style="color:#fff;margin-bottom:28px">Categories (<?= count($categories) ?>)</h2>
    <?php if ($categories === []): ?>
      <p style="color:#9ca3af">No categories yet.</p>
    <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:64px">
      <?php foreach ($categories as $cat): ?>
      <a href="<?= cms_e(cms_url('blog/category/' . rawurlencode($cat['category']))) ?>"
         style="display:block;padding:20px 24px;background:#0d1117;border-radius:12px;text-decoration:none;border:1px solid #1e2433;transition:border-color .2s"
         onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='#1e2433'">
        <strong style="color:#fff;font-size:16px"><?= cms_e($cat['category']) ?></strong>
        <p style="color:#9ca3af;margin:6px 0 0;font-size:13px"><?= (int) $cat['post_count'] ?> post<?= $cat['post_count'] !== '1' ? 's' : '' ?></p>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h2 style="color:#fff;margin-bottom:28px">All Tags (<?= count($tags) ?>)</h2>
    <?php if ($tags === []): ?>
      <p style="color:#9ca3af">No tags yet.</p>
    <?php else: ?>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
      <?php foreach ($tags as $tag): ?>
      <a href="<?= cms_e(cms_url('blog/tag/' . rawurlencode($tag['tag']))) ?>"
         style="padding:8px 18px;background:#1e2433;color:#9ca3af;border-radius:50px;text-decoration:none;font-size:14px;transition:background .2s,color .2s"
         onmouseover="this.style.background='var(--primary)';this.style.color='#fff'" onmouseout="this.style.background='#1e2433';this.style.color='#9ca3af'">
        <?= cms_e($tag['tag']) ?> <span style="opacity:.6">(<?= (int) $tag['post_count'] ?>)</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </div>
</section>

<?php cms_public_footer(); ?>
</body>
</html>
