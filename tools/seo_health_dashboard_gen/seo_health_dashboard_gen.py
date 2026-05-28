"""
SEO Health Dashboard Generator — Single HTML dashboard from audit data.
Usage: python seo_health_dashboard_gen.py --input audit.xlsx --output dashboard.html
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/seo_health_dashboard_gen/
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

LOGGER = logging.getLogger("seo_health_dashboard_gen")


def configure_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser(description="SEO Health Dashboard Generator")
    p.add_argument("--input", required=True)
    p.add_argument("--output", default="dashboard.html")
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
    out_html = "<!DOCTYPE html><html><head><meta charset=utf-8><title>SEO Dashboard</title>"
    out_html += "<style>*{box-sizing:border-box;margin:0}body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;padding:24px}"
    out_html += "h1{font-size:24px;margin:0 0 24px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px;margin:0 0 32px}"
    out_html += ".card{background:#1e293b;border-radius:12px;padding:20px;border:1px solid #334155}"
    out_html += ".card h3{font-size:14px;color:#94a3b8;margin:0 0 8px}.card .num{font-size:28px;font-weight:700}"
    out_html += "table{width:100%;border-collapse:collapse;margin:16px 0;font-size:13px}"
    out_html += "th,td{padding:8px;text-align:left;border-bottom:1px solid #334155}th{color:#94a3b8}</style></head><body>"
    out_html += "<h1>SEO Health Dashboard</h1><div class=grid>"
    # Summary cards
    for sheet in sheets[:4]:
        try:
            df = pd.read_excel(a.input, sheet_name=sheet)
        except Exception as exc:
            LOGGER.warning("Failed reading sheet '%s': %s", sheet, exc)
            continue
        safe_sheet = html.escape(sheet)
        out_html += f'<div class=card><h3>{safe_sheet}</h3><div class=num>{len(df)}</div><div style="color:#94a3b8;font-size:13px">rows</div></div>'
    out_html += "</div>"
    # Tables
    for sheet in sheets:
        try:
            df = pd.read_excel(a.input, sheet_name=sheet)
        except Exception as exc:
            LOGGER.warning("Failed reading sheet '%s': %s", sheet, exc)
            continue
        safe_sheet = html.escape(sheet)
        out_html += (
            f"<h2 style='font-size:18px;margin:24px 0 12px;color:#f8fafc'>{safe_sheet}</h2>"
        )
        out_html += df.head(25).to_html(index=False, classes="data", border=0)
    out_html += "</body></html>"

    try:
        with open(a.output, "w", encoding="utf-8") as f:
            f.write(out_html)
    except Exception as exc:
        LOGGER.error("Failed writing output '%s': %s", a.output, exc)
        return 2

    LOGGER.info("Dashboard generated: %s (%d sections)", a.output, len(sheets))
    return 0


if __name__ == "__main__":
    sys.exit(main())
