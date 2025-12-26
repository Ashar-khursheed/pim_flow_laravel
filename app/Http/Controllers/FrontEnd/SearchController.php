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
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

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
        $defaultImage = asset('images/default-thumbnail.jpg');
    
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
                'category_url' => $product->category_url(),
                'parent_category_url' => $product->parent_category_url(),
                'url' => $product->seoUrl->url ?? null,
                'sku' => $product->sku,
                'images' => json_decode($product->images) ?? [],
                'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'front_sale_price' => $firstSupplier ? (float) ($firstSupplier->sale_price ?? $firstSupplier->price) : null,
                'vendor_id' => $firstSupplier?->vendor_id,
                'currency_title' => $product->currency->symbol ?? null,
                'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                'price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'sale_price' => $firstSupplier->sale_price ?? null,
                'map' => $firstSupplier->map ?? null,
                'inventory' => $firstSupplier->inventory ?? null,
                'in_stock' => $firstSupplier->in_stock ?? null,
                'delivery_days' => $firstSupplier->delivery_days ?? null,
                'return_policy' => $firstSupplier->return_policy ?? null,
                'free_shipping' => $firstSupplier->free_shipping ?? null,
               'warranty_information' => !empty($product->warrantyAttribute?->attribute_value)
                    ? $product->warrantyAttribute->attribute_value
                    : ($firstSupplier->warranty_information ?? null),
                'min_quantity' => $firstSupplier->min_quantity ?? 0,
                'quote_available' => $product->quote_available ?? null,
                 'isRequired' => $product->isRequired,
                'is_fixed' => $firstSupplier->is_fixed ?? 0,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => optional($product->brand->slug)->key,
                ] : null,
            ];
        };
    
        // Fast fuzzy search terms generator
        $generateSearchTerms = function ($query) {
            $terms = [];
            $cleanQuery = strtolower(trim($query));
            
            // Original query
            $terms[] = $cleanQuery;
            
            // Individual words (only if multi-word)
            $words = explode(' ', $cleanQuery);
            if (count($words) > 1) {
                foreach ($words as $word) {
                    if (strlen($word) > 2) {
                        $terms[] = $word;
                    }
                }
            }
            
            // Quick variations for common misspellings
            if (strlen($cleanQuery) > 3) {
                // Remove last character (handles extra letters)
                $terms[] = substr($cleanQuery, 0, -1);
                // Remove first character (handles extra letters at start)
                $terms[] = substr($cleanQuery, 1);
            }
            
            return array_unique($terms);
        };
    
        // Default brands to show
        $defaultBrands = ['Atosa', 'BakeMax', 'True', 'Beverage-Air', 'Midea'];
    
        if (empty($query)) {
            return Cache::remember('search_default_data', 60, function () use ($imageUrl, $defaultBrands, $mapProduct) {
                $products = Product::with(['slug', 'currency', 'brand' , 'seoUrl'])
                    ->where('status', 'published')
                    ->inRandomOrder()
                    ->take(4)
                    ->get()
                    ->map($mapProduct);
    
                $categories = Category::with([
                    'slug',
                    'parent.slug',
                    'parent.parent.slug',
                    'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug',  'currency', 'brand'  , 'seoUrl'])
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
                    'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'currency', 'brand' , 'seoUrl'])
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
    
        // Generate search terms for fuzzy matching
        $searchTerms = $generateSearchTerms($query);
        
        // Super fast product search with database-level fuzzy matching
        $products = Product::with(['slug', 'brand', 'currency', 'productSuppliers'])
            ->where('status', 'published')
            ->where(function ($q) use ($query, $searchTerms) {
                // Exact matches first (highest priority)
                $q->where('sku', '=', $query)
                  ->orWhere('name', 'LIKE', "%{$query}%")
                  ->orWhere('sku', 'LIKE', "%{$query}%");
                
                // Fuzzy matches using SOUNDEX and multiple LIKE patterns
                foreach ($searchTerms as $term) {
                    if ($term !== $query) {
                        $q->orWhere('name', 'LIKE', "%{$term}%")
                          ->orWhere('sku', 'LIKE', "%{$term}%");
                    }
                }
                
                // SOUNDEX for phonetic matching (handles pronunciation-based misspellings)
                $q->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$query])
                  ->orWhereRaw('SOUNDEX(sku) = SOUNDEX(?)', [$query]);
                  
                // Brand name matching
                $q->orWhereHas('brand', function ($brandQuery) use ($query, $searchTerms) {
                    $brandQuery->where('name', 'LIKE', "%{$query}%");
                    foreach ($searchTerms as $term) {
                        if ($term !== $query) {
                            $brandQuery->orWhere('name', 'LIKE', "%{$term}%");
                        }
                    }
                    $brandQuery->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$query]);
                });
            })
            ->orderByRaw("
                CASE 
                    WHEN sku = ? THEN 1
                    WHEN name LIKE ? THEN 2
                    WHEN sku LIKE ? THEN 3
                    WHEN SOUNDEX(name) = SOUNDEX(?) THEN 4
                    WHEN SOUNDEX(sku) = SOUNDEX(?) THEN 5
                    ELSE 6
                END
            ", [$query, "%{$query}%", "%{$query}%", $query, $query])
            ->take(12)
            ->get()
            ->map($mapProduct);
    
        // Fast category search
        $categories = Category::with([
            'slug',
            'parent.slug',
            'parent.parent.slug',
            'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'brand', 'currency', 'productSuppliers' , 'seoUrl'])
        ])
        ->where('status', 'published')
        ->whereHas('products', fn($q) => $q->where('status', 'published'))
        ->where(function ($q) use ($query, $searchTerms) {
            $q->where('name', 'LIKE', "%{$query}%");
            
            foreach ($searchTerms as $term) {
                if ($term !== $query) {
                    $q->orWhere('name', 'LIKE', "%{$term}%");
                }
            }
            
            $q->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$query]);
            
            // Also search by products in category
            $q->orWhereHas('products', function ($prodQuery) use ($query, $searchTerms) {
                $prodQuery->where('status', 'published')
                    ->where(function ($subQ) use ($query, $searchTerms) {
                        $subQ->where('name', 'LIKE', "%{$query}%")
                             ->orWhere('sku', 'LIKE', "%{$query}%");
                        
                        foreach ($searchTerms as $term) {
                            if ($term !== $query) {
                                $subQ->orWhere('name', 'LIKE', "%{$term}%")
                                     ->orWhere('sku', 'LIKE', "%{$term}%");
                            }
                        }
                    });
            });
        })
        ->orderByRaw("
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN SOUNDEX(name) = SOUNDEX(?) THEN 2
                ELSE 3
            END
        ", ["%{$query}%", $query])
        ->take(5)
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
    
        // Fast brand search
        $brands = Brand::with([
            'slug',
            'products' => fn($q) => $q->where('status', 'published')->take(4)->with(['slug', 'currency', 'brand' , 'seoUrl'])
        ])
        ->where('status', 'published')
        ->where(function ($q) use ($query, $searchTerms) {
            $q->where('name', 'LIKE', "%{$query}%");
            
            foreach ($searchTerms as $term) {
                if ($term !== $query) {
                    $q->orWhere('name', 'LIKE', "%{$term}%");
                }
            }
            
            $q->orWhereRaw('SOUNDEX(name) = SOUNDEX(?)', [$query]);
            
            // Also search by products of the brand
            $q->orWhereHas('products', function ($prodQuery) use ($query, $searchTerms) {
                $prodQuery->where('status', 'published')
                    ->where(function ($subQ) use ($query, $searchTerms) {
                        $subQ->where('name', 'LIKE', "%{$query}%")
                             ->orWhere('sku', 'LIKE', "%{$query}%");
                        
                        foreach ($searchTerms as $term) {
                            if ($term !== $query) {
                                $subQ->orWhere('name', 'LIKE', "%{$term}%")
                                     ->orWhere('sku', 'LIKE', "%{$term}%");
                            }
                        }
                    });
            });
        })
        ->orderByRaw("
            CASE 
                WHEN name LIKE ? THEN 1
                WHEN SOUNDEX(name) = SOUNDEX(?) THEN 2
                ELSE 3
            END
        ", ["%{$query}%", $query])
        ->take(8)
        ->get()
        ->map(function ($brand) use ($imageUrl, $mapProduct) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                'slug' => optional($brand->slug)->key,
                'url' => $brand->url,
                'image' => $brand->logo,
                'products' => $brand->products->map($mapProduct),
            ];
        });
    
        // Fast suggestions generation (only if few results)
        $suggestions = [];
        $totalResults = $products->count() + $categories->count() + $brands->count();
        
        if ($totalResults < 3) {
            // Quick suggestion using cached common terms
            $suggestions = Cache::remember('search_suggestions_' . substr(md5($query), 0, 8), 300, function () use ($query) {
                $commonTerms = [];
                
                // Get top brand names
                $topBrands = Brand::where('status', 'published')
                    ->whereRaw('SOUNDEX(name) = SOUNDEX(?)', [$query])
                    ->orWhere('name', 'LIKE', "%{$query}%")
                    ->limit(3)
                    ->pluck('name')
                    ->toArray();
                
                // Get top product terms
                $topProducts = Product::where('status', 'published')
                    ->whereRaw('SOUNDEX(name) = SOUNDEX(?)', [$query])
                    ->orWhere('name', 'LIKE', "%{$query}%")
                    ->limit(3)
                    ->pluck('name')
                    ->toArray();
                
                $commonTerms = array_merge($topBrands, $topProducts);
                
                // Extract individual words from product names
                $words = [];
                foreach ($topProducts as $product) {
                    $productWords = explode(' ', strtolower($product));
                    foreach ($productWords as $word) {
                        if (strlen($word) > 3 && !in_array($word, $words)) {
                            $words[] = $word;
                        }
                    }
                }
                
                return array_slice(array_unique(array_merge($commonTerms, $words)), 0, 3);
            });
        }
    
        $response = [
            'products' => $products,
            'categories' => $categories,
            'brands' => $brands,
            'query' => $query,
            'total_results' => $totalResults,
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
                'sku' => $product->sku,
                'category_url' => $product->category_url(),
                'parent_category_url' => $product->parent_category_url(),
                'url' => $product->seoUrl->url ?? null,
                'images' => json_decode($product->images) ?? [],
                'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                'front_sale_price' => $firstSupplier ? (float) ($firstSupplier->price ?? $firstSupplier->price) : null,
                'vendor_id' => $firstSupplier?->vendor_id,
                'currency_title' => $product->currency->symbol ?? null,
                'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                'price' => $firstSupplier ? (float) ($firstSupplier->price ?? $firstSupplier->price) : null,
                'sale_price' =>  $firstSupplier ? (float) ($firstSupplier->sale_price ?? $firstSupplier->sale_price) : null,
                'map' => $firstSupplier->map ?? null,
                'inventory' => $firstSupplier->inventory ?? null,
                'in_stock' => $firstSupplier->in_stock ?? null,
                'delivery_days' => $firstSupplier->delivery_days ?? null,
                'return_policy' => $firstSupplier->return_policy ?? null,
                'free_shipping' => $firstSupplier->free_shipping ?? null,
                'warranty_information' => $firstSupplier->warranty_information ?? null,
                'min_quantity' => $firstSupplier->min_quantity ?? 0,
                'quote_available' => $product->quote_available ?? null,
                 'isRequired' => $product->isRequired,
                'is_fixed' => $firstSupplier->is_fixed ?? 0,
                'brand' => $product->brand ? [
                    'id' => $product->brand->id,
                    'name' => $product->brand->name,
                    'slug' => optional($product->brand->slug)->key,
                ] : null,
            ];
        };

        // Query logic
        $products = Product::with(['slug', 'brand', 'currency', 'productSuppliers' ,  'seoUrl'])
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
  
    public function searchnlp(Request $request)
    {
          $query = $request->query('q', '');

        if (!$query) {
            return response()->json(['error' => 'Query parameter `q` is required.'], 400);
        }

        $scriptPath = base_path('app/Script/nlpmobile.py');

        $process = new Process(['/var/www/html/pim_flow_laravel/app/Script/venv/bin/python3', $scriptPath, $query]);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();
        $data = json_decode($output, true);

        return response()->json($data);
    }
    // public function searchnlp(Request $request)
    // {
    //     $query = trim($request->query('q', ''));

    //     if (!$query) {
    //         return response()->json(['error' => 'Query parameter `q` is required.'], 400);
    //     }

    //     $tokens = explode(' ', strtolower($query));

    //     // Start building the query
    //     $productQuery = DB::table('ec_products as ep')
    //         ->join('product_suppliers as ps', 'ep.id', '=', 'ps.product_id')
    //         ->leftJoin('seo_management as sm', 'sm.relational_id', '=', 'ep.id')
    //         ->select([
    //             'ep.name as product_name',
    //             'ep.sku',
    //             'ps.price',
    //             'ps.sale_price',
    //             'ps.delivery_days',
    //             'ps.warranty_information',
    //             'sm.url as seo_url'
    //         ])
    //         ->where('ep.status', 'published');

    //     // Add a dynamic "where" clause using tokens
    //     $productQuery->where(function ($q) use ($tokens) {
    //         foreach ($tokens as $token) {
    //             $q->orWhere('ep.name', 'LIKE', "%$token%");
    //         }
    //     });

    //     // Fetch limited results
    //     $products = $productQuery->limit(100)->get();

    //     // You can still calculate `kw` in PHP if needed
    //     $results = $products->map(function ($product) use ($tokens) {
    //         $name = strtolower($product->product_name);
    //         $kw_matches = collect($tokens)->filter(fn($token) => str_contains($name, $token))->count();

    //         return [
    //             'product_name' => $product->product_name,
    //             'sku' => $product->sku,
    //             'clicks' => (int)($product->clicks ?? 0),
    //             'price' => $product->price,
    //             'sale_price' => $product->sale_price,
    //             'delivery_days' => $product->delivery_days,
    //             'warranty_information' => $product->warranty_information,
    //             'seo_url' => $product->seo_url,
    //             'kw' => $kw_matches,
    //         ];
    //     });

    //     $sorted = $results
    //         ->filter(fn($item) => $item['kw'] > 0)
    //         ->sortByDesc(fn($item) => [$item['kw'], $item['clicks']])
    //         ->values()
    //         ->take(40);

    //     return response()->json($sorted);
    // }

//   public function searchnlp(Request $request)
//     {
//         $query = $request->input('query');

//         $response = Http::get(env('SEARCH_API_URL') . '/search', [
//             'query' => $query
//         ]);

//         return response()->json($response->json());
//     }
    
    

}