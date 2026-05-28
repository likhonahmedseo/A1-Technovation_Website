# Content Uniqueness Scorer

CLI tool that compares rewritten/spun content against a source to score originality using embedding similarity, n-gram overlap, and structural comparison.

More context: [Content Uniqueness Scorer – AAIO Inc.](https://aaioinc.com/tools/content_uniqueness_scorer/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`, `pandas`, `numpy`, `scikit-learn`, `sentence-transformers` (optional but recommended for semantic similarity), `openpyxl` (needed for `.xlsx` export)

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\content_uniqueness_scorer` on Windows):

```bash
python content_uniqueness_scorer.py [options]
```

From the parent `Tools` folder:

```bash
cd content_uniqueness_scorer
python content_uniqueness_scorer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--source` | Path to the original source text file. |
| `--rewrite` | Path to the rewritten/new text file. |
| `--source-url` | URL of the original source content. |
| `--rewrite-url` | URL of the rewritten/new content. |
| `--output` | Save report as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Compare two local text files:

```bash
python content_uniqueness_scorer.py --source original.txt --rewrite new.txt
```

Compare two URLs:

```bash
python content_uniqueness_scorer.py --source-url https://source.com --rewrite-url https://new.com
```
