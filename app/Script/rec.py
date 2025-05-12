import os
import json
import requests
import sys
import re
import traceback
import pymysql
from dotenv import load_dotenv
load_dotenv(dotenv_path='.env')  # Adjust path if needed

class DBConfig:
    @property
    def connection(self):
        return {
            'host': os.getenv('DB_HOST'),
            'user': os.getenv('DB_USERNAME'),
            'password': os.getenv('DB_PASSWORD'),
            'database': os.getenv('DB_DATABASE'),
            'charset': 'utf8mb4',
            'cursorclass': pymysql.cursors.DictCursor
        }

class ClaudeRecommender:
    def __init__(self):
        self.headers = {
            "x-api-key": os.getenv("CLAUDE_API_KEY"),
            "anthropic-version": os.getenv("CLAUDE_VERSION"),
            "Content-Type": "application/json"
        }
        self.api_url = os.getenv("CLAUDE_API_URL")
        self.model = os.getenv("CLAUDE_MODEL", "claude-3-sonnet-20240229")

    def get_attributes_from_claude(self, parent_id, child_ids, attributes_data, product_names):
        """Get AI-selected relevant attributes using database-fetched data"""
        try:
            # Debug: Print input data
            print(f"Claude Input - Parent ID: {parent_id}", file=sys.stderr)
            print(f"Child IDs: {child_ids}", file=sys.stderr)
            print("Product Names:", json.dumps(product_names, indent=2), file=sys.stderr)
            print("Attributes Data:", json.dumps(attributes_data, indent=2), file=sys.stderr)

            prompt = f"""
            You are analyzing a product family of commercial kitchen equipment with parent ID {parent_id} and variant IDs {child_ids}.
            Below are the product names and attributes fetched from the database for these variants:

            Product Names:
            {json.dumps(product_names, indent=2)}

            Attributes:
            {json.dumps(attributes_data, indent=2)}

            Select the most relevant technical attributes for this family and its variants. Prioritize attributes such as:
            - Width (e.g., "54 1/8\"")
            - Voltage (e.g., "115 V")
            - Capacity (e.g., "50 cu. ft.")
            - Door Type (e.g., "Glass")
            - Compressor Location (e.g., "Bottom Mount")

            Output a JSON object with the following schema:
            {{
              "common_attributes": [
                {{"attribute_id": int, "attribute_name": string}}
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

            - "common_attributes" should list 2-3 attributes shared across all variants.
            - Each variant should have 2-3 attributes.
            - Use attribute IDs from the provided attributes if available.
            - Ensure attributes are consistent with the product names.
            - Do not include any text, comments, or explanations outside the JSON object.
            """

            payload = {
                "model": self.model,
                "system": "You are a PIM specialist analyzing commercial kitchen equipment specifications. Provide precise, realistic attributes.",
                "messages": [
                    {
                        "role": "user",
                        "content": prompt
                    }
                ],
                "max_tokens": 1000
            }

            response = requests.post(self.api_url, json=payload, headers=self.headers)
            response.raise_for_status()
            content = response.json()
            raw_response = content['content'][0]['text']
            
            print(f"Raw Claude Response: {raw_response}", file=sys.stderr)
            
            try:
                parsed_response = json.loads(raw_response)
            except json.JSONDecodeError:
                json_match = re.search(r'\{.*\}', raw_response, re.DOTALL)
                if json_match:
                    json_string = json_match.group(0)
                    parsed_response = json.loads(json_string)
                else:
                    print("No valid JSON found in Claude response", file=sys.stderr)
                    return None

            print("Parsed Claude Response:", json.dumps(parsed_response, indent=2), file=sys.stderr)
            return parsed_response

        except Exception as e:
            print(f"Error in get_attributes_from_claude: {str(e)}", file=sys.stderr)
            print(traceback.format_exc(), file=sys.stderr)
            return None

def get_product_ids(group_id, connection_config):
    """Fetch product IDs from product_group_items for a given group_id"""
    connection = pymysql.connect(**connection_config)
    
    try:
        with connection.cursor(pymysql.cursors.DictCursor) as cursor:
            sql = """
                SELECT product_id
                FROM product_group_items
                WHERE group_id = %s
            """
            cursor.execute(sql, (group_id,))
            results = cursor.fetchall()
            product_ids = [row['product_id'] for row in results]
            
            print(f"Fetched Product IDs for Group {group_id}: {product_ids}", file=sys.stderr)
            return product_ids
    except Exception as e:
        print(f"Error fetching product IDs: {e}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
        return []
    finally:
        connection.close()

def get_product_data(product_ids, connection_config):
    """Fetch product details from ec_products and related tables"""
    if not product_ids:
        print("No product IDs provided to get_product_data", file=sys.stderr)
        return {}

    connection = pymysql.connect(**connection_config)
    
    try:
        with connection.cursor(pymysql.cursors.DictCursor) as cursor:
            sql = """
                SELECT 
                    p.id,
                    p.name,
                    p.sku,
                    p.image,
                    b.name AS brand,
                    c.name AS product_family,
                    COALESCE(s.key, 'Unknown') AS taxonomy_path
                FROM ec_products p
                INNER JOIN ec_product_category_product cp ON p.id = cp.product_id
                INNER JOIN ec_product_categories c ON cp.category_id = c.id
                LEFT JOIN ec_brands b ON p.brand_id = b.id
                LEFT JOIN slugs s ON p.id = s.reference_id
                WHERE p.id IN %s AND p.status = 'published'
            """
            cursor.execute(sql, (product_ids,))
            results = cursor.fetchall()
            
            print("Product Data Results:", json.dumps(results, indent=2), file=sys.stderr)
            
            product_dict = {
                str(row['id']): {
                    "name": row['name'],
                    "sku": row['sku'],
                    "image": row['image'],
                    "brand": row['brand'],
                    "product_family": row['product_family'],
                    "taxonomy_path": row['taxonomy_path']
                } for row in results
            }
            
            print("Processed Product Dictionary:", json.dumps(product_dict, indent=2), file=sys.stderr)
            return product_dict
    except Exception as e:
        print(f"Error fetching product data: {e}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
        return {}
    finally:
        connection.close()

def get_product_attributes(product_ids, connection_config):
    """Fetch attributes from product_attributes joined with attributes"""
    if not product_ids:
        print("No product IDs provided to get_product_attributes", file=sys.stderr)
        return []

    connection = pymysql.connect(**connection_config)
    
    try:
        with connection.cursor(pymysql.cursors.DictCursor) as cursor:
            sql = """
                SELECT 
                    pa.product_id,
                    pa.attribute_id,
                    pa.attribute_value AS value,
                    COALESCE(a.name, 'Unknown') AS attribute_name
                FROM product_attributes pa
                LEFT JOIN attributes a ON pa.attribute_id = a.id
                WHERE pa.product_id IN %s
            """
            cursor.execute(sql, (product_ids,))
            results = cursor.fetchall()
            
            print("Product Attributes Results:", json.dumps(results, indent=2), file=sys.stderr)
            
            return results
    except Exception as e:
        print(f"Error fetching product attributes: {e}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
        return []
    finally:
        connection.close()

def get_common_family_name(product_names):
    """Extract brand and product family from product names for family_name"""
    if not product_names:
        return "Unknown Family"
    
    print(f"Product Names for Family Name: {product_names}", file=sys.stderr)
    
    words = [name.split() for name in product_names]
    min_length = min(len(w) for w in words)
    common_words = []
    
    for i in range(min_length):
        if all(words[0][i] == w[i] for w in words):
            common_words.append(words[0][i])
        else:
            break
    
    brand = " ".join(common_words[:2]) if len(common_words) >= 2 else product_names[0].split()[0]
    
    family_terms = []
    for name in product_names:
        terms = name.split(',')[0].split()
        descriptive_terms = []
        capture = False
        for term in terms:
            if term in ["Worktop", "Refrigerator"]:
                capture = True
                descriptive_terms.append(term)
            elif capture and term != "Cu.Ft":
                descriptive_terms.append(term)
        if descriptive_terms:
            family_terms.append(" ".join(descriptive_terms))
    
    family_part = family_terms[0] if family_terms else "Refrigerator"
    family_name = f"{brand} {family_part}".strip()
    
    print(f"Generated Family Name: {family_name}", file=sys.stderr)
    
    return family_name or "Unknown Family"

def clean_json(input_str):
    """Remove comments and trailing commas from JSON"""
    lines = []
    for line in input_str.split('\n'):
        line = re.sub(r'//.*', '', line)
        line = re.sub(r',\s*}(?=\s*})', '}', line)
        line = re.sub(r',\s*\](?=\s*\])', ']', line)
        if line.strip():
            lines.append(line)
    return '\n'.join(lines)

def process_families(input_data):
    # Get database connection config
    config = DBConfig().connection
    recommender = ClaudeRecommender()
    families = []

    print("Processing Families - Input Data:", json.dumps(input_data, indent=2), file=sys.stderr)

    for item in input_data:
        parent_id = item.get("parent_id")
        if not parent_id:
            print("Skipping item - No parent_id", file=sys.stderr)
            continue

        child_ids = get_product_ids(parent_id, config)
        if not child_ids:
            print(f"No child IDs found for parent ID {parent_id}", file=sys.stderr)
            continue
        
        product_data = get_product_data(tuple(child_ids), config)
        if not product_data:
            print(f"No product data found for child IDs {child_ids}", file=sys.stderr)
            continue
        
        product_names = [info["name"] for info in product_data.values()]
        family_name = get_common_family_name(product_names)
        
        attributes = get_product_attributes(tuple(child_ids), config)
        if not attributes:
            print(f"No attributes found for child IDs {child_ids}", file=sys.stderr)
            continue
        
        attributes_data = {}
        for attr in attributes:
            pid = str(attr['product_id'])
            if pid not in attributes_data:
                attributes_data[pid] = []
            attributes_data[pid].append({
                "attribute_id": attr['attribute_id'],
                "attribute_name": attr['attribute_name'],
                "value": attr['value']
            })
        
        print("Attributes Data:", json.dumps(attributes_data, indent=2), file=sys.stderr)
        
        ai_response = recommender.get_attributes_from_claude(parent_id, child_ids, attributes_data, product_names)
        if not ai_response or not isinstance(ai_response, dict):
            print("Claude AI did not return a valid response", file=sys.stderr)
            continue

        if not ai_response.get("common_attributes") or not ai_response.get("variants"):
            print("Claude AI response lacks common attributes or variants", file=sys.stderr)
            continue

        family = {
            "parent_id": parent_id,
            "family_name": family_name,
            "child_ids": child_ids,
            "common_attributes": ai_response["common_attributes"],
            "variants": []
        }

        for variant in ai_response["variants"]:
            product_id = variant.get("product_id")
            if product_id not in child_ids:
                print(f"Skipping variant - product ID {product_id} not in child IDs", file=sys.stderr)
                continue

            product_info = product_data.get(str(product_id), {
                "name": f"Product {parent_id}-{product_id}",
                "sku": f"SKU-{product_id}",
                "image": f"https://example.com/{product_id}.webp",
                "brand": "Unknown",
                "product_family": "Unknown",
                "taxonomy_path": "Unknown"
            })

            family["variants"].append({
                "product_id": product_id,
                "product_name": product_info["name"],
                "sku": product_info["sku"],
                "image": product_info["image"] if product_info["image"] else "https://example.com/no_image.webp",
                "brand": product_info["brand"],
                "product_family": product_info["product_family"],
                "taxonomy_path": product_info["taxonomy_path"],
                "attributes": variant.get("attributes", [])
            })

        if family["variants"]:
            families.append(family)
    
    print(f"Total Families Processed: {len(families)}", file=sys.stderr)

    if not families:
        return {
            "success": False,
            "category_id": str(parent_id) if 'parent_id' in locals() else "unknown",
            "error": "No valid families processed",
            "debug_info": "Check system error log for detailed debugging information"
        }

    return {
        "success": True,
        "category_id": str(parent_id),
        "families": families
    }

def main():
    try:
        # Read input from stdin
        raw_input = sys.stdin.read()
        
        # Check if input is empty
        if not raw_input.strip():
            raise ValueError("No input provided")
        
        print(f"Raw Input Received: {raw_input}", file=sys.stderr)
        
        # Clean and parse input JSON
        cleaned_input = clean_json(raw_input)
        print(f"Cleaned Input: {cleaned_input}", file=sys.stderr)
        
        input_data = json.loads(cleaned_input)
        
        # Process families
        result = process_families(input_data)
        
        # Output result as JSON
        print(json.dumps(result, indent=2))
    
    except json.JSONDecodeError as e:
        # Handle JSON parsing errors
        error_details = {
            "success": False,
            "error": f"JSON Error: {str(e)}",
            "received_sample": raw_input[:100],
            "full_error": traceback.format_exc()
        }
        print(json.dumps(error_details, indent=2))
        print(f"JSON Decode Error: {str(e)}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)
    
    except Exception as e:
        # Handle any other unexpected errors
        error_details = {
            "success": False,
            "error": str(e),
            "full_error": traceback.format_exc()
        }
        print(json.dumps(error_details, indent=2))
        print(f"Unexpected Error: {str(e)}", file=sys.stderr)
        print(traceback.format_exc(), file=sys.stderr)

if __name__ == "__main__":
    main()