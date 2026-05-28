<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const A1_TOOL_MAX_TEXT = 20000;
const A1_TOOL_MAX_LIST_ITEMS = 12;
const A1_TOOL_TIMEOUT = 35;

function tool_json(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function tool_configs(): array
{
    return [
        'ai_content_detector' => [
            'title' => 'AI Content Detector',
            'script' => 'ai_content_detector/ai_content_detector.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url'],
                ['name' => 'text', 'label' => 'Text to analyze', 'type' => 'textarea', 'arg' => '--text'],
            ],
            'one_of' => ['url', 'text'],
        ],
        'ai_overview_optimizer' => [
            'title' => 'AI Overview Optimizer',
            'script' => 'ai_overview_optimizer/ai_overview_optimizer.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url', 'required' => true],
                ['name' => 'keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--keyword'],
            ],
        ],
        'content_brief_generator' => [
            'title' => 'Content Brief Generator',
            'script' => 'content_brief_generator/content_brief_generator.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'Competitor URLs', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--keyword', 'required' => true],
            ],
        ],
        'content_calendar_generator' => [
            'title' => 'Content Calendar Generator',
            'script' => 'content_calendar_generator/content_calendar_generator.py',
            'fields' => [
                ['name' => 'topics', 'label' => 'Topics or keywords', 'type' => 'list', 'arg' => '--topics', 'required' => true],
                ['name' => 'weeks', 'label' => 'Weeks', 'type' => 'number', 'arg' => '--weeks', 'default' => '4', 'min' => 1, 'max' => 12],
                ['name' => 'posts_per_week', 'label' => 'Posts per week', 'type' => 'number', 'arg' => '--posts-per-week', 'default' => '3', 'min' => 1, 'max' => 7],
            ],
        ],
        'content_optimizer' => [
            'title' => 'Content Optimizer',
            'script' => 'content_optimizer/content_optimizer.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url'],
                ['name' => 'text', 'label' => 'Text to analyze', 'type' => 'textarea', 'arg' => '--text'],
                ['name' => 'target_keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--target-keyword', 'required' => true],
            ],
            'one_of' => ['url', 'text'],
        ],
        'content_similarity_checker' => [
            'title' => 'Content Similarity Checker',
            'script' => 'content_similarity_checker/content_similarity_checker.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to compare', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'threshold', 'label' => 'Similarity threshold', 'type' => 'number', 'arg' => '--threshold', 'default' => '0.7', 'min' => 0, 'max' => 1],
            ],
        ],
        'content_uniqueness_scorer' => [
            'title' => 'Content Uniqueness Scorer',
            'script' => 'content_uniqueness_scorer/content_uniqueness_scorer.py',
            'fields' => [
                ['name' => 'source_url', 'label' => 'Original URL', 'type' => 'url', 'arg' => '--source-url', 'required' => true],
                ['name' => 'rewrite_url', 'label' => 'Rewritten URL', 'type' => 'url', 'arg' => '--rewrite-url', 'required' => true],
            ],
        ],
        'core_web_vitals_estimator' => [
            'title' => 'Core Web Vitals Estimator',
            'script' => 'core_web_vitals_estimator/core_web_vitals_estimator.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to analyze', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
            ],
        ],
        'local_citation_checker' => [
            'title' => 'Local Citation Checker',
            'script' => 'local_citation_checker/local_citation_checker.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'Citation URLs', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
            ],
        ],
        'paragraph_optimizer' => [
            'title' => 'Paragraph Optimizer',
            'script' => 'paragraph_optimizer/paragraph_optimizer.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url', 'required' => true],
                ['name' => 'keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--keyword'],
            ],
        ],
        'search_console_analyzer' => [
            'title' => 'Search Console Analyzer',
            'script' => 'search_console_analyzer/search_console_analyzer.py',
            'fields' => [
                ['name' => 'input', 'label' => 'GSC CSV/XLSX export', 'type' => 'file', 'arg' => '--input', 'required' => true],
                ['name' => 'min_impressions', 'label' => 'Minimum impressions', 'type' => 'number', 'arg' => '--min-impressions', 'default' => '50', 'min' => 0, 'max' => 100000],
            ],
        ],
        'seo_health_dashboard_gen' => [
            'title' => 'SEO Health Dashboard Generator',
            'script' => 'seo_health_dashboard_gen/seo_health_dashboard_gen.py',
            'fields' => [
                ['name' => 'input', 'label' => 'Audit workbook', 'type' => 'file', 'arg' => '--input', 'required' => true],
            ],
            'output_arg' => '--output',
            'output_ext' => 'html',
        ],
        'seo_report_generator' => [
            'title' => 'SEO Report Generator',
            'script' => 'seo_report_generator/seo_report_generator.py',
            'fields' => [
                ['name' => 'input', 'label' => 'Audit workbook', 'type' => 'file', 'arg' => '--input', 'required' => true],
                ['name' => 'title', 'label' => 'Report title', 'type' => 'text', 'arg' => '--title', 'default' => 'SEO Audit Report'],
            ],
            'output_arg' => '--output',
            'output_ext' => 'html',
        ],
        'social_preview_validator' => [
            'title' => 'Social Preview Validator',
            'script' => 'social_preview_validator/social_preview_validator.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to validate', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
            ],
        ],
        'structured_data_generator' => [
            'title' => 'Structured Data Generator',
            'script' => 'structured_data_generator/structured_data_generator.py',
            'fields' => [
                ['name' => 'type', 'label' => 'Schema type', 'type' => 'select', 'arg' => '--type', 'required' => true, 'options' => ['article', 'product', 'local-business', 'howto', 'breadcrumbs']],
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url'],
                ['name' => 'name', 'label' => 'Name/title', 'type' => 'text', 'arg' => '--name'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'arg' => '--description'],
                ['name' => 'author', 'label' => 'Author', 'type' => 'text', 'arg' => '--author'],
            ],
        ],
        'structured_data_validator' => [
            'title' => 'Structured Data Validator',
            'script' => 'structured_data_validator/structured_data_validator.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url', 'required' => true],
            ],
        ],
        'text_html_ratio' => [
            'title' => 'Text-to-HTML Ratio Analyzer',
            'script' => 'text_html_ratio/text_html_ratio.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to analyze', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
            ],
        ],
        'text_summarizer_seo' => [
            'title' => 'SEO Text Summarizer',
            'script' => 'text_summarizer_seo/text_summarizer_seo.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url'],
                ['name' => 'text', 'label' => 'Text to summarize', 'type' => 'textarea', 'arg' => '--text'],
                ['name' => 'keyword', 'label' => 'Keyword to preserve', 'type' => 'text', 'arg' => '--keyword'],
                ['name' => 'type', 'label' => 'Summary type', 'type' => 'select', 'arg' => '--type', 'default' => 'snippet', 'options' => ['snippet', 'meta', 'paragraph', 'abstract']],
            ],
            'one_of' => ['url', 'text'],
        ],
        'tfidf_extractor' => [
            'title' => 'TF-IDF Extractor',
            'script' => 'tfidf_extractor/tfidf_extractor.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to analyze', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'top', 'label' => 'Top terms', 'type' => 'number', 'arg' => '--top', 'default' => '30', 'min' => 5, 'max' => 100],
            ],
        ],
        'thin_content_detector' => [
            'title' => 'Thin Content Detector',
            'script' => 'thin_content_detector/thin_content_detector.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to check', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'min_words', 'label' => 'Minimum words', 'type' => 'number', 'arg' => '--min-words', 'default' => '300', 'min' => 50, 'max' => 5000],
            ],
        ],
        'title_tag_optimizer' => [
            'title' => 'Title Tag Optimizer',
            'script' => 'title_tag_optimizer/title_tag_optimizer.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url'],
                ['name' => 'titles', 'label' => 'Titles to score', 'type' => 'list', 'arg' => '--titles'],
                ['name' => 'keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--keyword'],
            ],
            'one_of' => ['url', 'titles'],
        ],
        'topic_authority_scorer' => [
            'title' => 'Topic Authority Scorer',
            'script' => 'topic_authority_scorer/topic_authority_scorer.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'Site page URLs', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'topic', 'label' => 'Topic', 'type' => 'text', 'arg' => '--topic', 'required' => true],
            ],
        ],
        'topic_coverage_completeness' => [
            'title' => 'Topic Coverage Completeness',
            'script' => 'topic_coverage_completeness/topic_coverage_completeness.py',
            'fields' => [
                ['name' => 'my_url', 'label' => 'Your URL', 'type' => 'url', 'arg' => '--my-url', 'required' => true],
                ['name' => 'competitor_urls', 'label' => 'Competitor URLs', 'type' => 'url_list', 'arg' => '--competitor-urls', 'required' => true],
                ['name' => 'keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--keyword'],
            ],
        ],
        'topical_link_cluster' => [
            'title' => 'Topical Link Cluster',
            'script' => 'topical_link_cluster/topical_link_cluster.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to cluster', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'num_clusters', 'label' => 'Number of clusters', 'type' => 'number', 'arg' => '--num-clusters', 'default' => '3', 'min' => 2, 'max' => 12],
            ],
        ],
        'url_slug_analyzer' => [
            'title' => 'URL Slug Analyzer',
            'script' => 'url_slug_analyzer/url_slug_analyzer.py',
            'fields' => [
                ['name' => 'urls', 'label' => 'URLs to audit', 'type' => 'url_list', 'arg' => '--urls', 'required' => true],
                ['name' => 'keyword', 'label' => 'Target keyword', 'type' => 'text', 'arg' => '--keyword'],
            ],
        ],
        'word_frequency_analyzer' => [
            'title' => 'Word Frequency Analyzer',
            'script' => 'word_frequency_analyzer/word_frequency_analyzer.py',
            'fields' => [
                ['name' => 'url', 'label' => 'Page URL', 'type' => 'url', 'arg' => '--url', 'required' => true],
                ['name' => 'keywords', 'label' => 'Keywords', 'type' => 'list', 'arg' => '--keywords'],
                ['name' => 'top', 'label' => 'Top words', 'type' => 'number', 'arg' => '--top', 'default' => '30', 'min' => 5, 'max' => 100],
            ],
        ],
        'xml_sitemap_generator' => [
            'title' => 'XML Sitemap Generator',
            'script' => 'xml_sitemap_generator/xml_sitemap_generator.py',
            'fields' => [
                ['name' => 'urls_text', 'label' => 'URLs or paths', 'type' => 'url_file', 'arg' => '--urls', 'required' => true],
                ['name' => 'base_url', 'label' => 'Base URL for relative paths', 'type' => 'url', 'arg' => '--base-url'],
            ],
            'output_arg' => '--output',
            'output_ext' => 'xml',
        ],
    ];
}

function rate_limit(): void
{
    $ip = preg_replace('/[^a-zA-Z0-9_.:-]/', '', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $file = sys_get_temp_dir() . '/a1_tool_rate_' . sha1($ip) . '.json';
    $now = time();
    $runs = [];
    if (is_file($file)) {
        $runs = json_decode((string) file_get_contents($file), true);
        $runs = is_array($runs) ? $runs : [];
    }
    $runs = array_values(array_filter($runs, static fn($t) => is_int($t) && $t > $now - 3600));
    if (count($runs) >= 30) {
        tool_json(['ok' => false, 'error' => 'Too many tool runs from this connection. Please try again later.'], 429);
    }
    $runs[] = $now;
    @file_put_contents($file, json_encode($runs), LOCK_EX);
}

function post_value(string $name): string
{
    return trim((string) ($_POST[$name] ?? ''));
}

function clean_scalar(string $value): string
{
    $value = trim($value);
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length > A1_TOOL_MAX_TEXT) {
        tool_json(['ok' => false, 'error' => 'Input is too long. Please shorten it and run again.'], 422);
    }
    return $value;
}

function clean_url(string $value): string
{
    $value = clean_scalar($value);
    if ($value === '') {
        return '';
    }
    if (!filter_var($value, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $value)) {
        tool_json(['ok' => false, 'error' => 'Please enter valid http or https URLs only.'], 422);
    }
    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
    if ($host === 'localhost' || str_ends_with($host, '.local')) {
        tool_json(['ok' => false, 'error' => 'Local network URLs are not allowed.'], 422);
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $public = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($public === false) {
            tool_json(['ok' => false, 'error' => 'Private or reserved IP URLs are not allowed.'], 422);
        }
    }
    return $value;
}

function split_list(string $value, bool $urls = false): array
{
    $parts = preg_split('/[\r\n,]+/', $value) ?: [];
    $items = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $items[] = $urls ? clean_url($part) : clean_scalar($part);
    }
    $items = array_slice(array_values(array_unique($items)), 0, A1_TOOL_MAX_LIST_ITEMS);
    return $items;
}

function uploaded_file_path(string $name, array &$temps): string
{
    if (!isset($_FILES[$name]) || !is_array($_FILES[$name]) || (int) ($_FILES[$name]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    $size = (int) ($_FILES[$name]['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        tool_json(['ok' => false, 'error' => 'Uploaded files must be under 5 MB.'], 422);
    }
    $original = (string) ($_FILES[$name]['name'] ?? 'input.dat');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'xlsx', 'xls', 'txt'], true)) {
        tool_json(['ok' => false, 'error' => 'Upload CSV, XLSX, XLS, or TXT files only.'], 422);
    }
    $target = tempnam(sys_get_temp_dir(), 'a1_tool_upload_');
    if ($target === false || !move_uploaded_file((string) $_FILES[$name]['tmp_name'], $target)) {
        tool_json(['ok' => false, 'error' => 'Could not save uploaded file.'], 500);
    }
    $renamed = $target . '.' . $ext;
    @rename($target, $renamed);
    $temps[] = $renamed;
    return $renamed;
}

function temp_text_file(string $content, string $ext, array &$temps): string
{
    $path = tempnam(sys_get_temp_dir(), 'a1_tool_input_');
    if ($path === false) {
        tool_json(['ok' => false, 'error' => 'Could not create temporary input file.'], 500);
    }
    $path .= '.' . $ext;
    file_put_contents($path, $content);
    $temps[] = $path;
    return $path;
}

function python_command_candidates(): array
{
    $configured = trim((string) getenv('A1_PYTHON_BIN'));
    if ($configured !== '') {
        return [[$configured]];
    }
    if (defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows') {
        return [['py', '-3'], ['python'], ['python3']];
    }
    $candidates = [];
    foreach (['/usr/bin/python3', '/usr/local/bin/python3', '/opt/alt/python311/bin/python3', '/opt/alt/python310/bin/python3', '/opt/alt/python39/bin/python3'] as $path) {
        if (is_file($path) && is_executable($path)) {
            $candidates[] = [$path];
        }
    }
    $candidates[] = ['python3'];
    $candidates[] = ['python'];
    return $candidates;
}

function run_command(array $cmd, string $cwd): array
{
    $command = implode(' ', array_map('escapeshellarg', $cmd));
    $descriptor = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptor, $pipes, $cwd);
    if (!is_resource($process)) {
        tool_json(['ok' => false, 'error' => 'Could not start the tool runner.'], 500);
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $stderr = '';
    $start = time();
    $timedOut = false;
    $exitCode = null;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = $status['exitcode'];
        }
        if (time() - $start > A1_TOOL_TIMEOUT) {
            proc_terminate($process);
            $timedOut = true;
            break;
        }
        usleep(100000);
    } while ($status['running']);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedCode = proc_close($process);
    $code = $exitCode !== null && $exitCode >= 0 ? $exitCode : $closedCode;
    return [
        'code' => $timedOut ? 124 : $code,
        'output' => trim($stdout . "\n" . $stderr),
        'timed_out' => $timedOut,
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    tool_json(['ok' => false, 'error' => 'Use POST to run a tool.'], 405);
}

rate_limit();

$configs = tool_configs();
$tool = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($_POST['tool'] ?? '')));
if ($tool === '' || !isset($configs[$tool])) {
    tool_json(['ok' => false, 'error' => 'Unknown tool.'], 404);
}

$config = $configs[$tool];
$root = __DIR__;
$script = $root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $config['script']);
if (!is_file($script)) {
    tool_json(['ok' => false, 'error' => 'Tool script is missing on the server.'], 500);
}

$temps = [];
$cmd = [];
$hasOneOf = [];

foreach ($config['fields'] as $field) {
    $name = (string) $field['name'];
    $type = (string) $field['type'];
    $arg = (string) ($field['arg'] ?? '');
    $required = !empty($field['required']);
    $value = post_value($name);
    if ($value === '' && isset($field['default'])) {
        $value = (string) $field['default'];
    }
    if ($type === 'file') {
        $path = uploaded_file_path($name, $temps);
        if ($path === '' && $required) {
            tool_json(['ok' => false, 'error' => "Please upload {$field['label']}."], 422);
        }
        if ($path !== '') {
            $cmd[] = $arg;
            $cmd[] = $path;
        }
        continue;
    }
    if ($type === 'url_file') {
        $items = split_list($value, false);
        if (!$items && $required) {
            tool_json(['ok' => false, 'error' => "Please enter {$field['label']}."], 422);
        }
        if ($items) {
            $path = temp_text_file(implode("\n", $items), 'txt', $temps);
            $cmd[] = $arg;
            $cmd[] = $path;
            $hasOneOf[$name] = true;
        }
        continue;
    }
    if ($type === 'url') {
        $value = clean_url($value);
        if ($value === '' && $required) {
            tool_json(['ok' => false, 'error' => "Please enter {$field['label']}."], 422);
        }
        if ($value !== '') {
            $cmd[] = $arg;
            $cmd[] = $value;
            $hasOneOf[$name] = true;
        }
        continue;
    }
    if ($type === 'url_list' || $type === 'list') {
        $items = split_list($value, $type === 'url_list');
        if (!$items && $required) {
            tool_json(['ok' => false, 'error' => "Please enter {$field['label']}."], 422);
        }
        if ($items) {
            $cmd[] = $arg;
            foreach ($items as $item) {
                $cmd[] = $item;
            }
            $hasOneOf[$name] = true;
        }
        continue;
    }
    if ($type === 'number') {
        if ($value === '') {
            continue;
        }
        if (!is_numeric($value)) {
            tool_json(['ok' => false, 'error' => "{$field['label']} must be a number."], 422);
        }
        $num = (float) $value;
        if (isset($field['min']) && $num < (float) $field['min']) {
            $num = (float) $field['min'];
        }
        if (isset($field['max']) && $num > (float) $field['max']) {
            $num = (float) $field['max'];
        }
        $cmd[] = $arg;
        $cmd[] = (string) $num;
        continue;
    }
    if ($type === 'select') {
        $options = $field['options'] ?? [];
        if ($value === '' && $required) {
            tool_json(['ok' => false, 'error' => "Please choose {$field['label']}."], 422);
        }
        if ($value !== '') {
            if (!in_array($value, $options, true)) {
                tool_json(['ok' => false, 'error' => "Invalid option for {$field['label']}."], 422);
            }
            $cmd[] = $arg;
            $cmd[] = $value;
        }
        continue;
    }
    $value = clean_scalar($value);
    if ($value === '' && $required) {
        tool_json(['ok' => false, 'error' => "Please enter {$field['label']}."], 422);
    }
    if ($value !== '') {
        $cmd[] = $arg;
        $cmd[] = $value;
        $hasOneOf[$name] = true;
    }
}

if (!empty($config['one_of'])) {
    $ok = false;
    foreach ($config['one_of'] as $name) {
        if (!empty($hasOneOf[$name])) {
            $ok = true;
            break;
        }
    }
    if (!$ok) {
        tool_json(['ok' => false, 'error' => 'Please fill at least one primary input for this tool.'], 422);
    }
}

$generatedPath = '';
if (!empty($config['output_arg'])) {
    $ext = preg_replace('/[^a-z0-9]/', '', (string) ($config['output_ext'] ?? 'txt')) ?: 'txt';
    $generatedPath = tempnam(sys_get_temp_dir(), 'a1_tool_output_');
    if ($generatedPath === false) {
        tool_json(['ok' => false, 'error' => 'Could not create temporary output file.'], 500);
    }
    $generatedPath .= '.' . $ext;
    $temps[] = $generatedPath;
    $cmd[] = (string) $config['output_arg'];
    $cmd[] = $generatedPath;
}

$cmd[] = '--log-level';
$cmd[] = 'INFO';

$result = null;
$pythonTried = [];
foreach (python_command_candidates() as $pythonPrefix) {
    $pythonTried[] = implode(' ', $pythonPrefix);
    $runCmd = array_merge($pythonPrefix, [$script], $cmd);
    $run = run_command($runCmd, dirname($script));
    $missingPython = (int) $run['code'] === 127 && preg_match('/(?:not found|not recognized|No such file)/i', $run['output']);
    if (!$missingPython) {
        $result = $run;
        break;
    }
}
if ($result === null) {
    tool_json([
        'ok' => false,
        'tool' => $tool,
        'title' => $config['title'],
        'exitCode' => 127,
        'output' => "Python is not available on this server. Tried: " . implode(', ', $pythonTried) . ". Ask hosting support for the Python executable path and set A1_PYTHON_BIN to that full path.",
        'generated' => null,
    ], 500);
}
$generated = null;
if ($generatedPath !== '' && is_file($generatedPath)) {
    $generated = [
        'name' => $tool . '.' . ($config['output_ext'] ?? 'txt'),
        'content' => substr((string) file_get_contents($generatedPath), 0, 200000),
    ];
}

foreach ($temps as $tmp) {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
}

$message = $result['timed_out'] ? 'Tool timed out. Try fewer URLs or shorter input.' : ($result['output'] ?: 'Tool completed.');
if (str_contains($message, 'ModuleNotFoundError')) {
    $message .= "\n\nServer note: this Python environment is missing one or more packages. Install tools/requirements.txt on the server.";
}
tool_json([
    'ok' => !$result['timed_out'] && (int) $result['code'] === 0,
    'tool' => $tool,
    'title' => $config['title'],
    'exitCode' => $result['code'],
    'output' => $message,
    'generated' => $generated,
]);
