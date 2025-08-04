import os
import numpy as np
import pandas as pd
from sentence_transformers import SentenceTransformer
from fastapi import FastAPI
from pydantic import BaseModel
from typing import List
from fastapi.middleware.cors import CORSMiddleware
from contextlib import asynccontextmanager
import pymysql
import uvicorn


# === Config === (Change path here)
BASE_DIR = os.path.dirname(__file__)
CLICK_FILE = os.path.join(BASE_DIR, 'product_clicks.csv')
EMBEDDINGS_FILE = os.path.join(BASE_DIR, 'product_embeddings.npy')
NAMES_FILE = os.path.join(BASE_DIR, 'product_names.txt')
TOP_K           = 50

#loading the data from sql
SQL = """
select id as product_id, name as product_name, sku from ec_products where status="published"
"""

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

df = load_products_from_sql(
    "horecadb.ch0qsm2uacmv.us-west-1.rds.amazonaws.com", 3306,
    "horecadb", "admin", "Mangoorange9987", SQL
)


# === Loaders ===
def load_products_and_embeddings(df):
    df['sku'] = df['sku'].str.strip().str.upper()
    names = df['product_name'].astype(str).tolist()
    skus = df['sku'].tolist()

    regenerate = False

    # Check if both files exist
    if os.path.exists(EMBEDDINGS_FILE) and os.path.exists(NAMES_FILE):
        with open(NAMES_FILE, 'r', encoding='utf-8') as f:
            saved_names = f.read().splitlines()

        # Check if saved names match current df
        if len(saved_names) != len(names):
            regenerate = True
    else:
        regenerate = True

    if regenerate:
        print("⚙️ Regenerating embeddings and names...")
        model = SentenceTransformer('all-MiniLM-L6-v2')
        embs = model.encode(names, convert_to_numpy=True)
        np.save(EMBEDDINGS_FILE, embs)
        with open(NAMES_FILE, 'w', encoding='utf-8') as f:
            f.write("\n".join(names))
    else:
        print("✅ Using existing embeddings and name list.")
        embs = np.load(EMBEDDINGS_FILE)

    return skus, names, embs

# def load_products_and_embeddings(df):
#     # df = pd.read_csv(PRODUCT_FILE, dtype={"sku": str})
#     df['sku'] = df['sku'].str.strip().str.upper()
#     skus = df['sku'].tolist()
#     names = df['product_name'].astype(str).tolist()
#
#     # rebuild if missing or out of sync
#     if (
#         not os.path.exists(EMBEDDINGS_FILE) or
#         not os.path.exists(NAMES_FILE) or
#         len(open(NAMES_FILE, 'r', encoding='utf-8').readlines()) != len(names)
#     ):
#         model = SentenceTransformer('all-MiniLM-L6-v2')
#         embs = model.encode(names, convert_to_numpy=True)
#         np.save(EMBEDDINGS_FILE, embs)
#         with open(NAMES_FILE, 'w', encoding='utf-8') as f:
#             f.write("\n".join(names))
#     else:
#         embs = np.load(EMBEDDINGS_FILE)
#
#     return skus, names, embs

def load_click_counts():
    try:
        df = pd.read_csv(CLICK_FILE, encoding='utf-8', dtype=str)
    except:
        df = pd.read_csv(CLICK_FILE, encoding='latin1', dtype=str)

    # auto-detect sku & clicks columns
    sku_col   = next((c for c in df.columns if 'sku'   in c.lower()), df.columns[0])
    click_col = next((c for c in df.columns if 'click' in c.lower()), df.columns[1])
    df = df.rename(columns={sku_col: 'sku', click_col: 'clicks'})

    df['sku']    = df['sku'].str.strip().str.upper()
    df['clicks'] = pd.to_numeric(df['clicks'], errors='coerce').fillna(0).astype(int)

    agg = df.groupby('sku', as_index=False)['clicks'].sum()
    return dict(zip(agg['sku'], agg['clicks']))


@asynccontextmanager
async def lifespan(app: FastAPI):
    global product_skus, product_names, embeddings, click_counts, model
    df_sql = load_products_from_sql(
        "horecadb.ch0qsm2uacmv.us-west-1.rds.amazonaws.com", 3306,
        "horecadb", "admin", "Mangoorange9987", SQL
    )
    product_skus, product_names, embeddings = load_products_and_embeddings(df_sql)
    click_counts = load_click_counts()
    model = SentenceTransformer('all-MiniLM-L6-v2')
    yield

app = FastAPI(lifespan=lifespan)

# where your React frontend is running.
# example (dont change here)
# allow_origins=[
#     "https://your-frontend.com",       # Replace with your real frontend URL
#     "https://app.netlify.app",         # Example if using Netlify
#     "https://yourproject.vercel.app"   # Example if using Vercel
# ]

# enable CORS for your React dev server
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# === Pydantic model ===
class SearchResult(BaseModel):
    sku:    str
    name:   str
    clicks: int

# === Endpoints ===
@app.get("/", tags=["root"])
async def root():
    return {"message": "Welcome to Product Search API"}

@app.get("/search", response_model=List[SearchResult], tags=["search"])
def search(query: str):
    if not query.strip():
        return []

    q_emb  = model.encode([query], convert_to_numpy=True).flatten()
    tokens = query.lower().split()

    scored = []
    for sku, name, emb in zip(product_skus, product_names, embeddings):
        kw     = sum(1 for t in tokens if t in name.lower())
        sim    = float(np.dot(emb, q_emb))
        clicks = click_counts.get(sku, 0)
        scored.append((kw, sim, clicks, sku, name))

    # keyword + semantic sort, take top K
    scored.sort(key=lambda x: (x[0], x[1]), reverse=True)
    topk = scored[:TOP_K]

    # re-rank by clicks (preserving relevance within same click-count)
    buckets = {}
    for item in topk:
        buckets.setdefault(item[2], []).append(item)

    final = []
    for count in sorted(buckets.keys(), reverse=True):
        final.extend(buckets[count])

    return [
        SearchResult(sku=sku, name=name, clicks=ctr)
        for _, _, ctr, sku, name in final
    ]

# === Run via PyCharm “Run” or `python nlp_v5.py` ===
# uvicorn nlp_v5:app --reload

if __name__ == "__main__":
    # uvicorn.run(app, host="127.0.0.1", port=8000, reload=True)
    uvicorn.run(app, host="0.0.0.0", port=8000, reload=True)