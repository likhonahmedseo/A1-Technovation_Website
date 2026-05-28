# Title Tag Optimizer

CLI tool. Title Tag CTR Optimizer.

More context: [Title Tag Optimizer - AAIO Inc.](https://aaioinc.com/tools/title_tag_optimizer/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\title_tag_optimizer` on Windows):

```bash
python title_tag_optimizer.py [options]
```

From the parent `Tools` folder:

```bash
cd title_tag_optimizer
python title_tag_optimizer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--titles` | Title tags to score |
| `--url` | Fetch and score page title |
| `--file` | CSV with titles |
| `--column` | Column name |
| `--keyword` | Target keyword |
| `--suggest` | Show improvement suggestions |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python title_tag_optimizer.py --titles 10 Best SEO Tools in 2025 SEO Tools Guide
python title_tag_optimizer.py --url https://example.com --suggest
python title_tag_optimizer.py --file titles.csv --output scored_titles.xlsx
```
