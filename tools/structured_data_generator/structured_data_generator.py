"""
Structured Data Generator
============================
Generates JSON-LD schema markup for Article, Product, LocalBusiness,
HowTo, BreadcrumbList, and Organization from page content or user input.

Usage:
    python structured_data_generator.py --type article --url https://example.com/blog-post
    python structured_data_generator.py --type product --name "Widget Pro" --price 29.99 --currency USD
    python structured_data_generator.py --type local-business --name "Joe's Pizza" --address "123 Main St, NYC"
    python structured_data_generator.py --type howto --url https://example.com/tutorial --output schema.json
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/structured_data_generator/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"

import argparse
import json
import logging
import re
import sys
from datetime import datetime
from collections.abc import Sequence
from typing import Optional

LOGGER = logging.getLogger("structured_data_generator")


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


def fetch_page_data(session, url: str) -> dict:
    from bs4 import BeautifulSoup

    resp = session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15)
    resp.raise_for_status()
    soup = BeautifulSoup(resp.text, "html.parser")
    title = soup.title.get_text(strip=True) if soup.title else ""
    h1 = soup.find("h1").get_text(strip=True) if soup.find("h1") else title
    meta_desc = ""
    md = soup.find("meta", attrs={"name": "description"})
    if md and md.get("content"):
        meta_desc = md["content"]
    og_image = ""
    og = soup.find("meta", attrs={"property": "og:image"})
    if og and og.get("content"):
        og_image = og["content"]
    # Extract date
    date_pub = ""
    time_tag = soup.find("time", attrs={"datetime": True})
    if time_tag:
        date_pub = time_tag["datetime"]
    # Author
    author = ""
    author_tag = soup.find(attrs={"class": re.compile(r"author", re.I)})
    if author_tag:
        author = author_tag.get_text(strip=True)
    # Steps (for howto)
    steps = []
    for h2 in soup.find_all("h2"):
        text_parts = []
        for sib in h2.next_siblings:
            sib_name = getattr(sib, "name", None)
            if sib_name and re.match(r"^h[1-2]$", str(sib_name)):
                break
            if sib_name in ("p", "div"):
                text_parts.append(sib.get_text(strip=True))
        if text_parts:
            steps.append(
                {"name": h2.get_text(strip=True), "text": " ".join(text_parts)[:500]}
            )

    return {
        "title": title,
        "h1": h1,
        "description": meta_desc,
        "image": og_image,
        "date_published": date_pub,
        "author": author,
        "url": url,
        "steps": steps,
    }


def gen_article(data, author_name=None):
    return {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": data.get("h1") or data.get("title", ""),
        "description": data.get("description", ""),
        "image": data.get("image", ""),
        "author": {
            "@type": "Person",
            "name": author_name or data.get("author", "Author"),
        },
        "publisher": {
            "@type": "Organization",
            "name": "Publisher Name",
            "logo": {"@type": "ImageObject", "url": ""},
        },
        "datePublished": data.get("date_published")
        or datetime.now().strftime("%Y-%m-%d"),
        "dateModified": datetime.now().strftime("%Y-%m-%d"),
        "mainEntityOfPage": {"@type": "WebPage", "@id": data.get("url", "")},
    }


def gen_product(
    name,
    description="",
    price=None,
    currency="USD",
    image="",
    brand="",
    rating=None,
    review_count=None,
):
    schema = {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": name,
        "description": description,
        "image": image,
    }
    if brand:
        schema["brand"] = {"@type": "Brand", "name": brand}
    if price is not None:
        schema["offers"] = {
            "@type": "Offer",
            "price": str(price),
            "priceCurrency": currency,
            "availability": "https://schema.org/InStock",
        }
    if rating:
        schema["aggregateRating"] = {
            "@type": "AggregateRating",
            "ratingValue": str(rating),
            "reviewCount": str(review_count or 1),
        }
    return schema


def gen_local_business(
    name, address, phone="", hours="", lat=None, lng=None, biz_type="LocalBusiness"
):
    schema = {
        "@context": "https://schema.org",
        "@type": biz_type,
        "name": name,
        "address": {"@type": "PostalAddress", "streetAddress": address},
    }
    if phone:
        schema["telephone"] = phone
    if hours:
        schema["openingHours"] = hours
    if lat and lng:
        schema["geo"] = {"@type": "GeoCoordinates", "latitude": lat, "longitude": lng}
    return schema


def gen_howto(data, title=None):
    steps_schema = [
        {"@type": "HowToStep", "name": s["name"], "text": s["text"]}
        for s in data.get("steps", [])
    ]
    return {
        "@context": "https://schema.org",
        "@type": "HowTo",
        "name": title or data.get("h1", "How-To Guide"),
        "description": data.get("description", ""),
        "image": data.get("image", ""),
        "step": steps_schema,
    }


def gen_breadcrumbs(items):
    return {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": i + 1,
                "name": item["name"],
                "item": item.get("url", ""),
            }
            for i, item in enumerate(items)
        ],
    }


def main(argv: Optional[Sequence[str]] = None) -> int:
    parser = argparse.ArgumentParser(description="Structured Data Generator")
    parser.add_argument(
        "--version", action="version", version=f"%(prog)s {__version__}"
    )
    parser.add_argument(
        "--type",
        required=True,
        choices=["article", "product", "local-business", "howto", "breadcrumbs"],
    )
    parser.add_argument("--url", help="Page URL to extract data from")
    parser.add_argument("--name", help="Name (product/business)")
    parser.add_argument("--description", help="Description")
    parser.add_argument("--price", type=float, help="Product price")
    parser.add_argument("--currency", default="USD")
    parser.add_argument("--address", help="Business address")
    parser.add_argument("--phone", help="Business phone")
    parser.add_argument("--author", help="Author name")
    parser.add_argument("--brand", help="Product brand")
    parser.add_argument("--rating", type=float, help="Rating value")
    parser.add_argument(
        "--breadcrumbs", nargs="+", help="Breadcrumb items as name:url pairs"
    )
    parser.add_argument("--output", help="Save JSON to file")
    parser.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    args = parser.parse_args(argv)
    configure_logging(args.log_level)

    data = {}
    if args.url:
        import requests

        session = requests.Session()
        url = _normalize_url(args.url)
        if not url:
            LOGGER.error("URL is empty")
            return 2
        LOGGER.info("Extracting data from %s", url)
        try:
            data = fetch_page_data(session, url)
        except Exception as exc:
            LOGGER.error("Failed fetching %s: %s", url, exc)
            return 2

    if args.type == "article":
        schema = gen_article(data, args.author)
    elif args.type == "product":
        if not (args.name or data.get("h1") or data.get("title")):
            LOGGER.error("Product schema requires --name or a fetchable --url")
            return 2
        schema = gen_product(
            args.name or data.get("h1", "Product"),
            args.description or data.get("description", ""),
            args.price,
            args.currency,
            data.get("image", ""),
            args.brand,
            args.rating,
        )
    elif args.type == "local-business":
        if not (args.name and args.address):
            LOGGER.error("Local business schema requires --name and --address")
            return 2
        schema = gen_local_business(
            args.name or "", args.address or "", args.phone or ""
        )
    elif args.type == "howto":
        if not data.get("steps") and not args.url:
            LOGGER.warning("HowTo schema generation works best with --url to extract steps")
        schema = gen_howto(data, args.name)
    elif args.type == "breadcrumbs":
        if not args.breadcrumbs:
            LOGGER.error("Breadcrumbs schema requires --breadcrumbs")
            return 2
        items = []
        for b in args.breadcrumbs or []:
            parts = b.split(":", 1)
            items.append({"name": parts[0], "url": parts[1] if len(parts) > 1 else ""})
        schema = gen_breadcrumbs(items)

    json_str = json.dumps(schema, indent=2, ensure_ascii=False)
    sys.stdout.write(json_str + "\n")

    if args.output:
        try:
            with open(args.output, "w", encoding="utf-8") as f:
                f.write(json_str)
            LOGGER.info("Saved to %s", args.output)
        except Exception as exc:
            LOGGER.error("Failed writing output '%s': %s", args.output, exc)
            return 2

    return 0


if __name__ == "__main__":
    sys.exit(main())
