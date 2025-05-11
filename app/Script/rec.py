import os
import json
import requests
import pymysql
import sys
from dotenv import load_dotenv

load_dotenv()

class ClaudeRecommender:
    def __init__(self, env_config=None):
        if env_config:
            self.headers = {
                "x-api-key": env_config.get("CLAUDE_API_KEY"),
                "anthropic-version": env_config.get("CLAUDE_VERSION"),
                "Content-Type": "application/json"
            }
            self.api_url = env_config.get("CLAUDE_API_URL")
            self.model = env_config.get("CLAUDE_MODEL")
        else:
            self.headers = {
                "x-api-key": os.getenv("CLAUDE_API_KEY"),
                "anthropic-version": os.getenv("CLAUDE_VERSION"),
                "Content-Type": "application/json"
            }
            self.api_url = os.getenv("CLAUDE_API_URL")
            self.model = os.getenv("CLAUDE_MODEL")

    def get_attributes_from_claude(self, parent_id, products):
        prompt = f"""
You are a product information expert.

Below is a list of product variants under a parent product ID {parent_id}. Each product has technical attributes.

Your task:
1. Identify attributes that exist in **every product**.
2. From those, choose the **top 3 most relevant**.
3. Return the result in the format:

{{
  "common_attributes": [
    {{ "attribute_name": "Color", "reason": "e.g. always shown on product listings" }},
    ...
  ],
  "variants": [
    {{
      "product_id": 2971,
      "attributes": [
        {{ "attribute_name": "Color", "value": "Black" }},
        ...
      ]
    }}
  ]
}}

Here are the product variants and their attributes:
{json.dumps(products, indent=2)}
        """

        payload = {
            "model": self.model,
            "messages": [
                {
                    "role": "system",
                    "content": "You are a PIM specialist focused on choosing key product attributes."
                },
                {
                    "role": "user",
                    "content": prompt
                }
            ],
            "max_tokens": 1500
        }

        try:
            response = requests.post(self.api_url, json=payload, headers=self.headers)
            response.raise_for_status()
            print("Claude raw response:", json.dumps(response.json(), indent=2), file=sys.stderr)
            return json.loads(response.json()['content'][0]['text'])
        except Exception as e:
            print(f"Claude API Error: {str(e)}", file=sys.stderr)
            return None

def fetch_product_attributes(child_ids):
    db = pymysql.connect(
        host=os.getenv("DB_HOST"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        database=os.getenv("DB_NAME"),
        charset='utf8mb4',
        cursorclass=pymysql.cursors.DictCursor
    )

    try:
        with db.cursor() as cursor:
            format_ids = ",".join(str(pid) for pid in child_ids)
            query = f"""
            SELECT pa.product_id, a.name as attribute_name, pa.attribute_value
            FROM product_attributes pa
            JOIN attributes a ON pa.attribute_id = a.id
            WHERE pa.product_id IN ({format_ids})
            """
            cursor.execute(query)
            results = cursor.fetchall()

        product_map = {}
        for row in results:
            pid = row["product_id"]
            if pid not in product_map:
                product_map[pid] = []
            product_map[pid].append({
                "attribute_name": row["attribute_name"],
                "attribute_value": row["attribute_value"]
            })

        return [
            {"product_id": pid, "attributes": attrs}
            for pid, attrs in product_map.items()
        ]
    finally:
        db.close()

def process_families(input_data):
    recommender = ClaudeRecommender()
    families = []

    for item in input_data:
        parent_id = item.get("parent_id")
        child_ids = item.get("child_ids")

        if not child_ids:
            continue

        products = fetch_product_attributes(child_ids)
        print("Fetched product attributes:", json.dumps(products, indent=2), file=sys.stderr)

        if not products:
            continue

        ai_response = recommender.get_attributes_from_claude(parent_id, products)

        if not ai_response:
            continue

        family = {
            "parent_id": parent_id,
            "family_name": f"family-{parent_id}",
            "child_ids": child_ids,
            "common_attributes": ai_response.get("common_attributes", []),
            "variants": []
        }

        for variant in ai_response.get("variants", []):
            family["variants"].append({
                "product_id": variant["product_id"],
                "product_name": f"Product {parent_id}-{variant['product_id']}",
                "image": f"https://example.com/{variant['product_id']}.webp",
                "attributes": variant["attributes"]
            })

        families.append(family)

    return families

def main():
    try:
        if len(sys.argv) != 3:
            raise ValueError("Expected exactly two arguments: env_config and input_data")

        env_config = json.loads(sys.argv[1])
        input_payload = json.loads(sys.argv[2])
        input_data = input_payload  # ← FIXED this line

        families = process_families(input_data)

        result = {
            "success": True,
            "families": families
        }

        print(json.dumps(result))

    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"JSON Error: {str(e)}",
            "received_env": sys.argv[1][:100] if len(sys.argv) > 1 else "No env data",
            "received_input": sys.argv[2][:100] if len(sys.argv) > 2 else "No input data"
        }))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))

if __name__ == "__main__":
    main()
