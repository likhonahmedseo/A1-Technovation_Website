# Structured Data Validator

CLI tool. Structured Data Test Suite — Validate schema against Google requirements.

More context: [Structured Data Validator - AAIO Inc.](https://aaioinc.com/tools/structured_data_validator/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\structured_data_validator` on Windows):

```bash
python structured_data_validator.py [options]
```

From the parent `Tools` folder:

```bash
cd structured_data_validator
python structured_data_validator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--url` | CLI option. |
| `--output` | CLI option. |
| `--version` | CLI option. |
| `--log-level` | CLI option. |

## Examples

```bash
python structured_data_validator.py --help
```
