<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle = 'Blogs';
$activeNav = 'blogs';
$currentAdmin = cms_current_admin();
$errorMessage = '';
$successMessage = '';
$skipReloadFromDatabase = false;
$structureTemplates = cms_get_blog_structure_templates();

$blankPost = [
    'id' => 0,
    'title' => '',
    'slug' => '',
    'status' => 'draft',
    'excerpt' => '',
    'content_html' => '',
    'custom_html' => '',
    'layout_mode' => 'standard',
    'head_html' => '',
    'custom_css' => '',
    'body_class' => '',
    'featured_image' => '',
    'author_name' => (string) cms_config('app.default_author', 'A1 Technovation'),
    'author_image' => cms_default_author_image_url(),
    'category' => '',
    'tags' => '',
    'default_interlinks_enabled' => 0,
    'default_interlinks_html' => '',
    'meta_title' => '',
    'meta_description' => '',
    'published_at' => '',
    'import_source_path' => 'pages/what-is-seo.html',
    'import_html_document' => '',
];

$editingPost = $blankPost;

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'save_post') {
            $postId = cms_save_post($_POST, (int) ($currentAdmin['id'] ?? 0));
            cms_flash_set('success', 'Blog post saved successfully.');
            cms_redirect('admin/blogs.php?edit=' . $postId);
        }

        if ($action === 'import_post_layout') {
            $skipReloadFromDatabase = true;
            $sourcePath = trim((string) cms_post('import_source_path'));
            $sourceHtml = trim((string) cms_post('import_html_document'));

            if ($sourceHtml === '') {
                if ($sourcePath === '') {
                    throw new RuntimeException('Provide a relative source path like pages/what-is-seo.html or paste a full HTML document to import.');
                }
                $normalizedPath = cms_normalize_static_file_path($sourcePath);
                $absolutePath = cms_root($normalizedPath);
                if (!is_file($absolutePath)) {
                    throw new RuntimeException('The import source file could not be found inside this website project.');
                }
                $sourceHtml = (string) file_get_contents($absolutePath);
            }

            $importedPost = cms_import_blog_html_document($sourceHtml, 'Imported Blog Post');
            $editingPost = array_merge($blankPost, $_POST, $editingPost, $importedPost, [
                'import_source_path' => $sourcePath !== '' ? $sourcePath : (string) cms_post('import_source_path'),
                'import_html_document' => '',
            ]);
            $successMessage = 'Imported the blog structure into the editor. Review the content, widgets, and SEO fields, then save the post.';
        }

        if ($action === 'save_structure_template') {
            $skipReloadFromDatabase = true;
            $templateName = trim((string) cms_post('structure_template_name'));
            cms_save_blog_structure_template($templateName, $_POST);
            $editingPost = array_merge($blankPost, $_POST);
            $successMessage = 'Reusable blog structure saved. You can load it again from the saved structures list.';
            $structureTemplates = cms_get_blog_structure_templates();
        }

        if ($action === 'load_structure_template') {
            $skipReloadFromDatabase = true;
            $templateKey = trim((string) cms_post('structure_template_key'));
            $template = cms_get_blog_structure_template($templateKey);
            if (!$template) {
                throw new RuntimeException('The selected reusable structure could not be found.');
            }
            $editingPost = array_merge($blankPost, $_POST, (array) ($template['payload'] ?? []), [
                'import_source_path' => (string) ($editingPost['import_source_path'] ?? $blankPost['import_source_path']),
                'import_html_document' => '',
            ]);
            $successMessage = 'Reusable blog structure loaded into the editor. Update the content details and save the post.';
        }

        if ($action === 'delete_post') {
            cms_delete_post((int) cms_post('id'));
            cms_log_activity('delete_post', 'post', (int) cms_post('id'));
            cms_flash_set('success', 'Blog post deleted.');
            cms_redirect('admin/blogs.php');
        }

        if ($action === 'update_featured_image') {
            $postId = (int) cms_post('id');
            cms_update_post_featured_image($postId, (string) cms_post('featured_image'), (int) ($currentAdmin['id'] ?? 0));
            cms_flash_set('success', 'Featured image updated for the selected blog post.');
            cms_redirect('admin/blogs.php?edit=' . $postId);
        }

        if ($action === 'update_static_featured_image') {
            $sourcePath = trim((string) cms_post('source_path'));
            cms_save_static_blog_featured_image($sourcePath, (string) cms_post('featured_image'));
            cms_flash_set('success', 'Static blog featured image updated.');
            cms_redirect('admin/blogs.php');
        }

        if ($action === 'bulk_action') {
            $bulkAction = (string) cms_post('bulk_action_type');
            $ids = array_filter(array_map('intval', (array) cms_post('selected_ids', [])));
            if ($ids === []) {
                throw new RuntimeException('Select at least one post to apply a bulk action.');
            }
            $count = cms_bulk_action_posts($bulkAction, $ids, (int) ($currentAdmin['id'] ?? 0));
            cms_flash_set('success', "$count post(s) updated.");
            cms_redirect('admin/blogs.php');
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
        $editingPost = array_merge($blankPost, $_POST);
    }
}

$editId = cms_query_int('edit');
if ($editId > 0 && $errorMessage === '') {
    if (!$skipReloadFromDatabase) {
        $loadedPost = cms_get_post($editId);
        if (is_array($loadedPost)) {
            $editingPost = array_merge($blankPost, $loadedPost);
        }
    }
}

$editingPost['published_at'] = isset($editingPost['published_at']) && $editingPost['published_at'] !== ''
    ? str_replace(' ', 'T', substr((string) $editingPost['published_at'], 0, 16))
    : '';

$posts = cms_get_posts_admin();
$staticBlogSources = cms_static_blog_sources();
$recentMedia = array_slice(cms_get_recent_media(), 0, 10);
$componentSnippets = cms_editor_component_snippets();

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-newspaper"></i> Blog Management</span>
    <h1 class="admin-page-title">Write, schedule, and publish</h1>
    <p class="admin-page-copy">Create long-form blog content with CKEditor, add ready-made HTML assets, pull media into the article body faster, and publish directly to the live clean blog URLs.</p>
  </div>
  <div class="card-actions">
    <a href="<?= cms_e(cms_blog_index_url()) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i class="fas fa-globe"></i> View Public Blog</a>
    <?php if ((int) $editingPost['id'] > 0): ?>
      <a href="<?= cms_e(cms_url('blog-post.php?preview_post=' . (int) $editingPost['id'])) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Preview Draft</a>
    <?php endif; ?>
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
      <h3><i class="fas fa-file-import"></i> Import a custom blog layout</h3>
      <p class="muted">Bring in a full HTML article like <code>pages/what-is-seo.html</code>, keep its head assets and CSS, and turn it into a reusable custom blog inside the CMS.</p>
    </div>
  </div>
  <form method="post" class="form-grid">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="import_post_layout" />
    <input type="hidden" name="id" value="<?= (int) $editingPost['id'] ?>" />
    <div class="form-grid two-col">
      <div class="field">
        <label for="import_source_path">Import from project file</label>
        <input id="import_source_path" type="text" name="import_source_path" value="<?= cms_e((string) $editingPost['import_source_path']) ?>" placeholder="pages/what-is-seo.html" />
        <p class="helper-text">Use a relative file path from this website repo, or paste a full HTML document below.</p>
      </div>
      <div class="field">
        <label>Quick template source</label>
        <div class="inline-actions" style="margin-top:10px">
          <button type="submit" name="import_source_path" value="pages/what-is-seo.html" class="btn btn-secondary"><i class="fas fa-wand-magic-sparkles"></i> Load What Is SEO Structure</button>
        </div>
      </div>
    </div>
    <div class="field">
      <label for="import_html_document">Or paste full HTML</label>
      <textarea id="import_html_document" name="import_html_document" rows="10" placeholder="Paste a complete HTML document here if it is not already inside the project."><?= cms_e((string) $editingPost['import_html_document']) ?></textarea>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-file-arrow-down"></i> Import into Blog Editor</button>
  </form>
</section>

<section class="admin-card" style="margin-bottom:24px">
  <div class="toolbar">
    <div>
      <h3><i class="fas fa-file-lines"></i> Static blog files</h3>
      <p class="muted">These are the blog HTML files already inside the project. Open them in the live static editor, import them into the CMS post editor, or view the public blog URL.</p>
    </div>
    <span class="admin-badge"><?= count($staticBlogSources) ?> files</span>
  </div>

  <?php if ($staticBlogSources === []): ?>
    <div class="empty-state">No static blog files were found in the configured blog source list.</div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>Blog file</th>
            <th>Slug</th>
            <th>Featured image</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($staticBlogSources as $staticBlog): ?>
            <?php
              $staticBlogPath = (string) ($staticBlog['path'] ?? '');
              $staticBlogSlug = (string) ($staticBlog['slug'] ?? '');
              $staticBlogTitle = (string) ($staticBlog['title'] ?? $staticBlogSlug);
              $staticBlogFeaturedImage = trim((string) ($staticBlog['featured_image'] ?? ''));
              $staticBlogFeaturedOverride = trim((string) ($staticBlog['featured_image_override'] ?? ''));
              $staticBlogFeaturedDetected = trim((string) ($staticBlog['featured_image_detected'] ?? ''));
              $staticImagePanelId = 'static-featured-' . md5($staticBlogPath);
            ?>
            <tr>
              <td>
                <strong><?= cms_e($staticBlogTitle) ?></strong>
                <p style="margin:6px 0 0;color:var(--admin-muted);font-size:.92rem"><?= cms_e($staticBlogPath) ?></p>
              </td>
              <td><code><?= cms_e($staticBlogSlug) ?></code></td>
              <td>
                <?php if ($staticBlogFeaturedOverride !== ''): ?>
                  <span class="status-badge status-published">Custom image set</span>
                <?php elseif ($staticBlogFeaturedImage !== ''): ?>
                  <span class="status-badge status-published">First image ready</span>
                <?php else: ?>
                  <span class="status-badge status-draft">No image found</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="inline-actions post-row-actions">
                  <a href="<?= cms_e(cms_url('admin/static-editor.php?file=' . rawurlencode($staticBlogPath))) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen"></i><span>Live Editor</span></a>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                    <input type="hidden" name="action" value="import_post_layout" />
                    <input type="hidden" name="import_source_path" value="<?= cms_e($staticBlogPath) ?>" />
                    <button type="submit" class="btn btn-ghost btn-sm"><i class="fas fa-file-arrow-down"></i><span>Import to CMS</span></button>
                  </form>
                  <button type="button" class="btn btn-ghost btn-sm" data-toggle-static-featured="<?= cms_e($staticImagePanelId) ?>"><i class="fas fa-image"></i><span>Change image</span></button>
                  <a href="<?= cms_e(cms_blog_post_url($staticBlogSlug)) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm"><i class="fas fa-globe"></i><span>View</span></a>
                </div>
                <div id="<?= cms_e($staticImagePanelId) ?>" style="display:none;margin-top:12px;padding:14px;border:1px solid var(--admin-border);border-radius:14px;background:#f8fbff">
                  <?php if ($staticBlogFeaturedImage !== ''): ?>
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                      <img src="<?= cms_e(cms_media_url($staticBlogFeaturedImage)) ?>" alt="<?= cms_e($staticBlogTitle) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:12px;border:1px solid var(--admin-border)" />
                      <div style="font-size:.92rem;color:var(--admin-muted)">
                        <?php if ($staticBlogFeaturedOverride !== ''): ?>
                          Custom featured image is active for this static blog.
                        <?php else: ?>
                          Using the first image found in the blog HTML right now.
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endif; ?>
                  <form method="post" class="form-grid" style="gap:10px">
                    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                    <input type="hidden" name="action" value="update_static_featured_image" />
                    <input type="hidden" name="source_path" value="<?= cms_e($staticBlogPath) ?>" />
                    <input type="text" name="featured_image" value="<?= cms_e($staticBlogFeaturedOverride) ?>" placeholder="<?= cms_e($staticBlogFeaturedDetected !== '' ? $staticBlogFeaturedDetected : 'Paste a featured image URL') ?>" />
                    <p class="helper-text" style="margin:0">Paste a custom featured image URL here. Leave it blank and save to go back to the first image found inside the blog HTML.</p>
                    <div class="inline-actions">
                      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-save"></i><span>Save image</span></button>
                    </div>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="admin-card" style="margin-bottom:24px">
  <div class="toolbar">
    <div>
      <h3><i class="fas fa-layer-group"></i> Reusable blog structures</h3>
      <p class="muted">Save the current imported layout as a reusable structure, then load it for future custom blogs without importing the source file again.</p>
    </div>
  </div>
  <div class="form-grid two-col">
    <form method="post" class="field">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="save_structure_template" />
      <input type="hidden" name="title" value="<?= cms_e((string) $editingPost['title']) ?>" />
      <input type="hidden" name="excerpt" value="<?= cms_e((string) $editingPost['excerpt']) ?>" />
      <input type="hidden" name="content_html" value="<?= cms_e((string) $editingPost['content_html']) ?>" />
      <input type="hidden" name="custom_html" value="<?= cms_e((string) $editingPost['custom_html']) ?>" />
      <input type="hidden" name="layout_mode" value="<?= cms_e((string) $editingPost['layout_mode']) ?>" />
      <input type="hidden" name="head_html" value="<?= cms_e((string) $editingPost['head_html']) ?>" />
      <input type="hidden" name="custom_css" value="<?= cms_e((string) $editingPost['custom_css']) ?>" />
      <input type="hidden" name="body_class" value="<?= cms_e((string) $editingPost['body_class']) ?>" />
      <input type="hidden" name="featured_image" value="<?= cms_e((string) $editingPost['featured_image']) ?>" />
      <input type="hidden" name="author_name" value="<?= cms_e((string) $editingPost['author_name']) ?>" />
      <input type="hidden" name="author_image" value="<?= cms_e((string) $editingPost['author_image']) ?>" />
      <input type="hidden" name="category" value="<?= cms_e((string) $editingPost['category']) ?>" />
      <input type="hidden" name="tags" value="<?= cms_e((string) $editingPost['tags']) ?>" />
      <input type="hidden" name="default_interlinks_enabled" value="<?= !empty($editingPost['default_interlinks_enabled']) ? '1' : '0' ?>" />
      <input type="hidden" name="default_interlinks_html" value="<?= cms_e((string) $editingPost['default_interlinks_html']) ?>" />
      <input type="hidden" name="meta_title" value="<?= cms_e((string) $editingPost['meta_title']) ?>" />
      <input type="hidden" name="meta_description" value="<?= cms_e((string) $editingPost['meta_description']) ?>" />
      <label for="structure_template_name">Save current structure as template</label>
      <input id="structure_template_name" type="text" name="structure_template_name" value="What Is SEO Structure" />
      <p class="helper-text">This saves the imported layout, custom CSS, head assets, image, and article structure for reuse later.</p>
      <button type="submit" class="btn btn-secondary" style="margin-top:12px"><i class="fas fa-save"></i> Save Structure Template</button>
    </form>

    <form method="post" class="field">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="load_structure_template" />
      <label for="structure_template_key">Load saved structure</label>
      <select id="structure_template_key" name="structure_template_key">
        <option value="">Select a reusable blog structure</option>
        <?php foreach ($structureTemplates as $template): ?>
          <option value="<?= cms_e((string) $template['key']) ?>"><?= cms_e((string) $template['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="helper-text">Use this to start a new custom blog with a saved layout instead of importing the source file again.</p>
      <button type="submit" class="btn btn-primary" style="margin-top:12px"><i class="fas fa-wand-magic-sparkles"></i> Load Saved Structure</button>
    </form>
  </div>
</section>

<section class="split-grid wide" style="display:grid;gap:20px">
  <article class="editor-card">
    <div class="toolbar">
      <div>
        <h3><?= (int) $editingPost['id'] > 0 ? 'Edit post' : 'Create a new post' ?></h3>
        <p class="muted">Draft it now, publish it when ready, and keep SEO fields alongside the content.</p>
      </div>
    </div>

    <form id="blogEditorForm" method="post" class="form-grid" data-autosave-form data-autosave-key="cms-blog-<?= (int) $editingPost['id'] ?>">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="save_post" />
      <input type="hidden" name="id" value="<?= (int) $editingPost['id'] ?>" />

      <div class="seo-checklist" data-seo-checklist data-content-type="blog">
        <strong><i class="fas fa-list-check"></i> SEO checklist</strong>
        <div class="seo-checklist-items"></div>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="title">Post title</label>
          <input id="title" type="text" name="title" value="<?= cms_e((string) $editingPost['title']) ?>" required data-slug-source />
        </div>
        <div class="field">
          <label for="slug">Slug</label>
          <input id="slug" type="text" name="slug" value="<?= cms_e((string) $editingPost['slug']) ?>" data-slug-target />
        </div>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="status">Status</label>
          <select id="status" name="status">
            <option value="draft" <?= (string) $editingPost['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= (string) $editingPost['status'] === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
        </div>
        <div class="field">
          <label for="published_at">Publish date</label>
          <input id="published_at" type="datetime-local" name="published_at" value="<?= cms_e((string) $editingPost['published_at']) ?>" />
        </div>
      </div>

      <?php
      $scheduledAt = '';
      if (!empty($editingPost['id']) && cms_column_exists('blog_posts', 'scheduled_at')) {
          $row = cms_db()->prepare('SELECT scheduled_at FROM blog_posts WHERE id=:id LIMIT 1');
          $row->execute(['id' => (int) $editingPost['id']]);
          $sRow = $row->fetch();
          $scheduledAt = is_array($sRow) && $sRow['scheduled_at'] ? str_replace(' ', 'T', substr($sRow['scheduled_at'], 0, 16)) : '';
      }
      ?>
      <div class="field" style="margin-bottom:16px">
        <label for="scheduled_at" style="display:flex;align-items:center;gap:6px">
          <i class="fas fa-calendar-clock" style="color:var(--admin-primary)"></i>
          Schedule auto-publish
        </label>
        <input id="scheduled_at" type="datetime-local" name="scheduled_at" value="<?= cms_e($scheduledAt) ?>" />
        <small style="color:var(--admin-muted)">Set a future date/time to auto-publish this draft. Leave blank to publish manually.</small>
      </div>

      <div class="field" style="padding:16px 18px;border:1px solid var(--admin-border);border-radius:18px;background:linear-gradient(135deg,rgba(59,130,246,.08),rgba(37,99,235,.03))">
        <label for="featured_image" style="display:flex;align-items:center;gap:8px;font-size:1rem">
          <i class="fas fa-image" style="color:var(--admin-primary)"></i>
          Blog cover / featured image
        </label>
        <input id="featured_image" type="text" name="featured_image" value="<?= cms_e((string) $editingPost['featured_image']) ?>" placeholder="Paste a media library URL or external image URL" />
        <p class="helper-text">Use this if you want a specific image for the blog card, hero section, and social preview. If you leave it empty, the CMS automatically uses the first image found in the article content or imported blog layout.</p>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="author_name">Author name</label>
          <input id="author_name" type="text" name="author_name" value="<?= cms_e((string) $editingPost['author_name']) ?>" />
        </div>
        <div class="field">
          <label for="author_image">Author image URL</label>
          <input id="author_image" type="text" name="author_image" value="<?= cms_e((string) $editingPost['author_image']) ?>" placeholder="Paste a media library URL or external image URL" />
        </div>
      </div>

      <div class="field">
        <label style="display:flex;align-items:center;gap:8px;font-weight:700">
          <input type="checkbox" name="default_interlinks_enabled" value="1" <?= !empty($editingPost['default_interlinks_enabled']) ? 'checked' : '' ?> />
          Add default interlinks below the article
        </label>
        <p class="helper-text">Leave the HTML empty to use the default A1 service/blog links. Add custom HTML when this post needs a specific interlink block.</p>
        <textarea id="default_interlinks_html" name="default_interlinks_html" rows="5" placeholder="<?= cms_e(cms_default_blog_interlinks_html()) ?>"><?= cms_e((string) $editingPost['default_interlinks_html']) ?></textarea>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="category">Category</label>
          <input id="category" type="text" name="category" value="<?= cms_e((string) $editingPost['category']) ?>" placeholder="SEO, PPC, Web Design..." />
        </div>
      </div>

      <div class="field">
        <label for="tags">Tags</label>
        <input id="tags" type="text" name="tags" value="<?= cms_e((string) $editingPost['tags']) ?>" placeholder="seo, content marketing, social media" />
      </div>

      <div class="field">
        <label for="excerpt">Excerpt</label>
        <textarea id="excerpt" name="excerpt" placeholder="Leave blank to auto-generate from the article content."><?= cms_e((string) $editingPost['excerpt']) ?></textarea>
      </div>

      <div class="field">
        <label for="content_html">Article content</label>
        <p class="helper-text" style="margin-top:-4px">Use the snippet and media inserters on the right to drop images, videos, download buttons, FAQs, CTAs, and other HTML blocks into the article body. Imported custom blogs can keep their full `<main>` structure here.</p>
        <textarea id="content_html" class="editor-textarea" name="content_html" data-ckeditor><?= cms_e((string) $editingPost['content_html']) ?></textarea>
      </div>

      <div class="field">
        <label for="custom_html">Custom HTML <span class="field-optional">(optional)</span></label>
        <p class="helper-text" style="margin-top:-4px">Inject raw HTML rendered below the article body — useful for embeds, iframes, maps, or custom elements.</p>
        <div class="code-editor-wrap">
          <div class="code-editor-bar"><i class="fas fa-code"></i> HTML</div>
          <textarea id="custom_html" name="custom_html" data-codemirror="html"><?= cms_e((string) $editingPost['custom_html']) ?></textarea>
        </div>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="layout_mode">Blog layout mode</label>
          <select id="layout_mode" name="layout_mode">
            <?php foreach (cms_blog_layout_modes() as $layoutKey => $layoutLabel): ?>
              <option value="<?= cms_e($layoutKey) ?>" <?= (string) $editingPost['layout_mode'] === $layoutKey ? 'selected' : '' ?>><?= cms_e($layoutLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="helper-text">Use <strong>Imported custom article layout</strong> for self-contained blog pages like <code>pages/what-is-seo.html</code>.</p>
        </div>
        <div class="field">
          <label for="body_class">Body classes</label>
          <input id="body_class" type="text" name="body_class" value="<?= cms_e((string) $editingPost['body_class']) ?>" placeholder="bg-brand-light font-sans text-slate-800 antialiased" />
        </div>
      </div>

      <div class="field">
        <label for="head_html">Head assets <span class="field-optional">(custom import only)</span></label>
        <p class="helper-text" style="margin-top:-4px">Fonts, CDN scripts, and JSON-LD from imported layouts are stored here so custom blog pages keep their original structure.</p>
        <div class="code-editor-wrap">
          <div class="code-editor-bar"><i class="fas fa-code"></i> HEAD HTML</div>
          <textarea id="head_html" name="head_html" data-codemirror="html"><?= cms_e((string) $editingPost['head_html']) ?></textarea>
        </div>
      </div>

      <div class="field">
        <label for="custom_css">Custom CSS <span class="field-optional">(custom import only)</span></label>
        <div class="code-editor-wrap">
          <div class="code-editor-bar"><i class="fas fa-paintbrush"></i> CSS</div>
          <textarea id="custom_css" name="custom_css" data-codemirror="css"><?= cms_e((string) $editingPost['custom_css']) ?></textarea>
        </div>
      </div>

      <div class="form-grid two-col">
        <div class="field">
          <label for="meta_title">Meta title</label>
          <input id="meta_title" type="text" name="meta_title" value="<?= cms_e((string) $editingPost['meta_title']) ?>" />
        </div>
        <div class="field">
          <label for="meta_description">Meta description</label>
          <textarea id="meta_description" name="meta_description" style="min-height:110px"><?= cms_e((string) $editingPost['meta_description']) ?></textarea>
        </div>
      </div>

      <div class="inline-actions">
        <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Post</button>
        <?php if ((int) $editingPost['id'] > 0): ?>
          <a href="<?= cms_e(cms_url('blog-post.php?preview_post=' . (int) $editingPost['id'])) ?>" target="_blank" rel="noopener" class="btn btn-secondary"><i class="fas fa-eye"></i> Preview Draft</a>
        <?php endif; ?>
        <?php if ((int) $editingPost['id'] > 0 && (string) $editingPost['status'] === 'published'): ?>
          <a href="<?= cms_e(cms_blog_post_url((string) $editingPost['slug'])) ?>" target="_blank" rel="noopener" class="btn btn-secondary"><i class="fas fa-arrow-up-right-from-square"></i> Preview Live</a>
        <?php endif; ?>
      </div>
    </form>
  </article>

  <div class="content-grid" style="display:grid;gap:20px">
    <article class="table-card">
      <div class="toolbar">
        <div>
          <h3>Media shortcuts</h3>
          <p class="muted">Use these files as featured images or insert them as ready-made HTML assets inside the article body.</p>
        </div>
        <a href="<?= cms_e(cms_url('admin/media.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-upload"></i> Manage Media</a>
      </div>

      <?php if ($recentMedia === []): ?>
        <div class="empty-state">The media library is empty. Upload files from the media page first.</div>
      <?php else: ?>
        <div class="media-grid">
          <?php foreach ($recentMedia as $media): ?>
            <?php $mediaUrl = cms_media_url((string) $media['file_path']); ?>
            <?php
              $mediaTitle = (string) ($media['title'] ?: $media['original_name']);
              $mediaAlt = (string) ($media['alt_text'] ?: $mediaTitle);
              $mimeType = (string) $media['mime_type'];
              if (str_starts_with($mimeType, 'image/')) {
                  $articleSnippet = '<figure><img src="' . cms_e($mediaUrl) . '" alt="' . cms_e($mediaAlt) . '" /><figcaption>' . cms_e($mediaTitle) . '</figcaption></figure>';
              } elseif (str_starts_with($mimeType, 'video/')) {
                  $articleSnippet = '<video src="' . cms_e($mediaUrl) . '" controls playsinline></video><p>' . cms_e($mediaTitle) . '</p>';
              } else {
                  $articleSnippet = '<p><a class="btn btn-primary" href="' . cms_e($mediaUrl) . '" target="_blank" rel="noopener">' . cms_e($mediaTitle) . '</a></p>';
              }
            ?>
            <article class="media-card">
              <div class="media-preview">
                <?php if (str_starts_with((string) $media['mime_type'], 'image/')): ?>
                  <img src="<?= cms_e($mediaUrl) ?>" alt="<?= cms_e((string) ($media['alt_text'] ?: $media['title'])) ?>" />
                <?php elseif (str_starts_with((string) $media['mime_type'], 'video/')): ?>
                  <video src="<?= cms_e($mediaUrl) ?>" muted playsinline></video>
                <?php else: ?>
                  <i class="fas fa-file"></i>
                <?php endif; ?>
              </div>
              <div class="media-body">
                <strong><?= cms_e((string) $media['title']) ?></strong>
                <input class="media-url" type="text" readonly value="<?= cms_e($mediaUrl) ?>" />
                <div class="inline-actions">
                  <button type="button" class="btn btn-secondary btn-sm" data-copy-text="<?= cms_e($mediaUrl) ?>">Copy URL</button>
                  <button type="button" class="btn btn-ghost btn-sm" data-fill-target="#featured_image" data-fill-value="<?= cms_e($mediaUrl) ?>">Use as Featured</button>
                  <button type="button" class="btn btn-ghost btn-sm" data-fill-target="#author_image" data-fill-value="<?= cms_e($mediaUrl) ?>">Use as Author</button>
                  <button type="button" class="btn btn-ghost btn-sm" data-insert-target="#content_html" data-insert-snippet="<?= cms_e($articleSnippet) ?>">Insert in Article</button>
                  <button type="button" class="btn btn-ghost btn-sm" data-insert-target="#custom_html" data-insert-snippet="<?= cms_e($articleSnippet) ?>">Insert in HTML</button>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </article>

    <article class="table-card">
      <div class="toolbar">
        <div>
          <h3>HTML asset snippets</h3>
          <p class="muted">Add reusable CTA sections, split layouts, FAQ blocks, maps, and download cards without writing the whole HTML from scratch.</p>
        </div>
      </div>
      <div class="snippet-grid">
        <?php foreach ($componentSnippets as $snippet): ?>
          <article class="snippet-card">
            <strong><?= cms_e((string) $snippet['label']) ?></strong>
            <p class="muted"><?= cms_e((string) $snippet['description']) ?></p>
            <div class="inline-actions">
              <button type="button" class="btn btn-secondary btn-sm" data-insert-target="#content_html" data-insert-snippet="<?= cms_e((string) $snippet['html']) ?>">Insert in Article</button>
              <button type="button" class="btn btn-ghost btn-sm" data-insert-target="#custom_html" data-insert-snippet="<?= cms_e((string) $snippet['html']) ?>">Insert in HTML</button>
              <button type="button" class="btn btn-ghost btn-sm" data-copy-snippet="<?= cms_e((string) $snippet['html']) ?>">Copy HTML</button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </article>

    <article class="table-card blog-posts-manager">
      <div class="toolbar">
        <div>
          <h3>All posts</h3>
          <p class="muted">Find, edit, preview, or clean up published posts and drafts.</p>
        </div>
        <span class="admin-badge"><?= count($posts) ?> total</span>
      </div>
      <div class="field" style="margin-bottom:16px">
        <label for="postsSearch">Search posts</label>
        <div class="blog-post-search">
          <i class="fas fa-magnifying-glass"></i>
          <input id="postsSearch" type="search" placeholder="Search title, category, status, or slug" data-table-search="#postsTable" />
        </div>
      </div>

      <?php if ($posts === []): ?>
        <div class="empty-state">No posts created yet. The form on the left is ready for the first one.</div>
      <?php else: ?>
        <form method="post" id="bulk-posts-form">
          <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
          <input type="hidden" name="action" value="bulk_action" />
          <div class="blog-bulk-bar">
            <div style="display:flex;align-items:center;gap:8px">
              <label class="blog-select-all">
                <input type="checkbox" id="select-all-posts" onchange="document.querySelectorAll('.post-check').forEach(c=>c.checked=this.checked)" />
                Select all
              </label>
            </div>
            <div class="card-actions">
              <select name="bulk_action_type" class="blog-bulk-select" style="cursor:pointer">
                <option value="">Bulk action…</option>
                <option value="publish">Publish selected</option>
                <option value="draft">Set to draft</option>
                <option value="delete">Delete selected</option>
              </select>
              <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirmBulk()"><i class="fas fa-check"></i> Apply</button>
            </div>
          </div>
          <div class="table-scroll blog-posts-scroll">
            <table id="postsTable">
              <thead>
                <tr>
                  <th style="width:32px"></th>
                  <th>Title</th>
                  <th>Featured image</th>
                  <th>Status</th>
                  <th>Published</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($posts as $post): ?>
                  <?php $postFeaturedImage = cms_resolve_post_featured_image((array) $post); ?>
                  <tr>
                    <td class="post-select-cell"><input type="checkbox" class="post-check" name="selected_ids[]" value="<?= (int) $post['id'] ?>" /></td>
                    <td class="post-title-cell">
                      <strong><?= cms_e((string) $post['title']) ?></strong>
                      <p><span><?= cms_e((string) ($post['category'] ?: 'Uncategorised')) ?></span><span><?= cms_e((string) $post['slug']) ?></span></p>
                    </td>
                    <td>
                      <form method="post" class="form-grid" style="gap:10px;min-width:280px">
                        <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                        <input type="hidden" name="action" value="update_featured_image" />
                        <input type="hidden" name="id" value="<?= (int) $post['id'] ?>" />
                        <?php if ($postFeaturedImage !== ''): ?>
                          <div style="display:flex;align-items:center;gap:10px">
                            <img src="<?= cms_e(cms_media_url($postFeaturedImage)) ?>" alt="<?= cms_e((string) $post['title']) ?>" style="width:56px;height:56px;object-fit:cover;border-radius:12px;border:1px solid var(--admin-border)" />
                            <span class="status-badge status-published">Current image</span>
                          </div>
                        <?php else: ?>
                          <span class="status-badge status-draft">Will use first article image if found</span>
                        <?php endif; ?>
                        <input type="text" name="featured_image" value="<?= cms_e((string) ($post['featured_image'] ?? '')) ?>" placeholder="Paste image URL or leave blank for auto" />
                        <div class="inline-actions">
                          <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-image"></i><span>Save image</span></button>
                          <a href="<?= cms_e(cms_url('admin/blogs.php?edit=' . (int) $post['id'])) ?>" class="btn btn-ghost btn-sm"><i class="fas fa-pen"></i><span>Open full editor</span></a>
                        </div>
                      </form>
                    </td>
                    <td class="post-status-cell"><span class="status-badge status-<?= cms_e((string) $post['status']) ?>"><?= cms_e((string) $post['status']) ?></span></td>
                    <td class="post-date-cell"><span><?= cms_e(cms_format_date((string) ($post['published_at'] ?? ''))) ?: 'Not scheduled' ?></span></td>
                    <td class="post-actions-cell">
                      <div class="inline-actions post-row-actions">
                        <a href="<?= cms_e(cms_url('admin/blogs.php?edit=' . (int) $post['id'])) ?>" class="btn btn-secondary btn-sm" title="Edit post"><i class="fas fa-pen"></i><span>Edit</span></a>
                        <a href="<?= cms_e(cms_url('blog-post.php?preview_post=' . (int) $post['id'])) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="Preview post"><i class="fas fa-eye"></i><span>Preview</span></a>
                        <?php if ((string) $post['status'] === 'published'): ?>
                          <a href="<?= cms_e(cms_blog_post_url((string) $post['slug'])) ?>" target="_blank" rel="noopener" class="btn btn-ghost btn-sm" title="View live post"><i class="fas fa-globe"></i><span>View</span></a>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Delete this post permanently?');" style="display:inline">
                          <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                          <input type="hidden" name="action" value="delete_post" />
                          <input type="hidden" name="id" value="<?= (int) $post['id'] ?>" />
                          <button type="submit" class="btn btn-danger btn-sm" title="Delete post"><i class="fas fa-trash"></i><span>Delete</span></button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </form>
      <?php endif; ?>
    </article>
  </div>
</section>
<script>
function confirmBulk() {
  const sel = document.querySelector('[name="bulk_action_type"]')?.value;
  if (!sel) { alert('Choose a bulk action first.'); return false; }
  const checked = document.querySelectorAll('.post-check:checked').length;
  if (checked === 0) { alert('Select at least one post.'); return false; }
  if (sel === 'delete') return confirm('Delete ' + checked + ' post(s) permanently?');
  return true;
}

document.querySelectorAll('[data-toggle-static-featured]').forEach((button) => {
  button.addEventListener('click', () => {
    const id = button.getAttribute('data-toggle-static-featured');
    const panel = id ? document.getElementById(id) : null;
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' || panel.style.display === '' ? 'block' : 'none';
  });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
