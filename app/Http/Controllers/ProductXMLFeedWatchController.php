<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product; 
use App\Models\Category; 
use Illuminate\Support\Facades\Cache;
class ProductXMLFeedWatchController extends Controller
{
    
/**
 * @OA\Get(
 *     path="/api/feed/products.xml",
 *     summary="Get product feed for DataFeedWatch",
 *     description="Returns dynamic XML feed with all products for DataFeedWatch integration",
 *     tags={"Product Feed XML"},
 *     @OA\Parameter(
 *         name="category",
 *         in="query",
 *         description="Filter by category ID",
 *         required=false,
 *         @OA\Schema(type="integer",example=45,)
 *     ),
 *     @OA\Parameter(
 *         name="brand",
 *         in="query",
 *         description="Filter by brand",
 *         required=false,
 *         @OA\Schema(type="string")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\MediaType(
 *             mediaType="application/xml",
 *             example=""
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
    public function getProductFeed(Request $request)
    { 
        // Cache the feed for 1 hour
        $cacheKey = 'datafeedwatch_feed_' . md5($request->fullUrl());
     
        $xmlContent = Cache::remember($cacheKey, 3600, function () use ($request) {
            return $this->generateProductFeed($request);
        });

        return response($xmlContent, 200)->header('Content-Type', 'application/xml; charset=utf-8');
    }

    private function generateProductFeed(Request $request)
    {  
        // Get products with filters
        $query = Product::with(['categories'])
            ->where('status', 'active')
            ->where('stock', '>', 0);

    

         if ($request->has('category')) {
			$category = Category::find($request->category);
			$leafCategories = Category::getLeafCategories($category);
			$leafCategoryIds = $leafCategories->pluck('id')->toArray();
			$query->whereHas('categories', function ($q) use ($leafCategoryIds) {
				$q->whereIn('category_id', $leafCategoryIds);
			});
		}

        if ($request->has('brand')) {
            $query->where('brand_id', $request->brand);
        }

        $products = $query->get()->limit(2);
dd($products);
        // Create XML
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
        
        $channel = $xml->addChild('channel');
        $channel->addChild('title', htmlspecialchars(config('app.name')));
        $channel->addChild('link', config('app.url'));
        $channel->addChild('description', 'Product feed for DataFeedWatch');

        foreach ($products as $product) {
            $this->addProductToXml($channel, $product);
        }

        return $xml->asXML();
    }

    private function addProductToXml($channel, $product)
    {
        $item = $channel->addChild('item');
        
        // Basic product information
        $item->addChild('g:id', $product->id, 'http://base.google.com/ns/1.0');
        $item->addChild('g:title', htmlspecialchars($product->name), 'http://base.google.com/ns/1.0');
        $item->addChild('g:description', htmlspecialchars(strip_tags($product->description)), 'http://base.google.com/ns/1.0');
        $item->addChild('g:link', route('product.show', $product->slug), 'http://base.google.com/ns/1.0');
        
        // Image
        if ($product->images->isNotEmpty()) {
            $item->addChild('g:image_link', $product->images->first()->url, 'http://base.google.com/ns/1.0');
            
            // Additional images
            foreach ($product->images->slice(1, 10) as $image) {
                $item->addChild('g:additional_image_link', $image->url, 'http://base.google.com/ns/1.0');
            }
        }
        
        // Price and availability
        $item->addChild('g:price', number_format($product->price, 2) . ' ' . config('app.currency', 'USD'), 'http://base.google.com/ns/1.0');
        
        if ($product->sale_price) {
            $item->addChild('g:sale_price', number_format($product->sale_price, 2) . ' ' . config('app.currency', 'USD'), 'http://base.google.com/ns/1.0');
        }
        
        $availability = $product->stock > 0 ? 'in stock' : 'out of stock';
        $item->addChild('g:availability', $availability, 'http://base.google.com/ns/1.0');
        
        // Category and brand
        if ($product->category) {
            $item->addChild('g:product_type', htmlspecialchars($product->category->name), 'http://base.google.com/ns/1.0');
            $item->addChild('g:google_product_category', htmlspecialchars($product->category->google_category), 'http://base.google.com/ns/1.0');
        }
        
        if ($product->brand) {
            $item->addChild('g:brand', htmlspecialchars($product->brand), 'http://base.google.com/ns/1.0');
        }
        
        // Product identifiers
        if ($product->gtin) {
            $item->addChild('g:gtin', $product->gtin, 'http://base.google.com/ns/1.0');
        }
        
        if ($product->mpn) {
            $item->addChild('g:mpn', $product->mpn, 'http://base.google.com/ns/1.0');
        }
        
        $item->addChild('g:condition', $product->condition ?? 'new', 'http://base.google.com/ns/1.0');
        
        // Shipping
        if ($product->shipping_weight) {
            $item->addChild('g:shipping_weight', $product->shipping_weight . ' kg', 'http://base.google.com/ns/1.0');
        }
        
        // Additional attributes
        if ($product->color) {
            $item->addChild('g:color', htmlspecialchars($product->color), 'http://base.google.com/ns/1.0');
        }
        
        if ($product->size) {
            $item->addChild('g:size', htmlspecialchars($product->size), 'http://base.google.com/ns/1.0');
        }
        
        if ($product->material) {
            $item->addChild('g:material', htmlspecialchars($product->material), 'http://base.google.com/ns/1.0');
        }
        
        // Age group and gender
        $item->addChild('g:age_group', $product->age_group ?? 'adult', 'http://base.google.com/ns/1.0');
        $item->addChild('g:gender', $product->gender ?? 'unisex', 'http://base.google.com/ns/1.0');
    }
}
