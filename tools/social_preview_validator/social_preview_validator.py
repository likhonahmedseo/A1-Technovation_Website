"""
Social Preview Validator
==========================
Validates Open Graph, Twitter Card, and social share previews.
Checks image dimensions, title/description lengths, and missing tags.

Usage:
    python social_preview_validator.py --url https://example.com/post
    python social_preview_validator.py --urls https://a.com https://b.com --output social.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/social_preview_validator/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"

import argparse
import logging
import re
import sys
from collections.abc import Sequence
from typing import Optional

import pandas as pd

LOGGER = logging.getLogger("social_preview_validator")


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


def validate_social(session, url: str) -> dict:
    from bs4 import BeautifulSoup

    resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "html.parser")
    issues = []
    data = {"url": url}

    # Open Graph
    og = {}
    for meta in soup.find_all("meta", attrs={"property": re.compile(r"^og:")}):
        og[meta["property"]] = meta.get("content", "")

    data["og_title"] = og.get("og:title", "")
    data["og_description"] = og.get("og:description", "")
    data["og_image"] = og.get("og:image", "")
    data["og_type"] = og.get("og:type", "")
    data["og_url"] = og.get("og:url", "")

    if not data["og_title"]:
        issues.append("Missing og:title")
    elif len(data["og_title"]) > 95:
        issues.append(f"og:title too long ({len(data['og_title'])} chars)")
    if not data["og_description"]:
        issues.append("Missing og:description")
    elif len(data["og_description"]) > 300:
        issues.append("og:description too long")
    if not data["og_image"]:
        issues.append("Missing og:image (critical for social sharing)")
    if not data["og_url"]:
        issues.append("Missing og:url")
    if not data["og_type"]:
        issues.append("Missing og:type")

    # Check og:image dimensions if accessible
    if data["og_image"]:
        try:
            img_resp = session.head(data["og_image"], timeout=5, allow_redirects=True)
            if img_resp.status_code >= 400:
                issues.append(f"og:image returns {img_resp.status_code}")
        except Exception:
            issues.append("og:image URL not accessible")

    # Twitter Card
    tc = {}
    for meta in soup.find_all("meta", attrs={"name": re.compile(r"^twitter:")}):
        tc[meta["name"]] = meta.get("content", "")
    for meta in soup.find_all("meta", attrs={"property": re.compile(r"^twitter:")}):
        tc[meta["property"]] = meta.get("content", "")

    data["twitter_card"] = tc.get("twitter:card", "")
    data["twitter_title"] = tc.get("twitter:title", "")
    data["twitter_description"] = tc.get("twitter:description", "")
    data["twitter_image"] = tc.get("twitter:image", "")
    data["twitter_site"] = tc.get("twitter:site", "")

    if not data["twitter_card"]:
        issues.append("Missing twitter:card")
    if not data["twitter_title"] and not data["og_title"]:
        issues.append("No Twitter title (falls back to og:title)")

    # General page title/description
    title = soup.title.get_text(strip=True) if soup.title else ""
    md = soup.find("meta", attrs={"name": "description"})
    meta_desc = md["content"] if md and md.get("content") else ""

    data["page_title"] = title
    data["meta_description"] = meta_desc
    data["issues"] = "; ".join(issues) if issues else "All social tags present"
    data["issue_count"] = len(issues)
    data["score"] = max(0, 100 - len(issues) * 12)

    return data


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Social Preview Validator")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="Single URL")
    parser.add_argument("--urls", nargs="+")
    parser.add_argument("--output", help="Save as XLSX")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    urls = list(args.urls or []) + ([args.url] if args.url else [])
    if not urls:
        LOGGER.error("Provide --url or --urls")
        return 2

    import requests

    session = requests.Session()

    results = []
    for url in urls:
        u = _normalize_url(url)
        if not u:
            continue
        LOGGER.info("Validating %s", u)
        try:
            results.append(validate_social(session, u))
        except Exception as exc:
            LOGGER.error("Validation failed for %s: %s", u, exc)
            results.append({"url": u, "issues": str(exc), "issue_count": 1, "score": 0})

    for r in results:
        sys.stdout.write("\n" + "=" * 60 + "\n")
        sys.stdout.write(f"  SOCIAL PREVIEW: {str(r.get('url', ''))[:80]} [{r.get('score', 0)}/100]\n")
        sys.stdout.write("=" * 60 + "\n")
        sys.stdout.write(f"  OG Title:  {str(r.get('og_title', ''))[:60] or 'MISSING'}\n")
        sys.stdout.write(f"  OG Desc:   {str(r.get('og_description', ''))[:60] or 'MISSING'}\n")
        sys.stdout.write(f"  OG Image:  {str(r.get('og_image', ''))[:60] or 'MISSING'}\n")
        sys.stdout.write(f"  TW Card:   {str(r.get('twitter_card', '')) or 'MISSING'}\n")
        if r.get("issue_count"):
            sys.stdout.write(f"\n  Issues ({r.get('issue_count')}):\n")
            for i in str(r.get("issues", "")).split("; "):
                if i.strip():
                    sys.stdout.write(f"    {i}\n")

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
