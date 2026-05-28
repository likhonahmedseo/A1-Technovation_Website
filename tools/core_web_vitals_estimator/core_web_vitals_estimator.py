"""
Core Web Vitals Estimator
============================
Estimates CWV signals from HTML analysis: LCP candidates, CLS risk factors,
render-blocking resources, and FID/INP risk from JavaScript weight.

Usage:
    python core_web_vitals_estimator.py --url https://example.com
    python core_web_vitals_estimator.py --urls https://a.com https://b.com --output cwv.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/core_web_vitals_estimator/
# ---------------------------------------------------------------------------

from __future__ import annotations

__version__ = "1.0.0"

import argparse
import logging
import re
import sys
import time
from typing import Any, Dict, List, Optional, Sequence

import pandas as pd

LOGGER = logging.getLogger("core_web_vitals_estimator")


def configure_logging(level: str = "INFO") -> None:
    """Configure console logging for CLI output."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO), format="%(message)s"
    )


def estimate_cwv(url: str, timeout: int = 20, user_agent: str = "Mozilla/5.0") -> Dict[str, Any]:
    """
    Analyze a URL to estimate Core Web Vitals performance risks.

    Args:
        url: The URL to analyze.
        timeout: Request timeout in seconds.
        user_agent: User-Agent string.

    Returns:
        Dictionary of extracted CWV metrics and issues.
    """
    import requests
    from bs4 import BeautifulSoup

    start = time.time()
    try:
        resp = requests.get(url, headers={"User-Agent": user_agent}, timeout=timeout)
        resp.raise_for_status()
    except Exception as e:
        LOGGER.error(f"Failed to fetch {url}: {e}")
        return {
            "url": url[:60],
            "ttfb_s": 0.0,
            "html_size_kb": 0.0,
            "score": 0,
            "lcp_score": 0,
            "cls_score": 0,
            "fid_score": 0,
            "grade": "POOR",
            "issues": f"Fetch Error: {e}",
            "external_scripts": 0,
            "blocking_scripts": 0,
            "stylesheets": 0,
        }

    ttfb = round(time.time() - start, 3)
    html = resp.text
    html_size = len(html.encode("utf-8"))
    soup = BeautifulSoup(html, "html.parser")

    issues = []
    results = {
        "url": url[:60],
        "ttfb_s": ttfb,
        "html_size_kb": round(html_size / 1024, 1),
    }

    # --- LCP Estimation ---
    # Largest Contentful Paint candidates: hero images, large text blocks
    lcp_candidates = []
    # Hero image (first large image, often above fold)
    first_img = soup.find("img")
    if first_img:
        src = first_img.get("src", "")
        loading = first_img.get("loading", "")
        if loading == "lazy":
            issues.append("First image has lazy loading (delays LCP)")
        lcp_candidates.append(f"img: {src[:50]}")
    # H1 (often LCP for text-heavy pages)
    h1 = soup.find("h1")
    if h1:
        lcp_candidates.append(f"h1: {h1.get_text(strip=True)[:40]}")

    results["lcp_candidates"] = "; ".join(lcp_candidates[:3])

    # Preloaded resources
    preloads = soup.find_all("link", rel="preload")
    preload_images = [p for p in preloads if p.get("as") == "image"]
    if not preload_images and first_img:
        issues.append(
            "No preloaded hero image (add <link rel='preload'> for LCP image)"
        )

    # --- CLS Risk ---
    images_no_dims = [
        i for i in soup.find_all("img") if not (i.get("width") and i.get("height"))
    ]
    results["images_no_dimensions"] = len(images_no_dims)
    if images_no_dims:
        issues.append(f"{len(images_no_dims)} images without width/height (CLS risk)")

    # Iframes without dimensions
    iframes_no_dims = [
        i for i in soup.find_all("iframe") if not (i.get("width") and i.get("height"))
    ]
    if iframes_no_dims:
        issues.append(f"{len(iframes_no_dims)} iframes without dimensions (CLS risk)")

    # Dynamic content injection risk
    ad_divs = soup.find_all(attrs={"class": re.compile(r"ad|banner|promo", re.I)})
    if ad_divs:
        issues.append(
            f"{len(ad_divs)} potential ad containers (CLS risk if dynamically injected)"
        )

    # Font display
    font_faces = re.findall(r"font-display:\s*(\w+)", html)
    if "swap" not in font_faces and "optional" not in font_faces:
        issues.append("No font-display: swap/optional found (FOIT risk -> CLS)")

    # --- FID / INP Risk ---
    scripts = soup.find_all("script")
    ext_scripts = [s for s in scripts if s.get("src")]
    blocking_scripts = [
        s for s in ext_scripts if not s.get("defer") and not s.get("async")
    ]
    inline_js_size = sum(len(s.string or "") for s in scripts if not s.get("src"))

    results["external_scripts"] = len(ext_scripts)
    results["blocking_scripts"] = len(blocking_scripts)
    results["inline_js_kb"] = round(inline_js_size / 1024, 1)

    if len(blocking_scripts) > 3:
        issues.append(
            f"{len(blocking_scripts)} render-blocking scripts (add defer/async)"
        )
    if inline_js_size > 100000:
        issues.append(
            f"Large inline JS ({inline_js_size//1024}KB) — may block main thread"
        )

    # --- Render-blocking CSS ---
    stylesheets = soup.find_all("link", rel="stylesheet")
    results["stylesheets"] = len(stylesheets)
    if len(stylesheets) > 8:
        issues.append(
            f"{len(stylesheets)} external stylesheets (combine/defer non-critical CSS)"
        )

    # --- Scoring ---
    lcp_score = max(
        0, 33 - len([i for i in issues if "LCP" in i or "preload" in i]) * 11
    )
    cls_score = max(
        0, 33 - len([i for i in issues if "CLS" in i or "dimension" in i]) * 8
    )
    fid_score = max(
        0, 34 - len([i for i in issues if "script" in i.lower() or "JS" in i]) * 8
    )
    total = lcp_score + cls_score + fid_score

    results["score"] = total
    results["lcp_score"] = lcp_score
    results["cls_score"] = cls_score
    results["fid_score"] = fid_score
    results["grade"] = (
        "GOOD" if total >= 80 else "NEEDS IMPROVEMENT" if total >= 50 else "POOR"
    )
    results["issues"] = "; ".join(issues) if issues else "No major issues"

    return results


def main(argv: Optional[Sequence[str]] = None) -> int:
    """
    Main CLI entry point for the Core Web Vitals Estimator.

    Args:
        argv: Optional sequence of command-line arguments.

    Returns:
        Integer exit code (0 for success, 1 or 2 for errors).
    """
    parser = argparse.ArgumentParser(description="Core Web Vitals Estimator")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument("--url", help="Single URL to analyze")
    parser.add_argument("--urls", nargs="+", help="Multiple URLs to analyze")
    parser.add_argument("--output", help="Save report as XLSX/CSV")
    parser.add_argument(
        "--log-level", default="INFO", choices=["DEBUG", "INFO", "WARNING", "ERROR"], help="Set logging level"
    )
    args = parser.parse_args(argv)

    configure_logging(args.log_level)

    urls = list(args.urls or []) + ([args.url] if args.url else [])
    if not urls:
        LOGGER.error("Provide --url or --urls")
        return 1

    results = []
    for url in urls:
        LOGGER.info(f"Analyzing {url[:60]}...")
        results.append(estimate_cwv(url))

    for r in results:
        icon = {"GOOD": "🟢", "NEEDS IMPROVEMENT": "🟡", "POOR": "🔴"}.get(r["grade"], "⚪")
        LOGGER.info(f"\n{'='*60}")
        LOGGER.info(f"  {icon} {r['url']} [{r['grade']}] {r['score']}/100")
        LOGGER.info(f"{'='*60}")
        LOGGER.info(
            f"  TTFB: {r['ttfb_s']}s | LCP: {r['lcp_score']}/33 | CLS: {r['cls_score']}/33 | FID: {r['fid_score']}/34"
        )
        LOGGER.info(
            f"  Scripts: {r.get('external_scripts', 0)} ({r.get('blocking_scripts', 0)} blocking) | CSS: {r.get('stylesheets', 0)}"
        )
        if r.get("issues", "No major issues") != "No major issues":
            for issue in str(r["issues"]).split("; "):
                LOGGER.info(f"    ⚠️  {issue}")

    if args.output:
        try:
            df = pd.DataFrame(results)
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
