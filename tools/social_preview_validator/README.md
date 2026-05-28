# Social Preview Validator

CLI tool. Social Preview Validator.

More context: [Social Preview Validator - AAIO Inc.](https://aaioinc.com/tools/social_preview_validator/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\social_preview_validator` on Windows):

```bash
python social_preview_validator.py [options]
```

From the parent `Tools` folder:

```bash
cd social_preview_validator
python social_preview_validator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--url` | Single URL |
| `--urls` | CLI option. |
| `--output` | Save as XLSX |
| `--log-level` | CLI option. |

## Examples

```bash
python social_preview_validator.py --url https://example.com/post
python social_preview_validator.py --urls https://a.com https://b.com --output social.xlsx
```
