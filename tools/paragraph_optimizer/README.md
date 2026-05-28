# Paragraph-Level Optimizer

CLI tool to score each paragraph on a page for relevance, readability, keyword contribution, and sentence variation.

More context: [Paragraph Optimizer – AAIO Inc.](https://aaioinc.com/tools/paragraph_optimizer/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`, `pandas`, `openpyxl` (needed for `.xlsx` export)

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\paragraph_optimizer` on Windows):

```bash
python paragraph_optimizer.py [options]
```

From the parent `Tools` folder:

```bash
cd paragraph_optimizer
python paragraph_optimizer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | URL to analyze. |
| `--file` | Local text or HTML file to analyze. |
| `--keyword` | Optional keyword to score paragraph relevance against. |
| `--output` | Save report as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Analyze and score paragraphs on a specific page targeting a keyword:

```bash
python paragraph_optimizer.py --url https://example.com --keyword "seo" --output paragraphs.xlsx
```