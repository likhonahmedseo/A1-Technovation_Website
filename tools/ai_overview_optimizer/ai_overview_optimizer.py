"""
AI Overview Optimizer
=======================
Optimizes content structure for Google AI Overviews.
Checks for clear definitions, structured data, concise answers, and source-worthy formatting.

Usage:
    python ai_overview_optimizer.py --url https://example.com/post --keyword "what is SEO"
    python ai_overview_optimizer.py --file article.html --output aio_report.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/ai_overview_optimizer/
# ---------------------------------------------------------------------------

from __future__ import annotations

__version__ = "1.0.0"

import argparse
import logging
import re
import sys
from typing import Any, Dict, List, Optional, Sequence

import pandas as pd
from bs4 import BeautifulSoup

LOGGER = logging.getLogger("ai_overview_optimizer")


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def fetch_soup(url: str, timeout: int = 15, user_agent: str = "Mozilla/5.0") -> BeautifulSoup:
    """
    Fetch HTML from a URL and parse it into a BeautifulSoup object.

    Args:
        url: URL to fetch.
        timeout: Timeout in seconds for the HTTP request.
        user_agent: User-Agent string to use for the request.

    Returns:
        BeautifulSoup parsed HTML object.

    Raises:
        RuntimeError: If requests is not installed.
        requests.exceptions.RequestException: If the HTTP request fails.
    """
    try:
        import requests
    except ImportError:
        raise RuntimeError("Missing dependency: requests. Run: pip install requests")

    r = requests.get(url, headers={"User-Agent": user_agent}, timeout=timeout)
    r.raise_for_status()
    return BeautifulSoup(r.text, "html.parser")


def analyze(soup: BeautifulSoup, keyword: Optional[str] = None) -> Dict[str, Any]:
    """
    Analyze HTML content for AI Overview optimization readiness.

    Args:
        soup: Parsed HTML content.
        keyword: Optional target keyword to check presence in the first paragraph.

    Returns:
        A dictionary containing the score, grade, positive factors, and improvement tips.
    """
    text = soup.get_text(" ", strip=True)
    score = 0
    tips: List[str] = []
    factors: List[str] = []

    # Concise definition paragraphs (40-60 words ideal for AIO)
    paras = [
        p.get_text(strip=True)
        for p in soup.find_all("p")
        if 30 < len(p.get_text(strip=True)) < 350
    ]
    short_paras = [p for p in paras if 100 <= len(p) <= 300]
    
    if short_paras:
        score += 15
        factors.append(f"{len(short_paras)} concise answer-length paragraphs")
    else:
        tips.append(
            "Add 1-2 concise paragraphs (40-60 words) that directly answer the query"
        )

    # Structured lists
    lists = soup.find_all(["ul", "ol"])
    list_items = sum(len(l.find_all("li")) for l in lists)
    if list_items >= 5:
        score += 10
        factors.append(f"{list_items} list items (good for step/list AIO)")
    else:
        tips.append("Add structured lists for step-by-step or feature-type queries")

    # Tables
    tables = soup.find_all("table")
    if tables:
        score += 10
        factors.append(f"{len(tables)} tables (good for comparison AIO)")

    # Question headings matching query
    h2s = soup.find_all(["h2", "h3"])
    question_h2 = [
        h
        for h in h2s
        if h.get_text().strip().endswith("?")
        or any(
            h.get_text().lower().startswith(w)
            for w in ["what", "how", "why", "when", "who", "which", "where"]
        )
    ]
    if question_h2:
        score += 10
        factors.append(f"{len(question_h2)} question-format headings")
    else:
        tips.append("Use question-format H2s that match common queries")

    # Schema
    schemas = soup.find_all("script", type="application/ld+json")
    has_faq = any("FAQPage" in s.string for s in schemas if s.string)
    has_howto = any("HowTo" in s.string for s in schemas if s.string)
    if has_faq:
        score += 10
        factors.append("FAQPage schema present")
    if has_howto:
        score += 10
        factors.append("HowTo schema present")
    if not has_faq and not has_howto:
        tips.append("Add FAQ or HowTo schema markup")

    # Definition patterns
    if re.search(
        r"(is a|refers to|is defined as|means|is the process of)", text.lower()
    ):
        score += 10
        factors.append("Contains definition-style language")

    # Keyword in first paragraph
    if keyword and paras:
        if keyword.lower() in paras[0].lower():
            score += 10
            factors.append("Keyword in first paragraph")
        else:
            tips.append(f"Include '{keyword}' in the first paragraph")

    # Authority signals
    if re.search(
        r"(according to|research shows|study|data from|statistics)", text.lower()
    ):
        score += 5
        factors.append("Authority/citation language present")

    # Freshness
    if re.search(r"\b202[4-9]\b", text):
        score += 5
        factors.append("Current year reference")

    score = min(score, 100)
    grade = "A" if score >= 75 else "B" if score >= 55 else "C" if score >= 35 else "D"
    
    return {
        "url": "",
        "score": score,
        "grade": grade,
        "factors": factors,
        "tips": tips,
    }


def main(argv: Optional[Sequence[str]] = None) -> int:
    """
    Main CLI entry point for the AI Overview Optimizer.

    Args:
        argv: Optional sequence of command-line arguments.

    Returns:
        Integer exit code (0 for success, 1 or 2 for errors).
    """
    p = argparse.ArgumentParser(description="AI Overview Optimizer")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument("--url", help="URL to analyze")
    p.add_argument("--file", help="Local HTML file to analyze")
    p.add_argument("--keyword", help="Target keyword to check for optimization")
    p.add_argument("--output", help="Save report as XLSX/CSV")
    p.add_argument(
        "--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"], help="Set logging level"
    )
    a = p.parse_args(argv)

    configure_logging(a.log_level)

    try:
        if a.url:
            soup = fetch_soup(a.url)
            r = analyze(soup, a.keyword)
            r["url"] = a.url
        elif a.file:
            with open(a.file, "r", encoding="utf-8") as f:
                soup = BeautifulSoup(f.read(), "html.parser")
            r = analyze(soup, a.keyword)
            r["url"] = a.file
        else:
            LOGGER.error("Provide --url or --file")
            return 2
    except Exception as e:
        LOGGER.error(f"Failed to process content: {e}")
        return 1

    icon = {"A": "🟢", "B": "🟡", "C": "🟠", "D": "🔴"}.get(r["grade"], "")
    
    LOGGER.info(f"\n{'='*60}")
    LOGGER.info(f"  {icon} AI OVERVIEW READINESS [{r['grade']}] {r['score']}/100")
    LOGGER.info(f"{'='*60}")
    
    if r["factors"]:
        LOGGER.info("  Strengths:")
        for f in r["factors"]:
            LOGGER.info(f"    ✅ {f}")
            
    if r["tips"]:
        LOGGER.info("  Improvements:")
        for t in r["tips"]:
            LOGGER.info(f"    💡 {t}")
            
    if a.output:
        df = pd.DataFrame([r])
        try:
            if a.output.lower().endswith(".csv"):
                df.to_csv(a.output, index=False)
            else:
                df.to_excel(a.output, index=False)
            LOGGER.info(f"\nSaved to {a.output}")
        except Exception as e:
            fallback = re.sub(r"\.xlsx?$", "", a.output, flags=re.IGNORECASE) + ".csv"
            df.to_csv(fallback, index=False)
            LOGGER.info(f"\nFailed to write Excel ({e}); saved CSV instead: {fallback}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
