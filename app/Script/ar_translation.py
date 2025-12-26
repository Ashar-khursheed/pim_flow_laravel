import sys
import pymysql
import pandas as pd
from langchain_openai import ChatOpenAI
from langchain_core.prompts import PromptTemplate
from langchain.chains import LLMChain
from langchain_core.output_parsers import JsonOutputParser
import os, json

SQL = """
SELECT
    ec_products.id AS product_id,
    ec_products.sku as SKU,
    ec_products.name AS product_name,
    attributes.name AS attribute_name,
    product_attributes.attribute_value AS attribute_value
FROM ec_products
JOIN product_attributes
    ON ec_products.id = product_attributes.product_id
JOIN attributes
    ON product_attributes.attribute_id = attributes.id;
"""

conn = pymysql.connect(
    host=os.getenv("DB_HOST"),
    user=os.getenv("DB_USERNAME"),
    password=os.getenv("DB_PASSWORD"),
    port=int(os.getenv("DB_PORT")),
    database=os.getenv("DB_DATABASE")
)

df = pd.read_sql(SQL, conn)

# Group and combine attributes into a dictionary
data = []
for (product_id, product_name), group in df.groupby(['product_id', 'product_name']):
    attributes = {row['attribute_name']: row['attribute_value'] for _, row in group.iterrows()}
    data.append({
        "product_id": product_id,
        "sku" : group['SKU'].iloc[0],
        "product_name": product_name,
        "attributes": attributes
    })
df_out = pd.DataFrame(data)
df_out.head()
conn.close()

# user_input = int(input("Enter the product ID to generate content for: "))
if len(sys.argv) < 2:
    raise ValueError("Please provide a product ID as a command-line argument.")

user_input = int(sys.argv[1])

if user_input in df_out['product_id'].values:
    product_row = df_out.loc[df_out["product_id"] == user_input].iloc[0]
else:
    raise ValueError(f'{user_input} is not a valid PRODUCT ID in the database.')

def _get_from_attrs(attrs: dict, candidates):
    for k, v in attrs.items():
        kl = str(k).strip().lower()
        for c in candidates:
            if kl == c:
                return v
    return ""

attrs = product_row["attributes"] if isinstance(product_row["attributes"], dict) else {}
brand_name = _get_from_attrs(attrs, ["brand", "brand name", "manufacturer"])

sku_value = ""
if "sku" in product_row and pd.notna(product_row["sku"]):
    sku_value = str(product_row["sku"]).strip()
else:
    sku_value = _get_from_attrs(attrs, ["sku", "item code", "product code", "model", "mpn"])

product_json = {
    "brand_name": brand_name or "",
    "product_name": product_row["product_name"],
    "sku": sku_value or "",
    "attributes": attrs
}
model = ChatOpenAI(
   model="gpt-4",
    api_key=os.getenv("OPENAI_API_KEY")
)

prompt_text = """
Act as a HORECA content specialist creating professional product content for a B2B eCommerce platform.
Audience: chefs, commercial kitchen operators, hotels, and catering businesses.

🗣️ TONE & STYLE
Write in Modern Standard Arabic (MSA) — clear, factual, and professional.
Maintain a B2B tone: informative, concise, and confident.
Use active voice, no fluff, no marketing exaggeration.
All text must be Right-to-Left (RTL) aligned.
Write all numbers using English digits (e.g., 1, 2, 3).
Keep decimal points as dots (.) and not commas (e.g., 1.6 not 1,6).
Sentences must always end with a period (no em dashes).
Do not include section titles in descriptions. The first line must be a natural sentence, not a heading.

⚙️ UNITS & MEASUREMENTS
Liter / L / Liters → لتر (never لترات; never omitted).
Kg / kilogram / kilograms → كجم.
g / gram / grams → غرام.
cm / Cms → سم.
Dimensions must be translated exactly as in data — no rearranging or auto-formatting (do not use WxDxH).
Do not mix capacity (لتر) and length (سم) units.
Keep decimal precision exactly as given in data.

🧩 NAMING, BRAND, AND ATTRIBUTE RULES
Brand names:
If already in Arabic → copy exactly as given.
If in English → transliterate once and reuse consistently.
Do not merge brand name, product name, or SKU — keep separate.
SKU must always appear as رمز المنتج {{{{sku}}}} when mentioned.
If product type (e.g., Series, Collection) is listed, keep it distinct from the brand name and title — do not omit or merge it.
For “1-Group”, “2-Group”, “3-Group” → translate as: ١ مجموعة, ٢ مجموعة, ٣ مجموعة.
Electrical phases: 1-Phase → طور أحادي, 2-Phase → طور ثنائي, 3-Phase → طور ثلاثي.
Colors: if multiple, join with "و" (e.g., "أسود وفضي").
Translate “Halal” correctly as حلال (never “هلال”).
Stainless steel → ستانلس ستيل.
Specific meat cuts → translate into common UAE Arabic, not transliteration.
For “UltraVent” or similar trademarks, transliterate appropriately.
Never invent, omit, or reinterpret attribute values.

Titles
- Do not generate titles inside the description.
- The first sentence must be a proper descriptive sentence, not a title-style line.
- Generate a stable, consistent title for each product based on the given data.
- Do not include any English words in the title.
- DO NOT EVER TRANSLATE THE BRAND NAME INTO ARABIC LANGUAGE. JUST THE WORD ITSELF CONVERT IT INTO ARABIC NAME THATS IT. LIKE TRANSLATE THE EXACT ENGLISH NAME INTO ARABIC NAME. DO NOT CHANGE THE BRAND NAME AT ALL.
- Give the title better and best than an other e-commerce platform.

📦 DESCRIPTION GUIDELINES
Generate 4 descriptions which should be overall less than 500 characters including spaces.
Always write from right to left (RTL).
Do not include any English words in the description.
DO NOT EVER TRANSLATE THE BRAND NAME INTO ARABIC LANGUAGE. JUST THE WORD ITSELF CONVERT IT INTO ARABIC NAME THATS IT. LIKE TRANSLATE THE EXACT ENGLISH NAME INTO ARABIC NAME. DO NOT CHANGE THE BRAND NAME AT ALL.
Generate a 4-paragraph Arabic description (~٣٠٠–٣٥٠ characters each). Each paragraph must end with a period.
Paragraph 1: “يعد {{{{brand_name}}}} {{{{product_name}}}} رمز المنتج {{{{sku}}}}…” (omit brand/SKU gracefully if missing).
Paragraph 2: Core technical specifications, functions, and control features.
Paragraph 3: If dimensions exist, include them exactly as in data. Add brief installation/placement/safety notes.
Paragraph 4: Certifications, warranty, commercial suitability. Include accessories/compliance only if listed (e.g., ADA, Halal, BPA-free).

Hard Rules:
Never mention product weight unless handheld and explicitly listed.
Never reference packaging, shipping, or origin if “Made in China.”
Do not invent missing specs or claims.

🌟 BENEFITS SECTION
Output must contain exactly 5 items.
Each item object: {{"benefit": "Benefit Title", "feature": "One crisp sentence linking to a listed spec."}}
No dimensions or weight as benefits. Each benefit must tie directly to a listed spec.

❓ TECHNICAL FAQ SECTION
Provide exactly 5 concise Q&A pairs. All in Arabic, factual, technical, not marketing.
No weight/dimensions unless handheld. End each sentence with a period.

💾 OUTPUT FORMAT (STRICT JSON ONLY)
Output valid JSON only — no Markdown, no extra text.
{{
  "title": "Stable Arabic title from data",
  "description": ["paragraph1", "paragraph2", "paragraph3", "paragraph4"],
  "benefits": [
    {{"benefit": "Benefit Title", "feature": "Linked feature sentence."}}
  ],
  "faqs": [
    {{"question": "Question?", "answer": "Answer."}}
  ]
}}

Use ONLY this product JSON as your source of truth.
{product_json}
"""

template = PromptTemplate(input_variables=["product_json"], template=prompt_text)
output_parser = JsonOutputParser()
chain = LLMChain(llm=model, prompt=template, output_parser=output_parser)

payload = json.dumps(product_json, ensure_ascii=False)
result = chain.run(product_json=payload)

try:
    out = json.loads(result) if isinstance(result, str) else result
    if isinstance(out, dict):
        out = [out]
    final_output = json.dumps(out, ensure_ascii=False)

    print(final_output)
    # with open(f"product_{user_input}.json", "w", encoding="utf-8") as f:
    #     f.write(final_output)

except Exception as e:
    print("Error:", e)
