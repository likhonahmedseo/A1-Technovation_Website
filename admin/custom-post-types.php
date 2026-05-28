<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();
cms_require_role('editor');

$pageTitle    = 'Custom Post Types';
$activeNav    = 'custom-post-types';
$currentAdmin = cms_current_admin();
$errorMessage = '';

$blankCpt = [
    'id'                 => 0,
    'name'               => '',
    'label'              => '',
    'label_plural'       => '',
    'icon'               => 'fa-file',
    'fields_schema_json' => '[]',
];
$editingCpt = $blankCpt;

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'save_cpt') {
            $cptId = cms_save_custom_post_type($_POST, (int) ($currentAdmin['id'] ?? 0));
            cms_flash_set('success', 'Post type saved.');
            cms_redirect('admin/custom-post-types.php?edit=' . $cptId);
        }

        if ($action === 'delete_cpt') {
            cms_delete_custom_post_type((int) cms_post('id'));
            cms_flash_set('success', 'Post type and all its entries deleted.');
            cms_redirect('admin/custom-post-types.php');
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        if ((string) cms_post('action') === 'save_cpt') {
            $editingCpt = array_merge($blankCpt, $_POST);
        }
    }
}

$editId = cms_query_int('edit');
if ($editId > 0 && $errorMessage === '') {
    $loaded = cms_get_custom_post_type($editId);
    if ($loaded) {
        $editingCpt = array_merge($blankCpt, $loaded);
        $editingCpt['fields_schema_json'] = $loaded['fields_schema_json'];
    }
}

$cpts = cms_get_custom_post_types();

$iconOptions = [
    'fa-file'        => 'Document',
    'fa-star'        => 'Star',
    'fa-briefcase'   => 'Portfolio',
    'fa-users'       => 'Team',
    'fa-quote-left'  => 'Testimonial',
    'fa-tag'         => 'Tag',
    'fa-image'       => 'Gallery',
    'fa-code'        => 'Code',
    'fa-chart-bar'   => 'Stats',
    'fa-lightbulb'   => 'Ideas',
    'fa-store'       => 'Products',
    'fa-calendar'    => 'Events',
];

$fieldTypeOptions = [
    'text'     => 'Short Text',
    'textarea' => 'Long Text',
    'email'    => 'Email',
    'url'      => 'URL',
    'number'   => 'Number',
    'date'     => 'Date',
    'image'    => 'Image URL',
    'select'   => 'Select / Options',
    'checkbox' => 'True/False',
    'rich'     => 'Rich Text (HTML)',
];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-layer-group"></i> Content Types</span>
    <h1 class="admin-page-title">Custom post types</h1>
    <p class="admin-page-copy">Define your own content structures — portfolio items, team members, testimonials, FAQs, products — with custom fields. Each type gets its own manager page.</p>
  </div>
  <div class="card-actions">
    <a href="<?= cms_e(cms_url('admin/custom-post-types.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> All Types</a>
  </div>
</section>

<?php if ($errorMessage !== ''): ?><div class="flash flash-error"><?= cms_e($errorMessage) ?></div><?php endif; ?>

<!-- ── Existing types ─────────────────────────────────────────────────── -->
<?php if ($cpts !== []): ?>
<section class="table-card">
  <div class="toolbar">
    <div><h3>Registered post types</h3><p class="muted">Click "Manage" to add and edit entries for any type.</p></div>
  </div>
  <div class="table-scroll">
    <table>
      <thead>
        <tr><th>Type</th><th>Name</th><th>Fields</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($cpts as $cpt): ?>
          <?php $schema = cms_json_decode_array((string) ($cpt['fields_schema_json'] ?? ''), []); ?>
          <tr>
            <td>
              <i class="fas <?= cms_e((string) $cpt['icon']) ?>"></i>
              <strong><?= cms_e((string) $cpt['label_plural']) ?></strong>
              <p class="muted"><?= cms_e((string) $cpt['name']) ?></p>
            </td>
            <td><?= cms_e((string) $cpt['label']) ?> / <?= cms_e((string) $cpt['label_plural']) ?></td>
            <td><?= count($schema) ?> field<?= count($schema) !== 1 ? 's' : '' ?></td>
            <td class="card-actions" style="gap:6px">
              <a href="<?= cms_e(cms_url('admin/custom-posts.php?type=' . rawurlencode((string) $cpt['name']))) ?>" class="btn btn-primary btn-sm"><i class="fas fa-database"></i> Manage</a>
              <a href="<?= cms_e(cms_url('admin/custom-post-types.php?edit=' . (int) $cpt['id'])) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen"></i> Edit</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this type and ALL its entries? This cannot be undone.')">
                <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                <input type="hidden" name="action" value="delete_cpt" />
                <input type="hidden" name="id" value="<?= (int) $cpt['id'] ?>" />
                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<!-- ── Type editor ────────────────────────────────────────────────────── -->
<section class="admin-card" style="margin-top:24px">
  <div class="toolbar">
    <div>
      <h3><?= (int) $editingCpt['id'] > 0 ? 'Edit post type' : 'Register new post type' ?></h3>
      <p class="muted">Define the type and its custom fields. You can always add more fields later.</p>
    </div>
    <?php if ((int) $editingCpt['id'] > 0): ?>
      <a href="<?= cms_e(cms_url('admin/custom-post-types.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-xmark"></i> Cancel</a>
    <?php endif; ?>
  </div>

  <form method="post" class="form-grid" id="cpt-editor-form">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_cpt" />
    <input type="hidden" name="id" value="<?= (int) $editingCpt['id'] ?>" />
    <input type="hidden" name="fields_schema_json" id="cpt_fields_json" value="<?= cms_e((string) $editingCpt['fields_schema_json']) ?>" />

    <div class="form-grid two-col">
      <div class="field">
        <label for="cpt_label">Singular label <span class="required">*</span></label>
        <input id="cpt_label" type="text" name="label" value="<?= cms_e((string) $editingCpt['label']) ?>" placeholder="e.g. Team Member" required />
      </div>
      <div class="field">
        <label for="cpt_label_plural">Plural label</label>
        <input id="cpt_label_plural" type="text" name="label_plural" value="<?= cms_e((string) $editingCpt['label_plural']) ?>" placeholder="e.g. Team Members" />
      </div>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="cpt_name">Type key (a-z, underscore only) <span class="required">*</span></label>
        <input id="cpt_name" type="text" name="name" value="<?= cms_e((string) $editingCpt['name']) ?>"
               placeholder="e.g. team_member" <?= (int) $editingCpt['id'] > 0 ? 'readonly' : '' ?>
               pattern="[a-z0-9_]+" required />
        <?php if ((int) $editingCpt['id'] > 0): ?>
          <p class="helper-text">Type key cannot be changed after creation.</p>
        <?php endif; ?>
      </div>
      <div class="field">
        <label for="cpt_icon">Icon (Font Awesome class)</label>
        <select id="cpt_icon" name="icon">
          <?php foreach ($iconOptions as $cls => $iconLabel): ?>
            <option value="<?= cms_e($cls) ?>" <?= $editingCpt['icon'] === $cls ? 'selected' : '' ?>>
              <?= cms_e($iconLabel) ?> (<?= cms_e($cls) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label>Custom fields</label>
      <div id="cpt-fields-builder" style="display:flex;flex-direction:column;gap:8px"></div>
      <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="addCptField()">
        <i class="fas fa-plus"></i> Add Custom Field
      </button>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Post Type</button>
  </form>
</section>

<script>
const cptFieldTypes = <?= json_encode($fieldTypeOptions) ?>;
let cptFields = [];
try { cptFields = JSON.parse(document.getElementById('cpt_fields_json')?.value || '[]'); } catch(e) {}
if (!Array.isArray(cptFields)) cptFields = [];

function renderCptFields() {
  const container = document.getElementById('cpt-fields-builder');
  if (!container) return;
  container.innerHTML = '';
  cptFields.forEach((f, i) => {
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 0.5fr auto auto;gap:8px;align-items:end;background:var(--bg-card);padding:10px;border-radius:8px;border:1px solid var(--border)';
    row.innerHTML = `
      <div class="field" style="margin:0">
        <label style="font-size:.75rem">Label</label>
        <input type="text" value="${escHtml(f.label||'')}" oninput="cptFields[${i}].label=this.value;syncCptJson()" placeholder="Field label" />
      </div>
      <div class="field" style="margin:0">
        <label style="font-size:.75rem">Type</label>
        <select onchange="cptFields[${i}].type=this.value;syncCptJson()">
          ${Object.entries(cptFieldTypes).map(([k,v])=>`<option value="${k}"${f.type===k?' selected':''}>${v}</option>`).join('')}
        </select>
      </div>
      <div class="field" style="margin:0">
        <label style="font-size:.75rem">Key</label>
        <input type="text" value="${escHtml(f.key||'')}" oninput="cptFields[${i}].key=this.value;syncCptJson()" placeholder="field_key" />
      </div>
      <label style="font-size:.75rem;display:flex;align-items:center;gap:4px;cursor:pointer">
        <input type="checkbox" ${f.required?'checked':''} onchange="cptFields[${i}].required=this.checked;syncCptJson()" /> Req
      </label>
      <button type="button" onclick="removeCptField(${i})" class="btn btn-danger btn-sm"><i class="fas fa-xmark"></i></button>
    `;
    container.appendChild(row);
  });
  syncCptJson();
}

function addCptField() {
  cptFields.push({label:'New Field',key:'field_'+(cptFields.length+1),type:'text',required:false});
  renderCptFields();
}
function removeCptField(i) { cptFields.splice(i,1); renderCptFields(); }
function syncCptJson() {
  const el = document.getElementById('cpt_fields_json');
  if (el) el.value = JSON.stringify(cptFields);
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

// Auto-fill name from label
document.getElementById('cpt_label')?.addEventListener('input', function() {
  const nameEl = document.getElementById('cpt_name');
  if (nameEl && nameEl.value === '' && !nameEl.readOnly) {
    nameEl.value = this.value.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_+|_+$/g,'');
  }
  const pluralEl = document.getElementById('cpt_label_plural');
  if (pluralEl && pluralEl.value === '') {
    pluralEl.value = this.value + 's';
  }
});

document.addEventListener('DOMContentLoaded', renderCptFields);
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
