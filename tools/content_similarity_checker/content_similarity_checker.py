"""
Content Similarity Checker
=============================
Detects duplicate or near-duplicate content across pages using
MinHash/LSH for fast similarity and cosine similarity for precision.

Usage:
    python content_similarity_checker.py --urls https://a.com https://b.com https://c.com --threshold 0.7
    python content_similarity_checker.py --files *.html --output duplicates.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/content_similarity_checker/
# ---------------------------------------------------------------------------

from __future__ import annotations

__version__ = "1.0.0"

import argparse
import hashlib
import logging
import re
import sys
from typing import Any, Dict, List, Optional, Sequence, Set

import numpy as np
import pandas as pd

LOGGER = logging.getLogger("content_similarity_checker")


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def fetch_text(url: str, timeout: int = 15, user_agent: str = "Mozilla/5.0") -> str:
    """
    Fetch HTML from a URL and extract text.

    Args:
        url: The URL to fetch.
        timeout: Request timeout in seconds.
        user_agent: User-Agent string.

    Returns:
        Cleaned text extracted from the URL.

    Raises:
        requests.exceptions.RequestException: If the HTTP request fails.
    """
    import requests
    from bs4 import BeautifulSoup

    resp = requests.get(url, headers={"User-Agent": user_agent}, timeout=timeout)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "html.parser")
    for t in soup(["script", "style", "nav", "footer"]):
        t.decompose()
    return re.sub(r"\s+", " ", soup.get_text(" ", strip=True))


def shingle(text: str, k: int = 5) -> Set[str]:
    """
    Create k-shingles from text.

    Args:
        text: Input string.
        k: Shingle length (in words).

    Returns:
        A set of string shingles.
    """
    words = text.lower().split()
    return set(" ".join(words[i : i + k]) for i in range(len(words) - k + 1))


def minhash_signature(shingles: Set[str], num_hashes: int = 128) -> List[int]:
    """
    Generate a MinHash signature for a set of shingles.

    Args:
        shingles: Set of shingles.
        num_hashes: Number of hash functions to use.

    Returns:
        List of integer hash values.
    """
    sig = []
    for i in range(num_hashes):
        min_hash = float("inf")
        for s in shingles:
            # Using hashlib for stable cross-run hashing, unlike built-in hash()
            h = int(hashlib.md5(f"{i}:{s}".encode()).hexdigest(), 16)
            if h < min_hash:
                min_hash = h
        sig.append(int(min_hash) if min_hash != float("inf") else 0)
    return sig


def minhash_similarity(sig_a: List[int], sig_b: List[int]) -> float:
    """
    Compute Jaccard similarity estimate from MinHash signatures.

    Args:
        sig_a: First signature.
        sig_b: Second signature.

    Returns:
        Estimated Jaccard similarity (0.0 to 1.0).
    """
    if not sig_a or not sig_b:
        return 0.0
    return sum(1 for a, b in zip(sig_a, sig_b) if a == b) / len(sig_a)


def main(argv: Optional[Sequence[str]] = None) -> int:
    """
    Main CLI entry point for the Content Similarity Checker.

    Args:
        argv: Optional sequence of command-line arguments.

    Returns:
        Integer exit code (0 for success, 1 or 2 for errors).
    """
    parser = argparse.ArgumentParser(description="Content Similarity Checker")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--urls", nargs="+", help="URLs to check")
    parser.add_argument("--files", nargs="+", help="Files to check")
    parser.add_argument("--threshold", type=float, default=0.7, help="Similarity threshold (0.0 - 1.0)")
    parser.add_argument(
        "--method", choices=["minhash", "cosine", "both"], default="both", help="Similarity method"
    )
    parser.add_argument("--output", help="Save report as XLSX/CSV")
    parser.add_argument(
        "--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"], help="Set logging level"
    )
    args = parser.parse_args(argv)

    configure_logging(args.log_level)

    texts, labels = [], []
    
    for u in args.urls or []:
        LOGGER.info(f"Fetching {u}...")
        try:
            texts.append(fetch_text(u))
            labels.append(u[:60])
        except Exception as e:
            LOGGER.warning(f"Failed to fetch {u}: {e}")

    for f in args.files or []:
        try:
            with open(f, "r", encoding="utf-8") as fh:
                content = fh.read()
                if "<html" in content.lower():
                    from bs4 import BeautifulSoup

                    soup = BeautifulSoup(content, "html.parser")
                    for t in soup(["script", "style"]):
                        t.decompose()
                    texts.append(re.sub(r"\s+", " ", soup.get_text(" ", strip=True)))
                else:
                    texts.append(content)
            labels.append(f)
        except Exception as e:
            LOGGER.warning(f"Failed to read file {f}: {e}")

    if len(texts) < 2:
        LOGGER.error("Need at least 2 pages/files to compare.")
        return 1

    duplicates = []

    if args.method in ("minhash", "both"):
        LOGGER.info("Computing MinHash signatures...")
        shingles_list = [shingle(t) for t in texts]
        sigs = [minhash_signature(s) for s in shingles_list]
        for i in range(len(texts)):
            for j in range(i + 1, len(texts)):
                sim = minhash_similarity(sigs[i], sigs[j])
                if sim >= args.threshold:
                    duplicates.append(
                        {
                            "page_a": labels[i],
                            "page_b": labels[j],
                            "minhash_sim": round(sim, 3),
                            "method": "minhash",
                        }
                    )

    if args.method in ("cosine", "both"):
        LOGGER.info("Computing Cosine Similarity...")
        try:
            from sklearn.feature_extraction.text import TfidfVectorizer
            from sklearn.metrics.pairwise import cosine_similarity

            vec = TfidfVectorizer(stop_words="english", max_features=2000)
            matrix = vec.fit_transform(texts)
            cos_sim = cosine_similarity(matrix)
            for i in range(len(texts)):
                for j in range(i + 1, len(texts)):
                    if cos_sim[i][j] >= args.threshold:
                        existing = [
                            d
                            for d in duplicates
                            if d["page_a"] == labels[i] and d["page_b"] == labels[j]
                        ]
                        if existing:
                            existing[0]["cosine_sim"] = round(float(cos_sim[i][j]), 3)
                        else:
                            duplicates.append(
                                {
                                    "page_a": labels[i],
                                    "page_b": labels[j],
                                    "cosine_sim": round(float(cos_sim[i][j]), 3),
                                    "method": "cosine",
                                }
                            )
        except ImportError:
            LOGGER.warning("Missing scikit-learn dependency for cosine similarity.")

    LOGGER.info(f"\n{'='*60}")
    LOGGER.info(f"  CONTENT SIMILARITY CHECK (threshold: {args.threshold})")
    LOGGER.info(f"{'='*60}")
    LOGGER.info(f"  Pages analyzed: {len(texts)}")
    LOGGER.info(f"  Duplicate pairs found: {len(duplicates)}")

    for d in sorted(
        duplicates, key=lambda x: -(x.get("cosine_sim", 0) + x.get("minhash_sim", 0))
    ):
        sim = d.get("cosine_sim", d.get("minhash_sim", 0))
        LOGGER.info(f"\n  🔴 Similarity: {sim:.3f}")
        LOGGER.info(f"    {d['page_a']}")
        LOGGER.info(f"    {d['page_b']}")

    if not duplicates:
        LOGGER.info("\n  ✅ No duplicates found at this threshold.")

    if args.output:
        try:
            df = pd.DataFrame(duplicates)
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
