# -*- coding: utf-8 -*-
# pip install anthropic==0.34.2 httpx==0.27.2 python-slugify==8.0.4 python-dotenv==0.19.0
import sys
import json
from slugify import slugify
import anthropic
import os
from dotenv import load_dotenv
import pymysql
import re
import random
# UTF-8 setup
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")

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


# Configuration - FIXED API KEY USAGE
client = anthropic.Anthropic(
    api_key=os.getenv('CLAUDE_API_KEY')  # Fixed: changed [] to () for getenv
)


# Urgency-focused CTAs designed for maximum CTR
URGENCY_CTAS = [
    "⚡ Limited Stock", "🔥 Flash Sale", "⏰ 24H Only", "💥 Act Fast", 
    "🚨 Selling Out", "⚡ Order Now", "🔥 Hot Deal", "⏳ Today Only",
    "💯 Best Price", "🚀 Quick Ship", "⭐ Top Rated", "🎯 Must Have",
    "🔥 Trending", "⚡ Lightning Deal", "💎 Premium Pick", "🚨 Final Hours",
    "⏰ Hurry Up", "💥 Mega Sale", "🎉 Special Offer", "🔥 Hot Item",
    "⚡ Instant Buy", "🚀 Fast Track", "💯 Guaranteed", "⭐ #1 Choice"
]

# Power words for high-impact titles
POWER_WORDS = [
    "Ultimate", "Premium", "Professional", "Exclusive", "Advanced",
    "Superior", "Elite", "Prime", "Luxury", "Master", "Expert",
    "Precision", "Heavy-Duty", "Industrial", "Commercial", "Pro-Grade"
]

# Emotional triggers for descriptions
EMOTIONAL_TRIGGERS = [
    "Transform your kitchen", "Revolutionize your cooking", "Upgrade your business",
    "Dominate your competition", "Maximize your profits", "Slash your costs",
    "Boost your efficiency", "Supercharge your operations", "Skyrocket your success"
]

def generate_high_ctr_seo(name, keyword, page_type):
    # Select random elements for uniqueness
    urgency_cta = random.choice(URGENCY_CTAS)
    power_word = random.choice(POWER_WORDS)
    emotional_trigger = random.choice(EMOTIONAL_TRIGGERS)
    
    # Determine emoji based on product category
    emoji_map = {
        "freezer": "🧊", "refrigerator": "❄️", "oven": "🔥", "mixer": "🥄",
        "cutter": "⚔️", "coffee": "☕", "ice": "🧊", "table": "🪑",
        "display": "📺", "grill": "🍖", "fryer": "🍟", "cooler": "🧊"
    }
    
    product_emoji = "⚡"
    for key, emoji in emoji_map.items():
        if key in keyword.lower():
            product_emoji = emoji
            break

    prompt = f"""
As an SEO specialist focused on maximizing click-through rates, create ultra-compelling SEO fields for '{name}' targeting '{keyword}' for a global or non-specific market.

Your mission: Create IRRESISTIBLE titles and descriptions that make users STOP scrolling and CLICK immediately.
**CRITICAL: ALL FIELDS MUST BE 100% COMPLETE. NO SENTENCES, PHRASES, OR WORDS ARE TO BE CUT IN HALF OR TRUNCATED. PRIORITIZE COMPLETENESS AT ALL COSTS.**
**The urgency CTR must be prevailing and dominating in all outputs.**
**Absolutely avoid using words like 'Discover' or 'Uncover'. Focus on direct, action-oriented, and human-like language.**

Key Elements to Include:
- Primary keyword: '{keyword}'
- Urgency CTA: '{urgency_cta}' (Use the *essence* of this, not necessarily the exact phrase if it doesn't fit naturally)
- Power word: '{power_word}' (Use the *essence* of this, not necessarily the exact word if it doesn't fit naturally)
- Emotional trigger: '{emotional_trigger}' (Use the *essence* of this, not necessarily the exact phrase if it doesn't fit naturally)
- Product emoji: '{product_emoji}'
- Brand: Horeca Store

CRITICAL CTR OPTIMIZATION RULES:
1. Lead with URGENCY and SCARCITY in a compelling, complete manner.
2. Use POWER WORDS that trigger immediate action, ensuring full words.
3. Include SPECIFIC BENEFITS users care about, in complete sentences.
4. Create FOMO (Fear of Missing Out) with clear, untruncated calls.
5. Use numbers and specifics when possible, without cutting them off.
6. Appeal to EMOTIONS and DESIRES with full, impactful phrases.
7. **ABSOLUTELY NO PERCENTAGE DISCOUNTS (e.g., "30% off")** as they are not always reliable. Focus on other value propositions like "Massive Savings," "Best Value," "Unbeatable Prices."

Generate these high-converting SEO fields:

1. META TITLE (50-60 chars): 
   - Start with urgency/scarcity.
   - Include primary keyword naturally.
   - Add compelling benefit (e.g., "Boost Efficiency," "Rapid Delivery").
   - End with brand if space allows, ensuring full words.
   - Format: "[URGENCY/VALUE] [KEYWORD] - [BENEFIT] | Horeca Store" (Example: "Limited Stock Countertop Oven - Boost Efficiency | Horeca")

2. TITLE TAG (55-65 chars):
   - Different approach from meta title.
   - Focus on transformation/outcome.
   - Include power word.
   - Strong, complete call-to-action.
   - Format: "[POWER_WORD] [KEYWORD] [TRANSFORMATION] - [COMPLETE CTA]" (Example: "Elite Commercial Microwave - Revolutionize Cooking - Act Now")

3. OG TITLE (50-55 chars + emoji):
   - Social media optimized.
   - Highly shareable angle.
   - Maximum curiosity/intrigue.
   - Must end with emoji, ensuring the text before it is complete.
   - Format: "[CURIOSITY_HOOK] [KEYWORD] [BENEFIT] {product_emoji}" (Example: "Unleash Kitchen Power with New Ovens 🔥")

4. META DESCRIPTION (Strive for 150-155 chars):
   - Start with emotional trigger or strong benefit.
   - Include 2-3 specific benefits, in complete sentences.
   - Create urgency with full phrases.
   - End with a strong, complete CTA.
   - NO emojis in meta description.
   - **Ensure every sentence is fully formed and ends with punctuation.**

5. OG DESCRIPTION (Strive for 190-195 chars + emoji):
   - Longer, more detailed version.
   - Include social proof elements (e.g., "Trusted by chefs").
   - Multiple benefits, in complete sentences.
   - Strong urgency close with full phrases.
   - End with emoji, ensuring the text before it is complete.
   - Perfect for social sharing.
   - **Ensure every sentence is fully formed and ends with punctuation.**

Each field must be UNIQUE in approach and wording. Think like a conversion copywriter - every word must earn its place by driving clicks, and every phrase must be complete.

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
            temperature=0.9, 
            messages=[{"role": "user", "content": prompt}]
        )
        
        seo_fields = parse_seo_response(response.content[0].text, keyword, product_emoji)
        paragraphs = generate_conversion_content(keyword, name, emotional_trigger)
        
        return {**seo_fields, **paragraphs}
        
    except Exception as e:
        print(f"API Error: {e}", file=sys.stderr)
        #return generate_fallback_seo(name, keyword, product_emoji)

def parse_seo_response(text, keyword, emoji):
    """Parse Claude's response into structured SEO fields"""
    fields = {}
    
    patterns = {
        'meta_title': r'Meta Title:\s*(.+?)(?=\n|Title Tag:|$)',
        'title_tag': r'Title Tag:\s*(.+?)(?=\n|OG Title:|$)', 
        'og_title': r'OG Title:\s*(.+?)(?=\n|Meta Description:|$)',
        'meta_description': r'Meta Description:\s*(.+?)(?=\n|OG Description:|$)',
        'og_description': r'OG Description:\s*(.+?)(?=\n|$)'
    }
    
    for field, pattern in patterns.items():
        match = re.search(pattern, text, re.IGNORECASE | re.DOTALL)
        if match:
            value = match.group(1).strip()
            # Aggressively clean up leading/trailing quotation marks and extra spaces
            value = re.sub(r'^["\']|["\']$', '', value).strip()
            value = re.sub(r'\s+', ' ', value)
            value = value.replace('&', 'and')
            fields[field] = value
        else:
            fields[field] = f"Default {field} for {keyword}"
    
    # Validate and enforce limits
    fields['meta_title'] = enforce_seo_limit(fields['meta_title'], 60, keyword, is_title=True)
    fields['title_tag'] = enforce_seo_limit(fields['title_tag'], 65, keyword, is_title=True)
    # OG fields need emoji handling
    fields['og_title'] = enforce_seo_limit(fields['og_title'], 55, keyword, is_title=True, requires_emoji=True, emoji=emoji)
    fields['meta_description'] = enforce_seo_limit(fields['meta_description'], 155, keyword)
    fields['og_description'] = enforce_seo_limit(fields['og_description'], 195, keyword, requires_emoji=True, emoji=emoji)
    
    return fields

def enforce_seo_limit(text, max_chars, keyword, is_title=False, requires_emoji=False, emoji=""):
    """
    Enforce character limits, ensuring words are never cut in half and sentences are complete
    wherever possible. Prioritizes completeness.
    """
    if not text:
        # Fallback for empty text, ensuring completeness
        if is_title:
            base_text = f"Premium {keyword} - Act Now!"
        else:
            base_text = f"Get high-quality {keyword} with swift delivery and full warranty. Shop today for instant savings!"
        return (base_text + (f" {emoji}" if requires_emoji and emoji else ""))[:max_chars].strip()

    # Determine emoji string to append and calculate effective max_chars for text
    emoji_to_append = f" {emoji}" if requires_emoji and emoji else ""
    effective_max_chars = max_chars - len(emoji_to_append)

    # --- Logic for descriptions and OG descriptions (prioritize complete sentences) ---
    if not is_title:
        sentences = re.split(r'(?<=[.!?])\s*', text) # Split by sentence-ending punctuation, keeping delimiter
        
        result_text = ""
        for i, sentence in enumerate(sentences):
            clean_sentence = sentence.strip()
            # Ensure sentence ends with punctuation if it's supposed to (not the very last one)
            if clean_sentence and not clean_sentence.endswith(('.', '!', '?')) and i < len(sentences) - 1:
                clean_sentence += '.'
            
            # Add space if not the first segment
            segment_to_add = clean_sentence
            if result_text:
                segment_to_add = " " + clean_sentence

            # If adding this complete sentence (and a space) exceeds the effective limit, stop.
            # This ensures the last included sentence is fully intact.
            if len(result_text) + len(segment_to_add) > effective_max_chars:
                break
            result_text += segment_to_add
        
        final_text = result_text.strip()

        # Ensure the final string ends with appropriate punctuation if it's a description
        if final_text and not final_text.endswith(('.', '!', '?')):
            # If adding a period fits, add it.
            if len(final_text) + 1 <= effective_max_chars:
                final_text += '.'
        
        # Append emoji if required
        final_output = final_text + emoji_to_append
        return final_output[:max_chars].strip() # Final strict char limit enforcement

    # --- Logic for titles (prioritize complete words, no truncation) ---
    words = text.split(' ')
    fitting_words = []
    current_length = 0

    for word in words:
        potential_length = current_length + len(word) + (1 if fitting_words else 0)
        if potential_length <= effective_max_chars:
            fitting_words.append(word)
            current_length = potential_length
        else:
            break # This word doesn't fit, stop here, ensuring previous words are complete

    trimmed = ' '.join(fitting_words).strip()
    
    # Ensure it ends cleanly (no trailing punctuation for titles)
    trimmed = trimmed.rstrip('.,;:')
    
    # Append emoji if required
    final_output = trimmed + emoji_to_append
    return final_output[:max_chars].strip() # Final strict character limit enforcement


def generate_conversion_content(keyword, name, emotional_trigger):
    """Generate high-converting content paragraphs"""
    
    content_prompt = f"""
As a conversion copywriter, create 4 high-converting content paragraphs for '{keyword}' that will drive sales and engagement.
**CRITICAL: EACH PARAGRAPH MUST BE 100% COMPLETE. NO SENTENCES, PHRASES, OR WORDS ARE TO BE CUT IN HALF OR TRUNCATED. PRIORITIZE COMPLETENESS AT ALL COSTS.**
**Target character range is strict: strive for 230 characters per paragraph, maintaining full sentence integrity.**
**Absolutely avoid using words like 'Discover' or 'Uncover'. Focus on direct, action-oriented, and human-like language.**

Each paragraph must be 220-240 characters long and designed to convert visitors into customers.

Requirements:
- Paragraph 1: Include '{keyword}' once, focus on immediate benefits, end with a strong, complete CTA like "Shop Now!"
- Paragraph 2: Highlight unique selling points, create urgency with complete phrases, end with a strong, complete CTA like "Limited Time!"  
- Paragraph 3: Build trust and credibility, end with a strong, complete CTA like "Order Today!"
- Paragraph 4: Final push with scarcity/social proof, end with a strong, complete CTA like "Get Yours Now!"
- **Absolutely no percentage discounts (e.g., "30% off")**. Focus on other value propositions.

Use emotional triggers, specific benefits, and power words. Write in conversational, persuasive tone that makes people want to buy immediately.
Ensure every paragraph is a cohesive, complete thought.

Also create 6 trending tags for a global or non-specific market.

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
            temperature=0.9, 
            messages=[{"role": "user", "content": content_prompt}]
        )
        
        content = response.content[0].text
        paragraphs = {}
        
        # Extract paragraphs
        for i in range(1, 5):
            pattern = f"Paragraph {i}:\\s*(.+?)(?=\\nParagraph {i+1}:|\\nPopular Tags:|$)"
            match = re.search(pattern, content, re.DOTALL)
            if match:
                para_text = match.group(1).strip()
                # Ensure proper length and completeness using the stricter logic
                para_text = optimize_paragraph_length(para_text, 220, 240, keyword if i == 1 else "")
                paragraphs[f"paragraph_{i}"] = para_text
            else:
                paragraphs[f"paragraph_{i}"] = generate_fallback_paragraph(i, keyword)
        
        # Extract tags
        tags_match = re.search(r'Popular Tags:\s*(\[.*?\])', content, re.DOTALL)
        if tags_match:
            try:
                tags = json.loads(tags_match.group(1))
                paragraphs["popular_tags"] = json.dumps(tags[:6])  # Ensure max 6 tags
            except:
                paragraphs["popular_tags"] = json.dumps([
                    f"{keyword} equipment", f"commercial {keyword}", f"restaurant {keyword}",
                    f"hotel {keyword}", f"professional {keyword}", f"kitchen {keyword}"
                ])
        else:
            paragraphs["popular_tags"] = json.dumps([
                f"{keyword} equipment", f"commercial {keyword}", f"restaurant {keyword}",
                f"hotel {keyword}", f"professional {keyword}", f"kitchen {keyword}"
            ])
        
        return paragraphs
        
    except Exception as e:
        print(f"Content generation error: {e}", file=sys.stderr)
        return generate_fallback_content(keyword, name)

def optimize_paragraph_length(text, min_len, max_len, keyword=""):
    """
    Optimizes paragraph length while maintaining conversion focus and ensuring 100% completeness
    of sentences and words. Prioritizes completeness.
    """
    if not text:
        return f"Premium {keyword} delivers exceptional results for your business. Shop Now!"
    
    sentences = re.split(r'(?<=[.!?])\s*', text) # Split by sentence-ending punctuation, keeping delimiter

    result_text = ""
    for i, sentence in enumerate(sentences):
        clean_sentence = sentence.strip()
        # Ensure sentence ends with punctuation if it's supposed to (not the very last one)
        if clean_sentence and not clean_sentence.endswith(('.', '!', '?')) and i < len(sentences) - 1:
            clean_sentence += '.'

        # Add space if not the first segment
        segment_to_add = clean_sentence
        if result_text:
            segment_to_add = " " + clean_sentence

        # If adding the complete sentence would exceed, stop.
        if len(result_text) + len(segment_to_add) > max_len:
            break 
        result_text += segment_to_add
    
    final_text = result_text.strip()

    # Ensure the final string ends with appropriate punctuation if it's a sentence
    if final_text and not final_text.endswith(('.', '!', '?')):
        if len(final_text) + 1 <= max_len:
            final_text += '.'

    return final_text[:max_len].strip() # Final strict character limit enforcement

def generate_fallback_paragraph(paragraph_num, keyword):
    """Generate fallback paragraphs if API fails"""
    fallback_content = {
        1: f"Transform your kitchen with premium {keyword} designed for modern businesses. Professional-grade quality meets affordable prices. Shop Now!",
        2: f"Exclusive deals on commercial equipment built to last. Limited stock available - don't miss out on these incredible savings. Limited Time!",
        3: f"Trusted by top restaurants and cafes worldwide, our equipment delivers consistent results every time. Join thousands of satisfied customers. Order Today!",
        4: f"High demand means supply is limited! Don't let your competitors get ahead - secure your equipment now before it's too late. Get Yours Now!"
    }
    
    base_text = fallback_content.get(paragraph_num, f"Premium equipment for your business needs. Great value and quality. Order now!")
    # Apply optimize_paragraph_length to fallbacks to ensure completeness.
    return optimize_paragraph_length(base_text, 220, 240, keyword if paragraph_num == 1 else "")

# Define generate_fallback_content before it's called
def generate_fallback_content(keyword, name):
    """Generate fallback content for paragraphs and tags if API fails"""
    fallback_paragraphs = {
        1: f"Elevate your commercial kitchen with our {keyword}. Experience unparalleled performance and efficiency, built for the demands of any professional setting. Shop Now!",
        2: f"Get cutting-edge technology and robust construction in every unit. Our limited stock ensures you get an exclusive deal. Don't miss this opportunity to upgrade! Limited Time!",
        3: f"Trusted by leading restaurants and hotels globally. Benefit from our extensive warranty and dedicated support team. Order Today!",
        4: f"The demand is high, and supply is limited! Act quickly to secure your advanced {keyword} and stay ahead of the competition. Get Yours Now!"
    }
    
    paragraphs = {}
    for i in range(1, 5):
        paragraphs[f"paragraph_{i}"] = optimize_paragraph_length(fallback_paragraphs[i], 220, 240, keyword if i == 1 else "")

    paragraphs["popular_tags"] = json.dumps([
        f"{keyword} equipment", f"commercial {keyword}", f"restaurant {keyword}",
        f"hotel {keyword}", f"professional {keyword}", f"kitchen {keyword}"
    ])
    return paragraphs


def main():
    """Main function to process input and generate SEO fields"""
    try:
        data = json.load(sys.stdin)
        keyword = data.get("primary_keyword") or data["relational_name"]
        
        # Generate high-CTR SEO fields
        seo_fields = generate_high_ctr_seo(
            data["relational_name"],
            keyword,
            data.get("relational_type", "Product")
        )
        
        # Merge with original data
        result = {**data, **seo_fields}
        
        # Generate URL if not provided, ensure it's generic
        if not data.get("url"):
            result["url"] = slugify(data['relational_name'])
        
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

# if __name__ == "__main__":
#     main()
if __name__ == "__main__":
    name = "Stainless Steel Double Door Freezer"
    keyword = "commercial freezer"
    page_type = "Category"

    seo_result = generate_high_ctr_seo(name, keyword, page_type)

    print(json.dumps(seo_result, indent=2, ensure_ascii=False))