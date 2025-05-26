import os
import pymysql
import json
import argparse
import re
import unicodedata
import socket  # For fallback to system hostname if needed

# Set the USER environment variable to avoid getpass issue on Windows
os.environ['USER'] = 'root'

def slugify(value):
    """Create SEO-friendly slug from product name"""
    value = unicodedata.normalize('NFKD', value).encode('ascii', 'ignore').decode('ascii')
    value = re.sub(r'[^\w\s-]', '', value.lower())
    return re.sub(r'[-\s]+', '-', value).strip('-_')

def main(category_id):
    try:
        # Database config (can replace with real values)
        db_config = {
             "host": "pim-flow-db.ch0qsm2uacmv.us-west-1.rds.amazonaws.com",
            "user": "admin",
            "password": "Y7Btx88Qe0BZg8ihk6Jc",
            "database": "pim_flow_db",
            "cursorclass": pymysql.cursors.DictCursor
        }

        # Fallback for username retrieval (Windows workaround)
        try:
            username = socket.gethostname()  # Use the machine's hostname if user cannot be fetched
        except Exception:
            username = "root"  # Use "root" as fallback user

        # Connect to the database
        connection = pymysql.connect(**db_config)

        try:
            with connection.cursor() as cursor:
                sql = """
                    SELECT 
                        p.id,
                        p.name,
                        p.sku,
                        p.image,
                        b.name AS brand,
                        s_store.name AS store,
                        c.name AS product_family
                    FROM ec_products p
                    INNER JOIN ec_product_category_product cp ON p.id = cp.product_id
                    INNER JOIN ec_product_categories c ON cp.category_id = c.id
                    LEFT JOIN ec_brands b ON p.brand_id = b.id
                    LEFT JOIN mp_stores s_store ON p.store_id = s_store.id
                    WHERE c.id = %s AND p.status = 'published'
                """
                cursor.execute(sql, (category_id,))
                products = cursor.fetchall()
        finally:
            connection.close()

        # Sort and group by common name prefix
        sorted_products = sorted(products, key=lambda x: x['name'])

        groups = []
        current_group = []
        current_prefix = ""

        for product in sorted_products:
            name = product['name']
            if not current_group:
                current_group.append(product)
                current_prefix = name
            else:
                common = os.path.commonprefix([current_prefix, name])
                if common.strip():
                    current_group.append(product)
                    current_prefix = common
                else:
                    groups.append((current_prefix, current_group))
                    current_group = [product]
                    current_prefix = name

        if current_group:
            groups.append((current_prefix, current_group))

        # Format grouped products
        formatted_data = {}
        for prefix, products_in_group in groups:
            formatted_products = []
            for p in products_in_group:
                formatted_products.append({
                    "id": p["id"],
                    "name": p["name"],
                    "sku": p["sku"],
                    "image": p["image"],
                    "brand": p.get("brand"),
                    "store": p.get("store"),
                    "status": "published",
                    "product_family": [p["product_family"]],
                    "taxonomy_path": slugify(p["name"])
                })
            formatted_data[prefix] = formatted_products

        return {
            "success": True,
            "message": "Products retrieved successfully",
            "data": formatted_data
        }

    except Exception as e:
        return {
            "success": False,
            "message": f"Error: {str(e)}",
            "data": {}
        }

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("category_id", type=int)
    args = parser.parse_args()
    print(json.dumps(main(args.category_id), indent=2))
