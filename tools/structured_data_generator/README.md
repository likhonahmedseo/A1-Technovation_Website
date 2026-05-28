# Structured Data Generator

CLI tool. Structured Data Generator.

More context: [Structured Data Generator - AAIO Inc.](https://aaioinc.com/tools/structured_data_generator/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\structured_data_generator` on Windows):

```bash
python structured_data_generator.py [options]
```

From the parent `Tools` folder:

```bash
cd structured_data_generator
python structured_data_generator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--version` | CLI option. |
| `--type` | CLI option. |
| `--url` | Page URL to extract data from |
| `--name` | CLI option. |
| `--description` | Description |
| `--price` | Product price |
| `--currency` | CLI option. |
| `--address` | Business address |
| `--phone` | Business phone |
| `--author` | Author name |
| `--brand` | Product brand |
| `--rating` | Rating value |
| `--breadcrumbs` | Breadcrumb items as name:url pairs |
| `--output` | Save JSON to file |
| `--log-level` | CLI option. |

## Examples

```bash
python structured_data_generator.py --type article --url https://example.com/blog-post
python structured_data_generator.py --type product --name "Widget Pro" --price 29.99 --currency USD
python structured_data_generator.py --type local-business --name Joe's Pizza --address 123 Main St, NYC
python structured_data_generator.py --type howto --url https://example.com/tutorial --output schema.json
```
