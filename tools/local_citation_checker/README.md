# Local Citation Consistency Checker

CLI tool to check Name, Address, Phone Number (NAP) consistency across different web pages for local SEO.

More context: [Local Citation Checker – AAIO Inc.](https://aaioinc.com/tools/local_citation_checker/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`, `pandas`, `openpyxl` (needed for `.xlsx` export)

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\local_citation_checker` on Windows):

```bash
python local_citation_checker.py [options]
```

From the parent `Tools` folder:

```bash
cd local_citation_checker
python local_citation_checker.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--urls` | (Required) URLs to check for NAP consistency. Provide multiple separated by space. |
| `--output` | Save report as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Check NAP consistency across multiple URLs and export:

```bash
python local_citation_checker.py --urls https://page1.com https://page2.com --output citations.xlsx
```