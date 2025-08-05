import os
import numpy as np
import pandas as pd
import pymysql
from sentence_transformers import SentenceTransformer
from functools import lru_cache
from dotenv import load_dotenv

# === Load .env ===
BASE_DIR = os.path.dirname(os.path.abspath(__file__))

load_dotenv(dotenv_path='/var/www/html/pim_flow_laravel/.env')

# === Config ===
CLICK_FILE = os.path.join(BASE_DIR, "product_clicks.csv")
EMBEDDINGS_FILE = os.path.join(BASE_DIR, "product_embeddings.npy")
NAMES_FILE = os.path.join(BASE_DIR, "product_names.txt")
TOP_K = 40

# === SQL Query ===
SQL = """
SELECT 
    ep.id AS product_id, 
    ep.name AS product_name, 
    ep.sku, 
    ep.sale_price, 
    ep.price, 
    ep.delivery_days, 
    ep.warranty_information,
    sm.url AS seo_url
FROM ec_products AS ep
JOIN product_suppliers AS ps ON ep.id = ps.product_id
LEFT JOIN seo_management AS sm ON sm.relational_id = ep.id
WHERE ep.status = "published"
"""

# === Load Products from MySQL ===
def load_products_from_sql(host, port, database, username, password, sql_query):
    conn = pymysql.connect(
        host=host,
        port=port,
        user=username,
        password=password,
        database=database
    )
    df = pd.read_sql(sql_query, conn)
    conn.close()
    df.columns = [col.lower().replace(' ', '_') for col in df.columns]
    return df

@lru_cache()
def load_product_df():
    df = load_products_from_sql(
        host=os.getenv("DB_HOST"),
        port=int(os.getenv("DB_PORT", 3306)),
        database=os.getenv("DB_DATABASE"),
        username=os.getenv("DB_USERNAME"),
        password=os.getenv("DB_PASSWORD"),
        sql_query=SQL
    )
    df['sku'] = df['sku'].astype(str).str.strip().str.upper()
    return df

@lru_cache()
def load_click_counts():
    try:
        df = pd.read_csv(CLICK_FILE, encoding="utf-8", dtype=str)
    except:
        df = pd.read_csv(CLICK_FILE, encoding="latin1", dtype=str)

    sku_col = next((c for c in df.columns if "sku" in c.lower()), df.columns[0])
    click_col = next((c for c in df.columns if "click" in c.lower()), df.columns[1])

    df = df.rename(columns={sku_col: "sku", click_col: "clicks"})
    df["sku"] = df["sku"].astype(str).str.strip().str.upper()
    df["clicks"] = pd.to_numeric(df["clicks"], errors="coerce").fillna(0).astype(int)

    click_df = df.groupby("sku", as_index=False)["clicks"].sum()
    return dict(zip(click_df["sku"], click_df["clicks"]))

@lru_cache()
def load_embeddings():
    df = load_product_df()
    names = df['product_name'].astype(str).tolist()
    skus = df['sku'].tolist()

    if not os.path.exists(EMBEDDINGS_FILE) or not os.path.exists(NAMES_FILE) or \
            (os.path.exists(NAMES_FILE) and len(open(NAMES_FILE, 'r', encoding='utf-8').readlines()) != len(names)):
        model = SentenceTransformer('all-MiniLM-L6-v2')
        embs = model.encode(names, convert_to_numpy=True)
        np.save(EMBEDDINGS_FILE, embs)
        with open(NAMES_FILE, "w", encoding="utf-8") as f:
            f.write("\n".join(names))
    else:
        embs = np.load(EMBEDDINGS_FILE)

    return skus, names, embs

# === Hybrid Search Function ===
def search_products(query: str, top_k: int = TOP_K):
    if not query.strip():
        return []

    model = SentenceTransformer('all-MiniLM-L6-v2')
    q_emb = model.encode([query], convert_to_numpy=True).flatten()
    tokens = query.lower().split()

    skus, names, embeddings = load_embeddings()
    click_counts = load_click_counts()
    df = load_product_df()
    df = df.set_index("sku")

    results = []
    for sku, name, emb in zip(skus, names, embeddings):
        kw_matches = sum(1 for token in tokens if token in name.lower())
        similarity = float(np.dot(emb, q_emb))
        clicks = click_counts.get(sku, 0)

        results.append({
            "sku": sku,
            "name": name,
            "kw": kw_matches,
            "sim": similarity,
            "clicks": clicks
        })

    results.sort(key=lambda x: (x["kw"], x["sim"]), reverse=True)
    top_results = results[:top_k]
    top_results.sort(key=lambda x: (x["clicks"] == 0, -x["clicks"]))

    final_results = []
    for item in top_results:
        sku = item["sku"]
        extra = df.loc[sku].to_dict() if sku in df.index else {}
        final_results.append({
            "product_name": item["name"],
            "sku": sku,
            "clicks": item["clicks"],
            "price": extra.get("price"),
            "sale_price": extra.get("sale_price"),
            "delivery_days": extra.get("delivery_days"),
            "warranty_information": extra.get("warranty_information"),
            "seo_url": extra.get("seo_url")
        })

    return final_results

# === CLI / Laravel Bridge ===
if __name__ == "__main__":
    import sys
    import json

    query = sys.argv[1] if len(sys.argv) > 1 else ""
    results = search_products(query)
    print(json.dumps(results, ensure_ascii=False))
