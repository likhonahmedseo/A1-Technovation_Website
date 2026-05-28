"""
Search Console Data Analyzer
================================
Analyzes Google Search Console exported CSV data: finds quick wins,
declining queries, CTR opportunities, and impression-rich low-CTR pages.

Usage:
    python search_console_analyzer.py --input gsc_queries.csv --output gsc_insights.xlsx
    python search_console_analyzer.py --input queries.csv --min-impressions 100
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/search_console_analyzer/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"

import argparse
import logging
import os
from collections.abc import Sequence
from typing import Optional

import numpy as np
import pandas as pd
import sys

LOGGER = logging.getLogger("search_console_analyzer")


def configure_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def _read_table(path: str) -> pd.DataFrame:
    p = (path or "").strip()
    if not p:
        raise ValueError("Input path is empty")
    if not os.path.isfile(p):
        raise FileNotFoundError(p)
    lower = p.lower()
    if lower.endswith(".csv"):
        return pd.read_csv(p)
    return pd.read_excel(p)


def _write_outputs(
    raw: pd.DataFrame,
    outputs: dict[str, pd.DataFrame],
    output_path: str,
) -> str:
    out = (output_path or "").strip()
    if not out:
        raise ValueError("Output path is empty")
    lower = out.lower()

    if lower.endswith(".csv"):
        combined = []
        combined.append(raw.assign(section="Raw Data"))
        for section, df in outputs.items():
            if df is None or df.empty:
                continue
            combined.append(df.assign(section=section))
        pd.concat(combined, ignore_index=True).to_csv(out, index=False, encoding="utf-8-sig")
        return out

    if not (lower.endswith(".xlsx") or lower.endswith(".xls")):
        out = out + ".xlsx"

    try:
        with pd.ExcelWriter(out, engine="openpyxl") as w:
            raw.to_excel(w, sheet_name="Raw Data", index=False)
            for section, df in outputs.items():
                if df is None or df.empty:
                    continue
                sheet = section[:31]
                df.to_excel(w, sheet_name=sheet, index=False)
        return out
    except Exception:
        fallback = out.rsplit(".", 1)[0] + ".csv"
        combined = []
        combined.append(raw.assign(section="Raw Data"))
        for section, df in outputs.items():
            if df is None or df.empty:
                continue
            combined.append(df.assign(section=section))
        pd.concat(combined, ignore_index=True).to_csv(
            fallback, index=False, encoding="utf-8-sig"
        )
        return fallback


def auto_detect_columns(df):
    cols = {c.lower().strip(): c for c in df.columns}
    query_col = next(
        (cols[k] for k in cols if "query" in k or "keyword" in k), df.columns[0]
    )
    clicks_col = next((cols[k] for k in cols if "click" in k), None)
    impr_col = next((cols[k] for k in cols if "impress" in k), None)
    ctr_col = next((cols[k] for k in cols if "ctr" in k), None)
    pos_col = next((cols[k] for k in cols if "position" in k), None)
    page_col = next((cols[k] for k in cols if "page" in k or "url" in k), None)
    return query_col, clicks_col, impr_col, ctr_col, pos_col, page_col


def _coerce_numeric(series: pd.Series) -> pd.Series:
    return pd.to_numeric(series, errors="coerce")


def _coerce_ctr(series: pd.Series) -> pd.Series:
    if series.dtype == object:
        cleaned = series.astype(str).str.replace("%", "", regex=False)
        vals = pd.to_numeric(cleaned, errors="coerce")
        return np.where(vals > 1, vals / 100.0, vals)
    vals = pd.to_numeric(series, errors="coerce")
    return np.where(vals > 1, vals / 100.0, vals)


def find_quick_wins(df, impr_col, pos_col, ctr_col, min_impr=50):
    """Keywords ranking 4-20 with good impressions but low CTR."""
    if not all([impr_col, pos_col]):
        return pd.DataFrame()
    mask = (df[impr_col] >= min_impr) & (df[pos_col] >= 4) & (df[pos_col] <= 20)
    wins = df[mask].sort_values(impr_col, ascending=False)
    return wins.head(30)


def find_ctr_opportunities(df, impr_col, ctr_col, pos_col, min_impr=100):
    """Pages ranking top 5 but with below-average CTR."""
    if not all([impr_col, ctr_col, pos_col]):
        return pd.DataFrame()

    work = df.copy()
    work[pos_col] = _coerce_numeric(work[pos_col])
    work[impr_col] = _coerce_numeric(work[impr_col])
    work[ctr_col] = _coerce_ctr(work[ctr_col])

    work = work.dropna(subset=[pos_col, impr_col, ctr_col])
    work["_pos_round"] = work[pos_col].round()
    avg_ctr_by_pos = work.groupby("_pos_round")[ctr_col].mean()
    work["_expected_ctr"] = work["_pos_round"].map(avg_ctr_by_pos).fillna(0.0)

    mask = (
        (work[impr_col] >= min_impr)
        & (work[pos_col] <= 5)
        & (work[ctr_col] < work["_expected_ctr"] * 0.7)
        & (work["_expected_ctr"] > 0)
    )
    return work.loc[mask].sort_values(impr_col, ascending=False).head(20).drop(
        columns=["_pos_round", "_expected_ctr"], errors="ignore"
    )


def find_striking_distance(df, impr_col, pos_col, min_impr=20):
    """Keywords at positions 11-20 (page 2) with decent impressions."""
    if not all([impr_col, pos_col]):
        return pd.DataFrame()
    mask = (df[impr_col] >= min_impr) & (df[pos_col] >= 11) & (df[pos_col] <= 20)
    return df[mask].sort_values(impr_col, ascending=False).head(30)


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Search Console Data Analyzer")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--input", required=True, help="GSC exported CSV/XLSX")
    parser.add_argument("--min-impressions", type=int, default=50)
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    try:
        df = _read_table(args.input)
    except Exception as exc:
        LOGGER.error("Failed to read input '%s': %s", args.input, exc)
        return 2

    LOGGER.info("Loaded %d rows with %d columns", len(df), len(df.columns))

    query_col, clicks_col, impr_col, ctr_col, pos_col, page_col = auto_detect_columns(
        df
    )

    LOGGER.info(
        "Detected columns: query=%s clicks=%s impressions=%s ctr=%s position=%s page=%s",
        query_col,
        clicks_col,
        impr_col,
        ctr_col,
        pos_col,
        page_col,
    )

    # Overview
    if clicks_col and clicks_col in df.columns:
        total_clicks = _coerce_numeric(df[clicks_col]).sum(skipna=True)
        LOGGER.info("Total clicks: %.0f", total_clicks)
    if impr_col and impr_col in df.columns:
        total_impr = _coerce_numeric(df[impr_col]).sum(skipna=True)
        LOGGER.info("Total impressions: %.0f", total_impr)
    if pos_col and pos_col in df.columns:
        avg_pos = _coerce_numeric(df[pos_col]).mean(skipna=True)
        if not np.isnan(avg_pos):
            LOGGER.info("Avg position: %.2f", avg_pos)

    # Quick wins
    quick_wins = find_quick_wins(df, impr_col, pos_col, ctr_col, args.min_impressions)
    if not quick_wins.empty:
        LOGGER.info("Quick Wins rows: %d", len(quick_wins))

    # Striking distance
    striking = find_striking_distance(df, impr_col, pos_col, args.min_impressions // 2)
    if not striking.empty:
        LOGGER.info("Striking Distance rows: %d", len(striking))

    # CTR opportunities
    ctr_opps = find_ctr_opportunities(
        df, impr_col, ctr_col, pos_col, args.min_impressions
    )
    if not ctr_opps.empty:
        LOGGER.info("CTR Opportunities rows: %d", len(ctr_opps))

    if args.output:
        try:
            written = _write_outputs(
                df,
                {
                    "Quick Wins": quick_wins,
                    "Striking Distance": striking,
                    "CTR Opportunities": ctr_opps,
                },
                args.output,
            )
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
