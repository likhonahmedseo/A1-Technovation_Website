# Word Frequency Analyzer

CLI tool. Word Frequency & Keyword Density Analyzer.

More context: [Word Frequency Analyzer - AAIO Inc.](https://aaioinc.com/tools/word_frequency_analyzer/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\word_frequency_analyzer` on Windows):

```bash
python word_frequency_analyzer.py [options]
```

From the parent `Tools` folder:

```bash
cd word_frequency_analyzer
python word_frequency_analyzer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--url` | URL to analyze |
| `--file` | HTML/text file |
| `--keywords` | Keywords to check density for |
| `--top` | CLI option. |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python word_frequency_analyzer.py --url https://example.com/page --keywords cloud hosting best vps server
python word_frequency_analyzer.py --file article.html --top 40 --output freq.xlsx
```
