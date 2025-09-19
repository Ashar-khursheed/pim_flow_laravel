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
        // Set memory and execution limits for image processing
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600); // 10 minutes

        // Validate the uploaded file
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:204800', // 200MB max size
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
     * ALWAYS converts to WebP regardless of input format
     *
     * @param string $imagePath
     * @param string $sku
     * @return string|false Returns path to compressed WebP image or false on failure
     */
    private function processAndCompressImage($imagePath, $sku)
    {
        try {
            // Get image information
            $imageInfo = getimagesize($imagePath);
            if ($imageInfo === false) {
                return false;
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];

            // Create image resource based on file type (ALL will be converted to WebP)
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $sourceImage = imagecreatefromwebp($imagePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($imagePath);
                    break;
                case IMAGETYPE_BMP:
                    $sourceImage = imagecreatefrombmp($imagePath);
                    break;
                case IMAGETYPE_TIFF_II:
                case IMAGETYPE_TIFF_MM:
                    // Note: TIFF support may require ImageMagick
                    $sourceImage = imagecreatefromstring(file_get_contents($imagePath));
                    break;
                default:
                    return false;
            }

            if ($sourceImage === false) {
                return false;
            }

            // Create a new 1000x1000 canvas
            $targetWidth = 1000;
            $targetHeight = 1000;
            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

            // Set background to white (in case of transparency)
            $white = imagecolorallocate($targetImage, 255, 255, 255);
            imagefill($targetImage, 0, 0, $white);

            // Calculate dimensions to maintain aspect ratio
            $aspectRatio = $originalWidth / $originalHeight;
            
            if ($aspectRatio > 1) {
                // Landscape image
                $newWidth = $targetWidth;
                $newHeight = $targetWidth / $aspectRatio;
                $offsetX = 0;
                $offsetY = ($targetHeight - $newHeight) / 2;
            } else {
                // Portrait or square image
                $newHeight = $targetHeight;
                $newWidth = $targetHeight * $aspectRatio;
                $offsetX = ($targetWidth - $newWidth) / 2;
                $offsetY = 0;
            }

            // Resize and copy the image to the canvas with high quality
            imagecopyresampled(
                $targetImage, $sourceImage,
                $offsetX, $offsetY, 0, 0,
                $newWidth, $newHeight, $originalWidth, $originalHeight
            );

            // Clean up source image
            imagedestroy($sourceImage);

            // Create temporary directory if it doesn't exist
            $tempDir = storage_path('app/temp');
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }
            
            // ALWAYS save as WebP format regardless of input format
            $tempFilePath = $tempDir . '/' . Str::random(10) . '_compressed.webp';

            // Try different quality levels to get under 100KB (ALWAYS WebP output)
            $qualityLevels = [90, 85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35, 30, 25, 20];
            $maxFileSize = 100 * 1024; // 100KB
            $success = false;

            foreach ($qualityLevels as $quality) {
                // ALWAYS save as WebP with current quality
                if (imagewebp($targetImage, $tempFilePath, $quality)) {
                    $fileSize = filesize($tempFilePath);
                    
                    if ($fileSize <= $maxFileSize) {
                        $success = true;
                        break;
                    }
                }
            }

            // Clean up target image
            imagedestroy($targetImage);

            if (!$success) {
                // If we couldn't get under 100KB, delete temp file and return false
                if (File::exists($tempFilePath)) {
                    File::delete($tempFilePath);
                }
                return false;
            }

            return $tempFilePath;

        } catch (\Exception $e) {
            // Clean up resources in case of exception
            if (isset($sourceImage) && is_resource($sourceImage)) {
                imagedestroy($sourceImage);
            }
            if (isset($targetImage) && is_resource($targetImage)) {
                imagedestroy($targetImage);
            }
            if (isset($tempFilePath) && File::exists($tempFilePath)) {
                File::delete($tempFilePath);
            }
            
            return false;
        }
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