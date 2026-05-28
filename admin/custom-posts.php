<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle    = 'Custom Posts';
$activeNav    = 'custom-post-types';
$currentAdmin = cms_current_admin();
$errorMessage = '';

$typeName = trim((string) cms_query('type'));
if ($typeName === '') {
    cms_redirect('admin/custom-post-types.php');
}

$cpt = cms_get_custom_post_type_by_name($typeName);
if (!$cpt) {
    cms_flash_set('error', 'Post type not found.');
    cms_redirect('admin/custom-post-types.php');
}

$pageTitle  = $cpt['label_plural'];
$schema     = $cpt['fields_schema'] ?? [];

$blankPost = ['id' => 0, 'title' => '', 'status' => 'draft', 'sort_order' => 0, 'data_json' => '{}', 'post_type' => $typeName];
$editingPost = $blankPost;

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'save_custom_post') {
            // Build data_json from individual field inputs
            $data = [];
            foreach ($schema as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key !== '') {
                    $data[$key] = (string) cms_post('cf_' . $key, '');
                }
            }
            $_POST['data_json'] = json_encode($data);
            $pid = cms_save_custom_post($_POST, (int) ($currentAdmin['id'] ?? 0));
            cms_flash_set('success', $cpt['label'] . ' saved.');
            cms_redirect('admin/custom-posts.php?type=' . rawurlencode($typeName) . '&edit=' . $pid);
        }

        if ($action === 'delete_custom_post') {
            cms_delete_custom_post((int) cms_post('id'));
            cms_flash_set('success', $cpt['label'] . ' deleted.');
            cms_redirect('admin/custom-posts.php?type=' . rawurlencode($typeName));
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        if ((string) cms_post('action') === 'save_custom_post') {
            $editingPost = array_merge($blankPost, $_POST);
        }
    }
}

$editId = cms_query_int('edit');
if ($editId > 0 && $errorMessage === '') {
    $loaded = cms_get_custom_post($editId);
    if ($loaded && $loaded['post_type'] === $typeName) {
        $editingPost = array_merge($blankPost, $loaded);
    }
}

$posts = cms_get_custom_posts($typeName);

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas <?= cms_e((string) $cpt['icon']) ?>"></i> <?= cms_e((string) $cpt['label_plural']) ?></span>
    <h1 class="admin-page-title">Manage <?= cms_e(strtolower((string) $cpt['label_plural'])) ?></h1>
    <p class="admin-page-copy">Create and edit all <?= cms_e(strtolower((string) $cpt['label_plural'])) ?> entries. They are stored as structured data you can query or display anywhere.</p>
  </div>
  <div class="card-actions">
    <a href="<?= cms_e(cms_url('admin/custom-post-types.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> All Types</a>
    <?php if ((int) $editingPost['id'] > 0): ?>
      <a href="<?= cms_e(cms_url('admin/custom-posts.php?type=' . rawurlencode($typeName))) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-xmark"></i> Cancel edit</a>
    <?php endif; ?>
  </div>
</section>

<?php if ($errorMessage !== ''): ?><div class="flash flash-error"><?= cms_e($errorMessage) ?></div><?php endif; ?>

<section class="content-grid">
  <!-- ── Entry list ── -->
  <article class="table-card">
    <div class="toolbar">
      <div>
        <h3><?= cms_e((string) $cpt['label_plural']) ?></h3>
        <p class="muted"><?= count($posts) ?> entr<?= count($posts) === 1 ? 'y' : 'ies' ?></p>
      </div>
    </div>
    <?php if ($posts === []): ?>
      <div class="empty-state">No <?= cms_e(strtolower((string) $cpt['label_plural'])) ?> yet. Create the first entry using the form.</div>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>Title</th>
              <th>Status</th>
              <th>Updated</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posts as $p): ?>
              <tr>
                <td><strong><?= cms_e((string) $p['title']) ?></strong></td>
                <td><span class="status-badge status-<?= cms_e((string) $p['status']) ?>"><?= cms_e((string) $p['status']) ?></span></td>
                <td><?= cms_e(cms_format_date((string) ($p['updated_at'] ?? ''), 'M j, Y')) ?></td>
                <td class="card-actions" style="gap:6px">
                  <a href="<?= cms_e(cms_url('admin/custom-posts.php?type=' . rawurlencode($typeName) . '&edit=' . (int) $p['id'])) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen"></i></a>
                  <form method="post" style="display:inline" onsubmit="return confirm('Delete this entry?')">
                    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                    <input type="hidden" name="action" value="delete_custom_post" />
                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>" />
                    <input type="hidden" name="post_type" value="<?= cms_e($typeName) ?>" />
                    <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>

  <!-- ── Entry editor ── -->
  <article class="admin-card">
    <div class="toolbar">
      <div>
        <h3><?= (int) $editingPost['id'] > 0 ? 'Edit ' . cms_e((string) $cpt['label']) : 'New ' . cms_e((string) $cpt['label']) ?></h3>
      </div>
    </div>

    <?php
    $editData = cms_json_decode_array((string) ($editingPost['data_json'] ?? '{}'), []);
    if (is_string($editingPost['data_json']) && strpos($editingPost['data_json'], 'cf_') === false) {
        // came from DB
    }
    ?>

    <form method="post" class="form-grid">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="save_custom_post" />
      <input type="hidden" name="id" value="<?= (int) $editingPost['id'] ?>" />
      <input type="hidden" name="post_type" value="<?= cms_e($typeName) ?>" />

      <div class="form-grid two-col">
        <div class="field">
          <label for="cp_title">Title <span class="required">*</span></label>
          <input id="cp_title" type="text" name="title" value="<?= cms_e((string) $editingPost['title']) ?>" required />
        </div>
        <div class="field">
          <label for="cp_status">Status</label>
          <select id="cp_status" name="status">
            <option value="draft" <?= $editingPost['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $editingPost['status'] === 'published' ? 'selected' : '' ?>>Published</option>
          </select>
        </div>
      </div>

      <?php if ($schema === []): ?>
        <div class="flash flash-info" style="margin:0">This post type has no custom fields yet. <a href="<?= cms_e(cms_url('admin/custom-post-types.php?edit=' . (int) $cpt['id'])) ?>">Add fields</a> to the type definition.</div>
      <?php else: ?>
        <?php foreach ($schema as $field): ?>
          <?php
          $fKey   = (string) ($field['key'] ?? '');
          $fLabel = (string) ($field['label'] ?? $fKey);
          $fType  = (string) ($field['type'] ?? 'text');
          $fReq   = !empty($field['required']);
          $fVal   = (string) ($editData[$fKey] ?? '');
          $inputName = 'cf_' . $fKey;
          ?>
          <div class="field">
            <label for="cf_<?= cms_e($fKey) ?>"><?= cms_e($fLabel) ?><?= $fReq ? ' <span class="required">*</span>' : '' ?></label>
            <?php if ($fType === 'textarea' || $fType === 'rich'): ?>
              <textarea id="cf_<?= cms_e($fKey) ?>" name="<?= cms_e($inputName) ?>" rows="4" <?= $fReq ? 'required' : '' ?>><?= cms_e($fVal) ?></textarea>
            <?php elseif ($fType === 'checkbox'): ?>
              <label style="display:flex;align-items:center;gap:8px;font-weight:400">
                <input type="checkbox" id="cf_<?= cms_e($fKey) ?>" name="<?= cms_e($inputName) ?>" value="1" <?= $fVal === '1' ? 'checked' : '' ?> />
                <?= cms_e($fLabel) ?>
              </label>
            <?php else: ?>
              <input id="cf_<?= cms_e($fKey) ?>" type="<?= cms_e(in_array($fType, ['text','email','url','number','date','image'], true) ? ($fType === 'image' ? 'url' : $fType) : 'text') ?>"
                     name="<?= cms_e($inputName) ?>" value="<?= cms_e($fVal) ?>" <?= $fReq ? 'required' : '' ?>
                     placeholder="<?= $fType === 'image' ? 'https://...' : '' ?>" />
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="field">
        <label for="cp_sort_order">Sort order</label>
        <input id="cp_sort_order" type="number" name="sort_order" value="<?= (int) $editingPost['sort_order'] ?>" min="0" />
        <p class="helper-text">Lower numbers appear first.</p>
      </div>

      <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save <?= cms_e((string) $cpt['label']) ?></button>
    </form>
  </article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
