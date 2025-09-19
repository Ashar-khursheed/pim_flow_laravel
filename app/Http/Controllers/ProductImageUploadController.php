<?php


// namespace App\Http\Controllers;

// use App\Http\Controllers\Controller;
// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Storage;
// use Illuminate\Support\Facades\File;
// use ZipArchive;
// use App\Models\Product;
// use Illuminate\Support\Str;

// class ProductImageUploadController extends Controller
// {
//     /**
//      * Upload product images from zip file to S3 and update database
//      *
//      * @param Request $request
//      * @return \Illuminate\Http\JsonResponse
//      */

//     /**
//      * Upload product images from a zip file.
//      *
//      * @OA\Post(
//      *     path="/api/product/upload-images",
//      *     summary="Upload product images from zip file",
//      *     description="Upload a ZIP file containing product images organized by SKU folders, extract and process them to S3, and update product records in the database. Only webp images with dimensions 1000x1000 are allowed.",
//      *     tags={"Products"},
//      *     security={{"bearerAuth":{}}},
//      *     @OA\RequestBody(
//      *         required=true,
//      *         @OA\MediaType(
//      *             mediaType="multipart/form-data",
//      *             @OA\Schema(
//      *                 @OA\Property(
//      *                     property="zip_file",
//      *                     type="string",
//      *                     format="binary",
//      *                     description="ZIP file containing product images organized in folders by SKU. Only webp images with dimensions 1000x1000 are allowed."
//      *                 )
//      *             )
//      *         )
//      *     ),
//      *     @OA\Response(
//      *         response=200,
//      *         description="Images processed successfully",
//      *         @OA\JsonContent(
//      *             @OA\Property(property="success", type="boolean", example=true),
//      *             @OA\Property(property="message", type="string", example="Product images processed successfully"),
//      *             @OA\Property(
//      *                 property="processed_skus",
//      *                 type="array",
//      *                 @OA\Items(
//      *                     type="object",
//      *                     @OA\Property(property="sku", type="string", example="ABC123"),
//      *                     @OA\Property(property="status", type="string", example="success", description="success, no_images_found, product_not_found, validation_error"),
//      *                     @OA\Property(property="image_count", type="integer", example=5, description="Number of images processed for this SKU"),
//      *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"), description="Array of error messages for invalid images")
//      *                 )
//      *             )
//      *         )
//      *     ),
//      *     @OA\Response(
//      *         response=400,
//      *         description="Invalid input",
//      *         @OA\JsonContent(
//      *             @OA\Property(property="error", type="string", example="Unable to open the zip file")
//      *         )
//      *     ),
//      *     @OA\Response(
//      *         response=422,
//      *         description="Validation error",
//      *         @OA\JsonContent(
//      *             @OA\Property(property="message", type="string", example="The given data was invalid."),
//      *             @OA\Property(
//      *                 property="errors",
//      *                 type="object",
//      *                 @OA\Property(
//      *                     property="zip_file",
//      *                     type="array",
//      *                     @OA\Items(type="string", example="The zip file field is required.")
//      *                 )
//      *             )
//      *         )
//      *     ),
//      *     @OA\Response(
//      *         response=500,
//      *         description="Server error",
//      *         @OA\JsonContent(
//      *             @OA\Property(property="success", type="boolean", example=false),
//      *             @OA\Property(property="error", type="string", example="Error message")
//      *         )
//      *     )
//      * )
//      */
//     public function uploadProductImages(Request $request)
//     {
//         // Validate the uploaded file
//         $request->validate([
//             'zip_file' => 'required|file|mimes:zip|max:102400', // 100MB max size
//         ]);

//         // Create a temporary directory to extract the zip file
//         $tempPath = storage_path('app/temp/' . Str::random(10));
//         File::makeDirectory($tempPath, 0755, true);

//         try {
//             // Get the uploaded zip file
//             $zipFile = $request->file('zip_file');
//             $zipFilePath = $zipFile->path();

//             // Extract the zip file
//             $zip = new ZipArchive();
//             if ($zip->open($zipFilePath) !== true) {
//                 return response()->json(['error' => 'Unable to open the zip file'], 400);
//             }

//             $zip->extractTo($tempPath);
//             $zip->close();

//             // Process the extracted directory
//             $processedSkus = $this->processExtractedDirectory($tempPath);

//             // Clean up the temporary directory
//             File::deleteDirectory($tempPath);

//             return response()->json([
//                 'success' => true,
//                 'message' => 'Product images processed successfully',
//                 'processed_skus' => $processedSkus
//             ]);
//         } catch (\Exception $e) {
//             // Clean up on error
//             if (File::exists($tempPath)) {
//                 File::deleteDirectory($tempPath);
//             }

//             return response()->json([
//                 'success' => false,
//                 'error' => $e->getMessage()
//             ], 500);
//         }
//     }

//     /**
//      * Sanitize SKU for use in filenames and URLs
//      * Removes or replaces characters that could cause issues in URLs
//      *
//      * @param string $sku
//      * @return string
//      */
//     private function sanitizeSku($sku)
//     {
//         // Replace spaces with underscores and remove/replace problematic characters
//         $sanitized = str_replace(' ', '_', $sku);

//         // Remove or replace other special characters that could cause URL issues
//         $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sanitized);

//         // Remove multiple consecutive underscores
//         $sanitized = preg_replace('/_+/', '_', $sanitized);

//         // Trim underscores from start and end
//         $sanitized = trim($sanitized, '_');

//         // Ensure it's not empty after sanitization
//         if (empty($sanitized)) {
//             $sanitized = 'unknown_sku';
//         }

//         return $sanitized;
//     }

//     /**
//      * Process the extracted directory structure
//      *
//      * @param string $extractPath
//      * @return array
//      */
//     private function processExtractedDirectory($extractPath)
//     {
//         $processedSkus = [];

//         // Get all directories in the extracted path (each directory represents a SKU)
//         $skuDirectories = File::directories($extractPath);

//         // Get the authenticated user and their role
//         $user = auth()->user();
//         $userRole = $user ? $user->getRoleNames()->first() : null;

//         // Define roles that are allowed to override the approval check
//         $allowedRoles = [
//             'Super Admin',
//             'Admin'
//         ];

//         foreach ($skuDirectories as $skuDir) {
//             // Get the original SKU from the directory name
//             $originalSku = basename($skuDir);

//             // Sanitize SKU for filename usage
//             $sanitizedSku = $this->sanitizeSku($originalSku);

//             // Find product with the original SKU using the existing Product model
//             $product = Product::where('sku', $originalSku)->first();

//             if ($product) {
//                 // Skip modification if approved AND user is not allowed
//                 if ($product->approved == 1 && !in_array($userRole, $allowedRoles)) {
//                     $processedSkus[] = [
//                         'sku' => $originalSku,
//                         'status' => 'already_approved',
//                         'errors' => ['This product is already approved and cannot be modified.'],
//                     ];
//                     continue;
//                 }

//                 // Proceed with uploading using sanitized SKU for filenames
//                 $result = $this->uploadProductImagesToS3($skuDir, $originalSku, $sanitizedSku);

//                 if (!empty($result['imageUrls'])) {
//                     $product->images = $result['imageUrls'];
//                     $product->save();

//                     $processedSkus[] = [
//                         'sku' => $originalSku,
//                         'status' => empty($result['errors']) ? 'success' : 'partial_success',
//                         'image_count' => count($result['imageUrls']),
//                         'errors' => $result['errors'],
//                         'sanitized_sku' => $sanitizedSku,
//                         'image_url' => $result['imageUrls']
//                     ];
//                 } else {
//                     $processedSkus[] = [
//                         'sku' => $originalSku,
//                         'status' => 'no_valid_images_found',
//                         'errors' => $result['errors'],
//                         'image_url' => $result['imageUrls']
//                     ];
//                 }
//             } else {
//                 $processedSkus[] = [
//                     'sku' => $originalSku,
//                     'status' => 'product_not_found'
//                 ];
//             }
//         }

//         return $processedSkus;
//     }

//     /**
//      * Upload images to S3 and return array of URLs and errors
//      *
//      * @param string $imagesDir
//      * @param string $originalSku Original SKU for error messages
//      * @param string $sanitizedSku Sanitized SKU for filename
//      * @return array
//      */
//     private function uploadProductImagesToS3($imagesDir, $originalSku, $sanitizedSku)
//     {
//         $storageEnv = env('STORAGE_ENV');
//         $s3Path = $storageEnv . '/products/images/';
//         $imageUrls = [];
//         $errors = [];
//         // Get all files in the SKU directory
//         $files = File::files($imagesDir);

//         // Filter for webp files only
//         $imageFiles = [];
//         foreach ($files as $file) {
//             $extension = strtolower($file->getExtension());

//             if ($extension !== 'webp') {
//                 $errors[] = "File {$file->getFilename()} in SKU folder {$originalSku} is not a webp image.";
//                 continue;
//             }

//             $imageFiles[] = $file;
//         }

//         if (empty($imageFiles)) {
//             $errors[] = "No webp image files found in SKU folder {$originalSku}.";
//             return [
//                 'imageUrls' => $imageUrls,
//                 'errors' => $errors
//             ];
//         }

//         // Upload each valid image to S3
//         foreach ($imageFiles as $index => $imageFile) {
//             try {
//                 // Verify image dimensions using PHP's built-in GD library
//                 $imageInfo = getimagesize($imageFile->getPathname());
//                 if ($imageInfo === false) {
//                     $errors[] = "Could not read image information for {$imageFile->getFilename()} in SKU folder {$originalSku}.";
//                     continue;
//                 }

//                 $width = $imageInfo[0];
//                 $height = $imageInfo[1];

//                 if ($width > 1000 || $height > 1000) {
//                     $errors[] = "Image {$imageFile->getFilename()} in SKU folder {$originalSku} has dimensions {$width}x{$height}, but must be exactly 1000x1000.";
//                     continue;
//                 }

//                 // Generate a unique filename using sanitized SKU
//                 $uniqueFileName = $sanitizedSku . '_' . ($index + 1) . '_' . Str::random(10) . '.webp';
//                 $s3FilePath = $s3Path . $uniqueFileName;

//                 // Create temporary file path for compressed image
//                 $tempCompressedPath = storage_path('app/temp/' . $uniqueFileName);

//                 // Ensure temp directory exists
//                 if (!File::exists(dirname($tempCompressedPath))) {
//                     File::makeDirectory(dirname($tempCompressedPath), 0755, true);
//                 }

//                 // Load the WebP image
//                 $srcImage = imagecreatefromwebp($imageFile->getPathname());

//                 if ($srcImage === false) {
//                     $errors[] = "Failed to load WebP image {$imageFile->getFilename()} for compression.";
//                     continue;
//                 }

//                 // Save compressed WebP to temporary path
//                 $compressionSuccess = imagewebp($srcImage, $tempCompressedPath, 80); 
//                 imagedestroy($srcImage);

//                 if (!$compressionSuccess) {
//                     $errors[] = "Failed to compress WebP image {$imageFile->getFilename()}.";
//                     continue;
//                 }

//                 // Upload compressed file to S3
//                 $fileStream = fopen($tempCompressedPath, 'r');
//                 if ($fileStream === false) {
//                     $errors[] = "Failed to open compressed image file for upload: {$imageFile->getFilename()}";
//                     continue;
//                 }

//                 Storage::disk('s3')->put($s3FilePath, $fileStream);
//                 fclose($fileStream);

//                 // Clean up temporary file
//                 if (File::exists($tempCompressedPath)) {
//                     File::delete($tempCompressedPath);
//                 }

//                 // Get the full URL from S3 storage
//                 $imageUrl = Storage::disk('s3')->url($s3FilePath);

//                 // Add the full URL to the image URLs array
//                 $imageUrls[] = $imageUrl;

//             } catch (\Exception $e) {
//                 $errors[] = "Error processing image {$imageFile->getFilename()} in SKU folder {$originalSku}: {$e->getMessage()}";

//                 // Clean up temporary file in case of exception
//                 if (isset($tempCompressedPath) && File::exists($tempCompressedPath)) {
//                     File::delete($tempCompressedPath);
//                 }
//             }
//         }

//         if (empty($imageUrls)) {
//             $errors[] = "No valid images found in SKU folder {$originalSku} that meet the required criteria (webp format, 1000x1000 dimensions).";
//         }

//         return [
//             'imageUrls' => $imageUrls,
//             'errors' => $errors
//         ];
//     }
//     private function uploadProductImagesToS3_old($imagesDir, $originalSku, $sanitizedSku)
//     {
//         $storageEnv = env('STORAGE_ENV');
//         $s3Path = $storageEnv . '/products/images/';
//         $imageUrls = [];
//         $errors = [];

//         // Get all files in the SKU directory
//         $files = File::files($imagesDir);

//         // Filter for webp files only
//         $imageFiles = [];
//         foreach ($files as $file) {
//             $extension = strtolower($file->getExtension());

//             if ($extension !== 'webp') {
//                 $errors[] = "File {$file->getFilename()} in SKU folder {$originalSku} is not a webp image.";
//                 //continue;
//             }

//             $imageFiles[] = $file;
//         }

//         if (empty($imageFiles)) {
//             $errors[] = "No webp image files found in SKU folder {$originalSku}.";
//             return [
//                 'imageUrls' => $imageUrls,
//                 'errors' => $errors
//             ];
//         }

//         // Upload each valid image to S3
//         foreach ($imageFiles as $index => $imageFile) {
//             try {
//                 // Verify image dimensions using PHP's built-in GD library
//                 $imageInfo = getimagesize($imageFile->getPathname());
//                 if ($imageInfo === false) {
//                     $errors[] = "Could not read image information for {$imageFile->getFilename()} in SKU folder {$originalSku}.";
//                     //continue;
//                 }

//                 $width = $imageInfo[0];
//                 $height = $imageInfo[1];

//                 if ($width !== 1000 || $height !== 1000) {
//                     $errors[] = "Image {$imageFile->getFilename()} in SKU folder {$originalSku} has dimensions {$width}x{$height}, but must be exactly 1000x1000.";
//                     //continue;
//                 }

//                 // Generate a unique filename using sanitized SKU
//                 // Include index to maintain order and avoid collisions
//                 $uniqueFileName = $sanitizedSku . '_' . ($index + 1) . '_' . Str::random(10) . '.webp';
//                 $s3FilePath = $s3Path . $uniqueFileName;


//                 // Open file and directly upload to S3
//                 $fileStream = fopen($imageFile->getPathname(), 'r');
//                 Storage::disk('s3')->put($s3FilePath, $fileStream);
//                 fclose($fileStream);

//                 // Get the full URL from S3 storage
//                 $imageUrl = Storage::disk('s3')->url($s3FilePath);

//                 // Add the full URL to the image URLs array
//                 $imageUrls[] = $imageUrl;


//             } catch (\Exception $e) {
//                 $errors[] = "Error processing image {$imageFile->getFilename()} in SKU folder {$originalSku}: {$e->getMessage()}";
//             }
//         }

//         if (empty($imageUrls)) {
//             $errors[] = "No valid images found in SKU folder {$originalSku} that meet the required criteria (webp format, 1000x1000 dimensions).";
//         }

//         return [
//             'imageUrls' => $imageUrls,
//             'errors' => $errors
//         ];
//     }
// }

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
     * Check if WebP support is available
     *
     * @return array
     */
    private function checkWebPSupport()
    {
        $support = [
            'gd_loaded' => extension_loaded('gd'),
            'webp_support' => false,
            'version_info' => '',
            'supported_formats' => []
        ];

        if ($support['gd_loaded']) {
            $gdInfo = gd_info();
            $support['version_info'] = $gdInfo['GD Version'] ?? 'Unknown';
            $support['webp_support'] = $gdInfo['WebP Support'] ?? false;
            
            // Check what formats are supported
            if (function_exists('imagetypes')) {
                $imageTypes = imagetypes();
                if ($imageTypes & IMG_WEBP) $support['supported_formats'][] = 'WebP';
                if ($imageTypes & IMG_JPEG) $support['supported_formats'][] = 'JPEG';
                if ($imageTypes & IMG_PNG) $support['supported_formats'][] = 'PNG';
                if ($imageTypes & IMG_GIF) $support['supported_formats'][] = 'GIF';
            }
        }

        return $support;
    }

    /**
     * Upload product images from zip file to S3 and update database
     * Enhanced version with better error reporting
     */

    /**
     * Upload product images from a zip file.
     *
     * @OA\Post(
     *     path="/api/product/upload-images",
     *     summary="Upload product images from zip file",
     *     description="Upload a ZIP file containing product images organized by SKU folders, extract and process them to S3, and update product records in the database. All images are automatically converted to WebP format, resized to 1000x1000, and compressed to under 100KB.",
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
     *                     description="ZIP file containing product images organized in folders by SKU. Supports JPG, PNG, WebP, GIF, BMP, TIFF formats. All will be converted to WebP 1000x1000 under 100KB."
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
        // Check WebP support first
        $webpSupport = $this->checkWebPSupport();
        if (!$webpSupport['webp_support']) {
            return response()->json([
                'success' => false,
                'error' => 'WebP support is not available on this server. Please enable WebP in GD extension.',
                'support_info' => $webpSupport
            ], 500);
        }

        // Log system capabilities
        error_log("WebP Support Check: " . json_encode($webpSupport));

        // Set memory and execution limits for image processing
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 1200); // 20 minutes for large batches

        // Validate the uploaded file
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:512000', // 500MB max size
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
     * Sanitize SKU for use in filenames and URLs
     * Removes or replaces characters that could cause issues in URLs
     *
     * @param string $sku
     * @return string
     */
    private function sanitizeSku($sku)
    {
        // Replace spaces with underscores and remove/replace problematic characters
        $sanitized = str_replace(' ', '_', $sku);

        // Remove or replace other special characters that could cause URL issues
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sanitized);

        // Remove multiple consecutive underscores
        $sanitized = preg_replace('/_+/', '_', $sanitized);

        // Trim underscores from start and end
        $sanitized = trim($sanitized, '_');

        // Ensure it's not empty after sanitization
        if (empty($sanitized)) {
            $sanitized = 'unknown_sku';
        }

        return $sanitized;
    }

    /**
     * Process the extracted directory structure
     *
     * @param string $extractPath
     * @return array
     */
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
            // Get the original SKU from the directory name
            $originalSku = basename($skuDir);

            // Sanitize SKU for filename usage
            $sanitizedSku = $this->sanitizeSku($originalSku);

            // Find product with the original SKU using the existing Product model
            $product = Product::where('sku', $originalSku)->first();

            if ($product) {
                // Skip modification if approved AND user is not allowed
                if ($product->approved == 1 && !in_array($userRole, $allowedRoles)) {
                    $processedSkus[] = [
                        'sku' => $originalSku,
                        'status' => 'already_approved',
                        'errors' => ['This product is already approved and cannot be modified.'],
                    ];
                    continue;
                }

                // Proceed with uploading using sanitized SKU for filenames
                $result = $this->uploadProductImagesToS3($skuDir, $originalSku, $sanitizedSku);

                if (!empty($result['imageUrls'])) {
                    $product->images = $result['imageUrls'];
                    $product->save();

                    $processedSkus[] = [
                        'sku' => $originalSku,
                        'status' => empty($result['errors']) ? 'success' : 'partial_success',
                        'image_count' => count($result['imageUrls']),
                        'errors' => $result['errors'],
                        'sanitized_sku' => $sanitizedSku,
                        'image_url' => $result['imageUrls']
                    ];
                } else {
                    $processedSkus[] = [
                        'sku' => $originalSku,
                        'status' => 'no_valid_images_found',
                        'errors' => $result['errors'],
                        'image_url' => $result['imageUrls']
                    ];
                }
            } else {
                $processedSkus[] = [
                    'sku' => $originalSku,
                    'status' => 'product_not_found'
                ];
            }
        }

        return $processedSkus;
    }

    /**
     * Upload images to S3 with compression and resizing - ALWAYS convert to WebP
     *
     * @param string $imagesDir
     * @param string $originalSku Original SKU for error messages
     * @param string $sanitizedSku Sanitized SKU for filename
     * @return array
     */
    private function uploadProductImagesToS3($imagesDir, $originalSku, $sanitizedSku)
    {
        $storageEnv = env('STORAGE_ENV');
        $s3Path = $storageEnv . '/products/images/';
        $imageUrls = [];
        $errors = [];
        
        // Get all files in the SKU directory
        $files = File::files($imagesDir);

        // Filter for image files (accept common formats, ALWAYS convert to webp)
        $imageFiles = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff'];
        
        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            
            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = "File {$file->getFilename()} in SKU folder {$originalSku} is not a supported image format. Supported: JPG, PNG, WebP, GIF, BMP, TIFF (all will be converted to WebP).";
                continue;
            }
            
            $imageFiles[] = $file;
        }

        if (empty($imageFiles)) {
            $errors[] = "No supported image files found in SKU folder {$originalSku}.";
            return [
                'imageUrls' => $imageUrls,
                'errors' => $errors
            ];
        }

        // Upload each valid image to S3
        foreach ($imageFiles as $index => $imageFile) {
            try {
                // Process and compress the image (ALWAYS converts to WebP)
                $compressedImagePath = $this->processAndCompressImage($imageFile->getPathname(), $originalSku);
                
                if ($compressedImagePath === false) {
                    $errors[] = "Failed to process image {$imageFile->getFilename()} in SKU folder {$originalSku}.";
                    continue;
                }

                // Check if compressed file size is under 100KB
                $fileSize = filesize($compressedImagePath);
                if ($fileSize > 100 * 1024) { // 100KB in bytes
                    $errors[] = "Image {$imageFile->getFilename()} in SKU folder {$originalSku} is still over 100KB after compression ({$fileSize} bytes).";
                    // Clean up temporary file
                    if (File::exists($compressedImagePath)) {
                        File::delete($compressedImagePath);
                    }
                    continue;
                }

                // Generate a unique filename (ALWAYS .webp extension)
                $uniqueFileName = $sanitizedSku . '_' . ($index + 1) . '_' . Str::random(10) . '.webp';
                $s3FilePath = $s3Path . $uniqueFileName;

                // Upload compressed file to S3
                $fileStream = fopen($compressedImagePath, 'r');
                if ($fileStream === false) {
                    $errors[] = "Failed to open compressed image file for upload: {$imageFile->getFilename()}";
                    continue;
                }

                Storage::disk('s3')->put($s3FilePath, $fileStream);
                fclose($fileStream);

                // Clean up temporary file
                if (File::exists($compressedImagePath)) {
                    File::delete($compressedImagePath);
                }

                // Get the full URL from S3 storage
                $imageUrl = Storage::disk('s3')->url($s3FilePath);
                $imageUrls[] = $imageUrl;

            } catch (\Exception $e) {
                $errors[] = "Error processing image {$imageFile->getFilename()} in SKU folder {$originalSku}: {$e->getMessage()}";
                
                // Clean up temporary file in case of exception
                if (isset($compressedImagePath) && File::exists($compressedImagePath)) {
                    File::delete($compressedImagePath);
                }
            }
        }

        if (empty($imageUrls)) {
            $errors[] = "No valid images found in SKU folder {$originalSku} that could be processed successfully.";
        }

        return [
            'imageUrls' => $imageUrls,
            'errors' => $errors
        ];
    }

    /**
     * Process and compress image to 1000x1000 WebP under 100KB
     * Optimized specifically for large file size images (1MB+)
     *
     * @param string $imagePath
     * @param string $sku
     * @return string|false Returns path to compressed WebP image or false on failure
     */
    private function processAndCompressImage($imagePath, $sku)
    {
        // Initialize variables for cleanup
        $sourceImage = null;
        $targetImage = null;
        $tempFilePath = null;
        $success = false;

        try {
            // Get current memory usage
            $memoryBefore = memory_get_usage(true);
            
            // Increase memory limit dynamically based on file size
            $originalMemoryLimit = ini_get('memory_limit');
            $fileSize = filesize($imagePath);
            
            // Calculate required memory (rough estimate: file_size * 8 for processing)
            $requiredMemory = max(1024, ($fileSize * 12) / (1024 * 1024)); // At least 1GB, or 12x file size
            ini_set('memory_limit', $requiredMemory . 'M');
            
            error_log("File size: {$fileSize} bytes (" . round($fileSize/1024/1024, 2) . "MB), Memory limit set to: {$requiredMemory}MB");

            // Verify the file exists and is readable
            if (!File::exists($imagePath) || !is_readable($imagePath)) {
                error_log("Image file not readable: {$imagePath}");
                return false;
            }

            // For large files (>500KB), log extra details
            if ($fileSize > 500 * 1024) {
                error_log("Processing LARGE image file: {$imagePath} - Size: " . round($fileSize/1024, 2) . "KB");
            }

            // Get image information with error handling
            $imageInfo = @getimagesize($imagePath);
            if ($imageInfo === false) {
                error_log("Could not get image size for: {$imagePath}");
                return false;
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];
            $mimeType = $imageInfo['mime'] ?? '';

            // Calculate uncompressed image size in memory
            $uncompressedSize = $originalWidth * $originalHeight * 4; // 4 bytes per pixel (RGBA)
            $uncompressedSizeMB = round($uncompressedSize / (1024 * 1024), 2);
            
            error_log("Processing: {$imagePath} - {$originalWidth}x{$originalHeight} - File: " . round($fileSize/1024, 2) . "KB - Uncompressed: {$uncompressedSizeMB}MB");

            // For very large uncompressed sizes, we need even more memory
            if ($uncompressedSize > 100 * 1024 * 1024) { // >100MB uncompressed
                $extraMemory = $requiredMemory + ($uncompressedSizeMB * 2);
                ini_set('memory_limit', $extraMemory . 'M');
                error_log("Large uncompressed size detected, increasing memory to: {$extraMemory}MB");
            }

            // Validate image dimensions
            if ($originalWidth <= 0 || $originalHeight <= 0 || $originalWidth > 10000 || $originalHeight > 10000) {
                error_log("Invalid or too large image dimensions: {$originalWidth}x{$originalHeight}");
                return false;
            }

            // Create image resource with optimized loading for large files
            $sourceImage = false;
            
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    // For large JPEGs, try to load with memory optimization
                    $sourceImage = @imagecreatefromjpeg($imagePath);
                    break;
                    
                case IMAGETYPE_PNG:
                    // PNG can be memory-intensive, load carefully
                    $sourceImage = @imagecreatefrompng($imagePath);
                    break;
                    
                case IMAGETYPE_WEBP:
                    error_log("Loading large WebP: {$imagePath}");
                    
                    // For large WebP files, try multiple methods
                    $sourceImage = @imagecreatefromwebp($imagePath);
                    
                    // If direct load fails, try chunked reading for very large files
                    if ($sourceImage === false && $fileSize > 1024 * 1024) { // >1MB
                        error_log("Direct WebP load failed for large file, trying alternative method");
                        
                        // Try loading in chunks if file is very large
                        $handle = @fopen($imagePath, 'rb');
                        if ($handle) {
                            $imageData = '';
                            while (!feof($handle)) {
                                $chunk = fread($handle, 8192); // Read 8KB chunks
                                if ($chunk === false) break;
                                $imageData .= $chunk;
                            }
                            fclose($handle);
                            
                            if (strlen($imageData) > 0) {
                                $sourceImage = @imagecreatefromstring($imageData);
                                unset($imageData); // Free memory immediately
                            }
                        }
                    }
                    break;
                    
                case IMAGETYPE_GIF:
                    $sourceImage = @imagecreatefromgif($imagePath);
                    break;
                    
                case IMAGETYPE_BMP:
                    if (function_exists('imagecreatefrombmp')) {
                        $sourceImage = @imagecreatefrombmp($imagePath);
                    } else {
                        // For large BMP files, load carefully
                        if ($fileSize < 10 * 1024 * 1024) { // Only if <10MB
                            $imageData = @file_get_contents($imagePath);
                            if ($imageData !== false) {
                                $sourceImage = @imagecreatefromstring($imageData);
                                unset($imageData);
                            }
                        }
                    }
                    break;
                    
                default:
                    error_log("Unsupported image type: {$imageType} for file: {$imagePath}");
                    return false;
            }

            if ($sourceImage === false || !is_resource($sourceImage)) {
                error_log("Failed to create image resource from: {$imagePath} (Type: {$imageType})");
                return false;
            }

            // Log memory usage after loading source image
            $memoryAfterLoad = memory_get_usage(true);
            $memoryUsed = round(($memoryAfterLoad - $memoryBefore) / (1024 * 1024), 2);
            error_log("Memory used for loading source image: {$memoryUsed}MB");

            // Verify the source image dimensions
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);
            
            if ($sourceWidth != $originalWidth || $sourceHeight != $originalHeight) {
                error_log("Warning: Source image dimensions mismatch. Expected: {$originalWidth}x{$originalHeight}, Got: {$sourceWidth}x{$sourceHeight}");
                // Continue processing with actual dimensions
                $originalWidth = $sourceWidth;
                $originalHeight = $sourceHeight;
            }

            // Create target canvas with proper settings for quality
            $targetWidth = 1000;
            $targetHeight = 1000;
            $targetImage = @imagecreatetruecolor($targetWidth, $targetHeight);

            if ($targetImage === false || !is_resource($targetImage)) {
                if (is_resource($sourceImage)) {
                    imagedestroy($sourceImage);
                }
                error_log("Failed to create target image canvas");
                return false;
            }

            // Optimize target image for quality
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);

            // Set white background
            $white = imagecolorallocate($targetImage, 255, 255, 255);
            imagefill($targetImage, 0, 0, $white);

            // Calculate dimensions to maintain aspect ratio
            $aspectRatio = $sourceWidth / $sourceHeight;
            
            if (abs($aspectRatio - 1.0) < 0.01) {
                // Nearly square image
                $newWidth = $targetWidth;
                $newHeight = $targetHeight;
                $offsetX = 0;
                $offsetY = 0;
            } elseif ($aspectRatio > 1) {
                // Landscape image
                $newWidth = $targetWidth;
                $newHeight = intval($targetWidth / $aspectRatio);
                $offsetX = 0;
                $offsetY = intval(($targetHeight - $newHeight) / 2);
            } else {
                // Portrait image
                $newHeight = $targetHeight;
                $newWidth = intval($targetHeight * $aspectRatio);
                $offsetX = intval(($targetWidth - $newWidth) / 2);
                $offsetY = 0;
            }

            error_log("Resizing from {$sourceWidth}x{$sourceHeight} to {$newWidth}x{$newHeight} at offset ({$offsetX},{$offsetY})");

            // Use high-quality resampling
            $resampleResult = @imagecopyresampled(
                $targetImage, $sourceImage,
                $offsetX, $offsetY, 0, 0,
                $newWidth, $newHeight, $sourceWidth, $sourceHeight
            );

            // Free source image memory immediately
            if (is_resource($sourceImage)) {
                imagedestroy($sourceImage);
                $sourceImage = null;
            }

            if (!$resampleResult) {
                if (is_resource($targetImage)) {
                    imagedestroy($targetImage);
                }
                error_log("Failed to resample image: {$imagePath}");
                return false;
            }

            // Log memory usage after resampling
            $memoryAfterResample = memory_get_usage(true);
            $memoryUsedTotal = round(($memoryAfterResample - $memoryBefore) / (1024 * 1024), 2);
            error_log("Total memory used after resampling: {$memoryUsedTotal}MB");

            // Create temporary directory
            $tempDir = storage_path('app/temp');
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }
            
            // Generate unique filename
            $tempFilePath = $tempDir . '/' . Str::random(15) . '_' . time() . '_compressed.webp';

            // For originally large files, start with lower quality to save processing time
            $initialQuality = ($fileSize > 1024 * 1024) ? 70 : 90; // Start at 70% for >1MB files
            $qualityLevels = [];
            
            // Generate quality levels starting from initial quality
            for ($q = $initialQuality; $q >= 5; $q -= 5) {
                $qualityLevels[] = $q;
            }
            
            error_log("Starting compression with quality levels: " . implode(', ', array_slice($qualityLevels, 0, 5)) . "...");

            $maxFileSize = 100 * 1024; // 100KB target
            $success = false;

            foreach ($qualityLevels as $quality) {
                // Clean up previous attempt
                if (File::exists($tempFilePath)) {
                    File::delete($tempFilePath);
                }

                error_log("Trying WebP compression at quality {$quality}%");
                
                // Attempt WebP compression
                $webpResult = @imagewebp($targetImage, $tempFilePath, $quality);
                
                if ($webpResult && File::exists($tempFilePath)) {
                    $currentFileSize = filesize($tempFilePath);
                    
                    if ($currentFileSize > 0 && $currentFileSize <= $maxFileSize) {
                        $success = true;
                        $finalSizeKB = round($currentFileSize / 1024, 2);
                        $compressionRatio = round(($fileSize / $currentFileSize), 1);
                        error_log("SUCCESS: Compressed {$fileSize} bytes -> {$currentFileSize} bytes ({$finalSizeKB}KB) at {$quality}% quality. Compression ratio: {$compressionRatio}:1");
                        break;
                    } else {
                        $sizeKB = round($currentFileSize / 1024, 2);
                        error_log("Quality {$quality}% = {$sizeKB}KB (target: ≤100KB)");
                    }
                } else {
                    error_log("Failed to create WebP at quality {$quality}%");
                }
            }

            // Clean up target image
            if (is_resource($targetImage)) {
                imagedestroy($targetImage);
                $targetImage = null;
            }

            if (!$success) {
                if (isset($tempFilePath) && File::exists($tempFilePath)) {
                    File::delete($tempFilePath);
                }
                error_log("FAILED: Could not compress {$imagePath} (original: " . round($fileSize/1024, 2) . "KB) to under 100KB");
                return false;
            }

            // Final validation
            if (!File::exists($tempFilePath) || filesize($tempFilePath) == 0) {
                error_log("Final WebP file is invalid: {$tempFilePath}");
                return false;
            }

            // Log final memory usage
            $memoryAfter = memory_get_usage(true);
            $memoryFinal = round(($memoryAfter - $memoryBefore) / (1024 * 1024), 2);
            error_log("Processing completed. Final memory usage: {$memoryFinal}MB");

            return $tempFilePath;

        } catch (\Exception $e) {
            error_log("Exception processing large image: " . $e->getMessage() . " for file: {$imagePath}");
            error_log("Stack trace: " . $e->getTraceAsString());
        } catch (\Error $e) {
            error_log("Fatal error processing large image: " . $e->getMessage() . " for file: {$imagePath}");
        } finally {
            // Cleanup resources
            if (isset($sourceImage) && is_resource($sourceImage)) {
                imagedestroy($sourceImage);
            }
            if (isset($targetImage) && is_resource($targetImage)) {
                imagedestroy($targetImage);
            }
            if (isset($tempFilePath) && File::exists($tempFilePath) && !$success) {
                File::delete($tempFilePath);
            }
            
            // Restore original memory limit
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
        }

        return false;
    }

    /**
     * Legacy method - kept for backward compatibility but not used
     * The new processAndCompressImage method handles all compression needs
     */
    private function uploadProductImagesToS3_old($imagesDir, $originalSku, $sanitizedSku)
    {
        // This method is deprecated - use uploadProductImagesToS3 instead
        // Kept for reference only
        return [
            'imageUrls' => [],
            'errors' => ['Legacy method - use new compression system']
        ];
    }
}