<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle    = 'Settings';
$activeNav    = 'settings';
$errorMessage = '';
$settings     = cms_site_settings();
$extended     = cms_site_settings_extended();

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action', 'save_general');

        if ($action === 'save_general') {
            $settings = [
                'company_name'      => trim((string) cms_post('company_name')),
                'phone'             => trim((string) cms_post('phone')),
                'email'             => trim((string) cms_post('email')),
                'whatsapp_url'      => trim((string) cms_post('whatsapp_url')),
                'footer_text'       => trim((string) cms_post('footer_text')),
                'primary_cta_label' => trim((string) cms_post('primary_cta_label')),
                'primary_cta_url'   => trim((string) cms_post('primary_cta_url')),
            ];
            cms_save_site_settings($settings);
            cms_log_activity('save_settings', 'settings', 0, 'General');
            cms_flash_set('success', 'General settings saved.');
            cms_redirect('admin/settings.php#general');
        }

        if ($action === 'save_branding') {
            $extended = array_merge($extended, [
                'logo_url'   => trim((string) cms_post('logo_url')),
                'favicon_url' => trim((string) cms_post('favicon_url')),
            ]);
            cms_save_site_settings_extended($extended);
            cms_log_activity('save_settings', 'settings', 0, 'Branding');
            cms_flash_set('success', 'Branding settings saved.');
            cms_redirect('admin/settings.php#branding');
        }

        if ($action === 'save_social') {
            $extended = array_merge($extended, [
                'facebook_url'  => trim((string) cms_post('facebook_url')),
                'twitter_url'   => trim((string) cms_post('twitter_url')),
                'instagram_url' => trim((string) cms_post('instagram_url')),
                'linkedin_url'  => trim((string) cms_post('linkedin_url')),
                'youtube_url'   => trim((string) cms_post('youtube_url')),
                'tiktok_url'    => trim((string) cms_post('tiktok_url')),
            ]);
            cms_save_site_settings_extended($extended);
            cms_log_activity('save_settings', 'settings', 0, 'Social');
            cms_flash_set('success', 'Social links saved.');
            cms_redirect('admin/settings.php#social');
        }

        if ($action === 'save_scripts') {
            $extended = array_merge($extended, [
                'analytics_head_code' => trim((string) cms_post('analytics_head_code')),
                'header_scripts'      => trim((string) cms_post('header_scripts')),
                'footer_scripts'      => trim((string) cms_post('footer_scripts')),
            ]);
            cms_save_site_settings_extended($extended);
            cms_log_activity('save_settings', 'settings', 0, 'Scripts');
            cms_flash_set('success', 'Script injection settings saved.');
            cms_redirect('admin/settings.php#scripts');
        }

        if ($action === 'save_stock_images') {
            $extended = array_merge($extended, [
                'pexels_api_key'      => trim((string) cms_post('pexels_api_key')),
                'pixabay_api_key'     => trim((string) cms_post('pixabay_api_key')),
                'unsplash_access_key' => trim((string) cms_post('unsplash_access_key')),
            ]);
            cms_save_site_settings_extended($extended);
            cms_log_activity('save_settings', 'settings', 0, 'Stock image APIs');
            cms_flash_set('success', 'Stock image API settings saved.');
            cms_redirect('admin/settings.php#stock-images');
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-sliders"></i> Configuration</span>
    <h1 class="admin-page-title">Site settings</h1>
    <p class="admin-page-copy">Control site-wide contact info, branding, social links, CTA text, and script injection from one place.</p>
  </div>
</section>

<?php if ($errorMessage !== ''): ?><div class="flash flash-error"><?= cms_e($errorMessage) ?></div><?php endif; ?>

<!-- ── General ─────────────────────────────────────────────────────────── -->
<section id="general" class="admin-card" style="margin-bottom:24px">
  <div class="toolbar"><div><h3><i class="fas fa-building"></i> General</h3><p class="muted">Company name, contact details, and primary call-to-action.</p></div></div>
  <form method="post" class="form-grid">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_general" />
    <div class="form-grid two-col">
      <div class="field">
        <label for="company_name">Company name</label>
        <input id="company_name" type="text" name="company_name" value="<?= cms_e((string) $settings['company_name']) ?>" />
      </div>
      <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" type="text" name="phone" value="<?= cms_e((string) $settings['phone']) ?>" />
      </div>
    </div>
    <div class="form-grid two-col">
      <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="<?= cms_e((string) $settings['email']) ?>" />
      </div>
      <div class="field">
        <label for="whatsapp_url">WhatsApp URL</label>
        <input id="whatsapp_url" type="url" name="whatsapp_url" value="<?= cms_e((string) $settings['whatsapp_url']) ?>" />
      </div>
    </div>
    <div class="field">
      <label for="footer_text">Footer text</label>
      <textarea id="footer_text" name="footer_text"><?= cms_e((string) $settings['footer_text']) ?></textarea>
    </div>
    <div class="form-grid two-col">
      <div class="field">
        <label for="primary_cta_label">Primary CTA label</label>
        <input id="primary_cta_label" type="text" name="primary_cta_label" value="<?= cms_e((string) $settings['primary_cta_label']) ?>" />
      </div>
      <div class="field">
        <label for="primary_cta_url">Primary CTA URL</label>
        <input id="primary_cta_url" type="url" name="primary_cta_url" value="<?= cms_e((string) $settings['primary_cta_url']) ?>" />
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save General</button>
  </form>
</section>

<!-- ── Branding ────────────────────────────────────────────────────────── -->
<section id="branding" class="admin-card" style="margin-bottom:24px">
  <div class="toolbar"><div><h3><i class="fas fa-palette"></i> Branding</h3><p class="muted">Logo and favicon URLs served to the public site.</p></div></div>
  <form method="post" class="form-grid">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_branding" />
    <div class="form-grid two-col">
      <div class="field">
        <label for="logo_url">Logo URL</label>
        <input id="logo_url" type="text" name="logo_url" value="<?= cms_e((string) $extended['logo_url']) ?>" placeholder="/assets/images/logo.png" />
        <?php if ($extended['logo_url'] !== ''): ?>
          <img src="<?= cms_e(cms_media_url((string) $extended['logo_url'])) ?>" alt="Logo preview" style="max-height:48px;margin-top:8px;border-radius:4px" />
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="favicon_url">Favicon URL</label>
        <input id="favicon_url" type="text" name="favicon_url" value="<?= cms_e((string) $extended['favicon_url']) ?>" placeholder="/assets/images/favicon.ico" />
        <?php if ($extended['favicon_url'] !== ''): ?>
          <img src="<?= cms_e(cms_media_url((string) $extended['favicon_url'])) ?>" alt="Favicon preview" style="max-height:32px;margin-top:8px" />
        <?php endif; ?>
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Branding</button>
  </form>
</section>

<!-- ── Social links ────────────────────────────────────────────────────── -->
<section id="social" class="admin-card" style="margin-bottom:24px">
  <div class="toolbar"><div><h3><i class="fas fa-share-nodes"></i> Social links</h3><p class="muted">Used in the footer and Open Graph tags. Leave blank to hide.</p></div></div>
  <form method="post" class="form-grid">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_social" />
    <div class="form-grid two-col">
      <?php
      $socialFields = [
          'facebook_url'  => ['fab fa-facebook', 'Facebook URL'],
          'instagram_url' => ['fab fa-instagram', 'Instagram URL'],
          'twitter_url'   => ['fab fa-x-twitter', 'X / Twitter URL'],
          'linkedin_url'  => ['fab fa-linkedin', 'LinkedIn URL'],
          'youtube_url'   => ['fab fa-youtube', 'YouTube URL'],
          'tiktok_url'    => ['fab fa-tiktok', 'TikTok URL'],
      ];
      foreach ($socialFields as $key => [$icon, $label]): ?>
        <div class="field">
          <label for="<?= cms_e($key) ?>"><i class="<?= cms_e($icon) ?>"></i> <?= cms_e($label) ?></label>
          <input id="<?= cms_e($key) ?>" type="url" name="<?= cms_e($key) ?>" value="<?= cms_e((string) $extended[$key]) ?>" placeholder="https://..." />
        </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Social Links</button>
  </form>
</section>

<!-- ── Stock image APIs ────────────────────────────────────────────────── -->
<section id="stock-images" class="admin-card" style="margin-bottom:24px">
  <div class="toolbar"><div><h3><i class="fas fa-images"></i> Stock image APIs</h3><p class="muted">Used by Media Library image search. Dashboard keys override environment/config keys.</p></div></div>
  <form method="post" class="form-grid" autocomplete="off">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_stock_images" />
    <div class="form-grid two-col">
      <div class="field">
        <label for="pexels_api_key">Pexels API key</label>
        <input id="pexels_api_key" type="password" name="pexels_api_key" value="<?= cms_e((string) $extended['pexels_api_key']) ?>" placeholder="Paste Pexels API key" autocomplete="new-password" />
        <p class="helper-text">Used for Pexels search in Media Library. You can also set <code>PEXELS_API_KEY</code>.</p>
      </div>
      <div class="field">
        <label for="pixabay_api_key">Pixabay API key</label>
        <input id="pixabay_api_key" type="password" name="pixabay_api_key" value="<?= cms_e((string) $extended['pixabay_api_key']) ?>" placeholder="Optional Pixabay key" autocomplete="new-password" />
      </div>
    </div>
    <div class="field">
      <label for="unsplash_access_key">Unsplash access key</label>
      <input id="unsplash_access_key" type="password" name="unsplash_access_key" value="<?= cms_e((string) $extended['unsplash_access_key']) ?>" placeholder="Optional Unsplash access key" autocomplete="new-password" />
      <p class="helper-text">Unsplash imports call the required download tracking endpoint when a download URL is available.</p>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Stock APIs</button>
  </form>
</section>

<!-- ── Script injection ────────────────────────────────────────────────── -->
<section id="scripts" class="admin-card">
  <div class="toolbar"><div><h3><i class="fas fa-code"></i> Script injection</h3><p class="muted">Inject analytics, tracking, or any custom HTML into the public site head or footer.</p></div></div>
  <form method="post" class="form-grid">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_scripts" />
    <div class="field">
      <label for="analytics_head_code">Analytics / head code <span class="muted">(injected inside &lt;head&gt;)</span></label>
      <textarea id="analytics_head_code" name="analytics_head_code" rows="5" style="font-family:monospace;font-size:.82rem"><?= cms_e((string) $extended['analytics_head_code']) ?></textarea>
      <p class="helper-text">Paste your Google Analytics 4 snippet, Meta Pixel, GTM code, or any &lt;script&gt; tag here.</p>
    </div>
    <div class="field">
      <label for="header_scripts">Additional head scripts</label>
      <textarea id="header_scripts" name="header_scripts" rows="4" style="font-family:monospace;font-size:.82rem"><?= cms_e((string) $extended['header_scripts']) ?></textarea>
    </div>
    <div class="field">
      <label for="footer_scripts">Footer scripts <span class="muted">(injected before &lt;/body&gt;)</span></label>
      <textarea id="footer_scripts" name="footer_scripts" rows="4" style="font-family:monospace;font-size:.82rem"><?= cms_e((string) $extended['footer_scripts']) ?></textarea>
      <p class="helper-text">Livechat widgets, CRO tools, or deferred scripts go here.</p>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Scripts</button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
