<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Models\FrontEnd\DiffProduct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class DiffProductController extends Controller
{
   
    public function getDiffProducts(Request $request, $productInput = null)
    {
        try {
            $productInput = $productInput ?? $request->input('product_id');

            if (!$productInput) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ID or URL is required',
                ], 400);
            }

            // Determine if input is numeric ID or SEO URL
            if (is_numeric($productInput)) {
                $product = Product::where('id', $productInput)->first();
            } else {
                $product = Product::whereHas('seoUrl', function ($query) use ($productInput) {
                    $query->where('url', $productInput);
                })->first();
            }

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $productId = $product->id;
            $userId = Auth::id();
            $isUserLoggedIn = $userId !== null;

            Log::info('Fetching dif products for:', ['product_id' => $productId, 'user_id' => $userId]);

            $wishlistProductIds = $isUserLoggedIn
                ? DB::table('ec_wish_lists')->where('customer_id', $userId)->pluck('product_id')->map(fn($id) => (int) $id)->toArray()
                : session()->get('guest_wishlist', []);

            $difProductIds = DB::table('diff_brands')
                ->where('product_id', $productId)
                ->orderBy('priority', 'asc')
                ->orderByDesc('similarity')
                ->pluck('dif_id')
                ->toArray();

            if (empty($difProductIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No dif products found for this product',
                    'data' => [],
                ]);
            }

            $products = Product::with(['reviews:id,product_id,star', 'currency', 'productSuppliers', 'sellingUnitAttribute', 'seoUrl'])
                ->where('status', 'published')
                ->whereIn('id', $difProductIds)
                ->get()
                ->sortBy(fn($product) => array_search($product->id, $difProductIds));

            $transformedProducts = $products->map(function ($product) use ($wishlistProductIds) {
                $images = $this->normalizeMediaUrls($product->images);
                $videos = $this->normalizeMediaUrls($product->video_path);
                $totalReviews = $product->reviews?->count() ?? 0;
                $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;
                $quantity = $product->quantity ?? 0;
                $unitsSold = $product->units_sold ?? 0;
                $leftStock = $quantity - $unitsSold;

                $sellingType = null;
                if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                    $fullValue = $product->sellingUnitAttribute->attribute_value;
                    $attributeUnit = strpos($fullValue, '/') !== false
                        ? trim(explode('/', $fullValue)[1])
                        : $fullValue;

                    $sellingType = [
                        'attribute_value' => $fullValue,
                        'attribute_value_unit' => $attributeUnit,
                    ];
                }

                $firstSupplier = $product->productSuppliers->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'url' => $product->seoUrl->url ?? null,
                    'category_url' => $product->category_url(),
                    'parent_category_url' => $product->parent_category_url(),
                    'images' => $images,
                    'video_url' => $product->video_url,
                    'video_path' => $videos,
                    'sku' => $product->sku,
                    'start_date' => $product->start_date,
                    'end_date' => $product->end_date,
                    'currency' => $product->currency?->symbol,
                    'total_reviews' => $totalReviews,
                    'avg_rating' => $avgRating,
                    'leftStock' => $leftStock,
                    'currency_title' => $product->currency
                        ? ($product->currency->is_prefix_symbol
                            ? $product->currency->symbol
                            : ($product->price . ' ' . $product->currency->symbol))
                        : $product->price,
                    'in_wishlist' => in_array($product->id, $wishlistProductIds),
                    'selling_type' => $sellingType,
                    'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                    'price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                    'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                    'best_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'per_unit_price' => $product->per_unit_price,
                    'vendor_id' => $firstSupplier->vendor_id ?? null,
                    'map' => $firstSupplier ? (float) $firstSupplier->map : null,
                    'inventory' => $firstSupplier->inventory ?? null,
                    'in_stock' => $firstSupplier->in_stock ?? null,
                    'delivery_days' => $firstSupplier->delivery_days ?? null,
                    'return_policy' => $firstSupplier->return_policy ?? null,
                    'free_shipping' => $firstSupplier->free_shipping ?? null,
                    'warranty_information' => $firstSupplier->warranty_information ?? null,
                    'min_quantity' => $firstSupplier->min_quantity ?? 0,
                    'is_fixed' => $firstSupplier->is_fixed ?? 0,
                    'quote_available' => $product->quote_available ?? null,
                     'isRequired' => $product->isRequired,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedProducts->values(),
                'message' => 'dif products retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getDiffProducts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching dif products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getDiffGuestProducts(Request $request, $productInput = null)
    {
        try {
            $productInput = $productInput ?? $request->input('product_id');

            if (!$productInput) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product ID or URL is required',
                ], 400);
            }

            // Determine if input is numeric ID or SEO URL
            if (is_numeric($productInput)) {
                $product = Product::where('id', $productInput)->first();
            } else {
                $product = Product::whereHas('seoUrl', function ($query) use ($productInput) {
                    $query->where('url', $productInput);
                })->first();
            }

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $productId = $product->id;

            // Step 1: Get all dif product IDs
            $difProductIds = DB::table('diff_brands')
                ->where('product_id', $productId)
                ->orderBy('priority', 'asc')
                ->orderByDesc('similarity')
                ->pluck('dif_id')
                ->toArray();

            if (empty($difProductIds)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No dif products found for this product',
                    'data' => [],
                ]);
            }

            // Step 2: Get published products with those IDs
            $products = Product::with(['reviews:id,product_id,star', 'currency', 'productSuppliers', 'sellingUnitAttribute', 'seoUrl'])
                ->where('status', 'published')
                ->whereIn('id', $difProductIds)
                ->get()
                ->sortBy(fn($product) => array_search($product->id, $difProductIds));

            // Transform response
            $transformedProducts = $products->map(function ($product) {
                $images = $this->normalizeMediaUrls($product->images);
                $videos = $this->normalizeMediaUrls($product->video_path);

                $totalReviews = $product->reviews?->count() ?? 0;
                $avgRating = $totalReviews > 0 ? $product->reviews->avg('star') : null;

                $quantity = $product->quantity ?? 0;
                $unitsSold = $product->units_sold ?? 0;
                $leftStock = $quantity - $unitsSold;

                $sellingType = null;
                if ($product->sellingUnitAttribute && $product->sellingUnitAttribute->attribute_value) {
                    $fullValue = $product->sellingUnitAttribute->attribute_value;
                    $attributeUnit = strpos($fullValue, '/') !== false
                        ? trim(explode('/', $fullValue)[1])
                        : $fullValue;

                    $sellingType = [
                        'attribute_value' => $fullValue,
                        'attribute_value_unit' => $attributeUnit,
                    ];
                }

                $firstSupplier = $product->productSuppliers->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'url' => $product->seoUrl->url ?? null,
                    'category_url' => $product->category_url(),
                    'parent_category_url' => $product->parent_category_url(),
                    'images' => $images,
                    'video_url' => $product->video_url,
                    'video_path' => $videos,
                    'sku' => $product->sku,
                    'start_date' => $product->start_date,
                    'end_date' => $product->end_date,
                    'currency' => $product->currency?->symbol,
                    'total_reviews' => $totalReviews,
                    'avg_rating' => $avgRating,
                    'leftStock' => $leftStock,
                    'currency_title' => $product->currency
                        ? ($product->currency->is_prefix_symbol
                            ? $product->currency->symbol
                            : ($product->price . ' ' . $product->currency->symbol))
                        : $product->price,
                    'selling_type' => $sellingType,
                    'vendor_sku' => $firstSupplier->vendor_sku ?? null,
                    'price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                    'original_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'front_sale_price' => $firstSupplier ? (float) $firstSupplier->sale_price : null,
                    'best_price' => $firstSupplier ? (float) $firstSupplier->price : null,
                    'per_unit_price' => $product->per_unit_price,
                    'vendor_id' => $firstSupplier->vendor_id ?? null,
                    'map' => $firstSupplier ? (float) $firstSupplier->map : null,
                    'inventory' => $firstSupplier->inventory ?? null,
                    'in_stock' => $firstSupplier->in_stock ?? null,
                    'delivery_days' => $firstSupplier->delivery_days ?? null,
                    'return_policy' => $firstSupplier->return_policy ?? null,
                    'free_shipping' => $firstSupplier->free_shipping ?? null,
                    'warranty_information' => $firstSupplier->warranty_information ?? null,
                    'min_quantity' => $firstSupplier->min_quantity ?? 0,
                    'is_fixed' => $firstSupplier->is_fixed ?? 0,
                    'quote_available' => $product->quote_available ?? null,
                     'isRequired' => $product->isRequired,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedProducts->values(),
                'message' => 'dif products retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getDiffGuestProducts: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching dif products',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    protected function normalizeMediaUrls($media)
    {
        if (empty($media)) {
            return [];
        }

        if (is_string($media)) {
            $decoded = json_decode($media, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return is_array($decoded) ? $decoded : [];
            }
            return [];
        }

        if (is_array($media)) {
            return $media;
        }

        return [];
    }

  
   
    

}
