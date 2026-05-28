CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media_files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    title VARCHAR(190) NULL,
    alt_text VARCHAR(190) NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size INT UNSIGNED NOT NULL DEFAULT 0,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_media_uploaded_by
        FOREIGN KEY (uploaded_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    excerpt TEXT NULL,
    content_html MEDIUMTEXT NOT NULL,
    custom_html MEDIUMTEXT NULL,
    layout_mode VARCHAR(40) NOT NULL DEFAULT 'standard',
    head_html MEDIUMTEXT NULL,
    custom_css LONGTEXT NULL,
    body_class VARCHAR(255) NULL,
    featured_image VARCHAR(255) NULL,
    author_name VARCHAR(120) NOT NULL,
    author_image VARCHAR(255) NULL,
    category VARCHAR(120) NULL,
    tags VARCHAR(255) NULL,
    default_interlinks_enabled TINYINT(1) NOT NULL DEFAULT 0,
    default_interlinks_html MEDIUMTEXT NULL,
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(255) NULL,
    published_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_posts_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_posts_updated_by
        FOREIGN KEY (updated_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_posts_status_published_at ON blog_posts(status, published_at);
CREATE INDEX idx_posts_category ON blog_posts(category);
CREATE INDEX idx_media_created_at ON media_files(created_at);

CREATE TABLE IF NOT EXISTS cms_pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    page_type ENUM('page', 'service', 'landing') NOT NULL DEFAULT 'page',
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    excerpt TEXT NULL,
    template_key VARCHAR(80) NOT NULL DEFAULT 'service',
    featured_image VARCHAR(255) NULL,
    page_builder_json LONGTEXT NOT NULL,
    custom_html LONGTEXT NULL,
    custom_css MEDIUMTEXT NULL,
    custom_js MEDIUMTEXT NULL,
    show_in_nav TINYINT(1) NOT NULL DEFAULT 0,
    nav_group VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    meta_title VARCHAR(190) NULL,
    meta_description VARCHAR(255) NULL,
    canonical_url VARCHAR(255) NULL,
    og_image VARCHAR(255) NULL,
    schema_json LONGTEXT NULL,
    published_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pages_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_pages_updated_by
        FOREIGN KEY (updated_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_section_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    widget_type VARCHAR(80) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_section_templates_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cms_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_pages_type_status_order ON cms_pages(page_type, status, sort_order);
CREATE INDEX idx_pages_nav ON cms_pages(show_in_nav, nav_group, sort_order);

-- User roles + email verification
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS role ENUM('super_admin','editor','author','viewer') NOT NULL DEFAULT 'super_admin';
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS verification_token VARCHAR(64) NULL;
ALTER TABLE admin_users ADD COLUMN IF NOT EXISTS verification_sent_at DATETIME NULL;

-- Content revision history
CREATE TABLE IF NOT EXISTS cms_revisions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    content_type ENUM('post','page') NOT NULL,
    content_id INT UNSIGNED NOT NULL,
    snapshot_json LONGTEXT NOT NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_revisions_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
);
CREATE INDEX idx_revisions_content ON cms_revisions(content_type, content_id, created_at);

-- Admin activity / audit log
CREATE TABLE IF NOT EXISTS cms_activity_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    admin_name VARCHAR(120) NOT NULL DEFAULT '',
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(80) NOT NULL DEFAULT '',
    entity_id INT UNSIGNED NULL,
    entity_label VARCHAR(255) NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ON DELETE SET NULL
);
CREATE INDEX idx_activity_admin ON cms_activity_log(admin_id, created_at);
CREATE INDEX idx_activity_entity ON cms_activity_log(entity_type, entity_id);

-- Form builder definitions
CREATE TABLE IF NOT EXISTS cms_forms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    fields_json LONGTEXT NOT NULL DEFAULT '[]',
    success_message TEXT NULL,
    notify_email VARCHAR(190) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_forms_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
);

-- Form submission inbox
CREATE TABLE IF NOT EXISTS cms_form_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id INT UNSIGNED NOT NULL,
    data_json LONGTEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_submissions_form
        FOREIGN KEY (form_id) REFERENCES cms_forms(id)
        ON DELETE CASCADE
);
CREATE INDEX idx_submissions_form ON cms_form_submissions(form_id, is_read, created_at);

-- Custom post type definitions
CREATE TABLE IF NOT EXISTS cms_custom_post_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    label VARCHAR(120) NOT NULL,
    label_plural VARCHAR(120) NOT NULL,
    icon VARCHAR(60) NOT NULL DEFAULT 'fa-file',
    fields_schema_json LONGTEXT NOT NULL DEFAULT '[]',
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cpt_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
);

-- Custom post type entries
CREATE TABLE IF NOT EXISTS cms_custom_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_type VARCHAR(80) NOT NULL,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    data_json LONGTEXT NOT NULL DEFAULT '{}',
    sort_order INT NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_custom_post_type_slug (post_type, slug),
    CONSTRAINT fk_custom_posts_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL,
    CONSTRAINT fk_custom_posts_updated_by
        FOREIGN KEY (updated_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
);
CREATE INDEX idx_custom_posts_type_status ON cms_custom_posts(post_type, status, sort_order);

CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(50) NULL,
    company VARCHAR(150) NULL,
    service_interest VARCHAR(100) NULL,
    budget_range VARCHAR(80) NULL,
    message TEXT NOT NULL,
    source_page VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    remote_address VARCHAR(45) NULL,
    forwarded_for VARCHAR(255) NULL,
    country_code VARCHAR(2) NULL,
    country_name VARCHAR(80) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('new','reviewed','archived') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE INDEX idx_contact_submissions_status ON contact_submissions(status, created_at);

-- ─────────────────────────────────────────────────────────────────────────────
-- FEATURE: Newsletter subscriber list
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cms_newsletter_subscribers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    full_name VARCHAR(120) NULL,
    status ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
    confirm_token VARCHAR(64) NULL,
    subscribed_at DATETIME NULL,
    unsubscribed_at DATETIME NULL,
    source VARCHAR(120) NULL COMMENT 'page or widget that captured the email',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
CREATE INDEX idx_newsletter_status ON cms_newsletter_subscribers(status, subscribed_at);

CREATE TABLE IF NOT EXISTS cms_newsletter_campaigns (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    preview_text VARCHAR(255) NULL,
    body_html LONGTEXT NOT NULL,
    status ENUM('draft','sent') NOT NULL DEFAULT 'draft',
    sent_at DATETIME NULL,
    sent_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_campaigns_created_by
        FOREIGN KEY (created_by) REFERENCES admin_users(id)
        ON DELETE SET NULL
);

-- ─────────────────────────────────────────────────────────────────────────────
-- FEATURE: REST API tokens
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cms_api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    token_hash VARCHAR(128) NOT NULL UNIQUE,
    scopes VARCHAR(255) NOT NULL DEFAULT 'read' COMMENT 'comma-separated: read,write,delete',
    admin_id INT UNSIGNED NULL,
    last_used_at DATETIME NULL,
    expires_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_api_tokens_admin
        FOREIGN KEY (admin_id) REFERENCES admin_users(id)
        ON DELETE SET NULL
);

-- ─────────────────────────────────────────────────────────────────────────────
-- FEATURE: hreflang / multi-language page links
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cms_hreflang (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_key VARCHAR(80) NOT NULL COMMENT 'shared key linking translated versions',
    locale VARCHAR(10) NOT NULL COMMENT 'e.g. en, ar, fr, en-US, en-AU',
    url VARCHAR(255) NOT NULL,
    page_label VARCHAR(190) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_hreflang_group_locale (group_key, locale)
);
CREATE INDEX idx_hreflang_url ON cms_hreflang(url(191));

-- ─────────────────────────────────────────────────────────────────────────────
-- FEATURE: Granular role permissions (per-module overrides)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cms_role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role ENUM('super_admin','editor','author','viewer') NOT NULL,
    module VARCHAR(80) NOT NULL COMMENT 'e.g. posts, pages, media, seo, settings, users, forms, newsletter, api',
    can_view TINYINT(1) NOT NULL DEFAULT 0,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_module (role, module)
);

-- ─────────────────────────────────────────────────────────────────────────────
-- FEATURE: Page cache index (track which URLs are cached)
-- ─────────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS cms_page_cache (
    url_hash CHAR(64) PRIMARY KEY COMMENT 'SHA-256 of the URL',
    url VARCHAR(500) NOT NULL,
    cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    file_path VARCHAR(500) NOT NULL
);
CREATE INDEX idx_cache_expires ON cms_page_cache(expires_at);

-- ─────────────────────────────────────────────────────────────────────────────
-- FEATURE: Enhancements to existing tables
-- ─────────────────────────────────────────────────────────────────────────────

-- Content scheduling: allow posts/pages to auto-publish at a future time
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS scheduled_at DATETIME NULL AFTER published_at;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS layout_mode VARCHAR(40) NOT NULL DEFAULT 'standard' AFTER custom_html;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS head_html MEDIUMTEXT NULL AFTER layout_mode;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS custom_css LONGTEXT NULL AFTER head_html;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS body_class VARCHAR(255) NULL AFTER custom_css;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS author_image VARCHAR(255) NULL AFTER author_name;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS default_interlinks_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER tags;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS default_interlinks_html MEDIUMTEXT NULL AFTER default_interlinks_enabled;
ALTER TABLE cms_pages ADD COLUMN IF NOT EXISTS scheduled_at DATETIME NULL AFTER published_at;

-- Media: track WebP conversion status and original size
ALTER TABLE media_files ADD COLUMN IF NOT EXISTS webp_path VARCHAR(255) NULL AFTER file_path;
ALTER TABLE media_files ADD COLUMN IF NOT EXISTS original_size INT UNSIGNED NOT NULL DEFAULT 0 AFTER file_size;
ALTER TABLE media_files ADD COLUMN IF NOT EXISTS width SMALLINT UNSIGNED NULL AFTER original_size;
ALTER TABLE media_files ADD COLUMN IF NOT EXISTS height SMALLINT UNSIGNED NULL AFTER width;

-- Activity log: store before/after diff for edits
ALTER TABLE cms_activity_log ADD COLUMN IF NOT EXISTS change_summary TEXT NULL AFTER entity_label;
ALTER TABLE cms_activity_log ADD COLUMN IF NOT EXISTS user_agent VARCHAR(255) NOT NULL DEFAULT '' AFTER ip_address;
