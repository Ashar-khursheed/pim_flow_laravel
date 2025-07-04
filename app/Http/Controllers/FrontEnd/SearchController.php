<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Review;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Add this line
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
    
class SearchController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/frontend/search",
     *     operationId="searchContent",
     *     tags={"Frontend-Search"},
     *     summary="Search for products, categories, and brands",
     *     description="Returns a list of products, categories, and brands matching the search query. If no query is provided, random popular items are returned.",
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="Search term for products, categories, and brands",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response with search results",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="products",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sample Product"),
     *                     @OA\Property(property="image", type="string", format="url", example="https://example.com/storage/product.jpg"),
     *                     @OA\Property(property="slug", type="string", example="sample-product"),
     *                     @OA\Property(property="price", type="number", format="float", example=100),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=80)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=10),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="url", type="string", format="url", example="https://example.com/categories/electronics"),
     *                     @OA\Property(property="image", type="string", format="url", example="https://example.com/storage/category.jpg"),
     *                     @OA\Property(property="parent_id", type="integer", example=3),
     *                     @OA\Property(property="parent_slug", type="string", example="gadgets"),
     *                     @OA\Property(property="parent_parent_slug", type="string", example="tech"),
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="name", type="string", example="Tablet"),
     *                             @OA\Property(property="slug", type="string", example="tablet"),
     *                             @OA\Property(property="image", type="string", format="url", example="https://example.com/storage/tablet.jpg"),
     *                             @OA\Property(property="price", type="number", format="float", example=150),
     *                             @OA\Property(property="sale_price", type="number", format="float", example=120)
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="brands",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=5),
     *                     @OA\Property(property="name", type="string", example="Apple"),
     *                     @OA\Property(property="slug", type="string", example="apple"),
     *                     @OA\Property(property="url", type="string", format="url", example="https://example.com/brands/apple"),
     *                     @OA\Property(property="image", type="string", format="url", example="https://example.com/storage/logo.png"),
     *                     @OA\Property(
     *                         property="products",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=7),
     *                             @OA\Property(property="name", type="string", example="iPhone 13"),
     *                             @OA\Property(property="slug", type="string", example="iphone-13"),
     *                             @OA\Property(property="image", type="string", format="url", example="https://example.com/storage/iphone13.jpg"),
     *                             @OA\Property(property="price", type="number", format="float", example=999),
     *                             @OA\Property(property="sale_price", type="number", format="float", example=899)
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    // public function search(Request $request)
    // {
    //     $query = $request->input('query');
    //     $defaultImage = asset('images/default-thumbnail.jpg'); // Set your default image path here
    
    //     // Helper for image URL
    //     $imageUrl = function ($img) use ($defaultImage) {
    //         if (!$img) {
    //             return $defaultImage;
    //         }
    
    //         $imagePath = public_path('storage/' . ltrim($img, '/'));
    
    //         return File::exists($imagePath)
    //             ? asset('storage/' . ltrim($img, '/'))
    //             : $defaultImage;
    //     };
    
    //     if (empty($query)) {
    //         return Cache::remember('search_default_data', 60, function () use ($imageUrl) {
    //             $products = Product::with('slug')
    //                 ->where('status', 'published')
    //                 ->inRandomOrder()->take(4)->get()
    //                 ->map(fn($product) => [
    //                     'id' => $product->id,
    //                     'name' => $product->name,
    //                     'url' => $product->url,
    //                    'image' => json_decode($product->images)[0] ?? null,
    //                 ]);
    
    //             $categories = Category::with([
    //                     'slug',
    //                     'parent.slug',
    //                     'parent.parent.slug',
    //                     'products' => fn($q) => $q->where('status', 'published')->take(4)->with('slug') // ✅ filter applied here

    //                 ])
    //                 ->where('status', 'published') // ✅ Only published categories
    //                 ->inRandomOrder()->take(4)
    //                 ->with(['products' => fn($q) => $q->where('status', 'published')->take(4)])
    //                 ->get()->map(function ($cat) use ($imageUrl) {
    //                     return [
    //                         'id' => $cat->id,
    //                         'name' => $cat->name,
    //                         'slug' => $cat->slug,
    //                         'url' => $cat->url,
    //                         'image' =>$cat->image,
    //                         'parent_id' => $cat->parent_id,
    //                         'parent_slug' => $cat->parent?->slug,
    //                         'parent_parent_slug' => $cat->parent?->parent?->slug,
    //                         'products' => $cat->products->map(fn($p) => [
    //                             'id' => $p->id,
    //                             'name' => $p->name,
    //                             'slug' => optional($p->slug)->key,
    //                            'image' => json_decode($p->images)[0] ?? null,
    //                             'price' => $p->price,
    //                             'sale_price' => $p->sale_price,
    //                         ]),
    //                     ];
    //                 });
    
    //                 $brands = Brand::with([
    //                     'slug',
    //                     'products' => fn($q) => $q->where('status', 'published')->take(4)->with('slug') // ✅ filter applied here
    //                 ])
    //                 ->where('status', 'published') // ✅ Only published brands
    //                 ->inRandomOrder()->take(4)
    //                 ->with(['products' => fn($q) => $q->where('status', 'published')->take(4)])
    //                 ->get()->map(function ($brand) use ($imageUrl) {
    //                     return [
    //                         'id' => $brand->id,
    //                         'name' => $brand->name,
    //                         'url' => $brand->url,
    //                         'slug' => optional($brand->slug)->key,
    //                         'image' => $brand->logo,
    //                         'products' => $brand->products->map(fn($p) => [
    //                             'id' => $p->id,
    //                             'name' => $p->name,
    //                             'slug' => optional($p->slug)->key,
    //                             'image' =>  json_decode($p->images)[0] ?? null,
    //                             'price' => $p->price,
    //                             'sale_price' => $p->sale_price,
    //                         ]),
    //                     ];
    //                 });
    
    //             return response()->json([
    //                 'products' => $products,
    //                 'categories' => $categories,
    //                 'brands' => $brands,
    //             ]);
    //         });
    //     }
    
    //     // Query search logic
    //     $products = Product::with('slug')
    //         ->where('status', 'published')
    //         ->where(function ($q) use ($query) {
    //             $q->where('name', 'LIKE', "%{$query}%")
    //               ->orWhere('sku', 'LIKE', "%{$query}%")
    //               ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
    //         })
    //         ->take(5)->get()
    //         ->map(fn($p) => [
    //             'id' => $p->id,
    //             'name' => $p->name,
    //            'image' => json_decode($p->images)[0] ?? null,
    //             'slug' => optional($p->slug)->key,
    //             'price' => $p->price,
    //             'sale_price' => $p->sale_price,
    //         ]);
    
    //     $categories = Category::with([
    //             'slug',
    //             'parent.slug',
    //             'parent.parent.slug',
    //             'products' => fn($q) => $q->where('status', 'published')->take(4)->with('slug') // ✅ filter applied here
    //         ])
    //         ->where('status', 'published') // ✅ Only published categories
    //         ->where(function ($q) use ($query) {
    //             $q->where('name', 'LIKE', "%{$query}%")
    //               ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
    //         })
    //         ->take(5)->get()
    //         ->map(function ($cat) use ($imageUrl) {
    //             return [
    //                 'id' => $cat->id,
    //                 'name' => $cat->name,
    //                 'slug' => $cat->slug,
    //                 'url' => $cat->url,
    //                 'image' => $imageUrl($cat->image),
    //                 'parent_id' => $cat->parent_id,
    //                 'parent_slug' => $cat->slug,
    //                 'parent_parent_slug' => $cat->parent?->parent?->slug,
    //                 'products' => $cat->products->map(fn($p) => [
    //                     'id' => $p->id,
    //                     'name' => $p->name,
    //                     'slug' => optional($p->slug)->key,
    //                     'image' =>  json_decode($p->images)[0] ?? null,
    //                     'price' => $p->price,
    //                     'sale_price' => $p->sale_price,
    //                 ]),
    //             ];
    //         });
    
    //         $brands = Brand::with([
    //             'slug',
    //             'products' => fn($q) => $q->where('status', 'published')->take(4)->with('slug') // ✅ filter applied here
    //         ])
    //         ->where('status', 'published') // ✅ Only published brands
    //         ->where(function ($q) use ($query) {
    //             $q->where('name', 'LIKE', "%{$query}%")
    //               ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
    //         })
    //         ->take(5)
    //         ->with(['products' => fn($q) => $q->where('status', 'published')->take(4)])
    //         ->get()
    //         ->map(function ($brand) use ($imageUrl) {
    //             return [
    //                 'id' => $brand->id,
    //                 'name' => $brand->name,
    //                 'slug' => optional($brand->slug)->key,
    //                 'url' => $brand->url,
    //                 'image' => $brand->logo,
    //                 'products' => $brand->products->map(fn($p) => [
    //                     'id' => $p->id,
    //                     'name' => $p->name,
    //                     'slug' => optional($p->slug)->key,
    //                     'image' =>  json_decode($p->images)[0] ?? null,
    //                     'price' => $p->price,
    //                     'sale_price' => $p->sale_price,
    //                 ]),
    //             ];
    //         });
    
    //     return response()->json([
    //         'products' => $products,
    //         'categories' => $categories,
    //         'brands' => $brands,
    //     ]);
    // }
    // public function search(Request $request)
    // {
    //     $query = $request->input('query');
    //     $defaultImage = asset('images/default-thumbnail.jpg'); // Set your default image path here
    
    //     // Helper for image URL
    //     $imageUrl = function ($img) use ($defaultImage) {
    //         if (!$img) {
    //             return $defaultImage;
    //         }
    
    //         $imagePath = public_path('storage/' . ltrim($img, '/'));
    
    //         return File::exists($imagePath)
    //             ? asset('storage/' . ltrim($img, '/'))
    //             : $defaultImage;
    //     };
    
    //     // Helper function for consistent product mapping
    //     $mapProduct = function ($product) {
    //         $firstSupplier = $product->productSuppliers->first();
        
    //         return [
    //             'id' => $product->id,
    //             'name' => $product->name,
    //             'url' => $product->url,
    //             'sku' => $product->sku,
    //             'images' => json_decode($product->images) ?? [],
    //             'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
    //             'front_sale_price' => $firstSupplier ? (float) ($firstSupplier->sale_price ?? $firstSupplier->price) : null,
    //             'vendor_id' => $firstSupplier?->vendor_id,
    //             'currency_title' => $product->currency->symbol ?? null,
    //             'vendor_sku' => $firstSupplier->vendor_sku ?? null,
    //             'sale_price' => $firstSupplier->sale_price ?? null,
    //             'map' => $firstSupplier->map ?? null,
    //             'inventory' => $firstSupplier->inventory ?? null,
    //             'in_stock' => $firstSupplier->in_stock ?? null,
    //             'delivery_days' => $firstSupplier->delivery_days ?? null,
    //             'return_policy' => $firstSupplier->return_policy ?? null,
    //             'free_shipping' => $firstSupplier->free_shipping ?? null,
    //             'warranty_information' => $firstSupplier->warranty_information ?? null,
    //             'brand' => $product->brand ? [
    //                 'id' => $product->brand->id,
    //                 'name' => $product->brand->name,
    //                 'slug' => optional($product->brand->slug)->key,
    //             ] : null,
    //         ];
    //     };
    
    //     // Default brands to show
    //     $defaultBrands = ['Atosa', 'BakeMax', 'True', 'Beverage-Air', 'Midea', 'Serv-ware', 'Manitowoc', 'Hoshizaki'];
    
    //     if (empty($query)) {
    //         return Cache::remember('search_default_data', 60, function () use ($imageUrl, $defaultBrands, $mapProduct) {
    //             $products = Product::with(['slug', 'currency', 'brand']) // Added brand relation
    //                 ->where('status', 'published')
    //                 ->inRandomOrder()
    //                 ->take(4)
    //                 ->get()
    //                 ->map($mapProduct);
    
    //             $categories = Category::with([
    //                 'slug',
    //                 'parent.slug',
    //                 'parent.parent.slug',
    //                 'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug',  'currency', 'brand'])
    //             ])
    //             ->where('status', 'published')
    //             ->whereHas('products', fn($q) => $q->where('status', 'published'))
    //             ->inRandomOrder()
    //             ->take(4)
    //             ->get()
    //             ->map(function ($cat) use ($imageUrl, $mapProduct) {
    //                 return [
    //                     'id' => $cat->id,
    //                     'name' => $cat->name,
    //                     'slug' => $cat->slug,
    //                     'url' => $cat->url,
    //                     'image' => $imageUrl($cat->image),
    //                     'parent_id' => $cat->parent_id,
    //                     'parent_slug' => $cat->parent?->slug,
    //                     'parent_parent_slug' => $cat->parent?->parent?->slug,
    //                     'products' => $cat->products->map($mapProduct),
    //                 ];
    //             });
    
    //             // Show specific default brands with their products
    //             $brands = Brand::with([
    //                 'slug',
    //                 'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'currency', 'brand'])
    //             ])
    //             ->where('status', 'published')
    //             ->whereIn('name', $defaultBrands)
    //             ->get()->map(function ($brand) use ($imageUrl, $mapProduct) {
    //                 return [
    //                     'id' => $brand->id,
    //                     'name' => $brand->name,
    //                     'url' => $brand->url,
    //                     'slug' => optional($brand->slug)->key,
    //                     'image' => $brand->logo,
    //                     'products' => $brand->products->map($mapProduct),
    //                 ];
    //             });
    
    //             return response()->json([
    //                 'products' => $products,
    //                 'categories' => $categories,
    //                 'brands' => $brands,
    //             ]);
    //         });
    //     }
    
    //     // Enhanced query search logic with comprehensive SKU search
    //     $products = Product::with(['slug', 'brand', 'currency']) // Added vendor and currency relations
    //         ->where('status', 'published')
    //         ->where(function ($q) use ($query) {
    //             $q->where('name', 'LIKE', "%{$query}%")
    //               ->orWhere('sku', 'LIKE', "%{$query}%")
    //               ->orWhere('sku', '=', $query) // Exact SKU match for better accuracy
    //               ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
    //         })
    //         ->orderByRaw("
    //             CASE 
    //                 WHEN sku = ? THEN 1
    //                 WHEN sku LIKE ? THEN 2
    //                 WHEN name LIKE ? THEN 3
    //                 ELSE 4
    //             END
    //         ", [$query, "{$query}%", "{$query}%"]) // Prioritize exact SKU matches
    //         ->take(4) // Increased limit for better SKU search results
    //         ->get()
    //         ->map($mapProduct);
    
    //     $categories = Category::with([
    //         'slug',
    //         'parent.slug',
    //         'parent.parent.slug',
    //         'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'brand', 'currency', 'productSuppliers'])
    //     ])
    //     ->where('status', 'published')
    //     ->whereHas('products', fn($q) => $q->where('status', 'published'))
    //     ->where(function ($q) use ($query) {
    //         $q->where('name', 'LIKE', "%{$query}%")
    //           ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"))
    //           ->orWhereHas('products', function ($q) use ($query) {
    //               // Also search categories by their products' SKUs
    //               $q->where('status', 'published')
    //                 ->where(function ($subQ) use ($query) {
    //                     $subQ->where('sku', 'LIKE', "%{$query}%")
    //                          ->orWhere('sku', '=', $query);
    //                 });
    //           });
    //     })
    //     ->take(5)
    //     ->get()
    //     ->map(function ($cat) use ($imageUrl, $mapProduct) {
    //         return [
    //             'id' => $cat->id,
    //             'name' => $cat->name,
    //             'slug' => $cat->slug,
    //             'url' => $cat->url,
    //             'image' => $imageUrl($cat->image),
    //             'parent_id' => $cat->parent_id,
    //             'parent_slug' => $cat->parent?->slug,
    //             'parent_parent_slug' => $cat->parent?->parent?->slug,
    //             'products' => $cat->products->map($mapProduct),
    //         ];
    //     });
    
    //     // Enhanced brand search - show brands that match query OR have products matching query (including SKU)
    //     $brands = Brand::with([
    //         'slug',
    //         'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug',  'currency', 'brand'])
    //     ])
    //     ->where('status', 'published')
    //     ->where(function ($q) use ($query) {
    //         $q->where('name', 'LIKE', "%{$query}%")
    //           ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"))
    //           ->orWhereHas('products', function ($q) use ($query) {
    //               $q->where('status', 'published')
    //                 ->where(function ($subQ) use ($query) {
    //                     $subQ->where('name', 'LIKE', "%{$query}%")
    //                          ->orWhere('sku', 'LIKE', "%{$query}%")
    //                          ->orWhere('sku', '=', $query) // Exact SKU match
    //                          ->orWhereHas('slug', fn($slugQ) => $slugQ->where('key', 'LIKE', "%{$query}%"));
    //                 });
    //           });
    //     })
    //     ->take(5)
    //     ->get()
    //     ->map(function ($brand) use ($imageUrl, $mapProduct) {
    //         return [
    //             'id' => $brand->id,
    //             'name' => $brand->name,
    //             'slug' => optional($brand->slug)->key,
    //             'url' => $brand->url,
    //             'image' => $brand->logo,
    //             'products' => $brand->products->map($mapProduct),
    //         ];
    //     });
    
    //     // Get related brands for searched products (enhanced with SKU consideration)
    //     $relatedBrands = collect();
    //     if ($products->isNotEmpty()) {
    //         $productBrandIds = $products->pluck('brand.id')->filter()->unique();
            
    //         $additionalBrands = Brand::with([
    //             'slug',
    //             'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug',  'currency', 'brand'])
    //         ])
    //         ->where('status', 'published')
    //         ->whereIn('id', $productBrandIds)
    //         ->whereNotIn('id', $brands->pluck('id'))
    //         ->take(4)
    //         ->get()
    //         ->map(function ($brand) use ($imageUrl, $mapProduct) {
    //             return [
    //                 'id' => $brand->id,
    //                 'name' => $brand->name,
    //                 'slug' => optional($brand->slug)->key,
    //                 'url' => $brand->url,
    //                 'image' => $brand->logo,
    //                 'products' => $brand->products->map($mapProduct),
    //             ];
    //         });
    
    //         $brands = $brands->merge($additionalBrands);
    //     }
    
    //     // Get brands related to searched categories (enhanced with SKU consideration)
    //     if ($categories->isNotEmpty()) {
    //         $categoryIds = $categories->pluck('id');
            
    //         $categoryBrands = Brand::with([
    //             'slug',
    //             'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'currency', 'brand'])
    //         ])
    //         ->where('status', 'published')
    //         ->whereHas('products', function ($q) use ($categoryIds) {
    //             $q->where('status', 'published')
    //               ->whereHas('categories', fn($catQ) => $catQ->whereIn('categories.id', $categoryIds));
    //         })
    //         ->whereNotIn('id', $brands->pluck('id'))
    //         ->take(4)
    //         ->get()
    //         ->map(function ($brand) use ($imageUrl, $mapProduct) {
    //             return [
    //                 'id' => $brand->id,
    //                 'name' => $brand->name,
    //                 'slug' => optional($brand->slug)->key,
    //                 'url' => $brand->url,
    //                 'image' => $brand->logo,
    //                 'products' => $brand->products->map($mapProduct),
    //             ];
    //         });
    
    //         $brands = $brands->merge($categoryBrands);
    //     }
    
    //     return response()->json([
    //         'products' => $products,
    //         'categories' => $categories,
    //         'brands' => $brands->take(8), // Limit total brands shown
    //     ]);
    // }
    public function search(Request $request)
{
    $query = $request->input('query');
    $defaultImage = asset('images/default-thumbnail.jpg'); // Set your default image path here

    // Helper for image URL
    $imageUrl = function ($img) use ($defaultImage) {
        if (!$img) {
            return $defaultImage;
        }

        $imagePath = public_path('storage/' . ltrim($img, '/'));

        return File::exists($imagePath)
            ? asset('storage/' . ltrim($img, '/'))
            : $defaultImage;
    };

    // Helper function for consistent product mapping
    $mapProduct = function ($product) {
        $firstSupplier = $product->productSuppliers->first();
    
        return [
            'id' => $product->id,
            'name' => $product->name,
            'url' => $product->url,
            'sku' => $product->sku,
            'images' => json_decode($product->images) ?? [],
            'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
            'front_sale_price' => $firstSupplier ? (float) ($firstSupplier->sale_price ?? $firstSupplier->price) : null,
            'vendor_id' => $firstSupplier?->vendor_id,
            'currency_title' => $product->currency->symbol ?? null,
            'vendor_sku' => $firstSupplier->vendor_sku ?? null,
            'sale_price' => $firstSupplier->sale_price ?? null,
            'map' => $firstSupplier->map ?? null,
            'inventory' => $firstSupplier->inventory ?? null,
            'in_stock' => $firstSupplier->in_stock ?? null,
            'delivery_days' => $firstSupplier->delivery_days ?? null,
            'return_policy' => $firstSupplier->return_policy ?? null,
            'free_shipping' => $firstSupplier->free_shipping ?? null,
            'warranty_information' => $firstSupplier->warranty_information ?? null,
            'brand' => $product->brand ? [
                'id' => $product->brand->id,
                'name' => $product->brand->name,
                'slug' => optional($product->brand->slug)->key,
            ] : null,
        ];
    };

    // Fuzzy search helper function
    $fuzzySearch = function ($searchTerm, $targetString, $threshold = 0.6) {
        // Convert to lowercase for comparison
        $searchTerm = strtolower($searchTerm);
        $targetString = strtolower($targetString);
        
        // Exact match gets highest score
        if ($searchTerm === $targetString) {
            return 1.0;
        }
        
        // Contains match gets high score
        if (strpos($targetString, $searchTerm) !== false) {
            return 0.9;
        }
        
        // Calculate similarity using similar_text
        $similarity = 0;
        similar_text($searchTerm, $targetString, $similarity);
        $similarity = $similarity / 100;
        
        // Also try Levenshtein distance for short strings
        if (strlen($searchTerm) <= 50 && strlen($targetString) <= 50) {
            $maxLen = max(strlen($searchTerm), strlen($targetString));
            $levenshtein = levenshtein($searchTerm, $targetString);
            $levenshteinSimilarity = 1 - ($levenshtein / $maxLen);
            
            // Use the higher similarity score
            $similarity = max($similarity, $levenshteinSimilarity);
        }
        
        return $similarity >= $threshold ? $similarity : 0;
    };

    // Function to get search terms variations
    $getSearchVariations = function ($query) {
        $variations = [$query];
        
        // Add individual words
        $words = explode(' ', $query);
        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $variations[] = $word;
            }
        }
        
        // Add partial matches (remove common suffixes/prefixes)
        $commonSuffixes = ['s', 'es', 'ing', 'ed', 'er', 'ly'];
        foreach ($commonSuffixes as $suffix) {
            if (str_ends_with($query, $suffix) && strlen($query) > strlen($suffix) + 2) {
                $variations[] = substr($query, 0, -strlen($suffix));
            }
        }
        
        return array_unique($variations);
    };

    // Default brands to show
    $defaultBrands = ['Atosa', 'BakeMax', 'True', 'Beverage-Air', 'Midea', 'Serv-ware', 'Manitowoc', 'Hoshizaki'];

    if (empty($query)) {
        return Cache::remember('search_default_data', 60, function () use ($imageUrl, $defaultBrands, $mapProduct) {
            $products = Product::with(['slug', 'currency', 'brand'])
                ->where('status', 'published')
                ->inRandomOrder()
                ->take(4)
                ->get()
                ->map($mapProduct);

            $categories = Category::with([
                'slug',
                'parent.slug',
                'parent.parent.slug',
                'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug',  'currency', 'brand'])
            ])
            ->where('status', 'published')
            ->whereHas('products', fn($q) => $q->where('status', 'published'))
            ->inRandomOrder()
            ->take(4)
            ->get()
            ->map(function ($cat) use ($imageUrl, $mapProduct) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'url' => $cat->url,
                    'image' => $imageUrl($cat->image),
                    'parent_id' => $cat->parent_id,
                    'parent_slug' => $cat->parent?->slug,
                    'parent_parent_slug' => $cat->parent?->parent?->slug,
                    'products' => $cat->products->map($mapProduct),
                ];
            });

            $brands = Brand::with([
                'slug',
                'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'currency', 'brand'])
            ])
            ->where('status', 'published')
            ->whereIn('name', $defaultBrands)
            ->get()->map(function ($brand) use ($imageUrl, $mapProduct) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'url' => $brand->url,
                    'slug' => optional($brand->slug)->key,
                    'image' => $brand->logo,
                    'products' => $brand->products->map($mapProduct),
                ];
            });

            return response()->json([
                'products' => $products,
                'categories' => $categories,
                'brands' => $brands,
            ]);
        });
    }

    // Get search variations for fuzzy matching
    $searchVariations = $getSearchVariations($query);
    
    // Enhanced fuzzy search for products
    $allProducts = Product::with(['slug', 'brand', 'currency', 'productSuppliers'])
        ->where('status', 'published')
        ->get();
    
    $scoredProducts = collect();
    
    foreach ($allProducts as $product) {
        $maxScore = 0;
        $matchType = '';
        
        // Check exact matches first (highest priority)
        if ($product->sku === $query) {
            $maxScore = 1.0;
            $matchType = 'exact_sku';
        } elseif (stripos($product->name, $query) !== false) {
            $maxScore = 0.95;
            $matchType = 'exact_name';
        } elseif (stripos($product->sku, $query) !== false) {
            $maxScore = 0.9;
            $matchType = 'partial_sku';
        } else {
            // Fuzzy matching for misspellings
            foreach ($searchVariations as $variation) {
                // Check product name
                $nameScore = $fuzzySearch($variation, $product->name);
                if ($nameScore > $maxScore) {
                    $maxScore = $nameScore;
                    $matchType = 'fuzzy_name';
                }
                
                // Check product SKU
                $skuScore = $fuzzySearch($variation, $product->sku);
                if ($skuScore > $maxScore) {
                    $maxScore = $skuScore;
                    $matchType = 'fuzzy_sku';
                }
                
                // Check brand name
                if ($product->brand) {
                    $brandScore = $fuzzySearch($variation, $product->brand->name);
                    if ($brandScore > $maxScore) {
                        $maxScore = $brandScore;
                        $matchType = 'fuzzy_brand';
                    }
                }
            }
        }
        
        // Only include products with meaningful similarity
        if ($maxScore > 0.4) {
            $scoredProducts->push([
                'product' => $product,
                'score' => $maxScore,
                'match_type' => $matchType
            ]);
        }
    }
    
    // Sort by score and get top results
    $products = $scoredProducts
        ->sortByDesc('score')
        ->take(12) // Get more results for better fuzzy matching
        ->map(function ($item) use ($mapProduct) {
            return $mapProduct($item['product']);
        });

    // Enhanced fuzzy search for categories
    $allCategories = Category::with([
        'slug',
        'parent.slug',
        'parent.parent.slug',
        'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'brand', 'currency', 'productSuppliers'])
    ])
    ->where('status', 'published')
    ->whereHas('products', fn($q) => $q->where('status', 'published'))
    ->get();

    $scoredCategories = collect();
    
    foreach ($allCategories as $category) {
        $maxScore = 0;
        
        foreach ($searchVariations as $variation) {
            $categoryScore = $fuzzySearch($variation, $category->name);
            if ($categoryScore > $maxScore) {
                $maxScore = $categoryScore;
            }
        }
        
        if ($maxScore > 0.5) {
            $scoredCategories->push([
                'category' => $category,
                'score' => $maxScore
            ]);
        }
    }
    
    $categories = $scoredCategories
        ->sortByDesc('score')
        ->take(5)
        ->map(function ($item) use ($imageUrl, $mapProduct) {
            $cat = $item['category'];
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'url' => $cat->url,
                'image' => $imageUrl($cat->image),
                'parent_id' => $cat->parent_id,
                'parent_slug' => $cat->parent?->slug,
                'parent_parent_slug' => $cat->parent?->parent?->slug,
                'products' => $cat->products->map($mapProduct),
            ];
        });

    // Enhanced fuzzy search for brands
    $allBrands = Brand::with([
        'slug',
        'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'currency', 'brand'])
    ])
    ->where('status', 'published')
    ->get();

    $scoredBrands = collect();
    
    foreach ($allBrands as $brand) {
        $maxScore = 0;
        
        foreach ($searchVariations as $variation) {
            $brandScore = $fuzzySearch($variation, $brand->name);
            if ($brandScore > $maxScore) {
                $maxScore = $brandScore;
            }
        }
        
        if ($maxScore > 0.5) {
            $scoredBrands->push([
                'brand' => $brand,
                'score' => $maxScore
            ]);
        }
    }
    
    $brands = $scoredBrands
        ->sortByDesc('score')
        ->take(8)
        ->map(function ($item) use ($imageUrl, $mapProduct) {
            $brand = $item['brand'];
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => optional($brand->slug)->key,
                'url' => $brand->url,
                'image' => $brand->logo,
                'products' => $brand->products->map($mapProduct),
            ];
        });

    // Generate "Did you mean?" suggestions
    $suggestions = [];
    if ($products->count() < 3) {
        // Get common product names and brand names for suggestions
        $commonTerms = collect();
        
        // Add product names
        Product::where('status', 'published')
            ->select('name')
            ->get()
            ->each(function ($product) use ($commonTerms) {
                $words = explode(' ', strtolower($product->name));
                foreach ($words as $word) {
                    if (strlen($word) > 3) {
                        $commonTerms->push($word);
                    }
                }
            });
        
        // Add brand names
        Brand::where('status', 'published')
            ->select('name')
            ->get()
            ->each(function ($brand) use ($commonTerms) {
                $commonTerms->push(strtolower($brand->name));
            });
        
        // Find closest matches
        $commonTerms = $commonTerms->unique()->filter(function ($term) use ($query) {
            return strlen($term) > 2 && $term !== strtolower($query);
        });
        
        foreach ($commonTerms as $term) {
            $score = $fuzzySearch($query, $term, 0.6);
            if ($score > 0.6) {
                $suggestions[] = [
                    'term' => $term,
                    'score' => $score
                ];
            }
        }
        
        // Sort suggestions by score and take top 3
        $suggestions = collect($suggestions)
            ->sortByDesc('score')
            ->take(3)
            ->pluck('term')
            ->toArray();
    }

    $response = [
        'products' => $products,
        'categories' => $categories,
        'brands' => $brands,
        'query' => $query,
        'total_results' => $products->count() + $categories->count() + $brands->count(),
    ];
    
    // Add suggestions if available
    if (!empty($suggestions)) {
        $response['suggestions'] = $suggestions;
        $response['message'] = "Did you mean: " . implode(', ', $suggestions) . "?";
    }
    
    return response()->json($response);
}
    /**
     * @OA\Get(
     *     path="/api/frontend/search-categories",
     *     summary="Search published categories by query",
     *     description="Returns a list of up to 10 categories that match the search query by name or slug. Results are cached for performance.",
     *     operationId="searchCategories",
     *     tags={"Frontend-Search"},
     *     @OA\Parameter(
     *         name="query",
     *         in="query",
     *         description="The search query string",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful search",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="categories",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="name", type="string", example="Electronics"),
     *                     @OA\Property(property="slug", type="string", example="electronics"),
     *                     @OA\Property(property="slug_path", type="string", example="parent-category/electronics")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function searchCategories(Request $request)
    {
        $query = $request->input('query');

        if (empty($query)) {
            return response()->json(['categories' => []]);
        }

        $cacheKey = 'categories_search_' . md5($query);

        $categories = Cache::get($cacheKey);

        if (!$categories) {
            $categories = Category::where('status', 'published') // Filter only published categories
                ->where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                    ->orWhereHas('slug', function ($subQ) use ($query) {
                        $subQ->where('key', 'LIKE', "%{$query}%");
                    });
                })
                ->with(['slug', 'parent.slug'])
                ->take(4)
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => optional($category->slug)->key,
                        'slug_path' => $this->getSlugPath($category),
                    ];
                });

            Cache::put($cacheKey, $categories, 60);
        }

        return response()->json(['categories' => $categories]);
    }

    public function getSlugPath($category)
    {
        $slugPath = [];
        $current = $category;

        // Collect parent categories slugs efficiently
        while ($current->parent_id) {
            $parent = $current->parent; // Lazy load parent category
            if ($parent && $parent->slug) {
                array_unshift($slugPath, $parent->slug->key);
            }
            $current = $parent;
        }

        // Add the current category's slug
        if ($category->slug) {
            $slugPath[] = $category->slug->key;
        }

        return implode('/', $slugPath);
    }

    
    public function getProductsOnly(Request $request)
    {
        $query = $request->input('query');
        $defaultImage = asset('images/default-thumbnail.jpg');

        // Helper for image URL
        $imageUrl = function ($img) use ($defaultImage) {
            if (!$img) return $defaultImage;
            $imagePath = public_path('storage/' . ltrim($img, '/'));
            return File::exists($imagePath) ? asset('storage/' . ltrim($img, '/')) : $defaultImage;
        };

        // Map function for product formatting
        $mapProduct = function ($product) {
            $firstSupplier = $product->productSuppliers->first();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'url' => $product->url,
                'sku' => $product->sku,
                'images' => json_decode($product->images) ?? [],
                'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'front_sale_price' => $firstSupplier ? (float) ($firstSupplier->sale_price ?? $firstSupplier->price) : null,
                'vendor_id' => $firstSupplier?->vendor_id,
                'currency_title' => $product->currency->symbol ?? null,
                'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                'sale_price' => $firstSupplier->sale_price ?? null,
                'map' => $firstSupplier->map ?? null,
                'inventory' => $firstSupplier->inventory ?? null,
                'in_stock' => $firstSupplier->in_stock ?? null,
                'delivery_days' => $firstSupplier->delivery_days ?? null,
                'return_policy' => $firstSupplier->return_policy ?? null,
                'free_shipping' => $firstSupplier->free_shipping ?? null,
                'warranty_information' => $firstSupplier->warranty_information ?? null,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => optional($product->brand->slug)->key,
                ] : null,
            ];
        };

        // Query logic
        $products = Product::with(['slug', 'brand', 'currency', 'productSuppliers'])
        ->where('status', 'published')
        ->where(function ($q) use ($query) {
            $q->where('name', 'LIKE', "%{$query}%")
                ->orWhere('sku', 'LIKE', "%{$query}%")
                ->orWhere('sku', '=', $query)
                ->orWhereHas('slug', fn($s) => $s->where('key', 'LIKE', "%{$query}%"))
                ->orWhereHas('brand', fn($b) => $b->where('name', 'LIKE', "%{$query}%")); // 🔥 add this
        })
        ->take(20)
        ->get()
        ->map($mapProduct);
    

        return response()->json(['products' => $products]);
    }
  
    

}