"""
URL & Slug SEO Analyzer
==========================
Analyzes URL structure, slug quality, depth, length, keyword usage,
and parameter issues across a set of URLs.

Usage:
    python url_slug_analyzer.py --urls https://site.com/page1 https://site.com/long/nested/page
    python url_slug_analyzer.py --sitemap https://site.com/sitemap.xml --output url_audit.xlsx
    python url_slug_analyzer.py --file urls.txt --keyword "cloud hosting"
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/url_slug_analyzer/
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
from urllib.parse import urlparse, parse_qs

LOGGER = logging.getLogger("url_slug_analyzer")


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


def load_urls_from_file(path):
    with open(path, "r", encoding="utf-8", errors="replace") as f:
        return [
            _normalize_url(line.strip())
            for line in f
            if line.strip() and _normalize_url(line.strip())
        ]


def load_sitemap(sitemap_url):
    from bs4 import BeautifulSoup

    import requests

    session = requests.Session()
    resp = session.get(
        sitemap_url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15
    )
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "xml")
    return [_normalize_url(loc.text.strip()) for loc in soup.find_all("loc") if _normalize_url(loc.text.strip())]


def analyze_url(url, keyword=None):
    parsed = urlparse(url)
    path = parsed.path.rstrip("/")
    slug = path.split("/")[-1] if path else ""
    segments = [s for s in path.split("/") if s]
    params = parse_qs(parsed.query)

    issues = []
    # Length
    if len(url) > 100:
        issues.append(f"URL too long ({len(url)} chars)")
    if len(slug) > 60:
        issues.append(f"slug too long ({len(slug)} chars)")
    # Depth
    depth = len(segments)
    if depth > 4:
        issues.append(f"deep nesting ({depth} levels)")
    # Underscores
    if "_" in slug:
        issues.append("underscores in slug (use hyphens)")
    # Uppercase
    if slug != slug.lower():
        issues.append("uppercase chars in slug")
    # Numbers only
    if slug and slug.isdigit():
        issues.append("numeric-only slug")
    # Parameters
    if params:
        issues.append(f"{len(params)} query parameters")
    # Stop words in slug
    stops = {
        "the",
        "a",
        "an",
        "in",
        "on",
        "at",
        "to",
        "for",
        "of",
        "and",
        "or",
        "but",
        "is",
        "are",
        "with",
    }
    slug_words = slug.replace("-", " ").replace("_", " ").split()
    stop_in_slug = [w for w in slug_words if w.lower() in stops]
    if stop_in_slug:
        issues.append(f"stop words in slug: {', '.join(stop_in_slug)}")
    # File extension
    if re.search(r"\.(php|asp|aspx|jsp|cgi)$", slug.lower()):
        issues.append("dynamic file extension visible")
    # Keyword in URL
    kw_in_url = False
    if keyword:
        kw_in_url = (
            keyword.lower().replace(" ", "-") in path.lower()
            or keyword.lower().replace(" ", "") in path.lower()
        )
        if not kw_in_url:
            issues.append(f"keyword '{keyword}' not in URL")

    return {
        "url": url,
        "slug": slug,
        "path": path,
        "depth": depth,
        "url_length": len(url),
        "slug_length": len(slug),
        "has_params": bool(params),
        "param_count": len(params),
        "keyword_in_url": kw_in_url if keyword else "N/A",
        "issues": "; ".join(issues) if issues else "OK",
        "issue_count": len(issues),
        "score": max(0, 100 - len(issues) * 15),
    }


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="URL & Slug SEO Analyzer")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--urls", nargs="+")
    parser.add_argument("--file", help="Text file with URLs (one per line)")
    parser.add_argument("--sitemap", help="Sitemap XML URL")
    parser.add_argument("--keyword", help="Check keyword in URLs")
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    urls = [_normalize_url(u) for u in (args.urls or []) if _normalize_url(u)]
    if args.file:
        if not os.path.isfile(args.file):
            LOGGER.error("File not found: %s", args.file)
            return 2
        urls.extend(load_urls_from_file(args.file))
    if args.sitemap:
        sm = _normalize_url(args.sitemap)
        if not sm:
            LOGGER.error("Sitemap URL is empty")
            return 2
        LOGGER.info("Loading sitemap: %s", sm)
        try:
            urls.extend(load_sitemap(sm))
        except Exception as exc:
            LOGGER.error("Failed loading sitemap %s: %s", sm, exc)
            return 2

    if not urls:
        LOGGER.error("Provide --urls, --file, or --sitemap")
        return 2

    results = [analyze_url(u, args.keyword) for u in urls]

    total = len(results)
    issues_count = sum(1 for r in results if r["issue_count"] > 0)
    avg_depth = sum(r["depth"] for r in results) / total
    avg_length = sum(r["url_length"] for r in results) / total

    sys.stdout.write("\n" + "=" * 60 + "\n")
    sys.stdout.write(f"  URL & SLUG SEO AUDIT ({total} URLs)\n")
    sys.stdout.write("=" * 60 + "\n")
    sys.stdout.write(
        f"  URLs with issues: {issues_count}/{total} ({issues_count/total*100:.0f}%)\n"
    )
    sys.stdout.write(f"  Avg URL depth: {avg_depth:.1f}\n")
    sys.stdout.write(f"  Avg URL length: {avg_length:.0f} chars\n")

    if args.keyword:
        kw_count = sum(1 for r in results if r["keyword_in_url"] == True)
        sys.stdout.write(f"  Keyword in URL: {kw_count}/{total}\n")

    # Depth distribution
    depths = pd.Series([r["depth"] for r in results])
    sys.stdout.write("\n  Depth Distribution:\n")
    for d, c in depths.value_counts().sort_index().items():
        sys.stdout.write(f"    Depth {d}: {'█' * int(c)} {int(c)}\n")

    # Problem URLs
    problems = sorted(
        [r for r in results if r["issue_count"] > 0], key=lambda x: -x["issue_count"]
    )
    if problems:
        sys.stdout.write("\n  Problematic URLs (top 15):\n")
        for r in problems[:15]:
            sys.stdout.write(f"    [{r['score']}/100] {r['url'][:60]}\n")
            sys.stdout.write(f"             {r['issues']}\n")

    if args.output:
        try:
            written = _write_results(pd.DataFrame(results), args.output)
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
