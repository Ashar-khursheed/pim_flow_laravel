<?php
namespace App\Http\Controllers\FrontEnd;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandTemp1;
use App\Models\BrandTemp2;
use App\Models\BrandTemp3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class BrandPageController extends Controller
{


      /**
     * @OA\Get(
     *     path="/api/frontend/brand-page/{id}",
     *     summary="Get brand data with template",
     *     tags={"Frontend-Brand Pages"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Brand ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="brand", type="object"),
     *             @OA\Property(property="template_type", type="string", example="temp1"),
     *             @OA\Property(
     *                 property="template_data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="category_id",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="category_id", type="integer", example=3),
     *                         @OA\Property(
     *                             property="category_details",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=3),
     *                             @OA\Property(property="name", type="string", example="Electronics")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Brand or Template not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Brand not found")
     *         )
     *     )
     * )
     */
    // public function show($id): JsonResponse
    // {
    //     $brand = Brand::find($id);
    
    //     if (!$brand) {
    //         return response()->json(['error' => 'Brand not found'], 404);
    //     }
    
    //     // Try finding matching template data
    //     $templateData = BrandTemp1::where('brand_id', $id)->first();
    //     $templateType = 'temp1';
    
    //     if (!$templateData) {
    //         $templateData = BrandTemp2::where('brand_id', $id)->first();
    //         $templateType = 'temp2';
    //     }
    
    //     if (!$templateData) {
    //         $templateData = BrandTemp3::where('brand_id', $id)->first();
    //         $templateType = 'temp3';
    //     }
    
    //     if (!$templateData) {
    //         return response()->json(['error' => 'No template data found for this brand'], 404);
    //     }
    
    //     // Get all unique category IDs from the templateData
    //     $categoryIds = [];
    //     if (!empty($templateData->category_id)) {
    //         foreach ($templateData->category_id as $categoryItem) {
    //             if (isset($categoryItem['category_id'])) {
    //                 $categoryIds[] = $categoryItem['category_id'];
    //             }
    //         }
    //     }
    //     $categoryIds = array_unique($categoryIds);
    
    //     // Fetch category details
    //     $categories = [];
    //     if (!empty($categoryIds)) {
    //         $categories = \DB::table('categories')
    //             ->whereIn('id', $categoryIds)
    //             ->get();
    //     }
    
    //     // Create category lookup
    //     $categoryData = [];
    //     foreach ($categories as $category) {
    //         $categoryData[$category->id] = $category;
    //     }
    
    //     // Enhance category data
    //     $enhancedCategoryData = [];
    //     if (!empty($templateData->category_id)) {
    //         foreach ($templateData->category_id as $categoryItem) {
    //             $catId = $categoryItem['category_id'];
    //             $newItem = $categoryItem;
    
    //             if (isset($categoryData[$catId])) {
    //                 $newItem['category_details'] = $categoryData[$catId];
    //             }
    
    //             $enhancedCategoryData[] = $newItem;
    //         }
    //     }
    
    //     // Convert template data to array and inject enhanced categories
    //     $templateDataArray = $templateData->toArray();
    //     $templateDataArray['category_id'] = $enhancedCategoryData;
    
    //     // ✅ Get reviews and average rating using Eloquent
    //     $products = $brand->products()
    //         ->where('status', 'published')
    //         ->with('reviews')
    //         ->get();
    
    //     $totalReviews = 0;
    //     $totalStars = 0;
    
    //     foreach ($products as $product) {
    //         $validReviews = $product->reviews->whereNotNull('star');
    //         $totalReviews += $validReviews->count();
    //         $totalStars += $validReviews->sum('star');
    //     }
    
    //     $avgRating = $totalReviews > 0 ? round($totalStars / $totalReviews, 1) : null;
    
    //     // ✅ Return everything
    //     return response()->json([
    //         'brand' => $brand,
    //         'template_type' => $templateType,
    //         'template_data' => $templateDataArray,
    //         'reviews' => [
    //             'total_reviews' => $totalReviews,
    //             'average_rating' => $avgRating,
    //         ],
    //     ]);
    // }
    public function show($id): JsonResponse
{
    // Step 1: Detect if $id is numeric or a slug
    if (is_numeric($id)) {
        $brand = Brand::find($id);
    } else {
        // Fetch relational_id from seo_management using the slug
        $seoEntry = \DB::table('seo_management')
            ->where('url', $id)
            ->where('relational_type', 'Brand')
            ->first();

        $brand = $seoEntry ? Brand::find($seoEntry->relational_id) : null;
    }

    if (!$brand) {
        return response()->json(['error' => 'Brand not found'], 404);
    }

    // Step 2: Load template data
    $templateData = BrandTemp1::where('brand_id', $brand->id)->first();
    $templateType = 'temp1';

    if (!$templateData) {
        $templateData = BrandTemp2::where('brand_id', $brand->id)->first();
        $templateType = 'temp2';
    }

    if (!$templateData) {
        $templateData = BrandTemp3::where('brand_id', $brand->id)->first();
        $templateType = 'temp3';
    }

    if (!$templateData) {
        return response()->json(['error' => 'No template data found for this brand'], 404);
    }

    // Step 3: Process category data
    $categoryIds = [];
    if (!empty($templateData->category_id)) {
        foreach ($templateData->category_id as $categoryItem) {
            if (isset($categoryItem['category_id'])) {
                $categoryIds[] = $categoryItem['category_id'];
            }
        }
    }

    $categoryIds = array_unique($categoryIds);
    $categories = [];

    if (!empty($categoryIds)) {
        $categories = \DB::table('categories')
            ->whereIn('id', $categoryIds)
            ->get();
    }

    $categoryData = [];
    foreach ($categories as $category) {
        $categoryData[$category->id] = $category;
    }

    $enhancedCategoryData = [];
    if (!empty($templateData->category_id)) {
        foreach ($templateData->category_id as $categoryItem) {
            $catId = $categoryItem['category_id'];
            $newItem = $categoryItem;

            if (isset($categoryData[$catId])) {
                $newItem['category_details'] = $categoryData[$catId];
            }

            $enhancedCategoryData[] = $newItem;
        }
    }

    $templateDataArray = $templateData->toArray();
    $templateDataArray['category_id'] = $enhancedCategoryData;

    // Step 4: Fetch reviews and calculate average rating
    $products = $brand->products()
        ->where('status', 'published')
        ->with('reviews')
        ->get();

    $totalReviews = 0;
    $totalStars = 0;

    foreach ($products as $product) {
        $validReviews = $product->reviews->whereNotNull('star');
        $totalReviews += $validReviews->count();
        $totalStars += $validReviews->sum('star');
    }

    $avgRating = $totalReviews > 0 ? round($totalStars / $totalReviews, 1) : null;

    return response()->json([
        'brand' => $brand,
        'template_type' => $templateType,
        'template_data' => $templateDataArray,
        'reviews' => [
            'total_reviews' => $totalReviews,
            'average_rating' => $avgRating,
        ],
    ]);
}

    
}