import os
import json
import requests
from dotenv import load_dotenv
import sys
from collections import Counter

load_dotenv()

class ClaudeRecommender:
    def __init__(self, env_config=None):
        """Initialize the Claude Recommender with environment configurations."""
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

    def get_attributes_from_claude(self, parent_id, child_ids):
        """Get AI-generated attributes using parent/child context."""
        prompt = f"""
        Analyze the following product family with parent ID {parent_id} containing product variants {child_ids}.
        Please identify the top 3 most relevant common attributes shared across all products in the family.
        The attributes should be listed in JSON format, with each attribute having a name and value.
        The returned JSON should contain:
        {{
            "common_attributes": [
                {{"attribute_name": string, "value": string}}
            ],
            "variants": [
                {{
                    "product_id": int,
                    "attributes": [
                        {{"attribute_name": string, "value": string}}
                    ]
                }}
            ]
        }}
        """
        payload = {
            "model": self.model,
            "messages": [
                {"role": "system", "content": "You are an expert analyzing product attributes and identifying the most relevant ones."},
                {"role": "user", "content": prompt}
            ],
            "max_tokens": 1000
        }

        try:
            response = requests.post(self.api_url, json=payload, headers=self.headers)
            response.raise_for_status()
            return json.loads(response.json()['content'][0]['text'])
        except Exception as e:
            print(f"Claude API Error: {str(e)}", file=sys.stderr)
            return None

    def process_common_attributes(self, attributes_data):
        """Process and filter attributes that are common across all products."""
        all_attributes = []
        for product in attributes_data:
            for attribute in product.get('attributes', []):
                all_attributes.append(attribute['attribute_name'])

        # Count how many times each attribute occurs across the products
        attribute_counts = Counter(all_attributes)

        # Find common attributes that are available in every product (i.e., appear in all products)
        common_attributes = [attr for attr, count in attribute_counts.items() if count == len(attributes_data)]
        
        return common_attributes[:3]  # Only return top 3 most common attributes


def process_families(input_data):
    """Process each family and return the common top 3 attributes for each."""
    recommender = ClaudeRecommender()
    families = []

    for item in input_data:
        if not item["child_ids"]:
            continue

        parent_id = item["parent_id"]
        
        # Get AI response for the common attributes of the given product group
        ai_response = recommender.get_attributes_from_claude(parent_id, item["child_ids"])
        
        if not ai_response:
            continue

        # Process the attributes returned by Claude to get the common ones
        common_attributes = recommender.process_common_attributes(ai_response.get("variants", []))

        # If no common attributes, skip this family
        if not common_attributes:
            continue
        
        family = {
            "parent_id": parent_id,
            "family_name": f"family-{parent_id}",
            "child_ids": item["child_ids"],
            "common_attributes": [{"attribute_name": attr, "value": "N/A"} for attr in common_attributes],
            "variants": []
        }

        for variant in ai_response.get("variants", []):
            family["variants"].append({
                "product_id": variant["product_id"],
                "product_name": f"Product {parent_id}-{variant['product_id']}",
                "image": f"https://example.com/{variant['product_id']}.webp",
                "attributes": [{"attribute_name": attr['attribute_name'], "value": attr['value']} for attr in variant.get('attributes', [])]
            })

        families.append(family)

    return families


def main():
    try:
        if len(sys.argv) != 3:
            raise ValueError("Expected exactly two arguments: env_config and input_data")
        
        env_config = json.loads(sys.argv[1])
        input_data = json.loads(sys.argv[2])

        recommender = ClaudeRecommender(env_config)

        # Process families based on the input data
        families = process_families(input_data)

        result = {
            "success": True,
            "families": families
        }

        print(json.dumps(result))

    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))

if __name__ == "__main__":
    main()
