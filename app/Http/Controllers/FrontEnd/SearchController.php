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
    
        if (empty($query)) {
            return Cache::remember('search_default_data', 60, function () use ($imageUrl) {
                $products = Product::with('slug')
                    ->where('status', 'published')
                    ->inRandomOrder()->take(4)->get()
                    ->map(fn($product) => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'url' => $product->url,
                       'image' => json_decode($product->images)[0] ?? null,
                    ]);
    
                $categories = Category::with([
                        'slug',
                        'parent.slug',
                        'parent.parent.slug',
                        'products.slug'
                    ])
                    ->where('status', 'published') // ✅ Only published categories
                    ->inRandomOrder()->take(4)
                    ->with(['products' => fn($q) => $q->where('status', 'published')->take(3)])
                    ->get()->map(function ($cat) use ($imageUrl) {
                        return [
                            'id' => $cat->id,
                            'name' => $cat->name,
                            'slug' => optional($cat->slug)->key,
                            'url' => $cat->url,
                            'image' =>$cat->image,
                            'parent_id' => $cat->parent_id,
                            'parent_slug' => optional($cat->parent?->slug)->key,
                            'parent_parent_slug' => optional($cat->parent?->parent?->slug)->key,
                            'products' => $cat->products->map(fn($p) => [
                                'id' => $p->id,
                                'name' => $p->name,
                                'slug' => optional($p->slug)->key,
                               'image' => json_decode($p->images)[0] ?? null,
                                'price' => $p->price,
                                'sale_price' => $p->sale_price,
                            ]),
                        ];
                    });
    
                $brands = Brand::with(['slug', 'products.slug'])
                    ->where('status', 'published') // ✅ Only published brands
                    ->inRandomOrder()->take(4)
                    ->with(['products' => fn($q) => $q->where('status', 'published')->take(3)])
                    ->get()->map(function ($brand) use ($imageUrl) {
                        return [
                            'id' => $brand->id,
                            'name' => $brand->name,
                            'url' => $brand->url,
                            'slug' => optional($brand->slug)->key,
                            'image' => $brand->logo,
                            'products' => $brand->products->map(fn($p) => [
                                'id' => $p->id,
                                'name' => $p->name,
                                'slug' => optional($p->slug)->key,
                                'image' =>  json_decode($p->images)[0] ?? null,
                                'price' => $p->price,
                                'sale_price' => $p->sale_price,
                            ]),
                        ];
                    });
    
                return response()->json([
                    'products' => $products,
                    'categories' => $categories,
                    'brands' => $brands,
                ]);
            });
        }
    
        // Query search logic
        $products = Product::with('slug')
            ->where('status', 'published')
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%")
                  ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
            })
            ->take(5)->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'name' => $p->name,
               'image' => json_decode($p->images)[0] ?? null,
                'slug' => optional($p->slug)->key,
                'price' => $p->price,
                'sale_price' => $p->sale_price,
            ]);
    
        $categories = Category::with([
                'slug',
                'parent.slug',
                'parent.parent.slug',
                'products.slug'
            ])
            ->where('status', 'published') // ✅ Only published categories
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
            })
            ->take(5)->get()
            ->map(function ($cat) use ($imageUrl) {
                return [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'slug' => optional($cat->slug)->key,
                    'url' => $cat->url,
                    'image' => $imageUrl($cat->image),
                    'parent_id' => $cat->parent_id,
                    'parent_slug' => optional($cat->parent?->slug)->key,
                    'parent_parent_slug' => optional($cat->parent?->parent?->slug)->key,
                    'products' => $cat->products->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'slug' => optional($p->slug)->key,
                        'image' =>  json_decode($p->images)[0] ?? null,
                        'price' => $p->price,
                        'sale_price' => $p->sale_price,
                    ]),
                ];
            });
    
        $brands = Brand::with(['slug', 'products.slug'])
            ->where('status', 'published') // ✅ Only published brands
            ->where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")
                  ->orWhereHas('slug', fn($q) => $q->where('key', 'LIKE', "%{$query}%"));
            })
            ->take(5)
            ->with(['products' => fn($q) => $q->where('status', 'published')->take(3)])
            ->get()
            ->map(function ($brand) use ($imageUrl) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->name,
                    'slug' => optional($brand->slug)->key,
                    'url' => $brand->url,
                    'image' => $brand->logo,
                    'products' => $brand->products->map(fn($p) => [
                        'id' => $p->id,
                        'name' => $p->name,
                        'slug' => optional($p->slug)->key,
                        'image' =>  json_decode($p->images)[0] ?? null,
                        'price' => $p->price,
                        'sale_price' => $p->sale_price,
                    ]),
                ];
            });
    
        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
        ]);
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
                ->take(10)
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

  
    

}