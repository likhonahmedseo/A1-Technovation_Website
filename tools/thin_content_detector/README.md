# Thin Content Detector

CLI tool. Thin Content Detector.

More context: [Thin Content Detector - AAIO Inc.](https://aaioinc.com/tools/thin_content_detector/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\thin_content_detector` on Windows):

```bash
python thin_content_detector.py [options]
```

From the parent `Tools` folder:

```bash
cd thin_content_detector
python thin_content_detector.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--urls` | URLs to check |
| `--files` | HTML files |
| `--min-words` | CLI option. |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python thin_content_detector.py --urls https://site.com/p1 https://site.com/p2 --min-words 300
python thin_content_detector.py --files *.html --output thin_report.xlsx
```
