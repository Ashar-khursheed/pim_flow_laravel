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
     *     description="Upload a ZIP file containing product images organized by SKU folders, extract and process them to S3, and update product records in the database.",
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
     *                     description="ZIP file containing product images organized in folders by SKU"
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
     *                     @OA\Property(property="status", type="string", example="success", description="success, no_images_found, or product_not_found"),
     *                     @OA\Property(property="image_count", type="integer", example=5, description="Number of images processed for this SKU")
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
    private function processExtractedDirectory($extractPath)
    {
        $processedSkus = [];
        
        // Get all directories in the extracted path (each directory represents a SKU)
        $skuDirectories = File::directories($extractPath);
        
        foreach ($skuDirectories as $skuDir) {
            // Get the SKU from the directory name
            $sku = basename($skuDir);
            
            // Find product with this SKU using the existing Product model
            $product = Product::where('sku', $sku)->first();
            
            if ($product) {
                // Process images for this product
                $imageUrls = $this->uploadProductImagesToS3($skuDir, $sku);
                
                if (!empty($imageUrls)) {
                    // Update the product record with new image URLs
                    $product->images = $imageUrls;
                    $product->save();
                    
                    $processedSkus[] = [
                        'sku' => $sku,
                        'status' => 'success',
                        'image_count' => count($imageUrls) - 1 // Subtract 1 for the "string" element
                    ];
                } else {
                    $processedSkus[] = [
                        'sku' => $sku,
                        'status' => 'no_images_found'
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
     * Upload images to S3 and return array of URLs
     *
     * @param string $imagesDir
     * @param string $sku
     * @return array
     */
    private function uploadProductImagesToS3($imagesDir, $sku)
    {
        $s3Path = 'tanuj_local/products/images';
        $imageUrls = [];
        
        // Get all image files in the SKU directory
        $imageFiles = File::files($imagesDir);
        
        // Filter for image files only
        $imageFiles = array_filter($imageFiles, function($file) {
            $extension = strtolower($file->getExtension());
            return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
        });
        
        if (empty($imageFiles)) {
            return $imageUrls;
        }
        
        // Upload each image directly to S3
        foreach ($imageFiles as $imageFile) {
            // Generate a unique filename to prevent overwriting
            $uniqueFileName = Str::random(40) . '.' . $imageFile->getExtension();
            $s3FilePath = $s3Path . $uniqueFileName;
            
            // Open file and directly upload to S3
            $fileStream = fopen($imageFile->getPathname(), 'r');
            Storage::disk('s3')->put($s3FilePath, $fileStream);
            fclose($fileStream);
            
            // Get the full URL from S3 storage
            $imageUrl = Storage::disk('s3')->url($s3FilePath);
            
            // Add the full URL to the image URLs array
            $imageUrls[] = $imageUrl;
        }
        
        return $imageUrls;
    }
}