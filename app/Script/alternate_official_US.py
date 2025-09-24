import pandas as pd
import pymysql
import re
import json
import ast
import os
import anthropic
import warnings
import logging
from typing import List, Optional, Dict
import mysql.connector
from mysql.connector import Error
from tqdm import tqdm
import time
import sys 
from dotenv import load_dotenv
load_dotenv()
warnings.filterwarnings("ignore", category=DeprecationWarning)
logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s: %(message)s")

def get_api_key():   
    api_key = os.getenv(CLAUDE_API_KEY)   
    if not api_key:
        raise ValueError("ANTHROPIC_API_KEY not set in environment variables.")
    return api_key

def get_llm():
    return anthropic.Anthropic(api_key=get_api_key())


def retry_on_rate_limit(func, max_retries=5, initial_delay=5, backoff=2):
    delay = initial_delay
    for attempt in range(max_retries):
        try:
            return func()
        except Exception as e:
            if "rate_limit_error" in str(e):
                logging.warning(f"Rate limit hit. Retry {attempt + 1}/{max_retries} in {delay} seconds...")
                time.sleep(delay)
                delay *= backoff
            else:
                raise e
    raise Exception("Max retries exceeded due to rate limits.")


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
    df = df.drop_duplicates().reset_index(drop=True)
    df.columns = [col.lower().replace(' ', '_') for col in df.columns]
    return df



def parse_attributes(attr_str):
    attrs = {}
    for pair in re.split(r',\s*(?=\w+\s*-\s*)', attr_str):
        if '-' in pair:
            key, val = pair.split('-', 1)
            attrs[key.strip().lower()] = val.strip().lower()
    return attrs


def prepare_dataframe(df):
    df['allattributes_dict'] = df['attribute_combined'].apply(parse_attributes)

    # df["product_type"] = df["allattributes_dict"].apply(extract_product_type)
    imp_df = df[['product_id', 'product_name', 'brand_name', 'allattributes_dict', 'product_type', 'sub_category']]
    imp_df["embedding_text"] = imp_df[[
        "product_id",
        "product_name",
        "brand_name",
        "allattributes_dict",
        "product_type"
    ]].astype(str).agg(" ".join, axis=1)
    imp_df['product_type'] = imp_df['product_type'].str.replace(r'\d+', '', regex=True)
    imp_df['product_type'] = imp_df['product_type'].str.strip()
    return imp_df


def llm_attribute_extractor(product_text_from_attribute_column):
    llm = get_llm()
    prompt = f"""
    You are a highly specialized Product Attribute Extraction Engine. Your task is to analyze the provided product description and extract its key physical and functional attributes into a structured JSON object.

    Strict Rules for Extraction:
    2.  **Dimensions**: Extract all numerical dimensions and their units (e.g., length, width, height, capacity, weight, diameter). Combine the number and unit, or just the number if the unit is implied. Look for "inches", "cm", "cu. ft.", "liters", "HP", "BTU", etc.
    3.  **Door_Details**: If applicable, extract details about doors, including the number of doors ("1 Door", "2 Doors"), and door type/mounting ("Single Door", "Double Door", "Reach-In", "Top-Mounted", "Bottom-Mounted", "French Door", "Glass Door", "Steel Door") and always give it in numeric format.
    4.  **Quantity**: Extract any explicit quantities or case sizes (e.g., "Case of 12 Pcs", "6 Pans").
    5.  **Material**: Extract the primary material if specified (e.g., "Stainless Steel", "Ceramic", "Glass", "Plastic").
    6.  **Form_Factor**: Extract any specific form factors not covered by other categories (e.g., "Oval", "Hollow").
    7.  **Other_Features**: Any other significant, factual features not categorized above (e.g., "6-Year Warranty", "Auto-shutoff", "Digital Display").

    Constraints:
    * **Strict JSON Format**: Your output MUST be a valid JSON object.
    * **Keys**: Use the exact keys listed above (Product_Type, Dimensions, Door_Details, Quantity, Material, Form_Factor, Other_Features). If a category is not found, its value should be an empty list `[]` or `null` if it's a single value (like Product_Type if none is found).
    * **Values**:
        * `Dimensions`: A list of strings, each combining the number and unit (e.g., ["14 inches", "12.9 Cu. Ft."]).
        * `Door_Details`: A list of strings (e.g., ["Single Door"]). Only include number of doors thats it nothing else
        * `Quantity`: A list of strings (e.g., ["Case of 12 Pcs"]).
        * `Material`: A list of strings (e.g., ["Stainless Steel"]).
        * `Form_Factor`: A list of strings (e.g., ["Oval", "Hollow"]).
        * `Other_Features`: A list of strings.
    * **No Extraneous Text**: Do not include any conversational text or explanations outside the JSON object.

    Here are examples:

    Example 1:
    Query: "Arctic Air AWR25 30.75 Single Door Reach-In Refrigerator in White"
    Expected JSON:
    {{
      "Dimensions": ["30.75 inches"],
      "Door_Details": ["Single Door"],
      "Quantity": [],
      "Material": [],
      "Form_Factor": [],
      "Other_Features": []
    }}

    Example 2:
    Query: "CAC China GAD-14 Oval Platter 14\" Case of 12 Pcs"
    Expected JSON:
    {{
      "Dimensions": ["14 inches"],
      "Door_Details": [],
      "Quantity": ["Case of 12 Pcs"],
      "Material": [],
      "Form_Factor": ["Oval"],
      "Other_Features": []
    }}

    Example 3:
    Query: "Sandwich Prep Table Refrigerator, 48\", 2 Doors, 12 Pans, 12.9 Cu. Ft., Stainless Steel, 6-Year Warranty"
    Expected JSON:
    {{
      "Dimensions": ["48 inches", "12.9 Cu. Ft."],
      "Door_Details": ["2 Doors"],
      "Quantity": ["12 Pans"],
      "Material": ["Stainless Steel"],
      "Form_Factor": [],
      "Other_Features": ["6-Year Warranty"]
    }}

    Example 4:
    Query: "Winco Z-CL-08 Cadenza Claret Dinner Knife (Hollow)"
    Expected JSON:
    {{
      "Dimensions": [],
      "Door_Details": [],
      "Quantity": [],
      "Material": [],
      "Form_Factor": ["Hollow"],
      "Other_Features": []
    }}

    Now, analyze the following user product text:
    \"\"\"{product_text_from_attribute_column}\"\"\"
    """

    def call_llm():
        return llm.messages.create(
            model="claude-3-5-sonnet-20240620",
            system="You are an expert product attribute extraction agent. Strictly output only valid JSON based on the user's instructions.",
            messages=[{"role": "user", "content": prompt}],
            max_tokens=300,
            temperature=0.1
        )

    response = retry_on_rate_limit(call_llm)
    extracted_json_string = None

    try:
        # Anthropic's response.content can be a list or a string
        if hasattr(response, "content"):
            if isinstance(response.content, list) and len(response.content) > 0 and hasattr(response.content[0],
                                                                                            "text"):
                extracted_json_string = response.content[0].text.strip()
            elif isinstance(response.content, str):
                extracted_json_string = response.content.strip()
            else:
                logging.error(f"Unexpected response.content type: {type(response.content)} | Value: {response.content}")
        else:
            logging.error(f"No 'content' attribute in response: {response}")
    except Exception as e:
        logging.error(f"Exception while extracting LLM response: {e}")
        logging.error(f"Raw response: {response}")

    if not extracted_json_string:
        logging.warning("No usable content in LLM response.")
        return {}

    try:
        return json.loads(extracted_json_string)
    except Exception as e:
        logging.error(f"Error decoding JSON from LLM response: {e}")
        logging.error(f"Raw LLM response: {extracted_json_string}")
        return {}


def fallback_top3_by_llm(filtered_rows: pd.DataFrame, matched_rows: pd.DataFrame, query: str) -> pd.DataFrame:
    if "product_name" not in filtered_rows.columns or "allattributes_dict" not in filtered_rows.columns:
        logging.warning("No recommendations found: missing required columns.")
        return pd.DataFrame()

    llm = get_llm()

    mask = filtered_rows["product_name"] == query
    if not mask.any():
        logging.warning(f"No matching product_name '{query}' found in filtered_rows for fallback_top3_by_llm.")
        return pd.DataFrame()
    raw = filtered_rows.loc[mask, "allattributes_dict"].iloc[0]
    qattrs = ast.literal_eval(raw) if isinstance(raw, str) else raw

    lines = []
    for _, row in matched_rows.iterrows():
        name = row.get("product_name", "<no-name>")
        cell = row.get("allattributes_dict", {})
        attrs = ast.literal_eval(cell) if isinstance(cell, str) else cell
        lines.append(f"- {name}: {json.dumps(attrs)}")

    prompt = f"""
You are a product similarity assistant. The query product has attributes:
{json.dumps(qattrs)}

Here are candidate products and their attributes:
{chr(10).join(lines)}

Which three products above are most similar to the query?
Respond with ONLY a JSON array of exactly three product names, e.g. ["Prod A", "Prod B", "Prod C"].
"""

    def call_llm():
        return llm.messages.create(
            model="claude-3-5-sonnet-20240620",
            system="You are a product matching assistant. Output only a JSON array of names.",
            messages=[{"role": "user", "content": prompt}],
            max_tokens=150,
            temperature=0.0
        )

    resp = retry_on_rate_limit(call_llm)
    content = (
        resp.content[0].text.strip()
        if isinstance(resp.content, list)
        else resp.content
    )

    try:
        top3 = json.loads(content)
    except json.JSONDecodeError:
        top3 = []
    return matched_rows[matched_rows["product_name"].isin(top3)]


def parse_numbers_from_strings(strings: List[str]) -> List[float]:
    nums = []
    for s in strings:
        if not s:
            continue
        for match in re.finditer(r"([0-9]*\.?[0-9]+)", s):
            nums.append(float(match.group(1)))
    return nums


def get_top3_nearest_by_dimensions_and_capacity(filtered_rows: pd.DataFrame, matched_rows: pd.DataFrame,
                                                query: str) -> pd.DataFrame:
    if "product_name" not in filtered_rows.columns or "allattributes_dict" not in filtered_rows.columns:
        logging.warning("No recommendations found: missing required columns.")
        return pd.DataFrame()

    if "product_name" not in matched_rows.columns:
        logging.warning("No recommendations found: missing required columns.")
        return pd.DataFrame()

    qseries = filtered_rows.loc[filtered_rows["product_name"] == query, "allattributes_dict"]

    if qseries.empty:
        logging.warning(f"No recommendations found: query '{query}' not in data.")
        return pd.DataFrame()
    raw = qseries.iloc[0]
    qattrs = ast.literal_eval(raw) if isinstance(raw, str) else raw

    q_raw: List[str] = []
    if "capacity" in qattrs:
        q_raw.append(str(qattrs["capacity"]))
    q_raw.extend(qattrs.get("Dimensions", []))
    q_vals = parse_numbers_from_strings(q_raw)

    if not q_vals:
        return fallback_top3_by_llm(filtered_rows, matched_rows, query)

    recs = []
    for _, row in matched_rows.iterrows():
        cell = row.get("allattributes_dict", {})
        attrs = ast.literal_eval(cell) if isinstance(cell, str) else cell

        cand_raw: List[str] = []
        if "capacity" in attrs:
            cand_raw.append(str(attrs["capacity"]))
        cand_raw.extend(attrs.get("Dimensions", []))
        cand_vals = parse_numbers_from_strings(cand_raw)

        if not cand_vals:
            continue
        min_diff = min(abs(c - q) for c in cand_vals for q in q_vals)

        recs.append({
            "product_name": row.get("product_name"),
            "brand_name": row.get("brand_name"),
            "diff": min_diff
        })

    if not recs:
        return fallback_top3_by_llm(filtered_rows, matched_rows, query)

    df_diff = pd.DataFrame(recs).sort_values("diff", ascending=True)
    query_brands = set()

    if "brand_name" in filtered_rows.columns:
        query_brands = set(
            filtered_rows.loc[filtered_rows["product_name"] == query, "brand_name"]
        )
    df_other = df_diff[~df_diff["brand_name"].isin(query_brands)]
    top = df_other.head(3)

    if len(top) < 3:
        remaining = df_diff[~df_diff["product_name"].isin(top["product_name"])]
        top = pd.concat([top, remaining]).head(3)

    return matched_rows[matched_rows["product_name"].isin(top["product_name"])]


def get_similarity_score_and_matched_attrs(llm, query_attrs: Dict, candidate_attrs: Dict) -> Dict:
    prompt = f"""
You are a product comparison assistant.

Query product attributes:
{json.dumps(query_attrs)}

Candidate product attributes:
{json.dumps(candidate_attrs)}

Provide a JSON output with:
- similarity_percentage: a float (0-100) for overall attribute similarity.
- matched_attributes_count: an integer for the number of matching attributes.

Respond ONLY with the JSON.
"""

    def call_llm():
        return llm.messages.create(
            model="claude-3-5-sonnet-20240620",
            system="You are a product matching assistant. Output only JSON.",
            messages=[{"role": "user", "content": prompt}],
            max_tokens=100,
            temperature=0.1
        )

    resp = retry_on_rate_limit(call_llm)
    content = resp.content[0].text.strip() if isinstance(resp.content, list) else resp.content
    try:
        return json.loads(content)
    except json.JSONDecodeError:
        return {"similarity_percentage": 0.0, "matched_attributes_count": 0}


def process_index(i, imp_df):
    query = imp_df.loc[i, "product_name"]
    x = imp_df.loc[imp_df["product_name"] == query, "sub_category"]

    if x.empty:
        filtered_rows = pd.DataFrame()
    else:
        product_type = x.unique()
        filtered_rows = imp_df[imp_df["sub_category"].isin(product_type)].copy()
        filtered_rows.reset_index(drop=True, inplace=True)

    keywords = llm_attribute_extractor(query)
    query_text = ",".join([
        ",".join(keywords.get("Door_Details", [])),
        ",".join(keywords.get("Material", []))
    ])

    parts = [p.strip() for p in query_text.split(',') if p.strip()]
    desired_door_info = next((p for p in parts if 'door' in p.lower()), None)
    desired_material_info = next((p for p in parts if p != desired_door_info), None)

    def extract_segment(x: dict, desired_info: str) -> Optional[str]:
        for value_string in x.values():
            if isinstance(value_string, str):
                for part in value_string.split(','):
                    if desired_info.lower() in part.lower():
                        return part.strip()
        return None

    def matches(row) -> bool:
        cell = row['allattributes_dict']
        attrs = {}
        if isinstance(cell, dict):
            attrs = cell
        elif isinstance(cell, str):
            try:
                parsed = ast.literal_eval(cell)
                if isinstance(parsed, dict):
                    attrs = parsed
                else:
                    attrs = {}
            except (ValueError, SyntaxError):
                attrs = {}
        else:
            attrs = {}



        door_match = bool(desired_door_info and extract_segment(attrs, desired_door_info))
        mat_match = bool(desired_material_info and extract_segment(attrs, desired_material_info))
        if desired_door_info and desired_material_info:
            return door_match or mat_match
        if desired_door_info:
            return door_match
        if desired_material_info:
            return mat_match
        return True

    if not (desired_door_info or desired_material_info):
        matched_rows = filtered_rows.copy()
    else:
        matched_rows = filtered_rows[filtered_rows.apply(matches, axis=1)]
    matched_rows.reset_index(drop=True, inplace=True)

    original_brands = []
    if "product_name" in filtered_rows.columns and "brand_name" in filtered_rows.columns:
        mask = filtered_rows["product_name"] == query
        if mask.any():
            original_brands = filtered_rows.loc[mask, "brand_name"].unique()

    if len(original_brands) and "brand_name" in matched_rows.columns:
        matched_rows = matched_rows[~matched_rows["brand_name"].isin(original_brands)]
        matched_rows.reset_index(drop=True, inplace=True)

    if matched_rows.empty:
        matched_rows = filtered_rows[filtered_rows.apply(matches, axis=1)]
        matched_rows.reset_index(drop=True, inplace=True)

    llm = get_llm()
    qseries = filtered_rows.loc[filtered_rows["product_name"] == query, "allattributes_dict"]
    if not qseries.empty:
        raw = qseries.iloc[0]
        query_attrs = ast.literal_eval(raw) if isinstance(raw, str) else raw
    else:
        query_attrs = {}
    top3_df = get_top3_nearest_by_dimensions_and_capacity(filtered_rows, matched_rows, query)
    top3_df = top3_df[top3_df["product_name"] != query]
    top3_df.reset_index(drop=True, inplace=True)
    if len(top3_df) >= 3:
        answer = top3_df[:3].copy()
    elif len(top3_df) > 0:
        answer = top3_df.copy()
    elif len(matched_rows) > 1:
        filtered_matched = matched_rows[matched_rows["product_name"] != query]
        answer = filtered_matched[:3].copy() if len(filtered_matched) >= 3 else filtered_matched.copy()
    else:
        answer = "No Alternate Products"

    if isinstance(answer, pd.DataFrame) and not answer.empty and query_attrs:
        def add_similarity(row):
            candidate_attrs = ast.literal_eval(row["allattributes_dict"]) if isinstance(row["allattributes_dict"],
                                                                                        str) else row[
                "allattributes_dict"]
            result = get_similarity_score_and_matched_attrs(llm, query_attrs, candidate_attrs)
            return pd.Series({
                "similarity_percentage": result["similarity_percentage"],
                "matched_attributes_count": result["matched_attributes_count"]
            })

        similarity_df = answer.apply(add_similarity, axis=1)
        answer = pd.concat([answer, similarity_df], axis=1)
    query_product_id = imp_df.loc[i, 'product_id'] if 'product_id' in imp_df.columns else None

    if isinstance(answer, pd.DataFrame):
        answer['id'] = query_product_id
        answer['product_you_may_like'] = answer['product_id'] if 'product_id' in answer.columns else None
        answer['priority'] = answer['matched_attributes_count']
        answer['similarity'] = answer['similarity_percentage']
        cols_to_keep = ['id', 'product_you_may_like', 'priority', 'similarity']
        answer = answer[cols_to_keep]
    return answer

def save_alternates_to_db(answer_df: pd.DataFrame, db_config: dict):
    """
    Deletes existing alternate products for a given product_id and inserts the new ones.
    """
    if answer_df.empty:
        return

    # Ensure product_id is a standard Python int for the DELETE statement
    product_id = int(answer_df['id'].iloc[0])

    # SQL statements
    delete_sql = "DELETE FROM alternate_products WHERE product_id = %s"
    insert_sql = """
    INSERT INTO alternate_products
    (product_id, product_you_may_like_id, priority, similarity, created_at, updated_at)
    VALUES (%s, %s, %s, %s, NOW(), NOW())
    """

    conn = None
    try:
        conn = mysql.connector.connect(**db_config)
        cursor = conn.cursor()

        # Step 1: Delete all existing alternates for this product_id
        cursor.execute(delete_sql, (product_id,))

        # Step 2: Insert the new set of alternates
        for _, row in answer_df.iterrows():
            # FIX: Convert all numpy types to standard Python types (int, float)
            data_to_insert = (
                int(row['id']),
                int(row['product_you_may_like']),
                int(row['priority']),
                float(row['similarity'])
            )
            cursor.execute(insert_sql, data_to_insert)

        conn.commit()
        logging.info(f"Successfully saved {len(answer_df)} alternate products for product_id {product_id} to the database.")

    except Error as e:
        logging.error(f"Database error for product_id {product_id}: {e}")
        if conn:
            conn.rollback()
    finally:
        if conn and conn.is_connected():
            cursor.close()
            conn.close()




def main():
        # --- CONFIGURATION ---
    # Set a value (e.g., '1') to filter by that category.
    # Set to None to run for specific product IDs.
    category_id = None

    # Define the specific list of product IDs to process if category_id is None
 

    # raw_input = sys.stdin.read()
    # if not raw_input.strip():
    #     raise ValueError("No input provided")   
    # input_data = json.loads(raw_input)
    # product_id_list = input_data.get("product_id_list", [])
    # print(product_id_list)
    product_id_list = [1683];

    #test branch
    # db_config = {
    #     "host": "pim-flow-db.ch0qsm2uacmv.us-west-1.rds.amazonaws.com",
    #     "port": 3306,
    #     "database": "pim_flow_db",
    #     "user": "admin",
    #     "password": "Yellowgrapes3322"
    # }
    db_config = {
        "host": os.getenv("DB_HOST"),
        "port": int(os.getenv("DB_PORT", 3306)),  # default 3306
        "database": os.getenv("DB_DATABASE"),
        "user": os.getenv("DB_USERNAME"),
        "password": os.getenv("DB_PASSWORD")
    }
    
    # db_config = {
    #     "host": "horecadb.ch0qsm2uacmv.us-west-1.rds.amazonaws.com",
    #     "port": 3306,
    #     "database": "horecadb",
    #     "user": "admin",
    #     "password": "Mangoorange9987"
    # }

    # db_config = {
    #     "host": "horecadbuaebackup.c1c86oy8g663.me-south-1.rds.amazonaws.com",
    #     "port": 3306,
    #     "database": "horecadbuae",
    #     "user": "horecaDbUAE",
    #     "password": "Stopthehacker2207"
    # }

    # --- DYNAMIC SQL QUERY CONSTRUCTION ---
    base_sql = """
    WITH cte AS (
    SELECT
        ep.id AS product_id,
        ep.name AS product_name,
        eb.name AS brand_name,
        ep.description,
        ep.benefits_features,
        ep.stock_status,
        ep.status,
        ps.price AS price,
        ps.delivery_days,
        er.star,
        er.comment
    FROM ec_products ep
    JOIN ec_brands eb ON ep.brand_id = eb.id AND eb.status = 'published'
    JOIN product_suppliers ps ON ps.product_id = ep.id
    LEFT JOIN ec_reviews er ON er.product_id = ep.id
    WHERE ep.status = 'published'
),
cte2 AS (
    SELECT
        p.product_id,
        GROUP_CONCAT(CONCAT(a.name, ' - ', p.attribute_value) ORDER BY p.attribute_id SEPARATOR ', ') AS attribute_combined
    FROM product_attributes p
    JOIN attributes a ON p.attribute_id = a.id
    GROUP BY p.product_id
),
cte_cat AS (
    SELECT
        pc.product_id,
        MAX(pc.category_id) AS category_id
    FROM product_categories pc
    GROUP BY pc.product_id
),
cte_product_type AS (
    SELECT
        pa.product_id,
        pa.attribute_value AS product_type
    FROM product_attributes pa
    JOIN attributes a ON pa.attribute_id = a.id
    WHERE a.name = 'Type'   --
)
SELECT
    c.product_id,
    c.product_name,
    c.brand_name,
    c2.attribute_combined,
    pt.product_type,  --
    ct.category_id,
    cat.name AS sub_category
FROM cte c
JOIN cte2 c2 ON c.product_id = c2.product_id
JOIN cte_cat ct ON c.product_id = ct.product_id
JOIN categories cat ON cat.id = ct.category_id
LEFT JOIN cte_product_type pt ON c.product_id = pt.product_id
    """

    final_sql = ""
    if category_id:
        logging.info(f"Constructing query for category ID: {category_id}")
        # If category_id is provided, append the recursive WHERE clause to the base query
        category_filter_sql = f"""
        WHERE c.product_id IN (
            WITH RECURSIVE category_tree AS (
                SELECT id FROM categories WHERE id = '{category_id}'
                UNION ALL
                SELECT c.id FROM categories c INNER JOIN category_tree ct ON c.parent_id = ct.id
            )
            SELECT DISTINCT p.id AS product_id
            FROM category_tree ct
            JOIN product_categories pc ON pc.category_id = ct.id
            JOIN ec_products p ON p.id = pc.product_id
            WHERE p.status = 'published'
        )
        """
        final_sql = base_sql + category_filter_sql + " ORDER BY c.product_id;"
    else:
        logging.info("Constructing query for all products.")
        # If category_id is None, use the base query as is
        final_sql = base_sql + " ORDER BY c.product_id;"

    # --- DATA LOADING & PREPARATION ---
    all_products_df = load_products_from_sql(db_config["host"], db_config["port"], db_config["database"], db_config["user"], db_config["password"], final_sql)
    imp_df = prepare_dataframe(all_products_df)

    if imp_df.empty:
        logging.warning("The prepared DataFrame 'imp_df' is empty. No products to process.")
        return

    # --- PROCESSING LOGIC ---
    product_indices_to_process = []
    if category_id:
        # If a category was specified, process all products loaded in the filtered DataFrame
        logging.info(f"Will process all {len(imp_df)} products from the filtered category.")
        product_indices_to_process = imp_df.index.tolist()
    else:
        # If no category, filter the full DataFrame by the specific product ID list
        logging.info(f"Will process specific product IDs: {product_id_list}")
        product_indices_to_process = imp_df[imp_df['product_id'].isin(product_id_list)].index.tolist()

    if not product_indices_to_process:
        logging.warning("No products found to process based on the specified criteria.")
        return

    # --- MAIN PROCESSING LOOP ---
    for i in tqdm(product_indices_to_process, desc="Processing products"):
        product_id = imp_df.loc[i, 'product_id']
        logging.info(f"Processing product_id: {product_id}")

        try:
            # The process_index function takes the full imp_df for context, even if it's pre-filtered
            answer_df = process_index(i, imp_df)
            if isinstance(answer_df, pd.DataFrame) and not answer_df.empty:
                save_alternates_to_db(answer_df, db_config)
            else:
                logging.warning(f"No alternate products found for product_id {product_id}.")
        except Exception as e:
            logging.error(f"Error processing product_id {product_id}: {e}")
            continue

    logging.info("Processing complete.")
if __name__ == "__main__":
    main()