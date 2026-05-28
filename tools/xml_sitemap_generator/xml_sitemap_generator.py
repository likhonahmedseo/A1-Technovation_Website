"""
Smart Sitemap Generator — Generate priority-weighted sitemaps from URL list.
Usage: python xml_sitemap_generator.py --urls urls.txt --output sitemap.xml
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/xml_sitemap_generator/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"
import argparse
import logging
import os
import sys
from collections.abc import Sequence
from typing import Optional

from datetime import datetime
from urllib.parse import urljoin, urlparse

LOGGER = logging.getLogger("xml_sitemap_generator")


def configure_logging(level: str) -> None:
    """Set up root logger with the specified verbosity level."""
    logging.basicConfig(
        level=getattr(logging, level.upper(), logging.INFO),
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )


def _normalize_url(url: str, base_url: str | None) -> str:
    """Resolve a URL against an optional base, returning empty string on failure."""
    u = (url or "").strip()
    if not u:
        return ""
    if u.startswith(("http://", "https://")):
        return u
    if base_url:
        return urljoin(base_url.rstrip("/") + "/", u.lstrip("/"))
    return ""


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser(description="Smart Sitemap Generator")
    p.add_argument("--urls", required=True, help="File with URLs")
    p.add_argument("--output", default="sitemap.xml")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument("--base-url", help="Base URL for relative paths")
    p.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    a = p.parse_args(argv)
    configure_logging(a.log_level)

    if not os.path.isfile(a.urls):
        LOGGER.error("URL list file not found: %s", a.urls)
        return 2

    with open(a.urls, "r", encoding="utf-8", errors="replace") as f:
        raw_urls = [l.strip() for l in f if l.strip()]

    urls: list[str] = []
    for u in raw_urls:
        nu = _normalize_url(u, a.base_url)
        if nu:
            urls.append(nu)

    if not urls:
        LOGGER.error("No valid URLs found (use absolute URLs or provide --base-url)")
        return 2

    xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
    for url in urls:
        path = urlparse(url).path
        depth = len([p for p in path.split("/") if p])
        priority = (
            1.0 if depth == 0 else 0.8 if depth == 1 else 0.6 if depth == 2 else 0.4
        )
        freq = "daily" if depth <= 1 else "weekly" if depth <= 2 else "monthly"
        xml += f'  <url>\n    <loc>{url}</loc>\n    <lastmod>{datetime.now().strftime("%Y-%m-%d")}</lastmod>\n    <changefreq>{freq}</changefreq>\n    <priority>{priority}</priority>\n  </url>\n'
    xml += "</urlset>"
    try:
        with open(a.output, "w", encoding="utf-8") as f:
            f.write(xml)
    except Exception as exc:
        LOGGER.error("Failed writing sitemap '%s': %s", a.output, exc)
        return 2

    LOGGER.info("Generated sitemap with %d URLs -> %s", len(urls), a.output)
    return 0


if __name__ == "__main__":
    sys.exit(main())
