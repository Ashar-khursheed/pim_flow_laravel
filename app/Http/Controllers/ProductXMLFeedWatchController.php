<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\SeoManagement;
use App\Models\Attribute;
use App\Models\ProductAttribute;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ProductXMLFeedWatchController extends Controller
{

    /**
     * @OA\Get(
     *     path="/api/feed/products.xml",
     *     summary="Get product feed for DataFeedWatch",
     *     description="Returns dynamic XML feed with all products for DataFeedWatch integration",
     *     tags={"Product Feed XML"},
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 500)",
     *         required=false,
     *         @OA\Schema(type="integer", example=500)
     *     ),     
     *      
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\MediaType(
     *             mediaType="application/json"
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    // public function getProductFeed(Request $request)
    // {
    //     // Cache the feed for 1 hour with pagination parameters
    //     $cacheKey = 'datafeedwatch_feed_r' . md5($request->fullUrl());

    //     $data = Cache::remember($cacheKey, 3600, function () use ($request) {
    //         return $this->generateProductFeed($request);
    //     });

    //     return $data;
    // }

 

    public function generateProductFeed(Request $request)
    {
        $perPage = $request->input('per_page');
        $query = Product::with([
            'brand:id,name,logo',
            'categories:id,name,parent_id',
            'categories.parent:id,name',
            'slug:id,key,reference_id',
            'productSuppliers.vendor:id,name',
            'vendors:id,name',
            'seoUrl',
            'seoProductUrl',
            'productVariants'
        ])
        ->select([
            'id',
            'name',
            'sku',
            'images',
            'brand_id',
            'status',
            'gen_type',
            'approved',
            'description',
            'quote_available',
            'stock_status',
            'barcode',
        ])
        ->where('status', 'published')
        ->orderBy('id', 'desc');

        $website = config('app.url', 'https://www.thehorecastore.com');

        // Start XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>Product Feed</title>';
        $xml .= '<link>' . $website . '</link>';
        $xml .= '<description>DataFeedWatch Product Feed</description>';

        // Process products
        if (!empty($perPage)) {
            // For paginated requests, get only the requested page
            $products = $query->paginate($perPage, ['*'], 'page', $request->input('page', 1));
            
            foreach ($products as $product) {
                $xml .= $this->mapProductToXml($product);
            }
        } else {
            // For full feed, use chunk to avoid memory issues
            $query->chunk(500, function ($products) use (&$xml) {
                foreach ($products as $product) {
                    $xml .= $this->mapProductToXml($product);
                }
            });
        }

        // Close channel and rss
        $xml .= '</channel>';
        $xml .= '</rss>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    /**
     * Helper function to map a single product to XML
     */
    private function mapProductToXml($product)
    {
        // Get the first supplier for price info
        $firstSupplier = $product->productSuppliers->first();
        $price = $firstSupplier->price ?? 0;
        $salePrice = $firstSupplier->sale_price ?? 0;

        // Decode description safely
        $descriptionText = '';
        if (is_string($product->description)) {
            $decoded = json_decode($product->description, true);
            $descriptionText = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                ? implode(' ', array_filter($decoded))
                : $product->description;
        }
        $descriptionText = preg_replace('/\s+/', ' ', trim(strip_tags(html_entity_decode($descriptionText))));

        // SEO data
        $seoData = SeoManagement::where('relational_id', $product->id)
            ->select('url', 'meta_title', 'meta_description')
            ->first();

        // Product images
        $image = null;
        if ($imageUrls = json_decode($product->images, true)) {
            $image = $imageUrls[0] ?? null;
        }

        // Product categories
        $currentCategory = $product->categories->first();
        $parentCategory = $currentCategory?->parent;
        $fullSlug = $product->parent_category_url() . '/' . $product->category_url() . '/' . ($product->seoProductUrl->url ?? "");

        $product_type = '';
        $google_product_category = '';
        if ($parentCategory && $currentCategory) {
            $product_type = $parentCategory->name . ' > ' . $currentCategory->name;
            $google_product_category = $product_type;
        } elseif ($currentCategory) {
            $product_type = $currentCategory->name;
            $google_product_category = $currentCategory->name;
        }

        // Product attributes
        $attributes = productAttribute::join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
            ->where('product_id', $product->id)
            ->select('attributes.name as attribute_name', 'product_attributes.attribute_value')
            ->get()
            ->map(fn($attr) => [
                'attribute_name' => $attr->attribute_name,
                'attribute_value' => $attr->attribute_value,
            ])
            ->toArray();

        // Variants
        $productVariants = $product->productVariants->map(function ($variant) {
            $childIds = json_decode($variant->child_ids, true) ?? [];
            $variants = json_decode($variant->variants, true) ?? [];
            if (empty($childIds) || empty($variants)) {
                return [];
            }

            $children = Product::whereIn('id', $childIds)->select('id')->get();
            $attributeIds = array_column($variants, 'attribute_id');
            $attributes = Attribute::whereIn('id', $attributeIds)->pluck('name', 'id');
            $productAttributes = ProductAttribute::whereIn('product_id', $childIds)
                ->whereIn('attribute_id', $attributeIds)
                ->get()
                ->groupBy('product_id');

            $result = [];
            foreach ($variants as $v) {
                $attributeId = $v['attribute_id'];
                $attributeName = $attributes[$attributeId] ?? null;
                if (!$attributeName) continue;

                $seenAttributeValues = [];
                foreach ($children as $child) {
                    $attrValue = $productAttributes->get($child->id)?->firstWhere('attribute_id', $attributeId)?->attribute_value ?? null;
                    if (empty($attrValue) || isset($seenAttributeValues[$attrValue])) continue;
                    $seenAttributeValues[$attrValue] = true;

                    $result[] = [
                        'attribute_id' => $attributeId,
                        'attribute_name' => $attributeName,
                        'label' => $v['labels'] ?? $attributeName,
                        'type' => $v['type'] ?? 'dropdown',
                        'attrValue' => $attrValue ?? '',
                    ];
                }
            }

            return $result;
        })->flatten(1)->values();
 
        // Start XML for this product
        $xml = '<item>';
        $xml .= '<g:id>' . $product->id . '</g:id>';
        $xml .= '<g:sku>' . htmlspecialchars($product->sku ?? '') . '</g:sku>';
        $xml .= '<g:barcode>' . htmlspecialchars($product->barcode ?? '') . '</g:barcode>';
        $xml .= '<g:title>' . htmlspecialchars($seoData?->meta_title ?? $product->name) . '</g:title>';        
        $xml .= '<g:link>' . config('app.url') . '/' . $fullSlug . '</g:link>';
        $xml .= '<g:description>' . htmlspecialchars($descriptionText) . '</g:description>';
        $xml .= '<g:price>' . number_format($price, 2) . '</g:price>';
        $xml .= '<g:sale_price>' . number_format($salePrice, 2) . '</g:sale_price>';
        $xml .= '<g:availability>' . $product->stock_status . '</g:availability>';
        $xml .= '<g:brand>' . htmlspecialchars($product->brand?->name ?? '') . '</g:brand>';
        $xml .= '<g:gtin> '.htmlspecialchars($product->barcode ?? '').'</g:gtin>';
        $xml .= '<g:mpn>' . $product->sku . '</g:mpn>';
       
        $xml .= '<g:material>'.htmlspecialchars($parentCategory->name).'</g:material>';
        if ($image) {
            $xml .= '<g:image_link>' . htmlspecialchars($image) . '</g:image_link>';
        }

        // Product attributes
        foreach ($attributes as $attr) {
           
            $xml .= '<g:product_detail>';
            $xml .= '<g:section_name> Key Specification </g:section_name>';
            $xml .= '<g:attribute_name>' . htmlspecialchars($attr['attribute_name']) . '</g:attribute_name>';
            $xml .= '<g:attribute_value>' . htmlspecialchars($attr['attribute_value']) . '</g:attribute_value>';
            
            $xml .= '</g:product_detail>';
            
        }

        // Product variants
        foreach ($productVariants as $highlight) {
            
            $xml .= '<g:product_highlight>' . htmlspecialchars($highlight['label']) . '</g:product_highlight>';
           
            $xml .= '<g:attribute_name>' . htmlspecialchars($highlight['attribute_name']) . '</g:attribute_name>';
            $xml .= '<g:attribute_value>' . htmlspecialchars($highlight['attrValue']) . '</g:attribute_value>';
            
        }
       
        $xml .= '<g:store_code> </g:store_code>';      
        $xml .= '<g:identifier_exists>no</g:identifier_exists>';
        $xml .= '<g:condition>new</g:condition>';       
        $xml .= '<g:google_product_category>' . htmlspecialchars($google_product_category) . '</g:google_product_category>';
        $xml .= '<g:sale_price_effective_date></g:sale_price_effective_date>';
        $xml .= '<g:product_type>' . htmlspecialchars($product_type) . '</g:product_type>';
        $xml .= '</item>';

        return $xml;
    }


    private function xmlEscape($value)
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
    }

    /**
     * @OA\Get(
     *     path="/api/feed/one-products.xml",
     *     summary="Get product feed for DataFeedWatch",
     *     description="Returns dynamic XML feed with all products for DataFeedWatch integration",
     *     tags={"Product Feed XML"},
     *     @OA\Parameter(
     *         name="product_id",
     *         in="query",
     *         description="Number of items per page (default: 1818)",
     *         required=false,
     *         @OA\Schema(type="integer", example=1818)
     *     ),     
     *      
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\MediaType(
     *             mediaType="application/json"
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */

  public function generateOneProductFeed(Request $request)
    {
        $product_id = $request->input('product_id');
        $query = Product::where('id',$product_id)->with([
            'brand:id,name,logo',
            'categories:id,name,parent_id',
            'categories.parent:id,name',
            'slug:id,key,reference_id',
            'productSuppliers.vendor:id,name',
            'vendors:id,name',
            'seoUrl',
            'seoProductUrl',
            'productVariants'
        ])
            ->select([
                'id',
                'name',
                'sku',
                'images',
                'brand_id',
                'status',
                'gen_type',
                'approved',
                'description',
                'quote_available',
                'stock_status',
                'barcode',

            ])
            ->where('status', 'published')
            ->orderBy('id', 'desc');

        $website = config('app.url', 'https://www.thehorecastore.com');

        // Start XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';
        $xml .= '<channel>';
        $xml .= '<title>Product Feed</title>';
        $xml .= '<link>' . $website . '</link>';
        $xml .= '<description>DataFeedWatch Product Feed</description>';


        $products = $query->get();
        
     
        if (!empty($products)) {
            
                foreach ($products as $product) {
                    $xml .= $this->mapProductToXml($product);
                }
        
        }

        // Close channel and rss
        $xml .= '</channel>';
        $xml .= '</rss>';

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }


    /**
     * Get XML product.
     *
     * @OA\Get(
     *     path="/api/feed/products-1.xml",
     *     summary="Get products1.xml",
     *     description="Returns dynamic XML 0-1000 feed with all products for DataFeedWatch integration.",
     *     tags={"Product Feed XML"},
     *     @OA\Response(
     *         response=200,
     *         description="XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    public function getProductFeed1()
    {

        return $this->productFeed(0, 1000);
    }
    /**
     * Get XML product.
     *
     * @OA\Get(
     *     path="/api/feed/products-2.xml",
     *     summary="Get products2.xml",
     *     description="Returns dynamic XML 1000-2000 feed with all products for DataFeedWatch integration.",
     *     tags={"Product Feed XML"},
     *     @OA\Response(
     *         response=200,
     *         description="XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    public function getProductFeed2()
    {

        return $this->productFeed(1000, 1000);
    }
    /**
     * Get XML product.
     *
     * @OA\Get(
     *     path="/api/feed/products-3.xml",
     *     summary="Get products3.xml",
     *     description="Returns dynamic XML 2000-3000 feed with all products for DataFeedWatch integration.",
     *     tags={"Product Feed XML"},
     *     @OA\Response(
     *         response=200,
     *         description="XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    public function getProductFeed3()
    {

        return $this->productFeed(2000, 1000);
    }

    /**
     * Get XML product.
     *
     * @OA\Get(
     *     path="/api/feed/products-4.xml",
     *     summary="Get products4.xml",
     *     description="Returns dynamic XML 3000-4000 feed with all products for DataFeedWatch integration.",
     *     tags={"Product Feed XML"},
     *     @OA\Response(
     *         response=200,
     *         description="XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    public function getProductFeed4()
    {

        return $this->productFeed(3000, 1000);
    }

    /**
     * Get XML product.
     *
     * @OA\Get(
     *     path="/api/feed/products-5.xml",
     *     summary="Get products5.xml",
     *     description="Returns dynamic XML 4000-5000 feed with all products for DataFeedWatch integration.",
     *     tags={"Product Feed XML"},
     *     @OA\Response(
     *         response=200,
     *         description="XML generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/xml",
     *             @OA\Schema(type="string", format="xml", example="<?xml version='1.0' encoding='UTF-8'?><urlset xmlns='http://www.sitemaps.org/schemas/sitemap/0.9'><url><loc>https://example.com/</loc><lastmod>2025-09-06T00:00:00+00:00</lastmod><changefreq>daily</changefreq><priority>1.0</priority></url></urlset>")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error generating sitemap")
     *         )
     *     )
     * )
     */
    public function getProductFeed5()
    {

        return $this->productFeed(4000, 1000);
    }




    public function productFeed($offset, $limit)
    {

        $allowedSortColumns = ['id', 'name', 'sku', 'brand_id', 'status', 'gen_type', 'approved'];

        $products = Product::with([
            'brand:id,name,logo',
            'categories:id,name,parent_id', // Added parent_id
            'categories.parent:id,name', // Load parent category
            'slug:id,key,reference_id',
            'productSuppliers.vendor:id,name',
            'vendors:id,name',
            'seoUrl',
            'seoProductUrl',
            'productVariants'
        ])
            ->select(['id', 'name', 'sku', 'images', 'brand_id', 'status', 'gen_type', 'approved', 'description', 'quote_available', 'stock_status'])
            ->where('status', 'published')
            ->offset($offset)
            ->limit($limit)
            ->orderBy('id', 'asc')->get();


        $formattedProducts = $products->map(function ($product) {
            $firstSupplier = $product->productSuppliers->first();
            $price = $firstSupplier->price ?? 0;
            $salePrice = $firstSupplier->sale_price ?? 0;

            $descriptionText = '';
            if (is_string($product->description)) {
                $decoded = json_decode($product->description, true);
                $descriptionText = (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
                    ? implode(' ', array_filter($decoded))
                    : $product->description;
            }
            $descriptionText = preg_replace('/\s+/', ' ', trim(strip_tags(html_entity_decode($descriptionText))));

            $seoData = SeoManagement::where('relational_id', $product->id)
                ->select('url', 'meta_title', 'meta_description', 'og_description', 'og_image_url', 'og_image_alt_text', 'og_image_name')
                ->first();

            $attributes = productAttribute::join('attributes', 'attributes.id', '=', 'product_attributes.attribute_id')
                ->where('product_id', $product->id)
                ->select('attributes.name as attribute_name', 'product_attributes.attribute_value')
                ->get()
                ->map(fn($attr) => [
                    'attribute_name' => $attr->attribute_name,
                    'attribute_value' => $attr->attribute_value,
                ])
                ->toArray();

            $image = ($imageUrls = json_decode($product->images, true)) && isset($imageUrls[0]) ? $imageUrls[0] : null;

            // Get current category and parent category
            $currentCategory = $product->categories->first(); // or however you determine the main category
            $parentCategory = $currentCategory?->parent;

            // Build category hierarchy for slug
            $categorySlug = '';
            if ($parentCategory) {
                $categorySlug = $parentCategory->name . '/';
            }
            if ($currentCategory) {
                $categorySlug .= $currentCategory->name . '/';
            }

            $fullSlug =  $product->parent_category_url() . '/' .
                $product->category_url() . '/' .
                ($product->seoProductUrl->url ?? "");

            // Build product type and google category
            $product_type = '';
            $google_product_category = '';

            if ($parentCategory && $currentCategory) {
                $product_type = $parentCategory->name . ' > ' . $currentCategory->name;
                $google_product_category = $parentCategory->name . ' > ' . $currentCategory->name;
            } elseif ($currentCategory) {
                $product_type = $currentCategory->name;
                $google_product_category = $currentCategory->name;
            }

            $productVariants = $product->productVariants->map(function ($variant) {
                $childIds = json_decode($variant->child_ids, true) ?? [];
                $variants = json_decode($variant->variants, true) ?? [];

                if (empty($childIds) || empty($variants)) {
                    return [];
                }

                $children = Product::whereIn('id', $childIds)
                    ->select('id', 'sku')
                    ->get();

                $attributeIds = array_column($variants, 'attribute_id');
                $attributes = Attribute::whereIn('id', $attributeIds)
                    ->pluck('name', 'id');

                $productAttributes = ProductAttribute::whereIn('product_id', $childIds)
                    ->whereIn('attribute_id', $attributeIds)
                    ->get()
                    ->groupBy('product_id');

                $result = [];

                foreach ($variants as $v) {
                    $attributeId = $v['attribute_id'];
                    $attributeName = $attributes[$attributeId] ?? null;

                    if (!$attributeName) {
                        continue;
                    }

                    $seenAttributeValues = [];

                    foreach ($children as $child) {
                        $attrValue = $productAttributes->get($child->id)?->firstWhere('attribute_id', $attributeId)?->attribute_value ?? null;

                        if (empty($attrValue) || isset($seenAttributeValues[$attrValue])) {
                            continue;
                        }

                        $seenAttributeValues[$attrValue] = true;

                        $result[] = [
                            'attribute_id' => $attributeId,
                            'attribute_name' => $attributeName,
                            'label' => $v['labels'] ?? $attributeName,
                            'type' => $v['type'] ?? 'dropdown',
                            'attrValue' => $attrValue ?? '',
                        ];
                    }
                }

                return $result;
            })->flatten(1)->values();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'meta_title' => $seoData?->meta_title,
                'meta_description' => $seoData?->meta_description,
                'og_description' => $seoData?->og_description,
                'og_image_url' => $seoData?->og_image_url,
                'og_image_alt_text' => $seoData?->og_image_alt_text,
                'og_image_name' => $seoData?->og_image_name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'brand' => $product->brand?->name,
                'slug' => $fullSlug,
                'price' => $price,
                'sale_price' => $salePrice,
                'availability' => $product->stock_status,
                'description' => $descriptionText,
                'attributes' => $attributes,
                'product_type' => $product_type,
                'google_product_category' => $google_product_category,
                'image' => $image,
                'product_highlight' => $productVariants ?? [],
                'current_category' => $currentCategory?->name,
                'parent_category' => $parentCategory?->name,
            ];
        });

        $website = config('app.url', 'https://www.thehorecastore.com');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">';

        foreach ($formattedProducts as $product) {
            $xml .= '<channel>';
            $xml .= '<title>' . $product['meta_title'] . '</title>';
            $xml .= '<link>' . $website . '</link>';
            $xml .= '<description>' . htmlspecialchars($product['meta_description']) . '</description>';
            $xml .= '<item>';
            $xml .= '<g:id>' . $product['id'] . '</g:id>';
            $xml .= '<g:sku>' . $product['sku'] . '</g:sku>';
            $xml .= '<g:barcode>' . $product['barcode'] . '</g:barcode>';
            $xml .= '<g:title>' . $product['meta_title'] . '</g:title>';
            $xml .= '<g:link>' . $website . '/' . $product['slug'] . '</g:link>';
            $xml .= '<g:description>' . htmlspecialchars($product['description']) . '</g:description>';
            $xml .= '<g:price>' . number_format($product['price'], 2) . '</g:price>';
            $xml .= '<g:sale_price>' . number_format($product['sale_price'], 2) . '</g:sale_price>';
            $xml .= '<g:availability>' . $product['availability'] . '</g:availability>';
            $xml .= '<g:brand>' . $product['brand'] . '</g:brand>';
            $xml .= '<g:sku>' . $product['sku'] . '</g:sku>';

            if ($product['image']) {
                $xml .= '<g:image_link>' . $product['image'] . '</g:image_link>';
            }

            // Attributes
            if (!empty($product['attributes'])) {
                foreach ($product['attributes'] as $attr) {
                    $xml .= '<g:product_detail>';
                    $xml .= '<g:section_name>Key Specification</g:section_name>';
                    $xml .= '<g:attribute_name>' . $attr['attribute_name'] . '</g:attribute_name>';
                    $xml .= '<g:attribute_value>' . $attr['attribute_value'] . '</g:attribute_value>';
                    $xml .= '</g:product_detail>';
                }
            }

            // Product highlights
            if (!empty($product['product_highlight'])) {
                foreach ($product['product_highlight'] as $highlight) {

                    $xml .= '<g:product_highlight>' . $highlight['attribute_name'] ?? '' . ' : ' . $highlight['attrValue'] ?? '' . '</g:product_highlight>';
                }
            }

            $xml .= '<g:identifier_exists>no</g:identifier_exists>';
            $xml .= '<g:material>'.htmlspecialchars($product['parent_category']).'</g:material>';
            $xml .= '<g:store_code></g:store_code>';
            $xml .= '<g:condition>new</g:condition>';
            $xml .= '<g:google_product_category>' . $product['google_product_category'] . '</g:google_product_category>';
            $xml .= '<g:product_type>' . $product['product_type'] . '</g:product_type>';
            $xml .= '</item>';
            $xml .= '</channel>';
        }

        $xml .= '</rss>';

        return response($xml, 200)->header('Content-Type', 'application/xml');
    }
}
