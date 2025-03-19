<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ProductExportController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/products/export",
     *     summary="Export products as CSV",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Filter products by category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="brand_id",
     *         in="query",
     *         description="Filter products by brand ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *      @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter products by Store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Limit the number of products (default 100)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="fields",
     *         in="query",
     *         description="Comma-separated list of fields to include in export",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CSV file download",
     *         @OA\Header(header="Content-Disposition", @OA\Schema(type="string"))
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No products found for the given criteria.")
     *         )
     *     )
     * )
     */

    public function export(Request $request)
    {
        // Start with detailed logging
        Log::info('Export endpoint accessed', [
            'route' => request()->path(),
            'method' => request()->method(),
            'all_request_data' => $request->all(),
            'user' => auth()->check() ? auth()->id() : 'unauthenticated',
        ]);
        
        // Parse input parameters
        $categoryId = $request->input('category_id');
        $brandId = $request->input('brand_id');
        $storeId = $request->input('store_id');
        

        $limit = $request->input('limit', 100); // Default limit 100
        
        // Parse fields from comma-separated string to array if provided
        $fieldsParam = $request->input('fields');
        $selectedFields = $fieldsParam ? explode(',', $fieldsParam) : [];
        
        // Query builder
        $query = Product::query();
        
        // Eager load relationships 
        $query->with(['categories', 'brand', 'store', 'tags' , 'seoMetaData']);
        
        if ($categoryId) {
            Log::info('Filtering by category ID: ' . $categoryId);
            $query->whereHas('categories', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($brandId) {
            Log::info('Filtering by brand ID: ' . $brandId);
            $query->where('brand_id', $brandId);
        }
        
        if ($storeId) {
            Log::info('Filtering by Vendor ID: ' . $storeId);
            $query->where('store_id', $storeId);
        }
        
        // Get products
        $products = $query->limit($limit)->get();
        
        // Debugging log
        Log::info('Product Export Debug', [
            'SQL Query' => $query->toSql(),
            'Bindings' => $query->getBindings(),
            'Product Count' => $products->count(),
            'Category ID Filter' => $categoryId,
            'Limit Applied' => $limit,
            'Selected Fields' => $selectedFields
        ]);
    
        // Return message if products empty
        if ($products->isEmpty()) {
            return response()->json([
                "success" => false,
                "message" => "No products found for the given criteria.",
            ]);
        }
    
        // CSV ke liye fields define karna
        $allFields = [
            'id', 'url', 'name', 'content', 'description', 'warranty_information', 'sku', 'brand',
            'vendor', 'product_types', 'categories', 'tags', 'stock_status', 'with_storehouse_management',
            'quantity', 'cost_per_item', 'unit_of_measurement', 'price', 'sale_price', 'start_date_sale_price',
            'end_date_sale_price', 'minimum_order_quantity', 'box_quantity', 'delivery_days', 'variant_requires_shipping',
            'images', 'upload_video', 'seo_title', 'seo_description', 'barcode', 'refund_policy', 'status',
            'google_shopping_category', 'google_shopping_mpn', 'is_featured', 'weight_option', 'weight',
            'dimension_option', 'length', 'width', 'height', 'depth', 'shipping_weight_option',
            'shipping_weight', 'shipping_dimension_option', 'shipping_width', 'shipping_depth',
            'shipping_height', 'shipping_length', 'frequently_bought_together', 'compare_products',
            'variant_1_title', 'variant_1_value', 'variant_1_products', 'variant_2_title', 'variant_2_value',
            'variant_2_products', 'variant_3_title', 'variant_3_value', 'variant_3_products',
            'variant_color_title', 'variant_color_value', 'variant_color_products',
            'name_ar', 'description_ar', 'content_ar', 'warranty_information_ar'
        ];
    
        // Use selected fields if provided, otherwise use all fields
        $fields = !empty($selectedFields) ? array_intersect($allFields, $selectedFields) : $allFields;
    
        // CSV response create karna
        $response = new StreamedResponse(function () use ($products, $fields) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $fields);
            
            foreach ($products as $product) {
                $row = [];
                foreach ($fields as $field) {
                    // Format special fields
                    switch ($field) {
                        case 'categories':
                            $row[] = $product->categories ? implode(',', $product->categories->pluck('name')->toArray()) : '';
                            break;
                            
                        case 'tags':
                            $row[] = $product->tags ? implode(',', $product->tags->pluck('name')->toArray()) : '';
                            break;
                            
                        case 'brand':
                            // Extract just the brand name from the object
                            if (is_string($product->brand) && json_decode($product->brand)) {
                                $brandData = json_decode($product->brand, true);
                                $row[] = $brandData['name'] ?? '';
                            } elseif (is_object($product->brand) || is_array($product->brand)) {
                                $brandData = is_array($product->brand) ? $product->brand : $product->brand->toArray();
                                $row[] = $brandData['name'] ?? '';
                            } else {
                                $row[] = $product->brand ?? '';
                            }
                            break;
                            
                            case 'vendor':
                                $row[] = $product->store->name ?? ''; // Get store (vendor) name from the relationship
                                break;
                            
                            
                        case 'images':
                            // Format images as clean URLs
                            $imageData = $product->images;
                            if (is_string($imageData) && json_decode($imageData)) {
                                $imageArray = json_decode($imageData, true);
                                $cleanUrls = [];
                                foreach ($imageArray as $img) {
                                    if (is_string($img)) {
                                        $cleanUrls[] = str_replace('\/', '/', trim($img, '"'));
                                    }
                                }
                                $row[] = implode(',', $cleanUrls);
                            } else {
                                $row[] = is_string($imageData) ? $imageData : '';
                            }
                            break;
                            
                        case 'upload_video':
                            // Format videos as clean URLs
                            $videoData = $product->upload_video;
                            if (is_string($videoData) && json_decode($videoData)) {
                                $videoArray = json_decode($videoData, true);
                                $cleanUrls = [];
                                foreach ($videoArray as $video) {
                                    if (is_string($video)) {
                                        $cleanUrls[] = str_replace('\/', '/', trim($video, '"'));
                                    }
                                }
                                $row[] = implode(',', $cleanUrls);
                            } else {
                                $row[] = is_string($videoData) ? $videoData : '';
                            }
                            break;
                            
                        case 'frequently_bought_together':
                            // Format as comma-separated values
                            $fbtData = $product->frequently_bought_together;
                            if (is_string($fbtData) && json_decode($fbtData)) {
                                $fbtArray = json_decode($fbtData, true);
                                $values = array_map(function($item) {
                                    return $item['value'] ?? '';
                                }, $fbtArray);
                                $row[] = implode(',', $values);
                            } else {
                                $row[] = is_string($fbtData) ? $fbtData : '';
                            }
                            break;
                            
                            case 'compare_products':
                                // Ensure it's an array and format as comma-separated IDs
                                $compareData = is_string($product->compare_products) ? json_decode($product->compare_products, true) : $product->compare_products;
                                if (is_array($compareData)) {
                                    $row[] = implode(',', $compareData); // Ensure IDs are separated properly
                                } else {
                                    $row[] = '';
                                }
                                break;

                                case 'url':
                                    $row[] = $product->slug ? 'https://thehorecastore.co/products/' . $product->slug->key : '';
                                    break;
                                

                            case 'seo_title':
                                $row[] = $product->seoMetaData ? ($product->seoMetaData->value['seo_title'] ?? '') : '';
                                break;
                            
                            case 'seo_description':
                                $row[] = $product->seoMetaData ? ($product->seoMetaData->value['seo_description'] ?? '') : '';
                                break;
                                   

                            
                        default:
                            $row[] = $product->$field ?? '';
                    }
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        });
    
        // Headers set karna
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="products-' . date('Y-m-d') . '.csv"');
    
        return $response;
    }
}