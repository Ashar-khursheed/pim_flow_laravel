<?php

namespace App\Http\Controllers;

use App\Models\BrandTemp1;
use App\Models\BrandTemp2;
use App\Models\BrandTemp3;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandTemp1Controller extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/brand-temp-1",
     *     summary="Get all brand temp records",
     *     tags={"BrandTemp1"},
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/BrandTemp1"))),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function index()
    {
        // Eager load brand and order by latest ID
        $brands = BrandTemp1::with(['brand:id,name'])
                    ->orderBy('id', 'desc')
                    ->get();
    
        // Add brand_name and hide the full brand object
        $brands->each(function ($item) {
            $item->brand_name = $item->brand->name ?? null;
            $item->makeHidden('brand');
        });
    
        return response()->json([
            'success' => true,
            'message' => __("msg_rec_list"),
            'data' => $brands
        ]);
    }
    
    /**
     * @OA\Post(
     *     path="/api/brand-temp-1",
     *     summary="Create brand temp",
     *     tags={"BrandTemp1"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"brand_id"},
     *                 @OA\Property(property="brand_id", type="integer"),
     *                 @OA\Property(property="page_top_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_top_banners_desktop_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_top_banners_desktop_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_top_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_top_banners_mobile_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_top_banners_mobile_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="category_banners[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="category_banners_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="category_banners_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(
     *                           property="category_id",
     *                           type="string",
     *                           description="A JSON string containing category_id and product_ids"
     *                  ),
     *                 @OA\Property(property="page_middle_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_middle_banners_desktop_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_middle_banners_desktop_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_middle_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_middle_banners_mobile_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_middle_banners_mobile_file_name[]", type="array", @OA\Items(type="string")),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created", @OA\JsonContent(ref="#/components/schemas/BrandTemp1")),
     *     @OA\Response(response=422, description="Validation error"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    // public function store(Request $request)
    // {
    //     $data = $this->handleUploads($request);
        
    //     // Ensure category_id is properly formatted
    //     if ($request->has('category_id')) {
    //         $data['category_id'] = $request->category_id;
    //     }

    //     $brand = BrandTemp1::create($data);
        
    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Brand template created successfully.',
    //         'data' => $brand
    //     ], 201);   
    // }
    public function store(Request $request)
    {
        $data = $this->handleUploads($request);
        
        // Ensure category_id is properly formatted
        if ($request->has('category_id')) {
            $data['category_id'] = $request->category_id;
        }
        
        // Check if brand_id exists in any of the brand tables
        if (isset($data['brand_id'])) {
            $brandId = $data['brand_id'];
            
            $existsInTemp1 = BrandTemp1::where('brand_id', $brandId)->exists();
            $existsInTemp2 = BrandTemp2::where('brand_id', $brandId)->exists();
            $existsInTemp3 = BrandTemp3::where('brand_id', $brandId)->exists();
            
            if ($existsInTemp1 || $existsInTemp2 || $existsInTemp3) {
                return response()->json([
                    'success' => false,
                    'message' => 'Brand ID already exists in one of the brand template tables.',
                ], 422);
            }
        }
        
        // Create the brand if it doesn't exist
        $brand = BrandTemp1::create($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Brand template created successfully.',
            'data' => $brand
        ], 201);   
    }
    
    /**
     * @OA\Get(
     *     path="/api/brand-temp-1/{id}",
     *     summary="Get specific brand temp",
     *     tags={"BrandTemp1"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Success", @OA\JsonContent(ref="#/components/schemas/BrandTemp1")),
     *     @OA\Response(response=404, description="Not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function show($id)
    {
        return response()->json(BrandTemp1::findOrFail($id));
    }

    /**
     * @OA\Post(
     *     path="/api/brand-temp-1/{id}",
     *     summary="Update brand temp",
     *     tags={"BrandTemp1"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="brand_id", type="integer"),
     *                 @OA\Property(property="page_top_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_top_banners_desktop_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_top_banners_desktop_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_top_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_top_banners_mobile_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_top_banners_mobile_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="category_banners[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="category_banners_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="category_banners_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(
     *                       property="category_id",
     *                       type="string",
     *                       description="A JSON string containing category_id and product_ids"
     *                   ),
     *                 @OA\Property(property="page_middle_banners_desktop[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_middle_banners_desktop_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_middle_banners_desktop_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_middle_banners_mobile[]", type="array", @OA\Items(type="string", format="binary")),
     *                 @OA\Property(property="page_middle_banners_mobile_alt_text[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="page_middle_banners_mobile_file_name[]", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="_method", type="string", example="PUT")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Updated", @OA\JsonContent(ref="#/components/schemas/BrandTemp1")),
     *     @OA\Response(response=404, description="Not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function update(Request $request, $id)
    {
        $brand = BrandTemp1::findOrFail($id);
        $data = $this->handleUploads($request, $brand->brand_id ?? $request->brand_id);

        if ($request->has('category_id')) {
            $data['category_id'] = $request->category_id;
        }

        $brand->update($data);

        // Add brand_name to the response
        $brand->load('brand');
        $brand->brand_name = $brand->brand->name ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Brand updated successfully.',
            'data' => $brand
        ], 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/brand-temp-1/{id}",
     *     summary="Delete brand temp",
     *     tags={"BrandTemp1"},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted", @OA\JsonContent(@OA\Property(property="message", type="string", example="Deleted"))),
     *     @OA\Response(response=404, description="Not found"),
     *     security={{"bearerAuth":{}}}
     * )
     */
    public function destroy($id)
    {
        BrandTemp1::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    private function handleUploads(Request $request, $brandId = null)
{
    $imageFields = [
        'page_top_banners_desktop',
        'page_top_banners_mobile',
        'category_banners',
        'page_middle_banners_desktop',
        'page_middle_banners_mobile',
    ];

    // Define alt text fields corresponding to image fields
    $altTextFields = [
        'page_top_banners_desktop_alt_text',
        'page_top_banners_mobile_alt_text',
        'category_banners_alt_text',
        'page_middle_banners_desktop_alt_text',
        'page_middle_banners_mobile_alt_text',
    ];
    
    // Define file name fields
    $fileNameFields = [
        'page_top_banners_desktop_file_name',
        'page_top_banners_mobile_file_name',
        'category_banners_file_name',
        'page_middle_banners_desktop_file_name',
        'page_middle_banners_mobile_file_name',
    ];

    // Get all data except the image, alt text, and file name fields
    $data = $request->except(array_merge($imageFields, $altTextFields, $fileNameFields));

    $brandName = 'unknown';
    if ($brandId || $request->brand_id) {
        $brandModel = Brand::find($brandId ?? $request->brand_id);
        if ($brandModel) {
            $brandName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $brandModel->name);
        }
    }

    $baseFolder = env('STORAGE_ENV', 'local') . "/Brand/{$brandName}";

    // Process each image field
    foreach ($imageFields as $index => $field) {
        $altTextField = $altTextFields[$index]; // Get corresponding alt text field
        $fileNameField = $fileNameFields[$index]; // Get corresponding file name field
        
        if ($request->hasFile($field)) {
            $files = [];
            $altTexts = [];
            $fileNames = [];
            
            // Get the alt text values from request
            $altTextValues = $request->input($altTextField, []);
            
            // Get the file name values from request
            $fileNameValues = $request->input($fileNameField, []);
            
            // Process and upload each file
            foreach ($request->file($field) as $i => $file) {
                // Use the provided file name if available, otherwise use original
                $customFileName = isset($fileNameValues[$i]) && !empty($fileNameValues[$i]) 
                    ? $fileNameValues[$i] 
                    : $file->getClientOriginalName();
                
                // Get file extension from original file
                $extension = $file->getClientOriginalExtension();
                
                // Make sure the custom filename has the correct extension
                if (!str_ends_with(strtolower($customFileName), "." . strtolower($extension))) {
                    $customFileName = $customFileName . '.' . $extension;
                }
                
                // Use the custom file name for the S3 storage
                $path = Storage::disk('s3')->putFileAs(
                    "{$baseFolder}/{$field}", 
                    $file, 
                    $customFileName
                );
                
                $url = Storage::disk('s3')->url($path);
                
                $files[] = $url;
                $fileNames[] = $customFileName;
                
                // Get corresponding alt text or use empty string if not provided
                $altText = isset($altTextValues[$i]) ? $altTextValues[$i] : '';
                $altTexts[] = $altText;
            }
            
            // Save files, alt texts, and file names to data array
            $data[$field] = $files;
            $data[$altTextField] = $altTexts;
            $data[$fileNameField] = $fileNames;
        }
    }

    return $data;
}
}