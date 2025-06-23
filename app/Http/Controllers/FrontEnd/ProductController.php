<?php

namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GroupedProduct;
use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Review;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Add this line
use App\Models\FrontEnd\Customer;
use Illuminate\Support\Facades\Auth;


class ProductController extends Controller

{
    /**
     * @OA\Get(
     *     path="/api/frontend/products",
     *     summary="Get all products with filters and pagination (for authenticated and guest users)",
     *     tags={"Frontend-Product"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort products by 'created_at', 'price', or 'name'",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *      @OA\Parameter(
     *            name="product_id",
     *           in="query",
     *           description="Get details of a specific product by ID",
     *           required=false,
     *            @OA\Schema(type="integer")
     *       ),
     *     @OA\Response(
     *         response=200,
     *         description="List of products with filters, brand/category info, wishlist status, and pagination details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="brands", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string")
     *             )),
     *             @OA\Property(property="categories", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="name", type="string")
     *             )),
     *             @OA\Property(property="price_min", type="number", format="float"),
     *             @OA\Property(property="price_max", type="number", format="float")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getAllProducts(Request $request)
    {
                // Keep existing user and wishlist logic
                $userId = Auth::id();
                $isUserLoggedIn = $userId !== null;

                Log::info('User logged in:', ['user_id' => $userId]);

                $wishlistProductIds = [];
                if ($isUserLoggedIn) {
                    $wishlistProductIds = DB::table('ec_wish_lists')
                        ->where('customer_id', $userId)
                        ->pluck('product_id')
                        ->map(function($id) {
                            return (int) $id;
                        })
                        ->toArray();
                } else {
                    $wishlistProductIds = session()->get('guest_wishlist', []);
                }

                // Start building the base query
                $query = Product::with(['categories', 'brand' , 'brand.products.reviews'])
                    ->where('status', 'published');

                                    
                // Check if filtering by specific product ID
                $productId = $request->input('product_id');
                if ($productId) {
                    $query->where('id', $productId);
                }
                // Apply filters
                $this->applyFilters($query, $request);

                // Log query for debugging
                \Log::info($query->toSql());
                \Log::info($query->getBindings());

                // Get filtered IDs efficiently
                $filteredProductIds = $query->pluck('id');

                // Calculate min-max values only for filtered products
                $priceMin = Product::whereIn('id', $filteredProductIds)->min('sale_price');
                $priceMax = Product::whereIn('id', $filteredProductIds)->max('sale_price');

                $DeliveryMin = Product::whereIn('id', $filteredProductIds)
                    ->whereNotNull('delivery_days')
                    ->selectRaw('MIN(CAST(delivery_days AS UNSIGNED)) as min_delivery_days')
                    ->value('min_delivery_days');

                $DeliveryMax = Product::whereIn('id', $filteredProductIds)
                    ->whereNotNull('delivery_days')
                    ->selectRaw('MAX(CAST(delivery_days AS UNSIGNED)) as max_delivery_days')
                    ->value('max_delivery_days');

                // Get sort parameter
                $sortBy = $request->input('sort_by', 'created_at');
                $validSortOptions = ['created_at', 'price', 'name'];
                if (!in_array($sortBy, $validSortOptions)) {
                    $sortBy = 'created_at';
                }

                // Subquery for best price and delivery date
                $subQuery = Product::select('sku')
                    ->selectRaw('MIN(price) as best_price')
                    ->selectRaw('MIN(delivery_days) as best_delivery_date')
                    ->whereIn('id', $filteredProductIds)
                    ->groupBy('sku');

                // Paginate efficiently - only get the required number of products
                $perPage = 50;
                $page = $request->input('page', 1);

                $products = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                    $join->on('ec_products.sku', '=', 'best_products.sku')
                        ->whereColumn('ec_products.price', 'best_products.best_price');
                })
                ->whereIn('id', $filteredProductIds)
                ->select('ec_products.*', 'best_products.best_price', 'best_products.best_delivery_date')
                ->with([
                    'reviews' => function($query) {
                        $query->select('id', 'product_id', 'star');
                    },
                    'currency' ,
                    'categories',
                    'productAttributes.attributeDetails',
                     ])
                ->orderBy($sortBy, 'desc')
                ->paginate($perPage);

                // Add query parameters to pagination
                $products->appends($request->all());

                // Calculate pagination details
                $currentPage = $products->currentPage();
                $lastPage = $products->lastPage();
                $startPage = max($currentPage - 2, 1);
                $endPage = min($startPage + 4, $lastPage);

                if ($endPage - $startPage < 4) {
                    $startPage = max($endPage - 4, 1);
                }

                $pagination = [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $products->total(),
                    'has_more_pages' => $products->hasMorePages(),
                    'visible_pages' => range($startPage, $endPage),
                    'has_previous' => $currentPage > 1,
                    'has_next' => $currentPage < $lastPage,
                    'previous_page' => $currentPage - 1,
                    'next_page' => $currentPage + 1,
                ];

                    // Transform the products collection
                    $products->getCollection()->transform(function ($product) use ($wishlistProductIds) {

                        $product->benefits_features = json_decode($product->benefits_features, true);


                        if (is_string($product->description)) {
                            $decoded = json_decode($product->description, true);
                        
                            // If it's a valid JSON array, use it directly
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $product->description = $decoded;
                            } else {
                                // If it's not a valid JSON array, wrap the raw string in an array
                                $product->description = [$product->description];
                            }
                        }
                        

                        if ($product->brand) {
                            $product->brand_id = $product->brand->id;
                            $product->brand_name = $product->brand->name;
                            $product->brand_logo = $product->brand->logo;
                        
                            // Get review stats directly from the database
                            $brandProductIds = \DB::table('ec_products')
                                ->where('brand_id', $product->brand->id)
                                ->pluck('id');
                        
                            $brandReviewsQuery = \DB::table('ec_reviews')
                                ->whereIn('product_id', $brandProductIds);
                        
                            $brandReviewCount = $brandReviewsQuery->count();
                            $brandAvgRating = $brandReviewCount > 0
                                ? round($brandReviewsQuery->avg('star'), 1)
                                : null;
                        
                            $product->brand_avg_rating = $brandAvgRating;
                            $product->brand_review_count = $brandReviewCount;
                        }
                        $product->images = collect(json_decode($product->images, true))->map(function ($image) {
                            return  $image;
                        });

                        // Custom sorting for documents
                         // Custom sorting for documents
                         $desiredOrder = [
                            'Technical Specification Sheet',
                            'Warranty Information',
                            'Horeca Buying Guide',
                            'Setup & Usage Instructions',
                            'Product Installation Guide',
                            'Installation & Elevation Diagram',
                            'Spare Parts List',
                            'Product Brochure',
                        ];

                        $documents = json_decode($product->documents, true);
                        if (is_array($documents)) {
                            // Remove .pdf extension from titles
                            foreach ($documents as &$doc) {
                                $doc['title'] = preg_replace('/\.pdf$/i', '', $doc['title']);
                            }

                            // Sort documents by desired order
                            usort($documents, function ($a, $b) use ($desiredOrder) {
                                $posA = array_search($a['title'], $desiredOrder);
                                $posB = array_search($b['title'], $desiredOrder);
                                $posA = $posA === false ? PHP_INT_MAX : $posA;
                                $posB = $posB === false ? PHP_INT_MAX : $posB;
                                return $posA <=> $posB;
                            });

                            $product->documents = $documents;
                        } else {
                            $product->documents = [];
                        }
                        
                       
                        // $documents = json_decode($product->documents, true);
                        // if (is_array($documents)) {
                        //     // Remove .pdf extension from titles
                        //     foreach ($documents as &$doc) {
                        //         $doc['title'] = preg_replace('/\.pdf$/i', '', $doc['title']);
                        //     }

                        //     // Sort documents by desired order
                        //     usort($documents, function ($a, $b) use ($desiredOrder) {
                        //         $posA = array_search($a['title'], $desiredOrder);
                        //         $posB = array_search($b['title'], $desiredOrder);
                        //         $posA = $posA === false ? PHP_INT_MAX : $posA;
                        //         $posB = $posB === false ? PHP_INT_MAX : $posB;
                        //         return $posA <=> $posB;
                        //     });

                        //     $product->documents = $documents;
                        // } else {
                        //     $product->documents = [];
                        // }
                        $documents = $product->documents;

                        // If $documents is already an array, skip decoding
                        if (is_string($documents)) {
                            $documents = json_decode($documents, true);
                        }

                        // Proceed if it's a valid array
                        if (is_array($documents)) {
                            // Remove .pdf extension from titles
                            foreach ($documents as &$doc) {
                                if (isset($doc['title'])) {
                                    $doc['title'] = preg_replace('/\.pdf$/i', '', $doc['title']);
                                }
                            }
                            // Sort documents based on desired order
                            usort($documents, function ($a, $b) use ($desiredOrder) {
                                $posA = array_search($a['title'], $desiredOrder);
                                $posB = array_search($b['title'], $desiredOrder);
                                $posA = $posA === false ? PHP_INT_MAX : $posA;
                                $posB = $posB === false ? PHP_INT_MAX : $posB;
                                return $posA <=> $posB;
                            });

                            // Save back to product
                            $product->documents = $documents;
                        }

                        // Handle videos
                        $videoPaths = json_decode($product->video_path, true);
                        $product->video_path = collect($videoPaths)->map(function ($video) {
                            return $video; // Already a full URL, just return it
                        });
                        $sellingType = null;
                        if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                            $fullValue = $product->sellingUnitAttribute->attribute_value;
                            if (strpos($fullValue, '/') !== false) {
                                $parts = explode('/', $fullValue);
                                $product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
                            } else {
                                $product->sellingUnitAttribute->attribute_value_unit = $fullValue;
                            }
                        }
                        if ($product->ingredientsAttribute && $product->ingredientsAttribute->attribute_value) {
                            $fullValue = $product->ingredientsAttribute->attribute_value;
                        }

                        // Calculate per unit price
                        $unitsPerCase = $product->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
                        $packType = $product->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');
                        

                            $basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
                            $perUnitPrice = null;

                            if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
                                $unitValue = (float) $unitsPerCase->attribute_value;
                                if ($unitValue > 0) {
                                    $calculated = round($basePrice / $unitValue, 2);
                                    $perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
                                }
                            }

                            $product->per_unit_price = $perUnitPrice;

                
                        
                        // Add review and stock details
                        $totalReviews = $product->reviews->count();
                        $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
                        $quantity = $product->quantity ?? 0;
                        $unitsSold = $product->units_sold ?? 0;
                        $leftStock = $quantity - $unitsSold;

                        $product->total_reviews = $totalReviews;
                        $product->avg_rating = $avgRating;
                        $product->leftStock = $leftStock;
                        $product->in_wishlist = in_array($product->id, $wishlistProductIds);

                        // Handle currency
                        if ($product->currency) {
                            $product->currency_title = $product->currency->is_prefix_symbol
                                ? $product->currency->title
                                : $product->price . ' ' . $product->currency->title;
                        } else {
                            $product->currency_title = $product->price;
                        }

                    //     $product->category_list = $product->categories->map(function ($category) {
                    //         return [
                    //             'id' => $category->id,
                    //             'name' => $category->name,
                    //             'slug' => optional($category->slugable)->key, // Get slug from the slugs table
                    //         ];
                    //     });

                    //     return $product;
                    // });
                    // Get all categories including parent hierarchies
                    $allCategories = collect();

                    $product->categories->each(function ($category) use ($allCategories) {
                        // Function to recursively get parent categories
                        $getParentHierarchy = function($cat) use (&$getParentHierarchy) {
                            $parents = collect();
                            if ($cat->parent_id) {
                                // Get parent category - adjust model name if needed
                                $parent = Category::with('slug')->find($cat->parent_id);
                                if ($parent) {
                                    // Recursively get parent's hierarchy
                                    $parents = $parents->merge($getParentHierarchy($parent));
                                    $parents->push($parent);
                                }
                            }
                            return $parents;
                        };
                        
                        // Get all parent categories
                        $parentHierarchy = $getParentHierarchy($category);
                        
                        // Add parents to collection
                        $allCategories->push(...$parentHierarchy);
                        
                        // Add current category
                        $allCategories->push($category);
                    });

                    // Remove duplicates and map to desired structure
                    $product->category_list = $allCategories->unique('id')->map(function ($category) {
                        return [
                            'id' => $category->id,
                            'name' => $category->name,
                            'slug' => $category->slug,
                        ];
                    })->values();

                    return $product;
                });
                    return response()->json([
                        'success' => true,
                        'data' => $products,
                        'pagination' => $pagination,
                        'price_min' => $priceMin,
                        'price_max' => $priceMax
            
                    ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/public-products",
     *     summary="Get all public products with filters and price range (for guests)",
     *     tags={"Frontend-Product"},
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort products by 'created_at', 'price', or 'name'",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *            name="product_id",
     *           in="query",
     *           description="Get details of a specific product by ID",
     *           required=false,
     *            @OA\Schema(type="integer")
     *       ),
     *     @OA\Response(
     *         response=200,
     *         description="List of public products with min/max price and delivery details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="price_min", type="number", format="float"),
     *             @OA\Property(property="price_max", type="number", format="float"),
     *             @OA\Property(property="delivery_min", type="integer"),
     *             @OA\Property(property="delivery_max", type="integer")
     *         )
     *     )
     * )
     */
    public function getAllPublicProducts(Request $request)
    {

              // Start building the base query
                $query = Product::with(['categories', 'brand', 'brand.products.reviews'])
                    ->where('status', 'published');

                
                // Check if filtering by specific product ID
                $productId = $request->input('product_id');
                if ($productId) {
                    $query->where('id', $productId);
                }
                $this->applyFilters($query, $request);

                // Log query for debugging
                \Log::info($query->toSql());
                \Log::info($query->getBindings());

                // Get filtered IDs efficiently
                $filteredProductIds = $query->pluck('id');

                // Calculate min-max values only for filtered products
                $priceMin = Product::whereIn('id', $filteredProductIds)->min('sale_price');
                $priceMax = Product::whereIn('id', $filteredProductIds)->max('sale_price');
              

                $DeliveryMin = Product::whereIn('id', $filteredProductIds)
                    ->whereNotNull('delivery_days')
                    ->selectRaw('MIN(CAST(delivery_days AS UNSIGNED)) as min_delivery_days')
                    ->value('min_delivery_days');

                $DeliveryMax = Product::whereIn('id', $filteredProductIds)
                    ->whereNotNull('delivery_days')
                    ->selectRaw('MAX(CAST(delivery_days AS UNSIGNED)) as max_delivery_days')
                    ->value('max_delivery_days');

                // Get sort parameter
                $validSortOptions = ['created_at', 'price', 'name'];
                $sortBy = $request->input('sort_by', 'created_at');

                // if (!in_array($sortBy, $validSortOptions)) {
                //     $sortBy = 'created_at';
                // }

                // Subquery for best price and delivery date
                $subQuery = Product::select('sku')
                    ->selectRaw('MIN(price) as best_price')
                    ->selectRaw('MIN(delivery_days) as best_delivery_date')
                    ->whereIn('id', $filteredProductIds)
                    ->groupBy('sku');

                // Paginate efficiently - only get the required number of products
                $perPage = 30;
                $page = $request->input('page', 1);

                $products = Product::leftJoinSub($subQuery, 'best_products', function ($join) {
                    $join->on('ec_products.sku', '=', 'best_products.sku')
                        ->whereColumn('ec_products.price', 'best_products.best_price');
                })
                ->whereIn('id', $filteredProductIds)
                ->select('ec_products.*', 'best_products.best_price', 'best_products.best_delivery_date')
                ->with([
                    'reviews' => function($query) {
                        $query->select('id', 'product_id', 'star');
                    },
                    'currency',  'categories' , 'productAttributes.attributeDetails',
                ])
                ->orderBy($sortBy, 'desc')
                ->paginate($perPage);

                // Add query parameters to pagination
                $products->appends($request->all());

                // Calculate pagination details
                $currentPage = $products->currentPage();
                $lastPage = $products->lastPage();
                $startPage = max($currentPage - 2, 1);
                $endPage = min($startPage + 4, $lastPage);

                if ($endPage - $startPage < 4) {
                    $startPage = max($endPage - 4, 1);
                }

                $pagination = [
                    'current_page' => $currentPage,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'total' => $products->total(),
                    'has_more_pages' => $products->hasMorePages(),
                    'visible_pages' => range($startPage, $endPage),
                    'has_previous' => $currentPage > 1,
                    'has_next' => $currentPage < $lastPage,
                    'previous_page' => $currentPage - 1,
                    'next_page' => $currentPage + 1,
                ];

                // Get categories and brands (consider caching these)
                // $categories = Category::select('id', 'name')->get();

                    // Transform the products collection
                    $products->getCollection()->transform(function ($product) {

                        $product->benefits_features = json_decode($product->benefits_features, true);
                    
                        // if (is_string($product->description)) {
                        //     $product->description = json_decode($product->description, true);
                        // }
                        if (is_string($product->description)) {
                            $decoded = json_decode($product->description, true);
                        
                            // If it's a valid JSON array, use it directly
                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                $product->description = $decoded;
                            } else {
                                // If it's not a valid JSON array, wrap the raw string in an array
                                $product->description = [$product->description];
                            }
                        }
                        
                    
                        if ($product->brand) {
                            $product->brand_id = $product->brand->id;
                            $product->brand_name = $product->brand->name;
                            $product->brand_logo = $product->brand->logo;
                        
                            // Get review stats directly from the database
                            $brandProductIds = \DB::table('ec_products')
                                ->where('brand_id', $product->brand->id)
                                ->pluck('id');
                        
                            $brandReviewsQuery = \DB::table('ec_reviews')
                                ->whereIn('product_id', $brandProductIds);
                        
                            $brandReviewCount = $brandReviewsQuery->count();
                            $brandAvgRating = $brandReviewCount > 0
                                ? round($brandReviewsQuery->avg('star'), 1)
                                : null;
                        
                            $product->brand_avg_rating = $brandAvgRating;
                            $product->brand_review_count = $brandReviewCount;
                        }
                        
                        $product->images = collect(json_decode($product->images, true))->map(function ($image) {
                            return  $image;
                        });

                        // Custom sorting for documents
                        $desiredOrder = [
                            'Technical Specification Sheet',
                            'Warranty Information',
                            'Horeca Buying Guide',
                            'Setup & Usage Instructions',
                            'Product Installation Guide',
                            'Installation & Elevation Diagram',
                            'Spare Parts List',
                            'Product Brochure',
                        ];

                        $documents = json_decode($product->documents, true);
                        if (is_array($documents)) {
                            // Remove .pdf extension from titles
                            foreach ($documents as &$doc) {
                                $doc['title'] = preg_replace('/\.pdf$/i', '', $doc['title']);
                            }

                            // Sort documents by desired order
                            usort($documents, function ($a, $b) use ($desiredOrder) {
                                $posA = array_search($a['title'], $desiredOrder);
                                $posB = array_search($b['title'], $desiredOrder);
                                $posA = $posA === false ? PHP_INT_MAX : $posA;
                                $posB = $posB === false ? PHP_INT_MAX : $posB;
                                return $posA <=> $posB;
                            });

                            $product->documents = $documents;
                        } else {
                            $product->documents = [];
                        }
                                            
                        $videoPaths = json_decode($product->video_path, true);
                        $product->video_path = collect($videoPaths)->map(function ($video) {
                            return $video;
                        });
                        $sellingType = null;
                        if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                            $fullValue = $product->sellingUnitAttribute->attribute_value;
                            if (strpos($fullValue, '/') !== false) {
                                $parts = explode('/', $fullValue);
                                $product->sellingUnitAttribute->attribute_value_unit = trim($parts[1]);
                            } else {
                                $product->sellingUnitAttribute->attribute_value_unit = $fullValue;
                            }
                        }
                        if ($product->ingredientsAttribute && $product->ingredientsAttribute->attribute_value) {
                            $fullValue = $product->ingredientsAttribute->attribute_value;
                        }

                        // Calculate per unit price
                        $unitsPerCase = $product->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Units per Case');
                        $packType = $product->per_unit_price_attributes->firstWhere(fn($attr) => $attr->attributeDetails->name === 'Pack Type');
                        

                        $basePrice = ($product->sale_price > 0) ? $product->sale_price : $product->price;
                        $perUnitPrice = null;

                        if ($basePrice && $unitsPerCase && is_numeric($unitsPerCase->attribute_value)) {
                            $unitValue = (float) $unitsPerCase->attribute_value;
                            if ($unitValue > 0) {
                                $calculated = round($basePrice / $unitValue, 2);
                                $perUnitPrice = $calculated . ' ' . '/' . ($packType?->attribute_value ?? '');
                            }
                        }

                        $product->per_unit_price = $perUnitPrice;

                                            
                        // Reviews and stock
                        $totalReviews = $product->reviews->count();
                        $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
                        $quantity = $product->quantity ?? 0;
                        $unitsSold = $product->units_sold ?? 0;
                        $leftStock = $quantity - $unitsSold;
                    
                        $product->total_reviews = $totalReviews;
                        $product->avg_rating = $avgRating;
                        $product->leftStock = $leftStock;
                    
                        // Currency
                        if ($product->currency) {
                            $product->currency_title = $product->currency->is_prefix_symbol
                                ? $product->currency->title
                                : $product->price . ' ' . $product->currency->title;
                        } else {
                            $product->currency_title = $product->price;
                        }
                    
                        // ❌ Removed specifications section
                    
                        // ❌ Removed frequently bought together section
                       // Get all categories including parent hierarchies
                        $allCategories = collect();

                        $product->categories->each(function ($category) use ($allCategories) {
                            // Function to recursively get parent categories
                            $getParentHierarchy = function($cat) use (&$getParentHierarchy) {
                                $parents = collect();
                                if ($cat->parent_id) {
                                    // Get parent category - adjust model name if needed
                                    $parent = Category::with('slug')->find($cat->parent_id);
                                    if ($parent) {
                                        // Recursively get parent's hierarchy
                                        $parents = $parents->merge($getParentHierarchy($parent));
                                        $parents->push($parent);
                                    }
                                }
                                return $parents;
                            };
                            
                            // Get all parent categories
                            $parentHierarchy = $getParentHierarchy($category);
                            
                            // Add parents to collection
                            $allCategories->push(...$parentHierarchy);
                            
                            // Add current category
                            $allCategories->push($category);
                        });

                        // Remove duplicates and map to desired structure
                        $product->category_list = $allCategories->unique('id')->map(function ($category) {
                            return [
                                'id' => $category->id,
                                'name' => $category->name,
                                'slug' => $category->slug,
                            ];
                        })->values();

                        return $product;
                        });
                    

                    return response()->json([
                        'success' => true,
                        'data' => $products,
                        'pagination' => $pagination,
                        'price_min' => $priceMin,
                        'price_max' => $priceMax
        
                    ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/products/{id}/related",
     *     tags={"Frontend-Product"},
     *      security={{"bearerAuth": {}}},
     *     summary="Get related products by category",
     *     description="Returns a list of related products based on the same categories as the given product.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the product to find related items",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of related products",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found or no related categories found"
     *     )
     * )
     */

    public function relatedProducts($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Auth and wishlist logic
        $userId = Auth::id();
        $wishlistProductIds = [];

        if ($userId) {
            $wishlistProductIds = DB::table('ec_wish_lists')
                ->where('customer_id', $userId)
                ->pluck('product_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
        } else {
            $wishlistProductIds = session()->get('guest_wishlist', []);
        }

        // Get related categories
        $categoryIds = $product->categories->pluck('id');

        if ($categoryIds->isEmpty()) {
            return response()->json(['message' => 'No related categories found'], 404);
        }

        $relatedProducts = Product::whereHas('categories', function ($query) use ($categoryIds) {
                $query->whereIn('categories.id', $categoryIds);
            })
            ->where('id', '!=', $id)
            ->where('status', 'published')
            ->inRandomOrder()
            ->limit(20)
            ->with([
                'reviews:id,product_id,star',
                'currency'            ])
            ->get();

        $transformed = $relatedProducts->map(function ($product) use ($wishlistProductIds) {
            // $product->images = collect($product->images)->map(function ($image) {
            //     return filter_var($image, FILTER_VALIDATE_URL) ? $image : url('storage/' . ltrim($image, '/'));
            // });

            // $videoPaths = json_decode($product->video_path, true) ?? [];
            // $product->video_path = collect($videoPaths)->map(function ($video) {
            //     return filter_var($video, FILTER_VALIDATE_URL) ? $video : url('storage/' . ltrim($video, '/'));
            // });
            // $product->images = collect($product->images)->map(function ($image) {
            //             return $image;
            //         });

            $imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
            $cleanedImages = collect($imageArray)->map(function ($item) {
                if (is_string($item) && str_starts_with($item, '[')) {
                    $decoded = json_decode($item, true);
                    return is_array($decoded) ? $decoded : [$item];
                }
                return [$item];
            })->flatten()->filter()->values();

                    $videoPaths = json_decode($product->video_path, true);
                    $product->video_path = collect($videoPaths)->map(function ($video) {
                        return $video;
                    });


            $totalReviews = $product->reviews->count();
            $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
            $quantity = $product->quantity ?? 0;
            $unitsSold = $product->units_sold ?? 0;
            $leftStock = $quantity - $unitsSold;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'images' => $cleanedImages,
                'video_url' => $product->video_url,
                'video_path' => $product->video_path,
                'sku' => $product->sku,
                'original_price' => $product->price,
                'sale_price' => $product->sale_price,
                'front_sale_price' => $product->sale_price ?? $product->price,
                'price' => $product->price,
                'start_date' => $product->start_date,
                'end_date' => $product->end_date,
                'warranty_information' => $product->warranty_information,
                'currency' => $product->currency?->title,
                'total_reviews' => $totalReviews,
                'avg_rating' => $avgRating,
                'best_price' => $product->sale_price ?? $product->price,
                'best_delivery_date' => null, // optional to calculate
                'leftStock' => $leftStock,
                'currency_title' => $product->currency
                    ? ($product->currency->is_prefix_symbol
                        ? $product->currency->title
                        : ($product->price . ' ' . $product->currency->title))
                    : $product->price,
                'in_wishlist' => in_array($product->id, $wishlistProductIds),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/brands/{id}/products",
     *     tags={"Frontend-Product"},
     *     security={{"bearerAuth": {}}},
     *     summary="Get products by brand",
     *     description="Returns paginated list of products for the specified brand.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated products list",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products fetched successfully"),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="total", type="integer", example=50),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found"
     *     )
     * )
     */

    public function productsByBrand($id, Request $request)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        $perPage = $request->get('per_page', 10);

        // Auth and wishlist logic
        $userId = Auth::id();
        $wishlistProductIds = [];

        if ($userId) {
            $wishlistProductIds = DB::table('ec_wish_lists')
                ->where('customer_id', $userId)
                ->pluck('product_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
        } else {
            $wishlistProductIds = session()->get('guest_wishlist', []);
        }

        // Get paginated products with relationships
        $products = $brand->products()
            ->where('status', 'published')
            ->with(['reviews:id,product_id,star', 'currency'])
            ->paginate($perPage);

        // Transform each product
        $transformed = collect($products->items())->map(function ($product) use ($wishlistProductIds) {
            // $product->images = collect($product->images)->map(function ($image) {
            //     return filter_var($image, FILTER_VALIDATE_URL) ? $image : url('storage/' . ltrim($image, '/'));
            // });

            // $videoPaths = json_decode($product->video_path, true) ?? [];
            // $product->video_path = collect($videoPaths)->map(function ($video) {
            //     return filter_var($video, FILTER_VALIDATE_URL) ? $video : url('storage/' . ltrim($video, '/'));
            // });
            $product->images = collect($product->images)->map(function ($image) {
                return $image;
            });

            $videoPaths = json_decode($product->video_path, true);
            $product->video_path = collect($videoPaths)->map(function ($video) {
                return $video;
            });


            $totalReviews = $product->reviews->count();
            $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
            $quantity = $product->quantity ?? 0;
            $unitsSold = $product->units_sold ?? 0;
            $leftStock = $quantity - $unitsSold;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'images' => $product->images,
                'video_url' => $product->video_url,
                'video_path' => $product->video_path,
                'sku' => $product->sku,
                'original_price' => $product->price,
                'sale_price' => $product->sale_price,
                'front_sale_price' => $product->sale_price ?? $product->price,
                'price' => $product->price,
                'start_date' => $product->start_date,
                'end_date' => $product->end_date,
                'warranty_information' => $product->warranty_information,
                'currency' => $product->currency?->title,
                'total_reviews' => $totalReviews,
                'avg_rating' => $avgRating,
                'best_price' => $product->sale_price ?? $product->price,
                'best_delivery_date' => null,
                'leftStock' => $leftStock,
                'currency_title' => $product->currency
                    ? ($product->currency->is_prefix_symbol
                        ? $product->currency->title
                        : ($product->price . ' ' . $product->currency->title))
                    : $product->price,
                'in_wishlist' => in_array($product->id, $wishlistProductIds),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Products fetched successfully',
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
            'per_page' => $products->perPage(),
            'data' => $transformed,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/frontend/brands/{id}/sale-products",
     *     tags={"Frontend-Product"},
     *     security={{"bearerAuth": {}}},
     *     summary="Get sale products by brand",
     *     description="Returns paginated list of products on sale for a specific brand.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Brand ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Number of items per page",
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Paginated list of sale products",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Products fetched successfully"),
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="total", type="integer", example=20),
     *             @OA\Property(property="per_page", type="integer", example=10),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found"
     *     )
     * )
     */

    public function saleProductsByBrand($id, Request $request)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        $perPage = $request->get('per_page', 10);

        // Wishlist logic
        $userId = Auth::id();
        $wishlistProductIds = [];

        if ($userId) {
            $wishlistProductIds = DB::table('ec_wish_lists')
                ->where('customer_id', $userId)
                ->pluck('product_id')
                ->map(fn($id) => (int) $id)
                ->toArray();
        } else {
            $wishlistProductIds = session()->get('guest_wishlist', []);
        }

        // Fetch products with non-empty sale_price
        $products = $brand->products()
            ->where('status', 'published')
            ->whereNotNull('sale_price')
            ->where('sale_price', '>', 0)
            ->with(['reviews:id,product_id,star', 'currency'])
            ->paginate($perPage);

        $transformed = collect($products->items())->map(function ($product) use ($wishlistProductIds) {
            $product->images = collect($product->images)->map(function ($image) {
            return $image;
                });

                $videoPaths = json_decode($product->video_path, true);
                $product->video_path = collect($videoPaths)->map(function ($video) {
                    return $video;
                });
            $totalReviews = $product->reviews->count();
            $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
            $quantity = $product->quantity ?? 0;
            $unitsSold = $product->units_sold ?? 0;
            $leftStock = $quantity - $unitsSold;

            return [
                'id' => $product->id,
                'name' => $product->name,
                'images' => $product->images,
                'video_url' => $product->video_url,
                'video_path' => $product->video_path,
                'sku' => $product->sku,
                'original_price' => $product->price,
                'sale_price' => $product->sale_price,
                'front_sale_price' => $product->sale_price ?? $product->price,
                'price' => $product->price,
                'start_date' => $product->start_date,
                'end_date' => $product->end_date,
                'warranty_information' => $product->warranty_information,
                'currency' => $product->currency?->title,
                'total_reviews' => $totalReviews,
                'avg_rating' => $avgRating,
                'best_price' => $product->sale_price ?? $product->price,
                'best_delivery_date' => null,
                'leftStock' => $leftStock,
                'currency_title' => $product->currency
                    ? ($product->currency->is_prefix_symbol
                        ? $product->currency->title
                        : ($product->price . ' ' . $product->currency->title))
                    : $product->price,
                'in_wishlist' => in_array($product->id, $wishlistProductIds),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Sale products fetched successfully',
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
            'total' => $products->total(),
            'per_page' => $products->perPage(),
            'data' => $transformed,
        ]);
    }


    /**
     * @OA\Get(
     *     path="/api/frontend/brands/{id}/summary",
     *     summary="Get brand summary statistics",
     *     description="Returns summary stats like total units sold and total reviews for a given brand.",
     *     operationId="getBrandSummaryStats",
     *     tags={"Frontend-Product"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the brand",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Brand summary fetched successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="brand_id", type="integer", example=1),
     *                 @OA\Property(property="brand_name", type="string", example="Apple"),
     *                 @OA\Property(property="total_units_sold", type="integer", example=2500),
     *                 @OA\Property(property="total_reviews", type="integer", example=320)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Brand not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Brand not found")
     *         )
     *     )
     * )
     */
    public function brandSummaryStats($id)
    {
        $brand = Brand::find($id);

        if (!$brand) {
            return response()->json([
                'success' => false,
                'message' => 'Brand not found',
            ], 404);
        }

        $productIds = Product::where('brand_id', $id)->pluck('id');

        $totalUnitsSold = Product::whereIn('id', $productIds)->sum('units_sold');

        $totalReviews = DB::table('ec_reviews')
            ->whereIn('product_id', $productIds)
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Brand summary fetched successfully',
            'data' => [
                'brand_id' => $id,
                'brand_name' => $brand->name,
                'total_units_sold' => $totalUnitsSold,
                'total_reviews' => $totalReviews,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/category-random-products/{categoryId}",
     *     operationId="getCategoryWiseRandomProducts",
     *     tags={"Frontend-Product"},
     *     summary="Get 15 random products by category ID (including child categories)",
     *     description="Returns up to 15 random products from the specified category. If not enough products are found, it searches in child and descendant categories recursively.",
     *     
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         required=true,
     *         description="The ID of the category to fetch products from",
     *         @OA\Schema(type="integer")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Success response with product data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="name", type="string", example="Product Name"),
     *                     @OA\Property(property="sku", type="string", example="SKU123"),
     *                     @OA\Property(property="price", type="number", format="float", example=100.00),
     *                     @OA\Property(property="sale_price", type="number", format="float", example=90.00),
     *                     @OA\Property(property="best_delivery_date", type="string", format="date", example="2024-06-20"),
     *                     @OA\Property(property="total_reviews", type="integer", example=12),
     *                     @OA\Property(property="avg_rating", type="number", format="float", example=4.5),
     *                     @OA\Property(property="left_stock", type="integer", example=20),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(
     *                         property="images",
     *                         type="array",
     *                         @OA\Items(type="string", example="https://example.com/image.jpg")
     *                     ),
     *                     @OA\Property(property="original_price", type="number", example=100.00),
     *                     @OA\Property(property="front_sale_price", type="number", example=90.00),
     *                     @OA\Property(property="best_price", type="number", example=90.00)
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=400,
     *         description="Bad Request - category ID not provided",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="category_id is required")
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - No products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No products found in this category or its children")
     *         )
     *     )
     * )
     */

    public function getCategoryWiseRandomProducts(Request $request, $categoryId)
    {
        if (!$categoryId) {
            return response()->json(['error' => 'category_id is required'], 400);
        }

        $allCategoryIds = $this->getAllChildCategoryIds($categoryId); // includes the given ID

        $products = Product::where('status', 'published') // only published products
        ->whereHas('categories', function ($query) use ($allCategoryIds) {
            $query->whereIn('categories.id', $allCategoryIds);
        })
        ->inRandomOrder()
        ->take(15)
        ->get();


        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products found in this category or its children'], 404);
        }

        $data = $products->map(function ($product) {
            $imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
            $cleanedImages = collect($imageArray)->map(function ($item) {
                if (is_string($item) && str_starts_with($item, '[')) {
                    $decoded = json_decode($item, true);
                    return is_array($decoded) ? $decoded : [$item];
                }
                return [$item];
            })->flatten()->filter()->values();

            return [
                "id" => $product->id,
                "name" => $product->name,
                "sku" => $product->sku,
                "price" => $product->price,
                "sale_price" => $product->sale_price,
                "best_delivery_date" => $product->best_delivery_date,
                "total_reviews" => $product->reviews->count(),
                "avg_rating" => $product->reviews->count() > 0 ? $product->reviews->avg('star') : null,
                "left_stock" => $product->left_stock ?? 0,
                "currency" => $product->currency->symbol ?? 'USD',
                "images" => $cleanedImages,
                "original_price" => $product->price,
                "front_sale_price" => $product->price,
                "best_price" => $product->price,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/category-random-products-guest/{categoryId}",
     *     operationId="getCategoryWiseRandomProductsForUser",
     *     tags={"Frontend-Product"},
     *     summary="Get 15 random products by category ID for logged-in users (with wishlist info)",
     *     description="Returns up to 15 random products from the specified category and child categories, along with wishlist info for logged-in users.",
     *     
     *     @OA\Parameter(
     *         name="categoryId",
     *         in="path",
     *         required=true,
     *         description="The ID of the category to fetch products from",
     *         @OA\Schema(type="integer")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Success response with product and wishlist data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=123),
     *                     @OA\Property(property="name", type="string", example="Product Name"),
     *                     @OA\Property(property="sku", type="string", example="SKU123"),
     *                     @OA\Property(property="price", type="number", example=100.00),
     *                     @OA\Property(property="sale_price", type="number", example=90.00),
     *                     @OA\Property(property="best_delivery_date", type="string", format="date", example="2024-06-20"),
     *                     @OA\Property(property="total_reviews", type="integer", example=12),
     *                     @OA\Property(property="avg_rating", type="number", example=4.5),
     *                     @OA\Property(property="left_stock", type="integer", example=20),
     *                     @OA\Property(property="currency", type="string", example="USD"),
     *                     @OA\Property(property="images", type="array", @OA\Items(type="string")),
     *                     @OA\Property(property="original_price", type="number", example=100.00),
     *                     @OA\Property(property="front_sale_price", type="number", example=90.00),
     *                     @OA\Property(property="best_price", type="number", example=90.00),
     *                     @OA\Property(property="is_in_wishlist", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Login required"
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Not Found - No products found"
     *     )
     * )
     */
    public function getCategoryWiseRandomProductsForUser(Request $request, $categoryId)
    {
          // Auth and wishlist logic
          $userId = Auth::id();
          $wishlistProductIds = [];
  
          if ($userId) {
              $wishlistProductIds = DB::table('ec_wish_lists')
                  ->where('customer_id', $userId)
                  ->pluck('product_id')
                  ->map(fn($id) => (int) $id)
                  ->toArray();
          } else {
              $wishlistProductIds = session()->get('guest_wishlist', []);
          }

        if (!$categoryId) {
            return response()->json(['error' => 'category_id is required'], 400);
        }

        $allCategoryIds = $this->getAllChildCategoryIds($categoryId);

        $products = Product::where('status', 'published') // only published products
        ->whereHas('categories', function ($query) use ($allCategoryIds) {
            $query->whereIn('categories.id', $allCategoryIds);
        })
        ->inRandomOrder()
        ->take(15)
        ->get();
    

        if ($products->isEmpty()) {
            return response()->json(['message' => 'No products found in this category or its children'], 404);
        }

        $transformed = $products->map(function ($product) use ($wishlistProductIds) {
            // $product->images = collect($product->images)->map(function ($image) {
            //     return filter_var($image, FILTER_VALIDATE_URL) ? $image : url('storage/' . ltrim($image, '/'));
            // });

            // $videoPaths = json_decode($product->video_path, true) ?? [];
            // $product->video_path = collect($videoPaths)->map(function ($video) {
            //     return filter_var($video, FILTER_VALIDATE_URL) ? $video : url('storage/' . ltrim($video, '/'));
            // });
            $imageArray = is_array($product->images) ? $product->images : json_decode($product->images, true);
            $cleanedImages = collect($imageArray)->map(function ($item) {
                if (is_string($item) && str_starts_with($item, '[')) {
                    $decoded = json_decode($item, true);
                    return is_array($decoded) ? $decoded : [$item];
                }
                return [$item];
            })->flatten()->filter()->values();

                    $videoPaths = json_decode($product->video_path, true);
                    $product->video_path = collect($videoPaths)->map(function ($video) {
                        return $video;
                    });


            $totalReviews = $product->reviews->count();
            $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
            $quantity = $product->quantity ?? 0;
            $unitsSold = $product->units_sold ?? 0;
            $leftStock = $quantity - $unitsSold;

            return [
                'id' => $product->id,
                'name' => $product->name,
                "images" => $cleanedImages,
                'video_url' => $product->video_url,
                'video_path' => $product->video_path,
                'sku' => $product->sku,
                'original_price' => $product->price,
                'sale_price' => $product->sale_price,
                'front_sale_price' => $product->sale_price ?? $product->price,
                'price' => $product->price,
                'start_date' => $product->start_date,
                'end_date' => $product->end_date,
                'warranty_information' => $product->warranty_information,
                'currency' => $product->currency?->title,
                'total_reviews' => $totalReviews,
                'avg_rating' => $avgRating,
                'best_price' => $product->sale_price ?? $product->price,
                'best_delivery_date' => null, // optional to calculate
                'leftStock' => $leftStock,
                'currency_title' => $product->currency
                    ? ($product->currency->is_prefix_symbol
                        ? $product->currency->title
                        : ($product->price . ' ' . $product->currency->title))
                    : $product->price,
                'in_wishlist' => in_array($product->id, $wishlistProductIds),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformed
        ]);
    }
    

    private function getAllChildCategoryIds($categoryId)
    {
        // Get all categories
        $allCategories = Category::select('id', 'parent_id')->get();
    
        // Build a lookup table
        $childrenMap = [];
        foreach ($allCategories as $cat) {
            $childrenMap[$cat->parent_id][] = $cat->id;
        }
    
        // Recursively gather all children
        $stack = [$categoryId];
        $result = [];
    
        while (!empty($stack)) {
            $current = array_pop($stack);
            $result[] = $current;
            if (isset($childrenMap[$current])) {
                foreach ($childrenMap[$current] as $childId) {
                    $stack[] = $childId;
                }
            }
        }
    
        return $result;
    }
    



    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Http\Request $request)
        {
            // Log the request to ensure you're receiving the correct parameters
            \Log::info($request->all());
          \Log::info('Request Parameters:', $request->all());
            // Apply ID filter
            if ($request->has('id')) {
                $id = $request->input('id');
                $query->where('id', $id);
                \Log::info('Filter by ID: ' . $id);
            }

            // Search filters
            // if ($request->has('search')) {
            //     $searchTerm = $request->input('search');
            //     $query->where(function($q) use ($searchTerm) {
            //         $q->where('name', 'like', '%' . $searchTerm . '%')
            //           ->orWhere('sku', 'like', '%' . $searchTerm . '%');
            //     });
            // }

                    // Search filters with category and brand

            // Search filter (product name or SKU)

            if ($request->has('search')) {
                $searchTerm = $request->input('search');
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'like', '%' . $searchTerm . '%')
                      ->orWhere('sku', 'like', '%' . $searchTerm . '%')
                      ->orWhereHas('categories', function($q) use ($searchTerm) {
                          $q->where('name', 'like', '%' . $searchTerm . '%');
                      })
                      ->orWhereHas('brand', function($q) use ($searchTerm) {
                          $q->where('name', 'like', '%' . $searchTerm . '%');
                      });
                });
            }

            if ($request->has('name')) {
                $query->where('name', 'LIKE', '%' . $request->input('name') . '%');
            }

            if ($request->has('description')) {
                $query->where('description', 'LIKE', '%' . $request->input('description') . '%');
            }

            // SKU filter
            if ($request->has('sku')) {
                $skus = $request->input('sku');
                if (is_array($skus)) {
                    $query->whereIn('sku', $skus);
                } else {
                    $query->where('sku', $skus);
                }
            }

            // Status filter
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Stock status filter
            if ($request->has('stock_status')) {
                $query->where('stock_status', $request->input('stock_status'));
            }

            // Numerical filters
                // Delivery Days
            if ($request->has('delivery_days')) {
                $query->where('delivery_days', $request->input('delivery_days'));
            }
            if ($request->has('price_min')) {
                $query->where('price', '>=', $request->input('price_min'));
            }

            if ($request->has('price_max')) {
                $query->where('price', '<=', $request->input('price_max'));
            }

            if ($request->has('quantity_min')) {
                $query->where('quantity', '>=', $request->input('quantity_min'));
            }

            if ($request->has('quantity_max')) {
                $query->where('quantity', '<=', $request->input('quantity_max'));
            }

            // Date filters
            if ($request->has('start_date')) {
                $query->where('created_at', '>=', $request->input('start_date'));
            }

            if ($request->has('end_date')) {
                $query->where('created_at', '<=', $request->input('end_date'));
            }

        
            if ($request->has('is_featured')) {
                $query->where('is_featured', $request->input('is_featured'));
            }

            if ($request->has('rating')) {
                $rating = $request->input('rating');
                $query->whereHas('reviews', function($q) use ($rating) {
                    $q->selectRaw('product_id, AVG(star) as avg_rating') // Include product_id in the select statement
                      ->groupBy('product_id')
                      ->havingRaw('AVG(star) = ?', [$rating]); // Change from >= to =
                });
            }

                    if ($request->has('brand_id')) {
                        $brandIds = $request->input('brand_id');

                        // Convert to array if needed
                        if (!is_array($brandIds)) {
                            $brandIds = explode(',', $brandIds);
                        }

                        // Ensure brand IDs are integers
                        $brandIds = array_map('intval', $brandIds);

                        \Log::info('Filtering by Brand IDs: ', $brandIds);

                        // Apply filter on the existing query object
                        $query->whereIn('brand_id', $brandIds);
                    }
                    // Continue with any other filters or sorting options



            // Brand filter by name
            if ($request->has('brand_names')) {
                $brandNames = $request->input('brand_names');

                // Check if $brandNames is an array
                if (is_array($brandNames)) {
                    // Fetch brand IDs based on names
                    $brandIds = Brand::whereIn('name', $brandNames)->pluck('id');

                    // Apply the filter using brand IDs sd
                    $query->whereIn('brand_id', $brandIds);
                } else {
                    // If it's a single name, convert it into an array
                    $brandIds = Brand::where('name', $brandNames)->pluck('id');
                    $query->whereIn('brand_id', $brandIds);
                }
            }

                     // Sort by price if specified, else default to the general `sort_by` handling
            if ($request->has('sort_by_price')) {
                $order = strtolower($request->input('sort_by_price')); // Normalize input
                if (in_array($order, ['asc', 'desc'])) {
                    $query->orderBy('sale_price', $order);
                    \Log::info("Sorting by price in $order order");
                } else {
                    \Log::info("Invalid sort_by_price parameter: $order");
                }
            } else {
                // General sorting by other columns
                $allowedSortBy = ['id', 'price', 'created_at', 'name'];
                $sortBy = $request->input('sort_by', 'id');
                $sortOrder = strtolower($request->input('sort_order', 'asc'));

                if (in_array($sortBy, $allowedSortBy) && in_array($sortOrder, ['asc', 'desc'])) {
                    $query->orderBy($sortBy, $sortOrder);
                    \Log::info("Sorting by: $sortBy in $sortOrder order");
                } else {
                    \Log::info("Invalid sort parameters: sort_by = $sortBy, sort_order = $sortOrder");
                }
            }

             //$products = $query->orderBy($sortBy, 'asc')->paginate($request->input('per_page', 15)); // Pagination

                        //  $products = $query->orderBy($sortBy, 'asc'); // Pagination


            // Log the final SQL query for debugging
            \Log::info($query->toSql());
            \Log::info($query->getBindings());
        }



}    