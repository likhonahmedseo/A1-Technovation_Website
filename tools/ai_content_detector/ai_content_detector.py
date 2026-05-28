"""
AI Content Detector
=====================
Detects AI-generated text using statistical signals such as burstiness (sentence
length variance), vocabulary richness, repetition patterns, and stylistic markers.

Usage:
    python ai_content_detector.py --url https://example.com/post
    python ai_content_detector.py --file article.txt --output ai_detection.xlsx
    python ai_content_detector.py --text "Your content here..."
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/ai_content_detector/
# ---------------------------------------------------------------------------


from __future__ import annotations

__version__ = "1.0.0"

import argparse
import logging
import math
import re
import sys
from collections import Counter
from dataclasses import dataclass
from pathlib import Path
from typing import Optional, Sequence, TypedDict

import pandas as pd

LOGGER = logging.getLogger("ai_content_detector")

WHITESPACE_RE = re.compile(r"[ \t\f\v]+")
MULTI_NEWLINE_RE = re.compile(r"\n{3,}")
SENTENCE_SPLIT_RE = re.compile(r"(?<=[.!?])\s+")
WORD_RE = re.compile(r"\b[a-z]+\b", re.IGNORECASE)
WORD_ANY_RE = re.compile(r"\b\w+\b")

TRANSITION_WORDS = frozenset(
    {
        "however",
        "furthermore",
        "moreover",
        "additionally",
        "consequently",
        "nevertheless",
        "therefore",
        "subsequently",
        "specifically",
        "essentially",
        "certainly",
        "undoubtedly",
        "notably",
        "importantly",
        "significantly",
        "delve",
        "tapestry",
        "landscape",
        "leverage",
        "streamline",
        "robust",
    }
)

STRIP_TAGS = ("script", "style", "nav", "footer", "header")


class AIContentResult(TypedDict):
    word_count: int
    sentence_count: int
    burstiness: float
    cttr: float
    bigram_repetition: float
    starter_diversity: float
    para_uniformity: float
    transition_density_per_1k: float
    repeated_trigrams: int
    ai_score: int
    verdict: str
    factors: list[str]


@dataclass(frozen=True)
class FetchConfig:
    timeout: int = 15
    user_agent: str = "Mozilla/5.0"


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI-style output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def _normalize_text_preserve_paragraphs(text: str) -> str:
    """Normalize whitespace while preserving paragraph breaks as double newlines."""
    text = text.replace("\r\n", "\n").replace("\r", "\n")
    lines = [WHITESPACE_RE.sub(" ", line).strip() for line in text.split("\n")]
    normalized = "\n".join(lines)
    normalized = MULTI_NEWLINE_RE.sub("\n\n", normalized)
    return normalized.strip()


def _extract_text_from_html(html: str) -> str:
    """Extract visible text from HTML while removing common boilerplate tags."""
    from bs4 import BeautifulSoup

    soup = BeautifulSoup(html, "html.parser")
    for tag in soup(STRIP_TAGS):
        tag.decompose()
    return _normalize_text_preserve_paragraphs(soup.get_text("\n\n", strip=True))


def fetch_text(url: str, *, config: FetchConfig = FetchConfig()) -> str:
    """Fetch a URL and return cleaned visible text suitable for analysis."""
    from urllib.parse import urlparse

    parsed = urlparse(url)
    if parsed.scheme not in {"http", "https"}:
        raise ValueError("URL must start with http:// or https://")

    import requests

    session = requests.Session()
    resp = session.get(
        url, headers={"User-Agent": config.user_agent}, timeout=config.timeout
    )
    resp.raise_for_status()
    return _extract_text_from_html(resp.text)


def _sentences(text: str) -> list[str]:
    """Split normalized text into sentences using punctuation boundaries."""
    return [s.strip() for s in SENTENCE_SPLIT_RE.split(text) if len(s.strip()) > 5]


def _words(text: str) -> list[str]:
    """Tokenize normalized text into lowercase word tokens (a-z only)."""
    return [w.lower() for w in WORD_RE.findall(text)]


def _burstiness(sentences: Sequence[str]) -> float:
    """Compute burstiness as normalized sentence-length standard deviation."""
    sent_lengths = [len(WORD_ANY_RE.findall(s)) for s in sentences]
    mean_len = sum(sent_lengths) / len(sent_lengths)
    variance = sum((l - mean_len) ** 2 for l in sent_lengths) / len(sent_lengths)
    return math.sqrt(variance) / max(mean_len, 1.0)


def _cttr(words: Sequence[str]) -> float:
    """Compute corrected type-token ratio (CTTR)."""
    unique_words = len(set(words))
    return unique_words / math.sqrt(2.0 * len(words))


def _ngram_repetition(words: Sequence[str]) -> tuple[float, int]:
    """Compute bigram repetition ratio and number of repeated trigram types."""
    if len(words) < 3:
        return 0.0, 0

    bigram_counts = Counter(" ".join(pair) for pair in zip(words, words[1:]))
    repeated_bigrams = sum(1 for c in bigram_counts.values() if c > 2)
    bigram_rep_ratio = repeated_bigrams / max(len(bigram_counts), 1)

    trigram_counts = Counter(" ".join(tri) for tri in zip(words, words[1:], words[2:]))
    repeated_trigrams = sum(1 for c in trigram_counts.values() if c > 1)
    return bigram_rep_ratio, repeated_trigrams


def _starter_diversity(sentences: Sequence[str]) -> float:
    """Compute diversity of first-word sentence starters."""
    starters = [(s.split()[0].lower() if s.split() else "") for s in sentences]
    return len(set(starters)) / max(len(starters), 1)


def _paragraph_uniformity(text: str) -> float:
    """Estimate how uniform paragraph lengths are (higher means more uniform)."""
    paragraphs = [p.strip() for p in re.split(r"\n{2,}", text) if len(p.strip()) > 20]
    if len(paragraphs) <= 2:
        return 0.5

    para_lengths = [len(p.split()) for p in paragraphs]
    mean_len = sum(para_lengths) / len(para_lengths)
    variance = sum((l - mean_len) ** 2 for l in para_lengths) / len(para_lengths)
    para_std = math.sqrt(variance)
    return 1.0 - min(para_std / max(mean_len, 1.0), 1.0)


def _transition_density(words: Sequence[str]) -> float:
    """Compute density (per 1k words) of transition/AI-typical tokens."""
    transition_count = sum(1 for w in words if w in TRANSITION_WORDS)
    return transition_count / max(len(words), 1) * 1000.0


def analyze_text(text: str) -> Optional[AIContentResult]:
    """Analyze text and return a heuristic AI-likelihood report, or None if too short."""
    if not isinstance(text, str):
        raise TypeError("text must be a string")

    text = _normalize_text_preserve_paragraphs(text)
    sentences = _sentences(text)
    words = _words(text)
    if len(sentences) < 5 or len(words) < 50:
        return None

    burstiness = _burstiness(sentences)
    cttr = _cttr(words)
    bigram_rep_ratio, repeated_trigrams = _ngram_repetition(words)
    starter_unique = _starter_diversity(sentences)
    para_uniformity = _paragraph_uniformity(text)
    transition_density = _transition_density(words)

    ai_score = 0
    factors: list[str] = []

    if burstiness < 0.35:
        ai_score += 20
        factors.append(f"Low burstiness ({burstiness:.2f}) — uniform sentence lengths")
    elif burstiness < 0.5:
        ai_score += 10
        factors.append(f"Moderate burstiness ({burstiness:.2f})")

    if cttr < 6.5:
        ai_score += 15
        factors.append(f"Low vocabulary richness (CTTR={cttr:.1f})")

    if bigram_rep_ratio > 0.05:
        ai_score += 15
        factors.append(f"High phrase repetition ({bigram_rep_ratio:.3f})")

    if starter_unique < 0.5:
        ai_score += 10
        factors.append(f"Low sentence starter diversity ({starter_unique:.2f})")

    if para_uniformity > 0.7:
        ai_score += 10
        factors.append(f"Very uniform paragraph lengths ({para_uniformity:.2f})")

    if transition_density > 8:
        ai_score += 20
        factors.append(f"High AI-typical word density ({transition_density:.1f}/1000)")
    elif transition_density > 4:
        ai_score += 10
        factors.append(f"Elevated AI-typical words ({transition_density:.1f}/1000)")

    if repeated_trigrams > len(sentences) * 0.1:
        ai_score += 10
        factors.append(f"{repeated_trigrams} repeated trigrams")

    verdict = (
        "LIKELY AI"
        if ai_score >= 60
        else "POSSIBLY AI" if ai_score >= 35 else "LIKELY HUMAN"
    )

    return {
        "word_count": len(words),
        "sentence_count": len(sentences),
        "burstiness": round(burstiness, 3),
        "cttr": round(cttr, 2),
        "bigram_repetition": round(bigram_rep_ratio, 4),
        "starter_diversity": round(starter_unique, 3),
        "para_uniformity": round(para_uniformity, 3),
        "transition_density_per_1k": round(transition_density, 1),
        "repeated_trigrams": repeated_trigrams,
        "ai_score": min(int(ai_score), 100),
        "verdict": verdict,
        "factors": factors,
    }


def _read_file_text(path: Path) -> str:
    """Read a text file using UTF-8 with replacement for invalid bytes."""
    return path.read_text(encoding="utf-8", errors="replace")


def _load_text_from_args(
    url: Optional[str],
    file_path: Optional[str],
    raw_text: Optional[str],
    fetch: FetchConfig,
) -> str:
    """Load input text from URL, file, or raw text, matching the CLI options."""
    if url:
        return fetch_text(url, config=fetch)
    if file_path:
        path = Path(file_path)
        if not path.exists() or not path.is_file():
            raise FileNotFoundError(f"File not found: {file_path}")
        text = _read_file_text(path)
        if "<html" in text.lower():
            return _extract_text_from_html(text)
        return _normalize_text_preserve_paragraphs(text)
    if raw_text:
        return _normalize_text_preserve_paragraphs(raw_text)
    raise ValueError("Provide --url, --file, or --text")


def _format_report(result: AIContentResult) -> list[str]:
    """Format the analysis result into a list of human-readable report lines."""
    icon = {"LIKELY AI": "🤖", "POSSIBLY AI": "🤔", "LIKELY HUMAN": "✍️"}.get(
        result["verdict"], ""
    )
    lines = [
        "",
        "=" * 60,
        "  AI CONTENT DETECTION",
        "=" * 60,
        f"  {icon} Verdict: {result['verdict']} (score: {result['ai_score']}/100)",
        f"  Words: {result['word_count']} | Sentences: {result['sentence_count']}",
        "",
        "  Signals:",
        f"    Burstiness (sentence variance): {result['burstiness']} {'⚠️ low' if result['burstiness'] < 0.4 else '✅'}",
        f"    Vocabulary richness (CTTR):      {result['cttr']} {'⚠️ low' if result['cttr'] < 6.5 else '✅'}",
        f"    Phrase repetition:               {result['bigram_repetition']} {'⚠️ high' if result['bigram_repetition'] > 0.05 else '✅'}",
        f"    Sentence starter diversity:      {result['starter_diversity']} {'⚠️ low' if result['starter_diversity'] < 0.5 else '✅'}",
        f"    Paragraph uniformity:            {result['para_uniformity']} {'⚠️ high' if result['para_uniformity'] > 0.7 else '✅'}",
        f"    AI-typical word density:          {result['transition_density_per_1k']}/1000w {'⚠️ high' if result['transition_density_per_1k'] > 8 else '✅'}",
    ]
    if result["factors"]:
        lines.append("")
        lines.append("  Key Findings:")
        lines.extend([f"    • {f}" for f in result["factors"]])
    lines.extend(["", "  Note: Statistical detection is indicative, not definitive."])
    return lines


def main(argv: Optional[Sequence[str]] = None) -> int:
    """CLI entrypoint. Returns a process exit code (0=success)."""
    parser = argparse.ArgumentParser(description="AI Content Detector")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="URL to analyze")
    parser.add_argument("--file", help="Text/HTML file")
    parser.add_argument("--text", help="Raw text")
    parser.add_argument("--output", help="Save as XLSX/CSV")
    parser.add_argument("--timeout", type=int, default=15)
    parser.add_argument("--user-agent", default="Mozilla/5.0")
    parser.add_argument(
        "--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"]
    )
    args = parser.parse_args(argv)

    configure_logging(args.log_level)
    fetch = FetchConfig(timeout=args.timeout, user_agent=args.user_agent)

    try:
        text = _load_text_from_args(args.url, args.file, args.text, fetch)
    except Exception as e:
        LOGGER.error(str(e))
        return 1

    result = analyze_text(text)
    if not result:
        LOGGER.error("Not enough content to analyze (need 50+ words, 5+ sentences)")
        return 1

    for line in _format_report(result):
        LOGGER.info(line)

    if args.output:
        df = pd.DataFrame([result])
        try:
            if args.output.lower().endswith(".csv"):
                df.to_csv(args.output, index=False)
            else:
                df.to_excel(args.output, index=False)
            LOGGER.info(f"Saved to {args.output}")
        except Exception as e:
            LOGGER.error(f"Failed to write output: {e}")
            return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
