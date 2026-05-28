"""
SEO Report Generator — Generate HTML report from audit data.
Usage: python seo_report_generator.py --input audit.xlsx --output report.html
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/seo_report_generator/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"
import argparse
import html
import logging
import os
from collections.abc import Sequence
from typing import Optional

import pandas as pd
import sys

LOGGER = logging.getLogger("seo_report_generator")


def configure_logging(level: str) -> None:
    """Set up root logger with the specified verbosity level."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser(description="SEO Report Generator")
    p.add_argument("--input", required=True)
    p.add_argument("--output", default="seo_report.html")
    p.add_argument("--title", default="SEO Audit Report")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    a = p.parse_args(argv)
    configure_logging(a.log_level)

    if not os.path.isfile(a.input):
        LOGGER.error("Input file not found: %s", a.input)
        return 2

    try:
        xls = pd.ExcelFile(a.input)
    except Exception as exc:
        LOGGER.error("Failed to open input '%s': %s", a.input, exc)
        return 2

    sheets = xls.sheet_names
    safe_title = html.escape(a.title)
    safe_input = html.escape(a.input)

    out_html = f"<!DOCTYPE html><html><head><meta charset=utf-8><title>{safe_title}</title>"
    out_html += "<style>body{font-family:Arial,sans-serif;max-width:1000px;margin:40px auto;padding:0 20px;color:#333}"
    out_html += "h1{color:#1a1a2e}h2{color:#16213e;border-bottom:2px solid #eee;padding-bottom:8px}table{border-collapse:collapse;width:100%;margin:16px 0}"
    out_html += "th,td{border:1px solid #ddd;padding:8px;text-align:left;font-size:13px}th{background:#f5f5f5}tr:nth-child(even){background:#fafafa}"
    out_html += ".stat{display:inline-block;background:#f0f4ff;padding:12px 20px;border-radius:8px;margin:4px;text-align:center}"
    out_html += ".stat-n{font-size:24px;font-weight:bold;color:#1a1a2e}.stat-l{font-size:12px;color:#666}</style></head><body>"
    out_html += f"<h1>{safe_title}</h1><p>Generated from {safe_input}</p>"
    for sheet in sheets:
        try:
            df = pd.read_excel(a.input, sheet_name=sheet)
        except Exception as exc:
            LOGGER.warning("Failed reading sheet '%s': %s", sheet, exc)
            continue
        safe_sheet = html.escape(sheet)
        out_html += f"<h2>{safe_sheet}</h2><p>{len(df)} rows</p>"
        out_html += df.head(50).to_html(index=False, classes="data")
    out_html += "</body></html>"

    try:
        with open(a.output, "w", encoding="utf-8") as f:
            f.write(out_html)
    except Exception as exc:
        LOGGER.error("Failed writing output '%s': %s", a.output, exc)
        return 2

    LOGGER.info("Report generated: %s (%d sections)", a.output, len(sheets))
    return 0


if __name__ == "__main__":
    sys.exit(main())
