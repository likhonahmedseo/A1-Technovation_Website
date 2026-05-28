# Topic Authority Scorer

CLI tool. Topic Authority Scorer.

More context: [Topic Authority Scorer - AAIO Inc.](https://aaioinc.com/tools/topic_authority_scorer/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\topic_authority_scorer` on Windows):

```bash
python topic_authority_scorer.py [options]
```

From the parent `Tools` folder:

```bash
cd topic_authority_scorer
python topic_authority_scorer.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--urls` | Site page URLs |
| `--files` | HTML files |
| `--topic` | Topic to score authority for |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python topic_authority_scorer.py --urls https://site.com/p1 https://site.com/p2 https://site.com/p3 --topic machine learning
python topic_authority_scorer.py --files page1.html page2.html --topic "cloud computing" --output authority.xlsx
```
