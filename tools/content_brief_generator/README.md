# Content Brief Generator

CLI tool that generates data-driven content briefs by analyzing top-ranking competitor pages. Produces recommended word count, headings, entities, terms, and structure.

More context: [Content Brief Generator – AAIO Inc.](https://aaioinc.com/tools/content_brief_generator/)

## Prerequisites

- Python 3
- Packages: `requests`, `beautifulsoup4`, `pandas`, `numpy`, `scikit-learn`, `spacy`, `openpyxl`

Install minimal dependencies (from this folder):

```bash
pip install -r requirements.txt
```

If `spacy` is installed, the tool will automatically download the `en_core_web_sm` model on first run.

## How to run

From the folder that contains this README (`Tools\content_brief_generator` on Windows):

```bash
python content_brief_generator.py [options]
```

From the parent `Tools` folder:

```bash
cd content_brief_generator
python content_brief_generator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--urls` | (Required) Competitor URLs to analyze. Provide multiple separated by space. |
| `--keyword` | (Required) Target keyword. |
| `--output` | Save brief as `.xlsx` or `.csv`. |
| `--log-level` | `DEBUG`, `INFO`, `WARNING`, `ERROR` (default: `INFO`). |
| `--version` | Print version and exit. |

## Examples

Generate a brief for a keyword based on competitor pages:

```bash
python content_brief_generator.py --urls https://comp1.com https://comp2.com https://comp3.com --keyword "best crm software" --output brief.xlsx
```
