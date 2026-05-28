# Content Calendar Generator

CLI tool that generates a content calendar from keyword/topic data with publishing dates, content types, priorities, and cluster assignments.

More context: [Content Calendar Generator – AAIO Inc.](https://aaioinc.com/tools/content_calendar_generator/)

## Prerequisites

- Python 3
- Packages: `pandas`, `openpyxl` (needed for `.xlsx` export)

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\content_calendar_generator` on Windows):

```bash
python content_calendar_generator.py [options]
```

From the parent `Tools` folder:

```bash
cd content_calendar_generator
python content_calendar_generator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--input` | Input CSV file containing keywords. |
| `--topics` | List of topics/keywords provided directly. Provide multiple separated by space. |
| `--weeks` | Number of weeks to schedule. |
| `--posts-per-week` | Number of posts per week. |
| `--output` | Save calendar as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Generate a calendar from a list of topics:

```bash
python content_calendar_generator.py --topics "SEO basics" "Link building" "Technical SEO" --weeks 8 --output calendar.xlsx
```

Generate a calendar from an input CSV file:

```bash
python content_calendar_generator.py --input keywords.csv --weeks 12 --posts-per-week 3 --output calendar.csv
```
