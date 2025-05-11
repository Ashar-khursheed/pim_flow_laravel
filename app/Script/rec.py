# app/Scripts/rec.py

import os
import json
import sys
import requests

class ClaudeRecommender:
    def __init__(self, env_vars):
        self.headers = {
            "x-api-key": env_vars["CLAUDE_API_KEY"],
            "anthropic-version": env_vars["CLAUDE_VERSION"],
            "Content-Type": "application/json"
        }
        self.api_url = env_vars["CLAUDE_API_URL"]
        self.model = env_vars["CLAUDE_MODEL"]

    def get_attributes_from_claude(self, parent_id, child_ids):
        prompt = f"""
        Analyze product family with parent ID {parent_id} containing variants {child_ids}.
        Recommend relevant technical attributes and values in JSON format:
        {{
          "common_attributes": [{{"attribute_id": int, "attribute_name": string}}],
          "variants": [
            {{
              "product_id": int,
              "attributes": [{{"attribute_name": string, "value": string}}]
            }}
          ]
        }}
        """

        payload = {
            "model": self.model,
            "messages": [
                {"role": "system", "content": "You are a PIM specialist analyzing product relationships."},
                {"role": "user", "content": prompt}
            ],
            "max_tokens": 1000
        }

        try:
            response = requests.post(self.api_url, json=payload, headers=self.headers)
            response.raise_for_status()
            return json.loads(response.json()['content'][0]['text'])
        except Exception as e:
            print(f"Claude API Error: {str(e)}")
            return None


def main():
    try:
        env_vars = json.loads(sys.argv[1])  # First argument is env config
        recommender = ClaudeRecommender(env_vars)

        raw_input = sys.stdin.read()
        input_data = json.loads(raw_input)

        families = []

        for item in input_data:
            if not item["child_ids"]:
                continue

            parent_id = item["parent_id"]
            ai_response = recommender.get_attributes_from_claude(parent_id, item["child_ids"])

            if not ai_response:
                continue

            family = {
                "parent_id": parent_id,
                "family_name": f"family-{parent_id}",
                "child_ids": item["child_ids"],
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

        print(json.dumps({
            "success": True,
            "families": families
        }))

    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"JSON Error: {str(e)}"
        }))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))

if __name__ == "__main__":
    main()
