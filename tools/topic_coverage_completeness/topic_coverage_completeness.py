"""
Topic Coverage Completeness Checker
=======================================
Compare your content's subtopic coverage against an ideal topic map
built from top-ranking competitors.

Usage:
    python topic_coverage_completeness.py --my-url https://mysite.com/post --competitor-urls https://a.com https://b.com --keyword "crm software" --output coverage.xlsx
"""
# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/topic_coverage_completeness/
# ---------------------------------------------------------------------------

__version__ = "1.0.0"

import argparse, logging, re, sys, time
from collections.abc import Sequence
from typing import Optional
import pandas as pd, numpy as np

LOGGER = logging.getLogger("topic_coverage_completeness")


def configure_logging(level: str) -> None:
    """Set up root logger with the specified verbosity level."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def fetch_text(url: str) -> str:
    """Fetch and clean text content from a URL."""
    import requests; from bs4 import BeautifulSoup
    try:
        soup = BeautifulSoup(requests.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15).text, "html.parser")
        for t in soup(["script", "style", "nav", "footer"]): t.decompose()
        return re.sub(r"\s+", " ", soup.get_text(" ", strip=True))
    except Exception:
        return ""

def main(argv: Optional[Sequence[str]] = None) -> int:
    """Main entry point for topic coverage completeness checker."""
    parser = argparse.ArgumentParser(description="Topic Coverage Completeness")
    parser.add_argument("--my-url", required=True); parser.add_argument("--competitor-urls", nargs="+", required=True)
    parser.add_argument("--keyword"); parser.add_argument("--output")
    parser.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    parser.add_argument("--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"])
    args = parser.parse_args(argv)

    configure_logging(args.log_level)

    print("Fetching pages...")
    my_text = fetch_text(args.my_url)
    comp_texts = []
    for u in args.competitor_urls:
        t = fetch_text(u)
        if t: comp_texts.append(t)
        time.sleep(0.3)

    if not my_text or not comp_texts:
        LOGGER.error("Need your page + competitor pages")
        return 2

    from sklearn.feature_extraction.text import TfidfVectorizer
    from sklearn.decomposition import NMF

    all_texts = comp_texts
    vec = TfidfVectorizer(ngram_range=(1, 3), stop_words="english", max_features=500, min_df=1)
    matrix = vec.fit_transform(all_texts)
    features = vec.get_feature_names_out()

    n_topics = min(12, len(comp_texts) * 3)
    nmf = NMF(n_components=n_topics, random_state=42, max_iter=200)
    nmf.fit(matrix)

    # Get subtopics from competitors
    subtopics = []
    for idx, topic in enumerate(nmf.components_):
        top_terms = [features[i] for i in topic.argsort()[-5:][::-1]]
        subtopics.append({"id": idx, "label": top_terms[0], "terms": top_terms})

    # Check my content coverage
    my_lower = my_text.lower()
    results = []
    for st in subtopics:
        covered = any(t in my_lower for t in st["terms"])
        coverage_count = sum(1 for t in st["terms"] if t in my_lower)
        results.append({
            "subtopic": st["label"],
            "key_terms": ", ".join(st["terms"]),
            "covered": covered,
            "terms_found": coverage_count,
            "coverage_pct": round(coverage_count / len(st["terms"]) * 100, 0),
        })

    covered = sum(1 for r in results if r["covered"])
    total = len(results)
    score = round(covered / max(total, 1) * 100, 1)

    print(f"\n{'='*65}")
    print(f"  TOPIC COVERAGE: {score}% ({covered}/{total} subtopics)")
    print(f"{'='*65}")

    for r in results:
        icon = "\u2705" if r["covered"] else "\U0001f534"
        print(f"  {icon} {r['subtopic']:<25} [{r['coverage_pct']:.0f}%] terms: {r['key_terms'][:40]}")

    missing = [r for r in results if not r["covered"]]
    if missing:
        print(f"\n  Missing subtopics ({len(missing)}) — add content about:")
        for r in missing:
            print(f"    \u2192 {r['subtopic']}: include terms like {r['key_terms'][:50]}")

    if args.output:
        pd.DataFrame(results).to_excel(args.output, index=False)
        print(f"\nSaved to {args.output}")

    return 0


if __name__ == "__main__":
    sys.exit(main())
