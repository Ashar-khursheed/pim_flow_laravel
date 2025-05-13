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
        self.model = os.getenv("CLAUDE_MODEL", "claude-3-sonnet-20240229")

    def find_common_and_best_attributes(self, product_ids, attributes_data, product_names):
        """
        Find common attributes across products and select the most relevant ones
        """
        # Group attributes by attribute name
        attribute_groups = {}
        for pid, attrs in attributes_data.items():
            for attr in attrs:
                name = attr['attribute_name']
                value = attr['value']
                if name not in attribute_groups:
                    attribute_groups[name] = {}
                attribute_groups[name][pid] = value

        # Find attributes present in all products
        common_attributes = {}
        for name, values in attribute_groups.items():
            if len(values) == len(product_ids):
                common_attributes[name] = values

        # If no common attributes, return None
        if not common_attributes:
            return None

        # If more than 3 common attributes, use Claude to select top 3
        if len(common_attributes) > 3:
            prompt = f"""
            You are analyzing commercial kitchen equipment with the following common attributes:
            {json.dumps(common_attributes, indent=2)}

            Product Names: {json.dumps(product_names, indent=2)}

            Select the three most relevant technical attributes for these products.
            Prioritize attributes that:
            1. Are critical for comparing product variants
            2. Provide meaningful differentiation
            3. Are technical and measurable

            Respond ONLY with a JSON array of the three attribute names you select.
            """

            payload = {
                "model": self.model,
                "system": "You are a PIM specialist analyzing product specifications. Be precise and strategic.",
                "messages": [{"role": "user", "content": prompt}],
                "max_tokens": 100
            }

            try:
                response = requests.post(self.api_url, json=payload, headers=self.headers)
                response.raise_for_status()
                content = response.json()
                raw_response = content['content'][0]['text']
                
                # Extract JSON array
                json_match = re.search(r'\[.*?\]', raw_response, re.DOTALL)
                if json_match:
                    selected_attrs = json.loads(json_match.group(0))
                    common_attributes = {k: common_attributes[k] for k in selected_attrs if k in common_attributes}
                else:
                    # Fallback to first 3 if JSON parsing fails
                    common_attributes = dict(list(common_attributes.items())[:3])
            except Exception:
                # Fallback to first 3 if Claude fails
                common_attributes = dict(list(common_attributes.items())[:3])

        # Prepare the result
        result = {
            "common_attributes": [
                {"attribute_name": name, "attribute_id": i+1} 
                for i, name in enumerate(common_attributes.keys())
            ],
            "variants": []
        }

        # Add variants with their attribute values
        for pid in product_ids:
            variant_attrs = []
            for attr_name in common_attributes.keys():
                value = common_attributes[attr_name].get(str(pid), "N/A")
                variant_attrs.append({
                    "attribute_name": attr_name,
                    "value": value
                })
            
            result["variants"].append({
                "product_id": pid,
                "attributes": variant_attrs
            })

        return result

def get_product_data(product_ids):
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
                INNER JOIN ec_product_category_product cp ON p.id = cp.product_id
                INNER JOIN ec_product_categories c ON cp.category_id = c.id
                LEFT JOIN ec_brands b ON p.brand_id = b.id
                WHERE p.id IN %s AND p.status = 'published'
            """
            cursor.execute(sql, (product_ids,))
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
        print(f"Error in get_product_data: {e}")
        return {}
    finally:
        connection.close()

def get_product_attributes(product_ids):
    """Fetch attributes from product_attributes joined with attributes"""
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
            cursor.execute(sql, (product_ids,))
            results = cursor.fetchall()
            
            attributes_data = {}
            for row in results:
                pid = str(row['product_id'])
                if pid not in attributes_data:
                    attributes_data[pid] = []
                attributes_data[pid].append({
                    "attribute_id": row['attribute_id'],
                    "attribute_name": row['attribute_name'],
                    "value": row['value']
                })
            
            return attributes_data
    except Exception as e:
        print(f"Error in get_product_attributes: {e}")
        return {}
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
            if term in ["Worktop", "Refrigerator"]:
                capture = True
                descriptive_terms.append(term)
            elif capture and term not in ["Cu.Ft", "27.5\"", "48.3\"", "60.2\""]:
                descriptive_terms.append(term)
        if descriptive_terms:
            family_terms.append(" ".join(descriptive_terms))
    
    family_part = family_terms[0] if family_terms else "Product"
    family_name = f"{brand} {family_part}".strip()
    
    return family_name or "Unknown Family"

def process_families(input_data):
    recommender = ClaudeRecommender()
    families = []

    for item in input_data:
        parent_id = item.get("parent_id")
        input_child_ids = item.get("child_ids", [])
        
        # Use input child IDs instead of fetching from database
        child_ids = [str(pid) for pid in input_child_ids]
        
        if not child_ids:
            continue
        
        product_data = get_product_data(tuple(child_ids))
        if not product_data:
            continue
        
        product_names = [info["name"] for info in product_data.values()]
        family_name = get_common_family_name(product_names)
        
        attributes = get_product_attributes(tuple(child_ids))
        if not attributes:
            continue
        
        # Find common and best attributes
        ai_response = recommender.find_common_and_best_attributes(child_ids, attributes, product_names)
        
        if not ai_response or not ai_response.get("common_attributes") or not ai_response.get("variants"):
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
    
    if not families:
        return {
            "success": False,
            "category_id": str(parent_id) if 'parent_id' in locals() else "unknown",
            "error": "No valid families processed"
        }

    return {
        "success": True,
        "category_id": str(parent_id),
        "families": families
    }

def main():
    try:
        raw_input = sys.stdin.read()
        if not raw_input.strip():
            raise ValueError("No input provided")
        
        # Remove comments and clean JSON
        cleaned_input = re.sub(r'//.*', '', raw_input)
        input_data = json.loads(cleaned_input)
        
        result = process_families(input_data)
        print(json.dumps(result, indent=2))
    except json.JSONDecodeError as e:
        print(json.dumps({
            "success": False,
            "error": f"JSON Error: {str(e)}",
            "received_sample": raw_input[:100]
        }, indent=2))
    except Exception as e:
        print(json.dumps({
            "success": False,
            "error": str(e),
            "trace": traceback.format_exc()
        }, indent=2))

if __name__ == "__main__":
    main()