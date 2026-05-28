# Tfidf Extractor

CLI tool. TF-IDF Keyword Extractor.

More context: [Tfidf Extractor - AAIO Inc.](https://aaioinc.com/tools/tfidf_extractor/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\tfidf_extractor` on Windows):

```bash
python tfidf_extractor.py [options]
```

From the parent `Tools` folder:

```bash
cd tfidf_extractor
python tfidf_extractor.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--url` | Single URL to analyze |
| `--urls` | Multiple URLs |
| `--file` | Single file |
| `--files` | Multiple files |
| `--top` | Top N terms per document |
| `--ngram-min` | CLI option. |
| `--ngram-max` | CLI option. |
| `--output` | Save results as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python tfidf_extractor.py --url https://example.com/page --top 30
python tfidf_extractor.py --files page1.html page2.html --output tfidf_results.xlsx
python tfidf_extractor.py --file article.txt --ngram-range 1 3
```
