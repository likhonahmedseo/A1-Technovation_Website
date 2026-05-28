# Content Similarity Checker

CLI tool to detect duplicate or near-duplicate content across pages using MinHash/LSH for fast similarity detection and cosine similarity for precision.

More context: [Content Similarity Checker – AAIO Inc.](https://aaioinc.com/tools/content_similarity_checker/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`, `pandas`, `numpy`, `scikit-learn`, `openpyxl` (needed for `.xlsx` export)

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\content_similarity_checker` on Windows):

```bash
python content_similarity_checker.py [options]
```

From the parent `Tools` folder:

```bash
cd content_similarity_checker
python content_similarity_checker.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--urls` | URLs to check for duplicate content. Provide multiple separated by space. |
| `--files` | Local files to check. Provide multiple separated by space. |
| `--threshold` | Similarity threshold (0.0 - 1.0, default: 0.7). |
| `--method` | Similarity method: `minhash`, `cosine`, or `both` (default: `both`). |
| `--output` | Save report as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Check similarity across URLs:

```bash
python content_similarity_checker.py --urls https://a.com https://b.com https://c.com --threshold 0.7
```

Check similarity across local HTML files and export:

```bash
python content_similarity_checker.py --files *.html --output duplicates.xlsx
```
