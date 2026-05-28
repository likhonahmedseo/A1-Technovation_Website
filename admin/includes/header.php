<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'CMS';
$activeNav = $activeNav ?? '';
$currentAdmin = cms_current_admin();
$flash = $flash ?? cms_flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= cms_e($pageTitle) ?> | A1 Technovation CMS</title>
  <link rel="icon" type="image/png" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="apple-touch-icon" href="<?= cms_e(cms_site_favicon_url()) ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('admin/assets/admin.css?v=20260519-blog-post-manager')) ?>" />
</head>
<body>
<div class="admin-shell">
  <aside class="admin-sidebar">
    <div class="admin-sidebar-head">
      <a href="<?= cms_e(cms_url('')) ?>" class="admin-brand" aria-label="Open A1 Technovation homepage in a new tab" target="_blank" rel="noopener">
        <img src="<?= cms_e(cms_url('assets/images/A1Technovation-Dark-Background.png')) ?>" alt="A1 Technovation" />
      </a>
      <div class="admin-brand-copy">
        <strong>Content Control Panel</strong>
        <span>Manage content, media, and admin access for the live site.</span>
      </div>
      <button type="button" class="admin-sidebar-toggle" data-sidebar-toggle aria-label="Minimize sidebar" aria-expanded="true" title="Minimize sidebar">
        <i class="fas fa-angles-left"></i>
        <span>Minimize sidebar</span>
      </button>
    </div>

    <nav class="admin-nav" aria-label="CMS navigation">
      <a href="<?= cms_e(cms_url('admin/dashboard.php')) ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Dashboard</a>

      <span class="admin-nav-group-label">Content</span>
      <a href="<?= cms_e(cms_url('admin/blogs.php')) ?>" class="<?= $activeNav === 'blogs' ? 'active' : '' ?>"><i class="fas fa-newspaper"></i> Blogs</a>
      <a href="<?= cms_e(cms_url('admin/pages.php')) ?>" class="<?= $activeNav === 'pages' ? 'active' : '' ?>"><i class="fas fa-laptop-code"></i> Pages</a>
      <a href="<?= cms_e(cms_url('admin/static-pages.php')) ?>" class="<?= $activeNav === 'static-pages' ? 'active' : '' ?>"><i class="fas fa-file-code"></i> Static Pages</a>
      <a href="<?= cms_e(cms_url('admin/templates.php')) ?>" class="<?= $activeNav === 'templates' ? 'active' : '' ?>"><i class="fas fa-layer-group"></i> Templates</a>
      <a href="<?= cms_e(cms_url('admin/custom-post-types.php')) ?>" class="<?= in_array($activeNav, ['custom-post-types','custom-posts'], true) ? 'active' : '' ?>"><i class="fas fa-database"></i> Custom Types</a>
      <a href="<?= cms_e(cms_url('admin/forms.php')) ?>" class="<?= $activeNav === 'forms' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> Forms
        <?php
        $unreadForms = 0;
        if (cms_table_exists('cms_form_submissions') && cms_column_exists('cms_form_submissions', 'is_read')) {
            $unreadForms = (int) cms_db()->query("SELECT COUNT(*) FROM cms_form_submissions WHERE is_read=0")->fetchColumn();
        }
        if ($unreadForms > 0): ?>
          <span class="nav-badge"><?= $unreadForms ?></span>
        <?php endif; ?>
      </a>

      <span class="admin-nav-group-label">Assets</span>
      <a href="<?= cms_e(cms_url('admin/media.php')) ?>" class="<?= $activeNav === 'media' ? 'active' : '' ?>"><i class="fas fa-photo-film"></i> Media</a>

      <span class="admin-nav-group-label">Marketing</span>
      <a href="<?= cms_e(cms_url('admin/newsletter.php')) ?>" class="<?= $activeNav === 'newsletter' ? 'active' : '' ?>">
        <i class="fas fa-paper-plane"></i> Newsletter
        <?php
        $pendingNL = cms_newsletter_count('pending');
        if ($pendingNL > 0): ?>
          <span class="nav-badge"><?= $pendingNL ?></span>
        <?php endif; ?>
      </a>

      <span class="admin-nav-group-label">Tools</span>
      <a href="<?= cms_e(cms_url('admin/mail.php')) ?>" class="<?= $activeNav === 'mail' ? 'active' : '' ?>"><i class="fas fa-envelope-open-text"></i> Mail Copies</a>
      <a href="<?= cms_e(cms_url('admin/seo.php')) ?>" class="<?= $activeNav === 'seo' ? 'active' : '' ?>"><i class="fas fa-magnifying-glass-chart"></i> SEO</a>
      <a href="<?= cms_e(cms_url('admin/link-checker.php')) ?>" class="<?= $activeNav === 'link-checker' ? 'active' : '' ?>"><i class="fas fa-link-slash"></i> Link Checker</a>
      <a href="<?= cms_e(cms_url('admin/hreflang.php')) ?>" class="<?= $activeNav === 'hreflang' ? 'active' : '' ?>"><i class="fas fa-language"></i> hreflang</a>
      <a href="<?= cms_e(cms_url('admin/cache.php')) ?>" class="<?= $activeNav === 'cache' ? 'active' : '' ?>"><i class="fas fa-bolt"></i> Page Cache</a>
      <a href="<?= cms_e(cms_url('admin/revisions.php')) ?>" class="<?= $activeNav === 'revisions' ? 'active' : '' ?>"><i class="fas fa-code-compare"></i> Revisions</a>
      <a href="<?= cms_e(cms_url('admin/backup.php')) ?>" class="<?= $activeNav === 'backup' ? 'active' : '' ?>"><i class="fas fa-database"></i> Backup</a>
      <a href="<?= cms_e(cms_url('admin/activity-log.php')) ?>" class="<?= $activeNav === 'activity-log' ? 'active' : '' ?>"><i class="fas fa-clock-rotate-left"></i> Activity Log</a>

      <span class="admin-nav-group-label">Admin</span>
      <a href="<?= cms_e(cms_url('admin/users.php')) ?>" class="<?= $activeNav === 'users' ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a>
      <a href="<?= cms_e(cms_url('admin/permissions.php')) ?>" class="<?= $activeNav === 'permissions' ? 'active' : '' ?>"><i class="fas fa-shield-halved"></i> Permissions</a>
      <a href="<?= cms_e(cms_url('admin/api-tokens.php')) ?>" class="<?= $activeNav === 'api-tokens' ? 'active' : '' ?>"><i class="fas fa-key"></i> API Tokens</a>
      <a href="<?= cms_e(cms_url('admin/settings.php')) ?>" class="<?= $activeNav === 'settings' ? 'active' : '' ?>"><i class="fas fa-sliders"></i> Settings</a>

      <span class="admin-nav-group-label">Site</span>
      <a href="<?= cms_e(cms_blog_index_url()) ?>" target="_blank" rel="noopener"><i class="fas fa-globe"></i> View Site</a>
      <a href="<?= cms_e(cms_url('admin/logout.php')) ?>"><i class="fas fa-right-from-bracket"></i> Logout</a>
    </nav>

    <div class="admin-sidebar-footer">
      <p class="admin-sidebar-note">Current admin: <?= cms_e($currentAdmin['full_name'] ?? 'Admin') ?><br /><?= cms_e($currentAdmin['email'] ?? '') ?></p>
    </div>
  </aside>

  <main class="admin-main">
    <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
      <div class="flash flash-<?= cms_e((string) $flash['type']) ?>" data-autohide-flash><?= cms_e((string) $flash['message']) ?></div>
    <?php endif; ?>
    <script>
    (function(){
      var toggle = document.querySelector('[data-sidebar-toggle]');
      var storageKey = 'a1-cms-sidebar-collapsed';

      function setSidebarState(collapsed) {
        document.body.classList.toggle('admin-sidebar-collapsed', collapsed);
        if (!toggle) return;
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
        toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Minimize sidebar');
        var label = toggle.querySelector('span');
        var icon = toggle.querySelector('i');
        if (label) label.textContent = collapsed ? 'Expand sidebar' : 'Minimize sidebar';
        if (icon) icon.className = collapsed ? 'fas fa-angles-right' : 'fas fa-angles-left';
      }

      try {
        setSidebarState(window.localStorage && localStorage.getItem(storageKey) === '1');
      } catch (error) {
        setSidebarState(false);
      }

      if (toggle && toggle.getAttribute('data-sidebar-toggle-bound') !== '1') {
        toggle.setAttribute('data-sidebar-toggle-bound', '1');
        toggle.addEventListener('click', function () {
          var collapsed = !document.body.classList.contains('admin-sidebar-collapsed');
          try {
            localStorage.setItem(storageKey, collapsed ? '1' : '0');
          } catch (error) {}
          setSidebarState(collapsed);
        });
      }

      // Scroll the active nav item into the visible sidebar area on load
      var active = document.querySelector('.admin-nav a.active');
      var sidebar = document.querySelector('.admin-sidebar');
      if (active && sidebar) {
        var sidebarRect = sidebar.getBoundingClientRect();
        var activeRect  = active.getBoundingClientRect();
        var offset      = activeRect.top - sidebarRect.top - (sidebarRect.height / 2) + (activeRect.height / 2);
        sidebar.scrollTop = sidebar.scrollTop + offset;
      }
    })();
    </script>
