# Topic Coverage Completeness

CLI tool. Topic Coverage Completeness Checker.

More context: [Topic Coverage Completeness - AAIO Inc.](https://aaioinc.com/tools/topic_coverage_completeness/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\topic_coverage_completeness` on Windows):

```bash
python topic_coverage_completeness.py [options]
```

From the parent `Tools` folder:

```bash
cd topic_coverage_completeness
python topic_coverage_completeness.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--my-url` | CLI option. |
| `--competitor-urls` | CLI option. |
| `--keyword` | CLI option. |
| `--output` | CLI option. |
| `--version` | CLI option. |
| `--log-level` | CLI option. |

## Examples

```bash
python topic_coverage_completeness.py --my-url https://mysite.com/post --competitor-urls https://a.com https://b.com --keyword "crm software" --output coverage.xlsx
```
