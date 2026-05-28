"""
Thin Content Detector
=======================
Identifies thin, low-quality, or duplicate content across pages.
Flags pages below word count thresholds, with low text-to-HTML ratio,
or excessive boilerplate.

Usage:
    python thin_content_detector.py --urls https://site.com/p1 https://site.com/p2 --min-words 300
    python thin_content_detector.py --files *.html --output thin_report.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/thin_content_detector/
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

LOGGER = logging.getLogger("thin_content_detector")


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


def fetch_page(session, url: str) -> str:
    resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
    resp.raise_for_status()
    return resp.text


def analyze_page(html, label):
    from bs4 import BeautifulSoup

    raw_size = len(html)
    soup = BeautifulSoup(html, "html.parser")
    for t in soup(["script", "style", "nav", "footer", "header"]):
        t.decompose()
    text = re.sub(r"\s+", " ", soup.get_text(" ", strip=True))
    words = re.findall(r"\b\w+\b", text)
    sentences = [s.strip() for s in re.split(r"[.!?]+", text) if len(s.strip()) > 5]
    paragraphs = [
        p.get_text(strip=True)
        for p in soup.find_all("p")
        if len(p.get_text(strip=True)) > 20
    ]
    images = len(soup.find_all("img"))
    links = len(soup.find_all("a", href=True))
    text_ratio = len(text) / max(raw_size, 1) * 100

    # Duplicate sentence check
    sent_counts = {}
    for s in sentences:
        s_lower = s.lower().strip()
        sent_counts[s_lower] = sent_counts.get(s_lower, 0) + 1
    dup_sentences = sum(1 for c in sent_counts.values() if c > 1)

    return {
        "source": label,
        "word_count": len(words),
        "sentence_count": len(sentences),
        "paragraph_count": len(paragraphs),
        "image_count": images,
        "link_count": links,
        "html_size_kb": round(raw_size / 1024, 1),
        "text_html_ratio_pct": round(text_ratio, 1),
        "duplicate_sentences": dup_sentences,
        "avg_sentence_length": round(len(words) / max(len(sentences), 1), 1),
    }


def classify(row, min_words=300):
    issues = []
    if row["word_count"] < min_words:
        issues.append(f"Thin content ({row['word_count']} words < {min_words})")
    if row["text_html_ratio_pct"] < 10:
        issues.append(f"Low text ratio ({row['text_html_ratio_pct']}%)")
    if row["paragraph_count"] < 2:
        issues.append("Very few paragraphs")
    if row["duplicate_sentences"] > 5:
        issues.append(f"High sentence duplication ({row['duplicate_sentences']})")
    severity = "THIN" if issues else "OK"
    return severity, "; ".join(issues) if issues else "Passes checks"


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Thin Content Detector")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--urls", nargs="+", help="URLs to check")
    parser.add_argument("--files", nargs="+", help="HTML files")
    parser.add_argument("--min-words", type=int, default=300)
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    import requests

    session = requests.Session()

    results = []
    for u in args.urls or []:
        try:
            url = _normalize_url(u)
            if not url:
                continue
            html = fetch_page(session, url)
            results.append(analyze_page(html, url[:60]))
        except Exception as e:
            LOGGER.warning("Failed: %s - %s", u, e)
    for f in args.files or []:
        fp = (f or "").strip()
        if not fp:
            continue
        if not os.path.isfile(fp):
            LOGGER.warning("File not found: %s", fp)
            continue
        with open(fp, "r", encoding="utf-8", errors="replace") as fh:
            results.append(analyze_page(fh.read(), fp))

    if not results:
        LOGGER.error("No pages to analyze")
        return 2

    sys.stdout.write("\n" + "=" * 70 + "\n")
    sys.stdout.write(f"  THIN CONTENT REPORT (min words: {args.min_words})\n")
    sys.stdout.write("=" * 70 + "\n")

    thin_count = 0
    for r in results:
        severity, msg = classify(r, args.min_words)
        r["status"] = severity
        r["issues"] = msg
        icon = "🔴" if severity == "THIN" else "🟢"
        if severity == "THIN":
            thin_count += 1
        sys.stdout.write(f"\n  {icon} {r['source']}\n")
        sys.stdout.write(
            f"    Words: {r['word_count']}  Sentences: {r['sentence_count']}  Text ratio: {r['text_html_ratio_pct']}%\n"
        )
        sys.stdout.write(f"    Status: {msg}\n")

    sys.stdout.write(
        f"\n  Summary: {thin_count}/{len(results)} pages flagged as thin\n"
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
