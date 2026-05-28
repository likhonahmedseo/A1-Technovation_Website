# Search Console Analyzer

CLI tool to analyze Search Console exports and surface quick wins, striking-distance terms, and CTR opportunities.

More details: https://aaioinc.com/tools/search_console_analyzer/

## Prerequisites
- Python 3.10+
- Install dependencies:

```bash
pip install -r requirements.txt
```

## How to run

```bash
python search_console_analyzer.py --input gsc_queries.csv --output gsc_insights.xlsx
```

## Command-line options
- `--input`: Input CSV/XLSX exported from Search Console.
- `--min-impressions`: Minimum impressions threshold (default: `50`).
- `--output`: Optional output file path (`.xlsx` or `.csv`).
- `--log-level`: Logging level (`DEBUG`, `INFO`, `WARNING`, `ERROR`, `CRITICAL`).
- `--version`: Show tool version.

## Examples

```bash
python search_console_analyzer.py --input queries.csv --min-impressions 100
python search_console_analyzer.py --input queries.xlsx --output opportunities.csv
```
