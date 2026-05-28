# Text Summarizer Seo

CLI tool. SEO-Aware Text Summarizer.

More context: [Text Summarizer Seo - AAIO Inc.](https://aaioinc.com/tools/text_summarizer_seo/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\text_summarizer_seo` on Windows):

```bash
python text_summarizer_seo.py [options]
```

From the parent `Tools` folder:

```bash
cd text_summarizer_seo
python text_summarizer_seo.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | CLI option. |
| `--file` | CLI option. |
| `--text` | CLI option. |
| `--keyword` | Target keyword to preserve |
| `--type` | CLI option. |
| `--output` | CLI option. |
| `--version` | CLI option. |
| `--log-level` | CLI option. |

## Examples

```bash
python text_summarizer_seo.py --url https://example.com/post --keyword "cloud hosting" --type snippet
python text_summarizer_seo.py --file article.html --keyword "best crm" --type meta --output summary.txt
```
