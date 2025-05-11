import os
import json
import requests
from dotenv import load_dotenv
import json
import sys
load_dotenv()

class ClaudeRecommender:
    def __init__(self):
        self.headers = {
            "x-api-key": os.getenv("CLAUDE_API_KEY"),
            "anthropic-version": os.getenv("CLAUDE_VERSION"),
            "Content-Type": "application/json"
        }
        self.api_url = os.getenv("CLAUDE_API_URL")
        self.model = os.getenv("CLAUDE_MODEL")

    def get_attributes_from_claude(self, parent_id, child_ids):
        """Get AI-generated attributes using parent/child context"""
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
                {
                    "role": "system",
                    "content": "You are a PIM specialist analyzing product relationships. Focus on technical specifications."
                },
                {
                    "role": "user",
                    "content": prompt
                }
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

def process_families(input_data):
    recommender = ClaudeRecommender()
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
    
    return families



def main():
    try:
        # Read all input from stdin
        raw_input = sys.stdin.read()
        
        # Debug: Check what's being received
        print("Received raw input:", repr(raw_input[:100]))  # First 100 characters
        
        input_data = json.loads(raw_input)
        
        # Rest of your processing code
        result = {
            "success": True,
            "families": []  # Your actual processing here
        }
        
        print(json.dumps(result, indent=2))
        
    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"JSON Error: {str(e)}",
            "received_sample": raw_input[:100]  # First 100 chars for debugging
        }, indent=2))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }, indent=2))

if __name__ == "__main__":
    main()