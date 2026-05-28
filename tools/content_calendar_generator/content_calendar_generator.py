"""
Content Calendar Generator
=============================
Generates a content calendar from keyword/topic data with publishing dates,
content types, priorities, and cluster assignments.

Usage:
    python content_calendar_generator.py --input keywords.csv --weeks 12 --posts-per-week 3
    python content_calendar_generator.py --topics "SEO basics" "Link building" "Technical SEO" --weeks 8 --output calendar.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/content_calendar_generator/
# ---------------------------------------------------------------------------

from __future__ import annotations

__version__ = "1.0.0"

import argparse
import logging
import re
import sys
from datetime import datetime, timedelta
from typing import Any, Dict, List, Optional, Sequence

import pandas as pd

LOGGER = logging.getLogger("content_calendar_generator")

CONTENT_TYPES: Dict[str, List[str]] = {
    "informational": [
        "Ultimate Guide",
        "How-To Tutorial",
        "Explainer Post",
        "Listicle",
        "FAQ Roundup",
    ],
    "commercial": [
        "Comparison Post",
        "Best-Of Roundup",
        "Product Review",
        "Buyer's Guide",
        "Case Study",
    ],
    "transactional": [
        "Landing Page",
        "Product Page",
        "Pricing Guide",
        "Free Tool Page",
    ],
    "navigational": ["Pillar Page", "Resource Hub", "Glossary"],
}


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def classify_intent(keyword: str) -> str:
    """
    Classify the search intent of a keyword.

    Args:
        keyword: The keyword to classify.

    Returns:
        A string representing the intent (transactional, commercial, informational).
    """
    kw = keyword.lower()
    if re.search(r"\b(buy|price|coupon|deal|discount|order|shop)\b", kw):
        return "transactional"
    if re.search(r"\b(best|top|review|vs|compare|alternative)\b", kw):
        return "commercial"
    if re.search(r"\b(how|what|why|guide|tutorial|tips|learn)\b", kw):
        return "informational"
    return "informational"


def prioritize(keywords: List[str]) -> List[Dict[str, str]]:
    """
    Assign priority to keywords based on length and intent.

    Args:
        keywords: A list of keyword strings.

    Returns:
        A list of dictionaries with keyword, intent, and priority, sorted by priority.
    """
    scored = []
    for kw in keywords:
        intent = classify_intent(kw)
        word_count = len(kw.split())
        # Longer tail = easier to rank = higher initial priority
        priority = "high" if word_count >= 4 else "medium" if word_count >= 2 else "low"
        scored.append({"keyword": kw, "intent": intent, "priority": priority})
        
    # Sort: high first, then commercial > informational
    order = {"high": 0, "medium": 1, "low": 2}
    intent_order = {
        "commercial": 0,
        "transactional": 1,
        "informational": 2,
        "navigational": 3,
    }
    scored.sort(key=lambda x: (order[x["priority"]], intent_order[x["intent"]]))
    return scored


def generate_calendar(
    keywords_data: List[Dict[str, str]], start_date: datetime, weeks: int, posts_per_week: int
) -> List[Dict[str, Any]]:
    """
    Generate a content calendar based on prioritized keywords.

    Args:
        keywords_data: List of dictionaries with keyword, intent, and priority.
        start_date: The start date for the calendar.
        weeks: Number of weeks to schedule.
        posts_per_week: Number of posts to schedule per week.

    Returns:
        A list of dictionaries representing the scheduled content calendar.
    """
    calendar = []
    current_date = start_date
    kw_index = 0

    if not keywords_data:
        return calendar

    for week in range(1, weeks + 1):
        for post in range(posts_per_week):
            if kw_index >= len(keywords_data):
                kw_index = 0  # Cycle if needed

            kw = keywords_data[kw_index]
            intent = kw["intent"]
            content_types = CONTENT_TYPES.get(intent, CONTENT_TYPES["informational"])
            content_type = content_types[kw_index % len(content_types)]

            # Spread posts across the week (Mon, Wed, Fri or similar)
            day_offsets = (
                [0, 2, 4] if posts_per_week == 3 else list(range(posts_per_week))
            )
            offset = day_offsets[post % len(day_offsets)]
            pub_date = current_date + timedelta(days=offset)

            title_templates = {
                "Ultimate Guide": f"The Ultimate Guide to {kw['keyword'].title()}",
                "How-To Tutorial": f"How to {kw['keyword'].title()}: Step-by-Step Guide",
                "Listicle": f"10 Best {kw['keyword'].title()} Tips for 2025",
                "Comparison Post": f"{kw['keyword'].title()}: Complete Comparison Guide",
                "Best-Of Roundup": f"Best {kw['keyword'].title()} in 2025 (Expert Picks)",
                "Product Review": f"{kw['keyword'].title()} Review: Pros, Cons & Verdict",
            }
            suggested_title = title_templates.get(
                content_type, f"{content_type}: {kw['keyword'].title()}"
            )

            calendar.append(
                {
                    "week": week,
                    "publish_date": pub_date.strftime("%Y-%m-%d"),
                    "day": pub_date.strftime("%A"),
                    "keyword": kw["keyword"],
                    "intent": intent,
                    "content_type": content_type,
                    "priority": kw["priority"],
                    "suggested_title": suggested_title,
                    "status": "planned",
                    "target_word_count": (
                        2000
                        if content_type in ("Ultimate Guide", "Buyer's Guide")
                        else 1500 if "Guide" in content_type else 1000
                    ),
                }
            )
            kw_index += 1

        current_date += timedelta(weeks=1)

    return calendar


def main(argv: Optional[Sequence[str]] = None) -> int:
    """
    Main CLI entry point for the Content Calendar Generator.

    Args:
        argv: Optional sequence of command-line arguments.

    Returns:
        Integer exit code (0 for success, 1 or 2 for errors).
    """
    parser = argparse.ArgumentParser(description="Content Calendar Generator")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--input", help="CSV/XLSX with keywords")
    parser.add_argument("--column", help="Keyword column name")
    parser.add_argument("--topics", nargs="+", help="Topics/keywords manually")
    parser.add_argument(
        "--weeks", type=int, default=12, help="Calendar duration in weeks"
    )
    parser.add_argument("--posts-per-week", type=int, default=3)
    parser.add_argument(
        "--start-date", help="Start date YYYY-MM-DD (default: next Monday)"
    )
    parser.add_argument("--output", help="Save as XLSX/CSV")
    parser.add_argument(
        "--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"], help="Set logging level"
    )
    args = parser.parse_args(argv)

    configure_logging(args.log_level)

    keywords = []
    if args.input:
        try:
            df = (
                pd.read_csv(args.input)
                if args.input.endswith(".csv")
                else pd.read_excel(args.input)
            )
            col = args.column or df.columns[0]
            keywords = df[col].dropna().astype(str).tolist()
        except Exception as e:
            LOGGER.error(f"Failed to read input file {args.input}: {e}")
            return 1
            
    if args.topics:
        keywords.extend(args.topics)
        
    if not keywords:
        LOGGER.error("Provide --input or --topics")
        return 1

    # Start date
    if args.start_date:
        try:
            start = datetime.strptime(args.start_date, "%Y-%m-%d")
        except ValueError as e:
            LOGGER.error(f"Invalid start date format. Use YYYY-MM-DD. Error: {e}")
            return 1
    else:
        today = datetime.now()
        start = today + timedelta(days=(7 - today.weekday()))  # Next Monday

    kw_data = prioritize(keywords)
    calendar = generate_calendar(kw_data, start, args.weeks, args.posts_per_week)

    LOGGER.info(f"\n{'='*70}")
    LOGGER.info(f"  CONTENT CALENDAR ({args.weeks} weeks, {args.posts_per_week} posts/week)")
    LOGGER.info(f"  Start: {start.strftime('%Y-%m-%d')}  |  Total posts: {len(calendar)}")
    LOGGER.info(f"{'='*70}")

    current_week = 0
    for entry in calendar:
        if entry["week"] != current_week:
            current_week = entry["week"]
            LOGGER.info(f"\n  --- Week {current_week} ---")
        LOGGER.info(
            f"  {entry['publish_date']} ({entry['day'][:3]})  [{entry['priority'].upper()}] [{entry['content_type']}]"
        )
        LOGGER.info(f"    {entry['suggested_title'][:65]}")

    if args.output:
        try:
            df = pd.DataFrame(calendar)
            if args.output.lower().endswith(".csv"):
                df.to_csv(args.output, index=False)
            else:
                df.to_excel(args.output, index=False)
            LOGGER.info(f"\nSaved to {args.output}")
        except Exception as e:
            LOGGER.error(f"Failed to write output to {args.output}: {e}")
            return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
