# -*- coding: utf-8 -*-
# pip install anthropic==0.34.2 httpx==0.27.2 python-slugify==8.0.4 python-dotenv==0.19.0
import sys
import json
from slugify import slugify
import anthropic
import os
from dotenv import load_dotenv

# UTF-8 setup
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")
    sys.stderr.reconfigure(encoding="utf-8")

load_dotenv()

# Configuration
client = anthropic.Anthropic(
    api_key=os.environ["CLAUDE_API_KEY"]
    # Removed the explicit base_url
)

def generate_seo_fields(name, keyword, page_type):
    prompt = f"""
Follow these rules:
    # 1. A meta title (strictly less than 60 characters) that starts with '{keyword}', includes UAE and Horeca Store, like Webstaurant's style (e.g., "Stainless Steel Salt & Pepper Shakers in UAE - Horeca Store").For terms like Herbs and Spices which are very generic and broad, do it like this "Buy Premium Herbs and Spices in UAE | Horeca Store " for all titles, but make sure they match the character count.
    # 2. A title tag (strictly less than 70 characters) that starts with '{keyword}', includes UAE and Horeca Store, reflecting Katon's product-focused approach (e.g., "Stainless Steel Salt & Pepper Shakers in UAE | Horeca Store").For terms like Herbs and Spices which are very generic and broad, do it like this "Buy Premium Herbs and Spices in UAE | Horeca Store " for all titles, but make sure they match the character count.
    # 3. An OG title (strictly less than 60 characters) that is shareable, '{keyword}', includes UAE and Horeca Store,catchy if possible, inspired by Webstaurant (e.g., " Stainless Steel Salt & Pepper Shakers in UAE - Horeca Store").For terms like Herbs and Spices which are very generic and broad, do it like this "Buy Premium Herbs and Spices in UAE | Horeca Store " for all titles, and add a emogi after that, but make sure they match the character count.
    # 4. A meta description (strictly less than 160 characters) that starts with 'Shop {keyword} in UAE at Horeca Store', includes a call to action and benefits, like Katon's style (e.g., "Shop stainless steel salt & pepper shakers in UAE at Horeca Store. Perfect for restaurants – sleek, rust-resistant & fast delivery.").
    # 5. An OG description (strictly less than 200 characters) that starts with 'Shop {keyword} in UAE at Horeca Store', is engaging, includes benefits, similar to Webstaurant's style (e.g., "Shop stainless steel salt & pepper shakers in UAE at Horeca Store. Sleek, durable restaurant essentials with fast delivery. Tap to shop!").
    THE FIRST LETTER IN ANY TITLE SHOULD ALWAYS BE CAPITAL OR IN UPPERCASE.
    # Important instructions:
    # - Ensure all fields are unique and do not use exact phrasing across fields.
    # - Start Meta Title, Title Tag, and OG Title with the exact '{keyword}'.
    # - Include 'UAE' once per field where required, without repetition (e.g., not "UAE Shakers in UAE").
    # - Include 'Horeca Store' in each field as specified, without prefixes like 'Shop' or 'Premium'.
    # - Adhere to character limits: meta_title = 60, title_tag = 70, og_title = 60, meta_description = 160, og_description = 200.
    # - Use action-oriented language (e.g., "Shop Now", "Order Now") and highlight benefits (e.g., "Sleek", "Rust-resistant").
    # - Every title or description should be unique, catchy, and engaging, avoiding repetitive phrases across products or categories.
    # - For terms like Herbs and Spices which are very generic and broad, do it like this "Buy Premium Herbs and Spices in UAE | Horeca Store UAE" for all titles.
    # Please return the five fields separated by newlines, formatted as:
    # Meta Title: [title]
    # Title Tag: [title]
    # OG Title: [title]
    # Meta Description: [description]
    # OG Description: [description]
    ADD CTA's like this for more attention, just keep it under charcater limit- E-commerce CTA Buttons That Spark Curiosity:
    USE THESE CTAs INSIDE THE DESCRIPTIONS(use exactly, no emojis in descriptions):
        CTA’s to Choose 
        🛒 Action-Oriented Meta Title CTAs 
            Buy Online Today 
            Shop Now 
            Order Now 
            Explore Collection 
            Browse & Buy 
            Book Yours 
            Reserve Today 
            Try Now 
            ✅ Example: Commercial Kitchen Equipment | Shop Now at The Horeca Store 
        💰 Sales & Offer CTAs 
            Upto 50% Off 
            Bulk Deals Available 
            Flat Discounts 
            Buy More Save More 
            Free Delivery 
            Limited-Time Offer 
            Sale Ending Soon 
            Wholesale Prices 
            ✅ Example: Restaurant Supplies at Wholesale Prices | Limited-Time Offer 
        🔥 Urgency & Scarcity CTAs 
            Limited Stock 
            Fast Selling 
            Selling Out Soon 
            Only Few Left 
            Hurry! Shop Fast 
            Don’t Miss Out 
            Ends Tonight 
            Last Chance 
            ✅ Example: Top Commercial Fryers | Limited Stock – Order Now 
        🚚 Delivery & Logistics-Based CTAs 
            Free Shipping 
            Ships in 24H 
            Ready to Dispatch 
            Delivered Fast 
            Express Delivery 
            Same-Day Dispatch 
            Ready Stock 
            ✅ Example: Buy Commercial Ovens Online | Free Shipping Available 

        🏆 Trust & Quality-Driven CTAs 
            Trusted by Chefs 
            Premium Quality 
            Best for Hotels 
            Commercial Grade 
            Heavy-Duty Use 
            Rated #1 
            5-Star Rated 
            ✅ Example: Best Commercial Mixers | Heavy-Duty – Shop Trusted Brands

        **COMMON CTA's ALTERNATIVES **

        🔹 Action-Oriented CTAs 
        Generic / Overused              Suggested Alternatives 
        ⚠️ Shop Now                     👉 Shop [Product Name] Collection 
        ⚠️ Buy Now                      👉 Buy [Benefit-driven item] Now (e.g., Buy Glowing Skin Now) 
        ⚠️ Order Online                 👉 Order with 1-Click Delivery 
        ⚠️ Grab Yours                   👉 Grab Yours Before It’s Gone 
        ⚠️ Claim Yours                  👉 Claim Your Free Sample / Discount 
        ⚠️ Get It Today                 👉 Delivered in 24 Hours – Order Now 
        ⚠️ Explore Now                  👉 Explore Our Skincare Range for Oily Skin 
        ⚠️ Start Shopping               👉 Start with Bestsellers 
        ⚠️ Find Deals                   👉 Browse Live Deals Today 
        ⚠️ Unlock Offers                👉 Instant 10% Off – Click to Reveal Code

        🔹 Descriptive CTAs (SEO-Friendly) 
        Generic / Overused          Suggested Alternatives 
        ⚠️ Top-Rated               👉 Rated 4.9★ by 5,000+ Customers 
        ⚠️ Best-Selling            👉 #1 Best-Seller in Skincare Category 
        ⚠️ Most Popular            👉 Our Most Loved Tan Remover
        ⚠️ Top Picks               👉 Handpicked for Glowing Skin
        ⚠️ Editor's Choice         👉 Chosen by Beauty Experts 
        ⚠️ Customer Favorites      👉 Back by Customer Demand 
        ⚠️ Best Value              👉 Big Pack, Bigger Savings 
        ⚠️ Premium Selection       👉 Luxury Skincare – Budget Price 

        🔹 Urgency-Based CTAs 
        Generic / Overused           Suggested Alternatives 
        ⚠️ Limited Stock            👉 Only 12 Left – Order Now 
        ⚠️ Hurry! Selling Fast      👉 Selling Out Fast – Secure Yours 
        ⚠️ Only a Few Left          👉 Low Stock Alert – Act Fast 
        ⚠️ Ends Soon                👉 Offer Ends at Midnight 
        ⚠️ Last Chance              👉 Last Chance to Save 20% 
        ⚠️ Don’t Miss Out           👉 Missed Last Time? Grab It Now 
        ⚠️ Hot Deals Today          👉 Today Only: Upto 40% Off 
        🔹 Incentive-Focused CTAs 
        Generic / Overused               Suggested Alternatives 
            ⚠️ Free Shipping            👉 Enjoy Free Shipping on ₹499+ 
            ⚠️ Wholesale Prices         👉 Bulk Deals for Salons & Spas 
            ⚠️ Big Discounts            👉 Flat 30% Off Sitewide Today 
            ⚠️ Special Offers           👉 Buy 1 Get 1 Free – Limited Time 
            ⚠️ Exclusive Deals          👉 Members-Only Discount – Sign Up Free 
            ⚠️ Save More                👉 More You Buy, More You Save
            ⚠️ Best Price Guaranteed    👉 Price Match Guarantee 

        🔹 Trust/Quality Signals 
        Generic / Overused                  Suggested Alternatives 
            
            ⚠️ Trusted Brand                👉 9 Years of Skin-Trusted Solutions 
            ⚠️ #1 Choice                    👉 India’s Trusted Face Scrub Brand 
            ⚠️ Top Quality                  👉 Dermatologically Tested Quality
            ⚠️ Commercial-Grade             👉 Salon-Grade Formulas at Home 
            ⚠️ Satisfaction Guaranteed      👉 Money-Back Guarantee – No Questions Asked 

       *** NEGATIVE CTA's WHICH SHOULD NOT BE USED***
        ⚠️ Overused / Generic / "Negative" CTAs 
        These can sound too "AI-ish" or fail to trigger meaningful user action: 
            Discover now 
            Unlock the secrets 
            Unleash your potential 
            Start your journey today 
            Transform your business 
            Revolutionize your workflow 
            Embrace the future 
            Level up your skills 
            Take control today 
            Supercharge your growth 
            Don't miss out 
            Act now 
            Get started today 
            Experience the difference 
            Uncover hidden opportunities 
            Harness the power of [X] 
            Step into success
            Seize the moment 
            Boost your productivity 
            Make the switch today 
        ✅ CTA Phrases Frequently Used by ChatGPT 
            Discover More 
            Explore Now 
            Learn More 
            Uncover the Benefits 
            Dive In 
            Find Out How 
            Unlock the Secrets 
            Reveal What’s Inside 
            See the Difference 
        Get the Inside Scoop 
    ALWAYS USE DIFFERENT CTAs from the ones provided above each time to get the perfect description and title. But make sure no one exceeds the character limit.
    1. Meta Title (<60 chars):
        - Start with '{keyword}', followed by 'in UAE - Horeca Store'
        - Exciting and catchy, like Webstaurant
        - Example: "Stainless Steel Salt & Pepper Shakers in UAE - Horeca Store"

    2. Title Tag (<70 chars):
        - Start with '{keyword}', followed by 'in UAE | Horeca Store'
        - Product-focused, like Katon
        -Do not inclucde emogis, you only CTAs in text.
        - Example: "Stainless Steel Salt & Pepper Shakers in UAE | Horeca Store"

    3. OG Title (<=60 chars):
        - '{keyword}', followed by 'in UAE - Horeca Store' if possible
        - Mobile/social optimized, action-oriented
        -emogi at end
        - Example: " Stainless Steel Salt & Pepper Shakers in UAE - Horeca Store"

    4. Meta Description (<160 chars):
        - Start with "Shop {keyword} in UAE at Horeca Store"
        - Include benefits (e.g., sleek, rust-resistant) and urgency.
        -Do not inclucde emogis,use only CTAs in text.
        -Should be strictly less than 160 characters 
        - Example: "Shop stainless steel salt & pepper shakers in UAE at Horeca Store. Perfect for restaurants – sleek, rust-resistant & fast delivery."

    5. OG Description (=200 chars):
        - Start with "Shop {keyword} in UAE at Horeca Store"
        - Shareable, benefit-focused, engaging for social media.
        - The description should be exactly between 190 to 200 characters . It should not exceed 200 characters.
        - Do not use two CTA's, only use one and make sure it does not exceed 200 characters.
        - Strictly make sure it does not exceed 200 characters.so that no word is half cut or truncated.
        - Example: "Shop stainless steel salt & pepper shakers in UAE at Horeca Store. Sleek, durable restaurant essentials with fast delivery. Tap to shop!"
    remove the note from the end.
    """

    try:
        response = client.messages.create(
            model="claude-3-5-sonnet-20241022",
            max_tokens=300,
            temperature=0.7,
            messages=[{"role": "user", "content": prompt}]
        )
        return parse_response(response.content[0].text)
    except Exception as e:
        print(f"API Error: {e}", file=sys.stderr)
        return default_seo_fields(name, keyword)

def parse_response(text):
    fields = {}
    for line in text.split('\n'):
        if ':' in line:
            key, val = line.split(':', 1)
            fields[key.strip().lower().replace(' ', '_')] = val.strip()
    return fields

def default_seo_fields(name, keyword):
    return {
        "meta_title": f"{keyword} in UAE - Horeca Store",
        "title_tag": f"{name} in UAE | Horeca Store",
        "og_title": f"🔥 {keyword} UAE - Horeca Store",
        "meta_description": f"Shop {keyword} in UAE at Horeca Store. High quality, fast delivery.",
        "og_description": f"Shop top {keyword} in UAE at Horeca Store. Durable and stylish. Tap to shop!",
        
    }

def main():
    try:
        data = json.load(sys.stdin)
        keyword = data.get("primary_keyword") or data["relational_name"]

        seo_fields = generate_seo_fields(
            data["relational_name"],
            keyword,
            data["relational_type"].split('\\')[-1]
        )

        # Update only empty fields
        result = {**data, **{
            k: v for k, v in seo_fields.items()
            if not data.get(k)
        }}

        if not data.get("url"):
            result["url"] = f"uae-{slugify(data['relational_name'])}"

        print(json.dumps(result, indent=2, ensure_ascii=False))

    except Exception as e:
        print(json.dumps({"error": str(e)}), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()