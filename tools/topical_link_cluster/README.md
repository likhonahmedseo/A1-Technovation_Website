# Topical Link Cluster

CLI tool. Topical Link Cluster Builder — Group pages into topic silos with optimal interlinking.

More context: [Topical Link Cluster - AAIO Inc.](https://aaioinc.com/tools/topical_link_cluster/)

## Prerequisites

- Python 3
- Install dependencies from project root:

```bash
pip install -r requirements.txt
```

## How to run

From the folder that contains this README (`Tools\topical_link_cluster` on Windows):

```bash
python topical_link_cluster.py [options]
```

From the parent `Tools` folder:

```bash
cd topical_link_cluster
python topical_link_cluster.py [options]
```

## Command-line options

| Option | Description |
|--------|-------------|
| `--urls` | CLI option. |
| `--num-clusters` | CLI option. |
| `--output` | CLI option. |
| `--version` | CLI option. |
| `--log-level` | CLI option. |

## Examples

```bash
python topical_link_cluster.py --help
```
