<?php
declare(strict_types=1);

require_once __DIR__ . '/../php/cms/bootstrap.php';

cms_require_login();

$pageTitle = 'Static Page Editor';
$currentAdmin = cms_current_admin();
$errorMessage = '';
$requestedPath = trim((string) cms_query('file'));
$editingFile = null;

if (cms_is_post()) {
    try {
        cms_require_csrf();
        if ((string) cms_post('action') === 'save_static_page') {
            $path = (string) cms_post('path');
            cms_save_static_page_admin($path, (string) cms_post('content'));
            cms_flash_set('success', 'Static page saved successfully.');
            cms_redirect('admin/static-editor.php?file=' . rawurlencode($path));
        }
    } catch (Throwable $exception) {
        $errorMessage = $exception->getMessage();
        $requestedPath = (string) cms_post('path', $requestedPath);
        $editingFile = [
            'path' => $requestedPath,
            'title' => $requestedPath,
            'content' => (string) cms_post('content'),
            'url' => cms_url(preg_replace('/\.html$/', '', $requestedPath) ?? $requestedPath),
            'updated_at' => '',
            'size' => 0,
        ];
    }
}

if ($requestedPath === '') {
    cms_redirect('admin/static-pages.php');
}

if ($editingFile === null) {
    try {
        $editingFile = cms_get_static_page_admin($requestedPath);
    } catch (Throwable $exception) {
        cms_flash_set('error', $exception->getMessage());
        cms_redirect('admin/static-pages.php');
    }
}

$flash = cms_flash_get();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= cms_e($pageTitle) ?> | A1 Technovation CMS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="<?= cms_e(cms_url('admin/assets/admin.css?v=20260514-static-editor-responsive')) ?>" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css" />
</head>
<body class="static-editor-page">

  <form id="seForm" method="post" data-autosave-form data-autosave-key="static-page-<?= cms_e((string) $editingFile['path']) ?>">
    <input type="hidden" name="_token"  value="<?= cms_e(cms_csrf_token()) ?>" />
    <input type="hidden" name="action"  value="save_static_page" />
    <input type="hidden" name="path"    value="<?= cms_e((string) $editingFile['path']) ?>" />

    <!-- ── Top bar ─────────────────────────────────────────────────────── -->
    <header class="se-bar">
      <div class="se-bar-left">
        <span class="se-kicker"><i class="fas fa-file-code"></i> Static Editor</span>
        <div class="se-file-info">
          <strong><?= cms_e((string) $editingFile['title']) ?></strong>
          <span><?= cms_e((string) $editingFile['path']) ?> &middot; <?= cms_e(cms_format_date((string) $editingFile['updated_at'], 'M j, Y g:i A')) ?></span>
        </div>
      </div>

      <!-- View mode tabs -->
      <div class="se-mode-tabs" id="seModeTabs">
        <button type="button" class="se-mode-tab active" data-mode="split"><i class="fas fa-table-columns"></i> Split</button>
        <button type="button" class="se-mode-tab" data-mode="code"><i class="fas fa-code"></i> Code</button>
        <button type="button" class="se-mode-tab" data-mode="preview"><i class="fas fa-eye"></i> Preview</button>
      </div>

      <div class="se-bar-actions">
        <a href="<?= cms_e(cms_url('admin/static-pages.php')) ?>" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        <a href="<?= cms_e((string) $editingFile['url']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-up-right-from-square"></i> Live</a>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-floppy-disk"></i> Save</button>
      </div>
    </header>

    <?php if (is_array($flash) && isset($flash['message'], $flash['type'])): ?>
      <div class="flash flash-<?= cms_e((string) $flash['type']) ?> se-flash" data-autohide-flash><?= cms_e((string) $flash['message']) ?></div>
    <?php endif; ?>
    <?php if ($errorMessage !== ''): ?>
      <div class="flash flash-error se-flash"><?= cms_e($errorMessage) ?></div>
    <?php endif; ?>

    <!-- ── Workspace ────────────────────────────────────────────────────── -->
    <div class="se-workspace" id="seWorkspace" data-mode="split">

      <div class="se-panes" id="sePanes">

        <!-- Code pane -->
        <div class="se-pane se-pane-code" id="sePaneCode">
          <div class="se-pane-bar">
            <span><i class="fas fa-code"></i> HTML Source</span>
            <div class="se-pane-bar-actions">
              <button type="button" class="se-icon-btn" id="seFormatBtn" title="Format HTML"><i class="fas fa-wand-magic-sparkles"></i></button>
              <button type="button" class="se-icon-btn" id="seWrapBtn" title="Toggle line wrap"><i class="fas fa-arrow-right-arrow-left"></i></button>
            </div>
          </div>
          <div class="se-code-body">
            <textarea id="content" name="content"><?= cms_e((string) $editingFile['content']) ?></textarea>
          </div>
        </div>

        <!-- Drag divider -->
        <div class="se-divider" id="seDivider" title="Drag to resize">
          <div class="se-divider-grip"></div>
        </div>

        <!-- Preview pane -->
        <div class="se-pane se-pane-preview" id="sePanePreview">
          <div class="se-pane-bar">
            <span><i class="fas fa-eye"></i> Preview</span>
            <div class="se-device-btns">
              <button type="button" class="se-device-btn active" data-w="100%" title="Desktop"><i class="fas fa-desktop"></i></button>
              <button type="button" class="se-device-btn" data-w="768px" title="Tablet"><i class="fas fa-tablet-screen-button"></i></button>
              <button type="button" class="se-device-btn" data-w="390px" title="Mobile"><i class="fas fa-mobile-screen"></i></button>
            </div>
          </div>
          <div class="se-preview-body" id="sePreviewBody">
            <div class="se-preview-frame-wrap" id="sePreviewWrap">
              <iframe id="staticPreviewFrame"
                title="Static page preview"
                sandbox="allow-forms allow-modals allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts"
                src="<?= cms_e((string) $editingFile['url']) ?>"></iframe>
            </div>
          </div>
        </div>

      </div><!-- /.se-panes -->
    </div><!-- /.se-workspace -->

  </form>

  <?php $ckeditorCdn = (string) cms_config('app.ckeditor_cdn', ''); ?>
  <?php if ($ckeditorCdn !== ''): ?>
    <script src="<?= cms_e($ckeditorCdn) ?>"></script>
  <?php endif; ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/matchbrackets.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/edit/closetag.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldcode.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/xml-fold.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/brace-fold.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/search.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/search/searchcursor.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/fold/foldgutter.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/addon/dialog/dialog.min.css" />
  <script src="<?= cms_e(cms_url('admin/assets/admin.js?v=20260514-demo-defaults')) ?>"></script>

  <script>
  (function () {
    var textarea   = document.getElementById('content');
    var frame      = document.getElementById('staticPreviewFrame');
    var workspace  = document.getElementById('seWorkspace');
    var panes      = document.getElementById('sePanes');
    var codePane   = document.getElementById('sePaneCode');
    var previewPane= document.getElementById('sePanePreview');
    var divider    = document.getElementById('seDivider');
    var previewWrap= document.getElementById('sePreviewWrap');
    var baseHref   = <?= json_encode((string) $editingFile['url'], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    if (!textarea || !frame) return;

    // ── CodeMirror init ────────────────────────────────────────────────────
    var cm = CodeMirror.fromTextArea(textarea, {
      mode: 'htmlmixed',
      theme: 'dracula',
      lineNumbers: true,
      lineWrapping: false,
      matchBrackets: true,
      autoCloseTags: true,
      foldGutter: true,
      gutters: ['CodeMirror-linenumbers', 'CodeMirror-foldgutter'],
      tabSize: 2,
      indentWithTabs: false,
      extraKeys: {
        'Ctrl-S': function () { syncAndSave(); },
        'Cmd-S':  function () { syncAndSave(); },
        'Ctrl-F': 'findPersistent',
      }
    });

    function syncTextarea() {
      cm.save();
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function syncAndSave() {
      syncTextarea();
      var form = document.getElementById('seForm');
      if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
      } else {
        form.querySelector('[type="submit"]').click();
      }
    }

    function getCode() { return cm.getValue(); }

    // ── Live preview ───────────────────────────────────────────────────────
    function withBase(html) {
      var base = '<base href="' + baseHref.replace(/"/g, '&quot;') + '">';
      if (/<base\s/i.test(html)) return html;
      if (/<head[^>]*>/i.test(html)) return html.replace(/<head([^>]*)>/i, '<head$1>' + base);
      return base + html;
    }

    var previewTimer = 0;
    function schedulePreview() {
      clearTimeout(previewTimer);
      previewTimer = setTimeout(function () {
        frame.srcdoc = withBase(getCode());
      }, 220);
    }

    cm.on('change', function () {
      syncTextarea();
      schedulePreview();
    });
    setTimeout(schedulePreview, 400);

    // Sync CodeMirror back to textarea before every form submit path.
    document.getElementById('seForm').addEventListener('submit', syncTextarea);

    textarea.addEventListener('input', function () {
      if (textarea.value !== cm.getValue()) {
        cm.setValue(textarea.value);
        schedulePreview();
      }
    });

    // ── Mode tabs ──────────────────────────────────────────────────────────
    document.querySelectorAll('.se-mode-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.se-mode-tab').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        workspace.dataset.mode = btn.dataset.mode;
        setTimeout(function () { cm.refresh(); }, 10);
      });
    });

    // ── Drag-to-resize divider ─────────────────────────────────────────────
    var codePct = 50;

    function isStackedLayout() {
      return window.getComputedStyle(panes).flexDirection === 'column';
    }

    function applyPct(pct) {
      var stacked = isStackedLayout();
      var min = stacked ? 46 : 24;
      var max = stacked ? 78 : 76;
      codePct = Math.min(max, Math.max(min, pct));
      codePane.style.flexBasis = codePct + '%';
      previewPane.style.flexBasis = (100 - codePct) + '%';
    }

    function pointerPct(event) {
      var rect = panes.getBoundingClientRect();
      if (isStackedLayout()) {
        return (event.clientY - rect.top) / rect.height * 100;
      }
      return (event.clientX - rect.left) / rect.width * 100;
    }

    var dragging = false;

    divider.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      dragging = true;
      divider.setPointerCapture(e.pointerId);
      document.body.style.cursor = isStackedLayout() ? 'row-resize' : 'col-resize';
      document.body.style.userSelect = 'none';
      divider.classList.add('dragging');
    });

    divider.addEventListener('pointermove', function (e) {
      if (!dragging) return;
      applyPct(pointerPct(e));
      cm.refresh();
    });

    divider.addEventListener('pointerup', function () {
      if (!dragging) return;
      dragging = false;
      document.body.style.cursor = '';
      document.body.style.userSelect = '';
      divider.classList.remove('dragging');
      cm.refresh();
    });

    window.addEventListener('resize', function () {
      applyPct(codePct);
      cm.refresh();
    });

    // ── Device preview width buttons ───────────────────────────────────────
    document.querySelectorAll('.se-device-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        document.querySelectorAll('.se-device-btn').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var w = btn.dataset.w;
        if (w === '100%') {
          previewWrap.style.width = '100%';
          previewWrap.style.margin = '0';
          previewWrap.style.borderRadius = '0';
          previewWrap.style.boxShadow = 'none';
        } else {
          previewWrap.style.width = w;
          previewWrap.style.margin = '16px auto';
          previewWrap.style.borderRadius = '12px';
          previewWrap.style.boxShadow = '0 8px 40px rgba(0,0,0,0.4)';
        }
      });
    });

    // ── Format button (pretty-print HTML) ─────────────────────────────────
    document.getElementById('seFormatBtn').addEventListener('click', function () {
      var html = getCode();
      // Basic indent: add newlines around block tags
      var formatted = html
        .replace(/>\s*</g, '>\n<')
        .replace(/^\s+|\s+$/gm, function (m) { return m.trim() === '' ? '' : m; })
        .split('\n')
        .filter(function (l) { return l.trim() !== ''; })
        .join('\n');
      cm.setValue(formatted);
      schedulePreview();
    });

    // ── Line wrap toggle ───────────────────────────────────────────────────
    var wrapped = false;
    document.getElementById('seWrapBtn').addEventListener('click', function () {
      wrapped = !wrapped;
      cm.setOption('lineWrapping', wrapped);
      this.classList.toggle('active', wrapped);
    });

  })();
  </script>
</body>
</html>
