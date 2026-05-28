"""
SEO-Aware Text Summarizer
============================
Generate summaries optimized for featured snippets and meta descriptions.
Keeps target keywords, maintains factual accuracy, hits ideal length.

Usage:
    python text_summarizer_seo.py --url https://example.com/post --keyword "cloud hosting" --type snippet
    python text_summarizer_seo.py --file article.html --keyword "best crm" --type meta --output summary.txt
"""
# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/text_summarizer_seo/
# ---------------------------------------------------------------------------

__version__ = "1.0.0"

import argparse, logging, re, sys
from collections.abc import Sequence
from typing import Optional
import pandas as pd

LOGGER = logging.getLogger("text_summarizer_seo")


def configure_logging(level: str) -> None:
    """Set up root logger with the specified verbosity level."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def extractive_summary(text: str, keyword: Optional[str] = None, target_length: int = 50, summary_type: str = "snippet") -> str:
    """Extract best sentences for SEO summary."""
    sentences = [s.strip() for s in re.split(r"(?<=[.!?])\s+", text) if len(s.strip()) > 20]
    if not sentences: return ""

    kw_lower = keyword.lower() if keyword else ""

    scored = []
    for i, sent in enumerate(sentences):
        score = 0
        # Keyword presence
        if kw_lower and kw_lower in sent.lower(): score += 30
        # Position bonus (earlier = better)
        score += max(0, 20 - i * 2)
        # Length preference based on type
        wc = len(sent.split())
        if summary_type == "snippet":
            if 30 <= wc <= 50: score += 20  # Ideal snippet length
        elif summary_type == "meta":
            if 15 <= wc <= 25: score += 20  # Ideal meta desc length
        else:  # paragraph
            if 40 <= wc <= 80: score += 15
        # Contains data/numbers
        if re.search(r"\d+", sent): score += 10
        # Definition pattern (good for snippets)
        if re.search(r"\b(is a|refers to|is defined as|means|is the)\b", sent.lower()): score += 15
        # Contains list signals
        if re.search(r"\b(include|such as|for example|like)\b", sent.lower()): score += 5

        scored.append((score, sent))

    scored.sort(key=lambda x: -x[0])

    # Build summary to target length
    summary_sents = []
    word_count = 0
    for score, sent in scored:
        wc = len(sent.split())
        if word_count + wc <= target_length * 1.3:
            summary_sents.append(sent)
            word_count += wc
        if word_count >= target_length: break

    return " ".join(summary_sents)

def main(argv: Optional[Sequence[str]] = None) -> int:
    """Main entry point for text summarizer."""
    parser = argparse.ArgumentParser(description="SEO-Aware Text Summarizer")
    parser.add_argument("--url"); parser.add_argument("--file"); parser.add_argument("--text")
    parser.add_argument("--keyword", help="Target keyword to preserve")
    parser.add_argument("--type", choices=["snippet", "meta", "paragraph", "abstract"], default="snippet")
    parser.add_argument("--output")
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    parser.add_argument("--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"])
    args = parser.parse_args(argv)

    configure_logging(args.log_level)

    if args.url:
        import requests; from bs4 import BeautifulSoup
        soup = BeautifulSoup(requests.get(args.url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15).text, "html.parser")
        for t in soup(["script", "style", "nav", "footer"]): t.decompose()
        text = soup.get_text(" ", strip=True)
    elif args.file: text = open(args.file).read()
    elif args.text: text = args.text
    else:
        LOGGER.error("Provide --url, --file, or --text")
        return 2

    targets = {"snippet": 45, "meta": 25, "paragraph": 80, "abstract": 150}
    target_len = targets[args.type]
    summary = extractive_summary(text, args.keyword, target_len, args.type)

    wc = len(summary.split())
    has_kw = args.keyword and args.keyword.lower() in summary.lower()

    print(f"\n{'='*65}")
    print(f"  SEO SUMMARY ({args.type.upper()}, {wc} words)")
    print(f"{'='*65}")
    print(f"\n  {summary}")
    print(f"\n  Words: {wc} | Chars: {len(summary)}")
    kw_icon = "\u2705 Yes" if has_kw else "\u274c No"
    print(f"  Keyword present: {kw_icon}")

    # Length check
    if args.type == "meta":
        if len(summary) <= 160: print(f"  Meta length: \u2705 {len(summary)}/160 chars")
        else: print(f"  Meta length: \u26a0\ufe0f {len(summary)}/160 chars (too long)")
    elif args.type == "snippet":
        if 200 <= len(summary) <= 350: print(f"  Snippet length: \u2705 ideal for featured snippet")
        else: print(f"  Snippet length: \u26a0\ufe0f adjust to 200-350 chars")

    if args.output:
        with open(args.output, "w") as f: f.write(summary)
        print(f"\nSaved to {args.output}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
