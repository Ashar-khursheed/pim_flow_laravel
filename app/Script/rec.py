import os
import json
import pymysql
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
            self.db_host = env_config.get("DB_HOST")
            self.db_name = env_config.get("DB_DATABASE")
            self.db_user = env_config.get("DB_USERNAME")
            self.db_password = env_config.get("DB_PASSWORD")
        else:
            self.headers = {
                "x-api-key": os.getenv("CLAUDE_API_KEY"),
                "anthropic-version": os.getenv("CLAUDE_VERSION"),
                "Content-Type": "application/json"
            }
            self.api_url = os.getenv("CLAUDE_API_URL")
            self.model = os.getenv("CLAUDE_MODEL")
            self.db_host = os.getenv("DB_HOST")
            self.db_name = os.getenv("DB_DATABASE")
            self.db_user = os.getenv("DB_USERNAME")
            self.db_password = os.getenv("DB_PASSWORD")

    def connect_to_db(self):
        """Connect to the database and return the connection"""
        try:
            connection = pymysql.connect(
                host=self.db_host,
                user=self.db_user,
                password=self.db_password,
                database=self.db_name
            )
            return connection
        except pymysql.MySQLError as e:
            print(f"Error connecting to database: {e}", file=sys.stderr)
            return None

    def get_attributes_from_db(self, child_ids):
        """Get attributes for the given child product IDs"""
        connection = self.connect_to_db()
        if connection is None:
            return []

        try:
            with connection.cursor() as cursor:
                # Query to fetch the attributes for the child products
                query = """
                    SELECT pa.product_id, a.id as attribute_id, a.name as attribute_name, pa.value as attribute_value
                    FROM product_attributes pa
                    JOIN attributes a ON pa.attribute_id = a.id
                    WHERE pa.product_id IN (%s)
                """
                cursor.execute(query, (",".join(map(str, child_ids)),))
                results = cursor.fetchall()

            attributes = {}
            for row in results:
                product_id = row["product_id"]
                attribute = {
                    "attribute_id": row["attribute_id"],
                    "attribute_name": row["attribute_name"],
                    "attribute_value": row["attribute_value"]
                }
                if product_id not in attributes:
                    attributes[product_id] = []
                attributes[product_id].append(attribute)

            return attributes
        finally:
            connection.close()

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
