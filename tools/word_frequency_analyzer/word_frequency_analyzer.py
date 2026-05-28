"""
Word Frequency & Keyword Density Analyzer
=============================================
Detailed word/phrase frequency analysis with keyword density calculations,
prominence scoring (title, headings, first paragraph), and distribution mapping.

Usage:
    python word_frequency_analyzer.py --url https://example.com/page --keywords "cloud hosting" "best vps" "server"
    python word_frequency_analyzer.py --file article.html --top 40 --output freq.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/word_frequency_analyzer/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"

import argparse
import logging
import os
import re
import sys
from collections import Counter
from collections.abc import Sequence
from typing import Optional

import pandas as pd

LOGGER = logging.getLogger("word_frequency_analyzer")


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


def _write_outputs(
    words: list[tuple[str, int]],
    bigrams: list[tuple[str, int]],
    trigrams: list[tuple[str, int]],
    densities: Optional[list[dict]],
    output_path: str,
) -> str:
    out = (output_path or "").strip()
    if not out:
        raise ValueError("Output path is empty")
    lower = out.lower()

    words_df = pd.DataFrame(words, columns=["word", "count"])
    bi_df = pd.DataFrame(bigrams, columns=["phrase", "count"])
    tri_df = pd.DataFrame(trigrams, columns=["phrase", "count"])
    dens_df = pd.DataFrame(densities or [])

    if lower.endswith(".csv"):
        combined = pd.concat(
            [
                words_df.assign(section="Words"),
                bi_df.assign(section="Bigrams"),
                tri_df.assign(section="Trigrams"),
                dens_df.assign(section="Keyword Density") if not dens_df.empty else pd.DataFrame(),
            ],
            ignore_index=True,
        )
        combined.to_csv(out, index=False, encoding="utf-8-sig")
        return out

    if not (lower.endswith(".xlsx") or lower.endswith(".xls")):
        out = out + ".xlsx"
    try:
        with pd.ExcelWriter(out, engine="openpyxl") as w:
            words_df.to_excel(w, sheet_name="Words", index=False)
            bi_df.to_excel(w, sheet_name="Bigrams", index=False)
            tri_df.to_excel(w, sheet_name="Trigrams", index=False)
            if not dens_df.empty:
                dens_df.to_excel(w, sheet_name="Keyword Density", index=False)
        return out
    except Exception:
        fallback = out.rsplit(".", 1)[0] + ".csv"
        combined = pd.concat(
            [
                words_df.assign(section="Words"),
                bi_df.assign(section="Bigrams"),
                tri_df.assign(section="Trigrams"),
                dens_df.assign(section="Keyword Density") if not dens_df.empty else pd.DataFrame(),
            ],
            ignore_index=True,
        )
        combined.to_csv(fallback, index=False, encoding="utf-8-sig")
        return fallback


def fetch_parts(session, url: str) -> dict:
    from bs4 import BeautifulSoup

    resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "html.parser")
    for t in soup(["script", "style"]):
        t.decompose()
    title = soup.title.get_text(strip=True) if soup.title else ""
    h1 = " ".join(h.get_text(strip=True) for h in soup.find_all("h1"))
    h2s = " ".join(h.get_text(strip=True) for h in soup.find_all("h2"))
    first_para = ""
    for p in soup.find_all("p"):
        t = p.get_text(strip=True)
        if len(t) > 30:
            first_para = t
            break
    for t in soup(["nav", "footer", "header", "aside"]):
        t.decompose()
    text = re.sub(r"\s+", " ", soup.get_text(" ", strip=True))
    return {
        "text": text,
        "title": title,
        "h1": h1,
        "h2s": h2s,
        "first_para": first_para,
    }


def analyze_frequency(text, top_n=40):
    words = re.findall(r"\b[a-z]+\b", text.lower())
    try:
        import nltk
        from nltk.corpus import stopwords

        stops = set(stopwords.words("english"))
    except Exception:
        stops = set(
            "the a an in on at to for of and or but is are was were be been has have had do does did".split()
        )
    filtered = [w for w in words if w not in stops and len(w) > 2]

    unigrams = Counter(filtered).most_common(top_n)
    bigrams = Counter(
        " ".join(filtered[i : i + 2]) for i in range(len(filtered) - 1)
    ).most_common(top_n)
    trigrams = Counter(
        " ".join(filtered[i : i + 3]) for i in range(len(filtered) - 2)
    ).most_common(top_n)
    return unigrams, bigrams, trigrams, len(words)


def keyword_density(text, keywords, total_words):
    text_lower = text.lower()
    results = []
    for kw in keywords:
        count = len(re.findall(re.escape(kw.lower()), text_lower))
        kw_words = len(kw.split())
        density = count * kw_words / max(total_words, 1) * 100
        results.append(
            {"keyword": kw, "count": count, "density_pct": round(density, 3)}
        )
    return results


def prominence_score(parts, keyword):
    kw = keyword.lower()
    score = 0
    if kw in parts["title"].lower():
        score += 30
    if kw in parts["h1"].lower():
        score += 25
    if kw in parts["h2s"].lower():
        score += 15
    if kw in parts["first_para"].lower():
        score += 15
    # Position in body
    pos = parts["text"].lower().find(kw)
    if pos >= 0:
        position_ratio = pos / max(len(parts["text"]), 1)
        score += max(0, 15 * (1 - position_ratio))
    return round(score, 1)


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(
        description="Word Frequency & Keyword Density Analyzer"
    )
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="URL to analyze")
    parser.add_argument("--file", help="HTML/text file")
    parser.add_argument("--keywords", nargs="+", help="Keywords to check density for")
    parser.add_argument("--top", type=int, default=30)
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    if args.url:
        import requests

        session = requests.Session()
        url = _normalize_url(args.url)
        if not url:
            LOGGER.error("URL is empty")
            return 2
        try:
            parts = fetch_parts(session, url)
        except Exception as exc:
            LOGGER.error("Failed fetching %s: %s", url, exc)
            return 2
    elif args.file:
        if not os.path.isfile(args.file):
            LOGGER.error("File not found: %s", args.file)
            return 2
        with open(args.file, "r", encoding="utf-8", errors="replace") as f:
            html = f.read()
        from bs4 import BeautifulSoup

        soup = BeautifulSoup(html, "html.parser")
        parts = {
            "text": re.sub(r"\s+", " ", soup.get_text(" ", strip=True)),
            "title": soup.title.get_text(strip=True) if soup.title else "",
            "h1": " ".join(h.get_text(strip=True) for h in soup.find_all("h1")),
            "h2s": " ".join(h.get_text(strip=True) for h in soup.find_all("h2")),
            "first_para": next(
                (
                    p.get_text(strip=True)
                    for p in soup.find_all("p")
                    if len(p.get_text(strip=True)) > 30
                ),
                "",
            ),
        }
    else:
        LOGGER.error("Provide --url or --file")
        return 2

    unigrams, bigrams, trigrams, total = analyze_frequency(parts["text"], args.top)

    sys.stdout.write("\n" + "=" * 60 + "\n")
    sys.stdout.write(f"  WORD FREQUENCY ANALYSIS ({total} total words)\n")
    sys.stdout.write("=" * 60 + "\n")

    sys.stdout.write("\n  Top Words:\n")
    for w, c in unigrams[:15]:
        sys.stdout.write(f"    {c:>5}  {w}\n")

    sys.stdout.write("\n  Top Phrases (2-word):\n")
    for w, c in bigrams[:10]:
        sys.stdout.write(f"    {c:>5}  {w}\n")

    sys.stdout.write("\n  Top Phrases (3-word):\n")
    for w, c in trigrams[:10]:
        sys.stdout.write(f"    {c:>5}  {w}\n")

    if args.keywords:
        densities = keyword_density(parts["text"], args.keywords, total)
        sys.stdout.write("\n  Keyword Density:\n")
        sys.stdout.write(
            f"  {'Keyword':<30} {'Count':>6} {'Density':>8} {'Prominence':>11}\n"
        )
        for d in densities:
            prom = prominence_score(parts, d["keyword"])
            status = "✅" if 0.5 <= d["density_pct"] <= 2.5 else "⚠️"
            sys.stdout.write(
                f"  {d['keyword']:<30} {d['count']:>6} {d['density_pct']:>7.3f}% {prom:>8}/100 {status}\n"
            )

    if args.output:
        try:
            written = _write_outputs(
                unigrams,
                bigrams,
                trigrams,
                densities if args.keywords else None,
                args.output,
            )
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
