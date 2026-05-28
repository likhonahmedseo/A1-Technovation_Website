"""
Content Uniqueness Scorer
============================
Compares rewritten/spinned content against source to score originality
using embedding similarity, n-gram overlap, and structural comparison.

Usage:
    python content_uniqueness_scorer.py --source original.txt --rewrite new.txt
    python content_uniqueness_scorer.py --source-url https://source.com --rewrite-url https://new.com
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/content_uniqueness_scorer/
# ---------------------------------------------------------------------------

from __future__ import annotations

__version__ = "1.0.0"

import argparse
import logging
import re
import sys
from typing import Any, Dict, List, Optional, Sequence, Tuple

import numpy as np
import pandas as pd

LOGGER = logging.getLogger("content_uniqueness_scorer")


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def load_text(path_or_url: str) -> str:
    """
    Load text from a URL or local file.

    Args:
        path_or_url: URL starting with http/https, or a local file path.

    Returns:
        Cleaned text string.
        
    Raises:
        Exception: If fetching or reading fails.
    """
    if path_or_url.startswith("http"):
        import requests
        from bs4 import BeautifulSoup

        r = requests.get(path_or_url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
        r.raise_for_status()
        s = BeautifulSoup(r.text, "html.parser")
        for t in s(["script", "style", "nav", "footer"]):
            t.decompose()
        return re.sub(r"\s+", " ", s.get_text(" ", strip=True))
        
    with open(path_or_url, "r", encoding="utf-8") as f:
        return f.read()


def ngram_overlap(text_a: str, text_b: str, n: int = 3) -> float:
    """
    Compute n-gram Jaccard overlap between two texts.

    Args:
        text_a: First text string.
        text_b: Second text string.
        n: N-gram size.

    Returns:
        Jaccard overlap ratio (0.0 to 1.0).
    """
    wa = re.findall(r"\b[a-z]+\b", text_a.lower())
    wb = re.findall(r"\b[a-z]+\b", text_b.lower())
    
    ga = set(" ".join(wa[i : i + n]) for i in range(len(wa) - n + 1))
    gb = set(" ".join(wb[i : i + n]) for i in range(len(wb) - n + 1))
    
    if not ga or not gb:
        return 0.0
    return len(ga & gb) / len(ga | gb)


def sentence_similarity(text_a: str, text_b: str) -> Tuple[float, List[Dict[str, Any]], List[Dict[str, Any]]]:
    """
    Compute sentence-level semantic similarity using embeddings.

    Args:
        text_a: First text string.
        text_b: Second text string.

    Returns:
        Tuple containing:
        - Average similarity score
        - List of all matches
        - List of near-identical (copied) matches
    """
    try:
        from sentence_transformers import SentenceTransformer
        from sklearn.metrics.pairwise import cosine_similarity
    except ImportError:
        LOGGER.warning("Missing dependencies for semantic similarity (sentence-transformers, scikit-learn). Skipping.")
        return 0.0, [], []

    m = SentenceTransformer("all-MiniLM-L6-v2")
    sa = [
        s.strip() for s in re.split(r"(?<=[.!?])\s+", text_a) if len(s.strip()) > 20
    ][:50]
    sb = [
        s.strip() for s in re.split(r"(?<=[.!?])\s+", text_b) if len(s.strip()) > 20
    ][:50]
    
    if not sa or not sb:
        return 0.0, [], []
        
    ea = m.encode(sa)
    eb = m.encode(sb)
    sim = cosine_similarity(ea, eb)
    
    # For each source sentence, find best match
    matches = []
    for i in range(len(sa)):
        best_j = int(np.argmax(sim[i]))
        matches.append(
            {
                "source": sa[i][:80],
                "rewrite": sb[best_j][:80],
                "similarity": round(float(sim[i][best_j]), 3),
            }
        )
        
    avg = float(np.mean([m["similarity"] for m in matches]))
    copied = [m for m in matches if m["similarity"] > 0.92]
    
    return avg, matches, copied


def main(argv: Optional[Sequence[str]] = None) -> int:
    """
    Main CLI entry point for the Content Uniqueness Scorer.

    Args:
        argv: Optional sequence of command-line arguments.

    Returns:
        Integer exit code (0 for success, 1 or 2 for errors).
    """
    p = argparse.ArgumentParser(description="Content Uniqueness Scorer")
    p.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    p.add_argument("--source", help="Source text (file path)")
    p.add_argument("--rewrite", help="Rewritten text (file path)")
    p.add_argument("--source-url", help="Source text (URL)")
    p.add_argument("--rewrite-url", help="Rewritten text (URL)")
    p.add_argument("--output", help="Save detailed sentence matches to XLSX/CSV")
    p.add_argument(
        "--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"], help="Set logging level"
    )
    a = p.parse_args(argv)

    configure_logging(a.log_level)

    src_path = a.source_url or a.source
    rw_path = a.rewrite_url or a.rewrite

    if not src_path or not rw_path:
        LOGGER.error("Provide both a source (--source/--source-url) and a rewrite (--rewrite/--rewrite-url).")
        return 1

    try:
        LOGGER.info("Loading source content...")
        src = load_text(src_path)
    except Exception as e:
        LOGGER.error(f"Failed to load source from {src_path}: {e}")
        return 1

    try:
        LOGGER.info("Loading rewritten content...")
        rw = load_text(rw_path)
    except Exception as e:
        LOGGER.error(f"Failed to load rewrite from {rw_path}: {e}")
        return 1

    # N-gram overlaps
    LOGGER.info("Computing N-gram overlaps...")
    tri = ngram_overlap(src, rw, 3)
    quad = ngram_overlap(src, rw, 4)
    penta = ngram_overlap(src, rw, 5)
    
    # Embedding similarity
    LOGGER.info("Computing sentence-level similarity...")
    avg_sim, matches, copied = sentence_similarity(src, rw)
    
    # Uniqueness score (0=identical, 100=completely unique)
    uniqueness = max(0, 100 - int(tri * 120) - int(avg_sim * 50 if avg_sim else 0))
    verdict = (
        "HIGHLY UNIQUE"
        if uniqueness >= 75
        else (
            "MODERATELY UNIQUE"
            if uniqueness >= 50
            else "LOW UNIQUENESS" if uniqueness >= 25 else "NEAR DUPLICATE"
        )
    )
    
    LOGGER.info(f"\n{'='*60}")
    LOGGER.info(f"  CONTENT UNIQUENESS ANALYSIS")
    LOGGER.info(f"{'='*60}")
    LOGGER.info(f"  Uniqueness: {uniqueness}/100 — {verdict}")
    LOGGER.info(f"\n  N-gram Overlap:")
    LOGGER.info(f"    Trigram:   {tri:.3f} {'⚠️' if tri>0.3 else '✅'}")
    LOGGER.info(f"    4-gram:    {quad:.3f} {'⚠️' if quad>0.2 else '✅'}")
    LOGGER.info(f"    5-gram:    {penta:.3f} {'⚠️' if penta>0.15 else '✅'}")
    
    if avg_sim:
        LOGGER.info(
            f"  Avg sentence similarity: {avg_sim:.3f} {'⚠️' if avg_sim>0.8 else '✅'}"
        )
        
    if copied:
        LOGGER.info(f"\n  Near-identical sentences ({len(copied)}):")
        for c in copied[:5]:
            LOGGER.info(f"    [{c['similarity']}] \"{c['source']}\"")
            
    if a.output and matches:
        try:
            df = pd.DataFrame(matches)
            if a.output.lower().endswith(".csv"):
                df.to_csv(a.output, index=False)
            else:
                df.to_excel(a.output, index=False)
            LOGGER.info(f"\nSaved detailed matches to {a.output}")
        except Exception as e:
            LOGGER.error(f"Failed to write output to {a.output}: {e}")
            return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
