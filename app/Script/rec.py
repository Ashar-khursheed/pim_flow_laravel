import os
import json
import requests
from dotenv import load_dotenv
import sys
load_dotenv()

class ClaudeRecommender:
    def __init__(self, env_config=None):
        # Use environment variables from config if provided, otherwise use env vars
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
            print(f"Claude API Error: {str(e)}", file=sys.stderr)
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
        # Get arguments from command line
        if len(sys.argv) != 3:
            raise ValueError("Expected exactly two arguments: env_config and input_data")
        
        env_config = json.loads(sys.argv[1])
        input_data = json.loads(sys.argv[2])
        
        # Initialize recommender with env config
        recommender = ClaudeRecommender(env_config)
        
        # Process the families
        families = process_families(input_data)
        
        # Return the result
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