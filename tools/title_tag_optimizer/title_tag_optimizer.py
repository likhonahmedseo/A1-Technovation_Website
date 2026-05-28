"""
Title Tag CTR Optimizer
=========================
Scores title tags for click-through-rate potential using NLP signals:
power words, numbers, emotional triggers, length, keyword placement.

Usage:
    python title_tag_optimizer.py --titles "10 Best SEO Tools in 2025" "SEO Tools Guide"
    python title_tag_optimizer.py --url https://example.com --suggest
    python title_tag_optimizer.py --file titles.csv --output scored_titles.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/title_tag_optimizer/
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

LOGGER = logging.getLogger("title_tag_optimizer")


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

POWER_WORDS = set(
    "ultimate best top proven free easy quick fast simple complete guide essential powerful amazing incredible secret hack boost instant guaranteed step expert exclusive comprehensive definitive".split()
)
NEGATIVE_WORDS = set("worst avoid never mistake wrong bad terrible horrible".split())
EMOTIONAL_WORDS = set(
    "surprising shocking unbelievable amazing stunning incredible remarkable extraordinary mindblowing heartbreaking".split()
)
CTA_WORDS = set(
    "learn discover find get try start explore unlock master download".split()
)


def score_title(title, keyword=None):
    title_lower = title.lower()
    words = re.findall(r"\b[a-z]+\b", title_lower)
    score = 50  # Base score

    factors = []

    # Length (ideal 50-60 chars)
    length = len(title)
    if 50 <= length <= 60:
        score += 10
        factors.append(("Length: ideal (50-60)", +10))
    elif 40 <= length < 50:
        score += 5
        factors.append(("Length: acceptable", +5))
    elif length > 60:
        score -= 5
        factors.append(("Length: truncated in SERP", -5))
    elif length < 30:
        score -= 10
        factors.append(("Length: too short", -10))

    # Numbers
    if re.search(r"\d+", title):
        score += 8
        factors.append(("Contains numbers", +8))

    # Power words
    pw = set(words) & POWER_WORDS
    if pw:
        score += min(len(pw) * 4, 12)
        factors.append((f"Power words: {', '.join(pw)}", min(len(pw) * 4, 12)))

    # Emotional words
    ew = set(words) & EMOTIONAL_WORDS
    if ew:
        score += 5
        factors.append((f"Emotional triggers: {', '.join(ew)}", +5))

    # Negative/curiosity gap words
    nw = set(words) & NEGATIVE_WORDS
    if nw:
        score += 5
        factors.append((f"Curiosity/negative: {', '.join(nw)}", +5))

    # Year
    if re.search(r"\b20\d{2}\b", title):
        score += 5
        factors.append(("Contains year (freshness)", +5))

    # Brackets/parentheses
    if re.search(r"[\[\(]", title):
        score += 5
        factors.append(("Has brackets (CTR boost)", +5))

    # Question format
    if title.endswith("?") or title_lower.startswith(
        ("how", "what", "why", "when", "where", "who")
    ):
        score += 5
        factors.append(("Question format", +5))

    # Keyword position (should be early)
    if keyword:
        kw_lower = keyword.lower()
        if kw_lower in title_lower:
            pos = title_lower.index(kw_lower)
            if pos == 0:
                score += 10
                factors.append(("Keyword at start", +10))
            elif pos < 20:
                score += 5
                factors.append(("Keyword near start", +5))
            else:
                score += 2
                factors.append(("Keyword present but late", +2))
        else:
            score -= 10
            factors.append(("Target keyword missing", -10))

    # Pipe/dash separator (common in well-optimized titles)
    if " | " in title or " - " in title or " — " in title:
        score += 2
        factors.append(("Brand separator present", +2))

    # All caps words (1-2 is OK for emphasis)
    caps = re.findall(r"\b[A-Z]{3,}\b", title)
    if len(caps) > 2:
        score -= 5
        factors.append(("Too many ALL CAPS words", -5))

    return min(max(score, 0), 100), factors


def suggest_improvements(title, keyword=None):
    suggestions = []
    title_lower = title.lower()
    words = re.findall(r"\b[a-z]+\b", title_lower)

    if len(title) > 60:
        suggestions.append(f"Shorten to <60 chars (currently {len(title)})")
    if len(title) < 40:
        suggestions.append("Add more descriptive words (currently too short)")
    if not re.search(r"\d+", title):
        suggestions.append("Add a number (e.g., '7 Best...', 'Top 10...')")
    if not (set(words) & POWER_WORDS):
        suggestions.append("Add a power word (best, ultimate, proven, guide)")
    if not re.search(r"\b20\d{2}\b", title):
        suggestions.append("Add current year for freshness")
    if not re.search(r"[\[\(]", title):
        suggestions.append("Add brackets, e.g., [2025 Guide] or (Free)")
    if keyword and keyword.lower() not in title_lower:
        suggestions.append(f"Include target keyword '{keyword}'")
    if keyword and keyword.lower() in title_lower:
        pos = title_lower.index(keyword.lower())
        if pos > 20:
            suggestions.append("Move keyword closer to the beginning")

    return suggestions


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Title Tag CTR Optimizer")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--titles", nargs="+", help="Title tags to score")
    parser.add_argument("--url", help="Fetch and score page title")
    parser.add_argument("--file", help="CSV with titles")
    parser.add_argument("--column", default="title", help="Column name")
    parser.add_argument("--keyword", help="Target keyword")
    parser.add_argument(
        "--suggest", action="store_true", help="Show improvement suggestions"
    )
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    titles = list(args.titles or [])
    if args.url:
        import requests
        from bs4 import BeautifulSoup

        session = requests.Session()
        url = _normalize_url(args.url)
        if not url:
            LOGGER.error("URL is empty")
            return 2
        try:
            resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
            resp.raise_for_status()
            soup = BeautifulSoup(resp.text, "html.parser")
            if soup.title:
                titles.append(soup.title.get_text(strip=True))
        except Exception as exc:
            LOGGER.error("Failed fetching %s: %s", url, exc)
            return 2
    if args.file:
        try:
            df = _read_table(args.file)
        except Exception as exc:
            LOGGER.error("Failed reading '%s': %s", args.file, exc)
            return 2
        if args.column not in df.columns:
            LOGGER.error("Column '%s' not found in %s", args.column, args.file)
            return 2
        titles.extend(df[args.column].dropna().astype(str).tolist())

    if not titles:
        LOGGER.error("Provide --titles, --url, or --file")
        return 2

    results = []
    sys.stdout.write("\n" + "=" * 70 + "\n")
    sys.stdout.write("  TITLE TAG CTR ANALYSIS\n")
    sys.stdout.write("=" * 70 + "\n")

    for title in titles:
        score, factors = score_title(title, args.keyword)
        grade = (
            "A" if score >= 80 else "B" if score >= 65 else "C" if score >= 50 else "D"
        )
        bar = "█" * (score // 5) + "░" * (20 - score // 5)

        sys.stdout.write(f"\n  [{grade}] {score}/100 {bar}\n")
        sys.stdout.write(f'  "{title}" ({len(title)} chars)\n')
        for desc, pts in factors:
            sys.stdout.write(f"    {'+' if pts > 0 else ''}{pts:>3}  {desc}\n")

        if args.suggest:
            suggestions = suggest_improvements(title, args.keyword)
            if suggestions:
                sys.stdout.write("  Suggestions:\n")
                for s in suggestions:
                    sys.stdout.write(f"    {s}\n")

        results.append(
            {"title": title, "score": score, "grade": grade, "length": len(title)}
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
