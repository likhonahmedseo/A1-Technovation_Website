"""
Local Citation Consistency Checker — Check NAP across pages.
Usage: python local_citation_checker.py --urls https://page1.com https://page2.com --output citations.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/local_citation_checker/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"
import argparse
import logging
import re
from collections.abc import Sequence
from dataclasses import dataclass
from typing import Optional

import pandas as pd
import sys


LOGGER = logging.getLogger("local_citation_checker")


@dataclass(frozen=True)
class FetchConfig:
    timeout: int = 15
    user_agent: str = "Mozilla/5.0"


def configure_logging(level: str) -> None:
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


_EMAIL_RE = re.compile(r"[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}")
_PHONE_CANDIDATE_RE = re.compile(r"[\+]?[0-9\s\-\(\)]{10,15}")
_PHONE_STRICT_RE = re.compile(r"\d{3}.*\d{3}.*\d{4}")
_ADDRESS_RE = re.compile(
    r"\d+\s+[\w\s]+(?:Street|St|Ave|Road|Rd|Dr|Blvd|Lane|Way)[.,]?\s*[\w\s]*,?\s*[A-Z]{2}\s*\d{5}"
)


def _normalize_url(url: str) -> str:
    u = (url or "").strip()
    if not u:
        return ""
    if not u.startswith(("http://", "https://")):
        u = "https://" + u
    return u


def extract_nap(url: str, *, fetch: FetchConfig = FetchConfig()) -> dict[str, str]:
    try:
        import requests
        from bs4 import BeautifulSoup
    except Exception as exc:
        raise RuntimeError(
            "Missing dependencies. Install: pip install requests beautifulsoup4"
        ) from exc

    try:
        session = requests.Session()
        resp = session.get(
            url, headers={"User-Agent": fetch.user_agent}, timeout=fetch.timeout
        )
        resp.raise_for_status()
        s = BeautifulSoup(resp.text or "", "html.parser")
        text = s.get_text(" ", strip=True)
        phones = list(set(_PHONE_CANDIDATE_RE.findall(text)))
        phones = [p.strip() for p in phones if _PHONE_STRICT_RE.search(p)]
        emails = list(set(_EMAIL_RE.findall(text)))
        addresses = _ADDRESS_RE.findall(text)
        return {
            "url": url[:60],
            "phones": "; ".join(phones[:3]),
            "emails": "; ".join(emails[:3]),
            "addresses": "; ".join(addresses[:2]),
        }
    except Exception as exc:
        LOGGER.debug("Failed extracting NAP from %s: %s", url, exc)
        return {"url": url[:60], "phones": "error", "emails": "", "addresses": ""}


def _write_results(df: pd.DataFrame, output_path: str) -> str:
    out = output_path.strip()
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


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser()
    p.add_argument("--urls", nargs="+", required=True)
    p.add_argument("--output")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR"],
        help="Logging verbosity.",
    )
    a = p.parse_args(argv)

    configure_logging(a.log_level)

    urls = [_normalize_url(u) for u in a.urls]
    urls = [u for u in urls if u]
    if not urls:
        LOGGER.error("No valid URLs provided.")
        return 2

    results = [extract_nap(u) for u in urls]
    LOGGER.info("NAP CONSISTENCY CHECK (%d sources)", len(results))
    for r in results:
        LOGGER.info("%s | phone=%s | email=%s", r["url"], r["phones"], r["emails"])
    # Check consistency
    all_phones = set()
    for r in results:
        if r["phones"] and r["phones"] != "error":
            all_phones.update(r["phones"].split("; "))
    if len(all_phones) > 1:
        LOGGER.warning("INCONSISTENT phones: %s", sorted(p for p in all_phones if p))
    if a.output:
        try:
            saved = _write_results(pd.DataFrame(results), a.output)
            LOGGER.info("Saved to %s", saved)
        except Exception as exc:
            LOGGER.error("Failed writing output: %s", exc)
            return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
