"""
Content Optimization & SEO Scoring
====================================
Analyzes content for SEO quality: TF-IDF keyword coverage, entity extraction,
readability scoring, and structural analysis.

Usage:
    python content_optimizer.py --url https://example.com/page --target-keyword "project management tools"
    python content_optimizer.py --file article.html --target-keyword "cloud hosting"
    python content_optimizer.py --text "Your content here..." --target-keyword "best crm software"
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/content_optimizer/
# ---------------------------------------------------------------------------

from __future__ import annotations

__version__ = "1.0.0"

import argparse
import logging
import re
import sys
from collections import Counter
from typing import Any, Dict, List, Optional, Sequence, Tuple

LOGGER = logging.getLogger("content_optimizer")


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def fetch_url(url: str, timeout: int = 15, user_agent: str = "Mozilla/5.0") -> str:
    """
    Fetch and parse HTML from a URL.

    Args:
        url: The URL to fetch.
        timeout: Timeout in seconds for the HTTP request.
        user_agent: User-Agent string.

    Returns:
        The raw HTML string.

    Raises:
        requests.exceptions.RequestException: If the HTTP request fails.
    """
    import requests

    headers = {"User-Agent": user_agent}
    resp = requests.get(url, headers=headers, timeout=timeout)
    resp.raise_for_status()
    return resp.text


def extract_text_and_structure(html: str) -> Dict[str, Any]:
    """
    Extract text, headings, links, and structure from HTML.

    Args:
        html: Raw HTML string.

    Returns:
        Dictionary containing extracted metadata and text.
    """
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(html, "html.parser")

    # Remove script/style
    for tag in soup(["script", "style", "nav", "footer", "header"]):
        tag.decompose()

    # Headings
    headings = {}
    for level in range(1, 7):
        tags = soup.find_all(f"h{level}")
        if tags:
            headings[f"h{level}"] = [t.get_text(strip=True) for t in tags]

    # Links
    links = {"internal": [], "external": []}
    for a in soup.find_all("a", href=True):
        href = a.get("href")
        if not href:
            continue
        if href.startswith("http"):
            links["external"].append(href)
        elif href.startswith("/") or href.startswith("#"):
            links["internal"].append(href)

    # Title & meta description
    title = soup.title.get_text(strip=True) if soup.title else ""
    meta_desc = ""
    meta_tag = soup.find("meta", attrs={"name": "description"})
    if meta_tag and meta_tag.get("content"):
        meta_desc = meta_tag["content"]

    # Body text
    text = soup.get_text(separator=" ", strip=True)
    text = re.sub(r"\s+", " ", text)

    return {
        "text": text,
        "title": title,
        "meta_description": meta_desc,
        "headings": headings,
        "links": links,
        "html": str(soup),
    }


def count_syllables(word: str) -> int:
    """
    Rough syllable count for English words.

    Args:
        word: A single word string.

    Returns:
        Estimated number of syllables.
    """
    word = word.lower().strip()
    if len(word) <= 2:
        return 1
    count = len(re.findall(r"[aeiouy]+", word))
    if word.endswith("e"):
        count -= 1
    return max(count, 1)


def readability_scores(text: str) -> Dict[str, float]:
    """
    Compute Flesch-Kincaid and Gunning Fog readability scores.

    Args:
        text: Extracted text to score.

    Returns:
        Dictionary of readability metrics.
    """
    sentences = re.split(r"[.!?]+", text)
    sentences = [s.strip() for s in sentences if len(s.strip()) > 3]
    words = re.findall(r"\b[a-zA-Z]+\b", text)

    if not sentences or not words:
        return {
            "flesch_kincaid_grade": 0.0,
            "gunning_fog": 0.0,
            "avg_sentence_length": 0.0,
            "total_words": 0,
            "total_sentences": 0,
        }

    num_sentences = len(sentences)
    num_words = len(words)
    num_syllables = sum(count_syllables(w) for w in words)
    complex_words = sum(1 for w in words if count_syllables(w) >= 3)

    asl = num_words / num_sentences
    asw = num_syllables / num_words

    fk_grade = 0.39 * asl + 11.8 * asw - 15.59
    gunning_fog = 0.4 * (asl + 100 * (complex_words / num_words))

    return {
        "flesch_kincaid_grade": round(fk_grade, 1),
        "gunning_fog": round(gunning_fog, 1),
        "avg_sentence_length": round(asl, 1),
        "total_words": num_words,
        "total_sentences": num_sentences,
    }


def tfidf_keyword_analysis(
    text: str, target_keyword: str, top_n: int = 30
) -> Dict[str, Any]:
    """
    Analyze keyword coverage using TF-IDF.

    Args:
        text: The text to analyze.
        target_keyword: The target keyword.
        top_n: Number of top terms to return.

    Returns:
        Dictionary of TF-IDF analysis results.
    """
    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
    except ImportError:
        LOGGER.warning("Missing dependency scikit-learn. Skipping TF-IDF analysis.")
        return {
            "top_tfidf_terms": [],
            "target_keyword_frequency": 0,
            "keyword_density_pct": 0.0,
        }

    # Treat paragraphs as documents
    paragraphs = re.split(r"\n{2,}|\. {2,}", text)
    paragraphs = [p.strip() for p in paragraphs if len(p.strip()) > 20]
    if not paragraphs:
        paragraphs = [text]

    vectorizer = TfidfVectorizer(
        ngram_range=(1, 3), stop_words="english", max_features=500
    )

    try:
        tfidf_matrix = vectorizer.fit_transform(paragraphs)
        feature_names = vectorizer.get_feature_names_out()
        avg_scores = tfidf_matrix.mean(axis=0).A1
        term_scores = sorted(zip(feature_names, avg_scores), key=lambda x: -x[1])[
            :top_n
        ]
    except ValueError:
        term_scores = []

    # Check target keyword presence
    target_lower = target_keyword.lower()
    target_words = target_lower.split()
    text_lower = text.lower()

    keyword_freq = text_lower.count(target_lower)
    word_count = len(re.findall(r"\b\w+\b", text))
    keyword_density = (
        (keyword_freq * len(target_words)) / word_count * 100 if word_count else 0
    )

    return {
        "top_tfidf_terms": term_scores,
        "target_keyword_frequency": keyword_freq,
        "keyword_density_pct": round(keyword_density, 2),
    }


def extract_entities(text: str) -> List[Dict[str, Any]]:
    """
    Extract named entities using spaCy.

    Args:
        text: The text to analyze.

    Returns:
        A list of dictionaries representing the extracted entities.
    """
    import spacy

    try:
        nlp = spacy.load("en_core_web_sm")
    except OSError:
        import subprocess

        subprocess.run(
            [sys.executable, "-m", "spacy", "download", "en_core_web_sm"], check=True
        )
        nlp = spacy.load("en_core_web_sm")

    # Process in chunks if text is long
    max_len = 100000
    doc = nlp(text[:max_len])

    entities = []
    for ent in doc.ents:
        entities.append({"text": ent.text, "label": ent.label_})

    entity_counts = Counter((e["text"], e["label"]) for e in entities)
    entity_summary = [
        {"entity": ent_text, "type": label, "count": count}
        for (ent_text, label), count in entity_counts.most_common(50)
    ]
    return entity_summary


def compute_seo_score(
    structure: Dict[str, Any],
    readability: Dict[str, float],
    tfidf_data: Dict[str, Any],
    entities: List[Dict[str, Any]],
    target_keyword: str,
) -> Tuple[int, List[Tuple[str, int, int]]]:
    """
    Compute a weighted SEO score (0-100).

    Args:
        structure: Dictionary of structure metrics.
        readability: Dictionary of readability metrics.
        tfidf_data: Dictionary of TF-IDF keyword analysis.
        entities: List of extracted entities.
        target_keyword: The target keyword.

    Returns:
        A tuple containing the final score and a list of scoring details.
    """
    score = 0
    details = []

    # Title contains keyword (15 pts)
    if target_keyword.lower() in structure["title"].lower():
        score += 15
        details.append(("Title contains keyword", 15, 15))
    else:
        details.append(("Title contains keyword", 0, 15))

    # Meta description contains keyword (10 pts)
    if target_keyword.lower() in structure["meta_description"].lower():
        score += 10
        details.append(("Meta description contains keyword", 10, 10))
    else:
        details.append(("Meta description contains keyword", 0, 10))

    # H1 contains keyword (10 pts)
    h1s = structure["headings"].get("h1", [])
    if any(target_keyword.lower() in h.lower() for h in h1s):
        score += 10
        details.append(("H1 contains keyword", 10, 10))
    else:
        details.append(("H1 contains keyword", 0, 10))

    # Keyword density 0.5-2.5% (10 pts)
    density = tfidf_data["keyword_density_pct"]
    if 0.5 <= density <= 2.5:
        score += 10
        details.append((f"Keyword density {density}% (ideal 0.5-2.5%)", 10, 10))
    elif density > 0:
        score += 5
        details.append((f"Keyword density {density}% (ideal 0.5-2.5%)", 5, 10))
    else:
        details.append((f"Keyword density {density}% (ideal 0.5-2.5%)", 0, 10))

    # Word count (15 pts) — 1000+ ideal
    wc = readability["total_words"]
    if wc >= 1500:
        score += 15
        details.append((f"Word count: {wc} (1500+ ideal)", 15, 15))
    elif wc >= 800:
        score += 10
        details.append((f"Word count: {wc} (1500+ ideal)", 10, 15))
    elif wc >= 300:
        score += 5
        details.append((f"Word count: {wc} (1500+ ideal)", 5, 15))
    else:
        details.append((f"Word count: {wc} (1500+ ideal)", 0, 15))

    # Readability (10 pts) — FK grade 6-12 ideal
    fk = readability["flesch_kincaid_grade"]
    if 6 <= fk <= 12:
        score += 10
        details.append((f"Readability FK grade: {fk} (6-12 ideal)", 10, 10))
    elif 4 <= fk <= 14:
        score += 5
        details.append((f"Readability FK grade: {fk} (6-12 ideal)", 5, 10))
    else:
        details.append((f"Readability FK grade: {fk} (6-12 ideal)", 0, 10))

    # Heading structure (10 pts)
    has_h2 = len(structure["headings"].get("h2", [])) >= 2
    has_h3 = len(structure["headings"].get("h3", [])) >= 1
    heading_pts = (5 if has_h2 else 0) + (5 if has_h3 else 0)
    score += heading_pts
    details.append(
        (
            f"Heading structure (H2s: {len(structure['headings'].get('h2', []))}, H3s: {len(structure['headings'].get('h3', []))})",
            heading_pts,
            10,
        )
    )

    # Internal links (10 pts)
    int_links = len(structure["links"]["internal"])
    if int_links >= 3:
        score += 10
        details.append((f"Internal links: {int_links}", 10, 10))
    elif int_links >= 1:
        score += 5
        details.append((f"Internal links: {int_links}", 5, 10))
    else:
        details.append((f"Internal links: {int_links}", 0, 10))

    # Entity richness (10 pts)
    unique_entities = len(set(e["entity"] for e in entities))
    if unique_entities >= 10:
        score += 10
        details.append((f"Unique entities: {unique_entities}", 10, 10))
    elif unique_entities >= 5:
        score += 5
        details.append((f"Unique entities: {unique_entities}", 5, 10))
    else:
        details.append((f"Unique entities: {unique_entities}", 0, 10))

    return score, details


def print_report(
    structure: Dict[str, Any],
    readability: Dict[str, float],
    tfidf_data: Dict[str, Any],
    entities: List[Dict[str, Any]],
    score: int,
    score_details: List[Tuple[str, int, int]],
    target_keyword: str,
) -> None:
    """Print the SEO analysis report."""
    LOGGER.info(f"\n{'=' * 60}")
    LOGGER.info(f"  SEO CONTENT ANALYSIS: '{target_keyword}'")
    LOGGER.info("=" * 60)

    LOGGER.info(f"\n{'OVERALL SCORE':>20}: {score}/100")
    LOGGER.info("-" * 60)
    for desc, pts, max_pts in score_details:
        bar = "█" * pts + "░" * (max_pts - pts)
        LOGGER.info(f"  {desc:<45} {bar} {pts}/{max_pts}")

    LOGGER.info(f"\n--- Structure ---")
    LOGGER.info(f"  Title: {structure['title'][:80]}")
    LOGGER.info(f"  Meta desc: {structure['meta_description'][:100]}")
    for level, texts in structure["headings"].items():
        LOGGER.info(f"  {level.upper()}: {len(texts)} found")
    LOGGER.info(f"  Internal links: {len(structure['links']['internal'])}")
    LOGGER.info(f"  External links: {len(structure['links']['external'])}")

    LOGGER.info(f"\n--- Readability ---")
    for k, v in readability.items():
        LOGGER.info(f"  {k}: {v}")

    LOGGER.info(f"\n--- Keyword Analysis ---")
    LOGGER.info(f"  Target: '{target_keyword}'")
    LOGGER.info(f"  Frequency: {tfidf_data['target_keyword_frequency']}")
    LOGGER.info(f"  Density: {tfidf_data['keyword_density_pct']}%")
    LOGGER.info(f"\n  Top TF-IDF terms:")
    for term, score_val in tfidf_data["top_tfidf_terms"][:15]:
        LOGGER.info(f"    {term:<30} {score_val:.4f}")

    LOGGER.info(f"\n--- Entities (top 15) ---")
    for e in entities[:15]:
        LOGGER.info(f"  {e['entity']:<30} {e['type']:<12} (x{e['count']})")

    LOGGER.info("=" * 60)


def main(argv: Optional[Sequence[str]] = None) -> int:
    """
    Main CLI entry point for the Content Optimizer.

    Args:
        argv: Optional sequence of command-line arguments.

    Returns:
        Integer exit code (0 for success, 1 or 2 for errors).
    """
    parser = argparse.ArgumentParser(description="SEO Content Optimizer & Scorer")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="URL to analyze")
    parser.add_argument("--file", help="Local HTML/text file to analyze")
    parser.add_argument("--text", help="Raw text to analyze")
    parser.add_argument(
        "--target-keyword", required=True, help="Primary target keyword"
    )
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR"],
        help="Set logging level",
    )
    args = parser.parse_args(argv)

    configure_logging(args.log_level)

    # Load content
    html = ""
    try:
        if args.url:
            html = fetch_url(args.url)
        elif args.file:
            with open(args.file, "r", encoding="utf-8") as f:
                html = f.read()
        elif args.text:
            html = f"<html><body><p>{args.text}</p></body></html>"
        else:
            LOGGER.error("Provide --url, --file, or --text")
            return 1
    except Exception as e:
        LOGGER.error(f"Failed to load content: {e}")
        return 1

    try:
        structure = extract_text_and_structure(html)
        readability = readability_scores(structure["text"])
        tfidf_data = tfidf_keyword_analysis(structure["text"], args.target_keyword)
        entities = extract_entities(structure["text"])
        score, score_details = compute_seo_score(
            structure, readability, tfidf_data, entities, args.target_keyword
        )

        print_report(
            structure,
            readability,
            tfidf_data,
            entities,
            score,
            score_details,
            args.target_keyword,
        )
    except Exception as e:
        LOGGER.error(f"Failed to analyze content: {e}")
        return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())