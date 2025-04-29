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

# Input → internal key map
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

# Variation pools
TITLE_TEMPLATES = [
    "{pk} Available Now in UAE",
    "Shop {pk} Across UAE Today",
    "Your Source for {pk} in UAE"
]
META_TEMPLATES = [
    "{usp}: {pk} in UAE | {brand}",
    "{pk} in UAE — {brand} | {usp}",
    "{brand} Presents {pk} in UAE — {usp}"
]
OG_TEMPLATES = [
    "{pk} in UAE — {usp}",
    "{usp} for {pk} in UAE",
    "{pk} — Top Choice in UAE by {brand}"
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

def generate_title_tag(pk):
    return pick(TITLE_TEMPLATES).format(pk=pk)[:70]

def generate_meta_title(pk, brand):
    usp = pick(BENEFITS)
    tpl = pick(META_TEMPLATES)
    return tpl.format(pk=pk, brand=brand, usp=usp)[:60]

def generate_og_title(pk, brand):
    usp = pick(BENEFITS)
    tpl = pick(OG_TEMPLATES)
    return tpl.format(pk=pk, brand=brand, usp=usp)[:60]

def generate_meta_description(pk, rtype):
    cta = pick(CTAS["read"]) if rtype=="Blog" else pick(CTAS["shop"])
    if rtype=="Blog":
        return f"Dive into {pk} insights—expert tips for UAE readers. {cta}"[:160]
    return f"Premium {pk} engineered for durability in UAE. {cta}"[:160]

def generate_og_description(meta_desc, rtype, pk):
    cta = pick(CTAS["explore"]) if rtype=="Blog" else pick(CTAS["shop"])
    if rtype=="Blog":
        part = meta_desc.split(".")[0]
        return f"Trusted source for {pk}—{part}. {cta}"[:200]
    return f"UAE’s #1 choice for {pk}! {meta_desc.split('—')[-1].strip()}. {cta}"[:200]

def enrich(record):
    rec, orig = {}, {}
    for key, internal in KEY_MAP.items():
        val = record.get(key, "")
        rec[internal] = str(val).strip()
        orig[internal] = bool(val and str(val).strip())

    pk = rec["primary_keyword"] or rec["relational_name"]
    rtype = extract_type(rec["relational_type"])
    brand = rec["relational_id"] or "YourBrand"

    if not orig["url"]:
        rec["url"] = generate_url(rec["relational_name"])
    if not orig["title_tag"]:
        rec["title_tag"] = generate_title_tag(pk)
    if not orig["meta_title"]:
        rec["meta_title"] = generate_meta_title(pk, brand)
    if not orig["og_title"]:
        rec["og_title"] = generate_og_title(pk, brand)
    if not orig["meta_description"]:
        rec["meta_description"] = generate_meta_description(pk, rtype)
    if not orig["og_description"]:
        rec["og_description"] = generate_og_description(rec["meta_description"], rtype, pk)

    return {REV_MAP[k]: v for k, v in rec.items()}

def main():
    try:
        inp = json.load(sys.stdin)
        out = enrich(inp)
        json.dump(out, sys.stdout, ensure_ascii=False, indent=2)
    except Exception as e:
        sys.stderr.write(f"Error: {e}\n")
        sys.exit(1)

if __name__=="__main__":
    main()
