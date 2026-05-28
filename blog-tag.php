<?php
declare(strict_types=1);

require_once __DIR__ . '/php/cms/bootstrap.php';
require_once __DIR__ . '/php/cms/functions.php';
require_once __DIR__ . '/php/cms/public-layout.php';

if (cms_begin_public_page_cache()) {
    return;
}

$tag = urldecode(trim((string) ($_GET['tag'] ?? '')));
if ($tag === '') {
    header('Location: ' . cms_blog_index_url());
    exit;
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset  = ($page - 1) * $perPage;
$posts   = cms_get_published_posts_by_tag($tag, $perPage, $offset);
$total   = cms_posts_count_by_tag($tag);
$pages   = (int) ceil($total / $perPage);

$pageTitle = 'Tag: ' . $tag . ' | A1 Technovation Blog';
$desc      = 'Browse all blog posts tagged "' . $tag . '" on A1 Technovation.';
$canonical = cms_url('blog/tag/' . rawurlencode($tag));
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
  <?php if ($page > 1): ?><link rel="prev" href="<?= cms_e($canonical . ($page > 2 ? '?page=' . ($page - 1) : '')) ?>" /><?php endif; ?>
  <?php if ($page < $pages): ?><link rel="next" href="<?= cms_e($canonical . '?page=' . ($page + 1)) ?>" /><?php endif; ?>
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
      <span>Tag: <?= cms_e($tag) ?></span>
    </div>
    <span class="label label-light label-dot">Tag Archive</span>
    <h1 class="page-hero-title"><?= cms_e($tag) ?></h1>
    <p class="page-hero-desc"><?= (int) $total ?> post<?= $total !== 1 ? 's' : '' ?> tagged with this topic</p>
  </div></div>
</section>

<section class="blog-section section-pad">
  <div class="container">
    <?php if ($posts === []): ?>
      <div style="text-align:center;padding:80px 0">
        <p style="color:#9ca3af;font-size:18px">No published posts tagged "<?= cms_e($tag) ?>" yet.</p>
        <a href="<?= cms_e(cms_blog_index_url()) ?>" class="btn btn-primary" style="margin-top:20px">Browse All Posts</a>
      </div>
    <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($posts as $post): ?>
      <article class="blog-card">
        <?php if ($post['featured_image']): ?>
        <a href="<?= cms_e(cms_blog_post_url($post['slug'])) ?>" class="blog-card-img-link">
          <img src="<?= cms_e($post['featured_image']) ?>" alt="<?= cms_e($post['title']) ?>" loading="lazy" decoding="async">
        </a>
        <?php endif; ?>
        <div class="blog-card-body">
          <?php if ($post['category']): ?>
            <a href="<?= cms_e(cms_url('blog/category/' . rawurlencode($post['category']))) ?>" class="blog-card-cat"><?= cms_e($post['category']) ?></a>
          <?php endif; ?>
          <h2 class="blog-card-title">
            <a href="<?= cms_e(cms_blog_post_url($post['slug'])) ?>"><?= cms_e($post['title']) ?></a>
          </h2>
          <?php if ($post['excerpt']): ?>
            <p class="blog-card-excerpt"><?= cms_e($post['excerpt']) ?></p>
          <?php endif; ?>
          <div class="blog-card-meta">
            <span><?= cms_e($post['author_name']) ?></span>
            <span><?= $post['published_at'] ? date('d M Y', strtotime($post['published_at'])) : '' ?></span>
          </div>
          <?php
          $tags = array_filter(array_map('trim', explode(',', (string) ($post['tags'] ?? ''))));
          if ($tags !== []): ?>
          <div class="blog-tags" style="margin-top:10px;display:flex;flex-wrap:wrap;gap:6px">
            <?php foreach ($tags as $t): ?>
              <a href="<?= cms_e(cms_url('blog/tag/' . rawurlencode($t))) ?>"
                 style="padding:3px 10px;background:<?= $t === $tag ? 'var(--primary)' : '#1e2433' ?>;color:<?= $t === $tag ? '#fff' : '#9ca3af' ?>;border-radius:20px;font-size:12px;text-decoration:none">
                <?= cms_e($t) ?>
              </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="blog-pagination" style="display:flex;justify-content:center;gap:8px;margin-top:48px">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <a href="?tag=<?= urlencode($tag) ?>&page=<?= $i ?>"
           class="pagination-btn<?= $i === $page ? ' active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php cms_public_footer(); ?>
</body>
</html>
