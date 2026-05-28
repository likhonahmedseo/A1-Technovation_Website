"""
Topic Authority Scorer
========================
Scores a site's topical authority by analyzing content coverage depth,
entity diversity, internal linking, and semantic coherence across pages.

Usage:
    python topic_authority_scorer.py --urls https://site.com/p1 https://site.com/p2 https://site.com/p3 --topic "machine learning"
    python topic_authority_scorer.py --files page1.html page2.html --topic "cloud computing" --output authority.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/topic_authority_scorer/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"

import argparse
import logging
import os
import re
import time
from collections import Counter
from collections.abc import Sequence
from typing import Optional

import numpy as np
import pandas as pd
import sys

LOGGER = logging.getLogger("topic_authority_scorer")


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


def fetch_text(session, url: str) -> Optional[dict]:
    from bs4 import BeautifulSoup

    try:
        resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "html.parser")
        for t in soup(["script", "style", "nav", "footer"]):
            t.decompose()
        text = re.sub(r"\s+", " ", soup.get_text(" ", strip=True))
        headings = []
        for h in soup.find_all(re.compile(r"^h[1-6]$")):
            headings.append(h.get_text(strip=True))
        return {
            "text": text,
            "headings": headings,
            "word_count": len(re.findall(r"\b\w+\b", text)),
            "url": url,
        }
    except Exception:
        return None


def score_authority(pages, topic):
    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
    except Exception:
        TfidfVectorizer = None

    texts = [p["text"] for p in pages]
    total_words = sum(p["word_count"] for p in pages)

    # 1. Coverage breadth (TF-IDF unique terms)
    unique_terms = 0
    if TfidfVectorizer is not None:
        vec = TfidfVectorizer(
            ngram_range=(1, 2), stop_words="english", max_features=1000
        )
        matrix = vec.fit_transform(texts)
        avg = matrix.mean(axis=0).A1
        unique_terms = int((avg > 0.01).sum())

    # 2. Topic relevance
    topic_lower = topic.lower()
    topic_mentions = sum(p["text"].lower().count(topic_lower) for p in pages)
    heading_mentions = sum(
        1 for p in pages for h in p["headings"] if topic_lower in h.lower()
    )

    # 3. Entity diversity
    entity_count = 0
    try:
        import spacy

        nlp = spacy.load("en_core_web_sm")
        all_ents = set()
        for p in pages:
            doc = nlp(p["text"][:30000])
            all_ents.update(
                ent.text.strip() for ent in doc.ents if len(ent.text.strip()) > 1
            )
        entity_count = len(all_ents)
    except Exception:
        pat = re.compile(r"\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2}\b")
        ents: set[str] = set()
        for p in pages:
            for m in pat.findall(p["text"][:30000]):
                ents.add(m)
        entity_count = len(ents)

    # 4. Semantic coherence
    try:
        from sentence_transformers import SentenceTransformer
        from sklearn.metrics.pairwise import cosine_similarity

        model = SentenceTransformer("all-MiniLM-L6-v2")
        embs = model.encode([" ".join(t.split()[:256]) for t in texts])
        sims = cosine_similarity(embs)
        avg_coherence = (
            np.mean(sims[np.triu_indices_from(sims, k=1)]) if len(pages) > 1 else 1.0
        )
    except Exception:
        avg_coherence = 0.5

    # Score components (each 0-25, total 0-100)
    coverage_score = min(25, len(pages) * 2.5 + total_words / 2000)
    relevance_score = min(25, topic_mentions * 0.5 + heading_mentions * 3)
    diversity_score = min(25, entity_count * 0.3 + unique_terms * 0.05)
    coherence_score = avg_coherence * 25

    total = round(
        coverage_score + relevance_score + diversity_score + coherence_score, 1
    )

    return {
        "topic": topic,
        "pages_analyzed": len(pages),
        "total_words": total_words,
        "unique_tfidf_terms": unique_terms,
        "unique_entities": entity_count,
        "topic_mentions": topic_mentions,
        "heading_mentions": heading_mentions,
        "semantic_coherence": round(avg_coherence, 3),
        "score_coverage": round(coverage_score, 1),
        "score_relevance": round(relevance_score, 1),
        "score_diversity": round(diversity_score, 1),
        "score_coherence": round(coherence_score, 1),
        "total_score": total,
        "grade": (
            "A" if total >= 80 else "B" if total >= 60 else "C" if total >= 40 else "D"
        ),
    }


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Topic Authority Scorer")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--urls", nargs="+", help="Site page URLs")
    parser.add_argument("--files", nargs="+", help="HTML files")
    parser.add_argument("--topic", required=True, help="Topic to score authority for")
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

    pages = []
    for u in args.urls or []:
        url = _normalize_url(u)
        if not url:
            continue
        LOGGER.info("Fetching %s", url)
        p = fetch_text(session, url)
        if p:
            pages.append(p)
        time.sleep(0.5)
    for f in args.files or []:
        fp = (f or "").strip()
        if not fp:
            continue
        if not os.path.isfile(fp):
            LOGGER.warning("File not found: %s", fp)
            continue
        with open(fp, "r", encoding="utf-8", errors="replace") as fh:
            from bs4 import BeautifulSoup

            html = fh.read()
            soup = BeautifulSoup(html, "html.parser")
            for t in soup(["script", "style"]):
                t.decompose()
            text = re.sub(r"\s+", " ", soup.get_text(" ", strip=True))
            headings = [
                h.get_text(strip=True) for h in soup.find_all(re.compile(r"^h[1-6]$"))
            ]
            pages.append(
                {
                    "text": text,
                    "headings": headings,
                    "word_count": len(re.findall(r"\b\w+\b", text)),
                    "url": f,
                }
            )

    if not pages:
        LOGGER.error("No pages loaded")
        return 2

    result = score_authority(pages, args.topic)

    sys.stdout.write("\n" + "=" * 60 + "\n")
    sys.stdout.write(f"  TOPIC AUTHORITY: '{args.topic}'\n")
    sys.stdout.write("=" * 60 + "\n")
    sys.stdout.write(f"  Grade: {result['grade']} ({result['total_score']}/100)\n")
    sys.stdout.write(
        f"  Coverage:  {result['score_coverage']}/25  ({result['pages_analyzed']} pages, {result['total_words']} words)\n"
    )
    sys.stdout.write(
        f"  Relevance: {result['score_relevance']}/25  ({result['topic_mentions']} mentions, {result['heading_mentions']} in headings)\n"
    )
    sys.stdout.write(
        f"  Diversity: {result['score_diversity']}/25  ({result['unique_entities']} entities, {result['unique_tfidf_terms']} terms)\n"
    )
    sys.stdout.write(
        f"  Coherence: {result['score_coherence']}/25  (avg similarity: {result['semantic_coherence']})\n"
    )

    if args.output:
        try:
            written = _write_results(pd.DataFrame([result]), args.output)
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
