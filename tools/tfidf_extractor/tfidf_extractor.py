"""
TF-IDF Keyword Extractor
==========================
Extract top TF-IDF terms from one or multiple documents.
Useful for discovering what terms define a page's content.

Usage:
    python tfidf_extractor.py --url https://example.com/page --top 30
    python tfidf_extractor.py --files page1.html page2.html --output tfidf_results.xlsx
    python tfidf_extractor.py --file article.txt --ngram-range 1 3
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/tfidf_extractor/
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

LOGGER = logging.getLogger("tfidf_extractor")


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


def fetch_url(session, url: str) -> str:
    from bs4 import BeautifulSoup

    resp = session.get(
        url, headers={"User-Agent": "Mozilla/5.0 (compatible; SEO-Bot/1.0)"}, timeout=15
    )
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "html.parser")
    for tag in soup(["script", "style", "nav", "footer"]):
        tag.decompose()
    return re.sub(r"\s+", " ", soup.get_text(separator=" ", strip=True))


def load_text(path):
    with open(path, "r", encoding="utf-8", errors="replace") as f:
        content = f.read()
    if "<html" in content.lower():
        from bs4 import BeautifulSoup

        soup = BeautifulSoup(content, "html.parser")
        for tag in soup(["script", "style"]):
            tag.decompose()
        return re.sub(r"\s+", " ", soup.get_text(separator=" ", strip=True))
    return content


def extract_tfidf(texts, labels, ngram_min=1, ngram_max=3, top_n=30):
    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
    except Exception as exc:
        raise ImportError("Missing optional dependency 'scikit-learn'.") from exc

    vectorizer = TfidfVectorizer(
        ngram_range=(ngram_min, ngram_max),
        stop_words="english",
        max_features=2000,
        min_df=1,
    )
    matrix = vectorizer.fit_transform(texts)
    features = vectorizer.get_feature_names_out()

    results = []
    for i, label in enumerate(labels):
        row = matrix[i].toarray().flatten()
        top_idx = row.argsort()[-top_n:][::-1]
        for j in top_idx:
            if row[j] > 0:
                results.append(
                    {
                        "source": label,
                        "term": features[j],
                        "tfidf_score": round(row[j], 5),
                    }
                )
    return pd.DataFrame(results)


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="TF-IDF Keyword Extractor")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="Single URL to analyze")
    parser.add_argument("--urls", nargs="+", help="Multiple URLs")
    parser.add_argument("--file", help="Single file")
    parser.add_argument("--files", nargs="+", help="Multiple files")
    parser.add_argument("--top", type=int, default=30, help="Top N terms per document")
    parser.add_argument("--ngram-min", type=int, default=1)
    parser.add_argument("--ngram-max", type=int, default=3)
    parser.add_argument("--output", help="Save results as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    import requests

    session = requests.Session()

    texts, labels = [], []
    for u in (args.urls or []) + ([args.url] if args.url else []):
        url = _normalize_url(u)
        if not url:
            continue
        LOGGER.info("Fetching %s", url)
        try:
            texts.append(fetch_url(session, url))
            labels.append(url[:60])
        except Exception as exc:
            LOGGER.error("Failed fetching %s: %s", url, exc)
            return 2
    for f in (args.files or []) + ([args.file] if args.file else []):
        fp = (f or "").strip()
        if not fp:
            continue
        if not os.path.isfile(fp):
            LOGGER.error("File not found: %s", fp)
            return 2
        texts.append(load_text(fp))
        labels.append(fp)

    if not texts:
        LOGGER.error("Provide --url, --urls, --file, or --files")
        return 2

    try:
        df = extract_tfidf(texts, labels, args.ngram_min, args.ngram_max, args.top)
    except ImportError as exc:
        LOGGER.error(str(exc))
        return 2

    for label in labels:
        subset = df[df["source"] == label]
        sys.stdout.write(f"\n=== {label} (top {args.top}) ===\n")
        sys.stdout.write(subset[["term", "tfidf_score"]].to_string(index=False) + "\n")

    if args.output:
        try:
            written = _write_results(df, args.output)
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
