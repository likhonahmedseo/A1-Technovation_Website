# AI Overview Optimizer

Scores how well a page’s structure and content match patterns that suit Google AI Overviews (clear answers, definitions, structured cues). Outputs a grade, score, strengths, and improvement tips.

More context: [AI Overview Optimizer – AAIO Inc.](https://aaioinc.com/tools/ai_overview_optimizer/)

## Prerequisites

- Python 3

```bash
pip install -r requirements.txt
```

Or: `pip install -r ../requirements.txt` from the parent `Tools` folder.

## How to run

From this folder:

```bash
python ai_overview_optimizer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | Fetch HTML from a URL and analyze. |
| `--file` | Analyze a local HTML file. |
| `--keyword` | Optional target keyword to check in early content. |
| `--output` | Save report as `.xlsx` or `.csv` (falls back to CSV if Excel write fails). |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, or `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

You must pass `--url` or `--file`.

## Examples

```bash
python ai_overview_optimizer.py --url https://example.com/post --keyword "what is SEO"
python ai_overview_optimizer.py --file article.html --output aio_report.xlsx
```

## Understanding the output

Logs show an AI Overview readiness grade/score, listed strengths, and tips. Exported file contains one row with the same structured fields.

Exit code `2` if neither `--url` nor `--file` is given; `1` on processing errors; `0` on success.
