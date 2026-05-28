# Text Html Ratio

CLI tool. Text-to-HTML Ratio Analyzer.

More context: [Text Html Ratio - AAIO Inc.](https://aaioinc.com/tools/text_html_ratio/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\text_html_ratio` on Windows):

```bash
python text_html_ratio.py [options]
```

From the parent `Tools` folder:

```bash
cd text_html_ratio
python text_html_ratio.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--url` | Single URL |
| `--urls` | CLI option. |
| `--files` | CLI option. |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python text_html_ratio.py --url https://example.com
python text_html_ratio.py --urls https://a.com https://b.com --output ratio_report.xlsx
```
