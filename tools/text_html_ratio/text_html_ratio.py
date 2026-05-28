"""
Text-to-HTML Ratio Analyzer
==============================
Measures text vs code ratio, identifies bloated pages, and flags
excessive scripts/styles that may hurt performance and crawlability.

Usage:
    python text_html_ratio.py --url https://example.com
    python text_html_ratio.py --urls https://a.com https://b.com --output ratio_report.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/text_html_ratio/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"

import argparse
import logging
import os
import re
import sys
from collections.abc import Sequence
from typing import Optional

import pandas as pd

LOGGER = logging.getLogger("text_html_ratio")


def configure_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def _normalize_url(url: str) -> str:
    u = (url or "").strip()
    if not u:
        return ""
    if not u.startswith(("http://", "https://")):
        u = "https://" + u
    return u


def _write_results(df: pd.DataFrame, output_path: str) -> str:
    out = (output_path or "").strip()
    if not out:
        raise ValueError("Output path is empty")
    lower = out.lower()
    if lower.endswith(".csv"):
        df.to_csv(out, index=False, encoding="utf-8-sig")
        return out
    if not (lower.endswith(".xlsx") or lower.endswith(".xls")):
        out = out + ".xlsx"
    try:
        df.to_excel(out, index=False)
        return out
    except Exception:
        fallback = out.rsplit(".", 1)[0] + ".csv"
        df.to_csv(fallback, index=False, encoding="utf-8-sig")
        return fallback


def fetch_raw(session, url: str) -> str:
    resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
    resp.raise_for_status()
    return resp.text


def analyze_ratio(html, label):
    from bs4 import BeautifulSoup

    raw_size = len(html.encode("utf-8"))
    soup = BeautifulSoup(html, "html.parser")

    # Script/style sizes
    scripts = soup.find_all("script")
    styles = soup.find_all("style")
    link_css = soup.find_all("link", rel="stylesheet")
    inline_script_size = sum(len(s.string or "") for s in scripts)
    inline_style_size = sum(len(s.string or "") for s in styles)

    # Remove non-content
    for t in soup(["script", "style", "nav", "footer", "header", "aside"]):
        t.decompose()
    text = re.sub(r"\s+", " ", soup.get_text(" ", strip=True))
    text_size = len(text.encode("utf-8"))

    ratio = text_size / max(raw_size, 1) * 100
    html_only = raw_size - inline_script_size - inline_style_size

    return {
        "source": label,
        "raw_html_kb": round(raw_size / 1024, 1),
        "text_content_kb": round(text_size / 1024, 1),
        "text_ratio_pct": round(ratio, 1),
        "inline_script_kb": round(inline_script_size / 1024, 1),
        "inline_style_kb": round(inline_style_size / 1024, 1),
        "external_scripts": len([s for s in scripts if s.get("src")]),
        "external_stylesheets": len(link_css),
        "inline_scripts": len([s for s in scripts if not s.get("src")]),
        "word_count": len(re.findall(r"\b\w+\b", text)),
        "status": "OK" if ratio >= 25 else "LOW" if ratio >= 10 else "VERY LOW",
    }


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Text-to-HTML Ratio Analyzer")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="Single URL")
    parser.add_argument("--urls", nargs="+")
    parser.add_argument("--files", nargs="+")
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    sources = []
    for u in (args.urls or []) + ([args.url] if args.url else []):
        sources.append(("url", _normalize_url(u)))
    for f in args.files or []:
        sources.append(("file", f))
    if not sources:
        LOGGER.error("Provide --url, --urls, or --files")
        return 2

    import requests

    session = requests.Session()

    results = []
    for stype, src in sources:
        try:
            if stype == "url":
                if not src:
                    continue
                html = fetch_raw(session, src)
                label = src[:60]
            else:
                if not os.path.isfile(src):
                    raise FileNotFoundError(src)
                with open(src, "r", encoding="utf-8", errors="replace") as f:
                    html = f.read()
                label = str(src)
            results.append(analyze_ratio(html, label))
        except Exception as e:
            LOGGER.warning("Failed: %s - %s", src, e)

    sys.stdout.write("\n" + "=" * 70 + "\n")
    sys.stdout.write("  TEXT-TO-HTML RATIO ANALYSIS\n")
    sys.stdout.write("=" * 70 + "\n")
    sys.stdout.write(
        f"  {'Source':<40} {'Size':>7} {'Text':>7} {'Ratio':>7} {'Status':>10}\n"
    )
    sys.stdout.write(
        f"  {'-'*40} {'-'*7} {'-'*7} {'-'*7} {'-'*10}\n"
    )

    for r in results:
        icon = {"OK": "🟢", "LOW": "🟡", "VERY LOW": "🔴"}[r["status"]]
        sys.stdout.write(
            f"  {r['source']:<40} {r['raw_html_kb']:>5}kb {r['text_content_kb']:>5}kb {r['text_ratio_pct']:>5}% {icon} {r['status']}\n"
        )

    for r in results:
        if r["status"] != "OK":
            sys.stdout.write(f"\n  {r['source']}:\n")
            if r["inline_script_kb"] > 10:
                sys.stdout.write(
                    f"    Inline scripts: {r['inline_script_kb']}kb - move to external files\n"
                )
            if r["inline_style_kb"] > 5:
                sys.stdout.write(
                    f"    Inline styles: {r['inline_style_kb']}kb - move to stylesheet\n"
                )
            if r["external_scripts"] > 10:
                sys.stdout.write(
                    f"    {r['external_scripts']} external scripts - reduce or defer\n"
                )
            sys.stdout.write(
                f"    Aim for 25%+ text ratio (currently {r['text_ratio_pct']}%)\n"
            )

    if args.output:
        try:
            written = _write_results(pd.DataFrame(results), args.output)
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
