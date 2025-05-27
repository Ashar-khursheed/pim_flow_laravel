# -*- coding: utf-8 -*-
import sys
import json
import re
from slugify import slugify
import anthropic
import os
from dotenv import load_dotenv
import pymysql

# UTF-8 setup
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")

load_dotenv(dotenv_path='.env')

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

# Configuration
client = anthropic.Anthropic(
    api_key=os.getenv('CLAUDE_API_KEY')
)

def generate_sales_seo(name, keyword):
    """Generate clean, sales-focused SEO content"""
    
    prompt = f"""
Create high-converting SEO content for '{name}' targeting '{keyword}' in UAE market.

Write natural sales-focused content that makes people want to click and buy.

Generate:

1. META TITLE
   - Lead with strongest benefit
   - Include keyword naturally
   - Make it irresistible to click

2. TITLE TAG  
   - Different angle from meta title
   - Focus on results/transformation
   - Strong appeal

3. OG TITLE
   - Most engaging social media angle
   - End with relevant emoji
   - Maximum shareability

4. META DESCRIPTION
   - Start with compelling benefit
   - Include 2-3 key advantages
   - End with strong call-to-action
   - No emojis
   - Complete sentences only

5. OG DESCRIPTION
   - Longer detailed version
   - Include trust elements
   - End with emoji
   - Perfect for social sharing

Make each element unique and compelling. Focus on what customers actually want to hear.

Response format:
Meta Title: [title]
Title Tag: [title]
OG Title: [title]
Meta Description: [description]
OG Description: [description]
"""

    try:
        response = client.messages.create(
            model="claude-3-5-sonnet-20241022",
            max_tokens=600,
            temperature=0.7,
            messages=[{"role": "user", "content": prompt}]
        )
        
        seo_fields = parse_seo_response(response.content[0].text)
        content_paragraphs = generate_content_paragraphs(keyword, name)
        
        return {**seo_fields, **content_paragraphs}
        
    except Exception as e:
        print(f"API Error: {e}", file=sys.stderr)
        return generate_fallback_seo(name, keyword)

def parse_seo_response(text):
    """Parse Claude's response into clean SEO fields with proper length limits"""
    fields = {}
    
    patterns = {
        'meta_title': r'Meta Title:\s*(.+?)(?=\n|Title Tag:|$)',
        'title_tag': r'Title Tag:\s*(.+?)(?=\n|OG Title:|$)', 
        'og_title': r'OG Title:\s*(.+?)(?=\n|Meta Description:|$)',
        'meta_description': r'Meta Description:\s*(.+?)(?=\n|OG Description:|$)',
        'og_description': r'OG Description:\s*(.+?)(?=\n|$)'
    }
    
    # Define safe length limits for database
    length_limits = {
        'meta_title': 60,
        'title_tag': 65,
        'og_title': 60,
        'meta_description': 160,
        'og_description': 200  # Safe limit for most databases
    }
    
    for field, pattern in patterns.items():
        match = re.search(pattern, text, re.IGNORECASE | re.DOTALL)
        if match:
            value = match.group(1).strip()
            # Clean up
            value = re.sub(r'^["\']|["\']

def generate_content_paragraphs(keyword, name):
    """Generate conversion-focused content paragraphs with proper length limits"""
    
    content_prompt = f"""
Create 4 compelling sales paragraphs for '{keyword}' that will convert visitors into customers.

Requirements:
- Paragraph 1: Focus on main benefit with '{keyword}' mentioned once (max 200 chars)
- Paragraph 2: Highlight unique advantages (max 200 chars)
- Paragraph 3: Build trust and mention UAE (max 200 chars)
- Paragraph 4: Create urgency for action (max 200 chars)

Each paragraph should end with a strong call-to-action.
Also create 6 trending hashtags for UAE market.

Keep paragraphs concise and impactful.

Format:
Paragraph 1: [content]
Paragraph 2: [content] 
Paragraph 3: [content]
Paragraph 4: [content]
Popular Tags: ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6"]
"""

    try:
        response = client.messages.create(
            model="claude-3-5-sonnet-20241022",
            max_tokens=800,
            temperature=0.7,
            messages=[{"role": "user", "content": content_prompt}]
        )
        
        content = response.content[0].text
        paragraphs = {}
        
        # Extract paragraphs with length limits
        for i in range(1, 5):
            pattern = f"Paragraph {i}:\\s*(.+?)(?=\\nParagraph {i+1}:|\\nPopular Tags:|$)"
            match = re.search(pattern, content, re.DOTALL)
            if match:
                para_text = match.group(1).strip()
                # Limit to 200 characters
                if len(para_text) > 200:
                    words = para_text[:200].split(' ')
                    if len(words) > 1:
                        words.pop()  # Remove last potentially incomplete word
                    para_text = ' '.join(words)
                    if not para_text.endswith(('.', '!', '?')):
                        para_text += '.'
                paragraphs[f"paragraph_{i}"] = para_text
            else:
                paragraphs[f"paragraph_{i}"] = f"Quality {keyword} for your business needs. Order now!"
        
        # Extract tags
        tags_match = re.search(r'Popular Tags:\s*(\[.*?\])', content, re.DOTALL)
        if tags_match:
            try:
                tags = json.loads(tags_match.group(1))
                paragraphs["popular_tags"] = json.dumps(tags[:6])
            except:
                paragraphs["popular_tags"] = json.dumps([
                    f"{keyword} UAE", f"commercial {keyword}", f"restaurant {keyword}",
                    f"hotel {keyword}", f"cafe {keyword}", f"kitchen {keyword}"
                ])
        else:
            paragraphs["popular_tags"] = json.dumps([
                f"{keyword} UAE", f"commercial {keyword}", f"restaurant {keyword}",
                f"hotel {keyword}", f"cafe {keyword}", f"kitchen {keyword}"
            ])
        
        return paragraphs
        
    except Exception as e:
        print(f"Content generation error: {e}", file=sys.stderr)
        return generate_fallback_content(keyword)

def generate_fallback_seo(name, keyword):
    """Simple fallback SEO if API fails - with proper length limits"""
    return {
        'meta_title': f"Premium {keyword} UAE - Best Prices | Horeca Store"[:60],
        'title_tag': f"Buy {keyword} Online UAE - Fast Delivery Available"[:65],
        'og_title': f"Top Quality {keyword} UAE - Shop Now! 🔥"[:60],
        'meta_description': f"Get the best {keyword} in UAE with fast delivery and warranty. Trusted by restaurants and hotels nationwide. Order today!"[:160],
        'og_description': f"Discover premium {keyword} perfect for UAE businesses. Professional quality with full warranty and expert support. 🚀"[:200]
    }

def generate_fallback_content(keyword):
    """Fallback content paragraphs with proper length limits"""
    return {
        'paragraph_1': f"Transform your kitchen with premium {keyword} designed for UAE businesses. Professional quality meets great value. Shop now!"[:200],
        'paragraph_2': f"Get exclusive features and robust performance that outlasts the competition. Limited time special pricing. Order today!"[:200],
        'paragraph_3': f"Trusted by top restaurants across UAE with full warranty and expert support. Join satisfied customers nationwide. Buy now!"[:200],
        'paragraph_4': f"Stock is moving fast! Secure your {keyword} before prices go up. Don't miss this opportunity. Get yours now!"[:200],
        'popular_tags': json.dumps([
            f"{keyword} UAE", f"commercial {keyword}", f"restaurant {keyword}",
            f"hotel {keyword}", f"professional {keyword}", f"kitchen {keyword}"
        ])
    }

def main():
    """Main function to process input and generate SEO fields"""
    try:
        data = json.load(sys.stdin)
        keyword = data.get("primary_keyword") or data["relational_name"]
        
        # Generate SEO fields
        seo_fields = generate_sales_seo(
            data["relational_name"],
            keyword
        )
        
        # Merge with original data
        result = {**data, **seo_fields}
        
        # Generate URL if not provided
        if not data.get("url"):
            result["url"] = f"uae-{slugify(data['relational_name'])}"
        
        # Output result
        print(json.dumps(result, indent=2, ensure_ascii=False))
        
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid JSON input: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)
    except KeyError as e:
        print(json.dumps({"error": f"Missing required field: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": f"Unexpected error: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main(), '', value).strip()
            value = re.sub(r'\s+', ' ', value)
            value = value.replace('&', 'and')
            
            # Apply length limit
            max_length = length_limits.get(field, 255)
            if len(value) > max_length:
                # Trim to complete words
                words = value[:max_length].split(' ')
                if len(words) > 1:
                    words.pop()  # Remove last potentially incomplete word
                value = ' '.join(words)
                
                # Ensure proper ending
                if field in ['meta_description', 'og_description']:
                    if not value.endswith(('.', '!', '?')):
                        value += '.'
            
            fields[field] = value
    
    return fields

def generate_content_paragraphs(keyword, name):
    """Generate conversion-focused content paragraphs"""
    
    content_prompt = f"""
Create 4 compelling sales paragraphs for '{keyword}' that will convert visitors into customers.

Requirements:
- Paragraph 1: Focus on main benefit with '{keyword}' mentioned once
- Paragraph 2: Highlight unique advantages
- Paragraph 3: Build trust and mention UAE
- Paragraph 4: Create urgency for action

Each paragraph should be around 200-250 characters and end with a strong call-to-action.
Also create 6 trending hashtags for UAE market.

Format:
Paragraph 1: [content]
Paragraph 2: [content] 
Paragraph 3: [content]
Paragraph 4: [content]
Popular Tags: ["tag1", "tag2", "tag3", "tag4", "tag5", "tag6"]
"""

    try:
        response = client.messages.create(
            model="claude-3-5-sonnet-20241022",
            max_tokens=800,
            temperature=0.7,
            messages=[{"role": "user", "content": content_prompt}]
        )
        
        content = response.content[0].text
        paragraphs = {}
        
        # Extract paragraphs
        for i in range(1, 5):
            pattern = f"Paragraph {i}:\\s*(.+?)(?=\\nParagraph {i+1}:|\\nPopular Tags:|$)"
            match = re.search(pattern, content, re.DOTALL)
            if match:
                paragraphs[f"paragraph_{i}"] = match.group(1).strip()
            else:
                paragraphs[f"paragraph_{i}"] = f"Quality {keyword} for your business needs. Order now!"
        
        # Extract tags
        tags_match = re.search(r'Popular Tags:\s*(\[.*?\])', content, re.DOTALL)
        if tags_match:
            try:
                tags = json.loads(tags_match.group(1))
                paragraphs["popular_tags"] = json.dumps(tags[:6])
            except:
                paragraphs["popular_tags"] = json.dumps([
                    f"{keyword} UAE", f"commercial {keyword}", f"restaurant {keyword}",
                    f"hotel {keyword}", f"cafe {keyword}", f"kitchen {keyword}"
                ])
        else:
            paragraphs["popular_tags"] = json.dumps([
                f"{keyword} UAE", f"commercial {keyword}", f"restaurant {keyword}",
                f"hotel {keyword}", f"cafe {keyword}", f"kitchen {keyword}"
            ])
        
        return paragraphs
        
    except Exception as e:
        print(f"Content generation error: {e}", file=sys.stderr)
        return generate_fallback_content(keyword)

def generate_fallback_seo(name, keyword):
    """Simple fallback SEO if API fails"""
    return {
        'meta_title': f"Premium {keyword} UAE - Best Prices | Horeca Store",
        'title_tag': f"Buy {keyword} Online UAE - Fast Delivery Available",
        'og_title': f"Top Quality {keyword} UAE - Shop Now! 🔥",
        'meta_description': f"Get the best {keyword} in UAE with fast delivery and warranty. Trusted by restaurants and hotels nationwide. Order today for instant savings!",
        'og_description': f"Discover premium {keyword} perfect for UAE businesses. Professional quality with full warranty and expert support. Limited stock available. 🚀"
    }

def generate_fallback_content(keyword):
    """Fallback content paragraphs"""
    return {
        'paragraph_1': f"Transform your kitchen with premium {keyword} designed for UAE businesses. Professional quality meets great value. Shop now!",
        'paragraph_2': f"Get exclusive features and robust performance that outlasts the competition. Limited time special pricing. Order today!",
        'paragraph_3': f"Trusted by top restaurants across UAE with full warranty and expert support. Join satisfied customers nationwide. Buy now!",
        'paragraph_4': f"Stock is moving fast! Secure your {keyword} before prices go up. Don't miss this opportunity. Get yours now!",
        'popular_tags': json.dumps([
            f"{keyword} UAE", f"commercial {keyword}", f"restaurant {keyword}",
            f"hotel {keyword}", f"professional {keyword}", f"kitchen {keyword}"
        ])
    }

def main():
    """Main function to process input and generate SEO fields"""
    try:
        data = json.load(sys.stdin)
        keyword = data.get("primary_keyword") or data["relational_name"]
        
        # Generate SEO fields
        seo_fields = generate_sales_seo(
            data["relational_name"],
            keyword
        )
        
        # Merge with original data
        result = {**data, **seo_fields}
        
        # Generate URL if not provided
        if not data.get("url"):
            result["url"] = f"uae-{slugify(data['relational_name'])}"
        
        # Output result
        print(json.dumps(result, indent=2, ensure_ascii=False))
        
    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid JSON input: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)
    except KeyError as e:
        print(json.dumps({"error": f"Missing required field: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": f"Unexpected error: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()