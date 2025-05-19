import os
import json
import requests
import sys
import re
import traceback
import pymysql
from dotenv import load_dotenv
from itertools import combinations
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
        self.model = os.getenv("CLAUDE_MODEL")

    def get_attributes_from_claude(self, child_ids, attributes_data, product_names):
        """Get AI-selected relevant attributes using database-fetched data"""
        prompt = f"""
        You are analyzing a product family of commercial kitchen equipment with variant IDs {child_ids}.
        Below are the product names and attributes fetched from the database:

        Product Names:
        {json.dumps(product_names, indent=2)}

        Attributes:
        {json.dumps(attributes_data, indent=2)}

        Select the three most relevant technical attributes for this product family based on the product names and available attributes.
        These attributes should be shared across variants that have attributes and used consistently in both common_attributes and variants.
        Prioritize attributes critical for comparing variants (e.g., for chef bases: Width, Capacity, Number of Drawers; for ovens: Temperature Range, Fuel Type, Oven Capacity).
        If attributes are missing for some variants, infer realistic attributes based on product names.

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
        - Always make sure that each product is returned with the same json format, if the product is not in the database, return the json with those empty fields.
        - "common_attributes" must list exactly three attributes, and they should be present inside the database necessarily.
        - Each variant in "variants" should list these three attributes with specific values, using database values when available or inferred values otherwise.
        - Include all provided variant IDs, even those without attributes.
        - Use attribute IDs from the provided attributes only.
        - ALWAYS LIST PRODUCTS EVEN IF THEY DON'T HAVE RELEVANT ATTRIBUTES, JUST SEND A MESSAGE IN THE ATTRIBUTE COLUMN FOR THEM, BUT ALWAYS DISPLAY ALL THE VARIANTS.
        - ALWAYS MAKE SURE THAT ALL VARIANTS OR PRODUCTS ARE FETCHED CORRECTLY FROM THE DATABASE WHICH ARE PRESENT INSIDE THE INPUT AS VARIANTS INCLUDING THEIR ATTRIBUTES.
        - Ensure attributes are consistent with product names and relevant to the product type and are present inside the database.
        - Do not include text, comments, or explanations outside the JSON object.

        Example for chef bases, and only for chef base, if the product is different and the family is different then:
        {{
          "common_attributes": [
            {{"attribute_id": 597, "attribute_name": "Width"}},
            {{"attribute_id": 194, "attribute_name": "Capacity"}},
            {{"attribute_id": 77, "attribute_name": "Number of Drawers"}}
          ],
          "variants": [
            {{
              "product_id": 21154,
              "attributes": [
                {{"attribute_name": "Width", "value": "112\""}},
                {{"attribute_name": "Capacity", "value": "20.78 Cu.Ft"}},
                {{"attribute_name": "Number of Drawers", "value": "6 drawers"}}
              ]
            }}
          ]
        }}
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

        try:
            response = requests.post(self.api_url, json=payload, headers=self.headers)
            response.raise_for_status()
            content = response.json()
            raw_response = content['content'][0]['text']
            
            try:
                return json.loads(raw_response)
            except json.JSONDecodeError:
                json_match = re.search(r'\{.*\}', raw_response, re.DOTALL)
                if json_match:
                    json_string = json_match.group(0)
                    return json.loads(json_string)
                else:
                    raise ValueError("No valid JSON found in Claude response")
        except requests.exceptions.RequestException as e:
            print(f"API Error: {str(e)}", file=sys.stderr)
            return None

def get_product_data(child_ids):
    """Fetch product details from ec_products and related tables"""
    config = DBConfig().connection
    connection = pymysql.connect(**config)
    
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
                    COALESCE((
                        SELECT GROUP_CONCAT(c2.name SEPARATOR ' > ')
                        FROM ec_product_categories c2
                        WHERE c2.id IN (
                            WITH RECURSIVE category_path AS (
                                SELECT id, name, parent_id
                                FROM ec_product_categories
                                WHERE id = cp.category_id
                                UNION ALL
                                SELECT c3.id, c3.name, c3.parent_id
                                FROM ec_product_categories c3
                                INNER JOIN category_path cp2 ON c3.id = cp2.parent_id
                            )
                            SELECT id FROM category_path
                        )
                    ), 'Unknown') AS taxonomy_path
                FROM ec_products p
                LEFT JOIN ec_product_category_product cp ON p.id = cp.product_id
                LEFT JOIN ec_product_categories c ON cp.category_id = c.id
                LEFT JOIN ec_brands b ON p.brand_id = b.id
                WHERE p.id IN %s 
                GROUP BY p.id
            """
            cursor.execute(sql, (child_ids,))
            results = cursor.fetchall()
            return {
                str(row['id']): {
                    "name": row['name'],
                    "sku": row['sku'],
                    "image": row['image'],
                    "brand": row['brand'],
                    "product_family": row['product_family'],
                    "taxonomy_path": row['taxonomy_path']
                } for row in results
            }
    except Exception as e:
        print(f"DB Error in get_product_data: {str(e)}", file=sys.stderr)
        return {}
    finally:
        connection.close()

def get_product_attributes(child_ids):
    """Fetch attributes from product_attributes and attributes"""
    config = DBConfig().connection
    connection = pymysql.connect(**config)
    
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
            cursor.execute(sql, (child_ids,))
            results = cursor.fetchall()
            return results
    except Exception as e:
        print(f"DB Error in get_product_attributes: {str(e)}", file=sys.stderr)
        return []
    finally:
        connection.close()

def get_common_family_name(product_names):
    """Extract brand and product family from product names for family_name"""
    if not product_names:
        return "Unknown Family"
    
    words = [name.split() for name in product_names]
    min_length = min(len(w) for w in words)
    common_words = []
    
    for i in range(min_length):
        if all(words[0][i] == w[i] for w in words):
            common_words.append(words[0][i])
        else:
            break
    
    brand = common_words[0] if common_words else product_names[0].split()[0]
    
    family_terms = []
    for name in product_names:
        terms = name.split(',')[0].split()
        descriptive_terms = []
        capture = False
        for term in terms:
            if term in ["Worktop", "Refrigerator", "Freezer", "Oven", "Range", "Chef", "Base"]:
                capture = True
                descriptive_terms.append(term)
            elif capture and term not in ["Cu.Ft", "36\"", "112\"", "120\""]:
                descriptive_terms.append(term)
        if descriptive_terms:
            family_terms.append(" ".join(descriptive_terms))
    
    family_part = family_terms[0] if family_terms else "Equipment"
    family_name = f"{brand} {family_part}".strip()
    
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
    recommender = ClaudeRecommender()
    families = []

    for item in input_data:
        child_ids = item.get("child_ids", [])
        parent_id = item.get("parent_id")
        
        if len(child_ids) < 2:
            return {
                "success": False,
                "error": "At least 2 child_ids required"
            }
        
        # Fetch product data and attributes for ALL child IDs
        product_data = get_product_data(tuple(child_ids))
        attributes = get_product_attributes(tuple(child_ids))
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

        # Get product names for all products with data
        valid_ids = [pid for pid in child_ids if str(pid) in product_data]
        product_names = [product_data[str(pid)]["name"] for pid in valid_ids if str(pid) in product_data]
        family_name = get_common_family_name(product_names)
        
        # Select up to 5 products for sending to Claude (prioritize those with attributes)
        # This is just for the API call to determine common attributes, we'll still return ALL products
        sample_ids = []
        
        # First add products with attributes
        for pid in valid_ids:
            if str(pid) in attributes_data and pid not in sample_ids:
                sample_ids.append(pid)
                if len(sample_ids) >= 5:  # Limit sample size for API efficiency
                    break
        
        # Then add more products without attributes if needed
        if len(sample_ids) < 3:
            for pid in valid_ids:
                if pid not in sample_ids:
                    sample_ids.append(pid)
                    if len(sample_ids) >= 3:
                        break
        
        if len(sample_ids) < 2:
            sample_ids = child_ids[:2]  # Use first two IDs as fallback
            
        # Get representative sample product names
        sample_names = [product_data.get(str(pid), {"name": f"Product {pid}"})["name"] for pid in sample_ids]
        
        # Get AI recommendations based on the sample
        ai_response = recommender.get_attributes_from_claude(sample_ids, 
                                                             {pid: attributes_data.get(str(pid), []) for pid in sample_ids}, 
                                                             sample_names)
        
        if not ai_response or not isinstance(ai_response, dict):
            ai_response = {"common_attributes": [], "variants": []}

        # Extract the 3 common attributes as recommended by Claude
        common_attributes = ai_response.get("common_attributes", [])[:3]
        
        # Create a lookup for variant attributes from Claude's response
        ai_variant_data = {v["product_id"]: v.get("attributes", []) for v in ai_response.get("variants", [])}
        
        family = {
            "parent_id": parent_id,
            "family_name": family_name,
            "child_ids": child_ids,
            "common_attributes": common_attributes,
            "variants": []
        }

        # Add ALL variants from child_ids to the result
        for product_id in child_ids:
            pid_str = str(product_id)
            is_in_db = pid_str in product_data
            
            # Use default values if product not found in DB
            product_info = product_data.get(pid_str, {
                "name": f"Product {product_id}",
                "sku": f"SKU-{product_id}",
                "image": None,
                "brand": "Unknown",
                "product_family": "Unknown",
                "taxonomy_path": "Unknown"
            })
            
            # Get attributes for this product (either from DB or AI-inferred)
            product_attributes = attributes_data.get(pid_str, [])
            ai_attributes = ai_variant_data.get(product_id, [])
            
            # If we have DB attributes but no AI attributes for this product,
            # try to match the common_attributes with the DB attributes
            matched_attributes = []
            
            if common_attributes and product_attributes and not ai_attributes:
                # Extract attribute names from common_attributes
                common_attr_names = [attr["attribute_name"] for attr in common_attributes]
                
                # Match product attributes to common attributes
                for common_name in common_attr_names:
                    found = False
                    for attr in product_attributes:
                        if attr["attribute_name"] == common_name:
                            matched_attributes.append({
                                "attribute_name": common_name,
                                "value": attr["value"]
                            })
                            found = True
                            break
                    
                    if not found:
                        # Add placeholder for missing attribute
                        matched_attributes.append({
                            "attribute_name": common_name,
                            "value": "Not specified"
                        })
            
            # Use AI attributes, matched attributes from DB, or empty list
            final_attributes = ai_attributes if ai_attributes else matched_attributes
            
            variant = {
                "product_id": product_id,
                "product_name": product_info["name"],
                "sku": product_info["sku"],
                "image": product_info["image"] if product_info["image"] else "https://example.com/no_image.webp",
                "brand": product_info["brand"],
                "product_family": product_info["product_family"],
                "taxonomy_path": product_info["taxonomy_path"],
                "in_database": is_in_db,
                "attributes": final_attributes
            }
            
            # Add a message if no attributes available
            if not final_attributes:
                variant["message"] = "Attributes not available for this product"
                
            family["variants"].append(variant)

        families.append(family)
    
    if not families:
        return {
            "success": False,
            "error": "No valid families processed"
        }

    return {
        "success": True,
        "families": families
    }

def main():
    try:
        raw_input = sys.stdin.read()
        if not raw_input.strip():
            raise ValueError("No input provided")
        cleaned_input = clean_json(raw_input)
        input_data = json.loads(cleaned_input)
        result = process_families(input_data)
        print(json.dumps(result, indent=2, ensure_ascii=False))
    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"JSON Error: {str(e)}",
            "received_sample": raw_input[:100]
        }, indent=2))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e)
        }, indent=2))

if __name__ == "__main__":
    main()