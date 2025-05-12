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

        # Add debug logging
        print(f"API URL: {self.api_url}", file=sys.stderr)
        print(f"Model: {self.model}", file=sys.stderr)
        print(f"API Key is set: {'Yes' if self.headers.get('x-api-key') else 'No'}", file=sys.stderr)

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

        # Using the messages format for Claude API
        payload = {
            "model": self.model,
            "messages": [
                {"role": "system", "content": "You are an expert analyzing product attributes and identifying the most relevant ones."},
                {"role": "user", "content": prompt}
            ],
            "max_tokens": 1000
        }

        try:
            print(f"Sending request to Claude API for parent_id {parent_id}...", file=sys.stderr)
            response = requests.post(self.api_url, json=payload, headers=self.headers)

            # Print response status and headers for debugging
            print(f"API Response Status: {response.status_code}", file=sys.stderr)
            print(f"API Response Headers: {response.headers}", file=sys.stderr)

            # Handle non-200 responses properly
            if response.status_code != 200:
                print(f"API Error: {response.text}", file=sys.stderr)
                return None

            # Print first part of response for debugging
            resp_text = response.text[:500]
            print(f"API Response (truncated): {resp_text}...", file=sys.stderr)

            # Parse the response JSON
            resp_json = response.json()

            # Handle both possible API response structures
            if 'content' in resp_json and len(resp_json['content']) > 0:
                # New Claude API format (messages API)
                content = resp_json['content'][0]['text']
                print(f"Extracted content: {content[:100]}...", file=sys.stderr)

                # Try to extract JSON from the content
                try:
                    # Look for JSON objects in the response
                    import re
                    json_match = re.search(r'\{.*\}', content, re.DOTALL)
                    if json_match:
                        extracted_json = json_match.group(0)
                        return json.loads(extracted_json)
                    else:
                        print("No JSON object found in response", file=sys.stderr)
                        return None
                except json.JSONDecodeError as e:
                    print(f"Failed to parse JSON from content: {e}", file=sys.stderr)
                    return None
            elif 'completion' in resp_json:
                # Old Claude API format
                try:
                    # Look for JSON objects in the response
                    import re
                    json_match = re.search(r'\{.*\}', resp_json['completion'], re.DOTALL)
                    if json_match:
                        extracted_json = json_match.group(0)
                        return json.loads(extracted_json)
                    else:
                        print("No JSON object found in completion", file=sys.stderr)
                        return None
                except json.JSONDecodeError as e:
                    print(f"Failed to parse JSON from completion: {e}", file=sys.stderr)
                    return None
            else:
                print("Unexpected API response format", file=sys.stderr)
                return None

        except Exception as e:
            print(f"Claude API Error: {str(e)}", file=sys.stderr)
            import traceback
            traceback.print_exc(file=sys.stderr)
            return None

    def process_common_attributes(self, attributes_data):
        """Process and filter attributes that are common across all products."""
        if not attributes_data:
            print("No attributes data provided", file=sys.stderr)
            return []

        all_attributes = []
        for product in attributes_data:
            for attribute in product.get('attributes', []):
                all_attributes.append(attribute['attribute_name'])

        # Count how many times each attribute occurs across the products
        attribute_counts = Counter(all_attributes)

        print(f"Attribute counts: {dict(attribute_counts)}", file=sys.stderr)

        # Find common attributes that are available in every product (i.e., appear in all products)
        common_attributes = [attr for attr, count in attribute_counts.items() if count == len(attributes_data)]

        print(f"Common attributes: {common_attributes}", file=sys.stderr)

        return common_attributes[:3]  # Only return top 3 most common attributes


def process_families(input_data):
    """Process each family and return the common top 3 attributes for each."""
    recommender = ClaudeRecommender()
    families = []

    print(f"Processing {len(input_data)} families...", file=sys.stderr)

    for idx, item in enumerate(input_data):
        print(f"Processing family {idx+1}/{len(input_data)}", file=sys.stderr)

        if not item.get("child_ids"):
            print(f"Skipping family with parent_id {item.get('parent_id')} - no child_ids", file=sys.stderr)
            continue

        parent_id = item["parent_id"]
        child_ids = item["child_ids"]

        print(f"Processing parent_id: {parent_id} with {len(child_ids)} children", file=sys.stderr)

        # Get AI response for the common attributes of the given product group
        ai_response = recommender.get_attributes_from_claude(parent_id, child_ids)

        if not ai_response:
            print(f"No AI response for parent_id {parent_id}, skipping", file=sys.stderr)
            continue

        print(f"AI response keys: {ai_response.keys()}", file=sys.stderr)

        # Check for expected data structure
        if "variants" not in ai_response:
            print(f"Missing 'variants' in AI response for parent_id {parent_id}", file=sys.stderr)
            continue

        # Process the attributes returned by Claude to get the common ones
        common_attributes = recommender.process_common_attributes(ai_response.get("variants", []))

        # If no common attributes, skip this family
        if not common_attributes:
            print(f"No common attributes found for parent_id {parent_id}", file=sys.stderr)
            continue

        print(f"Found {len(common_attributes)} common attributes for parent_id {parent_id}", file=sys.stderr)

        family = {
            "parent_id": parent_id,
            "family_name": f"family-{parent_id}",
            "child_ids": item["child_ids"],
            "common_attributes": [{"attribute_name": attr, "value": "N/A"} for attr in common_attributes],
            "variants": []
        }

        # Process each variant from the AI response
        for variant in ai_response.get("variants", []):
            if "product_id" not in variant:
                print(f"Missing product_id in variant for parent_id {parent_id}", file=sys.stderr)
                continue

            family["variants"].append({
                "product_id": variant["product_id"],
                "product_name": f"Product {parent_id}-{variant['product_id']}",
                "image": f"https://example.com/{variant['product_id']}.webp",
                "attributes": [{"attribute_name": attr['attribute_name'], "value": attr['value']}
                              for attr in variant.get('attributes', [])]
            })

        families.append(family)
        print(f"Added family for parent_id {parent_id}", file=sys.stderr)

    print(f"Processed {len(families)} families successfully", file=sys.stderr)
    return families


def main():
    try:
        print("Starting script...", file=sys.stderr)

        if len(sys.argv) != 3:
            print(f"Expected exactly two arguments, but got {len(sys.argv)-1}", file=sys.stderr)
            raise ValueError("Expected exactly two arguments: env_config and input_data")

        print("Parsing arguments...", file=sys.stderr)
        env_config = json.loads(sys.argv[1])
        input_data = json.loads(sys.argv[2])

        print(f"Initializing recommender...", file=sys.stderr)
        recommender = ClaudeRecommender(env_config)

        # Process families based on the input data
        print(f"Processing families...", file=sys.stderr)
        families = process_families(input_data)

        result = {
            "success": True,
            "families": families
        }

        print(f"Returning result with {len(families)} families", file=sys.stderr)
        print(json.dumps(result))

    except Exception as e:
        print(f"Error in main: {str(e)}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        print(json.dumps({
            "success": False,
            "error": str(e)
        }))

if __name__ == "__main__":
    main()