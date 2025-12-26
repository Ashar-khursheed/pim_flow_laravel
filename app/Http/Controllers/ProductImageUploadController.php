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
// 	/**
// 	 * Upload product images from zip file to S3 and update database
// 	 *
// 	 * @param Request $request
// 	 * @return \Illuminate\Http\JsonResponse
// 	 */

// 	/**
// 	 * Upload product images from a zip file.
// 	 *
// 	 * @OA\Post(
// 	 *     path="/api/product/upload-images",
// 	 *     summary="Upload product images from zip file",
// 	 *     description="Upload a ZIP file containing product images organized by SKU folders, extract and process them to S3, and update product records in the database. All images are automatically converted to WebP format, resized to 1000x1000, and compressed to under 100KB.",
// 	 *     tags={"Products"},
// 	 *     security={{"bearerAuth":{}}},
// 	 *     @OA\RequestBody(
// 	 *         required=true,
// 	 *         @OA\MediaType(
// 	 *             mediaType="multipart/form-data",
// 	 *             @OA\Schema(
// 	 *                 @OA\Property(
// 	 *                     property="zip_file",
// 	 *                     type="string",
// 	 *                     format="binary",
// 	 *                     description="ZIP file containing product images organized in folders by SKU. Supports JPG, PNG, WebP, GIF, BMP, TIFF formats. All will be converted to WebP 1000x1000 under 100KB."
// 	 *                 )
// 	 *             )
// 	 *         )
// 	 *     ),
// 	 *     @OA\Response(
// 	 *         response=200,
// 	 *         description="Images processed successfully",
// 	 *         @OA\JsonContent(
// 	 *             @OA\Property(property="success", type="boolean", example=true),
// 	 *             @OA\Property(property="message", type="string", example="Product images processed successfully"),
// 	 *             @OA\Property(
// 	 *                 property="processed_skus",
// 	 *                 type="array",
// 	 *                 @OA\Items(
// 	 *                     type="object",
// 	 *                     @OA\Property(property="sku", type="string", example="ABC123"),
// 	 *                     @OA\Property(property="status", type="string", example="success", description="success, no_images_found, product_not_found, validation_error"),
// 	 *                     @OA\Property(property="image_count", type="integer", example=5, description="Number of images processed for this SKU"),
// 	 *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"), description="Array of error messages for invalid images")
// 	 *                 )
// 	 *             )
// 	 *         )
// 	 *     ),
// 	 *     @OA\Response(
// 	 *         response=400,
// 	 *         description="Invalid input",
// 	 *         @OA\JsonContent(
// 	 *             @OA\Property(property="error", type="string", example="Unable to open the zip file")
// 	 *         )
// 	 *     ),
// 	 *     @OA\Response(
// 	 *         response=422,
// 	 *         description="Validation error",
// 	 *         @OA\JsonContent(
// 	 *             @OA\Property(property="message", type="string", example="The given data was invalid."),
// 	 *             @OA\Property(
// 	 *                 property="errors",
// 	 *                 type="object",
// 	 *                 @OA\Property(
// 	 *                     property="zip_file",
// 	 *                     type="array",
// 	 *                     @OA\Items(type="string", example="The zip file field is required.")
// 	 *                 )
// 	 *             )
// 	 *         )
// 	 *     ),
// 	 *     @OA\Response(
// 	 *         response=500,
// 	 *         description="Server error",
// 	 *         @OA\JsonContent(
// 	 *             @OA\Property(property="success", type="boolean", example=false),
// 	 *             @OA\Property(property="error", type="string", example="Error message")
// 	 *         )
// 	 *     )
// 	 * )
// 	 */
// 	public function uploadProductImages(Request $request)
// 	{
// 		// Set memory and execution limits for image processing
// 		ini_set('memory_limit', '1024M');   // Increased from 512M for large images
// 		ini_set('max_execution_time', 1200); // Increased to 20 minutes

// 		// Validate the uploaded file
// 		$request->validate([
// 			'zip_file' => 'required|file|mimes:zip|max:204800', // 200MB max size
// 		]);

// 		// Create a temporary directory to extract the zip file
// 		$tempPath = storage_path('app/temp/' . Str::random(10));
// 		File::makeDirectory($tempPath, 0755, true);

// 		try {
// 			// Get the uploaded zip file
// 			$zipFile = $request->file('zip_file');
// 			$zipFilePath = $zipFile->path();

// 			// Extract the zip file
// 			$zip = new ZipArchive();
// 			if ($zip->open($zipFilePath) !== true) {
// 				return response()->json(['error' => 'Unable to open the zip file'], 400);
// 			}

// 			$zip->extractTo($tempPath);
// 			$zip->close();

// 			// Process the extracted directory
// 			$processedSkus = $this->processExtractedDirectory($tempPath);

// 			// Clean up the temporary directory
// 			File::deleteDirectory($tempPath);

// 			return response()->json([
// 				'success' => true,
// 				'message' => 'Product images processed successfully',
// 				'processed_skus' => $processedSkus
// 			]);
// 		} catch (\Exception $e) {
// 			// Clean up on error
// 			if (File::exists($tempPath)) {
// 				File::deleteDirectory($tempPath);
// 			}

// 			return response()->json([
// 				'success' => false,
// 				'error' => $e->getMessage()
// 			], 500);
// 		}
// 	}

// 	/**
// 	 * Sanitize SKU for use in filenames and URLs
// 	 * Removes or replaces characters that could cause issues in URLs
// 	 *
// 	 * @param string $sku
// 	 * @return string
// 	 */
// 	private function sanitizeSku($sku)
// 	{
// 		// Replace spaces with underscores and remove/replace problematic characters
// 		$sanitized = str_replace(' ', '_', $sku);

// 		// Remove or replace other special characters that could cause URL issues
// 		$sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sanitized);

// 		// Remove multiple consecutive underscores
// 		$sanitized = preg_replace('/_+/', '_', $sanitized);

// 		// Trim underscores from start and end
// 		$sanitized = trim($sanitized, '_');

// 		// Ensure it's not empty after sanitization
// 		if (empty($sanitized)) {
// 			$sanitized = 'unknown_sku';
// 		}

// 		return $sanitized;
// 	}

// 	/**
// 	 * Process the extracted directory structure
// 	 *
// 	 * @param string $extractPath
// 	 * @return array
// 	 */
// 	private function processExtractedDirectory($extractPath)
// 	{
// 		$processedSkus = [];

// 		// Get all directories in the extracted path (each directory represents a SKU)
// 		$skuDirectories = File::directories($extractPath);

// 		// Get the authenticated user and their role
// 		$user = auth()->user();
// 		$userRole = $user ? $user->getRoleNames()->first() : null;

// 		// Define roles that are allowed to override the approval check
// 		$allowedRoles = [
// 			'Super Admin',
// 			'Admin'
// 		];

// 		foreach ($skuDirectories as $skuDir) {
// 			// Get the original SKU from the directory name
// 			$originalSku = basename($skuDir);

// 			// Sanitize SKU for filename usage
// 			$sanitizedSku = $this->sanitizeSku($originalSku);

// 			// Find product with the original SKU using the existing Product model
// 			$product = Product::where('sku', $originalSku)->first();

// 			if ($product) {
// 				// Skip modification if approved AND user is not allowed
// 				if ($product->approved == 1 && !in_array($userRole, $allowedRoles)) {
// 					$processedSkus[] = [
// 						'sku' => $originalSku,
// 						'status' => 'already_approved',
// 						'errors' => ['This product is already approved and cannot be modified.'],
// 					];
// 					continue;
// 				}

// 				// Proceed with uploading using sanitized SKU for filenames
// 				$result = $this->uploadProductImagesToS3($skuDir, $originalSku, $sanitizedSku);

// 				if (!empty($result['imageUrls'])) {
// 					$product->images = $result['imageUrls'];
// 					$product->save();

// 					$processedSkus[] = [
// 						'sku' => $originalSku,
// 						'status' => empty($result['errors']) ? 'success' : 'partial_success',
// 						'image_count' => count($result['imageUrls']),
// 						'errors' => $result['errors'],
// 						'sanitized_sku' => $sanitizedSku,
// 						'image_url' => $result['imageUrls']
// 					];
// 				} else {
// 					$processedSkus[] = [
// 						'sku' => $originalSku,
// 						'status' => 'no_valid_images_found',
// 						'errors' => $result['errors'],
// 						'image_url' => $result['imageUrls']
// 					];
// 				}
// 			} else {
// 				$processedSkus[] = [
// 					'sku' => $originalSku,
// 					'status' => 'product_not_found'
// 				];
// 			}
// 		}

// 		return $processedSkus;
// 	}

// 	/**
// 	 * Upload images to S3 with compression and resizing - ALWAYS convert to WebP
// 	 *
// 	 * @param string $imagesDir
// 	 * @param string $originalSku Original SKU for error messages
// 	 * @param string $sanitizedSku Sanitized SKU for filename
// 	 * @return array
// 	 */
// 	private function uploadProductImagesToS3($imagesDir, $originalSku, $sanitizedSku)
// 	{
// 		$storageEnv = env('STORAGE_ENV');
// 		$s3Path = $storageEnv . '/products/images/';
// 		$imageUrls = [];
// 		$errors = [];

// 		// Get all files in the SKU directory
// 		$files = File::files($imagesDir);

// 		// Filter for image files (accept common formats, ALWAYS convert to webp)
// 		$imageFiles = [];
// 		$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff'];

// 		foreach ($files as $file) {
// 			$extension = strtolower($file->getExtension());

// 			if (!in_array($extension, $allowedExtensions)) {
// 				$errors[] = "File {$file->getFilename()} in SKU folder {$originalSku} is not a supported image format. Supported: JPG, PNG, WebP, GIF, BMP, TIFF (all will be converted to WebP).";
// 				continue;
// 			}

// 			$imageFiles[] = $file;
// 		}

// 		if (empty($imageFiles)) {
// 			$errors[] = "No supported image files found in SKU folder {$originalSku}.";
// 			return [
// 				'imageUrls' => $imageUrls,
// 				'errors' => $errors
// 			];
// 		}

// 		// Upload each valid image to S3
// 		foreach ($imageFiles as $index => $imageFile) {
// 			try {
// 				// Process and compress the image (ALWAYS converts to WebP)
// 				$compressedImagePath = $this->processAndCompressImage($imageFile->getPathname(), $originalSku);

// 				if ($compressedImagePath === false) {
// 					$errors[] = "Failed to process image {$imageFile->getFilename()} in SKU folder {$originalSku}.";
// 					continue;
// 				}

// 				// Check if compressed file size is under 100KB
// 				$fileSize = filesize($compressedImagePath);
// 				if ($fileSize > 100 * 1024) { // 100KB in bytes
// 					$errors[] = "Image {$imageFile->getFilename()} in SKU folder {$originalSku} is still over 100KB after compression ({$fileSize} bytes).";
// 					// Clean up temporary file
// 					if (File::exists($compressedImagePath)) {
// 						File::delete($compressedImagePath);
// 					}
// 					continue;
// 				}

// 				// Generate a unique filename (ALWAYS .webp extension)
// 				$uniqueFileName = $sanitizedSku . '_' . ($index + 1) . '_' . Str::random(10) . '.webp';
// 				$s3FilePath = $s3Path . $uniqueFileName;

// 				// Upload compressed file to S3
// 				$fileStream = fopen($compressedImagePath, 'r');
// 				if ($fileStream === false) {
// 					$errors[] = "Failed to open compressed image file for upload: {$imageFile->getFilename()}";
// 					continue;
// 				}

// 				Storage::disk('s3')->put($s3FilePath, $fileStream);
// 				fclose($fileStream);

// 				// Clean up temporary file
// 				if (File::exists($compressedImagePath)) {
// 					File::delete($compressedImagePath);
// 				}

// 				// Get the full URL from S3 storage
// 				$imageUrl = Storage::disk('s3')->url($s3FilePath);
// 				$imageUrls[] = $imageUrl;

// 			} catch (\Exception $e) {
// 				$errors[] = "Error processing image {$imageFile->getFilename()} in SKU folder {$originalSku}: {$e->getMessage()}";

// 				// Clean up temporary file in case of exception
// 				if (isset($compressedImagePath) && File::exists($compressedImagePath)) {
// 					File::delete($compressedImagePath);
// 				}
// 			}
// 		}

// 		if (empty($imageUrls)) {
// 			$errors[] = "No valid images found in SKU folder {$originalSku} that could be processed successfully.";
// 		}

// 		return [
// 			'imageUrls' => $imageUrls,
// 			'errors' => $errors
// 		];
// 	}

// 	/**
// 	 * Enhanced image processing with aggressive compression for large files
// 	 * ALWAYS converts to WebP regardless of input format and maintains 1000x1000 size
// 	 *
// 	 * @param string $imagePath
// 	 * @param string $sku
// 	 * @return string|false Returns path to compressed WebP image or false on failure
// 	 */
// 	private function processAndCompressImage($imagePath, $sku)
// 	{
// 		try {
// 			// Increase memory limit for large images
// 			$originalMemoryLimit = ini_get('memory_limit');
// 			ini_set('memory_limit', '1024M'); // Increase to 1GB for large image processing

// 			// Verify the file exists and is readable
// 			if (!File::exists($imagePath) || !is_readable($imagePath)) {
// 				error_log("Image file not readable: {$imagePath}");
// 				return false;
// 			}

// 			// Get file size to determine processing strategy
// 			$fileSize = filesize($imagePath);

// 			// Get image information
// 			$imageInfo = getimagesize($imagePath);
// 			if ($imageInfo === false) {
// 				error_log("Could not get image size for: {$imagePath}");
// 				return false;
// 			}

// 			$originalWidth = $imageInfo[0];
// 			$originalHeight = $imageInfo[1];
// 			$imageType = $imageInfo[2];

// 			// Log image details for debugging
// 			error_log("Processing image: {$imagePath} - {$originalWidth}x{$originalHeight} - Size: {$fileSize} bytes - Type: {$imageType}");

// 			// Create image resource based on file type
// 			$sourceImage = $this->createImageResource($imagePath, $imageType);
// 			if ($sourceImage === false) {
// 				error_log("Failed to create image resource from: {$imagePath}");
// 				return false;
// 			}

// 			// Always create 1000x1000 canvas for consistent product grid
// 			$targetWidth = 1000;
// 			$targetHeight = 1000;

// 			// Create target canvas
// 			$targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
// 			if ($targetImage === false) {
// 				imagedestroy($sourceImage);
// 				error_log("Failed to create target image canvas");
// 				return false;
// 			}

// 			// Set background to white (in case of transparency)
// 			$white = imagecolorallocate($targetImage, 255, 255, 255);
// 			imagefill($targetImage, 0, 0, $white);

// 			// Calculate dimensions to maintain aspect ratio
// 			$resizeData = $this->calculateResizeDimensions(
// 				$originalWidth,
// 				$originalHeight,
// 				$targetWidth,
// 				$targetHeight
// 			);

// 			// Resize with high quality resampling
// 			imagecopyresampled(
// 				$targetImage, $sourceImage,
// 				$resizeData['offsetX'], $resizeData['offsetY'], 0, 0,
// 				$resizeData['newWidth'], $resizeData['newHeight'],
// 				$originalWidth, $originalHeight
// 			);

// 			// Clean up source image
// 			imagedestroy($sourceImage);

// 			// Create temporary directory if it doesn't exist
// 			$tempDir = storage_path('app/temp');
// 			if (!File::exists($tempDir)) {
// 				File::makeDirectory($tempDir, 0755, true);
// 			}

// 			// Generate temp file path
// 			$tempFilePath = $tempDir . '/' . Str::random(10) . '_compressed.webp';

// 			// Compress with adaptive quality based on file size
// 			$success = $this->compressImageAdaptively($targetImage, $tempFilePath, $fileSize);

// 			// Clean up target image
// 			imagedestroy($targetImage);

// 			// Restore original memory limit
// 			ini_set('memory_limit', $originalMemoryLimit);

// 			if (!$success) {
// 				if (File::exists($tempFilePath)) {
// 					File::delete($tempFilePath);
// 				}
// 				error_log("Failed to compress image under 100KB: {$imagePath}");
// 				return false;
// 			}

// 			$finalSize = filesize($tempFilePath);
// 			error_log("Successfully compressed {$imagePath} to {$finalSize} bytes");

// 			return $tempFilePath;

// 		} catch (\Exception $e) {
// 			error_log("Exception in processAndCompressImage: " . $e->getMessage());

// 			// Clean up resources
// 			if (isset($sourceImage) && is_resource($sourceImage)) {
// 				imagedestroy($sourceImage);
// 			}
// 			if (isset($targetImage) && is_resource($targetImage)) {
// 				imagedestroy($targetImage);
// 			}
// 			if (isset($tempFilePath) && File::exists($tempFilePath)) {
// 				File::delete($tempFilePath);
// 			}

// 			// Restore memory limit
// 			if (isset($originalMemoryLimit)) {
// 				ini_set('memory_limit', $originalMemoryLimit);
// 			}

// 			return false;
// 		}
// 	}

// 	/**
// 	 * Create image resource from file based on type
// 	 */
// 	private function createImageResource($imagePath, $imageType)
// 	{
// 		switch ($imageType) {
// 			case IMAGETYPE_JPEG:
// 			return @imagecreatefromjpeg($imagePath);
// 			case IMAGETYPE_PNG:
// 			return @imagecreatefrompng($imagePath);
// 			case IMAGETYPE_WEBP:
// 			return @imagecreatefromwebp($imagePath);
// 			case IMAGETYPE_GIF:
// 			return @imagecreatefromgif($imagePath);
// 			case IMAGETYPE_BMP:
// 			if (function_exists('imagecreatefrombmp')) {
// 				return @imagecreatefrombmp($imagePath);
// 			} else {
// 				return @imagecreatefromstring(file_get_contents($imagePath));
// 			}
// 			case IMAGETYPE_TIFF_II:
// 			case IMAGETYPE_TIFF_MM:
// 			return @imagecreatefromstring(file_get_contents($imagePath));
// 			default:
// 			return false;
// 		}
// 	}

// 	/**
// 	 * Calculate resize dimensions maintaining aspect ratio for 1000x1000 canvas
// 	 */
// 	private function calculateResizeDimensions($originalWidth, $originalHeight, $targetWidth, $targetHeight)
// 	{
// 		$aspectRatio = $originalWidth / $originalHeight;

// 		if ($aspectRatio > 1) {
// 			// Landscape image - fit to width
// 			$newWidth = $targetWidth;
// 			$newHeight = intval($targetWidth / $aspectRatio);
// 			$offsetX = 0;
// 			$offsetY = intval(($targetHeight - $newHeight) / 2);
// 		} elseif ($aspectRatio < 1) {
// 			// Portrait image - fit to height
// 			$newHeight = $targetHeight;
// 			$newWidth = intval($targetHeight * $aspectRatio);
// 			$offsetX = intval(($targetWidth - $newWidth) / 2);
// 			$offsetY = 0;
// 		} else {
// 			// Square image - fit to canvas
// 			$newWidth = $targetWidth;
// 			$newHeight = $targetHeight;
// 			$offsetX = 0;
// 			$offsetY = 0;
// 		}

// 		return [
// 			'newWidth' => $newWidth,
// 			'newHeight' => $newHeight,
// 			'offsetX' => $offsetX,
// 			'offsetY' => $offsetY
// 		];
// 	}

// 	/**
// 	 * Compress image with advanced techniques for 1000x1000 WebP under 100KB
// 	 */
// 	private function compressImageAdaptively($targetImage, $tempFilePath, $originalFileSize)
// 	{
// 		$maxFileSize = 100 * 1024; // 100KB target

// 		// For large source files, we need to be very aggressive with compression
// 		// since we're maintaining 1000x1000 resolution
// 		if ($originalFileSize > 1024 * 1024) { // > 1MB - be very aggressive
// 			$qualityLevels = [45, 40, 35, 30, 25, 20, 18, 15, 12, 10, 8, 6, 5, 3, 1];
// 		} elseif ($originalFileSize > 700 * 1024) { // > 700KB - be aggressive
// 			$qualityLevels = [55, 50, 45, 40, 35, 30, 25, 22, 20, 18, 15, 12, 10, 8, 5];
// 		} else { // Smaller files - normal quality range
// 			$qualityLevels = [90, 85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35, 30, 25, 20];
// 		}

// 		foreach ($qualityLevels as $quality) {
// 			if (imagewebp($targetImage, $tempFilePath, $quality)) {
// 				$fileSize = filesize($tempFilePath);

// 				error_log("Trying quality {$quality}: resulted in {$fileSize} bytes (target: {$maxFileSize})");

// 				if ($fileSize <= $maxFileSize) {
// 					return true; // Success!
// 				}
// 			} else {
// 				error_log("Failed to save WebP with quality {$quality}");
// 			}
// 		}

// 		// If still not compressed enough, try additional optimization techniques
// 		return $this->tryAdvancedCompression($targetImage, $tempFilePath, $maxFileSize);
// 	}

// 	/**
// 	 * Advanced compression techniques for stubborn large images
// 	 * Applies additional optimization while maintaining 1000x1000 size
// 	 */
// 	private function tryAdvancedCompression($targetImage, $tempFilePath, $maxFileSize)
// 	{
// 		try {
// 			// Try extremely low quality levels with additional processing
// 			$extremeQualityLevels = [3, 2, 1];

// 			foreach ($extremeQualityLevels as $quality) {
// 				// Create a copy for additional processing
// 				$optimizedImage = imagecreatetruecolor(1000, 1000);

// 				// Set white background
// 				$white = imagecolorallocate($optimizedImage, 255, 255, 255);
// 				imagefill($optimizedImage, 0, 0, $white);

// 				// Copy the image
// 				imagecopy($optimizedImage, $targetImage, 0, 0, 0, 0, 1000, 1000);

// 				// Apply slight blur to reduce file size (helps with compression)
// 				imagefilter($optimizedImage, IMG_FILTER_GAUSSIAN_BLUR);

// 				// Try saving with this extremely low quality
// 				if (imagewebp($optimizedImage, $tempFilePath, $quality)) {
// 					$fileSize = filesize($tempFilePath);
// 					error_log("Advanced compression - Quality {$quality}: {$fileSize} bytes");

// 					if ($fileSize <= $maxFileSize) {
// 						imagedestroy($optimizedImage);
// 						return true;
// 					}
// 				}

// 				imagedestroy($optimizedImage);
// 			}

// 			// Last resort: Try reducing color palette while maintaining size
// 			return $this->tryColorReduction($targetImage, $tempFilePath, $maxFileSize);

// 		} catch (\Exception $e) {
// 			error_log("Advanced compression failed: " . $e->getMessage());
// 			return false;
// 		}
// 	}

// 	/**
// 	 * Final attempt: Reduce color palette for extreme compression
// 	 */
// 	private function tryColorReduction($targetImage, $tempFilePath, $maxFileSize)
// 	{
// 		try {
// 			// Create palette-based image for extreme compression
// 			$paletteLevels = [64, 32, 16, 8]; // Number of colors in palette

// 			foreach ($paletteLevels as $colors) {
// 				// Create palette version
// 				$paletteImage = imagecreatetruecolor(1000, 1000);

// 				// Set white background
// 				$white = imagecolorallocate($paletteImage, 255, 255, 255);
// 				imagefill($paletteImage, 0, 0, $white);

// 				// Copy original
// 				imagecopy($paletteImage, $targetImage, 0, 0, 0, 0, 1000, 1000);

// 				// Convert to palette to reduce colors
// 				imagetruecolortopalette($paletteImage, false, $colors);

// 				// Convert back to truecolor for WebP saving
// 				$finalImage = imagecreatetruecolor(1000, 1000);
// 				imagecopy($finalImage, $paletteImage, 0, 0, 0, 0, 1000, 1000);

// 				// Try saving with very low quality
// 				if (imagewebp($finalImage, $tempFilePath, 1)) {
// 					$fileSize = filesize($tempFilePath);
// 					error_log("Color reduction ({$colors} colors): {$fileSize} bytes");

// 					if ($fileSize <= $maxFileSize) {
// 						imagedestroy($paletteImage);
// 						imagedestroy($finalImage);
// 						return true;
// 					}
// 				}

// 				imagedestroy($paletteImage);
// 				imagedestroy($finalImage);
// 			}

// 			error_log("All compression attempts failed - image cannot be compressed to under 100KB while maintaining 1000x1000");
// 			return false;

// 		} catch (\Exception $e) {
// 			error_log("Color reduction failed: " . $e->getMessage());
// 			return false;
// 		}
// 	}

// 	/**
// 	 * Legacy method - kept for backward compatibility but not used
// 	 * The new processAndCompressImage method handles all compression needs
// 	 */
// 	private function uploadProductImagesToS3_old($imagesDir, $originalSku, $sanitizedSku)
// 	{
// 		// This method is deprecated - use uploadProductImagesToS3 instead
// 		// Kept for reference only
// 		return [
// 			'imageUrls' => [],
// 			'errors' => ['Legacy method - use new compression system']
// 		];
// 	}
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
    public function uploadProductImages(Request $request)
    {
        ini_set('memory_limit', '2048M');   // Increased to 2GB
        ini_set('max_execution_time', 1800); // 30 minutes
        
        $request->validate([
            'zip_file' => 'required|file|mimes:zip|max:204800',
        ]);

        $tempPath = storage_path('app/temp/' . Str::random(10));
        File::makeDirectory($tempPath, 0755, true);

        try {
            $zipFile = $request->file('zip_file');
            $zipFilePath = $zipFile->path();

            $zip = new ZipArchive();
            if ($zip->open($zipFilePath) !== true) {
                return response()->json(['error' => 'Unable to open the zip file'], 400);
            }

            $zip->extractTo($tempPath);
            $zip->close();

            $processedSkus = $this->processExtractedDirectory($tempPath);

            File::deleteDirectory($tempPath);

            return response()->json([
                'success' => true,
                'message' => 'Product images processed successfully',
                'processed_skus' => $processedSkus
            ]);
        } catch (\Exception $e) {
            if (File::exists($tempPath)) {
                File::deleteDirectory($tempPath);
            }

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function sanitizeSku($sku)
    {
        $sanitized = str_replace(' ', '_', $sku);
        $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sanitized);
        $sanitized = preg_replace('/_+/', '_', $sanitized);
        $sanitized = trim($sanitized, '_');

        if (empty($sanitized)) {
            $sanitized = 'unknown_sku';
        }

        return $sanitized;
    }

    // private function processExtractedDirectory($extractPath)
    // {
    //     $processedSkus = [];
    //     $skuDirectories = File::directories($extractPath);

    //     $user = auth()->user();
    //     $userRole = $user ? $user->getRoleNames()->first() : null;

    //     $allowedRoles = ['Super Admin', 'Admin'];

    //     foreach ($skuDirectories as $skuDir) {
    //         // Force garbage collection between SKUs
    //         gc_collect_cycles();
            
    //         $originalSku = basename($skuDir);
    //         $sanitizedSku = $this->sanitizeSku($originalSku);

    //         $product = Product::where('sku', $originalSku)->first();

    //         if ($product) {
    //             if ($product->approved == 1 && !in_array($userRole, $allowedRoles)) {
    //                 $processedSkus[] = [
    //                     'sku' => $originalSku,
    //                     'status' => 'already_approved',
    //                     'errors' => ['This product is already approved and cannot be modified.'],
    //                 ];
    //                 continue;
    //             }

    //             $result = $this->uploadProductImagesToS3($skuDir, $originalSku, $sanitizedSku);

    //             if (!empty($result['imageUrls'])) {
    //                 $product->images = $result['imageUrls'];
    //                  if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
    //                 $jsonEncoded = json_encode($result['imageUrls']);
    //                 $product->translateOrNew('en')->images_tr = $jsonEncoded;
    //             }
    //                 $product->save();

    //                 $processedSkus[] = [
    //                     'sku' => $originalSku,
    //                     'status' => empty($result['errors']) ? 'success' : 'partial_success',
    //                     'image_count' => count($result['imageUrls']),
    //                     'errors' => $result['errors'],
    //                     'sanitized_sku' => $sanitizedSku,
    //                     'image_url' => $result['imageUrls']
    //                 ];
    //             } else {
    //                 $processedSkus[] = [
    //                     'sku' => $originalSku,
    //                     'status' => 'no_valid_images_found',
    //                     'errors' => $result['errors'],
    //                     'image_url' => $result['imageUrls']
    //                 ];
    //             }
    //         } else {
    //             $processedSkus[] = [
    //                 'sku' => $originalSku,
    //                 'status' => 'product_not_found'
    //             ];
    //         }
    //     }

    //     return $processedSkus;
    // }

    private function processExtractedDirectory($extractPath)
    {
        $processedSkus = [];
        $skuDirectories = File::directories($extractPath);

        $user = auth()->user();
        $userRole = $user ? $user->getRoleNames()->first() : null;

        $allowedRoles = ['Super Admin', 'Admin'];

        foreach ($skuDirectories as $skuDir) {
            // Force garbage collection between SKUs
            gc_collect_cycles();
            
            $originalSku = basename($skuDir);
            $sanitizedSku = $this->sanitizeSku($originalSku);

            $product = Product::where('sku', $originalSku)->first();

            if ($product) {
                if ($product->approved == 1 && !in_array($userRole, $allowedRoles)) {
                    $processedSkus[] = [
                        'sku' => $originalSku,
                        'status' => 'already_approved',
                        'errors' => ['This product is already approved and cannot be modified.'],
                    ];
                    continue;
                }

                $result = $this->uploadProductImagesToS3($skuDir, $originalSku, $sanitizedSku);

                if (!empty($result['imageUrls'])) {
                    $product->images = $result['imageUrls'];
                    
                    // Add translation for UAE, UAE_T, and SA locales
                    if (in_array(config('app.website'), ['UAE', 'UAE_T', 'SA'])) {
                        $jsonEncoded = json_encode($result['imageUrls']);
                        $product->translateOrNew('en')->images_tr = $jsonEncoded;
                    }
                    
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
    private function uploadProductImagesToS3($imagesDir, $originalSku, $sanitizedSku)
    {
        $storageEnv = env('STORAGE_ENV');
        $s3Path = $storageEnv . '/products/images/';
        $imageUrls = [];
        $errors = [];

        $files = File::files($imagesDir);
        $imageFiles = [];
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'tiff'];

        foreach ($files as $file) {
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, $allowedExtensions)) {
                $errors[] = "File {$file->getFilename()} is not a supported image format.";
                continue;
            }
            $imageFiles[] = $file;
        }

        if (empty($imageFiles)) {
            $errors[] = "No supported image files found in SKU folder {$originalSku}.";
            return ['imageUrls' => $imageUrls, 'errors' => $errors];
        }

        // Process each image individually with full cleanup
        foreach ($imageFiles as $index => $imageFile) {
            $compressedImagePath = null;
            $fileStream = null;

            try {
                // Force garbage collection before each image
                gc_collect_cycles();
                clearstatcache();
                
                $originalFileName = $imageFile->getFilename();
                error_log("Processing image " . ($index + 1) . "/" . count($imageFiles) . ": {$originalFileName}");

                $compressedImagePath = $this->processAndCompressImage(
                    $imageFile->getPathname(), 
                    $originalSku
                );

                if ($compressedImagePath === false) {
                    $errors[] = "Failed to process image {$originalFileName}. Check server logs for details.";
                    continue;
                }

                if (!File::exists($compressedImagePath) || !is_readable($compressedImagePath)) {
                    $errors[] = "Compressed file not accessible for {$originalFileName}";
                    continue;
                }

                $fileSize = filesize($compressedImagePath);
                if ($fileSize > 100 * 1024) {
                    $errors[] = "Image {$originalFileName} could not be compressed under 100KB ({$fileSize} bytes).";
                    File::delete($compressedImagePath);
                    continue;
                }

                $uniqueFileName = $sanitizedSku . '_' . ($index + 1) . '_' . Str::random(10) . '.webp';
                $s3FilePath = $s3Path . $uniqueFileName;

                $fileStream = fopen($compressedImagePath, 'r');
                if ($fileStream === false) {
                    $errors[] = "Failed to open compressed file for upload: {$originalFileName}";
                    File::delete($compressedImagePath);
                    continue;
                }

                $uploadSuccess = Storage::disk('s3')->put($s3FilePath, $fileStream);
                
                // Close stream immediately
                if (is_resource($fileStream)) {
                    fclose($fileStream);
                    $fileStream = null;
                }

                if (!$uploadSuccess) {
                    $errors[] = "Failed to upload {$originalFileName} to S3.";
                    File::delete($compressedImagePath);
                    continue;
                }

                // Delete temp file immediately
                File::delete($compressedImagePath);
                $compressedImagePath = null;

                $imageUrl = Storage::disk('s3')->url($s3FilePath);
                $imageUrls[] = $imageUrl;

                error_log("Successfully uploaded: {$originalFileName} ({$fileSize} bytes)");

            } catch (\Exception $e) {
                $errorMsg = "Error processing {$imageFile->getFilename()}: {$e->getMessage()}";
                error_log($errorMsg);
                $errors[] = $errorMsg;

                // Cleanup on exception
                if (is_resource($fileStream)) {
                    @fclose($fileStream);
                }
                if ($compressedImagePath && File::exists($compressedImagePath)) {
                    @File::delete($compressedImagePath);
                }
            }

            // Force cleanup after each image
            gc_collect_cycles();
        }

        if (empty($imageUrls)) {
            $errors[] = "No images could be processed successfully in SKU folder {$originalSku}.";
        }

        return ['imageUrls' => $imageUrls, 'errors' => $errors];
    }

    private function processAndCompressImage($imagePath, $sku)
    {
        $sourceImage = null;
        $targetImage = null;
        $tempFilePath = null;
        $originalMemoryLimit = null;

        try {
            $originalMemoryLimit = ini_get('memory_limit');
            ini_set('memory_limit', '2048M');

            if (!File::exists($imagePath) || !is_readable($imagePath)) {
                error_log("Image file not readable: {$imagePath}");
                return false;
            }

            $fileSize = filesize($imagePath);
            $imageInfo = @getimagesize($imagePath);
            
            if ($imageInfo === false) {
                error_log("Could not get image info for: {$imagePath}");
                return false;
            }

            $originalWidth = $imageInfo[0];
            $originalHeight = $imageInfo[1];
            $imageType = $imageInfo[2];

            error_log("Processing: {$originalWidth}x{$originalHeight}, " . round($fileSize/1024) . "KB");

            $sourceImage = $this->createImageResource($imagePath, $imageType);
            if ($sourceImage === false) {
                error_log("Failed to create image resource");
                return false;
            }

            $targetWidth = 1000;
            $targetHeight = 1000;
            $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);
            
            if ($targetImage === false) {
                if ($sourceImage) imagedestroy($sourceImage);
                error_log("Failed to create target canvas");
                return false;
            }

            $white = imagecolorallocate($targetImage, 255, 255, 255);
            imagefill($targetImage, 0, 0, $white);

            $resizeData = $this->calculateResizeDimensions(
                $originalWidth, $originalHeight, $targetWidth, $targetHeight
            );

            $resizeSuccess = imagecopyresampled(
                $targetImage, $sourceImage,
                $resizeData['offsetX'], $resizeData['offsetY'], 0, 0,
                $resizeData['newWidth'], $resizeData['newHeight'],
                $originalWidth, $originalHeight
            );

            // Destroy source immediately
            imagedestroy($sourceImage);
            $sourceImage = null;

            if (!$resizeSuccess) {
                error_log("Failed to resize image");
                imagedestroy($targetImage);
                return false;
            }

            $tempDir = storage_path('app/temp');
            if (!File::exists($tempDir)) {
                File::makeDirectory($tempDir, 0755, true);
            }

            $tempFilePath = $tempDir . '/' . Str::random(10) . '_compressed.webp';

            $success = $this->compressImageAdaptively($targetImage, $tempFilePath, $fileSize);

            // Destroy target immediately
            imagedestroy($targetImage);
            $targetImage = null;

            if ($originalMemoryLimit) {
                ini_set('memory_limit', $originalMemoryLimit);
            }

            if (!$success) {
                if (File::exists($tempFilePath)) {
                    File::delete($tempFilePath);
                }
                error_log("Failed to compress image under 100KB");
                return false;
            }

            $finalSize = filesize($tempFilePath);
            error_log("Successfully compressed to {$finalSize} bytes");

            return $tempFilePath;

        } catch (\Exception $e) {
            error_log("Exception in processAndCompressImage: " . $e->getMessage());

            // Cleanup all resources
            if (isset($sourceImage) && is_resource($sourceImage)) {
                @imagedestroy($sourceImage);
            }
            if (isset($targetImage) && is_resource($targetImage)) {
                @imagedestroy($targetImage);
            }
            if (isset($tempFilePath) && File::exists($tempFilePath)) {
                @File::delete($tempFilePath);
            }
            if ($originalMemoryLimit) {
                @ini_set('memory_limit', $originalMemoryLimit);
            }

            return false;
        }
    }

    private function createImageResource($imagePath, $imageType)
    {
        // Add error suppression and better error handling
        set_error_handler(function() {});
        
        $image = false;
        
        try {
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $image = imagecreatefromjpeg($imagePath);
                    break;
                case IMAGETYPE_PNG:
                    $image = imagecreatefrompng($imagePath);
                    break;
                case IMAGETYPE_WEBP:
                    $image = imagecreatefromwebp($imagePath);
                    break;
                case IMAGETYPE_GIF:
                    $image = imagecreatefromgif($imagePath);
                    break;
                case IMAGETYPE_BMP:
                    if (function_exists('imagecreatefrombmp')) {
                        $image = imagecreatefrombmp($imagePath);
                    } else {
                        $image = imagecreatefromstring(file_get_contents($imagePath));
                    }
                    break;
                case IMAGETYPE_TIFF_II:
                case IMAGETYPE_TIFF_MM:
                    $image = imagecreatefromstring(file_get_contents($imagePath));
                    break;
                default:
                    error_log("Unsupported image type: {$imageType}");
                    break;
            }
        } catch (\Exception $e) {
            error_log("Error creating image resource: " . $e->getMessage());
        }
        
        restore_error_handler();
        
        return $image;
    }

    private function calculateResizeDimensions($originalWidth, $originalHeight, $targetWidth, $targetHeight)
    {
        $aspectRatio = $originalWidth / $originalHeight;

        if ($aspectRatio > 1) {
            $newWidth = $targetWidth;
            $newHeight = intval($targetWidth / $aspectRatio);
            $offsetX = 0;
            $offsetY = intval(($targetHeight - $newHeight) / 2);
        } elseif ($aspectRatio < 1) {
            $newHeight = $targetHeight;
            $newWidth = intval($targetHeight * $aspectRatio);
            $offsetX = intval(($targetWidth - $newWidth) / 2);
            $offsetY = 0;
        } else {
            $newWidth = $targetWidth;
            $newHeight = $targetHeight;
            $offsetX = 0;
            $offsetY = 0;
        }

        return [
            'newWidth' => $newWidth,
            'newHeight' => $newHeight,
            'offsetX' => $offsetX,
            'offsetY' => $offsetY
        ];
    }

    private function compressImageAdaptively($targetImage, $tempFilePath, $originalFileSize)
    {
        $maxFileSize = 100 * 1024;

        // More aggressive quality levels for all images
        if ($originalFileSize > 2 * 1024 * 1024) { // > 2MB
            $qualityLevels = [40, 35, 30, 25, 20, 15, 12, 10, 8, 6, 5, 3, 2, 1];
        } elseif ($originalFileSize > 1024 * 1024) { // > 1MB
            $qualityLevels = [50, 45, 40, 35, 30, 25, 20, 15, 12, 10, 8, 6, 5, 3, 1];
        } elseif ($originalFileSize > 700 * 1024) { // > 700KB
            $qualityLevels = [60, 55, 50, 45, 40, 35, 30, 25, 20, 15, 12, 10, 8, 5, 3];
        } else {
            $qualityLevels = [85, 80, 75, 70, 65, 60, 55, 50, 45, 40, 35, 30, 25, 20, 15];
        }

        foreach ($qualityLevels as $quality) {
            if (@imagewebp($targetImage, $tempFilePath, $quality)) {
                clearstatcache();
                $fileSize = filesize($tempFilePath);

                error_log("Quality {$quality}: {$fileSize} bytes");

                if ($fileSize <= $maxFileSize) {
                    return true;
                }
            }
        }

        return $this->tryAdvancedCompression($targetImage, $tempFilePath, $maxFileSize);
    }

    private function tryAdvancedCompression($targetImage, $tempFilePath, $maxFileSize)
    {
        try {
            $extremeQualityLevels = [3, 2, 1];

            foreach ($extremeQualityLevels as $quality) {
                $optimizedImage = imagecreatetruecolor(1000, 1000);
                if ($optimizedImage === false) continue;

                $white = imagecolorallocate($optimizedImage, 255, 255, 255);
                imagefill($optimizedImage, 0, 0, $white);
                imagecopy($optimizedImage, $targetImage, 0, 0, 0, 0, 1000, 1000);
                
                // Apply blur for better compression
                imagefilter($optimizedImage, IMG_FILTER_GAUSSIAN_BLUR);

                if (@imagewebp($optimizedImage, $tempFilePath, $quality)) {
                    clearstatcache();
                    $fileSize = filesize($tempFilePath);
                    error_log("Advanced compression Q{$quality}: {$fileSize} bytes");

                    if ($fileSize <= $maxFileSize) {
                        imagedestroy($optimizedImage);
                        return true;
                    }
                }

                imagedestroy($optimizedImage);
            }

            return $this->tryColorReduction($targetImage, $tempFilePath, $maxFileSize);

        } catch (\Exception $e) {
            error_log("Advanced compression failed: " . $e->getMessage());
            return false;
        }
    }

    private function tryColorReduction($targetImage, $tempFilePath, $maxFileSize)
    {
        try {
            $paletteLevels = [64, 32, 16, 8];

            foreach ($paletteLevels as $colors) {
                $paletteImage = imagecreatetruecolor(1000, 1000);
                if ($paletteImage === false) continue;

                $white = imagecolorallocate($paletteImage, 255, 255, 255);
                imagefill($paletteImage, 0, 0, $white);
                imagecopy($paletteImage, $targetImage, 0, 0, 0, 0, 1000, 1000);
                
                imagetruecolortopalette($paletteImage, false, $colors);

                $finalImage = imagecreatetruecolor(1000, 1000);
                if ($finalImage === false) {
                    imagedestroy($paletteImage);
                    continue;
                }
                
                imagecopy($finalImage, $paletteImage, 0, 0, 0, 0, 1000, 1000);

                if (@imagewebp($finalImage, $tempFilePath, 1)) {
                    clearstatcache();
                    $fileSize = filesize($tempFilePath);
                    error_log("Color reduction ({$colors} colors): {$fileSize} bytes");

                    if ($fileSize <= $maxFileSize) {
                        imagedestroy($paletteImage);
                        imagedestroy($finalImage);
                        return true;
                    }
                }

                imagedestroy($paletteImage);
                imagedestroy($finalImage);
            }

            error_log("All compression attempts failed - cannot compress to under 100KB");
            return false;

        } catch (\Exception $e) {
            error_log("Color reduction failed: " . $e->getMessage());
            return false;
        }
    }
}