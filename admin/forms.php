<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle    = 'Forms';
$activeNav    = 'forms';
$currentAdmin = cms_current_admin();
$errorMessage = '';

$blankForm = [
    'id'              => 0,
    'name'            => '',
    'fields_json'     => '[]',
    'success_message' => 'Thank you! Your message has been sent.',
    'notify_email'    => (string) ($currentAdmin['email'] ?? ''),
    'status'          => 'active',
];
$editingForm = $blankForm;

if (cms_is_post()) {
    try {
        cms_require_csrf();
        $action = (string) cms_post('action');

        if ($action === 'save_form') {
            $formId = cms_save_form($_POST, (int) ($currentAdmin['id'] ?? 0));
            cms_flash_set('success', 'Form saved successfully.');
            cms_redirect('admin/forms.php?edit=' . $formId);
        }

        if ($action === 'delete_form') {
            cms_delete_form((int) cms_post('id'));
            cms_flash_set('success', 'Form deleted.');
            cms_redirect('admin/forms.php');
        }

        if ($action === 'delete_submission') {
            cms_delete_submission((int) cms_post('submission_id'));
            cms_flash_set('success', 'Submission deleted.');
            cms_redirect('admin/forms.php?inbox=' . (int) cms_post('form_id'));
        }

        if ($action === 'export_selected_submissions') {
            $ids = array_values(array_filter(array_map('intval', (array) cms_post('selected_ids', []))));
            if ($ids === []) {
                throw new RuntimeException('Select at least one submission to export.');
            }
            $csv = cms_export_form_submissions_csv((int) cms_post('form_id'), $ids);
            cms_log_activity('export_form_submissions_selected', 'form', (int) cms_post('form_id'), count($ids) . ' selected submissions');
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="form-submissions-selected-' . date('Y-m-d-His') . '.csv"');
            header('Content-Length: ' . strlen($csv));
            echo $csv;
            exit;
        }

        if ($action === 'mark_read') {
            cms_mark_submissions_read((int) cms_post('form_id'));
            cms_flash_set('success', 'All submissions marked as read.');
            cms_redirect('admin/forms.php?inbox=' . (int) cms_post('form_id'));
        }
    } catch (Throwable $e) {
        $errorMessage = $e->getMessage();
        if ((string) cms_post('action') === 'save_form') {
            $editingForm = array_merge($blankForm, $_POST);
        }
    }
}

$download = (string) cms_query('download');
if ($download === 'form_submissions_csv') {
    $formId = cms_query_int('form_id');
    $csv = cms_export_form_submissions_csv($formId);
    cms_log_activity('export_form_submissions_all', 'form', $formId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="form-submissions-' . ($formId > 0 ? $formId . '-' : '') . date('Y-m-d-His') . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

try {
    cms_ensure_forms_schema();

    $editId = cms_query_int('edit');
    if ($editId > 0 && $errorMessage === '') {
        $loaded = cms_get_form($editId);
        if ($loaded) {
            $editingForm = array_merge($blankForm, $loaded);
            $editingForm['fields_json'] = $loaded['fields_json'];
        }
    }

    $inboxFormId = cms_query_int('inbox');
    $inboxForm = $inboxFormId > 0 ? cms_get_form($inboxFormId) : null;
    if ($inboxForm) {
        cms_mark_submissions_read($inboxFormId);
        $submissions = cms_get_form_submissions($inboxFormId);
    }

    $forms = cms_get_forms_admin();
} catch (Throwable $exception) {
    $errorMessage = $errorMessage !== '' ? $errorMessage : $exception->getMessage();
    $editId = 0;
    $inboxFormId = 0;
    $inboxForm = null;
    $submissions = [];
    $forms = [];
}

$fieldTypes = [
    'text'     => 'Short Text',
    'email'    => 'Email',
    'tel'      => 'Phone',
    'textarea' => 'Long Text',
    'select'   => 'Dropdown',
    'checkbox' => 'Checkbox',
    'number'   => 'Number',
    'url'      => 'URL',
    'date'     => 'Date',
];

require __DIR__ . '/includes/header.php';
?>
<section class="admin-topbar">
  <div>
    <span class="admin-kicker"><i class="fas fa-clipboard-list"></i> Form Builder</span>
    <h1 class="admin-page-title">Build forms, collect leads</h1>
    <p class="admin-page-copy">Drag-and-drop field builder. All submissions land in the inbox here so you never miss a lead.</p>
  </div>
  <div class="card-actions">
    <a href="<?= cms_e(cms_url('admin/forms.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-list"></i> All Forms</a>
    <?php if ((int) $editingForm['id'] === 0 && !$inboxForm): ?>
      <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('form-editor').scrollIntoView({behavior:'smooth'})">
        <i class="fas fa-plus"></i> New Form
      </button>
    <?php endif; ?>
  </div>
</section>

<?php if ($errorMessage !== ''): ?><div class="flash flash-error"><?= cms_e($errorMessage) ?></div><?php endif; ?>

<?php if ($inboxForm): ?>
<!-- ── Submission inbox ──────────────────────────────────────────────── -->
<section class="table-card">
  <div class="toolbar">
    <div>
      <h3><i class="fas fa-inbox"></i> Submissions — <?= cms_e((string) $inboxForm['name']) ?></h3>
      <p class="muted">All form responses. New submissions are marked as unread.</p>
    </div>
    <div class="card-actions">
      <a href="<?= cms_e(cms_url('admin/forms.php?download=form_submissions_csv&form_id=' . $inboxFormId)) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-file-csv"></i> Export all</a>
      <form method="post" style="display:inline">
        <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
        <input type="hidden" name="action" value="mark_read" />
        <input type="hidden" name="form_id" value="<?= $inboxFormId ?>" />
        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-check-double"></i> Mark all read</button>
      </form>
      <a href="<?= cms_e(cms_url('admin/forms.php?edit=' . $inboxFormId)) ?>" class="btn btn-primary btn-sm"><i class="fas fa-pen"></i> Edit Form</a>
    </div>
  </div>

  <?php if (empty($submissions)): ?>
    <div class="empty-state">No submissions yet for this form.</div>
  <?php else: ?>
    <div>
      <div class="inline-actions" style="margin-bottom:12px">
        <label style="display:flex;align-items:center;gap:8px;font-weight:600">
          <input type="checkbox" data-check-all="[name='selected_ids[]']" />
          Select all
        </label>
        <button type="submit" form="bulk-export-form" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Export selected</button>
      </div>
    </div>
    <div class="table-scroll">
      <?php foreach ($submissions as $sub): ?>
        <?php
          $decodedSubmission = is_array($sub['data_json']) ? $sub['data_json'] : json_decode((string) $sub['data_json'], true);
          $data = is_array($decodedSubmission) ? $decodedSubmission : [];
          $submissionAccent = ((int) $sub['is_read'] === 0) ? 'var(--admin-primary)' : '#e5e7eb';
        ?>
        <article class="admin-card" style="margin:0 0 12px;border-left:3px solid <?= cms_e($submissionAccent) ?>">
          <div class="toolbar" style="margin-bottom:8px">
            <div>
              <?php if ((int) $sub['is_read'] === 0): ?>
                <span class="status-badge status-published" style="font-size:.7rem">New</span>
              <?php endif; ?>
              <label style="display:inline-flex;align-items:center;gap:6px;margin-right:10px">
                <input type="checkbox" name="selected_ids[]" value="<?= (int) $sub['id'] ?>" form="bulk-export-form" />
                <span class="muted" style="font-size:.78rem">Export</span>
              </label>
              <span class="muted" style="font-size:.8rem"><?= cms_e(cms_format_date((string) $sub['created_at'], 'M j, Y g:i A')) ?> · IP: <?= cms_e((string) $sub['ip_address']) ?></span>
            </div>
            <form method="post">
              <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
              <input type="hidden" name="action" value="delete_submission" />
              <input type="hidden" name="submission_id" value="<?= (int) $sub['id'] ?>" />
              <input type="hidden" name="form_id" value="<?= $inboxFormId ?>" />
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this submission?')"><i class="fas fa-trash"></i></button>
            </form>
          </div>
          <dl style="display:grid;grid-template-columns:1fr 2fr;gap:4px 16px">
            <?php foreach ($data as $key => $val): ?>
              <dt style="font-weight:600;color:var(--admin-muted)"><?= cms_e(ucwords(str_replace(['_', '-'], ' ', (string) $key))) ?></dt>
              <dd style="margin:0"><?= cms_e((string) $val) ?></dd>
            <?php endforeach; ?>
          </dl>
        </article>
      <?php endforeach; ?>
    </div>
    <form id="bulk-export-form" method="post" style="display:none">
      <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
      <input type="hidden" name="action" value="export_selected_submissions" />
      <input type="hidden" name="form_id" value="<?= $inboxFormId ?>" />
    </form>
  <?php endif; ?>
</section>

<?php else: ?>
<!-- ── Forms list ────────────────────────────────────────────────────── -->
<section class="table-card">
  <div class="toolbar">
    <div><h3>Your forms</h3><p class="muted">Click the inbox icon to view submissions for a form.</p></div>
  </div>
  <?php if ($forms === []): ?>
    <div class="empty-state">No forms yet. Use the editor below to build your first form.</div>
  <?php else: ?>
    <div class="table-scroll">
      <table>
        <thead>
          <tr><th>Name</th><th>Status</th><th>Submissions</th><th>Unread</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($forms as $f): ?>
            <tr>
              <td><strong><?= cms_e((string) $f['name']) ?></strong><p class="muted"><?= cms_e((string) $f['slug']) ?></p></td>
              <td><span class="status-badge status-<?= $f['status'] === 'active' ? 'published' : 'draft' ?>"><?= cms_e((string) $f['status']) ?></span></td>
              <td><?= (int) $f['submission_count'] ?></td>
              <td>
                <?php if ((int) $f['unread_count'] > 0): ?>
                  <span class="status-badge status-published"><?= (int) $f['unread_count'] ?> new</span>
                <?php else: ?>
                  <span class="muted">—</span>
                <?php endif; ?>
              </td>
              <td class="card-actions" style="gap:6px">
                <a href="<?= cms_e(cms_url('admin/forms.php?inbox=' . (int) $f['id'])) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-inbox"></i> Inbox</a>
                <a href="<?= cms_e(cms_url('admin/forms.php?edit=' . (int) $f['id'])) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-pen"></i> Edit</a>
                <form method="post" style="display:inline" onsubmit="return confirm('Delete this form and all its submissions?')">
                  <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
                  <input type="hidden" name="action" value="delete_form" />
                  <input type="hidden" name="id" value="<?= (int) $f['id'] ?>" />
                  <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<!-- ── Form editor ───────────────────────────────────────────────────── -->
<section id="form-editor" class="admin-card" style="margin-top:24px">
  <div class="toolbar">
    <div>
      <h3><?= (int) $editingForm['id'] > 0 ? 'Edit form' : 'Create new form' ?></h3>
      <p class="muted">Add fields below using the + button. Drag rows to reorder.</p>
    </div>
    <?php if ((int) $editingForm['id'] > 0): ?>
      <a href="<?= cms_e(cms_url('admin/forms.php')) ?>" class="btn btn-secondary btn-sm"><i class="fas fa-xmark"></i> Cancel</a>
    <?php endif; ?>
  </div>

  <form method="post" class="form-grid" id="cms-form-editor">
    <input type="hidden" name="_token" value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action" value="save_form" />
    <input type="hidden" name="id" value="<?= (int) $editingForm['id'] ?>" />
    <input type="hidden" name="fields_json" id="fields_json_input" value="<?= cms_e((string) $editingForm['fields_json']) ?>" />

    <div class="form-grid two-col">
      <div class="field">
        <label for="form_name">Form name <span class="required">*</span></label>
        <input id="form_name" type="text" name="name" value="<?= cms_e((string) $editingForm['name']) ?>" required />
      </div>
      <div class="field">
        <label for="form_status">Status</label>
        <select id="form_status" name="status">
          <option value="active" <?= $editingForm['status'] === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $editingForm['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>

    <div class="form-grid two-col">
      <div class="field">
        <label for="form_success_message">Success message</label>
        <input id="form_success_message" type="text" name="success_message" value="<?= cms_e((string) $editingForm['success_message']) ?>" />
      </div>
      <div class="field">
        <label for="form_notify_email">Notify email (optional)</label>
        <input id="form_notify_email" type="email" name="notify_email" value="<?= cms_e((string) $editingForm['notify_email']) ?>" />
      </div>
    </div>

    <div class="field">
      <label>Form fields</label>
      <div id="fields-builder" style="display:flex;flex-direction:column;gap:8px"></div>
      <button type="button" class="btn btn-secondary btn-sm" style="margin-top:10px" onclick="addField()">
        <i class="fas fa-plus"></i> Add Field
      </button>
    </div>

    <div class="field" style="background:var(--admin-surface-2);padding:14px;border-radius:8px">
      <p class="muted" style="font-size:.82rem;margin:0"><strong>Form builder:</strong> Save field definitions here, then use this admin inbox to review and manage submissions.</p>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk"></i> Save Form</button>
  </form>
</section>
<?php endif; ?>

<script>
const FIELD_TYPES = <?= json_encode(array_map(null, array_keys($fieldTypes), array_values($fieldTypes))) ?>;
const fieldTypesMap = {<?php foreach ($fieldTypes as $k => $v): ?>"<?= $k ?>": "<?= $v ?>",<?php endforeach; ?>};

let fields = [];
try { fields = JSON.parse(document.getElementById('fields_json_input')?.value || '[]'); } catch(e){}
if (!Array.isArray(fields)) fields = [];
if (document.getElementById('fields_json_input') && fields.length === 0) {
  fields = [
    { label: 'Full Name', name: 'full_name', type: 'text', required: true },
    { label: 'Email Address', name: 'email', type: 'email', required: true },
    { label: 'Message', name: 'message', type: 'textarea', required: true }
  ];
}

function renderFields() {
  const container = document.getElementById('fields-builder');
  if (!container) return;
  container.innerHTML = '';
  fields.forEach((f, i) => {
    const row = document.createElement('div');
    row.style.cssText = 'display:grid;grid-template-columns:minmax(160px,1fr) minmax(140px,0.8fr) minmax(130px,0.7fr) auto auto auto;gap:10px;align-items:end;background:var(--admin-surface);padding:12px;border-radius:10px;border:1px solid var(--admin-border)';
    row.innerHTML = `
      <div class="field" style="margin:0">
        <label style="font-size:.75rem">Label</label>
        <input type="text" value="${escHtml(f.label||'')}" oninput="fields[${i}].label=this.value;syncJson()" placeholder="Field label" />
      </div>
      <div class="field" style="margin:0">
        <label style="font-size:.75rem">Type</label>
        <select onchange="fields[${i}].type=this.value;syncJson()">
          ${Object.entries(fieldTypesMap).map(([k,v])=>`<option value="${k}"${f.type===k?' selected':''}>${v}</option>`).join('')}
        </select>
      </div>
      <div class="field" style="margin:0">
        <label style="font-size:.75rem">Name (key)</label>
        <input type="text" value="${escHtml(f.name||'')}" oninput="fields[${i}].name=this.value;syncJson()" placeholder="field_name" />
      </div>
      <label style="font-size:.75rem;display:flex;align-items:center;gap:4px;cursor:pointer">
        <input type="checkbox" ${f.required?'checked':''} onchange="fields[${i}].required=this.checked;syncJson()" /> Req
      </label>
      <button type="button" onclick="moveField(${i},-1)" class="btn btn-secondary btn-sm" title="Move up"><i class="fas fa-chevron-up"></i></button>
      <button type="button" onclick="removeField(${i})" class="btn btn-danger btn-sm" title="Remove"><i class="fas fa-xmark"></i></button>
    `;
    container.appendChild(row);
  });
  syncJson();
}

function addField() {
  fields.push({label:'New Field',name:'field_'+(fields.length+1),type:'text',required:false});
  renderFields();
}
function removeField(i) { fields.splice(i,1); renderFields(); }
function moveField(i,dir) {
  const j = i+dir;
  if (j<0||j>=fields.length) return;
  [fields[i],fields[j]]=[fields[j],fields[i]];
  renderFields();
}
function syncJson() {
  const el = document.getElementById('fields_json_input');
  if (el) el.value = JSON.stringify(fields);
}
function escHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;'); }

document.addEventListener('DOMContentLoaded', renderFields);
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
