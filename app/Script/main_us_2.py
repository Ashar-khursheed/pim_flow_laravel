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

def generate_seo_fields(name, keyword, page_type):
    prompt = f"""
    Generate SEO fields for a {page_type} page about {name} (Keyword: {keyword}) for the US market, branded as Horeca Store. Follow these rules strictly:
    ALWAYS REMEMBER TO COMPLETELY WRITE THE WORD HORECA STORE IN EACH TITLE, IT SHOULD NOT BE HALF CUT OR INCOMPLETE. IT SHOULD BE COMPLETE ALWAYS, EVEN IF THERE IS NO CTA IN BETWEEN.
    THE FIRST LETTER IN ANY TITLE SHOULD ALWAYS BE CAPITAL OR IN UPPERCASE.
    1. Meta Title: 50-60 chars, starts with '{keyword}', ends with '- Horeca Store'. Example: "Large Squeeze Bottle - Horeca Store". Only add such things in between keyword and Horeca Storter so that the character length is not exceeding, but is around the limit, but make sure 'Horeca Store' word is completed. Never halft cut the word Horeca Store, it should be complete, even if there is nothing in between.
    2. Title Tag: 60-70 chars, starts with '{keyword}', ends with '| Horeca Store', no emojis. Example: "Large Squeeze Bottle | Horeca Store".Only add such things in between keyword and Horeca Storter so that the character length is not exceeding, but is around the limit, but make sure 'Horeca Store'word is completed.Never halft cut the word Horeca Store, it should be complete, even if there is nothing in between. the words should never be 'Hore...' or ho... or just horeca...., it should be complete Horeca Store.    3. OG Title: <60 chars, starts with '{keyword}', ends with '- Horeca Store', add 🔥 emoji at end. Example: "Large Squeeze Bottle - Horeca Store 🔥". Only add such things in between keyword and Horeca Storter so that the character length is not exceeding, but is around the limit, but make sure 'Horeca Store' is always complete.
    3. Horeca Store' word is completed and there is an imogi after that.Always make sure there is Horeca Store completely written and emogi at end of OG title.Never halft cut the word Horeca Store, it should be complete, even if there is nothing in between. For big keywors like restaurants equipment suppliers, only use restaurants equipment suppliers - Horeca Store this format, do not add anything in between and definetly don't exceed the character limit in the Title tag. It should not come like this serrated utility knife 5 inch Professional Grade Kitchen Tool | Hor...", it should be complete Horeca Store in the Title tag or don't add the CTA in middle.
    4.Meta Description: 140-160 chars, starts with 'Shop {keyword} at Horeca Store', includes benefits (e.g., durable, leak-proof), ends with one text-only CTA from the list below, no emojis. Example: "Shop large squeeze bottle at Horeca Store. Durable, leak-proof. Shop the Secret.". Make sure to end the sentence and do not leave anything half finished, if the character length is exceeding too much, do not add anything new, stop at the previous word only.I repeat Do not add another word or CTA if it does not fit the character limit, you can go upto 165 maximium characters to finsih it but always finish the sentence.
    5. OG Description: 180-200 chars, starts with 'Shop {keyword} at Horeca Store', includes benefits, ends with a different text-only CTA from meta description, no emojis.Make sure the sentence or CTA ends though, it shoould not be incomplete, do not start new sentence if the character limit has reached over 190. Example: "Shop large squeeze bottle at Horeca Store. Durable, leak-proof restaurant essentials with fast delivery. Tap to Reveal the Deal.". Make sure to end the sentence and do not leave anything half finished, if the character length is exceeding too much, do not add anything new, stop at the previous word only. I repeat Do not add another word or CTA if it does not fit the character limit, you can go upto 205 maximium to finsih it but always finish the sentence. You can use imogies to make sure it is 190 characters above.
    6. MAKE SURE EVERY TITLE HAS 'HORECA STORE' COMPLETELY AND ALL OF THEM ARE STRICTLY BELOW THEIR CHARACTER LIMIT.
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
    Instructions:
    - *ALWAYS MAKE SURE THAT HORECA STORE IS LISTED INSIDE THE TITLES, NEVER CUT IT OFF IN MIDDLE OR LEAVE IR UNIFINSIHED.* 
    - THE HORECA STORE SHOULD NOT BE CUT IN THE MIDDLE, IT SHOULD BE COMPLETE EVEN IF THERE IS NO CTA IN TITLE, I JUST WANT THAT HORECA STORE IS PRESENT COMPLETELY
    - *ALWAYS ENSURE THAT THE CTA's are not cut in half, if it is exceeding the character limit, USE A DIFFERENT AND SMALLER CTA ONLY.
    - Ensure fields are unique, with no repeated phrasing.
    - Adhere to character limits: meta_title <60, title_tag <70, og_title <60, meta_description <160, og_description 180-200.Never exceed the limits at any cost.
    - Highlight benefits (e.g., durable, leak-proof). Do not cut words in the middle, either don't use them at all or use small CTA's
    - Avoid location references (e.g., no 'US').
    - Never should a sentence be incomplete, if the any word is being unifnished, remove the previous word to be semantically accurate.
    - DO NOT ADD BLANK SPACES TO MEET CRITERIA, YOU CAN USE ANOTHER SMALLER CTA INSTEAD.
    -Try to use different CTAs like Surprise Awaits and Peek inside, do not repeat CTAs in two consecutive products or their descriptions, they should be unique every time while staying inside the character limits.
    - If the chacracter limit is not being fullfiled in OG description even with one CTA and imogi, you can add another CTA which is large enough and meets the character limit but does not end in middle or exceeds it.
    - Return only the five fields, formatted as:
    Meta Title: [title]
    Title Tag: [title]
    OG Title: [title]
    Meta Description: [description]
    OG Description: [description]
    """

    try:
        response = client.messages.create(
            model="claude-3-5-sonnet-20241022",
            max_tokens=300,
            temperature=0.7,
            messages=[{"role": "user", "content": prompt}],
            timeout=30.0
        )
        if not response.content or not isinstance(response.content, list) or not response.content[0].text:
            print("Error: Empty response from Claude API", file=sys.stderr)
            return default_seo_fields(name, keyword)
        return parse_response(response.content[0].text)
    except Exception as e:
        print(f"Claude API Error: {str(e)}", file=sys.stderr)
        return default_seo_fields(name, keyword)

def parse_response(text):
    fields = {}
    valid_keys = ['meta_title', 'title_tag', 'og_title', 'meta_description', 'og_description']
    discarded = []
    for line in text.split('\n'):
        if ':' in line:
            key, val = line.split(':', 1)
            key_normalized = key.strip().lower().replace(' ', '_')
            if key_normalized in valid_keys:
                fields[key_normalized] = val.strip()
            else:
                discarded.append(key_normalized)
    # Ensure all required fields are present
    for field in valid_keys:
        if field not in fields:
            fields[field] = ''
    # Validate and trim fields
    if fields.get('meta_title', '') and len(fields['meta_title']) > 60:
        fields['meta_title'] = fields['meta_title'][:57] + '...'
    if fields.get('title_tag', '') and len(fields['title_tag']) > 70:
        fields['title_tag'] = fields['title_tag'][:67] + '...'
    if fields.get('og_title', '') and len(fields['og_title']) > 60:
        fields['og_title'] = fields['og_title'][:57] + '...'
    if fields.get('meta_description', '') and len(fields['meta_description']) > 160:
        fields['meta_description'] = fields['meta_description'][:157] + '...'
    if fields.get('og_description', '') and len(fields['og_description']) < 190:
        fields['og_description'] = fields['og_description'] + ' ' * (190 - len(fields['og_description']))
    elif fields.get('og_description', '') and len(fields['og_description']) > 200:
        fields['og_description'] = fields['og_description'][:197] + '...'
    return fields

def default_seo_fields(name, keyword):
    return {
        "meta_title": f"{keyword} - Horeca Store",
        "title_tag": f"{keyword} | Horeca Store",
        "og_title": f"{keyword} - Horeca Store 🔥",
        "meta_description": f"Shop {keyword} at Horeca Store. Durable, leak-proof. Shop the Secret.",
        "og_description": f"Shop {keyword} at Horeca Store. Durable, leak-proof restaurant essentials with fast delivery. Tap to Reveal the Deal."
    }

def main():
    try:
        # For debugging
        print("Script started", file=sys.stderr)
        
        data = json.load(sys.stdin)
        required_keys = ["relational_id", "relational_name", "relational_type", "primary_keyword"]
        missing_keys = [k for k in required_keys if k not in data]
        if missing_keys:
            raise ValueError(f"Missing required keys in input JSON: {missing_keys}")

        keyword = data.get("primary_keyword") or data["relational_name"]

        # For debugging
        print(f"Processing: {data['relational_name']} with keyword: {keyword}", file=sys.stderr)

        seo_fields = generate_seo_fields(
            data["relational_name"],
            keyword,
            data["relational_type"].split('\\')[-1]
        )

        # Update empty SEO fields
        result = {**data, **{
            k: v for k, v in seo_fields.items()
            if k in ['meta_title', 'title_tag', 'og_title', 'meta_description', 'og_description'] and not data.get(k)
        }}

        if not data.get("url"):
            result["url"] = slugify(data["relational_name"])

        # For debugging
        print("Script completed successfully", file=sys.stderr)
            
        print(json.dumps(result, indent=2, ensure_ascii=False))

    except json.JSONDecodeError as e:
        print(json.dumps({"error": f"Invalid input JSON: {e}"}, indent=2), file=sys.stderr)
        sys.exit(1)
    except Exception as e:
        print(json.dumps({"error": str(e)}, indent=2), file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()