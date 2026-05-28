"""
Paragraph-Level Optimizer — Score each paragraph for relevance, readability, keyword contribution.
Usage: python paragraph_optimizer.py --url https://example.com --keyword "seo" --output paragraphs.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/paragraph_optimizer/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"
import argparse
import logging
import re
from collections.abc import Sequence
from typing import Optional

import pandas as pd
import sys


LOGGER = logging.getLogger("paragraph_optimizer")


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
    out = output_path.strip()
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


def score_para(text, keyword=None):
    words = re.findall(r"\b\w+\b", text.lower())
    wc = len(words)
    sents = [s for s in re.split(r"[.!?]+", text) if len(s.strip()) > 3]
    score = 50
    if 40 <= wc <= 150:
        score += 10
    elif wc < 20:
        score -= 15
    if sents:
        avg_sl = wc / len(sents)
        if 10 <= avg_sl <= 20:
            score += 10
        elif avg_sl > 30:
            score -= 10
    if keyword and keyword.lower() in text.lower():
        score += 15
    if re.search(r"\d+", text):
        score += 5
    unique = len(set(words)) / max(wc, 1)
    if unique > 0.6:
        score += 5
    return min(max(score, 0), 100)


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--url")
    p.add_argument("--file")
    p.add_argument("--keyword")
    p.add_argument("--output")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR"],
        help="Logging verbosity.",
    )
    a = p.parse_args(argv)

    configure_logging(a.log_level)

    if a.url:
        url = _normalize_url(a.url)
        if not url:
            LOGGER.error("Invalid --url")
            return 2
        try:
            import requests
            from bs4 import BeautifulSoup
        except Exception:
            LOGGER.error("Missing dependencies. Install: pip install requests beautifulsoup4")
            return 1

        session = requests.Session()
        resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text or "", "html.parser")
        paras = [p.get_text(strip=True) for p in soup.find_all("p") if len(p.get_text(strip=True)) > 20]
    elif a.file:
        try:
            with open(a.file, "r", encoding="utf-8", errors="ignore") as f:
                paras = [p.strip() for p in f.read().split("\n\n") if len(p.strip()) > 20]
        except Exception as exc:
            LOGGER.error("Failed reading file: %s", exc)
            return 2
    else:
        LOGGER.error("Provide --url or --file")
        return 2

    if not paras:
        LOGGER.error("No paragraphs found.")
        return 1
    results = [
        {
            "index": i + 1,
            "text": p[:100],
            "word_count": len(p.split()),
            "score": score_para(p, a.keyword),
        }
        for i, p in enumerate(paras)
    ]
    results.sort(key=lambda x: x["score"])
    LOGGER.info("PARAGRAPH ANALYSIS (%d paragraphs)", len(results))
    for r in results[:10]:
        LOGGER.info("[%s/100] P%s: %s...", r["score"], r["index"], r["text"])
    if a.output:
        try:
            saved = _write_results(pd.DataFrame(results), a.output)
            LOGGER.info("Saved to %s", saved)
        except Exception as exc:
            LOGGER.error("Failed writing output: %s", exc)
            return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
