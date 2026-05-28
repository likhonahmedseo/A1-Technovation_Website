"""
Topical Link Cluster Builder — Group pages into topic silos with optimal interlinking.
Usage: python topical_link_cluster.py --urls https://site.com/p1 https://site.com/p2 --output clusters.xlsx
"""

# ---------------------------------------------------------------------------
# SEO Tools Library
# Copyright (c) 2026. All rights reserved.
# Original Developer: Rashed Hasan Akash (facebook.com/ORM69)
# Documentation: https://aaioinc.com/tools/topical_link_cluster/
# ---------------------------------------------------------------------------


__version__ = "1.0.0"
import argparse
import logging
import re
import time
from collections.abc import Sequence
from typing import Optional

import numpy as np
import pandas as pd
import sys

LOGGER = logging.getLogger("topical_link_cluster")


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


def main(argv: Optional[Sequence[str]] = None) -> int:
    p = argparse.ArgumentParser(description="Topical Link Cluster Builder")
    p.add_argument("--urls", nargs="+", required=True)
    p.add_argument("--num-clusters", type=int)
    p.add_argument("--output")
    p.add_argument("--version", action="version", version=f"%(prog)s {__version__}")
    p.add_argument(
        "--log-level",
        default="INFO",
        choices=["DEBUG", "INFO", "WARNING", "ERROR", "CRITICAL"],
    )
    a = p.parse_args(argv)
    configure_logging(a.log_level)

    texts = []
    labels = []
    import requests
    from bs4 import BeautifulSoup

    session = requests.Session()

    for u in a.urls:
        url = _normalize_url(u)
        if not url:
            continue
        try:
            s = BeautifulSoup(
                session.get(url, headers={"User-Agent": "Mozilla/5.0"}, timeout=15).text,
                "html.parser",
            )
            [t.decompose() for t in s(["script", "style", "nav", "footer"])]
            texts.append(re.sub(r"\s+", " ", s.get_text(" ", strip=True)))
            labels.append(url[:60])
        except Exception as exc:
            LOGGER.warning("Failed fetching %s: %s", url, exc)
        time.sleep(0.3)
    if len(texts) < 3:
        LOGGER.error("Need 3+ pages")
        return 2

    try:
        from sentence_transformers import SentenceTransformer
        from sklearn.cluster import KMeans
    except Exception:
        LOGGER.error(
            "Missing optional dependencies. Install 'sentence-transformers' and 'scikit-learn'."
        )
        return 2

    embs = SentenceTransformer("all-MiniLM-L6-v2").encode(
        [" ".join(t.split()[:256]) for t in texts]
    )
    k = a.num_clusters or min(max(2, len(texts) // 4), 8)
    km = KMeans(n_clusters=k, random_state=42, n_init=10)
    clusters = km.fit_predict(embs)
    results = []
    for cid in range(k):
        members = [labels[i] for i in range(len(labels)) if clusters[i] == cid]
        if not members:
            continue
        sys.stdout.write(f"\nCluster {cid} ({len(members)} pages) - link these together:\n")
        for m in members:
            sys.stdout.write(f"  - {m}\n")
            results.append({"cluster": cid, "url": m, "action": "interlink within cluster"})
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
