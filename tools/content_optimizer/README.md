# Content Optimizer

CLI tool to analyze content for SEO quality. It performs TF-IDF keyword coverage, entity extraction, readability scoring, and structural analysis.

More context: [Content Optimizer – AAIO Inc.](https://aaioinc.com/tools/content_optimizer/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\content_optimizer` on Windows):

```bash
python content_optimizer.py [options]
```

From the parent `Tools` folder:

```bash
cd content_optimizer
python content_optimizer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | URL to analyze. |
| `--file` | Local file to analyze. |
| `--text` | Raw text string to analyze. |
| `--target-keyword` | (Required) Target keyword for SEO scoring. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

You must provide at least one `--url`, `--file`, or `--text`.

## Examples

Analyze content from a URL:

```bash
python content_optimizer.py --url https://example.com/page --target-keyword "project management tools"
```

Analyze content from a local HTML file:

```bash
python content_optimizer.py --file article.html --target-keyword "cloud hosting"
```

Analyze raw text:

```bash
python content_optimizer.py --text "Your content here..." --target-keyword "best crm software"
```
