<?php
namespace App\Http\Controllers;

use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage; // ✅ RIGHT
use Illuminate\Validation\ValidationException;
class SubCategoryController extends Controller
{
 /**
 * @OA\Get(
 *     path="/api/subcategories",
 *     summary="Get all subcategories",
 *     tags={"Subcategories"},
 *     @OA\Response(
 *         response=200,
 *         description="List of Subcategories",
 *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/SubCategory"))
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */
public function index(Request $request)
{
    if (!auth()->user()->can('list sub category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }
    // Get limit parameter from request, default to 10 if not provided
    $perPage = $request->input('limit', 10);

    // Ensure it's at least 1
    $perPage = max((int)$perPage, 1);

    $subcategories = SubCategory::with(['category'])->paginate($perPage);

    // Transform each subcategory to update the nested category image
    $data = $subcategories->map(function ($subcat) {
        if ($subcat->category && $subcat->category->image) {
            $subcat->category->image = asset('storage/' . $subcat->category->image);
        } elseif ($subcat->category) {
            $subcat->category->image = null;
        }
        return $subcat;
    });

    return response()->json([
        'data' => $data,
        'current_page' => $subcategories->currentPage(),
        'limit' => $perPage,
        'total_pages' => $subcategories->lastPage(),
        'total_records' => $subcategories->total(),
    ]);
}
    /**
     * @OA\Get(
     *     path="/api/subcategories/{id}",
     *     summary="Get a specific subcategory by ID",
     *     tags={"Subcategories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subcategory details",
     *         @OA\JsonContent(ref="#/components/schemas/SubCategory")
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        if (!auth()->user()->can('show sub category page')) {
            return response()->json([
                'success' => false,
                'message' => "You don't have permission to access this module.",
            ]);
        }

        $subcategory = SubCategory::with(['category'])->findOrFail($id);
        return response()->json($subcategory);
    }
/**
 * @OA\Post(
 *     path="/api/subcategories",
 *     summary="Create a new subcategory",
 *     tags={"Subcategories"},
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"name", "category_id"},
 *                 @OA\Property(property="name", type="string", example="New Subcategory"),
 *                 @OA\Property(property="category_id", type="integer", example=1),
 *
 *                 @OA\Property(
 *                     property="products_ids[]",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Send as products_ids[] for each value",
 *                     example={1, 2, 3}
 *                 ),
 *                 @OA\Property(
 *                     property="attributes_ids[]",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Send as attributes_ids[] for each value",
 *                     example={1, 2}
 *                 ),

 *                 @OA\Property(
 *                     property="web_banners[0][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Web banner image file",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="web_banners[0][alt_text]",
 *                     type="string",
 *                     example="Main web banner alt text",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="web_banners[1][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Second web banner image",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="web_banners[1][alt_text]",
 *                     type="string",
 *                     example="Secondary web banner alt",
 *                     nullable=true
 *                 ),

 *                 @OA\Property(
 *                     property="mobile_banners[0][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Mobile banner image file",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="mobile_banners[0][alt_text]",
 *                     type="string",
 *                     example="Main mobile banner alt",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="mobile_banners[1][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Second mobile banner image",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="mobile_banners[1][alt_text]",
 *                     type="string",
 *                     example="Secondary mobile banner alt",
 *                     nullable=true
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=201,
 *         description="Subcategory Created",
 *         @OA\JsonContent(ref="#/components/schemas/SubCategory")
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */



 public function store(Request $request)
 {
    if (!auth()->user()->can('add sub category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }

     // Validate request
     $validated = $request->validate([
         'name' => 'required|string|max:255',
         'category_id' => 'required|exists:categories,id',
         'products_ids' => 'nullable|array',
         'products_ids.*' => 'exists:ec_products,id',
         'attributes_ids' => 'nullable|array',
         'attributes_ids.*' => 'exists:attributes,id',
         'web_banners' => 'array',
         'web_banners.*.image' => 'required|image',
         'web_banners.*.alt_text' => 'nullable|string',
         'mobile_banners' => 'array',
         'mobile_banners.*.image' => 'required|image',
         'mobile_banners.*.alt_text' => 'nullable|string',
     ]);

     // ❌ If a subcategory with the same category_id already exists
     $existing = SubCategory::where('category_id', $validated['category_id'])->first();
     if ($existing) {
         return response()->json([
             'message' => 'A subcategory page with this category ID already exists.',
         ], 422);
     }

     // ✅ Create the subcategory
     $subcategory = SubCategory::create([
         'name' => $validated['name'],
         'category_id' => $validated['category_id'],
     ]);

     // Associate product IDs
     if ($request->has('products_ids')) {
         $subcategory->update([
             'products_ids' => $request->input('products_ids')
         ]);
     }

     // Associate attribute IDs
     if ($request->has('attributes_ids')) {
         $subcategory->update([
             'attributes_ids' => $request->input('attributes_ids')
         ]);
     }

     // Upload banners to S3
     $webBannersData = [];
     if ($request->has('web_banners')) {
         foreach ($request->web_banners as $banner) {
             if (isset($banner['image'])) {
                 $path = $banner['image']->store('tanuj_local/subcategory/web', 's3');
                 $webBannersData[] = [
                     'image_name' => Storage::disk('s3')->url($path),
                     'alt_text' => $banner['alt_text'] ?? null,
                 ];
             }
         }
     }

     $mobileBannersData = [];
     if ($request->has('mobile_banners')) {
         foreach ($request->mobile_banners as $banner) {
             if (isset($banner['image'])) {
                 $path = $banner['image']->store('tanuj_local/subcategory/mobile', 's3');
                 $mobileBannersData[] = [
                     'image_name' => Storage::disk('s3')->url($path),
                     'alt_text' => $banner['alt_text'] ?? null,
                 ];
             }
         }
     }

     // Save banners
     $subcategory->update([
         'web_banners' => $webBannersData,
         'mobile_banners' => $mobileBannersData,
     ]);

     return response()->json([
        'success' => true,
        'message' => 'Subcategory created successfully',
        'subcategory' => $subcategory->fresh(['category'])->toArray()
    ], 201);

 }

/**
 * @OA\Post(
 *     path="/api/subcategories/{id}",
 *     summary="Update an existing subcategory",
 *     tags={"Subcategories"},
 *     @OA\Parameter(
 *         name="id",
 *         in="path",
 *         required=true,
 *         description="ID of the subcategory to update",
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\MediaType(
 *             mediaType="multipart/form-data",
 *             @OA\Schema(
 *                 required={"name", "category_id"},
 *                 @OA\Property(property="name", type="string", example="Updated Subcategory"),
 *                 @OA\Property(property="category_id", type="integer", example=1),
 *
 *                 @OA\Property(
 *                     property="products_ids[]",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Send as products_ids[] for each value",
 *                     example={1, 2, 3}
 *                 ),
 *                 @OA\Property(
 *                     property="attributes_ids[]",
 *                     type="array",
 *                     @OA\Items(type="integer"),
 *                     description="Send as attributes_ids[] for each value",
 *                     example={1, 2}
 *                 ),

 *                 @OA\Property(
 *                     property="web_banners[0][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Web banner image file",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="web_banners[0][alt_text]",
 *                     type="string",
 *                     example="Main web banner alt text",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="web_banners[1][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Second web banner image",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="web_banners[1][alt_text]",
 *                     type="string",
 *                     example="Secondary web banner alt",
 *                     nullable=true
 *                 ),

 *                 @OA\Property(
 *                     property="mobile_banners[0][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Mobile banner image file",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="mobile_banners[0][alt_text]",
 *                     type="string",
 *                     example="Main mobile banner alt",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="mobile_banners[1][image]",
 *                     type="string",
 *                     format="binary",
 *                     description="Second mobile banner image",
 *                     nullable=true
 *                 ),
 *                 @OA\Property(
 *                     property="mobile_banners[1][alt_text]",
 *                     type="string",
 *                     example="Secondary mobile banner alt",
 *                     nullable=true
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Subcategory Updated",
 *         @OA\JsonContent(ref="#/components/schemas/SubCategory")
 *     ),
 *     security={{"bearerAuth":{}}}
 * )
 */

 public function update(Request $request, $id)
 {
    if (!auth()->user()->can('update sub category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }

     // Validate request
     $validated = $request->validate([
         'name' => 'required|string|max:255',
         'category_id' => 'required|exists:categories,id',
         'products_ids' => 'nullable|array',
         'products_ids.*' => 'exists:ec_products,id',
         'attributes_ids' => 'nullable|array',
         'attributes_ids.*' => 'exists:attributes,id',
         'web_banners' => 'array',
         'web_banners.*.image' => 'nullable|image', // Make it nullable for optional upload
         'web_banners.*.alt_text' => 'nullable|string',
         'mobile_banners' => 'array',
         'mobile_banners.*.image' => 'nullable|image', // Make it nullable for optional upload
         'mobile_banners.*.alt_text' => 'nullable|string',
     ]);

     // Find the subcategory by ID
     $subcategory = SubCategory::findOrFail($id);

     // ✅ Only check for duplicate category_id if it has changed
     if ($validated['category_id'] != $subcategory->category_id) {
         $existing = SubCategory::where('category_id', $validated['category_id'])
                                 ->where('id', '!=', $subcategory->id) // Ensure it's not the same subcategory
                                 ->first();
         if ($existing) {
             return response()->json([
                 'message' => 'A subcategory page with this category ID already exists.',
             ], 422);
         }
     }

     // ✅ Update the subcategory details
     $subcategory->update([
         'name' => $validated['name'],
         'category_id' => $validated['category_id'],
     ]);

     // Update product IDs if present
     if ($request->has('products_ids')) {
         $subcategory->update([
             'products_ids' => $request->input('products_ids')
         ]);
     }

     // Update attribute IDs if present
     if ($request->has('attributes_ids')) {
         $subcategory->update([
             'attributes_ids' => $request->input('attributes_ids')
         ]);
     }

     // Upload new web banners if provided
     $webBannersData = $subcategory->web_banners ?? []; // Preserve existing web banners if none are uploaded
     if ($request->has('web_banners')) {
         foreach ($request->web_banners as $banner) {
             // Only update banner if the image is provided
             if (isset($banner['image'])) {
                 // Delete old banner images if new ones are uploaded
                 foreach ($webBannersData as $key => $existingBanner) {
                     // If banner already exists, remove old one from storage
                     Storage::disk('s3')->delete(parse_url($existingBanner['image_name'], PHP_URL_PATH));
                     unset($webBannersData[$key]);
                 }
                 $path = $banner['image']->store('tanuj_local/subcategory/web', 's3');
                 $webBannersData[] = [
                     'image_name' => Storage::disk('s3')->url($path),
                     'alt_text' => $banner['alt_text'] ?? null,
                 ];
             }
         }
     }

     // Upload new mobile banners if provided
     $mobileBannersData = $subcategory->mobile_banners ?? []; // Preserve existing mobile banners if none are uploaded
     if ($request->has('mobile_banners')) {
         foreach ($request->mobile_banners as $banner) {
             // Only update banner if the image is provided
             if (isset($banner['image'])) {
                 // Delete old banner images if new ones are uploaded
                 foreach ($mobileBannersData as $key => $existingBanner) {
                     // If banner already exists, remove old one from storage
                     Storage::disk('s3')->delete(parse_url($existingBanner['image_name'], PHP_URL_PATH));
                     unset($mobileBannersData[$key]);
                 }
                 $path = $banner['image']->store('tanuj_local/subcategory/mobile', 's3');
                 $mobileBannersData[] = [
                     'image_name' => Storage::disk('s3')->url($path),
                     'alt_text' => $banner['alt_text'] ?? null,
                 ];
             }
         }
     }

     // Save the updated banner data (only save if there's any update)
     $subcategory->update([
         'web_banners' => !empty($webBannersData) ? $webBannersData : $subcategory->web_banners,
         'mobile_banners' => !empty($mobileBannersData) ? $mobileBannersData : $subcategory->mobile_banners,
     ]);

     return response()->json([
        'success' => true,
         'message' => 'Subcategory updated successfully',
         'subcategory' => $subcategory->fresh(['category'])->toArray()
     ], 200);
 }



    /**
     * @OA\Delete(
     *     path="/api/subcategories/{id}",
     *     summary="Delete a subcategory",
     *     tags={"Subcategories"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Subcategory Deleted"
     *     ),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
    if (!auth()->user()->can('delete sub category page')) {
        return response()->json([
            'success' => false,
            'message' => "You don't have permission to access this module.",
        ]);
    }

        $subcategory = SubCategory::findOrFail($id);
        $subcategory->delete();

        return response()->json(['message' => 'Subcategory deleted successfully']);
    }
}
