# Core Web Vitals Estimator

CLI tool that estimates Core Web Vitals (CWV) signals from HTML analysis: LCP candidates, CLS risk factors, render-blocking resources, and FID/INP risk from JavaScript weight.

More context: [Core Web Vitals Estimator – AAIO Inc.](https://aaioinc.com/tools/core_web_vitals_estimator/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`, `pandas`, `openpyxl` (needed for `.xlsx` export)

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\core_web_vitals_estimator` on Windows):

```bash
python core_web_vitals_estimator.py [options]
```

From the parent `Tools` folder:

```bash
cd core_web_vitals_estimator
python core_web_vitals_estimator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | URL to analyze. |
| `--urls` | Multiple URLs to analyze (separated by space). |
| `--output` | Save report as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Analyze a single URL:

```bash
python core_web_vitals_estimator.py --url https://example.com
```

Analyze multiple URLs and export:

```bash
python core_web_vitals_estimator.py --urls https://a.com https://b.com --output cwv.xlsx
```
