<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use ZipArchive;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductImageUploadController extends Controller
{
    /**
     * Upload product images from zip file to S3 and update database
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */

     /**
     * Upload product images from a zip file.
     *
     * @OA\Post(
     *     path="/api/product/upload-images",
     *     summary="Upload product images from zip file",
     *     description="Upload a ZIP file containing product images organized by SKU folders, extract and process them to S3, and update product records in the database. Only webp images with dimensions 1000x1000 are allowed.",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="zip_file",
     *                     type="string",
     *                     format="binary",
     *                     description="ZIP file containing product images organized in folders by SKU. Only webp images with dimensions 1000x1000 are allowed."
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Images processed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Product images processed successfully"),
     *             @OA\Property(
     *                 property="processed_skus",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="sku", type="string", example="ABC123"),
     *                     @OA\Property(property="status", type="string", example="success", description="success, no_images_found, product_not_found, validation_error"),
     *                     @OA\Property(property="image_count", type="integer", example=5, description="Number of images processed for this SKU"),
     *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"), description="Array of error messages for invalid images")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Unable to open the zip file")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="zip_file",
     *                     type="array",
     *                     @OA\Items(type="string", example="The zip file field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function uploadProductImages(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:102400', // 100MB max size
        ]);

        // Create a temporary directory to extract the zip file
        $tempPath = storage_path('app/temp/' . Str::random(10));
        File::makeDirectory($tempPath, 0755, true);

        try {
            // Get the uploaded zip file
            $zipFile = $request->file('zip_file');
            $zipFilePath = $zipFile->path();

            // Extract the zip file
            $zip = new ZipArchive();
            if ($zip->open($zipFilePath) !== true) {
                return response()->json(['error' => 'Unable to open the zip file'], 400);
            }
            
            $zip->extractTo($tempPath);
            $zip->close();

            // Process the extracted directory
            $processedSkus = $this->processExtractedDirectory($tempPath);

            // Clean up the temporary directory
            File::deleteDirectory($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Product images processed successfully',
                'processed_skus' => $processedSkus
            ]);
        } catch (\Exception $e) {
            // Clean up on error
            if (File::exists($tempPath)) {
                File::deleteDirectory($tempPath);
            }
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process the extracted directory structure
     *
     * @param string $extractPath
     * @return array
     */
    // private function processExtractedDirectory($extractPath)
    // {
    //     $processedSkus = [];
        
    //     // Get all directories in the extracted path (each directory represents a SKU)
    //     $skuDirectories = File::directories($extractPath);
        
    //     foreach ($skuDirectories as $skuDir) {
    //         // Get the SKU from the directory name
    //         $sku = basename($skuDir);
            
    //         // Find product with this SKU using the existing Product model
    //         $product = Product::where('sku', $sku)->first();
            
    //         if ($product) {
    //             // Process images for this product
    //             if ($product->approved == 1) {
    //             $processedSkus[] = [
    //                 'sku' => $sku,
    //                 'status' => 'already_approved',
    //                 'errors' => ['This product is already approved and cannot be modified.'],
    //             ];
    //             continue;
    //              }
    //              $result = $this->uploadProductImagesToS3($skuDir, $sku);

    //         if (!empty($result['imageUrls'])) {
    //             $product->images = $result['imageUrls'];
    //             $product->save();

    //             $processedSkus[] = [
    //                 'sku' => $sku,
    //                 'status' => empty($result['errors']) ? 'success' : 'partial_success',
    //                 'image_count' => count($result['imageUrls']),
    //                 'errors' => $result['errors']
    //             ];
    //         } else {
    //             $processedSkus[] = [
    //                 'sku' => $sku,
    //                 'status' => 'no_valid_images_found',
    //                 'errors' => $result['errors']
    //             ];
    //         }
    //     } else {
    //         $processedSkus[] = [
    //             'sku' => $sku,
    //             'status' => 'product_not_found'
    //         ];
    //     }
    // }
    private function processExtractedDirectory($extractPath)
{
    $processedSkus = [];

    // Get all directories in the extracted path (each directory represents a SKU)
    $skuDirectories = File::directories($extractPath);

    // Get the authenticated user and their role
    $user = auth()->user();
    $userRole = $user ? $user->getRoleNames()->first() : null;

    // Define roles that are allowed to override the approval check
    $allowedRoles = [
        'Super Admin',
        'Admin'
    ];

    foreach ($skuDirectories as $skuDir) {
        // Get the SKU from the directory name
        $sku = basename($skuDir);

        // Find product with this SKU using the existing Product model
        $product = Product::where('sku', $sku)->first();

        if ($product) {
            // Skip modification if approved AND user is not allowed
            if ($product->approved == 1 && !in_array($userRole, $allowedRoles)) {
                $processedSkus[] = [
                    'sku' => $sku,
                    'status' => 'already_approved',
                    'errors' => ['This product is already approved and cannot be modified.'],
                ];
                continue;
            }

            // Proceed with uploading
            $result = $this->uploadProductImagesToS3($skuDir, $sku);

            if (!empty($result['imageUrls'])) {
                $product->images = $result['imageUrls'];
                $product->save();

                $processedSkus[] = [
                    'sku' => $sku,
                    'status' => empty($result['errors']) ? 'success' : 'partial_success',
                    'image_count' => count($result['imageUrls']),
                    'errors' => $result['errors']
                ];
            } else {
                $processedSkus[] = [
                    'sku' => $sku,
                    'status' => 'no_valid_images_found',
                    'errors' => $result['errors']
                ];
            }
        } else {
            $processedSkus[] = [
                'sku' => $sku,
                'status' => 'product_not_found'
            ];
        }
    }

    return $processedSkus;
}



    /**
     * Upload images to S3 and return array of URLs and errors
     *
     * @param string $imagesDir
     * @param string $sku
     * @return array
     */
    private function uploadProductImagesToS3($imagesDir, $sku)
    {
        $storageEnv = env('STORAGE_ENV');
        $s3Path = $storageEnv . '/products/images/';
        $imageUrls = [];
        $errors = [];
        
        // Get all files in the SKU directory
        $files = File::files($imagesDir);
        
        // Filter for webp files only
        $imageFiles = [];
        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            
            if ($extension !== 'webp') {
                $errors[] = "File {$file->getFilename()} in SKU folder {$sku} is not a webp image.";
                continue;
            }
            
            $imageFiles[] = $file;
        }
        
        if (empty($imageFiles)) {
            $errors[] = "No webp image files found in SKU folder {$sku}.";
            return [
                'imageUrls' => $imageUrls,
                'errors' => $errors
            ];
        }
        
        // Upload each valid image to S3
        foreach ($imageFiles as $imageFile) {
            try {
                // Verify image dimensions using PHP's built-in GD library
                $imageInfo = getimagesize($imageFile->getPathname());
                if ($imageInfo === false) {
                    $errors[] = "Could not read image information for {$imageFile->getFilename()} in SKU folder {$sku}.";
                    continue;
                }
                
                $width = $imageInfo[0];
                $height = $imageInfo[1];
                
                if ($width !== 1000 || $height !== 1000) {
                    $errors[] = "Image {$imageFile->getFilename()} in SKU folder {$sku} has dimensions {$width}x{$height}, but must be exactly 1000x1000.";
                    continue;
                }
                
                // Generate a unique filename to prevent overwriting
                $uniqueFileName = $sku . '_' . Str::random(20) . '.webp';
                $s3FilePath = $s3Path . $uniqueFileName;
                
                // Open file and directly upload to S3
                $fileStream = fopen($imageFile->getPathname(), 'r');
                Storage::disk('s3')->put($s3FilePath, $fileStream);
                fclose($fileStream);
                
                // Get the full URL from S3 storage
                $imageUrl = Storage::disk('s3')->url($s3FilePath);
                
                // Add the full URL to the image URLs array
                $imageUrls[] = $imageUrl;
            } catch (\Exception $e) {
                $errors[] = "Error processing image {$imageFile->getFilename()} in SKU folder {$sku}: {$e->getMessage()}";
            }
        }
        
        if (empty($imageUrls)) {
            $errors[] = "No valid images found in SKU folder {$sku} that meet the required criteria (webp format, 1000x1000 dimensions).";
        }
        
        return [
            'imageUrls' => $imageUrls,
            'errors' => $errors
        ];
    }
}