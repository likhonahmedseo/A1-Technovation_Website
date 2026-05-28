"""
Structured Data Test Suite — Validate schema against Google requirements.
Usage: python structured_data_validator.py --url https://example.com --output validation.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/structured_data_validator/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"
import argparse
import json
import logging
from collections.abc import Sequence
from typing import Optional

import pandas as pd
import sys

LOGGER = logging.getLogger("structured_data_validator")


def configure_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


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

REQUIRED = {
    "Article": ["headline", "author", "datePublished", "image"],
    "Product": ["name", "image", "offers"],
    "FAQPage": ["mainEntity"],
    "HowTo": ["name", "step"],
    "LocalBusiness": ["name", "address"],
    "BreadcrumbList": ["itemListElement"],
    "VideoObject": ["name", "uploadDate", "thumbnailUrl"],
    "Review": ["itemReviewed", "reviewRating", "author"],
    "Recipe": ["name", "image", "recipeIngredient"],
}


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser(description="Structured Data Test Suite")
    p.add_argument("--url", required=True)
    p.add_argument("--output")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    a = p.parse_args(argv)
    configure_logging(a.log_level)

    import requests
    from bs4 import BeautifulSoup

    session = requests.Session()
    try:
        resp = session.get(a.url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
        resp.raise_for_status()
    except Exception as exc:
        LOGGER.error("Failed fetching %s: %s", a.url, exc)
        return 2

    s = BeautifulSoup(resp.text, "html.parser")
    schemas = []
    results = []
    for sc in s.find_all("script", type="application/ld+json"):
        try:
            raw = sc.string or ""
            if not raw.strip():
                continue
            d = json.loads(raw)
            if isinstance(d, list):
                schemas.extend(d)
            elif isinstance(d, dict):
                if "@graph" in d:
                    schemas.extend(d["@graph"])
                else:
                    schemas.append(d)
        except Exception:
            continue
    if not schemas:
        LOGGER.info("No JSON-LD found")
        return 0
    for schema in schemas:
        stype_val = schema.get("@type", "Unknown")
        types = (
            [str(t) for t in stype_val if t]
            if isinstance(stype_val, list)
            else [str(stype_val)]
        )
        for stype in types:
            required = REQUIRED.get(stype, [])
            present = [f for f in required if f in schema]
            missing = [f for f in required if f not in schema]
            score = len(present) / max(len(required), 1) * 100
            results.append(
                {
                    "type": stype,
                    "required": len(required),
                    "present": len(present),
                    "missing": ", ".join(missing),
                    "score": round(score, 1),
                }
            )
            icon = "OK" if score == 100 else "WARN" if score >= 50 else "FAIL"
            LOGGER.info("%s %s: %.0f%% complete", icon, stype, score)
            if missing:
                LOGGER.warning("Missing (%s): %s", stype, ", ".join(missing))
    if a.output:
        try:
            written = _write_results(pd.DataFrame(results), a.output)
            LOGGER.info("Wrote %s", written)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", a.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
