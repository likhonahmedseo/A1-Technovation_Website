<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle = 'Static Pages';
$activeNav = 'static-pages';
$currentAdmin = cms_current_admin();
$errorMessage = '';
$successMessage = '';
$requestedPath = trim((string) cms_query('file'));

if ($requestedPath !== '') {
    cms_redirect('admin/static-editor.php?file=' . rawurlencode($requestedPath));
}

$staticPages = cms_get_static_pages_admin();

$publishDefaults = [
    'source_path' => (string) cms_query('publish_file'),
    'title' => '',
    'slug' => '',
    'category' => '',
    'tags' => '',
    'featured_image' => '',
    'author_name' => (string) cms_config('app.default_author', 'A1 Technovation'),
    'author_image' => cms_default_author_image_url(),
    'default_interlinks_enabled' => '1',
    'default_interlinks_html' => '',
    'status' => 'published',
    'published_at' => date('Y-m-d\TH:i'),
    'html_code' => '',
    'css_code' => '',
];

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'publish_static_blog') {
            $postId = cms_publish_static_html_as_blog($_POST, (int) ($currentAdmin['id'] ?? 0));
            cms_log_activity('publish_static_blog', 'post', $postId, (string) cms_post('title', 'Static HTML blog'));
            cms_flash_set('success', 'Static HTML published as a blog post and sitemap.xml was regenerated.');
            cms_redirect('admin/blogs.php?edit=' . $postId);
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
        $publishDefaults = array_merge($publishDefaults, $_POST);
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-file-code"></i> Static Site Pages</span>
    <h1 class="admin-page-title">Choose a static page to edit</h1>
    <p class="admin-page-copy">Open existing <code>pages/*.html</code> files and key root HTML files in the full-screen static editor with live preview above the source code.</p>
  </div>
</section>

<?php if ($errorMessage !== ''): ?>
  <div class="flash flash-error"><?= cms_e($errorMessage) ?></div>
<?php endif; ?>
<?php if ($successMessage !== ''): ?>
  <div class="flash flash-success"><?= cms_e($successMessage) ?></div>
<?php endif; ?>

<section class="admin-card" style="margin-bottom:24px">
  <div class="toolbar">
    <div>
      <h3><i class="fas fa-newspaper"></i> Publish static HTML as a blog</h3>
      <p class="muted">Paste one full HTML document with CSS inside it, or paste HTML and CSS separately. The CMS keeps the imported layout, publishes it to the blog, assigns a category, and regenerates <code>sitemap.xml</code>.</p>
    </div>
    <a href="<?= cms_e(cms_blog_index_url()) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i class="fas fa-globe"></i> View Blog</a>
  </div>

  <form method="post" class="form-grid" id="staticBlogPublishForm">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="publish_static_blog" />

    <div class="form-grid two-col">
      <div class="field">
        <label for="source_path">Import from static page</label>
        <select id="source_path" name="source_path">
          <option value="">Paste direct HTML code below</option>
          <?php foreach ($staticPages as $file): ?>
            <?php $path = (string) $file['path']; ?>
            <?php if (str_starts_with($path, 'pages/')): ?>
              <option value="<?= cms_e($path) ?>" <?= (string) $publishDefaults['source_path'] === $path ? 'selected' : '' ?>><?= cms_e((string) $file['title']) ?> — <?= cms_e($path) ?></option>
            <?php endif; ?>
          <?php endforeach; ?>
        </select>
        <p class="helper-text">Leave this empty when you want to paste brand-new HTML code directly.</p>
      </div>
      <div class="field">
        <label for="status">Publish status</label>
        <select id="status" name="status">
          <option value="published" <?= (string) $publishDefaults['status'] === 'published' ? 'selected' : '' ?>>Published</option>
          <option value="draft" <?= (string) $publishDefaults['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
        </select>
      </div>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="title">Blog title <span class="field-optional">(optional)</span></label>
        <input id="title" type="text" name="title" value="<?= cms_e((string) $publishDefaults['title']) ?>" placeholder="Leave blank to use the HTML title tag" data-slug-source />
      </div>
      <div class="field">
        <label for="slug">Blog slug <span class="field-optional">(optional)</span></label>
        <input id="slug" type="text" name="slug" value="<?= cms_e((string) $publishDefaults['slug']) ?>" placeholder="technical-seo-checklist" data-slug-target />
      </div>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="category">Category</label>
        <input id="category" type="text" name="category" value="<?= cms_e((string) $publishDefaults['category']) ?>" placeholder="Technical SEO, SEO Strategy, PPC..." required />
      </div>
      <div class="field">
        <label for="tags">Tags <span class="field-optional">(optional)</span></label>
        <input id="tags" type="text" name="tags" value="<?= cms_e((string) $publishDefaults['tags']) ?>" placeholder="seo, technical seo, crawlability" />
      </div>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="author_name">Author name</label>
        <input id="author_name" type="text" name="author_name" value="<?= cms_e((string) $publishDefaults['author_name']) ?>" />
      </div>
      <div class="field">
        <label for="author_image">Author image URL</label>
        <input id="author_image" type="text" name="author_image" value="<?= cms_e((string) $publishDefaults['author_image']) ?>" placeholder="Paste an author image URL" />
      </div>
    </div>

    <div class="field">
      <label for="featured_image">Featured image URL <span class="field-optional">(optional)</span></label>
      <input id="featured_image" type="text" name="featured_image" value="<?= cms_e((string) $publishDefaults['featured_image']) ?>" placeholder="Paste a media library URL or external image URL" />
      <p class="helper-text">Leave blank to auto-detect the first image from the imported HTML. Add a URL here when you want a specific cover image for the blog card, hero image, and social preview.</p>
    </div>

    <div class="field">
      <label style="display:flex;align-items:center;gap:8px;font-weight:700">
        <input type="checkbox" name="default_interlinks_enabled" value="1" <?= !empty($publishDefaults['default_interlinks_enabled']) ? 'checked' : '' ?> />
        Add default interlinks below the article
      </label>
      <p class="helper-text">Uses the default service/blog links when the custom HTML box is empty.</p>
      <textarea name="default_interlinks_html" rows="5" placeholder="<?= cms_e(cms_default_blog_interlinks_html()) ?>"><?= cms_e((string) $publishDefaults['default_interlinks_html']) ?></textarea>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="published_at">Publish date</label>
        <input id="published_at" type="datetime-local" name="published_at" value="<?= cms_e((string) $publishDefaults['published_at']) ?>" />
      </div>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="html_code">HTML code</label>
        <p class="helper-text">Option 1: paste a complete HTML document with <code>&lt;style&gt;</code> CSS inside. Option 2: paste only the body/main HTML and add CSS in the field beside it.</p>
        <textarea id="html_code" name="html_code" rows="14" placeholder="Paste the full HTML document or article HTML here."><?= cms_e((string) $publishDefaults['html_code']) ?></textarea>
      </div>
      <div class="field">
        <label for="css_code">Separate CSS code <span class="field-optional">(optional)</span></label>
        <p class="helper-text">Use this when your CSS is separate. Do not include <code>&lt;style&gt;</code> tags here.</p>
        <textarea id="css_code" name="css_code" rows="14" placeholder=".article-hero { ... }"><?= cms_e((string) $publishDefaults['css_code']) ?></textarea>
      </div>
    </div>

    <div class="inline-actions">
      <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish to Blog</button>
      <a href="<?= cms_e(cms_url('admin/blogs.php')) ?>" class="btn btn-secondary"><i class="fas fa-pen"></i> Open Blog Editor</a>
    </div>
  </form>
</section>

<section>
  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3>Uploaded/static pages</h3>
        <p class="muted">Search, open, and edit existing HTML pages.</p>
      </div>
    </div>
    <div class="field" style="margin-bottom:16px">
      <label for="staticSearch">Search pages</label>
      <input id="staticSearch" type="search" placeholder="Search by title or file path" data-table-search="#staticPagesTable" />
    </div>
    <div class="table-scroll">
      <table id="staticPagesTable">
        <thead>
          <tr>
            <th>Page</th>
            <th>Updated</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staticPages as $file): ?>
            <tr>
              <td><strong><?= cms_e((string) $file['title']) ?></strong><p><?= cms_e((string) $file['path']) ?></p></td>
              <td><?= cms_e(cms_format_date((string) $file['updated_at'], 'M j, Y')) ?></td>
              <td>
                <div class="inline-actions">
                  <?php if (str_starts_with((string) $file['path'], 'pages/')): ?>
                    <a href="<?= cms_e(cms_url('admin/static-pages.php?publish_file=' . rawurlencode((string) $file['path']))) ?>" class="btn btn-primary btn-sm"><i class="fas fa-newspaper"></i> Use as Blog</a>
                  <?php endif; ?>
                  <a href="<?= cms_e(cms_url('admin/static-editor.php?file=' . rawurlencode((string) $file['path']))) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen"></i> Edit</a>
                  <a href="<?= cms_e((string) $file['url']) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> View</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
