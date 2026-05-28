# Xml Sitemap Generator

CLI tool. Smart Sitemap Generator — Generate priority-weighted sitemaps from URL list.

More context: [Xml Sitemap Generator - AAIO Inc.](https://aaioinc.com/tools/xml_sitemap_generator/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\xml_sitemap_generator` on Windows):

```bash
python xml_sitemap_generator.py [options]
```

From the parent `Tools` folder:

```bash
cd xml_sitemap_generator
python xml_sitemap_generator.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--urls` | File with URLs |
| `--output` | CLI option. |
| `--version` | CLI option. |
| `--base-url` | Base URL for relative paths |
| `--log-level` | CLI option. |

## Examples

```bash
python xml_sitemap_generator.py --help
```
