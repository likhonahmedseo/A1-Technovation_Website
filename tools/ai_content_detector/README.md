# AI Content Detector

CLI tool that scores text for statistical signals often associated with AI-generated copy (burstiness, vocabulary richness, repetition, etc.). Use for triage only—results are indicative, not proof.

More context: [AI Content Detector – AAIO Inc.](https://aaioinc.com/tools/ai_content_detector/)

## Prerequisites

- Python 3

```bash
pip install -r requirements.txt
```

Or install from the parent `Tools` folder: `pip install -r ../requirements.txt`

## How to run

From this folder:

```bash
python ai_content_detector.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | Fetch and analyze visible text from a URL. |
| `--file` | Analyze a local text or HTML file (HTML is stripped to text). |
| `--text` | Analyze raw text from the command line. |
| `--output` | Save a single-row report as `.xlsx` or `.csv`. |
| `--timeout` | HTTP timeout seconds when using `--url` (default: 15). |
| `--user-agent` | User-Agent for `--url` (default: `Mozilla/5.0`). |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, or `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

Provide exactly one of `--url`, `--file`, or `--text`. The tool needs enough content (50+ words, 5+ sentences) or it exits with an error.

## Examples

```bash
python ai_content_detector.py --url https://example.com/post
python ai_content_detector.py --file article.txt --output ai_detection.xlsx
python ai_content_detector.py --text "Your long paragraph of content here..."
```

## Understanding the output

Console output includes a verdict (`LIKELY AI`, `POSSIBLY AI`, `LIKELY HUMAN`), an AI score (0–100), word/sentence counts, signal metrics, and optional “key findings.” Spreadsheet export mirrors the main numeric/text fields in one row.

Exit code `1` means missing/invalid input, fetch/read failure, insufficient text, or output write failure. Success is `0`.

## Tests

From this folder:

```bash
python -m unittest test_ai_content_detector -v
```

The test module `test_ai_content_detector.py` lives next to `ai_content_detector.py` so imports resolve when the working directory is this folder.
