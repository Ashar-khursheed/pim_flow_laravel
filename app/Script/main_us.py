#!/usr/bin/env python3
import sys
# Force UTF-8 on Windows
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")
else:
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", errors="replace")
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8", errors="replace")

import json
from slugify import slugify
import random

KEY_MAP = {
    "relational_id": "relational_id",
    "relational_name": "relational_name",
    "relational_type": "relational_type",
    "url": "url",
    "primary_keyword": "primary_keyword",
    "monthly_search_volume": "monthly_search_volume",
    "title_tag": "title_tag",
    "meta_title": "meta_title",
    "meta_description": "meta_description",
    "internal_links": "internal_links",
    "indexing": "indexing",
    "og_title": "og_title",
    "og_description": "og_description",
    "og_image_url": "og_image_url",
    "og_image_alt_text": "og_image_alt_text",
    "og_image_name": "og_image_name",
    "tags": "tags",
    "created_by": "created_by",
    "schema_rating": "schema_rating",
    "schema_reviews_count": "schema_reviews_count"
}
REV_MAP = {v: k for k, v in KEY_MAP.items()}

TITLE_TEMPLATES = [
    "{pk} Available Now",
    "Shop {pk} Today",
    "Your Source for {pk}"
]
META_TEMPLATES = [
    "{usp} on {pk} Excellence",
    "{pk} with {usp} Quality",
    "Exclusive {pk} with {usp}"
]
OG_TEMPLATES = [
    "{pk} with {usp} Quality",
    "{usp} for {pk} Excellence",
    "{pk} — {usp} Excellence"
]
BENEFITS = ["Fast Delivery", "Unbeatable Deals", "Top Performance", "Exclusive Savings"]
CTAS = {
    "shop": ["Shop Now!", "Order Today!", "Grab Yours!"],
    "explore": ["Explore More", "Discover Now", "Learn More"],
    "read": ["Read More", "Dive In", "Learn More"]
}

def pick(pool): return random.choice(pool)
def extract_type(full): return full.split("\\")[-1]
def generate_url(name): return slugify(name)
def generate_title_tag(pk): return pick(TITLE_TEMPLATES).format(pk=pk)[:70]
def generate_meta_title(pk):
    usp = pick(BENEFITS); tpl = pick(META_TEMPLATES)
    return tpl.format(pk=pk, usp=usp)[:60]
def generate_og_title(pk):
    usp = pick(BENEFITS); tpl = pick(OG_TEMPLATES)
    return tpl.format(pk=pk, usp=usp)[:60]
def generate_meta_description(pk, rtype):
    cta = pick(CTAS["read"]) if rtype=="Blog" else pick(CTAS["shop"])
    if rtype=="Blog":
        return f"Explore {pk} insights, with expert tips and in-depth analysis from trusted resources. {cta} to learn more."[:160]
    return f"Premium {pk}, built for durability and excellence. Enjoy {pick(BENEFITS)} with every purchase. {cta}"[:160]
def generate_og_description(meta_desc, rtype, pk):
    cta = pick(CTAS["explore"]) if rtype=="Blog" else pick(CTAS["shop"])
    if rtype=="Blog":
        return f"Trusted source for {pk} insights. Dive into expert tips, analysis, and resources to elevate your knowledge. {cta}"[:200]
    return f"Top choice for {pk}! Premium quality, lasting durability, and {pick(BENEFITS)}. {cta} to experience excellence."[:200]

def enrich(record):
    rec, orig = {}, {}
    for k, i in KEY_MAP.items():
        v = record.get(k, "")
        rec[i], orig[i] = str(v).strip(), bool(v and str(v).strip())
    pk = rec["primary_keyword"] or rec["relational_name"]
    rtype = extract_type(rec["relational_type"])

    if not orig["url"]:            rec["url"] = generate_url(rec["relational_name"])
    if not orig["title_tag"]:      rec["title_tag"] = generate_title_tag(pk)
    if not orig["meta_title"]:     rec["meta_title"] = generate_meta_title(pk)
    if not orig["og_title"]:       rec["og_title"] = generate_og_title(pk)
    if not orig["meta_description"]:rec["meta_description"] = generate_meta_description(pk, rtype)
    if not orig["og_description"]: rec["og_description"] = generate_og_description(rec["meta_description"], rtype, pk)

    return {REV_MAP[i]: v for i, v in rec.items()}

def main():
    try:
        inp = json.load(sys.stdin)
        json.dump(enrich(inp), sys.stdout, ensure_ascii=False, indent=2)
    except Exception as e:
        sys.stderr.write(f"Error: {e}\n"); sys.exit(1)

if __name__=="__main__":
    main()