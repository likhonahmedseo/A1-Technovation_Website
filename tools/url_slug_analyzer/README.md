# Url Slug Analyzer

CLI tool. URL & Slug SEO Analyzer.

More context: [Url Slug Analyzer - AAIO Inc.](https://aaioinc.com/tools/url_slug_analyzer/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\url_slug_analyzer` on Windows):

```bash
python url_slug_analyzer.py [options]
```

From the parent `Tools` folder:

```bash
cd url_slug_analyzer
python url_slug_analyzer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--urls` | CLI option. |
| `--file` | CLI option. |
| `--sitemap` | Sitemap XML URL |
| `--keyword` | Check keyword in URLs |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python url_slug_analyzer.py --urls https://site.com/page1 https://site.com/long/nested/page
python url_slug_analyzer.py --sitemap https://site.com/sitemap.xml --output url_audit.xlsx
python url_slug_analyzer.py --file urls.txt --keyword cloud hosting
```
